<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Component\Bucket\EmptyResultBucket;
use Kiboko\Contract\Pipeline\TransformerInterface;

final class DealTransformer implements TransformerInterface
{
    public function __construct(
        private string $orderItemsField,
    ) {
    }

    public function transform(): \Generator
    {
        $line = yield new EmptyResultBucket();

        /* @phpstan-ignore-next-line */
        while (true) {
            $output = [];
            foreach ($line[$this->orderItemsField] as $item) {
                unset($line[$this->orderItemsField]);
                $output[] = array_merge($line, $item);
            }

            $line = yield new AcceptanceResultBucket(...$output);
        }
    }
}
