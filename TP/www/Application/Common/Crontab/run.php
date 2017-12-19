<?php

if (PHP_SAPI != 'cli') {
    exit('access deny!');
}

if ($argc == 1) {
    echo str_pad("*", 50, "*"), PHP_EOL;
    echo str_pad("*", 19, " ")," HELP TIPS ",str_pad("*", 20, " ", STR_PAD_LEFT), PHP_EOL;
    echo str_pad("*", 4, " ")," php -f run ControllerName ActionName ",str_pad("*", 8, " ", STR_PAD_LEFT), PHP_EOL;
    echo str_pad("*", 50, "*"), PHP_EOL;
    exit;
}


include_once __DIR__.'/../Common/functions.php';
include_once __DIR__.'/../Conf/config.php';
include_once __DIR__.'/autoload.php';

spl_autoload_register("\\AutoLoading\\loading::autoload");

// 注册AUTOLOAD方法

$controller = $argv[1];

if (strpos($controller, '_') !== false) {
    list($namespace, $controller) = explode('_', $controller);

    $controller = $namespace . '/Controller/'.$controller;
}

$controller = str_replace('/', '\\', $controller);

$action = $argv[2];


if (class_exists($controller)) {
    $object = new $controller();
} else {
    exit($controller . ' Class is Not exit!');
}

if (method_exists($controller, $action)) {
    $object->$action($argv);
} else {
    exit('method is not exit!');
}


