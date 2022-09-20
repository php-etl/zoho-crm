<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM\Client;

use Psr\Http\Message\ResponseInterface;

final class BadRequestException extends \RuntimeException
{
    public function __construct(string $message, private readonly ResponseInterface $response)
    {
        parent::__construct($message);
    }

    public function getResponse(): ResponseInterface
    {
       return $this->response;
    }
}
