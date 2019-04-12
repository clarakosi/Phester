<?php namespace API;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use GuzzleHttp\Client;

/**
 * Class TestCommand
 * @package API
 */
class TestCommand extends SymfonyCommand {
	/** @var string URI to be tested against */
	private $base_uri;

	/** @var object HTTP client */
	private $client;

	/** @var object Output Interface that logs to console*/
	private $output;

	/**
	 * TestCommand constructor.
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Configures application with name, description, input options and arguments.
	 */
	public function configure() {
		$this->setName( 'test' )
			->setDescription( 'Run API tests by supplying a valid yaml file' )
			->setHelp( 'Allows you to run various tests by supplying a valid yaml file' )
			->addArgument( 'file_path', InputArgument::REQUIRED, 'Path to the test yaml file' )
			->addArgument( 'base_uri', InputArgument::REQUIRED, "URI to test against" );
	}

	/**
	 * Executes the current command
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int|void|null if everything went well
	 */
	public function execute( InputInterface $input, OutputInterface $output ) {
		$this->base_uri = $input->getArgument( 'base_uri' );
		$this->client = new Client();
		$this->output = $output;

		$this->getYaml( $input->getArgument( 'file_path' ) );
	}

	/**
	 * Parses a Yaml file to an Array
	 * @param $file_path
	 */
	private function getYaml( $file_path ) {
		$results = Yaml::parseFile( $file_path );
		$this->runTests( $results['tests'] );
	}

	/**
	 * Runs the given tests
	 * @param $tests
	 */
	private function runTests( $tests ) {
		foreach ( $tests as $test ) {
			$description = $test['description'];
			$interaction = $test['interaction'];
			$keys = array_keys( $interaction );

			for ( $i = 0; $i < count( $keys ); $i++ ) {
				if ( $keys[$i] == 'request' ) {
					if ( $keys[$i + 1] == 'response' ) {
						$this->executeRequest( $interaction[$keys[$i]], $interaction[$keys[$i + 1]], $description );
						$i += 2;
					} else {
						// TODO: Assume response is 200
					}
				}
				// TODO: Handle error
			}
		}
	}

	/**
	 * Executes Http Requests
	 * @param $request
	 * @param $expectedResponse
	 * @param $description
	 */
	private function executeRequest( $request, $expectedResponse, $description ) {
		// TODO: Other methods
		$path = $request['path'] ? $request['path'] : '';
		$method = $request['method'];
		$query = $request['query'];
		$response = $this->client->request( $method, $this->base_uri . $path, [ 'query' => $query ] );

		$this->compareResponses( $expectedResponse, $response, $description );
	}

	/**
	 * Compares the expected response to the actual response from the API
	 * @param $expected
	 * @param $actual
	 * @param $description
	 */
	private function compareResponses( $expected, $actual, $description ) {
		// TODO: Compare other aspects of the response
		$this->assertDeepEqual( $expected['status'], $actual->getStatusCode(), $description );
	}

	/**
	 * Compares two values. If not equal an error will be logged to the console
	 * @param $expected
	 * @param $actual
	 * @param $message
	 */
	private function assertDeepEqual( $expected, $actual, $message ) {
		if ( $expected !== $actual ) {
			// TODO: Pick a different color; red is hard to read
			$this->output->writeln( "<error>$message failed, expected: $expected, actual: $actual</error>" );
		}
	}
}
