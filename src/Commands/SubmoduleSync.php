<?php
namespace TUT\Commands;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use TUT\Command as Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SubmoduleSync extends Command {
	/**
	 * @var bool Should the command prompt for plugin selection?
	 */
	public $do_plugin_selection = false;

	/**
	 * @var string Tmp dir.
	 */
	private $tmp_dir;

	/**
	 * @var string Branch to synchronize.
	 */
	protected $branch;

	/**
	 * @var array Branches in our various repos.
	 */
	protected $branches = [];

	/**
	 * @var string GitHub org.
	 */
	protected $org = 'moderntribe';

	protected function configure() {
		parent::configure();

		$this
			->setName( 'submodule-sync' )
			->setDescription( 'Synchronize submodules by branch' )
			->setHelp( 'This command ensures submodules for feature/release buckets are in sync' )
			->addOption( 'branch', '', InputOption::VALUE_REQUIRED, 'Limit synchronization to a specific branch' );
	}

	/**
	 * Execute the process
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->get_github_client();

		$this->branch = $this->branch ?: $input->getOption( 'branch' );

		$this->tmp_dir = sys_get_temp_dir();
		$this->tmp_dir .= '/' . uniqid( '', true );

		if ( ! file_exists( $this->tmp_dir ) ) {
			mkdir( $this->tmp_dir );
		}

		// prep submodules and analyze them
		foreach ( $this->config->submodules as $submodule ) {
			$submodule = (object) $submodule;

			// Skip tribe-common because it is prepped in the plugin loop.
			if ( 'tribe-common' === $submodule->name ) {
				continue;
			}

			$this->io->write( '<fg=cyan>checking branches on</> <fg=yellow>' . $submodule->name . '</><fg=cyan>:</>' );
			$this->io->write( " <fg=green>✓</>\n" );

			$this->prep_branches( $submodule->name );
		}

		$submodule_branches = [];

		foreach ( $this->config->plugins as $plugin ) {
			$plugin = (object) $plugin;

			$this->io->write( '<fg=cyan>checking branches on</> <fg=yellow>' . $plugin->name . '</><fg=cyan>:</>' );

			$updated_hash = false;
			$did_compare_hashes = false;

			$this->prep_branches( $plugin->name );

			// loop over the possible submodules and sync if the submodule exists in the plugin
			foreach ( $this->config->submodules as $submodule ) {
				$submodule = (object) $submodule;

				if ( $plugin->name === $submodule->name ) {
					continue;
				}

				$branches_for_submodule = array_keys( $this->branches[ $submodule->name ] );
				$branches_for_plugin    = array_keys( $this->branches[ $plugin->name ] );

				$submodule_branches[ $submodule->name ] = array_intersect( $branches_for_submodule, $branches_for_plugin );

				foreach ( $submodule_branches[ $submodule->name ] as $branch ) {
					if ( 'master' === $branch || 'main' === $branch ) {
						continue;
					}

					if ( ! $this->get_github_client()->api( 'repo' )->contents()->exists( $this->org, $plugin->name, $submodule->path, $branch ) ) {
						continue;
					}

					$plugin_submodule_hash = $this->get_github_client()->api( 'repo' )->contents()->show( $this->org, $plugin->name, $submodule->path, $branch );
					$upstream_submodule_hash = $this->branches[ $submodule->name ][ $branch ];

					if ( empty( $plugin_submodule_hash['sha'] ) ) {
						$this->io->error( "Unable to fetch the submodule hash from {$plugin->name}" );
						exit( 1 );
					}

					$plugin_submodule_hash = $plugin_submodule_hash['sha'];
					if ( ! $did_compare_hashes ) {
						$this->io->newLine();
						$did_compare_hashes = true;
					}

					if ( $plugin_submodule_hash != $upstream_submodule_hash ) {
						$this->io->writeln( "  <fg=red>x</> Hash mismatch on {$branch} (committed: {$plugin_submodule_hash}, should be: {$upstream_submodule_hash} )!" );
						$this->update_hash( $plugin, $branch, $submodule, $output );
						$updated_hash = true;
					} else {
						$this->io->writeln( "  <fg=green>✓</> {$submodule->name} hashes match for {$branch}" );
					}
				}
			}

			if ( ! $did_compare_hashes ) {
				$this->io->write( " <fg=green>✓</>\n" );
			}

			// re-prep tribe-common so that we get the latest hashes
			if ( 'tribe-common' === $plugin->name && $updated_hash ) {
				$this->prep_branches( $plugin->name );
			}
		}

		if ( $this->tmp_dir != '/' ) {
			$this->run_process( "rm -rf {$this->tmp_dir}" );
		}
	}

	protected function update_hash( $plugin, $branch, $submodule, $output ) {
		$upstream_submodule_hash = $this->branches[ $submodule->name ][ $branch ];

		$command = $this->getApplication()->find( 'git:clone' );
		$arguments = [
			'--repo'          => "{$this->org}/{$plugin->name}",
			'--path'          => $this->tmp_dir,
			'--alias'         => $plugin->name,
			'--shallow-clone' => true,
			'--single-branch' => true,
			'--ref'           => $branch,
		];

		$clone_input = new ArrayInput( $arguments );
		if ( 0 !== $command->run( $clone_input, $output ) ) {
			$this->io->error( "Could not clone {$plugin->name}!" );
			exit( 1 );
		}

		$current_dir = getcwd();

		chdir( "{$this->tmp_dir}/{$plugin->name}" );

		$this->run_process( 'git submodule update --init --recursive' );

		chdir( $submodule->path );

		$this->run_process( 'git fetch' );
		$process = $this->run_process( 'git checkout ' . $upstream_submodule_hash );

		chdir( "{$this->tmp_dir}/{$plugin->name}" );

		$this->io->writeln( "<fg=cyan>Committing {$submodule->name} {$branch}@{$upstream_submodule_hash} to {$plugin->name} {$branch}</>" );
		$this->run_process( 'git commit ' . $submodule->path . ' -m ":fast_forward: https://github.com/moderntribe/' . $plugin->name . '/commit/' . $upstream_submodule_hash . '"' );
		$this->run_process( 'git push origin HEAD' );

		chdir( $current_dir );
	}

	/**
	 * Prepares a repository for the branch analysis/submodule sync process
	 */
	protected function prep_branches( $repo ) {
		$client = $this->get_github_client();
		$branches = $client->api( 'repo' )->branches( $this->org, $repo );

		if ( empty( $this->branches[ $repo ] ) ) {
			$this->branches[ $repo ] = [];
		}

		$branches = array_filter( $branches, static function( $v, $k ) {
			return ! preg_match( '/^dependabot/', $v['name'] );
		}, ARRAY_FILTER_USE_BOTH );

		// If a specific branch was specified, only allow synchronization on that branch.
		if ( $this->branch ) {
			$allowed_branch = $this->branch;

			$branches = array_filter( $branches, static function( $v, $k ) use ( $allowed_branch ) {
				return $allowed_branch === $v['name'];
			}, ARRAY_FILTER_USE_BOTH );
		}

		foreach ( $branches as $branch ) {
			$this->branches[ $repo ][ $branch['name'] ] = $branch['commit']['sha'];
		}

		if ( isset( $this->branches[ $repo ]['main'] ) ) {
			$this->branches[ $repo ]['master'] = $this->branches[ $repo ]['main'];
		}
	}
}
