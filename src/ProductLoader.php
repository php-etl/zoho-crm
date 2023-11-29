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

final readonly class ProductLoader implements LoaderInterface
{
    public function __construct(
        private Client $client,
        private \Psr\Log\LoggerInterface $logger,
    )
    {
    }

    public function load(): \Generator
    {
        $line = yield new EmptyResultBucket();

        do {
            try {
                $this->client->upsertProducts($line);
            } catch (ApiRateExceededException|InternalServerErrorException $exception) {
                $this->logger->critical($exception->getMessage(), ['exception' => $exception, 'item' => $line]);

                yield new \Kiboko\Component\Bucket\RejectionResultBucket(
                    'It seems that the API request limit has been reached or that there is a problem with the server. Please, retry later.',
                    $exception,
                    $line
                );
            } catch (ForbiddenException|NotFoundException $exception) {
                $this->logger->error($exception->getMessage(), ['exception' => $exception, 'item' => $line]);
                $line = yield new \Kiboko\Component\Bucket\RejectionResultBucket(
                    'It seems that the resource does not exist or that you do not have the rights to access this resource. Please check your rights and try again.',
                    $exception,
                    $line
                );
                continue;
            } catch (BadRequestException|RequestEntityTooLargeException $exception) {
                $this->logger->error($exception->getMessage(), [
                    'response' => $exception->getResponse()->getBody()->getContents(),
                    'item' => $line,
                ]);
                $line = yield new \Kiboko\Component\Bucket\RejectionResultBucket(
                    'It seems that the format of the request is not correct or it is too long. Please check your request and try again.',
                    $exception,
                    $line
                );
                continue;
            } catch (MultiStatusResponseException $exception) {
                $this->logger->error($exception->getMessage(), [
                    'response' => $exception->getResponse()->getBody()->getContents(),
                    'item' => $line,
                ]);
                $line = yield new \Kiboko\Component\Bucket\RejectionResultBucket(
                    'It seems that one of the resources in the request has failed. Please check your request and try again.',
                    $exception,
                    $line
                );
                continue;
            }
        } while ($line = yield new \Kiboko\Component\Bucket\AcceptanceResultBucket($line));
    }
}
