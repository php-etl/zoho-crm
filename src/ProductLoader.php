<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM;

use Kiboko\Component\Flow\ZohoCRM\Client\ApiRateExceededException;
use Kiboko\Component\Flow\ZohoCRM\Client\BadRequestException;
use Kiboko\Component\Flow\ZohoCRM\Client\Client;
use Kiboko\Component\Flow\ZohoCRM\Client\ForbiddenException;
use Kiboko\Component\Flow\ZohoCRM\Client\InternalServerErrorException;
use Kiboko\Component\Flow\ZohoCRM\Client\MultiStatusResponseException;
use Kiboko\Component\Flow\ZohoCRM\Client\NotFoundException;
use Kiboko\Component\Flow\ZohoCRM\Client\RequestEntityTooLargeException;
use Kiboko\Contract\Pipeline\LoaderInterface;

final readonly class ProductLoader implements LoaderInterface
{
    public function __construct(private Client $client, private \Psr\Log\LoggerInterface $logger)
    {
    }

    public function load(): \Generator
    {
        $line = yield;
        do {
            try {
                $this->client->upsertProducts($line);
            } catch (InternalServerErrorException|ApiRateExceededException $exception) {
                $this->logger->critical($exception->getMessage(), ['exception' => $exception, 'item' => $line]);

                yield new \Kiboko\Component\Bucket\RejectionResultBucket($line);
            } catch (ForbiddenException|RequestEntityTooLargeException|NotFoundException $exception) {
                $this->logger->error($exception->getMessage(), ['exception' => $exception, 'item' => $line]);
                yield new \Kiboko\Component\Bucket\RejectionResultBucket($line);
            } catch (BadRequestException|MultiStatusResponseException $exception) {
                $this->logger->error($exception->getMessage(), [
                    'response' => $exception->getResponse()->getBody()->getContents(),
                    'item' => $line,
                ]);
                yield new \Kiboko\Component\Bucket\RejectionResultBucket($line);
            }
        } while ($line = yield new \Kiboko\Component\Bucket\AcceptanceResultBucket($line));
    }
}
