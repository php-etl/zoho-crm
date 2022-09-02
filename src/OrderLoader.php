<?php

declare(strict_types=1);

namespace Kiboko\ZohoCRM\Loader;

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
            $this->logger->alert($exception->getMessage(), ['exception' => $exception]);
            return;
        }

        $line = yield;
        do {
            $recordOperations = new \com\zoho\crm\api\record\RecordOperations();

            $body = new \com\zoho\crm\api\record\BodyWrapper();

            $contact = new \com\zoho\crm\api\record\Record();
            $contact->addKeyValue('id', $line['Contact_ID']);

            $record = new \com\zoho\crm\api\record\Record();
            $record->addFieldValue(\com\zoho\crm\api\record\Sales_Orders::Subject(), $line['Subject']);
            $record->addFieldValue(\com\zoho\crm\api\record\Sales_Orders::ContactName(), $contact);
            $record->addFieldValue(\com\zoho\crm\api\record\Sales_Orders::Status(), new \com\zoho\crm\api\util\Choice($line['Status']));
            $record->addFieldValue(\com\zoho\crm\api\record\Sales_Orders::Tax(), $line['Tax']);
            $record->addFieldValue(\com\zoho\crm\api\record\Sales_Orders::BillingStreet(), $line['Billing_Street']);
            $record->addFieldValue(\com\zoho\crm\api\record\Sales_Orders::BillingCode(), $line['Billing_Code']);
            $record->addFieldValue(\com\zoho\crm\api\record\Sales_Orders::BillingState(), $line['Billing_State']);
            $record->addFieldValue(\com\zoho\crm\api\record\Sales_Orders::BillingCountry(), $line['Billing_Country']);
            $record->addFieldValue(\com\zoho\crm\api\record\Sales_Orders::ShippingStreet(), $line['Shipping_Street']);
            $record->addFieldValue(\com\zoho\crm\api\record\Sales_Orders::ShippingCode(), $line['Shipping_Code']);
            $record->addFieldValue(\com\zoho\crm\api\record\Sales_Orders::ShippingCity(), $line['Shipping_City']);
            $record->addFieldValue(\com\zoho\crm\api\record\Sales_Orders::ShippingState(), $line['Shipping_State']);
            $record->addFieldValue(\com\zoho\crm\api\record\Sales_Orders::ShippingCountry(), $line['Shipping_Country']);
            $record->addFieldValue(\com\zoho\crm\api\record\Sales_Orders::Description(), $line['Description']);
            $record->addKeyValue('Store', new \com\zoho\crm\api\util\Choice($line['Store']));
            $record->addKeyValue('Libell_promotion', $line['Libell_promotion']);
            $record->addKeyValue('Date_de_la_commande', new \DateTime($line['Date_de_la_commande']));
            $record->addKeyValue('E_mail_de_la_commande', $line['E_mail_de_la_commande']);
            $record->addKeyValue('Informations_de_livraison', new \com\zoho\crm\api\util\Choice($line['Informations_de_livraison']));
            $record->addKeyValue('Port_et_Manutention', $line['Port_et_Manutention']);
            $record->addKeyValue('Moyen_de_paiement', new \com\zoho\crm\api\util\Choice($line['Moyen_de_paiement']));

            // TODO : manage product items list
            foreach ($line['items'] as $item) {
                $record->addKeyValue('Product_Name', $item['name']);
//                $record->addKeyValue('Statut', $item['name']);
                $record->addKeyValue('Quantity', $item['qty_ordered']);
                $record->addKeyValue('Unit_Price', $item['price']);
                $record->addKeyValue('List_Price', $item['price']);
//                $record->addKeyValue('Tax', $item['tax_amount']);
                $record->addKeyValue('Total', $item['row_total']);
            }

            try {
                $recordOperations->upsertRecords(
                    'Sales_Orders',
                    $body,
                );
            } catch (\com\zoho\crm\api\exception\SDKException $exception) {
                $this->logger->alert($exception->getMessage(), ['exception' => $exception]);
                return;
            }
        } while ($line = yield new \Kiboko\Component\Bucket\AcceptanceResultBucket($line));
    }
}
