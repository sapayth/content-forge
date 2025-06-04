<?php
namespace ContentForge;

use ContentForge\Traits\ContainerTrait;

if (!defined('ABSPATH')) {
	exit;
}

class Loader
{
	use ContainerTrait;

	/**
	 * Registered generator instances.
	 * @var array
	 */
	protected $generators = [];

	/**
	 * Load plugin textdomain for translations.
	 */
	protected function load_textdomain()
	{
		load_plugin_textdomain('content-forge', false, dirname(CFORGE_BASENAME) . '/languages');
	}

	/**
	 * Load all generator classes.
	 */
	protected function load_generators()
	{
		$generator_dir = CFORGE_INCLUDES_PATH . 'Generator/';
		if (is_dir($generator_dir)) {
			foreach (glob($generator_dir . '*.php') as $file) {
				require_once $file;
			}
		}
	}

	/**
	 * @return void
	 */
	public function load()
	{
		$this->load_textdomain();
		$this->load_generators();

		$this->container['api'] = new Api();

		if (is_admin()) {
			$this->container['admin'] = new Admin();
		}
	}
}