<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Component\Bucket\RejectionResultBucket;
use Kiboko\Component\Flow\ZohoCRM\Client\Client;
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
            if (array_key_exists($this->mappingField, $line)) {
                try {
                    $lookup = $this->cache->get(sprintf('order.%s', $line[$this->mappingField]));

                    if ($lookup === null) {
                        $lookup = $this->client->getOrder(id: $line[$this->mappingField]);

                        $this->cache->set(sprintf('order.%s', $line[$this->mappingField]), $lookup);
                    }
                } catch (\RuntimeException $exception) {
                    $this->logger->warning($exception->getMessage(), ['exception' => $exception, 'item' => $line]);
                    $line = yield new RejectionResultBucket($line);
                    continue;
                }

                $output = ($this->mapper)($lookup, $line);

                $line = yield new AcceptanceResultBucket($output);
            } else {
                $line = yield new AcceptanceResultBucket($line);
            }
        }
    }
}
