<?php

namespace Test\Unit\Krizalys\Onedrive;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use Krizalys\Onedrive\Client;
use Krizalys\Onedrive\Proxy\DriveItemProxy;
use Krizalys\Onedrive\Proxy\DriveProxy;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Http\GraphRequest;
use Microsoft\Graph\Http\GraphResponse;
use Microsoft\Graph\Model\Drive;
use Microsoft\Graph\Model\DriveItem;
use Psr\Http\Message\ResponseInterface;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    const CLIENT_ID     = '01234567-89ab-cdef-0123-456789abcdef';
    const CLIENT_SECRET = 'SeCrEt';
    const REDIRECT_URI  = 'http://ho.st/redirect/uri';
    const AUTH_CODE     = 'M01234567-89ab-cdef-0123-456789abcdef';
    const USER_ID       = '0000000000000001';
    const GROUP_ID      = '0000000000000002';
    const SITE_ID       = '0000000000000003';
    const DRIVE_ID      = '0000000000000004';
    const DRIVE_ITEM_ID = '0123';

    /**
     * @expectedException \Exception
     */
    public function testConstructorWithNullGraphShouldThrowException()
    {
        $graph      = $this->mockGraph();
        $httpClient = $this->createMock(ClientInterface::class);
        new Client(null, $graph, $httpClient, []);
    }

    public function testGetLogInUrlShouldReturnExpectedValue()
    {
        $graph      = $this->mockGraph();
        $httpClient = $this->createMock(ClientInterface::class);
        $sut        = new Client(self::CLIENT_ID, $graph, $httpClient, []);

        $scopes = [
            'test.scope.1',
            'test.scope.2',
        ];

        $actual = $sut->getLogInUrl($scopes, self::REDIRECT_URI);
        $this->assertEquals('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?client_id=' . self::CLIENT_ID . '&response_type=code&redirect_uri=http%3A%2F%2Fho.st%2Fredirect%2Furi&scope=test.scope.1%20test.scope.2&response_mode=query', $actual);
    }

    public function testGetTokenExpireShouldReturnExpectedValue()
    {
        \FunctionsMock::$timeCallback = function () {
            return strtotime('1999-01-01T00:00:01Z');
        };

        $options = [
            'options' => [
                'state' => (object) [
                    'token' => (object) [
                        'obtained' => strtotime('1999-01-01Z'),
                        'data'     => (object) ['expires_in' => 3600],
                    ],
                ],
            ],
        ];

        $graph  = $this->mockGraph();
        $sut    = $this->createClient($graph, $options);
        $actual = $sut->getTokenExpire();
        $this->assertSame(3599, $actual);
    }

    public function provideGetAccessTokenStatusShouldReturnExpectedValue()
    {
        return [
            'Fresh token' => [
                'time'     => strtotime('1999-01-01T00:58:59Z'),
                'expected' => 1,
            ],

            'Expiring token' => [
                'time'     => strtotime('1999-01-01T00:59:00Z'),
                'expected' => -1,
            ],

            'Expired token' => [
                'time'     => strtotime('1999-01-01T01:00:00Z'),
                'expected' => -2,
            ],
        ];
    }

    /**
     * @dataProvider provideGetAccessTokenStatusShouldReturnExpectedValue
     */
    public function testGetAccessTokenStatusShouldReturnExpectedValue(
        $time,
        $expected
    ) {
        \FunctionsMock::$timeCallback = function () use ($time) {
            return $time;
        };

        $options = [
            'options' => [
                'state' => (object) [
                    'token' => (object) [
                        'obtained' => strtotime('1999-01-01Z'),
                        'data'     => (object) ['expires_in' => 3600],
                    ],
                ],
            ],
        ];

        $graph  = $this->mockGraph();
        $sut    = $this->createClient($graph, $options);
        $actual = $sut->getAccessTokenStatus();
        $this->assertEquals($expected, $actual);
    }

    public function testObtainAccessTokenShouldSetExpectedState()
    {
        \FunctionsMock::$timeCallback = function () {
            return strtotime('1999-01-01Z');
        };

        $httpClient      = new ClientMock();
        $receivedUri     = null;
        $receivedOptions = null;

        $httpClient->postCallback = function ($uri, $options) use (&$receivedUri, &$receivedOptions) {
            $receivedUri     = $uri;
            $receivedOptions = $options;
            $response        = $this->createMock(ResponseInterface::class);

            $response->method('getBody')->willReturn(json_encode([
                'access_token' => 'AcCeSs+ToKeN',
                'key'          => 'value',
            ]));

            return $response;
        };

        $options = [
            'httpClient' => $httpClient,

            'options' => [
                'state' => (object) [
                    'redirect_uri' => self::REDIRECT_URI,

                    'token' => (object) [
                        'obtained' => strtotime('1999-01-01Z'),
                    ],
                ],
            ],
        ];

        $graph = $this->mockGraph();
        $sut   = $this->createClient($graph, $options);
        $sut->obtainAccessToken(self::CLIENT_SECRET, self::AUTH_CODE);
        $this->assertSame('https://login.microsoftonline.com/common/oauth2/v2.0/token', $receivedUri);

        $this->assertSame(
            $receivedOptions,
            [
                'form_params' => [
                    'client_id'     => self::CLIENT_ID,
                    'redirect_uri'  => self::REDIRECT_URI,
                    'client_secret' => self::CLIENT_SECRET,
                    'code'          => self::AUTH_CODE,
                    'grant_type'    => 'authorization_code',
                ],
            ]
        );

        $actual = $sut->getState();

        $this->assertEquals((object) [
            'redirect_uri' => null,

            'token' => (object) [
                'obtained' => strtotime('1999-01-01Z'),

                'data' => (object) [
                    'access_token' => 'AcCeSs+ToKeN',
                    'key'          => 'value',
                ],
            ],
        ], $actual);
    }

    public function testRenewAccessTokenShouldSetExpectedState()
    {
        \FunctionsMock::$timeCallback = function () {
            return strtotime('1999-01-01Z');
        };

        $httpClient      = new ClientMock();
        $receivedUri     = null;
        $receivedOptions = null;

        $httpClient->postCallback = function ($uri, $options) use (&$receivedUri, &$receivedOptions) {
            $receivedUri     = $uri;
            $receivedOptions = $options;
            $response        = $this->createMock(ResponseInterface::class);

            $response->method('getBody')->willReturn(json_encode([
                'access_token' => 'AcCeSs+ToKeN',
                'key'          => 'value',
            ]));

            return $response;
        };

        $options = [
            'httpClient' => $httpClient,

            'options' => [
                'state' => (object) [
                    'token' => (object) [
                        'obtained' => strtotime('1999-01-01Z'),
                        'data'     => (object) ['refresh_token' => 'ReFrEsH+ToKeN'],
                    ],
                ],
            ],
        ];

        $graph  = $this->mockGraph();
        $sut    = $this->createClient($graph, $options);
        $sut->renewAccessToken(self::CLIENT_SECRET);

        $this->assertSame('https://login.microsoftonline.com/common/oauth2/v2.0/token', $receivedUri);

        $this->assertSame(
            $receivedOptions,
            [
                'form_params' => [
                    'client_id'     => self::CLIENT_ID,
                    'client_secret' => self::CLIENT_SECRET,
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => 'ReFrEsH+ToKeN',
                ],
            ]
        );

        $actual = $sut->getState();

        $this->assertEquals((object) [
            'token' => (object) [
                'obtained' => strtotime('1999-01-01Z'),

                'data' => (object) [
                    'access_token' => 'AcCeSs+ToKeN',
                    'key'          => 'value',
                ],
            ],
        ], $actual);
    }

    public function testGetDrivesShouldReturnExpectedValue()
    {
        $drive      = $this->mockDrive(self::DRIVE_ID);
        $graph      = $this->mockGraphWithCollectionResponse([$drive]);
        $httpClient = $this->createMock(ClientInterface::class);
        $sut        = new Client(self::CLIENT_ID, $graph, $httpClient, []);
        $actual     = $sut->getDrives();
        $this->assertInternalType('array', $actual);
        $this->assertCount(1, $actual);

        foreach ($actual as $drive) {
            $this->assertInstanceOf(DriveProxy::class, $drive);
            $this->assertSame(self::DRIVE_ID, $drive->id);
        }
    }

    public function testGetMyDriveShouldReturnExpectedValue()
    {
        $drive      = $this->mockDrive(self::DRIVE_ID);
        $graph      = $this->mockGraphWithResponse($drive);
        $httpClient = $this->createMock(ClientInterface::class);
        $sut        = new Client(self::CLIENT_ID, $graph, $httpClient, []);
        $actual     = $sut->getMyDrive();
        $this->assertInstanceOf(DriveProxy::class, $actual);
        $this->assertSame(self::DRIVE_ID, $actual->id);
    }

    public function testGetDriveByIdShouldReturnExpectedValue()
    {
        $drive      = $this->mockDrive(self::DRIVE_ID);
        $graph      = $this->mockGraphWithResponse($drive);
        $httpClient = $this->createMock(ClientInterface::class);
        $sut        = new Client(self::CLIENT_ID, $graph, $httpClient, []);
        $actual     = $sut->getDriveById(self::DRIVE_ID);
        $this->assertInstanceOf(DriveProxy::class, $actual);
        $this->assertSame(self::DRIVE_ID, $actual->id);
    }

    public function testGetDriveByUserShouldReturnExpectedValue()
    {
        $drive      = $this->mockDrive(self::DRIVE_ID);
        $graph      = $this->mockGraphWithResponse($drive);
        $httpClient = $this->createMock(ClientInterface::class);
        $sut        = new Client(self::CLIENT_ID, $graph, $httpClient, []);
        $actual     = $sut->getDriveByUser(self::USER_ID);
        $this->assertInstanceOf(DriveProxy::class, $actual);
        $this->assertSame(self::DRIVE_ID, $actual->id);
    }

    public function testGetDriveByGroupShouldReturnExpectedValue()
    {
        $drive      = $this->mockDrive(self::DRIVE_ID);
        $graph      = $this->mockGraphWithResponse($drive);
        $httpClient = $this->createMock(ClientInterface::class);
        $sut        = new Client(self::CLIENT_ID, $graph, $httpClient, []);
        $actual     = $sut->getDriveByGroup(self::GROUP_ID);
        $this->assertInstanceOf(DriveProxy::class, $actual);
        $this->assertSame(self::DRIVE_ID, $actual->id);
    }

    public function testGetDriveBySiteShouldReturnExpectedValue()
    {
        $drive      = $this->mockDrive(self::DRIVE_ID);
        $graph      = $this->mockGraphWithResponse($drive);
        $httpClient = $this->createMock(ClientInterface::class);
        $sut        = new Client(self::CLIENT_ID, $graph, $httpClient, []);
        $actual     = $sut->getDriveBySite(self::SITE_ID);
        $this->assertInstanceOf(DriveProxy::class, $actual);
        $this->assertSame(self::DRIVE_ID, $actual->id);
    }

    public function testGetDriveItemByIdShouldReturnExpectedValue()
    {
        $item       = $this->mockDriveItem(self::DRIVE_ITEM_ID);
        $graph      = $this->mockGraphWithResponse($item);
        $httpClient = $this->createMock(ClientInterface::class);
        $sut        = new Client(self::CLIENT_ID, $graph, $httpClient, []);
        $actual     = $sut->getDriveItemById(self::DRIVE_ID, self::DRIVE_ITEM_ID);
        $this->assertInstanceOf(DriveItemProxy::class, $actual);
        $this->assertSame(self::DRIVE_ITEM_ID, $actual->id);
    }

    public function testGetRootShouldReturnExpectedValue()
    {
        $item       = $this->mockDriveItem(self::DRIVE_ITEM_ID);
        $graph      = $this->mockGraphWithResponse($item);
        $httpClient = $this->createMock(ClientInterface::class);
        $sut        = new Client(self::CLIENT_ID, $graph, $httpClient, []);
        $actual     = $sut->getRoot();
        $this->assertInstanceOf(DriveItemProxy::class, $actual);
        $this->assertSame(self::DRIVE_ITEM_ID, $actual->id);
    }

    public function testGetSpecialFolderShouldReturnExpectedValue()
    {
        $item       = $this->mockDriveItem(self::DRIVE_ITEM_ID);
        $graph      = $this->mockGraphWithResponse($item);
        $httpClient = $this->createMock(ClientInterface::class);
        $sut        = new Client(self::CLIENT_ID, $graph, $httpClient, []);
        $actual     = $sut->getSpecialFolder('documents');
        $this->assertInstanceOf(DriveItemProxy::class, $actual);
        $this->assertSame(self::DRIVE_ITEM_ID, $actual->id);
    }

    public function testGetSharedShouldReturnExpectedValue()
    {
        $item       = $this->mockDriveItem(self::DRIVE_ITEM_ID);
        $graph      = $this->mockGraphWithCollectionResponse([$item]);
        $httpClient = $this->createMock(ClientInterface::class);
        $sut        = new Client(self::CLIENT_ID, $graph, $httpClient, []);
        $actual     = $sut->getShared();

        foreach ($actual as $item) {
            $this->assertInstanceOf(DriveItemProxy::class, $item);
            $this->assertSame(self::DRIVE_ITEM_ID, $item->id);
        }
    }

    public function testGetRecentShouldReturnExpectedValue()
    {
        $item       = $this->mockDriveItem(self::DRIVE_ITEM_ID);
        $graph      = $this->mockGraphWithCollectionResponse([$item]);
        $httpClient = $this->createMock(ClientInterface::class);
        $sut        = new Client(self::CLIENT_ID, $graph, $httpClient, []);
        $actual     = $sut->getRecent();

        foreach ($actual as $item) {
            $this->assertInstanceOf(DriveItemProxy::class, $item);
            $this->assertSame(self::DRIVE_ITEM_ID, $item->id);
        }
    }

    private function createClient(Graph $graph, array $options = [])
    {
        $httpClient = array_key_exists('httpClient', $options) ?
            $options['httpClient']
            : $this->createMock(ClientInterface::class);

        $options = array_key_exists('options', $options) ?
            $options['options']
            : [];

        return new Client(
            self::CLIENT_ID,
            $graph,
            $httpClient,
            $options
        );
    }

    private function mockGraph()
    {
        return $this->createMock(Graph::class);
    }

    private function mockGraphWithResponse($payload)
    {
        $response = $this->createMock(GraphResponse::class);
        $response->method('getStatus')->willReturn('200');
        $response->method('getResponseAsObject')->willReturn($payload);
        $request = $this->createMock(GraphRequest::class);
        $request->method('execute')->willReturn($response);
        $graph = $this->createMock(Graph::class);
        $graph->method('createRequest')->willReturn($request);

        return $graph;
    }

    private function mockGraphWithCollectionResponse($payload)
    {
        $response = $this->createMock(GraphResponse::class);
        $response->method('getStatus')->willReturn('200');
        $response->method('getResponseAsObject')->willReturn($payload);
        $request = $this->createMock(GraphRequest::class);
        $request->method('execute')->willReturn($response);
        $graph = $this->createMock(Graph::class);
        $graph->method('createCollectionRequest')->willReturn($request);

        return $graph;
    }

    private function mockDrive($id)
    {
        $drive = $this->createMock(Drive::class);
        $drive->method('getId')->willReturn($id);

        return $drive;
    }

    private function mockDriveItem($id)
    {
        $item = $this->createMock(DriveItem::class);
        $item->method('getId')->willReturn($id);

        return $item;
    }
}

class ClientMock extends GuzzleClient
{
    public $postCallback;

    public function post($uri, array $options = [])
    {
        $function = $this->postCallback;

        return $function($uri, $options);
    }
}
