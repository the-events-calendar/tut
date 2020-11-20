<?php

namespace TUT\Commands\Git;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output;
use Symfony\Component\Console\Output\OutputInterface;

class CodeReview extends GitAbstract {
	/**
	 * @var GitHub\PullRequest
	 */
	private $pr;

	/**
	 * @var array
	 */
	private $icons = [
		'error'   => ':no_entry_sign:',
		'warning' => ':warning:',
	];

	/**
	 * @var array
	 */
	private $phpcs_ignore = [
		'*/tests/*',
		'*/vendor/*',
	];

	/**
	 * Configure the command
	 */
	public function configure() {
		parent::configure();

		$this->setName( 'code-review' )
			->setDescription( 'Runs a code review on a specific pull request and posts as tr1b0t' )
			->setHelp( 'Runs a code review on a specific pull request and posts as tr1b0t' )
			->addOption( 'repo', null, InputOption::VALUE_REQUIRED, 'Repository to run code review against' )
			->addOption( 'pr', null, InputOption::VALUE_REQUIRED, 'PR number' )
			->addOption( 'path', null, InputOption::VALUE_REQUIRED, 'Path to clone. Providing this will prevent a git clone from occurring.' )
			->addOption( 'rules', null, InputOption::VALUE_REQUIRED, 'Sniffer rules to use' );
	}

	/**
	 * Execute the command
	 *
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->repo = empty( $input->getOption( 'repo' ) ) ? null : urldecode( $input->getOption( 'repo' ) );
		$pr_number  = empty( $input->getOption( 'pr' ) ) ? null : $input->getOption( 'pr' );
		$path       = empty( $input->getOption( 'path' ) ) ? null : $input->getOption( 'path' );
		$rules      = empty( $input->getOption( 'rules' ) ) ? 'TribalScents' : $input->getOption( 'rules' );

		list( $this->org, $this->repo ) = $this->parse_repo_string();

		$this->github = GitHub::factory( $this->org );
		$pr_numbers   = [];

		if ( empty( $pr_number ) ) {
			$pull_requests = $this->github->pull_requests( $this->repo );
			foreach ( $pull_requests as $pull_request ) {
				$pr_numbers[] = $pull_request->number;
			}
		} else {
			$pr_numbers[] = $pr_number;
		}

		foreach ( $pr_numbers as $pr_number ) {
			$this->code_review_pr( $pr_number, $rules, $path );
		}
	}

	/**
	 * Executes a review for a single PR
	 *
	 * @param int $pr_number
	 * @param string|null $rules Sniffer ruleset
	 * @param string|null $path Path to clone
	 */
	private function code_review_pr( $pr_number, $rules, $path = null ) {
		$this->output->writeln( "<comment>Executing code sniff against PR {$pr_number} of {$this->org}/{$this->repo} using the {$rules} sniffer rules.</comment>" );

		$do_cleanup  = false;
		$current_dir = getcwd();

		// Instantiate our PullRequest object
		$this->pr = new GitHub\PullRequest( $this->org, $this->repo, $pr_number );

		// Shallow clone the repo branch if path wasn't provided
		if ( empty( $path ) ) {
			$path       = $this->clone_repo();
			$do_cleanup = true;
		}

		$this->output->writeln( "* cd {$path}" );

		// Change to the cloned directory
		chdir( $path );

		if ( $this->phpcs_file_exists() ) {
			$this->run_process( 'composer dump-autoload', true );
			$this->run_process( 'composer install', true );
		}

		$this->output->writeln( "* Running PHPCS" );

		// Run the CodeSniff rules against the changed lines
		$phpcs_results = $this->phpcs( $rules );

		$this->output->writeln( $phpcs_results );

		// Build array of CodeSniff comments to be sent to GitHub along with summary data of the comments
		$comment_data = $this->build_comments_for_review( $phpcs_results );

		// Remove comments that should not longer appear in the PR based on the sniffer results
		// Gets back an array of hashes for comments that already exist in the PR
		$existing_comment_hashes = $this->cleanup_outdated_comments( $comment_data );

		// Filter out the comments that should be delivered to GitHub so we don't get duplicates
		$comment_data = $this->filter_out_existing_comments( $comment_data, $existing_comment_hashes );

		// Create a review if necessary
		$result = $this->maybe_create_review( $comment_data );

		if ( $do_cleanup ) {
			// Get rid of the just-cloned repo
			$this->cleanup_clone( $path );

			chdir( $current_dir );

			// Let's make sure old clones aren't hanging around
			$this->cleanup_clone_base_dir();
		}

		$this->output->writeln( '<comment>Code sniffing is complete</comment>' );
	}

	/**
	 * Shallow clones a repo with a specific branch
	 *
	 * @return string
	 */
	private function clone_repo() : string {
		$command = $this->getApplication()->find( 'git:clone' );

		$args = [
			'--repo'          => "{$this->org}/{$this->repo}",
			'--ref'           => $this->pr->get_branch(),
			'--single-branch' => true,
		];

		$output = new Output\BufferedOutput();

		$command->run( new ArrayInput( $args ), $output );

		$results = explode( "\n", $output->fetch() );

		// toss out the final line break
		array_pop( $results );

		$this->output->writeln( $results );

		return array_pop( $results );
	}

	/**
	 * Removes cloned repo
	 *
	 * @param string $path
	 */
	private function cleanup_clone( string $path ) {
		shell_exec( 'rm -rf ' . escapeshellarg( $path ) );
	}

	/**
	 * Handles the deletion of old clones that might need removal
	 */
	private function cleanup_clone_base_dir() {
		$base_dir = $this->get_base_temp_dir();

		// if the base directory is /, bail
		if ( '/' === trim( $base_dir ) ) {
			return;
		}

		// if the base directory contains .., let's bail because it is unsafe
		if ( false !== strpos( $base_dir, '..' ) ) {
			return;
		}

		// if the base directory doesn't contain the repo, then bail
		if ( false === strpos( $base_dir, $this->repo ) ) {
			return;
		}

		// if the directory doesn't exist, bail
		if ( ! file_exists( $base_dir ) ) {
			return;
		}

		// if the directory is empty, bail
		if ( 2 === count( scandir( $base_dir ) ) ) {
			return;
		}

		chdir( $base_dir );

		// delete all directories older than 1 day
		shell_exec( "find {$base_dir} -maxdepth 1 -type d -mtime +1 | xargs rm -rf" );
	}

	/**
	 * Runs PHP Codesniffer
	 *
	 * @param $rules
	 *
	 * @return array|bool
	 */
	private function phpcs( $rules ) {
		$files = $this->pr->get_changed_files();
		$lines = $this->pr->get_lines_changed();

		$pwd         = trim( `pwd` );

		$files       = array_map( 'escapeshellarg', $files );
		$file_string = trim( implode( ' ', $files ) );

		if ( ! $file_string ) {
			return false;
		}

		$ignore      = escapeshellarg( implode( ',', $this->phpcs_ignore ) );
		$rules       = escapeshellarg( $rules );

		$command        = './vendor/bin/phpcs';
		$command_args   = [];
		$command_args[] = "--ignore={$ignore}";
		$command_args[] = "--extensions=php";
		$command_args[] = "--report=csv";

		if ( ! $this->phpcs_file_exists() ) {
			$command        = 'phpcs';
			$command_args[] = "--standard={$rules}";
		}

		$command = "{$command} " . implode( ' ', $command_args ) . " {$file_string}";

		$result = $this->run_process( $command, true );
		$result = $result->getOutput();

		// who knows why, but there are 3 hidden characters prepended to the header
		$results_tmp      = preg_replace( '/^[^F]+/', '', $result );
		$results_data_tmp = $this->csv_string_to_array( $results_tmp );

		$results_data = [];
		$results      = '';
		foreach ( $results_data_tmp as $data ) {
			$data['File'] = str_replace( $pwd . '/', '', $data['File'] );
			$data['base_file'] = basename( $data['File'] );

			if ( $lines ) {
				// filter out files
				if ( ! in_array( $data['File'], array_keys( $lines ) ) ) {
					continue;
				}

				// filter out lines
				if ( ! in_array( $data['Line'], $lines[ $data['File'] ] ) ) {
					continue;
				}
			}

			$results .= implode( ',', $data ) . "\n";
			$results_data[] = $data;
		}

		if ( ! $results_data ) {
			$this->output->writeln( 'FAILED TO PARSE DATA' );
			return false;
		}

		// add the headings on top
		$results = implode( ',', array_keys( $results_data[0] ) ) . "\n" . $results;

		return [
			'data'   => $results_data,
			'report' => $results,
		];
	}

	/**
	 * Determines if a phpcs.xml file exists
	 */
	private function phpcs_file_exists() {
		return file_exists( 'phpcs.xml' )
			|| file_exists( '.phpcs.xml' )
			|| file_exists( 'phpcs.xml.dist' )
			|| file_exists( '.phpcs.xml.dist' );
	}

	/**
	 * converts a CSV string with headers to an associative array
	 */
	private function csv_string_to_array( string $tmp_data ) {
		$tmp_data    = explode( "\n", $tmp_data );
		$tmp_data    = array_map( 'str_getcsv', $tmp_data );
		$header      = $tmp_data[0];
		$num_headers = count( $header );
		$data        = [];

		// get rid of the header row
		unset( $tmp_data[0] );

		foreach ( $tmp_data as $row ) {
			if ( count( $row ) !== $num_headers ) {
				continue;
			}

			$row    = array_map( 'stripslashes', $row );
			$data[] = array_combine( $header, $row );
		}

		return $data;
	}

	/**
	 * Builds comments for review
	 *
	 * @param $phpcs_results
	 *
	 * @return array
	 */
	private function build_comments_for_review( $phpcs_results ) {
		$diff_map = $this->pr->diff()->get_line_map();
		$comments = [];
		$num      = [
			'error'   => 0,
			'warning' => 0,
		];

		foreach ( (array) $phpcs_results['data'] as $result_item ) {
			$key = "{$result_item['File']}:{$result_item['Line']}";

			$comment = [
				'path' => $result_item['File'],
				'body' => "{$this->icons[ $result_item['Type'] ]} {$result_item['Message']}",
				'position' => $diff_map[ $result_item['File'] ]['file-line-to-diff-map'][ $result_item['Line'] ],
			];

			$num[ $result_item['Type'] ]++;

			if ( empty( $comments[ $key ] ) ) {
				$comments[ $key ] = $comment;
			} else {
				if ( 0 !== strpos( $comments[ $key ]['body'], '* ' ) ) {
					$comments[ $key ]['body'] = "* " . $comments[ $key ]['body'];
				}
				$comments[ $key ]['body'] .= "\n* " . $comment['body'];
			}
		}

		$hashes = [];

		foreach ( $comments as &$comment ) {
			$comment['hash']  = md5( "{$comment['path']}:{$comment['position']}:{$comment['body']}" );
			$comment['body'] .= "\n\n<sub>hash: {$comment['hash']}</sub>";
			$hashes[]         = $comment['hash'];
		}

		return [
			'counts'   => $num,
			'comments' => array_values( $comments ),
			'hashes'   => $hashes,
		];
	}

	/**
	 * Removes old and outdated comments, returning hashes of comments that remain
	 *
	 * @param $comment_data
	 *
	 * @return array
	 */
	private function cleanup_outdated_comments( $comment_data ) : array {
		$comments               = $this->pr->get_all_review_comments( 'tr1b0t' );
		$exiting_comment_hashes = [];
		$hashes                 = implode( '|', $comment_data['hashes'] );

		foreach ( $comments as $comment ) {
			if ( ! preg_match( '/hash: (' . $hashes . ')/', $comment->body, $matches ) ) {
				$this->github->delete_comment( $this->repo, $comment );
				continue;
			}

			$exiting_comment_hashes[] = $matches[1];
		}

		return $exiting_comment_hashes;
	}

	/**
	 * Excludes comments that already exist in the PR
	 *
	 * @param array $comment_data
	 * @param array $existing_comment_hashes
	 * @return array
	 */
	private function filter_out_existing_comments( array $comment_data, array $existing_comment_hashes ) : array {
		$existing_comment_hashes = implode( '|', $existing_comment_hashes );

		if ( ! empty( $existing_comment_hashes ) ) {
			// make sure we don't re-insert existing comments
			$comment_data['comments'] = array_filter(
				$comment_data['comments'], function( $value ) use ( $existing_comment_hashes ) {
				return ! preg_match( '/hash: (' . $existing_comment_hashes . ')/', $value['body'] );
			}
			);
		}

		// Get rid of the hash parameter. We don't need it anymore and GitHub freaks out with extra params
		foreach ( $comment_data['comments'] as &$comment ) {
			unset( $comment['hash'] );
		}

		return $comment_data;
	}

	/**
	 * Creates a review on the PR if there are comments to be inserted
	 *
	 * @param $comment_data
	 *
	 * @return array|bool
	 */
	private function maybe_create_review( $comment_data ) {
		if ( empty( $comment_data['comments'] ) ) {
			return false;
		}

		$review = [
			'body'     => [],
			'event'    => 'COMMENT',
			'comments' => $comment_data['comments'],
		];

		if ( $comment_data['counts']['error'] ) {
			$review['body'][] = "{$this->icons['error']} {$comment_data['counts']['error']} error(s)";
		}

		if ( $comment_data['counts']['warning'] ) {
			$review['body'][] = "{$this->icons['warning']} {$comment_data['counts']['warning']} warning(s)";
		}

		$review['body']   = implode( "\n", $review['body'] );

		return $this->github->create_review( $this->repo, $this->pr->data()->number, $review );
	}
}
