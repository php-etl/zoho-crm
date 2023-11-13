<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM\Client;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriFactoryInterface;

class AuthenticationMiddleware implements ClientInterface
{
    public function __construct(
        private readonly ClientInterface $decorated,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly UriFactoryInterface $uriFactory,
        private readonly string $oauthBaseUri,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private string $accessToken,
        private readonly string $refreshToken,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $response = $this->tryRequest($request);
        if (401 === $response->getStatusCode()) {
            $this->refreshToken();

            $response = $this->tryRequest($request);
        }

        return $response;
    }

    private function tryRequest(RequestInterface $request): ResponseInterface
    {
        $request = $request->withHeader('Authorization', sprintf('Bearer %s', $this->accessToken));

        return $this->decorated->sendRequest($request);
    }

    private function refreshToken(): void
    {
        $response = $this->decorated->sendRequest(
            $this->requestFactory->createRequest(
                method: 'POST',
                uri: $this->uriFactory->createUri()
                    ->withQuery(http_build_query([
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret,
                        'refresh_token' => $this->refreshToken,
                        'grant_type' => 'refresh_token',
                    ]))
                    ->withPath('/oauth/v2/token')
                    ->withHost($this->oauthBaseUri)
                    ->withScheme('https')
            )
        );

        if (200 !== $response->getStatusCode()) {
            throw new AccessDeniedException(sprintf('Something went wrong while refreshing your credentials: %d - "%s"', $response->getStatusCode(), $response->getBody()->getContents()));
        }

        $credentials = json_decode($response->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);

        if (\array_key_exists('error', $credentials)) {
            throw new InvalidCodeException('Invalid grant token. Please check your information.');
        }

        $this->accessToken = $credentials['access_token'];
    }
}
