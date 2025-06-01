<?php

namespace ContentForge;

use ContentForge\Api\Post;
use ContentForge\Api\Comment;
use ContentForge\Traits\ContainerTrait;

class Api {
	use ContainerTrait;

	/**
	 * Api constructor.
	 */
	public function __construct() {
        $this->container['post']    = new Post();
        $this->container['comment'] = new Comment();

		add_action( 'rest_api_init', [ $this, 'init_api' ] );
	}

	/**
	 * Initialize API
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function init_api() {
		foreach ( $this->container as $class ) {
			$object = new $class();
			$object->register_routes();
		}
	}
}
