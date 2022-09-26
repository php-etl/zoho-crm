<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM;

use Kiboko\Component\Flow\ZohoCRM\Client\ApiRateExceededException;
use Kiboko\Component\Flow\ZohoCRM\Client\BadRequestException;
use Kiboko\Component\Flow\ZohoCRM\Client\Client;
use Kiboko\Component\Flow\ZohoCRM\Client\ForbiddenException;
use Kiboko\Component\Flow\ZohoCRM\Client\InternalServerErrorException;
use Kiboko\Component\Flow\ZohoCRM\Client\NotFoundException;
use Kiboko\Component\Flow\ZohoCRM\Client\RequestEntityTooLargeException;
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
            } catch (InternalServerErrorException|ApiRateExceededException $exception) {
                $this->logger->critical($exception->getMessage(), ['exception' => $exception]);
                return;
            } catch (BadRequestException|ForbiddenException|RequestEntityTooLargeException|NotFoundException $exception) {
                $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            }
        } while ($line = yield new \Kiboko\Component\Bucket\AcceptanceResultBucket($line));
    }
}
