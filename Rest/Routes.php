<?php

namespace CRPlugins\Oca\Rest;

use CRPlugins\Oca\Orders\OrdersProcessor;
use CRPlugins\Oca\Orders\Quoter;
use CRPlugins\Oca\Sdk\OcaSdk;
use CRPlugins\Oca\Settings\HealthChecker;
use CRPlugins\Oca\ShippingLabels\LabelsProcessorInterface;
use CRPlugins\Oca\ShippingMethod\ShippingBranchesManager;

defined( 'ABSPATH' ) || exit;

class Routes {
	private const NAMESPACE_V1 = 'oca-for-woocommerce/v1';

	public function __construct(
		ShippingBranchesManager $manager,
		LabelsProcessorInterface $pdf_a4_processor,
		LabelsProcessorInterface $pdf_10x15_processor,
		LabelsProcessorInterface $zpl_processor,
		OrdersProcessor $orders_processor,
		Quoter $quoter,
		OcaSdk $sdk,
		HealthChecker $health_checker
	) {
		add_filter( 'woocommerce_is_rest_api_request', array( $this, 'rest_modifier' ) );

		$routers = array(
			new ShippingMethodRest( self::NAMESPACE_V1, $manager ),
			new OrdersRest(
				self::NAMESPACE_V1,
				$pdf_a4_processor,
				$pdf_10x15_processor,
				$zpl_processor,
				$orders_processor,
				$quoter
			),
			new SettingsRest( self::NAMESPACE_V1, $sdk, $health_checker ),
		);

		foreach ( $routers as $router ) {
			add_action( 'rest_api_init', array( $router, 'register_routes' ) );
		}
	}

	/**
	 * @psalm-suppress PossiblyUndefinedArrayOffset
	 */
	public function rest_modifier( bool $is_rest_api_request ): bool {
		if ( false === strpos( wp_unslash( $_SERVER['REQUEST_URI'] ), self::NAMESPACE_V1 ) ) { // phpcs:ignore
			return $is_rest_api_request;
		}

		return false;
	}
}
