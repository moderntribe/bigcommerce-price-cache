<?php


namespace BigCommerce_Price_Cache\Import\Processors;

use BigCommerce\Api\v3\Api\PricingApi;
use BigCommerce\Api\v3\ApiException;
use BigCommerce\Api\v3\Model\PricingRequest;
use BigCommerce\Api\v3\ObjectSerializer;
use BigCommerce\Import\No_Cache_Options;
use BigCommerce\Import\Processors\Import_Processor;
use BigCommerce\Import\Runner\Status;
use BigCommerce\Logging\Error_Log;
use BigCommerce\Post_Types\Product\Product;
use BigCommerce\Settings\Sections\Currency;
use BigCommerce\Taxonomies\Channel\Channel;

class Fetch_Price_Cache implements Import_Processor {
	use No_Cache_Options;

	const RUNNING      = 'fetching_price_cache';
	const COMPLETE     = 'fetched_price_cache';
	const STATE_OPTION = 'bigcommerce_price_cache_fetching_state';

	/** @var PricingApi */
	private $pricing;
	/** @var \WP_Term */
	private $channel_term;
	/** @var int */
	private $limit;

	public function __construct( PricingApi $pricing, \WP_Term $channel_term, $limit = 100 ) {
		$this->pricing      = $pricing;
		$this->channel_term = $channel_term;
		$this->limit        = $limit;
	}

	public function run() {
		$status = new Status();
		$status->set_status( self::RUNNING . '-' . $this->channel_term->term_id );

		$channel_id = (int) get_term_meta( $this->channel_term->term_id, Channel::CHANNEL_ID, true );
		if ( empty( $channel_id ) ) {
			do_action( 'bigcommerce/log', Error_Log::ERROR, __( 'Unable to retrieve prices. Channel ID not found.', 'bigcommerce' ), [
				'term_id' => $this->channel_term->term_id,
			] );
			$status->set_status( self::COMPLETE . '-' . $this->channel_term->term_id );
			$this->clear_state();

			return;
		}

		$next = $this->get_next();

		do_action( 'bigcommerce/log', Error_Log::DEBUG, __( 'Retrieving prices', 'bigcommerce' ), [
			'limit' => $this->limit,
			'page'  => $next ?: 0,
		] );

		$posts_to_query = $this->get_post_ids( $next, $this->limit );
		$product_ids    = $this->map_post_ids_to_products( $posts_to_query[ 'post_ids' ] );

		if ( empty( $product_ids ) ) {
			do_action( 'bigcommerce/log', Error_Log::ERROR, __( 'No product IDs found.', 'bigcommerce' ), [
				'term_id' => $this->channel_term->term_id,
			] );
			$status->set_status( self::COMPLETE . '-' . $this->channel_term->term_id );
			$this->clear_state();

			return;
		}

		/**
		 * Filter the customer group ID passed to the BigCommerce API
		 *
		 * @param int|null $group_id The customer group ID
		 * @param \WP_Term $channel  The channel the request is for
		 */
		$customer_group = apply_filters( 'bigcommerce_price_cache/request/customer_group_id', null, $this->channel_term );
		/**
		 * Filter the currency passed to the BigCommerce API
		 *
		 * @param string   $currency_code The three character currency code
		 * @param \WP_Term $channel       The channel the request is for
		 */
		$currency_code = apply_filters( 'bigcommerce_price_cache/request/currency_code', get_option( Currency::CURRENCY_CODE, 'USD' ), $this->channel_term );

		$args = [
			'items'             => array_map( function( $product_id ) {
				return [ 'product_id' => $product_id ];
			}, $product_ids ),
			'channel_id'        => $channel_id,
			'currency_code'     => $currency_code,
			'customer_group_id' => $customer_group,
		];

		try {
			$pricing_request  = new PricingRequest( $args );
			$pricing_response = $this->pricing->getPrices( $pricing_request );
		} catch ( ApiException $e ) {
			do_action( 'bigcommerce/import/error', $e->getMessage(), [
				'response' => $e->getResponseBody(),
				'headers'  => $e->getResponseHeaders(),
			] );
			do_action( 'bigcommerce/log', Error_Log::DEBUG, $e->getTraceAsString(), [] );

			return;
		}

		$post_ids = array_flip( $product_ids );
		$meta_key = 'bigcommerce_price_cache-' . $this->channel_term->term_id;
		foreach ( $pricing_response->getData() as $price ) {
			$post_id = array_key_exists( $price->getProductId(), $post_ids ) ? $post_ids[ $price->getProductId() ] : 0;
			if ( empty( $post_id ) ) {
				continue;
			}
			update_post_meta( $post_id, $meta_key, json_encode( ObjectSerializer::sanitizeForSerialization( $price ) ) );
		}


		if (  $next < $posts_to_query[ 'total_pages' ]  ) {
			do_action( 'bigcommerce/log', Error_Log::DEBUG, __( 'Ready for next page of prices', 'bigcommerce' ), [
				'next' => $next + 1,
			] );
			$this->set_next( $next + 1 );
		} else {
			$status->set_status( self::COMPLETE . '-' . $this->channel_term->term_id );
			$this->clear_state();
		}
	}

	/**
	 * @param int $page
	 * @param int $limit
	 *
	 * @return array
	 */
	private function get_post_ids( $page, $limit ) {
		$query    = new \WP_Query();
		$post_ids = $query->query( [
			'posts_per_page' => $limit,
			'paged'          => $page,
			'fields'         => 'ids',
			'post_type'      => Product::NAME,
			'tax_query'      => [
				[
					'taxonomy' => Channel::NAME,
					'terms'    => $this->channel_term->term_id,
					'field'    => 'term_id',
					'operator' => 'IN',
				],
			],
		] );

		return [
			'post_ids' => array_map( 'intval', $post_ids ),
			'total_pages' => $query->max_num_pages,
		];
	}

	/**
	 * Map post IDs to their corresponding product IDs.
	 *
	 * Uses a direct DB query, because that's more efficient than
	 * looping through each post ID and calling get_post_meta()
	 *
	 * @param int[] $post_ids
	 *
	 * @return int[] Product IDs keyed by post ID
	 */
	private function map_post_ids_to_products( array $post_ids ) {
		if ( empty( $post_ids ) ) {
			return [];
		}
		/** @var \wpdb $wpdb */
		global $wpdb;
		$post_id_list = implode( ',', array_map( 'intval', $post_ids ) );
		$sql          = "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key=%s AND post_id IN ( $post_id_list )";
		$sql          = $wpdb->prepare( $sql, Product::BIGCOMMERCE_ID );
		$results      = $wpdb->get_results( $sql, ARRAY_A );

		return array_map( 'intval', wp_list_pluck( $results, 'meta_value', 'post_id' ) );
	}

	private function get_next() {
		$state = $this->get_state();
		if ( ! array_key_exists( 'next', $state ) ) {
			return 1;
		}

		return $state['next'];
	}

	private function set_next( $next ) {
		$state         = $this->get_state();
		$state['next'] = (int) $next;
		$this->set_state( $state );
	}

	private function get_state() {
		$state = $this->get_option( $this->state_option(), [] );
		if ( ! is_array( $state ) ) {
			return [];
		}

		return $state;
	}

	private function set_state( array $state ) {
		$this->update_option( $this->state_option(), $state, false );
	}

	private function clear_state() {
		$this->delete_option( $this->state_option() );
	}

	private function state_option() {
		return sprintf( '%s-%d', self::STATE_OPTION, $this->channel_term->term_id );
	}
}
