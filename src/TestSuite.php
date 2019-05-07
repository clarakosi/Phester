<?php namespace Wikimedia\Phester;

use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Class TestSuite
 * @package Wikimedia\Phester
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
	 * @var Instructions test suite information
	 */
	private $suiteData;

	/**
	 * TestSuite constructor
	 * @param string $base_uri
	 * @param Instructions $suiteData
	 */
	public function __construct( $base_uri, Instructions $suiteData ) {
		$this->base_uri = $base_uri;
		$this->suiteData = $suiteData;

		$this->logger = new Logger( "Phester" );
		$this->client = new Client();
	}

	/**
	 * Runs test suite
	 * @return array|void errors from test suite
	 * @throws GuzzleException
	 */
	public function run() {
		$output = [];

		if ( !$this->suiteData->has( 'suite' ) || !$this->suiteData->has( 'description' ) ) {
			$this->logger->error( "Test suite must include 'suite' and 'description'" );
			return;
		}

		if ( $this->suiteData->has( 'setup' ) ) {
			$errors = $this->runInteraction( $this->suiteData->get( 'setup' ), "Test Setup" );

			if ( $errors ) {
				$output = array_merge( $output, $errors );
			}
		}

		if ( $this->suiteData->has( 'tests' ) ) {
			$errors = $this->runTests( $this->suiteData->get( 'tests' ) );

			if ( $errors ) {
				$output = array_merge( $output, $errors );
			}
		} else {
			$this->logger->error( "Test suites must have the 'test' keyword" );
			return;
		}

		if ( $output ) {
			array_unshift( $output, "\nTest: " . $this->suiteData->get( 'suite' ),
				"Description: " . $this->suiteData->get( 'description' ) );
		}

		return $output;
	}

	/**
	 * Runs the given tests from YAML
	 * @param Instructions $tests
	 * @return array|void errors from tests
	 * @throws GuzzleException
	 */
	private function runTests( $tests ) {
		$output = [];
		foreach ( $tests->getArray() as $test ) {
			$test = new Instructions( $test );

			if ( !$test->has( 'description' ) || !$test->has( 'interaction' ) ) {
				$this->logger->error( "Test must include 'description' and 'interaction'" );
				return;
			}

			$description = $test->get( 'description' );
			$interaction = $test->get( 'interaction' );

			$errors = $this->runInteraction( $interaction, $description );

			if ( $errors ) {
				$output = array_merge( $output, $errors );
			}
		}
		return $output;
	}

	/**
	 * Runs the interaction(s) from setup or interaction section under tests in YAML
	 * @param Instructions $interaction
	 * @param string $description of test or setup
	 * @return array|void errors from interaction
	 * @throws GuzzleException
	 */
	private function runInteraction( $interaction, $description ) {
		$output = [];
		foreach ( $interaction->getArray() as $rrPair ) {
			$rrPair = new Instructions( $rrPair );
			if ( $rrPair->has( 'request' ) ) {
				$response = $rrPair->get( 'response' );
				$expected = $response instanceof Instructions ? $response->getArray() : [];
				$expected['status'] = $expected['status'] ?? 200;

				$errors = $this->executeRequest( $rrPair->get( 'request' ), $expected, $description );

				if ( $errors ) {
					$output = array_merge( $output, $errors );
				}
			} else {
				$this->logger->error( "Expected 'request' key in object but instead found the following
                        object:", [ $rrPair->arrayToString() ] );
				return;
			}
		}
		return $output;
	}

	/**
	 * Executes Http Requests and checks the response against the
	 * expectations defined in the test suite definition
	 * @param Instructions $request
	 * @param array $expectedResponse
	 * @param string $description description of test or setup
	 * @return array|void comparison errors
	 * @throws GuzzleException
	 */
	private function executeRequest( $request, $expectedResponse, $description ) {
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
			$payload['query'] = $request->get( 'parameters' )->getArray();
		}

		if ( $request->has( 'headers' ) &&
			$request->get( [ 'headers', 'content-type' ] ) !== 'multipart/form-data' ) {
			$payload['headers'] = $request->get( 'headers' )->getArray();
		}

		$response = $this->client->request( $method, $this->base_uri . $path, $payload );
		return $this->compareResponses( $expectedResponse, $response, $description );
	}

	/**
	 * Converts a request's form-data to the proper Guzzle payload
	 * @param Instructions $request
	 * @param string $from
	 * @return array
	 */
	private function getFormDataPayload( $request, $from ) {
		$payload = [];

		if ( $request->has( 'headers' ) && $request->hasArray( 'headers' )
			&& $request->getLowerCase( [ 'headers', 'content-type' ] ) === 'multipart/form-data'
		) {

			$multipart = [];
			foreach ( $request->get( $from )->getArray() as $key => $value ) {
				$multipart[] = [ 'name' => $key, 'contents' => $value ];
			}

			$payload['multipart'] = $multipart;

			$headers = $request->get( 'headers' )->getArray();

			// Guzzle multipart request option does not accept a content-type
			// header and will throw an error if provided.
			unset( $headers['content-type'] );

			if ( $headers ) {
				$payload['headers'] = $headers;
			}
		} else {
			$payload['form_params'] = $request->get( $from )->getArray();
		}

		return $payload;
	}

	/**
	 * Converts a request's body to the proper Guzzle payload
	 * @param Instructions $request
	 * @return array|void
	 */
	private function getBodyPayload( $request ) {
		$payload = [];

		if ( $request->hasArray( 'body' ) ) {
			if ( $request->has( 'headers' ) && $request->hasArray( 'headers' ) ) {
				$headers = $request->get( 'headers' );
				if ( $headers->getLowerCase( 'content-type' ) === 'multipart/form-data'
					|| $headers->getLowerCase( 'content-type' ) === 'application/x-www-form-urlencoded'
				) {
					$payload = $this->getFormDataPayload( $request, 'body' );
				} else {
					$payload['json'] = $request->get( 'body' )->getArray();
				}
			} else {
				$payload['json'] = $request->get( 'body' )->getArray();
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
	 * @param string $description description of the test
	 * @return array
	 */
	private function compareResponses( $expected, $actual, $description ) {
		$output = [];
		foreach ( $expected as $key => $value ) {
			switch ( strtolower( $key ) ) {
				case 'status':
					$assertionResult = $this->assertMatch( $value, $actual->getStatusCode(), $description );

					if ( $assertionResult ) {
						$output[] = $assertionResult;
					}
					break;
				case 'headers':
					foreach ( $value as $header => $headerVal ) {
						$assertionResult = $this->assertMatch( $headerVal, $actual->getHeaderLine( $header ),
							$description );

						if ( $assertionResult ) {
							$output[] = $assertionResult;
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
						$assertionResult = $this->assertMatch( $value, $body, $description );

						if ( $assertionResult ) {
							$output[] = $assertionResult;
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
	 * @param string|TaggedValue $expected
	 * @param string $actual
	 * @param string $message test description
	 * @return string|void
	 */
	private function assertMatch( $expected, $actual, $message ) {
		if ( $expected instanceof TaggedValue ) {
			$tagName = $expected->getTag();

			if ( strtolower( $tagName ) === 'pcre/pattern:' ) {
				$pattern = $expected->getValue();

				if ( !preg_match( $pattern, $actual ) ) {
					return "\t$message failed, expected: $pattern, actual: $actual";
				};
			} else {
				$this->logger->error( "$tagName is not a supported yaml tag." );
			}
		} else {
			if ( $expected !== $actual ) {
				return "\t$message failed, expected: $expected, actual: $actual";
			}
		}
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
