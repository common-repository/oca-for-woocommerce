<?php

namespace CRPlugins\Oca\Orders;

use CRPlugins\Oca\Helper\Helper;
use CRPlugins\Oca\Orders\OrdersProcessor;
use WC_Order;

defined( 'ABSPATH' ) || exit;

class OrdersList {
	/**
	 * @var OrdersProcessor
	 */
	private $orders_processor;

	public function __construct( OrdersProcessor $orders_processor ) {
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_tracking_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'tracking_column_content' ), 10, 1 );

		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_tracking_column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'tracking_column_content' ), 10, 2 );

		$this->orders_processor = $orders_processor;
	}

	/**
	 * @param array<string,string> $actions
	 * @return array<string,string>
	 */
	public function add_bulk_actions( array $actions ): array {

		wp_enqueue_style( 'wc-oca-general-css' );
		wp_enqueue_script( 'wc-oca-orders-list-js' );
		wp_localize_script(
			'wc-oca-orders-list-js',
			'wc_oca_translation_texts',
			array(
				'generic_error_try_again' => esc_html__( 'There was an error, please try again', 'wc-oca' ),
				'loading'                 => esc_html__( 'Loading...', 'wc-oca' ),
			)
		);

		$actions['wc_oca_bulk_process'] = esc_html__( 'OCA - Process orders', 'wc-oca' );

		// Zlib is needed to merge pdfs
		if ( extension_loaded( 'zlib' ) ) {
			$actions['wc_oca_view_pdf_labels_A4']    = esc_html__( 'OCA - View shipping labels in PDF - A4', 'wc-oca' );
			$actions['wc_oca_view_pdf_labels_10x15'] = esc_html__( 'OCA - View shipping labels in PDF - 10x15', 'wc-oca' );
		}

		// Zip needed for zipping files
		if ( extension_loaded( 'zip' ) ) {
			$actions['wc_oca_bulk_pdf_labels_download_A4']    = esc_html__( 'OCA - Download PDF shipping labels - A4', 'wc-oca' );
			$actions['wc_oca_bulk_pdf_labels_download_10x15'] = esc_html__( 'OCA - Download PDF shipping labels - 10x15', 'wc-oca' );
			$actions['wc_oca_bulk_zpl_labels_download']       = esc_html__( 'OCA - Download ZPL shipping labels', 'wc-oca' );
		}

		return $actions;
	}

	/**
	 * @param string[] $ids
	 */
	public function handle_bulk_actions( string $redirect_to, string $action, array $ids ): string {
		if ( 'wc_oca_bulk_process' === $action ) {
			$this->handle_bulk_process( $ids );
		}

		return esc_url_raw( $redirect_to );
	}

	/**
	 * @param array<string,string> $columns
	 * @return array<string,string>
	 */
	public function add_tracking_column( array $columns ): array {
		$columns['oca_tracking_number'] = __( 'OCA Tracking number', 'wc-oca' );
		return $columns;
	}

	public function tracking_column_content( string $column, ?WC_Order $order = null ): void {
		global $post;

		if ( 'oca_tracking_number' !== $column ) {
			return;
		}

		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			if ( ! \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$order = wc_get_order( $post->ID );
			}
		} else {
			$order = wc_get_order( $post->ID );
		}

		if ( ! $order ) {
			return;
		}

		echo esc_html( Helper::get_order_shipping_method( $order )->get_tracking_number() );
	}

	/**
	 * @param string[] $order_ids
	 */
	public function handle_bulk_process( array $order_ids ): void {
		foreach ( $order_ids as $order_id ) {
			$this->orders_processor->process_order( wc_get_order( $order_id ), true );
		}
	}
}
