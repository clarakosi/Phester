<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Wikimedia\Phester\Console\TestCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Wikimedia\Phester\Console\TestCommand
 */
 class TestCommandTest extends TestCase {

	 public function testExecuteNoResponse() {
		 $application = new Application();
		 $application->add( new TestCommand() );

		 $command = $application->find( 'test' );

		 $commandTester = new CommandTester( $command );

		 $commandTester->setInputs( [ 'no' ] );

		 $commandTester->execute( [
			 'command' => $command->getName() ,
			 'base_uri' => 'https://www.mediawiki.org',
			 'file_paths' => [ __DIR__ . '/unittest2.yaml' ],
		 ] );

		 $this->assertRegExp( '/Test data will be written to the site at https:\/\/www.mediawiki.org/',
			 $commandTester->getDisplay() );
	 }
 }
