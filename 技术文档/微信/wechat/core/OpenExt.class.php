<?php
namespace Library\wechat\core;

use Model\Wechat\WxMember;
use pft\Member\MemberAccount;

class OpenExt {

    const PFT_APP_ID    = 'wxd72be21f7455640d';//票付通的ID
    const PFT_APPLY_AID = 22647;//默认供应商账号

    public static $model = null;
    public static function getModel()
    {
        if (is_null(self::$model)) {
            self::$model = new WxMember();
        }
        return self::$model;
    }

    public static function GetDefaultAid()
    {
        $aid = OpenAccessToken::getCache()->get('config.PFT_AID');
//        $aid = 0;
        if (!$aid) {
            $info = OpenAccessToken::getModel()->getBindInfo(self::PFT_APP_ID);
            OpenAccessToken::getCache()->set('config.PFT_AID', $info['fid']);
        }
        return $aid;
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
     *
     * @param $open_appid
     * @return int
     */
    static function getMid($open_appid)
    {
        $info = OpenAccessToken::getModel()->getBindInfo($open_appid);
        if ($info) {
            return $info['fid'];
        }
        return self::GetDefaultAid();
    }

    /**
     * 绑定微信账号
     *
     * @param $account
     * @param $fid
     * @param $openid
     * @param string $dname
     * @param bool $wx_notify
     * @param $open_appid
     * @param \PDO $db
     * @return array
     */
    public static function Bind($account, $fid, $aid, $openid, $dname='',
                                $wx_notify=true, $open_appid, \PDO $db)
    {
        $chk_res = self::OpenChkBindStatus($openid, $open_appid);
        if ($chk_res['id']>0) {
            return array(
                'status'=>'fail',
                'msg'=>'该账号已经绑定，无需重复绑定。ID:' . $chk_res['id']
            );
        }

        $aid = !$aid ? self::getMid($open_appid) : $aid;
        $lastid = self::getModel()->saveWxMemberInfo($fid, $aid, $account, $openid, $open_appid);
        $data = array(
            'first'=> array('value'=>'微信账号绑定成功','color'=>'#173177'),
            'keyword1'=> array('value'=>$dname,'color'=>'#173177'),
            'keyword2'=> array('value'=>$account,'color'=>'#ff9900'),
            'keyword3'=> array('value'=>'所有','color'=>'#173177'),
            'remark'  => array('value'=>'','color'=>''),
        );

        if ($lastid !==false) {
            //TODO::微信通知
            if ($wx_notify) {
                $tplId = TemplateMessage::getTmpId('BIND_MSG', $open_appid);
                \LaneWeChat\Core\TemplateMessage::openSendTemplateMessage($data,
                    $openid, $tplId, '','#FF0000', $open_appid);
            }
            return array('status'=>'ok', 'msg'=>'微信账号绑定成功，绑定账号【'.$account.'】' );
        }
        //TODO::发送微信通知
        return array('status'=>'fail', 'msg'=>'绑定微信账号失败' );
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
        $row = self::getModel()->getWxInfo(0, $appId, false, $openId);
        return $row!==false ? array_shift( $row ) : false;
    }

    /**
     * 粉丝取消关注后删除之前绑定的数据——如果存在的话
     *
     * @param $openId
     * @return bool
     */
    public static function RmBind($openId)
    {
        return self::getModel()->RmWxMember($openId);
    }

    /**
     * 设置关键字回复
     *
     * @param $appid
     * @param $key
     * @param $word
     * @return array
     */
    public static function SetKeyWord($appid, $key, $word, $msg_type=1)
    {
        if (!self::GetKeyWord($appid, $key)) {
            $sql = "INSERT INTO pft_wx_resp (appid,keyword,response,msg_type,create_time) VALUES('$appid', '$key', '$word', $msg_type ,now())";
        } else {
            $sql = "UPDATE pft_wx_resp SET response='$word',msg_type=$msg_type,status=0 WHERE appid='$appid' AND keyword='$key' LIMIT 1";
        }
//        return array('status'=>'ok', 'msg'=>'添加成功');
        if (\PFT\Db::Connect()->exec($sql)!==false ) {
            return array('status'=>'ok', 'msg'=>'添加成功');
        }
        return array('status'=>'fail', 'msg'=>'添加失败,失败信息:' . implode(',', Db::Connect()->errorInfo()));
    }


    /**
     * 根据关键字读取回复信息
     *
     * @param $appid
     * @param $key
     * @return string
     */
    public static function GetKeyWord($appid, $key)
    {
        $sqlChk = "SELECT response,msg_type FROM pft_wx_resp WHERE appid='$appid' AND keyword='$key' AND status=0 LIMIT 1";
        $stmt = \PFT\Db::Connect()->prepare($sqlChk);
        try {
            $stmt->execute();
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch(\PDOException $e) {
            return $e->getMessage();
        }
    }

    /**
     * 获取回复
     *
     * @param $appid
     * @param $where
     * @return array
     */
    public static function GetKeyWordList($appid, $where=null)
    {
        $sqlChk = "SELECT id,keyword,response,create_time FROM pft_wx_resp WHERE appid=?";
        $stmt = \PFT\Db::Connect()->prepare($sqlChk . $where);
        $stmt->execute(array($appid));
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
     * @param $appid
     * @return bool
     */
    public static function RemoveKewWord($id, $appid)
    {
        $sqlRm = "DELETE FROM pft_wx_resp WHERE id=$id AND appid='{$appid}' LIMIT 1";
        if ( \PFT\Db::Connect()->exec($sqlRm) ) {
            return true;
        }
        return false;
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
            'wxd72be21f7455640d',//票付通
            'wxa544a548c904b9b3',//魔瑞水世界
            'wxe408a552f551ca5c',//爱读书
            'wx737a67e4e0e42334',//游便宜V
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