<?php
namespace Library\wechat\core;
use Library\Cache\Cache;
use Library\Cache\RedisCache;

/**
 * 微信Access_Token的获取与过期检查
 * Created by Lane.
 * User: lane
 * Date: 13-12-29
 * Time: 下午5:54
 * Mail: lixuan868686@163.com
 * Website: http://www.lanecn.com
 */
class AccessToken{

    public static function getCache()
    {
        return Cache::getInstance('redis');
    }
    /**
     * 获取微信Access_Token
     */
    public static function getAccessToken($forceGet=false){
        //检测本地是否已经拥有access_token，并且检测access_token是否过期
        $accessToken = self::_checkAccessToken();
        //$accessToken = false;
        if($accessToken === false || $forceGet===true){
            $accessToken = self::_getAccessToken();
        }
        if (isset($accessToken['authorizer_access_token'])){
            return $accessToken['authorizer_access_token'];
        }
        elseif (isset($accessToken['access_token'])) {
            return $accessToken['access_token'];
        }
        else {
            $accessToken = self::_getAccessToken();
            return isset($accessToken['access_token']) ? $accessToken['access_token'] : '';
        }
    }

    static function getOpenAccessToken()
    {
        $accessToken = self::_checkAccessToken();
        if($accessToken === false){
            $accessToken = self::OpenAccessToken();
        }
        return $accessToken['component_access_token'];
    }

    public static function _getJsApiTicket()
    {
        $access_token = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$access_token.'&type=jsapi';
        $jsapiTicket = Curl::callWebServer($url, '', 'GET');
        if(!isset($jsapiTicket['ticket'])){
            $access_token = self::_getAccessToken();
            $url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$access_token.'&type=jsapi';
            $jsapiTicket = Curl::callWebServer($url, '', 'GET');
            if(!isset($jsapiTicket['ticket'])){
                return array();
            }
        }
//        var_dump($jsapiTicket);
        $jsapiTicket['time'] = time();
        $jsapiTicketJson = json_encode($jsapiTicket);
        //存入数据库
        /**
         * 这里通常我会把access_token存起来，然后用的时候读取，判断是否过期，如果过期就重新调用此方法获取，存取操作请自行完成
         *
         * 请将变量$accessTokenJson给存起来，这个变量是一个字符串
         */
        self::getCache()->set('wx:js_api_ticket:'.WECHAT_APPID, $jsapiTicketJson, $jsapiTicket['expires_in']-100);
        return $jsapiTicket;
    }

    /**
     * JS-SDK，获取jsapiticket
     *
     * @return bool|mixed
     */
    public static function jsApiTicket()
    {
        $ticket = self::_checkAccessToken(2);
        if ($ticket === false) {
            return self::_getJsApiTicket();
        }
        return $ticket;

    }

    /**
     * @descrpition 从微信服务器获取微信ACCESS_TOKEN
     * @return Ambigous|bool
     */
    private static function _getAccessToken(){
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.WECHAT_APPID.'&secret='.WECHAT_APPSECRET;
        $accessToken = Curl::callWebServer($url, '', 'GET');
        if(!isset($accessToken['access_token'])){
            pft_log('/wx/access_token', "获取access_token失败,url={$url},返回:".json_encode($accessToken), 'month');
            //echo "获取access_token失败，信息:".json_encode($accessToken).'WECHAT_APPSECRET='. WECHAT_APPSECRET,'WECHAT_APPID=',WECHAT_APPID;
            return Msg::returnErrMsg(MsgConstant::ERROR_GET_ACCESS_TOKEN, '获取ACCESS_TOKEN失败');
        }
        $accessToken['time'] = time();
        $accessTokenJson = json_encode($accessToken);
        //print_r($accessToken);
        //存入数据库
        /**
         * 这里通常我会把access_token存起来，然后用的时候读取，判断是否过期，如果过期就重新调用此方法获取，存取操作请自行完成
         *
         * 请将变量$accessTokenJson给存起来，这个变量是一个字符串
         */
        $redis_key = 'wx:access_token:'.WECHAT_APPID;
        self::getCache()->set($redis_key, $accessTokenJson, $accessToken['expires_in']-100);
        return $accessToken;
    }


    /**
     * @descrpition 检测微信ACCESS_TOKEN是否过期
     *              -10是预留的网络延迟时间
     * @return bool
     */
    private static function _checkAccessToken($file_type=1){
        //获取access_token。是上面的获取方法获取到后存起来的。
        if ($file_type==2) {
            $key = 'wx:js_api_ticket:'.WECHAT_APPID;
        } else {
            $key = 'wx:access_token:'.WECHAT_APPID;
        }
        $accessToken = self::getCache()->get($key);
        if (!empty($accessToken)) {
            return json_decode($accessToken, true);
        }
        return false;
    }
}
?>