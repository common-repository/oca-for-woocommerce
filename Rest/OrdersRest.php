<?php

namespace CRPlugins\Oca\Rest;

use CRPlugins\Oca\Helper\Helper;
use CRPlugins\Oca\Orders\OrdersProcessor;
use CRPlugins\Oca\Orders\Quoter;
use CRPlugins\Oca\ShippingLabels\LabelFilesManager;
use CRPlugins\Oca\ShippingLabels\LabelsProcessorInterface;
use DateTime;
use Exception;
use WC_Order;
use WC_Order_Item_Shipping;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class OrdersRest implements RestRouteInterface {

	/**
	 * @var LabelsProcessorInterface
	 */
	private $pdf_a4_processor;

	/**
	 * @var LabelsProcessorInterface
	 */
	private $pdf_10x15_processor;

	/**
	 * @var LabelsProcessorInterface
	 */
	private $zpl_processor;

	/**
	 * @var OrdersProcessor
	 */
	private $orders_processor;

	/**
	 * @var Quoter
	 */
	private $quoter;

	/**
	 * @var string
	 */
	private $routes_namespace;

	public function __construct(
		string $routes_namespace,
		LabelsProcessorInterface $pdf_a4_processor,
		LabelsProcessorInterface $pdf_10x15_processor,
		LabelsProcessorInterface $zpl_processor,
		OrdersProcessor $orders_processor,
		Quoter $quoter
	) {
		$this->routes_namespace    = $routes_namespace;
		$this->pdf_a4_processor    = $pdf_a4_processor;
		$this->pdf_10x15_processor = $pdf_10x15_processor;
		$this->zpl_processor       = $zpl_processor;
		$this->orders_processor    = $orders_processor;
		$this->quoter              = $quoter;
	}

	public function register_routes(): void {
		// Labels
		register_rest_route(
			$this->routes_namespace,
			'/orders/(?P<order_id>\d+)/shipping-labels',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_shipping_label' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_rest_route(
			$this->routes_namespace,
			'/orders/shipping-labels',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_bulk_shipping_labels' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_rest_route(
			$this->routes_namespace,
			'/orders/shipping-labels/download',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_download_link_bulk_shipping_labels' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		// Tracking
		register_rest_route(
			$this->routes_namespace,
			'/orders/(?P<order_id>\d+)/tracking',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_tracking' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		// Order
		register_rest_route(
			$this->routes_namespace,
			'/orders/(?P<order_id>\d+)/quotes',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_quotes' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_rest_route(
			$this->routes_namespace,
			'/orders/(?P<order_id>\d+)/set-oca',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_oca_as_shipping_method' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_rest_route(
			$this->routes_namespace,
			'/orders/(?P<order_id>\d+)/process',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'process_order' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_rest_route(
			$this->routes_namespace,
			'/orders/(?P<order_id>\d+)/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cancel_order' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_rest_route(
			$this->routes_namespace,
			'/orders/(?P<order_id>\d+)/packages',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_packages' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_rest_route(
			$this->routes_namespace,
			'/orders/(?P<order_id>\d+)/mails/tracking/send',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'send_tracking_mail' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	public function get_shipping_label( WP_REST_Request $request ): WP_REST_Response {
		$query = $request->get_query_params();
		try {
			Validator::key_exists( $query, 'format' );
			Validator::one_of( $query['format'], array( 'pdf', 'zpl' ), 'El formato dado no es válido' );
		} catch ( \Throwable $th ) {
			return new WP_REST_Response( array( 'error' => $th->getMessage() ), 400 );
		}

		if ( 'pdf' === $query['format'] && empty( $query['size'] ) ) {
			return new WP_REST_Response( array( 'error' => 'No size provided' ), 400 );
		}

		/** @var array{order_id: string} */
		$url_params = $request->get_url_params();
		$order      = wc_get_order( (int) $url_params['order_id'] );
		if ( ! $order ) {
			return new WP_REST_Response( array( 'error' => 'Invalid order id' ), 400 );
		}

		$processor = $this->get_labels_processor_for_request( $query );

		$processor->create_label( $order );
		$content = $processor->get_label_content( $order );

		if ( 'pdf' === $query['format'] ) {
			$content = base64_encode( $content );
		}

		return new WP_REST_Response( array( 'content' => $content ) );
	}

	public function get_bulk_shipping_labels( WP_REST_Request $request ): WP_REST_Response {
		$query = $request->get_query_params();
		try {
			Validator::key_exists( $query, 'orders' );
			Validator::key_exists( $query, 'format' );
			Validator::not_empty( $query['orders'], 'Debes seleccionar ordenes' );
			Validator::one_of( $query['format'], array( 'pdf', 'zpl' ), 'El formato dado no es válido' );
		} catch ( \Throwable $th ) {
			return new WP_REST_Response( array( 'error' => $th->getMessage() ), 400 );
		}

		if ( 'pdf' === $query['format'] && empty( $query['size'] ) ) {
			return new WP_REST_Response( array( 'error' => 'No size provided' ), 400 );
		}

		$processor = $this->get_labels_processor_for_request( $query );
		$order_ids = explode( ',', $query['orders'] );
		$labels    = array();

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			if ( ! Helper::order_is_sent_with_oca( $order ) ) {
				continue;
			}

			$processor->create_label( $order );
			$path     = $processor->get_label_path( $order );
			$filename = basename( $path );

			if ( ! file_exists( $path ) ) {
				continue;
			}

			$labels[] = $filename;
		}

		if ( ! $labels ) {
			return new WP_REST_Response( array( 'error' => 'Could not retrieve shipping labels' ), 400 );
		}

		try {
			$content = LabelFilesManager::create_merged_pdf( $labels );
		} catch ( Exception $e ) {
			Helper::log_error( 'Could not create merged PDF: ' . $e->getMessage() );
			return new WP_REST_Response( array( 'error' => 'Could not create merged PDF' ), 400 );
		}

		return new WP_REST_Response( array( 'content' => base64_encode( $content ) ) );
	}

	public function get_download_link_bulk_shipping_labels( WP_REST_Request $request ): WP_REST_Response {
		$query = $request->get_query_params();
		try {
			Validator::key_exists( $query, 'orders' );
			Validator::key_exists( $query, 'format' );
			Validator::not_empty( $query['orders'], 'Debes seleccionar ordenes' );
			Validator::one_of( $query['format'], array( 'pdf', 'zpl' ), 'El formato dado no es válido' );
		} catch ( \Throwable $th ) {
			return new WP_REST_Response( array( 'error' => $th->getMessage() ), 400 );
		}

		if ( 'pdf' === $query['format'] && empty( $query['size'] ) ) {
			return new WP_REST_Response( array( 'error' => 'No size provided' ), 400 );
		}

		$processor = $this->get_labels_processor_for_request( $query );
		$order_ids = explode( ',', $query['orders'] );
		$labels    = array();

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			if ( ! Helper::order_is_sent_with_oca( $order ) ) {
				continue;
			}

			$processor->create_label( $order );
			$label = $processor->get_label_path( $order );

			$labels[] = $label;
		}

		try {
			$now      = new DateTime();
			$zip_name = sprintf( __( '%1$s-labels-%2$s', 'wc-oca' ), $query['format'], $now->format( 'd-m-Y' ) );
			LabelFilesManager::create_zip( $zip_name, $labels );
		} catch ( Exception $e ) {
			Helper::log_error( 'Could not create compressed ZIP: ' . $e->getMessage() );
			return new WP_REST_Response( array( 'error' => 'Could not create compressed ZIP' ), 400 );
		}

		$zip_url = LabelFilesManager::get_zip_url( $zip_name );
		if ( ! $zip_url ) {
			return new WP_REST_Response( array( 'error' => 'Zip file could not be created' ), 400 );
		}

		$url = $zip_url . '?version=' . time();
		return new WP_REST_Response( array( 'url' => $url ) );
	}

	public function get_tracking( WP_REST_Request $request ): WP_REST_Response {
		/** @var array{order_id: string} */
		$url_params = $request->get_url_params();
		$order      = wc_get_order( (int) $url_params['order_id'] );
		if ( ! $order ) {
			return new WP_REST_Response( array( 'error' => 'Invalid order id' ), 400 );
		}

		$statuses = $this->orders_processor->get_tracking_statuses( $order );

		return new WP_REST_Response( $statuses );
	}

	public function get_quotes( WP_REST_Request $request ): WP_REST_Response {
		/** @var array{order_id: string} */
		$url_params = $request->get_url_params();
		$order      = wc_get_order( (int) $url_params['order_id'] );
		if ( ! $order ) {
			return new WP_REST_Response( array( 'error' => 'Invalid order id' ), 400 );
		}

		$quotes         = $this->quoter->quote_order( $order );
		$shipping_codes = Helper::get_seller_settings()->get_shipping_codes();

		$formatted_quotes = array();
		foreach ( $quotes as $code_id => $quote ) {
			$shipping_code  = $shipping_codes->get_by_id( $code_id );
			$shipping_label = $shipping_code->get_name();

			$has_days_placeholder = Helper::str_contains( $shipping_label, '{{oca_tiempo_entrega}}' );
			if ( $has_days_placeholder && $quote['days'] ) {
				$extra_delivery_days = absint( (int) Helper::get_option( 'extra_delivery_days', 0 ) );
				$quote['days']       = $quote['days'] + $extra_delivery_days;
				$shipping_label      = str_replace( '{{oca_tiempo_entrega}}', $quote['days'], $shipping_label );
			}

			$$shipping_label = str_replace( '{{oca_precio_envio}}', '', $shipping_label );

			$formatted_quotes[] = array(
				'id'    => $shipping_code->get_id(),
				'type'  => $shipping_code->get_type(),
				'name'  => $shipping_label,
				'price' => $quote['price'],
			);
			break;
		}

		return new WP_REST_Response( ! empty( $formatted_quotes ) ? current( $formatted_quotes ) : array() );
	}

	public function set_oca_as_shipping_method( WP_REST_Request $request ): WP_REST_Response {
		/** @var array{order_id: string} */
		$url_params = $request->get_url_params();
		$order      = wc_get_order( (int) $url_params['order_id'] );
		if ( ! $order ) {
			return new WP_REST_Response( array( 'error' => 'Invalid order id' ), 400 );
		}
		/** @var WC_Order $order */

		$response = $request->get_json_params();
		try {
			Validator::key_exists( $response, 'id' );
			Validator::key_exists( $response, 'name' );
			Validator::key_exists( $response, 'type' );
			Validator::key_exists( $response, 'price' );
			Validator::key_exists( $response, 'hiddenPrice' );
			Validator::string( $response['id'], 'El id debe ser de tipo texto' );
			Validator::string( $response['name'], 'El nombre debe ser de tipo texto' );
			Validator::string( $response['type'], 'El type debe ser de tipo texto' );
			Validator::float( $response['price'], 'El precio debe ser de tipo float' );
			Validator::boolean( $response['hiddenPrice'], 'El hiddenPrice debe ser de tipo booleano' );
		} catch ( \Throwable $th ) {
			return new WP_REST_Response( array( 'error' => $th->getMessage() ), 400 );
		}

		$items = $order->get_items( 'shipping' );
		foreach ( $items as $item_id => $item ) {
			$order->remove_item( $item_id );
		}

		$item = new WC_Order_Item_Shipping();
		$item->set_props(
			array(
				'method_title' => $response['name'],
				'method_id'    => 'oca',
				'instance_id'  => sprintf( 'oca:1234|door|%s', $response['id'] ),
				'total'        => $response['hiddenPrice'] ? 0 : wc_format_decimal( $response['price'] ),
				'taxes'        => array(),
			)
		);

		$code  = Helper::get_seller_settings()->get_shipping_codes()->get_by_id( $response['id'] );
		$items = Helper::get_items_from_order( $order );

		$meta = array(
			'shipping_code'     => array(
				'code' => $code->get_id(),
				'name' => $code->get_name(),
				'type' => $code->get_type(),
			),
			'packages_quantity' => $items->get_totals()->get_quantity(),
		);

		if ( $response['hiddenPrice'] ) {
			$meta['hidden_price'] = wc_format_decimal( $response['price'] );
		}

		foreach ( $meta as $key => $value ) {
			$item->add_meta_data( $key, $value, true );
		}
		$order->add_item( $item );

		$order->calculate_totals();
		$order->save();

		return new WP_REST_Response( null, 204 );
	}

	public function process_order( WP_REST_Request $request ): WP_REST_Response {
		/** @var array{order_id: string} */
		$url_params = $request->get_url_params();
		$order      = wc_get_order( (int) $url_params['order_id'] );
		if ( ! $order ) {
			return new WP_REST_Response( array( 'error' => 'Invalid order id' ), 400 );
		}
		/** @var WC_Order $order */

		$body = $request->get_json_params();
		try {
			Validator::key_exists( $body, 'force' );
			Validator::boolean( $body['force'], 'Force debe ser booleano' );
		} catch ( \Throwable $th ) {
			return new WP_REST_Response( array( 'error' => $th->getMessage() ), 400 );
		}

		$this->orders_processor->process_order( $order, true );
		return new WP_REST_Response( null, 204 );
	}

	public function cancel_order( WP_REST_Request $request ): WP_REST_Response {
		/** @var array{order_id: string} */
		$url_params = $request->get_url_params();
		$order      = wc_get_order( (int) $url_params['order_id'] );
		if ( ! $order ) {
			return new WP_REST_Response( array( 'error' => 'Invalid order id' ), 400 );
		}
		/** @var WC_Order $order */

		$this->orders_processor->cancel_order( $order );
		return new WP_REST_Response( null, 204 );
	}

	public function update_packages( WP_REST_Request $request ): WP_REST_Response {
		/** @var array{order_id: string} */
		$url_params = $request->get_url_params();
		$order      = wc_get_order( (int) $url_params['order_id'] );
		if ( ! $order ) {
			return new WP_REST_Response( array( 'error' => 'Invalid order id' ), 400 );
		}
		/** @var WC_Order $order */

		$body = $request->get_json_params();
		try {
			Validator::key_exists( $body, 'quantity' );
			Validator::integer( $body['quantity'], 'El número de bultos debe ser numérico' );
		} catch ( \Throwable $th ) {
			return new WP_REST_Response( array( 'error' => $th->getMessage() ), 400 );
		}

		$shipping_method = Helper::get_order_shipping_method( $order );

		$old_quantity = $shipping_method->get_packages_quantity();
		$new_quantity = $body['quantity'];
		if ( $old_quantity === $new_quantity ) {
			return new WP_REST_Response( null, 204 );
		}

		$shipping_method->set_packages_quantity( $new_quantity );

		$order->add_order_note( sprintf( __( 'OCA - Number of packages to be sent has been modified from %1$d to %2$d', 'wc-oca' ), $old_quantity, $new_quantity ) );
		Helper::log_info( sprintf( __( 'Order %1$d - Number of packages to be sent has been modified from %2$d to %3$d', 'wc-oca' ), $order->get_id(), $old_quantity, $new_quantity ) );

		return new WP_REST_Response( null, 204 );
	}

	public function send_tracking_mail( WP_REST_Request $request ): WP_REST_Response {
		/** @var array{order_id: string} */
		$url_params = $request->get_url_params();
		$order      = wc_get_order( (int) $url_params['order_id'] );
		if ( ! $order ) {
			return new WP_REST_Response( array( 'error' => 'Invalid order id' ), 400 );
		}
		/** @var WC_Order $order */

		$this->orders_processor->send_tracking_mail( $order );

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * @param array<string,string> $request
	 */
	protected function get_labels_processor_for_request( array $request ): LabelsProcessorInterface {
		$processor = $this->pdf_a4_processor;

		if ( 'pdf' === $request['format'] ) {
			if ( 'A4' === $request['size'] ) {
				$processor = $this->pdf_a4_processor;
			} elseif ( '10x15' === $request['size'] ) {
				$processor = $this->pdf_10x15_processor;
			}
		} else {
			$processor = $this->zpl_processor;
		}

		return $processor;
	}
}
