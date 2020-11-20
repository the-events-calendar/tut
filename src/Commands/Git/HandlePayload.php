<?php

namespace TUT\Commands\Git;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class HandlePayload extends GitAbstract {
	/**
	 * @var string Jenkins build URL
	 */
	private $build_url;

	/**
	 * @var string Zip output path
	 */
	private $output_path;

	/**
	 * @var string payload from GitHub
	 */
	private $payload;

	/**
	 * Configure the command
	 */
	public function configure() {
		parent::configure();

		$this->setName( 'git:handle-payload' )
			->setDescription( 'Receives a payload and reacts to it' )
			->setHelp( 'Receives a payload and reacts to it' )
			->addOption( 'build-url', null, InputOption::VALUE_REQUIRED, 'The Jenkins build URL' )
			->addOption( 'output-path', null, InputOption::VALUE_REQUIRED, 'Directory to place packaged zips' )
			->addOption( 'tut-path', null, InputOption::VALUE_REQUIRED, 'Path where the tut CLI command lives' )
			->addOption( 'payload-file', null, InputOption::VALUE_REQUIRED, 'The github payload file' )
			->addOption( 'payload', null, InputOption::VALUE_REQUIRED, 'The github payload' );
	}

	/**
	 * Execute the command
	 *
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->build_url   = $input->getOption( 'build-url' );
		$this->output_path = $input->getOption( 'output-path' );

		if ( $payload_file = $input->getOption( 'payload-file' ) ) {
			$this->payload   = json_decode( file_get_contents( $payload_file ) );
		} else {
			$this->payload   = json_decode( $input->getOption( 'payload' ) );
		}

		// if there's no action, we don't need to act on this payload
		if ( empty( $this->payload->action ) ) {
			return;
		}

		// if the action isn't one of the actions we care about, we can bail
		if ( ! in_array( $this->payload->action, [ 'review_requested', 'synchronize' ] ) ) {
			return;
		}

		$plugin    = $this->payload->pull_request->head->repo->name;
		$repo      = $this->payload->pull_request->head->repo->full_name;
		$branch    = $this->payload->pull_request->head->ref;
		$pr_number = $this->payload->number;


		// Temporarily disabling code reviews as we test out GitHub actions
		// $this->code_review( $repo, $pr_number );

		$this->output->writeln( '*******************************' );

		$this->package( $plugin, $branch );
	}

	/**
	 * Runs a codesniff
	 *
	 * @return string
	 */
	private function code_review( $repo, $pr_number ) : string {
		$command = $this->getApplication()->find( 'code-review' );

		$args = [
			'--repo' => $repo,
			'--pr'   => $pr_number,
		];

		$code_review_results = $command->run( new ArrayInput( $args ), $this->output );

		if ( $code_review_results ) {
			$this->output->writeln( '<error>Code sniffing failed</error>' );
		} else {
			$this->output->writeln( '<info>Code sniffing was successful</info>' );
		}

		return $code_review_results;
	}

	/**
	 * Packages the plugin
	 *
	 * @return string
	 */
	private function package( $plugin, $branch ) : string {
		$command = $this->getApplication()->find( 'package:product-plugin' );

		$args = [
			'--plugin'  => $plugin,
			'--branch'  => $branch,
			'--verbose' => true,
		];

		if ( $tut_path = $this->input->getOption( 'tut-path' ) ) {
			$args['--tut-path'] = $tut_path;
		}

		if ( ! empty( $this->output_path ) ) {
			$args['--output-path'] = $this->output_path;
		}

		return $command->run( new ArrayInput( $args ), $this->output );
	}
}
