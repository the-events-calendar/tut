<?php
namespace TUT\Commands;

use Graze\ParallelProcess\Display\Lines;
use Graze\ParallelProcess\Pool;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use TUT\Command as Command;

class Build extends Command {
	/**
	 * @var string The branch in which the version is being prepared
	 */
	protected $branch;

	/**
	 * @var bool Has a common/ directory
	 */
	private $has_common = false;

	/**
	 * @var bool Should the command prompt for plugin selection?
	 */
	public $do_plugin_selection = true;

	/**
	 * The $HOME directory (without trailing slash).
	 *
	 * @return string
	 */
	private static function get_home_dir() {
		return (string) getenv( 'HOME' );
	}

	/**
	 * Returns the current Operating System family.
	 *
	 * @return string The human-readable name of the OS PHP is running on. One of `Linux`, `macOS`, or `Unknown`.
	 */
	private function os() {
		$map = [
			'dar' => 'macOS',
			'lin' => 'Linux',
		];

		$key = strtolower( substr( PHP_OS, 0, 3 ) );

		return isset( $map[ $key ] ) ? $map[ $key ] : 'Unknown';
	}

	/**
	 * Returns the source command based on OS.
	 *
	 * @return string The source command for the OS PHP is running on.
	 */
	private function get_source_command() {
		return 'macOS' === $this->os() ? 'source' : '.';
	}

	protected function configure() {
		parent::configure();

		$this
			->setName( 'build' )
			->setDescription( 'Run build processes on plugins' )
			->setHelp( 'This command allows you to run the build processes across all or some plugins in a directory' );
	}

	/**
	 * Whether NVM is detected on the system.
	 *
	 * @return bool
	 */
	private function nvm_exists() {
		return file_exists( $this->get_nvm_path() );
	}

	/**
	 * The expected location of NVM, whether or not it exists.
	 *
	 * @return string
	 */
	private function get_nvm_path() {
		return self::get_home_dir() . '/.nvm/nvm.sh';
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		if( ! $this->nvm_exists() ) {
			$this->io->error( 'NVM needs to exist within ' . self::get_home_dir() );
			exit( 1 );
		}

		foreach ( $this->selected_plugins as $plugin ) {
			$this->io->title( $plugin->name );

			$plugin_dir = $this->get_plugin_dir( $plugin );

			if ( ! file_exists( $plugin_dir ) ) {
				$this->io->warning( "The {$plugin->name} directory doesn't exist here!" );
				continue;
			}

			// cd into the plugin directory
			chdir( $plugin_dir );

			$this->has_common = file_exists( 'common' );

			$this->build();

			// go back up to the plugins directory
			chdir( '../' );
		}//end foreach

		$output->writeln( '<info>-------------------</info>' );
		$this->io->success( 'DONE' );
	}

	/**
	 * Runs build commands in parallel
	 */
	private function build() {
		$pool = new Pool();

		$this->output->writeln( '<fg=cyan>* Running composer install</>', OutputInterface::VERBOSITY_VERBOSE );
		$this->output->writeln( '<fg=cyan>* Running npm install</>', OutputInterface::VERBOSITY_VERBOSE );

		$pool->add( new Process( 'composer dump-autoload && composer install' ), [ 'composer' ] );
		$pool->add( new Process( $this->get_source_command() . ' ' . $this->get_nvm_path() . ' && nvm use && npm install && npm update product-taskmaster' ), [ 'npm' ] );

		if ( $this->has_common ) {
			$pool->add( new Process( 'cd common && composer dump-autoload && composer install' ), [ 'composer common' ] );
			$pool->add( new Process( 'cd common && ' . $this->get_source_command() . ' ' . $this->get_nvm_path() . ' && nvm use && npm install && npm update product-taskmaster' ), [ 'npm common' ] );
		}

		$lines = new Lines( $this->output, $pool );
		$lines->run();

		$this->output->writeln( '<fg=cyan>* Gulping</>', OutputInterface::VERBOSITY_VERBOSE );

		$pool = new Pool();

		$pool->add( new Process( $this->get_source_command() . ' ' . $this->get_nvm_path() . ' && nvm use && ./node_modules/.bin/gulp' ), [ 'gulp' ] );

		if ( $this->has_common ) {
			$pool->add( new Process( 'cd common && ' . $this->get_source_command() . ' ' . $this->get_nvm_path() . ' && nvm use && ./node_modules/.bin/gulp' ), [ 'gulp common' ] );
		}

		$lines = new Lines( $this->output, $pool );
		$lines->run();

		if ( file_exists( 'webpack.config.js' ) ) {
			$this->output->writeln( '<fg=cyan>* Gulp Webpack</>', OutputInterface::VERBOSITY_VERBOSE );
			// gulp webpack with a timeout of 15 minutes
			$this->run_process( $this->get_source_command() . ' ' . $this->get_nvm_path() . ' && nvm use && ./node_modules/.bin/gulp webpack', true, 900 );
		}
	}
}
