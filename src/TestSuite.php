<?php namespace Wikimedia\Phester;

use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
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
	 * @var Instructions test suite information
	 */
	private $suiteData;

	/**
	 * TestSuite constructor
	 * @param Instructions $suiteData
	 * @param LoggerInterface $logger
	 * @param Client $client
	 */
	public function __construct( Instructions $suiteData,
								 LoggerInterface $logger, Client $client ) {
		$this->suiteData = $suiteData;

		$this->logger = $logger;
		$this->client = $client;
	}

	/**
	 * Runs test suite
	 * @return array errors from test suite
	 * @throws GuzzleException
	 */
	public function run() {
		$output = [];
		$output[] = "- Suite: " . $this->suiteData->get( 'suite' );

		if ( !$this->suiteData->has( 'suite' ) || !$this->suiteData->has( 'description' ) ) {
			$this->logger->error( "Test suite must include 'suite' and 'description'" );
			return $output;
		}

		if ( $this->suiteData->has( 'setup' ) ) {
			$errors = $this->runInteraction( $this->suiteData->get( 'setup' ) );

			if ( $errors ) {
				$output[] = "! Setup failed:";
				$output = array_merge( $output, $errors );
				return $output;
			}
		}

		if ( $this->suiteData->has( 'tests' ) ) {
			$errors = $this->runTests( $this->suiteData->get( 'tests' ) );

			if ( $errors ) {
				$output = array_merge( $output, $errors );
			}
		} else {
			$this->logger->warning( "No tests defined in suite" );
			return $output;
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
		foreach ( $tests->asArray() as $test ) {
			$test = new Instructions( $test );

			if ( !$test->has( 'description' ) || !$test->has( 'interaction' ) ) {
				$this->logger->error( "Test must include 'description' and 'interaction'" );
				return;
			}

			$description = $test->get( 'description' );
			$interaction = $test->get( 'interaction' );

			$errors = $this->runInteraction( $interaction );

			if ( $errors ) {
				$output[] = "! Test failed: $description";
				$output = array_merge( $output, $errors );
			}
		}
		return $output;
	}

	/**
	 * Runs the interaction(s) from setup or interaction section under tests in YAML
	 * @param Instructions $interaction
	 * @return array errors from interaction
	 * @throws GuzzleException
	 */
	private function runInteraction( $interaction ) {
		foreach ( $interaction->asArray() as $rrPair ) {
			$rrPair = new Instructions( $rrPair );
			if ( $rrPair->has( 'request' ) ) {
				$response = $rrPair->get( 'response' );
				$expected = $response instanceof Instructions ? $response->asArray() : [];
				$expected['status'] = $expected['status'] ?? 200;

				$errors = $this->executeRequest( $rrPair->get( 'request' ), $expected );

				if ( $errors ) {
					// fail the entire test if one step failed.
					return $errors;
				}
			} else {
				$this->logger->error(
					"Expected 'request' key in object but instead found the following object:",
					[ $rrPair->arrayToString() ] );
				return [];
			}
		}
		return [];
	}

	/**
	 * Executes Http Requests and checks the response against the
	 * expectations defined in the test suite definition
	 * @param Instructions $request
	 * @param array $expectedResponse
	 * @return array|void comparison errors
	 * @throws GuzzleException
	 */
	private function executeRequest( $request, $expectedResponse ) {
		$path = $request->get( 'path', '' );
		$pathVar = $request->get( 'path-vars', '' );

		if ( $pathVar instanceof Instructions ) {
			$arr = $pathVar->asArray();
			$path = $this->urlEncode( $path, $arr );
		}
		$method = $request->getLowerCase( 'method', 'get' );
		$payload = [];

		if ( $method === 'post' || $method === 'put' ) {
			if ( $request->has( 'form-data' ) ) {
				if ( $request->hasArray( 'form-data' ) ) {
					$payload = $this->getFormDataPayload( $request, 'form-data' );
				} else {
					$this->logger->error( 'form-data must be an object' );
					return;
				}
			} elseif ( $request->has( 'body' ) ) {

				$body = $this->getBodyPayload( $request );
				if ( $body ) {
					$payload = $body;
				}
			}
		}

		if ( $request->has( 'parameters' ) ) {
			$payload['query'] = $request->get( 'parameters' )->asArray();
		}

		if ( $request->has( 'headers' ) &&
			$request->get( [ 'headers', 'content-type' ] ) !== 'multipart/form-data' ) {
			$payload['headers'] = $request->get( 'headers' )->asArray();
		}

		$payload['http_errors'] = false;
		$response = $this->client->request( $method, $path, $payload );
		return $this->compareResponses( $expectedResponse, $response );
	}

	/**
	 * Encodes path variables and returns updated $path
	 * @param string $path
	 * @param array $pathVar
	 * @return mixed
	 */
	private function urlEncode( $path, $pathVar ) {
		$pathVar = array_map( function ( $value ) {
				return urlencode( $value );
		}, $pathVar );

		return str_replace( array_keys( $pathVar ), $pathVar, $path );
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
			foreach ( $request->get( $from )->asArray() as $key => $value ) {
				$multipart[] = [ 'name' => $key, 'contents' => $value ];
			}

			$payload['multipart'] = $multipart;

			$headers = $request->get( 'headers' )->asArray();

			// Guzzle multipart request option does not accept a content-type
			// header and will throw an error if provided.
			unset( $headers['content-type'] );

			if ( $headers ) {
				$payload['headers'] = $headers;
			}
		} else {
			$payload['form_params'] = $request->get( $from )->asArray();
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
					$payload['json'] = $request->get( 'body' )->asArray();
				}
			} else {
				$payload['json'] = $request->get( 'body' )->asArray();
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
	 * @return array
	 */
	private function compareResponses( $expected, $actual ) {
		if ( !isset( $expected['headers']['content-type'] )
			&& isset( $expected['body'] )
			&& is_array( $expected['body'] )
		) {
			$expected['headers']['content-type'] =
				new TaggedValue( 'pcre/pattern:', '@^application/json\b@' );
		}

		$output = [];
		foreach ( $expected as $key => $value ) {
			switch ( strtolower( $key ) ) {
				case 'status':
					$assertionResult = $this->assertMatch( $value,
						$actual->getStatusCode(), 'Status' );

					if ( $assertionResult ) {
						$output[] = "\t$assertionResult";
					}
					break;
				case 'headers':
					foreach ( $value as $header => $headerVal ) {
						$assertionResult = $this->assertMatch( $headerVal,
							$actual->getHeaderLine( $header ), "$header header" );

						if ( $assertionResult ) {
							$output[] = "\t$assertionResult";
						}
					};
					break;
				case 'body':
					$body = (string)$actual->getBody();

					if ( is_array( $value ) ) {
						if ( !$this->compareArrays( $value, json_decode( $body, true ) ) ) {
							$output[] = "\tBody JSON: expected:" . json_encode( $value )
								. " actual: $body";
						}
					} else {
						$assertionResult = $this->assertMatch( $value, $body, 'Body text' );

						if ( $assertionResult ) {
							$output[] = "\t$assertionResult";
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
	 * @param string|int|float|bool|TaggedValue $expected
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
					return "$message: expected match for: $tagName$pattern, actual: $actual";
				};
			} else {
				$this->logger->error( "$tagName is not a supported yaml tag." );
			}
		} elseif ( is_array( $expected ) || is_object( $expected ) ) {
			$this->logger->error( 'Value of type ' . gettype( $expected )
				. ' cannot be used in direct comparison.' );
			return "$message: invalid expected value!";
		} else {
			if ( $expected !== $actual ) {
				return "$message: expected: $expected, actual: $actual";
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
