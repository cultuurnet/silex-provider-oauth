# Silex Service Provider OAuth

[![Build Status](https://travis-ci.org/cultuurnet/silex-service-provider-oauth.svg?branch=master)](https://travis-ci.org/cultuurnet/silex-service-provider-oauth)

This is an UiTID [OAuth 1.0](http://tools.ietf.org/html/rfc5849) webservice
authentication provider for the [Silex SecurityServiceProvider](http://silex.sensiolabs.org/doc/providers/security.html).

## Usage

There's a [demo application](https://github.com/cultuurnet/demo-silex-oauth) 
which shows you how to integrate & configure this component.

First register the provider in your Silex application. Supply the base url of 
the desired UiTID API environment, and an OAuth consumer key & secret that are 
allowed to access the UiTID Credentials API.

```php
$app->register(
    new \CultuurNet\SilexServiceProviderOAuth\OAuthServiceProvider(),
    array(
        'oauth.fetcher.base_url' => 'http://acc2.uitid.be',
        'oauth.fetcher.consumer' => array(
            'key' => 'notsosecretkey',
            'secret' => 'verysecret',
        ),
    )
);
```

Define a service named _oauth.model.provider.nonce_provider_ that implements
_CultuurNet\SymfonySecurityOAuth\Model\Provider\NonceProviderInterface_.
The cultuurnet/symfony-security-oauth-redis package provides an implementation
that used Redis for storage. It uses the predis PHP client library for Redis.
However, you are free to use your own implementation for a suitable
storage mechanism.

```php
$app['predis.client'] = $app->share(
    function () {
        return new \Predis\Client('tcp://127.0.0.1:6379');
    }
);

$app['oauth.model.provider.nonce_provider'] = $app->share(
    function (\Silex\Application $app) {
        return new \CultuurNet\SymfonySecurityOAuthRedis\NonceProvider(
            $app['predis.client']
        );
    }
);
```

Then configure a firewall to make use of the _oauth_ authentication provider:

```php
$app->register(
  new \Silex\Provider\SecurityServiceProvider(),
  array(
      'security.firewalls' => array(
          'default' => array(
              'oauth' => true,
           ),
      ),
  )
);
```

For improved performance, you can cache the tokens retrieved from the UiTID 
Credentials API. The best way to do this is by wrapping the original
oauth.model.provider.token_provider service in a decorator that implements the
same interface and takes care of caching. Again, you are free to use your own
implementation for a suitable storage mechanism. The 
cultuurnet/symfony-security-oauth-redis package provides an implementation
that used Redis.
 
```php
$app->extend(
    'oauth.model.provider.token_provider',
    function (
        \CultuurNet\SymfonySecurityOAuth\Model\Provider\TokenProviderInterface $tokenProvider,
        \Silex\Application $app
    ) {
        return new \CultuurNet\SymfonySecurityOAuthRedis\TokenProviderCache(
            $tokenProvider,
            $app['predis.client']
        );
    }
);
```
