<?php
namespace Controller\pay;


use Library\Business\WePay\WxPayApi;
use Library\Business\WePay\WxPayDataBase;
use Library\Business\WePay\WxPayLib;
use Library\Business\WePay\WxPayNotifyResponse;

class WxPay extends Controller implements PayInterface
{
    /**
     * 定义支付渠道
     */
    const SOURCE_T = 1;

    const RECHARGE_NOTIFY_URL = PAY_DOMAIN . 'r/pay_WxPay/rechargeNotify';
    const ORDER_NOTIFY_URL    = PAY_DOMAIN . 'order/mobile_wepay_notify.php';
    const RENEW_NOTIFY_URL    = PAY_DOMAIN . 'r/pay_WxPay/renewNotify/'; //平台会员充值通知地址
    const PARK_NOTIFY_URL     = PAY_DOMAIN . 'r/pay_WxPay/parkPayNotify/'; //停车场支付通知地址

    private $WePayConf  = [];
    private $orderModel = null;
    private $merchantId = 0;
    /**
     * @var $wxPayLib WxPayLib
     */
    private $wxPayLib;
    public function __construct($config = null, $merchantId = 0)
    {
        $appid = I('post.appid');
        $this->wxPayLib   = new WxPayLib($appid, $config);
        $this->merchantId = $merchantId;
        //$this->WePayConf = include '/var/www/html/wx/pay/wepay/WxPayPubHelper/WePay.conf.php';
    }

    /**
     * 订单支付
     */
    public function order()
    {
        //平台订单号
        $outTradeNo = I('out_trade_no', 0, 'string');
        //是否二维码支付
        $qrPay = I('is_qr', 0, 'intval');
        //微信用户openid
        $openid = I('openid', null);
        //订单主体说明
        $subject = mb_substr(trim(I('subject')), 0, 20, 'utf-8');
        if (!$outTradeNo || !$subject) {
            parent::apiReturn(401, [], '参数缺失');
        }
        //jsapi支付时，必须openid
        if (!$qrPay && !$openid) {
            parent::apiReturn(401, [], 'openid缺失,请重试登陆再试');
        }

        //获取订单总金额--不要相信前端的数据，一定要从后台查询
        $OrderQeury = new Model();
        $totalFee   = $OrderQeury->get_order_total_fee((string) $outTradeNo);

        //生成微信支付二维码
        if ($qrPay) {
            $tmpOutTradeNo = 'qr_' . $outTradeNo;
            $result        = $this->_orderPayByQrcode($totalFee, $tmpOutTradeNo, $subject);
            $data          = ['outTradeNo' => $outTradeNo, 'qrUrl' => $result['data']];
        } else {
            //调用微信jsapi支付
            $result = $this->_orderPayByJsapi($totalFee, $outTradeNo, $subject, $openid);
            $data   = $result['data'];
        }

        if ($result['status'] == 0) {
            parent::apiReturn(401, [], $result['msg']);
        } else {
            //生成支付记录
            $tradeModel = new OnlineModel();
            $ret        = $tradeModel->addLog($outTradeNo, $totalFee / 100, $subject, $subject, self::SOURCE_T, OnlineTrade::PAY_METHOD_ORDER, '', $this->merchantId);

            if (!$ret) {
                parent::apiReturn(401, [], '支付记录生成失败');
            }
        }
        echo json_encode(['code' => 200, 'data' => $data, 'status' => 'ok', 'msg' => 'success']);
        exit;
    }
    /**
     * 微信端支付成功后发起支付状态检测
     */
    public function payResultCheck()
    {
        $ordernum = I('post.ordernum');
        //支付场景：1订单支付,2充值
        $pay_scen = I('post.pay_scen', 2);
        //todo::检测支付状态
        $model   = new OnlineTrade();
        $pay_log = $model->getLog($ordernum, self::SOURCE_T);
        if (!$pay_log) {
            parent::apiReturn(parent::CODE_CREATED, [], '支付记录不存在', true);
        }

        if ($pay_log['status'] == 1) {
            parent::apiReturn(parent::CODE_SUCCESS, [], '支付成功', true);
        }
        parent::apiReturn(parent::CODE_INVALID_REQUEST, [], '支付失败', true);
    }
    /**
     * 微信二维码支付
     *
     * @param  float  $totalFee   总金额
     * @param  string $outTradeNo 平台订单号
     * @param  string $subject    订单描述
     * @return array
     */
    private function _orderPayByQrcode($totalFee, $outTradeNo, $subject)
    {
        //微信二维码支付
        $parameters = $this->wxPayLib->qrPay(
            $totalFee,
            $subject,
            $outTradeNo,
            self::ORDER_NOTIFY_URL,
            '微信二维码支付',
            'orderpay'
        );

        if ($parameters['return_code'] == 'SUCCESS') {
            $return = [
                'status' => 1,
                'data'   => $parameters['code_url'],
            ];
        } else {
            $return = [
                'status' => 0,
                'msg'    => $parameters['return_msg'],
            ];
        }

        return $return;
    }

    /**
     * 微信jsapi支付
     * @param  [type] $totalFee   总金额
     * @param  [type] $outTradeNo 平台订单号
     * @param  [type] $subject    订单描述
     * @param  [type] $openid     微信penid
     * @return [type]             [description]
     */
    public function _orderPayByJsapi($totalFee, $outTradeNo, $subject, $openid)
    {
        //微信jsapi支付
        $parameters = $this->wxPayLib->jsApiPay(
            $totalFee,
            $subject,
            $outTradeNo,
            $openid,
            self::ORDER_NOTIFY_URL
        );

        if (is_array($parameters) && isset($parameters['return_code']) && $parameters['return_code'] != 'SUCCESS') {
            $return = [
                'status' => 0,
                'msg'    => $parameters['return_msg'],
            ];
        } else {
            $return = [
                'status' => 1,
                'data'   => is_array($parameters) ?: json_decode($parameters, true),
            ];
        }

        return $return;
    }

    public function micropay()
    {
        $outTradeNo  = I('post.ordernum');
        $auth_code   = I('post.auth_code');
        $total_fee   = I('post.money') * 100;
        $is_member   = I('post.is_member', 0);
        $subject     = I('post.subject', '订单支付');
        $pay_scen    = I('post.pay_scen', 1);
        $payTerminal = I('post.terminal', 0, 'intval'); //支付的终端

        if (!$outTradeNo || !$auth_code) {
            $this->apiReturn(parent::CODE_INVALID_REQUEST, [], '参数错误，订单号或支付码必须传');
        }
        pft_log('/micropay/wepay', json_encode($_POST));
        switch ($pay_scen) {
            case 1:
                //订单支付
                if (is_numeric($outTradeNo)) {
                    $orderQuery = new OrderQuery();
                    $total_fee  = $orderQuery->get_order_total_fee($outTradeNo);
                }
                break;
            case 2:
                break;
        }
        $OnlineTrade = new OnlineTrade();
        $result      = $OnlineTrade->addLog($outTradeNo, $total_fee / 100, $subject, $subject, self::SOURCE_T, 0, '', $this->merchantId);
        if (!$result) {
            parent::apiReturn(parent::CODE_INVALID_REQUEST, [], '支付失败,生成交易记录失败', true);
        }

        $pay_result = $this->wxPayLib->micropay($auth_code, $subject, $total_fee, $outTradeNo);
        if ($pay_result == false) {
            parent::apiReturn(parent::CODE_INVALID_REQUEST, [], "支付失败,订单号:{$outTradeNo},请求微信支付失败", true);
        }

        $buyerInfo = array(
            'openid'     => $pay_result['openid'],
            'sub_openid' => $pay_result['sub_openid'],
        );
        $sellerInfo = array(
            'appid'     => $pay_result['appid'],
            'sub_appid' => $pay_result['sub_appid'],
        );
        $transaction_id = $pay_result['transaction_id'];
        $json_buy       = json_encode($buyerInfo);
        $json_sell      = json_encode($sellerInfo);
        $pay_to_pft     = $pay_result['sub_appid'] == PFT_WECHAT_APPID ? true : false;
        $pay_channel    = 11;
        //是否支付后立即验证标识
        $needVerify = I('post.verify', 0);
        //支付时所使用的终端号
        $terminal = I('post.terminal', 0, 'strval');
        try {
            $res      = parent::getSoap()->Change_Order_Pay($outTradeNo, $transaction_id, OnlineTrade::CHANNEL_WEPAY,
                $total_fee, $is_member, $json_sell, $json_buy, 1, $pay_to_pft, $pay_channel, '', $payTerminal);
        } catch (\SoapFault $e) {
            $retry = true;
            pft_log('order_pay/error', "(微信)订单支付失败:{$outTradeNo}，soap抛出异常:{$e->getCode()}->{$e->getMessage()}");
            //Helpers::sendWechatWarningMessage("WTF!{$parentOrderNum}子票下单异常,{$e->getMessage()}",[], "套票子票下单异常", Helpers::getServerIp());
            Helpers::sendDingTalkGroupRobotMessage("(微信)[{$outTradeNo}]订单支付失败;soap抛出异常:{$e->getCode()}->{$e->getMessage()}", "支付失败",  Helpers::getServerIp(), DingTalkRobots::MYSQL_ERROR);
        }
        if (isset($retry) && $retry === true) {
            $res      = parent::getSoap()->Change_Order_Pay($outTradeNo, $transaction_id, OnlineTrade::CHANNEL_WEPAY,
                $total_fee, $is_member, $json_sell, $json_buy, 1, $pay_to_pft, $pay_channel, '', $payTerminal);
        }
        if ($res === 100) {
            $orderInfo = $this->_getOrder($outTradeNo);
            if (Helpers::isMobile($orderInfo['ordertel'])) {
                $args = [
                    'ordernum' => $outTradeNo,
                    'buyerId'  => $orderInfo['member'], //购买人的ID
                    'mobile'   => $orderInfo['ordertel'],
                    'aid'      => $orderInfo['aid'],
                ];
                $jobId = \Library\Resque\Queue::push('notify', 'OrderNotify_Job', $args);
            }
            if ($needVerify) {
                if (empty($this->orderInfo)) {
                }
                $query   = new Query();
                $paymode = 5;
                $query->getOrderInfoForPrint($outTradeNo, $this->orderInfo, $total_fee, $paymode, $terminal);
            }
            $msg    = '支付成功';
            $code   = parent::CODE_SUCCESS;
        } else {
            $msg    = "微信支付成功但订单状态更新失败,订单号:{$outTradeNo}";
            $code   = parent::CODE_SUCCESS;
        }
        pft_log('micropay/wepay_result', $outTradeNo. ':支付结果响应:'.json_encode($pay_result) . ';修改支付状态返回:'. $res);
        parent::apiReturn($code, [], $msg, true);
    }
    private function _getOrder($ordernum)
    {
        $this->orderModel = new OrderTools('localhost');
        $orderInfo        = $this->orderModel->getOrderInfo($ordernum, 'member,ordername,ordertel,aid,code,lid,tid,tnum,paymode,salerid', 'de.aids,de.series');
        if ($orderInfo['aids']) {
            // 转分销获取第二级分销商
            $tmp                 = explode(',', $orderInfo['aids']);
            $orderInfo['member'] = $tmp[1];
        }
        $this->orderInfo = $orderInfo;
        return $orderInfo;
    }
    /**
     * @author Guangpeng Chen
     * @date 2016-10-03
     *
     * @description 充值-post
     * @money:充值的金额，单位“元”
     * @openid:微信openid
     * @aid:供应商ID，可以为空；大于0表示授信预存
     * @did:充值的人的id
     * @appid:微信收款公众号
     * @is_qr:是否扫码支付
     * @memo:备注
     */
    public function recharge()
    {
        $money     = I('post.money');
        $money     = floatval(number_format($money, 2, '.', ''));
        $total_fee = $money * 100;
        $openid    = I('post.openid');
        $aid       = I('post.aid');
        $did       = I('post.did', $_SESSION['sid']);
        $is_qr     = I('post.qr_pay', 0);
        $memo      = I('post.memo', '', 'trim');
        if (!$did) {
            exit('{"status":"fail","msg":"用户身份获取错误"}');
        }
        if ($is_qr == 0 && empty($openid)) {
            exit('{"status":"fail","msg":"OPENID为空"}');
        }
        if (!is_numeric($money) || $money < 0) {
            exit('{"status":"fail","msg":"请输入大于0的金额，金额必须是数字"}');
        }
        $modelMember = new Member();
        if ($did == 1) {
            $body = '补打款';
        } else {
            $seller_nama = $modelMember->getMemberCacheById($did, 'dname');
            if ($aid > 0) {
                $boss_name = $modelMember->getMemberCacheById($aid, 'dname');
                $body      = "[$seller_nama]给{$boss_name}充值{$money}元|{$did}|$aid";
            } else {
                $body = "[{$seller_nama}]账户充值{$money}元|{$did}";
            }
        }

        if ($memo) {
            $body .= '|' . $memo;
        }

        $out_trade_no             = time() . $did . mt_rand(1000, 9999);
        $log_data                 = $_POST;
        $log_data['out_trade_no'] = $out_trade_no;
        $log_data['body']         = $body;
        //pft_log('wepay/recharge_debug','resqust:' . json_encode($log_data, JSON_UNESCAPED_UNICODE));
        unset($log_data);
        if ($is_qr) {
            //$parameters = $this->wxPayLib->qrPay($total_fee, $body, $out_trade_no, self::RECHARGE_NOTIFY_URL,'微信充值','recharge');
            $parameters = $this->wxPayLib->qrPay($total_fee, $body, $out_trade_no, self::RECHARGE_NOTIFY_URL, '微信充值', 'recharge');
            if ($parameters['return_code'] != 'SUCCESS') {
                $msg = "支付失敗，向微信提交支付订单失败，错误信息：{$parameters['return_msg']}";
                if (!$is_qr) {
                    $msg .= ",您可以尝试使用【微信二维码支付】功能来完成充值";
                }

                parent::apiReturn(401, [], $msg);
            }
            $data = ['outTradeNo' => $out_trade_no, 'qrUrl' => $parameters['code_url']];
        } else {
            $parameters = $this->wxPayLib->jsApiPay($total_fee, $body, $out_trade_no, $openid, self::RECHARGE_NOTIFY_URL);
            if (is_array($parameters) && isset($parameters['return_code']) && $parameters['return_code'] != 'SUCCESS') {
                parent::apiReturn(401, [], "支付失敗，向微信提交支付订单失败，错误信息：{$parameters['return_msg']}");
            }
            $data = ['parameter' => json_decode($parameters), 'outTradeNo' => $out_trade_no];
            //$data = json_decode($parameters);
        }
        $model = new OnlineTrade();
        $ret   = $model->addLog($out_trade_no, $money, $body, $body, self::SOURCE_T, OnlineTrade::PAY_METHOD_RECHARGE);
        if (!$ret) {
            parent::apiReturn(401, [], '记录发生错误,请联系客服人员');
        }

        parent::apiReturn(200, $data);
    }

    /**
     * 微信退款——post
     *
     * @author Guangpeng Chen
     * @date 2016-10-05
     * @appid string 微信公众号appid
     * @out_trade_no string 票付通平台订单号
     * @total_fee int 支付金额，单位：元
     */
    public function refund()
    {
        //退款权限控制
        $flag = 0;
        if (isset($_SESSION['openid'])) {
            $authOpenid = load_config('mobile_data_monitor');
            if (!in_array($_SESSION['openid'], $authOpenid)) {
                exit('{"code":401,"msg":"Auth Error"}');
            }
            $flag = 1;
        }
        if ($flag == 0 && (!isset($_SESSION['memberID']) || $_SESSION['memberID'] != 1)) {
            exit('{"code":401,"msg":"Auth Error"}');
        }
        $out_trade_no = I('post.out_trade_no');
        if (I('post.refund_raw', 0, 'intval') == 1) {
            $refund_fee = I('post.refund_money') * 100;
            $trade_no   = I('post.trade_no');
        } else {
            $modelTrade = new \Model\TradeRecord\OnlineRefund();
            $trade_info = $modelTrade->GetTradeLog($out_trade_no);
            $refund_fee = $trade_info['refund_money'];
            $trade_no   = $trade_info['trade_no'];
        }
        $total_fee     = I('post.total_fee', 0) * 100;
        $out_refund_no = I('post.ordernum') . '_' . time(); //商户退款单号，商户自定义，此处仅作举例
        $result        = $this->wxPayLib->refund($out_refund_no, $trade_no, $out_refund_no, $total_fee, $refund_fee);
        pft_log('wepay/refund', json_encode($result), 'month');
        if ($result['result_code'] == 'SUCCESS' && $result['return_code'] == 'SUCCESS') {
            echo '{"code":200, "msg":"退款成功"}';
        } else {
            echo '{"code":201, "msg":"退款失败,错误描述:' . $result['return_msg'] . '"}';
        }
    }

    /**
     * 查询微信支付的订单-POST
     *
     * @author Guangpeng Chen
     * @date 2016-10-05
     * @appid 微信公众号appid
     * @out_trade_no string 平台订单号
     * @trade_no string 微信支付交易号
     */
    public function query()
    {
        $out_trade_no = I('post.out_trade_no');
        $trade_no     = I('post.trade_no');
        $ret_type     = I('post.ret_type', '');
        $res          = $this->wxPayLib->query($out_trade_no, $trade_no, $ret_type);
        if ($ret_type == 'xml') {
            echo $res;
        } else {
            echo json_encode($res);
        }

    }

    /**
     * 微信充值异步通知
     * @author Guangpeng chen
     * @date 2017-01-24
     */
    public function rechargeNotify()
    {
        $log_path = 'wepay/recharge';
        $notify   = new WxPayNotifyResponse();
        $notify->saveData($log_path);
        $appid = $notify->data['appid'];
        WxPayApi::SetCommonInfo($appid);
        //使用通用通知接口
        if ($notify->checkSign() == false) {
            $notify->SetReturn_code("FAIL"); //返回状态码
            $notify->SetReturn_msg("签名失败"); //返回信息
        } else {
            if ($notify->data["return_code"] == "FAIL") {
                $notify->SetReturn_code("FAIL");
                $notify->SetReturn_msg("通信出错");
                pft_log('wepay/error', "【通信出错】:\n" . $notify->xml, LOG_NAME);
            } elseif ($notify->data["result_code"] == "FAIL") {
                $notify->SetReturn_code("FAIL");
                $notify->SetReturn_msg("业务出错");
                pft_log('wepay/error', "【业务出错】:\n" . $notify->xml, LOG_NAME);
            } else {
                $notify->SetReturn_code("SUCCESS"); //设置返回码
                $ordern        = $notify->data['out_trade_no']; //订单号
                $trade_no      = $notify->data['transaction_id']; //交易号
                $pay_total_fee = (int) $notify->data['total_fee'] + 0; //金额用分为单位
                $sourceT       = 1;
                $wx_app_id     = $notify->data['appid'];
                $wx_sub_app_id = $notify->data['sub_appid'];
                $buyerInfo     = array('openid' => $notify->data['openid']);
                $sellerInfo    = array('appid' => $notify->data['appid']);
                if (isset($notify->data['sub_appid'])) {
                    $buyerInfo['sub_openid'] = $notify->data['sub_openid'];
                    $sellerInfo['sub_appid'] = $notify->data['sub_appid'];
                }
                $json_buy      = json_encode($buyerInfo);
                $json_sell     = json_encode($sellerInfo);
                $modelRecharge = new Recharge();
                $res           = $modelRecharge->OnlineRecharge($ordern, $sourceT, $trade_no, $pay_total_fee, $json_buy,
                    $json_sell, $wx_app_id, $wx_sub_app_id);
                //TODO::包含全局文件
                if ($res !== true) {
                    $notify->SetReturn_code("FAIL");
                    $notify->SetReturn_msg("发生错误");
                } else {
                    $notify->SetReturn_code("SUCCESS"); //设置返回码
                }
            }
        }
        $returnXml = $notify->ToXml();
        echo $returnXml;
    }
    /**
     * 退款查詢
     */
    public function refundQuery()
    {
        $out_trade_no  = I('post.out_trade_no');
        $trade_no      = I('post.trade_no');
        $out_refund_no = I('post.out_refund_no');
        $refund_id     = I('post.refund_id');
        $input         = new WxPayDataBase();
        $input->SetTransaction_id($trade_no);
        $input->SetOut_trade_no($out_trade_no);
        $res = $this->wxPayLib->refundQuery($out_trade_no, $trade_no, $out_refund_no, $refund_id);
        echo json_encode($res);
    }

    /**
     * 平台会员续费
     * @return [type] [description]
     */
    public function renew()
    {
        $memberId = $this->isLogin('ajax');
        $memberId = $_SESSION['sid'];
        $meal     = I('meal');

        $Member      = $this->model('Member/Member');
        $Renew       = $this->model('Member/Renew');
        $Relation    = $this->model('Member/MemberRelationship');
        $OnlineTrade = $this->model('TradeRecord/OnlineTrade');

        //获取套餐金额
        $money = $Renew->gitMealMoney($meal, $memberId);
        if ($money == false) {
            $this->apiReturn(204, [], '非法提交');
        }

        $money = 1;
        //请求日志
        $logData = json_encode($_REQUEST, JSON_UNESCAPED_UNICODE);
        @pft_log('wepay/renew', $memberId . ':' . $logData);

        //充值人姓名
        $from = $Member->getMemberCacheById($memberId, 'dname');
        //描述主体
        $body = '票付通平台续费充值|' . $meal;
        //生成平台流水号
        $outTradeNo = time() . $memberId . mt_rand(1000, 9999);
        //支付成功通知地址
        $notifyUrl = $_SERVER['HTTP_HOST'] . '/r/pay_WxPay/renewNotify';
        //获取支付二维码
        $result = $this->wxPayLib->qrPay($money, $body, $outTradeNo, $notifyUrl, $body, 'renew');

        if ($result['return_code'] != 'SUCCESS') {
            $this->apiReturn(204, [], $result['return_msg']);
        }
        //生成充值记录
        $create = $OnlineTrade->addRecord(
            $outTradeNo,
            $body,
            $money,
            '',
            json_encode(['memberId' => $memberId]),
            1
        );

        if (!$create) {
            $this->apiReturn(204, [], '充值记录生成失败');
        }

        $this->apiReturn(200, ['qrUrl' => $result['code_url'], 'outTradeNo' => $outTradeNo]);

    }

    /**
     * 续费通知接口
     *
     */
    public function renewNotify()
    {
        //请求日志
        $logData = json_encode($_REQUEST, JSON_UNESCAPED_UNICODE);
        @pft_log('wepay/renew', $logData);

        $notify = new WxPayNotifyResponse();
        $notify->saveData('wepay/renew');
        $appid = $notify->data['appid'];

        //支付检测
        $checkRes = $this->_notifyBaseCheck($notify);
        if (!$checkRes) {
            //支付出错直接返回
            exit($notify->ToXml());
        }
        //进入平台逻辑处理
        @pft_log('wepay/renew', "进入平台逻辑处理");
        //这边加个锁
        $Redis = Cache::getInstance('redis');
        $key   = 'renew' . $notify->data['out_trade_no'];
        $lock  = $Redis->lock($key, 1, 30);

        if (!$lock) {
            exit($notify->ToXml());
        }

        $outTradeNo = $notify->data['out_trade_no'];
        $money      = $notify->data['total_fee'];
        $attach     = $notify->data['attach'];
        $meal       = explode('|', $attach)[1];
        //平台记录检测
        $logRes = $this->model('Member/Renew')->notifyVerify($money / 100, $outTradeNo, 1);

        if ($logRes['status'] == 0) {
            @pft_log('wepay/renew', $logRes['error']);
        } else {
            $memberId = $logRes['memberId'];
            $Renew    = $this->model('Member/Renew');
            $Renew->renewPayComplete($memberId, $outTradeNo, $notify->data['transaction_id'], $money, $meal);
        }

        $Redis->rm($key);

        exit($notify->ToXml());
    }

    /**
     * 支付基本检测,包括签名以及支付结果等
     * @param  array $notify
     * @return bool
     */
    private function _notifyBaseCheck(&$notify)
    {
        //签名验证
        if ($notify->checkSign() == false) {
            //设置返回状态码,下同
            $notify->SetReturn_code("FAIL");
            //设置返回信息,下同
            $notify->SetReturn_msg("签名失败");

            @pft_log('wepay/error', '签名验证失败');

            return false;
        }
        //通信检测
        if ($notify->data["return_code"] == "FAIL") {
            $notify->SetReturn_code("FAIL");
            $notify->SetReturn_msg("通信出错");

            @pft_log('wepay/error', "通信出错:\n" . $notify->xml);

            return false;
        }
        //业务检测
        if ($notify->data["result_code"] == "FAIL") {
            $notify->SetReturn_code("FAIL");
            $notify->SetReturn_msg("业务出错");

            @pft_log('wepay/error', "业务出错:\n" . $notify->xml);

            return false;
        }
        $notify->SetReturn_code("SUCCESS");
        return true;
    }

    public function orderNotify()
    {
        $log_path = 'wepay/order';
        $notify   = new WxPayNotifyResponse();
        $notify->saveData($log_path);
    }
}
