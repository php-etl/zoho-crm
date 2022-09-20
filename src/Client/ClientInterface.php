<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM\Client;

interface ClientInterface
{
    public function upsertContacts(array $body): void;
    public function insertProduct(array $body): void;
    public function updateProduct(string $code, array $body): void;
    public function upsertOrders(array $body): void;
}
