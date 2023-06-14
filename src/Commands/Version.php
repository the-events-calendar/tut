<?php
namespace TUT\Commands;

use TUT\Command as Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Version extends Command {

	/**
	 * @var string The version that's being prepared
	 */
	protected $version;

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

		$this->setName( 'version' )
			->setDescription( 'Sets the release version on our plugins.' )
			->setHelp( 'This command allows you to set and check the plugin versions and requirements in the relevant files.' )
			->addArgument( 'version', InputArgument::OPTIONAL, 'The version that\'s being prepared.', false )
			->addArgument( 'branch', InputArgument::OPTIONAL, 'The branch in which the version is being prepared.', false );
	}

	protected function interact( InputInterface $input, OutputInterface $output ) {
		parent::interact( $input, $output );

		while ( empty( $input->getArgument( 'version' ) ) && empty( $this->version ) ) {
			$this->version = $this->ask_for_string( 'What version are you preparing?', '' );

			if ( ! $this->ask_for_confirmation( "Are you sure the version <info>{$this->version}</info> is correct (y/n)?" ) ) {
				$this->version = null;
			}
		}

		while ( empty( $input->getArgument( 'branch' ) ) && empty( $this->branch ) ) {
			$this->branch = $this->ask_for_string( 'Within which branch would you like to change the version numbers?', 'develop' );

			if ( ! $this->ask_for_confirmation( "Are you sure the branch <info>{$this->branch}</info> is correct (y/n)?" ) ) {
				$this->branch = null;
			}
		}
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		if ( $input->getOption( 'dry-run' ) ) {
			return;
		}

		$this->version = $this->version ? : $input->getArgument( 'version' );
		$this->branch  = $this->branch ? : $input->getArgument( 'branch' );

		$required_tec_version         = null;
		$required_tec_version_for_all = null;

		foreach ( $this->selected_plugins as $plugin ) {
			$this->io->section( $plugin->name );

			if ( $this->already_in_plugin_dir ) {
				$plugin_dir = "{$this->origin_dir}";
			} else {
				$plugin_dir = "{$this->origin_dir}/{$plugin->name}";
			}

			if ( ! file_exists( $plugin_dir ) ) {
				$this->io->error( "The {$plugin->name} directory doesn't exist here!" );
				exit;
			}

			// cd into the plugin directory
			chdir( $plugin_dir );

			/**
			 * This whole section is somewhat deprecated we no longer use these variables to determine dependencies
			 */
			if ( 'the-events-calendar' === $plugin->name ) {
				$file = file_get_contents( 'src/Tribe/Main.php' );
				preg_match( "/const\s+MIN_ADDON_VERSION\s*\=\s*'([^']*).*/", $file, $matches );
				$current_version = $matches[1];

				if ( $this->ask_for_confirmation(
					'Do you wish to update the minimum version required for Add-Ons? (it is currently set to '
					. $current_version . ')'
				)
				) {
					$minimum_version = $this->ask_for_string( 'What should the minimum version for Add-Ons be set to?', $required_tec_version );
					$file            = preg_replace(
						"/(const\s+MIN_ADDON_VERSION\s*\=\s*')[^']*(.*)/", '${1}' . $minimum_version . '${2}', $file
					);
					file_put_contents( 'src/Tribe/Main.php', $file );
				}
			} elseif ( 'event-tickets' === $plugin->name ) {
				$file = file_get_contents( 'src/Tribe/Main.php' );
				preg_match( "/const\s+MIN_TEC_VERSION\s*\=\s*'([^']*).*/", $file, $matches );
				$current_version = $matches[1];

				if ( $this->ask_for_confirmation(
					'Do you wish to update the minimum required TEC version for Event Tickets? (it is currently set to '
					. $current_version . ')'
				)
				) {
					$minimum_version = $this->ask_for_string(
						'What should the minimum required TEC version for Event Tickets be set to?', $required_tec_version
					);
					$file            = preg_replace(
						"/(const\s+MIN_TEC_VERSION\s*\=\s*')[^']*(.*)/", '${1}' . $minimum_version . '${2}', $file
					);
					file_put_contents( 'src/Tribe/Main.php', $file );
				}
			} elseif ( 'event-tickets-plus' === $plugin->name ) {
				$file = file_get_contents( 'src/Tribe/Main.php' );
				preg_match( "/const\s+REQUIRED_TICKETS_VERSION\s*\=\s*'([^']*).*/", $file, $matches );
				$current_version = $matches[1];

				if ( $this->ask_for_confirmation(
					'Do you wish to update the required Event Tickets version? (it is currently set to '
					. $current_version . ')'
				)
				) {
					$minimum_version = $this->ask_for_string(
						'What should the minimum required Event Tickets version be set to?', $required_tec_version
					);
					$file            = preg_replace( "/(const\s+REQUIRED_TICKETS_VERSION\s*\=\s*')[^']*(.*)/", '${1}' . $minimum_version . '${2}', $file );
					file_put_contents( 'src/Tribe/Main.php', $file );
				}
			} else {
				if ( ! $required_tec_version_for_all
					&& $this->ask_for_confirmation(
						'Do you wish to update the required TEC version?'
					)
				) {
					$required_tec_version = $this->ask_for_string(
						"What should the minimum required TEC version for <info>{$plugin->name}</info> be set to?", $required_tec_version
					);

					if ( null === $required_tec_version_for_all ) {
						$required_tec_version_for_all = $this->ask_for_confirmation(
							'Should all plugins that you are updating have that same minimum required TEC version?'
						);
					}
				}
			}

			$version_storage_file = $this->get_plugin_version_storage_file_path( $plugin );
			if ( file_exists( $version_storage_file ) ) {
				$file = file_get_contents( $version_storage_file );

				$file = preg_replace( "/(const\s+VERSION\s*\=\s*')[^']*(.*)/", '${1}' . $this->version . '${2}', $file );
				$file = preg_replace( "/(pluginVersion\s*\=\s*')[^']*(.*)/", '${1}' . $this->version . '${2}', $file );
				$file = preg_replace( "/(this-\>currentVersion\s*\=\s*')[^']*(.*)/", '${1}' . $this->version . '${2}', $file );

				file_put_contents( $version_storage_file, $file );
			}

			if ( file_exists( 'package.json' ) ) {
				$file = file_get_contents( 'package.json' );
				$file = preg_replace(
					'/("version"\:[^"]*")[^"]*(.*)/',
					'${1}' . $this->json_compatible_version( $this->version ) . '${2}',
					$file
				);
				file_put_contents( 'package.json', $file );
			}

			if ( file_exists( 'readme.txt' ) ) {
				$file = file_get_contents( 'readme.txt' );
				$file = preg_replace( '/(Stable tag\:\s+).*/', '${1}' . $this->version, $file );
				file_put_contents( 'readme.txt', $file );
			}

			// if there's a package.json, we're in the plugin root. Update the boostrap file's version number
			if ( file_exists( 'package.json' ) ) {
				$file = file_get_contents( $plugin->bootstrap );
				$file = preg_replace( '/(Version\:\s*).*/', '${1}' . $this->version, $file );
				file_put_contents( $plugin->bootstrap, $file );
			}

			$this->commit_prompt();

			// go back a directory
			chdir( '../' );
		}//end foreach

		$output->writeln( '<info>-------------------</info>' );
		$this->io->success( 'DONE' );
	}

}
