<?php

declare(strict_types=1);

use Kiboko\Contract\Pipeline\LoaderInterface;

final class OrderLoader implements LoaderInterface
{
    public function load(): \Generator
    {
        $token = new \com\zoho\api\authenticator\OAuthToken(
            getenv('ZOHO_CLIENT_ID'),
            getenv('ZOHO_CLIENT_SECRET'),
            getenv('ZOHO_ACCESS_TOKEN'),
            getenv('ZOHO_REFRESH_TOKEN'),
        );

        $config = (new \com\zoho\crm\api\SDKConfigBuilder())->build();

        \com\zoho\crm\api\Initializer::initialize(
            new \com\zoho\crm\api\UserSignature(
                getenv('ZOHO_USER_EMAIL'),
            ),
            \com\zoho\crm\api\dc\EUDataCenter::PRODUCTION(),
            $token,
            new \com\zoho\api\authenticator\store\FileStore('/tmp/zoho_token.txt'),
            $config,
            getenv('ZOHO_RESOURCE_PATH')
        );

        $line = yield;
        do {
            $body = new \com\zoho\crm\api\record\BodyWrapper();
            $body->setData(
                []
            );

            (new \com\zoho\crm\api\record\RecordOperations())
                ->upsertRecords(
                    'SalesOrders',
                    $body,
                );
        } while ($line = yield new \Kiboko\Component\Bucket\AcceptanceResultBucket($line));
    }
}
