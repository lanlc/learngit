<?php namespace Library\Business\WePay;

/**
 *
 * 异步输入对象
 * @author Guangpeng Chen
 *
 */
class WxPayNotifyResponse extends WxPayDataBase
{
    public $data;
    public $xml;
    public function saveData($log_path)
    {
        $this->xml = file_get_contents("php://input");
        pft_log($log_path,$this->xml);
        $this->data = $this->xmlToArray($this->xml);
        if (!$this->data) exit('Empty Data');
    }
    /**
     * 将XML转为array
     *
     * @param string $xml
     * @return array
     */
    public function xmlToArray($xml)
    {
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $array_data;
    }
    /**
     * 校验微信请求过来的参数
     *
     * @return bool
     */
    public function checkSign()
    {
        $tmpData = $this->data;
        unset($tmpData['sign']);
        $sign = parent::getPaySign($tmpData);//本地签名
        if ($this->data['sign'] == $sign) {
            return TRUE;
        }
        return FALSE;
    }
    /**
     * 设置return_code
     * @param string $value
     **/
    public function SetReturn_code($value)
    {
        $this->values['return_code'] = $value;
    }
    /**
     * 设置return_code
     * @param string $value
     **/
    public function SetReturn_msg($value)
    {
        $this->values['return_msg'] = $value;
    }
}