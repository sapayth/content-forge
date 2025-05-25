<?php
/**
 * Loads the whole plugin.
 *
 * Please keep in mind that this is the only function that should be called from the main plugin file.
 * This function will load the plugin, all its dependencies, and it will boot the plugin.
 * Only function or class that is not namespaced.
 *
 * @since 1.0.0
 */
function fakegen_load_plugin() {
    $main = new FakeGen\Loader();
    $main->init();
}
