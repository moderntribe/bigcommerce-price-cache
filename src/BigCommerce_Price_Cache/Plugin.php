<?php


namespace BigCommerce_Price_Cache;

class Plugin {
	const VERSION     = '0.1.0-dev';
	const PLUGIN_FILE = 'bigcommerce_price_cache_plugin_file';

	protected static $_instance;

	/** @var \Pimple\Container */
	protected $container = null;

	/**
	 * @var \BigCommerce\Container\Provider[]
	 */
	private $providers = [];

	/**
	 * @param \Pimple\Container $container
	 */
	public function __construct( \Pimple\Container $container ) {
		$this->container = $container;
	}

	public function __get( $property ) {
		if ( array_key_exists( $property, $this->providers ) ) {
			return $this->providers[ $property ];
		}

		return null;
	}

	public function init() {
		$this->load_service_providers();
	}

	private function load_service_providers() {
		$this->providers['import']  = new Container\Import_Provider();
		$this->providers['pricing'] = new Container\Pricing_Provider();
		$this->providers['rest']    = new Container\Rest_Provider();

		/**
		 * Filter the service providers that power the plugin
		 *
		 * @param \BigCommerce\Container\Provider[] $providers
		 */
		$this->providers = apply_filters( 'bigcommerce_price_cache/plugin/providers', $this->providers );

		foreach ( $this->providers as $provider ) {
			$this->container->register( $provider );
		}
	}

	public function container() {
		return $this->container;
	}

	/**
	 * @return string The URL for the plugin's root directory, with a trailing slash
	 */
	public function plugin_dir_url() {
		return plugin_dir_url( $this->container()[ self::PLUGIN_FILE ] );
	}

	/**
	 * @return string The file system path for the plugin's root directory, with a trailing slash
	 */
	public function plugin_dir_path() {
		return plugin_dir_path( $this->container()[ self::PLUGIN_FILE ] );
	}

	/**
	 * @param null|\ArrayAccess $container
	 *
	 * @return self
	 * @throws \Exception
	 */
	public static function instance( $container = null ) {
		if ( ! isset( self::$_instance ) ) {
			if ( $container === null ) {
				throw new \Exception( 'You need to provide a Pimple container' );
			}

			$className       = __CLASS__;
			self::$_instance = new $className( $container );
		}

		return self::$_instance;
	}
}
