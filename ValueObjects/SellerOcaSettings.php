<?php

namespace CRPlugins\Oca\ValueObjects;

class SellerOcaSettings {

	/**
	 * @var string
	 */
	private $username;

	/**
	 * @var string
	 */
	private $password;

	/**
	 * @var string
	 */
	private $account_number;

	/**
	 * @var string
	 */
	private $cuit;

	/**
	 * @var ShippingCodes
	 */
	private $shipping_codes;

	public function __construct(
		string $username,
		string $password,
		string $account_number,
		string $cuit,
		ShippingCodes $shipping_codes
	) {
		$this->username       = $username;
		$this->password       = $password;
		$this->account_number = $account_number;
		$this->cuit           = $cuit;
		$this->shipping_codes = $shipping_codes;
	}

	public function get_username(): string {
		return $this->username;
	}

	public function get_password(): string {
		return base64_encode( $this->password );
	}

	public function get_account_number(): string {
		return $this->account_number;
	}

	public function get_cuit(): string {
		return $this->cuit;
	}

	public function get_shipping_codes(): ShippingCodes {
		return $this->shipping_codes;
	}
}
