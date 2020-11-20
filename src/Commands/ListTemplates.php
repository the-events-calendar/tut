<?php
namespace TUT\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TUT\Command as Command;

class ListTemplates extends Command {

	/**
	 * @var bool Should the command prompt for plugin selection?
	 */
	public $do_plugin_selection = true;

	protected function configure() {
		parent::configure();

		$this->setName( 'list-templates' )
		     ->setDescription( 'Lists out the template views and their short descriptions for KB purposes.' )
		     ->setHelp( 'This command provides a list of template views for given plugin(s).' );
	}

	protected function interact( InputInterface $input, OutputInterface $output ) {
		parent::interact( $input, $output );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		foreach ( $this->selected_plugins as $plugin ) {
			$this->io->section( $plugin->name );

			$plugin_dir = $this->origin_dir;

			// If we are already in the plugin directory, add the plugin name.
			if ( ! $this->already_in_plugin_dir ) {
				$plugin_dir .= "/{$plugin->name}";
			}

			// If plugin does not exist, bail.
			if ( ! file_exists( $plugin_dir ) ) {
				$this->io->error( "The {$plugin->name} directory doesn't exist here!" );

				continue;
			}

			// Change directory to the plugin directory.
			chdir( $plugin_dir );

			$template_list = [];
			$dir_path      = "{$plugin_dir}/src/views";

			// Get the directory we want to loop through.
			$dir = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir_path ) );

			// Loop through directory files and process them.
			/** @var \SplFileObject $file */
			foreach ( $dir as $file ) {
				// Skip directories like "." and ".." to avoid file_get_contents errors.
				if ( $file->isDir() ) {
					continue;
				}

				$file_path  = $file->getPathname();
				$short_path = str_replace( $dir_path . '/', '', $file_path );

				// Skip readme/markdown files.
				if ( preg_match( '/\.md$/i', $file_path ) ) {
					continue;
				}

				$content = file_get_contents( $file_path );
				$lines   = (array) explode( "\n", $content );

				$capture = null;
				$stop    = false;

				// Get the first few lines.
				foreach ( $lines as $line ) {
					$line = trim( $line );

					// Are we capturing?
					if ( null !== $capture ) {
						if ( '*' !== $line && '*/' !== $line ) {
							// Continue capturing the content if we aren't on a blank * comment line.

							// Don't capture the content if we see a phpdoc tag.
							if ( preg_match( '/\@(since|deprecated|version|link|see)/i', $line ) ) {
								break;
							}

							// Trim the line of * and spacing, then capture it.
							$line = ltrim( $line, "* \t\n\r\0\x0B" );

							// Remove additional tab characters on the line.
							$line = str_replace( "\t", '', $line );

							// Remove extra spaces on the line.
							$line = preg_replace( '/\s{2,}/', ' ', $line );

							$capture[] = $line;
						} elseif ( ! empty( $capture ) ) {
							// Stop if we have content.
							break;
						}
					} elseif ( '/**' === $line ) {
						// Start capturing the content after starting doc tag.
						$capture = [];
					}
				}

				if ( empty( $capture ) ) {
					// No template information found!
					$capture = 'N/A';
				} else {
					// Glue the string together by spaces.
					$capture = implode( ' ', $capture );
				}

				$template_list[ $short_path ] = $capture;
			}

			if ( empty( $template_list ) ) {
				$this->io->writeln( '<fg=green>No templates found.</>' );
				$this->io->newline();
			} else {
				// Sort templates by path.
				ksort( $template_list );

				foreach ( $template_list as $short_path => $capture ) {
					$this->io->writeln( "{$short_path}\t{$capture}" );
				}
			}

			$this->io->newline();

			// go back a directory
			chdir( '../' );
		}//end foreach

		$output->writeln( '<info>-------------------</info>' );

		$this->io->success( 'DONE' );
	}
}
