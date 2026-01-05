(function ($) {
	'use strict';

	/**
	 * Maneja mensajes en la UI.
	 *
	 * @param {string} selector
	 * @param {string} mensaje
	 * @param {boolean} esError
	 */
	const mostrarMensaje = function (selector, mensaje, esError = false) {
		const contenedor = $(selector);
		contenedor.removeClass('migrador-wpress__error migrador-wpress__ok');
		contenedor.addClass(esError ? 'migrador-wpress__error' : 'migrador-wpress__ok');
		contenedor.text(mensaje);
	};

	$('#migrador-wpress-backup').on('submit', function (evento) {
		evento.preventDefault();
		const salida = '#migrador-wpress-backup-salida';
		mostrarMensaje(salida, MigradorWPress.mensajes.iniciando, false);

		const datos = $(this).serializeArray();
		datos.push({ name: 'action', value: 'migrador_wpress_backup' });

		$.post(MigradorWPress.ajax, datos)
			.done(function (respuesta) {
				if (respuesta.success) {
					const info = respuesta.data;
					let texto = MigradorWPress.mensajes.backupListo + ' ';
					texto += 'Archivo: ' + info.paquete + ' | Checksum: ' + info.checksum;
					mostrarMensaje(salida, texto, false);
				} else {
					mostrarMensaje(salida, MigradorWPress.mensajes.error, true);
				}
			})
			.fail(function () {
				mostrarMensaje(salida, MigradorWPress.mensajes.error, true);
			});
	});

	$('#migrador-wpress-restore').on('submit', function (evento) {
		evento.preventDefault();
		const salida = '#migrador-wpress-restore-salida';
		mostrarMensaje(salida, MigradorWPress.mensajes.iniciando, false);

		const datos = new FormData(this);
		datos.append('action', 'migrador_wpress_restore');

		$.ajax({
			url: MigradorWPress.ajax,
			type: 'POST',
			data: datos,
			processData: false,
			contentType: false,
		})
			.done(function (respuesta) {
				if (respuesta.success) {
					const info = respuesta.data;
					let texto = MigradorWPress.mensajes.restoreListo + ' ';
					if (info.dominio_origen) {
						texto += 'Dominio origen: ' + info.dominio_origen + '. ';
					}
					texto += 'Dominio destino: ' + info.dominio_destino;
					mostrarMensaje(salida, texto, false);
				} else {
					mostrarMensaje(salida, MigradorWPress.mensajes.error, true);
				}
			})
			.fail(function () {
				mostrarMensaje(salida, MigradorWPress.mensajes.error, true);
			});
	});
})(jQuery);
