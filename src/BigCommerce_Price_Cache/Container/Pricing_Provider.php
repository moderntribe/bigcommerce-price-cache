<?php


namespace BigCommerce_Price_Cache\Container;

use BigCommerce\Container\Provider;
use BigCommerce_Price_Cache\Pricing\Price_Cache;
use Pimple\Container;

class Pricing_Provider extends Provider {
	const CACHE = 'bigcommerce_price_cache.pricing.cache';

	public function register( Container $container ) {
		$container[ self::CACHE ] = function ( Container $container ) {
			return new Price_Cache();
		};

		add_filter( 'bigcommerce/product/price_range/data', $this->create_callback( 'price_range_filter', function ( $price, $product ) use ( $container ) {
			return $container[ self::CACHE ]->filter_price_range( $price, $product );
		} ), 10, 2 );

		add_filter( 'bigcommerce/template/wrapper/classes', $this->create_callback( 'price_wrapper_class_filter', function ( $classes, $template ) use ( $container ) {
			return $container[ self::CACHE ]->add_preinitialized_wrapper_class( $classes, $template );
		} ), 10, 2 );
	}
}
