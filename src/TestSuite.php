<?php namespace API;

use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Bridge\Monolog\Logger;

/**
 * Class TestSuite
 * @package API
 */
class TestSuite {
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
	 * @var ArrayUtils test suite information
	 */
	private $testSuite;

	/**
	 * TestSuite constructor
	 * @param string $base_uri
	 * @param ArrayUtils $testSuite
	 */
	public function __construct( $base_uri, $testSuite ) {
		$this->base_uri = $base_uri;
		$this->testSuite = $testSuite;

		$this->logger = new Logger( "Phester" );
		$this->client = new Client();
	}

    /**
     * Runs test suite
     * @return array|void
     * @throws GuzzleException
     */
	public function run() {
		$output = [];
		if ( $this->testSuite->has( 'setup' ) ) {
			$errors = $this->runInteraction( $this->testSuite->get( 'setup' ), "Test Setup" );

			if ( !empty( $errors ) ) {
				$output = array_merge( $output, $errors );
			}
		}

		if ( $this->testSuite->has( 'tests' ) ) {
			$errors = $this->runTests( $this->testSuite->get( 'tests' ) );

			if ( !empty( $errors ) ) {
				$output = array_merge( $output, $errors );
			}
		} else {
			$this->logger->error( "Test suites must have the 'test' keyword" );
			return;
		}

		if ( !empty( $output ) ) {
			if ( !$this->testSuite->has( 'suite' ) || !$this->testSuite->has( 'description' ) ) {
				$this->logger->error( "Test suite must include 'suite' and 'description'" );
				return;
			}

			array_unshift( $output, "\nTest: " . $this->testSuite->get( 'suite' ),
				"Description: " . $this->testSuite->get( 'description' ) );
		}

		return $output;
	}

    /**
     * Runs the given tests
     * @param $tests
     * @return array|void
     * @throws GuzzleException
     */
	private function runTests( $tests ) {
		$output = [];
		foreach ( $tests as $test ) {
			$test = new ArrayUtils( $test );

			if ( !$test->has( 'description' ) || !$test->has( 'interaction' ) ) {
				$this->logger->error( "Test must include 'description' and 'interaction'" );
				return;
			}

			$description = $test->get( 'description' );
			$interaction = $test->get( 'interaction' );

			$errors = $this->runInteraction( $interaction, $description );

			if ( !empty( $errors ) ) {
				$output = array_merge( $output, $errors );
			}
		}
		return $output;
	}

    /**
     * Runs the interactions
     * @param array $interaction
     * @param string $description
     * @return array|void
     * @throws GuzzleException
     */
	private function runInteraction( $interaction, $description ) {
		$output = [];
		foreach ( $interaction as $rrPair ) {
			$rrPair = new ArrayUtils( $rrPair );
			if ( $rrPair->has( 'request' ) ) {
				$expected = $rrPair->get( 'response' ) ?? [];
				$expected['status'] = $expected['status'] ?? 200;

				$errors = $this->executeRequest( $rrPair->get( 'request' ), $expected, $description );

				if ( !empty( $errors ) ) {
					$output = array_merge( $output, $errors );
				}
			} else {
				$this->logger->error( "Expected 'request' key in object but instead found the following
                        object:", [ $rrPair ->arrayToString() ] );
				return;
			}
		}
		return $output;
	}

	/**
	 * Executes Http Requests
	 * @param array $request
	 * @param array $expectedResponse
	 * @param string $description
	 * @return array|void
	 * @throws GuzzleException
	 */
	private function executeRequest( $request, $expectedResponse, $description ) {
		$request = new ArrayUtils( array_change_key_case( $request, CASE_LOWER ) );
		$path = $request->get( 'path', '' );
		$method = $request->getLowerCase( 'method' );
		$payload = [];

		if ( $method === 'post' || $method === 'put' ) {
			if ( $request->has( 'form-data' ) ) {
				if ( $request->hasArray( 'form-data' ) ) {
					$payload = $this->getFormDataPayload( $request, 'form-data' );
				} else {
					$this->logger->error( 'form-data must be an object' );
					return;
				}
			} elseif ( $request->hasArray( 'body' ) ) {
				$payload = $this->getBodyPayload( $request );
			}
		}

		if ( $request->has( 'parameters' ) ) {
			$payload['query'] = $request->get( 'parameters' );
		}

		if ( $request->has( 'headers' ) &&
			$request->get( [ 'headers', 'content-type' ] ) !== 'multipart/form-data' ) {
			$payload['headers'] = $request->get( 'headers' );
		}

		$response = $this->client->request( $method, $this->base_uri . $path, $payload );
		return $this->compareResponses( $expectedResponse, $response, $description );
	}

	/**
	 * Converts a request's form-data to the proper Guzzle payload
	 * @param ArrayUtils $request
	 * @param string $from
	 * @return array
	 */
	private function getFormDataPayload( $request, $from ) {
		$payload = [];

		if ( $request->has( 'headers' ) && $request->hasArray( 'headers' )
			&& strtolower( $request->get( [ 'headers', 'content-type' ] ) ) === 'multipart/form-data'
		) {

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
	 * @param ArrayUtils $request
	 * @return array|void
	 */
	private function getBodyPayload( $request ) {
		$payload = [];

		if ( $request->hasArray( 'body' ) ) {
			if ( $request->has( 'headers' ) && $request->hasArray( 'headers' ) ) {
				$headers = new ArrayUtils( array_change_key_case( $request->get( 'headers' ), CASE_LOWER ) );
				if ( $headers->getLowerCase( 'content-type' ) === 'multipart/form-data'
					|| $headers->getLowerCase( 'content-type' ) === 'application/x-www-form-urlencoded'
				) {
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
	 * @return array
	 */
	private function compareResponses( $expected, $actual, $description ) {
		$output = [];
		foreach ( $expected as $key => $value ) {
			switch ( strtolower( $key ) ) {
				case 'status':
					$errors = $this->assertDeepEqual( $value, $actual->getStatusCode(), $description );

					if ( !empty( $errors ) ) {
						$output[] = $errors;
					}
					break;
				case 'headers':
					foreach ( $value as $header => $headerVal ) {
						$errors = $this->assertDeepEqual( $headerVal, $actual->getHeaderLine( $header ),
							$description );

						if ( !empty( $errors ) ) {
							$output[] = $errors;
						}
					};
					break;
				case 'body':
					$body = (string)$actual->getBody();

					if ( is_array( $value ) ) {
						if ( !$this->compareArrays( $value, json_decode( $body, true ) ) ) {
							$output[] = "\t$description failed, expected:" . json_encode( $value ) . " actual: $body";
						}
					} else {
						$errors = $this->assertDeepEqual( $value, $body, $description );

						if ( !empty( $errors ) ) {
							$output[] = $errors;
						}
					}
					break;
				default:
					$this->logger->warning( "$key is not supported in the response object" );
					break;
			}
		}

		return $output;
	}

    /**
     * Compares two values
     * @param string|object $expected
     * @param string $actual
     * @param string $message
     * @return string|void
     */
	private function assertDeepEqual( $expected, $actual, $message ) {
		if ( is_object( $expected ) ) {
			$pattern = $expected->getValue();

			if ( !preg_match( $pattern, $actual ) ) {
				return "\t$message failed, expected: $pattern, actual: $actual";
			};
		} else {
			if ( $expected !== $actual ) {
				return "\t$message failed, expected: $expected, actual: $actual";
			}
		}
		return;
	}

	/**
	 * Checks if items from $expected are in $actual
	 * @param array $expected
	 * @param array $actual
	 * @return bool false if items not found in $array2
	 */
	private function compareArrays( $expected, $actual ) {
		foreach ( $expected as $key => $value ) {
			if ( is_array( $actual ) && isset( $actual[$key] ) ) {
				if ( is_array( $value ) && is_array( $actual[$key] ) ) {
					return $this->compareArrays( $value, $actual[$key] );
				} else {
					if ( $value !== $actual[$key] ) {
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
