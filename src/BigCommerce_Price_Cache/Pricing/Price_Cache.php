<?php


namespace BigCommerce_Price_Cache\Pricing;

use BigCommerce\Api\v3\Model\ItemPricing;
use BigCommerce\Api\v3\ObjectSerializer;
use BigCommerce\Post_Types\Product\Product;
use BigCommerce\Settings\Sections\Currency;
use BigCommerce\Taxonomies\Channel\Connections;

class Price_Cache {
	const CACHE_PREFIX = 'bigcommerce_price_cache-';

	public static function meta_key( \WP_Term $channel ) {
		return self::CACHE_PREFIX . $channel->term_id;
	}

	/**
	 * @param array   $data
	 * @param Product $product
	 *
	 * @return array
	 * @filter bigcommerce/product/price_range/data
	 */
	public function filter_price_range( $data, Product $product ) {
		try {
			$connections = new Connections();
			$channel     = $connections->current();

			$cache = get_post_meta( $product->post_id(), self::meta_key( $channel ), true );
			if ( empty( $cache ) ) {
				return $data;
			}

			/** @var ItemPricing $item */
			$item = ObjectSerializer::deserialize( json_decode( $cache, false ), ItemPricing::class );

			$price   = $item->getPriceRange();
			$minimum = $price->getMinimum();
			$maximum = $price->getMaximum();
			switch ( get_option( Currency::PRICE_DISPLAY, Currency::DISPLAY_TAX_EXCLUSIVE ) ) {
				case Currency::DISPLAY_TAX_INCLUSIVE:
					$min_value = $minimum ? $minimum->getTaxInclusive() : 0;
					$max_value = $maximum ? $maximum->getTaxInclusive() : 0;
					break;
				case Currency::DISPLAY_TAX_EXCLUSIVE:
				default:
					$min_value = $minimum ? $minimum->getTaxExclusive() : 0;
					$max_value = $maximum ? $maximum->getTaxExclusive() : 0;
					break;
			}

			$data['price']['min'] = $data['calculated']['min'] = $min_value;
			$data['price']['max'] = $data['calculated']['max'] = $max_value;
		} catch ( \Exception $e ) {
			// no change to $data
		}

		return $data;
	}
}
