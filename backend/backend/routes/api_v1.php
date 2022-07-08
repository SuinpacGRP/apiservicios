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

//pruebas
Route::post('ubicacion-empresa-prueba','Checador\AsistenciaController@get_ubicacionMapa3');
Route::post('datos-empleados-v4','Checador\AsistenciaController@existe_empleado3');
Route::post('acceso-user','Checador\AsistenciaController@verificar_acceso');

//Rutas app Misa 
Route::post('login-presidente','Presidentes\PresidentesController@verificar_Usuario');
Route::post('datosLecturaAgua', 'Presidentes\PresidentesController@buscarDatosContribullente');
Route::post('extrarDatosLectura', 'Presidentes\PresidentesController@extrarLectura');
Route::post('guardarDatosLectura', 'Presidentes\PresidentesController@guardarLectura');
Route::post('extraerHistorial','Presidentes\PresidentesController@extraerHistorilaDelecturas');
Route::post('extraerDatosEditar','Presidentes\PresidentesController@extraerDatosEditar');
Route::post('actualizarRegistro' ,'Presidentes\PresidentesController@actualiarDatos');
Route::post('verificarUsuarioLecturista', 'Presidentes\PresidentesController@verificarUsuarioLecturista');
Route::post('crearReportePrueba', 'Presidentes\PresidentesController@crearReporte');

//rutas de prueba app Misa
Route::post('login-presidentePrueba','Presidentes\PresidentesPrueba@verificar_Usuario');
Route::post('datosLecturaAguaPrueba', 'Presidentes\PresidentesPrueba@buscarDatosContribullente');
Route::post('extrarDatosLecturaPrueba', 'Presidentes\PresidentesPrueba@extrarLectura');
Route::post('guardarDatosLecturaPrueba', 'Presidentes\PresidentesPrueba@guardarLectura');
Route::post('extraerHistorialPrueba','Presidentes\PresidentesPrueba@extraerHistorilaDelecturas');
Route::post('extraerDatosEditarPrueba','Presidentes\PresidentesPrueba@extraerDatosEditar');
Route::post('actualizarRegistroPrueba' ,'Presidentes\PresidentesPrueba@actualiarDatos');
Route::post('verificarUsuarioLecturistaPrueba', 'Presidentes\PresidentesPrueba@verificarUsuarioLecturista');
Route::post('crearReportePrueba', 'Presidentes\PresidentesPrueba@crearReporte');

Route::post('getAguaLectura', 'Presidentes\PresidentesPrueba@getAguaLectura');

