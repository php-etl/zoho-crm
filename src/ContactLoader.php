<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM;

use Kiboko\Component\Bucket\EmptyResultBucket;
use Kiboko\Component\Flow\ZohoCRM\Client\ApiRateExceededException;
use Kiboko\Component\Flow\ZohoCRM\Client\BadRequestException;
use Kiboko\Component\Flow\ZohoCRM\Client\Client;
use Kiboko\Component\Flow\ZohoCRM\Client\ForbiddenException;
use Kiboko\Component\Flow\ZohoCRM\Client\InternalServerErrorException;
use Kiboko\Component\Flow\ZohoCRM\Client\MultiStatusResponseException;
use Kiboko\Component\Flow\ZohoCRM\Client\NotFoundException;
use Kiboko\Component\Flow\ZohoCRM\Client\RequestEntityTooLargeException;
use Kiboko\Contract\Pipeline\LoaderInterface;

readonly class ContactLoader implements LoaderInterface
{
    public function __construct(
        private Client $client,
        private \Psr\Log\LoggerInterface $logger,
    ) {}

    public function load(): \Generator
    {
        $line = yield new EmptyResultBucket();
        do {
            try {
                $this->client->upsertContacts($line);
            } catch (ApiRateExceededException|InternalServerErrorException $exception) {
                $this->logger->critical($exception->getMessage(), ['exception' => $exception, 'item' => $line]);

                $line = yield new \Kiboko\Component\Bucket\RejectionResultBucket(
                    $exception->getMessage(),
                    $exception,
                    $line
                );
                continue;
            } catch (ForbiddenException|NotFoundException|RequestEntityTooLargeException $exception) {
                $this->logger->error($exception->getMessage(), ['exception' => $exception, 'item' => $line]);
                $line = yield new \Kiboko\Component\Bucket\RejectionResultBucket(
                    $exception->getMessage(),
                    $exception,
                    $line
                );
                continue;
            } catch (BadRequestException|MultiStatusResponseException $exception) {
                $this->logger->error($exception->getMessage(), [
                    'response' => $exception->getResponse()->getBody()->getContents(),
                    'item' => $line,
                ]);
                $line = yield new \Kiboko\Component\Bucket\RejectionResultBucket(
                    $exception->getMessage(),
                    $exception,
                    $line
                );
                continue;
            } catch (\Throwable $exception) {
                $this->logger->error($exception->getMessage(), ['item' => $line]);
                $line = yield new \Kiboko\Component\Bucket\RejectionResultBucket(
                    $exception->getMessage(),
                    $exception,
                    $line
                );
                continue;
            }
        } while ($line = yield new \Kiboko\Component\Bucket\AcceptanceResultBucket($line));
    }
}
