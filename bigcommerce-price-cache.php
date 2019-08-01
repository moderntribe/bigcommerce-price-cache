<?php
/*
Plugin Name:  BigCommerce - Pricing API Cache
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

// Start the plugin
add_action( 'bigcommerce/init', 'bigcommerce_price_cache_init', 1, 2 );

/**
 * Initialize the plugin after BigCommerce for WordPress has initialized
 *
 * @param \BigCommerce\Plugin $bigcommerce The running instance of the BigCommerce plugin
 * @param \Pimple\Container   $container   The DI container for the BigCommerce plugin
 */
function bigcommerce_price_cache_init( \BigCommerce\Plugin $bigcommerce, \Pimple\Container $container ) {
	if ( version_compare( $bigcommerce::VERSION, '3.5', '<' ) ) {
		return;
	}
	$plugin = \BigCommerce_Price_Cache\Plugin::instance( $container );
	$plugin->init();
}
