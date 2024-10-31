<?php

namespace CRPlugins\Oca\Orders;

use CRPlugins\Oca\Helper\Helper;
use CRPlugins\Oca\Sdk\OcaSdk;
use CRPlugins\Oca\ValueObjects\Items;
use Exception;
use WC_Order;

defined( 'ABSPATH' ) || exit;

class Quoter {

	/**
	 * @var OcaSdk
	 */
	private $sdk;

	public function __construct( OcaSdk $sdk ) {
		$this->sdk = $sdk;
	}

	/**
	 * @return array<string, array{price: float, days: int}>
	 */
	public function quote_order( WC_Order $order ): array {
		$shipping_codes = Helper::get_seller_settings()->get_shipping_codes()->to_door();

		try {
			$items = Helper::get_items_from_order( $order );

			$this->maybe_set_one_package( $items );
			$this->maybe_set_price_0( $items );
		} catch ( Exception $e ) {
			Helper::log_warning( __FUNCTION__ . ': ' . $e->getMessage() );
			return array();
		}

		$invalid_products = $items->get_invalid_products();
		if ( ! empty( $invalid_products ) ) {
			Helper::log_warning( 'Invalid products: ' . implode( ', ', $invalid_products ) );
			return array();
		}
		$customer = Helper::get_order_customer( $order );

		$quotes = $this->sdk->get_quotes(
			$items->get_totals(),
			Helper::get_seller_postcode(),
			$customer->get_address()->get_postcode(),
			$shipping_codes
		);

		if ( isset( $quotes['error'] ) ) {
			return array();
		}

		return $quotes;
	}

	protected function maybe_set_one_package( Items $items ): void {
		if ( ! Helper::is_enabled( 'proccess_always_one_package' ) ) {
			return;
		}

		$dimensions = Helper::get_default_package_size();
		$items->set_custom_items_quantity( 1, $dimensions['height'], $dimensions['width'], $dimensions['length'] );
	}

	protected function maybe_set_price_0( Items $items ): void {
		// If insurance is enabled and price is accepted by oca, leave it as it is.
		if ( Helper::is_enabled( 'enable_insurance' ) && $items->get_totals()->get_price() > 9999999 ) {
			return;
		}

		// Set to 0 to disable insurance
		$items->set_items_price( 0 );
	}
}
