<?php

use PHPUnit\Framework\TestCase;
use Wikimedia\Phester\Instructions;
use Wikimedia\Phester\TestSuite;
use Monolog\Handler\TestHandler;
use Symfony\Bridge\Monolog\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Yaml\Yaml;

/**
 * Class TestSuiteTest
 * @covers \Wikimedia\Phester\TestSuite
 */
class TestSuiteTest extends TestCase {

	/**
	 * @var Logger|null
	 */
	private $logger;

	/**
	 * @var TestHandler|null
	 */
	private $handler;

	protected function setUp(): void {
		$this->logger = new Logger( 'Test' );
		$this->handler = new TestHandler();
		$this->logger->pushHandler( $this->handler );
	}

	protected function tearDown(): void {
		$this->logger = null;
		$this->handler = null;
	}

	/**
	 * Provides incorrect suite data to be tested against TestSuite::run
	 * @return array
	 */
	public function runProvider() {
		/** Format: 'array suite data', 'string expected log message' */
		return [
			[ [ 'suite' => 'unit-test' ], "Test suite must include 'suite' and 'description'" ],
			[ [ 'description' => 'unit test' ], "Test suite must include 'suite' and 'description'" ],
			[ [ 'suite' => 'unit-test', 'description' => 'unit test' ],
				"Test suites must have the 'tests' keyword"
			],
			[
				[
					'suite' => 'unit-test',
					'description' => 'unit test',
					'tests' => [ 'description' => 'missing interaction' ]
				],
				"Test must include 'description' and 'interaction'"
			],
			[
				[
					'suite' => 'unit-test',
					'description' => 'unit test',
					'setup' => [ [ 'response' => [ 'status' => 200 ] ] ]
				],
				"Expected 'request' key in object but instead found the following
                        object:"
			],
			[
				[
					'suite' => 'unit-test',
					'description' => 'unit test',
					'tests' => [
						[
							'description' => 'testing number as form-data',
							'interaction' => [
								[
									'request' => [
										'method'  => 'post',
										'path' => '/w/api.php',
										'form-data' => 200
									]
								]
							]

						]
					]
				],
				"form-data must be an object"
			],
			[
				[
					'suite' => 'unit-test',
					'description' => 'unit test',
					'tests' => [
						[
							'description' => 'testing number as body',
							'interaction' => [
								[
									'request' => [
										'method'  => 'post',
										'path' => '/w/api.php',
										'body' => 22
									]
								]
							]

						]
					]
				],
				"body can only accept an object or string"
			],
			[
				[
					'suite' => 'unit-test',
					'description' => 'unit test',
					'tests' => [
						[
							'description' => 'testing random key in response',
							'interaction' => [
								[
									'request' => [
										'method'  => 'post',
										'path' => '/w/api.php',
										'body' => '22',
									],
									'response' => [
										'randomKey' => 200
									]
								]
							]

						]
					]
				],
				"randomkey is not supported in the response object"
			]
		];
	}

	/**
	 * Test TestSuite::run
	 * @dataProvider runProvider
	 * @param array $suiteData
	 * @param string $expected
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function testRunWithMissingOrInvalidInformation( $suiteData, $expected ) {
		$client  = $this->getClient( [
			new Response( 200, [ 'content-type' => 'application/json' ] ),
		] );
		$testSuite = new TestSuite( new Instructions( $suiteData ), $this->logger, $client );

		$testSuite->run();
		list( $record ) = $this->handler->getRecords();

		$this->assertEquals( $expected, $record['message'] );
	}

	/**
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function testRunWithFile() {
		$client = $this->getClient( [
			new Response( 200, [ 'content-type' => 'application/json' ], "\"validity\":\"Good\"" ),
			new Response( 200, [ 'content-type' => 'application/json' ], "test  body" ),
			new Response( 200, [ 'content-type' => 'application/json' ] ),
			new Response( 200, [ 'content-type' => 'application/json' ] ),
			new Response( 200, [ 'content-type' => 'application/json' ],
				"{\"batchcomplete\":\"\",\"query\":{\"pages\":1423}}" ),
			new Response( 200, [ 'content-type' => 'application/json' ] )
		] );

		$data = Yaml::parseFile( __DIR__ . '/unittest2.yaml', Yaml::PARSE_CUSTOM_TAGS );

		$testsuite = new TestSuite( new Instructions( $data ), $this->logger, $client );

		$this->assertEquals( $testsuite->run(), [] );
	}

	/**
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function testRunWithFileExpectErrors() {
		$client = $this->getClient( [
			new Response( 200, [ 'content-type' => 'application/json' ], "{\"validity\":\"Not Valid\"}" ),
			new Response( 200, [ 'content-type' => 'application/json' ], "{\"pages\":{\"pageids\":142}}" )
		] );

		$data = Yaml::parseFile( __DIR__ . '/unittest3.yaml', Yaml::PARSE_CUSTOM_TAGS );

		$testsuite = new TestSuite( new Instructions( $data ), $this->logger, $client );
		$run = $testsuite->run();

		list( $records ) = $this->handler->getRecords();

		$this->assertEquals( $run, [
			"\nTest: UnitTest",
			"Description: Testing unit",
			"\tTest Setup failed, expected: /text/, actual: application/json",
			"\tTest Setup failed, expected: {\"validity\":\"Good\"}, actual: {\"validity\":\"Not Valid\"}",
			"\tGet image failed, expected: 302, actual: 200",
			"\tGet image failed, expected:{\"pages\":{\"pageid\":143}} actual: {\"pages\":{\"pageids\":142}}"
		] );

		$this->assertRegExp( "/pcre: is not a supported yaml tag/", $records['message'] );
	}

	/**
	 * getClient returns the client with the provided responses
	 * @param array $responses
	 * @return Client
	 */
	public function getClient( $responses ) {
		$mock = new MockHandler( $responses );

		$handler = HandlerStack::create( $mock );
		$client = new Client( [ 'handler' => $handler, 'base_uri' => 'https://www.mediawiki.org' ] );

		return $client;
	}
}
