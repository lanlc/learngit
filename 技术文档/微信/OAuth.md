##### 最近稍微接触微信的开发，熟悉了自动授权的流程，步骤如下：
- 获取code值：```https://open.weixin.qq.com/connect/oauth2/authorize?appid=APPID&redirect_uri=REDIRECT_URI&response_type=code&scope=SCOPE&state=STATE#wechat_redirect```
    1. appid=公众号的唯一标识,redirect_uri=授权后重定向的回调链接地址,response_type=code,scope=应用授权作用域,state=自定义参数，原样回传，#wechat_redirect=直接在微信打开链接，可以不填此参数。做页面302重定向时候，必须带此参数
    2. 顺序要一致，否则无法认证
    3. 认证成功后，会跳转redirect_uri,带code值和state参数
- 根据code值获取token和openId: ```https://api.weixin.qq.com/sns/oauth2/access_token?appid=APPID&secret=SECRET&code=CODE&grant_type=authorization_code```
    1. appid=公众号的唯一标识,secret=公众号的appsecret,code=上面获取的code参数，grant_type=authorization_code
    2. 认证成功后，会返回JSON格式的数据，包含 access_token、expires_in(超时时间)、refresh_token、openid、scope
- 这个时候就可以根据openid去做文章了，当然还可以根据openid去获取到用户的信息

- 以上操作都是在用户关注了该公众号的前提下进行操作的