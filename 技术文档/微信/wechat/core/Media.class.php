<?php
namespace Library\wechat\core;
/**
 * 多媒体的上传与下载
 * Created by Lane.
 * User: lane
 * Date: 14-8-11
 * Time: 上午9:51
 * E-mail: lixuan868686@163.com
 * WebSite: http://www.lanecn.com
 */
class Media{

    /**
     * 多媒体上传。上传图片、语音、视频等文件到微信服务器，上传后服务器会返回对应的media_id，公众号此后可根据该media_id来获取多媒体。
     * 上传的多媒体文件有格式和大小限制，如下：
     * 图片（image）: 1M，支持JPG格式
     * 语音（voice）：2M，播放长度不超过60s，支持AMR\MP3格式
     * 视频（video）：10MB，支持MP4格式
     * 缩略图（thumb）：64KB，支持JPG格式
     * 媒体文件在后台保存时间为3天，即3天后media_id失效。
     *
     * @param $filename，文件绝对路径
     * @param $type, 媒体文件类型，分别有图片（image）、语音（voice）、视频（video）和缩略图（thumb）
     * @return {"type":"TYPE","media_id":"MEDIA_ID","created_at":123456789}
     */
    public static function upload($filename, $type, $appid = ''){

        $appid = $appid ?: PFT_WECHAT_APPID;
        //获取ACCESS_TOKEN
        $accessToken = OpenAccessToken::getAccessToken($appid);

        $queryUrl = 'http://file.api.weixin.qq.com/cgi-bin/media/upload?access_token='.$accessToken.'&type='.$type;
        $data = array();

        if (version_compare(PHP_VERSION, '5.6')) {
            $data['media'] = new \CURLFILE($filename);
        } else {
            $data['media'] = '@'.$filename;
        }
        
        return Curl::callWebServer($queryUrl, $data, 'POST', 1 , 0);
    }

    /**
     * 上传图片，永久保存
     * @param $filename 文件
     * @return 头信息如下
     *
     * HTTP/1.1 200 OK
     * Connection: close
     * Content-Type: image/jpeg
     * Content-disposition: attachment; filename="MEDIA_ID.jpg"
     * Date: Sun, 06 Jan 2013 10:20:18 GMT
     * Cache-Control: no-cache, must-revalidate
     * Content-Length: 339721
     */
    public static function uploadimg($filename){
        //获取ACCESS_TOKEN
        $accessToken = AccessToken::getAccessToken();
        $queryUrl = 'https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token='.$accessToken;
        $data = array();

        if (version_compare(PHP_VERSION, '5.6')) {
            $data['media'] = new \CURLFILE($filename);
        } else {
            $data['media'] = '@'.$filename;
        }

        return Curl::callWebServer($queryUrl, $data, 'POST', 1 , 0);
    }

    /**
     * 下载多媒体文件
     * @param $mediaId 多媒体ID
     * @return 头信息如下
     *
     * HTTP/1.1 200 OK
     * Connection: close
     * Content-Type: image/jpeg
     * Content-disposition: attachment; filename="MEDIA_ID.jpg"
     * Date: Sun, 06 Jan 2013 10:20:18 GMT
     * Cache-Control: no-cache, must-revalidate
     * Content-Length: 339721
     * curl -G "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token=ACCESS_TOKEN&media_id=MEDIA_ID"
     */
    public static function download($mediaId){
        //获取ACCESS_TOKEN
        $accessToken = AccessToken::getAccessToken();
        $queryUrl = 'http://file.api.weixin.qq.com/cgi-bin/media/get?access_token='.$accessToken.'&media_id='.$mediaId;
        return Curl::callWebServer($queryUrl, '', 'GET', 0);
    }


    public  function get_mediaid($img_url,$type='image'){
        $accessToken = AccessToken::getAccessToken();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"http://file.api.weixin.qq.com/cgi-bin/media/upload?access_token=$accessToken&type=$type");
        curl_setopt($ch, CURLOPT_POST, 1 );
        curl_setopt($ch, CURLOPT_POSTFIELDS, "media=@/var/www/html/wx/wechat/images/14206891529091.png");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $s = curl_exec ($ch);
        file_put_contents('/var/www/html/new/d/module/zax/mark.txt',$s.PHP_EOL,FILE_APPEND);
        return $s;
    }
}