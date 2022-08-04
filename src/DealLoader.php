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
            $body = new \com\zoho\crm\api\record\BodyWrapper();
            $body->setData(
                [
                    [
                        'Amount' => $line['Amount'],
                        'Deal_Name' => $line['Deal_Name'],
                        'Type' => $line['Type'],
                        'Closing_Date' => $line['Closing_Date'],
                        'Stage' => $line['Stage'],
                        'Contact_Name' => $line['Contact_Name'],
                        'Commande_client' => $line['Commande_client'],
                        'E_mail_de_la_commande' => $line['E_mail_de_la_commande'],
                        'Store' => $line['Store'],
                        'Libell_promotion' => $line['Libell_promotion'],
                        'Produit' => $line['Produit'],
                        'Famille_de_Produit' => $line['Famille_de_Produit'],
                        'Cat_gorie_de_Produit' => $line['Cat_gorie_de_Produit'],
                        'Collection' => $line['Collection'],
                        'Prix_unitaire_HT' => $line['Prix_unitaire_HT'],
                        'Qt' => $line['Qt'],
                        'Code_produit' => $line['Code_produit'],
                        'Remise_ligne' => $line['Remise_ligne'],
                        'TVA_ligne' => $line['TVA_ligne'],
                    ]
                ]
            );

            (new \com\zoho\crm\api\record\RecordOperations())
                ->upsertRecords(
                    'Potentials',
                    $body,
                );
        } while ($line = yield new \Kiboko\Component\Bucket\AcceptanceResultBucket($line));
    }
}
