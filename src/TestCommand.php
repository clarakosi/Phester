<?php namespace API;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Yaml\Yaml;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class TestCommand
 * @package API
 */
class TestCommand extends SymfonyCommand {
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
			->addArgument( 'file_paths', InputArgument::IS_ARRAY | InputArgument::REQUIRED,
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
		$base_uri = $input->getArgument( 'base_uri' );
		$helper = $this->getHelper( 'question' );
		$question = new ConfirmationQuestion( "Test data will be written to the site at "
			. $base_uri . " Existing data may be damaged. Type 'yes' to confirm! ", false );

		if ( !$helper->ask( $input, $output, $question ) ) {
			return;
		}

		$files = $input->getArgument( 'file_paths' );

		foreach ( $files as $file ) {
			$results = Yaml::parseFile( $file, Yaml::PARSE_CUSTOM_TAGS );
			$testSuite = new TestSuite( $base_uri, new ArrayUtils( $results ) );

			// TODO: $testSuiteOutput should be class with methods for formatting as plain text or html or
			// json
			$testSuiteOutput = $testSuite->run();

			if ( !empty( $testSuiteOutput ) ) {
				$output->writeln( $testSuiteOutput );
			}

		}
	}

}
