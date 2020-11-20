<?php
/**
 * This class is named GitClone because Clone is a reserved word.
 */

namespace TUT\Commands\Git;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GitClone extends GitAbstract {
	/**
	 * @var string Path
	 */
	private $path;

	/**
	 * @var string ref
	 */
	private $ref;

	/**
	 * @var string alias directory
	 */
	private $alias;

	/**
	 * Configure the command
	 */
	public function configure() {
		parent::configure();

		$this->setName( 'git:clone' )
			->setDescription( 'Clones the repo/branch to a specific location' )
			->setHelp( 'Gets the latest commit on a branch' )
			->addOption( 'repo', null, InputOption::VALUE_REQUIRED, 'The name of the repo' )
			->addOption( 'path', null, InputOption::VALUE_REQUIRED, 'Parent path to clone to' )
			->addOption( 'alias', null, InputOption::VALUE_REQUIRED, 'Directory alias' )
			->addOption( 'shallow-clone', null, InputOption::VALUE_NONE, 'If included, will only do a shallow clone' )
			->addOption( 'single-branch', null, InputOption::VALUE_NONE, 'If included, will only clone a single branch' )
			->addOption( 'prune', null, InputOption::VALUE_NONE, 'If included, will prune non-upstream branches' )
			->addOption( 'ref', null, InputOption::VALUE_REQUIRED, 'The name of the ref (branch, commit, tag, etc)' );
	}

	/**
	 * Execute the command
	 *
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->repo           = empty( $input->getOption( 'repo' ) ) ? null : urldecode( $input->getOption( 'repo' ) );
		$this->ref            = $input->getOption( 'ref' );
		$this->path           = $input->getOption( 'path' );
		$this->alias          = $input->getOption( 'alias' );
		$is_single_branch     = (bool) $input->getOption( 'single-branch' );
		$should_prune         = (bool) $input->getOption( 'prune' );
		$should_shallow_clone = (bool) $input->getOption( 'shallow-clone' );

		list( $this->org, $this->repo ) = $this->parse_repo_string();

		$repo_url = "git@github.com:{$this->org}/{$this->repo}.git";

		if ( empty( $this->alias ) ) {
			$this->alias = uniqid( '', true );
		}

		if ( empty( $this->path ) ) {
			$this->path = $this->get_base_temp_dir();
		}

		$this->path = "{$this->path}/{$this->alias}";

		// make sure the repo doesn't already exist
		$this->cleanup_clone( $this->path );

		$clean_branch   = escapeshellarg( $this->ref );
		$clean_repo_url = escapeshellarg( $repo_url );
		$clean_path     = escapeshellarg( $this->path );

		$args   = [];
		$args[] = "--branch {$clean_branch}";

		if ( $is_single_branch ) {
			$args[] = '--single-branch';
		}

		if ( $should_shallow_clone ) {
			$args[] = '--depth 1';
		}

		$args[] = $clean_repo_url;
		$args[] = $clean_path;

		$command = 'git clone ' . implode( ' ', $args );

		$this->output->writeln( "<comment>Cloning {$this->org}/{$this->repo} to {$this->path}</comment>" );

		if ( $this->output->isVerbose() ) {
			$this->output->writeln( $command );
		}

		$clone_output = $this->run_process( $command );

		if ( ! file_exists( $this->path ) ) {
			$this->output->writeln( "\n{$command}\n" );
			$this->output->writeln( $clone_output->getOutput() );
			$this->output->writeln( 'Clone failed!' );
			return 1;
		}

		if ( $should_prune ) {
			chdir( $this->path );
			$this->run_process( 'git fetch --all --prune' );
			$prune = 'for branch_to_prune in $( git branch -vv | grep \': gone]\' | awk \'{print $1}\' ); do' . "\n";
			$prune .= '  git branch -D $branch_to_prune;' . "\n";
			$prune .= 'done' . "\n";
			$this->run_process( $prune );
			$this->run_process( 'git gc' );
		}

		$this->output->writeln( $this->path );
	}

	/**
	 * Removes cloned repo
	 *
	 * @param string $path
	 */
	private function cleanup_clone( string $path ) {
		$this->run_process( 'rm -rf ' . escapeshellarg( $path ) );
	}
}
