<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 10/4-004
 * Time: 12:42
 */
namespace Library\Business\WePay;
class WxPayException extends \Exception {
    public function errorMessage()
    {
        return $this->getMessage();
    }
}