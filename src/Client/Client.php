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
                    ->withPath('/crm/v3/Products/upsert')
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
                    ->withPath('/crm/v3/Sales_Orders/upsert')
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
    public function getProducts(): array
    {
        $response = $this->client->sendRequest(
            $this->requestFactory->createRequest(
                'GET',
                $this->uriFactory->createUri()
                    ->withPath('/crm/v3/Products')
                    ->withQuery(http_build_query([
                        'fields' => ['id', 'Product_Code']
                    ]))
                    ->withHost($this->host)
                    ->withScheme('https')
            )
        );

        $this->processResponse($response);

        $result = json_decode($response->getBody()->getContents(), true);
        return $result["data"];
    }

    /**
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function getContacts(): array
    {
        $response = $this->client->sendRequest(
            $this->requestFactory->createRequest(
                'GET',
                $this->uriFactory->createUri()
                    ->withPath('/crm/v3/Contacts')
                    ->withQuery(http_build_query([
                        'fields' => ['id', 'Email']
                    ]))
                    ->withHost($this->host)
                    ->withScheme('https')
            )
        );

        $this->processResponse($response);

        $result = json_decode($response->getBody()->getContents(), true);
        return $result["data"];
    }

    private function processResponse(ResponseInterface $response): void
    {
        if ($response->getStatusCode() === 400) {
            throw new BadRequestException('The format of the request is not correct. Please check the information sent.');
        }

        if ($response->getStatusCode() === 403) {
            throw new ForbiddenException('You do not have the right to make this request. Please login before making your request or verify your rights.');
        }

        if ($response->getStatusCode() === 404) {
            throw new NotFoundException('What you are looking for does not exist. Please check your request.');
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
