<?php

namespace TUT\Commands\Git;

use TUT\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class File extends Command {

	/**
	 * Configure the command
	 */
	public function configure() {
		parent::configure();

		$this->setName( 'git-file' )
			->setDescription( 'Gets the latest commit on a branch' )
			->setHelp( 'Gets the latest commit on a branch' )
			->addOption( 'plugin', null, InputOption::VALUE_REQUIRED, 'The name of the plugin or repo' )
			->addOption( 'path', null, InputOption::VALUE_REQUIRED, 'Path and file to get' )
			->addOption( 'ref', null, InputOption::VALUE_REQUIRED, 'The name of the ref (branch, commit, tag, etc)' );
	}

	/**
	 * Execute the command
	 *
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$plugin = empty( $input->getOption( 'plugin' ) ) ? null : urldecode( $input->getOption( 'plugin' ) );
		$ref    = empty( $input->getOption( 'ref' ) ) ? null : $input->getOption( 'ref' );
		$path   = empty( $input->getOption( 'path' ) ) ? null : $input->getOption( 'path' );

		$github = new GitHub( 'moderntribe' );
		$file   = $github->get_file( $plugin, $ref, $path );

		echo json_encode( $file );
	}
}
