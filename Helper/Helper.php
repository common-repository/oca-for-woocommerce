<?php

namespace CRPlugins\Oca\Helper;

use CRPlugins\Oca\ValueObjects\Address;
use CRPlugins\Oca\ValueObjects\Seller;
use CRPlugins\Oca\ValueObjects\SellerOcaSettings;
use CRPlugins\Oca\ValueObjects\ShippingCode;
use CRPlugins\Oca\ValueObjects\ShippingCodes;

class Helper {

	use NoticesTrait;
	use SettingsTrait;
	use WooCommerceTrait;
	use LoggerTrait;
	use AssetsTrait;

	public static function get_seller(): Seller {
		$settings = array(
			'name'           => self::get_option( 'name' ),
			'email'          => self::get_option( 'email' ),
			'storeName'      => self::get_option( 'storeName' ),
			'timeFrame'      => self::get_option( 'timeFrame' ),
			'street'         => self::get_option( 'street' ),
			'streetNumber'   => self::get_option( 'streetNumber' ),
			'floor'          => self::get_option( 'floor' ),
			'apartment'      => self::get_option( 'apartment' ),
			'postcode'       => self::get_option( 'postcode' ),
			'city'           => self::get_option( 'city' ),
			'state'          => self::get_option( 'state' ),
			'observations'   => self::get_option( 'observations' ),
			'shippingBranch' => self::get_option( 'shippingBranch' ),
		);

		/**
		 * @var array{
		 *  name: string,
		 *  email: string,
		 *  storeName: string,
		 *  timeFrame: string,
		 *  street: string,
		 *  streetNumber: string,
		 *  floor: string,
		 *  apartment: string,
		 *  postcode: string,
		 *  city: string,
		 *  state: string,
		 *  observations: string,
		 *  shippingBranch: int|array{id: string},
		 * } $settings
		 */

		return new Seller(
			$settings['name'],
			$settings['email'],
			$settings['storeName'],
			$settings['timeFrame'],
			self::get_seller_settings(),
			new Address(
				$settings['street'],
				$settings['streetNumber'],
				$settings['floor'],
				$settings['apartment'],
				$settings['postcode'],
				$settings['city'],
				$settings['state'],
				$settings['observations'],
				is_array( $settings['shippingBranch'] ) ? $settings['shippingBranch']['id'] : (int) $settings['shippingBranch'] // BW compat, it was int before, now it's int
			)
		);
	}

	public static function get_seller_settings(): ?SellerOcaSettings {

		$settings = array(
			'username'      => self::get_option( 'username' ),
			'password'      => self::get_option( 'password' ),
			'accountNumber' => self::get_option( 'accountNumber' ),
			'cuit'          => self::get_option( 'cuit' ),
			'shippingCodes' => self::get_option( 'shippingCodes' ),
		);

		if (
			empty( $settings['username'] )
			|| empty( $settings['password'] )
			|| empty( $settings['accountNumber'] )
			|| empty( $settings['cuit'] )
			|| empty( $settings['shippingCodes'] )
			) {
			return null;
		}

		/**
		 * @var array{
		 *  username: string,
		 *  password: string,
		 *  accountNumber: string,
		 *  cuit: string,
		 *  shippingCodes: array<array{id: string, name: string, type: string, price: string}>,
		 * } $settings
		 */

		$shipping_codes = array();
		foreach ( $settings['shippingCodes'] as $code ) {
			$shipping_codes[] = new ShippingCode(
				(int) $code['id'],
				$code['name'],
				$code['type'],
				(float) $code['price']
			);
		}

		return new SellerOcaSettings(
			$settings['username'],
			$settings['password'],
			$settings['accountNumber'],
			$settings['cuit'],
			new ShippingCodes( $shipping_codes )
		);
	}

	public static function get_seller_postcode(): ?string {
		return self::get_option( 'postcode' );
	}

	public static function log_debug( string $msg ): void {
		if ( self::is_enabled( 'debug' ) ) {
			self::log_for_debug( $msg );
		}
	}

	/**
	 * @return array{height: float, width: float, length: float}
	 */
	public static function get_default_package_size(): array {
		$dimensions = self::get_option( 'default_package_size' );
		$dimensions = explode( 'x', $dimensions );

		return array(
			'height' => (float) $dimensions[0],
			'width'  => (float) $dimensions[1],
			'length' => (float) $dimensions[2],
		);
	}

	/**
	 * @return array{
	 *  apikey: string,
	 *  username: string,
	 *  password: string,
	 *  accountNumber: string,
	 *  cuit: string,
	 *  shippingCodes: array,
	 *  name: string,
	 *  storeName: string,
	 *  email: string,
	 *  street: string,
	 *  streetNumber: string,
	 *  floor: string,
	 *  apartment: string,
	 *  city: string,
	 *  state: string,
	 *  postcode: string,
	 *  observations: string,
	 *  timeFrame: string,
	 *  extra_dispatch_days: string,
	 *  dispatch_days: array,
	 *  shippingBranch: string,
	 *  status_processing: string,
	 *  enable_insurance: string,
	 *  notify_customer_order_processed: string,
	 *  default_package_size: string,
	 *  proccess_always_one_package: string,
	 *  add_branch_name_to_method: string,
	 *  enable_branch_selector: string,
	 *  price_multiplier: string,
	 *  free_shipping_extra_name: string,
	 *  extra_delivery_days: string,
	 *  round_costs: string,
	 *  free_shipping_with_coupon: string,
	 *  label_delete_cron_time: string,
	 *  debug: string,
	 *  }
	 */
	public static function get_all_settings(): array {
		return array(
			'apikey'                          => self::get_option( 'apikey', '' ),
			'username'                        => self::get_option( 'username', '' ),
			'password'                        => self::get_option( 'password', '' ),
			'accountNumber'                   => self::get_option( 'accountNumber', '' ),
			'cuit'                            => self::get_option( 'cuit', '' ),
			'shippingCodes'                   => self::get_option( 'shippingCodes', array() ),
			'name'                            => self::get_option( 'name', '' ),
			'storeName'                       => self::get_option( 'storeName', '' ),
			'email'                           => self::get_option( 'email', '' ),
			'street'                          => self::get_option( 'street', '' ),
			'streetNumber'                    => self::get_option( 'streetNumber', '' ),
			'floor'                           => self::get_option( 'floor', '' ),
			'apartment'                       => self::get_option( 'apartment', '' ),
			'city'                            => self::get_option( 'city', '' ),
			'state'                           => self::get_option( 'state', '' ),
			'postcode'                        => self::get_option( 'postcode', '' ),
			'observations'                    => self::get_option( 'observations', '' ),
			'timeFrame'                       => self::get_option( 'timeFrame', '1' ),
			'extra_dispatch_days'             => self::get_option( 'extra_dispatch_days', '0' ),
			'dispatch_days'                   => self::get_option( 'dispatch_days', array( '1', '2', '3', '4', '5', '6' ) ),
			'shippingBranch'                  => self::get_option( 'shippingBranch', '' ),
			'status_processing'               => self::get_option( 'status_processing', '0' ),
			'enable_insurance'                => self::get_option( 'enable_insurance', 'false' ),
			'notify_customer_order_processed' => self::get_option( 'notify_customer_order_processed', 'true' ),
			'tracking_mail_subject'           => self::get_option( 'tracking_mail_subject', 'Tu orden #{{orden}} ha sido enviada' ),
			'tracking_mail_body'              => self::get_option( 'tracking_mail_body', 'Tu orden #{{orden}} ha sido enviada con OCA, podés rastrearla usando el siguiente número de envío: {{tracking}}' ),
			'default_package_size'            => self::get_option( 'default_package_size', '0.5x0.2x0.2' ),
			'proccess_always_one_package'     => self::get_option( 'proccess_always_one_package', 'false' ),
			'add_branch_name_to_method'       => self::get_option( 'add_branch_name_to_method', 'true' ),
			'enable_branch_selector'          => self::get_option( 'enable_branch_selector', 'true' ),
			'price_multiplier'                => self::get_option( 'price_multiplier', '1' ),
			'free_shipping_extra_name'        => self::get_option( 'free_shipping_extra_name', '' ),
			'extra_delivery_days'             => self::get_option( 'extra_delivery_days', '0' ),
			'round_costs'                     => self::get_option( 'round_costs', 'true' ),
			'free_shipping_with_coupon'       => self::get_option( 'free_shipping_with_coupon', 'true' ),
			'label_delete_cron_time'          => self::get_option( 'label_delete_cron_time', '604800' ),
			'debug'                           => self::get_option( 'debug', '' ),
		);
	}

	public static function str_contains( string $haystack, string $needle ): bool {
		return ( '' === $needle || false !== strpos( $haystack, $needle ) );
	}

	public static function str_starts_with( string $haystack, string $needle ): bool {
		if ( '' === $needle ) {
			return true;
		}

		return 0 === strpos( $haystack, $needle );
	}

	public static function str_ends_with( string $haystack, string $needle ): bool {
		if ( '' === $haystack && '' !== $needle ) {
			return false;
		}

		$len = strlen( $needle );

		return 0 === substr_compare( $haystack, $needle, -$len, $len );
	}
}
