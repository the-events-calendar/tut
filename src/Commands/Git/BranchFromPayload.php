<?php

namespace TUT\Commands\Git;

use TUT\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BranchFromPayload extends Command {

	/**
	 * Configure the command
	 */
	public function configure() {
		parent::configure();

		$this->setName( 'git-branch-from-payload' )
			->setDescription( 'Gets the latest branch from a webhook payload stored in a file on the filesystem' )
			->setHelp( 'Gets the latest branch from a webhook payload stored in a file on the filesystem' )
			->addOption( 'payload-file', null, InputOption::VALUE_REQUIRED, 'The webhook payload file' );
	}

	/**
	 * Execute the command
	 *
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$payload_file = $input->getOption( 'payload-file' );

		if ( ! file_exists( $payload_file ) ) {
			$output->writeln( '<error>The payload file could not be found</error>' );
			exit( 1 );
		}

		$payload = file_get_contents( $payload_file );
		$payload = json_decode( $payload );

		// if the payload is a deleted one, bail
		if ( isset( $payload->deleted ) && $payload->deleted ) {
			return;
		}

		if ( ! empty( $payload->ref ) ) {
			$output->writeln( str_replace( 'refs/heads/', '', $payload->ref ) );
			return;
		}

		$skippable_pr_actions = [
			'assigned',
			'closed',
			'labeled',
			'review requested',
			'review request removed',
			'unassigned',
			'unlabeled',
		];

		// if the PR is skippable, bail
		if (
			! empty( $payload->pull_request )
			&& ! empty( $payload->action )
			&& in_array( $payload->action, $skippable_pr_actions )
		) {
			return;
		}

		if ( ! empty( $payload->pull_request ) && ! empty( $payload->pull_request->head->ref ) ) {
			$output->writeln( $payload->pull_request->head->ref );
			return;
		}
	}
}
