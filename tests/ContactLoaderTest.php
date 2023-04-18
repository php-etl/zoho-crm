<?php

declare(strict_types=1);

namespace Tests\Kiboko\Component\Flow\ZohoCRM;

use GuzzleHttp\Psr7\HttpFactory;
use Kiboko\Component\Flow\ZohoCRM\Client\AuthenticationMiddleware;
use Kiboko\Component\Flow\ZohoCRM\Client\Client;
use Kiboko\Component\Flow\ZohoCRM\ContactLoader;
use Kiboko\Component\PHPUnitExtension\Assert\LoaderAssertTrait;
use Kiboko\Component\PHPUnitExtension\PipelineRunner;
use Kiboko\Contract\Pipeline\PipelineRunnerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ContactLoaderTest extends TestCase
{
    use LoaderAssertTrait;

    public function testIsSuccessful(): void
    {
        $httpFactory = new HttpFactory();

        $loader = new ContactLoader(
            new Client(
                'https://example.com',
                new AuthenticationMiddleware(
                    new \Http\Mock\Client(),
                    $httpFactory,
                    $httpFactory,
                    'https://example.com',
                    '1234567890',
                    '12345678909876432',
                    '1234567890987654321234567890°',
                    '1234567890987654321234567890°',
                ),
                $httpFactory,
                $httpFactory,
                $httpFactory,
            ),
            new NullLogger(),
        );

        $data = [
            [
                'ID_Contact' => '098765432',
                'First_Name' => 'John',
                'Last_Name' => 'Doe',
                'Salutation' => 'Mr.',
                'Email' => 'johndoe@example.com',
                'Phone' => '+33700555998',
                'Date_of_Birth' => '1977-06-03',
                'Mailing_Street' => '21 avenue de l\'Amandier',
                'Mailing_Zip' => '33000',
                'Mailing_State' => 'Bordeaux',
                'Mailing_Country' => 'France',
                'Lead_Source' => 'Blog',
                'Sous_origine' => 'Inc',
                'Client_depuis' => '2000-09-06',
                'Langue' => 'Française',
                'Compte_bloqu' => false,
            ]
        ];

        $this->assertLoaderLoadsExactly($data, $data, $loader);
    }

    public function pipelineRunner(): PipelineRunnerInterface
    {
        return new PipelineRunner();
    }
}
