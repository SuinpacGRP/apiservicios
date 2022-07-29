<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
*/

Route::get('', function () { return "API SUINPAC V 1.0"; })->middleware('cors');
Route::get('logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index');

##========================================================================================
##                                     ╔═╗╔═╗╔═╗╦╔═╗                                    ##
##                                     ╚═╗║╣ ╠═╝║╠╣                                     ##
##                                     ╚═╝╚═╝╩  ╩╚                                     ##
##========================================================================================
Route::post('empleados_sepif', 'PruebaController@datosSEPIF')->middleware( 'jwt');

##========================================================================================##
##                     ╦═╗╦ ╦╔╦╗╔═╗╔═╗  ╔╦╗╔═╗  ╔═╗╦═╗╦ ╦╔═╗╔╗ ╔═╗╔═╗                     ##
##                     ╠╦╝║ ║ ║ ╠═╣╚═╗   ║║║╣   ╠═╝╠╦╝║ ║║╣ ╠╩╗╠═╣╚═╗                     ##
##                     ╩╚═╚═╝ ╩ ╩ ╩╚═╝  ═╩╝╚═╝  ╩  ╩╚═╚═╝╚═╝╚═╝╩ ╩╚═╝                     ##
##========================================================================================##

Route::get('add_log', 'PruebaController@addLog');

Route::post('profile', 'PruebaController@upload');

Route::post('conectarBase','ConexionController@conectarBase');
Route::get('nombreBase','ConexionController@nombreBase');
Route::post('pruebaDB','PruebaController@pruebaDB');
Route::post('pruebaSubir','PruebaController@pruebaSubir');
Route::get('datos', 'PruebaController@datos');
Route::get('user', 'PruebaController@getUser');
Route::get('getToken', 'PruebaController@getToken');
Route::get('getDatos', 'PruebaController@getDatos');
Route::post('datosPost', 'PruebaController@datosPost');
Route::post('refreshToken', 'PruebaController@getToken');
Route::get('error', function(){
    abort(500);
});
Route::get('customToken', 'PruebaController@customToken');
Route::get('getCustomToken', 'PruebaController@getCustomToken');
Route::get('getUserAuth', 'PruebaController@getUserAuth');
Route::post('getPersona', 'PruebaController@getPersona');

//! +-+-+-+-+-+ +-+-+-+-+ +-+-+ +-+-+-+-+-+-+ +-+-+ +-+-+-+-+-+-+
//! |R|u|t|a|s| |p|a|r|a| |e|l| |i|n|i|c|i|o| |d|e| |s|e|s|i|o|n|
//! +-+-+-+-+-+ +-+-+-+-+ +-+-+ +-+-+-+-+-+-+ +-+-+ +-+-+-+-+-+-+
Route::group(['prefix' => 'auth'],function () {
    Route::post('me',      'AuthController@me');
    Route::post('login',   'AuthController@login');
    Route::post('loggin',  'AuthController@loggin');
    Route::post('logout',  'AuthController@logout');
    Route::post('refresh', 'AuthController@refresh');
    Route::post('payload', 'AuthController@payload');
});   

//! +-+-+-+-+-+ +-+-+-+-+ +-+-+ +-+-+-+-+-+-+ +-+-+ +-+-+-+-+
//! |R|u|t|a|s| |p|a|r|a| |e|l| |p|a|d|r|o|n| |d|e| |a|g|u|a|
//! +-+-+-+-+-+ +-+-+-+-+ +-+-+ +-+-+-+-+-+-+ +-+-+ +-+-+-+-+
Route::group(['prefix' => 'padronAgua'], function () {
    /*Route::get('getPadronAgua/{cliente}/{query}', 'PadronAguaPotableController@padrones');
    Route::get('getPadronLecturas/{id}', 'PadronAguaLecturaController@padronLecturas');
    Route::post('registrarLectura', 'PadronAguaLecturaController@registrarLectura');*/

    Route::get('getImagenCliente/{cliente}', 'ExtrasController@getImagenCliente');

    Route::get('getPadronAnomalias',     'PadronAguaLecturaController@anomalias');
    Route::post('registrarLectura',      'PadronAguaLecturaController@registrarLectura');
    Route::get('getPadronLecturas/{id}', 'PadronAguaLecturaController@padronLecturas');

    Route::get('getPadronAgua/{cliente}/{query}', 'PadronAguaPotableController@padrones');
});

//! +-+-+-+-+-+ +-+-+-+-+ +-+-+ +-+-+-+-+-+-+-+-+
//! |R|u|t|a|s| |p|a|r|a| |e|l| |c|h|e|c|a|d|o|r|
//! +-+-+-+-+-+ +-+-+-+-+ +-+-+ +-+-+-+-+-+-+-+-+
Route::group(['prefix' => 'checador'],function () {
    Route::get('getToken',   'Checador\ChecadorController@getToken');
    Route::post('registrar', 'Checador\ChecadorController@registrar');
    Route::post('getEmpleado',   'Checador\ChecadorController@verificarUsuario');
    Route::post('registrar-asistencia',   'Checador\ChecadorController@registrarAsistencia');
    Route::post('historial-asistencia',   'Checador\ChecadorController@historialAsistencia');
    Route::post('urlLogo',   'Checador\ChecadorController@obtenerURLLogo');
    Route::get('fecha','Checador\ChecadorController@validarDiaRegistro');

    Route::post('registro-asistencia','Checador\ChecadorController@asistencias');
    Route::post('getChecadores','Checador\ChecadorController@getDatosChecador');

    Route::post('registrar-asistencia-delchecador','Checador\ChecadorController@registrarAsistenciaChecador');

    Route::post('get-clientes-movil','Checador\ChecadorController@getClientes');
    Route::post('get-clientes-checador','Checador\ChecadorController@getClientesChecador');
    Route::post('get-retardos-faltas','Checador\ChecadorController@getRetardosYfaltas');
    Route::post('get-retardos-faltas-v2','Checador\ChecadorController@get_retardos_y_faltas');


    // Route::post('auth','Checador\AsistenciaController@verificar_usuario_sistema');
    // Route::post('historial-asistencias','Checador\AsistenciaController@historialAsistencia');


    // Route::post('ejemplo2',function(){
    //     return "ejemplo2 en api";
    // });

});

//! +-+-+-+-+-+ +-+-+-+-+ +-+-+ +-+-+-+-+-+-+ +-+-+ +-+-+-+-+-+
//! |R|u|t|a|s| |p|a|r|a| |e|l| |p|o|r|t|a|l| |d|e| |p|a|g|o|s|
//! +-+-+-+-+-+ +-+-+-+-+ +-+-+ +-+-+-+-+-+-+ +-+-+ +-+-+-+-+-+
Route::group(['prefix' => 'portal'], function () {
    Route::post('existeContrato', 'PortalPago\PortalController@existeCuenta');
    Route::post('existe', 'PortalPago\PortalController@existe');
    Route::post('historial', 'PortalPago\PortalController@historial');
    Route::post('adeudo', 'PortalPago\PortalController@adeudo');

    Route::post('consumo', 'PortalPago\PortalController@consumo');
    Route::post('recibo', 'PortalPago\PortalController@recibo');

    Route::post('getClientesServicio', 'PortalPago\PortalController@getClientesServicio');//devuelve un arreglo con los clientes que tienen servicio en linea
    Route::post('pago', 'PortalPago\PortalController@pago');
    Route::post('pagosHistorial', 'PortalPago\PortalController@pagosHistorial');
    Route::post('getImagen', 'PortalPago\PortalController@getImagen');
    #Route::post('getServicios', 'PortalPago\PortalController@getServicios');
    Route::post('getImagenCopia', 'PortalPago\PortalController@getImagenCopia');
    Route::post('getImagenes', 'PortalPago\PortalController@getImagenes');
    Route::post('getClientes', 'PortalPago\PortalController@getClientes');
    Route::post('getClienteNombre', 'PortalPago\PortalController@getClienteNombre');
    Route::post('getClienteByNombre', 'PortalPago\PortalController@getClienteByNombre');
    Route::post('getNombreCliente', 'PortalPago\PortalController@getNombreCliente');
    Route::post('reciboIndividual', 'PortalPago\PortalController@reciboIndividual');
    Route::post('pagoCaja', 'PortalPago\PortalController@pagoCaja');
    Route::post('postSuinpacCaja', 'PortalPago\PortalController@postSuinpacCaja');
    Route::post('postSuinpacCajaTest', 'PortalPago\PortalController@postSuinpacCajaTest');
    Route::post('postSuinpacCajaPagoAnual', 'PortalPago\PortalController@postSuinpacCajaPagoAnual');
    Route::post('postSuinpacCajaPagoAnualCopia', 'PortalPago\PortalController@postSuinpacCajaPagoAnualCopia');
    Route::post('postSuinpacCajaRamon', 'PortalPago\PortalController@postSuinpacCajaRamon');
    Route::post('listadoAdeudoPagar', 'PortalPago\PortalController@listadoAdeudoPagar');
    Route::post('listadoAdeudoPagarEjecucionFiscal', 'PortalPago\PortalController@listadoAdeudoPagarEjecucionFiscal');


    Route::post('postSuinpacCajaListaAdeudo', 'PortalPago\PortalController@postSuinpacCajaListaAdeudo');
    Route::post('postSuinpacCajaListaAdeudoISAI', 'PortalPago\PortalController@postSuinpacCajaListaAdeudoISAI');
    Route::post('postSuinpacCajaListaAdeudoPredialZofemat', 'PortalPago\PortalController@postSuinpacCajaListaAdeudoPredialZofemat');
    Route::post('postSuinpacCajaListaAdeudoV2Ccdn', 'PortalPago\PortalController@postSuinpacCajaListaAdeudoV2Ccdn');

    Route::post('postSuinpacCajaListaAdeudoAnterior', 'PortalPago\PortalController@postSuinpacCajaListaAdeudoAnterior');
    Route::post('comprobanteDePago', 'PortalPago\PortalController@comprobanteDePago');
    Route::post('comprobanteDePagoDos', 'PortalPago\PortalController@comprobanteDePagoDos');
    Route::post('comprobanteDePagoV2', 'PortalPago\PortalController@comprobanteDePagoV2');
    Route::post('listadoServicios', 'PortalPago\PortalController@listadoServicios');
    Route::post('firmarDocumento', 'PortalPago\PortalController@firmarDocumento');
    Route::post('descargarXML', 'PortalPago\PortalController@descargarXML');
    Route::post('obtnerFacturaPagoLinea', 'PortalPago\PortalController@obtnerFacturaPagoLinea');
    Route::post('obtnerFacturaPagoLineaCopiaDos', 'PortalPago\PortalController@obtnerFacturaPagoLineaCopiaDos');
    Route::post('obtnerFacturaPagoLineaV2', 'PortalPago\PortalController@obtnerFacturaPagoLineaV2');
    Route::post('buscarCotizacionPorFolio', 'PortalPago\PortalController@buscarCotizacionPorFolio');
    Route::post('modificarCorreoContribuyente', 'PortalPago\PortalController@modificarCorreoContribuyente');
    Route::post('modificarDatoPagoEnLinea', 'PortalPago\PortalController@modificarDatoPagoEnLinea');
    Route::post('buscarContribuyente', 'PortalPago\PortalController@buscarContribuyente');
    Route::post('buscarContribuyenteCopia', 'PortalPago\PortalController@buscarContribuyenteCopia');
    Route::post('calcularTotalCotizacion', 'PortalPago\CotizacionServiciosPredialController@calcularTotalCotizacion');
    //
    Route::post('calcularTotalCotizacionCopia', 'PortalPago\CotizacionServiciosPredialController@calcularTotalCotizacionCopia');
    Route::post('obtenerURLEstadoCuentaAnual', 'PortalPago\PortalController@obtenerURLEstadoCuentaAnual');
    Route::post('formarReciboAnual', 'PortalPago\PortalController@formarReciboAnual');


    Route::post('postSuinpacCajaCopia', 'PortalPago\PortalController@postSuinpacCajaCopia');
    Route::post('postSuinpacCajaCopiaV2', 'PortalPago\PortalController@postSuinpacCajaCopiaV2');

    Route::post('postSuinpacCajaCopiazofemat', 'PortalPago\PortalController@postSuinpacCajaCopiazofemat');
    Route::post('postSuinpacCajaCopiaDos', 'PortalPago\PortalController@postSuinpacCajaCopiaDos');

    Route::post('prueba', 'PortalPago\PortalController@pruebaCaja');
    Route::post('postSuinpacCajaNuevo', 'PortalPago\PortalController@postSuinpacCajaNuevo');


    Route::get('obtenerPersonalidadJuridica', 'PortalPago\PortalController@obtenerPersonalidadJuridica');

    Route::get('obtenerPagosReferenciadosRegistrados', 'PortalPago\PortalController@obtenerPagosReferenciadosRegistrados');

    //ruta agregada para pago de ISAI

    Route::post('obtenerFolioCotizacion', 'PortalPago\PortalController@obtenerFolioCotizacion');
    Route::post('obtenerEstatusPadronCatastral', 'PortalPago\PortalController@obtenerEstatusPadronCatastral');
    Route::post('obtenerDatosPropietario', 'PortalPago\PortalController@obtenerDatosPropietario');
    Route::post('listadoAdeudoPagarCopia', 'PortalPago\PortalController@listadoAdeudoPagarCopia');
    Route::post('listadoAdeudoPagarISAI', 'PortalPago\PortalController@listadoAdeudoPagarISAI');

    Route::post('comprobanteDePagoCopia', 'PortalPago\PortalController@comprobanteDePagoCopia');
    Route::post('obtnerFacturaPagoLineaCopia', 'PortalPago\PortalController@obtnerFacturaPagoLineaCopia');

    Route::post('firmarDocumentoCopia', 'PortalPago\PortalController@firmarDocumentoCopia');
    Route::post('firmarDocumentoV2', 'PortalPago\PortalController@firmarDocumentoV2');
    Route::post('moverArchivo', 'PortalPago\PortalController@moverArchivo');

    Route::post('postSuinpacCajaReferenciado', 'PortalPago\PortalController@postSuinpacCajaReferenciado');
    Route::post('obtnerFacturaPagoLineaV2Copia', 'PortalPago\PortalController@obtnerFacturaPagoLineaV2Copia');

    Route::post('postSuinpacCajaReferenciado', 'PortalPago\PortalController@postSuinpacCajaReferenciado');

    Route::post('modificarEstatusReferenciado', 'PortalPago\PortalController@modificarEstatusReferenciado');

    Route::get('pruebaWebHook', 'PortalPago\PortalController@pruebaWebHook');
});

//! +-+-+-+-+-+ +-+-+-+-+ +-+-+ +-+-+-+-+-+-+ +-+-+ +-+-+-+-+-+
//! |R|u|t|a|s| |p|a|r|a| |e|l| |p|o|r|t|a|l| |d|e| |n|o|t|a|r|i|os
//! +-+-+-+-+-+ +-+-+-+-+ +-+-+ +-+-+-+-+-+-+ +-+-+ +-+-+-+-+-+
Route::group(['middleware' => ['throttle:10000,1'],'prefix' => 'portalnotarios'], function () {
    Route::post('validarAcceso', 'PortalNotarios\PortalNotariosController@validarAcceso');

    Route::post('agregraObservacion', 'PortalNotarios\PortalNotariosController@agregraObservacion');
    Route::post('modificarObservacion', 'PortalNotarios\PortalNotariosController@modificarObservacion');

    Route::post('obtenerDocumentosAyuntamiento', 'PortalNotarios\PortalNotariosController@obtenerDocumentosAyuntamiento');
    Route::post('obtenerDocumentosAyuntamiento2', 'PortalNotarios\PortalNotariosController@obtenerDocumentosAyuntamiento2');
    Route::post('obtenerDocumentosNotarios', 'PortalNotarios\PortalNotariosController@obtenerDocumentosNotarios');
    Route::post('getObservacion', 'PortalNotarios\PortalNotariosController@getObservacion');
    Route::post('subirArchivo', 'PortalNotarios\PortalNotariosController@subirArchivo');
    Route::post('getStatusDocumento', 'PortalNotarios\PortalNotariosController@getStatusDocumento');
    Route::post('modificarExtrasISAI', 'PortalNotarios\PortalNotariosController@modificarExtrasISAI');
    Route::post('getDatosExtra', 'PortalNotarios\PortalNotariosController@getDatosExtra');
    Route::post('modificarStatusDocumento', 'PortalNotarios\PortalNotariosController@modificarStatusDocumento');
    Route::post('eliminarObservacion', 'PortalNotarios\PortalNotariosController@eliminarObservacion');
    Route::post('obtenerDocumentosCatalogo', 'PortalNotarios\PortalNotariosController@obtenerDocumentosCatalogo');
    Route::post('obtenerDatosComprador', 'PortalNotarios\PortalNotariosController@obtenerDatosComprador');
    Route::post('obtenerDatosFiscales', 'PortalNotarios\PortalNotariosController@obtenerDatosFiscales');
    Route::post('obtenerRegimen', 'PortalNotarios\PortalNotariosController@obtenerRegimen');
    Route::post('modificarDatosFiscales', 'PortalNotarios\PortalNotariosController@modificarDatosFiscales');
    Route::post('obtenerObservacionesPendientes', 'PortalNotarios\PortalNotariosController@obtenerObservacionesPendientes');
    Route::post('estatusTramite', 'PortalNotarios\PortalNotariosController@estatusTramite');
    Route::post('totalISAI', 'PortalNotarios\PortalNotariosController@totalISAI');
    Route::post('obtenerDocumentoNotarios', 'PortalNotarios\PortalNotariosController@obtenerDocumentoNotarios');
    Route::post('getStatusDocumentoPrueba', 'PortalNotarios\PortalNotariosController@getStatusDocumentoPrueba');
    Route::post('obtenerPuntosCardinales', 'PortalNotarios\PortalNotariosController@obtenerPuntosCardinales');
    Route::post('eliminarColindancia', 'PortalNotarios\PortalNotariosController@eliminarColindancia');
    Route::post('modificarColindancia', 'PortalNotarios\PortalNotariosController@modificarColindancia');
    Route::post('addColindancia', 'PortalNotarios\PortalNotariosController@addColindancia');
    Route::post('obtenerColindancia', 'PortalNotarios\PortalNotariosController@obtenerColindancia');
    Route::post('formaPagada', 'PortalNotarios\PortalNotariosController@formaPagada');
    Route::post('obtenerCertificadoCatastral', 'PortalNotarios\PortalNotariosController@obtenerCertificadoCatastral');
    Route::post('getClienteNombre', 'PortalNotarios\PortalNotariosController@getClienteNombre');
    Route::post('obtenerDocumentosCatalogo2', 'PortalNotarios\PortalNotariosController@obtenerDocumentosCatalogo2');
    Route::post('obtenerDocumentosAyuntamientoMario', 'PortalNotarios\PortalNotariosController@obtenerDocumentosAyuntamientoMario');
    Route::post('getDatosUbicacion', 'PortalNotarios\PortalNotariosController@getDatosUbicacion');
    Route::post('modificarDatosContactoISAI', 'PortalNotarios\PortalNotariosController@modificarDatosContactoISAI');
    Route::post('getDatosContacto', 'PortalNotarios\PortalNotariosController@getDatosContacto');
    Route::post('insertarNotificacion', 'PortalNotarios\PortalNotariosController@insertarNotificacion');
    Route::post('obtenerOrdenPagoISAI', 'PortalNotarios\PortalNotariosController@obtenerOrdenPagoISAI');

    Route::post('ValidarPredioAptoParaTramiteISAI', 'PortalNotarios\PortalNotariosController@ValidarPredioAptoParaTramiteISAI');
    Route::post('getDatosExtras', 'PortalNotarios\PortalNotariosController@getDatosExtras');

    Route::post('validarAccesoCambioCorreo', 'PortalNotarios\PortalNotariosController@validarAccesoCambioCorreo');
    Route::post('getCorreo_Celular', 'PortalNotarios\PortalNotariosController@getCorreo_Celular');
    Route::post('modificarDatosContactoISAIYContribuyente', 'PortalNotarios\PortalNotariosController@modificarDatosContactoISAIYContribuyente');

    Route::post('estatusTramiteCopia', 'PortalNotarios\PortalNotariosController@estatusTramiteCopia');
    Route::post('obtenerDatosFiscalesCopia', 'PortalNotarios\PortalNotariosController@obtenerDatosFiscalesCopia');
    



//!--------------------------------------------------------
//!RUTAS PARA EL PORTAL LICENCIAS DE FUNCIONAMIENTO
//!--------------------------------------------------------    
Route::post('getAccesoLicenciasDeFuncionamiento', 'PortalLicencias\PortalLicenciasController@getAccesoLicenciasDeFuncionamiento');
Route::post('getGirosCliente', 'PortalLicencias\PortalLicenciasController@getGirosCliente');
Route::post('getLicalidadesPorCliente', 'PortalLicencias\PortalLicenciasController@getLicalidadesPorCliente');
Route::post('getColonias', 'PortalLicencias\PortalLicenciasController@getColonias');
Route::post('getMunicipioPorCliente', 'PortalLicencias\PortalLicenciasController@getMunicipioPorCliente');
Route::post('getColoniasPorCliente', 'PortalLicencias\PortalLicenciasController@getColoniasPorCliente');

Route::post('getPaisporCliente', 'PortalLicencias\PortalLicenciasController@getPaisporCliente');
Route::post('getEntidadPorCliente', 'PortalLicencias\PortalLicenciasController@getEntidadPorCliente');
Route::post('getPersonalidadJuridicaPorCliente', 'PortalLicencias\PortalLicenciasController@getPersonalidadJuridicaPorCliente');
Route::post('getLocalidadesPorClienteFiscal', 'PortalLicencias\PortalLicenciasController@getLocalidadesPorClienteFiscal');
Route::post('getRegimenFiscal', 'PortalLicencias\PortalLicenciasController@getRegimenFiscal');

Route::post('getDatosPredial', 'PortalLicencias\PortalLicenciasController@getDatosPredial');

Route::post('getContribuyenteByRfcCurp', 'PortalLicencias\PortalLicenciasController@getContribuyenteByRfcCurp');
Route::post('getPersonalidadJuridica', 'PortalLicencias\PortalLicenciasController@getPersonalidadJuridica');
//Insertar alta licencia
Route::post('postInsertAltaLicencia', 'PortalLicencias\PortalLicenciasController@postInsertAltaLicencia');
Route::post('postInsertAltaLicenciaRefrendo', 'PortalLicencias\PortalLicenciasController@postInsertAltaLicenciaRefrendo');
Route::post('getLicenciasByContribuyente', 'PortalLicencias\PortalLicenciasController@getLicenciasByContribuyente');
Route::post('getLicenciasEnProcesByContribuyente', 'PortalLicencias\PortalLicenciasController@getLicenciasEnProcesByContribuyente');
Route::post('postInsertAltaContribuyente', 'PortalLicencias\PortalLicenciasController@postInsertAltaContribuyente');
Route::post('getSolicitudLicencia', 'PortalLicencias\PortalLicenciasController@getSolicitudLicencia');
Route::post('getSolicitudLicenciasDocumentacionRequerida', 'PortalLicencias\PortalLicenciasController@getSolicitudLicenciasDocumentacionRequerida');
Route::post('getDocumentosByAltaLicenciaId', 'PortalLicencias\PortalLicenciasController@getDocumentosByAltaLicenciaId');
Route::post('resubirDocumentosv2', 'PortalLicencias\PortalLicenciasController@resubirDocumentosv2');
Route::post('resubirSoloDocumentosv2EnElRefrendo', 'PortalLicencias\PortalLicenciasController@resubirSoloDocumentosv2EnElRefrendo');
Route::post('getContribuyenteByRFCIndex', 'PortalLicencias\PortalLicenciasController@getContribuyenteByRFCIndex');
Route::post('getContribuyenteByRFCFOLIO', 'PortalLicencias\PortalLicenciasController@getContribuyenteByRFCFOLIO');
Route::post('getContribuyenteByRFCFOLIOAnterior', 'PortalLicencias\PortalLicenciasController@getContribuyenteByRFCFOLIOAnterior');
Route::post('ObtenerCartaResponsivaProteccionCivil', 'PortalLicencias\PortalLicenciasController@ObtenerCartaResponsivaProteccionCivil');
Route::post('getEstatusRevalidacion', 'PortalLicencias\PortalLicenciasController@getEstatusRevalidacion');


///para hacer la prueba de subida de documentos a la api
Route::post('PruebaFotos', 'PortalLicencias\PortalLicenciasController@PruebaFotos');

Route::post('ObtenerPDFLicencia', 'PortalLicencias\PortalLicenciasController@ObtenerPDFLicencia');
Route::post('ObtenerPDFLicenciasPrincipal', 'PortalLicencias\PortalLicenciasController@ObtenerPDFLicenciasPrincipal');

////! rutas licencias de alto mediano y bajo impacto
//Listado de rutas para las licencias con alto bajo y mediano impacto
Route::post('getContribuyenteByRFCFOLIOAltoMedioBajo', 'PortalLicencias\PortalLicenciasController@getContribuyenteByRFCFOLIOAltoMedioBajo');
Route::post('getSolicitudLicenciasDocumentacionRequeridaAltoMedianoBajo', 'PortalLicencias\PortalLicenciasController@getSolicitudLicenciasDocumentacionRequeridaAltoMedianoBajo');
Route::post('postInsertAltaLicenciaRefrendoAltaMediaBajo', 'PortalLicencias\PortalLicenciasController@postInsertAltaLicenciaRefrendoAltaMediaBajo');
Route::post('postInsertAltaLicenciaAltaMediaBajo', 'PortalLicencias\PortalLicenciasController@postInsertAltaLicenciaAltaMediaBajo');
Route::post('RegenerarSolicitudPDFLicencia', 'PortalLicencias\PortalLicenciasController@RegenerarSolicitudPDFLicencia');
Route::post('RegenerarSolicitudPDFLicenciaAltoMedioBajo', 'PortalLicencias\PortalLicenciasController@RegenerarSolicitudPDFLicenciaAltoMedioBajo');
Route::post('updateContribuyente', 'PortalLicencias\PortalLicenciasController@updateContribuyente');

//! Insercion de la encuaste
Route::post('postInsertEncuesta', 'PortalLicencias\PortalLicenciasController@postInsertEncuesta');
});
Route::group([ 'prefix' => 'PortalLicencias'], function() {
    Route::post('getTamanios','PortalLicencias\PortalLicenciasController@ObtenerTamanio');
    Route::post('getClaves','PortalLicencias\PortalLicenciasController@ObtenerClaves');
});


//! +-+-+-+-+-+ +-+-+-+-+ +-+-+ +-+-+-+-+-+-+ +-+-+ +-+-+-+-+-+
//! |R|u|t|a|s| |p|a|r|a| |e|l| |p|o|r|t|a|l| |d|e| pago en linea
//! +-+-+-+-+-+ +-+-+-+-+ +-+-+ +-+-+-+-+-+-+ +-+-+ +-+-+-+-+-+
Route::group(['prefix' => 'portalpagopredial'], function () {
    #validarCuentaZofemat
    Route::post('validarCuentaPredial', 'PortalPago\PredialController@validarCuentaPredial');
    Route::post('validarCuentaPredialConISAI', 'PortalPago\PredialController@validarCuentaPredialConISAI');
    Route::post('validarCuentaPredialPruebas', 'PortalPago\PredialController@validarCuentaPredialPruebas');
    Route::post('validarCuentaZofemat', 'PortalPago\PredialController@validarCuentaZofemat');
    Route::post('validarCuentaPredialAnterior', 'PortalPago\PredialController@validarCuentaPredialAnterior');
    Route::post('buscarCoopropietario', 'PortalPago\PredialController@buscarCoopropietario');
    Route::post('estatusTramite', 'PortalPago\PredialController@estatusTramite');
    Route::post('historialPredial', 'PortalPago\PredialController@historialPredial');
    Route::post('GeneraEstadoDeCuenta', 'PortalPago\PredialController@GeneraEstadoDeCuenta');
    Route::post('historialPredialAdeudo', 'PortalPago\PredialController@historialPredialAdeudo');
    Route::post('obtenerDatosFiscales', 'PortalPago\PredialController@obtenerDatosFiscales');
    Route::post('obtenerEstadoDeCuentaPredial', 'PortalPago\PredialController@obtenerEstadoDeCuentaPredial');
    Route::post('obtenerEstadoDeCuentaPredialSuinpac', 'PortalPago\PredialController@obtenerEstadoDeCuentaPredialSuinpac');
    Route::post('cotizacionServiciosPredial', 'PortalPago\CotizacionServiciosPredialController@cotizacionServiciosPredial');
    Route::post('pruebas', 'PortalPago\CotizacionServiciosPredialController@pruebas');
    //este es solo para poder hacer un pago que no se puede de otra manera
    Route::post('cotizacionServiciosPredial_recti', 'PortalPago\CotizacionServiciosPredialCDCrectificarController@CotizacionServiciosPredialCDCrectificar');
    ///

    Route::get('buscarTipoDeslinde', 'PortalPago\PredialController@buscarTipoDeslinde');
    Route::post('obtenerEstadoDeCuentaPredialCopia', 'PortalPago\PredialController@obtenerEstadoDeCuentaPredialCopia');
    Route::post('obtenerDatosEjecucionFiscalV2', 'PortalPago\PredialController@obtenerDatosEjecucionFiscalV2');
    Route::post('EjecucionFiscalCajaV2', 'PortalPago\PredialController@EjecucionFiscalCajaV2');

    Route::post('obtenerContribuyente', 'PortalPago\CotizacionServiciosPredialController@obtenerContribuyente');
    Route::post('obtenerContribuyenteCopia', 'PortalPago\CotizacionServiciosPredialController@obtenerContribuyenteCopia');
});

//! +-+-+-+-+-+ +-+-+-+-+ +-+-+ +-+-+-+-+-+-+ +-+-+ +-+-+-+-+-+
//! |R|u|t|a|s| |p|a|r|a| |e|l| |p|o|r|t|a|l| |d|e| pago en linea agua
//! +-+-+-+-+-+ +-+-+-+-+ +-+-+ +-+-+-+-+-+-+ +-+-+ +-+-+-+-+-+
Route::group(['prefix' => 'portalpagoagua'], function () {
    Route::post('validarExisteCuentaAgua', 'PortalPago\AguaController@validarExisteCuentaAgua');
    Route::post('validarExisteCuentaAguaCopia', 'PortalPago\AguaController@validarExisteCuentaAguaCopia');
    Route::post('estadoCuentaAgua', 'PortalPago\AguaController@estadoCuentaAgua');
    Route::post('estadoCuentaAguaCopia', 'PortalPago\AguaController@estadoCuentaAguaCopia');
    Route::post('cotizarServiciosAguaPotable', 'PortalPago\AguaController@cotizarServiciosAguaPotable');
    Route::post('validarAdeudoAguaOPD', 'PortalPago\AguaController@validarAdeudoAguaOPD');
    Route::post('obtenerURLEstadoCuentaAnual', 'PortalPago\AguaController@obtenerURLEstadoCuentaAnual');
    Route::post('pagoAnual', 'PortalPago\AguaController@pagoAnual');
    Route::post('obtenerURLEstadoCuentaPagoAnual', 'PortalPago\AguaController@obtenerURLEstadoCuentaPagoAnual');
    Route::post('obtenerContribuyente', 'PortalPago\AguaController@obtenerContribuyente');


});


//! +-+-+-+-+-+ +-+-+-+-+ +-+-+ +-+-+-+-+-+-+ +-+-+ +-+-+-+-+-+
//! |R|u|t|a|s| |p|a|r|a| |e|l| |p|o|r|t|a|l| |d|e| pago en linea agua
//! +-+-+-+-+-+ +-+-+-+-+ +-+-+ +-+-+-+-+-+-+ +-+-+ +-+-+-+-+-+
Route::group(['prefix' => 'portalpagolicenciafuncionamiento'], function () {

    Route::get('validarExisteLicenciaFuncionamiento', 'PortalPago\LicenciaFuncionamientoController@validarExisteLicenciaFuncionamiento');
    Route::get('generaEstadoDeCuentaOficial', 'PortalPago\LicenciaFuncionamientoController@generaEstadoDeCuentaOficial');
    Route::get('obtenerGirosLicenciFuncionamiento', 'PortalPago\LicenciaFuncionamientoController@obtenerGirosLicenciFuncionamiento');


    Route::post('generarSolicitudEcologia', 'PortalPago\LicenciaFuncionamientoController@generarSolicitudEcologiaPutamadre');

});



//! +-+-+-+-+-+ +-+-+-+-+ +-+-+ +-+-+-+-+-+-+ +-+-+ +-+-+-+-+-+
//! |R|u|t|a|s|
//! +-+-+-+-+-+ +-+-+-+-+ +-+-+ +-+-+-+-+-+-+ +-+-+ +-+-+-+-+-+
Route:: group(['middleware' => ['throttle:10000,1'],'prefix' => 'extras'], function () {
    Route::post('municipios', 'ExtrasController@municipios');
    Route::post('entidadFederativa', 'ExtrasController@entidadFederativa');
    Route::post('localidades', 'ExtrasController@localidades');
    Route::post('obtenerRegimen', 'ExtrasController@obtenerRegimen');
    Route::post('registrarNuevoContribuyente', 'ExtrasController@registrarNuevoContribuyente');
    Route::post('obtenerDatosTransaccion', 'ExtrasController@obtenerDatosTransaccion');
    Route::post('ajaxsFuntionsAPI', 'ExtrasController@ajaxsFuntionsAPI');

    Route::post('pruebas', 'ExtrasController@pruebas');

    Route::get('obtenerCampoRequerido', 'ExtrasController@obtenerCampoRequerido');

    Route::get('obtenerCamposRequerido', 'ExtrasController@obtenerCamposRequerido');
    Route::get('existePagoSUINPAC', 'ExtrasController@existePagoSUINPAC');
    Route::get('testAPI', 'ExtrasController@testAPI');

    ##rutas reacomodadas
    Route::post('getNombreCliente', 'ExtrasController@getNombreCliente');
    Route::post('listadoServicios', 'ExtrasController@listadoServicios');
    Route::post('getImagen', 'ExtrasController@getImagen');
    Route::post('getEstatusCliente', 'ExtrasController@getEstatusCliente');
    Route::post('getServiciosCliente', 'ExtrasController@getServiciosCliente');
    Route::post('getEstatusServiciosCliente', 'ExtrasController@getEstatusServiciosCliente');
    Route::post('getImagenes', 'ExtrasController@getImagenes');

    ##
    Route::post('ObtenerDatosPagoReferenciado', 'ExtrasController@ObtenerDatosPagoReferenciado');
    Route::post('obtenerVariablesDeServicio', 'ExtrasController@obtenerVariablesDeServicio');
    Route::post('obtenerNombreCortoCliente', 'ExtrasController@obtenerNombreCortoCliente');
    Route::post('obtenerBancosAPagar', 'ExtrasController@obtenerBancosAPagar');
    Route::post('getLogoBanco', 'ExtrasController@getLogoBanco');

    Route::post('obtenerPagosReferenciados', 'ExtrasController@obtenerPagosReferenciados');

});

##========================================================================================
##               ╦═╗╦ ╦╔╦╗╔═╗  ╔═╗╔═╗╦═╗╔═╗  ╦  ╔═╗  ╔═╗╔═╗╔═╗╔╦╗╦ ╦╦═╗╔═╗              ##
##               ╠╦╝║ ║ ║ ╠═╣  ╠═╝╠═╣╠╦╝╠═╣  ║  ╠═╣  ╠╣ ╠═╣║   ║ ║ ║╠╦╝╠═╣              ##
##               ╩╚═╚═╝ ╩ ╩ ╩  ╩  ╩ ╩╩╚═╩ ╩  ╩═╝╩ ╩  ╚  ╩ ╩╚═╝ ╩ ╚═╝╩╚═╩ ╩              ##
##========================================================================================
Route::post('factura', 'FacturaController@obtenerFactura');

//mario 09-01-20202 13:40
