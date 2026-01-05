<?php
/**
 * Gestión de la interfaz de administración.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase encargada de la página en Herramientas.
 */
class Migrador_WPress_Admin {

	/**
	 * Referencia al plugin.
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
		add_action( 'admin_menu', array( $this, 'registrar_pagina' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'cargar_recursos' ) );
	}

	/**
	 * Añade la página en Herramientas.
	 */
	public function registrar_pagina() {
		add_management_page(
			'Migrador WPress',
			'Migrador WPress',
			'manage_options',
			'migrador-wpress',
			array( $this, 'renderizar_pantalla' )
		);
	}

	/**
	 * Encola scripts y datos.
	 *
	 * @param string $hook Identificador de la pantalla.
	 */
	public function cargar_recursos( $hook ) {
		if ( 'tools_page_migrador-wpress' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'migrador-wpress-admin',
			MIGRADOR_WPRESS_URL . 'assets/css/admin.css',
			array(),
			MIGRADOR_WPRESS_VERSION
		);

		wp_enqueue_script(
			'migrador-wpress-admin',
			MIGRADOR_WPRESS_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			MIGRADOR_WPRESS_VERSION,
			true
		);

		wp_localize_script(
			'migrador-wpress-admin',
			'MigradorWPress',
			array(
				'ajax'        => admin_url( 'admin-ajax.php' ),
				'rest'        => esc_url_raw( rest_url( 'migrador-wpress/v1' ) ),
				'nonce'       => wp_create_nonce( 'migrador_wpress_acciones' ),
				'restNonce'   => wp_create_nonce( 'wp_rest' ),
				'mensajes'    => array(
					'iniciando'  => 'Procesando, por favor espera…',
					'backupListo' => 'Respaldo generado correctamente.',
					'restoreListo' => 'Restauración completada.',
					'error'       => 'Ha ocurrido un error, revisa el registro.',
				),
			)
		);
	}

	/**
	 * Dibuja la pantalla administrativa.
	 */
	public function renderizar_pantalla() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes.', 'migrador-wpress' ) );
		}

		$nonce = wp_create_nonce( 'migrador_wpress_acciones' );
		?>
		<div class="wrap migrador-wpress">
			<h1><?php esc_html_e( 'Migrador WPress', 'migrador-wpress' ); ?></h1>
			<p><?php esc_html_e( 'Genera y restaura respaldos .wpress desde una interfaz sencilla.', 'migrador-wpress' ); ?></p>
			<div class="migrador-wpress__contenedor">
				<div class="migrador-wpress__bloque">
					<h2><?php esc_html_e( 'Crear backup', 'migrador-wpress' ); ?></h2>
					<form id="migrador-wpress-backup" method="post">
						<input type="hidden" name="accion" value="backup">
						<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
						<p><?php esc_html_e( 'Selecciona qué carpetas omitir:', 'migrador-wpress' ); ?></p>
						<label><input type="checkbox" name="exclusiones[]" value="cache"> wp-content/cache</label><br>
						<label><input type="checkbox" name="exclusiones[]" value="uploads"> wp-content/uploads</label><br>
						<label><input type="checkbox" name="exclusiones[]" value="plugins"> wp-content/plugins</label><br>
						<label><input type="checkbox" name="exclusiones[]" value="themes"> wp-content/themes</label><br>
						<label><input type="checkbox" name="exclusiones[]" value="mu-plugins"> wp-content/mu-plugins</label><br>
						<p>
							<label for="exclusiones_personalizadas"><?php esc_html_e( 'Rutas adicionales (una por línea):', 'migrador-wpress' ); ?></label><br>
							<textarea name="exclusiones_personalizadas" id="exclusiones_personalizadas" rows="3" cols="60"></textarea>
						</p>
						<p>
							<button class="button button-primary" type="submit"><?php esc_html_e( 'Generar .wpress', 'migrador-wpress' ); ?></button>
						</p>
						<div class="migrador-wpress__salida" id="migrador-wpress-backup-salida"></div>
					</form>
				</div>
				<div class="migrador-wpress__bloque">
					<h2><?php esc_html_e( 'Restaurar', 'migrador-wpress' ); ?></h2>
					<form id="migrador-wpress-restore" method="post" enctype="multipart/form-data">
						<input type="hidden" name="accion" value="restore">
						<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
						<p>
							<label for="archivo_wpress"><?php esc_html_e( 'Archivo .wpress:', 'migrador-wpress' ); ?></label><br>
							<input type="file" name="archivo_wpress" id="archivo_wpress" accept=".wpress" required>
						</p>
						<p>
							<label for="dominio_nuevo"><?php esc_html_e( 'Dominio destino (para search/replace):', 'migrador-wpress' ); ?></label><br>
							<input type="url" name="dominio_nuevo" id="dominio_nuevo" class="regular-text" placeholder="https://ejemplo.com" required>
						</p>
						<p>
							<button class="button button-primary" type="submit"><?php esc_html_e( 'Restaurar', 'migrador-wpress' ); ?></button>
						</p>
						<div class="migrador-wpress__salida" id="migrador-wpress-restore-salida"></div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
}
