<?php

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use Automattic\WooCommerce\Utilities\FeaturesUtil;
use CRPlugins\Oca\Api\OcaApi;
use CRPlugins\Oca\Container\Container;
use CRPlugins\Oca\Container\ContainerInterface;
use CRPlugins\Oca\Helper\Helper;
use CRPlugins\Oca\Orders\DaysManager;
use CRPlugins\Oca\Orders\Metabox;
use CRPlugins\Oca\Orders\OrdersList;
use CRPlugins\Oca\Orders\OrdersProcessor;
use CRPlugins\Oca\Orders\Quoter;
use CRPlugins\Oca\Rest\Routes;
use CRPlugins\Oca\Sdk\OcaSdk;
use CRPlugins\Oca\Settings\HealthChecker;
use CRPlugins\Oca\Settings\MainSettings;
use CRPlugins\Oca\ShippingLabels\AllLabelsProcessor;
use CRPlugins\Oca\ShippingLabels\Pdf10x15LabelsProcessor;
use CRPlugins\Oca\ShippingLabels\PdfA4LabelsProcessor;
use CRPlugins\Oca\ShippingLabels\ShippingLabelsCleanerCron;
use CRPlugins\Oca\ShippingLabels\ZplLabelsProcessor;
use CRPlugins\Oca\ShippingMethod\CheckoutIntegration;
use CRPlugins\Oca\ShippingMethod\OcaShippingMethod;
use CRPlugins\Oca\ShippingMethod\ShippingBranchesManager;
use CRPlugins\Oca\Shortcodes\TrackingShortcode;

/**
 * Plugin Name: OCA para WooCommerce
 * Description: Integración entre OCA y WooCommerce
 * Version: 3.2.1
 * Requires PHP: 7.1
 * Author: CRPlugins
 * Author URI: https://crplugins.com.ar
 * Text Domain: wc-oca
 * Domain Path: /i18n/languages/
 * WC requires at least: 4.2
 * WC tested up to: 9.3.3
 */

defined( 'ABSPATH' ) || exit;

class CRPlugins_Oca {

	public const PLUGIN_NAME = 'OCA para WooCommerce';
	public const MAIN_FILE   = __FILE__;
	public const MAIN_DIR    = __DIR__;
	public const PLUGIN_VER  = '3.2.1';

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_register_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_register_scripts' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( self::MAIN_FILE ), array( $this, 'create_settings_link' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_wc_compatibility' ) );
	}

	public function init(): void {
		if ( ! $this->check_system() ) {
			return;
		}

		spl_autoload_register(
			function ( string $class_name ) {
				if ( strpos( $class_name, 'CRPlugins\Oca' ) === false ) {
					return;
				}
				$file = str_replace( 'CRPlugins\\Oca\\', '', $class_name );
				$file = str_replace( '\\', '/', $file );
				/** @psalm-suppress UnresolvableInclude */
				require_once sprintf( '%s/%s.php', __DIR__, $file );
			}
		);

		$this->init_container();
		$this->init_classes();
		$this->load_textdomain();
	}

	public function check_system( bool $show_notice = true ): bool {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$system = self::check_components();

		if ( $system['flag'] ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			if ( $show_notice ) {
				echo '<div class="notice notice-error is-dismissible">';
				echo '<p><strong>' . esc_attr( self::PLUGIN_NAME ) . '</strong> ' . sprintf( esc_html__( 'Requires at least %1$s version %2$s or greater.', 'wc-oca' ), esc_html( $system['flag'] ), esc_html( $system['version'] ) );
				echo '</div>';
			}
			return false;
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			if ( $show_notice ) {
				echo '<div class="notice notice-error is-dismissible">';
				echo '<p>' . esc_html__( 'WooCommerce must be active before using', 'wc-oca' ) . ' <strong>' . esc_html( self::PLUGIN_NAME ) . '</strong></p>';
				echo '</div>';
			}
			return false;
		}

		return true;
	}

	/**
	 * @return array{flag: string, version: string}
	 */
	private static function check_components(): array {

		global $wp_version;
		/** @var string $wp_version */
		$flag    = '';
		$version = '';

		if ( version_compare( PHP_VERSION, '7.1', '<' ) ) {
			$flag    = 'PHP';
			$version = '7.1';
		} elseif ( version_compare( $wp_version, '5.0', '<' ) ) {
			$flag    = 'WordPress';
			$version = '5.0';
		} elseif ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, '4.0', '<' ) ) {
			$flag    = 'WooCommerce';
			$version = '4.2';
		}

		return array(
			'flag'    => $flag,
			'version' => $version,
		);
	}

	public function init_container(): void {
		$container = Container::instance();
		$container->set(
			OcaApi::class,
			static function ( ContainerInterface $container ): OcaApi { // phpcs:ignore
				return new OcaApi( Helper::get_option( 'apikey', '' ) );
			}
		);
		$container->set(
			OcaSdk::class,
			static function ( ContainerInterface $container ): OcaSdk {
				return new OcaSdk( $container->get( OcaApi::class ), Helper::get_seller_settings() );
			}
		);
		$container->set(
			ShippingBranchesManager::class,
			static function ( ContainerInterface $container ): ShippingBranchesManager {
				return new ShippingBranchesManager( $container->get( OcaSdk::class ) );
			}
		);
		$container->set(
			PdfA4LabelsProcessor::class,
			static function ( ContainerInterface $container ): PdfA4LabelsProcessor {
				return new PdfA4LabelsProcessor( $container->get( OcaSdk::class ) );
			}
		);
		$container->set(
			Pdf10x15LabelsProcessor::class,
			static function ( ContainerInterface $container ): Pdf10x15LabelsProcessor {
				return new Pdf10x15LabelsProcessor( $container->get( OcaSdk::class ) );
			}
		);
		$container->set(
			ZplLabelsProcessor::class,
			static function ( ContainerInterface $container ): ZplLabelsProcessor {
				return new ZplLabelsProcessor( $container->get( OcaSdk::class ) );
			}
		);
		$container->set(
			Quoter::class,
			static function ( ContainerInterface $container ): Quoter {
				return new Quoter( $container->get( OcaSdk::class ) );
			}
		);
		$container->set(
			AllLabelsProcessor::class,
			static function ( ContainerInterface $container ): AllLabelsProcessor {
				return new AllLabelsProcessor(
					$container->get( OcaSdk::class ),
					$container->get( PdfA4LabelsProcessor::class ),
					$container->get( Pdf10x15LabelsProcessor::class ),
					$container->get( ZplLabelsProcessor::class )
				);
			}
		);
		$container->set(
			DaysManager::class,
			static function ( ContainerInterface $container ): DaysManager {
				return new DaysManager();
			}
		);
		$container->set(
			OrdersProcessor::class,
			static function ( ContainerInterface $container ): OrdersProcessor {
				return new OrdersProcessor(
					$container->get( OcaSdk::class ),
					$container->get( AllLabelsProcessor::class ),
					$container->get( DaysManager::class )
				);
			}
		);
		$container->set(
			OrdersList::class,
			static function ( ContainerInterface $container ): OrdersList {
				return new OrdersList( $container->get( OrdersProcessor::class ) );
			}
		);
		$container->set(
			TrackingShortcode::class,
			static function ( ContainerInterface $container ): TrackingShortcode {
				return new TrackingShortcode( $container->get( OcaSdk::class ) );
			}
		);
		$container->set(
			HealthChecker::class,
			static function ( ContainerInterface $container ): HealthChecker {
				return new HealthChecker( $container->get( OcaSdk::class ) );
			}
		);
		if ( interface_exists( IntegrationInterface::class ) ) {
			$container->set(
				CheckoutIntegration::class,
				static function ( ContainerInterface $container ): CheckoutIntegration {
					return new CheckoutIntegration( $container->get( ShippingBranchesManager::class ) );
				}
			);
		}
	}

	public function init_classes(): void {
		$container = Container::instance();

		// We init these classes so their hooks are registered
		$container->get( OrdersList::class );
		$container->get( TrackingShortcode::class );
		new Routes(
			$container->get( ShippingBranchesManager::class ),
			$container->get( PdfA4LabelsProcessor::class ),
			$container->get( Pdf10x15LabelsProcessor::class ),
			$container->get( ZplLabelsProcessor::class ),
			$container->get( OrdersProcessor::class ),
			$container->get( Quoter::class ),
			$container->get( OcaSdk::class ),
			$container->get( HealthChecker::class )
		);
		if ( interface_exists( IntegrationInterface::class ) ) {
			$container->get( CheckoutIntegration::class );
		}
		new Metabox();
		new ShippingLabelsCleanerCron();
		new MainSettings();

		/** @psalm-suppress InvalidArgument */
		add_action( 'admin_notices', array( Helper::class, 'check_notices' ) );
		add_filter( 'woocommerce_shipping_methods', array( $this, 'add_shipping_method' ) );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'wc-oca', false, basename( __DIR__ ) . '/i18n/languages' );
	}

	public function admin_register_scripts(): void {
		if ( ! $this->check_system( false ) ) {
			return;
		}

		wp_register_script( 'wc-oca-surreal', Helper::get_assets_folder_url() . '/js/surreal.js', array(), self::PLUGIN_VER, true );
		wp_register_script( 'wc-oca-helper-js', Helper::get_assets_folder_url() . '/js/helper.js', array( 'wc-oca-surreal' ), self::PLUGIN_VER, true );
		wp_localize_script(
			'wc-oca-helper-js',
			'wc_oca_helper_settings',
			array(
				'store_url' => get_site_url(),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
			)
		);
		wp_localize_script(
			'wc-oca-helper-js',
			'wc_oca_translation_texts',
			array(
				'generic_error_try_again' => esc_html__( 'There was an error, please try again', 'wc-oca' ),
				'loading'                 => esc_html__( 'Loading...', 'wc-oca' ),
			)
		);
		wp_register_script( 'wc-oca-settings-js', Helper::get_assets_folder_url() . '/js/settings.js', array(), self::PLUGIN_VER, true );
		wp_register_style( 'wc-oca-general-css', Helper::get_assets_folder_url() . '/css/general.css', array(), self::PLUGIN_VER );
		wp_register_script( 'wc-oca-orders-list-js', Helper::get_assets_folder_url() . '/js/orders-list.js', array( 'wc-oca-helper-js' ), self::PLUGIN_VER, true );
		wp_register_script( 'wc-oca-orders-js', Helper::get_assets_folder_url() . '/js/orders.js', array( 'wc-oca-helper-js' ), self::PLUGIN_VER, true );
		wp_register_style( 'wc-oca-orders-css', Helper::get_assets_folder_url() . '/css/orders.css', array(), self::PLUGIN_VER );
	}

	public function declare_wc_compatibility(): void {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', self::MAIN_FILE, true );
			FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', self::MAIN_FILE, true );
		}
	}

	public function frontend_register_scripts(): void {
		if ( ! $this->check_system( false ) ) {
			return;
		}

		wp_register_script( 'wc-oca-surreal', Helper::get_assets_folder_url() . '/js/surreal.js', array(), self::PLUGIN_VER, true );
		wp_register_script( 'wc-oca-shipping-method-js', Helper::get_assets_folder_url() . '/js/shipping-method.js', array( 'jquery', 'wc-oca-surreal' ), self::PLUGIN_VER, true );
	}

	public function create_settings_link( array $links ): array {
		$link = sprintf(
			'<a href="%s">%s</a>',
			esc_attr( esc_url( get_admin_url( null, 'admin.php?page=wc-oca-admin' ) ) ),
			esc_html__( 'Settings', 'wc-oca' )
		);
		array_unshift( $links, $link );
		return $links;
	}

	/**
	 * @param string[] $shipping_methods
	 * @return string[]
	 */
	public function add_shipping_method( $shipping_methods ): array {
		$shipping_methods['oca'] = OcaShippingMethod::class;
		return $shipping_methods;
	}
}

new CRPlugins_Oca();
