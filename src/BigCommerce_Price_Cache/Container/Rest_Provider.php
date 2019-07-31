<?php


namespace BigCommerce_Price_Cache\Container;

use BigCommerce\Container\Api;
use BigCommerce\Container\Provider;
use BigCommerce\Container\Rest;
use BigCommerce_Price_Cache\Rest\Cached_Pricing_Controller;
use Pimple\Container;

class Rest_Provider extends Provider {
	public function register( Container $container ) {
		// Override the pricing controller
		$container[ Rest::PRICING ] = function( Container $container ) {
			return new Cached_Pricing_Controller( $container[ Rest::NAMESPACE_BASE ], $container[ Rest::VERSION ], $container[ Rest::PRICING_BASE ], $container[ Api::FACTORY ]->pricing() );
		};
	}
}
