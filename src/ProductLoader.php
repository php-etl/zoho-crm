<?php

declare(strict_types=1);

namespace Kiboko\ZohoCRM;

use com\zoho\api\logger\Levels;
use com\zoho\api\logger\LogBuilder;
use com\zoho\crm\api\InitializeBuilder;
use Kiboko\Contract\Pipeline\LoaderInterface;

final class ProductLoader implements LoaderInterface
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
            $body = new \com\zoho\crm\api\record\BodyWrapper();

            $body->setData(
                [
                   [
                       'Product_Code' => $line['Product_Code'],
                       'Product_Name' => $line['Product_Name'],
                       'Product_Active' => $line['Product_Active'],
                       'Sales_Start_Date' => $line['Sales_Start_Date'],
                       'Sales_End_Date' => $line['Sales_End_Date'],
                       'Unit_Price' => $line['Product_Code'],
                       'Tax' => $line['Tax'],
                       'Description' => $line['Description'],
                       'Product_Category' => $line['Product_Category'],
                       'Famille_de_Produit' => $line['Famille_de_Produit'],
                       'Collection' => $line['Collection'],
                       'Type_de_produit' => $line['Type de produit'],
                       'URL_Produit_eshop' => $line['URL_Produit_eshop'],
                   ],
                ]
            );

            (new \com\zoho\crm\api\record\RecordOperations())
                ->upsertRecords(
                    'Products',
                    $body,
                );
        } while ($line = yield new \Kiboko\Component\Bucket\AcceptanceResultBucket($line));
    }
}
