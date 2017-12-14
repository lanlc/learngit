<?php
namespace Library\wechat\core;

function msubstr($str, $start=0, $length, $charset="utf-8", $suffix=false)  
{  
    if(function_exists("mb_substr")){  
        if($suffix)  
             return mb_substr($str, $start, $length, $charset)."...";  
        else 
             return mb_substr($str, $start, $length, $charset);  
    }  
    elseif(function_exists('iconv_substr')) {  
        if($suffix)  
             return iconv_substr($str,$start,$length,$charset)."...";  
        else 
             return iconv_substr($str,$start,$length,$charset);  
    }  
    $re['utf-8']   = "/[x01-x7f]|[xc2-xdf][x80-xbf]|[xe0-xef][x80-xbf]{2}|[xf0-xff][x80-xbf]{3}/";  
    $re['gb2312'] = "/[x01-x7f]|[xb0-xf7][xa0-xfe]/";  
    $re['gbk']    = "/[x01-x7f]|[x81-xfe][x40-xfe]/";  
    $re['big5']   = "/[x01-x7f]|[x81-xfe]([x40-x7e]|xa1-xfe])/";  
    preg_match_all($re[$charset], $str, $match);  
    $slice = join("",array_slice($match[0], $start, $length));  
    if($suffix) return $slice."…";  
    return $slice;
}

//微信卡卷发送验证码
class CardHandler {
    const API_KEY = 'RFGrfgY5CjVP8LcY';

    protected static $db = null;

    public static function setDb(\PDO $db) {
        self::$db = $db;
    }

    public static function setAppId($app_id) {
        Card2::setAppId($app_id);
    }

    /**
     * 创建一张卡卷,此时处于待审核状态
     * @param  array    $request  请求参数
     * @return string   msgcode
     */
    public static function create($request) {
        if (!(isset($request['open_id']) && isset($request['code']) && isset($request['begin_time']) && isset($request['end_time']) && $request['cash'])) {
            return 'PARAMS_MISS';
        }
        $base_info = array(
            'logo_url' => 'http://wx.12301.cc/public/images/pft_logo.jpg',
            'brand_name' => '票付通',
            'code_type' => 'CODE_TYPE_QRCODE',
            'title' =>  msubstr($request['ltitle'], 0, 9),
            'sub_title' => $request['ttitle'],
            'color' => 'Color010',
            'notice' => '刷二维码或输入二维码下方的凭证码',
            'service_phone' => '',
            'description' => $request['getaddr'],//'此票只能使用一次',
            'date_info' => array(
                'type' => 'DATE_TYPE_FIX_TIME_RANGE',
                'begin_timestamp' => $request['begin_time'],
                'end_timestamp' => $request['end_time']
            ),
            'sku' => array('quantity' => 0),
            'get_limit' => 1,
            'use_custom_code' => true,
            // 'get_custom_code_mode' => 'GET_CUSTOM_CODE_MODE_DEPOSIT',
            'bind_openid' => true
        );
        // $options = array('least_cost' => 0, 'reduce_cost' => $request['cash']);
        $options = array('ticket_class' => '门票');
        $result = Card2::create($base_info, $options, 'SCENIC_TICKET');
        self::cardLog($result, 0);
        if ($result['errcode'] == 0 && self::_addCardRecord($result['card_id'], $request)) {
            return 'SUCCESS';
        }
        
        return 'UNKNOWN';
    }

    /**
     * 保存卡卷信息,用于卡卷通过审核后的行为
     * @param  array    $request  请求参数
     * @return string   msgcode
     */
    private static function _addCardRecord($card_id, $request) {
        $stmt = self::$db->prepare('insert into pft_wechat_card
            (card_id, code, open_id, appid, salerid, ordernum, begin_time, end_time, cash, status, create_time, send_time)
            values
            (:card_id, :code, :open_id, :appid, :salerid, :ordernum, :begin_time, :end_time, :cash, :status, :create_time, :send_time)');
        return $stmt->execute(
            array(
                ':card_id' => $card_id,
                ':code' => $request['code'],
                ':open_id' => $request['open_id'],
                ':appid' => $request['app_id'],
                ':salerid' => $request['salerid'],
                ':ordernum' => $request['ordernum'],
                ':begin_time' => $request['begin_time'],
                ':end_time' => $request['end_time'],
                ':cash' => $request['cash'],
                ':status' => 0,
                ':create_time' => time(),
                ':send_time' => 0
            )
        );
    }

    /**
     * 发送卡卷给客户
     * @param  array    $request  请求参数
     * @return string   msgcode
     */
    public static function send($request) {
        if (!(isset($request['card_id']))) {
            return 'PARAMS_MISS';
        }
        $card_info = self::_getCardInfo($request['card_id']);
        //导入自定义code
        // sleep(2);
        $loop = 3;
        while ($loop--) {
            sleep(2);
            $deposit_result = self::_deposit($request['card_id'], array($card_info['code']));
            if ($deposit_result) break;
        }
        if ($deposit_result == false) {
            return 'DEPOSIT_FAIL';
        }
        //导入code后修改库存
        $modify_result = self::_modifyStock($request['card_id']);
        if ($modify_result == false) {
            return 'MODIFY_STOCK_FAIL';
        }
        //发送给用户
        $send_result = self::_sendCard($card_info['open_id'], $card_info['card_id'], $card_info['code']);
        if ($send_result == false) {
            return 'SEND_CARD_FAIL';
        }
        self::_modifyStatus($card_info['id']);
        return 'SUCCESS';
    }

    /**
     * 获取一条卡卷信息
     * @param  string    $card_id  微信生成的卡卷id
     * @return array
     */
    private static function _getCardInfo($card_id) {
        $sql = "select * from pft_wechat_card where card_id = :card_id limit 1";
        $stmt = self::$db->prepare($sql);
        $stmt->execute(array(':card_id' => $card_id));
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * 导入自定义code
     * @param  string    $card_id  微信生成的卡卷id
     * @param  string    $code     需要导入的code(验证码)
     * @return array
     */
    private static function _deposit($card_id, array $code) {
        $result = Card2::deposit($card_id, $code);
        return $result['errcode'] == 0 ? true : false;
    }

    /**
     * 导入自定义code后,需要修改库存
     * @param  string    $card_id  微信生成的卡卷id
     * @return bool
     */
    private static function _modifyStock($card_id) {
        $result = Card2::modifyStock($card_id);
        return $result['errcode'] == 0 ? true : false;
    }

    /**
     * 发送卡卷动作
     * @param  string    $open_id  微信用户openid
     * @param  string    $card_id  微信生成的卡卷id
     * @param  string    $code     需要导入的code(验证码)
     * @return bool
     */
    private static function _sendCard($open_id, $card_id, $code) {
        $result = Card2::send($open_id, $card_id, $code);
        return $result['errcode'] == 0 ? true : false;
    }

    /**
     * 发送卡卷成功，修改卡卷状态
     * @param  intval  $id  卡卷id
     */
    private static function _modifyStatus($id) {
        $stmt = self::$db->prepare('update pft_wechat_card
            set status = 1, send_time = :time
            where id = :id
            limit 1');
        $stmt->execute(array('time' => time(), ':id' => $id));
    }

    /**
     * 核销卡卷
     * @param  array    $request  请求参数
     * @return string   msgcode
     */
    public static function consume($request) {
        if (!isset($request['ordernum']) || !isset($request['salerid'])) {
            return 'PARAMS_MISS';
        }
        $stmt = self::$db->prepare('select id,card_id,code,appid from pft_wechat_card where ordernum = :ordernum and status = 2 LIMIT 1');
        $stmt->execute(array(':ordernum' => $request['ordernum']));
        $card_info = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($card_info) {
            Card2::setAppId($card_info['appid']);
            $result = Card2::consume($card_info['card_id'], $card_info['code']);
            if ($result['errcode'] == 0) {
                $stmt = self::$db->prepare('update pft_wechat_card set status = 3 where id = :id limit 1');
                $stmt->execute(array(':id' => $card_info['id']));
                return 'SUCCESS';
            }
            return 'CONSUME_FAIL';
        }
        return 'CONSUME_FAIL';
    }

    /**
     * 返回消息
     * @param  string   $msgcode  消息标识
     * @return json
     */
    public static function response($msgcode) {
        switch ($msgcode) {
            case 'PARAMS_MISS':
                $response = array('status' => 0, 'msg' => '参数错误');
                break;
            case 'DEPOSIT_FAIL':
                $response = array('status' => 0, 'msg' => 'code导入失败');
                break;
            case 'MODIFY_STOCK_FAIL':
                $response = array('status' => 0, 'msg' => '库存修改失败');
                break;
            case 'SEND_CARD_FAIL':
                $response = array('status' => 0, 'msg' => '卡卷发送失败');
                break;
            case 'CONSUME_FAIL':
                $response = array('status' => 0, 'msg' => '卡卷核销失败');
                break;
            case 'ACCESS_DENY':
                $response = array('status' => 0, 'msg' => '非法访问');
                break;
            case "SUCCESS":
                $response = array('status' => 1, 'msg' => 'success');
                break;
            default:
                $response = array('status' => 0, 'msg' => '未知错误');
                break;
        }
        exit(json_encode($response));
    }

    public static function cardLog($request, $result_code) {
        $dir = BASE_LOG_DIR . '/wechat/card/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $log_file = $dir . date('Y-m-d') . '.txt';
        @file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\r\n" . json_encode($request) . "\r\n" . $result_code . "\r\n", FILE_APPEND);
    }

    public static function debug() {
        $stmt = self::$db->prepare("select * from pft_wechat_card");
        $stmt->execute();
        var_dump($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public static function __callStatic($method, $args) {
        $request = $args[0];
        if (method_exists(self, $method)) {
            return self::$method($request);
        }
        return false;
    }
}