<?php

declare(strict_types=1);

namespace Kiboko\ZohoCRM\Loader;

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
            $this->logger->alert($exception->getMessage(), ['exception' => $exception]);
            return;
        }

        $line = yield;
        do {
            $recordOperations = new \com\zoho\crm\api\record\RecordOperations();

            $body = new \com\zoho\crm\api\record\BodyWrapper();

            $record = new \com\zoho\crm\api\record\Record();
            $record->addFieldValue(\com\zoho\crm\api\record\Products::ProductName(), $line['Product_Name']);
            $record->addFieldValue(\com\zoho\crm\api\record\Products::ProductCode(), $line['Product_Code']);
            $record->addFieldValue(\com\zoho\crm\api\record\Products::ProductActive(), (bool) $line['Product_Active']);
            $record->addFieldValue(\com\zoho\crm\api\record\Products::UnitPrice(), (float) $line['Unit_Price']);
            $record->addFieldValue(\com\zoho\crm\api\record\Products::ProductCategory(), new \com\zoho\crm\api\util\Choice($line['Product_Category']));
            $record->addKeyValue('Famille_de_Produit', new \com\zoho\crm\api\util\Choice($line['Famille_de_Produit']));
            $record->addKeyValue('Collection', new \com\zoho\crm\api\util\Choice($line['Collection']));

            $taxes = [];
            foreach ($line['Taxes'] as $tax) {
                $taxRecord = new \com\zoho\crm\api\record\Tax();
                $taxRecord->setValue($tax['Tax']);

                $taxes[] = $tax;
            }

            $record->addFieldValue(\com\zoho\crm\api\record\Products::Tax(), $taxes);

            $body->setData(
                [
                    $record
                ]
            );

            try {
                $recordOperations->upsertRecords(
                    'Products',
                    $body,
                );
            } catch (\com\zoho\crm\api\exception\SDKException $exception) {
                $this->logger->alert($exception->getMessage(), ['exception' => $exception]);
                return;
            }
        } while ($line = yield new \Kiboko\Component\Bucket\AcceptanceResultBucket($line));
    }
}
