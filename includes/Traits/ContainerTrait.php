<?php
/**
 * Container trait for Content Forge plugin.
 *
 * @package ContentForge
 * @since   1.0.0
 */

namespace ContentForge\Traits;

trait ContainerTrait {

    /**
     * Container for dynamic properties.
     *
     * @var array
     */
    protected $container = [];

    /**
     * Get dynamic property from container.
     *
     * @param string $name The property name to retrieve.
     *
     * @return mixed
     */
    public function __get( $name ) {
        if ( isset( $this->container[ $name ] ) ) {
            return $this->container[ $name ];
        }

        return null;
    }

    /**
     * Set dynamic property to container.
     *
     * @param string $name  The property name to set.
     * @param mixed  $value The property value to set.
     *
     * @return void
     */
    public function __set( $name, $value ) {
        $this->container[ $name ] = $value;
    }
}
