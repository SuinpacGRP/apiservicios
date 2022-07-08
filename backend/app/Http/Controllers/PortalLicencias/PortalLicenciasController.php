<?php

namespace App\Http\Controllers\PortalLicencias;

/*use App\CelaRepositorio;
use App\Modelos\Cotizacion;*/

use Illuminate\Support\Facades\Storage;
use App\Funciones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use LDAP\Result;
/*use App\ModelosNotarios\Observaciones;
use App\ModelosNotarios\PadronCatastral;
use App\ModelosNotarios\TramitesISAINotarios;
use App\Modelos\PadronCatastralTramitesISAINotarios;
use App\Libs\LibNubeS3;
use App\FuncionesFirma;
use App\Libs\Wkhtmltopdf;
use Illuminate\Support\Facades\Storage;
*/

use Validator;

class PortalLicenciasController extends Controller
{
    public function __construct()

    {

        $this->middleware('jwt', ['except' => ['getAccesoLicenciasDeFuncionamiento']]);
    }


    public function getGirosCliente(Request $request)
    {
        $Cliente   = $request->Cliente;
        $impacto   = $request-> impacto;
        Funciones::selecionarBase(56);
        $sqlGiros = "SELECT gd.id,g.Descripci_on,g.Riesgo from GiroDetalle gd INNER JOIN Giro g  ON (gd.idGiro=g.id) where g.Impacto=$impacto ORDER BY g.Descripci_on";
        $servicios = DB::select($sqlGiros);
        return response()->json([
            'success' => '200',
            'result' => $this->convert_from_latin1_to_utf8_recursively($servicios)
        ]);
    }
    public function updateContribuyente(Request $request)
    {
        $Cliente   = $request->Cliente;
        $idContribuyenteUpdate   = $request->idContribuyenteUpdate;
        $RfcContribuyenteUpdate   = $request->RfcContribuyenteUpdate;
        $CurpContribuyenteUpdate   = $request->CurpContribuyenteUpdate;
        $NombresContribuyenteUpdate   = $request->NombresContribuyenteUpdate;
        $ApellidoPaternoContribuyenteUpdate   = $request->ApellidoPaternoContribuyenteUpdate;
        $ApellidoMaternoContribuyente   = $request->ApellidoMaternoContribuyente;
        $TelParticularContribuyenteUpdate   = $request->TelParticularContribuyenteUpdate;

        $TelCelularContribuyenteUpdate   = $request->TelCelularContribuyenteUpdate;
        $CorreoContribuyenteUpdate   = $request->CorreoContribuyenteUpdate;
        $CalleContribuyenteUpdate   = $request->CalleContribuyenteUpdate;
        $ColoniaContribuyenteUpdate   = $request->ColoniaContribuyenteUpdate;
        $InteriorContribuyenteUpdate   = $request->InteriorContribuyenteUpdate;
        $ExteriorContribuyenteUpdate   = $request->ExteriorContribuyenteUpdate;
        $PostalContribuyenteUpdate   = $request->PostalContribuyenteUpdate;

/*






Sexo
NombreComercial
RepresentanteLegal
Tel_efonoParticular
Tel_efonoCelular
CorreoElectr_onico
Cliente
DatosFiscales
PersonalidadJur_idica
TipoPersona
NuevoContribuyente
Estatus
Pa_is_c
EntidadFederativa_c
Municipio_c
Localidad_c
Colonia_c
Calle_c
N_umeroInterior_c
N_umeroExterior_c
C_odigoPostal_c
Bloquear
TieneCopropietarios
ObservacionesNombre
id_folio
*/
        
        Funciones::selecionarBase(56);
    DB::table('Contribuyente') #insercio Por  subir
        ->where('id', '=', $idContribuyenteUpdate)->update([
            'Rfc' => $RfcContribuyenteUpdate,
            'Curp' => $CurpContribuyenteUpdate,
            'Nombres' => $NombresContribuyenteUpdate,
            'ApellidoPaterno' => $ApellidoPaternoContribuyenteUpdate,
            'ApellidoMaterno' => $ApellidoMaternoContribuyente,
            'Tel_efonoParticular' => $TelParticularContribuyenteUpdate,
            'Tel_efonoCelular' => $TelCelularContribuyenteUpdate,        
            'Calle_c' => $CalleContribuyenteUpdate,
            'Colonia_c' => $ColoniaContribuyenteUpdate,
            'C_odigoPostal_c' => $PostalContribuyenteUpdate,
            'N_umeroExterior_c' => $ExteriorContribuyenteUpdate,
            'N_umeroInterior_c' => $InteriorContribuyenteUpdate,
            'CorreoElectr_onico' => $CorreoContribuyenteUpdate            
        ]);

        return response()->json([
            'success' => '200',
            'messages' => 'actualizado correctamente',
            'result' => $idContribuyenteUpdate
        ]);
    }
    public function getLicalidadesPorCliente(Request $request)
    {
        $Cliente   = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $sqlLocalidades = "SELECT l.id,l.Nombre FROM Localidad l 
        INNER JOIN Municipio m 
        ON (m.id=l.Municipio)
        WHERE m.id=(SELECT df.Municipio FROM Cliente c 
        INNER JOIN DatosFiscalesCliente df
        ON(df.id=c.DatosFiscales)
        WHERE c.id=$Cliente)
        ";
        $localidades = DB::select($sqlLocalidades);
        return response()->json([
            'success' => '200',
            'result' => $localidades
        ]);
    }
    public function getLicalidagetColoniasdesPorCliente(Request $request)
    {
        $Cliente   = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $sqlLocalidades = "SELECT Nombre as id,Nombre as Nombre From CatalogoColonias
        ";
        $localidades = DB::select($sqlLocalidades);
        return response()->json([
            'success' => '200',
          
            'result' => $this->convert_from_latin1_to_utf8_recursively($localidades)
        ]);
    }

 

    public function getColonias(Request $request)
    {
        $Cliente   = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $sqlPais = "SELECT Nombre FROM  CatalogoColonia 
     
        ";
        $Pais = DB::select($sqlPais);
        return response()->json([
            'success' => '200',
            'result' => $this->convert_from_latin1_to_utf8_recursively($Pais)

        ]);
    }

    public function getColoniasPorCliente(Request $request)
    {
        $Cliente   = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $sqlColonias = "SELECT Nombre FROM CatalogoColonia
            
        ";
        $Colonias = DB::select($sqlColonias);
        return response()->json([
            'success' => '200',
            'result' => $Colonias
        ]);
    }
    public function getLocalidadesPorClienteFiscal(Request $request)
    {
        $Cliente   = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $sqlLocalidades = "SELECT l.id,l.Nombre FROM Localidad l 
        INNER JOIN Municipio m 
        ON (m.id=l.Municipio)
        WHERE m.id=(SELECT df.Municipio FROM Cliente c 
        INNER JOIN DatosFiscalesCliente df
        ON(df.id=c.DatosFiscales)
        WHERE c.id=$Cliente)
        ";
        $localidades = DB::select($sqlLocalidades);
        return response()->json([
            'success' => '200',
            'result' => $localidades
        ]);
    }


    public function getRegimenFiscal(Request $request)
    {
        $Cliente   = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $sqlGiros = "SELECT id, CONCAT(Clave,' - ',Descripci_on) as Nombre 
        FROM RegimenFiscal";
        $servicios = DB::select($sqlGiros);
        return response()->json([
            'success' => '200',
            'result' => $servicios
        ]);
    }
    public function postInsertEncuesta(Request $request)
    {

        $encuesta= array(
            "Cliente"=>56,
            "codigoPostal"=>$request->codigoPostal,
            "Edad"=>$request->Edad,
            "sexo"=>$request->sexo,
            "uno"=>$request->uno,
            "dos"=>$request->dos,
            "tres"=>$request->tres,
            "cuatro"=>$request->cuatro,
            "cinco"=>$request->cinco,
            "seis"=>$request->seis,
            "siete"=>$request->siete,
            "ocho"=>$request->ocho,
            "nueve"=>$request->nueve,
            "diez"=>$request->diez,
            "comentarios"=>$request->comentarios
        );
        $respuestas=json_encode($encuesta);

        Funciones::selecionarBase(56);
        $localInsertLocal = DB::table('PreguntaEncuesta')->insertGetId([
            "pregunta" => $respuestas,
            "categoria" => 1,
            "idSolicitud" => 1,
            "fechaTupla" => date("Y-m-d H:i:s"),
            
        ]);


        return response()->json([
            'success' => '200',
            'result' => $localInsertLocal,
            'folio' => $localInsertLocal#$localInsertAltaSolicitud,
            
        ]);
    }
    public function postInsertAltaLicencia(Request $request)
    {

        /*

//General general

   ObjetoAltaLicencia.telefonoContacto=telefonoContacto;
   ObjetoAltaLicencia.giroPrincipal=selecGiros;
   ObjetoAltaLicencia.correoElectronicoC=correoElectronicoC;
   ObjetoAltaLicencia.inversion=inversion;
   ObjetoAltaLicencia.superficie=superficie;
   ObjetoAltaLicencia.noEmpleado=noEmpleado;
   ObjetoAltaLicencia.ubicacion=ubicacion;
   //Domicilio
   ObjetoAltaLicencia.calle=calle;
   ObjetoAltaLicencia.colonia=colonia;
   ObjetoAltaLicencia.noInterior=noInterior;
   ObjetoAltaLicencia.Exterior=Exterior;
   ObjetoAltaLicencia.manzana=manzana;
   ObjetoAltaLicencia.lote=lote;
   ObjetoAltaLicencia.codigoPostal=codigoPostal;
   ObjetoAltaLicencia.aforo=aforo;
   ObjetoAltaLicencia.plaza=plaza;
   ObjetoAltaLicencia.nombrePlaza=nombrePlaza;
   ObjetoAltaLicencia.tipoDomicilio=tipoDomicilio;

*/

        $Cliente   = $request->cliente;
        $arryGirosAnexos   = $request->arryGirosAnexos;
        $ObjetoHorarioJSon   = $request->ObjetoHorarioJSon;
        $Horario   = $request->Horario;
        $idPredial   = null;
        $CuentaPredial   = $request->CuentaPredial;

        $idContribuyente   = $request->idContribuyente;
        $coordenadas   = $request->coordenadas;

        $nombreComercial   = $request->nombreComercial;
        $municipio   = $request->municipio;
        $selecLocalidad   = $request->selecLocalidad;
        $telefonoContacto   = $request->telefonoContacto;
        $giroPrincipal   = $request->giroPrincipal;
        $correoElectronicoC   = $request->correoElectronicoC;
        $inversion   = $request->inversion;
        $superficie   = $request->superficie;
        $noEmpleado   = $request->noEmpleado;
        //$ubicacion   = $request->ubicacion;


        $calle   = $request->calle;
        $colonia   = $request->colonia;
        $noInterior   = $request->noInterior;
        $noExterior   = $request->noExterior;
        $manzana   = $request->manzana;
        $lote   = $request->lote;
        $codigoPostal   = $request->codigoPostal;
        $aforo   = $request->aforo;
        $plaza   = $request->plaza;
        $nombrePlaza   = $request->nombrePlaza;
        $tipoDomicilio   = $request->tipoDomicilio;
        $ListaDocumentos = $request->ListaDocumentos;
        $Observaciones = $request->Observaciones;
        $usuarioRegistra = $request->usuarioRegistra;

        Funciones::selecionarBase(56);

            $an_oActual=DATE('y');
        $sqlFolioMaximo='SELECT (MAX(folioSolicitud)+1) AS FolioMaxivo  FROM SolicitudLicencia WHERE folioSolicitud LIKE "'.$an_oActual.'%"';
        $fmaz = Funciones::ObtenValor($sqlFolioMaximo, "FolioMaxivo");//DB::select($sqlFolioMaximo);
        $fmaz=($fmaz==null)?(date('y')."0001"):$fmaz;

        //$sqlInserAltaLicencia = "INSERT INTO `suinpac_56`.`SolicitudLicencia` (`id`, `FechaSolicitud`, `Contribuyente`, `GiroPrincipal`, `GirosAnexos`, `Superficie`, `Tipo`, `idDatosLocal`, `Ubicacion`, `NumeroEmpleados`, `Inversion`, `FechaTermino`, `Estatus`, `Email`, `NombreEstablecimiento`) VALUES (NULL, '2022-02-10 00:00:00', 9226, 121, NULL, NULL, 1, 1, NULL, 10, 10000.00, NULL, 0, 'demo@suinpac.com', 'Manolo S.A. de C.V.')";    
        $localInsertLocal = DB::table('DatosLocal')->insertGetId([
            "Calle" => $calle,
            "Colonia" => $colonia,
            "NumeroExterior" => $noExterior,
            "NumeroInterior" => $noInterior,
            "Manzana" => $manzana,
            "Lote" => $lote,
            "CodigoPostal" => $codigoPostal,
            "Aforo" => $aforo,
            "Plaza" => $plaza,
            "NombrePlaza" => $nombrePlaza,
            "Tipo" => $tipoDomicilio,
            "UbicacionMaps" => $coordenadas
        ]);
        $localInsertAltaSolicitud = DB::table('SolicitudLicencia')->insertGetId([
            "FechaSolicitud" => date("Y-m-d H:i:s"),
            "Contribuyente" => $idContribuyente,
            "GiroPrincipal" => $giroPrincipal,
            "GirosAnexos" => $arryGirosAnexos,
            "Superficie" => $superficie,
            "CuentaPredial" => $CuentaPredial,
            "HorarioJSON" => $ObjetoHorarioJSon,
            "Horario" => $Horario,
            "idDatosLocal" => $localInsertLocal,
            "telefono" => $telefonoContacto,
            "NumeroEmpleados" => $noEmpleado,
            "Inversion" => $inversion,
            "Email" => $correoElectronicoC,
            "NombreEstablecimiento" =>  strtoupper($nombreComercial),
            "Observaciones" => $Observaciones,
            "Categoria" => 1,

        ]);
        //$folioSolicitud =json_decode($fmaz,true); //DATE('y') . str_pad($localInsertAltaSolicitud, 4, "0", STR_PAD_LEFT);
        DB::table('SolicitudLicencia') #insercio Por  subir
            ->where('id', '=', $localInsertAltaSolicitud)->update(['folioSolicitud' => $fmaz]);

        $idSolicitudLicenciaHistorial = DB::table("SolicitudLicenciaHistorial")->insertGetId([
            "Estatus" => 1,
            "Fecha" => date("Y-m-d"),
            "Categoria" => 1,

            "FechaTermino" => NULL,
            "idSolicitud" => $localInsertAltaSolicitud,
            "UsuarioRegistra" => $usuarioRegistra,
            "Tipo" => 1
        ]);


        $idImagenesSubicas = $this->SubirImagenV2($ListaDocumentos, $localInsertAltaSolicitud, $Cliente);

        $RutaDelPDFGenerado = $this->ObtenerPDFLicencia($localInsertAltaSolicitud, 56);


        return response()->json([
            'success' => '200',
            'result' => $localInsertAltaSolicitud,
            'Ruta' => $RutaDelPDFGenerado
        ]);
    }
    public function postInsertAltaLicenciaRefrendo(Request $request)
    {

        /*

//General general

   ObjetoAltaLicencia.telefonoContacto=telefonoContacto;
   ObjetoAltaLicencia.giroPrincipal=selecGiros;
   ObjetoAltaLicencia.correoElectronicoC=correoElectronicoC;
   ObjetoAltaLicencia.inversion=inversion;
   ObjetoAltaLicencia.superficie=superficie;
   ObjetoAltaLicencia.noEmpleado=noEmpleado;
   ObjetoAltaLicencia.ubicacion=ubicacion;
   //Domicilio
   ObjetoAltaLicencia.calle=calle;
   ObjetoAltaLicencia.colonia=colonia;
   ObjetoAltaLicencia.noInterior=noInterior;
   ObjetoAltaLicencia.Exterior=Exterior;
   ObjetoAltaLicencia.manzana=manzana;
   ObjetoAltaLicencia.lote=lote;
   ObjetoAltaLicencia.codigoPostal=codigoPostal;
   ObjetoAltaLicencia.aforo=aforo;
   ObjetoAltaLicencia.plaza=plaza;
   ObjetoAltaLicencia.nombrePlaza=nombrePlaza;
   ObjetoAltaLicencia.tipoDomicilio=tipoDomicilio;

*/

        $Cliente   = $request->cliente;
        $FolioAnterior   = $request->FolioAnterior;
        $arryGirosAnexos   = $request->arryGirosAnexos;
        $ObjetoHorarioJSon   = $request->ObjetoHorarioJSon;
        $Horario   = $request->Horario;
        $idPredial   = null;
        $idLicenciaRevalidacion   = $request->idLicenciaRevalidacion;

        $idContribuyente   = $request->idContribuyente;
        $coordenadas   = $request->coordenadas;

        $nombreComercial   = $request->nombreComercial;
        $municipio   = $request->municipio;
        $selecLocalidad   = $request->selecLocalidad;
        $telefonoContacto   = $request->telefonoContacto;
        $giroPrincipal   = $request->giroPrincipal;
        $correoElectronicoC   = $request->correoElectronicoC;
        $inversion   = $request->inversion;
        $superficie   = $request->superficie;
        $noEmpleado   = $request->noEmpleado;
        //$ubicacion   = $request->ubicacion;


        $calle   = $request->calle;
        $colonia   = $request->colonia;
        $noInterior   = $request->noInterior;
        $noExterior   = $request->noExterior;
        $manzana   = $request->manzana;
        $lote   = $request->lote;
        $codigoPostal   = $request->codigoPostal;
        $aforo   = $request->aforo;
        $plaza   = $request->plaza;
        $nombrePlaza   = $request->nombrePlaza;
        $tipoDomicilio   = $request->tipoDomicilio;
        $ListaDocumentos = $request->ListaDocumentos;
        $Observaciones = $request->Observaciones;
        $usuarioRegistra = $request->usuarioRegistra;

        Funciones::selecionarBase(56);

        //$sqlInserAltaLicencia = "INSERT INTO `suinpac_56`.`SolicitudLicencia` (`id`, `FechaSolicitud`, `Contribuyente`, `GiroPrincipal`, `GirosAnexos`, `Superficie`, `Tipo`, `idDatosLocal`, `Ubicacion`, `NumeroEmpleados`, `Inversion`, `FechaTermino`, `Estatus`, `Email`, `NombreEstablecimiento`) VALUES (NULL, '2022-02-10 00:00:00', 9226, 121, NULL, NULL, 1, 1, NULL, 10, 10000.00, NULL, 0, 'demo@suinpac.com', 'Manolo S.A. de C.V.')";    
        $localInsertLocal = DB::table('DatosLocal')->insertGetId([
            "Calle" => $calle,
            "Colonia" => $colonia,
            "NumeroExterior" => $noExterior,
            "NumeroInterior" => $noInterior,
            "Manzana" => $manzana,
            "Lote" => $lote,
            "CodigoPostal" => $codigoPostal,
            "Aforo" => $aforo,
            "Plaza" => $plaza,
            "NombrePlaza" => $nombrePlaza,
            "Tipo" => $tipoDomicilio,
            "UbicacionMaps" => $coordenadas
        ]);
        DB::table('Padr_onLicencia') #insercio Por  subir
            ->where('id', '=', $idLicenciaRevalidacion)->update(['GiroDetalle' => $giroPrincipal,'Correo'=>$correoElectronicoC,'Telefono'=>$telefonoContacto]);

            

        $localInsertAltaSolicitud = DB::table('SolicitudLicencia')->insertGetId([
            "FechaSolicitud" => date("Y-m-d H:i:s"),
            "Contribuyente" => $idContribuyente,
            "GiroPrincipal" => $giroPrincipal,
            "GirosAnexos" => $arryGirosAnexos,
            "Superficie" => $superficie,
            "HorarioJSON" => $ObjetoHorarioJSon,
            "Horario" => $Horario,
            "idDatosLocal" => $localInsertLocal,
            "idPadronLicencia" => $idLicenciaRevalidacion,
            //"Ubicacion"=>NULL,
            "NumeroEmpleados" => $noEmpleado,
            "Inversion" => $inversion,
            "Email" => $correoElectronicoC,
            "telefono" => $telefonoContacto,
            "NombreEstablecimiento" => strtoupper($nombreComercial),
            "Observaciones" => $Observaciones,
            "folioSolicitud" => $FolioAnterior,
            "Categoria" => 1,

        ]);

        $idSolicitudLicenciaHistorial = DB::table("SolicitudLicenciaHistorial")->insertGetId([

            "Estatus" => 1,
            "Fecha" => date("Y-m-d"),
            "FechaTermino" => NULL,
            "idSolicitud" => $localInsertAltaSolicitud,
            "Categoria" => 1,
            "UsuarioRegistra" => $usuarioRegistra,
            "Tipo" => 2

        ]);


        $idImagenesSubicas = $this->SubirImagenV2($ListaDocumentos, $localInsertAltaSolicitud, $Cliente);

        $RutaDelPDFGenerado = $this->ObtenerPDFLicencia($localInsertAltaSolicitud, 56);


        return response()->json([
            'success' => '200',
            'result' => $localInsertAltaSolicitud,
            'Ruta' => $RutaDelPDFGenerado
        ]);
    }
    public function postInsertAltaLicenciaRefrendoAltaMediaBajo(Request $request)
    {

        /*

//General general

   ObjetoAltaLicencia.telefonoContacto=telefonoContacto;
   ObjetoAltaLicencia.giroPrincipal=selecGiros;
   ObjetoAltaLicencia.correoElectronicoC=correoElectronicoC;
   ObjetoAltaLicencia.inversion=inversion;
   ObjetoAltaLicencia.superficie=superficie;
   ObjetoAltaLicencia.noEmpleado=noEmpleado;
   ObjetoAltaLicencia.ubicacion=ubicacion;
   //Domicilio
   ObjetoAltaLicencia.calle=calle;
   ObjetoAltaLicencia.colonia=colonia;
   ObjetoAltaLicencia.noInterior=noInterior;
   ObjetoAltaLicencia.Exterior=Exterior;
   ObjetoAltaLicencia.manzana=manzana;
   ObjetoAltaLicencia.lote=lote;
   ObjetoAltaLicencia.codigoPostal=codigoPostal;
   ObjetoAltaLicencia.aforo=aforo;
   ObjetoAltaLicencia.plaza=plaza;
   ObjetoAltaLicencia.nombrePlaza=nombrePlaza;
   ObjetoAltaLicencia.tipoDomicilio=tipoDomicilio;

*/

        $Cliente   = $request->cliente;
        $TipoNegocio   = $request->TipoNegocio;
        $FolioAnterior   = $request->FolioAnterior;
        $arryGirosAnexos   = $request->arryGirosAnexos;
        $ObjetoHorarioJSon   = $request->ObjetoHorarioJSon;
        $Horario   = $request->Horario;
        $impacto   = $request->impacto;
        $giroPrincipal   = $request->giroPrincipal;
        $riesgo   = $request->riesgo;
        $idPredial   = null;
        $idLicenciaRevalidacion   = $request->idLicenciaRevalidacion;

        $idContribuyente   = $request->idContribuyente;
        $coordenadas   = $request->coordenadas;

        $nombreComercial   = $request->nombreComercial;
        $municipio   = $request->municipio;
        $selecLocalidad   = $request->selecLocalidad;
        $telefonoContacto   = $request->telefonoContacto;
        $giroPrincipal   = $request->giroPrincipal;
        $correoElectronicoC   = $request->correoElectronicoC;
        $inversion   = $request->inversion;
        $superficie   = $request->superficie;
        $noEmpleado   = $request->noEmpleado;
        //$ubicacion   = $request->ubicacion;


        $calle   = $request->calle;
        $colonia   = $request->colonia;
        $noInterior   = $request->noInterior;
        $noExterior   = $request->noExterior;
        $manzana   = $request->manzana;
        $lote   = $request->lote;
        $codigoPostal   = $request->codigoPostal;
        $aforo   = $request->aforo;
        $plaza   = $request->plaza;
        $nombrePlaza   = $request->nombrePlaza;
        $tipoDomicilio   = $request->tipoDomicilio;
        $ListaDocumentos = $request->ListaDocumentos;
        $Observaciones = $request->Observaciones;
       
        /*
     ObjetoAltaLicencia.ParqueIndustrial = ParqueIndustrial;
ObjetoAltaLicencia.NoProveedores = NoProveedores;
ObjetoAltaLicencia.OrigenCapital = OrigenCapital;
ObjetoAltaLicencia.exportaciones = exportaciones;
        */
        $ParqueIndustrial = $request->ParqueIndustrial;
        $NoProveedores = $request->NoProveedores;
        $OrigenCapital = $request->OrigenCapital;
        $exportaciones = $request->exportaciones;
        $impacto = $request->impacto;
$impactoValorReal=($impacto==3)?8:($impacto==2?9:10);
        Funciones::selecionarBase(56);

        //$sqlInserAltaLicencia = "INSERT INTO `suinpac_56`.`SolicitudLicencia` (`id`, `FechaSolicitud`, `Contribuyente`, `GiroPrincipal`, `GirosAnexos`, `Superficie`, `Tipo`, `idDatosLocal`, `Ubicacion`, `NumeroEmpleados`, `Inversion`, `FechaTermino`, `Estatus`, `Email`, `NombreEstablecimiento`) VALUES (NULL, '2022-02-10 00:00:00', 9226, 121, NULL, NULL, 1, 1, NULL, 10, 10000.00, NULL, 0, 'demo@suinpac.com', 'Manolo S.A. de C.V.')";    
        $localInsertLocal = DB::table('DatosLocal')->insertGetId([
            "Calle" => $calle,
            "Colonia" => $colonia,
            "NumeroExterior" => $noExterior,
            "NumeroInterior" => $noInterior,
            "Manzana" => $manzana,
            "Lote" => $lote,
            "CodigoPostal" => $codigoPostal,
            "Aforo" => $aforo,
            "Plaza" => $plaza,
            "NombrePlaza" => $nombrePlaza,
            "Tipo" => $tipoDomicilio,
            "UbicacionMaps" => $coordenadas
        ]);
        DB::table('Padr_onLicencia') #insercio Por  subir
            ->where('id', '=', $idLicenciaRevalidacion)->update(['NivelRiesgo' => $riesgo,'Superficie'=>$superficie,'Correo'=>$correoElectronicoC,'Telefono'=>$telefonoContacto]);

        $localInsertAltaSolicitud = DB::table('SolicitudLicencia')->insertGetId([
            "FechaSolicitud" => date("Y-m-d H:i:s"),
            "Contribuyente" => $idContribuyente,
            "GiroPrincipal" => $giroPrincipal,
            "GirosAnexos" => $arryGirosAnexos,
            "Superficie" => $superficie,
            "HorarioJSON" => $ObjetoHorarioJSon,
            "Horario" => $Horario,
            "idDatosLocal" => $localInsertLocal,
            "idPadronLicencia" => $idLicenciaRevalidacion,
            //"Ubicacion"=>NULL,
            "NumeroEmpleados" => $noEmpleado,
            "Inversion" => $inversion,
            "Email" => $correoElectronicoC,
            "telefono" => $telefonoContacto,
            "NombreEstablecimiento" => strtoupper($nombreComercial),
            "Observaciones" => $Observaciones,
            "folioSolicitud" => $FolioAnterior,
            "Categoria" => $impactoValorReal,
            "ParqueIndustrial" => $ParqueIndustrial,
            "NoProveedores" => $NoProveedores,
            "OrigenCapital" => $OrigenCapital,
            "Exportaciones" => $exportaciones,
            "TipoServicio" => $TipoNegocio,

        ]);

        $idSolicitudLicenciaHistorial = DB::table("SolicitudLicenciaHistorial")->insertGetId([

            "Estatus" => 1,
            "ProteccionCivil" => 1,
            "Fecha" => date("Y-m-d"),
            "FechaTermino" => NULL,
            "Categoria" => $impactoValorReal,
            "idSolicitud" => $localInsertAltaSolicitud,
            "Tipo" => 2
        ]);


        $idImagenesSubicas = $this->SubirImagenV2($ListaDocumentos, $localInsertAltaSolicitud, $Cliente);

        $RutaDelPDFGenerado = $this->ObtenerPDFRevalidacionBajoAltoMEdiano($localInsertAltaSolicitud, 56);


        return response()->json([
            'success' => '200',
            'result' => $localInsertAltaSolicitud,
            'Ruta' => $RutaDelPDFGenerado
        ]);
    }
    public function postInsertAltaLicenciaAltaMediaBajo(Request $request)
    {

        /*

//General general

   ObjetoAltaLicencia.telefonoContacto=telefonoContacto;
   ObjetoAltaLicencia.giroPrincipal=selecGiros;
   ObjetoAltaLicencia.correoElectronicoC=correoElectronicoC;
   ObjetoAltaLicencia.inversion=inversion;
   ObjetoAltaLicencia.superficie=superficie;
   ObjetoAltaLicencia.noEmpleado=noEmpleado;
   ObjetoAltaLicencia.ubicacion=ubicacion;
   //Domicilio
   ObjetoAltaLicencia.calle=calle;
   ObjetoAltaLicencia.colonia=colonia;
   ObjetoAltaLicencia.noInterior=noInterior;
   ObjetoAltaLicencia.Exterior=Exterior;
   ObjetoAltaLicencia.manzana=manzana;
   ObjetoAltaLicencia.lote=lote;
   ObjetoAltaLicencia.codigoPostal=codigoPostal;
   ObjetoAltaLicencia.aforo=aforo;
   ObjetoAltaLicencia.plaza=plaza;
   ObjetoAltaLicencia.nombrePlaza=nombrePlaza;
   ObjetoAltaLicencia.tipoDomicilio=tipoDomicilio;

*/

        $Cliente   = $request->cliente;
        $TipoNegocio   = $request->TipoNegocio;
        //$FolioAnterior   = $request->FolioAnterior;
        $arryGirosAnexos   = $request->arryGirosAnexos;
        $ObjetoHorarioJSon   = $request->ObjetoHorarioJSon;
        $Horario   = $request->Horario;
        $impacto   = $request->impacto;
        $riesgo   = $request->riesgo;
        $selecGiros   = $request->giroPrincipal;
        $idPredial   = null;
        $idLicenciaRevalidacion   = $request->idLicenciaRevalidacion;

        $idContribuyente   = $request->idContribuyente;
        $coordenadas   = $request->coordenadas;

        $nombreComercial   = $request->nombreComercial;
        $municipio   = $request->municipio;
        $selecLocalidad   = $request->selecLocalidad;
        $telefonoContacto   = $request->telefonoContacto;
        $giroPrincipal   = $request->giroPrincipal;
        $correoElectronicoC   = $request->correoElectronicoC;
        $inversion   = $request->inversion;
        $superficie   = $request->superficie;
        $noEmpleado   = $request->noEmpleado;
        //$ubicacion   = $request->ubicacion;


        $calle   = $request->calle;
        $colonia   = $request->colonia;
        $noInterior   = $request->noInterior;
        $noExterior   = $request->noExterior;
        $manzana   = $request->manzana;
        $lote   = $request->lote;
        $codigoPostal   = $request->codigoPostal;
        $aforo   = $request->aforo;
        $plaza   = $request->plaza;
        $CuentaPredial   = $request->CuentaPredial;
        $nombrePlaza   = $request->nombrePlaza;
        $tipoDomicilio   = $request->tipoDomicilio;
        $ListaDocumentos = $request->ListaDocumentos;
        $Observaciones = $request->Observaciones;
        //*adicionales */
        /*
     ObjetoAltaLicencia.ParqueIndustrial = ParqueIndustrial;
ObjetoAltaLicencia.NoProveedores = NoProveedores;
ObjetoAltaLicencia.OrigenCapital = OrigenCapital;
ObjetoAltaLicencia.exportaciones = exportaciones;
        */
        $ParqueIndustrial = $request->ParqueIndustrial;
        $NoProveedores = $request->NoProveedores;
        $OrigenCapital = $request->OrigenCapital;
        $exportaciones = $request->exportaciones;
        $impacto = $request->impacto;
        //8 bajo, 9 medio,10 alto
$impactoValorReal=($impacto==1)?10:($impacto==2?9:8);
        Funciones::selecionarBase(56);

        //$sqlInserAltaLicencia = "INSERT INTO `suinpac_56`.`SolicitudLicencia` (`id`, `FechaSolicitud`, `Contribuyente`, `GiroPrincipal`, `GirosAnexos`, `Superficie`, `Tipo`, `idDatosLocal`, `Ubicacion`, `NumeroEmpleados`, `Inversion`, `FechaTermino`, `Estatus`, `Email`, `NombreEstablecimiento`) VALUES (NULL, '2022-02-10 00:00:00', 9226, 121, NULL, NULL, 1, 1, NULL, 10, 10000.00, NULL, 0, 'demo@suinpac.com', 'Manolo S.A. de C.V.')";    
        $localInsertLocal = DB::table('DatosLocal')->insertGetId([
            "Calle" => $calle,
            "Colonia" => $colonia,
            "NumeroExterior" => $noExterior,
            "NumeroInterior" => $noInterior,
            "Manzana" => $manzana,
            "Lote" => $lote,
            "CodigoPostal" => $codigoPostal,
            "Aforo" => $aforo,
            "Plaza" => $plaza,
            "NombrePlaza" => $nombrePlaza,
            "Tipo" => $tipoDomicilio,
            "UbicacionMaps" => $coordenadas
        ]);
        /*DB::table('Padr_onLicencia') #insercio Por  subir
            ->where('id', '=', $idLicenciaRevalidacion)->update(['NivelRiesgo' => $riesgo]);*/
            $an_oActual=DATE('y');
            $sqlFolioMaximo='SELECT (MAX(folioSolicitud)+1) AS FolioMaxivo  FROM SolicitudLicencia WHERE folioSolicitud LIKE "'.$an_oActual.'%"';
            $fmaz = Funciones::ObtenValor($sqlFolioMaximo, "FolioMaxivo");//DB::select($sqlFolioMaximo);
            $fmaz=($fmaz==null)?(date('y')."0001"):$fmaz;


        $localInsertAltaSolicitud = DB::table('SolicitudLicencia')->insertGetId([
            "FechaSolicitud" => date("Y-m-d H:i:s"),
            "Contribuyente" => $idContribuyente,
            "GiroPrincipal" => $selecGiros,
            "GirosAnexos" => $arryGirosAnexos,
            "Superficie" => $superficie,
            "HorarioJSON" => $ObjetoHorarioJSon,
            "Horario" => $Horario,
            "idDatosLocal" => $localInsertLocal,
            "idPadronLicencia" => $idLicenciaRevalidacion,
            "CuentaPredial" => $CuentaPredial,
            //"Ubicacion"=>NULL,
            "NumeroEmpleados" => $noEmpleado,
            "Inversion" => $inversion,
            "Email" => $correoElectronicoC,
            "telefono" => $telefonoContacto,
            "NombreEstablecimiento" => strtoupper($nombreComercial),
            "Observaciones" => $Observaciones,
            //"folioSolicitud" => $FolioAnterior,
            "Categoria" => $impactoValorReal,
            "ParqueIndustrial" => $ParqueIndustrial,
            "NoProveedores" => $NoProveedores,
            "OrigenCapital" => $OrigenCapital,
            "Exportaciones" => $exportaciones,
            "TipoServicio" => $TipoNegocio,

        ]);
        $folioSolicitudFormato = DATE('y') . str_pad($localInsertAltaSolicitud, 4, "0", STR_PAD_LEFT);
        DB::table('SolicitudLicencia') #insercio Por  subir
            ->where('id', '=', $localInsertAltaSolicitud)->update(['folioSolicitud' => $fmaz]);

        $idSolicitudLicenciaHistorial = DB::table("SolicitudLicenciaHistorial")->insertGetId([

            "Estatus" => 1,
            "ProteccionCivil" => 1,
            "Categoria" => $impactoValorReal,
            "Fecha" => date("Y-m-d"),
            "FechaTermino" => NULL,
            "idSolicitud" => $localInsertAltaSolicitud,
            "Tipo" => 1
        ]);


        $idImagenesSubicas = $this->SubirImagenV2($ListaDocumentos, $localInsertAltaSolicitud, $Cliente);

        $RutaDelPDFGenerado = $this->ObtenerPDFRevalidacionBajoAltoMEdiano($localInsertAltaSolicitud, 56);


        return response()->json([
            'success' => '200',
            'result' => $localInsertAltaSolicitud,
            'Ruta' => $RutaDelPDFGenerado
        ]);
    }
    public function getMunicipioPorCliente(Request $request)
    {
        $Cliente   = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $sqlMunicipio = "SELECT m.id,m.Nombre FROM  Municipio m 
      
        WHERE m.id=(SELECT df.Municipio FROM Cliente c 
        INNER JOIN DatosFiscalesCliente df
        ON(df.id=c.DatosFiscales)
            WHERE c.id=$Cliente)
        ";
        $Municipio = DB::select($sqlMunicipio);
        return response()->json([
            'success' => '200',
            'result' => $Municipio
        ]);
    }
    /*  */
    public function getEntidadPorCliente(Request $request)
    {
        $Cliente   = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $sqlEntidad = "SELECT m.id,m.Nombre FROM  EntidadFederativa m 
      
        WHERE m.id=(SELECT df.EntidadFederativa FROM Cliente c 
        INNER JOIN DatosFiscalesCliente df
        ON(df.id=c.DatosFiscales)
            WHERE c.id=$Cliente)
        ";
        $Entidad = DB::select($sqlEntidad);
        return response()->json([
            'success' => '200',
            'result' => $Entidad
        ]);
    }

    public function getPaisporCliente(Request $request)
    {
        $Cliente   = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $sqlPais = "SELECT m.id,m.Nombre FROM  Pa_is m 
      
        WHERE m.id=(SELECT df.Pa_is FROM Cliente c 
        INNER JOIN DatosFiscalesCliente df
        ON(df.id=c.DatosFiscales)
            WHERE c.id=$Cliente)
        ";
        $Pais = DB::select($sqlPais);
        return response()->json([
            'success' => '200',
            'result' => $Pais
        ]);
    }
    /*  */
    public function getAccesoLicenciasDeFuncionamiento(Request $request)
    {
        $Cliente   = $request->Cliente;
        $RFC   = $request->RFC;
        $folio   = $request->folio;
        Funciones::selecionarBase($Cliente);
        $servicios = DB::select("SELECT pl.Estatus,pl.Folio,pl.FolioAnterior,pl.Cliente,c.rfc,pl.NombreEstablecimiento,COALESCE(pl.Observacion,'') AS Observacion,pl.Domicilio, pl.FolioAnterior,pl.FechaInicioActividades,pl.Telefono, (CASE pl.NivelRiesgo
            WHEN 1 THEN
                'Bajo'
                WHEN 2 THEN
                'Medio'
                WHEN 3 THEN
                'Alto'
            ELSE
             'Bajo'
        END) AS NivelRiesgo,
        (SELECT Nombre FROM Municipio WHERE id =pl.municipio) AS Municipio,
        (SELECT Nombre FROM Localidad WHERE id=pl.localidad) AS Localidad
        ,
        (SELECT CONCAT_WS(' ',Nombres, ApellidoPaterno,ApellidoMaterno)  AS Propietario FROM Contribuyente WHERE id=pl.Contribuyente) AS Contribuyente,
        (
        SELECT g.Descripci_on from GiroDetalle gd INNER JOIN
        Giro g  ON (gd.idGiro=g.id) WHERE gd.id =pl.GiroDetalle ) AS GiroPrincipal
          FROM Padr_onLicencia pl INNER JOIN Contribuyente c ON (c.id=pl.Contribuyente) WHERE pl.Folio LIKE'%$folio%' AND c.rfc='$RFC' ");

        if (count($servicios) == 0) {
            return  $response = [
                'success' => '404',
                'result' => "RFC O Folio No encontrado"
            ];
        }

        $response = [
            'success' => '200',
            'result' => $servicios
        ];
        $result = Funciones::respondWithToken($response);
        return $result;
    }


    public static function convert_from_latin1_to_utf8_recursively($dat)
    {
        if (is_string($dat)) {
            return utf8_encode($dat);
        } elseif (is_array($dat)) {
            $ret = [];
            foreach ($dat as $i => $d) $ret[$i] = self::convert_from_latin1_to_utf8_recursively($d);

            return $ret;
        } elseif (is_object($dat)) {
            foreach ($dat as $i => $d) $dat->$i = self::convert_from_latin1_to_utf8_recursively($d);

            return $dat;
        } else {
            return $dat;
        }
    }
    public function getContribuyenteByRfcCurp(Request $request)
    {
        $Cliente  = $request->Cliente;
        $dataSerch = $request->datoBusqueda;

        Funciones::selecionarBase(56);
        $sqlContribuyente = " SELECT c.id AS ContribuyenteID, (CASE c.PersonalidadJur_idica
        WHEN 1 THEN
            'Persona Fisica'
            WHEN 2 THEN
            'Persona Moral'
            WHEN 3 THEN
            'Juridica Colectiva'
        ELSE
         ''
         END) AS PersonalidadJur_idica, c.Rfc , c.Curp  ,COALESCE(c.NombreComercial,CONCAT_WS(' ',c.Nombres))AS  Nombres ,COALESCE(c.NombreComercial,CONCAT_WS(' ',c.Nombres,c.ApellidoPaterno,c.ApellidoMaterno))AS  NombreORazonSocial , c.ApellidoPaterno  ,c.ApellidoMaterno ,c.NombreComercial  , c.Tel_efonoParticular, c.Tel_efonoCelular, c.CorreoElectr_onico  , c.Pa_is_c, c.EntidadFederativa_c, c.Municipio_c, c.Localidad_c, c.Colonia_c, COALESCE(c.Calle_c,'')AS Calle_c, c.N_umeroInterior_c, c.N_umeroExterior_c, c.C_odigoPostal_c, df.id AS DatosFiscalesID, df.RFC AS RFC_DatosFiscales, df.NombreORaz_onSocial, df.Pa_is AS Pa_is_DatosFiscales, df.EntidadFederativa AS EntidadFederativa_DatosFiscales, df.Municipio AS Municipio_DatosFiscales, df.Localidad AS Localidad_DatosFiscales, df.Colonia AS Colonia_DatosFiscales, df.Calle AS Calle_DatosFiscales, df.N_umeroInterior AS NumeroInterior_DatosFiscales, df.N_umeroExterior AS NumeroExterior_DatosFiscales,df.C_odigoPostal AS CodigoPostal_DatosFiscales, df.Referencia, df.R_egimenFiscal, c.NombreComercial, c.RepresentanteLegal, (SELECT Nombre FROM Localidad WHERE id=c.Localidad_c)AS Localidad  FROM Contribuyente AS c INNER JOIN DatosFiscales AS df  ON(df.id = c.DatosFiscales) WHERE c.Estatus=1 and  (c.Rfc LIKE '%$dataSerch%' OR c.Curp LIKE '%$dataSerch%') limit 1";



        $contribuyente = DB::select($sqlContribuyente);


        if (count($contribuyente) == 0) {
            return response()->json([
                'success' => '203',
                'result' => 'Contribuyente no encontrado'
            ]);
        }


        return response()->json([
            'success' => '200',
            'result' => $this->convert_from_latin1_to_utf8_recursively($contribuyente) //json_encode($contribuyente, JSON_UNESCAPED_UNICODE)
        ]);
    }
    public function getContribuyenteByRFCIndex(Request $request)
    {
        $Cliente  = $request->Cliente;
        $dataSerch = $request->datoBusqueda;

        Funciones::selecionarBase($Cliente);
        $sqlContribuyente = " SELECT c.id AS ContribuyenteID, (CASE c.PersonalidadJur_idica
        WHEN 1 THEN
            'Persona Fisica'
            WHEN 2 THEN
            'Persona Moral'
            WHEN 3 THEN
            'Juridica Colectiva'
        ELSE
         ''
         END) AS PersonalidadJur_idica, c.Rfc , c.Curp  ,COALESCE(c.NombreComercial,CONCAT_WS('',c.Nombres,c.ApellidoPaterno,c.ApellidoMaterno))AS  Nombres  , c.ApellidoPaterno  ,c.ApellidoMaterno ,c.NombreComercial  , c.Tel_efonoParticular, c.Tel_efonoCelular, c.CorreoElectr_onico  , c.Pa_is_c, c.EntidadFederativa_c, c.Municipio_c, c.Localidad_c, c.Colonia_c, c.Calle_c, c.N_umeroInterior_c, c.N_umeroExterior_c, c.C_odigoPostal_c, df.id AS DatosFiscalesID, df.RFC AS RFC_DatosFiscales, df.NombreORaz_onSocial, df.Pa_is AS Pa_is_DatosFiscales, df.EntidadFederativa AS EntidadFederativa_DatosFiscales, df.Municipio AS Municipio_DatosFiscales, df.Localidad AS Localidad_DatosFiscales, df.Colonia AS Colonia_DatosFiscales, df.Calle AS Calle_DatosFiscales, df.N_umeroInterior AS NumeroInterior_DatosFiscales, df.N_umeroExterior AS NumeroExterior_DatosFiscales,df.C_odigoPostal AS CodigoPostal_DatosFiscales, df.Referencia, df.R_egimenFiscal, c.NombreComercial, c.RepresentanteLegal, (SELECT Nombre FROM Localidad WHERE id=c.Localidad_c)AS Localidad  FROM Contribuyente AS c INNER JOIN DatosFiscales AS df  ON(df.id = c.DatosFiscales) WHERE c.Estatus=1 and ( UPPER(c.Curp) = UPPER('$dataSerch')  OR UPPER(c.Rfc)=UPPER('$dataSerch'))  limit 1";



        $contribuyente = DB::select($sqlContribuyente);


        if (count($contribuyente) == 0) {
            return response()->json([
                'success' => '203',
                'result' => 'Contribuyente no encontrado'
            ]);
        }


        return response()->json([
            'success' => '200',
            'result' => $this->convert_from_latin1_to_utf8_recursively($contribuyente) //json_encode($contribuyente, JSON_UNESCAPED_UNICODE)
        ]);
    }

    /* FOLIO */
    public function getContribuyenteByRFCFOLIO(Request $request)
    {
        $Cliente  = $request->Cliente;
        $dataSerch = $request->datoBusqueda;
        $dataSerch1 = $request->datoBusqueda1;

        Funciones::selecionarBase($Cliente);
        $sql = "SELECT pl.* FROM Padr_onLicencia pl 
        
        INNER JOIN Contribuyente c ON (c.id=pl.Contribuyente)	 
        WHERE  c.Estatus=1 and pl.NivelRiesgo   IN(4) and pl.Folio='$dataSerch1' AND  (UPPER(c.Curp) = UPPER('$dataSerch')  OR UPPER(c.Rfc)=UPPER('$dataSerch'))";

        $padronLicencia = DB::select($sql);
        $cantidadEnLicencia = count($padronLicencia);




        $sqlContribuyente = " SELECT sl.id as idSolicitud,sl.*,dl.*,c.*,(SELECT (SELECT Descripci_on FROM Giro WHERE id=g.idGiro ) AS GiroPrincipal FROM GiroDetalle AS g WHERE g.id=sl.GiroPrincipal) as GiroP FROM SolicitudLicencia sl 
        INNER JOIN DatosLocal  dl ON(dl.id=sl.idDatosLocal)
        INNER JOIN Contribuyente c ON (c.id=sl.Contribuyente)

           WHERE sl.Categoria IN(1) AND  sl.folioSolicitud='$dataSerch1' AND  (UPPER(c.Curp) = UPPER('$dataSerch')  OR UPPER(c.Rfc)=UPPER('$dataSerch')) limit 1";



        $contribuyente = DB::select($sqlContribuyente);


        if (count($contribuyente) == 0 && $cantidadEnLicencia == 0) {
            return response()->json([
                'success' => '203',

                'result' => 'Contribuyente no encontrado'
            ]);
        }


        return response()->json([
            'success' => '200',
            'cantidadPadronLicencias' => $cantidadEnLicencia,
            'ValoresPadronLicencias' => $padronLicencia,
            'cantidadSolicitud' => count($contribuyente),
            'result' => $this->convert_from_latin1_to_utf8_recursively($contribuyente ) //json_encode($contribuyente, JSON_UNESCAPED_UNICODE)
        ]);
    }
    public function getContribuyenteByRFCFOLIOAltoMedioBajo(Request $request)
    {
        $Cliente  = $request->Cliente;
        $dataSerch = $request->datoBusqueda;
        $dataSerch1 = $request->datoBusqueda1;

        Funciones::selecionarBase($Cliente);
        $sql = "SELECT pl.* FROM Padr_onLicencia pl 
        
        INNER JOIN Contribuyente c ON (c.id=pl.Contribuyente)	 
        WHERE  c.Estatus=1 AND (pl.NivelRiesgo is NULL OR pl.NivelRiesgo   NOT IN(4))  and pl.Folio='$dataSerch1' AND  ((UPPER(c.Curp) = UPPER('$dataSerch')  OR UPPER(c.Rfc)=UPPER('$dataSerch')))";

        $padronLicencia = DB::select($sql);
        $cantidadEnLicencia = count($padronLicencia);




        $sqlContribuyente = " SELECT sl.id as idSolicitud,sl.*,dl.*,c.*,(SELECT (SELECT Descripci_on FROM Giro WHERE id=g.idGiro ) AS GiroPrincipal FROM GiroDetalle AS g WHERE g.id=sl.GiroPrincipal) as GiroP FROM SolicitudLicencia sl 
        INNER JOIN DatosLocal  dl ON(dl.id=sl.idDatosLocal)
        INNER JOIN Contribuyente c ON (c.id=sl.Contribuyente)

           WHERE sl.Categoria IN(8,9,10) AND  sl.folioSolicitud='$dataSerch1' AND  (UPPER(c.Curp) = UPPER('$dataSerch')  OR UPPER(c.Rfc)=UPPER('$dataSerch'))";



        $contribuyente = DB::select($sqlContribuyente);


        if (count($contribuyente) == 0 && $cantidadEnLicencia == 0) {
            return response()->json([
                'success' => '203',

                'result' => 'Contribuyente no encontrado'
            ]);
        }


        return response()->json([
            'success' => '200',
            'cantidadPadronLicencias' => $cantidadEnLicencia,
            'ValoresPadronLicencias' => $padronLicencia,
            'cantidadSolicitud' => count($contribuyente),
            'result' => $contribuyente //$this->convert_from_latin1_to_utf8_recursively($contribuyente) //json_encode($contribuyente, JSON_UNESCAPED_UNICODE)
        ]);
    }
    public function getContribuyenteByRFCFOLIOAnterior(Request $request)
    {
        $Cliente  = $request->Cliente;
        $dataSerch = $request->datoBusqueda;
        $dataSerch1 = $request->datoBusqueda1;

        Funciones::selecionarBase($Cliente);
        /*$sqlContribuyente = " SELECT * FROM SolicitudLicencia sl 
        INNER JOIN Contribuyente c ON (c.id=sl.Contribuyente)

           WHERE sl.Id='' AND  UPPER(c.Curp) = UPPER('$dataSerch')  OR UPPER(c.Rfc)=UPPER('$dataSerch') limit 1";*/
        $sqlContribuyente = "SELECT pl.* FROM Padr_onLicencia pl 
INNER JOIN Contribuyente c ON (c.id=pl.Contribuyente)

   WHERE pl.NivelRiesgo=4 AND pl.Folio='$dataSerch1' AND  (UPPER(c.Curp) = UPPER('$dataSerch')  OR UPPER(c.Rfc)=UPPER('$dataSerch')) limit 1";


        $contribuyente = DB::select($sqlContribuyente);


        if (count($contribuyente) == 0) {
            return response()->json([
                'success' => '203',
                'result' => 'Contribuyente no encontrado'
            ]);
        }


        return response()->json([
            'success' => '200',
            'result' => $this->convert_from_latin1_to_utf8_recursively($contribuyente) //json_encode($contribuyente, JSON_UNESCAPED_UNICODE)
        ]);
    }
    //sin ruta
    public function getPersonalidadJuridicaPorCliente(Request $request)
    {
        $Cliente  = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $sqlPersonalidadJur_idica = "SELECT id, Descripci_on FROM PersonalidadJur_idica";
        $PersonalidadJur_idica = DB::select($sqlPersonalidadJur_idica);
        return response()->json([
            'success' => '200',
            'result' => $PersonalidadJur_idica
        ]);
    }

    /* PREDIAL */

    public function getDatosPredial(Request $request)
    {
        $Cliente  = $request->Cliente;
        $dataSerch = $request->datoBusqueda;

        Funciones::selecionarBase($Cliente);
        $sql = "SELECT c.id, c.ClaveCatastral, c.Cuenta, c.CuentaAnterior, (m.Nombre) as Municipio , l.Nombre as Localidad, c.Ubicaci_on,c.SuperficieTerreno,c.ValorCatastral ,tp.Concepto as Tipopredio  ,c.Indiviso  FROM Padr_onCatastral AS c INNER JOIN Municipio AS m   ON (m.id = c.Municipio ) INNER JOIN TipoPredio  AS tp    ON (tp .id = c.TipoPredio  ) INNER JOIN Localidad   AS l   ON (l.id  = c.Localidad  ) WHERE c.ClaveCatastral = $dataSerch  OR c.Cuenta = $dataSerch";
        //$sqlPredial =  DB::select($sql);
        $Predial = DB::select($sql);
        if (count($Predial) == 0) {
            return response()->json([
                'success' => '203',
                'result' => 'Predial no encontrado'
            ]);
        }


        return response()->json([
            'success' => '200',
            'result' => $Predial
        ]);
    }
    public function getLicenciasByContribuyente(Request $request)
    {
        $Cliente  = 56; //$request->Cliente;
        $idContribuyente = $request->idContribuyente;

        Funciones::selecionarBase($Cliente);
        $sql = "SELECT pl.id,pl.Folio,pl.FolioAnterior,pl.NombreEstablecimiento ,COALESCE((SELECT Ruta FROM CelaRepositorio WHERE Tabla='LicenciaEmitida' AND idTabla=sl.id),NULL) AS Licencia, 
        COALESCE( pl.GiroAnterior,(SELECT (SELECT Descripci_on FROM Giro WHERE id=gd.idGiro ) AS GiroPrincipal FROM GiroDetalle AS gd WHERE gd.id= sl.GiroPrincipal)) AS GIRO,
        sh.FechaDesarrolloEconomico,sh.Tipo,
        TIMESTAMPDIFF(DAY, DATE(sh.FechaDesarrolloEconomico), NOW()) as diasTranscurrridos
         FROM Padr_onLicencia pl 
		INNER JOIN SolicitudLicencia sl 
		on(pl.Id=sl.idPadronLicencia)
        INNER JOIN SolicitudLicenciaHistorial sh 
		ON(sh.idSolicitud=sl.id)
		WHERE pl.Contribuyente= $idContribuyente";
        //$sqlPredial =  DB::select($sql);
        $Predial = DB::select($sql);
        if (count($Predial) == 0) {
            return response()->json([
                'success' => '203',
                'result' => 'Sin Registros'
            ]);
        }


        return response()->json([
            'success' => '200',
            'result' => $Predial
        ]);
    }
    public function getLicenciasEnProcesByContribuyente(Request $request)
    {
        $Cliente  = 56; //$request->Cliente;
        $idContribuyente = $request->idContribuyente;

        Funciones::selecionarBase($Cliente);
        $sql = " SELECT
	sl.id,
	(sl.FechaSolicitud) as FechaSolicitud,sl.folioSolicitud,
	( SELECT (SELECT Descripci_on FROM Giro WHERE id=gd.idGiro ) AS GiroPrincipal FROM GiroDetalle AS gd WHERE gd.id= sl.GiroPrincipal ) AS GIRO,
	sl.NombreEstablecimiento,sh.Estatus,sh.FechaLimite,
    (SELECT descripcion FROM SolicitudLicenciaCategorias WHERE id=sl.Categoria) AS TipoLicencia,
    sl.Categoria,
    COALESCE((SELECT crdc.Ruta FROM CelaRepositorioDocumentoContribuyente crdc WHERE  crdc.idRepositorio=sh.Fundamento),NULL) AS fundamentoReglamento,
	COALESCE((SELECT crdc.Ruta FROM CelaRepositorioDocumentoContribuyente crdc WHERE  crdc.idRepositorio=sh.DocPC),NULL) AS FundamentoProteccionCivil,
    COALESCE((SELECT crdc.Ruta FROM CelaRepositorioDocumentoContribuyente crdc WHERE  crdc.idRepositorio=sh.DocJuridico),NULL) AS FundamentoJuridico  
    ,sh.FechaLimite,sh.Tipo,sh.DesarrolloEconomico
FROM
	SolicitudLicencia as sl
	INNER JOIN SolicitudLicenciaHistorial sh ON ( sh.idSolicitud = sl.id ) 
WHERE
	 sl.Contribuyente= $idContribuyente";
        //$sqlPredial =  DB::select($sql);
        $Predial = DB::select($sql);
        if (count($Predial) == 0) {
            return response()->json([
                'success' => '203',
                'result' => 'Sin Registros'
            ]);
        }


        return response()->json([
            'success' => '200',
            'result' => $Predial
        ]);
    }


    /* insert contribuyente */
    public function postInsertAltaContribuyente(Request $request)
    {



        $Cliente   = 56;
        $PersonalidadJuridica   = $request->PersonalidadJuridica;
        $Rfc   = $request->Rfc;
        $Curp   = $request->Curp;
        $TipoPersona = 2;
        $Nombres   = $request->Nombres;
        $ApellidoPaterno   = $request->ApellidoPaterno;
        $ApellidoMaterno   = $request->ApellidoMaterno;
        $selecPais   = $request->selecPais;
        $selecEntidad   = $request->selecEntidad;
        $selecMunicipio   = $request->selecMunicipio;
        $selecLocalidad   = $request->selecLocalidad;
        $Colonia   = $request->Colonia;
        $Calle   = $request->Calle;
        $Exterior   = $request->Exterior;
        $codigo   = $request->codigo;
        $NombreComercial   = $request->NombreComercial;
        $Interior   = $request->Interior;
        $TelParticular   = $request->TelParticular;
        $Correo   = $request->Correo;
        $TelCelular   = $request->TelCelular;
        $RepresentanteLegal   = $request->RepresentanteLegal;


        /* fiscales */
        $RfcFiscal   = $request->RfcFiscal;
        $paisFiscal   = $request->paisFiscal;
        $FederativaFiscal   = $request->FederativaFiscal;
        $municipioFiscal   = $request->municipioFiscal;
        $LocalidadFiscal   = $request->LocalidadFiscal;
        $coloniaFiscal   = $request->coloniaFiscal;
        $calleFiscal   = $request->calleFiscal;
        $interiorFiscal   = $request->interiorFiscal;
        $exteriorFiscal   = $request->exteriorFiscal;
        $codigoFiscal   = $request->codigoFiscal;
        $referenciaFiscal   = $request->referenciaFiscal;
        $regimenFiscal   = $request->regimenFiscal;
        $NombreFiscal   = $request->NombreFiscal;


$Rfc=(strlen($Rfc)>13)? substr($Rfc,0,12) :$Rfc;



        Funciones::selecionarBase(56);

        //$selectRFCContribuyenteCount = "SELECT * FROM Contribuyente WHERE Rfc='$Rfc' OR CURP='$Rfc'";
        $selectRFCContribuyenteCount = "	SELECT * FROM Contribuyente c 
        INNER JOIN DatosFiscales df  ON (c.DatosFiscales=df.Id)
        WHERE  (c.Rfc='$Rfc' OR c.CURP='$Rfc') OR (c.Rfc='$RfcFiscal' OR c.CURP='$RfcFiscal') and c.Estatus in(1)";
        //$selectCURPContribuyenteCount = "SELECT * FROM Contribuyente WHERE Rfc='$Curp' OR CURP='$Curp'";
      //  $selectFiscalesCount = "SELECT * FROM DatosFiscales WHERE RFC='$RfcFiscal'";
       // $contribuyenteRFC = DB::select($selectRFCContribuyenteCount);
        //$contribuyenteCURP = DB::select($selectCURPContribuyenteCount);
        $fiscalesRFC = DB::select($selectRFCContribuyenteCount);

        if (count($fiscalesRFC) > 0) {
            //! Significa que tiene un registro ya, debe verificar la curp o rfc ingresados

            return response()->json([
                'success' => '203',
                //'result' => $localInsertAltaSolicitud
                'result' => 'El contribuyente ya existe'

            ]);
        }

        //$sqlInserAltaLicencia = "INSERT INTO `suinpac_56`.`SolicitudLicencia` (`id`, `FechaSolicitud`, `Contribuyente`, `GiroPrincipal`, `GirosAnexos`, `Superficie`, `Tipo`, `idDatosLocal`, `Ubicacion`, `NumeroEmpleados`, `Inversion`, `FechaTermino`, `Estatus`, `Email`, `NombreEstablecimiento`) VALUES (NULL, '2022-02-10 00:00:00', 9226, 121, NULL, NULL, 1, 1, NULL, 10, 10000.00, NULL, 0, 'demo@suinpac.com', 'Manolo S.A. de C.V.')";    
        $InserAltaFiscales = DB::table('DatosFiscales')->insertGetId([
            "RFC" => $RfcFiscal,
            "PersonalidadJur_idica" => $PersonalidadJuridica,

            "NombreORaz_onSocial" => $NombreFiscal,
            "Pa_is" => $paisFiscal,
            "EntidadFederativa" => $FederativaFiscal,
            "Municipio" => $municipioFiscal,
            "Localidad" => $LocalidadFiscal,
            "Colonia" => $coloniaFiscal,

            "Referencia" => $referenciaFiscal,
            "Calle" => $calleFiscal,
            "N_umeroInterior" => $interiorFiscal,
            "N_umeroExterior" => $exteriorFiscal,
            "C_odigoPostal" => $codigoFiscal,
            "R_egimenFiscal" => $regimenFiscal,
            "CorreoElectr_onico" => $Correo,
            "NombreComercial" => $NombreComercial
        ]);
        $InserAltaContribuyente = DB::table('Contribuyente')->insertGetId([
            "Rfc" => $Rfc,
            "Curp" => $Curp,
            "Nombres" => $Nombres,
            "ApellidoPaterno" => $ApellidoPaterno,
            "ApellidoMaterno" => $ApellidoMaterno,
            "NombreComercial" => $NombreComercial,
            "RepresentanteLegal" => $RepresentanteLegal,
            "Tel_efonoParticular" => $TelParticular,
            "Tel_efonoCelular" => $TelCelular,
            "CorreoElectr_onico" => $Correo,
            "Cliente" => $Cliente,
            "DatosFiscales" => $InserAltaFiscales,


            "RepresentanteLegal" => $RepresentanteLegal,
            "PersonalidadJur_idica" => $PersonalidadJuridica,
            "TipoPersona" => $TipoPersona,


            "Pa_is_c" => $selecPais,
            "EntidadFederativa_c" => $selecEntidad,
            "Municipio_c" => $selecMunicipio,
            "Localidad_c" => $selecLocalidad,
            "Colonia_c" => $Colonia,
            "Calle_c" => $Calle,
            "N_umeroInterior_c" => $Interior,
            "N_umeroExterior_c" => $Exterior,
            "C_odigoPostal_c" => $codigo

        ]);


        return response()->json([
            'success' => '200',
            //'result' => $localInsertAltaSolicitud
            'result' => $InserAltaContribuyente

        ]);
    }

    /* obtener datos de licencia */
    public function getSolicitudLicencia(Request $request)
    {
        $Cliente  = $request->Cliente;
        $dataSerch = $request->id;

        Funciones::selecionarBase($Cliente);
        $sqlLicencia = "SELECT g.id, g.FechaSolicitud, g.Contribuyente,if(sh.Tipo=2,'Revalidación',if(sh.Tipo=1,'Alta',''))AS TipoRegistro, (SELECT (SELECT Descripci_on FROM Giro WHERE id=gd.idGiro ) AS GiroPrincipal FROM GiroDetalle AS gd WHERE gd.id=g.GiroPrincipal) as GiroPrincipal  , g.GirosAnexos, g.Superficie, sh.Juridico,sh.Tipo, g.idDatosLocal, g.Ubicacion, g.NumeroEmpleados, g.Inversion, g.FechaTermino, sh.Estatus,sh.DesarrolloEconomico, g.Email, g.NombreEstablecimiento,dl.UbicacionMaps
        FROM SolicitudLicencia g INNER JOIN SolicitudLicenciaHistorial sh ON ( sh.idSolicitud = g.id ) 			INNER JOIN DatosLocal dl ON(dl.id=g.idDatosLocal)   WHERE g.id = $dataSerch";



        $Licencia = DB::select($sqlLicencia);


        if (count($Licencia) == 0) {
            return response()->json([
                'success' => '203',
                'result' => 'Licencia no encontrado'
            ]);
        }


        return response()->json([
            'success' => '200',
            'result' => $this->convert_from_latin1_to_utf8_recursively($Licencia) //json_encode($contribuyente, JSON_UNESCAPED_UNICODE)
        ]);
    }
    /*  */

    /*
    Metodo para escribir en el repositoroop
    */
    /*  */
    public function SubirImagenV2($imagenes, $idSolicitud, $Cliente, $nombreTabla = 'SolicitudLicencia')
    {
        $arregloNombre = array();
        $arregloSize = array();
        $arregloRuta = array();
        $arregloIdDocumentos = array(); //debe ser int
        $datosRepo = "";
        $nombreTabla = "SolicitudLicencia";
        $ruta = "SolicitudLicencia"; //date("Y/m/d");
        $fechaHora = date("Y-m-d H:i:s");
        foreach ($imagenes as $arregloFoto) {
            #return $arregloFoto;
            $image_64 = $arregloFoto["data"]; //your base64 encoded data
            $extension = explode('/', explode(':', substr($image_64, 0, strpos($image_64, ';')))[1])[1];   // .jpg .png .pdf
            $replace = substr($image_64, 0, strpos($image_64, ',') + 1);
            // find substring fro replace here eg: data:image/png;base64,
            $image = str_replace($replace, '', $image_64);
            $image = str_replace(' ', '+', $image);
            $imageName = $arregloFoto["nombre"] . '_' . uniqid() . '.' . $extension;

            #return base64_decode($image);
            $size_in_bytes = (int) (strlen(rtrim($image_64, '=')) * 3 / 4);
            $size_in_kb    = $size_in_bytes / 1024;
            $size_in_mb    = $size_in_kb / 1024;
            #return $size_in_bytes;
            Storage::disk('repositorio')->put($Cliente . "/" . $ruta . "/" . $imageName, base64_decode($image));
            #return $size;
            #return "repositorio/FotosAgua".$imageName;
            $idDocumentoDetalle = ($arregloFoto["nombre"]);
            array_push($arregloNombre, $imageName);
            array_push($arregloIdDocumentos, $idDocumentoDetalle);
            array_push($arregloSize, $size_in_bytes);
            array_push($arregloRuta, "repositorio/" . $Cliente . "/" . $ruta . "/" . $imageName);
        }
        Funciones::selecionarBase(56); //$Cliente);
        //insertamos las rutas en celaRepositorio
        $contador = 0;
        //NOTE: se inserta en las evidencias del reporte
        $arrayIndicesCelaRepositorio = [];
        foreach ($arregloRuta as $ruta) {

            $CelaRepositorioDocumentoContribuyente = DB::table("CelaRepositorioDocumentoContribuyente")->insertGetId([
                'Tabla' => $nombreTabla,
                'idTabla' => $idSolicitud, //12,//$idRegistro,
                'Ruta' => $ruta,
                'Descripci_on' => 'pdf Licencias Cuautitlan',
                'idUsuario' => NULL, //$usuario,
                'FechaDeCreaci_on' => $fechaHora,
                'Estado' => 1,
                'Reciente' => 1,
                'NombreOriginal' => $arregloNombre[$contador],
                'Size' => $arregloSize[$contador]
            ]);
            $DetalleDocumentacion = DB::table("DetalleDocumentacion")->insertGetId([

                "idRepositorio" => $CelaRepositorioDocumentoContribuyente,
                "Documento" => $arregloIdDocumentos[$contador],
                "idSolicitud" => $idSolicitud,
                "Estatus" => 0,
                "Observacion" => null,
                "Fecha" => date("Y-m-d H:i:s")
            ]);

            // DB::table('Ciudadano')->where('Curp','=',$CURP)->update(['Telefono' => $Telefono, 'CorreoElectronico' =>$Email]);
            $ultimoCela = Funciones::ObtenValor("SELECT idRepositorio FROM CelaRepositorioDocumentoContribuyente ORDER BY idRepositorio DESC", "idRepositorio");
            $datosRepo .= $ultimoCela . ",";
            $contador++;
        }
        return $datosRepo;
    }
    public function resubirDocumentosv2(Request $request)
    {

        $imagenes = $request->DataListFile;
        $Cliente = $request->Cliente;
        $idSolicitudLicenciaAActualizar = $request->idLicencia;
        $nombreTabla = 'SolicitudLicencia';

        //$imagenes,$idSolicitud,$Cliente,$nombreTabla = 'SolicitudLicencia'
        $arregloNombre = array();
        $arregloSize = array();
        $arregloRuta = array();
        $arregloIdDocumentos = array(); //debe ser int
        $datosRepo = "";
        $nombreTabla = "SolicitudLicencia";
        $ruta = "SolicitudLicencia"; //date("Y/m/d");
        $fechaHora = date("Y-m-d H:i:s");
        foreach ($imagenes as $arregloFoto) {
            #return $arregloFoto;
            $image_64 = $arregloFoto["data"]; //your base64 encoded data
            $extension = explode('/', explode(':', substr($image_64, 0, strpos($image_64, ';')))[1])[1];   // .jpg .png .pdf
            $replace = substr($image_64, 0, strpos($image_64, ',') + 1);
            // find substring fro replace here eg: data:image/png;base64,
            $image = str_replace($replace, '', $image_64);
            $image = str_replace(' ', '+', $image);
            $imageName = $arregloFoto["nombre"] . '_' . uniqid() . '.' . $extension;

            #return base64_decode($image);
            $size_in_bytes = (int) (strlen(rtrim($image_64, '=')) * 3 / 4);
            $size_in_kb    = $size_in_bytes / 1024;
            $size_in_mb    = $size_in_kb / 1024;
            #return $size_in_bytes;
            Storage::disk('repositorio')->put($Cliente . "/" . $ruta . "/" . $imageName, base64_decode($image));
            #return $size;
            #return "repositorio/FotosAgua".$imageName;
            $idDocumentoDetalle = ($arregloFoto["nombre"]);
            array_push($arregloNombre, $imageName);
            array_push($arregloIdDocumentos, $idDocumentoDetalle);
            array_push($arregloSize, $size_in_bytes);
            array_push($arregloRuta, "repositorio/" . $Cliente . "/" . $ruta . "/" . $imageName);
        }
        Funciones::selecionarBase(56); //$Cliente);
        //insertamos las rutas en celaRepositorio
        $contador = 0;
        //NOTE: se inserta en las evidencias del reporte
        $arrayIndicesCelaRepositorio = [];
        foreach ($arregloRuta as $ruta) {


            //Primero se actualizan todos los registros con el documento 
            DB::table('DetalleDocumentacion')
                ->where('Documento', '=', $arregloIdDocumentos[$contador])
                ->where('idSolicitud', '=', $idSolicitudLicenciaAActualizar)->update(['Estatus' => 4]);

            $CelaRepositorioDocumentoContribuyente = DB::table("CelaRepositorioDocumentoContribuyente")->insertGetId([
                'Tabla' => $nombreTabla,
                'idTabla' => $idSolicitudLicenciaAActualizar, ///12,//$idRegistro,
                'Ruta' => $ruta,
                'Descripci_on' => 'pdf Licencias Cuautitlan',
                'idUsuario' => NULL, //$usuario,
                'FechaDeCreaci_on' => $fechaHora,
                'Estado' => 1,
                'Reciente' => 1,
                'NombreOriginal' => $arregloNombre[$contador],
                'Size' => $arregloSize[$contador]
            ]);
            $DetalleDocumentacion = DB::table("DetalleDocumentacion")->insertGetId([

                "idRepositorio" => $CelaRepositorioDocumentoContribuyente,
                "Documento" => $arregloIdDocumentos[$contador],
                "idSolicitud" => $idSolicitudLicenciaAActualizar,
                "Estatus" => 0,
                "Observacion" => null,
                "Fecha" => date("Y-m-d H:i:s")
            ]);


            //$ultimoCela=Funciones::ObtenValor("SELECT idRepositorio FROM CelaRepositorioDocumentoContribuyente ORDER BY idRepositorio DESC","idRepositorio");
            //$datosRepo .= $ultimoCela.",";
            $contador++;
        }
        $res = [
            "NoDocumentosActualizados" => $contador
        ];
        return response()->json([
            'success' => '200',
            'result' => $res //json_encode($contribuyente, JSON_UNESCAPED_UNICODE)
        ]);
    }

    public function resubirSoloDocumentosv2EnElRefrendo(Request $request)
    {

        $imagenes = $request->DataListFile;
        $Cliente = $request->Cliente;
        $idSolicitudLicenciaAActualizar = $request->idLicencia;
        $nombreTabla = 'SolicitudLicencia';
        $idSolicitudLicenciaHistorial = DB::table("SolicitudLicenciaHistorial")->insertGetId([
            "Estatus" => 1,
            "Fecha" => date("Y-m-d"),
            "FechaTermino" => NULL,
            "idSolicitud" => $idSolicitudLicenciaAActualizar,
            "Tipo" => 2
        ]);

        //$imagenes,$idSolicitud,$Cliente,$nombreTabla = 'SolicitudLicencia'
        $arregloNombre = array();
        $arregloSize = array();
        $arregloRuta = array();
        $arregloIdDocumentos = array(); //debe ser int
        $datosRepo = "";
        $nombreTabla = "SolicitudLicencia";
        $ruta = "SolicitudLicencia"; //date("Y/m/d");
        $fechaHora = date("Y-m-d H:i:s");
        foreach ($imagenes as $arregloFoto) {
            #return $arregloFoto;
            $image_64 = $arregloFoto["data"]; //your base64 encoded data
            $extension = explode('/', explode(':', substr($image_64, 0, strpos($image_64, ';')))[1])[1];   // .jpg .png .pdf
            $replace = substr($image_64, 0, strpos($image_64, ',') + 1);
            // find substring fro replace here eg: data:image/png;base64,
            $image = str_replace($replace, '', $image_64);
            $image = str_replace(' ', '+', $image);
            $imageName = $arregloFoto["nombre"] . '_' . uniqid() . '.' . $extension;

            #return base64_decode($image);
            $size_in_bytes = (int) (strlen(rtrim($image_64, '=')) * 3 / 4);
            $size_in_kb    = $size_in_bytes / 1024;
            $size_in_mb    = $size_in_kb / 1024;
            #return $size_in_bytes;
            Storage::disk('repositorio')->put($Cliente . "/" . $ruta . "/" . $imageName, base64_decode($image));
            #return $size;
            #return "repositorio/FotosAgua".$imageName;
            $idDocumentoDetalle = ($arregloFoto["nombre"]);
            array_push($arregloNombre, $imageName);
            array_push($arregloIdDocumentos, $idDocumentoDetalle);
            array_push($arregloSize, $size_in_bytes);
            array_push($arregloRuta, "repositorio/" . $Cliente . "/" . $ruta . "/" . $imageName);
        }
        Funciones::selecionarBase(56); //$Cliente);
        //insertamos las rutas en celaRepositorio
        $contador = 0;
        //NOTE: se inserta en las evidencias del reporte
        $arrayIndicesCelaRepositorio = [];
        foreach ($arregloRuta as $ruta) {


            //Primero se actualizan todos los registros con el documento 
            DB::table('DetalleDocumentacion')
                ->where('Documento', '=', $arregloIdDocumentos[$contador])
                ->where('idSolicitud', '=', $idSolicitudLicenciaAActualizar)->update(['Estatus' => 4]);

            $CelaRepositorioDocumentoContribuyente = DB::table("CelaRepositorioDocumentoContribuyente")->insertGetId([
                'Tabla' => $nombreTabla,
                'idTabla' => $idSolicitudLicenciaAActualizar, ///12,//$idRegistro,
                'Ruta' => $ruta,
                'Descripci_on' => 'pdf Licencias Cuautitlan',
                'idUsuario' => NULL, //$usuario,
                'FechaDeCreaci_on' => $fechaHora,
                'Estado' => 1,
                'Reciente' => 1,
                'NombreOriginal' => $arregloNombre[$contador],
                'Size' => $arregloSize[$contador]
            ]);
            $DetalleDocumentacion = DB::table("DetalleDocumentacion")->insertGetId([

                "idRepositorio" => $CelaRepositorioDocumentoContribuyente,
                "Documento" => $arregloIdDocumentos[$contador],
                "idSolicitud" => $idSolicitudLicenciaAActualizar,
                "Estatus" => 0,
                "Observacion" => null,
                "Fecha" => date("Y-m-d H:i:s")
            ]);


            //$ultimoCela=Funciones::ObtenValor("SELECT idRepositorio FROM CelaRepositorioDocumentoContribuyente ORDER BY idRepositorio DESC","idRepositorio");
            //$datosRepo .= $ultimoCela.",";
            $contador++;
        }
        $res = [
            "NoDocumentosActualizados" => $contador
        ];
        return response()->json([
            'success' => '200',
            'result' => $res //json_encode($contribuyente, JSON_UNESCAPED_UNICODE)
        ]);
    }


    /* RUTA DEL PDF */


    public function ObtenerPDFLicencia($idLicenciaGenerar, $Cliente)
    {
        #return $request;
        $cliente = $Cliente; // $request->Cliente;
        #$idCotizacion = $request->IdCotizacion;
        $idSolicitudLicencia = $idLicenciaGenerar; //$request->idSolicitudLicencia;
        /*
    ---adicional---
    nombre:nombre,
                correo:correo,
                telefono:telefono,
                Tipo_Pago:Tipo_Pago,
                cotizacion:cotizacion

    */

        Funciones::selecionarBase($cliente);
        //$url = '';
        $url = 'https://suinpac.dev/PDFRMN.php';


        $dataForPost = array(
            'Cliente' => [
                "Cliente" => $cliente,
                #"IdCotizacion"=>$idCotizacion,
                "idSolicitudLicencia" => $idSolicitudLicencia,

            ]

        );

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($dataForPost),
            )
        );

        $context  = stream_context_create($options);
        $result = file_get_contents($url, true, $context);

        return $result;
        /*return response()->json([
        'result' => $result,
        'cliente' => $cliente,
        'DatosRecibidos' => $dataForPost,
        #'idCotizaciones' => $idCotizacion
    ], 200);*/
    }
    public function ObtenerPDFRevalidacionBajoAltoMEdiano($idLicenciaGenerar, $Cliente)
    {
        #return $request;
        $cliente = $Cliente; // $request->Cliente;
        #$idCotizacion = $request->IdCotizacion;
        $idSolicitudLicencia = $idLicenciaGenerar; //$request->idSolicitudLicencia;
        /*
    ---adicional---
    nombre:nombre,
                correo:correo,
                telefono:telefono,
                Tipo_Pago:Tipo_Pago,
                cotizacion:cotizacion

    */

        Funciones::selecionarBase($cliente);
        //$url = '';
        $url = 'https://suinpac.dev/PDFFormatoUnicoParaUnidadesEconomicas.php';


        $dataForPost = array(
            'Cliente' => [
                "Cliente" => $cliente,
                #"IdCotizacion"=>$idCotizacion,
                "idSolicitudLicencia" => $idSolicitudLicencia,

            ]

        );

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($dataForPost),
            )
        );

        $context  = stream_context_create($options);
        $result = file_get_contents($url, true, $context);

        return $result;
        /*return response()->json([
        'result' => $result,
        'cliente' => $cliente,
        'DatosRecibidos' => $dataForPost,
        #'idCotizaciones' => $idCotizacion
    ], 200);*/
    }
    public function RegenerarSolicitudPDFLicencia(Request $request)
    {
        #return $request;
        $cliente = $request->Cliente; // $request->Cliente;
        #$idCotizacion = $request->IdCotizacion;
        $idSolicitudLicencia = $request->idSolicitudLicencia; //$request->idSolicitudLicencia;
        /*
    ---adicional---
    nombre:nombre,
                correo:correo,
                telefono:telefono,
                Tipo_Pago:Tipo_Pago,
                cotizacion:cotizacion

    */

        Funciones::selecionarBase($cliente);
        //$url = '';
        $url = 'https://suinpac.dev/PDFRMN.php';


        $dataForPost = array(
            'Cliente' => [
                "Cliente" => $cliente,
                #"IdCotizacion"=>$idCotizacion,
                "idSolicitudLicencia" => $idSolicitudLicencia,

            ]

        );

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($dataForPost),
            )
        );

        $context  = stream_context_create($options);
        $result = file_get_contents($url, true, $context);

        //return $result;
        return response()->json([
            'result' => $result,
            'success' => '200',

            //'DatosRecibidos' => $dataForPost,
            #'idCotizaciones' => $idCotizacion
        ], 200);
    }
    public function RegenerarSolicitudPDFLicenciaAltoMedioBajo(Request $request)
    {
        #return $request;
        $cliente = $request->Cliente; // $request->Cliente;
        #$idCotizacion = $request->IdCotizacion;
        $idSolicitudLicencia = $request->idSolicitudLicencia; //$request->idSolicitudLicencia;
        /*
    ---adicional---
    nombre:nombre,
                correo:correo,
                telefono:telefono,
                Tipo_Pago:Tipo_Pago,
                cotizacion:cotizacion

    */

        Funciones::selecionarBase($cliente);
        //$url = '';
        $url = 'https://suinpac.dev/PDFFormatoUnicoParaUnidadesEconomicas.php';


        $dataForPost = array(
            'Cliente' => [
                "Cliente" => $cliente,
                #"IdCotizacion"=>$idCotizacion,
                "idSolicitudLicencia" => $idSolicitudLicencia,

            ]

        );

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($dataForPost),
            )
        );

        $context  = stream_context_create($options);
        $result = file_get_contents($url, true, $context);

        //return $result;
        return response()->json([
            'result' => $result,
            'success' => '200',

            //'DatosRecibidos' => $dataForPost,
            #'idCotizaciones' => $idCotizacion
        ], 200);
    }
    public function ObtenerCartaResponsivaProteccionCivil(Request $request)
    {
        //return $request;
        /*return response()->json([
        'result' => $request,
        
        
        
    ], 200);
    exit;*/
        $cliente = $request->Cliente; // $request->Cliente;
        $contribuyente = $request->contribuyente; //$request->idSolicitudLicencia;
        $caracter = $request->caracter; //$request->idSolicitudLicencia;
        $comercio = $request->comercio; //$request->idSolicitudLicencia;
        $direccion = $request->direccion; //$request->idSolicitudLicencia;
        $giro = $request->giro; //$request->idSolicitudLicencia;
        $HoraInicial = $request->HoraInicial; //$request->idSolicitudLicencia;
        $idHoraFinal = $request->idHoraFinal; //$request->idSolicitudLicencia;
        $Superficie = $request->Superficie; //$request->idSolicitudLicencia;
        $diasLaborales = $request->diasLaborales; //$request->idSolicitudLicencia;
        $colonia = $request->colonia; //$request->idSolicitudLicencia;

        Funciones::selecionarBase($cliente);
        $url = 'https://suinpac.dev/PDFPROTECCION.php';

        $dataForPost = array(
            'Cliente' => [
                "Cliente" => $cliente,
                "contribuyente" => $contribuyente,
                "caracter" => $caracter,
                "comercio" => $comercio,
                "direccion" => $direccion,
                "giro" => $giro,
                "HoraInicial" => $HoraInicial,
                "colonia" => $colonia,
                "Superficie" => $Superficie,
                "HoraFinal" => $idHoraFinal,
                "diasLaborales" => $diasLaborales,
            ]

        );

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($dataForPost),
            )
        );

        $context  = stream_context_create($options);
        $result = file_get_contents($url, true, $context);

        //return $result;
        return response()->json([
            'status' => "200",
            'cliente' => $cliente,
            'CartaResponsiva' => $result,
            #'idCotizaciones' => $idCotizacion
        ], 200);
    }
    //termina

    //funcion para prueba de insersion de fotos 
    public function PruebaFotos(Request $request)
    {
        $Cliente  = $request->Cliente;
        $arrayDocumentos = $request->arrayDocumentos;
        $resultado = $this->SubirImagenV2($arrayDocumentos, 12, 56, "SolicitudLicencia");
        return response()->json([
            'success' => '200',
            'result' => $resultado  //json_encode($contribuyente, JSON_UNESCAPED_UNICODE)
        ]);
    }


    public function getSolicitudLicenciasDocumentacionRequerida(Request $request)
    {
        $Cliente  = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $sqlDocumentacionRequerida = "SELECT * FROM SolicitudLicenciasDocumentacionRequerida WHERE Categoria IN(1) ORDER BY opcional";
        $DocumentacionRequerida = DB::select($sqlDocumentacionRequerida);
        return response()->json([
            'success' => '200',
            'result' => $DocumentacionRequerida
        ]);
    }
    public function getSolicitudLicenciasDocumentacionRequeridaAltoMedianoBajo(Request $request)
    {
        $Cliente  = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $sqlDocumentacionRequerida = "SELECT * FROM SolicitudLicenciasDocumentacionRequerida WHERE Categoria IN(8,9,10) ORDER BY opcional";
        $DocumentacionRequerida = DB::select($sqlDocumentacionRequerida);
        return response()->json([
            'success' => '200',
            'result' => $DocumentacionRequerida
        ]);
    }
    public function getEstatusRevalidacion(Request $request)
    {

        $Cliente  = $request->Cliente;
        $FolioAbuscar  = $request->Folio;
        Funciones::selecionarBase($Cliente);
        $sqlEstatusREvalidacion = "
        SELECT
            sh.Estatus
        FROM
            SolicitudLicencia as sl
            INNER JOIN SolicitudLicenciaHistorial sh ON ( sh.idSolicitud = sl.id ) 
        WHERE
             sl.folioSolicitud='$FolioAbuscar' and 
             sh.Estatus=4";
        $FolioBuscarDatos = DB::select($sqlEstatusREvalidacion);
        return response()->json([
            'success' => '200',
            'result' => $FolioBuscarDatos,
            'validoParaRevalidacion' => count($FolioBuscarDatos)
        ]);
    }
    public function getDocumentosByAltaLicenciaId(Request $request)
    {
        $Cliente  = $request->Cliente;
        $idLicencia  = $request->idLicencia;
        Funciones::selecionarBase($Cliente);
        $sqlDocumentacionRequeridaRegistrada = "SELECT dd.id AS DetalleDocumentacion,MAX(dd.Fecha) AS FechaDetalleDocumentacion,dd.Estatus,crd.Ruta,sldr.* ,COALESCE((SELECT descripcion from SolicitudLicenciaCatalogoRevision WHERE id=dd.TipoRevision),'') AS Observacion FROM DetalleDocumentacion dd 
        INNER JOIN CelaRepositorioDocumentoContribuyente crd ON(crd.idRepositorio=dd.idRepositorio)
        INNER JOIN SolicitudLicenciasDocumentacionRequerida sldr ON(sldr.id=dd.Documento) WHERE dd.idSolicitud=$idLicencia   AND dd.Estatus not IN(4)  GROUP BY dd.id ORDER BY dd.Estatus DESC";
        $DocumentacionRequerida = DB::select($sqlDocumentacionRequeridaRegistrada);
        return response()->json([
            'success' => '200',
            'result' => $DocumentacionRequerida
        ]);
    }



    public function ObtenerPDFLicenciasPrincipal($idLicenciaGenerar, $Cliente)
    {
        #return $request;
        $cliente = $Cliente; // $request->Cliente;
        #$idCotizacion = $request->IdCotizacion;
        $idSolicitudLicencia = $idLicenciaGenerar; //$request->idSolicitudLicencia;


        /*
        ---adicional---
        nombre:nombre,
                    correo:correo,
                    telefono:telefono,
                    Tipo_Pago:Tipo_Pago,
                    cotizacion:cotizacion
    
        */

        Funciones::selecionarBase($cliente);
        //$url = '';
        $url = 'https://suinpac.dev/PDFRMN2.php';


        $dataForPost = array(
            'Cliente' => [
                "Cliente" => $cliente,
                #"IdCotizacion"=>$idCotizacion,
                "idSolicitudLicencia" => $idSolicitudLicencia,

            ]

        );

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($dataForPost),
            )
        );

        $context  = stream_context_create($options);
        $result = file_get_contents($url, true, $context);

        return $result;
        /*return response()->json([
            'result' => $result,
            'cliente' => $cliente,
            'DatosRecibidos' => $dataForPost,
            #'idCotizaciones' => $idCotizacion
        ], 200);*/
    }
    public function ObtenerTamanio( Request $request ){
        $datos = $request->all();
        $rules = [ 'Cliente'=>"required|numeric" ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje'=>$validator->messages(),
                'Code' => 223 //Mensaje 223 campos incorrectos
            ]);
        }
        $Cliente = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $Tamanios = DB::table('Tamano')->select("*")->get();
        if($Tamanios){
            return [ 'Code'=> 200,'Datos'=> $Tamanios ];
        }else{
            return [ 'Code'=> 200,'Datos'=> "Error al realizar la consulta" ];
        }
    }
    public function ObtenerClaves( Request $request ){
        $datos = $request->all();
        $rules = [ 'Cliente'=>"required|numeric" ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje'=>$validator->messages(),
                'Code' => 223 //Mensaje 223 campos incorrectos
            ]);
        }
        $Cliente = $request->Cliente;
        Funciones::selecionarBase($Cliente);
        $Tamanios = DB::table('Claves')->select("*")->get();
        if($Tamanios){
            return [ 'Code'=> 200,'Datos'=> $Tamanios ];
        }else{
            return [ 'Code'=> 200,'Datos'=> "Error al realizar la consulta" ];
        }
    }
}




##========================================================================================
##                                                                                      ##
##               Carlos Dircio #2022-02-03 17:05 hrs
##                               R̷M̷N̷ 1̷7̷-̷0̷2̷-̷2̷0̷2̷2̷
##                                                                                      ##
##========================================================================================