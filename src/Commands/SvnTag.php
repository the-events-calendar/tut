<?php

namespace TUT\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SvnTag extends Command {
	protected ?InputInterface $input;
	protected ?OutputInterface $output;

	/**
	 * @since 1.2.8
	 *
	 * @var int The exit code to use when the command succeeds.
	 */
	protected const CMD_SUCCESS = 0;

	/**
	 * @since 1.2.8
	 *
	 * @var int The exit code to use when the command fails.
	 */
	protected const CMD_FAILURE = 1;

	/**
	 * @since 1.2.8
	 *
	 * @var string Store the base URL for the WordPress.org SVN repository.
	 */
	protected const WP_ORG_URL = 'https://plugins.svn.wordpress.org/';

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

	protected function get_input(): InputInterface {
		return $this->input;
	}

	protected function set_input( InputInterface $input ): void {
		$this->input = $input;
	}

	protected function set_output( OutputInterface $output ): void {
		$this->output = $output;
	}

	protected function get_output(): OutputInterface {
		return $this->output;
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

		if ( empty( $local_repo ) ) {
			$local_repo = "{$temp_dir}/repo";
		}
		$svn_url = self::WP_ORG_URL . $plugin;

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

		// If the plugin extraction dir exists, delete it and recreate it.
		if ( file_exists( $plugin_dir ) ) {
			$output->writeln( 'Plugin storage directory already exists: ' . $plugin_dir );
			shell_exec( "rm -rf {$plugin_dir}" );

			if ( file_exists( $plugin_dir ) ) {
				return $this->cleanup_to_error( "Couldn't cleanup the plugin storage directory, aborting." );
			}
		}

		$output->writeln( 'Creating plugin storage directory: ' . $plugin_dir );
		mkdir( $plugin_dir, 0755, true );

		if ( ! file_exists( $plugin_dir ) ) {
			return $this->cleanup_to_error( "Couldn't create the plugin storage directory, aborting." );
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
				$output->writeln( "<error>ZIP was not extracted correctly, expected destination: {$plugin_dir}.</error>" );
			}
		} else {
			$output->writeln( '<error>Failed to open ZIP file.</error>' );
			return self::CMD_FAILURE;
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

		// Update local repository
		$update_cmd = "svn update tags/{$destination_tag}";
		$output->writeln( "Executing SVN Command: \n" . $update_cmd );
		shell_exec( $cd_to_repo_cmd . $update_cmd );

		// Compare tag folder and extracted folder
		$source_tag_folder      = "{$local_repo}/tags/{$source_tag}";
		$destination_tag_folder = "{$local_repo}/tags/{$destination_tag}";

		// Delete tag folder
		$delete_cmd = "rm -rf {$destination_tag_folder}";
		$output->writeln( "Removing locally the destination tag: \n" . $delete_cmd );
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
			$output->writeln( '<error>Error while trying to compare folders.</error>' );
			return self::CMD_FAILURE;
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
		}

		$output->writeln( '' );

		// Commit changes
		$commit_cmd = "svn commit tags/{$destination_tag} -m 'Apply modifications to {$destination_tag}'";
		$output->writeln( "Executing SVN Command:  \n" . $commit_cmd );
		shell_exec( $cd_to_repo_cmd . $commit_cmd );

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

		$output->writeln( '<success>Operation completed successfully.</success>' );

		$output->writeln( 'Deleting temporary files to cleanup workspace.' );
		shell_exec( "rm -rf {$temp_dir}" );

		return self::CMD_SUCCESS;
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
	public function compare_directories( $source, $destination ) {
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
			'added'   => array_values( $added_in_source ),    // Re-index the array
		];
	}

	protected function cleanup_to_error( string $message = null ): int {

		$this->cleanup();

		$this->get_output()->writeln( $message );
		return self::CMD_FAILURE;
	}

	protected function cleanup_to_success( string $message = null ): int {
		$this->cleanup();

		$this->get_output()->writeln( $message );
		return self::CMD_SUCCESS;
	}

	protected function cleanup() {

	}
}