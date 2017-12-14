<?php
namespace Library\wechat\core;

/**
 * 处理请求
 * Created by Lane.
 * User: lane
 * Date: 13-12-19
 * Time: 下午11:04
 * Mail: lixuan868686@163.com
 * Website: http://www.lanecn.com
 */

class WechatRequest{
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
            case '001':
                return ResponsePassive::text($request['fromusername'],
                    $request['tousername'], $request['fromusername']);
                break;
            case '002':
                $ret = Ext::GetScenic(WECHAT_APPID, $request['fromusername'], false);
                return ResponsePassive::text($request['fromusername'],
                    $request['tousername'], $ret['msg']);
                break;
            case '003':
                $ret = Ext::GetScenic(WECHAT_APPID, $request['fromusername'], true);
                return ResponsePassive::text($request['fromusername'],
                    $request['tousername'], $ret['msg']);
                break;
            case 'pay':
                return ResponsePassive::text($request['fromusername'],
                    $request['tousername'], 'http://wx.12301.cc/html/pft_native.php');
                break;
            case 'devtest':
                $tuwenList[] = array('title'=>'标题1', 'description'=>'描述1', 'pic_url'=>'http://wx.12301.cc/public/static/quaImages/top_bg11.jpg', 'url'=>'http://www.baidu.com');
//            $tuwenList[] = array('title'=>'标题2', 'description'=>'描述2', 'pic_url'=>'http://wx.12301.cc/public/static/quaImages/top_bg22.jpg', 'url'=>'http://www.12301.cc');
//构建图文消息格式
                $itemList = array();
                foreach($tuwenList as $tuwen){
                    $itemList[] = \LaneWeChat\Core\ResponsePassive::newsItem($tuwen['title'], $tuwen['description'], $tuwen['pic_url'], $tuwen['url']);
                }
                return ResponsePassive::news($request['fromusername'],  $request['tousername'], $itemList);
                break;
            default:
                $content = Ext::GetKeyWord(WECHAT_HASH, $request['content']);
                if (!$content) {
                    //查找默认设置
                    $content = Ext::GetKeyWord(WECHAT_HASH, 'DEFAULT');
                    if (!$content ) {
                        if (in_array(WECHAT_HASH, Ext::CustomServiceConfig())) {
                            //TODO::多客服接口--票付通,先行
                            return ResponsePassive::forwardToCustomService($request['fromusername'], $request['tousername']);
                        }else {
                            $content = '有什么可以帮助您？';
                        }
                    }
                }
                return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
                break;
        }
//        if($request['content']=='abc') {
//        }
//        elseif ($request['content']=='devtest') {
//            $tuwenList[] = array('title'=>'标题1', 'description'=>'描述1', 'pic_url'=>'http://wx.12301.cc/public/static/quaImages/top_bg11.jpg', 'url'=>'http://www.baidu.com');
////            $tuwenList[] = array('title'=>'标题2', 'description'=>'描述2', 'pic_url'=>'http://wx.12301.cc/public/static/quaImages/top_bg22.jpg', 'url'=>'http://www.12301.cc');
////构建图文消息格式
//            $itemList = array();
//            foreach($tuwenList as $tuwen){
//                $itemList[] = \LaneWeChat\Core\ResponsePassive::newsItem($tuwen['title'], $tuwen['description'], $tuwen['pic_url'], $tuwen['url']);
//            }
//            return ResponsePassive::news($request['fromusername'],  $request['tousername'], $itemList);
//        } else {
//            //关键字查找
//            $content = Ext::GetKeyWord(WECHAT_HASH, $request['content']);
//            if (!$content) {
//                //查找默认设置
//                $content = Ext::GetKeyWord(WECHAT_HASH, 'DEFAULT');
//                if (!$content ) {
//                    if (in_array(WECHAT_HASH, Ext::CustomServiceConfig())) {
//                        //TODO::多客服接口--票付通,先行
//                        return ResponsePassive::forwardToCustomService($request['fromusername'], $request['tousername']);
//                    }else {
//                        $content = '有什么可以帮助您？';
//                    }
//                }
//            }
//        }
//        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    /**
     * @descrpition 图像
     * @param $request
     * @return array
     */
    public static function image(&$request){
        return ResponsePassive::text($request['fromusername'], $request['tousername'], '收到图片');
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
        $sql = 'SELECT response FROM pft_wx_resp WHERE rid=' . WECHAT_HASH .' AND keyword="EVENT_SUBSCRIBE"';
        $stmt = Db::Connect()->prepare($sql);
        $stmt->execute();
        $resp = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        if ($resp) {
            $cnt = count($resp);
            if ($cnt==1) {
                $rsp = array_shift($resp);
                if(strpos($rsp, 'IMAGE') !== false) {
                    list($tmp,$title, $picUrl, $description, $url) = explode('|', $resp);
                    $item = array(ResponsePassive::newsItem($title, $description, $picUrl, $url));
                    return ResponsePassive::news($request['fromusername'], $request['tousername'], $item);
                } else {
                    return ResponsePassive::text($request['fromusername'], $request['tousername'], $rsp);
                }
            } else {
                $item = array();
                foreach($resp as $rsp) {
                    if(strpos($rsp, 'IMAGE') === false) continue;
                    list($tmp,$title, $picUrl, $description, $url) = explode('|', $rsp);
                    $item[] = ResponsePassive::newsItem($title, $description, $picUrl, $url);
                }
                $cnt_item = count($item);
                if (!$cnt_item) {
                    return ResponsePassive::text($request['fromusername'], $request['tousername'], array_shift($resp));
                }
                return ResponsePassive::news($request['fromusername'], $request['tousername'], $item);
            }
        }
        /*if ($resp = $stmt->fetchColumn(0)) {
            //图文回复：IMAGE|标题|图片|摘要|地址
            if(strpos($resp, 'IMAGE') !== false) {
                list($tmp,$title, $picUrl, $description, $url) = explode('|', $resp);
                $item = array(ResponsePassive::newsItem($title, $description, $picUrl, $url));
                return ResponsePassive::news($request['fromusername'], $request['tousername'], $item);
            } else {
                return ResponsePassive::text($request['fromusername'], $request['tousername'], $resp);
            }
        }*/
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    /**
     * @descrpition 取消关注
     * @param $request
     * @return array
     */
    public static function eventUnsubscribe(&$request){
        $content = '为什么不理我了？';
        //删除之前绑定的数据
        Ext::RmBind($request['fromusername']);
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    public static function WeFansBind($openid, $scan_id)
    {
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
        $content = '欢迎您关注我们的微信';
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    /**
     * @descrpition 上报地理位置
     * @param $request
     * @return array
     */
    public static function eventLocation(&$request){
//        $content = '收到上报的地理位置';// . json_encode($request);
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
            $content = Ext::GetKeyWord(WECHAT_HASH, (string)$eventKey);
            if (!$content) {
                $content = Ext::GetKeyWord(WECHAT_HASH, 'DEFAULT');
                $content = $content ? $content :  '系统无法识别此操作';//
            }
//            $content = '收到点击菜单事件，您设置的key是' . $eventKey;
        }

        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
    }

    public static function matcherTxtEventBind(&$request)
    {
        $arr = explode(',', str_replace('，',',',$request['content']));
        //判断是否已经绑定
        if (Ext::chkBindStatus($request['fromusername'], WECHAT_HASH)) {
            return '您的微信账号已经和平台绑定，无需重复绑定~';
        }
        if (count($arr)!=3) {
            return '绑定账号输入格式有误！正确格式为：绑定,您的账号,您的密码。如：绑定,888888,111111';
        }
        $account = $arr[1];
        $pwd     = md5(md5($arr[2]));
        $ret = Ext::Bind($account, $pwd, $request['fromusername']);
        return $ret['msg'];
    }

    /**
     * 粉丝绑定事件
     *
     * @param $request
     * @return string
     */
    public static function matcherEventBind(&$request)
    {
//        $ret['msg'] = 'aaa';
        $ret = Ext::getBindUrl($request['fromusername'], WECHAT_HASH);
        if ($ret['status'] == 1 ) {
            $content =  '点击' . $ret['msg'] . '即可将平台账号跟微信号绑定。';
            //$content .= "您也可以使用快捷方式：输入“绑定,您的账号,您的密码”后发送（PS:字符之间必须用逗号“,”分隔；为了您的账号安全，在绑定成功后，请记得长按您的账号消息将其删除。），即可绑定您的账号啦！如：绑定,888888,111111";
        }
        else {
            $content = $ret['msg'];
        }
        return $content;
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
        $db = Db::Connect();
        $sql = "SELECT id FROM uu_wx_member_pft WHERE fromusername='{$request['fromusername']}' AND tousername='".WECHAT_APPID."' LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $log_id = $stmt->fetchColumn(0);
        if ($log_id) {
            $sql = "DELETE FROM uu_wx_member_pft WHERE id=$log_id LIMIT 1";
            if ($db->exec($sql)!==false) {
                $content = "取消绑定成功。";
            } else {
                $content  = '取消绑定失败';//. $sql;
            }
        }
        else {
            $content = "您还没有绑定过账号，无法执行取消绑定操作。";
        }
        return $content;
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
        return ResponsePassive::text($request['fromusername'], $request['tousername'], $content);
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
            Ext::Log("模板消息发送失败:因为用户拒收而发送失败:openid={$request['FromUserName']}",BASE_LOG_DIR . '/wx/tpl_msg/send_error.log');
        }else if($status == 'failed: system failed'){
            //其他原因发送失败
            Ext::Log("模板消息发送失败:其他原因发送失败:openid={$request['FromUserName']}",BASE_LOG_DIR . '/wx/tpl_msg/send_error.log');
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
