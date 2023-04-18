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
use Kiboko\Contract\Mapping\CompiledMapperInterface;
use Kiboko\Contract\Pipeline\TransformerInterface;
use Psr\SimpleCache\CacheInterface;

final readonly class ContactLookup implements TransformerInterface
{
    public function __construct(
        private Client $client,
        private \Psr\Log\LoggerInterface $logger,
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
                $encodedEmail = base64_encode(sprintf('contact.%s', $line[$this->mappingField]));
                $lookup = $this->cache->get($encodedEmail);

                if (null === $lookup) {
                    $lookup = $this->client->searchContact(email: $line[$this->mappingField]);

                    $this->cache->set($encodedEmail, $lookup);
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

            $output = ($this->mapper)($lookup, $line);

            $line = yield new AcceptanceResultBucket($output);
        }
    }
}
