<?php

namespace CRPlugins\Oca\Helper;

trait AssetsTrait {

	public static function get_assets_folder_url(): string {
		return plugin_dir_url( \CRPlugins_Oca::MAIN_FILE ) . 'assets/dist';
	}

	public static function get_blocks_assets_folder_url(): string {
		return plugin_dir_url( \CRPlugins_Oca::MAIN_FILE ) . 'blocks';
	}

	public static function get_labels_folder_path(): string {
		return plugin_dir_path( \CRPlugins_Oca::MAIN_FILE ) . 'labels';
	}

	public static function get_labels_folder_url(): string {
		return plugin_dir_url( \CRPlugins_Oca::MAIN_FILE ) . 'labels';
	}

	public static function get_libs_folder_path(): string {
		return plugin_dir_path( \CRPlugins_Oca::MAIN_FILE ) . 'libs';
	}

	public static function get_uploads_dir(): string {
		return wp_get_upload_dir()['basedir'] . '/oca-for-woocommerce';
	}

	public static function locate_template( string $template_name ): string {

		// Set variable to search in the templates folder of theme.
		$template_path = 'templates/';

		// Set default plugin templates path.
		$default_path = plugin_dir_path( \CRPlugins_Oca::MAIN_FILE ) . 'templates/';

		// Search template file in theme folder.
		$template = locate_template(
			array(
				'oca-for-woocommerce/templates/' . $template_name,
				$template_name,
			)
		);

		// Get plugins template file.
		if ( ! $template ) {
			$template = $default_path . $template_name;
		}

		return apply_filters( 'wc_oca_locate_template', $template, $template_name, $template_path, $default_path );
	}
}
