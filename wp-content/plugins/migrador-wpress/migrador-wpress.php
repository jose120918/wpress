<?php
/**
 * Plugin Name: Migrador WPress
 * Description: Herramienta para generar y restaurar respaldos en formato .wpress con soporte de UI, API y WP-CLI.
 * Version: 0.1.0
 * Author: Equipo Migrador
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MIGRADOR_WPRESS_VERSION' ) ) {
	define( 'MIGRADOR_WPRESS_VERSION', '0.1.0' );
}

if ( ! defined( 'MIGRADOR_WPRESS_RUTA' ) ) {
	define( 'MIGRADOR_WPRESS_RUTA', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'MIGRADOR_WPRESS_URL' ) ) {
	define( 'MIGRADOR_WPRESS_URL', plugin_dir_url( __FILE__ ) );
}

require_once MIGRADOR_WPRESS_RUTA . 'includes/class-migrador-wpress.php';

/**
 * Inicializa el cargador principal.
 */
function migrador_wpress_iniciar() {
	return Migrador_WPress::instancia();
}

add_action( 'plugins_loaded', 'migrador_wpress_iniciar' );
