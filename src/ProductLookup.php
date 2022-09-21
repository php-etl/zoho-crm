<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Component\Bucket\RejectionResultBucket;
use Kiboko\Component\Flow\ZohoCRM\Client\Client;
use Kiboko\Contract\Mapping\ArrayMapperInterface;
use Kiboko\Contract\Pipeline\TransformerInterface;
use Psr\SimpleCache\CacheInterface;

final class ProductLookup implements TransformerInterface
{
    public function __construct(
        private Client $client,
        private readonly \Psr\Log\LoggerInterface $logger,
        private CacheInterface $cache,
        private ArrayMapperInterface $mapper,
        private string $mappingField,
        private string $orderItemsField,
        private string $propertyPath,
    ) {
    }

    public function transform(): \Generator
    {
        $line = yield;
        while (true) {
            $output = $line;

            foreach ($line[$this->orderItemsField] as $key => $item) {
                try {
                    $lookup = $this->cache->get(sprintf('product.%s', $item[$this->mappingField]));

                    if ($lookup === null) {
                        $lookup = $this->client->searchProduct(code: $item[$this->mappingField]);

                        $this->cache->set(sprintf('product.%s', $item[$this->mappingField]), $lookup['id']);
                    }
                } catch (\RuntimeException $exception) {
                    $this->logger->warning($exception->getMessage(), ['exception' => $exception, 'item' => $item]);
                    $line = yield new RejectionResultBucket($line);
                    continue;
                }

                $output = ($this->mapper)(
                    $lookup,
                    $output,
                    new \Symfony\Component\PropertyAccess\PropertyPath(
                        sprintf($this->propertyPath, $key)
                    )
                );
            }

            $line = yield new AcceptanceResultBucket($output);
        }
    }
}
