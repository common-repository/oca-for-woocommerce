<?php

namespace CRPlugins\Oca\Settings;

use CRPlugins\Oca\Helper\Helper;
use CRPlugins\Oca\Sdk\OcaSdk;
use CRPlugins\Oca\ShippingMethod\OcaShippingMethod;
use CRPlugins\Oca\ValueObjects\Address;
use CRPlugins\Oca\ValueObjects\Customer;
use CRPlugins\Oca\ValueObjects\Item;
use CRPlugins\Oca\ValueObjects\Items;
use CRPlugins\Oca\ValueObjects\ItemsTotals;
use DateTime;
use DateTimeZone;
use WC_Order;
use WC_Shipping_Method;
use WC_Shipping_Zones;

class HealthChecker {

	/**
	 * @var OcaSdk
	 */
	private $sdk;

	public function __construct( OcaSdk $sdk ) {
		$this->sdk = $sdk;
	}

	/**
	 * @return array{id: int, name: string, valid: bool}[]
	 */
	public function get_invalid_shipping_zones(): array {
		$wc_zones = WC_Shipping_Zones::get_zones();

		$zones = array();
		/** @var array{id: int, zone_name: string, shipping_methods: WC_Shipping_Method[]}[] $zone */
		foreach ( $wc_zones as $zone ) {
			$valid = false;

			/** @var string[] $methods */
			$methods = array_map(
				function ( WC_Shipping_Method $zone ): string {
					return get_class( $zone );
				},
				$zone['shipping_methods']
			);

			if ( in_array( OcaShippingMethod::class, $methods, true ) ) {
				$valid = true;
			}

			$zones[] = array(
				'id'    => $zone['id'],
				'name'  => $zone['zone_name'],
				'valid' => $valid,
			);
		}

		return $zones;
	}

	/**
	 * @return array{file_permissions: bool, zlib: bool, zip: bool}
	 */
	public function get_misc_status(): array {
		$permissions = false;
		$file        = sprintf( '%s/test.pdf', Helper::get_labels_folder_path() );

		try {
			$file_stream = fopen( $file, 'w' ); // phpcs:ignore
			if ( ! $file_stream ) {
				throw new \Exception( 'Error' );
			}

			fwrite( $file_stream, '\n' ); // phpcs:ignore
			fclose( $file_stream ); // phpcs:ignore
			unlink( $file ); // phpcs:ignore
			$permissions = true;
		} catch ( \Throwable $th ) {
			$permissions = false;
		}

		return array(
			'file_permissions' => $permissions,
			'zlib'             => extension_loaded( 'zlib' ),
			'zip'              => extension_loaded( 'zip' ),
		);
	}

	/**
	 * @return array{id: int, link: string, valid: false}[]
	 */
	public function get_invalid_products(): array {
		$invalid_products = array();
		$all_products     = wc_get_products(
			array(
				'return'       => 'ids',
				'status'       => 'publish',
				'stock_status' => 'instock',
				'virtual'      => false,
				'limit'        => -1,
			)
		);

		// phpcs:disable WordPress.DB.SlowDBQuery
		$products_with_weight = get_posts(
			array(
				'fields'         => 'ids',
				'post_type'      => array( 'product' ),
				'post_status'    => array( 'publish' ),
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => '_weight',
						'value'   => 0,
						'compare' => '>',
					),
				),
			)
		);
		$invalid_products     = array_diff( $all_products, $products_with_weight );

		$products_with_height = get_posts(
			array(
				'fields'         => 'ids',
				'post_type'      => array( 'product' ),
				'post_status'    => array( 'publish' ),
				'post__not_in'   => $invalid_products,
				'posts_per_page' => -1,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => '_height',
						'value'   => 0,
						'compare' => '>',
					),
				),
			)
		);
		$invalid_products     = array_merge( $invalid_products, array_diff( $all_products, $products_with_height ) );

		$products_with_length = get_posts(
			array(
				'fields'         => 'ids',
				'post_type'      => array( 'product' ),
				'post_status'    => array( 'publish' ),
				'post__not_in'   => $invalid_products,
				'posts_per_page' => -1,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => '_length',
						'value'   => 0,
						'compare' => '>',
					),
				),
			)
		);
		$invalid_products     = array_merge( $invalid_products, array_diff( $all_products, $products_with_length ) );

		$products_with_width = get_posts(
			array(
				'fields'         => 'ids',
				'post_type'      => array( 'product' ),
				'post_status'    => array( 'publish' ),
				'post__not_in'   => $invalid_products,
				'posts_per_page' => -1,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => '_width',
						'value'   => 0,
						'compare' => '>',
					),
				),
			)
		);
		$invalid_products    = array_merge( $invalid_products, array_diff( $all_products, $products_with_width ) );

		$invalid_products = array_unique( $invalid_products );

		$invalid_products = array_map(
			function ( $product_id ) {
				return array(
					'id'    => $product_id,
					'link'  => get_edit_post_link( $product_id, '' ),
					'valid' => false,
				);
			},
			$invalid_products
		);

		return $invalid_products;
	}

	public function is_quote_api_valid(): bool {
		$settings = Helper::get_seller_settings();
		if ( ! $settings ) {
			return false;
		}

		$seller_postcode = Helper::get_seller_postcode();
		if ( ! $seller_postcode ) {
			return false;
		}

		$codes = $settings->get_shipping_codes();
		if ( ! $codes->to_door()->is_empty() ) {
			$codes = $codes->to_door();
		} elseif ( ! $codes->to_branch()->is_empty() ) {
			$codes = $codes->to_branch();
		} else {
			return false;
		}

		$totals = new ItemsTotals();
		$totals->add_volume( 0.001 );
		$totals->add_weight( 0.1 );
		$totals->add_price( 100 );
		$totals->add_quantity( 1 );

		$response = $this->sdk->get_quotes( $totals, $seller_postcode, '1040', $codes );

		return ! isset( $response['error'] );
	}

	public function is_processor_api_valid(): bool {
		$order = new WC_Order();
		$order->set_total( 100 );
		$order->set_id( 123 );

		$seller = Helper::get_seller();
		$codes  = $seller->get_settings()->get_shipping_codes();
		if ( ! $codes->to_door()->is_empty() ) {
			$code = current( $codes->to_door()->get() );
		} elseif ( ! $codes->to_branch()->is_empty() ) {
			$code = current( $codes->to_branch()->get() );
		} else {
			return false;
		}

		$customer = new Customer(
			'Prueba',
			'CRPLUGINS',
			new Address(
				'Rivadavia',
				'100',
				'1',
				'B',
				'1040',
				'Capital Federal',
				'C',
				'ORDEN DE PRUEBA - NO ENVIAR'
			)
		);
		$customer->get_address()->set_shipping_branch( 147 );

		$items = new Items( array( new Item( 0.1, 0.1, 0.1, 0.1, 100, 'test', 1, 100 ) ) );

		$today = new DateTime( 'now', new DateTimeZone( 'America/Argentina/Buenos_Aires' ) );

		$response = $this->sdk->process_order(
			$seller,
			$customer,
			$items,
			$order,
			$code,
			$today,
			true
		);

		if ( empty( $response ) ) {
			return false;
		}

		if ( isset( $response['error'] ) ) {
			return false;
		}

		$response = $this->sdk->cancel_order( $response['oca_number'] );

		if ( isset( $response['error'] ) ) {
			return false;
		}

		return true;
	}
}
