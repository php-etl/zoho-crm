<?php

declare(strict_types=1);

namespace Kiboko\ZohoCRM;

use com\zoho\api\logger\Levels;
use com\zoho\api\logger\LogBuilder;
use com\zoho\crm\api\InitializeBuilder;
use Kiboko\Contract\Pipeline\LoaderInterface;

final class ContactLoader implements LoaderInterface
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
                        'ID_Contact' => $line['ID_Contact'],
                        'Salutation' => $line['Salutation'],
                        'First_Name' => $line['First_Name'],
                        'Last_Name' => $line['Last_Name'],
                        'Email' => $line['Email'],
                        'Phone' => $line['Phone'],
                        'Date_of_Birth' => $line['Date_of_Birth'],
                        'Mailing_Street' => $line['Mailing_Street'],
                        'Mailing_Zip' => $line['Mailing_Zip'],
                        'Mailing_State' => $line['Mailing_State'],
                        'Mailing_Country' => $line['Mailing_Country'],
                        'Other_Street' => $line['Other_Street'],
                        'Lead_Source' => $line['Lead_Source'],
                        'Sous_origine' => $line['Sous_origine'],
                        'Client_depuis' => $line['Client_depuis'],
                        'Langue' => $line['Langue'],
                        'Compte_bloqu' => $line['Compte_bloqu'],
                    ]
                ]
            );

            (new \com\zoho\crm\api\record\RecordOperations())
                ->upsertRecords(
                    'Contacts',
                    $body,
                );
        } while ($line = yield new \Kiboko\Component\Bucket\AcceptanceResultBucket($line));
    }
}
