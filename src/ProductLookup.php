<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Component\Bucket\ComplexResultBucket;
use Kiboko\Component\Bucket\RejectionResultBucket;
use Kiboko\Component\Flow\ZohoCRM\Client\Client;
use Kiboko\Contract\Mapping\CompiledMapperInterface;
use Kiboko\Contract\Pipeline\TransformerInterface;
use Psr\SimpleCache\CacheInterface;

final class ProductLookup implements TransformerInterface
{
    public function __construct(
        private Client $client,
        private readonly \Psr\Log\LoggerInterface $logger,
        private CacheInterface $cache,
        private string $mappingField
    ) {
    }

    public function transform(): \Generator
    {
        $line = yield;
        while (true) {
            $output = $line;

            foreach ($line["Ordered_Items"] as $key => $item) {
                try {
                    $lookup = $this->cache->get(sprintf('product.%s', $item[$this->mappingField]));

                    if ($lookup === null) {
                        $lookup = $this->client->searchProduct(code: $item['Code_Produit']);

                        $this->cache->set(sprintf('product.%s', $line[$this->mappingField]), $lookup);
                    }
                } catch (\RuntimeException $exception) {
                    $this->logger->warning($exception->getMessage(), ['exception' => $exception, 'item' => $item]);
                    $line = yield new RejectionResultBucket($line);
                    continue;
                }

                $output['Ordered_Items'][$key]['Product_Name'] = $lookup['id'];
            }

            $line = yield new AcceptanceResultBucket($output);
        }
    }
}
