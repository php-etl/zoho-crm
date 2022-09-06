<?php

declare(strict_types=1);

namespace Kiboko\ZohoCRM\Loader;

use Kiboko\Component\Flow\ZohoCRM\Client\Client;
use Kiboko\Contract\Pipeline\LoaderInterface;

final class OrderLoader implements LoaderInterface
{
    public function __construct(private Client $client, private readonly \Psr\Log\LoggerInterface $logger)
    {
    }

    public function load(): \Generator
    {
        $line = yield;
        do {
            try {
                $this->client->upsertOrders($line);
            } catch (\RuntimeException $exception) {
                $this->logger->alert($exception->getMessage(), ['exception' => $exception]);
            }
        } while ($line = yield new \Kiboko\Component\Bucket\AcceptanceResultBucket($line));
    }
}
