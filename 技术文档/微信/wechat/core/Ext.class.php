<?php
namespace Library\wechat\core;

use pft\Member\MemberAccount;

class Ext {

    const PFT_APP_ID    = 'wxd72be21f7455640d';//票付通的ID
    const PFT_APPLY_AID = 21207;//默认供应商账号

    /**
     * 获取加密数据，验证链接失效时用。
     *
     * @param $openid
     * @param $timestamp
     * @return string
     */
    public static function getSignature($openid,$timestamp )
    {
        //token、OpenID、timestamp
        $signature = PRIVATE_TOKEN . $openid . $timestamp;
        return sha1($signature);
    }
    /**
     * 获取绑定的链接
     *
     * @param string $openid openid
     * @param string $desc url描述
     * @param string $hash 所属应用标识
     * @return string
     */
    public static function getBindUrl($openid, $hash, $desc='账号绑定')
    {
        $db = Db::Connect();
        $ret = self::chkBindStatus($openid, $hash,1);
        if ( $ret['fid'] ) {
            $sql = "SELECT account FROM pft_member WHERE id={$ret['fid']} LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $account = $stmt->fetchColumn(0);
            return array('status'=>0, 'msg'=>'您的微信账号已经和平台账号['
                .$account.']绑定。请直接点击“产品预订”或“订单管理”。');
        }
        $tm = strtotime('+5 mins');
        $signature = self::getSignature($openid, $tm);

//        $sql_acc = "SELECT account FROM pft_member WHERE id={$ret['wx_fid']} LIMIT 1";
//        $stmt = $db->prepare($sql_acc);
//        $stmt->execute();
//        $acc = $stmt->fetchColumn(0);
        $url_t = self::GetWxDomain($ret['wx_fid'], $hash);
//        $desc .= $url_t;
        $url = "$url_t/wx/html/login.html?bind=1&"
            . 'openid=' . $openid
            . "&timestamp=" . $tm
            . '&signature=' . $signature
            . '&hash=' . WECHAT_HASH ;
        return array('status'=>1, 'msg'=>"<a href='{$url}'>【{$desc}】</a>");
    }

    public static function GetScenic($appid, $openid, $dev=false)
    {
        //这个公众号是哪个供应商的
        $sql  = "SELECT fid FROM pft_wx WHERE appid=? LIMIT 1";
        $stmt = Db::Connect()->prepare($sql);
        $stmt->execute(array($appid));
        $boss_id  = $stmt->fetchColumn(0);

        if (!$boss_id) {
            return array('status'=>'fail', 'msg'=>'公众号不存在');
        }
        //这个粉丝是不是就是这个供应商
        $sql  = "SELECT fid from uu_wx_member_pft"
            ." WHERE fromusername='$openid' and dstatus=0 LIMIT 1";
        $stmt = Db::Connect()->prepare($sql);
        $stmt->execute();
        $user_fid  = $stmt->fetchColumn(0);
        if (!$dev) {
            if ($boss_id != $user_fid) {
                //若当前粉丝不是供应商，那么判断是不是该供应商的员工
                $sql_relation = "SELECT son_id FROM pft_member_relationship WHERE parent_id=? AND son_id_type=2";
                $stmt = Db::Connect()->prepare($sql_relation);
                $stmt->execute(array($boss_id));
                $son_id_list = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                if (!in_array($user_fid, $son_id_list)) {
                    return array('status'=>'fail', 'msg'=>'验证失败：公众号身份与绑定账号不一致');
                }
            }
        } else {
//            $boss_id = $user_fid;
            $boss_id = 94;
        }
        //获取该供应商的产品
        $sql = "SELECT scenicid_list FROM uu_land WHERE apply_did=$boss_id AND status=1 AND scenicid_list<>''";
        $stmt = Db::Connect()->prepare($sql);
        $stmt->execute();
        $list = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        if (!$list) {
            return array('status'=>'fail', 'msg'=>'您的产品列表中没有添加导览信息');
        }
        $list_str = implode(',', $list);
        $list_arr = array_unique(explode(',', $list_str));
        $list_arr = array_values(array_map('intval', $list_arr));

//        return array('msg'=>json_encode($list_arr));
        $base64_json_list = base64_encode(json_encode($list_arr));

        //到s.12301.cc服务器去生成一个authkey
        $token = file_get_contents('http://s.12301.cc/pft/auth.php?action=AccessToken&key=wx&subkey='.$openid.'&auth=' .md5('RFGrfgY5CjVP8LcYAccessToken'));
        $url = "http://s.12301.cc/pft/tour/saveLatLng.php?code=$base64_json_list&openid=$openid&auth=$token";
        return array(
            'status'=>1,
            'msg'=>"欢迎使用景区导览-保存景点地理位置功能。为保证数据准确，请您打开手机中GPS。点击【<a href='{$url}'>设置经纬度</a>】");

    }


    /**
     * 根据HASH获取绑定过微信公众平台的会员
     * 2015年7月2日11:32:55更新：加入通过开放平台的授权功能
     * @param int $open_plat_form_id
     * @param null $db
     * @return int|string
     */
    static function getMemberIdByHash($open_appid='', $db=null)
    {
        $db = is_null($db) ? Db::Connect() : $db;
        //新版开放平台
        if ($open_appid) {
            $sql = "SELECT fid FROM pft_wx_open WHERE appid=`$open_appid` LIMIT 1";
        }
        else {
            $sql = "SELECT fid FROM pft_wx WHERE id=".WECHAT_HASH." OR hash='".WECHAT_HASH."' LIMIT 1";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute();
        if ($aid=$stmt->fetchColumn(0)) {
            return $aid;
        }
        return self::PFT_APPLY_AID;
    }
    /**
     * 绑定微信账号
     *
     * @param $account
     * @param $pwd
     * @param $openid
     * @return bool
     */
    public static function Bind($account, $fid, $openid, $dname='',
                                $wx_notify=true, $open_appid='', $db=null)
    {
        $db = is_null($db) ? Db::Connect() : $db;
        if (!empty($open_appid)) {
            $chk_res = self::OpenChkBindStatus($openid, $open_appid);
        }
        else {


            $chk_res = self::chkBindStatus($openid, WECHAT_HASH, 1);
//            if ($account==100014){
//                print_r($_SESSION);
//                print_r($chk_res);
//                exit(WECHAT_HASH);
//            }
        }
//        var_dump($chk_res);
        if ($chk_res['id']>0) {
            return array(
                'status'=>'fail',
                'msg'=>'该账号已经绑定，无需重复绑定。ID:' . $chk_res['id']
            );
        }
        else {
            $aid = self::getMemberIdByHash($open_appid, $db);
            if (WECHAT_APPID  != self::PFT_APP_ID && $open_appid != self::PFT_APP_ID)
            {
                //非票付通公众号，检测是否建立供销关系-2015年7月2日12:30:21
                if (!class_exists('go_sql')) {
                    include '/var/www/html/new/conf/le.je';
                    $le = new \go_sql();
                    $le->connect();
                }
//            if (!class_exists('MemberAccount')) {
//                include '/var/www/html/new/d/class/MemberAccount.class.php';
//            }
                $memberObj = new MemberAccount($GLOBALS['le']);
                $memberObj->createRelationship($aid, $fid, 0, 0,'', false);
                $GLOBALS['le']->close();
            }

            $tousername = !empty($open_appid) ? $open_appid : WECHAT_APPID;
            $sql = <<<SQL
INSERT INTO uu_wx_member_pft (fid,aid,account,tousername,fromusername,
createtime,verifycode) VALUES ($fid,$aid,'$account','{$tousername}','{$openid}',
{$_SERVER['REQUEST_TIME']},1)
SQL;
//            echo $sql;exit;
            $data = array(
                'first'=> array('value'=>'微信账号绑定成功','color'=>'#173177'),
                'keyword1'=> array('value'=>$dname,'color'=>'#173177'),
                'keyword2'=> array('value'=>$account,'color'=>'#ff9900'),
                'keyword3'=> array('value'=>'所有','color'=>'#173177'),
                'remark'  => array('value'=>'','color'=>''),
            );
        }
        if ($db->exec($sql)!==false) {
            //TODO::微信通知
            if ($wx_notify) {
                $tplId = TemplateMessage::getTmpId('BIND_MSG');
                $mt = \LaneWeChat\Core\TemplateMessage::sendTemplateMessage($data,
                    $openid, $tplId,
                    '','#FF0000');
            }
            return array('status'=>'ok', 'msg'=>'微信账号绑定成功，绑定账号【'.$account.'】' );
        }
        //TODO::发送微信通知
        return array('status'=>'fail', 'msg'=>'绑定微信账号失败' );
    }

//    public static function chkRelationship($parent_id, $son_id)
//    {
//        $str_chk = "SELECT id,status FROM pft_member_relationship WHERE ".
//            "parent_id={$parent_id} AND son_id={$son_id} LIMIT 1";
//        $stmt = Db::Connect()->prepare($str_chk);
//        $stmt->execute();
//        return $stmt->fetch(\PDO::FETCH_ASSOC);
//    }
    /**
     * 检验用户是否已经绑定微信号
     *
     * @param string $openId  微信OPENID
     * @param int   $hash    公众号HASH
     * @param int   $ret_type
     * @return mixed
     */
    public static function chkBindStatus($openId, $hash, $ret_type=0)
    {
        $db = Db::Connect();
        $column = (!is_numeric($hash) || $hash>99999) ? 'hash' : 'id';
        $sql = "SELECT appid,fid FROM pft_wx WHERE $column='{$hash}' LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $ret = $stmt->fetch(\PDO::FETCH_ASSOC);

        $appid = !$ret['appid'] ? self::PFT_APP_ID : $ret['appid'];

        $sql = "SELECT id,fid,dstatus FROM uu_wx_member_pft"
            ." WHERE fromusername='$openId' AND tousername='{$appid}' LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $row['wx_fid'] = $ret['fid'];
        if (!$ret_type) {
            return  $row['dstatus']==1 ? 0 : $row['fid'] ;
        }
        return $row;
    }

    /**
     * 检测通过开放平台授权的公众号
     *
     * @param string $openId
     * @param string $appId
     * @return array
     */
    public static function OpenChkBindStatus($openId, $appId)
    {
        $db = Db::Connect();
        $sql = "SELECT id,fid,dstatus FROM uu_wx_member_pft"
            ." WHERE fromusername='$openId' AND tousername='{$appId}' LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row;
    }

    /**
     * 粉丝取消关注后删除之前绑定的数据——如果存在的话
     * @param $openId
     */
    public static function RmBind($openId)
    {
        $sql = "SELECT id FROM uu_wx_member_pft WHERE fromusername='$openId' LIMIT 1";
        $stmt = Db::Connect()->prepare($sql);
        $stmt->execute();
        $id=$stmt->fetchColumn(0);
        if ($id) {
            $rm = "DELETE FROM uu_wx_member_pft WHERE id=$id LIMIT 1";
            return Db::Connect()->exec($rm);
        }
        return false;
    }

    /**
     * 设置关键字回复
     *
     * @param $hash
     * @param $key
     * @param $word
     * @return array
     */
    public static function SetKeyWord($hash, $key, $word)
    {
        if (!self::GetKeyWord($hash, $key)) {
            $sql = "INSERT INTO pft_wx_resp (rid,keyword,response,create_time) VALUES('$hash', '$key', '$word',now())";
        } else {
            $sql = "UPDATE pft_wx_resp SET response='$word' WHERE rid=$hash AND keyword='$key' LIMIT 1";
        }
        if (Db::Connect()->exec($sql)!==false ) {
            return array('status'=>'ok', 'msg'=>'添加成功');
        }
        return array('status'=>'fail', 'msg'=>'添加失败,失败信息:' . Db::Connect()->errorInfo());
    }

    /**
     * 根据关键字读取回复信息
     *
     * @param $hash
     * @param $key
     * @return string
     */
    public static function GetKeyWord($hash, $key)
    {
        $sqlChk = "SELECT response FROM pft_wx_resp WHERE rid=$hash AND keyword='$key'";
        $stmt = Db::Connect()->prepare($sqlChk);
        try {
            $stmt->execute();
            return $stmt->fetchColumn(0);
        } catch(\PDOException $e) {
            return $e->getMessage();
        }
    }

    /**35
     * @param $hash
     * @param $where
     * @return array
     */
    public static function GetKeyWordList($hash, $where=null)
    {
        $sqlChk = "SELECT id,keyword,response,create_time FROM pft_wx_resp WHERE rid=?";
        $stmt = Db::Connect()->prepare($sqlChk . $where);
        $stmt->execute(array($hash));
        $rows = array();
        while($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            //关键字列表，过滤默认回复和关注回复
            if ($row['keyword']=='DEFAULT'
                || $row['keyword']=='EVENT_SUBSCRIBE') {
                continue;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * 删除关键字回复
     *
     * @param $id
     * @param $hash
     * @return bool
     */
    public static function RemoveKewWord($id, $hash)
    {
        $sqlRm = "DELETE FROM pft_wx_resp WHERE id=$id AND rid=$hash LIMIT 1";
        if ( Db::Connect()->exec($sqlRm) ) {
            return true;
        }
        return false;
    }

    public static function Login($user, $pwd, $code='')
    {
        $arr = array(
            'username'=>$user, //wx公众帐号
            'pwd'=>md5($pwd), //wx公众帐号密码
            'f'=>'json',
            //'imagecode' => $_POST['code'];
        );

        if (!empty($code)) {
            $arr['imgcode'] = $code;
        }
        $file = dirname(__FILE__).'/cookie/cookie_'.$arr['username'].'.txt';
        $headers = array(
            'User-Agent:Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1700.107 Safari/537.36',
            'Referer:https://mp.weixin.qq.com/',
        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://mp.weixin.qq.com/cgi-bin/login');
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0 );
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2 );
        curl_setopt($curl, CURLOPT_TIMEOUT, 10 );
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($arr));
        curl_setopt($curl, CURLOPT_HTTPHEADER,$headers);

        if (!empty($arr['imgcode'])) {
            curl_setopt($curl, CURLOPT_COOKIEFILE, $file);
        }

        $result = curl_exec ($curl);
        curl_close ( $curl );
        return $result;
    }

    public static function Save($username, $pwd, $fid )
    {
        $sqlChk = "SELECT id,fid,isconnect FROM pft_wx WHERE username='$username' AND isconnect<2 LIMIT 1";
        $stmt = Db::Connect()->prepare($sqlChk);
        $stmt->execute();
        // AND isconnect<2
        $hash = $_SESSION['account'];
        $password = md5(md5($pwd));
        $token = md5($_SERVER['REQUEST_TIME'].'pft'.$username);
        $encodingAESKey = str_shuffle($token . substr($token, 0, 11));
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            if ($row['fid']!=$fid) {
                $sql = "SELECT dname FROM pft_member WHERE id=? LIMIT 1";
                $stmt = Db::Connect()->prepare($sql);
                $stmt->execute(array($row['fid']));
                return array('status'=>'fail', 'msg'=>"该微信公众号({$username})已和票付通账号【".$stmt->fetchColumn(0)."】绑定，您无法重复绑定。");
            } else {
                //update
                $up = $row['isconnect'] == 2 ? 'isconnect=1,' : '';
                $id = $row['id'];
                $sql = <<<SQL
            UPDATE pft_wx SET token='{$token}',$up
            encodingAESKey='{$encodingAESKey}' WHERE id=$id LIMIT 1
SQL;
            }
        } else {
            $sql = <<<SQL
    INSERT INTO pft_wx (fid,`hash`,username,password,token,encodingAESKey,
    create_time) VALUES($fid,'{$hash}','{$username}',
    '{$password}','{$token}','{$encodingAESKey}',now())
SQL;
        }
        if (Db::Connect()->exec($sql)) {
            $ret = $id ? $id : Db::Connect()->lastInsertId();
            return array('status'=>'ok', 'key'=>$ret);
        }
        return false;
    }

    public static function AccountList($fid)
    {
        $domain = "http://{$_SESSION['saccount']}.12301.cc/wx/wechat/iwechat.php?hash=%HASH%";
        $sql = "SELECT id,name,hash,username,ac_type,encodingAESKey,create_time,token,isconnect FROM pft_wx WHERE fid=? AND isconnect<2";
        $stmt = \LaneWeChat\Core\Db::Connect()->prepare($sql);
        $stmt->execute(array($fid));//
        $accounts = array();
        $ac_type_list = array('认证订阅号/普通服务号','认证服务号','普通订阅号');
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $row['url'] = str_replace('%HASH%', $row['id'], $domain);
            $row['ac_type'] = $ac_type_list[$row['ac_type']];
            $accounts[] = $row;
        }
        return $accounts;
    }

    public static function GetWxDomain($fid, $hash)
    {
        //TODO::判断二级域名还没有生成的供应商，给他来一个！
        $url = 'http://%DOMAIN%.12301.cc';
        $sql = "SELECT id,M_domain,M_account_domain FROM pft_member_domain_info WHERE fid=$fid LIMIT 1";
//        return $sql;
        $stmt = Db::Connect()->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
//        return $row;
        if (!$row) {
            $sql_insert = "INSERT INTO pft_member_domain_info(fid,M_domain,M_account_domain,createtime) VALUES({$_SESSION['sid']},'{$hash}','{$hash}', now(),1)";
            Db::Connect()->exec($sql_insert);
            $_hash = $hash;
        } elseif(!$row['M_account_domain']) {
            $sql_update = "UPDATE pft_member_domain_info SET M_account_domain='$hash' WHERE id={$row['id']} LIMIT 1";
            Db::Connect()->exec($sql_update);
            $_hash = $hash;
        } else {
            $_hash = $row['M_account_domain'];
        }
        return str_replace('%DOMAIN%', $_hash, $url);
    }
    public static function GetApiUrl($fid, $hash, $account_id)
    {
        $uri = "/wx/wechat/iwechat.php?hash={$account_id}";
        return self::GetWxDomain($fid, $hash) . $uri;
    }
    public static function CreateAccount($id, $appid, $appsecret, $name, $ac_type, $fid)
    {
        $sql = <<<SQL
        UPDATE pft_wx set appid='$appid',appsecret='$appsecret',name='$name',ac_type=$ac_type
        WHERE id=$id AND fid=$fid limit 1
SQL;
        return Db::Connect()->exec($sql);
//        if (===) {
//            return true;
//        }
//        return false;
    }

    public static function RemoveAccount($id)
    {

        $sql = "SELECT COUNT(*) FROM pft_wx_resp WHERE rid=$id";
        $stmt = Db::Connect()->prepare($sql);
        $stmt->execute();
        if ($cnt = $stmt->fetchColumn(0) > 0) {
            $rm_rf = "DELETE FROM pft_wx_resp WHERE rid=$id LIMIT $cnt";
            Db::Connect()->exec($rm_rf);
        }
        $sql = "SELECT id FROM pft_wx WHERE id=$id LIMIT 1";
        $stmt= Db::Connect()->prepare($sql);
        $stmt->execute();
//        echo $sql;
        if ($stmt->fetchColumn(0)>0) {
//            $rm = "DELETE FROM pft_wx WHERE id=$id LIMIT 1";
            $rm = "UPDATE pft_wx SET isconnect=2 WHERE id=$id LIMIT 1";
//            echo $rm;
            Db::Connect()->exec($rm);
        }
        return true;
    }

    public static function loginByMemberId($memberID, $openID)
    {
        $sql = <<<SQL
SELECT id,dname,cname,dtype,derror,status,errortime,account
FROM pft_member WHERE id=? AND status IN(0,3) LIMIT 1
SQL;
        $stmt = Db::Connect()->prepare($sql);
        $stmt->execute(array($memberID));
        $accInfo = $stmt->fetch(\PDO::FETCH_ASSOC);
        if($accInfo) {
            $sql_up="UPDATE pft_member SET lasttime=now(),derror=0,errortime='0000-00-00 00:00:00' WHERE id={$accInfo['id']} LIMIT 1";
            Db::Connect()->exec($sql_up);
        }

        //修改最后登陆时间
        $sqlUpdateLoginTime = "UPDATE uu_wx_member_pft SET lastlogin=now() WHERE fid=$memberID AND fromusername='$openID' LIMIT 1";
        Db::Connect()->exec($sqlUpdateLoginTime);

        //set session
        $_SESSION['memberID'] = $_SESSION['sid'] = $accInfo['id'];
        $_SESSION['account']  = $_SESSION['saccount']= $accInfo['account'];
        $_SESSION['dname']    = $accInfo['dname'];
        $_SESSION['cname']    = $accInfo['cname'];
        if (ismobile($accInfo['account']) && $accInfo['dtype']==1) {
            $_SESSION['dtype'] = 5;
        } else {
            $_SESSION['dtype'] = $accInfo['dtype'];
        }
        //员工
        if ( $accInfo['dtype']==6 ) {
            $sql_stuff=<<<SQL
SELECT parent_id from pft_member_relationship WHERE son_id_type=2
AND ship_type=1 AND son_id={$accInfo['id']} limit 1
SQL;
            $stmt_stuff = Db::Connect()->prepare($sql_stuff);
            $stmt_stuff->execute();
            $stuff_info = $stmt_stuff->fetch(\PDO::FETCH_ASSOC);
            $_SESSION['sid'] = $stuff_info['parent_id'];
            $sql_boss ="select dname,dtype from pft_member where id={$stuff_info['parent_id']} limit 1";
            $stmt_boss = Db::Connect()->prepare($sql_boss);
            $stmt_boss->execute();
            $boss_info = $stmt_boss->fetch(\PDO::FETCH_ASSOC);
            $_SESSION['sdtype']     = $boss_info['dtype'];
            $_SESSION['sdname']     = $boss_info['dname'];
            $_SESSION['saccount']   = $boss_info['account'];
        }
        return true;
    }

    public static function getAuthInfoByCode($wechat_appid)
    {
        //应该独立出来作为公共的部分
        if (isset($_GET['code']) && !empty($_GET['code'])) {
            $json = OpenWeChatOAuth::getAccessTokenAndOpenId($wechat_appid, $_GET['code']);
//            print_r($json);
            if ($json['errcode']) {
//                mobile_error_redirect("获取用户身份出错{$json['errcode']}");
            }
            $_SESSION['openid'] = $json['openid'];
            //获取分销商ID !$_SESSION['memberID'] ||
            if ( empty($_SESSION['memberID']) ) {
                $sql_get_pid = "SELECT fid FROM uu_wx_member_pft WHERE fromusername='{$json['openid']}' LIMIT 1";
                $stmt =Db::Connect()->prepare($sql_get_pid);
                $stmt->execute();
                $did = $stmt->fetchColumn(0);
                if ($did>0) {
                    //LOGIN By OpenID
                    self::loginByMemberId($did, $json['openid']);
                    return true;
                }
                return false;
            }
        }
        return false;
    }

    public static function CustomServiceConfig() {
        return array(
            111,113,126,
        );
    }

    public static function Log($txt,$file='/var/www/html/wx/logs/wx_log.txt'){
        $fp = fopen($file,"a");
        flock($fp, LOCK_EX);
        fwrite($fp,date("Y-m-d H:i:s").":".$txt."\n");
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    public static function CacheFile($data, $file)
    {
//        $f = fopen('/var/www/html/wx/wechat/access_tokens/access_token_'.WECHAT_APPID, 'w+');
        $f = fopen($file, 'w+');
        fwrite($f, $data);
        fclose($f);
    }
}
?>