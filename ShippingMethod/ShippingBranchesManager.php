<?php

namespace CRPlugins\Oca\ShippingMethod;

use CRPlugins\Oca\Helper\Helper;
use CRPlugins\Oca\Sdk\OcaSdk;
use CRPlugins\Oca\ValueObjects\ShippingBranch;
use CRPlugins\Oca\ValueObjects\ShippingCode;
use WC_Shipping_Rate;

defined( 'ABSPATH' ) || exit;

class ShippingBranchesManager {

	public const SESSION_KEY_SELECTED_BRANCH = 'wc_oca_selected_shipping_branch';

	/**
	 * @var OcaSdk
	 */
	private $sdk;

	public function __construct( OcaSdk $sdk ) {
		$this->sdk = $sdk;

		add_action( 'woocommerce_after_shipping_rate', array( $this, 'maybe_show_branches_selection' ) );
		if ( $this->is_branches_selector_enabled() ) {
			add_filter( 'woocommerce_shipping_packages', array( $this, 'maybe_clear_cache' ) );
		}
	}

	public function enqueue_javascript() {
		wp_enqueue_script( 'wc-oca-shipping-method-js' );
		wp_localize_script(
			'wc-oca-shipping-method-js',
			'wc_oca_settings',
			array(
				'ajax_url'               => admin_url( 'admin-ajax.php' ),
				'ajax_nonce'             => wp_create_nonce( 'wp_rest' ),
				'page_is_cart'           => is_cart(),
				'page_is_single_product' => is_product(),
				'store_url'              => get_site_url(),
			)
		);
		wp_localize_script(
			'wc-oca-shipping-method-js',
			'wc_oca_translation_texts',
			array(
				'generic_error_try_again' => esc_html__( 'There was an error, please try again', 'wc-oca' ),
			)
		);
	}

	public function is_branches_selector_enabled(): bool {
		return Helper::is_enabled( 'enable_branch_selector' );
	}

	/**
	 * @return ShippingBranch[]
	 */
	public function get_branches_to( string $poscode ): array {
		$branches = $this->sdk->get_shipping_branches_to( $poscode );
		if ( isset( $branches['error'] ) ) {
			return array();
		}

		return array_map( array( $this, 'branch_response_to_object' ), $branches );
	}

	public function get_selected_branch(): ?ShippingBranch {
		/** @var string|null $selected_branch */
		$selected_branch = WC()->session->get( self::SESSION_KEY_SELECTED_BRANCH, null );
		if ( ! $selected_branch ) {
			return null;
		}

		$selected_branch = json_decode( $selected_branch, true );
		/** @var array{id: string, name: string, street: string, streetNumber: string, floor: string, apt: string, city: string, postcode: string, phone: string, hours: string, type: string} $selected_branch */

		return $this->branch_response_to_object( $selected_branch );
	}

	public function set_selected_branch( ShippingBranch $shipping_branch ): void {
		WC()->session->set(
			self::SESSION_KEY_SELECTED_BRANCH,
			wp_json_encode( $this->branch_to_array_response( $shipping_branch ) )
		);
	}

	public function maybe_show_branches_selection( \WC_Shipping_Rate $current_rate ): void {
		if ( ! $this->sdk->is_valid() ) {
			return;
		}

		// Check if the selector is enabled
		if ( ! $this->is_branches_selector_enabled() ) {
			return;
		}
		$this->enqueue_javascript();

		// Check if the chosen method is OCA branch
		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
		if ( empty( $chosen_methods[0] ) || ! Helper::str_ends_with( $chosen_methods[0], 'branch' ) ) {
			return;
		}

		// Check if the current method is OCA
		if ( 'oca' !== $current_rate->get_method_id() ) {
			return;
		}

		// Check if the current method is to branch
		$meta = $current_rate->get_meta_data();
		if ( empty( $meta['shipping_code'] ) ) {
			return;
		}

		$shipping_code = new ShippingCode(
			$meta['shipping_code']['id'],
			$meta['shipping_code']['name'],
			$meta['shipping_code']['type'],
			(float) $current_rate->get_cost()
		);

		if ( ! $shipping_code->is_to_branch() ) {
			return;
		}

		$customer_postcode = WC()->customer->get_shipping_postcode();
		if ( empty( $customer_postcode ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->no_address();
			return;
		}

		$branches = $this->get_branches_to( $customer_postcode );

		$selected_branch = $this->get_selected_branch();

		// If there are no branches returned but a branch was selected, keep it
		if ( ! $branches && $selected_branch ) {
			$branches = array( $selected_branch );
		}

		if ( $branches ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->show_branches_selector( $branches, $selected_branch );
		}
	}

	/**
	 * @param array{id: int, name: string, street: string, streetNumber: string, floor: string, apt: string, city: string, postcode: string, phone: string, hours: string, type: string} $branch
	 */
	public function branch_response_to_object( array $branch ): ShippingBranch {
		return new ShippingBranch(
			$branch['id'] ? $branch['id'] : 0,
			$branch['name'] ? $branch['name'] : '',
			$branch['street'] ? $branch['street'] : '',
			$branch['streetNumber'] ? $branch['streetNumber'] : '',
			$branch['floor'] ? $branch['floor'] : '',
			$branch['apt'] ? $branch['apt'] : '',
			$branch['city'] ? $branch['city'] : '',
			$branch['postcode'] ? $branch['postcode'] : '',
			$branch['phone'] ? $branch['phone'] : '',
			$branch['hours'] ? $branch['hours'] : '',
			$branch['type'] ? $branch['type'] : ''
		);
	}

	/**
	 * @return array{id: int, name: string, street: string, streetNumber: string, floor: string, apt: string, city: string, postcode: string, phone: string, hours: string, type: string}
	 */
	public function branch_to_array_response( ShippingBranch $branch ): array {
		return array(
			'id'           => $branch->get_id(),
			'name'         => $branch->get_name(),
			'street'       => $branch->get_street(),
			'streetNumber' => $branch->get_street_number(),
			'floor'        => $branch->get_floor(),
			'apt'          => $branch->get_apt(),
			'city'         => $branch->get_city(),
			'postcode'     => $branch->get_postcode(),
			'phone'        => $branch->get_phone(),
			'hours'        => $branch->get_hours(),
			'type'         => $branch->get_type(),
		);
	}

	private function no_address(): string {
		$output  = '<span class="oca-no-customer-address-message">';
		$output .= esc_html__( 'Please fill your shipping information to view the shipping branches near your area', 'wc-oca' );
		$output .= '</span>';
		return $output;
	}

	/**
	 * @param ShippingBranch[] $branches
	 */
	private function show_branches_selector( array $branches, ?ShippingBranch $selected ): string {
		$output  = '<div id="oca-branches-selection-wrapper">';
		$output .= sprintf(
			'<p class="oca-select-office-message" style="%s">%s</p>',
			'margin:10px 0 0 0;display:block;font-size:85%;',
			esc_html__( 'Select a OCA shipping branch for your order', 'wc-oca' )
		);
		$output .= '<select id="oca-shipping-branch-selector" style="margin:15px 0; width: 100%;" required>';
		$output .= '<option disabled>Seleccionar</option>';

		foreach ( $branches as $branch ) {
			$option_selected = $selected && $branch->get_id() === $selected->get_id() ? 'selected' : '';
			$output         .= sprintf(
				'<option value="%s"
					data-name="%s" 
					data-street="%s" 
					data-street-number="%s" 
					data-floor="%s" 
					data-apt="%s" 
					data-city="%s" 
					data-postcode="%s" 
					data-phone="%s" 
					data-hours="%s" 
					data-type="%s" 
					%s
				>%s</option>',
				esc_attr( $branch->get_id() ),
				esc_attr( $branch->get_name() ),
				esc_attr( $branch->get_street() ),
				esc_attr( $branch->get_street_number() ),
				esc_attr( $branch->get_floor() ),
				esc_attr( $branch->get_apt() ),
				esc_attr( $branch->get_city() ),
				esc_attr( $branch->get_postcode() ),
				esc_attr( $branch->get_phone() ),
				esc_attr( $branch->get_hours() ),
				esc_attr( $branch->get_type() ),
				esc_attr( $option_selected ),
				esc_html( $branch->get_address() )
			);
		}

		$output .= '</select>';
		$output .= '</div>';
		return $output;
	}

	public function maybe_clear_cache( array $packages ): array {
		foreach ( $packages as $key => $package ) {
			if ( ! isset( $package['rates'] ) ) {
				return $packages;
			}

			/** @var WC_Shipping_Rate $rate */
			foreach ( $package['rates'] as $rate ) {
				if ( 'oca' === $rate->get_method_id() && Helper::str_ends_with( $rate->get_id(), 'branch' ) ) {
					$this->clear_cache( $key );
					return $packages;
				}
			}
		}
		return $packages;
	}

	private function clear_cache( string $package_key ): void {
		$shipping_session = sprintf( 'shipping_for_package_%s', $package_key );
		unset( WC()->session->$shipping_session );
	}
}
