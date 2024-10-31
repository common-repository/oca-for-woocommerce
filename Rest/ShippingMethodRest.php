<?php

namespace CRPlugins\Oca\Rest;

use CRPlugins\Oca\ShippingMethod\ShippingBranchesManager;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class ShippingMethodRest implements RestRouteInterface {

	/**
	 * @var ShippingBranchesManager
	 */
	private $manager;

	/**
	 * @var string
	 */
	private $routes_namespace;

	public function __construct(
		string $routes_namespace,
		ShippingBranchesManager $manager
	) {
		$this->routes_namespace = $routes_namespace;
		$this->manager          = $manager;
	}

	public function register_routes(): void {
		register_rest_route(
			$this->routes_namespace,
			'/shipping-branch',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'set_shipping_branch' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function set_shipping_branch( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();
		if ( empty( $body ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid body' ), 400 );
		}

		$shipping_branch = $this->manager->branch_response_to_object( $body );

		$this->manager->set_selected_branch( $shipping_branch );

		return new WP_REST_Response( null, 204 );
	}
}
