<?php
namespace TUT\Commands\Extension;

use Dotenv;
use Github;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output;
use TUT\Command as Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TUT\Github\Api\Repository\Template;

class Create extends Command {
	/**
	 * @var array Supported plugin bases.
	 */
	protected $supported_bases = [
		'et'  => 'Event Tickets',
		'tec' => 'The Events Calendar',
	];

	/**
	 * @var bool Should the command prompt for plugin selection?
	 */
	public $do_plugin_selection = false;

	/**
	 * @var string The org in which to create the repo.
	 */
	protected $org = 'mt-support';

	/**
	 * @var string The repo to create.
	 */
	protected $repo;

	protected function configure() {
		parent::configure();

		$this
			->setName( 'extension:create' )
			->setDescription( 'Creates an extension repo on GitHub.' )
			->setHelp( 'Creates an extension repo on GitHub.' )
			->addOption( 'name', '', InputOption::VALUE_REQUIRED, 'The name of the extension.', null )
			->addOption( 'slug', '', InputOption::VALUE_OPTIONAL, 'The slug of the extension.', null )
			->addOption( 'base', '', InputOption::VALUE_REQUIRED, 'The plugin on which to base this extension on.', null )
			->addOption( 'namespace', '', InputOption::VALUE_OPTIONAL, 'The namespace for the extension.', null )
			->addOption( 'org', '', InputOption::VALUE_REQUIRED, 'Org in which to create the extension repo.', null )
			->addOption( 'no-create', '', InputOption::VALUE_NONE, 'If provided, prevents running the create action.', null )
		;
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$base      = $input->getOption( 'base' );
		$name      = $input->getOption( 'name' );
		$namespace = $input->getOption( 'namespace' );
		$org       = $input->getOption( 'org' );
		$slug      = $input->getOption( 'slug' );
		$do_create = $input->getOption( 'no-create' ) ? false : true;

		if ( ! isset( $this->supported_bases[ $base ] ) ) {
			$output->writeln( '<fg=red;options=bold>The extension MUST have a valid plugin base: et or tec</>' );
			return 1;
		}

		list( $slug, $slug_clean, $slug_clean_uppercase ) = $this->get_slug_mutations( $name, $slug );

		$namespace = $this->get_namespace( $name, $namespace );
		$base      = $this->supported_bases[ $base ];

		$vars = [
			'base'                 => $base,
			'description'          => null,
			'name'                 => $name,
			'namespace'            => $namespace,
			'slug'                 => $slug,
			'slug_clean'           => $slug_clean,
			'slug_clean_uppercase' => $slug_clean_uppercase,
			'url'                  => null,
			'version'              => '1.0.0',
		];

		if ( $org ) {
			$this->org = $org;
		}

		// Make sure the repo is prefixed.
		$this->repo = "tribe-ext-{$slug}";

		if ( $do_create && ! $this->create_repo( $output ) ) {
			return 1;
		}

		$output->write( '<fg=cyan>Checking out the extension to tmp...</>' );
		$path = $this->clone_repo();
		$output->write( '<fg=green>Done!</>' . "\n" );

		$output->write( '<fg=cyan>Injecting template variables...</>' );
		$this->inject_vars( $vars, $path );
		$output->write( '<fg=green>Done!</>' . "\n" );

		chdir( $path );

		$output->write( '<fg=cyan>Committing and pushing changes...</>' );
		$this->run_process( 'git commit -a -m ":memo: Updating extension template vars."' );
		$this->run_process( 'git push' );
		$output->write( '<fg=green>Done!</>' . "\n" );

		$output->write( '<fg=cyan>Purging the temporary clone...</>' );
		$this->run_process( 'rm -rf ' . escapeshellarg( $path ) );
		$output->write( '<fg=green>Done!</>' . "\n" );

		$output->writeln( "<fg=green>Extension created:</> https://github.com/{$this->org}/{$this->repo}" );
	}

	/**
	 * Creates the repo from the mt-support extension template.
	 *
	 * @return bool Whether the repo was created successfully or not.
	 */
	protected function create_repo( $output ) {
		$dotenv = Dotenv\Dotenv::createImmutable( __TUT_DIR__ );
		$dotenv->load();

		$client       = new Github\Client();
		$client->authenticate( getenv( 'GITHUB_OAUTH_TOKEN' ), null, Github\Client::AUTH_HTTP_TOKEN );

		$repositories = $client->api( 'repo' )->org( $this->org );
		foreach ( $repositories as $repository ) {
			if ( $repository['name'] !== $this->repo ) {
				continue;
			}

			$output->writeln( '<fg=red;options=bold>That repo already exists!</>' );
			return false;
		}

		$output->write( "<fg=cyan>Creating</> <fg=yellow>{$this->org}/{$this->repo}</><fg=cyan>...</>" );

		try {
			$template_api = new Template( $client );
			@$template_api->createRepo(
				'mt-support',
				'tribe-ext-extension-template',
				[
					'owner' => $this->org,
					'name'  => $this->repo,
				]
			);
		} catch ( \Exception $e ) {
			$output->write( "<fg=red>Error!</>\n" );
			$output->writeln( '<fg=red;options=bold>' . $e->getMessage() . '</>' );
			return false;
		}

		$output->write( "<fg=green>Done!</>\n" );

		return true;
	}

	/**
	 * Maybe generate and definitely sanitize the slug.
	 *
	 * @param string $name The provided name.
	 * @param string $slug The provided slug.
	 *
	 * @return string
	 */
	protected function get_slug_mutations( $name, $slug ) {
		if ( ! $slug ) {
			$slug = $name;

			// Replace all spaces with a single hyphen.
			$slug = preg_replace( '/\s+/', '-', $slug );
			$slug = strtolower( $slug );

			// Sanitize the slug.
			$slug = preg_replace( '/[^a-z0-9\-]/', '', $slug );
		}

		// A variable friendly version of the slug.
		$slug_clean           = str_replace( '-', '_', $slug );
		$slug_clean_uppercase = strtoupper( $slug_clean );

		return [
			$slug,
			$slug_clean,
			$slug_clean_uppercase,
		];
	}

	/**
	 * Maybe generate and definitely sanitize the namespace.
	 *
	 * @param string $name The provided name.
	 * @param string $namespace The provided namespace.
	 *
	 * @return string
	 */
	protected function get_namespace( $name, $namespace ) {
		if ( ! $namespace ) {
			$namespace = $name;
			$namespace = str_replace( ' ', '_', ucwords( $namespace ) );
			$namespace = preg_replace( '/_{2,}/', '_', $namespace );
			$namespace = preg_replace( '/[^a-zA-Z0-9_]/', '', $namespace );
		}

		if ( false !== strpos( $namespace, '\\' ) ) {
			$namespace = explode( '\\', $namespace );
			$namespace = end( $namespace );
		}

		return $namespace;
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
			'--ref'           => 'main',
			'--path'          => sys_get_temp_dir() . '/' . uniqid( 'tribe-ext-', true ),
			'--single-branch' => true,
		];

		$output = new Output\BufferedOutput();

		$command->run( new ArrayInput( $args ), $output );

		$results = explode( "\n", $output->fetch() );

		// toss out the final line break
		array_pop( $results );

		return array_pop( $results );
	}

	protected function inject_vars( $vars, $path ) {
		chdir( $path );

		$template_vars = [];

		foreach ( $vars as $key => $value ) {
			$template_vars[ strtoupper( "__TRIBE_{$key}__" ) ] = $value;
		}

		$directory = new \RecursiveDirectoryIterator( $path );
		$iterator  = new \RecursiveIteratorIterator( $directory );
		$files     = [];
		$file_types = [
			'css',
			'json',
			'js',
			'md',
			'pcss',
			'php',
			'txt',
		];
		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				continue;
			}

			if ( ! in_array( $file->getExtension(), $file_types ) ) {
				continue;
			}

			$file_path = $file->getPathname();
			$contents = file_get_contents( $file_path );
			$contents = str_replace( array_keys( $template_vars ), array_values( $template_vars ), $contents );

			file_put_contents( $file_path, $contents );
		}
	}
}
