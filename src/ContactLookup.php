<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Component\Bucket\EmptyResultBucket;
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
        $lookup = null;
        $line = yield new EmptyResultBucket();

        /* @phpstan-ignore-next-line */
        while (true) {
            try {
                $encodedEmail = base64_encode(sprintf('contact.%s', $line[$this->mappingField]));
                $lookup = $this->cache->get($encodedEmail);

                if (null === $lookup) {
                    $lookup = $this->client->searchContact(email: $line[$this->mappingField]);

                    $this->cache->set($encodedEmail, $lookup);
                }
            } catch (ApiRateExceededException|InternalServerErrorException $exception) {
                $this->logger->critical($exception->getMessage(), ['exception' => $exception, 'item' => $line]);
                yield new RejectionResultBucket(
                    'It seems that the API request limit has been reached or that there is a problem with the server. Please, retry later.',
                    $exception,
                    $line
                );
            } catch (BadRequestException|RequestEntityTooLargeException $exception) {
                $this->logger->error($exception->getMessage(), ['exception' => $exception, 'item' => $line]);
                $line = yield new RejectionResultBucket(
                    'It seems that the format of the request is not correct or it is too long. Please check your request and try again.',
                    $exception,
                    $line
                );
                continue;
            } catch (ForbiddenException|NoContentException|NotFoundException $exception) {
                $this->logger->error($exception->getMessage(), ['exception' => $exception, 'item' => $line]);
                $line = yield new RejectionResultBucket(
                    'It seems that the resource does not exist or that you do not have the rights to access this resource. Please check your rights and try again.',
                    $exception,
                    $line
                );
                continue;
            }

            $output = ($this->mapper)($lookup, $line);

            $line = yield new AcceptanceResultBucket($output);
        }
    }
}
