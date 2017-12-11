<?php
/**
 * Created by PhpStorm.
 * User: luoChen Lan
 * Date: 2016/10/5
 * Time: 08:59
 */

namespace Library\Business\WePay;


class WxPayLib
{
    const REFUND_ERROR_CODES = [
        'SYSTEMERROR' =>'描述:接口返回错误,原因:系统超时,解决方案:请用相同参数再次调用API',
        'USER_ACCOUNT_ABNORMAL' =>'描述:退款请求失败,原因:用户帐号注销 ,解决方案:此状态代表退款申请失败，商户可自行处理退款。',
        'NOTENOUGH' =>'描述:余额不足,原因:商户可用退款余额不足,解决方案:此状态代表退款申请失败，商户可根据具体的错误提示做相应的处理。',
        'INVALID_TRANSACTIONID' =>'描述:无效transaction_id,原因:请求参数未按指引进行填写,解决方案:请求参数错误，检查原交易号是否存在或发起支付交易接口返回失败',
        'PARAM_ERROR' =>'描述:参数错误,原因:请求参数未按指引进行填写,解决方案:请求参数错误，请重新检查再调用退款申请',
        'APPID_NOT_EXIST' =>'描述:APPID不存在,原因:参数中缺少APPID,解决方案:请检查APPID是否正确',
        'MCHID_NOT_EXIST' =>'描述:MCHID不存在,原因:参数中缺少MCHID,解决方案:请检查MCHID是否正确',
        'APPID_MCHID_NOT_MATCH' =>'描述:appid和mch_id不匹配,原因:appid和mch_id不匹配,解决方案:请确认appid和mch_id是否匹配',
        'REQUIRE_POST_METHOD' =>'描述:请使用post方法,原因:未使用post传递参数,解决方案:请检查请求参数是否通过post方法提交',
        'SIGNERROR' =>'描述:签名错误,原因:参数签名结果不正确,解决方案:请检查签名参数和方法是否都符合签名算法要求',
        'XML_FORMAT_ERROR' =>'描述:XML格式错误,原因:XML格式错误,解决方案:请检查XML参数格式是否正确',
    ];
    protected $WePayConf;
    public $returnParameters = [];
    public function __construct($appid, $config = [])
    {
        //公众号appId和配置信息
        WxPayApi::SetCommonInfo($appid, $config);
    }

    /**
     * 获取微信jsApi支的参数
     * @author luoChen Lan
     * @date 2016-10-02
     *
     * @param int $total_fee 交易金额，单位分
     * @param string $body 交易描述，不能超过20个字符
     * @param string $out_trade_no 平台交易订单号
     * @param string $openid 粉丝openid
     * @param string $notify_url 回调通知url
     * @param string $attach
     * @param string $tag
     * @param int $productid
     * @return string
     */
    public function jsApiPay($total_fee, $body, $out_trade_no, $openid,$notify_url='',$attach='',$tag='',$productid=1)
    {
        $body       = mb_substr(trim($body), 0, 20, 'utf-8');   //太长微信支付会报错
        //=========步骤2：使用统一支付接口，获取prepay_id============
        //使用统一支付接口
        $input  = new WxPayUnifiedOrder();
        //设置统一支付接口参数
        if (!empty(WxPayApi::$sub_appid)) {
            $input->SetSub_openid($openid);
        } else {
            $input->SetOpenid($openid);
        }
        $input->SetBody($body);
        $input->SetAttach($attach);//做标记——取消订单原路退回
        $input->SetOut_trade_no($out_trade_no);
        $input->SetTotal_fee($total_fee);
        if(ENV!='TEST'){
            $input->SetSub_appid(WxPayApi::$sub_appid);
            $input->SetSub_mchid(WxPayApi::$sub_mchid);
        }
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", $_SERVER['REQUEST_TIME'] + 7200));
        $input->SetGoods_tag($tag);
        $input->SetNotify_url($notify_url);
        $input->SetTrade_type("JSAPI");
        $input->SetProduct_id($productid);
        //微信充值：使用jsapi接口
        //$res = $this->getParameters($input);
        $result = WxPayApi::unifiedOrder($input, 30);
        if ($result['return_code']!='SUCCESS') {
            return $result;
        }
        $prepay_id = $result["prepay_id"];
        $jsApiObj["appId"] = WxPayApi::$appid;
        $time = time();
        $jsApiObj["timeStamp"] = "$time";
        $jsApiObj["nonceStr"] = WxPayApi::getNonceStr();
        $jsApiObj["package"] = "prepay_id=$prepay_id";
        $jsApiObj["signType"] = "MD5";
        $jsApiObj["paySign"] = $input->getPaySign($jsApiObj);
        return json_encode($jsApiObj);
    }
    /**
     * 获取二维码支付的参数
     *
     * @author luoChen Lan
     * @date 2016-10-04
     *
     * @param int    $total_fee     支付金额，单位“分”
     * @param string $body          支付描述
     * @param string $out_trade_no  订单号
     * @param string $notify_url    异步通知地址
     * @param string $attach
     * @param string $tag
     * @param int    $productid
     * @return array
     */
    public function qrPay($total_fee, $body, $out_trade_no, $notify_url='', $attach='',$tag='',$productid=1)
    {
        $input  = new WxPayUnifiedOrder();
        $input->SetBody($body);
        $input->SetAttach($attach);//做标记——取消订单原路退回
        $input->SetOut_trade_no($out_trade_no);
        $input->SetTotal_fee($total_fee);
        $input->SetSub_appid(WxPayApi::$sub_appid); //公众号appid
        $input->SetSub_mchid(WxPayApi::$sub_mchid); //商户号ID
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", $_SERVER['REQUEST_TIME'] + 7200));
        $input->SetGoods_tag($tag);
        $input->SetNotify_url($notify_url);
        $input->SetTrade_type("NATIVE");
        $input->SetProduct_id($productid);
        $result = WxPayApi::unifiedOrder($input, 30);
        return $result;
    }

    /**
     * 微信支付退款
     * @author luoChen Lan
     * @date 2010-10-05
     *
     * @param string $out_trade_no 商户订单号
     * @param string $trade_no 微信订单号
     * @param string $out_refund_no 商户退款单号
     * @param int $total_fee 总金额
     * @param int $refund_fee 退款金额
     * @return array 成功时返回
     * @throws WxPayException
     */
    public function refund($out_trade_no, $trade_no,$out_refund_no,$total_fee, $refund_fee)
    {
        $refund = new WxPayRefund();
        if (!empty($trade_no)) {
            $refund->SetTransaction_id($trade_no);
        }
        else {
            $refund->SetOut_trade_no($out_trade_no);
        }

        $refund->SetOut_refund_no($out_refund_no);
        $refund->SetTotal_fee($total_fee);
        $refund->SetRefund_fee($refund_fee);
        $refund->SetOp_user_id(WxPayApi::$mchid);//操作员
        //非必填参数，商户可根据实际情况选填
        //if (WxPayApi::$sub_mchid) {
        //    $refund->SetSub_appid(WxPayApi::$sub_appid);//微信分配的子商户公众账号ID
        //    $refund->SetSub_mchid(WxPayApi::$sub_mchid);//微信支付分配的子商户号
        //}
        $result = WxPayApi::refund($refund, 10);
        return $result;
    }

    public function query($out_trade_no, $trade_no='',$ret_type='array')
    {
        $input = new WxPayDataBase();
        if ($trade_no!='')  $input->SetTransaction_id($trade_no);
        if ($out_trade_no!='')  $input->SetOut_trade_no($out_trade_no);
        return WxPayApi::orderQuery($input, 6, $ret_type);
    }

    public function refundQuery($out_trade_no, $trade_no, $out_refund_no='', $refund_id='')
    {
        $input = new WxPayRefundQuery();
        $input->SetTransaction_id($trade_no);
        $input->SetOut_trade_no($out_trade_no);
        if ($out_refund_no!='') $input->SetOut_refund_no($out_refund_no);
        if ($refund_id!='') $input->SetRefund_id($refund_id);
        return WxPayApi::refundQuery($input);
    }


    /**
     * 提交刷卡支付，并且确认结果，接口比较慢
     *
     * @author luoChen Lan
     * @date 2016-10-09
     *
     * @param string $auth_code 微信付款碼
     * @param string $body 支付描述
     * @param int $total_fee 支付金額，單位：分
     * @param string $ordernum 訂單號
     * @return bool|int
     * @throws WxPayException
     */
    public function micropay($auth_code, $body, $total_fee, $ordernum)
    {
        $microPayInput = new WxPayMicroPay();
        $microPayInput->SetAuth_code($auth_code);
        $microPayInput->SetBody($body);
        $microPayInput->SetTotal_fee($total_fee);
        $microPayInput->SetOut_trade_no($ordernum);
        $result = WxPayApi::micropay($microPayInput, 30);
        if(!array_key_exists("return_code", $result)
            || !array_key_exists("result_code", $result))  {
            //echo "接口调用失败,请确认是否输入是否有误！";
            throw new WxPayException("接口调用失败,{$result['return_msg']}");
        }

        //签名验证
        $out_trade_no = $microPayInput->GetOut_trade_no();

        //②、接口调用成功，明确返回调用失败
        if($result["return_code"] == "SUCCESS" && $result["result_code"] == "FAIL" &&
            $result["err_code"] != "USERPAYING" && $result["err_code"] != "SYSTEMERROR") {
            return false;
        }

        //③、确认支付是否成功
        $queryTimes = 60;
        while($queryTimes > 0) {
            $queryTimes --;
            $succResult = 0;
            $queryResult = WxPayApi::micoPayQuery($out_trade_no, $succResult);
            //如果需要等待1s后继续
            if($succResult == 2){
                sleep(1);
                continue;
            } else if($succResult == 1){//查询成功
                return $queryResult;
            } else {//订单交易失败
                return false;
            }
        }
        $reverseInput = new WxPayDataBase();
        $reverseInput->SetOut_trade_no($out_trade_no);
        //④、次确认失败，则撤销订单
        if(!WxPayApi::reverse($reverseInput))  {
            throw new WxPayException("撤销单失败！");
        }
        return false;
    }
}