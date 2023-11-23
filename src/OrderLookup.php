<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Component\Bucket\EmptyResultBucket;
use Kiboko\Component\Bucket\RejectionResultBucket;
use Kiboko\Component\Flow\ZohoCRM\Client\ApiRateExceededException;
use Kiboko\Component\Flow\ZohoCRM\Client\BadRequestException;
use Kiboko\Component\Flow\ZohoCRM\Client\Client;
use Kiboko\Component\Flow\ZohoCRM\Client\ForbiddenException;
use Kiboko\Component\Flow\ZohoCRM\Client\InternalServerErrorException;
use Kiboko\Component\Flow\ZohoCRM\Client\NoContentException;
use Kiboko\Component\Flow\ZohoCRM\Client\NotFoundException;
use Kiboko\Component\Flow\ZohoCRM\Client\RequestEntityTooLargeException;
use Kiboko\Contract\Mapping\CompiledMapperInterface;
use Kiboko\Contract\Pipeline\TransformerInterface;
use Psr\SimpleCache\CacheInterface;

final readonly class OrderLookup implements TransformerInterface
{
    public function __construct(
        private Client $client,
        private \Psr\Log\LoggerInterface $logger,
        private CacheInterface $cache,
        private CompiledMapperInterface $mapper,
        private string $subjectMappingField,
        private string $storeMappingField,
    ) {
    }

    public function transform(): \Generator
    {
        $line = yield new EmptyResultBucket();

        /* @phpstan-ignore-next-line */
        while (true) {
            try {
                $encodingKey = base64_encode(sprintf('order.%s.%s', $line[$this->subjectMappingField], $line[$this->storeMappingField]));
                $lookup = $this->cache->get($encodingKey);

                if (null === $lookup) {
                    $lookup = $this->client->searchOrder(subject: $line[$this->subjectMappingField], store: $line[$this->storeMappingField]);

                    $this->cache->set($encodingKey, $lookup);
                }
            } catch (ApiRateExceededException|InternalServerErrorException $exception) {
                $this->logger->critical($exception->getMessage(), ['exception' => $exception, 'item' => $line]);
                yield new RejectionResultBucket(
                    $exception->getMessage(),
                    $exception,
                    $line
                );
            } catch (BadRequestException|ForbiddenException|NoContentException|NotFoundException|RequestEntityTooLargeException $exception) {
                $this->logger->error($exception->getMessage(), ['exception' => $exception, 'item' => $line]);
                $line = yield new RejectionResultBucket(
                    $exception->getMessage(),
                    $exception,
                    $line
                );
                continue;
            }

            $output = ($this->mapper)($lookup, $line);

            $line = yield new AcceptanceResultBucket($output);
        }
    }
}
