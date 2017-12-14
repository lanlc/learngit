<?php

namespace Business\Wechat;

use Library\wechat\core\OpenWeChatOAuth;
use Library\wechat\core\WeChatOAuth;
use Library\wechat\core\OpenUserManage;


/**
 * 微信授权相关业务接口
 * Class Authorization
 * @package Business\Wechat
 */
class Authorization{

    //授权回调的域名,公众后台配置
    const  __CALLBACK_DOMAIN__ = 'www.xxxx.com';

    //公众号appid
    private $_appid;


    public function __construct($appid = '') {
        $this->_appid = $appid;
    }


    /**
     * 发起微信授权请求
     *
     * @author luoChen Lan
     * @date   2017-05-19
     *
     * @param  string     $callback 授权回调地址(必须微信后台配置域名下的url)
     * @param  array      $params   回调参数
     * @param  string     $type     base : 无需用户同意授权, user : 需用户同意授权,用于获取微信账号信息
     */
    public function requestForAuth($callback, $params, $type = 'base') {

        $this->_beforeAuthRequest($callback, $params);

        $state = base64_encode(json_encode($params));

        if ($type == 'base') {
            //基础授权，只获取openid
            $scope = 'snsapi_base';
        } else {
            //获取用户信息
            $scope = 'snsapi_userinfo';
        }

        OpenWeChatOAuth::getCode($callback, $state, $scope, $this->_appid);
        exit();
    }


    /**
     * 获取openid
     *
     * @author luoChen Lan
     * @date   2017-06-23
     * @param  string  $code    发起网页授权后获得的code
     *
     * @return array
     */
    public function getOpenIdAndToken($code) {

        if (!$code) {
            return $this->returnData(204, '参数缺失');
        }


        $res = OpenWeChatOAuth::getAccessTokenAndOpenId($this->_appid, $code);

        if (isset($res['errcode'])) {
            return $this->returnData(204, "错误代码:{$res['errcode']}");
        }

        $data = [
            'openid'       => $res['openid'],
            'access_token' => $res['access_token'],
        ];

        return $this->returnData(200, '', $data);

    }

    /**
     * 获取微信用户资料(必须是关注用户|或者通过用户网页授权)
     * @author luoChen Lan
     * @date   2017-06-23
     *
     * @param  string     $openid
     *
     * @return array
     */
    public function getOpenUserInfo($openid) {
        if (!$openid) {
            return $this->returnData(204, '参数缺失');
        }

        //微信账号信息
        $userInfo = OpenUserManage::getUserInfo($openid, $this->_appid);
        if (!isset($userInfo['openid'])) {
            return $this->returnData(204, '用户信息获取失败');
        }

        return $this->returnData(200, '', $userInfo);
    }

    /**
     * 发起授权前的检测
     *
     * @date   2017-08-
     * @author LuoChen Lan
     *
     * @param  string    $callback 回调地址
     *
     * @return Exception
     */
    private function _beforeAuthRequest($callback) {
        $agent  = $_SERVER['HTTP_USER_AGENT'];
        $pos    = strpos($agent, 'MicroMessenger');
        if ($pos === false) {
            throw new \Exception("仅在微信app中支持授权");
        }

        $isUrl = filter_var($callback, FILTER_VALIDATE_URL);
        if (!$isUrl) {
            throw new \Exception("请填写合法的回调地址");
        }

        $domain = 'XXX';
        if (stripos($callback, $domain) === false) {
            throw new \Exception("回调域名错误");
        }
        return true;
    }
}