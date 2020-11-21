<?php
namespace TUT\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use TUT\Command as Command;

class Upgrade extends Command {
	/**
	 * @var bool Should the command prompt for plugin selection?
	 */
	public $do_plugin_selection = false;

	protected function configure() {
		parent::configure();

		$this
			->setName( 'upgrade' )
			->setDescription( 'Upgrade tut to the latest version' )
			->setHelp( 'Upgrade tut to the latest version' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		chdir( __TUT_DIR__ );
		$this->io->writeln( 'Fetching the latest and greatest of tut!' );
		$this->run_process( 'git checkout main && git pull' );
		$this->io->success( 'DONE' );
	}
}
