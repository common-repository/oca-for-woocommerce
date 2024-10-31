<?php

namespace CRPlugins\Oca\Api;

use CRPlugins_Oca;

class OcaFilesApi extends Client {

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
	 * @param array<string,string> $body
	 * @param array<string,string> $headers
	 * @return array{method: string, headers: array<string,string>}
	 */
	public function before_request( string $method, array $body, array $headers ): array {
		$headers['X-Agent']       = sprintf( 'oca-woocommerce-plugin/%s', CRPlugins_Oca::PLUGIN_VER );
		$headers['X-Origin']      = get_site_url();
		$headers['Authorization'] = $this->apikey;

		$password                = wp_generate_password( 24 );
		$headers['Content-Type'] = sprintf( 'multipart/form-data; boundary=%s', $password );

		$payload = '';
		// Upload the file
		foreach ( $body as $name => $file_path ) {
			$payload .= sprintf( '--%s', $password );
			$payload .= "\r\n";
			$payload .= sprintf( 'Content-Disposition: form-data; name="%s"; filename="%s"' . "\r\n", $name, basename( $file_path ) );
			$payload .= "\r\n";
			$payload .= file_get_contents( $file_path ); // phpcs:ignore
			$payload .= "\r\n";
		}
		$payload .= sprintf( '--%s--', $password );

		return array(
			'method'  => $method,
			'headers' => $headers,
			'body'    => $payload,
		);
	}

	public function get_base_url(): string {
		return self::PROD_BASE_URL;
	}
}
