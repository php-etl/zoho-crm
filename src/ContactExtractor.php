<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Component\Flow\ZohoCRM\Client\Client;
use Kiboko\Contract\Pipeline\ExtractorInterface;

final class ContactExtractor implements ExtractorInterface
{
    public function __construct(private Client $client, private readonly \Psr\Log\LoggerInterface $logger)
    {
    }

    public function extract(): iterable
    {
        try {
            yield new AcceptanceResultBucket(...$this->client->getContacts());
        } catch (\Exception $exception) {
            $this->logger->alert($exception->getMessage(), ['exception' => $exception]);
        }
    }
}
