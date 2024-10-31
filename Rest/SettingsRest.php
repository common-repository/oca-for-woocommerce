<?php

namespace CRPlugins\Oca\Rest;

use CRPlugins\Oca\Helper\Helper;
use CRPlugins\Oca\Sdk\OcaSdk;
use CRPlugins\Oca\Settings\HealthChecker;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;


class SettingsRest implements RestRouteInterface {

	/**
	 * @var string
	 */
	private $routes_namespace;

	/**
	 * @var OcaSdk
	 */
	private $sdk;

	/**
	 * @var HealthChecker
	 */
	private $health_checker;

	public function __construct(
		string $routes_namespace,
		OcaSdk $sdk,
		HealthChecker $health_checker
	) {
		$this->routes_namespace = $routes_namespace;
		$this->sdk              = $sdk;
		$this->health_checker   = $health_checker;
	}

	public function register_routes(): void {
		register_rest_route(
			$this->routes_namespace,
			'/stores/mine',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_store' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_rest_route(
			$this->routes_namespace,
			'/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_rest_route(
			$this->routes_namespace,
			'/settings',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_settings' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_rest_route(
			$this->routes_namespace,
			'/settings',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_settings' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_rest_route(
			$this->routes_namespace,
			'/shipping-branches',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_shipping_branches' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_rest_route(
			$this->routes_namespace,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_health_status' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_rest_route(
			$this->routes_namespace,
			'/health/api',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'check_api_health' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		register_rest_route(
			$this->routes_namespace,
			'/reports',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'send_report' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	public function get_store( WP_REST_Request $request ): WP_REST_Response {
		$store = $this->sdk->get_store();
		if ( isset( $store['error'] ) ) {
			Helper::log_error( 'Error retrieving store from api: ' . wc_print_r( $store, true ) );
			return new WP_REST_Response( $store, 400 );
		}

		return new WP_REST_Response( $store );
	}

	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		$settings = Helper::get_all_settings();

		$settings['availableStatuses'] = array(
			array(
				'label' => 'Seleccionar',
				'value' => '0',
			),
		);
		$statuses                      = wc_get_order_statuses();
		foreach ( $statuses as $key => $status ) {
			$settings['availableStatuses'][] = array(
				'label' => $status,
				'value' => $key,
			);
		}

		return new WP_REST_Response( $settings );
	}

	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();
		try {
			Validator::key_exists( $body, 'apikey' );
			Validator::key_exists( $body, 'username' );
			Validator::key_exists( $body, 'password' );
			Validator::key_exists( $body, 'accountNumber' );
			Validator::key_exists( $body, 'cuit' );
			Validator::key_exists( $body, 'shippingCodes' );
			Validator::key_exists( $body, 'name' );
			Validator::key_exists( $body, 'storeName' );
			Validator::key_exists( $body, 'email' );
			Validator::key_exists( $body, 'street' );
			Validator::key_exists( $body, 'streetNumber' );
			Validator::key_exists( $body, 'floor' );
			Validator::key_exists( $body, 'apartment' );
			Validator::key_exists( $body, 'city' );
			Validator::key_exists( $body, 'state' );
			Validator::key_exists( $body, 'postcode' );
			Validator::key_exists( $body, 'observations' );
			Validator::key_exists( $body, 'timeFrame' );
			Validator::key_exists( $body, 'dispatch_days' );
			Validator::key_exists( $body, 'extra_dispatch_days' );
			Validator::key_exists( $body, 'shippingBranch' );
			Validator::key_exists( $body, 'status_processing' );
			Validator::key_exists( $body, 'enable_insurance' );
			Validator::key_exists( $body, 'notify_customer_order_processed' );
			Validator::key_exists( $body, 'tracking_mail_subject' );
			Validator::key_exists( $body, 'tracking_mail_body' );
			Validator::key_exists( $body, 'default_package_size' );
			Validator::key_exists( $body, 'proccess_always_one_package' );
			Validator::key_exists( $body, 'add_branch_name_to_method' );
			Validator::key_exists( $body, 'enable_branch_selector' );
			Validator::key_exists( $body, 'price_multiplier' );
			Validator::key_exists( $body, 'free_shipping_extra_name' );
			Validator::key_exists( $body, 'extra_delivery_days' );
			Validator::key_exists( $body, 'round_costs' );
			Validator::key_exists( $body, 'free_shipping_with_coupon' );
			Validator::key_exists( $body, 'label_delete_cron_time' );
			Validator::key_exists( $body, 'debug' );
		} catch ( \Throwable $th ) {
			return new WP_REST_Response( array( 'error' => $th->getMessage() ), 400 );
		}

		$valid_options = array(
			'apikey',
			'username',
			'password',
			'accountNumber',
			'cuit',
			'shippingCodes',
			'name',
			'storeName',
			'email',
			'street',
			'streetNumber',
			'floor',
			'apartment',
			'city',
			'state',
			'postcode',
			'observations',
			'timeFrame',
			'dispatch_days',
			'extra_dispatch_days',
			'shippingBranch',
			'status_processing',
			'enable_insurance',
			'notify_customer_order_processed',
			'tracking_mail_subject',
			'tracking_mail_body',
			'default_package_size',
			'proccess_always_one_package',
			'add_branch_name_to_method',
			'enable_branch_selector',
			'price_multiplier',
			'free_shipping_extra_name',
			'extra_delivery_days',
			'round_costs',
			'free_shipping_with_coupon',
			'label_delete_cron_time',
			'debug',
		);

		Helper::save_option( 'settings_version', time() );

		foreach ( $valid_options as $option_key ) {
			Helper::save_option( $option_key, $body[ $option_key ] );
		}

		return new WP_REST_Response( null, 204 );
	}

	public function delete_settings( WP_REST_Request $request ): WP_REST_Response {
		$settings = Helper::get_all_settings();

		foreach ( array_keys( $settings ) as $key ) {
			Helper::delete_option( $key );
		}

		return new WP_REST_Response( null, 204 );
	}

	public function get_shipping_branches( WP_REST_Request $request ): WP_REST_Response {
		$query = $request->get_query_params();
		try {
			Validator::key_exists( $query, 'postcode' );
			Validator::numeric( $query['postcode'], 'El código postal debe ser numérico' );
		} catch ( \Throwable $th ) {
			return new WP_REST_Response( array( 'error' => $th->getMessage() ), 400 );
		}

		$branches = $this->sdk->get_shipping_branches_from( $query['postcode'] );

		return new WP_REST_Response( $branches );
	}

	public function get_health_status( WP_REST_Request $request ): WP_REST_Response {
		$products       = $this->health_checker->get_invalid_products();
		$shipping_zones = $this->health_checker->get_invalid_shipping_zones();
		$misc           = $this->health_checker->get_misc_status();

		return new WP_REST_Response(
			array(
				'products'       => $products,
				'shipping_zones' => $shipping_zones,
				'misc'           => $misc,
			)
		);
	}

	public function check_api_health( WP_REST_Request $request ): WP_REST_Response {
		$quotes_api    = $this->health_checker->is_quote_api_valid();
		$processor_api = false;

		if ( $quotes_api ) {
			$processor_api = $this->health_checker->is_processor_api_valid();
		}

		return new WP_REST_Response(
			array(
				'quotes_api'    => $quotes_api,
				'processor_api' => $processor_api,
			)
		);
	}

	public function send_report( WP_REST_Request $request ): WP_REST_Response {
		$uploads_dir = wp_get_upload_dir()['basedir'];

		// Logs.
		$logs_dir       = $uploads_dir . '/wc-logs';
		$available_logs = scandir( $logs_dir );
		$available_logs = array_filter(
			$available_logs,
			function ( string $log ): bool {
				return 'woocommerce-oca' === strtolower( substr( $log, 0, 15 ) );
			}
		);
		if ( ! $available_logs ) {
			return new WP_REST_Response( array( 'error' => 'No se encontraron logs para enviar' ), 400 );
		}
		$last_report          = array_pop( $available_logs );
		$previous_last_report = null;
		if ( $available_logs ) {
			$previous_last_report = array_pop( $available_logs );
		}

		// Settings.
		$settings    = Helper::get_all_settings();
		$uploads_dir = Helper::get_uploads_dir();
		if ( ! file_exists( $uploads_dir ) ) {
			mkdir( $uploads_dir, 0755 ); // phpcs:ignore
		}
		$settings_file = $uploads_dir . '/settings.txt';
		file_put_contents( $settings_file, json_encode( $settings ) ); // phpcs:ignore

		$response = $this->sdk->send_report( $logs_dir . '/' . $last_report );
		if ( empty( $response ) || isset( $response['error'] ) ) {
			return new WP_REST_Response( array( 'error' => 'No se pudo enviar el reporte' ), 400 );
		}

		$response = $this->sdk->send_report( $logs_dir . '/' . $previous_last_report );
		if ( empty( $response ) || isset( $response['error'] ) ) {
			return new WP_REST_Response( array( 'error' => 'No se pudo enviar el reporte' ), 400 );
		}

		$response = $this->sdk->send_report( $settings_file );
		if ( empty( $response ) || isset( $response['error'] ) ) {
			return new WP_REST_Response( array( 'error' => 'No se pudo enviar el reporte' ), 400 );
		}

		Helper::log_info( 'Logging report has been sent' );

		try {
			unlink( $settings_file ); // phpcs:ignore
		} catch ( \Throwable $th ) {
			Helper::log_warning( 'Could not delete settings file from report' );
		}

		return new WP_REST_Response( null, 204 );
	}
}
