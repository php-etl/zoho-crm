<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM\Client;

use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

class Client implements ClientInterface
{
    public function __construct(
        private string $host,
        private PsrClientInterface $client,
        private RequestFactoryInterface $requestFactory,
        private UriFactoryInterface $uriFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function upsertContacts(array $body): void
    {
        $response = $this->client->sendRequest(
            $this->requestFactory->createRequest(
                'POST',
                $this->uriFactory->createUri()
                    ->withPath('/crm/v3/Contacts/upsert')
                    ->withHost($this->host)
                    ->withScheme('https')
            )
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode(['data' => [$body]], JSON_THROW_ON_ERROR)))
        );

        $this->processResponse($response);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function upsertProducts(array $body): void
    {
        $response = $this->client->sendRequest(
            $this->requestFactory->createRequest(
                'POST',
                $this->uriFactory->createUri()
                    ->withPath('crm/v3/Products/upsert')
                    ->withHost($this->host)
                    ->withScheme('https')
            )
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode(['data' => [$body]], JSON_THROW_ON_ERROR)))
        );

        $this->processResponse($response);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function upsertOrders(array $body): void
    {
        $response = $this->client->sendRequest(
            $this->requestFactory->createRequest(
                'POST',
                $this->uriFactory->createUri()
                    ->withPath('crm/v3/Sales_Orders/upsert')
                    ->withHost($this->host)
                    ->withScheme('https')
            )
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream(json_encode(['data' => [$body]], JSON_THROW_ON_ERROR)))
        );

        $this->processResponse($response);
    }

    private function processResponse(ResponseInterface $response): void
    {
        if ($response->getStatusCode() === 400) {
            throw new BadRequestException('Please check the information sent.');
        }

        if ($response->getStatusCode() === 403) {
            throw new ForbiddenException('Please login before making your request.');
        }

        if ($response->getStatusCode() === 413) {
            throw new RequestEntityTooLargeException('The maximum size limit of the request has been exceeded. Please check the data sent.');
        }

        if ($response->getStatusCode() === 429) {
            throw new ApiRateExceededException('The request rate limit has been exceeded. Please try again later.');
        }

        if ($response->getStatusCode() === 500) {
            throw new InternalServerErrorException('The server encountered an unexpected error. Please try again later');
        }
    }
}
