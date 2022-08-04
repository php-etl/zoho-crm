<?php

declare(strict_types=1);

namespace Kiboko\ZohoCRM;

use com\zoho\api\logger\Levels;
use com\zoho\api\logger\LogBuilder;
use com\zoho\crm\api\InitializeBuilder;
use Kiboko\Contract\Pipeline\LoaderInterface;

final class OrderLoader implements LoaderInterface
{
    public function __construct(private readonly \Psr\Log\LoggerInterface $logger)
    {
    }

    public function load(): \Generator
    {
        try {
            $logger = (new LogBuilder())
                ->level(Levels::INFO)
                ->filePath(getcwd() . "/zoho.log")
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
            $body = new \com\zoho\crm\api\record\BodyWrapper();
            $body->setData(
                [
                    [
                        'Subject' => $line['Subject'],
                        'Contact_Name' => $line['Contact_Name'],
                        'Status' => $line['Status'],
                        'Tax' => $line['Tax'],
                        'Billing_Street' => $line['Billing_Street'],
                        'Billing_Code' => $line['Billing_Code'],
                        'Billing_City' => $line['Billing_City'],
                        'Billing_State' => $line['Billing_State'],
                        'Billing_Country' => $line['Billing_Country'],
                        'Shipping_Street' => $line['Shipping_Street'],
                        'Shipping_Code' => $line['Shipping_Code'],
                        'Shipping_City' => $line['Shipping_City'],
                        'Shipping_State' => $line['Shipping_State'],
                        'Shipping_Country' => $line['Shipping_Country'],
                        'Description' => $line['Description'],
                        'Store' => $line['Store'],
                        'Libell_promotion' => $line['Libell_promotion'],
                        'Date_de_la_commande' => $line['Date_de_la_commande'],
                        'Contact_de_livraison' => $line['Contact_de_livraison'],
                        'E_mail_de_la_commande' => $line['E_mail_de_la_commande'],
                        'Informations_de_livraison' => $line['Informations_de_livraison'],
                        'Port_et_Manutention' => $line['Port_et_Manutention'],
                        'Moyen_de_paiement' => $line['Port_et_Manutention'],
                    ]
                ]
            );

            (new \com\zoho\crm\api\record\RecordOperations())
                ->upsertRecords(
                    'Sales_Orders',
                    $body,
                );
        } while ($line = yield new \Kiboko\Component\Bucket\AcceptanceResultBucket($line));
    }
}
