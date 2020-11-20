<?php
namespace TUT\Commands;

use TUT\Command as Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SubmoduleSync extends Command {
	/**
	 * @var bool Should the command prompt for plugin selection?
	 */
	public $do_plugin_selection = false;

	/**
	 * @var string Tmp dir
	 */
	private $tmp_dir;

	/**
	 * @var array Branches in our various repos
	 */
	protected $branches = [];

	/**
	 * @var array Plugins to sync
	 */
	protected $plugins = [
		// tribe common is first to make sure its hashes are set appropriately FIRST
		'tribe-common-styles',
		'tribe-common',
		// THEN the rest of the plugins
		'event-tickets',
		'event-tickets-plus',
		'events-community',
		'events-community-tickets',
		'events-eventbrite',
		'events-filterbar',
		'events-pro',
		'image-widget-plus',
		'the-events-calendar',
	];

	/**
	 * @var array submodules
	 */
	protected $submodules = [
		'tribe-common'        => 'common',
		'tribe-common-styles' => 'src/resources/postcss/utilities',
	];

	protected function configure() {
		parent::configure();

		$this
			->setName( 'submodule-sync' )
			->setDescription( 'Synchronize submodules by branch' )
			->setHelp( 'This command ensures submodules for feature/release buckets are in sync' );
	}

	/**
	 * Execute the process
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->tmp_dir = sys_get_temp_dir();

		// prep submodules and analyze them
		foreach ( $this->submodules as $submodule => $submodule_path ) {
			$this->prep_and_analyze_repo( $submodule );
		}

		$submodule_branches = [];

		foreach ( $this->plugins as $plugin ) {
			$this->prep_and_analyze_repo( $plugin );

			chdir ( "{$this->tmp_dir}/{$plugin}" );

			// loop over the possible submodules and sync if the submodule exists in the plugin
			foreach ( $this->submodules as $submodule_repo => $submodule_path ) {
				$submodule_branches[ $submodule_repo ] = array_intersect( $this->branches[ $submodule_repo ], $this->branches[ $plugin ] );

				foreach ( $submodule_branches[ $submodule_repo ] as $branch ) {
					$this->maybe_update_hash( $plugin, $branch, $submodule_repo, $submodule_path );
				}
			}

			// re-prep tribe-common so that we get the latest hashes
			if ( 'tribe-common' === $plugin ) {
				$this->prep_and_analyze_repo( $plugin );
			}

			chdir( '..' );
		}
	}

	protected function maybe_update_hash( $repo, $branch, $repo_to_sync, $dir ) {
		$branch = str_replace( 'origin/', '', $branch );
		$this->run_process( 'git reset --hard', false );
		$this->run_process( "git checkout {$branch}", true );
		$this->run_process( 'git reset --hard HEAD~5', false );
		$this->run_process( "git branch --set-upstream-to=origin/{$branch} {$branch}", true );
		$this->run_process( 'git pull', true );
		$this->run_process( 'git submodule update --init --recursive', true );

		if ( ! file_exists( $dir ) ) {
			return;
		}

		$current_dir = getcwd();

		chdir( $dir );

		// get current hash of common
		$process = $this->run_process( 'git rev-parse HEAD', false );
		$current_common_hash = $process->getOutput();

		$this->run_process( 'git reset --hard', false );
		$this->run_process( "git checkout {$branch}", true );
		$this->run_process( 'git reset --hard HEAD~5', false );
		$this->run_process( "git branch --set-upstream-to=origin/{$branch} {$branch}", true );
		$this->run_process( 'git pull', true );

		// get current hash
		$process = $this->run_process( 'git rev-parse HEAD', true );
		$hash = $process->getOutput();

		chdir( $current_dir );

		if ( $current_common_hash === $hash ) {
			$this->io->writeln( "<fg=green>{$repo_to_sync} hash {$hash} in {$repo} {$branch} is already up to date</>" );
			return;
		}

		$this->io->writeln( "<fg=cyan>Committing {$repo_to_sync} {$branch}@{$hash} to {$repo} {$branch}</>" );

		$this->run_process( "git checkout {$branch}", true );
		$this->run_process( 'git branch', true );
		$this->run_process( 'git commit ' . $dir . ' -m ":fast_forward: https://github.com/moderntribe/' . $repo_to_sync . '/commit/' . $hash . '"' );
		$this->run_process( "git push origin {$branch}" );
	}

	/**
	 * Prepares a repository for the branch analysis/submodule sync process
	 */
	protected function prep_and_analyze_repo( $repo ) {
		$this->io->writeln( '<fg=cyan>prepping ' . $repo . '</>' );

		chdir( $this->tmp_dir );

		// get the latest of the repo
		if ( ! file_exists( $repo ) ) {
			$process = $this->run_process( 'git clone git@github.com:moderntribe/' . $repo . '.git' );
		}

		chdir( $repo );

		// fetch all branches
		$process = $this->run_process( 'git pull --all', false );

		// prune remote branches that no longer exist
		$process = $this->run_process( 'git remote prune origin', false );

		// prune local branches that don't exist in the remote
		// grab all remote branches
		$process = $this->run_process( 'git branch -r | awk \'{print $1}\'', false );
		$remote_branches = array_filter( array_map( 'trim', explode( "\n", $process->getOutput() ) ) );

		// grab all branches tracking remote branches
		$process = $this->run_process( 'git branch -vv | grep origin', false );
		$branches_tracking_remotely = array_filter( array_map( 'trim', explode( "\n", $process->getOutput() ) ) );

		// for any tracking branch that doesn't exist in the remote branch list, delete it
		foreach ( $branches_tracking_remotely as $key => $branch ) {
			$remove = true;
			foreach ( $remote_branches as $remote ) {
				if ( false !== strpos( $branch, $remote ) ) {
					continue;
				}

				$remove = false;
				break;
			}

			if ( ! $remove ) {
				continue;
			}

			preg_match( '/^\s*([^\s]+)/', $branch, $matches );
			$process = $this->run_process( 'git branch -d ' . $matches[1], false );
			$this->io->writeln( '<fg=red>PURGING ' . $matches[1] . '</>' );
		}

		// grab release and feature branches
		$this->branches[ $repo ] = $this->get_branches( 'develop' );
		$this->branches[ $repo ] = array_merge( $this->get_branches( 'release/*' ), $this->branches[ $repo ] );
		$this->branches[ $repo ] = array_merge( $this->get_branches( 'feature/*' ), $this->branches[ $repo ] );

		chdir( $this->tmp_dir );
	}

	protected function get_branches( $search ) {
		$command = 'git branch -r | grep "' . $search . '" | grep -v HEAD | awk \'{print $1}\'';
		$process = $this->run_process( $command, false );

		return array_filter( array_map( 'trim', explode( "\n", $process->getOutput() ) ) );
	}
}
