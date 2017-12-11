<?php
/**
 * Created by PhpStorm.
 * User: chenguangpeng
 * Date: 10/4-004
 * Time: 13:10
 * 微信支付异步通知返回
 */

namespace Library\Business\WePay;


/**
 *
 * 退款查询输入对象
 * @author widyhu
 *
 */
class WxPayRefundQuery extends WxPayDataBase
{
    /**
     * 设置商户退款单号
     * @param string $value
     **/
    public function SetOut_refund_no($value)
    {
        $this->values['out_refund_no'] = $value;
    }
    /**
     * 获取商户退款单号的值
     * @return 值
     **/
    public function GetOut_refund_no()
    {
        return $this->values['out_refund_no'];
    }
    /**
     * 判断商户退款单号是否存在
     * @return true 或 false
     **/
    public function IsOut_refund_noSet()
    {
        return array_key_exists('out_refund_no', $this->values);
    }


    /**
     * 设置微信退款单号refund_id、out_refund_no、out_trade_no、transaction_id四个参数必填一个，如果同时存在优先级为：refund_id>out_refund_no>transaction_id>out_trade_no
     * @param string $value
     **/
    public function SetRefund_id($value)
    {
        $this->values['refund_id'] = $value;
    }
    /**
     * 获取微信退款单号refund_id、out_refund_no、out_trade_no、transaction_id四个参数必填一个，如果同时存在优先级为：refund_id>out_refund_no>transaction_id>out_trade_no的值
     * @return 值
     **/
    public function GetRefund_id()
    {
        return $this->values['refund_id'];
    }
    /**
     * 判断微信退款单号refund_id、out_refund_no、out_trade_no、transaction_id四个参数必填一个，如果同时存在优先级为：refund_id>out_refund_no>transaction_id>out_trade_no是否存在
     * @return true 或 false
     **/
    public function IsRefund_idSet()
    {
        return array_key_exists('refund_id', $this->values);
    }
}