<?php
namespace TUT\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TUT\Command as Command;

class GetZipFilename extends Command {

	/**
	 * @var bool Should the command prompt for plugin selection?
	 */
	public $do_plugin_selection = true;

	/**
	 * @var string The branch in which the version is being prepared
	 */
	protected $branch;

	protected function configure() {
		parent::configure();

		$this
			->setName( 'get-zip-filename' )
			->setDescription( 'Gets the expected zip filename for the plugin.' )
			->setHelp( 'Gets the expected zip filename for the plugin.' )
			->addOption( 'final', '', InputOption::VALUE_NONE, 'Get the zip filename for production.' );
		;
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$final = (bool) $input->getOption( 'final' );

		foreach ( $this->selected_plugins as $plugin ) {
			$plugin_dir = $this->get_plugin_dir( $plugin );

			if ( ! file_exists( $plugin_dir ) ) {
				$this->io->error( "The {$plugin->name} directory doesn't exist here!" );
				return 1;
			}

			// cd into the plugin directory
			chdir( $plugin_dir );

			$zip_name = $plugin->name;

			if ( file_exists( 'package.json' ) ) {
				$package_json = json_decode( file_get_contents( 'package.json' ) );
				if ( $plugin->name === 'learndash-core' ) {
					$zip_name     = 'learndash-core';
				} else {
					$zip_name     = $package_json->_zipname;
				}
			}

			if ( ! file_exists( $plugin->main ) ) {
				$this->io->error( "Could not find {$plugin->main}" );
				return 1;
			}

			$file = file_get_contents( $plugin->main );

			if ( preg_match( '/define/', $plugin->version ) ) {
				preg_match( '/.*' . preg_quote( $plugin->version ) . "(?<version>[^']*)'.*/", $file, $matches );
			} else {
				preg_match( '/.*' . $plugin->version . "[^']*'(?<version>[^']*)'.*/", $file, $matches );
			}

			if ( ! isset( $matches['version'] ) ) {
				$output->writeln( "Could not correctly parse version of {$plugin->name}; is it correctly configured?</error>" );
				$version = 'undetermined';
			} else {
				$version = $matches['version'];
			}

			$hash      = trim( shell_exec( 'git rev-parse --short=8 HEAD' ) );
			$timestamp = trim( shell_exec( 'git --no-pager show -s --format=%ct HEAD' ) );
			$filename  = "{$zip_name}.{$version}-dev-{$timestamp}-${hash}.zip";

			if ( $final ) {
				$filename  = "{$zip_name}.{$version}.zip";
			}

			$output->writeln( $filename );

			// go back up to the plugins directory
			chdir( '../' );
		}//end foreach

		return 0;
	}
}
