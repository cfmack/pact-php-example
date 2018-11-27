<?php

require_once ('../src/ExampleThreeMeetupApiClient.php');

use PhpPact\Consumer\InteractionBuilder;
use PhpPact\Consumer\Model\ConsumerRequest;
use PhpPact\Consumer\Model\ProviderResponse;
use PhpPact\Consumer\Matcher\Matcher;
use GuzzleHttp\Psr7\Uri;
use PhpPact\Broker\Service\BrokerHttpClient;
use PhpPact\Http\GuzzleClient;
use PhpPact\Standalone\Exception\MissingEnvVariableException;
use PhpPact\Standalone\MockService\MockServer;
use PhpPact\Standalone\MockService\MockServerConfigInterface;
use PhpPact\Standalone\MockService\MockServerConfig;
use PhpPact\Standalone\MockService\Service\MockServerHttpService;
use PHPUnit\Framework\TestCase;



class ExampleThreeMeetupAPIClientTest extends TestCase
{
    const version = '3';
	
    private $server;

    /** @var MockServerConfigInterface */
    private $mockServerConfig;


    public function setUp()
    {
	$this->mockServerConfig = new MockServerConfig();
	$this->mockServerConfig->setHost("localhost");
	$this->mockServerConfig->setPort("7200");
	$this->mockServerConfig->setConsumer("ExampleThree");
	$this->mockServerConfig->setProvider("ExampleAPI");
	$this->mockServerConfig->setPactDir("/tmp");
	$this->mockServerConfig->setHealthCheckTimeout(10);

	$this->server = new MockServer($this->mockServerConfig);
        $this->server->start();
    }
	
    public function tearDown()
    {
	$this->mockServerConfig = new MockServerConfig();
	try {
        	$httpService = new MockServerHttpService(new GuzzleClient(), $this->mockServerConfig);
        	$httpService->verifyInteractions();

		$json = $httpService->getPactJson();
        } finally {
        	$this->server->stop();
        }
    }


    /**
     * @test
     */
    public function testCategories()
    {
        // build the request
        $path = '/' . self::version . '/categories';

        // build the request
        $request = new ConsumerRequest();
        $request
            ->setMethod('GET')
            ->setPath($path)
            ->addHeader('Content-Type', 'application/json');

        // build the response
        $matcher = new Matcher();

        $category1 = new \stdClass();
        $category1->name = $matcher->regex('Games','[gbBG]');
        $category1->sort_name = 'Games';
        $category1->id = 11;
        $category1->shortname = 'Games';

        $body = new \stdClass();
        $body->results= $matcher->eachLike($category1);

        $response = new ProviderResponse();
        $response
            ->setStatus(200)
            ->addHeader('Content-Type', 'application/json')
            ->setBody($body);

        // build up the expected results and appropriate responses
        $mockService = new InteractionBuilder($this->mockServerConfig);
        $mockService->given("General Meetup Categories")
            ->uponReceiving("A GET request to return JSON using Meetups category api under version 2")
            ->with($request)
            ->willRespondWith($response);


        $service = new \ExampleThreeMeetupApiClient($this->mockServerConfig->getBaseUri()); // Pass in the URL to the Mock Server.
        $serviceResponse = $service->categories();

        // do some asserts on the return
        $this->assertEquals('200', $serviceResponse->getStatusCode(), "Let's make sure we have an OK response");

        // do something with the body returned
        $body = (string) $serviceResponse->getBody();
        $this->assertTrue((json_decode($body) ? true : false), "Expect the JSON to be decoded without error");

        $hasException = false;
        try {
            $mockService->verify();
        } catch(\Exception $e) {
            $hasException = true;
        }

        $this->assertFalse($hasException, "We expect the pacts to validate");
    }
}
