<?php
/**
 * Loader class for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.0.0
 */

namespace ContentForge;

use ContentForge\Traits\ContainerTrait;

if ( !defined( 'ABSPATH' ) )
{
	exit;
}

class Loader
{

	use ContainerTrait;

	/**
	 * Registered generator instances.
	 *
	 * @var array
	 */
	protected $generators = [];

	/**
	 * Load all generator classes.
	 */
	protected function load_generators()
	{
		$generator_dir = CFORGE_INCLUDES_PATH . 'Generator/';
		if ( is_dir( $generator_dir ) )
		{
			foreach ( glob( $generator_dir . '*.php' ) as $file )
			{
				require_once $file;
			}
		}
	}

	/**
	 * Load components for Content Forge plugin.
	 *
	 * @return void
	 */
	public function load()
	{
		$this->load_generators();

		$this->container[ 'api' ] = new Api();

		// Load and initialize telemetry tracking.
		if ( file_exists( CFORGE_INCLUDES_PATH . 'Telemetry_Manager.php' ) )
		{
			require_once CFORGE_INCLUDES_PATH . 'Telemetry_Manager.php';
		}
		Telemetry_Manager::init();

		if ( is_admin() )
		{
			$this->container[ 'admin' ] = new Admin();
		}
	}
}
