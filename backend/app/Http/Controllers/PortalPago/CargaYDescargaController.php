<?php
namespace App\Http\Controllers\PortalPago;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DateTime;
use App\Cliente;
use App\Funciones;
use App\FuncionesCaja;
use App\Modelos\PadronAguaLectura;
use App\Modelos\PadronAguaPotable;
use App\ModelosNotarios\Observaciones;
use App\Libs\Wkhtmltopdf;
use Hamcrest\Core\HasToString;
use Illuminate\Support\Facades\DB;
use \Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use LDAP\Result;
use Prophecy\Doubler\Generator\Node\ReturnTypeNode;

class CargaYDescargaController extends Controller{
    public function ObtenerDatosCargaYDescarga(Request $request){    
        $serie = $request->Serie;    
        $cliente = $request->Cliente; 

        Funciones::selecionarBase($cliente);
        $Consulta = Funciones::ObtenValor("SELECT 
                                                IF(df.PersonalidadJur_idica = 2, c.NombreComercial,
                                                    IF(c.Nombres IS NULL OR c.Nombres = '', df.NombreORaz_onSocial,
                                                            CONCAT(COALESCE ( c.Nombres, '' ), ' ',
                                                            COALESCE ( c.ApellidoPaterno, '' ),' ',
                                                            COALESCE ( c.ApellidoMaterno, '' ))))  AS Contribuyente,
                                                pcdh.Contribuyente AS idContribuyente,
                                                COALESCE(pcdh.LicenciaFuncionamiento,0) AS Licencia,
                                                pcdh.id,
                                                pcdh.Serie,
                                                pcdh.Marca,
                                                pcdh.Modelo,
                                                pcdh.Motor,
                                                pcdh.Placas,
                                                pcdh.Estatus,
                                                pcdh.TipoVehiculo,
                                                (SELECT descripcion FROM PermisoCargaYDescarga_Categoria pcdc WHERE pcdc.id = pcdh.TipoVehiculo)  AS TipoVehiculoDescripcion,
                                                pcdh.Clasificaci_on AS TipoClasificacion,
                                                pcdh.FechaInicial,
                                                pcdh.Fechafinal, 
                                                MONTH(pcdh.FechaInicial) AS MesInicial,
                                                MONTH(pcdh.Fechafinal) AS MesFinal,
                                                pcdh.Cotizaci_on,
                                                pcdh.idRecaudo,
                                                CONCAT(
                                                        'Tonelada Inicio: ',
                                                        (SELECT MetrosInicio FROM PermisoCargaYDescarga_CategoriaClase pcdcc WHERE pcdcc.id = pcdh.Clasificaci_on),
                                                        ' - Tonelada Final: ', 
                                                        (SELECT MetrosFinal FROM PermisoCargaYDescarga_CategoriaClase pcdcc WHERE pcdcc.id = pcdh.Clasificaci_on)
                                                    )AS TipoClasificacionDescripcion, 
                                                (SELECT concepto FROM PermisoCargaYDescarga_Categoria pcdc WHERE pcdc.id = pcdh.TipoVehiculo) AS Concepto,
                                                (SELECT Importe FROM PermisoCargaYDescarga_CategoriaClase pcdcc WHERE pcdcc.id = pcdh.Clasificaci_on) AS Importe, 
                                                pcdh.Consumo AS Importe2
                                            FROM PermisoCargaYDescargaHistorial pcdh
                                                INNER JOIN Contribuyente c ON (c.id = pcdh.Contribuyente)
                                                INNER JOIN DatosFiscales df ON (df.id = c.DatosFiscales)
                                            WHERE pcdh.Serie = '".$serie."'
                                                AND c.Cliente = ".$cliente." 
                                                #AND date(now()) BETWEEN pcdh.FechaInicial AND pcdh.FechaFinal                                 
                                                AND YEAR(pcdh.FechaTupla) =".date("Y"). " ORDER BY id DESC LIMIT 1");
        return response()->json([
            'data' => $Consulta
        ]);
    }

    public function ObtenerContribuyentePermisoCD(Request $request){
        $Cliente = $request -> Cliente;
        $Serie = $request -> Serie;
        return $request;
    }

    public function actualizarEstatusLectura(Request $request){
        $Cliente = $request -> Cliente;        
        $IdPadron = $request -> IdPadron;        
        Funciones::selecionarBase($Cliente);
        $Actualizacion = DB::update("UPDATE PermisoCargaYDescargaHistorial SET Estatus = 2 WHERE id = ".$IdPadron);
        return $Actualizacion;
    }
    public function validarCotizado(Request $request){
        $Cliente = $request->Cliente;    
        $idHistorialCD = $request->idHistorialCD;
        Funciones::selecionarBase($Cliente);
        $Validacion = Funciones::ObtenValor("SELECT c.id FROM PermisoCargaYDescargaHistorial cdh 
                                                INNER JOIN Cotizaci_on c ON c.id = cdh.Cotizaci_on
                                                INNER JOIN ConceptoAdicionalesCotizaci_on cac ON cac.Cotizaci_on = c.id 
                                            WHERE cac.Origen = 'Cotizacion Permiso de Carga y Descarga' 
                                                AND cdh.id =".$idHistorialCD."
                                                AND YEAR(c.Fecha) = ".date('Y')."
                                                AND MONTH(c.Fecha) = ".date('m')."
                                            LIMIT 1");
        return response()->json([
            'Datos' => $Validacion
        ]);
    }

    public function cotizarServiciosCargaYDescarga(Request $request){
        global $Conexion;        
        $IdLicencia = $request->idHistorialCD;        
        $Concepto []=$request->Concepto;
        $Cliente = $request->Cliente;
        $Importe = $request->Importe;
        Funciones::selecionarBase($Cliente);
        $anio=date('Y');
        $fechainicial = date("Y-m-d", strtotime($request->FechaInicial));
        $fechafinal = date("Y-m-d", strtotime($request->FechaFinal));      
        $DatosPermiso =Funciones::ObtenValor("SELECT * FROM PermisoCargaYDescargaHistorial WHERE id=".$IdLicencia);
        $IdUltimoPermiso = 0;
        if($DatosPermiso -> FechaFinal < date('Y-m-d')) {
            $Padron=Funciones::ObtenValor("SELECT * FROM PermisoCargaYDescargaHistorial WHERE id=".$IdLicencia);                                                                                                                                                
            $clienteClave = Funciones::ObtenValor('SELECT Clave FROM Cliente WHERE id=' .$Cliente, 'Clave');
            $UltimaCotizacion = Funciones::ObtenValor("SELECT FolioCotizaci_on from Cotizaci_on where FolioCotizaci_on like '".$anio.$clienteClave."%' ORDER BY FolioCotizaci_on desc limit 0,1", "FolioCotizaci_on");

            $BaseCalculo=Funciones::ObtenValor("SELECT  c3.BaseCalculo as TipobaseCalculo
                                                    FROM ConceptoCobroCaja c
                                                INNER JOIN ConceptoRetencionesAdicionales c1 ON ( c.id = c1.Concepto  )
                                                INNER JOIN ConceptoRetencionesAdicionalesCliente c2 ON ( c1.id = c2.ConceptoRetencionesAdicionales  )
                                                INNER JOIN ConceptoAdicionales c3 ON ( c2.id = c3.ConceptoRetencionesAdicionalesCliente  )
                                                    WHERE c3.Cliente=".$Cliente." AND c3.EjercicioFiscal=".$anio." AND  c2.Cliente=".$Cliente." AND c.id = ".$Concepto[0],"TipobaseCalculo");
            if ($UltimaCotizacion=="NULL"|| $UltimaCotizacion==""){
                $N_umeroDeCotizacion=$anio.$clienteClave .str_pad(1, 8, '0', STR_PAD_LEFT);
            } else {
                $N_umeroDeCotizacion=$anio.$clienteClave .str_pad(intval(substr($UltimaCotizacion, -8, 8))+1, 8, '0', STR_PAD_LEFT);
            }

            $Fondo=Funciones::ObtenValor("SELECT PresupuestoAnualPrograma.Fondo as Fondo
                                            FROM PresupuestoAnualPrograma
                                        INNER JOIN Fondo ON (Fondo.id = PresupuestoAnualPrograma.Fondo)
                                        INNER JOIN Cat_alogoDeFondo ON (Cat_alogoDeFondo.id=Fondo.CatalogoFondo)
                                        INNER JOIN PresupuestoGeneralAnual ON (Fondo.Presupuesto= PresupuestoGeneralAnual.id)
                                            WHERE 
                                                (SELECT Descripci_on FROM Cat_alogoPrograma where PresupuestoAnualPrograma.Programa=Cat_alogoPrograma.id) LIKE '%ingresos propios%' 
                                                AND Cliente=".$Cliente." AND EjercicioFiscal=".$anio,"Fondo");

            $FuenteFinanciamiento=Funciones::ObtenValor("SELECT f1.id as FuenteFinanciamiento FROM Fondo f
                                                            INNER JOIN Cat_alogoDeFondo ca ON ( f.CatalogoFondo = ca.id  )
                                                            INNER JOIN FuenteFinanciamiento f1 ON ( ca.FuenteFinanciamiento = f1.id  )
                                                        WHERE f.id = ".$Fondo, "FuenteFinanciamiento");

            $CatalogoFondo= Funciones::ObtenValor('select CatalogoFondo FROM Fondo WHERE id='.$Fondo,'CatalogoFondo' );
            $fecha=date('Y-m-d');
            $fechaCFDI=date('Y-m-d H:i:s');
            $consulta_usuario = Funciones::ObtenValor("SELECT c.idUsuario, c.Usuario FROM CelaUsuario c 
                                                            INNER JOIN CelaRol c1 ON ( c.Rol = c1.idRol  )   
                                                        WHERE c.CorreoElectr_onico='" . $Cliente . "@gmail.com' ");

            $PresupuestoAnualPrograma=Funciones::ObtenValor("select id, (select Descripci_on from Cat_alogoPrograma where PresupuestoAnualPrograma.Programa=Cat_alogoPrograma.id) FROM PresupuestoAnualPrograma where Fondo=".$Fondo,"id");
            $areaRecaudadora=Funciones::getAreaRecaudadora($Cliente,$anio,$Concepto[0]);
            $ConsultaInserta = sprintf("INSERT INTO Cotizaci_on (  `id` , `FolioCotizaci_on` , `Contribuyente` , `AreaAdministrativa` , `Fecha` , `Cliente`, `Fondo`, `Programa`, `FuenteFinanciamiento`,`Tipo`,`FechaCFDI` ,`Usuario` ,`Padr_on`) VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
                Funciones::GetSQLValueString(NULL, "int unsigned"),
                Funciones::GetSQLValueString($N_umeroDeCotizacion, "varchar") ,
                Funciones::GetSQLValueString($Padron->Contribuyente, "int unsigned") ,
                Funciones::GetSQLValueString($areaRecaudadora, "int unsigned") ,
                Funciones::GetSQLValueString($fecha, "date"),
                Funciones::GetSQLValueString($Cliente, "int") ,
                Funciones::GetSQLValueString($Fondo, "varchar") ,
                Funciones::GetSQLValueString($PresupuestoAnualPrograma, "varchar") ,
                Funciones::GetSQLValueString($FuenteFinanciamiento, "varchar") ,
                Funciones::GetSQLValueString(25, "int") ,
                Funciones::GetSQLValueString($fechaCFDI, "date") ,
                Funciones::GetSQLValueString($consulta_usuario ->idUsuario, "int"),
                Funciones::GetSQLValueString($Padron->id, "int"));

            if( DB::insert($ConsultaInserta)){
                $IdRegistroCotizaci_on = DB::getPdo()->lastInsertId();
                // clve cliente + anio + area recaudadora + cpnsecutivo
                $InsertaPermisoHistorial = sprintf("INSERT INTO PermisoCargaYDescargaHistorial 
                    ( `id` , `LicenciaFuncionamiento` , `Marca` , `Serie` , `TipoLinea` , `Placas`, `Modelo`, `Motor`, `NumeroEconomico`,`Obsevaci_on`,
                    `Cotizaci_on` ,`idRecaudo` ,`Estatus`, `FechaTupla`, `UsuarioCotiza`,`UsuarioRecauda`, `TipoVehiculo`,`Clasificaci_on`,
                    `Entidad`,`Consumo`,`FechaInicial`,`FechaFinal`,`Observaci_on`,`FechaLectura`,`Contribuyente`) 
                VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,  %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
                    Funciones::GetSQLValueString(NULL, "int unsigned"),
                    Funciones::GetSQLValueString($Padron->LicenciaFuncionamiento, "int") ,
                    Funciones::GetSQLValueString($Padron->Marca, "varchar") ,
                    Funciones::GetSQLValueString($Padron->Serie, "varchar") ,
                    Funciones::GetSQLValueString($Padron->TipoLinea, "varchar") ,
                    Funciones::GetSQLValueString($Padron->Placas, "varchar") ,
                    Funciones::GetSQLValueString($Padron->Modelo, "varchar") ,
                    Funciones::GetSQLValueString($Padron->Motor, "varchar") ,
                    Funciones::GetSQLValueString($Padron->NumeroEconomico, "varchar") ,
                    Funciones::GetSQLValueString($Padron->Obsevaci_on, "text") ,
                    Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int") ,
                    Funciones::GetSQLValueString(null, "int") ,
                    Funciones::GetSQLValueString(1, "int") ,
                    Funciones::GetSQLValueString(date('Y-m-d h:m:s'), "datetime") ,
                    Funciones::GetSQLValueString(3663, "int") ,
                    Funciones::GetSQLValueString(null, "int") ,
                    Funciones::GetSQLValueString($Padron->TipoVehiculo, "int") ,
                    Funciones::GetSQLValueString($Padron->Clasificaci_on, "int") ,
                    Funciones::GetSQLValueString($Padron->Entidad, "varchar") ,
                    Funciones::GetSQLValueString($Importe, "decimal") ,
                    Funciones::GetSQLValueString($fechainicial, "varchar") ,
                    Funciones::GetSQLValueString($fechafinal, "varchar") ,
                    Funciones::GetSQLValueString($Padron->Observaci_on, "text") ,
                    Funciones::GetSQLValueString(date('Y-m-d'), "date") ,
                    Funciones::GetSQLValueString($Padron->Contribuyente, "int"));
                if( DB::insert($InsertaPermisoHistorial)){
                    $IdUltimoPermiso = DB::getPdo()->lastInsertId();
                    $Actualizacion = DB::update("UPDATE Cotizaci_on SET Padr_on=".$IdUltimoPermiso." WHERE id=".$IdRegistroCotizaci_on);
                }
                $CajadeCobro=Funciones::ObtenValor("SELECT CajaDeCobro from CelaUsuario where idUsuario=".$consulta_usuario ->idUsuario, "CajaDeCobro");
                $areaRecaudadora = Funciones::ObtenValor("SELECT Clave FROM AreasAdministrativas WHERE id=".$areaRecaudadora, "Clave");

                $UltimoFolio = Funciones::ObtenValor("SELECT Folio from XMLIngreso INNER JOIN Cotizaci_on c ON (c.id=XMLIngreso.idCotizaci_on)  where c.Cliente='".$Cliente."' AND Folio like '%" . $clienteClave . $anio. $areaRecaudadora . "%' order by Folio desc limit 0,1", "Folio");
                $Serie = $clienteClave . $anio . $areaRecaudadora;
                $medoPago=04;
                if ($UltimoFolio == 'NULL') {
                    $N_umeroDeFolio = str_pad(1, 8, '0', STR_PAD_LEFT);
                } else {
                    $N_umeroDeFolio = str_pad(intval(substr($UltimoFolio, -8, 8)) + 1, 8, '0', STR_PAD_LEFT);
                }
                $arr['Contribuyente']=$Padron->Contribuyente;
                $arr['LicenciaFuncionamiento']= $Padron->LicenciaFuncionamiento;
                $arr['Serie']=$Padron->Serie;
                $arr['Placas']=$Padron->Placas;
                $arr['Motor']=$Padron->Motor;
                $arr['Cliente'] = $Cliente;
                $arr['Usuario'] = Funciones::ObtenValor("SELECT NombreCompleto FROM CelaUsuario WHERE idUsuario=".$consulta_usuario ->idUsuario, "NombreCompleto");
                $arr['Leyenda'] =  "Elaboró";

                $DatosExtra = json_encode($arr, JSON_UNESCAPED_UNICODE);
                //echo "INSERT INTO XMLIngreso (Contribuyente, idCotizaci_on, Folio, MetodoDePago, DatosExtra) VALUES ('".$Padron->Contribuyente."','".$IdRegistroCotizaci_on."', '".$Serie.$N_umeroDeFolio."', '".$_POST['MetodoDePago']."', '".$DatosExtra."') ";

                DB::table('XMLIngreso')->insert([
                    [  'Contribuyente'=>$Padron->Contribuyente,
                    'idCotizaci_on'=>$IdRegistroCotizaci_on,
                    'Folio' => $Serie.$N_umeroDeFolio,
                    'MetodoDePago'=>$medoPago,
                    'DatosExtra'=>$DatosExtra,
                    ]
                ]);
                $XmlEndozar = DB::getPdo()->lastInsertId();
                $ConsultaInserta = "INSERT INTO ConceptoAdicionalesCotizaci_on (  `id` , `ConceptoAdicionales` , `Cotizaci_on` , `TipoContribuci_on` , `Importe` , `Estatus` , `MomentoCotizaci_on` , `MomentoPago` , `FechaPago` , `CajaDeCobro` , `Xml` , `TipoCobroCaja` , `N_umeroDeOperaci_on` , `CuentaBancaria`, `Adicional`,  `Cantidad`,  `Padre`,  `Mes` ,  `A_no` ,  `Origen`,  `TipoBase`,  `MontoBase`, `Padr_on` ) VALUES ";

                $totalConceptos= count($Concepto);
                $todoslosconceptos=array();
                $m=0;
                $momento=4;
                $mesEnviar=null;
                $A_noEnviar=null;

                for($i=0; $i<$totalConceptos; $i++){
                    $todoslosconceptos[$m]["id"]=$Concepto[$i];
                    $m++;
                    //Recorro cada concepto y hago la consulta
                    $ConsultaInserta .= sprintf("(  %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s),",
                        Funciones::GetSQLValueString(NULL, "int unsigned"),
                        Funciones::GetSQLValueString($Concepto[$i], "int unsigned") ,
                        Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int unsigned") ,
                        Funciones::GetSQLValueString(1, "int") , // 1 para concepto o 2 para retencion adicional
                        Funciones::GetSQLValueString($Importe, "decimal") , //importe
                        Funciones::GetSQLValueString(0, "int") , //0 para no pagado  1 para pagado
                        Funciones::GetSQLValueString($momento, "int unsigned") , //4 es ley de ingresos devengada
                        Funciones::GetSQLValueString(NULL, "int unsigned") ,//5 es para cuando se paga
                        Funciones::GetSQLValueString(NULL, "datetime") , //fecha de cobro
                        Funciones::GetSQLValueString($CajadeCobro, "int unsigned") ,//caja de cobro
                        Funciones::GetSQLValueString($XmlEndozar, "int unsigned") ,//el XML
                        Funciones::GetSQLValueString(NULL, "int unsigned") ,//TipoCobroCaja
                        Funciones::GetSQLValueString(NULL, "varchar") ,//numero de operacion
                        Funciones::GetSQLValueString(NULL, "int unsigned") , // cuenta bancaria
                        Funciones::GetSQLValueString(NULL, "int unsigned"), //adicional
                        Funciones::GetSQLValueString(1, "int unsigned"),//cantidad
                        Funciones::GetSQLValueString(NULL, "int"), //Padre
                        Funciones::GetSQLValueString($mesEnviar, "int"), //Mes
                        Funciones::GetSQLValueString($A_noEnviar, "int"), //A_no
                        Funciones::GetSQLValueString("Cotizacion Permiso de Carga y Descarga", "varchar"), //Origen
                        Funciones::GetSQLValueString(NULL, "int unsigned"), //tipoBase
                        Funciones::GetSQLValueString($BaseCalculo, "decimal"), //MontoBase
                        Funciones::GetSQLValueString($IdUltimoPermiso, "int") ); //idLicencia                  
                }
                $ConsultaInserta=substr_replace($ConsultaInserta,";",-1);
                if(DB::insert($ConsultaInserta)){
                    //AGREGO TODOS LOS DATOS A LA CONTABILIDAD
                    $DatosEncabezado=Funciones::ObtenValor("SELECT N_umeroP_oliza from EncabezadoContabilidad where Cotizaci_on=".$IdRegistroCotizaci_on,"N_umeroP_oliza");
                    $Programa=Funciones::ObtenValor("SELECT Programa FROM PresupuestoAnualPrograma WHERE id=".$PresupuestoAnualPrograma, "Programa");
                    if($DatosEncabezado=="NULL" || $DatosEncabezado==""){
                        $UltimaPoliza=Funciones::ObtenValor("SELECT N_umeroP_oliza as Ultimo from EncabezadoContabilidad where N_umeroP_oliza like '".$anio.$clienteClave.str_pad($Programa, 3, '0', STR_PAD_LEFT)."03%' order by N_umeroP_oliza desc limit 0,1","Ultimo");
                        if($UltimaPoliza=='NULL' || $UltimaPoliza=="")
                            $N_umeroDePoliza=$anio.$clienteClave.str_pad($Programa, 3, '0', STR_PAD_LEFT)."03".str_pad(1, 6, '0', STR_PAD_LEFT);
                        else
                            $N_umeroDePoliza=$anio.$clienteClave.str_pad($Programa, 3, '0', STR_PAD_LEFT)."03".str_pad(intval(substr($UltimaPoliza, -6, 6))+1, 6, '0', STR_PAD_LEFT);
                    } else {
                        $N_umeroDePoliza=$DatosEncabezado;
                    }
                    $ConsultaInsertaContabilidad = sprintf("INSERT INTO EncabezadoContabilidad ( id, Cliente, EjercicioFiscal, TipoP_oliza, N_umeroP_oliza, FechaP_oliza, Concepto, Cotizaci_on, AreaRecaudadora, FuenteFinanciamiento, Fondo, CatalogoFondo, Programa, idPrograma, Proyecto, Clasificaci_onProgram_atica, Clasificaci_onFuncional, Clasificaci_onAdministrativa, Contribuyente, Persona, CuentaBancaria, MovimientoBancario, N_umeroDeMovimientoBancario, Momento, EstatusTupla, FechaTupla, AreaAdministrativaProyecto, Proveedor, CuentaPorPagar) VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s )",
                        Funciones::GetSQLValueString(NULL, "int"),
                        Funciones::GetSQLValueString($Cliente, "int"),
                        Funciones::GetSQLValueString($anio, "int"),
                        Funciones::GetSQLValueString(1, "int"), // 1 ingresos   2 egresos  3 diario
                        Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //numero de poliza
                        Funciones::GetSQLValueString($fecha, "datetime"), //fecha de poliza
                        Funciones::GetSQLValueString("Cotizacion #".$N_umeroDeCotizacion, "varchar"), //descripcion o concepto
                        Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //id de cotizacion
                        Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area recaudadora
                        Funciones::GetSQLValueString($FuenteFinanciamiento, "int"),  //fuente de financiamiento
                        Funciones::GetSQLValueString($Fondo, "int"), //Fondo
                        Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                        Funciones::GetSQLValueString($Programa, "int"), //Programa
                        Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                        Funciones::GetSQLValueString(NULL, "int"), // proyecto
                        Funciones::GetSQLValueString(NULL, "int"),//Clasificaci_onProgram_atica
                        Funciones::GetSQLValueString(NULL, "int"),//Clasificaci_onFuncional
                        Funciones::GetSQLValueString(NULL, "int"),//Clasificaci_onAdministrativa
                        Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                        Funciones::GetSQLValueString(NULL, "int"), //Persona
                        Funciones::GetSQLValueString(NULL, "int"), //cuenta bancaria
                        Funciones::GetSQLValueString(NULL, "int"), //Movimiento bancario
                        Funciones::GetSQLValueString(NULL, "int"), //Num de mov bancario
                        Funciones::GetSQLValueString($momento, "int"), //Momento
                        Funciones::GetSQLValueString(1, "int"), //Status 1 activo   y 0 cancelado
                        Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                        Funciones::GetSQLValueString(NULL, "int"), //Area administrativa proyecto
                        Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                        Funciones::GetSQLValueString(NULL, "int")); //Cuenta por pagar
                    if(DB::insert($ConsultaInsertaContabilidad)){
                        $Status=true;
                        $IdEncabezadoContabilidad = DB::getPdo()->lastInsertId();
                        $ConsultaInsertaDetalleContabilidadC="INSERT INTO DetalleContabilidad
                        ( id, EncabezadoContabilidad, TipoP_oliza, FehaP_oliza, N_umeroP_oliza, ConceptoMovimientoContable, Cotizaci_on, AreaRecaudaci_on, FuenteFinanciamiento, Programa, idPrograma, Proyecto, AreaAdministrativaProyecto
                        , Clasificaci_onProgram_atica, Clasificaci_onFuncionalGasto, Clasificaci_onAdministrativa, Clasificaci_onEcon_omicaIGF, Contribuyente, Proveedor, CuentaBancaria, MovimientoBancario
                        , N_umeroMovimientoBancario, uuid, EstatusConcepto, ConceptoDeXML, MomentoContable, CRI, COG, TipoDeMovimientoContable, Importe, EstatusInventario, idDeLaObra, Persona, TipoDeGasto
                        , TipoCobroCaja, PlanDeCuentas, NaturalezaCuenta, TipoBien, TipoObra, EstatusTupla, Fondo, CatalogoFondo, FechaTupla, Origen,ConceptoCobroCajaId) VALUES ";
                        //Obtiene conceptos y adicioanles
                        $ConsultaObtiene = "SELECT c3.id as ConceptoCobroCajaID, co.id, co.Importe, co.MomentoCotizaci_on, co.Xml, c3.CRI, co.Adicional, (SELECT Cri FROM RetencionesAdicionales WHERE co.Adicional= RetencionesAdicionales.id) as CriBueno, (SELECT PlanDeCuentas FROM RetencionesAdicionales WHERE co.Adicional= RetencionesAdicionales.id) as PlanCuentasBueno, (SELECT Abono FROM MomentoContable WHERE Momento=".$momento." AND MomentoContable.CRI=c3.CRI ) as AbonoSegunPlan,  (SELECT Naturaleza FROM PlanCuentas WHERE  PlanCuentas.id= AbonoSegunPlan ) as NaturalezaSegunPlan, (SELECT Naturaleza FROM PlanCuentas WHERE  PlanCuentas.id= PlanCuentasBueno ) as NaturalezaPlanCuentasBueno
                                                FROM ConceptoAdicionalesCotizaci_on co
                                            INNER JOIN ConceptoCobroCaja c3 ON ( co.ConceptoAdicionales = c3.id  )
                                                WHERE co.Cotizaci_on =" . $IdRegistroCotizaci_on;
                        $UUID=NULL;
                        if($ResultadoObtieneConceptos = DB::select($ConsultaObtiene)){
                            foreach($ResultadoObtieneConceptos as $registroConceptos){
                                if(!is_null($registroConceptos->Adicional)){
                                    $registroConceptos->ConceptoCobroCajaID= Funciones::ObtenValor("SELECT ra.ConceptoCobro FROM RetencionesAdicionales ra WHERE ra.id=".$registroConceptos->Adicional,"ConceptoCobro");
                                }
                                //aqui agrego datos a Asignaci_onPresupuestal
                                $ConsultaInserta = sprintf("INSERT INTO Asignaci_onPresupuestal (  `id` , `Cri` , `ImporteAnual` , `PresupuestoAnualPrograma` , `FechaInicial` , `FechaFinal` , `EstatusTupla`, `AreaAdministrativa`,  `ImporteModificado`, `ImporteDevengado`, `Concepto` ) VALUES (  %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
                                    Funciones::GetSQLValueString(NULL, "int"),
                                    Funciones::GetSQLValueString($registroConceptos->CRI, "int") ,
                                    Funciones::GetSQLValueString(0, "decimal") ,
                                    Funciones::GetSQLValueString($PresupuestoAnualPrograma ,"int") ,
                                    Funciones::GetSQLValueString(date('Y-m-d'), "date") ,
                                    Funciones::GetSQLValueString(date('Y-m-d'), "date") ,
                                    Funciones::GetSQLValueString(1, "tinyint") ,
                                    Funciones::GetSQLValueString($areaRecaudadora, "int"),
                                    Funciones::GetSQLValueString(NULL, "decimal"),
                                    Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"),
                                    Funciones::GetSQLValueString($registroConceptos->id, "int")
                                    );
                                if(DB::insert($ConsultaInserta)){
                                    $IdRegistroAsignaci_onPresupuestal = DB::getPdo()->lastInsertId();
                                }
                                $DatosMomentoContable = Funciones::ObtenValor("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Abono) as NaturalezaAbono FROM  MomentoContable WHERE Momento=" . $momento . " AND Cri=" . $registroConceptos->CRI);
                                    //AQUI AGREGO LOS DATOS DE RetencionesAdicionales
                                    //REGLA: si el adicional es retencion se duplica el abono
                                    //verifico que sea un adicional=
                                if(!is_null($registroConceptos->Adicional)){//Adicional o concepto
                                    //es un adicional
                                    $tieneCri=Funciones::ObtenValor("SELECT * FROM RetencionesAdicionales WHERE id=".$registroConceptos->Adicional);
                                    if(is_null($tieneCri->Cri)){
                                        //es adicional con plan de cuentas
                                        //quiere decir que no tiene CRI, trae plan de cuentas
                                        $DatosMomentoContable = Funciones::ObtenValor("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Abono) as NaturalezaAbono FROM  MomentoContable WHERE Momento=" . $momento . " AND Cri=" . $registroConceptos->CRI);
                                        //$planCuentas=ObtenValor();
                                        /**/
                                        //Para Abono 1 negativo
                                        $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                            Funciones::GetSQLValueString(NULL, "int"),
                                            Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                            Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                            Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                            Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                            Funciones::GetSQLValueString("AA1PC Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                            Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                            Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                            Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                            Funciones::GetSQLValueString($Programa, "int"), //programa
                                            Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                            Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                            Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                            Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                            Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                            Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                            Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                            Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                            Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                            Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                            Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                            Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                            Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                            Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                            Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                            Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                            Funciones::GetSQLValueString(NULL, "int"), //CRI
                                            Funciones::GetSQLValueString(NULL, "int"), //COG
                                            Funciones::GetSQLValueString(1, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                            Funciones::GetSQLValueString(($registroConceptos->Importe), "decimal"), //importe
                                            Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                            Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                            Funciones::GetSQLValueString(NULL, "int"), //persona
                                            Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                            Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                            Funciones::GetSQLValueString($DatosMomentoContable->Cargo, "int"), //Plan de cuentas
                                            Funciones::GetSQLValueString($DatosMomentoContable->NaturalezaCargo, "int"), //naturaleza
                                            Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                            Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                            Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                            Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                            Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                            Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                            Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                            Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );
                                        //Para Abono 2 positivo
                                        $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                            Funciones::GetSQLValueString(NULL, "int"),
                                            Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                            Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                            Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                            Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                            Funciones::GetSQLValueString("AA2PC Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                            Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                            Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                            Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                            Funciones::GetSQLValueString($Programa, "int"), //programa
                                            Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                            Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                            Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                            Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                            Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                            Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                            Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                            Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                            Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                            Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                            Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                            Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                            Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                            Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                            Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                            Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                            Funciones::GetSQLValueString(NULL, "int"), //CRI
                                            Funciones::GetSQLValueString(NULL, "int"), //COG
                                            Funciones::GetSQLValueString(2, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                            Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), //importe
                                            Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                            Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                            Funciones::GetSQLValueString(NULL, "int"), //persona
                                            Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                            Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                            Funciones::GetSQLValueString($registroConceptos->PlanCuentasBueno, "int"), //Plan de cuentas
                                            Funciones::GetSQLValueString($registroConceptos->NaturalezaPlanCuentasBueno, "int"), //naturaleza
                                            Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                            Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                            Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                            Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                            Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                            Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                            Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                            Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );
                                    } else {
                                        //es adicional con cri
                                        $DatosMomentoContable = Funciones::ObtenValor("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Abono) as NaturalezaAbono FROM  MomentoContable WHERE Momento=" . $momento . " AND Cri=" . $registroConceptos->CriBueno);
                                        //Para cargo
                                        $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                            Funciones::GetSQLValueString(NULL, "int"),
                                            Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                            Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                            Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                            Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                            Funciones::GetSQLValueString("ACC Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                            Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                            Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                            Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                            Funciones::GetSQLValueString($Programa, "int"), //programa
                                            Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                            Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                            Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                            Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                            Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                            Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                            Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                            Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                            Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                            Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                            Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                            Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                            Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                            Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                            Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                            Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                            Funciones::GetSQLValueString($registroConceptos->CriBueno, "int"), //CRI
                                            Funciones::GetSQLValueString(NULL, "int"), //COG
                                            Funciones::GetSQLValueString(1, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                            Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), //importe
                                            Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                            Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                            Funciones::GetSQLValueString(NULL, "int"), //persona
                                            Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                            Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                            Funciones::GetSQLValueString($DatosMomentoContable->Cargo, "int"), //Plan de cuentas
                                            Funciones::GetSQLValueString($DatosMomentoContable->NaturalezaCargo, "int"), //naturaleza
                                            Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                            Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                            Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                            Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                            Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                            Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                            Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                            Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );
                                            //Para Abono
                                        $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                            Funciones::GetSQLValueString(NULL, "int"),
                                            Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                            Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                            Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                            Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                            Funciones::GetSQLValueString("ACA Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                            Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                            Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                            Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                            Funciones::GetSQLValueString($Programa, "int"), //programa
                                            Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                            Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                            Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                            Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                            Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                            Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                            Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                            Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                            Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                            Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                            Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                            Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                            Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                            Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                            Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                            Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                            Funciones::GetSQLValueString($registroConceptos->CriBueno, "int"), //CRI
                                            Funciones::GetSQLValueString(NULL, "int"), //COG
                                            Funciones::GetSQLValueString(2, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                            Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), //importe
                                            Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                            Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                            Funciones::GetSQLValueString(NULL, "int"), //persona
                                            Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                            Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                            Funciones::GetSQLValueString($DatosMomentoContable->Abono, "int"), //Plan de cuentas
                                            Funciones::GetSQLValueString($DatosMomentoContable->NaturalezaAbono, "int"), //naturaleza
                                            Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                            Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                            Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                            Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                            Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                            Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                            Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                            Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );
                                            /************************************************************************************/
                                        //Esto es presupuestal
                                        $ConsultaObtieneMomentoPresupuestal = sprintf("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Abono) as NaturalezaAbono FROM MomentoPresupuestal WHERE Momento=%s AND Cargo IS NOT NULL AND Abono IS NOT NULL ",  Funciones::GetSQLValueString($momento, "int"));
                                        $ResultadoObtieneMomentoPresupuestal = DB::select($ConsultaObtieneMomentoPresupuestal);
                                        foreach ($ResultadoObtieneMomentoPresupuestal as $RegistroObtieneMomentoPresupuestal) {
                                            //Para cargo en presupuestal
                                            $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                                Funciones::GetSQLValueString(NULL, "int"),
                                                Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                                Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                                Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                                Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                                Funciones::GetSQLValueString("---Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                                Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                                Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                                Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                                Funciones::GetSQLValueString($Programa, "int"), //programa
                                                Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                                Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                                Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                                Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                                Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                                Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                                Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                                Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                                Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                                Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                                Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                                Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                                Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                                Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                                Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                                Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                                Funciones::GetSQLValueString($registroConceptos->CriBueno, "int"), //CRI
                                                Funciones::GetSQLValueString(NULL, "int"), //COG
                                                Funciones::GetSQLValueString(1, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                                Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), //importe
                                                Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                                Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                                Funciones::GetSQLValueString(NULL, "int"), //persona
                                                Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                                Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                                Funciones::GetSQLValueString($RegistroObtieneMomentoPresupuestal->Cargo, "int"), //Plan de cuentas
                                                Funciones::GetSQLValueString($RegistroObtieneMomentoPresupuestal->NaturalezaCargo, "int"), //naturaleza
                                                Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                                Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                                Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                                Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                                Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                                Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                                Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                                Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );
                                            //Para abono en presupuestal
                                            $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                                Funciones::GetSQLValueString(NULL, "int"),
                                                Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                                Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                                Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                                Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                                Funciones::GetSQLValueString("---Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                                Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                                Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                                Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                                Funciones::GetSQLValueString($Programa, "int"), //programa
                                                Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                                Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                                Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                                Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                                Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                                Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                                Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                                Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                                Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                                Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                                Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                                Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                                Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                                Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                                Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                                Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                                Funciones::GetSQLValueString($registroConceptos->CriBueno, "int"), //CRI
                                                Funciones::GetSQLValueString(NULL, "int"), //COG
                                                Funciones::GetSQLValueString(2, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                                Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), //importe
                                                Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                                Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                                Funciones::GetSQLValueString(NULL, "int"), //persona
                                                Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                                Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                                Funciones::GetSQLValueString($RegistroObtieneMomentoPresupuestal->Abono, "int"), //Plan de cuentas
                                                Funciones::GetSQLValueString($RegistroObtieneMomentoPresupuestal->NaturalezaAbono, "int"), //naturaleza
                                                Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                                Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                                Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                                Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                                Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                                Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                                Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                                Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );
                                        }//while($RegistroObtieneMomentoPresupuestal = $ResultadoObtieneMomentoPresupuestal->fetch_assoc()){
                                        //TERMINA PRESUPUESTAL
                                    }
                                }//termina if para saber si es adicional o concepto
                                else{//es concepto
                                    $DatosMomentoContable = Funciones::ObtenValor("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Abono) as NaturalezaAbono FROM  MomentoContable WHERE Momento=" . $momento . " AND Cri=" . $registroConceptos->CRI);
                                    //Para cargo
                                    $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                        Funciones::GetSQLValueString(NULL, "int"),
                                        Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                        Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                        Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                        Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                        Funciones::GetSQLValueString("ConceptoC - Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                        Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                        Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                        Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                        Funciones::GetSQLValueString($Programa, "int"), //programa
                                        Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                        Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                        Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                        Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                        Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                        Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                        Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                        Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                        Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                        Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                        Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                        Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                        Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                        Funciones::GetSQLValueString($registroConceptos->CRI, "int"), //CRI
                                        Funciones::GetSQLValueString(NULL, "int"), //COG
                                        Funciones::GetSQLValueString(1, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                        Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), //importe
                                        Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                        Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                        Funciones::GetSQLValueString(NULL, "int"), //persona
                                        Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                        Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                        Funciones::GetSQLValueString($DatosMomentoContable->Cargo, "int"), //Plan de cuentas
                                        Funciones::GetSQLValueString($DatosMomentoContable->NaturalezaCargo, "int"), //naturaleza
                                        Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                        Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                        Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                        Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                        Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                        Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                        Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                        Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );
                                    //Para Abono
                                    $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                        Funciones::GetSQLValueString(NULL, "int"),
                                        Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                        Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                        Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                        Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                        Funciones::GetSQLValueString("ConceptoA - Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                        Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                        Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                        Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                        Funciones::GetSQLValueString($Programa, "int"), //programa
                                        Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                        Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                        Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                        Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                        Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                        Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                        Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                        Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                        Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                        Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                        Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                        Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                        Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                        Funciones::GetSQLValueString($registroConceptos->CRI, "int"), //CRI
                                        Funciones::GetSQLValueString(NULL, "int"), //COG
                                        Funciones::GetSQLValueString(2, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                        Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), //importe
                                        Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                        Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                        Funciones::GetSQLValueString(NULL, "int"), //persona
                                        Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                        Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                        Funciones::GetSQLValueString($DatosMomentoContable->Abono, "int"), //Plan de cuentas
                                        Funciones::GetSQLValueString($DatosMomentoContable->NaturalezaAbono, "int"), //naturaleza
                                        Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                        Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                        Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                        Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                        Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                        Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                        Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                        Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );
                                    /************************************************************************************/
                                    //Esto es presupuestal
                                    $ConsultaObtieneMomentoPresupuestal = sprintf("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Abono) as NaturalezaAbono FROM MomentoPresupuestal WHERE Momento=%s AND Cargo IS NOT NULL AND Abono IS NOT NULL ",  Funciones::GetSQLValueString($momento, "int"));
                                    $ResultadoObtieneMomentoPresupuestal = DB::select($ConsultaObtieneMomentoPresupuestal);
                                    foreach ($ResultadoObtieneMomentoPresupuestal  as $RegistroObtieneMomentoPresupuestal) {
                                        //Para cargo en presupuestal
                                        $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                            Funciones::GetSQLValueString(NULL, "int"),
                                            Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                            Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                            Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                            Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                            Funciones::GetSQLValueString("---Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                            Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                            Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                            Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                            Funciones::GetSQLValueString($Programa, "int"), //programa
                                            Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                            Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                            Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                            Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                            Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                            Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                            Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                            Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                            Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                            Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                            Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                            Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                            Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                            Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                            Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                            Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                            Funciones::GetSQLValueString($registroConceptos->CRI, "int"), //CRI
                                            Funciones::GetSQLValueString(NULL, "int"), //COG
                                            Funciones::GetSQLValueString(1, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                            Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), //importe
                                            Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                            Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                            Funciones::GetSQLValueString(NULL, "int"), //persona
                                            Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                            Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                            Funciones::GetSQLValueString($RegistroObtieneMomentoPresupuestal->Cargo, "int"), //Plan de cuentas
                                            Funciones::GetSQLValueString($RegistroObtieneMomentoPresupuestal->NaturalezaCargo, "int"), //naturaleza
                                            Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                            Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                            Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                            Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                            Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                            Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                            Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                            Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );
                                        //Para abono en presupuestal
                                        $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),",
                                            Funciones::GetSQLValueString(NULL, "int"),
                                            Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                            Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                            Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                            Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                            Funciones::GetSQLValueString("---Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                            Funciones::GetSQLValueString($IdRegistroCotizaci_on, "int"), //Cotizacion
                                            Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                            Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                            Funciones::GetSQLValueString($Programa, "int"), //programa
                                            Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),//idPrograma
                                            Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                            Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                            Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                            Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                            Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                            Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                            Funciones::GetSQLValueString($Padron->Contribuyente, "int"), //contribuyente
                                            Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                            Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                            Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                            Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                            Funciones::GetSQLValueString($UUID, "varchar"), //uuid
                                            Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                            Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                            Funciones::GetSQLValueString($momento, "int"), //4 Ley de ingreso devengado
                                            Funciones::GetSQLValueString($registroConceptos->CRI, "int"), //CRI
                                            Funciones::GetSQLValueString(NULL, "int"), //COG
                                            Funciones::GetSQLValueString(2, "int"), //Tipo movimiento contable  // 1  cargo   2 abono
                                            Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), //importe
                                            Funciones::GetSQLValueString(NULL, "int"), //estatus inventario
                                            Funciones::GetSQLValueString(NULL, "int"), //id de la obra
                                            Funciones::GetSQLValueString(NULL, "int"), //persona
                                            Funciones::GetSQLValueString(NULL, "int"), //Tipo de gasto
                                            Funciones::GetSQLValueString(NULL, "int"), //Tipo cobro caja
                                            Funciones::GetSQLValueString($RegistroObtieneMomentoPresupuestal->Abono, "int"), //Plan de cuentas
                                            Funciones::GetSQLValueString($RegistroObtieneMomentoPresupuestal->NaturalezaAbono, "int"), //naturaleza
                                            Funciones::GetSQLValueString(NULL, "int"), //tipo bien
                                            Funciones::GetSQLValueString(NULL, "int"), //tipo obra
                                            Funciones::GetSQLValueString(1, "int"), //estatus tupla
                                            Funciones::GetSQLValueString($Fondo, "int"), //fondo
                                            Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                            Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                            Funciones::GetSQLValueString("Cotizacion OPD", "varchar"),
                                            Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );
                                    }//while($RegistroObtieneMomentoPresupuestal = $ResultadoObtieneMomentoPresupuestal->fetch_assoc()){
                                    //TERMINA PRESUPUESTAL
                                }//termina else de si es adicional o concepto
                            }//while
                        }	//Obtiene conceptos
                        $ConsultaInsertaDetalleContabilidadC=substr_replace($ConsultaInsertaDetalleContabilidadC,";",-1);
                        //print $ConsultaInsertaDetalleContabilidadC;
                        //echo  "<pre>".$ConsultaInsertaDetalleContabilidadC."</pre>";
                        if( DB::insert($ConsultaInsertaDetalleContabilidadC)){
                            $ConsultaLog = sprintf("INSERT INTO CelaAccesos ( idAcceso, FechaDeAcceso, idUsuario, Tabla, IdTabla, Acci_on ) VALUES ( %s, %s, %s, %s, %s, %s)",
                                Funciones::GetSQLValueString( NULL, "int"),
                                Funciones::GetSQLValueString( date('Y-m-d H:i:s'), "varchar"),
                                Funciones::GetSQLValueString( $consulta_usuario ->idUsuario, "int"),
                                Funciones::GetSQLValueString( 'Cotizaci_on', "varchar"),
                                Funciones::GetSQLValueString( $IdRegistroCotizaci_on, "int"),
                                Funciones::GetSQLValueString( 2, "int"));
                            $ResultadoLog = DB::insert($ConsultaLog);
                        } else {
                            $Error = $Conexion->error;
                        }
                    }	//termina if($ResultadoInsertaContabilidad = $Conexion->query($ConsultaInsertaContabilidad)){
                    else{ //verifica si no insterto el encabezado contabilidad
                        $Error = $Conexion->error;
                    }
                }//state==0
                return response()->json([
                    'idCotizacion' =>  $IdRegistroCotizaci_on,
                    'Padr_on'=> $IdUltimoPermiso
                ], 200);
            }else{
                return response()->json([
                    'idCotizacion' =>  $IdRegistroCotizaci_on,
                    'Padr_on'=> $IdLicencia
                    ], 200);
                $Status = "Error";
                $Error  = $Conexion->error;
            }
            return response()->json([
                'idCotizacion' =>  $IdRegistroCotizaci_on,
                'Padr_on'=> $IdLicencia
            ], 200);
        } else {
            return response()->json([
                'idCotizacion' =>  $DatosPermiso -> Cotizaci_on,
                'Padr_on'=> $IdLicencia
            ], 200);
        } 
    }

    public static function getPermisoCargaDescarga(Request $request){
        $idCliente = $request -> Cliente;
        $idPadron = $request -> IdPadron;
        if(isset($request -> IdPadron) && $request -> IdPadron!="")
             $idPadron = $request -> IdPadron;
        Funciones::selecionarBase($idCliente);        
        $Cotizacion = Funciones::ObtenValor("SELECT Cotizaci_on FROM PermisoCargaYDescargaHistorial WHERE id = ".$idPadron, "Cotizaci_on");
        $url = 'https://suinpac.com/DocumentoOficialVistaPreviaV2Copia.php';
        $dataForPost = array(
            'Cliente'=> [
                "DocumentoOficial"=>"DocumentoOficialPermisoCargaDescargaHistorial.php",
                "idCotizacionDocumentos"=>$Cotizacion,
                "TipoDocumento"=>"PermisoCargaDescarga",
                "Cliente" => 29
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
        $result = file_get_contents($url, false, $context);
        $result = Funciones::respondWithToken($result);
        $result -> original  ;        
        return $result;
    }
}