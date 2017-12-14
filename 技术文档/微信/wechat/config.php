<?php
namespace LaneWeChat;
/**
 * 系统主配置文件.
 * @Created by Lane.
 * @Author: lane
 * @Mail lixuan868686@163.com
 * @Date: 14-8-1
 * @Time: 下午1:00
 * @Blog: Http://www.lanecn.com
 */
//版本号
define('LANEWECHAT_VERSION', '1.4');
define('LANEWECHAT_VERSION_DATE', '2014-11-05');

//微信公众平台相关
//define("WECHAT_URL", 'http://www.12301.cc');
//define('WECHAT_TOKEN', 'DaQg0DgqF0YrTQS0TKh0VdV8LkATDlsv');
//define('ENCODING_AES_KEY', "MqAuKoex6FyT5No0OcpRyCicThGs0P1vz4mJ2gwvvkF");
//
//define("WECHAT_APPID", 'wx5f3f2fba331ad1c6');
//define("WECHAT_APPSECRET", '0559ed7dd37413b54004895d47a83d52');

define("WECHAT_URL", 'http://www.12301.cc');
define('WECHAT_TOKEN', '27230262eb2d5d5ec751d9e92a176e65');
define('ENCODING_AES_KEY', "e7ec2de2ae26959d3762622b5ed0511b30265722272");

define("WECHAT_APPID", 'wxd72be21f7455640d');
define("WECHAT_APPSECRET", 'fb330082b1f0d8a82049a8c2098276be');

////-----引入系统所需类库-------------------
////引入错误消息类
//include_once 'core/msg.lib.php';
////引入错误码类
//include_once 'core/msgconstant.lib.php';
////引入CURL类
//include_once 'core/curl.lib.php';
//
////-----------引入微信所需的基本类库----------------
////引入微信处理中心类
//include_once 'core/wechat.lib.php';
////引入微信请求处理类
//include_once 'core/wechatrequest.lib.php';
////引入微信被动响应处理类
//include_once 'core/responsepassive.lib.php';
////引入微信access_token类
//include 'core/accesstoken.lib.php';
//
////-----如果是认证服务号，需要引入以下类--------------
////引入微信权限管理类
//include_once 'core/wechatoauth.lib.php';
////引入微信用户/用户组管理类
//include_once 'core/usermanage.lib.php';
////引入微信主动相应处理类
//include_once 'core/responseinitiative.lib.php';
////引入多媒体管理类
//include_once 'core/media.lib.php';
////引入自定义菜单类
//include_once 'core/menu.lib.php';
?>