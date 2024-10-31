<?php

namespace CRPlugins\Oca\ShippingLabels;

use WC_Order;

defined( 'ABSPATH' ) || exit;

interface LabelsProcessorInterface {
	public function get_label_content( WC_Order $order ): string;
	public function create_label( WC_Order $order, $content = null ): void;
	public function get_label_path( WC_Order $order ): string;
	public function create_label_from_base64( string $base64_data, WC_Order $order ): void;
	public function delete_label( WC_Order $order ): void;
	public function label_exists( WC_Order $order ): bool;
}
