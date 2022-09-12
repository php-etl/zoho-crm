<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Component\Flow\ZohoCRM\Client\Client;
use Kiboko\Contract\Pipeline\ExtractorInterface;
use Psr\Http\Client\ClientExceptionInterface;

final class ProductExtractor implements ExtractorInterface
{
    public function __construct(private Client $client, private readonly \Psr\Log\LoggerInterface $logger)
    {
    }

    public function extract(): iterable
    {
        try {
            yield new AcceptanceResultBucket(...$this->client->getProducts());
        } catch (\RuntimeException $exception) {
            $this->logger->warning($exception->getMessage(), ['exception' => $exception, 'item' => $item]);
        } catch (\JsonException $e) {
        } catch (ClientExceptionInterface $e) {
        }
    }
}
