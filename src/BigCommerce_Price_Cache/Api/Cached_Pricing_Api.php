<?php


namespace BigCommerce_Price_Cache\Api;

use BigCommerce\Api\v3\Api\PricingApi;
use BigCommerce\Api\v3\ApiException;
use BigCommerce\Api\v3\Model\ItemPricing;
use BigCommerce\Api\v3\Model\Meta;
use BigCommerce\Api\v3\Model\PricingRequest;
use BigCommerce\Api\v3\Model\PricingRequestItem;
use BigCommerce\Api\v3\Model\PricingResponse;
use BigCommerce\Api\v3\ObjectSerializer;
use BigCommerce\Post_Types\Product\Product;
use BigCommerce\Taxonomies\Channel\Channel;
use BigCommerce_Price_Cache\Exceptions\Cache_Not_Found_Exception;
use BigCommerce_Price_Cache\Pricing\Price_Cache;

/**
 * Class Cached_Pricing_Api
 *
 * Intercepts requests to the pricing API, returning data from
 * WordPress post meta if the request is for default item states
 */
class Cached_Pricing_Api extends PricingApi {
	/**
	 * Operation getPrices
	 * Get Prices
	 *
	 *
	 * @param PricingRequest $body   (required)
	 * @param array          $params = []
	 *
	 * @return PricingResponse
	 * @throws ApiException on non-2xx response
	 * @throws \InvalidArgumentException
	 */
	public function getPrices( $body, array $params = [] ) {
		if ( $body->getCustomerGroupId() !== null ) {
			// only the default customer group gets cached prices
			return parent::getPrices( $body, $params );
		}

		$items = $body->getItems();

		if ( $this->has_uncacheable_items( $items ) ) {
			return parent::getPrices( $body, $params );
		}

		try {
			$channel_id = $body->getChannelId();
			$channel    = Channel::find_by_id( $channel_id );

			$items = array_map( function ( $item ) use ( $channel ) {
				return $this->get_cached_pricing( $item, $channel );
			}, $items );

			return new PricingResponse( [
				'data' => $items,
				'meta' => new Meta(),
			] );
		} catch ( \Exception $e ) {
			return parent::getPrices( $body, $params );
		}
	}

	/**
	 * @param PricingRequestItem[]|array[] $items
	 *
	 * @return bool
	 */
	private function has_uncacheable_items( $items ) {
		foreach ( $items as $item ) {
			if ( ! empty( $item['options'] ) ) {
				return true;
			}
			if ( ! empty( $item['variant_id'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param PricingRequestItem|array $item
	 * @param \WP_Term                 $channel
	 *
	 * @return array|object|null
	 */
	private function get_cached_pricing( $item, \WP_Term $channel ) {
		$product = Product::by_product_id( $item['product_id'], $channel );
		$meta    = get_post_meta( $product->post_id(), Price_Cache::meta_key( $channel ), true );
		if ( empty( $meta ) ) {
			throw new Cache_Not_Found_Exception( __( 'No pricing cache found for product ID ' . $item['product_id'] ) );
		}

		/** @var ItemPricing $item */
		return ObjectSerializer::deserialize( json_decode( $meta ), ItemPricing::class );
	}

}
