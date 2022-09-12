<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM;

use Kiboko\Component\Bucket\ComplexResultBucket;
use Kiboko\Component\Flow\ZohoCRM\Client\Client;
use Kiboko\Contract\Pipeline\TransformerInterface;

final class ProductLookup implements TransformerInterface
{
    public function __construct(private Client $client, private readonly \Psr\Log\LoggerInterface $logger)
    {
    }

    public function transform(): \Generator
    {
        $line = yield;
        do {
            $bucket = new ComplexResultBucket();
            $output = $line;

            foreach ($line["Ordered_Items"] as $key => $item) {
                try {
                    $lookup = $this->client->searchProduct(code: $item['Code_Produit']);
                } catch (\RuntimeException $exception) {
                    $this->logger->warning($exception->getMessage(), ['exception' => $exception, 'item' => $item]);
                    $bucket->reject($item);
                    return;
                }

                $output = (function () use ($key, $lookup, $output) {
                    $output['Ordered_Items'][$key]['Product_Name'] = $lookup['id'];
                    return $output;
                })();
            }

            $bucket->accept($output);
        } while ($line = (yield $bucket));
    }
}
