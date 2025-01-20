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
// use App\Libs\qrcode;
use App\Libs\QRcode;
use Hamcrest\Core\HasToString;
use Illuminate\Support\Facades\DB;
use \Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use LDAP\Result;
use Prophecy\Doubler\Generator\Node\ReturnTypeNode;

class PermisoProvisionalController extends Controller{
    public function vehiculosPermisoProvisional(Request $request){    
        $Contribuyente = $request->Contribuyente;    
        $Cliente = $request->Cliente; 

        Funciones::selecionarBase($Cliente);
        $Consulta = Funciones::ObtenValores("SELECT pp.*, (SELECT COUNT(*) FROM Cotizaci_on c WHERE c.Tipo = 38 AND c.Padr_on = pp.id) AS Cotizado FROM Contribuyente c 
                                                INNER JOIN DatosFiscales df ON (df.id = c.DatosFiscales)
                                                INNER JOIN PermisoProvisional pp ON (pp.Contribuyente = c.id)
                                            WHERE c.id = ".$Contribuyente." AND c.Cliente = ".$Cliente." ORDER BY pp.id DESC");
        return response()->json([
            'data' => $Consulta
        ]);
    }

    public function obtenerFormatoPermisoProvisionalPorCliente(Request $request){
        $Cliente = $request->Cliente; 
        $idPermiso = $request->idPermiso; 
        $Cotizacion = $request->Cotizacion; 
        Funciones::selecionarBase($Cliente);
        $Consulta = Funciones::ObtenValor("SELECT *,
                                            (SELECT Ruta FROM CelaRepositorioC cr WHERE cr.idRepositorio=cf.Formato) as FormatoFrente,
                                            (SELECT Ruta FROM CelaRepositorioC cr WHERE cr.idRepositorio=cf.FormatoAtras) as FormatoAtras
                                        FROM PermisoProvisionalFormato cf WHERE Cliente = ". $Cliente." ORDER BY id DESC LIMIT 1");
        return response()->json([
            'success' => '1',
            'url'=>$Consulta
        ]);
    }

    public function permisosContribuyente(Request $request){
        $Contribuyente = $request->idContribuyente;
        $Cliente = $request->Cliente;        
        Funciones::selecionarBase($Cliente);
        $Registros = Funciones::ObtenValores("SELECT * FROM ControlPermisosProvisionales WHERE Contribuyente = ".$Contribuyente." AND idDetalleMovimientoBancario IS NOT NULL AND PermisosDisponibles > 0");
        $html = "<label class = 'col-sm-3' for = 'permisosDisponibles'><font color='red'>*</font>&nbsp;Seleccionar Compra:</label>
                <select class = 'form-control col-sm-9' id = 'permisosDisponibles' name = 'permisosDisponibles'>";
                    foreach($Registros as $Registro){
                        $html.= "<option value='".$Registro -> id."'>ID: ".$Registro -> id." - permisos disponibles: ".$Registro -> PermisosDisponibles." - Fecha de compra: ".$Registro -> FechaTupla."</option>";
                    }
        $html.= "</select>";
        return $html;
    }

    public function obtenerPDFPermisoProvisional(Request $request){
        $idPadron = $request->IdPadron;
        $cliente = $request->Cliente;
        $cotizacion = $request ->Cotizacion;
        Funciones::selecionarBase($cliente);
        switch ($cliente){
            case 14: #Permiso Provisional Taxco
                $url = PermisoProvisionalController::obtenerPermisoPorCliente( $idPadron, $cliente, $cotizacion);   
                return $url;
            break;
        }
    }
    public function obtenerCostoPermisoProvisional(Request $request){
        $IdConcepto = $request->IdConcepto;
        $Cliente = $request->Cliente;        
        Funciones::selecionarBase($Cliente);
        $Consulta = Funciones::ObtenValor("SELECT  GROUP_CONCAT(DISTINCT c.id) as ids, c3.EstatusVisible,c3.id as ConceptoGeneral, c.Importe as Importe, c.id as id, c3.id as c3id, c3.Descripci_on as Concepto, 
                                            ci.Descripci_on as Categoria, c4.Clave as CRI, ba.Descripci_on as BaseDeCalculo, a.Descripci_on as AreaAdministrativa, c.EjercicioFiscal,c.AplicaAdicional as estado,
                                            if (COALESCE(c.TipoAdicional,1) = 2,'Importe','Porcentaje') as TipoAdicional, Concat(c4.Clave,' - ',c4.Descripci_on) as ValorConTitle
                                        FROM ConceptoAdicionales c
                                            INNER JOIN ConceptoRetencionesAdicionalesCliente c1 ON ( c.ConceptoRetencionesAdicionalesCliente = c1.id  )
                                            INNER JOIN ConceptoRetencionesAdicionales c2 ON ( c1.ConceptoRetencionesAdicionales = c2.id  )
                                            INNER JOIN ConceptoCobroCaja c3 ON ( c2.Concepto = c3.id  )
                                            INNER JOIN Categor_iaCobro ci ON ( c3.Categor_ia = ci.id  )
                                            INNER JOIN CRI c4 ON ( c3.CRI = c4.id  )
                                            INNER JOIN BaseC_alculo ba ON ( c.BaseCalculo = ba.id  )
                                            INNER JOIN AreasAdministrativas a ON ( c.AreaRecaudadora = a.id  )
                                        WHERE c.Cliente = ".$Cliente." AND c1.Cliente = ".$Cliente." AND c.EjercicioFiscal = ".date('Y')." AND  EstatusVisible=1 AND c3. id = ".$IdConcepto." AND c.Status = 1 
                                            GROUP BY c3.id ORDER BY c3.id ASC");
        return response()->json([
            'Resultado'=>$Consulta
        ]);
    }
    public function obtenerLotesContribuyente(Request $request){
        $cliente = $request['Cliente'];
        $contribuyente = $request['Contribuyente'];
        return $contribuyente;
    }

    public function obtenerFormatoPermisoProvisional(Request $request){
        $cliente = $request['Cliente'];
        $idPermiso = (int) $request['id'];
        Funciones::selecionarBase($cliente);
		$Ruta = Funciones::ObtenValor("SELECT Ruta FROM CelaRepositorioC 
							            WHERE idRepositorio = 
								            ".Funciones::ObtenValor("SELECT Formato FROM PermisoProvisionalFormato 
												                        WHERE id = ".Funciones::ObtenValor("SELECT Valor FROM ClienteDatos 
                                                                                                                WHERE Cliente=" . $cliente . " AND Indice='FormatoPermisoProvisional'"
                                                                                                            , "Valor")
                                                                    ,"Formato")
                                    ,"Ruta");
        $DatosPermiso = Funciones::ObtenValor("SELECT pp.* FROM PermisoProvisional pp 
                                        INNER JOIN Cotizaci_on c ON c.id = pp.Cotizaci_on 
                                    WHERE pp.id = ".$idPermiso);        
        //Obtener el UUID del pago
        $UUIDConsulta="SELECT  x.uuid FROM PagoTicket t
                            INNER JOIN EncabezadoContabilidad ec ON (ec.Pago=t.Pago)
                            INNER JOIN DetalleContabilidad dc ON (dc.EncabezadoContabilidad=ec.id)
                            INNER JOIN Cotizaci_on c ON (c.id=dc.Cotizaci_on)
                            INNER JOIN XMLIngreso x ON (x.idCotizaci_on=c.id)
                            INNER JOIN XML x2 ON (x2.id=x.`xml`)
                        WHERE t.Pago = {$DatosPermiso -> idRecaudo} 
                            AND c.Contribuyente = {$DatosPermiso -> Contribuyente} 
                        GROUP BY x.uuid";
        $UUID = Funciones::ObtenValor($UUIDConsulta,"uuid");
        $rutaQR_code = 'repositorio/temporal/'.$UUID."_". uniqid().'.png';
        $urlCodigoQR = "http://v.servicioenlinea.mx/VerificadorPermisoProvisional.php?".Funciones::EncodeThisV2Personalizada("Cliente=".$cliente."&Pago=".$DatosPermiso -> idRecaudo,"b5s1i4t5a1316");
        $QR = $rutaQR_code;
        //Creación del QR
        if (!file_exists('repositorio/QR/'.date('Y/m/d').'/')) {
            mkdir('repositorio/QR/'.date('Y/m/d').'/', 0755, true);
        }
        if(!file_exists($QR)){
            QRcode::png($urlCodigoQR, $QR, 'M' , 4, 2);    
            // header("Refresh:0");
        }
        /******************* Termina Crea el QR   *****************************/
        $FechaActual= date("d-m-Y");
        $fechaAux= explode("-", $FechaActual);
        $Pago= Funciones::ObtenValor("SELECT date(p.Fecha)as Fecha FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN PagoTicket pt ON(pt.Pago=cac.Pago) INNER JOIN Pago p ON(p.id=pt.Pago) WHERE cac.Cotizaci_on=".$DatosPermiso -> Cotizaci_on,"Fecha");
        $fecha = date('Y-d-m');
        $Datos = Funciones::ObtenValor("SELECT * FROM PermisoProvisional WHERE id = (SELECT Padr_on FROM Cotizaci_on WHERE id = {$DatosPermiso -> Cotizaci_on})");
        $DatosCliente = Funciones::ObtenValor("SELECT C_odigoPostal,Tel_efonoInstitucioinal, N_umeroExterior, N_umeroInterior,Calle,Colonia,Descripci_on, (select Ruta from CelaRepositorioC where CelaRepositorioC.idRepositorio=Cliente.Logotipo) as Logo,"
                . "(SELECT (SELECT Nombre FROM Municipio m WHERE m.id=d.Municipio) FROM DatosFiscalesCliente d WHERE d.id=Cliente.DatosFiscales) AS Municipio,"
                . "(SELECT (SELECT Nombre FROM Localidad m WHERE m.id=d.Localidad) FROM DatosFiscalesCliente d WHERE d.id=Cliente.DatosFiscales) AS Localidad,"
                . "Nombre,DatosFiscales,(SELECT (SELECT Nombre FROM EntidadFederativa m WHERE m.id=d.EntidadFederativa) FROM DatosFiscalesCliente d WHERE d.id=Cliente.DatosFiscales) AS NombreEntidad from "
                . "Cliente inner join DatosFiscalesCliente on(DatosFiscalesCliente.id=Cliente.DatosFiscales) WHERE Cliente.id=" . $cliente);
        $Contribuyente = Funciones::ObtenValor("SELECT CONCAT_WS(' ', c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno) AS Nombre, c.*, CONCAT_WS(' ', Calle_c, Colonia_c, N_umeroExterior_c, Localidad_c ) AS Direccion FROM Contribuyente c WHERE c.id = {$DatosPermiso -> Contribuyente}");
        $Vencimiento = new DateTime($Pago);
        $Vencimiento->modify('+29 days');
        $miHtml = '<html> <meta charset="utf-8">
                        <link href="style.css" type="text/css" rel="stylesheet" media="screen" />
                        <link href="style.css" type="text/css" rel="stylesheet" media="print" />
                        <body style="margin:0px;pading:0px;">
                            <div style="height: 494px; width: 805px;">
                                <img  class="fondo" style="height: 494px; width: 805px;" src=' . 'https://suinpac.com/' . '' . $Ruta . ' style="">
                                <img src=' . 'https://suinpac.com/' . $QR . ' class="QRChico" alt="QR">
                                <strong class = "textofolio">FOLIO &Uacute;NICO</strong>
                                <strong class = "folio">'. str_pad($DatosPermiso->id, 5, "0", STR_PAD_LEFT).'</strong>
                                <strong class = "textoserie"># SERIE VEH&Iacute;CULO</strong>
                                <strong class = "serie">'. mb_strtoupper($DatosPermiso->Serie).'</strong>
                                <strong class = "estado">12</strong>
                                <strong class = "municipio">081</strong>
                                <strong class = "marcatext">MARCA DEL VEH&Iacute;CULO</strong>
                                <strong class = "marca">'.mb_strtoupper($DatosPermiso->Marca).'</strong>
                                <strong class = "modelotext">MODELO DEL VEH&Iacute;CULO</strong>
                                <strong class = "modelo">'.mb_strtoupper($DatosPermiso->Linea).'</strong>
                                <strong class = "colortext">COLOR DEL VEH&Iacute;CULO</strong>
                                <strong class = "color">'.mb_strtoupper($DatosPermiso->Color).'</strong>
                                <strong class = "aniotext">AÑO DEL VEH&Iacute;CULO</strong>
                                <strong class = "anio">'.mb_strtoupper($DatosPermiso->Modelo).'</strong>
        
                                <strong class = "motortext"># DEL MOTOR</strong>
                                <strong class = "motor">'.mb_strtoupper($DatosPermiso->Motor).'</strong>
                                <strong class = "expediciontext">FECHA DE EXPEDICI&Oacute;N</strong>
                                <strong class = "expedicion">'.mb_strtoupper($Pago).'</strong>
                                <strong class = "vencimientotext">FECHA DE VENCIMIENTO</strong>
                                <strong class = "vencimiento">'.$Vencimiento->format('Y-m-d').'</strong>
                                <strong class = "periodoMunicipal">Periodo Municipal</strong>
                                <strong class = "id">'. $DatosPermiso->id.'</strong>
                                </div>
                        </body>';
        $miHtml .= '<style type="text/css">
                    body{
                        font-family: Arial Rounded MT Bold;
                    }
                    .QRGrande{
                        position: absolute;
                        top: 73px;
                        left: 74px;
                        width: 113.5px;
                        height: 112.5px;
                    }
                    .QRChico{
                        position: absolute;
                        top: 248px;
                        left: 698px;
                        width: 85px;
                        height: 78px;
                    }
                    .periodoMunicipal{
                        position: absolute;
                        top: 252px;
                        left: 526px;
                        width: auto;
                        font-size: 18px;
                    }   
                    .id{
                        position: absolute;
                        top: 350px;
                        left: 570px;
                        width: auto;
                        height: 83px;
                        font-size: 40px;                    
                    }                    
                    .folio{
                        position: absolute;
                        top: 145px;
                        left: 205px;
                        width: auto;
                        height: 83px;
                        font-size: 38px;                    
                    }
                    .textofolio{
                        position: absolute;
                        top: 130px;
                        left: 205px;
                        width: auto;
                        height: 83px;                    
                    }
                    .serie{
                        position: absolute;
                        top: 210px;
                        left: 205px;
                        width: auto;
                        height: 83px;
                        font-size: 22px;                    
                    }                    
                    .textoserie{
                        position: absolute;
                        top: 190px;
                        left: 205px;
                        width: auto;
                        height: 83px;                    
                    }                    
                    .estado{
                        position: absolute;
                        top: 240px;
                        left: 90px;
                        width: auto;
                        height: 83px;
                        font-size: 30px;                    
                    }  
                    .municipio{
                        position: absolute;
                        top: 245px;
                        left: 130px;
                        width: auto;
                        height: 83px;
                        font-size: 23px;                    
                    }
                    .motortext{
                        position: absolute;
                        top: 280px;
                        left: 320px;
                        width: auto;
                        height: 83px;
                        font-size: 12px;                    
                    } 
                    .motor{
                        position: absolute;
                        top: 295px;
                        left: 320px;
                        width: auto;
                        height: 83px;
                        font-size: 19px;                    
                    } 
                    .expediciontext{
                        position: absolute;
                        top: 320px;
                        left: 320px;
                        width: auto;
                        height: 83px;
                        font-size: 12px;                    
                    }     
                    .expedicion{
                        position: absolute;
                        top: 335px;
                        left: 320px;
                        width: auto;
                        height: 83px;
                        font-size: 20px;                    
                    }    
                    .vencimientotext{
                        position: absolute;
                        top: 360px;
                        left: 320px;
                        width: auto;
                        height: 83px;
                        font-size: 12px;                    
                    }                                  
                    .vencimiento{
                        position: absolute;
                        top: 375px;
                        left: 320px;
                        width: auto;
                        height: 83px;
                        font-size: 28px;  
                        color: red;                  
                    }                                                                        
                    .marcatext{
                        position: absolute;
                        top: 280px;
                        left: 95px;
                        width: auto;
                        height: 83px;
                        font-size: 12px;                    
                    }                                                        
                    .marca{
                        position: absolute;
                        top: 295px;
                        left: 95px;
                        width: auto;
                        height: 83px;
                        font-size: 18px;                    
                    }
                    .modelotext{
                        position: absolute;
                        top: 320px;
                        left: 95px;
                        width: auto;
                        height: 83px;
                        font-size: 12px;                    
                    }                     
                    .modelo{
                        position: absolute;
                        top: 335px;
                        left: 95px;
                        width: auto;
                        height: 83px;
                        font-size: 20px;                    
                    }     
                    .colortext{
                        position: absolute;
                        top: 360px;
                        left: 95px;
                        width: auto;
                        height: 83px;
                        font-size: 12px;                    
                    }                                  
                    .color{
                        position: absolute;
                        top: 375px;
                        left: 95px;
                        width: auto;
                        height: 83px;
                        font-size: 20px;                    
                    }     
                    .aniotext{
                        position: absolute;
                        top: 400px;
                        left: 95px;
                        width: auto;
                        height: 83px;
                        font-size: 12px;                    
                    }                
                    .anio{
                        position: absolute;
                        top: 415px;
                        left: 95px;
                        width: auto;
                        height: 83px;
                        font-size: 20px;                    
                    }                    
                    </style>';                                                                      
        // $html = "<img id='imgFormato' width='100%' style='min-height:98vh' class='' src='https://suinpac.com/".$Ruta."'></img>";
        $response=[
            'success' => 1,
            'ruta' => $miHtml
        ];
        $result = Funciones::respondWithToken($response);
        return $result;                                                
	}

    public function depositoABanco(Request $request){
        global $Conexion; 
        $Cliente= $request->Cliente;
        $Contribuyente= $request-> Contribuyente;
        $CantidadPermisos = $request -> CantidadPermisos;
        Funciones::selecionarBase($Cliente);
        $_POST['CuentaATransferir'] = 1279;
        // $_POST['CuentaATransferir'] = 1188;
        $_POST['PlandeCuentasCargoCheque'] = 32;
        $_POST['ConceptoCheque'] = "PAGO POR VOLUMEN DE PERMISOS PROVISIONALES PARA CONDUCIR POR 30 DIAS";
        $_POST['MovimientoBancarioCheque'] = 10;
        $_POST['N_umeroMovimientoBancarioCheque'] = $request -> IdTransaccion;
        $_POST['ImporteCheque'] = $request -> ImporteTotal;
        $_POST['TipoM'] = 4;
        $caja = Funciones::ObtenValor("SELECT cc.id as idCorteCaja FROM 
                                            CorteDeCaja cc
                                        INNER JOIN CelaUsuario cu ON (cu.idUsuario=cc.Usuario AND cu.CajaDeCobro=cc.CajaDeCobro)
                                        INNER JOIN CajaDeCobro cdc ON (cdc.id=cu.CajaDeCobro)
                                            WHERE cu.CorreoElectr_onico='" . $Cliente . "@gmail.com'
                                        AND cdc.Cliente=" . $Cliente . " AND SaldoFinal IS NULL", "idCorteCaja");  
        $FuenteFinanciamiento = Funciones::ObtenValor('SELECT FuenteFinanciamiento FROM Cat_alogoDeFondo WHERE id=(SELECT CatalogoFondo FROM Fondo WHERE id=(SELECT Fondo FROM PresupuestoAnualPrograma WHERE id=(SELECT PresupuestoAnualPrograma FROM CuentaBancaria WHERE id='.$_POST['CuentaATransferir'].')))','FuenteFinanciamiento' );	        
	    $CatalogoFondo = Funciones::ObtenValor('SELECT id FROM Cat_alogoDeFondo WHERE id=(SELECT CatalogoFondo FROM Fondo WHERE id=(SELECT Fondo FROM PresupuestoAnualPrograma WHERE id=(SELECT PresupuestoAnualPrograma FROM CuentaBancaria WHERE id='.$_POST['CuentaATransferir'].')))', 'id' );	
	    $Fondo = Funciones::ObtenValor('SELECT Fondo FROM PresupuestoAnualPrograma WHERE id = (SELECT PresupuestoAnualPrograma FROM CuentaBancaria WHERE id='.$_POST['CuentaATransferir'].' )', 'Fondo');	
	    $Programa = Funciones::ObtenValor('SELECT Programa FROM  PresupuestoAnualPrograma WHERE id = ( SELECT PresupuestoAnualPrograma FROM CuentaBancaria WHERE id='.$_POST['CuentaATransferir'].' )', 'Programa');	
	    $PresupuestoAnualPrograma = Funciones::ObtenValor('SELECT id FROM  PresupuestoAnualPrograma WHERE id = ( SELECT PresupuestoAnualPrograma FROM CuentaBancaria WHERE id='.$_POST['CuentaATransferir'].' )', 'id');	
	    $ejercicioCorrecto = Funciones::ObtenValor('SELECT pga.EjercicioFiscal as EjercicioFiscal FROM  PresupuestoAnualPrograma pap 
                                        INNER JOIN Fondo f ON (pap.Fondo=f.id)
                                        INNER JOIN PresupuestoGeneralAnual pga ON (pga.id=f.Presupuesto)
                                    WHERE pap.id = '.$PresupuestoAnualPrograma, 'EjercicioFiscal');	
	    $NaturalezaCuenta = Funciones::ObtenValor('SELECT Naturaleza FROM  PlanCuentas WHERE id = ( SELECT PlandeCuentas FROM CuentaBancaria WHERE id='.$_POST['CuentaATransferir'].' )', 'Naturaleza');
	    $NaturalezaCuentaA = Funciones::ObtenValor('SELECT Naturaleza FROM  PlanCuentas WHERE id = ( SELECT PlanCuentas FROM TipoCobroCaja WHERE id='.$_POST['PlandeCuentasCargoCheque'].' )', 'Naturaleza');	
        $Proveedor=NULL;
        $Empleado=NULL;
        $ProyectoR=NULL;
        $Clasificaci_onAdministrativa=NULL;
        $Clasificaci_onFuncional=NULL;
        $Clasificaci_onProgram_atica=NULL;
        if($_POST['TipoM']==4){
            $Cargo = Funciones::ObtenValor('SELECT PlandeCuentas FROM CuentaBancaria WHERE id='.$_POST['CuentaATransferir'],'PlandeCuentas');
            $Abono = Funciones::ObtenValor('SELECT PlanCuentas FROM TipoCobroCaja WHERE id='.$_POST['PlandeCuentasCargoCheque'],'PlanCuentas');
            if($_POST['TipoM']==4){
                $_POST['PlandeCuentasCargoChequeDNI'] = 32; //Ingresos cobrados por adelantado a corto Plazo
                $NaturalezaCuentaA = Funciones::ObtenValor('SELECT Naturaleza FROM  PlanCuentas WHERE id = ( SELECT PlanCuentas FROM TipoCobroCaja WHERE id='.$_POST['PlandeCuentasCargoChequeDNI'].' )', 'Naturaleza');
                $Abono = Funciones::ObtenValor('SELECT PlanCuentas FROM TipoCobroCaja WHERE id='.$_POST['PlandeCuentasCargoChequeDNI'],'PlanCuentas');
            }            
        }
        $consulta_usuario = Funciones::ObtenValor("SELECT c.idUsuario, c.Usuario FROM CelaUsuario c 
                                                    INNER JOIN CelaRol c1 ON ( c.Rol = c1.idRol  )   
                                                WHERE c.CorreoElectr_onico='" . $Cliente . "@gmail.com' ");
	    //Registramos para el cargo        
	    $ConsultaInserta = sprintf("INSERT INTO DetalleMovimientoBancario ( id, Cliente, FuenteFinanciamiento, Fondo, Programa, CuentaBancaria, MovimientoBancario, N_umeroMovimientoBancario, Cog, PlanCuentas, 
                                    Momento, Cri, Fecha, Estatus, PagoAProveedor, PagoAEmpleado, PagoABeneficiario, ConceptoPagadoNoPagado, FechaConciliado, Importe, Origen,idCorteCaja) 
                                VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s )",
            Funciones::GetSQLValueString(NULL, "int"),
            Funciones::GetSQLValueString($Cliente, "int"),
            Funciones::GetSQLValueString($FuenteFinanciamiento, "int"),
            Funciones::GetSQLValueString($Fondo, "int"),
            Funciones::GetSQLValueString($Programa, "int"),
            Funciones::GetSQLValueString($_POST['CuentaATransferir'], "int"),
            Funciones::GetSQLValueString($_POST['MovimientoBancarioCheque'], "int"),
            Funciones::GetSQLValueString($_POST['N_umeroMovimientoBancarioCheque'], "int"),
            Funciones::GetSQLValueString(NULL, "int"),
            Funciones::GetSQLValueString(NULL, "int"),
            Funciones::GetSQLValueString(NULL, "int"),
            Funciones::GetSQLValueString(NULL, "int"),
            Funciones::GetSQLValueString(date('Y-m-d H:m:s'), "datetime"),
            Funciones::GetSQLValueString(1, "int"),
            Funciones::GetSQLValueString($Proveedor, "int"),
            Funciones::GetSQLValueString($Empleado, "int"),
            Funciones::GetSQLValueString($Contribuyente, "int"),
            Funciones::GetSQLValueString(NULL, "int"),
            Funciones::GetSQLValueString(date('Y-m-d H:m:s'), "datetime"),
            Funciones::GetSQLValueString($_POST['ImporteCheque'], "decimal"),
            Funciones::GetSQLValueString('Deposito Anticipado Banco', "varchar"),
            Funciones::GetSQLValueString($caja, "int"));
	    if(DB::insert($ConsultaInserta)){            
		    $IdDetalleMovimientoBancarioC = DB::getPdo()->lastInsertId();		
		    //Obtenemos el Folio para la poliza
		    $ClaveCliente = Funciones::ObtenValor('SELECT Clave FROM Cliente WHERE id='.$Cliente,'Clave');
		    $UltimaPoliza = Funciones::ObtenValor("SELECT N_umeroP_oliza as Ultimo FROM EncabezadoContabilidad WHERE N_umeroP_oliza like '".$ejercicioCorrecto.$ClaveCliente.str_pad($Programa, 3, '0', STR_PAD_LEFT)."02%' order by N_umeroP_oliza desc limit 0,1","Ultimo");
    		if($UltimaPoliza=='NULL')
	    		$N_umeroDePoliza=$ejercicioCorrecto.$ClaveCliente.str_pad($Programa, 3, '0', STR_PAD_LEFT)."02".str_pad(1, 6, '0', STR_PAD_LEFT);
		    else
			    $N_umeroDePoliza=$ejercicioCorrecto.$ClaveCliente.str_pad($Programa, 3, '0', STR_PAD_LEFT)."02".str_pad(substr($UltimaPoliza, -6, 6)+1, 6, '0', STR_PAD_LEFT);			
		    //Datos el la tabla de contabilidad.
		    $ConsultaInsertaContabilidad = sprintf("INSERT INTO EncabezadoContabilidad ( id, Cliente, EjercicioFiscal, TipoP_oliza, N_umeroP_oliza, FechaP_oliza, Concepto, Cotizaci_on, AreaRecaudadora, FuenteFinanciamiento, Fondo, CatalogoFondo, Programa, idPrograma, Proyecto, Clasificaci_onProgram_atica, Clasificaci_onFuncional, Clasificaci_onAdministrativa, Contribuyente, Persona, CuentaBancaria, MovimientoBancario, N_umeroDeMovimientoBancario, Momento, EstatusTupla, FechaTupla, AreaAdministrativaProyecto, Proveedor) 
                                                    VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s )",
                Funciones::GetSQLValueString(NULL, "int"),
                Funciones::GetSQLValueString($Cliente, "int"),
                Funciones::GetSQLValueString($ejercicioCorrecto, "int"),
                Funciones::GetSQLValueString(3, "int"),
                Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"),
                Funciones::GetSQLValueString(date('Y-m-d H:m:s'), "datetime"),
                Funciones::GetSQLValueString($_POST['ConceptoCheque'], "varchar"),
                Funciones::GetSQLValueString(NULL, "int"),
                Funciones::GetSQLValueString(NULL, "int"),
                Funciones::GetSQLValueString($FuenteFinanciamiento, "int"),
                Funciones::GetSQLValueString($Fondo, "int"),
                Funciones::GetSQLValueString($CatalogoFondo, "int"),
                Funciones::GetSQLValueString($Programa, "int"),
                Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),
                Funciones::GetSQLValueString($ProyectoR, "int"),
                Funciones::GetSQLValueString($Clasificaci_onProgram_atica, "int"),
                Funciones::GetSQLValueString($Clasificaci_onFuncional, "int"),
                Funciones::GetSQLValueString($Clasificaci_onAdministrativa, "int"),
                Funciones::GetSQLValueString($Contribuyente, "int"),
                Funciones::GetSQLValueString($Empleado, "int"),
                Funciones::GetSQLValueString($_POST['CuentaATransferir'], "int"),
                Funciones::GetSQLValueString($_POST['MovimientoBancarioCheque'], "int"),
                Funciones::GetSQLValueString($_POST['N_umeroMovimientoBancarioCheque'], "int"),
                Funciones::GetSQLValueString(NULL, "int"),
                Funciones::GetSQLValueString(1, "int"),
                Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                Funciones::GetSQLValueString(NULL, "int"),
                Funciones::GetSQLValueString($Proveedor, "int"));
            if(DB::insert($ConsultaInsertaContabilidad)){
                $IdEncabezadoContabilidad = DB::getPdo()->lastInsertId();           
                $InsertarControlPP = sprintf("INSERT INTO ControlPermisosProvisionales ( id, Contribuyente, CantidadPermisos, PermisosDisponibles, Impresos, FechaTupla, EjercicioFiscal, Cliente, TipoMovimiento, idDetalleMovimientoBancario) VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
                        Funciones::GetSQLValueString( NULL, "int"),
                        Funciones::GetSQLValueString( $Contribuyente, "int"),
                        Funciones::GetSQLValueString( $CantidadPermisos, "int"),
                        Funciones::GetSQLValueString( $CantidadPermisos, "int"),
                        Funciones::GetSQLValueString( 0, "int"),
                        Funciones::GetSQLValueString( date('Y-m-d H:m:s'), "varchar"),
                        Funciones::GetSQLValueString( $ejercicioCorrecto, "int"),
                        Funciones::GetSQLValueString( $Cliente, "int"),
                        Funciones::GetSQLValueString( 2, "int"),
                        Funciones::GetSQLValueString( $IdDetalleMovimientoBancarioC, "int"),
                    );
                DB::insert($InsertarControlPP);    
                /*Esta bloque es  para el Pago anticipado en mi tabla de control*/                                          
                //Se registra el detallado de la contabilidad para el moviminto bancario Cargo.
                $ConsultaInsertaDetalleContabilidadC=sprintf("INSERT INTO DetalleContabilidad ( id, EncabezadoContabilidad, TipoP_oliza, FehaP_oliza, N_umeroP_oliza, ConceptoMovimientoContable, Cotizaci_on, 
                        AreaRecaudaci_on, FuenteFinanciamiento, Programa, idPrograma, Proyecto, AreaAdministrativaProyecto, Clasificaci_onProgram_atica, Clasificaci_onFuncionalGasto, Clasificaci_onAdministrativa, 
                        Clasificaci_onEcon_omicaIGF, Contribuyente, Proveedor, CuentaBancaria, MovimientoBancario, N_umeroMovimientoBancario, uuid, EstatusConcepto, ConceptoDeXML, MomentoContable, CRI, COG, 
                        TipoDeMovimientoContable, Importe, EstatusInventario, idDeLaObra, Persona, TipoDeGasto, TipoCobroCaja, PlanDeCuentas, NaturalezaCuenta, TipoBien, TipoObra, EstatusTupla, Fondo, CatalogoFondo, 
                        FechaTupla, idMovimientoBancario, Origen) VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s );",
                    Funciones::GetSQLValueString(NULL, "int"),
                    Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"),
                    Funciones::GetSQLValueString(3, "int"),
                    Funciones::GetSQLValueString(date('Y-m-d H:m:s'), "datetime"),
                    Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"),
                    Funciones::GetSQLValueString($_POST['ConceptoCheque'], "varchar"),
                    Funciones::GetSQLValueString(NULL, "int"),
                    Funciones::GetSQLValueString(NULL, "int"),
                    Funciones::GetSQLValueString($FuenteFinanciamiento, "int"),
                    Funciones::GetSQLValueString($Programa, "int"),
                    Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),
                    Funciones::GetSQLValueString($ProyectoR, "int"),
                    Funciones::GetSQLValueString($Clasificaci_onProgram_atica, "int"),
                    Funciones::GetSQLValueString($Clasificaci_onFuncional, "int"),
                    Funciones::GetSQLValueString($Clasificaci_onAdministrativa, "int"),
                    Funciones::GetSQLValueString(NULL, "int"),
                    Funciones::GetSQLValueString(NULL, "int"),
                    Funciones::GetSQLValueString($Contribuyente, "int"),
                    Funciones::GetSQLValueString($Proveedor, "int"),
                    Funciones::GetSQLValueString($_POST['CuentaATransferir'], "int"),
                    Funciones::GetSQLValueString($_POST['MovimientoBancarioCheque'], "int"),
                    Funciones::GetSQLValueString($_POST['N_umeroMovimientoBancarioCheque'], "int"),
                    Funciones::GetSQLValueString(NULL, "int"),
                    Funciones::GetSQLValueString(NULL, "int"),
                    Funciones::GetSQLValueString(NULL, "int"),
                    Funciones::GetSQLValueString(NULL, "int"),
                    Funciones::GetSQLValueString(NULL, "int"),
                    Funciones::GetSQLValueString(NULL, "int"),
                    Funciones::GetSQLValueString(1, "int"),
                    Funciones::GetSQLValueString($_POST['ImporteCheque'], "decimal"),
                    Funciones::GetSQLValueString(NULL, "int"),
                    Funciones::GetSQLValueString(NULL, "int"),
                    Funciones::GetSQLValueString($Empleado, "int"),
                    Funciones::GetSQLValueString(NULL, "int"),
                    Funciones::GetSQLValueString(NULL, "int"),
                    Funciones::GetSQLValueString($Cargo, "int"),
                    Funciones::GetSQLValueString($NaturalezaCuenta, "int"),
                    Funciones::GetSQLValueString(NULL, "int"),
                    Funciones::GetSQLValueString(NULL, "int"),
                    Funciones::GetSQLValueString(1, "int"),
                    Funciones::GetSQLValueString($Fondo, "int"),
                    Funciones::GetSQLValueString($CatalogoFondo, "int"),
                    Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                    Funciones::GetSQLValueString($IdDetalleMovimientoBancarioC, "int"),
                    Funciones::GetSQLValueString('Deposito Anticipado Banco', "varchar"));
                if(DB::insert($ConsultaInsertaDetalleContabilidadC)){
                    $IdDetalleContabilidadC = DB::getPdo()->lastInsertId();
                    //Se registra el detallado de la contabilidad para el moviminto bancario Abono.
                    $ConsultaInsertaDetalleContabilidadA=sprintf("INSERT INTO DetalleContabilidad ( id, EncabezadoContabilidad, TipoP_oliza, FehaP_oliza, N_umeroP_oliza, ConceptoMovimientoContable, Cotizaci_on, 
                            AreaRecaudaci_on, FuenteFinanciamiento, Programa, idPrograma, Proyecto, AreaAdministrativaProyecto, Clasificaci_onProgram_atica, Clasificaci_onFuncionalGasto, Clasificaci_onAdministrativa, 
                            Clasificaci_onEcon_omicaIGF, Contribuyente, Proveedor, CuentaBancaria, MovimientoBancario, N_umeroMovimientoBancario, uuid, EstatusConcepto, ConceptoDeXML, MomentoContable, CRI, COG, 
                            TipoDeMovimientoContable, Importe, EstatusInventario, idDeLaObra, Persona, TipoDeGasto, TipoCobroCaja, PlanDeCuentas, NaturalezaCuenta, TipoBien, TipoObra, EstatusTupla, Fondo, 
                            CatalogoFondo, FechaTupla, idMovimientoBancario, Origen) VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s );",
                        Funciones::GetSQLValueString(NULL, "int"),
                        Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"),
                        Funciones::GetSQLValueString(3, "int"),
                        Funciones::GetSQLValueString(date('Y-m-d H:m:s'), "datetime"),
                        Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"),
                        Funciones::GetSQLValueString($_POST['ConceptoCheque'], "varchar"),
                        Funciones::GetSQLValueString(NULL, "int"),
                        Funciones::GetSQLValueString(NULL, "int"),
                        Funciones::GetSQLValueString($FuenteFinanciamiento, "int"),
                        Funciones::GetSQLValueString($Programa, "int"),
                        Funciones::GetSQLValueString($PresupuestoAnualPrograma, "int"),
                        Funciones::GetSQLValueString($ProyectoR, "int"),
                        Funciones::GetSQLValueString($Clasificaci_onProgram_atica, "int"),
                        Funciones::GetSQLValueString($Clasificaci_onFuncional, "int"),
                        Funciones::GetSQLValueString($Clasificaci_onAdministrativa, "int"),
                        Funciones::GetSQLValueString(NULL, "int"),
                        Funciones::GetSQLValueString(NULL, "int"),
                        Funciones::GetSQLValueString($Contribuyente, "int"),
                        Funciones::GetSQLValueString($Proveedor, "int"),
                        Funciones::GetSQLValueString($_POST['CuentaATransferir'], "int"),
                        Funciones::GetSQLValueString($_POST['MovimientoBancarioCheque'], "int"),
                        Funciones::GetSQLValueString($_POST['N_umeroMovimientoBancarioCheque'], "int"),
                        Funciones::GetSQLValueString(NULL, "int"),
                        Funciones::GetSQLValueString(NULL, "int"),
                        Funciones::GetSQLValueString(NULL, "int"),
                        Funciones::GetSQLValueString(NULL, "int"),
                        Funciones::GetSQLValueString(NULL, "int"),
                        Funciones::GetSQLValueString(NULL, "int"),
                        Funciones::GetSQLValueString(2, "int"),
                        Funciones::GetSQLValueString($_POST['ImporteCheque'], "decimal"),
                        Funciones::GetSQLValueString(NULL, "int"),
                        Funciones::GetSQLValueString(NULL, "int"),
                        Funciones::GetSQLValueString($Empleado, "int"),
                        Funciones::GetSQLValueString(NULL, "int"),
                        Funciones::GetSQLValueString(NULL, "int"),
                        Funciones::GetSQLValueString($Abono, "int"),
                        Funciones::GetSQLValueString($NaturalezaCuentaA, "int"),
                        Funciones::GetSQLValueString(NULL, "int"),
                        Funciones::GetSQLValueString(NULL, "int"),
                        Funciones::GetSQLValueString(1, "int"),
                        Funciones::GetSQLValueString($Fondo, "int"),
                        Funciones::GetSQLValueString($CatalogoFondo, "int"),
                        Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                        Funciones::GetSQLValueString($IdDetalleMovimientoBancarioC, "int"),
                        Funciones::GetSQLValueString('Deposito Anticipado Banco', "varchar"));
                    if(DB::insert($ConsultaInsertaDetalleContabilidadA)){
                        $IdDetalleContabilidadA = DB::getPdo()->lastInsertId();
                        $Status = "Success";
                        $Error = "";        
                        $ConsultaLog = sprintf("INSERT INTO CelaAccesos ( idAcceso, FechaDeAcceso, idUsuario, Tabla, IdTabla, Acci_on, Descripci_onCela) VALUES ( %s, %s, %s, %s, %s, %s, %s)",
                                Funciones::GetSQLValueString( NULL, "int"),
                                Funciones::GetSQLValueString( date('Y-m-d H:i:s'), "varchar"),
                                Funciones::GetSQLValueString( $consulta_usuario ->idUsuario, "int"),
                                Funciones::GetSQLValueString( 'Anticipo a Banco en Linea', "varchar"),
                                Funciones::GetSQLValueString( $IdDetalleMovimientoBancarioC, "int"),
                                Funciones::GetSQLValueString( 2, "int"),
                                Funciones::GetSQLValueString( "Permisos Provisonales del Contribuyente ".$Contribuyente, "int"),
                            );
                        $ResultadoLog = DB::insert($ConsultaLog);        
                    } else {
                        $Status = "Error";
                        $Error  = $Conexion->error;
                    }
                } else {
                    $Status = "Error";
                    $Error  = $Conexion->error;
                }
            } else {
                $Status = "Error";
                $Error  = $Conexion->error;
            }
        } else {
            $Status = "Error";
            $Error  = $Conexion->error;
        }        
        return response()->json([
            'Resultado' => $Status,
            'Error' => $Error,           
        ]);
    }

    public function obtenerPermisoPorCliente($idPadron, $cliente, $cotizacion = 0){
        $usuario='usuarioAPISUINPAC';
        $url = 'https://luisddev.suinpac.dev/FormatoPermisoProvisionalAPI.php';
        $dataForPost = array(
            'Cliente'=> [
                "Cliente"=>$cliente,
                "idPadron"=>$idPadron,
                "Usuario"=>$usuario,
                "Cotizacion"=>$cotizacion
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
        return response()->json([        
            'datos' => $result
        ]);
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
    public function InsertarVehiculoContribuyente(Request $request){
        global $Conexion; 
        $Cliente= $request->Cliente;
        $Contribuyente= $request-> Contribuyente;
        $Marca= $request->Marca;
        $Linea= $request->Linea;
        $Modelo= $request->Modelo;
        $Color= $request->Color;
        $Serie= $request->Serie;
        $Motor= $request->Motor;
        $Observacion= $request->Observacion;
        $Lote = $request->Lote;
        $FechaInicial = $request-> FechaPermiso;
        $Consumo = $request -> Consumo;
        Funciones::selecionarBase($Cliente);
        $FechaFinal = new DateTime($FechaInicial);
        $FechaFinal->modify('+29 days');
        $ConsultaInserta = sprintf("INSERT INTO PermisoProvisional (  `id` ,`LicenciaFuncionamiento`,  `Marca` , `Serie` , `Color` , `Modelo`, `Motor`, `Linea`, `Consumo`, `FechaInicial`, `FechaFinal` , `Obsevaci_on`, `FechaLectura`,`Estatus`,`FechaTupla` ,`UsuarioCotiza` ,`Contribuyente`, `Lote`) 
                                        VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s )",
            Funciones::GetSQLValueString(NULL, "int unsigned"),
            Funciones::GetSQLValueString(0, "int") ,
            Funciones::GetSQLValueString($Marca, "varchar") ,
            Funciones::GetSQLValueString($Serie, "varchar") ,
            Funciones::GetSQLValueString($Color, "varchar") ,
            Funciones::GetSQLValueString($Modelo, "varchar"),
            Funciones::GetSQLValueString($Motor, "varchar") ,
            Funciones::GetSQLValueString($Linea, "varchar") ,
            Funciones::GetSQLValueString($Consumo, "double") ,
            Funciones::GetSQLValueString($FechaInicial, "varchar") ,
            Funciones::GetSQLValueString($FechaFinal->format('Y-m-d'), "varchar") ,
            Funciones::GetSQLValueString($Observacion, "varchar") ,
            Funciones::GetSQLValueString(date('Y-m-d'), "varchar"),
            Funciones::GetSQLValueString(-1, "varchar") ,
            Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "varchar"),
            Funciones::GetSQLValueString(3667, "int") ,
            Funciones::GetSQLValueString($Contribuyente, "int"),
            Funciones::GetSQLValueString($Lote, "int"));
        if( DB::insert($ConsultaInserta)){
            echo DB::getPdo()->lastInsertId();
            DB::update("UPDATE ControlPermisosProvisionales SET PermisosDisponibles = (PermisosDisponibles - 1), Impresos = (Impresos + 1) WHERE id = ".$Lote);            
        }
    }
    public function cotizarPermisoProvisional(Request $request){
        global $Conexion;        
        $IdPermiso = $request->Padron;        
        $Concepto []=$request->Concepto;
        $Cliente = $request->Cliente;
        $Importe = $request->Importe;
        $IdRegistroCotizaci_on = "";
        Funciones::selecionarBase($Cliente);
        $anio=date('Y');
        $DatosPermiso =Funciones::ObtenValor("SELECT * FROM PermisoProvisional WHERE id=".$IdPermiso);
        $fechainicial = date("Y-m-d", strtotime($DatosPermiso->FechaInicial));
        $fechafinal = date("Y-m-d", strtotime($DatosPermiso->FechaFinal));                      
        $IdUltimoPermiso = 0;
        if($DatosPermiso -> FechaInicial != '') {
            $Padron=Funciones::ObtenValor("SELECT * FROM PermisoProvisional WHERE id=".$IdPermiso);                                                                                                                                                
            $clienteClave = Funciones::ObtenValor('SELECT Clave FROM Cliente WHERE id=' .$Cliente, 'Clave');
            $UltimaCotizacion = Funciones::ObtenValor("SELECT FolioCotizaci_on FROM Cotizaci_on WHERE FolioCotizaci_on like '".$anio.$clienteClave."%' ORDER BY FolioCotizaci_on desc limit 0,1", "FolioCotizaci_on");
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
                                                (SELECT Descripci_on FROM Cat_alogoPrograma WHERE PresupuestoAnualPrograma.Programa=Cat_alogoPrograma.id) LIKE '%ingresos propios%' 
                                                AND Cliente=".$Cliente." AND EjercicioFiscal=".$anio,"Fondo");
            $FuenteFinanciamiento=Funciones::ObtenValor("SELECT f1.id as FuenteFinanciamiento FROM Fondo f
                                                            INNER JOIN Cat_alogoDeFondo ca ON ( f.CatalogoFondo = ca.id  )
                                                            INNER JOIN FuenteFinanciamiento f1 ON ( ca.FuenteFinanciamiento = f1.id  )
                                                        WHERE f.id = ".$Fondo, "FuenteFinanciamiento");
            $CatalogoFondo= Funciones::ObtenValor('SELECT CatalogoFondo FROM Fondo WHERE id='.$Fondo,'CatalogoFondo' );
            $fecha=date('Y-m-d');
            $fechaCFDI=date('Y-m-d H:i:s');
            $consulta_usuario = Funciones::ObtenValor("SELECT c.idUsuario, c.Usuario FROM CelaUsuario c 
                                                            INNER JOIN CelaRol c1 ON ( c.Rol = c1.idRol  )   
                                                        WHERE c.CorreoElectr_onico='" . $Cliente . "@gmail.com' ");
            $IdRegistroCotizaci_on = 0;
            $PresupuestoAnualPrograma=Funciones::ObtenValor("SELECT id, (SELECT Descripci_on FROM Cat_alogoPrograma WHERE PresupuestoAnualPrograma.Programa=Cat_alogoPrograma.id) FROM PresupuestoAnualPrograma WHERE Fondo=".$Fondo,"id");
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
                Funciones::GetSQLValueString(38, "int") ,
                Funciones::GetSQLValueString($fechaCFDI, "date") ,
                Funciones::GetSQLValueString($consulta_usuario ->idUsuario, "int"),
                Funciones::GetSQLValueString($Padron->id, "int"));
            if( DB::insert($ConsultaInserta)){
                $IdRegistroCotizaci_on = DB::getPdo()->lastInsertId();
                // clve cliente + anio + area recaudadora + cpnsecutivo                
                DB::update("UPDATE PermisoProvisional SET Cotizaci_on = ".$IdRegistroCotizaci_on.", Estatus = 1 WHERE id = ".$Padron->id);
                $CajadeCobro=Funciones::ObtenValor("SELECT CajaDeCobro FROM CelaUsuario WHERE idUsuario=".$consulta_usuario ->idUsuario, "CajaDeCobro");
                $areaRecaudadora = Funciones::ObtenValor("SELECT Clave FROM AreasAdministrativas WHERE id=".$areaRecaudadora, "Clave");
                $UltimoFolio = Funciones::ObtenValor("SELECT Folio FROM XMLIngreso INNER JOIN Cotizaci_on c ON (c.id=XMLIngreso.idCotizaci_on)  WHERE c.Cliente='".$Cliente."' AND Folio like '%" . $clienteClave . $anio. $areaRecaudadora . "%' order by Folio desc limit 0,1", "Folio");
                $Serie = $clienteClave . $anio . $areaRecaudadora;
                $medoPago=04;
                if ($UltimoFolio == 'NULL') {
                    $N_umeroDeFolio = str_pad(1, 8, '0', STR_PAD_LEFT);
                } else {
                    $N_umeroDeFolio = str_pad(intval(substr($UltimoFolio, -8, 8)) + 1, 8, '0', STR_PAD_LEFT);
                }
                $arr['Contribuyente']=$Padron->Contribuyente;
                $arr['id']= $Padron->id;
                $arr['Serie']=$Padron->Serie;
                $arr['Lote']=$Padron->Lote;
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
                        Funciones::GetSQLValueString("Cotizacion Permiso Provisional 30 Dias", "varchar"), //Origen
                        Funciones::GetSQLValueString(NULL, "int unsigned"), //tipoBase
                        Funciones::GetSQLValueString($BaseCalculo, "decimal"), //MontoBase
                        Funciones::GetSQLValueString($IdUltimoPermiso, "int") ); //idLicencia                  
                }
                $ConsultaInserta=substr_replace($ConsultaInserta,";",-1);
                if(DB::insert($ConsultaInserta)){
                    //AGREGO TODOS LOS DATOS A LA CONTABILIDAD
                    $DatosEncabezado=Funciones::ObtenValor("SELECT N_umeroP_oliza FROM EncabezadoContabilidad WHERE Cotizaci_on=".$IdRegistroCotizaci_on,"N_umeroP_oliza");
                    $Programa=Funciones::ObtenValor("SELECT Programa FROM PresupuestoAnualPrograma WHERE id=".$PresupuestoAnualPrograma, "Programa");
                    if($DatosEncabezado=="NULL" || $DatosEncabezado==""){
                        $UltimaPoliza=Funciones::ObtenValor("SELECT N_umeroP_oliza as Ultimo FROM EncabezadoContabilidad WHERE N_umeroP_oliza like '".$anio.$clienteClave.str_pad($Programa, 3, '0', STR_PAD_LEFT)."03%' order by N_umeroP_oliza desc limit 0,1","Ultimo");
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
                        if($ResultadoObtieneConceptos = DB::SELECT($ConsultaObtiene)){
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
                                        //$planCuentas = Funciones::ObtenValor();
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
                                            Funciones::GetSQLValueString("Cotizacion Permiso Provisional", "varchar"),
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
                                            Funciones::GetSQLValueString("Cotizacion Permiso Provisional", "varchar"),
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
                                            Funciones::GetSQLValueString("Cotizacion Permiso Provisional", "varchar"),
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
                                            Funciones::GetSQLValueString("Cotizacion Permiso Provisional", "varchar"),
                                            Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );
                                            /************************************************************************************/
                                        //Esto es presupuestal
                                        $ConsultaObtieneMomentoPresupuestal = sprintf("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Abono) as NaturalezaAbono FROM MomentoPresupuestal WHERE Momento=%s AND Cargo IS NOT NULL AND Abono IS NOT NULL ",  Funciones::GetSQLValueString($momento, "int"));
                                        $ResultadoObtieneMomentoPresupuestal = DB::SELECT($ConsultaObtieneMomentoPresupuestal);
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
                                                Funciones::GetSQLValueString("Cotizacion Permiso Provisional", "varchar"),
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
                                                Funciones::GetSQLValueString("Cotizacion Permiso Provisional", "varchar"),
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
                                        Funciones::GetSQLValueString("Cotizacion Permiso Provisional", "varchar"),
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
                                        Funciones::GetSQLValueString("Cotizacion Permiso Provisional", "varchar"),
                                        Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int") );
                                    /************************************************************************************/
                                    //Esto es presupuestal
                                    $ConsultaObtieneMomentoPresupuestal = sprintf("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Abono) as NaturalezaAbono FROM MomentoPresupuestal WHERE Momento=%s AND Cargo IS NOT NULL AND Abono IS NOT NULL ",  Funciones::GetSQLValueString($momento, "int"));
                                    $ResultadoObtieneMomentoPresupuestal = DB::SELECT($ConsultaObtieneMomentoPresupuestal);
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
                                            Funciones::GetSQLValueString("Cotizacion Permiso Provisional", "varchar"),
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
                                            Funciones::GetSQLValueString("Cotizacion Permiso Provisional", "varchar"),
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
                                Funciones::GetSQLValueString( 'Cotizaci_on Permiso Provisional', "varchar"),
                                Funciones::GetSQLValueString( $IdRegistroCotizaci_on, "int"),
                                Funciones::GetSQLValueString( 2, "int"));
                            $ResultadoLog = DB::insert($ConsultaLog);
                        } else {
                            $Error = $Conexion->error;
                        }
                    }	//termina if(DB::insert($ConsultaInsertaContabilidad)){
                    else{ //verifica si no insterto el encabezado contabilidad
                        $Error = $Conexion->error;
                    }
                }//state==0
                return response()->json([
                    'Contribuyente' =>  $Padron -> Contribuyente,
                    'Padr_on'=> $IdUltimoPermiso,
                    'idCotizacion' => $IdRegistroCotizaci_on,
                    'idPermiso' => $Padron -> id
                ], 200);
            }else{
                return response()->json([
                    'Contribuyente' =>  $Padron -> Contribuyente,
                    'Padr_on'=> $IdPermiso,
                    'idCotizacion' => $IdRegistroCotizaci_on,
                    'idPermiso' => $Padron -> id
                ], 200);
                $Status = "Error";
                $Error  = $Conexion->error;
            }
            return response()->json([
                'Contribuyente' =>  $Padron -> Contribuyente,
                'Padr_on'=> $IdPermiso,
                'idCotizacion' => $IdRegistroCotizaci_on,
                'idPermiso' => $Padron -> id
            ], 200);
        } else {
            return response()->json([
                'Contribuyente' =>  $DatosPermiso -> Contribuyente,
                'Padr_on'=> $IdPermiso,
                'idCotizacion' => $IdRegistroCotizaci_on,
                'idPermiso' => $Padron -> id
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