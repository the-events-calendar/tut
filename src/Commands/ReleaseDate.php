<?php
namespace TUT\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use TUT\Command as Command;

class ReleaseDate extends Command {
	/**
	 * @var string The branch in which the version is being prepared.
	 */
	protected $branch;

	/**
	 * @var string The repository to perform the operation on.
	 */
	protected $repo;

	/**
	 * @var string The version to set the date on.
	 */
	protected $version;

	/**
	 * @var string The release date for the provided version.
	 */
	protected $release_date;

	/**
	 * @var bool Should the command prompt for plugin selection?
	 */
	public $do_plugin_selection = false;

	protected function configure() {
		parent::configure();

		$this
			->setName( 'release-date' )
			->setDescription( 'Set the release date for a specific version' )
			->setHelp( 'Set the release date for a specific version' )
			->addArgument( 'repo', InputArgument::REQUIRED, 'Repo on which to set the release date' )
			->addOption( 'release-date', '', InputOption::VALUE_OPTIONAL, 'Release date of version' )
			->addOption( 'release-version', '', InputOption::VALUE_OPTIONAL, 'Version you are setting the date on' )
			->addOption( 'branch', '', InputOption::VALUE_OPTIONAL, 'Branch on which to commit the release date' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->branch       = $this->branch ?: $input->getOption( 'branch' );
		$this->repo         = $this->repo ?: $input->getArgument( 'repo' );
		$this->version      = $this->version ?: $input->getOption( 'release-version' );
		$this->release_date = $this->release_date ?: $input->getOption( 'release-date' );

		$repo = $this->get_plugin( $this->repo );

		if ( ! $repo_dir = $this->get_plugin_dir( $repo ) ) {
			$this->io->error( "The {$this->repo} could not be found in the current path!" );
			exit( 1 );
		}

		chdir( $repo_dir );

		if ( ! file_exists( 'readme.txt' ) ) {
			$this->io->error( "There isn't a readme.txt file that can be edited!" );
			exit( 1 );
		}

		$readme = file_get_contents( 'readme.txt' );
		preg_match_all( '/= \[([0-9\.]+)\] [^\=]*\=/', $readme, $matches );
		if ( empty( $matches[1][0] ) ) {
			$this->io->error( "Could not detect a version number in the changelog!" );
			exit( 1 );
		}
		$potential_version = $matches[1][0];
		$potential_date    = date( 'Y-m-d' );

		$helper = $this->getHelper( 'question' );

		if ( ! $this->branch ) {
			$this->branch = $helper->ask( $input, $output, new Question( 'On which branch should the release date be set? (current branch) ', 'CURRENT BRANCH' ) );

			if ( 'CURRENT BRANCH' === $this->branch ) {
				$this->branch = null;
			}
		}

		if ( ! $this->version ) {
			if ( ! $this->version = $helper->ask( $input, $output, new Question( "What version is being released? ({$potential_version}) ", $potential_version ) ) ) {
				$this->io->error( 'A version number is required.' );
				exit( 1 );
			}
		}

		if ( ! $this->release_date ) {
			if ( ! $this->release_date = $helper->ask( $input, $output, new Question( "What is the release date for the version? ({$potential_date}) ", $potential_date ) ) ) {
				$this->io->error( 'A release date is required.' );
				exit( 1 );
			}
		}

		if ( $this->branch ) {
			$this->checkout( $this->branch )->pull( $this->branch );
		}

		$regex_version = preg_quote( $this->version );
		$readme        = preg_replace(
			'/= \[' . $regex_version . '\] [^\=]*\=/',
			"= [{$this->version}] {$this->release_date} =",
			$readme
		);
		file_put_contents( 'readme.txt', $readme );

		$process = $this->run_process( 'git diff readme.txt' );
		$output->write( $process->getOutput() . "\n\n" );

		if ( $helper->ask( $input, $output, new ConfirmationQuestion( 'Would you like to commit these changes? (y/n) ', true ) ) ) {
			$default_commit_message = "Updating release date for {$this->version} to {$this->release_date}.";
			$commit_question        = new Question( "What would you like your commit message to be? (default: {$default_commit_message}) ", $default_commit_message );
			$message                = trim( $helper->ask( $input, $output, $commit_question ) );

			if ( ! $message ) {
				$message = $default_commit_message;
			}

			$process = $this->run_process( 'git commit readme.txt -m ' . escapeshellarg( $message ) );
			$output->write( $process->getOutput() . "\n\n" );

		} elseif ( $helper->ask( $input, $output, new ConfirmationQuestion( 'Would you like to revert your readme.txt changes? (y/n) ', true ) ) ) {
			$process = $this->run_process( 'git checkout -- readme.txt' );
			$output->write( $process->getOutput() . "\n\n" );
			$output->writeln( 'readme.txt reverted!' );
		}

		$output->writeln( '<info>-------------------</info>' );
		$this->io->success( 'DONE' );
	}
}
