<?php
/**
 * Comandos WP-CLI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Comandos de respaldo y restauración.
 */
class Migrador_WPress_CLI extends WP_CLI_Command {

	/**
	 * Instancia principal.
	 *
	 * @var Migrador_WPress
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Migrador_WPress $plugin Instancia principal.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Genera un respaldo .wpress.
	 *
	 * ## OPTIONS
	 *
	 * [--excluir=<lista>]
	 * : Directorios a excluir separados por coma.
	 */
	public function backup( $args, $assoc_args ) {
		$exclusiones = array();
		if ( isset( $assoc_args['excluir'] ) ) {
			$exclusiones = array_map( 'trim', explode( ',', $assoc_args['excluir'] ) );
		}

		WP_CLI::log( 'Iniciando backup...' );
		$resultado = $this->plugin->generar_respaldo( $exclusiones );
		WP_CLI::success( 'Backup generado: ' . $resultado['paquete'] );
		WP_CLI::log( 'Checksum: ' . $resultado['checksum'] );
	}

	/**
	 * Restaura un archivo .wpress.
	 *
	 * ## OPTIONS
	 *
	 * <archivo>
	 * : Ruta al archivo .wpress.
	 *
	 * [--dominio=<dominio>]
	 * : Dominio destino para search/replace.
	 */
	public function restore( $args, $assoc_args ) {
		list( $archivo ) = $args;
		$dominio = isset( $assoc_args['dominio'] ) ? $assoc_args['dominio'] : home_url();

		if ( ! file_exists( $archivo ) ) {
			WP_CLI::error( 'El archivo indicado no existe.' );
		}

		WP_CLI::log( 'Restaurando desde ' . $archivo );
		$resultado = $this->plugin->restaurar_desde_paquete( $archivo, $dominio );
		WP_CLI::success( 'Restauración completada hacia ' . $resultado['dominio_destino'] );
	}
}
