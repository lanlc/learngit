<?php
namespace Library\Cache;

use Think\Exception;

class Cache {

    protected $params;
    protected $enable;
    /**
     * @var $handler \Redis
     */
    protected $handler;

    /**
     * 获取对应的缓存驱动
     *
     * @date   2017-12-06
     * @author LuoChen Lan
     *
     * @param  string  $type    缓存名称
     * @param  array   $params  缓存配置参数
     *
     * @return object
     */
    public function connect($type, $params = array()) {
        $type = strtolower($type);
        $class = 'Cache'. ucwords($type);
        $class = '\\Library\\Cache\\'.$class;
        if (!class_exists($class)) {
            throw new Exception(["[$class] Cache is not exit"]);
        }

        return new $class($params);
    }

    /**
     * 获取对应缓存实例
     *
     * @date   2017-12-06
     * @author LuoChen Lan
     *
     * @param  string  $type    缓存名称
     * @param  array   $params  缓存配置参数
     *
     * @return object
     */
    public static function getInstance($type, $params = array()) {
        $params = func_get_args();

        return get_obj_instance(__CLASS__, 'connect', $params);
    }
}

?>