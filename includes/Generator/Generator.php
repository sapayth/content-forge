<?php
/**
 * Abstract base generator class for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.0.0
 */

namespace ContentForge\Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for all Content Forge content generators.
 */
abstract class Generator {
	/**
	 * The user ID who triggers the generation.
     *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Constructor.
	 *
	 * @param int $user_id The user ID who triggers the generation.
	 */
	public function __construct( $user_id ) {
		$this->user_id = $user_id;
	}

	/**
	 * Generate content.
	 *
	 * @param int   $count Number of items to generate.
	 * @param array $args  Additional arguments for generation.
	 * @return array Array of generated object IDs.
	 */
	abstract public function generate( $count = 1, $args = [] );

	/**
	 * Delete generated content by IDs.
	 *
	 * @param array $object_ids Array of object IDs to delete.
	 * @return int Number of items deleted.
	 */
	abstract public function delete( array $object_ids );
}
