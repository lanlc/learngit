<?php
/**
 * 映射实例动作
 *
 * @params  object $class   类名
 * @params  string $method  动作名
 * @params  array  $args    参数
 *
 * @return mixed
 * @throws Exception
 *
 */
if (!function_exists('get_obj_instance')) {
    function get_obj_instance($class, $method, $args = array()) {
        static $_cache = array();

        $key = $class. $method . (empty($args)) ? null : md5(serialize($args));
        if (isset($_cache[$key])) {
            return $_cache[$key];
        } else if (class_exists($class)) {
            $obj = new $class();
            if (method_exists($obj, $method)) {
                if (empty($args)) {
                    $_cache[$key] = $obj->$method();
                } else {
                    $_cache[$key] = call_user_func_array(array(&$obj, $method), $args);

                }
            } else {
                $_cache[$key] = $obj;
            }

            return $_cache[$key];
        } else {
            throw new Exception('class' . $class . 'isn\'t exits!');
        }
    }
}

