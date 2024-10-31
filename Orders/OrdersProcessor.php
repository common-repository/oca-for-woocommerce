<?php

namespace CRPlugins\Oca\Orders;

use CRPlugins\Oca\Emails\TrackingMail;
use CRPlugins\Oca\Helper\Helper;
use CRPlugins\Oca\Sdk\OcaSdk;
use CRPlugins\Oca\ShippingLabels\AllLabelsProcessor;
use CRPlugins\Oca\ShippingMethod\CacheManager;
use CRPlugins\Oca\ValueObjects\Customer;
use CRPlugins\Oca\ValueObjects\Items;
use DateTime;
use DateTimeZone;
use Exception;
use WC_Order;

class OrdersProcessor {

	/**
	 * @var OcaSdk
	 */
	private $sdk;

	/**
	 * @var AllLabelsProcessor
	 */
	private $labels_processor;

	/**
	 * @var DaysManager
	 */
	private $days_manager;

	public function __construct( OcaSdk $sdk, AllLabelsProcessor $labels_processor, DaysManager $days_manager ) {
		$this->sdk              = $sdk;
		$this->labels_processor = $labels_processor;
		$this->days_manager     = $days_manager;

		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status' ), 10, 4 );
		add_action( 'woocommerce_thankyou', array( $this, 'clear_method_cache' ) );
	}

	public function handle_order_status( int $order_id, string $status_from, string $status_to, WC_Order $order ): void {
		$shipping_method = Helper::get_order_shipping_method( $order );
		if ( $shipping_method->is_oca() ) {
			$config_status = Helper::get_option( 'status_processing', '' );
			$config_status = str_replace( 'wc-', '', $config_status );

			if ( $order->has_status( $config_status ) && ! $shipping_method->is_processed() ) {
				$this->process_order( $order, true );
			}
		}
	}

	public function process_order( WC_Order $order, bool $force ): void {
		$shipping_method = Helper::get_order_shipping_method( $order );
		if ( $shipping_method->is_empty() ) {
			return;
		}

		if ( ! $shipping_method->is_oca() || ( $shipping_method->is_processed() && ! $force ) ) {
			return;
		}

		$shipping_code = $shipping_method->get_shipping_code();
		if ( ! $shipping_code ) {
			Helper::add_error( __( 'There was an error processing the order with OCA, please try again', 'wc-oca' ) );
			return;
		}

		$seller = Helper::get_seller();
		if ( empty( $seller->get_settings()->get_account_number() ) ) {
			Helper::add_error( __( 'There was an error processing the order with OCA, please try again', 'wc-oca' ) );
			return;
		}

		$to = Helper::get_order_customer( $order );

		try {
			$items = Helper::get_items_from_order( $order );

			$this->maybe_set_one_package( $items );
			$this->maybe_set_price_0( $items );
		} catch ( Exception $e ) {
			Helper::log_debug( __FUNCTION__ . ': ' . $e->getMessage() );
			Helper::add_error( __( 'There was an error processing the order with OCA, check the order items and try again', 'wc-oca' ) );
			return;
		}

		$override_packages = $shipping_method->get_packages_quantity();
		if ( $items->get_totals()->get_quantity() !== $override_packages ) {
			// Number of packages overridden, send a custom item
			$dimensions = Helper::get_default_package_size();
			$items->set_custom_items_quantity(
				$override_packages,
				$dimensions['height'],
				$dimensions['width'],
				$dimensions['length']
			);
		}

		$invalid_products = $items->get_invalid_products();
		if ( ! empty( $invalid_products ) ) {
			Helper::log_warning( 'Invalid products: ' . implode( ', ', $invalid_products ) );
			Helper::add_error( __( 'There was an error processing the order with OCA, check the order items and try again', 'wc-oca' ) );
			return;
		}

		// Considerations for shipping to a branch office
		if ( $shipping_code->is_to_branch() ) {
			$selected_branch = $shipping_method->get_shipping_branch_id();
			if ( ! $selected_branch ) {
				Helper::add_error( __( 'Tried to process a shipment to branch without having one destination branch selected', 'wc-oca' ) );
				return;
			}

			$to->get_address()->set_shipping_branch( $selected_branch );
		}

		// Considerations from door
		if ( $shipping_code->is_from_door() ) {
			$seller->get_address()->set_shipping_branch( 0 );
		}

		// If insurance is enabled and price is accepted by oca, leave it as it is.
		if ( ! Helper::is_enabled( 'enable_insurance' ) || $items->get_totals()->get_price() > 9999999 ) {
			$items->set_items_price( 0 );
		}

		/** @var Items $items */
		$items = apply_filters( 'wc_oca_items_before_process', $items, $to, $order );
		/** @var Customer $customer */
		$to = apply_filters( 'wc_oca_to_before_process', $to, $items, $order );

		$today = new DateTime( 'now', new DateTimeZone( 'America/Argentina/Buenos_Aires' ) );

		$dispatch_day = $this->days_manager->get_soonest_available_date(
			$today,
			Helper::get_option( 'dispatch_days', array( '1', '2', '3', '4', '5', '6' ) ),
			$seller->get_time_frame(),
			absint( (int) Helper::get_option( 'extra_dispatch_days', '0' ) )
		);

		do_action( 'wc_oca_before_order_process', $order );

		$response = $this->sdk->process_order( $seller, $to, $items, $order, $shipping_code, $dispatch_day, $force );

		if ( empty( $response ) ) {
			Helper::add_error( __( 'There was an error processing the order with OCA, please try again', 'wc-oca' ) );
			return;
		}

		if ( isset( $response['error'] ) ) {
			Helper::add_error( $response['error'] );
			return;
		}

		/** @var array{oca_number: string, tracking_number: string} $response */
		$response = apply_filters( 'wc_oca_response_after_process', $response, $order );

		$shipping_method->set_tracking_information( $response['tracking_number'], $response['oca_number'] );

		if ( Helper::is_enabled( 'notify_customer_order_processed', true ) ) {
			$this->send_tracking_mail( $order );
		}

		$processed_message = sprintf(
			__( 'Order %1$s processed with OCA succesfully, tracking number: %2$s', 'wc-oca' ),
			$order->get_id(),
			$response['tracking_number']
		);
		Helper::add_success( $processed_message );
		$order->add_order_note( $processed_message );

		// Delete all labels if this is a new oca order so we can fetch new ones.
		if ( $force ) {
			$this->labels_processor->delete_all_labels( $order );
		}

		$this->labels_processor->create_order_labels( $order );

		do_action( 'wc_oca_after_order_process', $order );
	}

	public function cancel_order( WC_Order $order ): void {
		$shipping_method = Helper::get_order_shipping_method( $order );
		if ( ! $shipping_method->is_oca() ) {
			return;
		}

		$oca_number = $shipping_method->get_oca_number();
		if ( ! $oca_number ) {
			Helper::add_error( __( 'Could not cancel the shipment, because the order has not been processed yet', 'wc-oca' ) );
			return;
		}

		if ( ! $this->sdk->is_valid() ) {
			Helper::add_error( __( 'There was an error canceling the shipment with OCA, please try again', 'wc-oca' ) );
			return;
		}

		$response = $this->sdk->cancel_order( $oca_number );

		if ( empty( $response ) ) {
			Helper::add_error( __( 'There was an error canceling the shipment with OCA, please try again', 'wc-oca' ) );
			return;
		}

		if ( isset( $response['error'] ) ) {
			Helper::add_error( $response['error'] );
		}

		// oca id 100 - Canceled | id 130 - Already canceled
		if ( ! in_array( $response['id'], array( '100', '130' ), true ) ) {
			Helper::add_error( $response['message'] );
			return;
		}

		do_action( 'wc_oca_before_order_cancel', $order );

		$shipping_method->cancel();

		$order->add_order_note( sprintf( __( 'OCA shipment for order %s has been canceled', 'wc-oca' ), $order->get_id() ) );
		Helper::add_success( sprintf( __( 'OCA shipment for order %s has been canceled', 'wc-oca' ), $order->get_id() ) );

		do_action( 'wc_oca_after_order_cancel', $order );
	}

	public function get_tracking_statuses( WC_Order $order ): array {
		if ( ! Helper::order_is_sent_with_oca( $order ) ) {
			return array();
		}

		$shipping_method = Helper::get_order_shipping_method( $order );
		$tracking_number = $shipping_method->get_tracking_number();
		if ( ! $tracking_number ) {
			return array();
		}

		$statuses = $this->sdk->get_tracking( $tracking_number );
		if ( isset( $statuses['error'] ) ) {
			return array();
		}

		return $statuses;
	}

	public function send_tracking_mail( WC_Order $order ): void {
		WC()->mailer(); // init mailer
		$mail = new TrackingMail();
		$mail->send_email( $order );
	}

	public function clear_method_cache(): void {
		CacheManager::clear_oca_shipping_cache();
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
