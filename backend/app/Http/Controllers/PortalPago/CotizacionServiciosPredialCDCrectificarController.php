<?php

namespace App\Http\Controllers\PortalPago;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Funciones;
use App\FuncionesCaja;
use Illuminate\Support\Facades\DB;
use \Illuminate\Support\Facades\Config;
class CotizacionServiciosPredialCDCrectificarController extends Controller
{
    //
     /**
     * ! Se asigna el middleware  al costructor
     */
    public function __construct()
    {
        #$this->middleware( 'jwt', ['except' => ['getToken']] );
    }


    public function CotizacionServiciosPredialCDCrectificar(Request $request){
       
        $cliente = $request->Cliente;
        $contribuyente = $request->Contribuyente;
        $concepto=$request->Concepto;
        $idPadron=$request->IdPadron;
        $consulta_usuario = Funciones::ObtenValor("SELECT c.idUsuario, c.Usuario
        FROM CelaUsuario c  INNER JOIN CelaRol c1 ON ( c.Rol = c1.idRol  )   WHERE c.CorreoElectr_onico='" . $cliente . "@gmail.com' ");
        $CajadeCobro = Funciones::ObtenValor("select CajaDeCobro from CelaUsuario where idUsuario=" .$consulta_usuario->idUsuario, "CajaDeCobro");

        Funciones::selecionarBase($cliente);
        $Cliente = Funciones::ObtenValor('select Clave from Cliente where id=' .$cliente, 'Clave');
        $ano=date('Y');
        $momento=4;
        $totalGeneral=0;
        //////

        //////
        $UltimaCotizacion = Funciones::ObtenValor("select FolioCotizaci_on from Cotizaci_on where FolioCotizaci_on like '" . $ano. $Cliente . "%' order by FolioCotizaci_on desc limit 0,1", "FolioCotizaci_on");

        if ($UltimaCotizacion == 'NULL') {
            $N_umeroDeCotizacion = $ano. $Cliente . str_pad(1, 8, '0', STR_PAD_LEFT);
        } else {
            $N_umeroDeCotizacion = $ano. $Cliente . str_pad(intval(substr($UltimaCotizacion, -8, 8)) + 1, 8, '0', STR_PAD_LEFT);
        }
      
$fondoDatos= CotizacionServiciosPredialCDCrectificarController::getFondo($cliente,$ano);
        $presupuestoAnualPrograma=$fondoDatos->Progid;
        $fondo=$fondoDatos->Fondoid;
        $fechaCFDI=date('Y-m-d H:i:s');
        $fecha=date('Y-m-d');
        $medoPago=04;

        $areaRecaudadora=Funciones::getAreaRecaudadora($cliente,$ano,$concepto);

        $FuenteFinanciamiento = Funciones::ObtenValor("SELECT f1.id as FuenteFinanciamiento
            FROM Fondo f
                INNER JOIN Cat_alogoDeFondo ca ON ( f.CatalogoFondo = ca.id  )
                    INNER JOIN FuenteFinanciamiento f1 ON ( ca.FuenteFinanciamiento = f1.id  )
            WHERE f.id = " . $fondo, "FuenteFinanciamiento");



        DB::table('Cotizaci_on')->insert([
             [  'id' => null,
                'FolioCotizaci_on' => $N_umeroDeCotizacion,
                'Contribuyente'=>$contribuyente,
                'AreaAdministrativa'=>$areaRecaudadora,
                'Fecha' => $fecha,
                'Cliente'=>$cliente,
                'Fondo'=>$fondo,
                'Programa' => $presupuestoAnualPrograma,
                'FuenteFinanciamiento'=>$FuenteFinanciamiento,
                'FechaCFDI'=>$fechaCFDI,
                'MetodoDePago' => $medoPago,
                'Padr_on'=>$idPadron,
                'Usuario'=> $consulta_usuario ->idUsuario,
             ]
        ]);
        $idCotizacion = DB::getPdo()->lastInsertId();;

        $CatalogoFondo=Funciones::ObtenValor('select CatalogoFondo FROM Fondo WHERE id='.$fondo,'CatalogoFondo' );
        if(isset($idCotizacion)){
            // $tipoDoc=$ListadoDocumentos;
            // clve cliente + anio + area recaudadora + cpnsecutivo
            $CajadeCobro = Funciones::ObtenValor("select CajaDeCobro from CelaUsuario where idUsuario=" . $consulta_usuario ->idUsuario, "CajaDeCobro");

            $areaRecaudadora = Funciones::ObtenValor("SELECT Clave FROM AreasAdministrativas WHERE id=".$areaRecaudadora, "Clave");

            $UltimoFolio = Funciones::ObtenValor("select Folio from XMLIngreso where Folio like '%" . $Cliente .$ano . $areaRecaudadora . "%' order by Folio desc limit 0,1", "Folio");

            $Serie = $Cliente . $ano . $areaRecaudadora;

            if ($UltimoFolio == 'NULL') {
                $N_umeroDeFolio = str_pad(1, 8, '0', STR_PAD_LEFT);
            } else {
                $N_umeroDeFolio = str_pad(intval(substr($UltimoFolio, -8, 8)) + 1, 8, '0', STR_PAD_LEFT);

            }
            $ConsultaPadr_onCatastral = Funciones::ObtenValor("SELECT * FROM Padr_onCatastral WHERE id =  " . $idPadron);
            $datosPadron = Funciones::ObtenValor("SELECT  COALESCE(SuperficieConstrucci_on,0) SuperficieConstrucci_on, COALESCE(SuperficieTerreno,0) SuperficieTerreno, COALESCE(TipoPredioValores,0) as TipoPredioValores, COALESCE(TipoConstrucci_onValores,0) TipoConstrucci_onValores, TipoConstruci_on FROM Padr_onCatastral pc WHERE pc.id=" . $ConsultaPadr_onCatastral->id);

            $tipoPredioValor = Funciones::ObtenValor("SELECT  COALESCE(tpve.Importe, 0) as Importe FROM TipoPredioValores tpv
            INNER JOIN TipoPredioValoresEjercicioFiscal tpve ON (tpv.id=tpve.idTipoPredioValores AND tpve.EjercicioFiscal=" . $ano . ")
            WHERE tpv.id=" . $datosPadron->TipoPredioValores, "Importe");

            #
            $tipoPredioValor = (($tipoPredioValor == "NULL") ? 0 : floatval($tipoPredioValor));
            $consultaPredio=  Funciones::ObtenValor("SELECT *, FORMAT(ValorCatastral,2) as ValorCatastral, (SELECT sum((Padr_onConstruccionDetalle.SuperficieConstrucci_on)) FROM Padr_onConstruccionDetalle WHERE Padr_onCatastral.id=Padr_onConstruccionDetalle.idPadron) as SuperficieConstrucci_on,  Padr_onCatastral.Colonia as ColoniaPadron   FROM Padr_onCatastral WHERE id=".$idPadron);

            $valorCatastral=number_format(  ( floatval(str_replace(",", "", $ConsultaPadr_onCatastral->SuperficieTerreno) ) * floatval(str_replace(",", "", number_format($tipoPredioValor, 2) ) )  ) * number_format($ConsultaPadr_onCatastral->Indiviso, 6) / 100, 2);
            $SuperficieTerreno= number_format(str_replace(",", "", $ConsultaPadr_onCatastral->SuperficieTerreno), 2);
            $patron = '/[\'"]/';
            $arr['Usuario'] = Funciones::ObtenValor("SELECT NombreCompleto FROM CelaUsuario WHERE idUsuario=".$consulta_usuario ->idUsuario, "NombreCompleto");
            $arr['Leyenda'] = "Elaboró";
            $arr['NumCuenta']=null;
            $arr['idPadron']=$idPadron;
            $arr['Observacion'] = "";
            $arr['Descuento']= null;

            $arr['Cuenta']=$ConsultaPadr_onCatastral->Cuenta;
            $arr['CuentaAnterior']=$ConsultaPadr_onCatastral->CuentaAnterior ;
            $arr['Localidad']=$consultaPredio->Localidad;
            $arr['ValorCatastral']=$consultaPredio->ValorCatastral ;
            $arr['SuperficieTerreno']=$consultaPredio->SuperficieTerreno ;
            $arr['ValorPredio']="" ;
            $arr['SuperficieConstruccion']=$consultaPredio->SuperficieConstrucci_on;
            $arr['ValorConstruccion']="" ;
            $arr['Ubicacion']=preg_replace($patron, '',$consultaPredio->Ubicaci_on." ".$consultaPredio->ColoniaPadron);
            $arr['TipoCotizacionPredial']="Servicio";
            $DatosExtra = json_encode($arr, JSON_UNESCAPED_UNICODE);


            DB::table('XMLIngreso')->insert([
                [  'Contribuyente'=>$contribuyente,
                   'idCotizaci_on'=>$idCotizacion,
                   'Folio' => $Serie.$N_umeroDeFolio,
                   'MetodoDePago'=>$medoPago,
                   'DatosExtra'=>$DatosExtra,
                ]
           ]);
           $elXMLIngreso = DB::getPdo()->lastInsertId();

            $mes = explode("-",$fecha);

            $tipoBaseCalculo=Funciones::ObtenValor("SELECT  c3.BaseCalculo as TipobaseCalculo
            FROM ConceptoCobroCaja c
            INNER JOIN ConceptoRetencionesAdicionales c1 ON ( c.id = c1.Concepto  )
            INNER JOIN ConceptoRetencionesAdicionalesCliente c2 ON ( c1.id = c2.ConceptoRetencionesAdicionales  )
            INNER JOIN ConceptoAdicionales c3 ON ( c2.id = c3.ConceptoRetencionesAdicionalesCliente  )
            WHERE c3.Cliente=".$cliente." AND c3.EjercicioFiscal=".$ano." AND  c2.Cliente=".$cliente." AND c.id = ".$concepto,"TipobaseCalculo");

           $ObtieneImporteyConceptos= CotizacionServiciosPredialCDCrectificarController::ObtieneImporteyConceptos($cliente,$ano,$concepto, 0, 1);
           $totalPagar=$ObtieneImporteyConceptos['punitario'];
		   if($tipoBaseCalculo==1 || $tipoBaseCalculo==3){
				$cantidadConcepto=1;
			}else{
				$cantidadConcepto=$ObtieneImporteyConceptos['baseCalculo'];
            }
            $totalGeneral+=$totalPagar;
			DB::table('ConceptoAdicionalesCotizaci_on')->insert([
                [  'id' => null,
                   'ConceptoAdicionales'=>$concepto,
                   'Cotizaci_on'=>$idCotizacion,
                   'TipoContribuci_on' =>1,// 1 para concepto o 2 para retencion adicional
                   'Importe'=>str_replace(',', '',$ObtieneImporteyConceptos['punitario']),//importe nota si no pongo el cero y es 50.6 no lo reconoce la base tiene que ser 50.60
                   'Estatus'=>0,//importe
                   'MomentoCotizaci_on'=>4,//4 es ley de ingresos devengada
                   'MomentoPago'=>null, //5 es para cuando se paga
                   'FechaPago' => null,//fecha de cobro
                   'CajaDeCobro'=>$CajadeCobro,//caja de cobro
                   'Xml'=>$elXMLIngreso,//el XML
                   'TipoCobroCaja'=>null,//TipoCobroCaja
                   'N_umeroDeOperaci_on'=>null,//numero de operacion
                   'CuentaBancaria' =>null ,// cuenta bancaria
                   'Adicional'=>null,//adicional
                   'Cantidad'=>$cantidadConcepto,//Cantidad de Conceptos ***** Aqui esta el detalle chato *****.
                   //Se modifico esta linea cuando se detecto que se generaban actualizaciones y recargos -> 12 mayo 2019
                   'Mes'=>null,
                   'A_no'=>$ano,
                   'TipoBase' => null,
                   'MontoBase'=>$ObtieneImporteyConceptos['baseCalculo'],
                   'Origen'=>"Cotizacion Servicios",
                ]
            ]);




			//obtengo los adicionales del concepto actual
			$datosAdicionales = CotizacionServiciosPredialCDCrectificarController::ObtieneImporteyConceptos2($cliente,$ano,$concepto,$tipoBaseCalculo);

			//echo "<pre>".print_r($datosAdicionales, true)."</pre>";

			//agrego a la consulta los adicionales
			for ($k = 1; $k <= $datosAdicionales['NumAdicionales']; $k++) {

				$tt=  str_replace(',', '',$datosAdicionales['adicionales'.$k]['Resultado']);
                //cantidad
                $totalGeneral+=str_replace(',', '',$datosAdicionales['adicionales'.$k]['Resultado']);
                DB::table('ConceptoAdicionalesCotizaci_on')->insert([
                    [  'id' => null,
                       'ConceptoAdicionales'=>$concepto,
                       'Cotizaci_on'=>$idCotizacion,
                       'TipoContribuci_on' =>2,// 1 para concepto o 2 para retencion adicional
                       'Importe'=>str_replace(',', '',$datosAdicionales['adicionales'.$k]['Resultado']), //importe
                       'Estatus'=>0,//0 para no pagado  1 para pagado
                       'MomentoCotizaci_on'=>4,//4 es ley de ingresos devengada
                       'MomentoPago'=>null, //5 es para cuando se paga
                       'FechaPago' => null,//fecha de cobro
                       'CajaDeCobro'=>$CajadeCobro,//caja de cobro
                       'Xml'=>$elXMLIngreso,//el XML
                       'TipoCobroCaja'=>null,//TipoCobroCaja
                       'N_umeroDeOperaci_on'=>null,//numero de operacion
                       'CuentaBancaria' =>null ,// cuenta bancaria
                       'Adicional'=>$datosAdicionales['adicionales' . $k]['idAdicional'],//adicional
                       'Cantidad'=>$cantidadConcepto,//Cantidad de Conceptos ***** Aqui esta el detalle chato *****.
                       //Se modifico esta linea cuando se detecto que se generaban actualizaciones y recargos -> 12 mayo 2019
                       'Mes'=>null,
                       'A_no'=>$ano,
                       'TipoBase' => $datosAdicionales['adicionales' . $k]['TipoBase'],
                       'MontoBase'=>$datosAdicionales['adicionales' . $k]['MontoBase'],
                       'Origen'=>"Cotizacion Servicios",
                    ]
                ]);
            }

            $FuenteFinanciamiento = Funciones::ObtenValor("SELECT f1.id as FuenteFinanciamiento
            FROM Fondo f
                INNER JOIN Cat_alogoDeFondo ca ON ( f.CatalogoFondo = ca.id  )
                    INNER JOIN FuenteFinanciamiento f1 ON ( ca.FuenteFinanciamiento = f1.id  )
            WHERE f.id = " .$fondo, "FuenteFinanciamiento");

            $CatalogoFondo=Funciones::ObtenValor('select CatalogoFondo FROM Fondo WHERE id='.$fondo,'CatalogoFondo' );

            //AGREGO TODOS LOS DATOS A LA CONTABILIDAD
            $DatosEncabezado = Funciones::ObtenValor("select N_umeroP_oliza from EncabezadoContabilidad where Cotizaci_on=" . $idCotizacion, "N_umeroP_oliza");
            $Programa = Funciones::ObtenValor("SELECT Programa FROM PresupuestoAnualPrograma WHERE id=" . $presupuestoAnualPrograma, "Programa");

            if ($DatosEncabezado=="") {
                $UltimaPoliza = Funciones::ObtenValor("select N_umeroP_oliza as Ultimo from EncabezadoContabilidad where N_umeroP_oliza like '" . $ano . $Cliente . str_pad($Programa, 3, '0', STR_PAD_LEFT) . "03%' order by N_umeroP_oliza desc limit 0,1", "Ultimo");
                if ($UltimaPoliza == 'NULL')
                    $N_umeroDePoliza = $ano. $Cliente . str_pad($Programa, 3, '0', STR_PAD_LEFT) . "03" . str_pad(1, 6, '0', STR_PAD_LEFT);
                else
                    $N_umeroDePoliza = $ano . $Cliente . str_pad($Programa, 3, '0', STR_PAD_LEFT) . "01" . str_pad(intval(substr($UltimaPoliza, -6, 6)) + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $N_umeroDePoliza = $DatosEncabezado;
            }

            //cantidad
            DB::table('EncabezadoContabilidad')->insert([
                [  'id' => null,
                   'Cliente'=>$cliente,
                   'EjercicioFiscal'=>$ano,
                   'TipoP_oliza' =>1,// 1 ingresos   2 egresos  3 diario
                   'N_umeroP_oliza'=>$N_umeroDePoliza, //numero de poliza
                   'FechaP_oliza'=>$fecha,//fecha de poliza
                   'Concepto'=>"Cotizacion #" . $N_umeroDeCotizacion,//descripcion o concepto
                   'Cotizaci_on'=>$idCotizacion, //
                   'AreaRecaudadora' => $areaRecaudadora,//
                   'FuenteFinanciamiento'=> $FuenteFinanciamiento ,//
                   'Fondo'=>$fondo,//
                   'CatalogoFondo'=>$CatalogoFondo,//
                   'Programa'=>$Programa,//
                   'idPrograma' =>$presupuestoAnualPrograma ,//
                   'Proyecto'=>null,//adicional
                   'Clasificaci_onProgram_atica'=>null,
                   'Clasificaci_onFuncional'=>null,
                   'Clasificaci_onAdministrativa'=>null,
                   'Contribuyente' => $contribuyente,
                   'Persona'=>null,
                   'CuentaBancaria'=>null,
                   'MovimientoBancario'=>null,//
                   'N_umeroDeMovimientoBancario' =>null ,//
                   'Momento'=>$momento,//
                   'EstatusTupla'=>1,//Status 1 activo   y 0 cancelado
                   'FechaTupla'=>date('Y-m-d H:i:s'),
                   'AreaAdministrativaProyecto'=>null,
                   'Proveedor' => null,
                   'CuentaPorPagar'=>null,

                ]
            ]);

            $Status = true;
            $IdEncabezadoContabilidad = DB::getPdo()->lastInsertId();

            if(isset($IdEncabezadoContabilidad ) && $IdEncabezadoContabilidad>0){

                $ConsultaInsertaDetalleContabilidadC = "INSERT INTO DetalleContabilidad
                ( id, EncabezadoContabilidad, TipoP_oliza, FehaP_oliza, N_umeroP_oliza, ConceptoMovimientoContable, Cotizaci_on, AreaRecaudaci_on, FuenteFinanciamiento, Programa, idPrograma, Proyecto, AreaAdministrativaProyecto
                , Clasificaci_onProgram_atica, Clasificaci_onFuncionalGasto, Clasificaci_onAdministrativa, Clasificaci_onEcon_omicaIGF, Contribuyente, Proveedor, CuentaBancaria, MovimientoBancario
                , N_umeroMovimientoBancario, uuid, EstatusConcepto, ConceptoDeXML, MomentoContable, CRI, COG, TipoDeMovimientoContable, Importe, EstatusInventario, idDeLaObra, Persona, TipoDeGasto
                , TipoCobroCaja, PlanDeCuentas, NaturalezaCuenta, TipoBien, TipoObra, EstatusTupla, Fondo, CatalogoFondo, FechaTupla, Origen,ConceptoCobroCajaId) VALUES ";

                //Obtiene conceptos y adicioanles
                $ConsultaObtiene = "SELECT c3.id as ConceptoCobroCajaID,co.id, co.Importe, co.MomentoCotizaci_on, co.Xml, c3.CRI, co.Adicional, (SELECT Cri FROM RetencionesAdicionales WHERE co.Adicional= RetencionesAdicionales.id) as CriBueno, (SELECT PlanDeCuentas FROM RetencionesAdicionales WHERE co.Adicional= RetencionesAdicionales.id) as PlanCuentasBueno, (SELECT Abono FROM MomentoContable WHERE Momento=".$momento." AND MomentoContable.CRI=c3.CRI ) as AbonoSegunPlan,  (SELECT Naturaleza FROM PlanCuentas WHERE  PlanCuentas.id= AbonoSegunPlan ) as NaturalezaSegunPlan, (SELECT Naturaleza FROM PlanCuentas WHERE  PlanCuentas.id= PlanCuentasBueno ) as NaturalezaPlanCuentasBueno
                ,co.A_no as A_no
                FROM ConceptoAdicionalesCotizaci_on co
                                INNER JOIN ConceptoCobroCaja c3 ON ( co.ConceptoAdicionales = c3.id  )
                WHERE co.Cotizaci_on =" . $idCotizacion;

                if ($ResultadoObtieneConceptos = DB::select($ConsultaObtiene)) {
                         foreach( $ResultadoObtieneConceptos as $registroConceptos ) {

                            if(!is_null($registroConceptos->Adicional)){
                                $registroConceptos->ConceptoCobroCajaID= Funciones::ObtenValor("SELECT ra.ConceptoCobro FROM RetencionesAdicionales ra WHERE ra.id=".$registroConceptos->Adicional,"ConceptoCobro");
                            }

                            //aqui agrego datos a Asignaci_onPresupuestal
                            $ConsultaInserta = sprintf("INSERT INTO Asignaci_onPresupuestal (  id , Cri , ImporteAnual , PresupuestoAnualPrograma , FechaInicial , FechaFinal , EstatusTupla, AreaAdministrativa,  ImporteModificado, ImporteDevengado, Concepto )
                            VALUES (  %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
                            Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($registroConceptos->CRI, "int"), Funciones::GetSQLValueString(0, "decimal"), Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"), Funciones::GetSQLValueString(date('Y-m-d'), "date"), Funciones::GetSQLValueString(date('Y-m-d'), "date"), Funciones::GetSQLValueString(1, "tinyint"), Funciones::GetSQLValueString($areaRecaudadora, "int"), Funciones::GetSQLValueString(NULL, "decimal"), Funciones::GetSQLValueString($registroConceptos->Importe, "decimal"), Funciones::GetSQLValueString($registroConceptos->id, "int"));


                           if(DB::insert($ConsultaInserta)){
                                $IdRegistroAsignaci_onPresupuestal = DB::getPdo()->lastInsertId();
                           }
                           $momento = 4;

                           if($registroConceptos->A_no!=$ano) {
                               $momento = 13;
                           }


                            $DatosMomentoContable = Funciones::ObtenValor("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Abono) as NaturalezaAbono FROM  MomentoContable WHERE Momento=" . $registroConceptos->MomentoCotizaci_on . " AND Cri=" . $registroConceptos->CRI);
                            //AQUGO LOS DATOS DE RetencionesAdicionales

                            //REGLA: si el adicional es retencion se duplica el abono
                            //verifico que sea un adicional=I AGRE
                            if(!is_null($registroConceptos->Adicional)){
                                    //es un adicional

                                    $tieneCri=Funciones::ObtenValor("SELECT * FROM RetencionesAdicionales WHERE id=".$registroConceptos->Adicional);
                                    if(is_null($tieneCri->Cri)){
                                        //es adicional con plan de cuentas
                                        //quiere decir que no tiene CRI, trae plan de cuentas
                                    $DatosMomentoContable = Funciones::ObtenValor("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Abono) as NaturalezaAbono FROM  MomentoContable WHERE Momento=" . $registroConceptos->MomentoCotizaci_on. " AND Cri=" . $registroConceptos->CRI);
                                        //$planCuentas=ObtenValor();
                                        /**/
                                            //Para Abono 1 negativo
                                    $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                    Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                    Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                    Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                    Funciones::GetSQLValueString("AA1PC Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                    Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                    Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                    Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                    Funciones::GetSQLValueString($Programa, "int"), //programa
                                    Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                    Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                    Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                    Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                    Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                    Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                    Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                    Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                    Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                    Funciones::GetSQLValueString(null, "varchar"), //uuid
                                    Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                    Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                    Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                    Funciones::GetSQLValueString($fondo, "int"), //fondo
                                    Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                    Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                    Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                    Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));
                                    /* */
                                    //Para Abono 2 positivo
                                    $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                    Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                    Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                    Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                    Funciones::GetSQLValueString("AA2PC Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                    Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                    Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                    Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                    Funciones::GetSQLValueString($Programa, "int"), //programa
                                    Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                    Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                    Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                    Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                    Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                    Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                    Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                    Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                    Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                    Funciones::GetSQLValueString(null, "varchar"), //uuid
                                    Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                    Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                    Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                    Funciones::GetSQLValueString($fondo, "int"), //fondo
                                    Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                    Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                    Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                    Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));



                                    }else{
                                    //es adicional con cri
                                    $DatosMomentoContable = Funciones::ObtenValor("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Abono) as NaturalezaAbono FROM  MomentoContable WHERE Momento=" . $registroConceptos->MomentoCotizaci_on. " AND Cri=" . $registroConceptos->CriBueno);

                                    //Para cargo
                                    $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                    Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                    Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                    Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                    Funciones::GetSQLValueString("ACC Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                    Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                    Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                    Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                    Funciones::GetSQLValueString($Programa, "int"), //programa
                                    Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                    Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                    Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                    Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                    Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                    Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                    Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                    Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                    Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                    Funciones::GetSQLValueString(null, "varchar"), //uuid
                                    Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                    Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                    Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                    Funciones::GetSQLValueString($fondo, "int"), //fondo
                                    Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                    Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                    Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                    Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));

                                    //Para Abono
                                    $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                    Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                    Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                    Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                    Funciones::GetSQLValueString("ACA Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                    Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                    Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                    Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                    Funciones::GetSQLValueString($Programa, "int"), //programa
                                    Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                    Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                    Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                    Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                    Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                    Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                    Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                    Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                    Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                    Funciones::GetSQLValueString(null, "varchar"), //uuid
                                    Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                    Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                    Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                    Funciones::GetSQLValueString($fondo, "int"), //fondo
                                    Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                    Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                    Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                    Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));

                                    /************************************************************************************/
                                    //Esto es presupuestal
                                    $ConsultaObtieneMomentoPresupuestal = sprintf("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Abono) as NaturalezaAbono FROM MomentoPresupuestal WHERE Momento=%s AND Cargo IS NOT NULL AND Abono IS NOT NULL ", Funciones::GetSQLValueString($momento, "int"));
                                    $ResultadoObtieneMomentoPresupuestal = DB::select($ConsultaObtieneMomentoPresupuestal);

                                    foreach ($ResultadoObtieneMomentoPresupuestal as $RegistroObtieneMomentoPresupuestal) {
                                        //Para cargo en presupuestal
                                        $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                        Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                        Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                        Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                        Funciones::GetSQLValueString("---Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                        Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                        Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                        Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                        Funciones::GetSQLValueString($Programa, "int"), //programa
                                        Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                        Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                        Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                        Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                        Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                        Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                        Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                        Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                        Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                        Funciones::GetSQLValueString(null, "varchar"), //uuid
                                        Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                        Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                        Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                        Funciones::GetSQLValueString($fondo, "int"), //fondo
                                        Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                        Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                        Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                        Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));

                                        //Para abono en presupuestal
                                       $ConsultaInsertaDetalleContabilidadC .= sprintf("(%s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                        Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                        Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                        Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                        Funciones::GetSQLValueString("---Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                        Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                        Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                        Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                        Funciones::GetSQLValueString($Programa, "int"), //programa
                                        Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                        Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                        Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                        Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                        Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                        Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                        Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                        Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                        Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                        Funciones::GetSQLValueString(null, "varchar"), //uuid
                                        Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                        Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                        Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                        Funciones::GetSQLValueString($fondo, "int"), //fondo
                                        Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                        Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                        Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                        Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));

                                    }//while($RegistroObtieneMomentoPresupuestal = $ResultadoObtieneMomentoPresupuestal->fetch_assoc()){
                                        //TERMINA PRESUPUESTAL

                            }//

                         }//termina if para saber si es adicional o concepto
                            else{
                                //es concepto
                                //
                                    $DatosMomentoContable = Funciones::ObtenValor("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoContable.Abono) as NaturalezaAbono FROM  MomentoContable WHERE Momento=" . $registroConceptos->MomentoCotizaci_on . " AND Cri=" . $registroConceptos->CRI);

                                    //Para cargo
                                    $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                    Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                    Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                    Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                    Funciones::GetSQLValueString("ConceptoC - Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                    Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                    Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                    Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                    Funciones::GetSQLValueString($Programa, "int"), //programa
                                    Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                    Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                    Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                    Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                    Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                    Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                    Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                    Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                    Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                    Funciones::GetSQLValueString(null, "varchar"), //uuid
                                    Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                    Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                    Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                    Funciones::GetSQLValueString($fondo, "int"), //fondo
                                    Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                    Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                    Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                    Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));

                                    //Para Abono
                                    $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                    Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                    Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                    Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                    Funciones::GetSQLValueString("ConceptoA - Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                    Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                    Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                    Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                    Funciones::GetSQLValueString($Programa, "int"), //programa
                                    Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                    Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                    Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                    Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                    Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                    Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                    Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                    Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                    Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                    Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                    Funciones::GetSQLValueString(null, "varchar"), //uuid
                                    Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                    Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                    Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                    Funciones::GetSQLValueString($fondo, "int"), //fondo
                                    Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                    Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                    Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                    Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));


                                    /************************************************************************************/
                                    //Esto es presupuestal
                                    $ConsultaObtieneMomentoPresupuestal = sprintf("SELECT *, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Cargo) as NaturalezaCargo, (SELECT Naturaleza FROM PlanCuentas WHERE PlanCuentas.id=MomentoPresupuestal.Abono) as NaturalezaAbono FROM MomentoPresupuestal WHERE Momento=%s AND Cargo IS NOT NULL AND Abono IS NOT NULL ", Funciones::GetSQLValueString($momento, "int"));
                                    $ResultadoObtieneMomentoPresupuestal =DB::select($ConsultaObtieneMomentoPresupuestal);

                                    foreach ( $ResultadoObtieneMomentoPresupuestal as $RegistroObtieneMomentoPresupuestal) {
                                        //Para cargo en presupuestal
                                        $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                        Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                        Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                        Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                        Funciones::GetSQLValueString("---Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                        Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                        Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                        Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                        Funciones::GetSQLValueString($Programa, "int"), //programa
                                        Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                        Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                        Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                        Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                        Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                        Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                        Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                        Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                        Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                        Funciones::GetSQLValueString(null, "varchar"), //uuid
                                        Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                        Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                        Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                        Funciones::GetSQLValueString($fondo, "int"), //fondo
                                        Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                        Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                        Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                        Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));

                                        //Para abono en presupuestal
                                        $ConsultaInsertaDetalleContabilidadC .= sprintf("( %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ),", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($IdEncabezadoContabilidad, "int"), //Encabezado contabilidad
                                        Funciones::GetSQLValueString(1, "int"), //ingreso  Tipo de poliza
                                        Funciones::GetSQLValueString($fecha, "datetime"), //Fecha de poliza
                                        Funciones::GetSQLValueString($N_umeroDePoliza, "varchar"), //Numero de poliza
                                        Funciones::GetSQLValueString("---Cotizacion #" . $N_umeroDeCotizacion, "varchar"), //Concepto movimiento contable
                                        Funciones::GetSQLValueString($idCotizacion, "int"), //Cotizacion
                                        Funciones::GetSQLValueString($areaRecaudadora, "int"), //Area de recaudacion
                                        Funciones::GetSQLValueString($FuenteFinanciamiento, "int"), //Fuente de financiamiento
                                        Funciones::GetSQLValueString($Programa, "int"), //programa
                                        Funciones::GetSQLValueString($presupuestoAnualPrograma, "int"),//idPrograma
                                        Funciones::GetSQLValueString(NULL, "int"), //Proyecto
                                        Funciones::GetSQLValueString(NULL, "int"), //area administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //clasificacion programatica
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificacion funcional
                                        Funciones::GetSQLValueString(NULL, "int"), //calsificaicon administrativa
                                        Funciones::GetSQLValueString(NULL, "int"), //Clasificaci_onEcon_omicaIGF No se sabe apra que
                                        Funciones::GetSQLValueString($contribuyente, "int"), //contribuyente
                                        Funciones::GetSQLValueString(NULL, "int"), //Proveedor
                                        Funciones::GetSQLValueString(NULL, "int"), //cta bancaria
                                        Funciones::GetSQLValueString(NULL, "int"), //mov bancario
                                        Funciones::GetSQLValueString(NULL, "int"), // #mov bancario
                                        Funciones::GetSQLValueString(null, "varchar"), //uuid
                                        Funciones::GetSQLValueString(1, "int"), //estatus concepto
                                        Funciones::GetSQLValueString($registroConceptos->id, "int"), //ConceptoXML
                                        Funciones::GetSQLValueString($registroConceptos->MomentoCotizaci_on, "int"), //4 Ley de ingreso devengado
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
                                        Funciones::GetSQLValueString($fondo, "int"), //fondo
                                        Funciones::GetSQLValueString($CatalogoFondo, "int"), //catalogofondo
                                        Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "datetime"),
                                        Funciones::GetSQLValueString("Cotizacion de servicios", "varchar"),
                                        Funciones::GetSQLValueString($registroConceptos->ConceptoCobroCajaID, "int"));

                                    }//while($RegistroObtieneMomentoPresupuestal = $ResultadoObtieneMomentoPresupuestal->fetch_assoc()){
                                        //TERMINA PRESUPUESTAL
                                        /************************************************/
                                }//termina else de si es adicional o concepto


                        }//while

                }//Obtiene conceptos
                            $ConsultaInsertaDetalleContabilidadC = substr_replace($ConsultaInsertaDetalleContabilidadC, ";", -1);

                            //print $ConsultaInsertaDetalleContabilidadC;

                            //echo  "<pre>".$ConsultaInsertaDetalleContabilidadC."</pre>";
                            if ($ResultadoInsertaDetalleContabilidadC =DB::insert($ConsultaInsertaDetalleContabilidadC)) {
                                $ConsultaLog = sprintf("INSERT INTO CelaAccesos ( idAcceso, FechaDeAcceso, idUsuario, Tabla, IdTabla, Acci_on ) VALUES ( %s, %s, %s, %s, %s, %s)", Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "varchar"), Funciones::GetSQLValueString($consulta_usuario ->idUsuario, "int"), Funciones::GetSQLValueString('Cotizaci_on', "varchar"), Funciones::GetSQLValueString($idCotizacion, "int"), Funciones::GetSQLValueString(2, "int"));
                                $ResultadoLog = DB::insert($ConsultaLog);

                                if (isset($_POST['ConceptoAdicionalesCotizaci_onInsertBack']) && $_POST['ConceptoAdicionalesCotizaci_onInsertBack'] == "ConceptoAdicionalesCotizaci_onInsertBack") {
                                    $Status = "Success";
                                } else {
                                    //$InsertGoTo = "ConceptoAdicionalesCotizaci_onLeer.php?".EncodeThis("Status=SuccessC");
                                    //header(sprintf("Location: %s", $InsertGoTo));

                                                                //Se guarda el registro en la tabla de tramites de isai en caso de que sea tramite por ISAI
                                                                if( $concepto==697 || $concepto==4328) {
                                                                    $Consulta="UPDATE Padr_onCatastral SET TrasladoDominio=1 WHERE id=".$idPadron;
                                                                    DB::update($Consulta);
                                                                        $ConsultaInserta = sprintf("INSERT INTO Padr_onCatastralTramitesISAINotarios ( `id` , `IdPadron`, `Estatus`, `IdUsuario`,  `IdCotizacionForma3` ) VALUES (%s, %s, %s, %s, %s)",
                                                                        Funciones::GetSQLValueString(NULL, "int unsigned"),
                                                                        Funciones::GetSQLValueString($idPadron, "varchar"),
                                                                        Funciones::GetSQLValueString(1, "varchar"),
                                                                        Funciones::GetSQLValueString($consulta_usuario ->idUsuario, "int"),
                                                                        Funciones::GetSQLValueString($idCotizacion, "int"));
                                                                        if(DB::insert($ConsultaInserta)){
                                                                            $idTramite =  DB::getPdo()->lastInsertId();
                                                                            $CatalogoDocumntosISAI = DB::select("SELECT * FROM TipoDocumentoTramiteISAI WHERE Requerido=1");
                                                                            foreach ($CatalogoDocumntosISAI as $DocCatalogo){

                                                                                $ConsultaInserta = sprintf("INSERT INTO Padr_onCatastralTramitesISAINotariosDocumentos (Id, IdTramite, IdTipoDocumento, Origen, ControlVersion) VALUES ( %s, %s, %s, %s, %s )",
                                                                                Funciones::GetSQLValueString( NULL, "int"),
                                                                                Funciones::GetSQLValueString($idTramite, "int"),
                                                                                Funciones::GetSQLValueString($DocCatalogo->Id, "int"),
                                                                                Funciones::GetSQLValueString($DocCatalogo->Origen, "int"),
                                                                                Funciones::GetSQLValueString(1, "int") );
                                                                                DB::insert($ConsultaInserta);



                                                                            }

                                                                        }

                                                                }

                                                                if( isset($_POST['ServicioDU']) && $_POST['ServicioDU']=='ServicioDU') {
                                                                    $ConsultaActualiza = sprintf("UPDATE Padr_onCatastral SET
                                                                    `UsoActual`=%s,
                                                                    `Frente`=%s,
                                                                    `NumeroOficial`=%s
                                                                     WHERE id = %s",
                                                                    Funciones::GetSQLValueString($_POST['UsoActual'], "varchar"),
                                                                    Funciones::GetSQLValueString($_POST['Frente'], "decimal"),
                                                                    Funciones::GetSQLValueString($_POST['NumeroOficial'], "varchar"),
                                                                    Funciones::GetSQLValueString($_POST['idPadron'], "int unsigned")  );
                                                                    if($ResultadoInserta = $Conexion->query($ConsultaActualiza)){

                                                                    }else{
                                                                        precode($Conexion->error,1,1);
                                                                    }
                                                                }

                                  //  header(sprintf("Location: %s", "Cotizaci_onVistaPrevia.php?" . EncodeThis("clave=" . $IdRegistroCotizaci_on."&TipoDocumento=Servicio")));
                                }
                                return response()->json([
                                    'idCotizacion' => $idCotizacion,
                                    'idXML'=>$elXMLIngreso,
                                    'IdEncabezadoContabilidad'=> $IdEncabezadoContabilidad,
                                    'Asignaci_onPresupuestal'=>$IdRegistroAsignaci_onPresupuestal,
                                    'Total'=>$totalGeneral

                                    ], 200);
                      } else {
                                $Error = $Conexion -> error;
                       }


            }


            return response()->json([
                'idCotizacion' => $idCotizacion,
                'CajadeCobro'=>$elXMLIngreso

                ], 200);

        }

    }

    public function obtenerContribuyente(Request $request){

        $cliente = $request->Cliente;
        $idServicio=$request->IdServicio;

        Funciones::selecionarBase($cliente);
        $Contribuyente = Funciones::ObtenValor('SELECT Contribuyente from Padr_onCatastral  WHERE id=' .$idServicio, 'Contribuyente');
dd( $Contribuyente);
        return response()->json([
            'Contribuyente' => $Contribuyente,

        ], 200);

    }

    public function obtenerContribuyenteCopia(Request $request){

        $cliente = $request->Cliente;
        $idServicio=$request->IdServicio;

        Funciones::selecionarBase($cliente);
        $Contribuyente = Funciones::ObtenValor('SELECT Contribuyente from Padr_onCatastral  WHERE id=' .$idServicio, 'Contribuyente');

        return response()->json([
            'Contribuyente' => $Contribuyente,

        ], 200);

        $result = Funciones::respondWithToken($Contribuyente);

        return $result;

    }


    public function calcularTotalCotizacionAnterior(Request $request){
        $cliente = $request->Cliente;
        $concepto=$request->Concepto;
        $ano=date('Y');
        Funciones::selecionarBase($cliente);

        $tipoBaseCalculo=Funciones::ObtenValor("SELECT  c3.BaseCalculo as TipobaseCalculo
        FROM ConceptoCobroCaja c
        INNER JOIN ConceptoRetencionesAdicionales c1 ON ( c.id = c1.Concepto  )
        INNER JOIN ConceptoRetencionesAdicionalesCliente c2 ON ( c1.id = c2.ConceptoRetencionesAdicionales  )
        INNER JOIN ConceptoAdicionales c3 ON ( c2.id = c3.ConceptoRetencionesAdicionalesCliente  )
        WHERE c3.Cliente=".$cliente." AND c3.EjercicioFiscal=".$ano." AND  c2.Cliente=".$cliente." AND c.id = ".$concepto,"TipobaseCalculo");

        $ObtieneImporteyConceptos= CotizacionServiciosPredialCDCrectificarController::ObtieneImporteyConceptos($cliente,$ano,$concepto, 0, 1);
        $totalGeneral=$ObtieneImporteyConceptos['punitario'];
        //obtengo los adicionales del concepto actual
        $datosAdicionales = CotizacionServiciosPredialCDCrectificarController::ObtieneImporteyConceptos2($cliente,$ano,$concepto,$tipoBaseCalculo);

        //echo "<pre>".print_r($datosAdicionales, true)."</pre>";

        //agrego a la consulta los adicionales
        for ($k = 1; $k <= $datosAdicionales['NumAdicionales']; $k++) {

            $totalGeneral+=str_replace(',', '',$datosAdicionales['adicionales'.$k]['Resultado']);

        }
        return response()->json([
        'success' => 1,
        'Total'=>$totalGeneral
            ], 200);


        }



    public function calcularTotalCotizacion(Request $request){
        $cliente = $request->Cliente;
        $concepto=$request->Concepto;
        $ano=date('Y');
        Funciones::selecionarBase($cliente);
        $padron=$request->Padron;


        $tipoBaseCalculo=Funciones::ObtenValor("SELECT  c3.BaseCalculo as TipobaseCalculo
        FROM ConceptoCobroCaja c
        INNER JOIN ConceptoRetencionesAdicionales c1 ON ( c.id = c1.Concepto  )
        INNER JOIN ConceptoRetencionesAdicionalesCliente c2 ON ( c1.id = c2.ConceptoRetencionesAdicionales  )
        INNER JOIN ConceptoAdicionales c3 ON ( c2.id = c3.ConceptoRetencionesAdicionalesCliente  )
        WHERE c3.Cliente=".$cliente." AND c3.EjercicioFiscal=".$ano." AND  c2.Cliente=".$cliente." AND c.id = ".$concepto,"TipobaseCalculo");

        $ObtieneImporteyConceptos= CotizacionServiciosPredialCDCrectificarController::ObtieneImporteyConceptos($cliente,$ano,$concepto, 0, 1);
        $totalGeneral=$ObtieneImporteyConceptos['punitario'];
        //obtengo los adicionales del concepto actual
        $datosAdicionales = CotizacionServiciosPredialCDCrectificarController::ObtieneImporteyConceptos2($cliente,$ano,$concepto,$tipoBaseCalculo);

        //echo "<pre>".print_r($datosAdicionales, true)."</pre>";

        //agrego a la consulta los adicionales
        for ($k = 1; $k <= $datosAdicionales['NumAdicionales']; $k++) {

            $totalGeneral+=str_replace(',', '',$datosAdicionales['adicionales'.$k]['Resultado']);

        }

        $descuento=0;
        //if($cliente==20){

        //}else{
          //  $descuento=self::descuentoSaldoDisponible($padron);
        //}


        $totalSinDescuento=$totalGeneral;

        if($descuento<$totalGeneral){
            $totalGeneral=$totalGeneral-$descuento;
        }else{
            $totalGeneral=0;
        }
        return response()->json([
            'success' => 1,
            'Total'=>$totalGeneral,
            'SaldoDisponible' => $descuento,
            'TotalGeneral' => $totalSinDescuento,
        ], 200);


    }
        //obtener descuento por saldo disponible

    public static function descuentoSaldoDisponible($padron){

        if(isset($padron) && $padron!=""){

            $saldoDisponible=Funciones::ObtenValor("SELECT SaldoNuevo as DescuentoSaldo FROM Padr_onAguaHistoricoAbono  WHERE idPadron=".$padron." order by id desc","DescuentoSaldo");

            if($saldoDisponible<=0){
                $saldoDisponible=0;
            }
        }else{
            $saldoDisponible = 0;
        }

        return $saldoDisponible;
    }


    public static function getFondo($cliente,$ano){

            //obtengo los datos por default
            $DatosPreseleccionados=Funciones::ObtenValor(" select
            PresupuestoAnualPrograma.Fondo as Fondoid,
            Cat_alogoDeFondo.Descripci_on as  FondoDesc,
            PresupuestoAnualPrograma .id as Progid,
            (select Descripci_on from Cat_alogoPrograma where PresupuestoAnualPrograma.Programa = Cat_alogoPrograma.id) as ProgDesc
            FROM PresupuestoAnualPrograma
                INNER JOIN Fondo ON (Fondo.id = PresupuestoAnualPrograma.Fondo)
                        INNER JOIN Cat_alogoDeFondo ON (Cat_alogoDeFondo.id=Fondo.CatalogoFondo)
                INNER JOIN PresupuestoGeneralAnual ON (Fondo.Presupuesto= PresupuestoGeneralAnual.id)
            WHERE
            (select Descripci_on from Cat_alogoPrograma where
            PresupuestoAnualPrograma.Programa=Cat_alogoPrograma.id) LIKE '%ingresos propios%' AND
            Cliente=".$cliente." AND EjercicioFiscal=".$ano );
            return $DatosPreseleccionados;
    }





public static  function ObtieneImporteyConceptos2($cliente,$ano,$idConcepto, $montobase){

    //Obtenemos el importe del concepto seleccionado.

     $datosConcepto=Funciones::ObtenValor("SELECT c3.`Importe` as import, c3.`BaseCalculo` as TipobaseCalculo
        FROM `ConceptoCobroCaja` c
        INNER JOIN `ConceptoRetencionesAdicionales` c1 ON ( c.id = c1.`Concepto`  )
            INNER JOIN `ConceptoRetencionesAdicionalesCliente` c2 ON ( c1.id = c2.`ConceptoRetencionesAdicionales`  )
                INNER JOIN `ConceptoAdicionales` c3 ON ( c2.id = c3.`ConceptoRetencionesAdicionalesCliente`  )
        WHERE c3.Cliente=".$cliente." AND c3.EjercicioFiscal=".$ano." AND c2.Cliente=".$cliente." AND c.id = ".$idConcepto );
    $Datos['baseCalculo']=$datosConcepto->TipobaseCalculo;

    if($datosConcepto->TipobaseCalculo==2){
        $eldato=str_replace(",","",$datosConcepto->import);
        $elotrodato=str_replace(",","",$montobase);
        $Datos['importe']=$eldato*$elotrodato/100;
        $Datos['punitario']=floatval(str_replace(",","",$datosConcepto->import));

    }else{
        $Datos['importe']=$montobase*floatval(str_replace(",","",$datosConcepto->import));
        $Datos['punitario']=floatval(str_replace(",","",$datosConcepto->import));
    }

   $ResultadoInserta = DB::select("SELECT * FROM RetencionesAdicionales
   WHERE id
   IN (SELECT RetencionAdicional
   FROM `ConceptoCobroCaja` c
   INNER JOIN `ConceptoRetencionesAdicionales` c1 ON ( c.id = c1.`Concepto`  )
       INNER JOIN `ConceptoRetencionesAdicionalesCliente` c2 ON ( c1.id = c2.`ConceptoRetencionesAdicionales`  )
           INNER JOIN `ConceptoAdicionales` c3 ON ( c2.id = c3.`ConceptoRetencionesAdicionalesCliente`  )
   WHERE c.id = ".$idConcepto.")");

    $i=0;
    foreach($ResultadoInserta as $filas){

        if(isset($filas->id)){
            $filasV2= CotizacionServiciosPredialCDCrectificarController::Configuraci_onAdicionales($filas,$cliente);

            $filas->id= $filasV2->id;
            $filas->Descripci_on =  $filasV2->Descripci_on;
            $filas->PlanDeCuentas =  $filasV2->PlanDeCuentas;
            $filas->Proveedor=  $filasV2->Proveedor;
            $filas->Porcentaje=  $filasV2->Porcentaje;
            $filas->ConceptoCobro =  $filasV2->ConceptoCobro;
        }
        $i++;
        if($Datos['baseCalculo']==1){ //importe
            $idConceptoOperacion=number_format((floatval(str_replace(",","",$Datos['importe']))*floatval($filas->Porcentaje / 100 )  ),2);

        }
        if($Datos['baseCalculo']==2){ //porcentaje

            $idConceptoOperacion=number_format((floatval(str_replace(",","",$Datos['importe']))*floatval($filas->Porcentaje) / 100 ),2);
        }

        if($Datos['baseCalculo']==3){ // SDG
            $zona=Funciones::ObtenValor("SELECT ZonaEconomica FROM Cliente WHERE id=".$cliente, "ZonaEconomica");

            $sdg=Funciones::ObtenValor("SELECT ".$zona." FROM SalariosM_inimos WHERE EjercicioFiscal=".$ano, $zona);

            $Datos['importe']=$datosConcepto->import*$sdg;
            $Datos['punitario']=$sdg;

            $calculoimporte= floatval(str_replace(",","",$sdg)) * floatval(str_replace(",","",$datosConcepto->import));
            $idConceptoOperacion=number_format((floatval($calculoimporte)*floatval($filas->Porcentaje) / 100 ),2);
        }

        $Datos['adicionales'.$i]['TipoBase']=$Datos['baseCalculo'];
        $Datos['adicionales'.$i]['MontoBase']=$Datos['punitario'];
        $Datos['adicionales'.$i]['idAdicional']=$filas->id;
        $Datos['adicionales'.$i]['Descripcion']=$filas->Descripci_on;
        $Datos['adicionales'.$i]['Resultado']=$idConceptoOperacion;



    }
    $Datos['NumAdicionales']=$i;
    //echo "<pre>";
    //print_r($Datos);
    //echo "</pre>";
    //obtenemos los datos de los adicionales
    //select Descripci_on from RetencionesAdicionales where id in (select RetencionAdicional from ConceptoRetencionesAdicionales where Concepto=10 and id in (select ConceptoRetencionesAdicionales from ConceptoRetencionesAdicionalesCliente where id in (select ConceptoRetencionesAdicionalesCliente from ConceptoAdicionales where Cliente=1 and AreaRecaudadora=2)))

    return ($Datos);
}

  public static  function Configuraci_onAdicionales($Adicional,$Cliente){
        $ClienteDatos = Funciones::ObtenValor("SELECT Turistico FROM Cliente c where c.id=$Cliente","Turistico");
        $AdicionalRespuesta=$Adicional;
        if(($Adicional->id==1 || $Adicional->id==3 || $Adicional->id==24 || $Adicional->id==26) && 1==1){
        if(isset($ClienteDatos) && $ClienteDatos==1 &&  $Adicional->id==24){
            $AdicionalRespuesta = Funciones::ObtenValor("SELECT * FROM RetencionesAdicionales r where r.id=26");
        }else  if(isset($ClienteDatos) && $ClienteDatos==0 &&  $Adicional->id==26){
            $AdicionalRespuesta = Funciones::ObtenValor("SELECT * FROM RetencionesAdicionales r where r.id=24");
        }
        else  if(isset($ClienteDatos) && $ClienteDatos==1 &&  $Adicional->id==1){
            $AdicionalRespuesta = Funciones::ObtenValor("SELECT * FROM RetencionesAdicionales r where r.id=3");
        }else  if(isset($ClienteDatos) && $ClienteDatos==0 &&  $Adicional->id==3){
            $AdicionalRespuesta = Funciones::ObtenValor("SELECT * FROM RetencionesAdicionales r where r.id=1");
        }
        }
        return $AdicionalRespuesta;
    }



public static function ObtieneImporteyConceptos($cliente,$ano,$valor, $indice=0, $montobase){


	//Obtenemos el importe del concepto seleccionado.
	$importe=Funciones::ObtenValor("SELECT c3.`Importe` as import, c3.`BaseCalculo` as TipobaseCalculo
    FROM `ConceptoCobroCaja` c
        INNER JOIN `ConceptoRetencionesAdicionales` c1 ON ( c.id = c1.`Concepto`  )
            INNER JOIN `ConceptoRetencionesAdicionalesCliente` c2 ON ( c1.id = c2.`ConceptoRetencionesAdicionales`  )
                INNER JOIN `ConceptoAdicionales` c3 ON ( c2.id = c3.`ConceptoRetencionesAdicionalesCliente`  )
    WHERE
        c3.Cliente=".$cliente." AND
        c2.Cliente=".$cliente." AND
        c3.EjercicioFiscal=".$ano." AND
        c.id = ".$valor." and c3.Status=1" );

	$Datos['baseCalculo']=$importe->TipobaseCalculo;

	$ConsultaSelect = "SELECT * FROM RetencionesAdicionales
    WHERE id
    IN (SELECT RetencionAdicional
    FROM `ConceptoCobroCaja` c
        INNER JOIN `ConceptoRetencionesAdicionales` c1 ON ( c.id = c1.`Concepto`  )
            INNER JOIN `ConceptoRetencionesAdicionalesCliente` c2 ON ( c1.id = c2.`ConceptoRetencionesAdicionales`  )
                INNER JOIN `ConceptoAdicionales` c3 ON ( c2.id = c3.`ConceptoRetencionesAdicionalesCliente`  )
    WHERE
        c3.Cliente=".$cliente." AND
        c3.EjercicioFiscal=".$ano." AND
        c2.Cliente=".$cliente." AND
        c.id = ".$valor.")";
	$ResultadoInserta = DB::select($ConsultaSelect);
	$i=0;
	$Datos['suma']=0;
	$Datos['adicionales']='<div class="losadicionales'.$indice.'" >';
	if($Datos['baseCalculo']==1){
		$Datos['importe']=$montobase*$importe->import;
		$Datos['punitario']=str_replace(",","",number_format($importe->import,2));
		$Datos['simbolo']="$";

		$Datos['montoBase']=str_replace(",","",number_format($montobase,2));
	}
	if($Datos['baseCalculo']==2){
		$Datos['importe']=$importe->import*$montobase/100;
		$Datos['punitario']=str_replace(",","",number_format($importe->import,2));
		$Datos['simbolo']="%";
		$Datos['montoBase']=str_replace(",","",number_format($montobase,2));
	}
	if($Datos['baseCalculo']==3){ //SDG
		$zona=Funciones::ObtenValor("SELECT ZonaEconomica FROM Cliente WHERE id=".$cliente, "ZonaEconomica", "ZonaEconomica");
		//echo "SELECT ".$zona." FROM SalariosM_inimos WHERE EjercicioFiscal=".$_SESSION['CELA_EjercicioFiscal'.$_SESSION['CELA_Aleatorio']];
		$sdg=Funciones::ObtenValor("SELECT ".$zona." FROM SalariosM_inimos WHERE EjercicioFiscal=".$ano,$zona);
		if($montobase==$importe->import || $montobase==1)
			$Datos['montoBase']=str_replace(",","",number_format($importe->import,2));
		else
			$Datos['montoBase']=str_replace(",","",number_format($montobase,2));
		$Datos['punitario']=str_replace(",","",number_format($sdg,2));
		$Datos['importe']=str_replace(",","",number_format($Datos['punitario']*$Datos['montoBase'],2));
		$Datos['simbolo']="SMG";

	}
	$Datos['suma']+=str_replace(",","",number_format($Datos['importe'],2));

	foreach($ResultadoInserta as $filas){
            /*Bloque de Configuraci_on de Adicionales*/
                if(isset($filas->id)){
                       $filasV2= CotizacionServiciosPredialCDCrectificarController::Configuraci_onAdicionales($filas,$cliente);
                       $filas->id = $filasV2->id;
                       $filas->Descripci_on =  $filasV2->Descripci_on;
                       $filas->PlanDeCuentas =  $filasV2->PlanDeCuentas;
                       $filas->Proveedor =  $filasV2->Proveedor;
                       $filas->Porcentaje =  $filasV2->Porcentaje;
                       $filas->ConceptoCobro =  $filasV2->ConceptoCobro;

                }
		$i++;
		if($Datos['baseCalculo']==1){
			$valorOperacion=number_format((floatval(str_replace(",","",$Datos['importe']))*floatval($filas->Porcentaje / 100 )  ),2);
			$valorOperacion2=(floatval(str_replace(",","",$Datos['importe']))*floatval($filas->Porcentaje / 100 )  );
		}
		if($Datos['baseCalculo']==2){
			$valorOperacion=number_format((floatval($Datos['importe'])*floatval($filas->Porcentaje) / 100 ),2);
			$valorOperacion2=(floatval($Datos['importe'])*floatval($filas->Porcentaje) / 100 );
		}
		if($Datos['baseCalculo']==3){
			$valorOperacion=number_format((floatval($Datos['importe'])*floatval($filas->Porcentaje) / 100 ),2);
			$valorOperacion2=(floatval($Datos['importe'])*floatval($filas->Porcentaje) / 100 );
		}
		$Datos['suma']+=floatval($valorOperacion);


		$Datos['adicionales'].='
										<div class="col-md-8 text-left">
											'.$filas->Descripci_on.'
										</div>

										<div class="col-md-4 text-right ">
											'.$valorOperacion.'
										</div><input type="hidden" value="'.str_replace(",","",number_format(($valorOperacion2),2)).'" class="asumar">


									';
		//echo $importe['import']." +++ ".$filas['Porcentaje'];

	}




	$Datos['adicionales'].=' </div>';
	$Datos['consulta']=$ConsultaSelect;

	$Datos['cantidadAdicional']=$i;
	//obtenemos los datos de los adicionales
	//select Descripci_on from RetencionesAdicionales where id in (select RetencionAdicional from ConceptoRetencionesAdicionales where Concepto=10 and id in (select ConceptoRetencionesAdicionales from ConceptoRetencionesAdicionalesCliente where id in (select ConceptoRetencionesAdicionalesCliente from ConceptoAdicionales where Cliente=1 and AreaRecaudadora=2)))

	return $Datos;
}

function ObtenValorPorClave($Clave, $Cliente){
	return Funciones::ObtenValor("SELECT Valor FROM ClienteDatos WHERE Cliente=".$Cliente." AND Indice='".$Clave."'", "Valor");

}

}
