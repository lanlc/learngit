<?php
namespace Library;

/**
 * @author neeke@php.net
 * Date: 14-1-27 16:47
 */
define('SEASLOG_ALL', 'ALL');
define('SEASLOG_DEBUG', 'DEBUG');
define('SEASLOG_INFO', 'INFO');
define('SEASLOG_NOTICE', 'NOTICE');
define('SEASLOG_WARNING', 'WARNING');
define('SEASLOG_ERROR', 'ERROR');
define('SEASLOG_CRITICAL', 'CRITICAL');
define('SEASLOG_ALERT', 'ALERT');
define('SEASLOG_EMERGENCY', 'EMERGENCY');
define('SEASLOG_DETAIL_ORDER_ASC', 1);
define('SEASLOG_DETAIL_ORDER_DESC', 2);


class BaseLog
{
    public function __construct()
    {
        #SeasLog init
    }

    public function __destruct()
    {
        #SeasLog distroy
    }

    /**
     * Set The basePath-default see php.ini config
     *
     * @param $basePath
     *
     * @return bool
     */
    static public function setBasePath($basePath)
    {
        return \SeasLog::getBasePath($basePath);
    }

    /**
     * Get The basePath
     *
     * @return string
     */
    static public function getBasePath()
    {
        return \SeasLog::getBasePath();
    }

    /**
     * Set The requestID
     * @param string
     * @return bool
     */
    static public function setRequestID($request_id){
        return \SeasLog::setRequestID($request_id);
    }
    /**
     * Get The requestID
     * @return string
     */
    static public function getRequestID(){
        return uniqid();
    }

    /**
     * Set The logger example:testModule/directory
     * @param $module
     *
     * @return bool
     */
    static public function setLogger($module)
    {
        return \SeasLog::setLogger($module);
    }

    /**
     * Get The lastest logger
     * @return string
     */
    static public function getLastLogger()
    {
        return \SeasLog::getLastLogger();
    }

    /**
     * Set The DatetimeFormat
     * @param $format
     *
     * @return bool
     */
    static public function setDatetimeFormat($format)
    {
        return \SeasLog::setDatetimeFormat($format);
    }

    /**
     * Get The DatetimeFormat
     * @return string
     */
    static public function getDatetimeFormat()
    {
        return \SeasLog::getDatetimeFormat();
    }

    /**
     * Count All Types（Or Type）Log Lines
     * @param string $level
     * @param string $log_path
     * @param null   $key_word
     *
     * @return array | long
     */
    static public function analyzerCount($level = 'all', $log_path = '*', $key_word = NULL)
    {
        return \SeasLog::analyzerCount($level, $log_path, $key_word);
    }

    /**
     * Get The Logs As Array
     *
     * @param        $level
     * @param string $log_path-log file name，default，search all log in the module
     * @param null   $key_word
     * @param int    $start
     * @param int    $limit
     * @param int    $order SEASLOG_DETAIL_ORDER_ASC(Default)  SEASLOG_DETAIL_ORDER_DESC
     *
     * @return array
     */
    static public function analyzerDetail($log_path = '*', $level = SEASLOG_ALL, $key_word = NULL, $start = 1, $limit = 20, $order = SEASLOG_DETAIL_ORDER_ASC)
    {
        return \SeasLog::analyzerDetail($level, $log_path, $key_word, $start, $limit, $order);
    }

    /**
     * Get The Buffer In Memory As Array
     *
     * @return array
     */
    static public function getBuffer()
    {
        return \SeasLog::getBuffer();
    }

    /**
     * Flush The Buffer Dump To Writer Appender
     *
     * @return bool
     */
    static public function flushBuffer()
    {
        return \SeasLog::flushBuffer();
    }

    /**
     * Record Debug Log
     *
     * @param        $message
     * @param array  $content
     * @param string $module
     */
    static public function debug($message, array $content = array(), $module = '')
    {
        return \SeasLog::debug($message, $content, $module);
    }

    /**
     * Record Info Log
     *
     * @param        $message
     * @param array  $content
     * @param string $module
     */
    static public function info($message, array $content = array(), $module = '')
    {
        return \SeasLog::info($message, $content, $module);
    }

    /**
     * Record Notice Log
     *
     * @param        $message
     * @param array  $content
     * @param string $module
     */
    static public function notice($message, array $content = array(), $module = '')
    {
        return \SeasLog::notice($message, $content, $module);
    }

    /**
     * Record Warning Log
     *
     * @param        $message
     * @param array  $content
     * @param string $module
     */
    static public function warning($message, array $content = array(), $module = '')
    {
        return \SeasLog::warning($message, $content, $module);
    }

    /**
     * Record Error Log
     *
     * @param        $message
     * @param array  $content
     * @param string $module
     */
    static public function error($message, array $content = array(), $module = '')
    {
        return \SeasLog::error($message, $content, $module);
    }

    /**
     * Record Critical Log
     *
     * @param        $message
     * @param array  $content
     * @param string $module
     */
    static public function critical($message, array $content = array(), $module = '')
    {
        return \SeasLog::critical($message, $content, $module);
    }

    /**
     * Record Alert Log
     *
     * @param        $message
     * @param array  $content
     * @param string $module
     */
    static public function alert($message, array $content = array(), $module = '')
    {
        return \SeasLog::alert($message, $content, $module);
    }

    /**
     * Record Emergency Log
     *
     * @param        $message
     * @param array  $content
     * @param string $module
     */
    static public function emergency($message, array $content = array(), $module = '')
    {
        return \SeasLog::emergency($message, $content, $module);
    }

    /**
     * The Common Record Log Function
     * @param        $level
     * @param        $message
     * @param array  $content
     * @param string $module
     */
    static public function log($level, $message, array $content = array(), $module = '') {
        return \Seaslog::log($level, $message,  $content , $module );
    }
}
