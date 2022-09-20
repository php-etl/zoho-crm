<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM;

use com\zoho\api\logger\Levels;
use com\zoho\api\logger\LogBuilder;
use com\zoho\crm\api\InitializeBuilder;
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
                $result = json_decode($exception->getResponse()->getBody()->getContents(), true);

                if ($result['data']['code'] === 'duplicate_data') {
                    $this->client->updateProduct($result['data']['details']['duplicate_record']['id'], $line);
                } else {
                    $this->logger->alert($exception->getMessage(), ['exception' => $exception]);
                }
            } catch (\RuntimeException $exception) {
                $this->logger->alert($exception->getMessage(), ['exception' => $exception]);
            }
        } while ($line = yield new \Kiboko\Component\Bucket\AcceptanceResultBucket($line));
    }
}
