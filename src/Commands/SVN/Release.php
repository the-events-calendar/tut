<?php

namespace TUT\Commands\SVN;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Release extends Command {
	/**
	 * Stores the input interface.
	 *
	 * @since 1.2.10
	 *
	 * @var InputInterface|null
	 */
	protected $input;

	/**
	 * Stores the output interface.
	 *
	 * @since 1.2.10
	 *
	 * @var OutputInterface|null
	 */
	protected $output;

	/**
	 * Stores the command steps to cleanup.
	 *
	 * @since 1.2.10
	 *
	 * @var array
	 */
	protected $cleanup_steps = [];

	/**
	 * Stores a list of allowed steps.
	 *
	 * @since 1.2.10
	 *
	 * @var array<string>
	 */
	protected $allowed_steps = [
		'remove_temp_dir',
	];

	/**
	 * Stores the temporary directory to use.
	 *
	 * @since 1.2.10
	 *
	 * @var string|null
	 */
	protected $temp_dir;

	/**
	 * Stores the plugin slug.
	 *
	 * @since 1.2.10
	 *
	 * @var string|null
	 */
	protected $plugin_slug;

	/**
	 * Stores the destination tag.
	 *
	 * @since 1.2.10
	 *
	 * @var string|null
	 */
	protected $tag;

	/**
	 * @since 1.2.10
	 *
	 * @var int The exit code to use when the command succeeds.
	 */
	protected const CMD_SUCCESS = 0;

	/**
	 * @since 1.2.10
	 *
	 * @var int The exit code to use when the command fails.
	 */
	protected const CMD_FAILURE = 1;

	/**
	 * @since 1.2.10
	 *
	 * @var string Store the base URL for the WordPress.org SVN repository.
	 */
	protected const WP_ORG_URL = 'https://plugins.svn.wordpress.org/';

	/**
	 * Configures the command.
	 *
	 * @since 1.2.10
	 */
	protected function configure() {
		$temp_dir = '/tmp/svn-tag/';

		$this
			->setName( 'svn:release' )
			->setDescription( 'Changes the SVN trunk readme.txt "stable" tag of a plugin.' )
			->setHelp( 'This command allows you to update which version of a plugin the "stable" tag applies to WordPress.org.' )
			->addArgument( 'plugin', InputArgument::REQUIRED, 'The slug of the Plugin on WordPress.org.' )
			->addArgument( 'tag', InputArgument::REQUIRED, 'The SVN tag to be released.' )
			->addOption( 'checksum_zip', 'c', InputOption::VALUE_OPTIONAL, 'The URL of the ZIP file in case you want to do a Checksum.' )
			->addOption( 'temp_dir', 't', InputOption::VALUE_OPTIONAL, 'The temporary directory to use.', $temp_dir );
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
	 * @since 1.2.10
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
	 * @since 1.2.10
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
	 * @since 1.2.10
	 *
	 * @return InputInterface The input interface.
	 */
	protected function get_input(): InputInterface {
		return $this->input;
	}

	/**
	 * Sets the input interface.
	 *
	 * @since 1.2.10
	 */
	protected function set_input( InputInterface $input ): void {
		$this->input = $input;
	}

	/**
	 * Sets the output interface.
	 *
	 * @since 1.2.10
	 */
	protected function set_output( OutputInterface $output ): void {
		$this->output = $output;
	}

	/**
	 * Gets the output interface.
	 *
	 * @since 1.2.10
	 *
	 * @return OutputInterface The output interface.
	 */
	protected function get_output(): OutputInterface {
		return $this->output;
	}

	/**
	 * Sets the temporary directory.
	 *
	 * @since 1.2.10
	 */
	protected function set_temp_dir( string $dir ): void {
		$this->temp_dir = $dir;
	}

	/**
	 * Gets the temporary directory.
	 *
	 * @since 1.2.10
	 *
	 * @return string The temporary directory.
	 */
	protected function get_temp_dir(): string {
		return $this->temp_dir;
	}

	/**
	 * Sets the plugin slug.
	 *
	 * @since 1.2.10
	 */
	protected function set_plugin_slug( string $plugin_slug ): void {
		$this->plugin_slug = $plugin_slug;
	}

	/**
	 * Gets the plugin slug.
	 *
	 * @since 1.2.10
	 *
	 * @return string The plugin slug.
	 */
	protected function get_plugin_slug(): string {
		return $this->plugin_slug;
	}

	/**
	 *
	 *
	 * @since 1.2.10
	 */
	protected function set_tag( string $tag ): void {
		$this->tag = $tag;
	}

	/**
	 * Gets the destination tag.
	 *
	 * @since 1.2.10
	 *
	 * @return string The destination tag.
	 */
	protected function get_tag(): string {
		return $this->tag;
	}

	/**
	 * Gets the URL for the plugin on WordPress.org.
	 *
	 * @since 1.2.10
	 *
	 * @return string The URL for the plugin on WordPress.org.
	 */
	protected function get_svn_url(): string {
		return self::WP_ORG_URL . $this->get_plugin_slug();
	}

	/**
	 * Executes the command.
	 *
	 * @since 1.2.10
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->set_input( $input );
		$this->set_output( $output );

		$id               = date( 'Y-m-d-H-m-s' ) . '-' . substr( uniqid(), 0, 12 );
		$plugin           = $input->getArgument( 'plugin' );
		$tag              = $input->getArgument( 'tag' );
		$checksum_zip_url = $input->getOption( 'checksum_zip' );
		$temp_dir         = $input->getOption( 'temp_dir' );

		$temp_dir = rtrim( $temp_dir, '/' ) . "/release-{$id}";

		$this->set_temp_dir( $temp_dir );
		$this->set_tag( $tag );
		$this->set_plugin_slug( $plugin );

		$local_repo = "{$temp_dir}/repo";
		$svn_url    = $this->get_svn_url();

		// Log the arguments and options
		$output->writeln( 'Plugin: ' . $plugin );
		$output->writeln( 'SVN URL: ' . $svn_url );
		$output->writeln( 'Tag: ' . $tag );
		if ( empty( $checksum_zip_url ) ) {
			$output->writeln( 'Checksum ZIP URL: ' . $checksum_zip_url );
		}
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

		$cd_to_repo_cmd = "cd {$local_repo} && ";

		if ( ! empty( $checksum_zip_url ) ) {
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

			file_put_contents( $zip_file, file_get_contents( $checksum_zip_url ) );
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
		}

		$clone_cmd = "svn co {$svn_url} {$local_repo} --depth=immediates";
		$output->writeln( "Executing SVN Command: \n" . $clone_cmd );
		shell_exec( $clone_cmd );

		$update_tag_trunk_cmd = "svn update tags/{$tag} trunk/readme.txt";
		$output->writeln( "Executing SVN Command: \n" . $update_tag_trunk_cmd );
		shell_exec( $cd_to_repo_cmd . $update_tag_trunk_cmd );

		// Compare tag folder and extracted folder.
		$tag_folder = "{$local_repo}/tags/{$tag}";

		if ( ! file_exists( $tag_folder ) || ! is_dir( $tag_folder ) ) {
			return $this->cleanup_to_error( "The tag was not downloaded properly, aborting." );
		}

		if ( ! empty( $checksum_zip_url ) ) {
			$scan = $this->check_folder_checksum( $plugin_dir, $tag_folder );

			$output->writeln( "Comparing Directories:" );
			$output->writeln( " - $plugin_dir" );
			$output->writeln( " - $tag_folder" );

			if ( ! $scan ) {
				return $this->cleanup_to_error( "Failed the Checksum of the two folders." );
			}
		}

		$output->writeln( '' );

		// Copy SVN tag readme into trunk.
		$copy_cmd = "\cp -f tags/{$tag}/readme.txt trunk/readme.txt";
		$output->writeln( "Executing Local Command:  \n" . $copy_cmd );
		shell_exec( $cd_to_repo_cmd . $copy_cmd );

		$svn_commit_cp = "svn ci -m 'Copying {$tag} readme.txt into Trunk.'";
		$output->writeln( "Executing SVN Command:  \n" . $svn_commit_cp );
		shell_exec( $cd_to_repo_cmd . $svn_commit_cp );

		return $this->cleanup_to_success( "Operation completed successfully." );
	}

	/**
	 * Compare two directories and return boolean depending on if they are equal.
	 * It will check file paths and file sizes.
	 *
	 * @since 1.2.10
	 *
	 * @param string $plugin_folder Directory A.
	 * @param string $tag_folder    Directory B.
	 *
	 * @return bool
	 */
	protected function check_folder_checksum( $plugin_folder, $tag_folder ): bool {
		// Check if directories exist
		if ( ! is_dir( $plugin_folder ) || ! is_dir( $tag_folder ) ) {
			return false;
		}

		// Execute rsync command to check for added files.
		$command = "cd {$plugin_folder} && find . -type f -exec stat -f \"%N|%z\" {} \; | sort";
		exec( $command, $plugin_files );

		// Execute rsync command to check for added files.
		$command = "cd {$tag_folder} && find . -type f -exec stat -f \"%N|%z\" {} \; | sort";
		exec( $command, $tag_files );

		return md5( serialize( $tag_files ) ) === md5( serialize( $plugin_files ) );
	}

	/**
	 * Cleanup the workspace and return error.
	 *
	 * @since 1.2.10
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
	 * @since 1.2.10
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
	 * @since 1.2.10
	 */
	protected function cleanup() {
		if ( $this->has_cleanup_step( 'remove_temp_dir' ) ) {
			$temp_dir = $this->get_temp_dir();

			$this->get_output()->writeln( 'Deleting temporary files to cleanup workspace.' );
			shell_exec( "rm -rf {$temp_dir}" );
		}
	}

	/**
	 * Catch CLI exit signals.
	 *
	 * @since 1.2.10
	 */
	protected function catch_cli_exit_signals(): void {
		pcntl_signal( SIGTERM, [ $this, 'cli_signal_handler' ] );
		pcntl_signal( SIGINT, [ $this, 'cli_signal_handler' ] );
	}

	/**
	 * Handle CLI exit signals.
	 *
	 * @since 1.2.10
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
