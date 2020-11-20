<?php
namespace TUT\Commands;

use TUT\Command as Command;

use __;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use LucidFrame\Console\ConsoleTable;

class BranchList extends Command {
	protected function configure() {
		parent::configure();

		$this
			->setName( 'show-branch' )
			->setDescription( 'List the currently installed TEC plugins with their branches.' )
			->setHelp( 'Gets the currently checked-out branches.' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$available_plugins = __::pluck( $this->config->plugins, 'name' );

		$table = new ConsoleTable();
		$table
			->addHeader( 'Plugin' )
			->addHeader( 'Branch' );

		foreach ( $available_plugins as $plugin ) {
			$plugin     = $this->get_plugin( $plugin );
			$plugin_dir = $this->get_plugin_dir( $plugin );

			if ( ! file_exists( $plugin_dir ) ) {
				continue;
			}

			// cd into the plugin directory
			chdir( $plugin_dir );

			$process = $this->run_process( 'git branch --show-current' );
			$branch = trim( $process->getOutput() );

			if ( false !== strpos( $branch, 'error: unknown option' ) ) {
				$process = $this->run_process( 'git rev-parse --abbrev-ref HEAD' );
				$branch = trim( $process->getOutput() );
			}

			if ( empty( $branch ) ) {
				$branch = '[unknown]';
			}

			$table
				->addRow()
				->addColumn( $plugin->name )
				->addColumn( $branch );

			// go back up to the plugins directory
			chdir( '../' );
		}

		$table->display();
	}
}
