<?php


namespace BigCommerce_Price_Cache\Container;

use BigCommerce\Container\Api;
use BigCommerce\Container\Provider;
use BigCommerce\Container\Rest;
use BigCommerce\Rest\Pricing_Controller;
use BigCommerce_Price_Cache\Api\Cached_Pricing_Api;
use Pimple\Container;

class Rest_Provider extends Provider {
	const CACHED_PRICING_API = 'bigcommerce_price_cache.rest.cached_pricing_api';

	public function register( Container $container ) {
		$container[ self::CACHED_PRICING_API ] = function( Container $container ) {
			return new Cached_Pricing_Api( $container[ Api::CLIENT ] );
		};

		// Override the pricing controller to use our caching API
		$container[ Rest::PRICING ] = function( Container $container ) {
			return new Pricing_Controller( $container[ Rest::NAMESPACE_BASE ], $container[ Rest::VERSION ], $container[ Rest::PRICING_BASE ], $container[ self::CACHED_PRICING_API ] );
		};
	}
}
