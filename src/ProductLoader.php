<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM;

use Kiboko\Component\Flow\ZohoCRM\Client\BadRequestException;
use Kiboko\Component\Flow\ZohoCRM\Client\Client;
use Kiboko\Contract\Pipeline\LoaderInterface;

final class ProductLoader implements LoaderInterface
{
    public function __construct(private Client $client, private readonly \Psr\Log\LoggerInterface $logger)
    {
    }

    public function load(): \Generator
    {
        $line = yield;
        do {
            try {
                $this->client->insertProduct($line);
            } catch (BadRequestException $exception) {
                $this->updateProduct($exception, $line);
            } catch (\RuntimeException $exception) {
                $this->logger->alert($exception->getMessage(), ['exception' => $exception]);
            }
        } while ($line = yield new \Kiboko\Component\Bucket\AcceptanceResultBucket($line));
    }

    public function updateProduct(BadRequestException $exception, array $line): void
    {
        $result = json_decode($exception->getResponse()->getBody()->getContents(), true);

        if ($result['data'][0]['code'] === 'DUPLICATE_DATA') {
            try {
                $this->client->updateProduct($result['data'][0]['details']['duplicate_record']['id'], $line);
            } catch (\RuntimeException $exception) {
                $this->logger->alert($exception->getMessage(), ['exception' => $exception]);
            }
        } else {
            $this->logger->alert($exception->getMessage(), ['exception' => $exception]);
        }
    }
}
