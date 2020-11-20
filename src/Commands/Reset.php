<?php
namespace TUT\Commands;

use TUT\Command as Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Reset extends Command {
	/**
	 * @var string The branch in which the version is being prepared.
	 */
	protected $branch;

	/**
	 * @var boolean Whether or not a reset --hard HEAD should be performed.
	 */
	protected $hard = false;

	/**
	 * @var boolean Whether or not a stash should be performed.
	 */
	protected $stash = false;

	/**
	 * @var bool Should the command prompt for plugin selection?
	 */
	public $do_plugin_selection = true;

	protected function configure() {
		parent::configure();

		$this
			->setName( 'reset' )
			->setDescription( 'Change repository branch back to main/master' )
			->setHelp( 'Change repository branch back to main/master' )
			->addOption( 'hard', '', InputOption::VALUE_NONE, 'Perform a hard reset (recursively)' )
			->addOption( 'stash', '', InputOption::VALUE_NONE, 'Perform a stash (recursively)' )
			->addOption( 'branch', '', InputOption::VALUE_OPTIONAL, 'Perform a hard reset (recursively)', 'master' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->branch = $this->branch ?: $input->getOption( 'branch' );
		$this->hard  = $this->hard ?: $input->getOption( 'hard' );
		$this->stash  = $this->stash ?: $input->getOption( 'stash' );

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

			$prep_command = null;

			if ( $this->stash ) {
				$prep_command = 'git stash';
			} elseif ( $this->hard ) {
				$prep_command = 'git reset --hard HEAD';
			}

			if ( $prep_command ) {

				$plugin_dir = getcwd();
				$submodules = array_filter( $this->get_submodule_paths() );
				$dirs       = array_merge( [ '.' ], $submodules );

				foreach ( $dirs as $dir ) {
					if ( ! file_exists( $dir ) ) {
						continue;
					}

					chdir( $dir );

					if ( $this->has_changes_in_current_path() ) {
						$output->writeln( "* Running {$prep_command} in {$dir}" );
						$this->run_process( $prep_command );
					}

					chdir( $plugin_dir );
				}
			}

			$output->writeln( "* Checking out {$this->branch}, pulling, and updating submodules" );

			$this
				->checkout( $this->branch )
				->pull( $this->branch )
				->update_submodules();

			// go back up to the plugins directory
			chdir( '../' );
		}//end foreach

		$output->writeln( '<info>-------------------</info>' );
		$this->io->success( 'DONE' );
	}
}
