<?php
return array(
	//'配置项'=>'配置值'
    'DEFAULT_FILTER' => 'htmlspecialchars', // 默认参数过滤方法 用于I函数...
    'db' => array(
        'localhost' => array(
            'db_type' => 'mysql',
            'db_host' => '127.0.0.1',
            'db_user' => 'php_test',
            'db_pwd'  => 'Php_!#@*159',
            'db_port' => 3306,
            'db_name' => 'qqjingcaixin',
        )
    ),

    'redis' => array(
        'master' => array(
            'db_host'=>'127.0.0.1',
            'db_port'=> 6379,
        ),
        'slave' => array(
            'db_host'=>'127.0.0.1',
            'db_port'=> 6379,
        ),
        'lock' => array(
            'db_host'=>'127.0.0.1',
            'db_port'=> 6379,
        )
    )
);