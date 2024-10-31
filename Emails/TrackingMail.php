<?php

namespace CRPlugins\Oca\Emails;

use CRPlugins\Oca\Helper\Helper;
use WC_Email;
use WC_Order;

defined( 'ABSPATH' ) || exit;

class TrackingMail extends WC_Email {

	public function __construct() {
		$this->id             = 'oca_tracking';
		$this->customer_email = true;
		$this->title          = __( 'OCA Tracking', 'wc-oca' );
		$this->description    = __( 'Notification to the customer with his tracking number', 'wc-oca' );
		$this->template_html  = Helper::locate_template( 'tracking-email.php' );
		$this->placeholders   = array();
		$this->manual         = true;

		parent::__construct();
	}

	public function send_email( WC_Order $order ): void {
		$this->setup_locale();

		$email_subject = Helper::get_option( 'tracking_mail_subject', 'Tu orden #{{orden}} ha sido enviada' );

		$this->send(
			$order->get_billing_email(),
			$this->replace_tags( $email_subject, $order ),
			$this->get_mail_content( $order, $email_subject ),
			$this->get_headers(),
			array()
		);

		$this->restore_locale();
	}

	public function get_mail_content( WC_Order $order, string $email_subject ): string {

		$email_body = Helper::get_option( 'tracking_mail_body', 'Tu orden #{{orden}} ha sido enviada con OCA, podés rastrearla usando el siguiente número de envío: {{tracking}}' );

		$email_heading      = $this->replace_tags( $email_subject, $order );
		$email_body         = nl2br( $this->replace_tags( $email_body, $order ) );
		$additional_content = $this->get_additional_content();
		$sent_to_admin      = false;
		$plain_text         = false;
		$email              = $this;

		ob_start();
		require_once Helper::locate_template( 'tracking-email.php' );

		return ob_get_clean();
	}

	protected function replace_tags( string $msg, WC_Order $order ): string {
		$shipping_method = Helper::get_order_shipping_method( $order );

		$msg = str_replace( '{{nombre}}', $order->get_billing_first_name(), $msg );
		$msg = str_replace( '{{nombre_completo}}', $order->get_formatted_billing_full_name(), $msg );
		$msg = str_replace( '{{sitio}}', get_bloginfo( 'name' ), $msg );
		$msg = str_replace( '{{orden}}', $order->get_id(), $msg );
		$msg = str_replace( '{{tracking}}', $shipping_method->get_tracking_number(), $msg );

		return $msg;
	}
}
