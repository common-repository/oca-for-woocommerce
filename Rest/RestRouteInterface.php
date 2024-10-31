<?php

namespace CRPlugins\Oca\Rest;

defined( 'ABSPATH' ) || exit;

interface RestRouteInterface {
	public function register_routes(): void;
}
