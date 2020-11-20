<?php
namespace TUT\Commands;

use TUT\Command as Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetHash extends Command {

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
			->setName( 'get-hash' )
			->setDescription( 'Gets the currently checked out git hash' )
			->setHelp( 'Gets the currently checked out git hash' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$process = $this->run_process( 'git rev-parse --short HEAD' );
		$output->writeln( trim( $process->getOutput() ) );
	}
}
