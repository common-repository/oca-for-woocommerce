<?php

namespace CRPlugins\Oca\Api;

use CRPlugins_Oca;

class OcaApi extends Client {

	public const PROD_BASE_URL = 'https://ocaapi.crplugins.com.ar/api/v2';

	/**
	 * @var string
	 */
	private $apikey;

	public function __construct( string $apikey ) {
		$this->apikey = $apikey;
	}

	/**
	 * @param string $method
	 * @param mixed[] $body
	 * @param array<string,string> $headers
	 * @return array{method: string, headers: array<string,string>}
	 */
	public function before_request( string $method, array $body, array $headers ): array {
		$headers['X-Agent']       = sprintf( 'oca-woocommerce-plugin/%s', CRPlugins_Oca::PLUGIN_VER );
		$headers['X-Origin']      = get_site_url();
		$headers['Authorization'] = $this->apikey;

		return parent::before_request( $method, $body, $headers );
	}

	public function get_base_url(): string {
		return self::PROD_BASE_URL;
	}

	public function get_api_key(): string {
		return $this->apikey;
	}
}
