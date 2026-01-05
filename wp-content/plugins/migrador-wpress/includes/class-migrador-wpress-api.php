<?php
/**
 * Endpoints REST y AJAX.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestiona las interacciones externas.
 */
class Migrador_WPress_Api {

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
		add_action( 'rest_api_init', array( $this, 'registrar_rest' ) );
		add_action( 'wp_ajax_migrador_wpress_backup', array( $this, 'ajax_backup' ) );
		add_action( 'wp_ajax_migrador_wpress_restore', array( $this, 'ajax_restore' ) );
	}

	/**
	 * Registra rutas REST.
	 */
	public function registrar_rest() {
		register_rest_route(
			'migrador-wpress/v1',
			'/backup',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_backup' ),
				'permission_callback' => array( $this, 'validar_permiso_rest' ),
			)
		);

		register_rest_route(
			'migrador-wpress/v1',
			'/restore',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_restore' ),
				'permission_callback' => array( $this, 'validar_permiso_rest' ),
				'args'                => array(
					'dominio_nuevo' => array(
						'type' => 'string',
					),
				),
			)
		);
	}

	/**
	 * Valida permisos y nonce REST.
	 *
	 * @param WP_REST_Request $request Petición.
	 * @return bool|WP_Error
	 */
	public function validar_permiso_rest( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'sin_permiso', 'No tienes permisos.', array( 'status' => 403 ) );
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'nonce_invalido', 'Nonce no válido.', array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Maneja backup vía REST.
	 *
	 * @param WP_REST_Request $request Petición.
	 * @return WP_REST_Response
	 */
	public function rest_backup( WP_REST_Request $request ) {
		$exclusiones = $this->preparar_exclusiones( $request->get_param( 'exclusiones' ), $request->get_param( 'exclusiones_personalizadas' ) );
		$resultado   = $this->plugin->generar_respaldo( $exclusiones );

		return new WP_REST_Response( $resultado, 200 );
	}

	/**
	 * Maneja restauración vía REST.
	 *
	 * @param WP_REST_Request $request Petición.
	 * @return WP_REST_Response
	 */
	public function rest_restore( WP_REST_Request $request ) {
		$archivos      = $request->get_file_params();
		$dominio_nuevo = sanitize_text_field( (string) $request->get_param( 'dominio_nuevo' ) );

		if ( empty( $archivos['archivo_wpress'] ) || empty( $archivos['archivo_wpress']['tmp_name'] ) ) {
			return new WP_REST_Response( array( 'error' => 'No se recibió archivo .wpress' ), 400 );
		}

		$archivo_subido = $this->mover_archivo_temporal( $archivos['archivo_wpress'] );
		if ( is_wp_error( $archivo_subido ) ) {
			return new WP_REST_Response( array( 'error' => $archivo_subido->get_error_message() ), 400 );
		}

		$resultado = $this->plugin->restaurar_desde_paquete( $archivo_subido, $dominio_nuevo );

		return new WP_REST_Response( $resultado, 200 );
	}

	/**
	 * Maneja backup vía AJAX.
	 */
	public function ajax_backup() {
		check_ajax_referer( 'migrador_wpress_acciones', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'mensaje' => 'No autorizado' ), 403 );
		}

		$exclusiones = $this->preparar_exclusiones( $_POST['exclusiones'] ?? array(), $_POST['exclusiones_personalizadas'] ?? '' );
		$resultado   = $this->plugin->generar_respaldo( $exclusiones );

		wp_send_json_success( $resultado );
	}

	/**
	 * Maneja restauración vía AJAX.
	 */
	public function ajax_restore() {
		check_ajax_referer( 'migrador_wpress_acciones', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'mensaje' => 'No autorizado' ), 403 );
		}

		$dominio_nuevo = isset( $_POST['dominio_nuevo'] ) ? sanitize_text_field( wp_unslash( $_POST['dominio_nuevo'] ) ) : '';
		$archivo       = $_FILES['archivo_wpress'] ?? null;

		if ( ! $archivo || empty( $archivo['tmp_name'] ) ) {
			wp_send_json_error( array( 'mensaje' => 'No se recibió archivo .wpress' ), 400 );
		}

		$archivo_subido = $this->mover_archivo_temporal( $archivo );
		if ( is_wp_error( $archivo_subido ) ) {
			wp_send_json_error( array( 'mensaje' => $archivo_subido->get_error_message() ), 400 );
		}

		$resultado = $this->plugin->restaurar_desde_paquete( $archivo_subido, $dominio_nuevo );

		wp_send_json_success( $resultado );
	}

	/**
	 * Combina y normaliza exclusiones.
	 *
	 * @param array|string $seleccion Lista recibida.
	 * @param string       $lineas    Entradas adicionales.
	 * @return array
	 */
	private function preparar_exclusiones( $seleccion, $lineas ) {
		$exclusiones = array();
		if ( is_array( $seleccion ) ) {
			foreach ( $seleccion as $item ) {
				$exclusiones[] = sanitize_text_field( $item );
			}
		}

		if ( ! empty( $lineas ) ) {
			$lineas = explode( "\n", (string) $lineas );
			foreach ( $lineas as $linea ) {
				$linea = trim( $linea );
				if ( ! empty( $linea ) ) {
					$exclusiones[] = $linea;
				}
			}
		}

		return array_unique( $exclusiones );
	}

	/**
	 * Mueve el archivo subido a staging seguro.
	 *
	 * @param array $archivo Datos del archivo.
	 * @return string|WP_Error
	 */
	private function mover_archivo_temporal( $archivo ) {
		$nombre = isset( $archivo['name'] ) ? sanitize_file_name( $archivo['name'] ) : 'respaldo.wpress';
		if ( '.wpress' !== strtolower( substr( $nombre, -7 ) ) ) {
			return new WP_Error( 'ext_invalida', 'El archivo debe tener extensión .wpress' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$resultado = wp_handle_upload(
			$archivo,
			array(
				'test_form' => false,
			)
		);

		if ( isset( $resultado['error'] ) ) {
			return new WP_Error( 'subida_fallida', $resultado['error'] );
		}

		$destino = trailingslashit( $this->plugin->obtener_ruta_staging() ) . basename( $resultado['file'] );
		wp_mkdir_p( dirname( $destino ) );

		if ( ! rename( $resultado['file'], $destino ) ) {
			return new WP_Error( 'movimiento_fallido', 'No se pudo mover el archivo subido' );
		}

		return $destino;
	}
}
