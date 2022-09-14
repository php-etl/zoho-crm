<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Component\Bucket\RejectionResultBucket;
use Kiboko\Component\Flow\ZohoCRM\Client\Client;
use Kiboko\Contract\Mapping\CompiledMapperInterface;
use Kiboko\Contract\Pipeline\TransformerInterface;
use Psr\SimpleCache\CacheInterface;

final class ContactLookup implements TransformerInterface
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
                $lookup = $this->cache->get(sprintf('contact.%s', $line[$this->mappingField]));

                if ($lookup === null) {
                    $lookup = $this->client->searchContact(email: $line[$this->mappingField]);

                    $this->cache->set(sprintf('contact.%s', $line[$this->mappingField]), $lookup);
                }
            } catch (\RuntimeException $exception) {
                $this->logger->warning($exception->getMessage(), ['exception' => $exception, 'item' => $line]);
                $line = yield new RejectionResultBucket($line);
                continue;
            }

            $output = ($this->mapper)($lookup, $line);

            $line = yield new AcceptanceResultBucket($output);
        }
    }
}
