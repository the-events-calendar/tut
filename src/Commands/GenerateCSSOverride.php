<?php
namespace TUT\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TUT\Command as Command;

class GenerateCSSOverride extends Command {

	/**
	 * @var bool Should the command prompt for plugin selection?
	 */
	public $do_plugin_selection = true;

	protected function configure() {
		parent::configure();

		$this
			->setName( 'generate-css-override' )
			->setDescription( 'Run build processes on plugins' )
			->addOption( 'search', '', InputOption::VALUE_REQUIRED )
			->addOption( 'replace', '', InputOption::VALUE_REQUIRED )
			->setHelp( 'This command generates CSS with a specific property override' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
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

			$css = $this->search_and_replace( $input->getOption( 'search' ), $input->getOption( 'replace' ) );

			$output->write( $css );

			// go back up to the plugins directory
			chdir( '../' );
		}//end foreach

		$output->writeln( '<info>-------------------</info>' );
		$this->io->success( 'DONE' );
	}

	private function find_files() {

		$files = [];

		if ( file_exists( 'common' ) ) {
			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( 'common/src/resources/css' ) );

			foreach ( $iterator as $file ) {
				if ( $file->isDir() ) {
					continue;
				}

				$files[] = $file->getPathname();
			}
		}

		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( 'src/resources/css' ) );

		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				continue;
			}

			$files[] = $file->getPathname();
		}

		return $files;
	}

	/**
	 * Runs build commands in parallel
	 */
	private function search_and_replace( $search, $replace ) {
		$search = preg_quote( $search );
		$files  = $this->find_files();
		$rules  = [];

		foreach ( $files as $file ) {
			$contents = explode( "\n", file_get_contents( $file ) );
			$contents = array_filter( $contents, static function( $value, $key ) use ( $search ) {
				if ( false !== strstr( $value, ',' ) ) {
					return true;
				}

				if ( false !== strstr( $value, '{' ) ) {
					return true;
				}

				if ( false !== strstr( $value, '}' ) ) {
					return true;
				}

				return preg_match( '/' . $search . '/', $value );
			}, ARRAY_FILTER_USE_BOTH );

			$contents = array_values( $contents );

			for ( $i = 0; $i < count( $contents ); $i++ ) {
				if ( ! preg_match( '/' . $search . '/', $contents[ $i ] ) ) {
					continue;
				}

				if ( false !== strstr( $contents[ $i ], '{' ) ) {
					$rules[] = preg_replace( '/{.*/', '', $contents[ $i ] );
				}

				$index = $i - 1;

				while ( false === strstr( $contents[ $index ], '}' ) ) {
					$rules[] = trim( str_replace( '{', '', trim( $contents[ $index ], ',' ) ) );
					$index--;
				}
			}
		}

		$rules = array_filter( $rules, function( $value, $key ) {
			return false === strstr( $value, '@media' );
		}, ARRAY_FILTER_USE_BOTH );

		$css = implode( ",\n", $rules );
		$css .= " {\n";
		$css .= "\t" . $replace . "\n";
		$css .= "}\n";

		return $css;
	}
}
