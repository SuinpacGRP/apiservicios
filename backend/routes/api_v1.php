<?php

use Illuminate\Http\Request;

Route::get('', function () { return "API  V 1.0"; });

Route::post('suinpac','ExtrasController@postSuinpac');

Route::post('3DCC','ExtrasController@generar3DCC');

Route::get('demo','ExtrasController@demo');

Route::get('demo2','ExtrasController@demo2');

Route::post('prueba','ExtrasController@pruebaS');

Route::post('Factura','ExtrasController@ObtenerFactura');

Route::post('obtenerDocumentosAyuntamiento','ExtrasController@obtenerDocumentosAyuntamiento');

Route::post('obtenerDocumentosAyuntamiento2','ExtrasController@obtenerDocumentosAyuntamiento2');

#Route::get('obtenerDatos', 'PruebaController@obtenerDatos');

Route::post('obtenerDatos', 'PruebaController@obtenerDatos');

//rutas para la aplicacion movil

Route::post('ejemplo',function(){
    $cadenanueva =  strtoupper('segm7707036T7');
    return "nueva rfc: ".$cadenanueva;
});

Route::post('auth','Checador\AsistenciaController@verificar_usuario_sistema');
Route::post('prueba','Checador\AsistenciaController@prueba');
Route::post('verificar-empleado','Checador\AsistenciaController@existe_empleado');
Route::post('agregar-telefono','Checador\AsistenciaController@agregarNumeroDeTelefono');

//para la nueva version
Route::post('datos-personales-2','Checador\AsistenciaController@datosPersonalesv2');
Route::post('registrar-asistencia-ubicacion','Checador\AsistenciaController@registrarAsistenciaV2');
Route::post('historial-asistencias-ubicacion','Checador\AsistenciaController@historialAsistenciaUbicacion');
Route::post('ubicacion-empresa','Checador\AsistenciaController@get_ubicacionMapa');

Route::post('registrar-asistencia-ubicacion-v2','Checador\AsistenciaController@registrarAsistenciaV3');

Route::post('urlLogo','Checador\AsistenciaController@obtenerURLLogo');

//=====================================================
//registrarAsistenciaV2

Route::post('auth-v2','Checador\AsistenciaController@verificar_usuario_sistema');
Route::post('datos-empleados-v3','Checador\AsistenciaController@existe_empleado2');

Route::post('registrar-asistencia-movil','Checador\AsistenciaController@registrarAsistenciaMovil');

Route::post('ubicacion-empresa-v2','Checador\AsistenciaController@getUbicacionEmpresaNuevo');

Route::post('ubicacion-empresa-v3','Checador\AsistenciaController@getUbicacionEmpresaNuevov2');

//rutas para la aplicacion del agua
Route::post('ubicacion-empresa-prueba','Checador\AsistenciaController@get_ubicacionMapa3');
Route::post('datos-empleados-v4','Checador\AsistenciaController@existe_empleado3');
Route::post('acceso-user','Checador\AsistenciaController@verificar_acceso');
Route::post('login-presidentePrueba','Presidentes\PresidentesPrueba@verificar_Usuario');
Route::post('datosLecturaAguaPrueba', 'Presidentes\PresidentesPrueba@buscarDatosContribullente');
Route::post('extrarDatosLecturaPrueba', 'Presidentes\PresidentesPrueba@extrarLectura');
Route::post('guardarDatosLecturaPrueba', 'Presidentes\PresidentesPrueba@guardarLecturaV2');//Quitar el V2 para usar la función sin la actualización de las fotos
Route::post('extraerHistorialPrueba','Presidentes\PresidentesPrueba@extraerHistorilaDelecturas');
Route::post('extraerDatosEditarPrueba','Presidentes\PresidentesPrueba@extraerDatosEditar');
Route::post('actualizarRegistroPrueba' ,'Presidentes\PresidentesPrueba@actualiarDatos');
Route::post('Prueba', 'Presidentes\PresidentesPrueba@verificarUsuarioLecturista');
Route::post('crearReportePrueba', 'Presidentes\PresidentesPrueba@crearReporte');
Route::post('buscarSectores', 'Presidentes\PresidentesPrueba@buscarSectores');
Route::get('getAguaLectura', 'Presidentes\PresidentesPrueba@getAguaLectura');
Route::post('datosLecturaPorSector', 'Presidentes\PresidentesPrueba@datosLecturaPorSector');
Route::post('inspeccionContribuyente','Presidentes\PresidentesPrueba@obtenerPadronContribyente');
Route::post('buscarSectorPalabraClave','Presidentes\PresidentesPrueba@getSectorBusquedaPalabraClave');
Route::post('paginasBusqueaSector','Presidentes\PresidentesPrueba@numeroPaginasSectorBusqueda');
Route::post('ObtenerPromedioEditar','Presidentes\PresidentesPrueba@obtenerPromerdioEditar');
Route::post('buscarPorContrato','Presidentes\PresidentesPrueba@buscarPorContrato');
Route::post('buscarPorMedidor','Presidentes\PresidentesPrueba@buscarPorMedidor');
Route::post('buscarPorFolio','Presidentes\PresidentesPrueba@buscarPorFolio');
Route::post('buscarPorContribuyente','Presidentes\PresidentesPrueba@buscarPorContribuyente');
Route::post('DatosTomaCorte','Presidentes\PresidentesPrueba@extraerDatosTomaCorte');
Route::post('consumo','Presidentes\PresidentesPrueba@calcularConsumo');
Route::post('RealizarCorte','Presidentes\PresidentesPrueba@RealizarCorteTomaSuinpac');
Route::post('listaCortes','Presidentes\PresidentesPrueba@ObtenerListaCortes');

//ruta de prueba carga de imagenes y reportes
Route::post('guardarDatosLecturaPruebaV1', 'Presidentes\PresidentesPrueba@guardarLecturaPruebas');//Quitar el V2 para usar la función sin la actualización de las fotos
Route::post('listaReportes', 'Presidentes\PresidentesPrueba@obtenerReportes');
Route::post('extraerReporte','Presidentes\PresidentesPrueba@obtenerReporte');
Route::post('paginas','Presidentes\PresidentesPrueba@numeroDePaginas');
Route::post('obtenerConsumoPromedio','Presidentes\PresidentesPrueba@obtenerPromerdio');
Route::post('extraerLogo','Presidentes\PresidentesPrueba@obtenerLogotipo');
Route::post('extraerContribuyente','Presidentes\PresidentesPrueba@obtenerContribuyente');
Route::post('guardarContribuyente','Presidentes\PresidentesPrueba@actualizarContactoContribuyente');
Route::post('paginasBusqueda','Presidentes\PresidentesPrueba@numeroPaginasBusqueda');
Route::post('obtenerPadronContribyenteDatos','Presidentes\PresidentesPrueba@obtenerPadronContribyenteDatos');

//Rutas de aplicacion de asistencias
Route::post('horarioEmpleado','Presidentes\PresidentesPrueba@horarioEmpleado');
Route::post('pruebaAsistencias','Presidentes\PresidentesPrueba@pruebaAsistencias');
Route::post('pruebaCombustibleQR','Presidentes\PresidentesPrueba@pruebaCombustibleQR');
Route::post('pruebaCombustibleQRValidar','Presidentes\PresidentesPrueba@pruebaCombustibleQRValidar');
Route::post('clientes','Presidentes\PresidentesPrueba@obtenerListaCientes');
Route::post('datosEmpleados','Presidentes\PresidentesPrueba@obtenerEmpleados');
Route::post('ConfigurarChecador','Presidentes\PresidentesPrueba@configurarChecador');
Route::post('auth-Checador','Presidentes\PresidentesPrueba@verificarChecador');
Route::post('ActualizarBanner','Presidentes\PresidentesPrueba@actualizarBanner');
Route::post('ChecadorSectores','Presidentes\PresidentesPrueba@obtenerSectores');
Route::post('RegistrarAsistenciaChecador','Presidentes\PresidentesPrueba@registrarAsistenciaChecador');
Route::post('obtenerBitacora','Presidentes\PresidentesPrueba@obtenerBitacoraChecador');
Route::post('ObtenerRecurso','Presidentes\PresidentesPrueba@obtenerRecurso');
Route::post('login-checador','Presidentes\PresidentesPrueba@recuperarChecador');
Route::post('logo-checador','Presidentes\PresidentesPrueba@obtenerLogotipoClienteChecador');
Route::post('actualizarConexion','Presidentes\PresidentesPrueba@actualizarConexion');
Route::post('TareaCompleta','Presidentes\PresidentesPrueba@bitacoraTareaCompleta');
Route::post('TareaEmpleado','Presidentes\PresidentesPrueba@obtenerEmpleadoTarea');
Route::post('ActualizarConfiguracion','Presidentes\PresidentesPrueba@actualizarConfiguracion');
Route::post('EmpleadosGeneral','Presidentes\PresidentesPrueba@obtenerEmpleadosGeneral');

//Rutas para abrir la aplicacion de luminarias 
#Pruebas
Route::post('verificarAsistencia','Presidentes\PresidentesPrueba@verificarAsistencia');
Route::post('verificarAsistenciaPrueba',"PadronComercios\PadronComerciosController@verificarAsistencia");
Route::post('verificarAsistenciaV2','Presidentes\PresidentesPrueba@verificarAsistenciaV2');

#Herramienta
Route::post('tiny','PadronComercios\PadronComerciosController@subirFotoEmpleadoTiny');
Route::post('Verificar','PadronComercios\PadronComerciosController@verificarAsistenciaV4');
#Herramienta

#Cambio de id checado
Route::post('ObtenerEmpleadosv2','PadronComercios\PadronComerciosController@obtenerEmpleados');
Route::post('ObtenerEmpleadosGeneralv2','PadronComercios\PadronComerciosController@obtenerEmpleadosGeneral');
Route::post('horarioEmpleadov2','PadronComercios\PadronComerciosController@horarioEmpleado');
Route::post('obtenerEmpleadoTareaV2','PadronComercios\PadronComerciosController@obtenerEmpleadoTareaV2');
Route::post('RegistrarAsistenciaChecadorV2','PadronComercios\PadronComerciosController@registrarAsistenciaChecador');

#Aplicacion de sorteo
Route::post('RomperVoleto','PadronComercios\PadronComerciosController@userVoleto');

//NOTE: Ruta para la aplicacion de luminarias, baches y agua(Cuatitlan)
Route::post('AtencionLogin','LuminariasBaches\LuminariasController@verificar_Usuario');
Route::post('ObtenerCatalogo','LuminariasBaches\LuminariasController@obtenerCatalogoAreas');
Route::post('Municipios','LuminariasBaches\LuminariasController@ObtenerMunicipio');
//Route::post('RegistrarCiudadano','LuminariasBaches\LuminariasController@GuardarCiudadano');
Route::post('GuardarReporte','LuminariasBaches\LuminariasController@GuardarReporte');
Route::post('ListaReportes','LuminariasBaches\LuminariasController@ObtenerListaReportes');
Route::post('DatosCiudadano','LuminariasBaches\LuminariasController@ObtenerDatosCiudadano');
Route::post('EditarContactoCiudadano','LuminariasBaches\LuminariasController@EditarCiudadano');
Route::post('RefrescarReporte','LuminariasBaches\LuminariasController@ObtenerReporte');
Route::post("CargarHistorialLuminaria","LuminariasBaches\LuminariasController@CargarHistorialLuminaria");
//NOTE: Esta funcion verifica la validez del token en la app
Route::post('VerificaSession','LuminariasBaches\LuminariasController@VerificarSession');

Route::group(['prefix' => 'Luminarias'], function() {
    // Rutas de los controladores dentro del Namespace "App\Http\Controllers\Admin"
    Route::get('Pruebas','LuminariasBaches\LuminariasMedidoresController@pruebaControladorGrupo');
    Route::POST('login-luminarias','LuminariasBaches\LuminariasMedidoresController@verificar_Usuario'); //REVIEW: Este metodo tambien se aplica para la aplicacion del padron simple 
    Route::POST('verificarDatos','LuminariasBaches\LuminariasMedidoresController@verificarRolLuminaria');
    Route::post('ObtenerCatalogosLuminarias','LuminariasBaches\LuminariasMedidoresController@ObtenerCatlogosLuminarias');
    Route::post('ObtenerMunicipio',"LuminariasBaches\LuminariasMedidoresController@ObtenerDatosCliente");
    Route::post("GuardarLuminaria",'LuminariasBaches\LuminariasMedidoresController@GuardarLuminaria');
    Route::post("ObtenerLocalidades","LuminariasBaches\LuminariasMedidoresController@ObtenerLocalidadesMunicipio");
    Route::post("HistorialLuminaria","LuminariasBaches\LuminariasMedidoresController@GuardarHistorialLuminaria");
    Route::post("HistorialMedidor","LuminariasBaches\LuminariasMedidoresController@GuardarHistoriallMedidor");
    Route::post("CargarHistorialMedidor","LuminariasBaches\LuminariasMedidoresController@CargarHistorialMedidor");
    //Ruta de prueba para verificar la area 4 del checador ( insertarTareaAsistenciasRelleno )
    Route::post("AsistenciasOmisios","LuminariasBaches\LuminariasMedidoresController@insertarTareaAsistenciasRelleno");
});

Route::group([ 'prefix' => 'AppAgua'], function() {
    Route::post('ReporteBuscarContrato','Presidentes\PresidentesPrueba@buscarPorContratoSinFiltro');
    Route::post('crearReporteV2','Presidentes\PresidentesPrueba@crearReporteV2');
    Route::post('ReporteBuscarMedidor','Presidentes\PresidentesPrueba@buscarPorMedidorSinFiltro');
    Route::post('ConfiguracionCoutaFija','Presidentes\PresidentesPrueba@ConfiguracionCoutaFija'); //
    Route::post('ConfiguracionEvidencia','AplicacionAgua\ControladorAgua@obtenerConfiguracionEvidencia');
    Route::post('GuardarLecturaV3','AplicacionAgua\ControladorAgua@guardarLecturaV3');
    Route::post('GuardarCoutaFija','AplicacionAgua\ControladorAgua@GuardarLecturaCuotaFija');
    Route::post('GuardarReporteAgua','AplicacionAgua\ControladorAgua@crearReporteV3');
    Route::post('RealizarCorte','AplicacionAgua\ControladorAgua@RealizarCorteTomaSuinpac');
    Route::post('HistorialLecturas','AplicacionAgua\ControladorAgua@extraerHistorilaDelecturas');
    Route::post('VerificarUsuario','AplicacionAgua\ControladorAgua@verificarUsuarioLecturista');
    Route::post('verificarUsuarioCortes','AplicacionAgua\ControladorAgua@verificarUsuarioCortes');
    Route::post('TareasCortes','AplicacionAgua\ControladorAgua@ObtenerListaTareas');
    Route::post('BuscarCortePorContrato','AplicacionAgua\ControladorAgua@BuscarContratoTarea'); //multarToma
    Route::post('BuscarCortePorMedidor','AplicacionAgua\ControladorAgua@BuscarMedidorContrato');
    Route::post('MultarToma','AplicacionAgua\ControladorAgua@multarToma');
    Route::post('ObtenerSectoresConfigurados','AplicacionAgua\ControladorAgua@ObtenerSectoresConfigurados');
    Route::post('PadronAguaAnomalias','AplicacionAgua\ControladorAgua@ObtenerAnomaliasAgua');
    Route::post('ObtenerConfiguracionesAgua','AplicacionAgua\ControladorAgua@ObtenerConfiguracionesAgua');
    Route::post('ContratosSector','AplicacionAgua\ControladorAgua@ObtenerSectorCompleto');
});
Route::group([ 'prefix' => 'wAsistencias'], function() {
    Route::post('ConfigurarRelleno','Aplicaciones\AsistenciaWindowsControler@obtenerConfiguracionMasivoAsitencias');
    Route::post('EnviarHorarioMasivo','Aplicaciones\AsistenciaWindowsControler@GenerarAsistenciasMasivoSuinpac'); //
    Route::post('EnviarIncidencia','Aplicaciones\AsistenciaWindowsControler@EnviarIncidenciasChecador'); //ObtenerDireccionFoto
    Route::post('ObtenerDireccionFoto','Aplicaciones\AsistenciaWindowsControler@ObtenerDireccionFoto');
    Route::post('ObtenerDireccionFotoCHECK','Aplicaciones\AsistenciaWindowsControler@ObtenerDireccionFotoCHECK');
    Route::post('crearBitacoraChecador','Aplicaciones\AsistenciaWindowsControler@crearBitacoraChecador');
});

Route::group([ 'prefix' => 'ReporteC4'], function() {
    Route::post('Reportar','Aplicaciones\ReportesCCuatro@realizarReporteC4');
    Route::post('BotonRosa','Aplicaciones\ReportesCCuatro@RealizarReporteBotonRosa');
    Route::post('ActualizarCoordenadas','Aplicaciones\ReportesCCuatro@ActualizarCoordenadas');
    Route::post('RegistrarCiudadano','Aplicaciones\ReportesCCuatro@RegistrarCiudadano');
    Route::post('LoginReportes','Aplicaciones\ReportesCCuatro@ValidarCiudadano');
    Route::post('ActualizarDatosPersonales','Aplicaciones\ReportesCCuatro@ActualizarDatosPersonales');
    Route::post('DatosDomicilio','Aplicaciones\ReportesCCuatro@ObtenerDatosDomicilio');
    Route::post('ActualizarDomicilio','Aplicaciones\ReportesCCuatro@ActualizarDatosDomicilio');
    Route::post('DatosContacto','Aplicaciones\ReportesCCuatro@ObtenerDatosContacto');
    Route::post('ActualizarDatosContacto','Aplicaciones\ReportesCCuatro@ActualizarDatosContacto');
    Route::post('ActualizarCiudadano','Aplicaciones\ReportesCCuatro@ActualizarDatosCiudadano');
    //NOTE: Metodos de prueba ActualizarDatosContacto GuardarReporte  ObtenerFotoPerfil guardarTokenExpo
    Route::post('BuscarMedidorCorte','Aplicaciones\ReportesCCuatro@BuscarMedidorContrato');
    Route::post('GuardarTokenExpo','Aplicaciones\ReportesCCuatro@guardarTokenExpo');
});
Route::group([ 'prefix' => 'PortalLicencias'], function() {
    Route::post('getClaves','Aplicaciones\ReportesCCuatro@ObtenerCalves');
});
Route::group([ 'prefix' => 'AtencionCliente'], function() {
    Route::post('Registrar','Aplicaciones\ReportesCCuatro@RegistrarCiudadanoCliente');
    Route::post('ClienteAreas','Aplicaciones\ReportesCCuatro@obtenerCatalogoAreas');
    Route::post('Reportar','Aplicaciones\ReportesCCuatro@GuardarReporte');
    Route::post('Historial','Aplicaciones\ReportesCCuatro@ObtenerListaReportes');
    Route::post('ObtenerCiudadano','Aplicaciones\ReportesCCuatro@ObtenerDatosCiudadano');
    Route::post('ActualizarCiudadano','Aplicaciones\ReportesCCuatro@ActualizarDatosCliente');
    Route::post('IniciarSesion','Aplicaciones\ReportesCCuatro@IniciarSession');
    Route::post('ActualizaFoto','Aplicaciones\ReportesCCuatro@ActualizarFotoPerfil');
    Route::post('FotoPerfil','Aplicaciones\ReportesCCuatro@ObtenerFotoPerfil');
    Route::post('Observaciones','Aplicaciones\ReportesCCuatro@ObtenerObservacioneReporte');
    Route::post('Responder','Aplicaciones\ReportesCCuatro@EnviarRespuestaObservacion');
});
Route::group([ 'prefix' => 'test'], function() {
    Route::post('testVerificar','testController@verificarUsuarioLecturista'); //ObtenerFormato 
    Route::get('ListaCredenciales','testController@listaCredenciales');
    Route::post('Formatos','testController@ObtenerFormato');
    Route::post('DocumentoEncode','testController@descargarLicencia');
    //NOTE: rutas de pruebas para el checador inteligente 
    //FIXME: Cambiar a la vercion de producion ObtenerEmpleadosMasivoIntV2
    Route::post('PruebaEmpleado','Aplicaciones\AsistenciaWindowsControler@ObtenerEmpleadoInte');
    Route::post('DatosEmpleado','Aplicaciones\AsistenciaWindowsControler@ObtenerDatosEmpleadosInt');
    Route::post('DatosEmpleadoCheck','Aplicaciones\AsistenciaWindowsControler@ObtenerDatosEmpleadosIntCheck');
    Route::post('EmpleadoMasivo','Aplicaciones\AsistenciaWindowsControler@ObtenerEmpleadosMasivoInt');
    Route::post('BitacoraInt','Aplicaciones\AsistenciaWindowsControler@ObtenerBitacoraChecadorInt');
    Route::post('BitacoraIntCHECK','Aplicaciones\AsistenciaWindowsControler@ObtenerBitacoraChecadorIntCHECK');
    Route::post('RegistrarDispositivoInt','Aplicaciones\AsistenciaWindowsControler@RegitrarChecadorInt');
    Route::post('EnviarRespuestaSuinpac','Aplicaciones\AsistenciaWindowsControler@EnviarRespuestaSuinpac');
    Route::post('EmpleadoMasivoV2','testController@ObtenerEmpleadosMasivoIntV2'); //INDEV: Cambiando a vercion  con fotos de los empleado raw
    Route::post('EmpleadoMasivoV2CHECK','testController@ObtenerEmpleadosMasivoIntV2CHECK'); //INDEV: Cambiando a vercion  con fotos de los empleado raw
    //NOTE: rutas de prueba para la multa del agua ObtenerPeridoValidoDeclaracionInicial
    Route::post('TotalMultas','testController@ObtnerContratosMulta');
    Route::post('Multar','testController@multarToma');    
    Route::post('PadronAguaAnomalias','testController@ObtenerAnomaliasAgua');    
    Route::post('ValidarDeclaracionInicial','testController@ObtenerPeridoValidoDeclaracionInicial');

});
