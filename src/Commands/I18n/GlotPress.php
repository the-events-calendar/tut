<?php
namespace TUT\Commands\I18n;

use Graze\ParallelProcess\Display\Lines;
use Graze\ParallelProcess\Pool;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use TUT\Command as Command;
use GuzzleHttp\Client;

class GlotPress extends Command {
	/**
	 * @var string The branch in which the version is being prepared
	 */
	protected $branch;

	/**
	 * @var bool Has a common/ directory
	 */
	private $has_common = false;

	protected $retries = 5;

	/**
	 * @var bool Should the command prompt for plugin selection?
	 */
	public $do_plugin_selection = true;

	/**
	 * Returns the package.json data of the current plugin.
	 *
	 * @return null|mixed
	 */
	private function get_plugin_package_data() {
		if ( ! $this->plugin_package_exists() ) {
			return null;
		}

		$json_string = file_get_contents( 'package.json' );

		if ( ! $this->is_json( $json_string ) ) {
			return null;
		}

		return json_decode( $json_string );
	}

	/**
	 * Determines if a given string is JSON or not.
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	protected function is_json( string $value ): bool {
		json_decode( $value );
		return json_last_error() === JSON_ERROR_NONE;
	}

	/**
	 * Returns the source command based on OS.
	 *
	 * @return string The source command for the OS PHP is running on.
	 */
	private function plugin_package_exists() {
		return file_exists( 'package.json' );
	}

	protected function configure() {
		parent::configure();

		$this->setName( 'i18n:glotpress' )
		     ->setDescription( 'Controls GlotPress download of .mo and .po files' )
		     ->setHelp( 'This command controls glotpress.' )
			 ->addOption( 'retries', 't', InputOption::VALUE_OPTIONAL, 'How many retries we do for each file.' );

	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->retries = (int) $this->retries ?: $input->getOption( 'retries' );

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

			$this->download_language_files( $plugin );

			// go back up to the plugins directory
			chdir( '../' );

			$output->writeln( '<info>-------------------</info>' . "\n" );
		}

		$this->io->success( 'DONE' );
	}

	/**
	 * Runs build commands in parallel
	 */
	private function download_language_files( $plugin ) {
		$package = $this->get_plugin_package_data();
		if ( null === $package ) {
			$this->output->writeln( "<fg=red>Failed to read `package.json` on {$plugin->name}</>", OutputInterface::VERBOSITY_VERBOSE );
			return;
		}

		$options = (object) [
			'domain_path' => $package->_domainPath,
			'url' => $package->_glotPressUrl,
			'slug' => $package->_glotPressSlug,
			'text_domain' => $package->_textDomain,
			'file_format' => $package->_glotPressFileFormat,
			'formats' => $package->_glotPressFormats,
			'filter' => $package->_glotPressFilter,
		];

		$client = new Client();

		$project_url = $options->url . '/api/projects/' . $options->slug;
		$project_res = $client->request( 'GET', $project_url );

		if ( 200 !== $project_res->getStatusCode() ) {
			$this->output->writeln( "<fg=red>Failed to fetch project data from {$project_url}</>", OutputInterface::VERBOSITY_VERBOSE );
			return;
		}

		$project_data = json_decode( $project_res->getBody() );
		$promises = [];

		foreach ( $project_data->translation_sets as $translation ) {
			// skip when translations are zero.
			if ( 0 === $translation->current_count ) {
				continue;
			}

			// Skip any translation set that does't match our min translated.
			if ( $options->filter->minimum_percentage > $translation->percent_translated ) {
				continue;
			}

			foreach ( $options->formats as $format ) {
				$promise = $this->download_and_save_translation( $plugin, $options, $translation, $format, $project_url );

				if ( null !== $promise ) {
					$promises[] = $promise;
				}
			}
		}

		$connections = 0;

		array_map(
			static function( $promise ) use ( $connections ) {
				$connections++;
				$wait = ( 0 === $connections % 4 ) ? 250 : 50;
				// Add some delay to prevent sever lockout
				usleep( $wait );
				$promise->wait();
			},
			$promises
		);
	}

	protected function download_and_save_translation( $plugin, $options, $translation, $format, $project_url, $tried = 0 ) {
		$translation_url = "{$project_url}/{$translation->locale}/{$translation->slug}/export-translations?format={$format}";
		if ( $tried >= $this->retries ) {
			$this->output->writeln( "<fg=red>Failed to fetch translation from {$translation_url} too many times, bailing on {$translation->slug}</>", OutputInterface::VERBOSITY_VERBOSE );
			return null;
		}

		$tried++;

		$client = new Client();
		$request = new \GuzzleHttp\Psr7\Request( 'GET', $translation_url );

		$promise = $client->sendAsync( $request )->then( function ( $response ) use ( $translation_url, $plugin, $options, $translation, $format, $project_url, $tried  ) {
			$search = [
				'%domainPath%',
				'%textdomain%',
				'%locale%',
				'%wp_locale%',
				'%format%',
			];
			$replace = [
				$options->domain_path,
				$options->text_domain,
				$translation->locale ?? '',
				$translation->wp_locale ?? '',
				$format,
			];
			$filename = str_replace( $search, $replace, $options->file_format );

			$translation_body = $response->getBody();
			$translation_size = $translation_body->getSize();

			if ( 200 > $translation_size ) {
				$this->output->writeln( "<fg=red>Failed to fetch translation from {$translation_url}</>", OutputInterface::VERBOSITY_VERBOSE );

				// Not sure if 2seconds is needed, but it prevents they firewall from catching us.
				sleep( 2 );

				// Retries to download this file.
				return $this->download_and_save_translation( $plugin, $options, $translation, $format, $project_url, $tried );
			}

			$translation_content = $translation_body->getContents();
			$file_path = "lang/{$filename}";

			$put_contents = file_put_contents( $file_path, $translation_content );

			if ( $put_contents !== $translation_size ) {
				$this->output->writeln( "<fg=red>Failed to save the translation from {$translation_url} to {$file_path}</>", OutputInterface::VERBOSITY_VERBOSE );

				// Delete the file in that case.
				unlink( $file_path );

				// Not sure if 2 seconds is needed, but it prevents the firewall from catching us.
				sleep( 2 );

				// Retries to download this file.
				return $this->download_and_save_translation( $plugin, $options, $translation, $format, $project_url, $tried );
			}

			$this->output->writeln( "<fg=green>Translation created for {$file_path}</>", OutputInterface::VERBOSITY_VERBOSE );
		} );

		return $promise;
	}
}
