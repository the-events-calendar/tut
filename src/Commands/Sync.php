<?php
namespace TUT\Commands;

use TUT\Command as Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Sync extends Command {
	/**
	 * @var string The branch in which the version is being prepared
	 */
	protected $branch;

	/**
	 * @var bool Should the command prompt for plugin selection?
	 */
	public $do_plugin_selection = true;

	protected function configure() {
		parent::configure();

		$this
			->setName( 'sync' )
			->setDescription( 'Sync our Plugins' )
			->setHelp( 'This command allows you to sync all or some plugins to GitHub' )
			->addArgument( 'branch', InputArgument::REQUIRED, 'Which branch will be Syncd' )
			->addOption( 'direction', 'd', InputOption::VALUE_REQUIRED, 'In which direction we should sync the plugins', 'down' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		if ( $input->getOption( 'dry-run' ) ) {
			return;
		}
		$this->branch = $this->branch ?: $input->getArgument( 'branch' );

		$direction = $input->getOption( 'direction' );

		$required_tec_version         = null;
		$required_tec_version_for_all = null;

		$branches = [];

		if ( 'mr' === $this->branch ) {
			$doc = file_get_contents( 'http://inside.tri.be/maintenance-releases/?heckyeah=howweroll' );

			// Fetch all the Given branches
			preg_match_all( '!"https://github.com/the-events-calendar/([^/]+)/tree/([^"]+)"!', $doc, $matches );
			foreach ( $matches[1] as $i => $plugin ) {
				$branches[ $plugin ] = $matches[2][ $i ];
			}
		}

		foreach ( $this->selected_plugins as $plugin ) {
			$this->io->title( $plugin->name );

			if ( $this->already_in_plugin_dir ) {
				$plugin_dir = "{$this->origin_dir}";
			} else {
				$plugin_dir = "{$this->origin_dir}/{$plugin->name}";
			}

			if ( ! file_exists( $plugin_dir ) ) {
				$this->io->warning( "The {$plugin->name} directory doesn't exist here!" );
				continue;
			}

			// cd into the plugin directory
			chdir( $plugin_dir );

			// Define the Branch
			$branch = ! empty( $branches[ $plugin->name ] ) ? $branches[ $plugin->name ] : $this->branch ;

			if ( 'down' === $direction ) {
				if ( 'release' === $branch ) {
					// make sure we have the latest tags
					$process = new Process( 'git fetch --tags' );
					$process->run();

					// grab all tags and determine the most recent release
					$process = new Process( 'git tag --sort="-version:refname"' );
					$process->run();
					$tags = array_map( 'trim', explode( "\n", $process->getOutput() ) );
					$tag = null;
					foreach ( $tags as $t ) {
						if ( preg_match( '/^[^0-9]/', $t ) ) {
							continue;
						}

						$tag = $t;
						break;
					}

					// grab all release branches
					$process = new Process( 'git branch --sort=refname | grep "release/"' );
					$process->run();

					// if there are available branches, attempt to find a release branch that isn't a tag
					// if one can't be found, default to master
					if ( $available_branches = array_map( 'trim', explode( "\n", $process->getOutput() ) ) ) {
						$branch = 'master';

						foreach ( $available_branches as $b ) {
							$b = trim( $b );
							$branch_version = str_replace( 'release/', '', $b );
							if ( false !== array_search( $branch_version, $tags ) ) {
								continue;
							}

							$branch = $b;
							break;
						}
					} else {
						$branch = 'master';
					}
				}

				$this
					->checkout( $branch )
					->pull( $branch )

					// When Pulling we update submodule after
					->update_submodules();
			} else {
				$this
					->checkout( $branch )

					// When Pushing we update submodule before
					->update_submodules()
					->push( $branch );
			}

			// go back up to the plugins directory
			chdir( '../' );
		}//end foreach

		$output->writeln( '<info>-------------------</info>' );
		$this->io->success( 'DONE' );
	}
}
