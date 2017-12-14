<?php
namespace Library\wechat\core;

class Card2 {

    private static $app_id = null;

	// 卡券类型
    const TYPE_GENERAL_COUPON = 'GENERAL_COUPON';   // 通用券
    const TYPE_GROUPON        = 'GROUPON';          // 团购券
    const TYPE_DISCOUNT       = 'DISCOUNT';         // 折扣券
    const TYPE_GIFT           = 'GIFT';             // 礼品券
    const TYPE_CASH           = 'CASH';             // 代金券

    const API_CREATE          = 'https://api.weixin.qq.com/card/create';
    const API_DEPOSIT         = 'http://api.weixin.qq.com/card/code/deposit';
    const API_MODIFYSTOCK     = 'https://api.weixin.qq.com/card/modifystock';
    const API_GETHTML         = 'https://api.weixin.qq.com/card/mpnews/gethtml';
    const API_GETTICKET       = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=wx_card';
    const API_SENDCARD        = 'https://api.weixin.qq.com/cgi-bin/message/custom/send';
    const API_CONSUME         = 'https://api.weixin.qq.com/card/code/consume';

    public static function setAppId($app_id = null) {
        if ($app_id) {
            self::$app_id = $app_id;
        } else {
            self::$app_id = OpenExt::PFT_APP_ID;
        }
    }


    /**
     * 创建卡券
     * @param string $json_data
     * @return array
     */
	public static function create($base, $options, $type = 'CASH') {
		$key  = strtolower($type);
        $info = array_merge(array('base_info' => $base), $options);
        $params = array(
            'card' => array(
                'card_type' => $type,
                $key        => $info,
            ),
        );
        if (version_compare(PHP_VERSION, '5.4', '>')) {
            $params = json_encode($params, JSON_UNESCAPED_UNICODE);
        } else {
            $json = json_encode($params);
            $params = preg_replace("#\\\u([0-9a-f]{4})#ie", "iconv('UCS-2BE', 'UTF-8', pack('H4', '\\1'))", $json);
        }
        $queryUrl = self::API_CREATE . '?access_token=' . OpenAccessToken::getAccessToken(self::$app_id);
        return Curl::callWebServer($queryUrl, $params, 'POST', 1, 1, 1);
	}

    /**
     * 导入自定义code
     * @param string    $card_id 创建好的卡券id
     * @param string   $code    自定义code
     */
    public static function deposit($card_id, $code) {
        $params = json_encode(array(
            'card_id' => $card_id,
            'code' => $code
        ));
        $queryUrl = self::API_DEPOSIT . '?access_token=' . OpenAccessToken::getAccessToken(self::$app_id);
        return Curl::callWebServer($queryUrl, $params, 'POST', 1, 1, 1);
    }

    /**
     * 导入code后修改库存
     * @param  string   $card_id    创建好的卡券id
     * @param string   $increase   要增加的库存量
     * @param string   $reduce     要减少的库存量
     */
    public static function modifyStock($card_id, $increase = 1, $reduce = 0) {
        $params = json_encode(array(
            'card_id' => $card_id,
            'increase_stock_value' => $increase,
            'reduce_stock_value' => $reduce 
        ));
        $queryUrl = self::API_MODIFYSTOCK . '?access_token=' . OpenAccessToken::getAccessToken(self::$app_id);
        return Curl::callWebServer($queryUrl, $params, 'POST', 1, 1, 1);
    }


    // public static function gethtml($card_id) {
    //     $queryUrl = self::API_GETHTML . '?access_token=' . AccessToken::getAccessToken();
    //     $params = json_encode(array('card_id' => $card_id));
    //     return Curl::callWebServer($queryUrl, $params, 'POST', 1, 1, 1);
    // }

    /**
     * 获取api_ticket
     */
    private static function getTicket() {
        $ticket_file = '/var/www/html/wx/wechat/api_ticket/api_ticket';
        if (file_exists($ticket_file)) {
            $data = file_get_contents($ticket_file);
            if(!empty($data)){
                $ticket = json_decode($data, true);
                if(time() - $ticket['time'] < $ticket['expires_in']-100){
                    return $ticket['ticket'];
                }
            }
        }
        $queryUrl = self::API_GETTICKET . '&access_token=' . OpenAccessToken::getAccessToken(self::$app_id);
        $ticket = Curl::callWebServer($queryUrl);
        file_put_contents($ticket_file, json_encode(array('time' => time(), 'expires_in' => 3600, 'ticket' => $ticket['ticket'])));
        return $ticket['ticket'];
    }


    /**
     * 导入code后修改库存
     * @param string   $open_id   绑定的公众号
     * @param string   $card_id   卡券id
     * @param string   $code    卡券code
     */
    private static function getCardExt($open_id, $card_id, $code) {
        $time = (string)time();
        $params = array(
            'code' => $code,
            'openid' => $open_id,
            'timestamp' => $time,
            'nonce_str' => md5($time . $card_id . $code),
            'api_ticket' => self::getTicket(),
            'card_id' => $card_id
        );
        asort($params, SORT_STRING);
        $params['signature'] = sha1(implode('',$params));
        unset($params['api_ticket'], $params['card_id']);
        return json_encode($params);
    }

    /**
     * 将卡券发放给用户
     * @param string   $open_id   绑定的公众号
     * @param string   $card_id   卡券id
     * @param string   $code      卡券code
     */
    public static function send($open_id, $card_id, $code) {
        $params = json_encode(array(
            'touser' => $open_id,
            'msgtype' => 'wxcard',
            'wxcard' => array(
                'card_id' => $card_id,
                'card_ext' => self::getCardExt($open_id, $card_id, $code)
            ),
        ));
        $queryUrl = self::API_SENDCARD . '?access_token=' . OpenAccessToken::getAccessToken(self::$app_id);
        return Curl::callWebServer($queryUrl, $params, 'POST', 1, 1, 1);
    }

    /**
     * 核销卡券,必须在用户领取之后调用
     * @param string   $card_id   卡券id
     * @param string   $code      卡券code
     */
    public static function consume($card_id, $code) {
        $params = json_encode(array(
            'code' => $code,
            'card_id' => $card_id
        ));
        $queryUrl = self::API_CONSUME . '?access_token=' . OpenAccessToken::getAccessToken(self::$app_id);
        return Curl::callWebServer($queryUrl, $params, 'POST', 1, 1, 1);
    }

    public static function checkConsume($card_id, $code, $check_consume = true) {
        $params = json_encode(array(
            'code' => $code,
            'card_id' => $card_id,
            'check_consume' => $check_consume
        ));
        echo $queryUrl = 'https://api.weixin.qq.com/card/code/get?access_token=' . OpenAccessToken::getAccessToken(self::$app_id);
        return Curl::callWebServer($queryUrl, $params, 'POST', 1, 1, 1);
    }

    /**
     * 卡券通过审核
     *
     * @param $request
     * @return string
     */
    public static function userGetCard($request)
    {
        $stmt = \PFT\Db::Connect()->prepare('select id from pft_wechat_card'
                           .' where card_id = :card_id and status = 1 LIMIT 1');
        $stmt->execute(array(':card_id' => $request['cardid']));
        if ($card_id = $stmt->fetchColumn()) {
            $sql = "UPDATE pft_wechat_card SET status=2 WHERE id=$card_id LIMIT 1";
            \PFT\Db::Connect()->exec($sql);
        }
        return ResponsePassive::text($request['fromusername'],
            $request['tousername'], '感谢您的支持，票券领取成功');
    }

    public static function userConsumeCard($request)
    {
        file_put_contents('/var/www/html/wx/logs/user_consume_card.txt', date('Y-m-d H:i:s').':'.json_encode($request)."\n", FILE_APPEND);
    }
    public static function cardNotPassCheck($request)
    {
        file_put_contents('/var/www/html/wx/logs/card_not_pass_check.txt', date('Y-m-d H:i:s').':'.json_encode($request)."\n", FILE_APPEND);
    }
    // private static function _getAccessToken() {
    //     OpenAccessToken::getAccessTokenManual(OpenExt::PFT_APP_ID);
    // }


}