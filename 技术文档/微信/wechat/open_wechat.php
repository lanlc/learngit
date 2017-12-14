<?php
namespace LaneWeChat;
//引入配置文件
if (ENV=='PRODUCTION') include_once __DIR__.'/open_platform_config.php';
else include_once __DIR__.'/open_platform_config_test.php';

//@TODO：请自行补全
//引入自动载入函数
include_once __DIR__.'/autoloader.php';
//调用自动载入函数
AutoLoader::register();