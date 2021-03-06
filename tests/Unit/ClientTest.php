<?php

namespace Lendable\GoCardlessEnterprise\Tests\Unit;

use Lendable\GoCardlessEnterprise\Client;
use Lendable\GoCardlessEnterprise\Model\CreditorBankAccount;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Client as GuzzleClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class ClientTest extends TestCase
{
    const BASE_URL = 'https://api-sandbox.gocardless.com/';
    const VERSION = '2015-07-06';
    const CREDITOR_ID = 'AAAAAAAAAAAA';
    const WEBHOOK_SECRET = 'BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB';
    const TOKEN = 'CCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCC';

    /**
     * @var Client
     */
    private $fixture;

    /**
     * @var GuzzleClient|MockObject
     */
    private $guzzleClient;

    protected function setUp()
    {
        parent::setUp();

        $config = [
            'baseUrl' => self::BASE_URL,
            'gocardlessVersion' => self::VERSION,
            'creditorId' => self::CREDITOR_ID,
            'webhook_secret' => self::WEBHOOK_SECRET,
            'token' => self::TOKEN,
        ];

        $this->guzzleClient = $this->createMock(GuzzleClient::class);

        $this->fixture = new Client($this->guzzleClient, $config);
    }

    public function test_create_creditor_bank_account()
    {
        // Example data from GoCardless documentation.

        $account = new CreditorBankAccount();
        $account->setAccountHolderName('Nude Wines');
        $account->setAccountNumber('55779911'); // GC Sandbox acc/sort.
        $account->setSortCode('200000');
        $account->setCountryCode('GB');
        $account->setLinks(['creditor' => 'CR123']);

        $this->guzzleClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                self::BASE_URL.'creditor_bank_accounts',
                [
                    'headers' => $this->getDefaultHeaders(),
                    'body' => $this->createExpectedBodyFromJsonString(
                        <<<'JSON'
{
  "creditor_bank_accounts": {
    "set_as_default_payout_account": false,
    "account_holder_name": "Nude Wines",
    "account_number": "55779911",
    "sort_code": "200000",
    "country_code": "GB",
    "links": {
      "creditor": "CR123"
    }
  }
}
JSON
                    ),
                ]
            )
            ->will(
                $this->returnValue(
                    $this->mockGuzzleJsonResponse(
                        [
                            'creditor_bank_accounts' => [
                                'id' => 'BA123',
                                'created_at' => '2014-05-27T12:43:17.000Z',
                                'account_holder_name' => 'Nude Wines',
                                'account_number_ending' => '11',
                                'country_code' => 'GB',
                                'currency' => 'GBP',
                                'bank_name' => 'BARCLAYS BANK PLC',
                                'enabled' => true,
                                'links' => [
                                    'creditor' => 'CR123',
                                ],
                            ],
                        ]
                    )
                )
            );

        $account = $this->fixture->createCreditorBankAccount($account);

        $this->assertSame('BA123', $account->getId());
        $this->assertSame('Nude Wines', $account->getAccountHolderName());
        $this->assertSame('11', $account->getAccountNumberEnding());
        $this->assertSame('GB', $account->getCountryCode());
        $this->assertSame('BARCLAYS BANK PLC', $account->getBankName());
        $this->assertSame(['creditor' => 'CR123'], $account->getLinks());
    }

    private function getDefaultHeaders()
    {
        return [
            'GoCardless-Version' => static::VERSION,
            'Authorization' => 'Bearer '.static::TOKEN,
            'Content-Type' => 'application/vnd.api+json',
        ];
    }

    private function createExpectedBodyFromJsonString($json)
    {
        return json_encode(json_decode($json, true));
    }

    private function mockGuzzleJsonResponse(array $data)
    {
        $streamContent = json_encode($data);
        assert(is_string($streamContent));
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->method('getBody')
            ->willReturn($this->createStream($streamContent));

        return $response;
    }

    /**
     * @param string $content
     * @return Stream
     */
    private function createStream($content)
    {
        $handle = fopen('php://memory', 'rb+');

        assert(is_resource($handle));

        fwrite($handle, $content);
        rewind($handle);

        return new Stream($handle);
    }
}
