<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM\Client;

use Psr\Http\Message\ResponseInterface;

final class MultiStatusResponseException extends \RuntimeException
{
    public function __construct(private readonly ResponseInterface $response, null|array $body = [], int $code = 0, \Throwable $previous = null)
    {
        $contents = json_decode($response->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);

        $messages = [];
        foreach ($contents['data'] as $key => $item) {
            if ('SUCCESS' !== $item['code']) {
                $messages[] = [
                    'response' => $item,
                    'body' => $body[$key],
                ];
            }
        }

        parent::__construct(
            sprintf('Zoho\'s response contains multiple statuses, %d items in the batch may have failed: %s', \count($messages), json_encode($messages, \JSON_THROW_ON_ERROR)),
            $code,
            $previous
        );
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
