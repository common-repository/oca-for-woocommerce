<?php

namespace CRPlugins\Oca\ShippingMethod;

use CRPlugins\Oca\Helper\Helper;

defined( 'ABSPATH' ) || exit;

class CacheManager {

	public static function get_oca_shipping_cache_key( array $package, string $destination ): string {
		unset( $package['rates'] );
		$hash      = md5( wp_json_encode( $package ) );
		$cache_key = sprintf( 'oca_shipping_cache_%s_%s', $hash, $destination );

		return $cache_key;
	}

	public static function clear_oca_shipping_cache(): void {
		$data = WC()->session->get_session_data();
		foreach ( array_keys( $data ) as $key ) {
			if ( Helper::str_starts_with( $key, 'oca_shipping_cache' ) ) {
				unset( WC()->session->$key );
			}
		}
	}

	public static function maybe_invalidate_oca_shipping_cache(): void {
		$cache_version    = WC()->session->get( 'oca_settings_version' );
		$settings_version = (string) Helper::get_option( 'settings_version' );

		if ( $cache_version && $settings_version !== $cache_version ) {
			self::clear_oca_shipping_cache();
		}
	}
}
