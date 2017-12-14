<?php
namespace Library\wechat\core;
use Library\Cache\RedisCache;
use Model\domain\Platform;
use PFT\Db;

/**
 * Created by PhpStorm.
 * User: Guangpeng Chen
 * Date: 15-6-16
 * Time: 上午10:48
 */
class OpenPlatform {

    static function AccessToken()
    {
        $queryAction = 'POST';
        $queryUrl = 'https://api.weixin.qq.com/cgi-bin/component/api_component_token';
        $xml = OpenAccessToken::GetComponentVerifyTicket();
        $obj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $arr = array(
            'component_appid'           => OPEN_WECHAT_APPID,
            'component_appsecret'       => OPEN_WECHAT_APPSECRET,
            'component_verify_ticket' => (string)$obj->ComponentVerifyTicket
        );
        $json_param = json_encode($arr);
        return Curl::callWebServer($queryUrl, $json_param, $queryAction);
    }

    static function PreAuthCode($accessToken)
    {
        $queryUrl = 'https://api.weixin.qq.com/cgi-bin/component/api_create_preauthcode?component_access_token=' . $accessToken;
        $queryAction = 'POST';
        $arr = array(
            'component_appid'       => OPEN_WECHAT_APPID,
        );
        $json_param = json_encode($arr);
        return Curl::callWebServer($queryUrl, $json_param, $queryAction);
    }

    static function AuthCode($redict_uri)
    {
        $queryUrl = 'https://open.weixin.qq.com/connect/qrconnect?appid='
            .OPEN_WECHAT_APPID.'&redirect_uri='.$redict_uri.'&response_type=code&scope=SCOPE&state=STATE#wechat_redirect';
        echo $queryUrl;
        return Curl::callWebServer($queryUrl);
    }

    static function ApiQueryAuth($accessToken, $auth_code_value)
    {
        $queryUrl = 'https://api.weixin.qq.com/cgi-bin/component/api_query_auth?component_access_token=' . $accessToken;
        $queryAction = 'POST';
        $arr = array(
            'component_appid'       => OPEN_WECHAT_APPID,
            'authorization_code'    => $auth_code_value,
        );
        $json_param = json_encode($arr);
        return Curl::callWebServer($queryUrl, $json_param, $queryAction);
    }

    static function ApiAuthorizerInfo($accessToken, $authorizer_appid)
    {
        $queryUrl = 'https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_info?component_access_token=' . $accessToken;
        $queryAction = 'POST';
        $arr = array(
            'component_appid'     => OPEN_WECHAT_APPID,
            'authorizer_appid'    => $authorizer_appid,
        );
        $json_param = json_encode($arr);
        return Curl::callWebServer($queryUrl, $json_param, $queryAction);
    }
    static function saveQr($url,$dir='/alidata/images/wechat/')
    {
        $name       = $_SERVER['REQUEST_TIME'] . '.jpg';
        $url_name   = 'http://images.12301.cc/wechat/'. $name;
        $file_name  = '/alidata/images/wechat/'. $name;
        $fp         = fopen($file_name, "w");
        $handle     = curl_init($url);
        curl_setopt($handle, CURLOPT_FILE, $fp);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_HEADER, false);
        // Do the request.
        $data = curl_exec($handle);
        // Clean up.
        curl_close($handle);
        fwrite($fp, $data);
        fclose($fp);
        return $url_name;
    }

    /**
     * 检查是否已经有二级域名的配置存在,不存在生成
     *
     * @param $fid int 会员ID
     * @param $account int 会员6位数账号
     * @param $info array 微信数据
     * @param $conf array 数据库配置
     * @return bool
     */
    static function CheckMemberDomain($fid, $account, $info, $conf)
    {
        //检查是否已经有二级域名的配置存在
        $platObj = new Platform();
        $exist   = $platObj->getBindedSubdomainInfo($fid);

        if (!$exist) {
            $data = array(
                'fid'               => $fid,
                'M_domain'          => $account,
                'M_name'            => $info['authorizer_info']['nick_name'],
                'M_account_domain'  => $account,
                'createtime'        => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']),
            );
            $platObj->SaveSubDomain($data);
        }
        return true;
    }
    static function SaveAuthorizerInfo($fid, $account, Array $info, Array $authInfo)
    {
        $funs = array();
        foreach($info['authorization_info']['func_info'] as $fun) {
            $funs[] = $fun['funcscope_category']['id'];
        }
        //$qrImg = self::saveQr($info['authorizer_info']['qrcode_url']);
        $appid = $info['authorization_info']['authorizer_appid'];
        $insert_arr = array(
            'fid'                   => $fid,
            'account'               => $account,
            'nick_name'             => $info['authorizer_info']['nick_name'],
            'head_img'              => $info['authorizer_info']['head_img'],
            'service_type_info'     => $info['authorizer_info']['service_type_info']['id'],
            'verify_type_info'      => $info['authorizer_info']['verify_type_info']['id'],
            'authorizer_refresh_token' => $authInfo['authorization_info']['authorizer_refresh_token'],
            'user_name'             => $info['authorizer_info']['user_name'],
            'qrcode_url'            => $info['authorizer_info']['qrcode_url'],
            'appid'                 => $appid,
            'func_info'             => implode(',',$funs),
            'create_time'           => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']),
            'isconnect'             => 0,
        );
        OpenAccessToken::getModel()->SaveAuthorizerInfo($appid, $insert_arr);
        //保存ACCESS TOKEN
        $accessToken = array(
            'authorizer_access_token'=>$authInfo['authorization_info']['authorizer_access_token'],
            'expires_in'=>$authInfo['authorization_info']['expires_in'],
            'time'=>$_SERVER['REQUEST_TIME'],
        );
        OpenAccessToken::getCache()->set("wx:access_token:{$appid}", json_encode($accessToken), '',$accessToken['expires_in']);
        //服务号或认证的订阅号，且未创建过菜单的公众号，为其创建默认菜单
        if ($info['authorizer_info']['service_type_info']['id']==2 || $info['authorizer_info']['verify_type_info']['id']==0) {
            $menu_obj = new \Model\Wechat\Menu();
            if (!$menu_obj->Get($appid)) {
                $json_menu = file_get_contents('/var/www/html/wx/wechat/default_config/menu_tpl.json');
                $json_menu = str_replace(
                    array('{%FID%}','{%ACCOUNT%}','{%APPID%}'),
                    array($fid, $account, $appid),
                    $json_menu);
                $menu_obj->Set($appid, $json_menu);
                $menuList = json_decode($json_menu, true);
                foreach ($menuList as $key=>$menu) {
                    foreach($menu as $k=>$m) {
                        if ($m==-1) $menuList[$key][$k] = '';
                    }
                }
                $ret = Menu::setMenu($menuList, $appid);
                OpenExt::Log('create default menu return:' . json_encode($ret),BASE_LOG_DIR . '/wx/open_platform_menu.log');
            }
        }
        return true;
    }
} #EOF CLASS