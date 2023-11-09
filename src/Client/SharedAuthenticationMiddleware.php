<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM\Client;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriFactoryInterface;

class SharedAuthenticationMiddleware
{
    private static ?AuthenticationMiddleware $client = null;
    private function __construct(
        private readonly ClientInterface $decorated,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly UriFactoryInterface $uriFactory,
        private readonly string $oauthBaseUri,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $accessToken,
        private readonly string $refreshToken,
    ) {
        self::$client = new AuthenticationMiddleware(
            $this->decorated, $this->requestFactory,
            $this->uriFactory, $this->oauthBaseUri,
            $this->clientId, $this->clientSecret,
            $this->accessToken, $this->refreshToken
        );
    }

    public static function getInstance(
        ClientInterface $decorated,
        RequestFactoryInterface $requestFactory,
        UriFactoryInterface $uriFactory,
        string $oauthBaseUri,
        string $clientId,
        string $clientSecret,
        string $accessToken,
        string $refreshToken,
    ): AuthenticationMiddleware
    {
        if (self::$client === null) {
            new SharedAuthenticationMiddleware($decorated, $requestFactory, $uriFactory, $oauthBaseUri, $clientId, $clientSecret, $accessToken, $refreshToken);
        }
        return self::$client;
    }
}
