<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM\Client;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;

final class Client implements ClientInterface
{
    private array $credentials;

    public function __construct(
        private \Psr\Http\Client\ClientInterface $client,
        private string $clientId,
        private string $clientSecret,
        private string $code,
    ) {
        $this->credentials = $this->generateToken($this->clientId, $this->clientSecret, $this->code);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function upsertContacts(array $body): void
    {
        $response = $this->client->sendRequest(
            new Request(
                method: 'POST',
                uri: 'https://www.zohoapis.eu/crm/v3/Contacts/upsert',
                headers: [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->credentials["access_token"],
                ],
                body: json_encode(['data' => [$body]], JSON_THROW_ON_ERROR),
            )
        );

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Oops, something went wrong while creating Contacts : %s', $response->getReasonPhrase()));
        }
    }

    /**
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function upsertProducts(array $body): void
    {
        $response = $this->client->sendRequest(
            new Request(
                method: 'POST',
                uri: 'https://www.zohoapis.eu/crm/v3/Products/upsert',
                headers: [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '. $this->credentials["access_token"],
                ],
                body: json_encode(['data' => [$body]], JSON_THROW_ON_ERROR),
            )
        );

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Oops, something went wrong while creating Products : %s', $response->getReasonPhrase()));
        }
    }

    /**
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function upsertOrders(array $body): void
    {
        $response = $this->client->sendRequest(
            new Request(
                method: 'POST',
                uri: 'https://www.zohoapis.eu/crm/v3/Sales_Orders/upsert',
                headers: [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer' . $this->credentials["access_token"],
                ],
                body: json_encode(['data' => [$body]], JSON_THROW_ON_ERROR),
            )
        );

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Oops, something went wrong while creating Orders : %s', $response->getReasonPhrase()));
        }
    }

    public function generateToken(string $clientId, string $clientSecret, string $code): array
    {
        $response = $this->client->sendRequest(
            new Request(
                method: 'POST',
                uri: 'https://accounts.zoho.eu/oauth/v2/token?client_id=' . $clientId . '&client_secret=' . $clientSecret . '&code=' . $code . '&grant_type=authorization_code'
            )
        );

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Oops, something went wrong while generating credentials : %s', $response->getReasonPhrase()));
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public function refreshToken(string $clientId, string $clientSecret): string
    {
        $response = $this->client->sendRequest(
            new Request(
                method: 'POST',
                uri: 'https://www.zohoapis.eu/oauth/v2/token?client_id=' . $clientId . '&client_secret=' . $clientSecret . '&refresh_token=' . $this->credentials["refresh_token"] . '&grant_type=refresh_token',
            )
        );

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Oops, something went wrong while refreshing credentials : %s', $response->getReasonPhrase()));
        }

        $credentials = json_decode($response->getBody()->getContents(), true);
        return $credentials["access_token"];
    }
}
