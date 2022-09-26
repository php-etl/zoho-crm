<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Component\Bucket\RejectionResultBucket;
use Kiboko\Component\Flow\ZohoCRM\Client\ApiRateExceededException;
use Kiboko\Component\Flow\ZohoCRM\Client\BadRequestException;
use Kiboko\Component\Flow\ZohoCRM\Client\Client;
use Kiboko\Component\Flow\ZohoCRM\Client\ForbiddenException;
use Kiboko\Component\Flow\ZohoCRM\Client\InternalServerErrorException;
use Kiboko\Component\Flow\ZohoCRM\Client\NoContentException;
use Kiboko\Component\Flow\ZohoCRM\Client\NotFoundException;
use Kiboko\Component\Flow\ZohoCRM\Client\RequestEntityTooLargeException;
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

                        $this->cache->set(sprintf('product.%s', $item[$this->mappingField]), $lookup);
                    }
                } catch (InternalServerErrorException|ApiRateExceededException $exception) {
                    $this->logger->critical($exception->getMessage(), ['exception' => $exception]);
                    $line = yield new RejectionResultBucket($line);
                    return;
                } catch (BadRequestException|ForbiddenException|RequestEntityTooLargeException|NotFoundException|NoContentException $exception) {
                    $this->logger->error($exception->getMessage(), ['exception' => $exception]);
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
