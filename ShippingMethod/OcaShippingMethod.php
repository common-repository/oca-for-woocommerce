<?php

namespace CRPlugins\Oca\ShippingMethod;

use CRPlugins\Oca\Container\Container;
use CRPlugins\Oca\Helper\Helper;
use CRPlugins\Oca\Sdk\OcaSdk;
use CRPlugins\Oca\ValueObjects\Customer;
use CRPlugins\Oca\ValueObjects\Items;
use CRPlugins\Oca\ValueObjects\ShippingBranch;
use CRPlugins\Oca\ValueObjects\ShippingCode;
use CRPlugins\Oca\ValueObjects\ShippingCodes;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use WC_Cart;
use WC_Discounts;
use WC_Shipping_method; /** @phpstan-ignore class.nameCase */

defined( 'ABSPATH' ) || class_exists( '\WC_Shipping_method' ) || exit;

/**
 *  @psalm-suppress InvalidClass
 *  @phpstan-ignore class.nameCase
 */
class OcaShippingMethod extends WC_Shipping_method {

	/**
	 * @var OcaSdk
	 */
	protected $sdk;

	/**
	 * @var ShippingBranchesManager
	 */
	protected $branches_manager;

	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'oca';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = 'OCA';
		$this->title              = 'OCA';
		$this->method_description = __( 'Allow your customers receive their orders with OCA', 'wc-oca' );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);

		$container              = Container::instance();
		$this->sdk              = $container->get( OcaSdk::class );
		$this->branches_manager = $container->get( ShippingBranchesManager::class );

		$this->init_form_fields();
	}

	public function init_form_fields(): void {
		$this->instance_form_fields = array(
			'free_shipping'               => array(
				'title' => __( 'Always free shipping', 'wc-oca' ),
				'type'  => 'checkbox',
			),
			'free_shipping_from_amount'   => array(
				'title'       => __( 'Free shipping from', 'wc-oca' ),
				'type'        => 'decimal',
				'description' => __( 'This adds free shipping when the cart amount is greater than the amount specified. (leave blank to disable)', 'wc-oca' ),
				'desc_tip'    => true,
			),
			'free_shipping_in_time_range' => array(
				'title'       => __( 'Free shipping within time range', 'wc-oca' ),
				'type'        => 'text',
				'description' => __( 'Automatically enables free shipping in a time range, use format 00-24 to enable or leave blank to disable. For example: "04-18" will enable free shipping from 04:00am until 06:00pm in Argentinian timezone.', 'wc-oca' ),
				'desc_tip'    => true,
			),
		);
	}

	public function calculate_shipping( $package = array() ): void {
		$seller_settings = Helper::get_seller_settings();
		if ( ! $seller_settings ) {
			return;
		}

		$customer = Helper::get_customer_from_wc( WC()->customer );
		if ( ! $customer->get_address()->has_postcode() ) {
			return;
		}

		// Make sure cache is up to date
		CacheManager::maybe_invalidate_oca_shipping_cache();

		// Set cache manually since WC's is broken
		$selected_branch = $this->branches_manager->get_selected_branch();
		$cache_key       = CacheManager::get_oca_shipping_cache_key(
			$package,
			$selected_branch ? $selected_branch->get_id() : $customer->get_address()->get_postcode()
		);

		$cache_rates = WC()->session->get( $cache_key );
		if ( $cache_rates ) {
			$this->rates = $cache_rates;
			return;
		}

		$cart = WC()->cart;
		do_action( 'wc_oca_cart_before_items_calculation', $cart, $customer );

		try {
			$items = Helper::get_items_from_cart( $cart );

			$this->maybe_set_one_package( $items );
			$this->maybe_set_price_0( $items );
		} catch ( Exception $e ) {
			Helper::log_warning( __FUNCTION__ . ': ' . $e->getMessage() );
			return;
		}

		$invalid_products = $items->get_invalid_products();
		if ( ! empty( $invalid_products ) ) {
			Helper::log_warning( 'Invalid products: ' . implode( ', ', $invalid_products ) );
			return;
		}

		$seller_postcode = Helper::get_seller_postcode();
		if ( ! $seller_postcode ) {
			Helper::log_warning( __FUNCTION__ . ': No seller postcode' );
		}

		/** @var Items */
		$items = apply_filters( 'wc_oca_cart_before_shipping_calculation', $items, $cart, $customer );

		$shipping_codes = $seller_settings->get_shipping_codes();

		/** @var ShippingCodes */
		$shipping_codes = apply_filters(
			'wc_oca_shipping_codes_before_quote',
			$shipping_codes,
			$seller_postcode,
			$items,
			$customer
		);

		$this->add_shipping_to_door( $shipping_codes->to_door(), $seller_postcode, $items, $customer );
		$this->add_shipping_to_branch( $shipping_codes->to_branch(), $seller_postcode, $items, $customer );

		WC()->session->set( $cache_key, $this->rates );
		WC()->session->set( 'oca_settings_version', Helper::get_option( 'settings_version', time() ) );
	}

	protected function add_shipping_to_door(
		ShippingCodes $shipping_codes,
		string $from_postcode,
		Items $items,
		Customer $customer
	): void {
		$quotes = $this->sdk->get_quotes(
			$items->get_totals(),
			$from_postcode,
			$customer->get_address()->get_postcode(),
			$shipping_codes
		);

		if ( isset( $quotes['error'] ) ) {
			return;
		}

		foreach ( $quotes as $code_id => $quote ) {
			$shipping_code = $shipping_codes->get_by_id( $code_id );
			$this->add_method( $quote['price'], $quote['days'], $shipping_code, $items );
		}
	}

	protected function add_shipping_to_branch(
		ShippingCodes $shipping_codes,
		string $from_postcode,
		Items $items,
		Customer $customer
	): void {
		if ( $this->branches_manager->is_branches_selector_enabled() ) {
			$selected_branch = $this->branches_manager->get_selected_branch();
			if ( ! $selected_branch ) {
				// If there is no branch selected, use the first one available
				$branches = $this->branches_manager->get_branches_to( $customer->get_address()->get_postcode() );
				if ( $branches ) {
					$selected_branch = $branches[0];
					$this->branches_manager->set_selected_branch( $selected_branch );
				}
			}
		} else {
			$selected_branch = null;
			$branches        = $this->branches_manager->get_branches_to( $customer->get_address()->get_postcode() );
			if ( $branches ) {
				$selected_branch = $branches[0];
				$this->branches_manager->set_selected_branch( $selected_branch );
			}
		}

		if ( ! $selected_branch ) {
			Helper::log_debug( sprintf( 'No shipping branches found for CP %s', $customer->get_address()->get_postcode() ) );
			return;
		}

		$quotes = $this->sdk->get_quotes(
			$items->get_totals(),
			$from_postcode,
			$selected_branch->get_postcode(),
			$shipping_codes
		);

		if ( isset( $quotes['error'] ) ) {
			return;
		}

		foreach ( $quotes as $code_id => $quote ) {
			$shipping_code = $shipping_codes->get_by_id( $code_id );
			$this->add_method( $quote['price'], $quote['days'], $shipping_code, $items, $selected_branch );
		}
	}

	protected function final_price( float $price, WC_Cart $cart ): float {
		// Always free shipping
		if ( $this->get_instance_option( 'free_shipping' ) === 'yes' ) {
			return 0;
		}

		// Free shipping from X cart amount
		$free_shipping_from_amount = $this->get_instance_option( 'free_shipping_from_amount' );
		if ( $free_shipping_from_amount && $cart->get_cart_contents_total() > (float) $free_shipping_from_amount ) {
			return 0;
		}

		// Free shipping if hour is X
		$free_shipping_in_time_range = $this->get_instance_option( 'free_shipping_in_time_range' );
		if ( $free_shipping_in_time_range && preg_match( '/(\d{2})-(\d{2})/', $free_shipping_in_time_range, $res ) ) {
			$res[1] = (int) $res[1];
			$res[2] = (int) $res[2];

			$now = new DateTimeImmutable( 'now', new DateTimeZone( 'America/Argentina/Buenos_Aires' ) );

			$start_hour = $now->setTime( $res[1], 0 );
			$end_hour   = $now->setTime( $res[2], 0 );
			if ( $res[1] > $res[2] ) {
				// e.g: 23-02
				$end_hour = $end_hour->modify( '+1 day' );
			}

			if ( $now >= $start_hour && $now <= $end_hour ) {
				return 0;
			}
		}

		// Free shipping if a free shipping coupon is present
		if ( Helper::is_enabled( 'free_shipping_with_coupon' ) ) {
			/** @var array<string,\WC_Coupon> */
			$coupons      = WC()->cart->get_coupons();
			$wc_discounts = new WC_Discounts( WC()->cart );

			foreach ( $coupons as $coupon ) {
				if ( $wc_discounts->is_coupon_valid( $coupon ) === true && $coupon->get_free_shipping() ) {
					return 0;
				}
			}
		}

		$price_multiplier = Helper::get_option( 'price_multiplier', 1 );
		$price           *= (float) $price_multiplier;

		if ( Helper::is_enabled( 'round_costs' ) ) {
			$price = ceil( $price ); // ceil to prevent money loss
		}

		return $price;
	}

	protected function shipping_label(
		ShippingCode $shipping_code,
		float $final_price,
		float $customer_price,
		int $delivery_days,
		?ShippingBranch $selected_branch = null
	): string {
		$shipping_label = $shipping_code->get_name();

		$has_days_placeholder = Helper::str_contains( $shipping_label, '{{oca_tiempo_entrega}}' );
		if ( $has_days_placeholder && $delivery_days ) {
			$extra_delivery_days = absint( (int) Helper::get_option( 'extra_delivery_days', 0 ) );
			$delivery_days       = $delivery_days + $extra_delivery_days;
			$shipping_label      = str_replace( '{{oca_tiempo_entrega}}', $delivery_days, $shipping_label );
		}

		$has_price_placeholder = Helper::str_contains( $shipping_label, '{{oca_precio_envio}}' );
		if ( $has_price_placeholder ) {
			$shipping_label = str_replace( '{{oca_precio_envio}}', $customer_price, $shipping_label );
		}

		$extra_name_free = Helper::get_option( 'free_shipping_extra_name', null );
		if ( 0.0 === $final_price && ! empty( $extra_name_free ) ) {
			$shipping_label .= ' ' . $extra_name_free;
		}

		if ( $selected_branch && Helper::is_enabled( 'add_branch_name_to_method' ) ) {
			$shipping_label .= ' - ' . $selected_branch->get_address();
		}

		return $shipping_label;
	}

	protected function add_method(
		float $price,
		int $delivery_days,
		ShippingCode $code,
		Items $items,
		?ShippingBranch $shipping_branch = null
	): void {
		$final_price = $this->final_price( $code->has_price() ? $code->get_price() : $price, WC()->cart );
		$rate        = array(
			'id'        => sprintf(
				'%s|%s|%s', // rate id is for WooCommerce, code id is for unique id, door|branch is for branches selector
				$this->get_rate_id(),
				$code->get_id(),
				$code->is_to_door() ? 'door' : 'branch'
			),
			'label'     => $this->shipping_label( $code, $final_price, $price, $delivery_days, $shipping_branch ),
			'cost'      => $final_price,
			'meta_data' => array(
				'shipping_code'     => array(
					'id'   => $code->get_id(),
					'name' => $code->get_name(),
					'type' => $code->get_type(),
				),
				'shipping_branch'   => $shipping_branch ? $shipping_branch->get_id() : null,
				'packages_quantity' => $items->get_totals()->get_quantity(),
			),
		);

		$rate = apply_filters( sprintf( 'wc_oca_%s_shipping_rate', $code->is_to_door() ? 'door' : 'branch' ), $rate, $code, $items );
		$this->add_rate( $rate );
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
