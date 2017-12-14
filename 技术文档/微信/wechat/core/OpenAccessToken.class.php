<?php
namespace Library\wechat\core;
use Library\Cache\Cache;
use Model\Wechat\open;

/**
 * 微信Access_Token的获取与过期检查
 * Created by Lane.
 * User: lane
 * Date: 13-12-29
 * Time: 下午5:54
 * Mail: lixuan868686@163.com
 * Website: http://www.lanecn.com
 */
class OpenAccessToken{
    /**
     * @var $cache \Library\Cache\CacheRedis
     */
    static $cacheObject = null;
    static $openModel = null;
    public static function getCache()
    {
        if (is_null(self::$cacheObject)) {
            self::$cacheObject =  Cache::getInstance('redis');
        }
        return self::$cacheObject;
    }

    public static function getModel()
    {
        if (is_null(self::$openModel)) {
            self::$openModel = new open();
        }
        return self::$openModel;
    }

    /**
     * 获取微信Access_Token
     */
    public static function getAccessToken($appid){
        //检测本地是否已经拥有access_token，并且检测access_token是否过期
        $accessToken = self::_checkAccessToken($appid);
        if($accessToken === false || !isset($accessToken['authorizer_access_token'])){
            $accessToken = self::_getAccessToken($appid);
        }
        return isset($accessToken['access_token']) ? $accessToken['access_token']
            : $accessToken['authorizer_access_token'];
    }
    public static function getAccessTokenManual($appid) {
        $accessToken = self::_getAccessToken($appid);
        return isset($accessToken['access_token']) ? $accessToken['access_token']
            : $accessToken['authorizer_access_token'];
    }
    static function getOpenAccessToken($forceRequest=false)
    {
        $accessToken = self::_checkAccessToken(OPEN_WECHAT_APPID);
        if($accessToken === false || $forceRequest===true){
            $accessToken = self::OpenAccessToken();
        }
        return $accessToken['component_access_token'];
    }

    public static function SaveComponentVerifyTicket($xml)
    {
        self::getCache()->set('config:component_verify_ticket', base64_encode($xml));
        return true;
    }
    public static function GetComponentVerifyTicket()
    {
        $xml = self::getCache()->get('config:component_verify_ticket');
        return base64_decode($xml);
    }

    static function OpenAccessToken()
    {
        $queryAction = 'POST';
        $queryUrl = 'https://api.weixin.qq.com/cgi-bin/component/api_component_token';
        $xml = self::GetComponentVerifyTicket();
        $obj = simplexml_load_string($xml);
        $arr = array(
            'component_appid'           => OPEN_WECHAT_APPID,
            'component_appsecret'       => OPEN_WECHAT_APPSECRET,
            'component_verify_ticket'   => (string)$obj->ComponentVerifyTicket
        );
        $json_param = json_encode($arr);
        $accessToken = Curl::callWebServer($queryUrl, $json_param, $queryAction);
        if (empty($accessToken['component_access_token'])) {
            pft_log('wx/access_token',"获取OpenAccessToken失败，url:{$queryUrl},请求参数:{$json_param},信息:".json_encode($accessToken), 'month');
            return false;
        }
        $accessToken['time'] = time();
        $accessTokenJson = json_encode($accessToken);
        self::getCache()->set("wx:access_token:".OPEN_WECHAT_APPID, $accessTokenJson, '', $accessToken['expires_in']-100);
        return $accessToken;
    }


    public static function _getJsApiTicket($appid)
    {
        $access_token = self::getAccessToken($appid);
        $url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$access_token.'&type=jsapi';
        $jsapiTicket = Curl::callWebServer($url, '', 'GET');
        if (!isset($jsapiTicket['ticket'])) {
            return array();
        }
        $jsapiTicket['time'] = time();
        $json_jsapiTicket = json_encode($jsapiTicket);
        self::getCache()->set("wx:js_api_ticket:{$appid}", $json_jsapiTicket, '', $jsapiTicket['expires_in']);
        return $jsapiTicket;
    }

    /**
     * JS-SDK，获取jsapiticket
     *
     * @return bool|mixed
     */
    public static function jsApiTicket($appid)
    {
        $ticket = self::_checkAccessToken($appid, 2);
        if ($ticket === false) {
            return self::_getJsApiTicket($appid);
        }
        return $ticket;
    }

    private static function _getAccessTokenFun($openAccessToken, $json_param)
    {
        $queryUrl   = 'https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token?component_access_token='.$openAccessToken;
        $accessToken = Curl::callWebServer($queryUrl, $json_param, 'POST');
        return $accessToken;
    }
    /**
     * 获取（刷新）授权公众号的令牌
     *
     * @param string $appid 授权方appid
     * @param string $authorizer_refresh_token 授权方的刷新令牌
     * @return bool|mixed
     */
    private static function _getAccessToken($appid)
    {
        $authorizer_refresh_token = self::getModel()->getRefreshToken($appid);
        $openAccessToken = self::getOpenAccessToken();
        $arr = array(
            'component_appid'           => OPEN_WECHAT_APPID,
            'authorizer_appid'          => $appid,
            'authorizer_refresh_token'  => $authorizer_refresh_token
        );
        $json_param = json_encode($arr);
        $accessToken = self::_getAccessTokenFun($openAccessToken, $json_param);
        if ($accessToken['errcode']==40001) {
            //40001有可能是access_token过期导致，重新获取一次
            $openAccessToken = self::getOpenAccessToken(true);
            pft_log('wx/access_token',"获取access_token失败,getOpenAccessToken,请求参数:{$json_param},信息:".json_encode($accessToken).';openAccessToken='.$openAccessToken, 'month');
            $accessToken = self::_getAccessTokenFun($openAccessToken, $json_param);
        }
        if (isset($accessToken['errcode'])) {
            pft_log('wx/access_token',"获取access_token失败,请求参数:{$json_param},信息:".json_encode($accessToken), 'month');
            return false;
        }
        $accessToken['time'] = time();
        $accessTokenJson = json_encode($accessToken);
        self::getCache()->set("wx:access_token:{$appid}", $accessTokenJson,'', $accessToken['expires_in']-100);
        return $accessToken;
    }

    /**
     * @descrpition 检测微信ACCESS_TOKEN是否过期
     *              -10是预留的网络延迟时间
     * @return bool
     */
    private static function _checkAccessToken($appid, $type=1){
        //获取access_token。是上面的获取方法获取到后存起来的。
        $key = $type==1 ? "wx:access_token:{$appid}" : "wx:js_api_ticket:{$appid}";
        $accessToken =  self::getCache()->get($key);
        if (!empty($accessToken)) {
            return json_decode($accessToken, true);
        }
        return false;
    }
}
?>