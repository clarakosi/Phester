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
	 * @return Generator
	 */
	public function provideBadRunData() {
		/** Format: 'string test description' => ['array suite data', 'string expected log message'] */

		yield 'missing suite description' => [ [ 'suite' => 'unit-test' ],
			"Test suite must include 'suite' and 'description'" ];

		yield 'missing suite information' => [ [ 'description' => 'unit test' ],
			"Test suite must include 'suite' and 'description'" ];

		yield 'missing tests' => [ [ 'suite' => 'unit-test', 'description' => 'unit test' ],
			"Test suites must have the 'tests' keyword" ];

		yield 'tests missing interaction' => [ [
			'suite' => 'unit-test',
			'description' => 'unit test',
			'tests' => [ 'description' => 'missing interaction' ] ],
			"Test must include 'description' and 'interaction'" ];

		yield 'missing request in interaction' => [ [
			'suite' => 'unit-test',
			'description' => 'unit test',
			'setup' => [ [ 'response' => [ 'status' => 200 ] ] ] ],
			"Expected 'request' key in object but instead found the following object:" ];

		yield 'unsupported form-data type' => [ [
			'suite' => 'unit-test',
			'description' => 'unit test',
			'tests' => [ [
				'description' => 'testing number as form-data',
				'interaction' => [ [
					'request' => [
						'method'  => 'post',
						'path' => '/w/api.php',
						'form-data' => 200
					]
				] ]

			] ] ], "form-data must be an object" ];

		yield 'unsupported body type' => [ [
			'suite' => 'unit-test',
			'description' => 'unit test',
			'tests' => [ [
				'description' => 'testing number as body',
				'interaction' => [ [
					'request' => [
						'method'  => 'post',
						'path' => '/w/api.php',
						'body' => 22
					]
				] ]

			] ]
		], "body can only accept an object or string" ];

		yield 'unsupported key in response' => [ [
			'suite' => 'unit-test',
			'description' => 'unit test',
			'tests' => [ [
				'description' => 'testing random key in response',
				'interaction' => [ [
					'request' => [
						'method'  => 'post',
						'path' => '/w/api.php',
						'body' => '22',
					],
					'response' => [
						'randomKey' => 200
					]
				] ]

			] ]
		], "randomkey is not supported in the response object" ];
	}

	/**
	 * Test TestSuite::run
	 * @dataProvider provideBadRunData
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

		$data = Yaml::parseFile( __DIR__ . '/unittest.yaml', Yaml::PARSE_CUSTOM_TAGS );

		$testsuite = new TestSuite( new Instructions( $data ), $this->logger, $client );

		$this->assertEquals( $testsuite->run(), [ '- Suite: UnitTest' ] );
	}

	public function provideInteraction() {
		$prelude = [
			'suite' => 'UnitTest',
			'description' => 'Testing unit',
			'type' => 'RPC',
		];

		yield [
			$prelude + [
				'setup' => [ [
					'request' => [ 'path' => 'ping' ],
				] ]
			],
			[
				new Response( 302 )
			],
			[
				"- Suite: UnitTest",
				"! Setup failed:",
				"\tStatus: expected: 200, actual: 302"
			]
		];

		yield [
			$prelude + [
				'setup' => [ [
					'request' => [ 'path' => 'ping' ],
					'response' => [ 'body' => 'yes' ],
				] ]
			],
			[
				new Response( 200, [], 'no' )
			],
			[
				"- Suite: UnitTest",
				"! Setup failed:",
				"\tBody text: expected: yes, actual: no"
			]
		];

		yield [
			$prelude + [
				'tests' => [
					[
						'description' => 'get foo',
						'interaction' => [
							[
								'request' => [ 'path' => 'foo' ],
								'response' => [],
							],
						]
					],
					[
						'description' => 'get xyz',
						'interaction' => [
							[
								'request' => [ 'path' => 'xyz' ],
								'response' => [
									'body' => [ 'pages' => [ 'pageid' => '143' ] ],
								],
							],
						]
					],
				]
			],
			[
				new Response( 302 ),
				new Response( 200, [ 'content-type' => 'application/json' ], "{\"pages\":{\"pageids\":77}}" ),
			],
			[
				"- Suite: UnitTest",
				"! Test failed: get foo",
				"\tStatus: expected: 200, actual: 302",
				"! Test failed: get xyz",
				"\tBody JSON: expected:{\"pages\":{\"pageid\":\"143\"}} actual: {\"pages\":{\"pageids\":77}}",
			]
		];
	}

	/**
	 * @dataProvider provideInteraction
	 */
	public function testInteraction( $instructions, $responses, $output ) {
		$client = $this->getClient( $responses );

		$testsuite = new TestSuite( new Instructions( $instructions ), $this->logger, $client );
		$result = $testsuite->run();

		$this->assertEquals( $result, $output );
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
