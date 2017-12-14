<?php
namespace Library\wechat\core\Aes;
use Library\wechat\core\Card2;
use Library\wechat\core\CardHandler;
use Library\wechat\core\Msg;
use Library\wechat\core\MsgConstant;
use Library\wechat\core\OpenAccessToken;
use Library\wechat\core\OpenExt;
use Library\wechat\core\ResponseInitiative;
use Library\wechat\core\ResponsePassive;
use Model\Wechat\open;
use Model\Wechat\WxMember;

/**
 * 处理请求
 * Created by Lane.
 * User: lane
 * Date: 13-12-19
 * Time: 下午11:04
 * Mail: lixuan868686@163.com
 * Website: http://www.lanecn.com
 */

class OpenWechatRequest{
    /**
     * @descrpition 分发请求
     * @param $request
     * @return array|string
     */
    public static function switchType(&$request){
        $data = array();
        switch ($request['msgtype']) {
            //事件
            case 'event':
                $request['event'] = strtolower($request['event']);
                switch ($request['event']) {
                    //关注
                    case 'subscribe':
                        //二维码关注
                        if(isset($request['eventkey']) && isset($request['ticket'])){
                            $data = self::eventQrsceneSubscribe($request);
                            //普通关注
                        }else{
                            $data = self::eventSubscribe($request);
                        }
                        break;
                    //扫描二维码
                    case 'scan':
                        $data = self::eventScan($request);
                        break;
                    //地理位置
                    case 'location':
                        $data = self::eventLocation($request);
                        break;
                    //自定义菜单 - 点击菜单拉取消息时的事件推送
                    case 'click':
                        $data = self::eventClick($request);
                        break;
                    //自定义菜单 - 点击菜单跳转链接时的事件推送
                    case 'view':
                        $data = self::eventView($request);
                        break;
                    //自定义菜单 - 扫码推事件的事件推送
                    case 'scancode_push':
                        $data = self::eventScancodePush($request);
                        break;
                    //自定义菜单 - 扫码推事件且弹出“消息接收中”提示框的事件推送
                    case 'scancode_waitmsg':
                        $data = self::eventScancodeWaitMsg($request);
                        break;
                    //自定义菜单 - 弹出系统拍照发图的事件推送
                    case 'pic_sysphoto':
                        $data = self::eventPicSysPhoto($request);
                        break;
                    //自定义菜单 - 弹出拍照或者相册发图的事件推送
                    case 'pic_photo_or_album':
                        $data = self::eventPicPhotoOrAlbum($request);
                        break;
                    //自定义菜单 - 弹出微信相册发图器的事件推送
                    case 'pic_weixin':
                        $data = self::eventPicWeixin($request);
                        break;
                    //自定义菜单 - 弹出地理位置选择器的事件推送
                    case 'location_select':
                        $data = self::eventLocationSelect($request);
                        break;
                    //取消关注
                    case 'unsubscribe':
                        $data = self::eventUnsubscribe($request);
                        break;
                    //群发接口完成后推送的结果
                    case 'masssendjobfinish':
                        $data = self::eventMassSendJobFinish($request);
                        break;
                    //模板消息完成后推送的结果
                    case 'templatesendjobfinish':
                        $data = self::eventTemplateSendJobFinish($request);
                        break;
                    case 'user_get_card':
                        return Card2::userGetCard($request);
                        break;
                    case 'card_pass_check':
                        //卡券通过审核
                        CardHandler::setAppId(WECHAT_APPID);
                        CardHandler::send(array('card_id'=>$request['cardid']));
                        break;
                    case 'card_not_pass_check':
                        //卡券未通过审核
                        Card2::cardNotPassCheck($request);
                        break;
                    case 'user_consume_card':
                        //核销事件推送
                        Card2::userConsumeCard($request);
                        break;
                    default:
                        return Msg::returnErrMsg(MsgConstant::ERROR_UNKNOW_TYPE, '收到了未知类型的消息', $request);
                        break;
                }

                break;
            //文本
            case 'text':
                $data = self::text($request);
                break;
            //图像
            case 'image':
                $data = self::image($request);
                break;
            //语音
            case 'voice':
                $data = self::voice($request);
                break;
            //视频
            case 'video':
                $data = self::video($request);
                break;
            //位置
            case 'location':
                $data = self::location($request);
                break;
            //链接
            case 'link':
                $data = self::link($request);
                break;
            default:
                return ResponsePassive::text($request['fromusername'], $request['tousername'], '收到未知的消息，我不知道怎么处理');
                break;
        }
//        Ext::Log("data=" . $data);
        return $data;
    }


    /**
     * @descrpition 文本
     * @param $request
     * @return array
     */
    public static function text(&$request)
    {
        switch ($request['content']) {
            case 'openid':
                return ResponsePassive::text($request['fromusername'],
                    $request['tousername'], $request['fromusername']);
                break;
            case '微商城演示':
                $text = '您好，感谢您使用演示功能。点击[<a href="http://123624.12301.cc/wx/h5/index.html">演示</a>]即可查看';
                return ResponsePassive::text($request['fromusername'],
                    $request['tousername'], $text);
                break;
            case '002':
                $ret = OpenExt::GetScenic(OPEN_WECHAT_APPID, $request['fromusername'], false);
                return ResponsePassive::text($request['fromusername'],
                    $request['tousername'], $ret['msg']);
                break;
            case '003':
                $ret = OpenExt::GetScenic(OPEN_WECHAT_APPID, $request['fromusername'], true);
                return ResponsePassive::text($request['fromusername'],
                    $request['tousername'], $ret['msg']);
                break;
            case '004':
                return ResponsePassive::text($request['fromusername'],
                    $request['tousername'], 'www');
                break;
            case 'music':
                //发送客服消息
                $access_token = OpenAccessToken::getAccessToken(WECHAT_APPID);
                ResponseInitiative::text($request['fromusername'], 'aaa',$access_token);
                $xml = ResponsePassive::text($request['fromusername'],
                    $request['tousername'], 'www');
                file_put_contents( BASE_LOG_DIR . '/wx/wx_log.txt', WECHAT_APPID.$xml . PHP_EOL, FILE_APPEND);
                return $xml;
                break;
            case 'pay':
                return ResponsePassive::text($request['fromusername'],
                    $request['tousername'], 'http://www.12301.cc/shops/wepay/next.php');
                break;
            case 'appid':
                return ResponsePassive::text($request['fromusername'],
                    $request['tousername'], WECHAT_APPID);
                break;
            case 6013:
                $tuwenList[] = array('title'=>'排钟琴', 'description'=>' 排钟琴 澳大拉西亚  Australasia 1950年 澳大利亚 ',
                    'pic_url'=>'http://static.12301.cc/gly/img/fengqin/6013.jpg',
                    'url'=>'http://mp.weixin.qq.com/s?__biz=MzA4NDI0Mzg2MQ==&mid=404943681&idx=1&sn=15faaa3f9977d305b557df14ad591261#rd');
                //构建图文消息格式
                $itemList = array();
                foreach($tuwenList as $tuwen){
                    $itemList[] = \LaneWeChat\Core\ResponsePassive::newsItem($tuwen['title'], $tuwen['description'], $tuwen['pic_url'], $tuwen['url']);
                }
                return ResponsePassive::news($request['fromusername'],  $request['tousername'], $itemList);
                break;
            case 'devtest':
//                return ResponsePassive::text($request['fromusername'],
//                    $request['tousername'], 'http://517232.12301.cc/wx/game/share_pages/page_dev.html?id=225');
                $tuwenList[] = array('title'=>'标题1', 'description'=>'描述1', 'pic_url'=>'http://wx.12301.cc/public/static/quaImages/top_bg11.jpg', 'url'=>'http://www.baidu.com');
                //构建图文消息格式
                $itemList = array();
                foreach($tuwenList as $tuwen){
                    $itemList[] = \LaneWeChat\Core\ResponsePassive::newsItem($tuwen['title'], $tuwen['description'], $tuwen['pic_url'], $tuwen['url']);
                }
                return ResponsePassive::news($request['fromusername'],  $request['tousername'], $itemList);
                break;
            default:
                $resp = OpenExt::GetKeyWord(WECHAT_APPID, $request['content']);
                if (!$resp) {
                    $resp = OpenExt::GetKeyWord(WECHAT_APPID, 'DEFAULT');
                }
                //音乐回复
                if($resp['msg_type']==3) {
                    $rsp   = json_decode($resp['response']);
                    return ResponsePassive::music(
                        $request['fromusername'],
                        $request['tousername'],
                        $rsp->title,
                        $rsp->digest,
                        $rsp->MusicUrl,
                        $rsp->HQMusicUrl,
                        '');
                }
                elseif ($resp['msg_type']==2) {//图文回复
                    $item = self::response_format($resp['response']);
                    return ResponsePassive::news($request['fromusername'], $request['tousername'], $item);
                }
                elseif ($resp['msg_type']==1) {
                    return ResponsePassive::text($request['fromusername'], $request['tousername'], $resp['response']);
                }
                else {
                    if (in_array(WECHAT_APPID, OpenExt::CustomServiceConfig())) {
                        //TODO::多客服接口--票付通,先行
                        return ResponsePassive::forwardToCustomService($request['fromusername'], $request['tousername']);
                    }
                    return ResponsePassive::text($request['fromusername'], $request['tousername'], '有什么可以帮助您？');
                }
                break;
        }
    }

    /**
     * @descrpition 图像
     * @param $request
     * @return array
     */
    public static function image(&$request){
        return ResponsePassive::text($request['fromusername'], $request['tousername'], '您的图片已收到');
    }

    /**
     * @descrpition 语音
     * @param $request
     * @return array
     */
    public static function voice(&$request){
        if(!isset($request['recognition'])){
            $content = '收到语音';
            return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
        }else{
            $content = '收到语音识别消息，语音识别结果为：'.$request['recognition'];
            return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
        }
    }

    /**
     * @descrpition 视频
     * @param $request
     * @return array
     */
    public static function video(&$request){
        $content = '收到视频';
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    /**
     * @descrpition 地理
     * @param $request
     * @return array
     */
    public static function location(&$request){
        $content = '收到上报的地理位置' ;// . json_encode($request);
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    /**
     * @descrpition 链接
     * @param $request
     * @return array
     */
    public static function link(&$request){
        $content = '收到连接';
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    /**
     * 图文回复：IMAGE|标题|图片|摘要|地址
     *
     * @descrpition 关注
     * @param $request
     * @return array
     */
    public static function eventSubscribe(&$request){
        $content = '欢迎您关注我们的微信，将为您竭诚服务';
        $resp = OpenAccessToken::getModel()->GetKeyWord(WECHAT_APPID, 'EVENT_SUBSCRIBE');
        if (is_array($resp)) {
            if ($resp['msg_type']==2) {
                $item = array();
                $item = self::response_format($resp['response']);
                return ResponsePassive::news($request['fromusername'], $request['tousername'], $item);
            }
            elseif ($resp['msg_type']==1) {
                return ResponsePassive::text($request['fromusername'], $request['tousername'], $resp['response']);
            }
        }
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content );
    }

    /**
     * @descrpition 取消关注
     * @param $request
     * @return array
     */
    public static function eventUnsubscribe(&$request){
        $content = '为什么不理我了？';
        //删除之前绑定的数据
        OpenExt::RmBind($request['fromusername']);
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    /**
     * 粉丝关注微信分店
     *
     * @param $openid
     * @param $scan_id
     * @return bool|int
     */
    public static function WeFansBind($openid, $scan_id)
    {
        //已关注扫码和未关注扫码问题
        //未关注会出现qrscene_140
        if(strpos($scan_id,'_')!==false){
            list($_tmp, $scan_id) = explode('_',$scan_id);
        }
        //检测是否绑定了账号,对绑定账号的无效
        $sql = "SELECT id FROM uu_wx_member_pft WHERE fromusername=? LIMIT 1";
        $stmt = Db::Connect()->prepare($sql);
        $stmt->execute(array($openid));
        if ($stmt->fetchColumn()) {
            Ext::Log("openid={$openid}已经绑定",BASE_LOG_DIR . '/wx/WeFansBind.log');
            return 1;
        }

        $dbConf = include '/var/www/html/new/d/module/common/db.conf.php';
        Db::Conf($dbConf['remote_1']);
        $sql  = "SELECT id,shop_id FROM pft_wxshop_fans WHERE openid=? LIMIT 1";
        $stmt = Db::Connect()->prepare($sql);
        $stmt->execute(array($openid));
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ( $row ) {
            if ($scan_id == $row['shop_id']) {
                return false;
            }
            else {
                $sql = "UPDATE pft_wxshop_fans SET shop_id={$scan_id} WHERE id={$row['id']} LIMIT 1";
                return Db::Connect()->exec($sql);
            }
        }
        $sql = "INSERT INTO pft_wxshop_fans(shop_id,openid) VALUES({$scan_id},'{$openid}')";
        Ext::Log("$sql",BASE_LOG_DIR . '/wx/WeFansBind.log');
//        return $sql;
        return Db::Connect()->exec($sql);
    }

    /**
     * @descrpition 扫描二维码关注（未关注时）
     * @param $request
     * @return array
     */
    public static function eventQrsceneSubscribe(&$request){
        self::WeFansBind($request['fromusername'], $request['eventkey']);
        $content = '欢迎您关注我们的微信';
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    /**
     * @descrpition 扫描二维码（已关注时）
     * @param $request
     * @return array
     */
    public static function eventScan(&$request){
        self::WeFansBind($request['fromusername'], $request['eventkey']);
        $content = '欢迎您关注我们的微信' . $request['eventkey'];
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    /**
     * @descrpition 上报地理位置
     * @param $request
     * @return array
     */
    public static function eventLocation(&$request){
//        $content = '收到上报的地理位置';// . json_encode($request);
        $content = '';// . json_encode($request);
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    /**
     * @descrpition 自定义菜单 - 点击菜单拉取消息时的事件推送
     * @param $request
     * @return array
     */
    public static function eventClick(&$request){
        //获取该分类的信息
        $eventKey = $request['eventkey'];
        $event = strtolower($eventKey);
        if (method_exists(__CLASS__, 'matcherEvent'.$event)) {
            $content = call_user_func_array(array(__CLASS__, 'matcherEvent'.$event), array($request));
        }
        else {
            $resp = OpenExt::GetKeyWord(WECHAT_APPID, $eventKey);
            if (!$resp) {
                $resp = OpenExt::GetKeyWord(WECHAT_APPID, 'DEFAULT');
            }
            if ($resp['msg_type']==2) {
                $item = array();

                $item = self::response_format($resp['response']);
                return ResponsePassive::news($request['fromusername'], $request['tousername'], $item);
            }
            elseif ($resp['msg_type']==1) {
                return ResponsePassive::text($request['fromusername'], $request['tousername'], $resp['response']);
            }
            else {
                if (in_array(WECHAT_APPID, OpenExt::CustomServiceConfig())) {
                    //TODO::多客服接口--票付通,先行
                    return ResponsePassive::forwardToCustomService($request['fromusername'], $request['tousername']);
                }
                return ResponsePassive::text($request['fromusername'], $request['tousername'], '有什么可以帮助您？');
            }
        }

        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    private static function response_format($response)
    {
        $item = array();
        $resp_list     = json_decode($response);
        if (count($resp_list)>1) {
            foreach($resp_list as $rsp) {
                $item[] = ResponsePassive::newsItem(
                    $rsp->title,
                    $rsp->digest,
                    $rsp->img,
                    $rsp->source_url
                );
            }
        }
        else {
            $item[] = ResponsePassive::newsItem(
                $resp_list->title,
                $resp_list->digest,
                $resp_list->img,
                $resp_list->source_url
            );
        }
        return $item;
    }

    /**
     * 取消微信账号绑定
     *
     * @notice
     * @param $request
     * @return string
     */
    public static function matcherEventUnBind($request)
    {
        $model = new WxMember();
        $model->RmWxMember($request['fromusername']);
        unset($model);
        return "取消绑定成功。";
    }
    /**
     * @descrpition 自定义菜单 - 点击菜单跳转链接时的事件推送
     * @param $request
     * @return array
     */
    public static function eventView(&$request){
        //获取该分类的信息
        $eventKey = $request['eventkey'];
        $content = '收到跳转链接事件，您设置的key是' . $eventKey;
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    /**
     * @descrpition 自定义菜单 - 扫码推事件的事件推送
     * @param $request
     * @return array
     */
    public static function eventScancodePush(&$request){
        //获取该分类的信息
        $eventKey = $request['eventkey'];
        $content = '收到扫码推事件的事件，您设置的key是' . $eventKey;
        $content .= '。扫描信息：'.$request['scancodeinfo'];
        $content .= '。扫描类型(一般是qrcode)：'.$request['scantype'];
        $content .= '。扫描结果(二维码对应的字符串信息)：'.$request['scanresult'];
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    /**
     * @descrpition 自定义菜单 - 扫码推事件且弹出“消息接收中”提示框的事件推送
     * @param $request
     * @return array
     */
    public static function eventScancodeWaitMsg(&$request){
        //获取该分类的信息
        $eventKey = $request['eventkey'];
        $content = '收到扫码推事件且弹出“消息接收中”提示框的事件，您设置的key是' . $eventKey;
        $content .= '。扫描信息：'.$request['scancodeinfo'];
        $content .= '。扫描类型(一般是qrcode)：'.$request['scantype'];
        $content .= '。扫描结果(二维码对应的字符串信息)：'.$request['scanresult'];
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    /**
     * @descrpition 自定义菜单 - 弹出系统拍照发图的事件推送
     * @param $request
     * @return array
     */
    public static function eventPicSysPhoto(&$request){
        //获取该分类的信息
        $eventKey = $request['eventkey'];
        $content = '收到弹出系统拍照发图的事件，您设置的key是' . $eventKey;
        $content .= '。发送的图片信息：'.$request['sendpicsinfo'];
        $content .= '。发送的图片数量：'.$request['count'];
        $content .= '。图片列表：'.$request['piclist'];
        $content .= '。图片的MD5值，开发者若需要，可用于验证接收到图片：'.$request['picmd5sum'];
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    /**
     * @descrpition 自定义菜单 - 弹出拍照或者相册发图的事件推送
     * @param $request
     * @return array
     */
    public static function eventPicPhotoOrAlbum(&$request){
        //获取该分类的信息
        $eventKey = $request['eventkey'];
        $content = '收到弹出拍照或者相册发图的事件，您设置的key是' . $eventKey;
        $content .= '。发送的图片信息：'.$request['sendpicsinfo'];
        $content .= '。发送的图片数量：'.$request['count'];
        $content .= '。图片列表：'.$request['piclist'];
        $content .= '。图片的MD5值，开发者若需要，可用于验证接收到图片：'.$request['picmd5sum'];
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    /**
     * @descrpition 自定义菜单 - 弹出微信相册发图器的事件推送
     * @param $request
     * @return array
     */
    public static function eventPicWeixin(&$request){
        //获取该分类的信息
        $eventKey = $request['eventkey'];
        $content = '收到弹出微信相册发图器的事件，您设置的key是' . $eventKey;
        $content .= '。发送的图片信息：'.$request['sendpicsinfo'];
        $content .= '。发送的图片数量：'.$request['count'];
        $content .= '。图片列表：'.$request['piclist'];
        $content .= '。图片的MD5值，开发者若需要，可用于验证接收到图片：'.$request['picmd5sum'];
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    /**
     * @descrpition 自定义菜单 - 弹出地理位置选择器的事件推送
     * @param $request
     * @return array
     */
    public static function eventLocationSelect(&$request){
        //获取该分类的信息
        $eventKey = $request['eventkey'];
        $content = '收到点击跳转事件，您设置的key是' . $eventKey;
        $content .= '。发送的位置信息：'.$request['sendlocationinfo'];
        $content .= '。X坐标信息：'.$request['location_x'];
        $content .= '。Y坐标信息：'.$request['location_y'];
        $content .= '。精度(可理解为精度或者比例尺、越精细的话 scale越高)：'.$request['scale'];
        $content .= '。地理位置的字符串信息：'.$request['label'];
        $content .= '。朋友圈POI的名字，可能为空：'.$request['poiname'];
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    /**
     * 群发接口完成后推送的结果
     *
     * 本消息有公众号群发助手的微信号“mphelper”推送的消息
     * @param $request
     */
    public static function eventMassSendJobFinish(&$request){
        //发送状态，为“send success”或“send fail”或“err(num)”。但send success时，也有可能因用户拒收公众号的消息、系统错误等原因造成少量用户接收失败。err(num)是审核失败的具体原因，可能的情况如下：err(10001), //涉嫌广告 err(20001), //涉嫌政治 err(20004), //涉嫌社会 err(20002), //涉嫌色情 err(20006), //涉嫌违法犯罪 err(20008), //涉嫌欺诈 err(20013), //涉嫌版权 err(22000), //涉嫌互推(互相宣传) err(21000), //涉嫌其他
        $status = $request['status'];
        //计划发送的总粉丝数。group_id下粉丝数；或者openid_list中的粉丝数
        $totalCount = $request['totalcount'];
        //过滤（过滤是指特定地区、性别的过滤、用户设置拒收的过滤，用户接收已超4条的过滤）后，准备发送的粉丝数，原则上，FilterCount = SentCount + ErrorCount
        $filterCount = $request['filtercount'];
        //发送成功的粉丝数
        $sentCount = $request['sentcount'];
        //发送失败的粉丝数
        $errorCount = $request['errorcount'];
        $content = '发送完成，状态是'.$status.'。计划发送总粉丝数为'.$totalCount.'。发送成功'.$sentCount.'人，发送失败'.$errorCount.'人。';
        return ResponsePassive::text('oNbmEuDdAEWDS_a02HYFlzNYFUTg', $request['tousername'], $content);
    }

    /**
     * 群发接口完成后推送的结果
     *
     * 本消息有公众号群发助手的微信号“mphelper”推送的消息
     * @param $request
     */
    public static function eventTemplateSendJobFinish(&$request){
        //发送状态，成功success，用户拒收failed:user block，其他原因发送失败failed: system failed
        $status = $request['status'];
        if($status == 'success'){
            //发送成功
        }else if($status == 'failed:user block'){
            //因为用户拒收而发送失败
            Ext::Log("模板消息发送失败:因为用户拒收而发送失败:openid={$request['FromUserName']}", BASE_LOG_DIR . '/wx/tpl_msg/send_error.log');
        }else if($status == 'failed: system failed'){
            //其他原因发送失败
            Ext::Log("模板消息发送失败:其他原因发送失败:openid={$request['FromUserName']}", BASE_LOG_DIR . '/wx/tpl_msg/send_error.log');
        }
        return ;
    }


    public static function test(){
        // 第三方发送消息给公众平台
        $encodingAesKey = "abcdefghijklmnopqrstuvwxyz0123456789ABCDEFG";
        $token = "pamtest";
        $timeStamp = "1409304348";
        $nonce = "xxxxxx";
        $appId = "wxb11529c136998cb6";
        $text = "<xml><ToUserName><![CDATA[oia2Tj我是中文jewbmiOUlr6X-1crbLOvLw]]></ToUserName><FromUserName><![CDATA[gh_7f083739789a]]></FromUserName><CreateTime>1407743423</CreateTime><MsgType><![CDATA[video]]></MsgType><Video><MediaId><![CDATA[eYJ1MbwPRJtOvIEabaxHs7TX2D-HV71s79GUxqdUkjm6Gs2Ed1KF3ulAOA9H1xG0]]></MediaId><Title><![CDATA[testCallBackReplyVideo]]></Title><Description><![CDATA[testCallBackReplyVideo]]></Description></Video></xml>";


        $pc = new Aes\WXBizMsgCrypt($token, $encodingAesKey, $appId);
        $encryptMsg = '';
        $errCode = $pc->encryptMsg($text, $timeStamp, $nonce, $encryptMsg);
        if ($errCode == 0) {
            print("加密后: " . $encryptMsg . "\n");
        } else {
            print($errCode . "\n");
        }

        $xml_tree = new \DOMDocument();
        $xml_tree->loadXML($encryptMsg);
        $array_e = $xml_tree->getElementsByTagName('Encrypt');
        $array_s = $xml_tree->getElementsByTagName('MsgSignature');
        $encrypt = $array_e->item(0)->nodeValue;
        $msg_sign = $array_s->item(0)->nodeValue;

        $format = "<xml><ToUserName><![CDATA[toUser]]></ToUserName><Encrypt><![CDATA[%s]]></Encrypt></xml>";
        $from_xml = sprintf($format, $encrypt);

// 第三方收到公众号平台发送的消息
        $msg = '';
        $errCode = $pc->decryptMsg($msg_sign, $timeStamp, $nonce, $from_xml, $msg);
        if ($errCode == 0) {
            print("解密后: " . $msg . "\n");
        } else {
            print($errCode . "\n");
        }
    }
}
