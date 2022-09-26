<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Component\Bucket\RejectionResultBucket;
use Kiboko\Component\Flow\ZohoCRM\Client\Client;
use Kiboko\Component\Flow\ZohoCRM\Client\NoContentException;
use Kiboko\Contract\Mapping\CompiledMapperInterface;
use Kiboko\Contract\Pipeline\TransformerInterface;
use Psr\SimpleCache\CacheInterface;

final class GetOrderLookup implements TransformerInterface
{
    public function __construct(
        private Client $client,
        private readonly \Psr\Log\LoggerInterface $logger,
        private CacheInterface $cache,
        private CompiledMapperInterface $mapper,
        private string $mappingField,
    ) {
    }

    public function transform(): \Generator
    {
        $line = yield;
        while (true) {
            try {
                $lookup = $this->cache->get(sprintf('order.%s', $line[$this->mappingField]));

                if ($lookup === null) {
                    $lookup = $this->client->searchOrder(subject: $line[$this->mappingField]);

                    $this->cache->set(sprintf('order.%s', $line[$this->mappingField]), $lookup);
                }

                $lookup = $this->client->getOrder(id: $lookup['id']);
                $output = ($this->mapper)($lookup, $line);

                $line = yield new AcceptanceResultBucket($output);
            } catch (NoContentException $exception) {
                $line = yield new AcceptanceResultBucket($line);
            } catch (\RuntimeException $exception) {
                $this->logger->warning($exception->getMessage(), ['exception' => $exception, 'item' => $line]);
                $line = yield new RejectionResultBucket($line);
                continue;
            }
        }
    }
}
