<?php

/*
 * This file is part of the overtrue/wechat.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace EasyWeChat\OfficialAccount\User;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Class ServiceProvider.
 */
class ServiceProvider implements ServiceProviderInterface
{
    /**
     *  注册User类信息，通过该方法，$app的user属性就指向了UserClient类
     * {@inheritdoc}.
     */
    public function register(Container $app)
    {
        $app['user'] = function ($app) {
            return new UserClient($app);
        };

        $app['user_tag'] = function ($app) {
            return new TagClient($app);
        };
    }
}
