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

		$this->setName( 'git:file' )
			->setDescription( 'Fetches a file from a repository.' )
			->setHelp( 'Fetches a file from a repository.' )
			->addOption( 'repo', null, InputOption::VALUE_REQUIRED, 'The name of the plugin or repo' )
			->addOption( 'path', null, InputOption::VALUE_REQUIRED, 'Path and file to get' )
			->addOption( 'org', null, InputOption::VALUE_REQUIRED, 'Org for the repo', 'moderntribe' )
			->addOption( 'ref', null, InputOption::VALUE_REQUIRED, 'The name of the ref (branch, commit, tag, etc)' );
	}

	/**
	 * Execute the command
	 *
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$repo = empty( $input->getOption( 'repo' ) ) ? null : urldecode( $input->getOption( 'repo' ) );
		$ref  = empty( $input->getOption( 'ref' ) ) ? null : $input->getOption( 'ref' );
		$path = empty( $input->getOption( 'path' ) ) ? null : $input->getOption( 'path' );
		$org  = empty( $input->getOption( 'org' ) ) ? null : $input->getOption( 'org' );

		$github_client = $this->get_github_client();

		$file = $github_client->api( 'repo' )->contents()->download( $org, $repo, $path, $ref );

		echo $file;
	}
}
