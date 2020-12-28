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
	protected $org = 'the-events-calendar';

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

		$this->branch  = $this->branch ?: $input->getOption( 'branch' );

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
						$this->update_hash( $plugin, $branch, $submodule, $this->branches[ $plugin->name ][ $branch ], $upstream_submodule_hash );
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
	}

	/**
	 * Updates the hash of the given submodule on the given branch.
	 *
	 * @param stdClass $repo Repository (plugin) object from tut.json.
	 * @param string $branch Branch on which to make the hash update.
	 * @param stdClass $submodule Submodule to update.
	 * @param string $branch_hash Current hash of the branch.
	 * @param string $new_hash Submodule hash the branch should be updated to.
	 *
	 * @throws \Github\Exception\MissingArgumentException
	 */
	protected function update_hash( $repo, $branch, $submodule, $branch_hash, $new_hash ) {
		$client = $this->get_github_client();

		$this->io->writeln( "<fg=cyan>Committing {$submodule->name} {$branch}@{$new_hash} to {$repo->name} {$branch}</>" );

		$tree_data = [
			'base_tree' => $branch_hash,
			'tree' => [
				[
					'path' => $submodule->path,
					'mode' => '160000', // Submodule commit.
					'type' => 'commit',
					'sha'  => $new_hash,
				],
			],
		];

		$tree = $client->api( 'gitData' )->trees()->create( $this->org, $repo->name, $tree_data );

		if ( empty( $tree['sha'] ) ) {
			$this->io->error( 'Could not create a submodule commit tree' );
			exit( 1 );
		}

		$commit_data = [
			'message' => ":fast_forward: https://github.com/{$this->org}/{$submodule->name}/commit/{$new_hash}",
			'tree'    => $tree['sha'],
			'parents' => [
				$branch_hash,
			],
		];

		$commit = $client->api( 'gitData' )->commits()->create( $this->org, $repo->name, $commit_data );

		if ( empty( $commit['sha'] ) ) {
			$this->io->error( 'Could not create a submodule commit' );
			exit( 1 );
		}

		$reference_data = [
			'sha' => $commit['sha'],
		];

		$reference = $client->api( 'gitData' )->references()->update( $this->org, $repo->name, "heads/{$branch}", $reference_data );

		if ( empty( $reference['object']['sha'] ) ) {
			$this->io->error( "Could not update {$branch} with the latest submodule commit" );
			exit( 1 );
		}
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
