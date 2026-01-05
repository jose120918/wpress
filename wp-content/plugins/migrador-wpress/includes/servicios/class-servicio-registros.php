<?php
/**
 * Servicio de registros.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maneja escritura de eventos.
 */
class Servicio_Registros {

	/**
	 * Ruta del log.
	 *
	 * @var string
	 */
	private $ruta_log;

	/**
	 * Constructor.
	 *
	 * @param string $ruta_log Ruta donde guardar el registro.
	 */
	public function __construct( $ruta_log ) {
		$this->ruta_log = $ruta_log;
		wp_mkdir_p( dirname( $ruta_log ) );
	}

	/**
	 * Escribe una línea en el registro.
	 *
	 * @param string $mensaje Mensaje a guardar.
	 * @param array  $contexto Datos adicionales.
	 */
	public function registrar( $mensaje, $contexto = array() ) {
		$marca_tiempo = current_time( 'mysql' );
		$linea        = '[' . $marca_tiempo . '] ' . $mensaje;

		if ( ! empty( $contexto ) ) {
			$linea .= ' ' . wp_json_encode( $contexto, JSON_UNESCAPED_SLASHES );
		}

		file_put_contents( $this->ruta_log, $linea . PHP_EOL, FILE_APPEND );
	}

	/**
	 * Lee el registro completo.
	 *
	 * @return string
	 */
	public function leer_registro() {
		if ( ! file_exists( $this->ruta_log ) ) {
			return '';
		}

		return file_get_contents( $this->ruta_log );
	}
}
