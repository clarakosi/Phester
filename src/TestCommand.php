<?php namespace API;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Yaml\Yaml;
use GuzzleHttp\Client;

/**
 * Class TestCommand
 * @package API
 */
class TestCommand extends SymfonyCommand {
	/**
	 * @var string URI to be tested against
	 */
	private $base_uri;

	/**
	 * @var Client
	 */
	private $client;

	/**
	 * @var OutputInterface logs to console
	 */
	private $output;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * TestCommand constructor.
	 * @param LoggerInterface $logger
	 */
	public function __construct( LoggerInterface $logger ) {
		parent::__construct();

		$this->logger = $logger;
	}

	/**
	 * Configures application with name, description, input options and arguments.
	 */
	public function configure() {
		$this->setName( 'test' )
			->setDescription( 'Run API tests by supplying a valid yaml file' )
			->setHelp( 'Allows you to run various tests by supplying a valid yaml file' )
			->addArgument( 'base_uri', InputArgument::REQUIRED, 'URI to test against' )
			->addArgument( 'file_path', InputArgument::IS_ARRAY | InputArgument::REQUIRED,
				'Path to the test yaml file' );
	}

	/**
	 * Executes the current command
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int|void|null
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function execute( InputInterface $input, OutputInterface $output ) {
		$this->base_uri = $input->getArgument( 'base_uri' );
		$helper = $this->getHelper( 'question' );
		$question = new ConfirmationQuestion( "Test data will be written to the site at "
			. $this->base_uri . " Existing data may be damaged. Type 'yes' to confirm! ", false );

		if ( !$helper->ask( $input, $output, $question ) ) {
			return;
		}

		$this->client = new Client();
		$this->output = $output;

		$this->getYaml( $input->getArgument( 'file_path' ) );
	}

	/**
	 * Parses a Yaml file to an Array
	 * @param $file_paths
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	private function getYaml( $file_paths ) {
		// TODO: Handle directories

		foreach ( $file_paths as $file ) {
			// TODO: Handle variables
			$results = Yaml::parseFile( $file, Yaml::PARSE_CUSTOM_TAGS );
			if ( isset( $results['setup'] ) ) {
				$this->runTests( $results['setup'] );
			}

			if ( isset( $results['tests'] ) ) {
				$this->runTests( $results['tests'] );
			} else {
				$this->logger->error( "Test suites must have the 'test' keyword" );
			}

		}
	}

	/**
	 * Runs the given tests
	 * @param $tests
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	private function runTests( $tests ) {
		foreach ( $tests as $test ) {
			$description = $test['description'];
			$interaction = $test['interaction'];

			foreach ( $interaction as $rrPair ) {
				if ( isset( $rrPair['request'] ) ) {
					if ( isset( $rrPair['response'] ) ) {
						$this->executeRequest( $rrPair['request'], $rrPair['response'], $description );
					} else {
						$this->executeRequest( $rrPair['request'], [ 'status' => 200 ], $description );
					}
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
	 * @param $request
	 * @param $expectedResponse
	 * @param $description
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	private function executeRequest( $request, $expectedResponse, $description ) {
		$request = array_change_key_case( $request, CASE_LOWER );
		$path = $request['path'] ? $request['path'] : '';
		$method = strtolower( $request['method'] );
		$payload = [];

		if ( $method === 'post' || $method === 'put' ) {
			if ( isset( $request['form-data'] ) ) {
				if ( is_array( $request['form-data'] ) ) {
					$payload = $this->getFormDataPayload( $request, 'form-data' );
				} else {
					$this->logger->error( 'form-data must be an object' );
					return;
				}
			} elseif ( isset( $request['body'] ) ) {
				$payload = $this->getBodyPayload( $request );
			}
		}

		if ( isset( $request['parameters'] ) ) {
			$payload['query'] = $request['parameters'];
		}

		if ( isset( $request['headers'] ) &&
			$request['headers']['content-type'] !== 'multipart/form-data' ) {
			$payload['headers'] = $request['headers'];
		}

		$response = $this->client->request( $method, $this->base_uri . $path, $payload );
		$this->compareResponses( $expectedResponse, $response, $description );
	}

	/**
	 * Converts a request's form-data to the proper Guzzle payload
	 * @param $request
	 * @param $from
	 * @return array
	 */
	private function getFormDataPayload( $request, $from ) {
		$payload = [];

		if ( isset( $request['headers'] ) && is_array( $request['headers'] )
			&& strtolower( $request['headers']['content-type'] ) === 'multipart/form-data' ) {

			$multipart = [];
			foreach ( $request[$from] as $key => $value ) {
				$multipart[] = [ 'name' => $key, 'contents' => $value ];
			}

			$payload['multipart'] = $multipart;

			$headers = $request['headers'];
			unset( $headers['content-type'] );

			if ( !empty( $headers ) ) {
				$payload['headers'] = $headers;
			}
		} else {
			$payload['form_params'] = $request[$from];
		}

		return $payload;
	}

	/**
	 * Converts a request's body to the proper Guzzle payload
	 * @param $request
	 * @return array
	 */
	private function getBodyPayload( $request ) {
		$payload = [];

		if ( is_array( $request['body'] ) ) {
			if ( isset( $request['headers'] ) && is_array( $request['headers'] ) ) {
				$headers = array_change_key_case( $request['headers'], CASE_LOWER );
				if ( strtolower( $headers['content-type'] ) === 'multipart/form-data'
					|| strtolower( $headers['content-type'] ) === 'application/x-www-form-urlencoded' ) {
					$payload = $this->getFormDataPayload( $request, 'body' );
				} else {
					$payload['json'] = $request['body'];
				}
			} else {
				$payload['json'] = $request['body'];
			}
		} elseif ( is_string( $request['body'] ) ) {
			$payload['body'] = $request['body'];
		} else {
			$this->logger->error( 'body can only accept an object or string' );
			return;
		}

		return $payload;
	}

	/**
	 * Compares the expected response to the actual response from the API
	 * @param $expected
	 * @param $actual
	 * @param $description
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
							$this->output->writeln( "$description failed, expected:"
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
	 * @param $expected
	 * @param $actual
	 * @param $message
	 */
	private function assertDeepEqual( $expected, $actual, $message ) {
		// TODO: Add errors to string then output at end of test suite.

		if ( is_object( $expected ) ) {
			$pattern = $expected->getValue();

			if ( !preg_match( $pattern, $actual ) ) {
				$this->output->writeln( "$message failed, expected: $pattern, actual: $actual" );
			};
		} else {
			if ( $expected !== $actual ) {
				$this->output->writeln( "$message failed, expected: $expected, actual: $actual" );
			}
		}
	}

	/**
	 * Checks to see if items from $array1 are in array2
	 * @param $array1
	 * @param $array2
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
