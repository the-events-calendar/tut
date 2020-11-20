<?php
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;
use TUT\CLI\Commands\VersionCommand;


/**
 * Class CliTestCase
 *
 * Base CLI test case class; any CLI specific utility test method should go here.
 */
class CliTestCase extends PHPUnit_Framework_TestCase {

	protected function getInputStream( $input ) {
		$input  = is_array( $input ) ? implode( "\n", $input ) : $input;
		$stream = fopen( 'php://memory', 'r+', false );
		fputs( $stream, $input );
		rewind( $stream );

		return $stream;
	}

	/**
	 * @param       $command
	 * @param       $name
	 * @param       $answers
	 *
	 * @param array $commandArgs
	 *
	 * @return CommandTester
	 */
	protected function execCommand( $command, $name, $answers, array $commandArgs = [] ) {
		$application = new Application();

		$application->add( $command );

		/** @var VersionCommand $command */
		$command       = $application->find( $name );
		$commandTester = new CommandTester( $command );

		/** @var QuestionHelper $helper */
		$helper = $command->getHelper( 'question' );

		$helper->setInputStream( $this->getInputStream( $answers ) );

		// as we are not answering a question
		$this->expectException( \RuntimeException::class );

		$defaults = [
			'command' => $command->getName()
		];

		$commandTester->execute( array_merge( $defaults, $commandArgs ) );

		return $commandTester;
	}
}
