<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM\Client;

interface ClientInterface
{
    public function upsertContacts(array $body): void;
    public function upsertProducts(array $body): void;
    public function upsertOrders(array $body): void;
}
