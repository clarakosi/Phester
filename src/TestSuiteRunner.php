<?php namespace API;

use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class TestSuiteRunner
 * @package API
 */
class TestSuiteRunner {
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var Client
	 */
	private $client;

	/**
	 * @var string URI to be tested against
	 */
	private $base_uri;

	/**
	 * @var array errors to be written to console
	 */
	private $output = [];

	/**
	 * TestSuiteRunner constructor.
	 * @param LoggerInterface $logger
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
		$this->client = new Client();
	}

	/**
	 * @param ArrayWrapper $testSuite
	 * @param string $base_uri
	 * @return array|void
	 * @throws GuzzleException
	 */
	public function run( $testSuite, $base_uri ) {
		$this->base_uri = $base_uri;

		if ( $testSuite->has( 'setup' ) ) {
			$this->runTests( $testSuite->get( 'setup' ) );
		}

		if ( $testSuite->has( 'tests' ) ) {
			$this->runTests( $testSuite->get( 'tests' ) );
		} else {
			$this->logger->error( "Test suites must have the 'test' keyword" );
			return;
		}

		if ( !empty( $this->output ) ) {
			if ( !$testSuite->has( 'suite' ) || !$testSuite->has( 'description' ) ) {
				$this->logger->error( "Test suite must include 'suite' and 'description'" );
				return;
			}

			array_unshift( $this->output, "\nTest: " . $testSuite->get( 'suite' ),
				"Description: " . $testSuite->get( 'description' ) );
		}

		return $this->output;
	}

	/**
	 * Runs the given tests
	 * @param array $tests
	 * @throws GuzzleException
	 */
	private function runTests( $tests ) {
		foreach ( $tests as $test ) {
			$test = new ArrayWrapper( $test );

			if ( !$test->has( 'description' ) || !$test->has( 'interaction' ) ) {
				$this->logger->error( "Test must include 'description' and 'interaction'" );
				return;
			}

			$description = $test->get( 'description' );
			$interaction = $test->get( 'interaction' );

			foreach ( $interaction as $rrPair ) {
				$rrPair = new ArrayWrapper( $rrPair );
				if ( $rrPair->has( 'request' ) ) {
					$expected = $rrPair->get( 'response' ) ?? [];
					$expected['status'] = $expected['status'] ?? 200;
					$this->executeRequest( $rrPair->get( 'request' ), $expected, $description );
				} else {
					$this->logger->error( "Expected 'request' key in object but instead found the following
					object:", [ json_encode( $rrPair ) ] );
					return;
				}
			}
		}
	}

	/**
	 * Executes Http Requests
	 * @param array $request
	 * @param array $expectedResponse
	 * @param string $description
	 * @throws GuzzleException
	 */
	private function executeRequest( $request, $expectedResponse, $description ) {
		$request = new ArrayWrapper( array_change_key_case( $request, CASE_LOWER ) );
		$path = $request->has( 'path' ) ? $request->get( 'path' ) : '';
		$method = strtolower( $request->get( 'method' ) );
		$payload = [];

		if ( $method === 'post' || $method === 'put' ) {
			if ( $request->has( 'form-data' ) ) {
				if ( $request->isArray( 'form-data' ) ) {
					$payload = $this->getFormDataPayload( $request, 'form-data' );
				} else {
					$this->logger->error( 'form-data must be an object' );
					return;
				}
			} elseif ( $request->isArray( 'body' ) ) {
				$payload = $this->getBodyPayload( $request );
			}
		}

		if ( $request->has( 'parameters' ) ) {
			$payload['query'] = $request->get( 'parameters' );
		}

		if ( $request->has( 'headers' ) &&
			$request->get( 'headers', 'content-type' ) !== 'multipart/form-data' ) {
			$payload['headers'] = $request->get( 'headers' );
		}

		$response = $this->client->request( $method, $this->base_uri . $path, $payload );
		$this->compareResponses( $expectedResponse, $response, $description );
	}

	/**
	 * Converts a request's form-data to the proper Guzzle payload
	 * @param ArrayWrapper $request
	 * @param string $from
	 * @return array
	 */
	private function getFormDataPayload( $request, $from ) {
		$payload = [];

		if ( $request->has( 'headers' ) && $request->isArray( 'headers' )
			&& strtolower( $request->get( 'headers', 'content-type' ) ) === 'multipart/form-data' ) {

			$multipart = [];
			foreach ( $request->get( $from ) as $key => $value ) {
				$multipart[] = [ 'name' => $key, 'contents' => $value ];
			}

			$payload['multipart'] = $multipart;

			$headers = $request->get( 'headers' );
			unset( $headers['content-type'] );

			if ( !empty( $headers ) ) {
				$payload['headers'] = $headers;
			}
		} else {
			$payload['form_params'] = $request->get( $from );
		}

		return $payload;
	}

	/**
	 * Converts a request's body to the proper Guzzle payload
	 * @param ArrayWrapper $request
	 * @return array|void
	 */
	private function getBodyPayload( $request ) {
		$payload = [];

		if ( $request->isArray( 'body' ) ) {
			if ( $request->has( 'headers' ) && $request->isArray( 'headers' ) ) {
				$headers = array_change_key_case( $request->get( 'headers' ), CASE_LOWER );
				if ( strtolower( $headers['content-type'] ) === 'multipart/form-data'
					|| strtolower( $headers['content-type'] ) === 'application/x-www-form-urlencoded' ) {
					$payload = $this->getFormDataPayload( $request, 'body' );
				} else {
					$payload['json'] = $request->get( 'body' );
				}
			} else {
				$payload['json'] = $request->get( 'body' );
			}
		} elseif ( is_string( $request->get( 'body' ) ) ) {
			$payload['body'] = $request->get( 'body' );
		} else {
			$this->logger->error( 'body can only accept an object or string' );
			return;
		}

		return $payload;
	}

	/**
	 * Compares the expected response to the actual response from the API
	 * @param array $expected
	 * @param Response $actual
	 * @param string $description
	 */
	private function compareResponses( $expected, $actual, $description ) {
		foreach ( $expected as $key => $value ) {
			switch ( strtolower( $key ) ) {
				case 'status':
					$this->assertDeepEqual( $value, $actual->getStatusCode(), $description );
					break;
				case 'headers':
					foreach ( $value as $header => $headerVal ) {
						$this->assertDeepEqual( $headerVal, $actual->getHeaderLine( $header ), $description );
					};
					break;
				case 'body':
					$body = (string)$actual->getBody();

					if ( is_array( $value ) ) {
						if ( !$this->compareArrays( $value, json_decode( $body, true ) ) ) {
							array_push( $this->output, "\t$description failed, expected:"
								. json_encode( $value ) . " actual: $body" );
						}
					} else {
						$this->assertDeepEqual( $value, $body, $description );
					}
					break;
				default:
					$this->logger->warning( "$key is not supported in the response object" );
					break;
			}
		}
	}

	/**
	 * Compares two values. If not equal an error will be logged to the console
	 * @param string|object $expected
	 * @param string $actual
	 * @param $message
	 */
	private function assertDeepEqual( $expected, $actual, $message ) {

		if ( is_object( $expected ) ) {
			$pattern = $expected->getValue();

			if ( !preg_match( $pattern, $actual ) ) {
				array_push( $this->output, "\t$message failed, expected: $pattern, actual: $actual" );
			};
		} else {
			if ( $expected !== $actual ) {
				array_push( $this->output, "\t$message failed, expected: $expected, actual: $actual" );
			}
		}
	}

	/**
	 * Checks to see if items from $array1 are in array2
	 * @param array $array1
	 * @param array $array2
	 * @return bool false if items not found in $array2
	 */
	private function compareArrays( $array1, $array2 ) {
		foreach ( $array1 as $key => $value ) {
			if ( is_array( $array2 ) && isset( $array2[$key] ) ) {
				if ( is_array( $value ) && is_array( $array2[$key] ) ) {
					return $this->compareArrays( $value, $array2[$key] );
				} else {
					if ( $value !== $array2[$key] ) {
						return false;
					}
				}
			} else {
				return false;
			}
		}
		return true;
	}
}
