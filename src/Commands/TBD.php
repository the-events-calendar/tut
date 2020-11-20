<?php
namespace TUT\Commands;

use TUT\Command as Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TBD extends Command {

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

		$this->setName( 'tbd' )
		     ->setDescription( 'Hunts for docblock TBD occurrences and tells you where to change them' )
		     ->setHelp( 'This command alerts if TBD exists in the codebase' );
	}

	protected function interact( InputInterface $input, OutputInterface $output ) {
		parent::interact( $input, $output );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		if ( $input->getOption( 'dry-run' ) ) {
			return;
		}

		$found_tbds = false;

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

			$files_to_skip = [
				'(\.(png|jpg|jpeg|svg|gif|ico)$)',
				'(\.min\.(css|js)$)',
			];

			$matched_lines = [];
			$current_dir = getcwd();

			$dir = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( "{$plugin_dir}/src" ) );
			foreach ( $dir as $file ) {
				// Skip directories like "." and ".." to avoid file_get_contents errors.
				if ( $file->isDir() ) {
					continue;
				}

				$file_path = $file->getPathname();
				$short_path = str_replace( $current_dir . '/', '', $file_path );

				if ( preg_match( '/\.((min\.css)|(min\.js)|png|jpg|jpeg|svg|gif|ico)$/', $file_path ) ) {
					continue;
				}

				$content   = file_get_contents( $file_path );
				$lines     = explode( "\n", $content );
				$num_lines = count( $lines );

				// loop over the lines
				for ( $line = 0; $line < $num_lines; $line++ ) {
					$lines[ $line ] = trim( $lines[ $line ] );

					// does the line match?
					if (
						preg_match( '/\*\s*\@(since|deprecated|version)\s.*tbd/i', $lines[ $line ] )
						|| preg_match( '/_deprecated_\w\(.*[\'"]tbd[\'"]/i', $lines[ $line ] )
						|| preg_match( '/[\'"]tbd[\'"]/i', $lines[ $line ] )
					) {

						// if the file isn't being tracked already, add it to the array
						if ( ! isset( $matched_lines[ $short_path ] ) ) {
							$matched_lines[ $short_path ] = [
								'lines' => [],
							];
						}

						$matched_lines[ $short_path ]['lines'][ $line + 1 ] = $lines[ $line ];
					}
				}
			}

			if ( $matched_lines ) {
				$found_tbds = true;
				$this->io->writeln( "<fg=red>TBDs have been found in {$plugin->name}!</>" );
				foreach ( $matched_lines as $file_path => $info ) {
					$this->io->writeln( "<fg=cyan>{$file_path}</>" );
					foreach ( $info['lines'] as $line_num => $line ) {
						$this->io->writeln( "<fg=yellow>{$line_num}:</> {$line}" );
					}
					$this->io->newline();
				}
			} else {
				$this->io->writeln( '<fg=green>No TBDs found!</>' );
				$this->io->newline();
			}
			$this->io->newline();

			// go back a directory
			chdir( '../' );
		}//end foreach

		$output->writeln( '<info>-------------------</info>' );
		if ( $found_tbds ) {
			$this->io->error( 'TBDs found!' );
		} else {
			$this->io->success( 'DONE' );
		}
	}
}
