<?php

/*
 * This file is part of the overtrue/wechat.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace EasyWeChat\MiniProgram\TemplateMessage;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

//服务提供者，必须实现 ServiceProviderInterface的 register方法
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * 该注册方法会给容器增加属性 template_message，返回闭包
     * 同时会调用offSet($id, $value)方法给容器Pimple的属性values keys赋值
     * 这里用的闭包，没有实例化真正提供实际功能的类，但已经完成了服务提供者的注册工作
     * {@inheritdoc}.
     */
    public function register(Container $app)
    {
        $app['template_message'] = function ($app) {
            return new Client($app);
        };
    }
}
