<?php

namespace CRPlugins\Oca\Settings;

use CRPlugins\Oca\Helper\Helper;

defined( 'ABSPATH' ) || exit;

class MainSettings {

	public function __construct() {
		add_action( 'admin_init', array( __CLASS__, 'init_settings' ) );
		add_action( 'admin_menu', array( $this, 'create_menu_option' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_asset_files' ) );
	}

	public static function init_settings(): void {
		register_setting( 'wc-oca', 'wc-oca_options' );

		if ( ! Helper::get_option( 'settings_version' ) ) {
			Helper::save_option( 'settings_version', time() );
		}
	}

	public function add_asset_files( string $hook ): void {
		if ( 'toplevel_page_wc-oca-admin' === $hook ) {
			wp_enqueue_script( 'wc-oca-settings-js' );
			wp_localize_script(
				'wc-oca-settings-js',
				'wc_oca_settings',
				array(
					'store_url' => get_site_url(),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
				)
			);
		}
	}

	public function create_menu_option(): void {
		add_menu_page(
			'OCA',
			'OCA',
			'manage_options',
			'wc-oca-admin',
			array( __CLASS__, 'settings_page_content' ),
			'data:image/svg+xml;base64,PHN2ZyB2ZXJzaW9uPSIxLjIiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmlld0JveD0iMCAwIDE2NyAxMzMiIHdpZHRoPSIxNjciIGhlaWdodD0iMTMzIj4KCTx0aXRsZT5sb2dvIGNyIGZvdG88L3RpdGxlPgoJPGRlZnM+CgkJPGltYWdlICB3aWR0aD0iMTY3IiBoZWlnaHQ9IjEzMyIgaWQ9ImltZzEiIGhyZWY9ImRhdGE6aW1hZ2UvcG5nO2Jhc2U2NCxpVkJPUncwS0dnb0FBQUFOU1VoRVVnQUFBS2NBQUFDRkNBTUFBQUFKckJqakFBQUFBWE5TUjBJQjJja3Nmd0FBQUZGUVRGUkZ3NDMydzQzMnc0MzJ3NDMydzQzMnc0MzJ3NDMydzQzMnc0MzJ3NDMydzQzMnc0MzJ3NDMydzQzMnc0MzJ3NDMydzQzMnc0MzJ3NDMydzQzMnc0MzJ3NDMydzQzMnc0MzJ3NDMydzQzMnc0MzJFUUo5cEFBQUFCdDBVazVUQUVDQWNCRC84T0RBRVVSM3FydFZNNW5kaU80aVpzeGdNTkFnbDdPeWZBQUFCU0JKUkVGVWVKenRuR3RYMnpBTWhwMzBtcmFrZEpUdGJQLy90NDJkM2RnRmFJRTBYY3JLSXRteUpMY3VwVHQrUDhBTzVQSlV0bVJKOXNqTWFTZzdOb0JTRFdmMnFsbXpySHI2Wmt6ZU9UWUxyM3BsVG9JemV6QW53V21xZGVLTXFTM25LL2IzWHIzNXV1Vjh4UnB1WEQxeFJsUGlqS3ZFR1ZlSk02NFNaMXdsenJoS25IR1ZPT01xY2NaVjRveXJ4QmxYU3M2aUtlMjMxWDF0T3RuUEZ5REQwbkNXZVhaajMxWjNCZGFlNStlVHl2VHUxM2NCaUg5ZktIR08rcDFmOUFzZmlxL01nMzJjV3cyS200V0c3MWtDNTJWMnpkdzg3dnBKQmM1bWxGYk0zWTVZenRHRW85eG9mTyt6aXNqWnFGK3JiY3B3RnAxNytmNXllVXYvUXNOcEpyWFdJLzJjWlkrZWw4NFRLdElvS2s1anpyL29ydk55dnZtdGUwRHpnU3JLS0VwT0xhaUhVelhtenlvWFJKalJjcHBDTld3MFozSG1PbEQ1MElUNEp0aDM3VmhxU0tPMG5PVUR3cktmWEdZYXR5YzVIY3hKVmozK200VkZyNXRaTmlDR3Z1VWNXak5vMmw4dUVZSm1ocEdjNTlpSGh6WldFMWNyYkZYWEtINU9Zd2U4bm1KMW9qakhhS0JtTitSakxtc0VQL3Njd29sTm9URW93VGw2aEJmMFBmSFJ6UE5yN2pxQjAweUFvNDdrS09weUZpTmdxUExSNzQzV0xMWmVKbkhDMTV4OTM0RVRqZ2dkR21sUUMwZmloTFBMbVRRS1RqanFQS1psZWlzUWlweHo4T3hIOGdxVzh3ekVESEd0UUZNWkp5MGlwM25iWGk5N3ZNMEozenorSWQyTnZBRy9UZVlFOTRaekFuT1dkM0xXTlllY05YUjVtUk5NVUc5UThYSENTYU5OWldnZGxoTmtTV0tPek91d25MTjJPZHpQbkllZG4xTndmVGVvem5KMFVIOEhRekVnMHJjUUhUUitBbS9mYzlnUHV4NWR0RXZLTktCb3BSU3l2c3R1aERtTDZ0L1B5Mis3SXo0cEpGOVMyQVJ4Z3NWSWxXUnpPbVQrV2JZdXJsZ3plYkdjdUV3TXp1ZURJcThnaG5QYVFZVk1lSDEwR000SkttTk0zcFZLSzBvdndDbElGd0NQenFtb09UWTZOcWNTODhpY1phNWQ5WTdLT1J5b0Z6MWZuSThZUC8ydkR1alN2OEI2UkdwMnkzYjNIZUU4QkR4Y1RyVjQ4WnpCdzRVNVFUcXY2S1d3Y3RlallnZ0N2R2F0aE1LY0lJbloxNUdJZGZNU0dGR1JjaUo1OC9uUUI5bWkxbmZZd3dpMEErYUV0Y0NlaVRMRmlYcENpdTRBa0ZWdmd0cHF6NEVuOHlXWWRXcFhvdTFETUNjWStNQVBiSXZrbks5QlFoZmtxVXcvWkQrRDB2a25iRlVIaFdpN3Z3UkdScE1ZanVDSmUwVi9DY1dta0pMVzVvUUdWWHhnNk1HcWZoMG9iWUptbHRQL2hGTmRISGtZRWEyUDVhczc0Q2NMY0NXSEUwMTFZV1NtWFZqbjRDWEd4d2xiUXdHdTVQYm4wYTRNQzdwYmZ6NjBJdlp4d2hqS0RqM0d0TDNPeTRtYSttcFhJamp4YUE1V25zayt6VkhkcU44L2dnT21kaVZxUHc1NWgrY2tRSkhqYXRjWlFhWitCMW5aYnZYUnMvREdZVk9BMitjamlueUk5NzhtUzlzdURDZU1UVnBYb3ZlMXoyMFREdGJ0T2FCcGYyRnZ6Z2Z0RitQWXBIUWx6emtCQjlROG5Ud3l4UDQ1amNseW90aTBRNThCeUJwNlZ1UzJIZHNIZzRjNmRLN2tQUjlpN1Zzem10VlVIc0QzNjJCc1VybVMvN3pOZEtEYm1QRzhodTkvb3VIU3VCSjN6bW84bEUwNnVmY1VaRUtmRnNhbUhjOEp0SnBYUWlMaVBXVWxjcUlkWE1XUkcrRjhIVXZxeGxVZ3FUOFBZNU1iZlIySjV4V0xmRXpPMDNMRk81ckVDUlBkOEgxRFV2TnMwY005NEdIdE9XelpTdHcvUWdmT3hPSldmZTUzMUYxdC8zRTcybk5yYVJmOVorZVRqNjdFR1ZlSk02NFNaMXdsenJoS25IR1ZPT01xY2NaVjRveXJ4QmxYaVRPdUVtZGNKYzY0UXB6dlh2Ny9OMnRWcnpkZlQrenZ3aVhPU0xyNGRCS2M4eXR6Q3B5ZDg4YWNHODRQVjhkRzRmVCs0OU8zMXgwL1cvMEIzUllIc3cvSWJYOEFBQUFBU1VWT1JLNUNZSUk9Ii8+Cgk8L2RlZnM+Cgk8c3R5bGU+Cgk8L3N0eWxlPgoJPHVzZSBpZD0iQmFja2dyb3VuZCIgaHJlZj0iI2ltZzEiIHg9IjAiIHk9IjAiLz4KPC9zdmc+'
		);
	}

	public static function settings_page_content(): void {

		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			die( 'what are you doing here?' );
		}

		?>
		<div id="wc-oca-settings-app"></div>
		<?php
	}
}
