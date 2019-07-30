<?php
/*
Plugin Name:  Pricing API Cache for BigCommerce for WordPress
Description:  Aggressively caches prices for BigCommerce products, making requests to the Pricing API during import.
Author:       BigCommerce
Version:      1.0.0-dev
Author URI:   https://www.bigcommerce.com/wordpress
Requires PHP: 5.6.24
Text Domain:  bigcommerce
License:      GPLv2 or later
*/

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

register_activation_hook( __FILE__, [ \BigCommerce\Plugin::class, 'activate' ] );

// Start the plugin
add_action( 'bigcommerce/init', 'bigcommerce_price_cache_init', 1, 2 );

/**
 * Initialize the plugin after BigCommerce for WordPress has initialized
 *
 * @param \BigCommerce\Plugin $bigcommerce The running instance of the BigCommerce plugin
 * @param \Pimple\Container   $container   The DI container for the BigCommerce plugin
 */
function bigcommerce_price_cache_init( \BigCommerce\Plugin $bigcommerce, \Pimple\Container $container ) {
	$plugin = \BigCommerce_Price_Cache\Plugin::instance( $container );
	$plugin->init();
}
