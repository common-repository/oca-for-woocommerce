<?php

namespace CRPlugins\Oca\ShippingMethod;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry;
use CRPlugins\Oca\Helper\Helper;
use CRPlugins\Oca\ValueObjects\ShippingBranch;
use CRPlugins_Oca;

class CheckoutIntegration implements IntegrationInterface {

	private const CALLBACKS_URI = 'crplugins-oca-for-woocommerce-callbacks';

	/**
	 * @var ShippingBranchesManager
	 */
	private $branches_manager;

	public function __construct( ShippingBranchesManager $branches_manager ) {
		$this->branches_manager = $branches_manager;

		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_blocks' ) );
	}

	public function register_blocks(): void {
		add_action(
			'woocommerce_blocks_mini-cart_block_registration',
			function ( IntegrationRegistry $integration_registry ): void {
				$integration_registry->register( $this );
			}
		);
		add_action(
			'woocommerce_blocks_cart_block_registration',
			function ( IntegrationRegistry $integration_registry ): void {
				$integration_registry->register( $this );
			}
		);
		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function ( IntegrationRegistry $integration_registry ): void {
				$integration_registry->register( $this );
			}
		);

		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => self::CALLBACKS_URI,
				'callback'  => array( $this, 'block_callbacks' ),
			)
		);
	}

	/**
	 * @param array{action: string, branch: ShippingBranch} $data
	 */
	public function block_callbacks( array $data ): void {
		if ( ! isset( $data['action'] ) ) {
			return;
		}

		if ( 'update_selected_branch' === $data['action'] && ! empty( $data['branch'] ) ) {
			$data['branch']['streetNumber'] = $data['branch']['street_number'];
			$branch                         = $this->branches_manager->branch_response_to_object( $data['branch'] );
			$this->branches_manager->set_selected_branch( $branch );
		}
	}

	public function get_name(): string {
		return 'crplugins-oca-for-woocommerce';
	}

	public function initialize(): void {
		wp_register_script(
			'wc-oca-blocks-shipping-method-js',
			Helper::get_blocks_assets_folder_url() . '/shipping-method/build/index.js',
			array( 'react', 'wc-blocks-checkout', 'wc-blocks-registry', 'wc-settings', 'wp-i18n', 'wp-plugins' ),
			CRPlugins_Oca::PLUGIN_VER,
			true
		);
	}

	/**
	 * @return string[]
	 */
	public function get_script_handles(): array {
		return array( 'wc-oca-blocks-shipping-method-js' );
	}

	/**
	 * @return string[]
	 */
	public function get_editor_script_handles(): array {
		return array();
	}

	/**
	 * @return array{branches: ShippingBranch[], selected_branch: ShippingBranch|null, callbacks_uri: string}
	 */
	public function get_script_data(): array {
		$customer = WC()->customer;
		if ( ! $customer ) {
			return array();
		}
		$customer_postcode = $customer->get_shipping_postcode();
		if ( empty( $customer_postcode ) ) {
			return array();
		}

		$branches = $this->branches_manager->get_branches_to( $customer_postcode );
		$branches = array_map(
			function ( ShippingBranch $branch ): array {
				$branch_array                  = $this->branches_manager->branch_to_array_response( $branch );
				$branch_array['street_number'] = $branch_array['streetNumber'];
				$branch_array['address']       = $branch->get_address();

				return $branch_array;
			},
			$branches
		);

		return array(
			'branches'        => $branches,
			'selected_branch' => $this->branches_manager->get_selected_branch(),
			'callbacks_uri'   => self::CALLBACKS_URI,
		);
	}
}
