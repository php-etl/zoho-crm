<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM\Client;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

class SharedAuthenticationMiddleware
{
    private static ?AuthenticationMiddleware $instance = null;

    public static function getInstance(
        ClientInterface $decorated,
        RequestFactoryInterface $requestFactory,
        UriFactoryInterface $uriFactory,
        string $oauthBaseUri,
        string $clientId,
        string $clientSecret,
        string $accessToken,
        string $refreshToken,
    ): AuthenticationMiddleware {
        if (null === self::$instance) {
            self::$instance = new AuthenticationMiddleware($decorated, $requestFactory, $uriFactory, $oauthBaseUri, $clientId, $clientSecret, $accessToken, $refreshToken);
        }

        return self::$instance;
    }
}
