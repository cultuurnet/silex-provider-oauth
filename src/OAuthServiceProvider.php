<?php
/**
 * Created by PhpStorm.
 * User: nicolas
 * Date: 29/09/15
 * Time: 12:45
 */

namespace CultuurNet\SilexServiceProviderOAuth;

use CultuurNet\Auth\ConsumerCredentials;
use CultuurNet\Clock\SystemClock;
use CultuurNet\SymfonySecurityOAuth\EventListener\OAuthRequestListener;
use CultuurNet\SymfonySecurityOAuth\Security\OAuthAuthenticationProvider;
use CultuurNet\SymfonySecurityOAuth\Security\OAuthListener;
use CultuurNet\SymfonySecurityOAuth\Service\OAuthServerService;
use CultuurNet\SymfonySecurityOAuth\Service\Signature\OAuthHmacSha1Signature;
use CultuurNet\SymfonySecurityOAuthUitid\ConsumerProvider;
use CultuurNet\SymfonySecurityOAuthUitid\TokenProvider;
use CultuurNet\UitidCredentials\UitidCredentialsFetcher;
use Silex\Application;
use Silex\ServiceProviderInterface;

class OAuthServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['oauth.fetcher'] = $app->share(function () use ($app) {
            $baseUrl = $app['oauth.fetcher.base_url'];
            $consumer = $app['oauth.fetcher.consumer'];
            $consumerkey = $consumer['key'];
            $consumersecret = $consumer['secret'];

            $consumerCredentials = new ConsumerCredentials($consumerkey, $consumersecret);

            return new UitidCredentialsFetcher($baseUrl, $consumerCredentials);
        });

        $app['oauth.model.provider.consumer_provider'] = $app->share(function ($app) {
            return new ConsumerProvider($app['oauth.fetcher']);
        });

        $app['oauth.model.provider.token_provider'] = $app->share(function ($app) {
            return new TokenProvider($app['oauth.fetcher']);
        });

        $app['clock'] = $app->share(function ($app) {
            return new SystemClock(new \DateTimeZone('Europe/Brussels'));
        });

        $app['oauth.service.oauth_server_service'] = $app->share(function () use ($app) {
            $consumerProvider = $app['oauth.model.provider.consumer_provider'];
            $tokenProvider = $app['oauth.model.provider.token_provider'];
            $nonceProvider = $app['oauth.model.provider.nonce_provider'];
            $clock = $app['clock'];
            $serverService =  new OAuthServerService($consumerProvider, $tokenProvider, $nonceProvider, $clock);
            $hmacsha1Service = new OAuthHmacSha1Signature();
            $serverService->addSignatureService($hmacsha1Service);

            return $serverService;
        });

        $app['security.authentication_listener.factory.oauth'] = $app->protect(function ($name, $options) use ($app) {
            // define the authentication provider object
            $app['security.authentication_provider.'.$name.'.oauth'] = $app->share(function () use ($app, $name) {
                return new OAuthAuthenticationProvider(
                    $app['security.user_provider.' . $name],
                    $app['oauth.service.oauth_server_service'] //__DIR__.'/security_cache',
                );
            });

            // define the authentication listener object
            $app['security.authentication_listener.'.$name.'.oauth'] = $app->share(function () use ($app) {
                // use 'security' instead of 'security.token_storage' on Symfony <2.6
                return new OAuthListener($app['security.token_storage'], $app['security.authentication_manager']);
            });

            return array(
                // the authentication provider id
                'security.authentication_provider.'.$name.'.oauth',
                // the authentication listener id
                'security.authentication_listener.'.$name.'.oauth',
                // the entry point id
                null,
                // the position of the listener in the stack
                'pre_auth'
            );
        });

        $app['oauth.request_listener'] = $app->share(function () {
            return new OAuthRequestListener();
        });

        $app['dispatcher']->addListener(
            'kernel.request',
            array(
                $app['oauth.request_listener'],
                'onEarlyKernelRequest'),
            255
        );

    }

    public function boot(Application $app)
    {
    }
}
