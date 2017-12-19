<?php
/**
 * Created by PhpStorm.
 * User: guangpeng
 * Date: 4/19-019
 * Time: 22:09
 */

namespace Library\Cache;

use Exception;
use Redis;

class CacheRedis extends Cache {
    private $config;
    private $connected;
    private $type;
    private $prefix;

    private $isLock = false;

    //redis日志目录
    private $_logPath = 'redis';

    public function __construct() {
        $this->config = C('redis');
        if (empty($this->config['slave'])) {
            $this->config['slave'] = $this->config['master'];
        }

        $this->prefix = $this->config['prefix'] ? $this->config['prefix'] : substr(md5($_SERVER['HTTP_HOST']), 0, 6) . '_';
        if (!extension_loaded('redis')) {
            throw new \Exception('redis failed to load');
        }
    }

    public function __call($name, $arguments) {
        if (!$this->handler) {
            $this->init_master();
        }
        if (!empty($arguments)) {
            return call_user_func_array(array($this->handler, $name), $arguments);
        }
        return $this->handler->$name();
    }
    private function init_master() {
        static $_cache = array();
        $md5           = md5(serialize($this->config['master']));

        if (isset($_cache[$md5])) {
            $this->handler = $_cache[$md5];
        } else {
            $func          = $this->config['pconnect'] ? 'pconnect' : 'connect';
            $this->handler = new Redis();
            $this->enable  = $this->handler->$func($this->config['master']['db_host'], $this->config['master']['db_port']);
            if (isset($this->config['master']['db_pwd'])) {
                $this->handler->auth($this->config['master']['db_pwd']);
            }
            $_cache[$md5] = $this->handler;
        }
    }

    private function init_slave() {
        $this->init('slave');
    }
    private function init($dbName)
    {
        static $_cache = array();
        $md5           = md5(serialize($this->config[$dbName]));
        if (isset($_cache[$md5])) {
            $this->handler = $_cache[$md5];
        } else {
            $this->handler = new Redis;
            $this->enable  = $this->handler->connect($this->config[$dbName]['db_host'], $this->config[$dbName]['db_port'], 5);

            if (isset($this->config[$dbName]['db_pwd'])) {
                $this->handler->auth($this->config[$dbName]['db_pwd']);
            }
            $_cache[$md5] = $this->handler;
        }


    }
    private function isConnected() {
        $this->init_master();
        return $this->enable;
    }

    public function get($key, $type = '', $unserizlize = false) {
        try {
            $this->init_slave();
            if (!$this->enable) {
                return false;
            }

            $this->type = $type;
            $value      = $this->handler->get($this->_key($key));
            return $unserizlize ? unserialize($value) : $value;
        } catch (Exception $e) {
            pft_log($this->_logPath, (string) $e);
            return false;
        }
    }

    public function lock($key, $val, $expire = 60) {
        try {
            $this->init('lock');
            //lock操作连到aliyun上
            $this->isLock = true;
            $ret = $this->handler->setnx($key, $val);
            if ($ret) {
                $this->handler->expire($key, $expire);
            }
            return $ret;
        } catch (Exception $e) {
            pft_log($this->_logPath, (string) $e);
            return false;
        }
    }

    public function set($key, $value, $prefix = '', $expire = null, $unserizlize = false) {

        try {
            $this->init_master();
            if (!$this->enable) {
                return false;
            }

            $this->type = $prefix;

            $value = $unserizlize ? serialize($value) : $value;
            if (is_int($expire)) {
                $result = $this->handler->setex($this->_key($key), $expire, $value);
            } else {
                $result = $this->handler->set($this->_key($key), $value);
            }
            return $result;
        } catch (Exception $e) {
            pft_log($this->_logPath, (string) $e);
            return false;
        }
    }

    //重写setex方法
    public function setex($key, $expire, $value, $prefix = '', $unserizlize = false) {
        $expire = intval($expire);
        return $this->set($key, $value, $prefix, $expire, $unserizlize);
    }

    public function expire($key, $expire = 1800) {
        try {
            if ($expire <= 0) {
                return false;
            }

            return $this->handler->expire($this->_key($key), $expire);
        } catch (Exception $e) {
            pft_log($this->_logPath, (string) $e);
            return false;
        }
    }
    private function _incrDecr($func, $key, $val) {
        $this->init_master();
        return $this->handler->$func($key, $val);
    }
    public function incrBy($key, $val = 1) {
        try {
            if ($this->get($key, '', false) !== false) {
                $result = $this->_incrDecr('incrBy', $this->_key($key), $val);
            } else {
                $result = $this->set($key, $val, '', 1800, false);
            }
            return $result;
        } catch (Exception $e) {
            pft_log($this->_logPath, (string) $e);
            return false;
        }
    }
    public function decrBy($key, $val = 1) {
        try {
            if ($this->get($key, '', false) !== false) {
                $result = $this->_incrDecr('decrBy', $this->_key($key), $val);
            } else {
                $result = $this->set($key, $val, '', 1800, false);
            }
            return $result;
        } catch (Exception $e) {
            pft_log($this->_logPath, (string) $e);
            return false;
        }
    }

    public function hset($name, $prefix, $data, $expire = 1800) {
        try {
            $this->init_master();
            if (!$this->enable || !is_array($data) || empty($data)) {
                return false;
            }

            $this->type = $prefix;
            foreach ($data as $key => $value) {
                if ($value[0] == 'exp') {
                    $value[1] = str_replace(' ', '', $value[1]);
                    preg_match('/^[A-Za-z_]+([+-]\d+(\.\d+)?)$/', $value[1], $matches);
                    if (is_numeric($matches[1])) {
                        $this->hIncrBy($name, $prefix, $key, $matches[1]);
                    }
                    unset($data[$key]);
                }
            }
            if (count($data) == 1) {
                $this->handler->hset($this->_key($name), key($data), current($data));
            } elseif (count($data) > 1) {
                $this->handler->hMset($this->_key($name), $data);
            }
            $this->handler->expire($this->_key($name), $expire);
        } catch (Exception $e) {
            pft_log($this->_logPath, (string) $e);
            return false;
        }
    }

    public function hget($name, $prefix = '', $key = null) {
        try {
            $this->init_slave();
            if (!$this->enable) {
                return false;
            }

            $this->type = $prefix;
            if ($key == '*' || is_null($key)) {
                return $this->handler->hGetAll($this->_key($name));
            } elseif (strpos($key, ',') != false) {
                return $this->handler->hmGet($this->_key($name), explode(',', $key));
            } else {
                return $this->handler->hget($this->_key($name), $key);
            }
        } catch (Exception $e) {
            pft_log($this->_logPath, (string) $e);
            return false;
        }
    }

    public function hdel($name, $prefix, $key = null) {
        try {
            $this->init_master();
            if (!$this->enable) {
                return false;
            }

            $this->type = $prefix;
            if (is_null($key)) {
                if (is_array($name)) {
                    return $this->handler->delete(array_walk($array, array(self, '_key')));
                } else {
                    return $this->handler->delete($this->_key($name));
                }
            } else {
                if (is_array($name)) {
                    foreach ($name as $key => $value) {
                        $this->handler->hdel($this->_key($name), $key);
                    }
                    return true;
                } else {
                    return $this->handler->hdel($this->_key($name), $key);
                }
            }
        } catch (Exception $e) {
            pft_log($this->_logPath, (string) $e);
            return false;
        }
    }
    public function hIncrBy($name, $prefix, $key, $num = 1) {
        try {
            if ($this->hget($name, $prefix, $key) !== false) {
                $this->init_master();
                return $this->handler->hIncrByFloat($this->_key($name), $key, floatval($num));
            }
        } catch (Exception $e) {
            pft_log($this->_logPath, (string) $e);
            return false;
        }
    }

    public function rm($key, $type = '') {
        try {
            if ($this->isLock) {
                $this->init('lock');
            } else {
                $this->init_master();
            }
            if (!$this->enable) {
                return false;
            }

            $this->type = $type;
            return $this->handler->delete($this->_key($key));
        } catch (Exception $e) {
            pft_log($this->_logPath, (string) $e);
            return false;
        }
    }

    public function clear() {
        try {
            $this->init_master();
            if (!$this->enable) {
                return false;
            }

            return $this->handler->flushDB();
        } catch (Exception $e) {
            pft_log($this->_logPath, (string) $e);
            return false;
        }
    }

    private function _key($str) {
        return $str;
        return $this->prefix . $this->type . $str;
    }

}