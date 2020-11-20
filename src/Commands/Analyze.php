<?php
namespace TUT\Commands;

use TUT\Command as Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Analyze extends Command {

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

		$this->setName( 'analyze' )
			->setDescription( 'Hunts for information into the changes made to plugins' )
			->setHelp( 'This will analyze changes made on plugins' )
			->addOption( 'output', 'o', InputOption::VALUE_OPTIONAL, 'Which type of output we are looking for', 'changelog' )
			->addOption( 'compare', 'c', InputOption::VALUE_OPTIONAL, 'To which commit we are comparing to.', null )
			->addOption( 'memory', 'm', InputOption::VALUE_OPTIONAL, 'When comparing with old Commits, make sure to bump the total memory, use MB.', null );
	}

	public function execute_on_plugin( $plugin ) {
		$memory = $this->input->getOption( 'memory' );

		if ( is_numeric( $memory ) ) {
			// Required for long diffs
			@ini_set('memory_limit', (int)$memory . 'M');
		}

		$output = $this->input->getOption( 'output' );
		$compare = $this->input->getOption( 'compare' );
		if ( null === $compare ) {
			$compare = trim( shell_exec('echo $(git describe --tags $(git rev-list --tags --max-count=1))') );
		}
		$folder = 'src';
		$command = 'git diff -U0 ' . $compare . ' -- ' . $folder;
		$diff = shell_exec( $command );
		$lines = explode( "\n", $diff );
		$end = count( $lines );
		$files = $this->get_files( $lines );
		$file_lines = [];


		foreach ( $files as $line => $file ) {
			$next = next( $files );
			$next_line = key( $files );
			if ( false === $next ) {
				$next_line = $end;
			}

			$file_lines[ $file ] = array_slice( $lines, $line, $next_line );
		}

		/**
		 * Single and multi-line function call regex with a preceding +/-
		 *
		 * For sprintf parameters:
		 * %1$s = +/-
		 * %2$s = function name
		 */
		$changed_func_regex = '/\%1$s[^\n]*%2$s\( +[\'\"]+([^\'\"]+)[\'\"]+/is';

		$patterns['filters'] = (object) [
			'added'   => sprintf( $changed_func_regex, '+', 'apply_filters' ),
			'removed' => sprintf( $changed_func_regex, '-', 'apply_filters' ),
		];

		$patterns['actions'] = (object) [
			'added'   => sprintf( $changed_func_regex, '+', 'do_action' ),
			'removed' => sprintf( $changed_func_regex, '-', 'do_action' ),
		];

		$patterns['views'] = '/diff --git a\/src\/views\/([^\.]*)\.php/i';

		// Can have some bugs inside of classes, we need to improve
		// $patterns['functions'] = (object) [
		// 	'removed' => '/\-.*function ([^\(]*)\(/i',
		// ];


		$results = array();

		foreach ( $patterns as $key => $pattern ) {
			if ( is_string( $pattern ) ) {
				preg_match_all( $pattern, $diff, $matches );
				$result = $matches[1];
			} else {
				$removed = $added = [];

				if ( isset( $pattern->removed ) ) {
					preg_match_all( $pattern->removed, $diff, $removed_matches );
					$removed = array_filter( $removed_matches[1] );
				}
				if ( isset( $pattern->added ) ) {
					preg_match_all( $pattern->added, $diff, $added_matches );
					$added = array_filter( $added_matches[1] );
				}

				$result = (object) [
					'added' => array_diff( $added, $removed ),
					'removed' => array_diff( $removed, $added ),
				];
			}


			$results[ $key ] = $result;
		}

		foreach ( $results as $key => $items ) {

			if ( 'changelog' === $output ) {

				if ( is_array( $items ) ) {
					$this->io->writeln( sprintf( '* Tweak - Changed %s: %s', $key, '`' . implode( '`, `', $items ) . '`' ) );
				} else {
					if ( ! empty( $items->added ) ) {
						$this->io->writeln( sprintf( '* Tweak - Added %s: %s', $key, '`' . implode( '`, `', $items->added ) . '`' ) );
					}

					if ( ! empty( $items->removed ) ) {
						$this->io->writeln( sprintf( '* Tweak - Removed %s: %s', $key, '`' . implode( '`, `', $items->removed ) . '`' ) );
					}
				}
			} elseif ( 'list' === $output ) {

				if ( is_array( $items ) ) {
					$this->io->writeln( 'Changed' );
					foreach ( $items as $line ) {
						$this->io->writeln( '- ' . $line  );
					}
				} else {
					if ( ! empty( $items->added ) ) {
						$this->io->writeln( 'Added' );
						foreach ( $items->added as $line ) {
							$this->io->writeln( '- ' . $line  );
						}
					}

					if ( ! empty( $items->removed ) ) {
						$this->io->writeln( 'Removed' );
						foreach ( $items->removed as $line ) {
							$this->io->writeln( '- ' . $line  );
						}
					}
				}
			} else {
				$pieces = [];

				if ( is_array( $items ) ) {
					$pieces[] = sprintf( '<h4>%s</h4>', ucfirst( $key ) );
					$pieces[] = '<ul>';
					foreach ( $items as $item ) {
						$pieces[] = "\t" . sprintf( '<li><samp>%s</samp></li>', $item );
					}
					$pieces[] = '</ul>';
				} else {
					if ( ! empty( $items->added ) ) {
						$pieces[] = sprintf( '<h4>%s added</h4>', ucfirst( $key ) );
						$pieces[] = '<ul>';
						foreach ( $items->added as $item ) {
							$pieces[] = "\t" . sprintf( '<li><samp>%s</samp></li>', $item );
						}
						$pieces[] = '</ul>';
					}

					if ( ! empty( $items->removed ) ) {
						$pieces[] = sprintf( '<h4>%s removed</h4>', ucfirst( $key ) );
						$pieces[] = '<ul>';
						foreach ( $items->removed as $item ) {
							$pieces[] = "\t" . sprintf( '<li><samp>%s</samp></li>', $item );
						}
						$pieces[] = '</ul>';
					}
				}

				$this->io->writeln( implode( "\n", $pieces ) );
			}
		}

	}

	protected function get_files( $lines ) {
		$files = [];
		$pattern = '/diff --git a\/([^\.]*\.[^ ]+)/i';
		foreach ( $lines as $i => $line ) {
			preg_match( $pattern, $line, $match );

			if ( empty( $match ) ) {
				continue;
			}

			$found = end( $match );
			$files[ $i ] = $found;
		}

		return $files;
	}

}
