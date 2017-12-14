<?php
namespace Library\wechat\core;
/**
 * 模板消息接口
 * User: lane
 * Date: 14-10-30
 * Time: 下午5:02
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */
/**
模板消息仅用于公众号向用户发送重要的服务通知，只能用于符合其要求的服务场景中，如信用卡刷卡通知，商品购买成功通知等。不支持广告等营销类消息以及其它所有可能对用户造成骚扰的消息。

关于使用规则，请注意：
1、所有服务号都可以在功能->添加功能插件处看到申请模板消息功能的入口，但只有认证后的服务号才可以申请模板消息的使用权限并获得该权限；
2、需要选择公众账号服务所处的2个行业，每月可更改1次所选行业；
3、在所选择行业的模板库中选用已有的模板进行调用；
4、每个账号可以同时使用10个模板。
5、当前每个模板的日调用上限为10000次。

关于接口文档，请注意：
1、模板消息调用时主要需要模板ID和模板中各参数的赋值内容；
2、模板中参数内容必须以".DATA"结尾，否则视为保留字；
3、模板保留符号"{{ }}"。
 */

class TemplateMessage{
    const HOTEL_ORDER_MSG  = 'OtFSwdNT9Z2jC0CbO_UHLGevPupCYWBv1zH34szILVg';
    const BIND_MSG         = 'ZPcWX0yvCZfuVnB2HC0RFEuUCmTib29_8rCBPuzSN2A';
    const NEW_ORDER        = '5QVNlRb5D9Fw3mFGIF5fhonoH42FPAbwllu5Au2013c';
    const CANCEL_ORDER     = 'vssudy2AGBlkZpygUbxlMAnLyOYYmtYvXfn0QvfoFRs';
    //充值通知
    const RECHARGE         = '1iCFwlvOj0pNB-Lxyabt9jK172BunB6GZKBbj60ewnw';
    //待办工作提醒
    //{{first.DATA}}
    //事项名称：{{keyword1.DATA}}
    //发起人：{{keyword2.DATA}}
    //发起时间：{{keyword3.DATA}}
    //{{remark.DATA}}
    const TODO_WORK        = '5GysNbvEyjuh9cKqMInxsMyihEqrTguaPbk-bVPpj8E';
    /**
     * 向用户推送模板消息
     * @param $data = array(
     *                  'first'=>array('value'=>'您好，您已成功消费。', 'color'=>'#0A0A0A')
     *                  'keynote1'=>array('value'=>'巧克力', 'color'=>'#CCCCCC')
     *                  'keynote2'=>array('value'=>'39.8元', 'color'=>'#CCCCCC')
     *                  'keynote3'=>array('value'=>'2014年9月16日', 'color'=>'#CCCCCC')
     *                  'keynote3'=>array('value'=>'欢迎再次购买。', 'color'=>'#173177')
     * );
     * @param string $touser 接收方的OpenId。
     * @param string $templateId 模板Id。在公众平台线上模板库中选用模板获得ID
     * @param string $url URL
     * @param string $topcolor 顶部颜色， 可以为空。默认是红色
     * @return array("errcode"=>0, "errmsg"=>"ok", "msgid"=>200228332} "errcode"是0则表示没有出错
     *
     * 注意：推送后用户到底是否成功接受，微信会向公众号推送一个消息。
     */
    public static function sendTemplateMessage($data, $touser, $templateId, $url, $topcolor='#FF0000', $appid=null){
        $queryUrl = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.AccessToken::getAccessToken();
        $queryAction = 'POST';
        $template = array();
        $template['template_id'] = $templateId;
        $template['url'] = $url;
        $template['topcolor'] = $topcolor;
        $template['data'] = $data;
        $template['touser'] = $touser;
        $template = json_encode($template, JSON_UNESCAPED_UNICODE);
        $ret = Curl::callWebServer($queryUrl, $template, $queryAction);
        pft_log('wx/template_msg', 'req='.$template . ';ret=' . json_encode($ret));
        return $ret;
    }

    public static function openSendTemplateMessage($data, $touser, $templateId, $url, $topcolor='#FF0000', $appid=null){
        $ret = self::_openSendTemplateMessage($data, $touser, $templateId, $url, $topcolor, $appid);
        //如果返回access_token失效，那么重新获取token，发送一次。
        if ($ret['errcode']==40001) {
            $access_token = OpenAccessToken::getAccessTokenManual($appid);
            self::_openSendTemplateMessage($data, $touser, $templateId, $url, $topcolor, $appid,$access_token);
        }
        return $ret;
    }
    private static function _openSendTemplateMessage($data, $touser, $templateId, $url,
                                                 $topcolor='#FF0000', $appid=null, $access_token='')
    {
        $queryUrl = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='
                    . (!empty($access_token) ? $access_token : OpenAccessToken::getAccessToken($appid));
        $queryAction = 'POST';
        $template = array();
        $template['template_id'] = self::getTmpId($templateId, $appid);
        $template['url'] = $url;
        $template['topcolor'] = $topcolor;
        $template['data'] = $data;
        $template['touser'] = $touser;
        $template = json_encode($template, JSON_UNESCAPED_UNICODE);
        $ret = Curl::callWebServer($queryUrl, $template, $queryAction);
        if ($ret['errcode']!=0){
            pft_log('wx/template_msg', '[open]req='.$template . ';ret=' . json_encode($ret));
        }
        return $ret;
    }
    public static function getTmpId($name, $appid=null)
    {
        $appid   = is_null($appid) ? WECHAT_APPID : $appid;
        $tplConf = load_config($appid, 'wechat_tplmsg');
        return $tplConf[$name];
    }
}