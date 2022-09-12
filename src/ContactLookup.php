<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\ZohoCRM;

use Kiboko\Component\Bucket\ComplexResultBucket;
use Kiboko\Component\Flow\ZohoCRM\Client\Client;
use Kiboko\Contract\Pipeline\TransformerInterface;

final class ContactLookup implements TransformerInterface
{
    public function __construct(private Client $client, private readonly \Psr\Log\LoggerInterface $logger)
    {
    }

    public function transform(): \Generator
    {
        $line = yield;
        do {
            $bucket = new ComplexResultBucket();
            $output = $line;

            try {
                $lookup = $this->client->searchContact(email: $line['E_mail_de_la_commande']);
            } catch (\RuntimeException $exception) {
                $this->logger->warning($exception->getMessage(), ['exception' => $exception, 'item' => $line]);
                $bucket->reject($line);
                return;
            }

            $output = (function () use ($lookup, $output) {
                $output['Contact_Name'] = $lookup['id'];
                return $output;
            })();

            $bucket->accept($output);
        } while ($line = (yield $bucket));
    }
}
