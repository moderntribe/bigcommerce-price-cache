<?php


namespace BigCommerce_Price_Cache\Rest;

use BigCommerce\Api\v3\Model\ItemPricing;
use BigCommerce\Api\v3\ObjectSerializer;
use BigCommerce\Post_Types\Product\Product;
use BigCommerce\Rest\Pricing_Controller;
use BigCommerce\Taxonomies\Channel\Connections;
use BigCommerce_Price_Cache\Exceptions\Cache_Not_Found_Exception;
use BigCommerce_Price_Cache\Import\Processors\Fetch_Price_Cache;

class Cached_Pricing_Controller extends Pricing_Controller {
	public function get_items( $request ) {
		if ( is_user_logged_in() ) {
			// logged in users will always get a fresh price
			return parent::get_items( $request );
		}

		$items = $request->get_param( 'items' );

		// Check if any of the items requested have additional qualifiers
		$uncachable_items = array_filter( $items, function ( $item ) {
			if ( ! empty( $item['options'] ) ) {
				return true;
			}
			if ( ! empty( $item['variant_id'] ) ) {
				return true;
			}

			return false;
		} );
		if ( ! empty( $uncachable_items ) ) {
			return parent::get_items( $request );
		}


		try {
			$connections = new Connections();
			$channel     = $connections->current();
			$items       = array_map( function ( $item ) use ( $channel ) {
				$product = Product::by_product_id( $item['product_id'], $channel );
				$meta    = get_post_meta( $product->post_id(), Fetch_Price_Cache::CACHE_PREFIX . $channel->term_id, true );
				if ( empty( $meta ) ) {
					throw new Cache_Not_Found_Exception( __( 'No pricing cache found for product ID ' . $item['product_id'] ) );
				}

				/** @var ItemPricing $item */
				$item = ObjectSerializer::deserialize( json_decode( $meta ), ItemPricing::class );
				return $this->format_prices( $item );
			}, $items );
		} catch ( \Exception $e ) {
			return parent::get_items( $request );
		}

		return rest_ensure_response( [
			'items' => $items,
		] );
	}

}
