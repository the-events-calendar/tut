<?php

namespace TUT\Commands\Git;

use TUT\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LatestCommit extends Command {

	/**
	 * Configure the command
	 */
	public function configure() {
		parent::configure();

		$this->setName( 'git-last-commit' )
			->setDescription( 'Gets the latest commit on a branch' )
			->setHelp( 'Gets the latest commit on a branch' )
			->addOption( 'plugin', null, InputOption::VALUE_REQUIRED, 'The name of the plugin or repo' )
			->addOption( 'branch', null, InputOption::VALUE_REQUIRED, 'The name of the branch' );
	}

	/**
	 * Execute the command
	 *
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$plugin      = empty( $input->getOption( 'plugin' ) ) ? null : urldecode( $input->getOption( 'plugin' ) );
		$branch      = empty( $input->getOption( 'branch' ) ) ? null : $input->getOption( 'branch' );

		$github = new GitHub( 'moderntribe' );
		$commit = $github->get_latest_branch_commit( $plugin, $branch );

		echo json_encode( $commit );
	}
}
