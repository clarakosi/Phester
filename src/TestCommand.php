<?php namespace API;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Yaml\Yaml;
use Symfony\Bridge\Monolog\Logger;
use GuzzleHttp\Exception\GuzzleException;

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
	 * @var OutputInterface logs to console
	 */
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
			->addArgument( 'base_uri', InputArgument::REQUIRED, 'URI to test against' )
			->addArgument( 'file_path', InputArgument::IS_ARRAY | InputArgument::REQUIRED,
				'Path to the test yaml file' );
	}

	/**
	 * Executes the current command
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int|void|null
	 * @throws GuzzleException
	 */
	public function execute( InputInterface $input, OutputInterface $output ) {
		$this->base_uri = $input->getArgument( 'base_uri' );
		$helper = $this->getHelper( 'question' );
		$question = new ConfirmationQuestion( "Test data will be written to the site at "
			. $this->base_uri . " Existing data may be damaged. Type 'yes' to confirm! ", false );

		if ( !$helper->ask( $input, $output, $question ) ) {
			return;
		}

		$this->output = $output;

		$this->getYaml( $input->getArgument( 'file_path' ) );
	}

	/**
	 * Parses a Yaml file to an Array and calls TestSuiteRunner
	 * @param $file_paths
	 * @throws GuzzleException
	 */
	private function getYaml( $file_paths ) {
		// TODO: Handle directories

		$logger = new Logger( "Phester" );

		foreach ( $file_paths as $file ) {
			// TODO: Handle variables
			$results = Yaml::parseFile( $file, Yaml::PARSE_CUSTOM_TAGS );
			$testSuite = new TestSuiteRunner( $logger );

			$testSuiteOutput = $testSuite->run( new ArrayWrapper( $results ), $this->base_uri );

			if ( !empty( $testSuiteOutput ) ) {
				$this->output->writeln( $testSuiteOutput );
			}

		}
	}

}
