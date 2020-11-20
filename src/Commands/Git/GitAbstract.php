<?php

namespace TUT\Commands\Git;

use TUT\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GitAbstract extends Command {
	/**
	 * @var InputInterface
	 */
	public $input;

	/**
	 * @var OutputInterface
	 */
	public $output;

	/**
	 * @var string Default organization string
	 */
	protected $default_org = 'moderntribe';

	/**
	 * @var GitHub
	 */
	protected $github;

	/**
	 * @var string
	 */
	protected $org;

	/**
	 * @var string
	 */
	protected $repo;

	/**
	 * Gets the base temp dir where clones will happen
	 *
	 * @return string
	 */
	protected function get_base_temp_dir() : string {
		return sys_get_temp_dir() . "/{$this->repo}";
	}

	/**
	 * Parses a repo string into an org/repo pair
	 *
	 * @return array
	 */
	protected function parse_repo_string() : array {
		if ( false !== strpos( $this->repo, '/' ) ) {
			return explode( '/', $this->repo );
		}

		return [ $this->default_org, $this->repo ];
	}
}
