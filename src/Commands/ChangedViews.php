<?php
namespace TUT\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use TUT\Command as Command;

class ChangedViews extends Command {
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
			->setName( 'changed-views' )
			->setDescription( 'List out the views that have been changed in the given branch' )
			->setHelp( 'List out the views that have been changed in the given branch' )
			->addArgument( 'repo', InputArgument::REQUIRED, 'Repo on which to set the release date' )
			->addOption( 'branch', '', InputOption::VALUE_OPTIONAL, 'Branch from which to list views' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->branch       = $this->branch ?: $input->getOption( 'branch' );
		$this->repo         = $this->repo ?: $input->getArgument( 'repo' );

		$repo = $this->get_plugin( $this->repo );

		if ( ! $repo_dir = $this->get_plugin_dir( $repo ) ) {
			$this->io->error( "The {$this->repo} could not be found in the current path!" );
			exit( 1 );
		}

		chdir( $repo_dir );

		$helper = $this->getHelper( 'question' );

		if ( ! $this->branch ) {
			$this->branch = $helper->ask( $input, $output, new Question( 'On which branch should the release date be set? (current branch) ', 'CURRENT BRANCH' ) );

			if ( 'CURRENT BRANCH' === $this->branch ) {
				$this->branch = null;
			}
		}

		if ( $this->branch ) {
			$this->checkout( $this->branch )->pull( $this->branch );
		}

		$changed_views = $this->changed_views( $repo );

		$output->writeln( "<info>Changed views:</info>" );

		foreach ( (array) $changed_views as $file => $info ) {
			$line = "* {$file}";
			if ( ! $info['view-version'] ) {
				$line .= ' - NO @version SET';
			} elseif ( $info['view-version'] !== $info['bootstrap-version'] ) {
				$line .= " - @version MISMATCH: Plugin version: {$info['bootstrap-version']} vs. View version: {$info['view-version']}";
			}

			$output->writeln( $line );
		}
		$output->writeln( '<info>-------------------</info>' );
		$this->io->success( 'DONE' );
	}

	/**
	 * Fetches changed views
	 */
	public function changed_views( $repo ) {
		$tag_hash = $this->get_revision_hash( $this->last_tag() );
		$process = $this->run_process( 'git diff --name-only ' . $tag_hash . ' HEAD|grep "src/views"|grep -v admin-views' );
		$views = trim( $process->getOutput(), "\n" );

		if ( ! $views ) {
			return null;
		}

		$views = explode( "\n", $views );

		$view_data = [];

		foreach ( $views as &$view ) {
			if ( ! preg_match( '!\.php$!', $view ) ) {
				continue;
			}

			$bootstrap = file_get_contents( $repo->bootstrap );
			preg_match( '/Version:\s*(.*)/', $bootstrap, $matches );

			$bootstrap_version = $matches[1];

			$file = file_get_contents( $view );
			preg_match( '/@version\s*(.*)/', $file, $matches );

			$view_version = empty( $matches[1] ) ? null : $matches[1];

			$view_data[ $view ] = array(
				'plugin'            => $repo->name,
				'bootstrap-version' => $bootstrap_version,
				'view-version'      => $view_version,
			);
		}

		return $view_data;
	}
}
