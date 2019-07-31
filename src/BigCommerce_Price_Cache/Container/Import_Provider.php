<?php


namespace BigCommerce_Price_Cache\Container;

use BigCommerce\Container\Api;
use BigCommerce\Container\Import;
use BigCommerce\Container\Provider;
use BigCommerce\Import\Task_Definition;
use BigCommerce\Import\Task_Manager;
use BigCommerce_Price_Cache\Import\Processors;
use Pimple\Container;

class Import_Provider extends Provider {
	const PRICING_TASK = 'bigcommerce_price_cache.import.pricing';

	public function register( Container $container ) {
		$container[ self::PRICING_TASK ] = function ( Container $container ) {
			return function ( $channel_term ) use ( $container ) {
				return new Processors\Fetch_Price_Cache( $container[ Api::FACTORY ]->pricing(), $channel_term, $container[ Import::LARGE_BATCH_SIZE ] );
			};
		};

		add_action( 'bigcommerce/import/task_manager/init', $this->create_callback( 'register_pricing_task', function ( Task_Manager $manager ) use ( $container ) {
			foreach ( $container[ Import::CHANNEL_LIST ] as $channel_term ) {
				$suffix = sprintf( '-%d', $channel_term->term_id );
				$manager->register( new Task_Definition( $this->create_callback( 'cache_pricing' . $suffix, function () use ( $container, $channel_term ) {
					$container[ self::PRICING_TASK ]( $channel_term )->run();
				} ), 75, Processors\Fetch_Price_Cache::COMPLETE . $suffix, [ Processors\Fetch_Price_Cache::RUNNING . $suffix ], sprintf( __( 'Caching default pricing for channel %s', 'bigcommerce' ), esc_html( $channel_term->name ) ) ) );
			}
		} ), 10, 1 );
	}

}
