<?php

namespace TUT\Commands\SVN;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Tag extends Command {
	/**
	 * Stores the input interface.
	 *
	 * @since 1.2.8
	 *
	 * @var InputInterface|null
	 */
	protected $input;

	/**
	 * Stores the output interface.
	 *
	 * @since 1.2.8
	 *
	 * @var OutputInterface|null
	 */
	protected $output;

	/**
	 * Stores the command steps to cleanup.
	 *
	 * @since 1.2.8
	 *
	 * @var array
	 */
	protected $cleanup_steps = [];

	/**
	 * Stores the temporary directory to use.
	 *
	 * @since 1.2.8
	 *
	 * @var string|null
	 */
	protected $temp_dir;

	/**
	 * Stores the plugin slug.
	 *
	 * @since 1.2.8
	 *
	 * @var string|null
	 */
	protected $plugin_slug;

	/**
	 * Stores the source tag.
	 *
	 * @since 1.2.8
	 *
	 * @var string|null
	 */
	protected $source_tag;

	/**
	 * Stores the destination tag.
	 *
	 * @since 1.2.8
	 *
	 * @var string|null
	 */
	protected $destination_tag;

	/**
	 * Stores a list of allowed steps.
	 *
	 * @since 1.2.9
	 *
	 * @var array<string>
	 */
	protected $allowed_steps = [
		'remove_temp_dir',
		'remove_remote_destination_tag',
	];

	/**
	 * @since 1.2.8
	 *
	 * @var int The exit code to use when the command succeeds.
	 */
	const CMD_SUCCESS = 0;

	/**
	 * @since 1.2.8
	 *
	 * @var int The exit code to use when the command fails.
	 */
	const CMD_FAILURE = 1;

	/**
	 * @since 1.2.8
	 *
	 * @var string Store the base URL for the WordPress.org SVN repository.
	 */
	const WP_ORG_URL = 'https://plugins.svn.wordpress.org/';

	/**
	 * Configures the command.
	 *
	 * @since 1.2.8
	 */
	protected function configure() {
		$temp_dir = '/tmp/svn-tag/';

		$this
			->setName( 'svn:tag' )
			->setDescription( 'Creates a new SVN tag and applies changes from a ZIP file.' )
			->setHelp( 'This command allows you to create a new SVN tag from an existing tag and apply changes from a ZIP file.' )
			->addArgument( 'plugin', InputArgument::REQUIRED, 'The slug of the Plugin on WordPress.org.' )
			->addArgument( 'source_tag', InputArgument::REQUIRED, 'The SVN tag to be copied.' )
			->addArgument( 'destination_tag', InputArgument::REQUIRED, 'The new SVN tag.' )
			->addOption( 'zip_url', 'z', InputOption::VALUE_OPTIONAL, 'The URL of the ZIP file with changes to apply.' )
			->addOption( 'temp_dir', 't', InputOption::VALUE_OPTIONAL, 'The temporary directory to use.', $temp_dir )
			->addOption( 'memory_limit', 'm', InputOption::VALUE_OPTIONAL, 'How much memory we clear for usage, since some of the operations can be expensive.', '512M' );
	}

	protected function has_cleanup_step( string $step ): bool {
		return in_array( $step, $this->get_cleanup_steps(), true );
	}

	protected function get_cleanup_steps(): array {
		return $this->cleanup_steps;
	}

	/**
	 * Includes a cleanup step, if it's an allowed step.
	 *
	 * @since 1.2.8
	 *
	 * @param string $step
	 */
	protected function include_cleanup_step( string $step ): void {
		if ( ! in_array( $step, $this->allowed_steps, true ) ) {
			return;
		}

		$this->cleanup_steps[] = $step;
	}

	/**
	 * Removes a cleanup step, if it's an allowed step.
	 *
	 * @since 1.2.9
	 *
	 * @param string $step
	 */
	protected function remove_cleanup_step( string $step ): void {
		if ( ! in_array( $step, $this->allowed_steps, true ) ) {
			return;
		}

		$index = array_search( $step, $this->cleanup_steps, true );

		// Bail if the step is not in the array.
		if ( ! isset( $this->cleanup_steps[ $index ] ) ) {
			return;
		}

		unset( $this->cleanup_steps[ $index ] );

		// Reindex the array.
		$this->cleanup_steps = array_values( $this->cleanup_steps );
	}

	/**
	 * Gets the input interface.
	 *
	 * @since 1.2.8
	 *
	 * @return InputInterface The input interface.
	 */
	protected function get_input(): InputInterface {
		return $this->input;
	}

	/**
	 * Sets the input interface.
	 *
	 * @since 1.2.8
	 */
	protected function set_input( InputInterface $input ): void {
		$this->input = $input;
	}

	/**
	 * Sets the output interface.
	 *
	 * @since 1.2.8
	 */
	protected function set_output( OutputInterface $output ): void {
		$this->output = $output;
	}

	/**
	 * Gets the output interface.
	 *
	 * @since 1.2.8
	 *
	 * @return OutputInterface The output interface.
	 */
	protected function get_output(): OutputInterface {
		return $this->output;
	}

	/**
	 * Sets the temporary directory.
	 *
	 * @since 1.2.8
	 */
	protected function set_temp_dir( string $dir ): void {
		$this->temp_dir = $dir;
	}

	/**
	 * Gets the temporary directory.
	 *
	 * @since 1.2.8
	 *
	 * @return string The temporary directory.
	 */
	protected function get_temp_dir(): string {
		return $this->temp_dir;
	}

	/**
	 * Sets the plugin slug.
	 *
	 * @since 1.2.8
	 */
	protected function set_plugin_slug( string $plugin_slug ): void {
		$this->plugin_slug = $plugin_slug;
	}

	/**
	 * Gets the plugin slug.
	 *
	 * @since 1.2.8
	 *
	 * @return string The plugin slug.
	 */
	protected function get_plugin_slug(): string {
		return $this->plugin_slug;
	}

	/**
	 *
	 *
	 * @since 1.2.8
	 */
	protected function set_destination_tag( string $tag ): void {
		$this->destination_tag = $tag;
	}

	/**
	 * Gets the destination tag.
	 *
	 * @since 1.2.8
	 *
	 * @return string The destination tag.
	 */
	protected function get_destination_tag(): string {
		return $this->destination_tag;
	}

	/**
	 * Sets the source tag.
	 *
	 * @since 1.2.8
	 */
	protected function set_source_tag( string $tag ): void {
		$this->source_tag = $tag;
	}

	/**
	 * Gets the source tag.
	 *
	 * @since 1.2.8
	 *
	 * @return string The source tag.
	 */
	protected function get_source_tag(): string {
		return $this->source_tag;
	}

	/**
	 * Gets the URL for the plugin on WordPress.org.
	 *
	 * @since 1.2.8
	 *
	 * @return string The URL for the plugin on WordPress.org.
	 */
	protected function get_svn_url(): string {
		return self::WP_ORG_URL . $this->get_plugin_slug();
	}

	/**
	 * Executes the command.
	 *
	 * @since 1.2.8
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->set_input( $input );
		$this->set_output( $output );

		$memory_limit = $input->getOption( 'memory_limit' );
		@ini_set( 'memory_limit', $memory_limit );

		$id              = date( 'Y-m-d-H-m-s' ) . '-' . substr( uniqid(), 0, 12 );
		$plugin          = $input->getArgument( 'plugin' );
		$source_tag      = $input->getArgument( 'source_tag' );
		$destination_tag = $input->getArgument( 'destination_tag' );
		$zip_url         = $input->getOption( 'zip_url' );
		$temp_dir        = $input->getOption( 'temp_dir' );

		$temp_dir = rtrim( $temp_dir, '/' ) . "/tag-{$id}";

		$this->set_temp_dir( $temp_dir );
		$this->set_source_tag( $source_tag );
		$this->set_destination_tag( $destination_tag );
		$this->set_plugin_slug( $plugin );

		$local_repo = "{$temp_dir}/repo";
		$svn_url    = self::WP_ORG_URL . $plugin;

		// Log the arguments and options
		$output->writeln( 'Plugin: ' . $plugin );
		$output->writeln( 'SVN URL: ' . $svn_url );
		$output->writeln( 'Source Tag: ' . $source_tag );
		$output->writeln( 'Destination Tag: ' . $destination_tag );
		$output->writeln( 'ZIP URL: ' . $zip_url );
		$output->writeln( 'Temporary Directory: ' . $temp_dir );

		$plugin_dir = $temp_dir . '/plugin/';

		if ( file_exists( $temp_dir ) ) {
			$output->writeln( 'Temporary directory already exists: ' . $temp_dir );
			shell_exec( "rm -rf {$temp_dir}" );

			if ( file_exists( $temp_dir ) ) {
				return $this->cleanup_to_error( "Couldn't cleanup the temporary directory, aborting." );
			}
		}

		$output->writeln( 'Creating temporary working directory: ' . $temp_dir );
		mkdir( $temp_dir, 0755, true );

		if ( ! file_exists( $temp_dir ) ) {
			return $this->cleanup_to_error( "Couldn't create the temporary directory, aborting." );
		}

		// From this moment forward we need to cleanup, so we catch an interrupt signal.
		$this->catch_cli_exit_signals();
		$this->include_cleanup_step( 'remove_temp_dir' );

		// If the plugin extraction dir exists, delete it and recreate it.
		if ( file_exists( $plugin_dir ) ) {
			$output->writeln( 'Plugin storage directory already exists: ' . $plugin_dir );
			shell_exec( "rm -rf {$plugin_dir}" );

			if ( file_exists( $plugin_dir ) ) {
				return $this->cleanup_to_error( "Couldn't cleanup the plugin storage directory, aborting." );
			}
		}

		// Download and extract ZIP file to temporary directory
		$zip_file = rtrim( $temp_dir, '/' ) . '/plugin.zip';

		// just to make sure we can download the new file.
		if ( file_exists( $zip_file ) && is_file( $zip_file ) ) {
			unlink( $zip_file );
			if ( file_exists( $zip_file ) ) {
				return $this->cleanup_to_error( "Couldn't delete the existing zip, aborting." );
			}
		}

		$output->writeln( 'Downloading ZIP file to: ' . $zip_file );

		file_put_contents( $zip_file, file_get_contents( $zip_url ) );
		$output->writeln( 'Extracting ZIP file to: ' . $temp_dir );
		$zip = new \ZipArchive();

		if ( $zip->open( $zip_file ) === true ) {
			$zip->extractTo( $temp_dir );
			$zip->close();

			shell_exec( "mv {$temp_dir}/{$plugin} $plugin_dir" );
			if ( ! file_exists( $plugin_dir ) ) {
				return $this->cleanup_to_error( "ZIP was not extracted correctly, expected destination: {$plugin_dir}." );
			}
		} else {
			return $this->cleanup_to_error( "Failed to open ZIP file." );
		}

		$cd_to_repo_cmd = "cd {$local_repo} && ";

		$clone_cmd = "svn co {$svn_url} {$local_repo} --depth=immediates";
		$output->writeln( "Executing SVN Command: \n" . $clone_cmd );
		shell_exec( $clone_cmd );

		$update_trunk_cmd = "svn update trunk/readme.txt";
		$output->writeln( "Executing SVN Command: \n" . $update_trunk_cmd );
		shell_exec( $cd_to_repo_cmd . $update_trunk_cmd );

		$update_tag_trunk_cmd = "svn update tags/{$source_tag}";
		$output->writeln( "Executing SVN Command: \n" . $update_tag_trunk_cmd );
		shell_exec( $cd_to_repo_cmd . $update_tag_trunk_cmd );

		// Copy SVN tag
		$copy_cmd = "svn copy {$svn_url}/tags/{$source_tag} {$svn_url}/tags/{$destination_tag} -m 'Copying {$source_tag} into {$destination_tag}'";
		$output->writeln( "Executing SVN Command:  \n" . $copy_cmd );
		shell_exec( $copy_cmd );

		$this->include_cleanup_step( 'remove_remote_destination_tag' );

		// Update local repository
		$update_cmd = "svn update tags/{$destination_tag}";
		$output->writeln( "Executing SVN Command: \n" . $update_cmd );
		shell_exec( $cd_to_repo_cmd . $update_cmd );

		// Compare tag folder and extracted folder
		$source_tag_folder      = "{$local_repo}/tags/{$source_tag}";
		$destination_tag_folder = "{$local_repo}/tags/{$destination_tag}";

		if ( ! file_exists( $source_tag_folder ) || ! is_dir( $source_tag_folder ) ) {
			return $this->cleanup_to_error( "The source tag was not downloaded properly, aborting." );
		}

		if ( ! file_exists( $destination_tag_folder ) || ! is_dir( $destination_tag_folder ) ) {
			return $this->cleanup_to_error( "The destination tag was not downloaded properly, aborting." );
		}

		// Delete tag folder
		$delete_cmd = "rm -rf {$destination_tag_folder}";
		$output->writeln( "Removing the local copy of the destination tag: \n" . $delete_cmd );
		shell_exec( $delete_cmd );

		// Move extracted files to tag folder
		$move_cmd = "mv {$plugin_dir} {$destination_tag_folder}";
		$output->writeln( "Move the ZIP version of the destination tag to the correct folder: \n" . $move_cmd );
		shell_exec( $move_cmd );

		$scan = $this->compare_directories( $source_tag_folder, $destination_tag_folder );

		$output->writeln( "Comparing Directories:" );
		$output->writeln( " - $source_tag_folder" );
		$output->writeln( " - $destination_tag_folder" );

		if ( null === $scan ) {
			return $this->cleanup_to_error( "Error while trying to compare folders." );
		}

		if ( ! empty( $scan['added'] ) ) {
			$output->writeln( '' );

			$output->writeln( "Added files: \n - " . implode( "\n - ", $scan['added'] ) );

			$output->writeln( '' );

			$output->writeln( "Including all added files from destination Tag folder: " );
			foreach ( $scan['added'] as $file ) {
				$add_cmd = "svn add --parents tags/{$destination_tag}/{$file}";
				$output->writeln( $add_cmd );
				shell_exec( $cd_to_repo_cmd . $add_cmd );
			}
		} else {
			$output->writeln( "No files added from '{$source_tag}' to '{$destination_tag}'." );
		}

		if ( ! empty( $scan['deleted'] ) ) {
			$output->writeln( '' );

			$output->writeln( "Deleted files: \n - " . implode( "\n - ", $scan['deleted'] ) );

			$output->writeln( '' );

			// Remove deleted files
			$output->writeln( "Deleting all removed files from destination Tag folder:" );
			foreach ( $scan['deleted'] as $file ) {
				$remove_cmd = "svn rm tags/{$destination_tag}/{$file}";
				$output->writeln( $remove_cmd );
				shell_exec( $cd_to_repo_cmd . $remove_cmd );
			}
		} else {
			$output->writeln( "No files removed from '{$source_tag}' to '{$destination_tag}'." );
		}

		$output->writeln( '' );

		// Commit changes to SVN.
		$commit_cmd = "svn commit tags/{$destination_tag} -m 'Apply modifications to {$destination_tag}'";
		$output->writeln( "Executing SVN Command:  \n" . $commit_cmd );
		shell_exec( $cd_to_repo_cmd . $commit_cmd );

		$this->remove_cleanup_step( 'remove_remote_destination_tag' );

		/**
		 * The check sum below doesnt work, need some more work before its ready.
		 *
		 * $svn_checksum = shell_exec( "svn info --show-item=checksum {$svn_url}/tags/{$destination_tag}" );
		 * $zip_checksum = sha1_file( $zip_file );
		 *
		 * if ($svn_checksum !== $zip_checksum) {
		 * $output->writeln('<error>Checksum mismatch.</error>');
		 * return self::CMD_FAILURE;
		 * }
		 */

		return $this->cleanup_to_success( "Operation completed successfully." );
	}

	/**
	 * Compare two directories and return an array of added and deleted files.
	 *
	 * @since 1.2.8
	 *
	 * @param string $source      Directory A.
	 * @param string $destination Directory B.
	 *
	 * @return array|null
	 */
	protected function compare_directories( $source, $destination ) {
		// Ensure directories end with '/'
		$source      = rtrim( $source, '/' ) . '/';
		$destination = rtrim( $destination, '/' ) . '/';

		// Check if directories exist
		if ( ! is_dir( $source ) || ! is_dir( $destination ) ) {
			return null;
		}

		// Execute rsync command to check for added files.
		$command = "rsync --dry-run --itemize-changes --recursive --ignore-existing {$destination}/ {$source}/ | grep '>f+' | awk '{print \$2}'";
		exec( $command, $added_in_source );

		// Execute rsync command to check for deleted files.
		$command = "rsync --dry-run --itemize-changes --recursive --ignore-existing {$source}/ {$destination}/ | grep '>f+' | awk '{print \$2}'";
		exec( $command, $removed_from_source );

		return [
			'deleted' => array_values( $removed_from_source ),  // Re-index the array
			'added' => array_values( $added_in_source ),    // Re-index the array
		];
	}

	/**
	 * Cleanup the workspace and return error.
	 *
	 * @since 1.2.8
	 *
	 * @param string|null $message
	 *
	 * @return int
	 */
	protected function cleanup_to_error( string $message = null ): int {

		$this->cleanup();

		$this->get_output()->writeln( $message );
		return self::CMD_FAILURE;
	}

	/**
	 * Cleanup the workspace and return success.
	 *
	 * @since 1.2.8
	 *
	 * @param string|null $message
	 *
	 * @return int
	 */
	protected function cleanup_to_success( string $message = null ): int {
		$this->cleanup();

		$this->get_output()->writeln( $message );
		return self::CMD_SUCCESS;
	}

	/**
	 * Cleanup the workspace.
	 *
	 * @since 1.2.8
	 */
	protected function cleanup() {
		if ( $this->has_cleanup_step( 'remove_temp_dir' ) ) {
			$temp_dir = $this->get_temp_dir();

			$this->get_output()->writeln( 'Deleting temporary files to cleanup workspace.' );
			shell_exec( "rm -rf {$temp_dir}" );
		}

		if ( $this->has_cleanup_step( 'remove_remote_destination_tag' ) ) {
			$svn_url         = $this->get_svn_url();
			$destination_tag = $this->get_destination_tag();

			$this->get_output()->writeln( "Deleting remote tag '{$destination_tag}'." );
			shell_exec( "svn rm {$svn_url}/tags/{$destination_tag} -m 'Delete remote tag {$destination_tag}.'" );
		}
	}

	/**
	 * Catch CLI exit signals.
	 *
	 * @since 1.2.8
	 */
	protected function catch_cli_exit_signals(): void {
		pcntl_signal( SIGTERM, [ $this, 'cli_signal_handler' ] );
		pcntl_signal( SIGINT, [ $this, 'cli_signal_handler' ] );
	}

	/**
	 * Handle CLI exit signals.
	 *
	 * @since 1.2.8
	 *
	 * @param mixed $signal
	 */
	public function cli_signal_handler( $signal ) {
		switch ( $signal ) {
			case SIGTERM:
			case SIGKILL:
			case SIGINT:
				$this->get_output()->writeln( "User interrupted the command, cleaning up required, please wait." );
				$this->cleanup();
				exit;
		}
	}
}
