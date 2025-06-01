<?php
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
	 * @var int
	 */
	protected $user_id;

	/**
	 * Constructor.
	 *
	 * @param int $user_id
	 */
	public function __construct( $user_id ) {
		$this->user_id = $user_id;
	}

	/**
	 * Generate content.
	 *
	 * @param int $count Number of items to generate.
	 * @param array $args Additional arguments for generation.
	 * @return array Array of generated object IDs.
	 */
	abstract public function generate( $count = 1, $args = [] );

	/**
	 * Delete generated content by IDs.
	 *
	 * @param array $object_ids
	 * @return int Number of items deleted.
	 */
	abstract public function delete( array $object_ids );
} 