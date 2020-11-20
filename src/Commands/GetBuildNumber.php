<?php
namespace TUT\Commands;

use TUT\Command as Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetBuildNumber extends Command {

	/**
	 * @var bool Should the command prompt for plugin selection?
	 */
	public $do_plugin_selection = false;

	/**
	 * @var string The branch in which the version is being prepared
	 */
	protected $branch;

	protected function configure() {
		parent::configure();

		$this
			->setName( 'get-build-number' )
			->setDescription( 'Get repo build number (timestamp of last commit)' )
			->setHelp( 'Get repo build number (timestamp of last commit)' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$process = $this->run_process( 'git show -s --format=%ct HEAD' );
		$output->writeln( trim( $process->getOutput() ) );
	}
}
