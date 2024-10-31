<?php

namespace CRPlugins\Oca\Orders;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use CRPlugins\Oca\Helper\Helper;
use CRPlugins\Oca\ValueObjects\ShippingMethod;
use WC_Coupon;
use WC_Order;
use WP_Post;

defined( 'ABSPATH' ) || exit;

class Metabox {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'create' ) );
	}

	public function create(): void {
		if ( class_exists( CustomOrdersTableController::class ) ) {
			$screen = wc_get_container()
				->get( CustomOrdersTableController::class )
				->custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

			add_meta_box(
				'oca_metabox',
				'OCA',
				array( $this, 'content' ),
				$screen,
				'side',
				'high'
			);
		} else {
			$order_types = wc_get_order_types( 'order-meta-boxes' );
			foreach ( $order_types as $order_type ) {
				add_meta_box(
					'oca_metabox',
					'OCA',
					array( $this, 'content' ),
					$order_type,
					'side',
					'default'
				);
			}
		}
	}

	/**
	 * @param WP_Post|WC_Order $post_or_order_object
	 */
	public function content( $post_or_order_object ): void {
		$order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
		if ( ! $order ) {
			return;
		}

		/** @var WC_Order $order */

		wp_enqueue_style( 'wc-oca-general-css' );
		wp_enqueue_style( 'wc-oca-orders-css' );

		$shipping_method = Helper::get_order_shipping_method( $order );

		wp_enqueue_script( 'wc-oca-orders-js' );
		wp_localize_script(
			'wc-oca-orders-js',
			'wc_oca_settings',
			array(
				'order_id'            => $order->get_id(),
				'oca_number'          => $shipping_method->is_oca() ? $shipping_method->get_oca_number() : '',
				'oca_tracking_number' => $shipping_method->is_oca() ? $shipping_method->get_tracking_number() : '',
			)
		);
		wp_localize_script(
			'wc-oca-orders-js',
			'wc_oca_translation_texts',
			array(
				'generic_error_try_again' => esc_html__( 'There was an error, please try again', 'wc-oca' ),
				'invalid_package_number'  => esc_html__( 'Input a valid package number', 'wc-oca' ),
				'packages_qty_updated'    => esc_html__( 'Packages quantity updated', 'wc-oca' ),
				'mail_sent'               => esc_html__( 'Mail sent', 'wc-oca' ),
				'loading'                 => esc_html__( 'Loading...', 'wc-oca' ),
				'track_order'             => esc_html__( 'Track order', 'wc-oca' ),
				'view_shipping_label_pdf' => esc_html__( 'View shipping label', 'wc-oca' ),
				'view_shipping_label_zpl' => esc_html__( 'Download shipping label ZPL', 'wc-oca' ),
				'process_order_now'       => esc_html__( 'Process order now', 'wc-oca' ),
				'cancel_order'            => esc_html__( 'Cancel shipment', 'wc-oca' ),
				'no_quote_found'          => esc_html__( 'No quote was found for this order', 'wc-oca' ),
				'new_quote_alert'         => esc_html__( 'A quote for this order has been found. Do you want to proceed? This will override the current order\'s shipping method and the additional cost will not be charged automatically to the customer, make sure to cover it by yourself. Name: {{shipping_name}} | Price: {{shipping_price}}', 'wc-oca' ),
			)
		);

		$status = 'unprocessed';
		if ( $shipping_method->is_empty() || ! $shipping_method->is_oca() ) {
			$status = 'not-oca';
		} elseif ( $shipping_method->is_canceled() ) {
			$status = 'canceled';
		} elseif ( $shipping_method->is_processed() ) {
			$status = 'processed';
		}

		switch ( $status ) {
			case 'unprocessed':
			default:
				$this->show_order_not_processed();
				$this->show_hidden_cost( $shipping_method );
				$this->show_packages_number( $shipping_method );
				$this->show_process_button();
				break;

			case 'not-oca':
				$this->show_not_using_oca( $order );
				break;

			case 'canceled':
				$this->show_order_canceled();
				$this->show_hidden_cost( $shipping_method );
				$this->show_packages_number( $shipping_method );
				$this->show_process_button();
				break;

			case 'processed':
				$this->show_order_processed( $shipping_method );
				$this->show_hidden_cost( $shipping_method );
				$this->show_label_actions();
				break;
		}
	}

	protected function show_not_using_oca( WC_Order $order ): void {
		$hide_shipping_cost = $this->should_hide_cost( $order );

		$this->show_row( __( 'This order is not using OCA as shipping method', 'wc-oca' ) );

		$content  = '<input type="checkbox" style="margin:0 5px 0 0;" id="hide_shipping_cost" ' . esc_attr( $hide_shipping_cost ? 'checked' : '' ) . ' />';
		$content .= '<label for="hide_shipping_cost" style="cursor:pointer;">' . esc_html__( 'Hide shipping cost', 'wc-oca' ) . '</label>';
		$this->show_row( $content );

		echo '<a class="oca-button block" target="_blank" id="set-method-to-oca">' . esc_html__( 'Set OCA as shipping method', 'wc-oca' ) . '</a>';
	}

	protected function show_order_processed( ShippingMethod $shipping_method ): void {
		$tracking_number = $shipping_method->get_tracking_number();

		$this->show_row( sprintf( __( 'The order has been processed, tracking number: <strong>%s</strong>', 'wc-oca' ), esc_html( $tracking_number ) ) );
	}

	protected function show_order_canceled(): void {
		$this->show_row( __( 'The shipment has been canceled', 'wc-oca' ) );
	}

	protected function show_order_not_processed(): void {
		$this->show_row( __( 'The order is not processed yet', 'wc-oca' ) );

		$config_status = Helper::get_option( 'status_processing', '' );
		if ( ! empty( $config_status ) ) {
			$statuses = wc_get_order_statuses();
			$this->show_row(
				sprintf(
					__( 'The order will be processed when its status is <strong>%s</strong>', 'wc-oca' ),
					esc_html( $statuses[ $config_status ] )
				)
			);
		}
	}

	protected function show_process_button(): void {
		printf(
			'<a class="oca-button block" target="_blank" id="oca-process-order">%s</a>',
			esc_html__( 'Process order now', 'wc-oca' )
		);
	}

	protected function show_hidden_cost( ShippingMethod $shipping_method ): void {
		$hidden_price = $shipping_method->get_hidden_price();
		if ( $hidden_price ) {
			$this->show_row(
				sprintf(
					__( 'The shipping has a cost of <strong>$%s</strong> (hidden to the customer)', 'wc-oca' ),
					esc_html( $hidden_price )
				)
			);
		}
	}

	protected function show_label_actions(): void {
		echo '<a class="oca-button block" id="oca-view-shipping-label-pdf-a4">' . esc_html__( 'View shipping label PDF - A4', 'wc-oca' ) . '</a>';
		echo '<a class="oca-button block" id="oca-view-shipping-label-pdf-10x15">' . esc_html__( 'View shipping label PDF - 10x15', 'wc-oca' ) . '</a>';
		echo '<a class="oca-button block" id="oca-download-shipping-label-pdf-a4">' . esc_html__( 'Download shipping label PDF - A4', 'wc-oca' ) . '</a>';
		echo '<a class="oca-button block" id="oca-download-shipping-label-pdf-10x15">' . esc_html__( 'Download shipping label PDF - 10x15', 'wc-oca' ) . '</a>';
		echo '<a class="oca-button block" id="oca-download-shipping-label-zpl">' . esc_html__( 'Download shipping label ZPL', 'wc-oca' ) . '</a>';
		echo '<a class="oca-button block" id="oca-view-tracking">' . esc_html__( 'Track order', 'wc-oca' ) . '</a>';
		echo '<a class="oca-button block" id="oca-send-tracking-mail">' . esc_html__( 'Resend tracking mail', 'wc-oca' ) . '</a>';
		echo '<a class="oca-red-button block" id="oca-cancel-order">' . esc_html__( 'Cancel order', 'wc-oca' ) . '</a>';
	}

	protected function show_packages_number( ShippingMethod $shipping_method ): void {
		$packages = $shipping_method->get_packages_quantity();

		$content  = __( 'Number of packages', 'wc-oca' );
		$content .= '<div class="oca-packages-wrapper">';
		$content .= sprintf( '<input type="number" step="1" id="oca-packages-quantity" value="%s" />', esc_attr( $packages ) );
		$content .= sprintf( '<a href="#" class="oca-button save-button edit-packages-button">%s</a>', esc_html__( 'Save', 'wc-oca' ) );
		$content .= '</div>';

		$this->show_row( $content );
	}

	protected function should_hide_cost( WC_Order $order ): bool {
		$hide_shipping_cost = false;
		$coupons            = $order->get_coupons();

		foreach ( $coupons as $coupon ) {
			$wc_coupon = new WC_Coupon( $coupon->get_code() );
			if ( $wc_coupon->get_free_shipping() ) {
				$hide_shipping_cost = true;
				break;
			}
		}

		return $hide_shipping_cost;
	}

	protected function show_row( string $content ): void {
		echo '<div class="oca-metabox-row">';
		printf(
			wp_kses(
				$content,
				array(
					'strong' => array(),
					'input'  => array(
						'type'    => array(),
						'step'    => array(),
						'id'      => array(),
						'value'   => array(),
						'style'   => array(),
						'checked' => array(),
					),
					'a'      => array(
						'href'  => array(),
						'class' => array(),
					),
					'label'  => array(
						'for'   => array(),
						'style' => array(),
					),
					'div'    => array( 'class' => array() ),
				)
			)
		);
		echo '</div>';
	}
}
