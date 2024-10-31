<?php

namespace CRPlugins\Oca\Helper;

use CRPlugins\Oca\ValueObjects\Address;
use CRPlugins\Oca\ValueObjects\Customer;
use CRPlugins\Oca\ValueObjects\Item;
use CRPlugins\Oca\ValueObjects\Items;
use CRPlugins\Oca\ValueObjects\ShippingMethod;
use Exception;
use WC_Cart;
use WC_Customer;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

trait WooCommerceTrait {

	public static function get_order_customer( WC_Order $order ): Customer {
		$first_name   = $order->get_shipping_first_name();
		$last_name    = $order->get_shipping_last_name();
		$company_name = $order->get_shipping_company();
		$address      = self::get_address( $order );

		if ( empty( $first_name ) && empty( $last_name ) && ! empty( $company_name ) ) {
			$first_name = 'Empresa';
			$last_name  = $company_name;
		}

		return new Customer(
			$first_name,
			$last_name,
			new Address(
				$address['street'],
				$address['number'],
				$address['floor'],
				$address['apartment'],
				$order->get_shipping_postcode(),
				$order->get_shipping_city(),
				self::get_state_name( $order->get_shipping_state() ),
				$order->get_customer_note()
			),
			$order->get_billing_email(),
			$order->get_billing_phone()
		);
	}

	public static function get_customer_from_wc( WC_Customer $customer ): Customer {
		$first_name   = $customer->get_shipping_first_name();
		$last_name    = $customer->get_shipping_last_name();
		$company_name = $customer->get_shipping_company();
		$address      = self::get_address( $customer );

		if ( empty( $first_name ) && empty( $last_name ) && ! empty( $company_name ) ) {
			$first_name = 'Empresa';
			$last_name  = $company_name;
		}

		return new Customer(
			$first_name,
			$last_name,
			new Address(
				$address['street'],
				$address['number'],
				$address['floor'],
				$address['apartment'],
				$customer->get_shipping_postcode(),
				$customer->get_shipping_city(),
				self::get_state_name( $customer->get_shipping_state() )
			)
		);
	}

	/**
	 * Gets the address of a customer or order
	 *
	 * @param WC_Customer|WC_order $customer
	 */
	public static function get_address( $customer ): array {
		if ( $customer->get_shipping_address_1() ) {
			$shipping_line_1 = trim( $customer->get_shipping_address_1() );
			$shipping_line_2 = trim( $customer->get_shipping_address_2() );
		} else {
			$shipping_line_1 = trim( $customer->get_billing_address_1() );
			$shipping_line_2 = trim( $customer->get_billing_address_2() );
		}

		$shipping_line_1 = self::remove_accents( $shipping_line_1 );
		$shipping_line_2 = self::remove_accents( $shipping_line_2 );

		$original_shipping_line_1 = $shipping_line_1;
		$original_shipping_line_2 = $shipping_line_2;

		if ( empty( $shipping_line_2 ) ) {
			// Av. Mexico 430, Piso 4 B
			preg_match( '/([^,]+),(.+)/i', $shipping_line_1, $res );
			if ( ! empty( $res ) ) {
				$shipping_line_1 = trim( $res[1] );
				$shipping_line_2 = trim( $res[2] );
			}
		}

		$street_number = '';
		$floor         = '';
		$apartment     = '';
		if ( ! empty( $shipping_line_2 ) ) {
			// there is something in the second line. Let's find out what
			$fl_apt_array  = self::get_floor_and_apt( $shipping_line_2 );
			$street_number = $fl_apt_array['number'];
			$floor         = $fl_apt_array['floor'];
			$apartment     = $fl_apt_array['apartment'];
		}

		// Floor cannot be longer than 2 chars, we have something weird in here, let's default everything to address1 so it doesnt get trimmed later by oca
		if ( strlen( $floor ) > 2 ) {
			return array(
				'street'    => $original_shipping_line_1 . ( ! empty( $original_shipping_line_2 ) ? ' ' . $original_shipping_line_2 : '' ),
				'number'    => '',
				'floor'     => '',
				'apartment' => '',
			);
		}

		// Street number detected in second line, check only for words in first line, it should be the street name
		if ( $street_number ) {
			preg_match( '/^[a-zA-Z \.]+$/i', $shipping_line_1, $res );
			if ( ! empty( $res ) ) {
				$street_name = trim( $res[0] );
				return array(
					'street'    => $street_name,
					'number'    => $street_number,
					'floor'     => $floor,
					'apartment' => $apartment,
				);
			} else {
				// More than just words in line 1, so we don't know what is in line 2, let's use it as floor
				$floor = $street_number . $apartment;
			}
		}

		// Av. Mexico 430
		preg_match( '/^([a-zA-Z\.#º ]+)[ ]+(\d+)$/i', $shipping_line_1, $res );
		if ( ! empty( $res ) ) {
			$street_name   = trim( $res[1] );
			$street_number = trim( $res[2] );
			return array(
				'street'    => $street_name,
				'number'    => $street_number,
				'floor'     => $floor,
				'apartment' => $apartment,
			);
		}

		// calle 27 nro. 1458
		preg_match( '/^(calle|avenida|av)[\.#º]*[ ]+([0-9]+)[ ]+(numero|altura|nro)[\.#º]*[ ]+([0-9]+)$/i', $shipping_line_1, $res );
		if ( ! empty( $res ) ) {
			$street_name   = trim( $res[1] . ' ' . $res[2] );
			$street_number = trim( $res[4] );
			return array(
				'street'    => $street_name,
				'number'    => $street_number,
				'floor'     => $floor,
				'apartment' => $apartment,
			);
		}

		// 27 nro 1458
		preg_match( '/^(\d+)[ ]+(numero|altura|nro)[\.#º]*[ ]+([0-9]+)$/i', $shipping_line_1, $res );
		if ( ! empty( $res ) ) {
			$street_name   = trim( $res[1] );
			$street_number = trim( $res[3] );
			return array(
				'street'    => $street_name,
				'number'    => $street_number,
				'floor'     => $floor,
				'apartment' => $apartment,
			);
		}

		// Fallback
		$fallback = $shipping_line_1;
		if ( empty( $floor ) && empty( $apartment ) ) {
			$fallback .= ' ' . $shipping_line_2;
		}
		return array(
			'street'    => trim( $fallback ),
			'number'    => $street_number,
			'floor'     => $floor,
			'apartment' => $apartment,
		);
	}

	/**
	 * @return array{
	 *  street: string,
	 *  number: string,
	 *  floor: string,
	 *  apartment: string
	 * }
	 */
	public static function get_floor_and_apt( string $fl_apt ): array {
		$street_name   = '';
		$street_number = '';
		$floor         = '';
		$apartment     = '';

		// Piso 8, dpto. A
		preg_match( '/^(piso|pso|pis|p)[\.º#]*[ ]*(\w+)[, ]*(departamento|depto|dept|dep|dpto|dpt|dto|apartamento|apto|apt)[\.º#]*[ ]*(\w+)[, ]*/i', $fl_apt, $res );
		if ( ! empty( $res ) ) {
			$floor     = trim( $res[2] );
			$apartment = trim( $res[4] );
			return array(
				'street'    => $street_name,
				'number'    => $street_number,
				'floor'     => $floor,
				'apartment' => $apartment,
			);
		}

		// Piso 4 B
		preg_match( '/^(piso|pso|pis|p)[\.º#]*[ ]*(\w+)[, ]*(\w+)[, ]*/i', $fl_apt, $res );
		if ( ! empty( $res ) ) {
			$floor     = trim( $res[2] );
			$apartment = trim( $res[3] );
			return array(
				'street'    => $street_name,
				'number'    => $street_number,
				'floor'     => $floor,
				'apartment' => $apartment,
			);
		}

		// Piso PB
		preg_match( '/^(piso|pso|pis|p)[\.º#]*[ ]*(\w+)$/i', $fl_apt, $res );
		if ( ! empty( $res ) ) {
			$floor = trim( $res[2] );
			return array(
				'street'    => $street_name,
				'number'    => $street_number,
				'floor'     => $floor,
				'apartment' => $apartment,
			);
		}

		// 1420 Piso 8, dpto. A
		preg_match( '/^([\d]+)[ ]+(piso|pso|pis|p)[\.º#]*[ ]*(\w+)[, ]*(departamento|depto|dept|dep|dpto|dpt|dto|apartamento|apto|apt)[\.º#]*[ ]*(\w+)[, ]*/i', $fl_apt, $res );
		if ( ! empty( $res ) ) {
			$street_number = trim( $res[1] );
			$floor         = trim( $res[3] );
			$apartment     = trim( $res[5] );
			return array(
				'street'    => $street_name,
				'number'    => $street_number,
				'floor'     => $floor,
				'apartment' => $apartment,
			);
		}

		// 1420 Piso 8
		preg_match( '/^([\d]+)[ ]+(piso|pso|pis|p)[\.º#]*[ ]*(\w+)[, ]*/i', $fl_apt, $res );
		if ( ! empty( $res ) ) {
			$street_number = trim( $res[1] );
			$floor         = trim( $res[3] );
			return array(
				'street'    => $street_name,
				'number'    => $street_number,
				'floor'     => $floor,
				'apartment' => $apartment,
			);
		}

		// Depto. 5, piso 24
		preg_match( '/^(departamento|depto|dept|dep|dpto|dto|apartamento|apto|apt)[\.º#]*[ ]*(\w+)[, ]*(piso|pso|pis|p)[\.º#]*[ ]*(\w+)[, ]*/i', $fl_apt, $res );
		if ( ! empty( $res ) ) {
			$floor     = trim( $res[4] );
			$apartment = trim( $res[2] );
			return array(
				'street'    => $street_name,
				'number'    => $street_number,
				'floor'     => $floor,
				'apartment' => $apartment,
			);
		}

		// depto 4A
		preg_match( '/^(departamento|depto|dept|dep|dpto|dpt|dto|apartamento|apto|apt)[\.º#]*[ ]*(\w+)$/i', $fl_apt, $res );
		if ( ! empty( $res ) ) {
			$apartment = trim( $res[2] );
			return array(
				'street'    => $street_name,
				'number'    => $street_number,
				'floor'     => $floor,
				'apartment' => $apartment,
			);
		}

		// depto 4 A
		preg_match( '/^(departamento|depto|dept|dep|dpto|dpt|dto|apartamento|apto|apt)[\.º#]*[ ]*(\d+)[ ]([a-z])$/i', $fl_apt, $res );
		if ( ! empty( $res ) ) {
			$floor     = trim( $res[2] );
			$apartment = trim( $res[3] );
			return array(
				'street'    => $street_name,
				'number'    => $street_number,
				'floor'     => $floor,
				'apartment' => $apartment,
			);
		}

		// 2 B
		preg_match( '/^(\d+)[ ]*(\D+)$/i', $fl_apt, $res );
		if ( ! empty( $res ) ) {
			$floor     = trim( $res[1] );
			$apartment = trim( $res[2] );
			return array(
				'street'    => $street_name,
				'number'    => $street_number,
				'floor'     => $floor,
				'apartment' => $apartment,
			);
		}

		// 1200
		preg_match( '/^(\d+)[ ]*$/i', $fl_apt, $res );
		if ( ! empty( $res ) ) {
			$street_number = trim( $res[1] );
			return array(
				'street'    => $street_name,
				'number'    => $street_number,
				'floor'     => $floor,
				'apartment' => $apartment,
			);
		}

		// 1200 4C
		preg_match( '/^(\d+)[ ,]+(\d+)[ ,]*(\w+)$/i', $fl_apt, $res );
		if ( ! empty( $res ) ) {
			$street_number = trim( $res[1] );
			$floor         = trim( $res[2] );
			$apartment     = trim( $res[3] );
			return array(
				'street'    => $street_name,
				'number'    => $street_number,
				'floor'     => $floor,
				'apartment' => $apartment,
			);
		}

		// 1200 4
		preg_match( '/^(\d+)[ ,]+(\w+)$/i', $fl_apt, $res );
		if ( ! empty( $res ) ) {
			$street_number = trim( $res[1] );
			$floor         = trim( $res[2] );
			return array(
				'street'    => $street_name,
				'number'    => $street_number,
				'floor'     => $floor,
				'apartment' => $apartment,
			);
		}

		// I give up. I can't make sense of it. We'll save it in case it's something useful
		return array(
			'street'    => $street_name,
			'number'    => $street_number,
			'floor'     => $fl_apt,
			'apartment' => $apartment,
		);
	}

	public static function get_state_name( string $state_id = '' ): string {
		switch ( $state_id ) {
			case 'C':
				$zone = 'Capital Federal';
				break;
			case 'B':
			default:
				$zone = 'Buenos Aires';
				break;
			case 'K':
				$zone = 'Catamarca';
				break;
			case 'H':
				$zone = 'Chaco';
				break;
			case 'U':
				$zone = 'Chubut';
				break;
			case 'X':
				$zone = 'Córdoba';
				break;
			case 'W':
				$zone = 'Corrientes';
				break;
			case 'E':
				$zone = 'Entre Ríos';
				break;
			case 'P':
				$zone = 'Formosa';
				break;
			case 'Y':
				$zone = 'Jujuy';
				break;
			case 'L':
				$zone = 'La Pampa';
				break;
			case 'F':
				$zone = 'La Rioja';
				break;
			case 'M':
				$zone = 'Mendoza';
				break;
			case 'N':
				$zone = 'Misiónes';
				break;
			case 'Q':
				$zone = 'Neuquén';
				break;
			case 'R':
				$zone = 'Río Negro';
				break;
			case 'A':
				$zone = 'Salta';
				break;
			case 'J':
				$zone = 'San Juan';
				break;
			case 'D':
				$zone = 'San Luis';
				break;
			case 'Z':
				$zone = 'Santa Cruz';
				break;
			case 'S':
				$zone = 'Santa Fe';
				break;
			case 'G':
				$zone = 'Santiago del Estero';
				break;
			case 'V':
				$zone = 'Tierra del Fuego';
				break;
			case 'T':
				$zone = 'Tucumán';
				break;
		}
		return $zone;
	}

	public static function get_items_from_cart( WC_Cart $cart ): Items {
		$items      = array();
		$cart_items = $cart->get_cart();

		/**
		 * @var array{data: WC_Product, quantity: int}[] $cart_items
		 */
		foreach ( $cart_items as $cart_item ) {
			$product = $cart_item['data'];
			if ( $product->is_virtual() ) {
				continue;
			}

			$items[] = self::get_item_from_cart_item( $cart_item );
		}
		if ( empty( $items ) ) {
			throw new Exception( esc_html__( 'No products detected, maybe cart is full of virtual items', 'wc-oca' ) );
		}
		return new Items( $items );
	}

	/**
	 * @param array{data: WC_Product, quantity: int} $cart_item
	 */
	public static function get_item_from_cart_item( array $cart_item ): Item {
		$item = $cart_item['data'];
		if ( ! $item->has_weight() ) {
			throw new Exception( sprintf( esc_html__( 'Product %s with invalid weight in cart', 'wc-oca' ), esc_html( (string) $item->get_id() ) ) );
		}

		$dimension_unit   = 'm'; // By OCA
		$weight_unit      = 'kg'; // By OCA
		$car_item_changes = $item->get_changes();
		/** @var array{price: string} $car_item_changes */

		// Price was modified by external plugin?
		$price = ! empty( $car_item_changes['price'] ) ? $car_item_changes['price'] : $item->get_price();

		$item = new Item(
			wc_get_dimension( (float) $item->get_height(), $dimension_unit ),
			wc_get_dimension( (float) $item->get_width(), $dimension_unit ),
			wc_get_dimension( (float) $item->get_length(), $dimension_unit ),
			wc_get_weight( (float) $item->get_weight(), $weight_unit ),
			(float) $price,
			$item->get_name(),
			$cart_item['quantity'],
			$item->get_id()
		);
		return $item;
	}

	public static function get_items_from_order( WC_Order $order ): Items {
		$items       = array();
		$order_items = $order->get_items();
		/** @var WC_Order_Item_Product $order_item */
		foreach ( $order_items as $order_item ) {
			/** @var WC_Product|false $product */
			$product = $order_item->get_product();
			if ( empty( $product ) || $product->is_virtual() ) {
				continue;
			}
			$item = self::get_item_from_order_item( $order_item, $product );

			// Use single unit price instead of total price.
			$item->set_price( $order->get_item_subtotal( $order_item, true, true ) );

			$items[] = $item;
		}
		if ( empty( $items ) ) {
			throw new Exception( esc_html__( 'No products detected, maybe cart is full of virtual items', 'wc-oca' ) );
		}
		return new Items( $items );
	}

	public static function get_item_from_order_item( WC_Order_Item_Product $order_item, WC_Product $product ): Item {
		if ( ! $product->has_weight() ) {
			throw new Exception( sprintf( esc_html__( 'Product %s with invalid weight in order', 'wc-oca' ), esc_html( (string) $product->get_id() ) ) );
		}

		$dimension_unit = 'm'; // By OCA
		$weight_unit    = 'kg'; // By OCA

		$item = new Item(
			wc_get_dimension( (float) $product->get_height(), $dimension_unit ),
			wc_get_dimension( (float) $product->get_width(), $dimension_unit ),
			wc_get_dimension( (float) $product->get_length(), $dimension_unit ),
			wc_get_weight( (float) $product->get_weight(), $weight_unit ),
			$order_item->get_total(),
			$product->get_name(),
			$order_item->get_quantity(),
			$product->get_id()
		);
		return $item;
	}

	public static function get_order_shipping_method( \WC_Order $order ): ShippingMethod {
		$shipping_methods = $order->get_shipping_methods();
		if ( empty( $shipping_methods ) ) {
			$shipping_methods = array( null );
		}

		$shipping_method = current( $shipping_methods );
		return new ShippingMethod( $shipping_method );
	}

	public static function order_is_sent_with_oca( \WC_Order $order ): bool {
		$shipping_method = self::get_order_shipping_method( $order );
		return $shipping_method->is_oca() && $shipping_method->is_processed();
	}

	public static function remove_accents( string $str, string $charset = 'utf-8' ): string {
		$str = htmlentities( $str, ENT_NOQUOTES, $charset );
		$str = preg_replace( '#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str );
		$str = preg_replace( '#&([A-za-z]{2})(?:lig);#', '\1', $str );
		$str = preg_replace( '#&[^;]+;#', '', $str );
		return $str;
	}
}
