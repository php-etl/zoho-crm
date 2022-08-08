<?php

declare(strict_types=1);

namespace Kiboko\ZohoCRM;

use com\zoho\api\logger\Levels;
use com\zoho\api\logger\LogBuilder;
use com\zoho\crm\api\InitializeBuilder;
use Kiboko\Contract\Pipeline\LoaderInterface;

final class DealLoader implements LoaderInterface
{
    public function __construct(private readonly \Psr\Log\LoggerInterface $logger)
    {
    }

    public function load(): \Generator
    {
        try {
            $logger = (new LogBuilder())
                ->level(Levels::INFO)
                ->filePath(getcwd(). "/zoho.log")
                ->build();

            /** @var \com\zoho\api\authenticator\Token $token */
            $token = (new \com\zoho\api\authenticator\OAuthBuilder())
                ->clientId(getenv('ZOHO_CLIENT_ID'))
                ->clientSecret(getenv('ZOHO_CLIENT_SECRET'))
                ->grantToken(getenv('ZOHO_GRANT_TOKEN'))
                ->build();

            $config = (new \com\zoho\crm\api\SDKConfigBuilder())->build();

            (new InitializeBuilder())
                ->user(
                    new \com\zoho\crm\api\UserSignature(
                        getenv('ZOHO_USER_EMAIL'),
                    )
                )
                ->environment(\com\zoho\crm\api\dc\EUDataCenter::PRODUCTION())
                ->token($token)
                ->store(new \com\zoho\api\authenticator\store\FileStore(getcwd() . '/zoho_token.txt'))
                ->SDKConfig($config)
                ->resourcePath(getcwd())
                ->logger($logger)
                ->initialize();
        } catch (\com\zoho\crm\api\exception\SDKException $exception) {
            $this->logger->alert($exception->getMessage());
        }

        $line = yield;
        do {
            $recordOperations = new \com\zoho\crm\api\record\RecordOperations();

            $body = new \com\zoho\crm\api\record\BodyWrapper();

            $contact = new \com\zoho\crm\api\record\Record();
            $contact->addKeyValue('id', $line['Contact_ID']);

            $order = new \com\zoho\crm\api\record\Record();
            $order->addKeyValue('id', $line['Order_ID']);

            $product = new \com\zoho\crm\api\record\Record();
            $product->addKeyValue('id', $line['Product_ID']);

            $record = new \com\zoho\crm\api\record\Record();
            $record->addFieldValue(\com\zoho\crm\api\record\Deals::Amount(), (float) $line['Amount']);
            $record->addFieldValue(\com\zoho\crm\api\record\Deals::DealName(), $line['Deal_Name']);
            $record->addFieldValue(\com\zoho\crm\api\record\Deals::Type(), new \com\zoho\crm\api\util\Choice($line['Type']));
            $record->addFieldValue(\com\zoho\crm\api\record\Deals::ClosingDate(), new \DateTime($line['Closing_Date']));
            $record->addFieldValue(\com\zoho\crm\api\record\Deals::Stage(), new \com\zoho\crm\api\util\Choice($line['Stage']));
            $record->addFieldValue(\com\zoho\crm\api\record\Deals::ContactName(), $contact);
            $record->addKeyValue('Commande_client', $order);
            $record->addKeyValue('E_mail_de_la_commande', $line['E_mail_de_la_commande']);
            $record->addKeyValue('Store', new \com\zoho\crm\api\util\Choice($line['Store']));
            $record->addKeyValue('Libell_promotion', $line['Libell_promotion']);
            $record->addKeyValue('Produit', $product);

            $body->setData(
                [
                    $record
                ]
            );

            $recordOperations->upsertRecords(
                'Deals',
                $body,
            );
        } while ($line = yield new \Kiboko\Component\Bucket\AcceptanceResultBucket($line));
    }
}
