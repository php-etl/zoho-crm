<?php

declare(strict_types=1);

namespace Kiboko\ZohoCRM\Loader;

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
            $this->logger->alert($exception->getMessage(), ['exception' => $exception]);
            return;
        }

        $line = yield;
        do {
            $recordOperations = new \com\zoho\crm\api\record\RecordOperations();

            $body = new \com\zoho\crm\api\record\BodyWrapper();

            $record = new \com\zoho\crm\api\record\Record();
            $record->addFieldValue(\com\zoho\crm\api\record\Contacts::LastName(), $line['Last_Name']);
            $record->addFieldValue(\com\zoho\crm\api\record\Contacts::FirstName(), $line['First_Name']);
            $record->addFieldValue(\com\zoho\crm\api\record\Contacts::Salutation(), new \com\zoho\crm\api\util\Choice($line['Salutation']));
            $record->addFieldValue(\com\zoho\crm\api\record\Contacts::Email(), $line['Email']);
            $record->addFieldValue(\com\zoho\crm\api\record\Contacts::Phone(), $line['Phone']);
            $record->addFieldValue(\com\zoho\crm\api\record\Contacts::DateOfBirth(), new \DateTime($line['Date_of_Birth']));
            $record->addFieldValue(\com\zoho\crm\api\record\Contacts::MailingStreet(), $line['Mailing_Street']);
            $record->addFieldValue(\com\zoho\crm\api\record\Contacts::MailingZip(), $line['Mailing_Zip']);
            $record->addFieldValue(\com\zoho\crm\api\record\Contacts::MailingState(), $line['Mailing_State']);
            $record->addFieldValue(\com\zoho\crm\api\record\Contacts::MailingCountry(), $line['Mailing_Country']);
            $record->addFieldValue(\com\zoho\crm\api\record\Contacts::LeadSource(), new \com\zoho\crm\api\util\Choice($line['Lead_Source']));
            $record->addKeyValue('ID_Contact', $line['ID_Contact']);
            $record->addKeyValue('Sous_origine', new \com\zoho\crm\api\util\Choice($line['Sous_origine']));
            $record->addKeyValue('Client_depuis', new \DateTime($line['Client_depuis']));
            $record->addKeyValue('Langue', new \com\zoho\crm\api\util\Choice($line['Langue']));
            $record->addKeyValue('Compte_bloqu', new \com\zoho\crm\api\util\Choice($line['Compte_bloqu']));

            $body->setData([$record]);

            try {
                $recordOperations->upsertRecords(
                    'Contacts',
                    $body,
                );
            } catch (\com\zoho\crm\api\exception\SDKException $exception) {
                $this->logger->alert($exception->getMessage(), ['exception' => $exception]);
                return;
            }
        } while ($line = yield new \Kiboko\Component\Bucket\AcceptanceResultBucket($line));
    }
}
