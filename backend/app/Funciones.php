<?php

namespace App;

use JWTAuth;
use DateTime;
use Exception;
use App\Cliente;
use App\Libs\QRcode;
use App\Libs\Wkhtmltopdf;
use App\Modelos\PadronAguaPotable;
use App\Modelos\PadronAguaLectura;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use \Illuminate\Support\Facades\Config;
use Illuminate\Http\Request;

class Funciones {
    /**
     * !Retorna un json con el nuevoken y el resultado obtenido psado por parametro.
     * @param string $token
     * @return \Illuminate\Http\JsonResponse
     */

    public static function respondWithToken( $result ){
        JWTAuth::factory()->setTTL(60);//Pedro Lopez Pacheco 13 de junio 2022, modificacion para que el token dure 50 minutos segun la doc de laravel JWT

        return response()->json([
            'token'      => 'bearer '.auth()->refresh(),
            'result'     => $result,
        ]);
    }

    


    public static function selecionarBase($cliente){
        $db = General::where('id', $cliente)->value('NombreDB');

        Config::set('database.connections.mysql.database', $db);
        DB::purge('mysql');
    }
    public static function selecionarBaseRemoto(){
        $db ="piacza_recauda";

        Config::set('database.connections.mysqlServicioRemoto.database', $db);
        DB::purge('mysqlServicioRemoto');
    }
    public static function conexionGeneral($cliente){


        Config::set('database.connections.mysql.database', $cliente);
        DB::purge('mysql');
    }
    public  static function re($v){
        return $v;
    }
    public  static function DecodeThis2($String) {
        $String = base64_decode($String); //codifico la cadena
        $String = Funciones::Decrypt2($String, "b5s1i4t5a1316");
        $String = utf8_decode($String);
        return($String);
    }

    public static function Decrypt2($String, $Key) {
        $Result = '';
        $String = base64_decode($String);
        for ($i = 0; $i < strlen($String); $i++) {
            $Char = substr($String, $i, 1);
            $KeyChar = substr($Key, ($i % strlen($Key)) - 1, 1);
            $Char = chr(ord($Char) - ord($KeyChar));
            $Result .= $Char;
        }
        return $Result;
    }
    public static function InformacionContratoAguaPotableOPD(){
        $array = array();

        $Datos = Funciones::VerificarExistenciaCuentaAgua('32', '1389', '2012', '05');

        return $Datos;

        if("$Importe" == "".$Datos['Importe']){
            $array['Importe'] = $Datos['Importe'];
            $array['Status'] ="Verificaciòn Exitosa.";
            $Contrato = json_decode($Datos['Contribuyente'], true);
            $Contribuyente = ObtenValor("SELECT * FROM Contribuyente c WHERE c.id=".$Contrato['Contribuyente']);
            $array['Contribuyente'] = json_encode($Contribuyente);
            $array ['Padr_on'] = json_encode($Contrato);
            $Resultado = json_encode($array);
            $Resultado= json_decode($Resultado, true);
        }else{
            $array['Status'] ="Error Verificación fallida.";
        }

        #precode($array,1);
    }

    //! +-+-+-+-+ +-+-+-+-+-+-+-+-+ +-+-+-+ +-+-+-+-+-+
    //! |P|a|r|a| |L|e|c|t|u|r|a|s| |Q|u|e| |D|e|b|e|n|
    //! +-+-+-+-+ +-+-+-+-+-+-+-+-+ +-+-+-+ +-+-+-+-+-+
    public static function VerificarExistenciaCuentaAgua($Cliente, $idPadron, $A_no, $Mes, $Tipo){
        /*$Datos = PadronAguaPotable::select('Padr_onAguaPotable.*')
            ->join('Padr_onDeAguaLectura as pl', 'Padr_onAguaPotable.id', '=', 'pl.Padr_onAgua')
            ->where('pl.A_no', $A_no)
            ->where('pl.Mes', $Mes)
            #->where(DB::raw('CAST(Padr_onAguaPotable.ContratoVigente AS UNSIGNED)'), $Contrato)
            ->where('Padr_onAguaPotable.Cliente', $Cliente)
            ->where('Padr_onAguaPotable.Estatus', '1')
            ->where('Padr_onAguaPotable.id', $Contrato)
            ->first();*/

        $ResultadoConcepto = DB::select("SELECT
                        ct.Importe as Pago, ct.id
                    FROM
                        Cotizaci_on c
                        INNER JOIN ConceptoAdicionalesCotizaci_on ct ON(ct.Cotizaci_on = c.id AND ct.Padr_on=" . $idPadron . "  AND ct.Estatus=0)
                        INNER JOIN ConceptoCobroCaja co ON ( ct.ConceptoAdicionales = co.id  )
                    WHERE c.Padr_on=" . $idPadron . " AND  ct.A_no=$A_no AND  ct.Mes=$Mes AND
                        c.Tipo = 9 AND
                        c.Cliente = $Cliente");

        $ImporteTotal = 0;

        if( $Tipo == 2){
            foreach($ResultadoConcepto as $RegistroConcepto){
                $ImporteTotal+= floatval($RegistroConcepto->Pago);
            }
        }else{
            foreach($ResultadoConcepto as $RegistroConcepto){
                $ImporteTotal+= floatval($RegistroConcepto->Pago) + floatval(Funciones::ObtenerRecargosYActualizacionesAguaPortal($Cliente, $idPadron, $RegistroConcepto->id));
            }

        }
        /*$arr = array();
        $arr['Importe'] = $ImporteTotal;
        $arr['Contribuyente'] = ($Datos);*/

        return $ImporteTotal;
    }

    public static function ObtenerRecargosYActualizacionesAguaPortal($cliente, $Padr_on, $Concepto ){

        $ConsultaConceptos = "SELECT c.id as idConceptoCotizacion, co.id as ConceptoCobro, co.Cantidad as Cantidad,
                c.Descripci_on as NombreConcepto, co.Importe as total, co.MontoBase as punitario,
                (select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional,
                co.A_no, COALESCE(co.Mes, '01') as Mes, ct.Tipo, c.TipoToma
            FROM ConceptoAdicionalesCotizaci_on co
                INNER JOIN Cotizaci_on ct ON ( co.Cotizaci_on = ct.id  )
                INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id  )
            WHERE ct.Padr_on =" . $Padr_on . " and Estatus=0 AND co.id  IN($Concepto) ORDER BY co.A_no DESC,
            COALESCE(co.Mes, '01') DESC ,  co.id ASC ";

        $ResultadoConcepto = DB::select($ConsultaConceptos);
        $RegistroConcepto = $ResultadoConcepto[0];
        #return $ResultadoConcepto[0]->idConceptoCotizacion;

        #$ResultadoConcepto=$Conexion->query($ConsultaConceptos);
        $totalConcepto=0;
        $idsConceptos='';
        $Contador=0;
        $ConceptoActual=0;
        $indexConcepto=0;
        $i = 0;
        #setlocale(LC_TIME,"es_MX.UTF-8");
        #$RegistroConcepto=$ResultadoConcepto->fetch_assoc();

        $totalConcepto = $RegistroConcepto->total;
        $ConceptoPadre[$indexConcepto]['id']=$RegistroConcepto->idConceptoCotizacion;
        $ConceptoPadre[$indexConcepto]['idConcepto']=$RegistroConcepto->ConceptoCobro;
        $ConceptoPadre[$indexConcepto]['TipoPredio']=$RegistroConcepto->Tipo;
        $ConceptoPadre[$indexConcepto]['Total']=0;
        $ConceptoPadre[$indexConcepto]['FechaConcepto']=$RegistroConcepto->A_no."-".$RegistroConcepto->Mes."-01";

        $recargosActualizaciones = array();

        #while($RegistroConcepto=$ResultadoConcepto->fetch_assoc()){
        foreach($ResultadoConcepto as $RegistroConcepto){
        //precode($RegistroConcepto,1);
            $i++;
            if($i != 1){
                if( empty($RegistroConcepto->Adicional) ){
                    //Es concepto
                    $ConceptoPadre[$indexConcepto]['Total']=$totalConcepto;

                    $totalConcepto=$RegistroConcepto->total ;
                    $Contador=0;
                    $ConceptoActual++;
                    $indexConcepto++;

                    $ConceptoPadre[$indexConcepto]['id']=$RegistroConcepto->idConceptoCotizacion;
                    $ConceptoPadre[$indexConcepto]['TipoPredio']=$RegistroConcepto->Tipo;
                    $ConceptoPadre[$indexConcepto]['Mes']=$RegistroConcepto->Mes;
                    $ConceptoPadre[$indexConcepto]['A_no']=$RegistroConcepto->A_no;

                    $ConceptoPadre[$indexConcepto]['idConcepto']=$RegistroConcepto->ConceptoCobro;
                    $ConceptoPadre[$indexConcepto]['Nombre']=$RegistroConcepto->NombreConcepto;
                    $ConceptoPadre[$indexConcepto]['FechaConcepto']=$RegistroConcepto->A_no."-".$RegistroConcepto->Mes."-01";

                }else{ //Es adicional
                    $totalConcepto +=$RegistroConcepto->total ;
                }
                $Contador++;
            }
        }

        $ConceptoPadre[$indexConcepto]['Total']=$totalConcepto;

        #return $ConceptoPadre;

        //Buscamos actualizaciones y recargos para los conceptos a pagar
        $ActualizacionesYRecargos="";
        $PagoActualizaciones=0;
        $sumatotalActyRec=0;

        $fechaActual = date("Y-m-d");
        $auxMes = array("", "Enero", "Febrero","Marzo","Abril","Mayo","Junio","Julio", "Agosto","Septiembre","Octubre","Noviembre","Diciembre");

        for($iC=0; $iC<count($ConceptoPadre); $iC++){
            $ConceptoExcluidosParaActualizacionesRecargos = array(5461, 5462,5467, 5469, 2489, 5084);
            if (!in_array($ConceptoPadre[$iC]['id'], $ConceptoExcluidosParaActualizacionesRecargos)) {

                //Obtenemos las actualizaciones y recargos.
                if($ConceptoPadre[$iC]['FechaConcepto']!="--01"){
                    if(date("Y-m", strtotime( $fechaActual ) ) > date('Y-m', strtotime($ConceptoPadre[$iC]['FechaConcepto']))){
                        //Obtenemos las multas del concepto
                        $ConsultaMultas = " SELECT mi.Categor_ia as Categoria, m.id as idMulta, m.Descripci_on as DescripcionMulta, c.id as idConcepto, c.Descripci_on as DescripcionConcepto
                        FROM MultaCategor_ia mi
                        INNER JOIN Multa m ON ( mi.Multa = m.id  )
                        INNER JOIN ConceptoCobroCaja c ON ( mi.Concepto = c.id  )
                        WHERE mi.Categor_ia = (select Categor_ia from ConceptoCobroCaja where id = ".$ConceptoPadre[$iC]['id'].")";
                        #precode($ConsultaMultas,1);

                        $ResultadoMultas = DB::select($ConsultaMultas);
                        #$ResultadoMultas=$Conexion->query($ConsultaMultas);

                        foreach($ResultadoMultas as $RegistroMultas){
                        #while($RegistroMultas=$ResultadoMultas->fetch_assoc()){
                            $fechainicial = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);
                            $elmes=$fechainicial[1];
                            $elanio=$fechainicial[0];

                            if($RegistroMultas->idMulta == 1){
                                //Es Actualizacion

                                if($ConceptoPadre[$iC]['TipoPredio']==3)//3 es para predial
                                {

                                }// termina tipo 3 predial
                                else{
                                    //Caso normal que no es predial
                                    $fechainicial  = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);
                                    $montoconcepto =  $ConceptoPadre[$iC]['Total'];
                                    $mes  = ($fechainicial[1])+1;
                                    $anio = $fechainicial[0];

                                    if(intval($mes)>12){
                                        $mes  = 1;
                                        $anio = $anio+1;
                                    }

                                    $mes=str_pad($mes, 2, "0", STR_PAD_LEFT);
                                    $dia=18;
                                    $fechaVencimiento= $anio."-".$mes."-".$dia;
                                    if(date('D',intval($fechaVencimiento))=="Sat"){
                                       $dia=$dia+2;
                                    }
                                    if(date('D',intval($fechaVencimiento))=="Sun"){
                                        $dia=$dia+1;
                                    }

                                    $fechaVencimiento = $anio."-".$mes."-".$dia;
                                    $fecha_actual     = strtotime(date("Y-m-d H:i:00",time()));
                                    $fecha_entrada    = strtotime(date($fechaVencimiento." H:i:00"));
                                    if($fecha_actual > $fecha_entrada){
                                        $actualizacionesOK = Funciones::CalculoActualizacionFecha1($fechaVencimiento, $montoconcepto, $fechaActual );
                                    }else{
                                        $actualizacionesOK = 0;
                                    }
                                }

                                if($actualizacionesOK>0){
                                    $PagoActualizaciones += $actualizacionesOK;
                                }
                            }
                            if($RegistroMultas->idMulta==2){
                                //Es Multa
                            }
                            if($RegistroMultas->idMulta==3){
                                //Es Recargo

                                if($ConceptoPadre[$iC]['TipoPredio']==3)//3 es para predial
                                {

                                }// termina tipo 3 predial
                                else{
                                    //Caso normal que no es predial
                                    $fechainicial = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);
                                    $montoconcepto=  $ConceptoPadre[$iC]['Total'];
                                    //precode($RegistroConcepto,1);
                                    $mes=($fechainicial[1])+1;
                                    $anio=$fechainicial[0];


                                    if(intval($mes)>12){
                                        $mes=1;
                                        $anio=$anio+1;
                                    }

                                    $mes=str_pad($mes, 2, "0", STR_PAD_LEFT);

                                    $dia=18;

                                    $fechaVencimiento= $anio."-".$mes."-".$dia;

                                    if(date('D',intval($fechaVencimiento))=="Sat"){
                                       $dia=$dia+2;
                                    }
                                    if(date('D',intval($fechaVencimiento))=="Sun"){
                                        $dia=$dia+1;
                                    }

                                    $fechaVencimiento= $anio."-".$mes."-".$dia;

                                    //exit;
                                    $fecha_actual = strtotime(date("Y-m-d H:i:00",time()));
                                    $fecha_entrada = strtotime(date($fechaVencimiento." H:i:00"));
                                    if($fecha_actual > $fecha_entrada){
                                        $fechaVencimiento.$montoconcepto;
                                        $recargosOK	= Funciones::CalculoRecargosFechaAgua($fechaVencimiento, $montoconcepto, $fechaActual, $cliente );
                                        //$actualizacionesOK = CalculoActualizacion($fechaVencimiento, $montoconcepto);
                                    }else{
                                        $recargosOK		   = 0;
                                    }
                                }

                                $recargosActualizaciones[] = ( round( $actualizacionesOK, 2) + round( $recargosOK, 2 ) );
                            }
                        }
                    }
                }//if si es fecha valida
            }
        }//for

        return array_sum($recargosActualizaciones);
    }

    public static function CalculoActualizacionFecha1($fechaConcepto, $ImporteConcepto, $fechaActualArg=NULL ){
		//Es Recargo
		if(is_null($fechaActualArg)){
			$fechaActualArg=date('Y-m-d');
		}

		//Es Actualizacion
		$fechaHoy=$fechaActualArg;
		#$fechaHoy= date("Y-m-d", strtotime ( '-1 month' , strtotime (  date('Y-m-d') )) );
	 	#$fechaConcepto= date("Y-m-d", strtotime ( '-1 month' , strtotime (  $fechaConcepto )) );

		$Recargoschecked="";
		$mesConocido=1;
		while(true){
			$fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ( $fechaConcepto ) ) ;
			setlocale(LC_TIME,"es_MX.UTF-8");
			$mes = intval(strftime("%m", $fecha ));
			$a_no = strftime("%Y", $fecha );
            #precode($a_no."-".$mes,1);

            $INPCCotizacion = DB::table('IndiceActualizaci_onV2')->where('A_no', $a_no)->where('Mes', $mes)->value('Importe');
			#$INPCCotizacion=ObtenValor("select Importe from IndiceActualizaci_onV2 where A_no=$a_no AND Mes=$mes","Importe");

			if(empty($INPCCotizacion) || $INPCCotizacion=='')
				$mesConocido++;
			else
				break;
		}

		$mesConocido=1;
		while(true){
			$fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ($fechaHoy)) ;
			setlocale(LC_TIME,"es_MX.UTF-8");
			$mes = intval(strftime("%m", $fecha ));
			$a_no = strftime("%Y", $fecha );
			#precode($a_no."-".$mes,1);
            #precode("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,1,1);
            $INPCPago = DB::table('IndiceActualizaci_onV2')->where('A_no', $a_no)->where('Mes', $mes)->value('Importe');
			#$INPCPago=ObtenValor("select Importe from IndiceActualizaci_onV2 where A_no=$a_no AND Mes=$mes","Importe");

			if(empty($INPCPago) || $INPCPago=='')
				$mesConocido++;
			else
				break;
		}

		$FactorActualizacion=$INPCPago/$INPCCotizacion;

        $Actualizacion=($ImporteConcepto*$FactorActualizacion)-$ImporteConcepto;

		return  $Actualizacion;
	}

    public static function CalculoRecargosFechaAgua($fechaConcepto, $ImporteConcepto, $fechaActualArg=NULL , $cliente){
        //Es Recargo
        if(is_null($fechaActualArg)){
            $fechaActualArg=date('Y-m-d');
        }
return "hola";
        $Actualizacion       = Funciones::CalculoActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg);
        $FactorActualizacion = Funciones::CalculofactorActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg);

        $mesConocido=0;
        $SumaDeTasa=0;

        $fechaHoy= date("Y-m-d", strtotime ( '-1 month' , strtotime (  $fechaActualArg )) );
        $fechaConcepto= date("Y-m-d", strtotime ( '-1 month' , strtotime (  $fechaConcepto )) );

        $fechafinal = explode('-', $fechaHoy);
        $fechainicial = explode('-', $fechaConcepto);

        $fechainicialdif = new DateTime($fechaConcepto);
        $fechafinaldif = new DateTime($fechaHoy);
        $elmes=$fechainicial[1];
        $elanio=$fechainicial[0];
        $diferencia = $fechainicialdif->diff($fechafinaldif);
        $meses = ( $diferencia->y * 12 ) + $diferencia->m;

        while($mesConocido<=$meses){
            $fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ( $fechaHoy)) ;
            setlocale(LC_TIME,"es_MX.UTF-8");
            $mes = (date("m", $fecha ));
            $a_no = strftime("%Y", $fecha );

            $valor = DB::table('PorcentajeRecargo')->where('A_no', $a_no)->where('Cliente', $cliente)->where('Mes', $mes)->value('Recargo');
            #ObtenValor("select Recargo from PorcentajeRecargo where A_no=".$a_no." and Cliente=".$cliente." and Mes=".$mes,"Recargo")

            $SumaDeTasa+=floatval($valor);
            $mesConocido++;
        }

        if($Actualizacion>0)
            $Recargo=(($ImporteConcepto*$FactorActualizacion)*round($SumaDeTasa, 2))/100;
        else
            $Recargo=(($ImporteConcepto)*$SumaDeTasa)/100;

        return $Recargo;
    }

    public static function CalculoActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg=NULL){
		//Es Recargo
		if(is_null($fechaActualArg)){
			$fechaActualArg=date('Y-m-d');
		}

		//Es Actualizacion
        $fechaHoy=$fechaActualArg;

		$Recargoschecked="";
		$mesConocido=1;
		while(true){
			 $fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ( $fechaConcepto ) ) ;
			setlocale(LC_TIME,"es_MX.UTF-8");
			$mes = ucwords(strftime("%B", $fecha ));
			$a_no = strftime("%Y", $fecha );
            #precode($a_no."-".$mes,1);
            $INPCCotizacion = DB::table('IndiceActualizaci_on')->where('A_no', $a_no)->value("$mes");
			#$INPCCotizacion=ObtenValor("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,$mes);

			if(empty($INPCCotizacion) || $INPCCotizacion =='')
				$mesConocido++;
			else
				break;
		}

		$mesConocido=1;
		while(true){
			$fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ($fechaHoy)) ;
			setlocale(LC_TIME,"es_MX.UTF-8");
			$mes = ucwords(strftime("%B", $fecha ));
			$a_no = strftime("%Y", $fecha );
			#precode($a_no."-".$mes,1);
            #precode("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,1,1);
            $INPCPago = DB::table('IndiceActualizaci_on')->where('A_no', $a_no)->value("$mes");
			#$INPCPago=ObtenValor("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,$mes);

			if(empty($INPCPago) || $INPCPago=='')
				$mesConocido++;
			else
				break;
		}

        $FactorActualizacion=$INPCPago/$INPCCotizacion;

		if($FactorActualizacion<1){
			$FactorActualizacion=1;
		}

		$Actualizacion=($ImporteConcepto*$FactorActualizacion)-$ImporteConcepto;

		return  $Actualizacion;
    }

    public static function CalculofactorActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg=NULL){

        if(is_null($fechaActualArg)){
            $fechaActualArg=date('Y-m-d');
        }

        //Es Actualizacion
        $fechaHoy=$fechaActualArg;

        $Recargoschecked="";
        $mesConocido=1;
        while(true){
                $fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ( $fechaConcepto ) ) ;
            setlocale(LC_TIME,"es_MX.UTF-8");
            $mes = ucwords(strftime("%B", $fecha ));
            $a_no = strftime("%Y", $fecha );
            #precode($a_no."-".$mes,1);
            $INPCCotizacion = DB::table('IndiceActualizaci_on')->where('A_no', $a_no)->value("$mes");
            #$INPCCotizacion=ObtenValor("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,$mes);

            if(empty($INPCCotizacion) || $INPCCotizacion=='')
                $mesConocido++;
            else
                break;
        }

        $mesConocido=1;
        while(true){
            $fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ($fechaHoy)) ;
            setlocale(LC_TIME,"es_MX.UTF-8");
            $mes = ucwords(strftime("%B", $fecha ));
            $a_no = strftime("%Y", $fecha );
            #precode($a_no."-".$mes,1);
            #precode("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,1,1);
            $INPCPago = DB::table('IndiceActualizaci_on')->where('A_no', $a_no)->value("$mes");
            #$INPCPago=ObtenValor("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,$mes);

            if(empty($INPCPago) || $INPCPago=='')
                $mesConocido++;
            else
                break;
        }
                $FactorActualizacion=$INPCPago/$INPCCotizacion;

        if($FactorActualizacion<1){
            $FactorActualizacion=1;
        }

        return $FactorActualizacion;
    }

    //! +-+-+-+-+ +-+-+-+-+-+-+-+-+ +-+-+-+-+-+-+-+ +-+-+-+-+
    //! |P|a|r|a| |L|e|c|t|u|r|a|s| |P|a|g|a|d|a|s| |A|g|u|a|
    //! +-+-+-+-+ +-+-+-+-+-+-+-+-+ +-+-+-+-+-+-+-+ +-+-+-+-+

    public static function obtieneDatosLectura($Lectura){

		$TipoToma=Funciones::ObtenValor("SELECT pl.TipoToma FROM  Padr_onDeAguaLectura pl WHERE id=".$Lectura, "TipoToma");
		#$TipoToma=($TipoToma=="NULL"?"pa":"pl");
		#precode("SELECT pl.TipoToma FROM  Padr_onDeAguaLectura pl WHERE id=".$Lectura,1,1);
		$DatosLecturaActual=Funciones::ObtenValor("SELECT * , pa.id as paid, pl.id as plid, pl.Status as EstatusPagado, CONCAT(pl.A_no,LPAD(pl.Mes, 2, 0 )) as MesEjercicio, t.Concepto as TipoTomaTexto
			FROM Padr_onAguaPotable pa
			INNER JOIN Padr_onDeAguaLectura pl ON (pa.id=pl.Padr_onAgua)
			INNER JOIN TipoTomaAguaPotable t ON (t.id=".(is_null($TipoToma)?"pa":"pl").".TipoToma)
			WHERE
			#pl.Status=0 AND
			pl.id=".$Lectura."
			ORDER BY pl.A_no DESC, pl.Mes DESC
			LIMIT 0, 1");
		#precode($DatosLecturaActual,1,1);

		$ConsultaLecturas="SELECT DISTINCT pal.id as palid,  ".(is_null($TipoToma)?"pap":"pal").".TipoToma, pal.Consumo, pal.Tarifa, ccc.Descripci_on, ccc.CRI, ccc.id as id, pal.Mes, pal.A_no, ca.Importe as ImporteUnitario, ca.BaseCalculo, pap.Cliente, CONCAT(pal.A_no,LPAD(pal.Mes,2,'0')) as MesEjercicio, pal.Status as EstatusPagado
        FROM Padr_onAguaPotable pap
        INNER JOIN Padr_onDeAguaLectura pal ON (pap.id=pal.Padr_onAgua)
        INNER JOIN ConceptoCobroCaja ccc ON (ccc.TipoToma=".(is_null($TipoToma)?"pap":"pal").".TipoToma )
        INNER JOIN ConceptoRetencionesAdicionales cra ON ( cra.Concepto= ccc.id )
        INNER JOIN ConceptoRetencionesAdicionalesCliente crac ON ( cra.id = crac.ConceptoRetencionesAdicionales)
        INNER JOIN ConceptoAdicionales ca ON (ca.ConceptoRetencionesAdicionalesCliente=crac.id)
        WHERE
        ccc.Desde IS NULL AND
        ccc.Hasta IS NULL AND
        ca.Status=1 AND
        pap.id =".$DatosLecturaActual->paid." AND
        pap.Cliente=".$DatosLecturaActual->Cliente." AND
        crac.Cliente=".$DatosLecturaActual->Cliente." AND
        pal.Status IN (0,1,2) AND
        pal.EstadoToma=1 AND
        CONCAT(pal.A_no,LPAD(pal.Mes,2,'0'))<=".$DatosLecturaActual->MesEjercicio." AND
        1=1";
#precode($ConsultaLecturas,1,1);

		$resultados=array();
		$resultados['SumaAdeudosAnteriores']=0;
		$resultados['AdeudoCompleto']=0;
		$resultados['AdeudoAnterior']=0;
		$resultados['AdeudoActual']=0;
		$resultados['MesInicial']="";
		$resultados['MesFinal']="";
		$resultados['MesDeCorte']=Funciones::mesSiguiente($DatosLecturaActual->MesEjercicio);
		$resultados['Consumo']=$DatosLecturaActual->Consumo;
		$resultados['Cuenta']=$DatosLecturaActual->Cuenta;
                //Contrato Vigente
                //Contrato Anterior
                //medidor
                $resultados['ContratoVigente']=$DatosLecturaActual->ContratoVigente;
                $resultados['ContratoAnterior']=$DatosLecturaActual->ContratoAnterior;
                $resultados['Medidor']=$DatosLecturaActual->Medidor;

		$resultados['FechaLectura']=$DatosLecturaActual->FechaLectura;
		$resultados['EstatusPagado']=$DatosLecturaActual->EstatusPagado;
		$resultados['numRecibo']=$DatosLecturaActual->plid;
		$resultados['MesActual']=$DatosLecturaActual->MesEjercicio;
		$resultados['TipoTomaTexto']=$DatosLecturaActual->TipoTomaTexto;

		//precode($DatosLecturaActual,1 );
		if($DatosLecturaActual->M_etodoCobro==2){
			$resultados['LecturaActual']=$DatosLecturaActual->LecturaActual;
			$resultados['LecturaAnterior']=$DatosLecturaActual->LecturaAnterior;
		}else{
			$resultados['LecturaActual']="No aplica";
			$resultados['LecturaAnterior']="No aplica";
		}
        $ejecutaLecturas=DB::select($ConsultaLecturas);

		foreach($ejecutaLecturas as $registroLectura ){
		#	precode($registroLectura,1,1);
			//Aqui veo todas las lecturas

			$datosAdicioaneles=Funciones::ObtieneAdicionales($registroLectura->id, $registroLectura->Consumo, $registroLectura->Tarifa, $registroLectura->Cliente, $registroLectura->BaseCalculo, $registroLectura->ImporteUnitario, $registroLectura->BaseCalculo,  $registroLectura->MesEjercicio);
			#precode($datosAdicioaneles,1);

			if($registroLectura->MesEjercicio==$DatosLecturaActual->MesEjercicio){

			    $resultados['AdeudoActual']=$datosAdicioaneles['SumaCompleta'];
				$resultados['AdeudoCompleto']+=$datosAdicioaneles['SumaCompleta'];

			}
			else{
				if($registroLectura->EstatusPagado!=2){
					if($resultados['MesInicial']==""){
						$resultados['MesInicial']=$datosAdicioaneles['MesEjercicio'];
					}
					$resultados['SumaAdeudosAnteriores']+=$datosAdicioaneles['SumaCompleta'];
					if($datosAdicioaneles['MesEjercicio']>$resultados['MesFinal']){
						$resultados['MesFinal']=$datosAdicioaneles['MesEjercicio'];
					}
					if($datosAdicioaneles['MesEjercicio']<$resultados['MesInicial']){
						$resultados['MesInicial']=$datosAdicioaneles['MesEjercicio'];
					}
					$resultados['AdeudoAnterior']+=$datosAdicioaneles['SumaCompleta'];
					$resultados['AdeudoCompleto']+=$datosAdicioaneles['SumaCompleta'];

				}
			}
		}
		$resultados['MesInicial']=Funciones::convierteMesA_no($resultados['MesInicial']);
		$resultados['MesFinal']=Funciones::convierteMesA_no($resultados['MesFinal']);
		$resultados['MesActual']=Funciones::convierteMesA_no($resultados['MesActual']);
		#precode($resultados,1,1);
		if($resultados['AdeudoAnterior']==0)
			$resultados['Rango']="No hay adeudo";
		else
			$resultados['Rango']=$resultados['MesInicial']." al ".$resultados['MesFinal'];
		return $resultados;
	}//termina funcion obtieneDatosLectura



    public static function mesSiguiente($MesEjercicio){
		$meses = array("", "Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");
		$mes=intval(substr($MesEjercicio, 4,2));
		$a_no=substr($MesEjercicio, 0,4);

 		if ( ($mes+1)==13 ){
			$messiguiente=1;
			$a_nosiguiente=$a_no+1;
		}else{
			$messiguiente=$mes+1;
			$a_nosiguiente=$a_no;
		}
		return $meses[$messiguiente]." ".$a_nosiguiente;
    }

    public static function convierteMesA_no($MesEjercicio){
		$meses = array("", "Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");
		$mes=intval(substr($MesEjercicio, 4,2));
		$a_no=substr($MesEjercicio, 0,4);
		return $meses[$mes]." ".$a_no;
	}

    public static function ObtieneAdicionales($Concepto, $MetrosConsumidos, $Tarifa, $cliente, $BaseCalculo, $ImporteConcepto, $ImporteConsumo, $MesEjercicio){
        $Datos = array();

        $resultadoConsulta = DB::table('ConceptoCobroCaja as c')->select('ra.id', 'ra.Descripci_on',
                'ra.Cri', 'ra.PlanDeCuentas', 'ra.ConceptoCobro', 'ra.Porcentaje', 'ra.Proveedor', 'c2.AplicaIVA'
            )
            ->join('ConceptoRetencionesAdicionales as c1',        'c.id',  '=', 'c1.Concepto')
            ->join('ConceptoRetencionesAdicionalesCliente as c2', 'c1.id', '=', 'c2.ConceptoRetencionesAdicionales')
            ->join('ConceptoAdicionales as c3',                   'c2.id', '=', 'c3.ConceptoRetencionesAdicionalesCliente')
            ->join('RetencionesAdicionales as ra',                'ra.id', '=', 'c1.RetencionAdicional')
            ->where('c2.AplicaEnSubtotal', '0')
            ->where('c3.Cliente', $cliente)
            ->where('c2.Cliente', $cliente)
            ->where('c.id', $Concepto)
            ->distinct()
            ->get();

        $i=0;
        $Datos['MesEjercicio']=$MesEjercicio;
        $Datos['Importe']=$Tarifa;
        $Datos['Subtotal']=$Tarifa;
        $sumaIVA = $Tarifa;
        $Datos['sumaAdicionales']=0;

        foreach ($resultadoConsulta as $filas) {
            $i++;
            $ConceptoOperacion=number_format((floatval(str_replace(",","",$Datos['Importe']))*floatval($filas->Porcentaje / 100 )  ),2);
            $ConceptoOperacion2=(floatval(str_replace(",","",$Datos['Importe']))*floatval($filas->Porcentaje / 100 )  );

            $Datos['Adicional_'.$i]=$ConceptoOperacion;
            $Datos['sumaAdicionales']+=$ConceptoOperacion2;
            $Datos['Subtotal']+=$ConceptoOperacion2;
            if($filas->AplicaIVA==1){
                $sumaIVA+=$ConceptoOperacion2;
            }
        }

        $ResultadoAdicionales = DB::table('ConceptoCobroCaja as c')->select('ra.id', 'ra.Descripci_on',
                'ra.Cri', 'ra.PlanDeCuentas', 'ra.ConceptoCobro', 'ra.Porcentaje', 'ra.Proveedor', 'c2.AplicaIVA'
            )
            ->join('ConceptoRetencionesAdicionales as c1',        'c.id',  '=', 'c1.Concepto')
            ->join('ConceptoRetencionesAdicionalesCliente as c2', 'c1.id', '=', 'c2.ConceptoRetencionesAdicionales')
            ->join('ConceptoAdicionales as c3',                   'c2.id', '=', 'c3.ConceptoRetencionesAdicionalesCliente')
            ->join('RetencionesAdicionales as ra',                'ra.id', '=', 'c1.RetencionAdicional')
            ->where('c2.AplicaEnSubtotal', '1')
            ->where('c3.Cliente', $cliente)
            ->where('c2.Cliente', $cliente)
            ->where('c.id', $Concepto)
            ->distinct()
            ->get();

        foreach ($ResultadoAdicionales as $filas) {
            #return $filas->Porcentaje;
            $i++;
            if($filas->AplicaIVA==1){
                $ConceptoOperacion=number_format((floatval(str_replace(",","",$sumaIVA))*floatval($filas->Porcentaje / 100 )  ),2);
                $ConceptoOperacion2=			(floatval(str_replace(",","",$sumaIVA))*floatval($filas->Porcentaje / 100 )  );
            }else{
                $ConceptoOperacion=number_format((floatval(str_replace(",","",$Datos['Subtotal']))*floatval($filas->Porcentaje / 100 )  ),2);
                $ConceptoOperacion2=             (floatval(str_replace(",","",$Datos['Subtotal']))*floatval($filas->Porcentaje / 100 )  );
            }

            $Datos['Adicional'.$i]=$ConceptoOperacion;
            $Datos['sumaAdicionales']+=$ConceptoOperacion2;
        }

        $Datos['sumaAdicionales'] = str_replace(",","", number_format(floatval(str_replace(",","",$Datos['sumaAdicionales']) ),2 ));
        $Datos['Subtotal'] = str_replace(",","", number_format(floatval(str_replace(",","",$Datos['Subtotal']) ),2 ));
        $Datos['SumaCompleta'] = str_replace(",","", number_format(floatval(str_replace(",","",$Datos['sumaAdicionales']) ) + floatval(str_replace(",","",$Datos['Importe']) ) ,2 ));

        $Datos['cantidadAdicional'] = $i;

        return ($Datos);
    }

    //! +-+-+-+-+ +-+-+-+-+-+-+ +-+-+-+-+
    //! |P|a|r|a| |R|e|c|i|b|o| |A|g|u|a|
    //! +-+-+-+-+ +-+-+-+-+-+-+ +-+-+-+-+

    public static function generaReciboOficialCapaz( $cliente, $idPadron, $a_no, $mes){

       /* $cliente = $request->Cliente;
        $idPadron = $request->Padron;
        $a_no = $request->A_no;
        $mes = $request->Mes;*/
        Funciones::selecionarBase($cliente);

        $contribuyente = PadronAguaPotable::select(
                DB::raw( "(SELECT COALESCE ( CONCAT_WS( ' ', ApellidoPaterno, ApellidoMaterno, Nombres ), NULL ) FROM Contribuyente WHERE Contribuyente.id = Padr_onAguaPotable.Contribuyente ) AS Contribuyente")
            )->where('Cliente', $cliente)->where('id', $idPadron)->first();

        $mesCobro = PadronAguaLectura::where('Padr_onAgua', $idPadron)->orderByDesc('A_no')->orderByDesc('Mes')->value('Mes');

        $lectura = PadronAguaLectura::where('Padr_onAgua', $idPadron)->first();


        $idLectura = $lectura->id;

        $estaPagado = FALSE;

        if (  $lectura->Status == 1 )
            $estaPagado = FALSE;

        if (  $lectura->Status == 2 )
            $estaPagado = TRUE;

        $adeudos = "";
        $adeudo = PadronAguaLectura::select( DB::raw("COUNT(id) AS adeudo") )
                ->where("Padr_onAgua", $idPadron)
                ->where('Status',  '1')
                ->value('adeudo');

        if ($adeudo && $adeudo > 0) {
            if ($adeudo >= 2) {
                $adeudo = $adeudo - 1;
                $adeudos = "Meses adeudo: " . $adeudo;
            }
        }

        $cuentasPapas = DB::table('Padr_onAguaPotable AS p')
            ->select( DB::raw("GROUP_CONCAT(p.id) AS CuentasPapas") )
            ->where("p.id", DB::raw("(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1)") )
            ->where("p.Cliente", $cliente)->value('CuentasPapas');

        $arrayPapas = explode(",", $cuentasPapas);

        $tam = count($arrayPapas);
        $esPapa = FALSE;

        //Recorro todos los papas
        for ($i = 0; $i < $tam; $i++) {
            if ($idPadron == $arrayPapas[$i]) {
                $esPapa = TRUE;
                break;
            }
        }

        $anomalia = PadronAguaLectura::select("pac.descripci_on as descripci_on")
            ->join("Padr_onAguaCatalogoAnomalia as pac", 'Padr_onDeAguaLectura.Observaci_on', '=', 'pac.id')
            ->where("Padr_onDeAguaLectura.id", $idLectura)->value('descripci_on');

        $tieneAnomalia = TRUE;

        if ($anomalia == '') {
            $anomalia = '';
            $tieneAnomalia = FALSE;
        }

        $DatosPadron = PadronAguaPotable::select(
            'd.RFC', 'Padr_onAguaPotable.Ruta', 'Padr_onAguaPotable.Lote',
            'Padr_onAguaPotable.Cuenta', 'Padr_onAguaPotable.Sector', 'Padr_onAguaPotable.Manzana',
            'Padr_onAguaPotable.Colonia', 'Padr_onAguaPotable.Medidor', 'Padr_onAguaPotable.Diametro',
            'Padr_onAguaPotable.TipoToma', 'Padr_onAguaPotable.Domicilio', 'Padr_onAguaPotable.SuperManzana',
            'Padr_onAguaPotable.ContratoVigente', 'd.NombreORaz_onSocial',
            DB::raw("COALESCE ( c.NombreComercial, NULL ) AS NombreComercialPadron"),
            DB::raw("( SELECT Descripci_on FROM Giro g WHERE g.id = Padr_onAguaPotable.Giro ) AS Giro"),
            DB::raw("( SELECT COALESCE ( Nombre, '' ) FROM Municipio m WHERE m.id = d.Municipio ) AS Municipio"),
            DB::raw("( SELECT COALESCE ( Nombre, '' ) FROM EntidadFederativa e WHERE e.id = d.EntidadFederativa ) AS Estado"),
            DB::raw("COALESCE ( CONCAT( c.Nombres, ' ', c.ApellidoPaterno, ' ', c.ApellidoMaterno ), NULL ) AS ContribuyentePadron")
        )
        ->join('Contribuyente as c','c.id','=','Padr_onAguaPotable.Contribuyente')
        ->join('DatosFiscales as d','d.id','=','c.DatosFiscales')
        ->where('Padr_onAguaPotable.id', $idPadron)
        ->first();

        if (! $DatosPadron->ContribuyentePadron || empty($DatosPadron->ContribuyentePadron) || strlen($DatosPadron->ContribuyentePadron) <= 2)
            $contribuyente = utf8_decode($DatosPadron->NombreComercialPadron);
        else
            $contribuyente = utf8_decode($DatosPadron->ContribuyentePadron);

        if (isset($DatosPadron->TipoToma) && $DatosPadron->TipoToma != '')
            $consultaToma = DB::table('TipoTomaAguaPotable')->where('id', $DatosPadron->TipoToma)->value('Concepto');
        else
            $consultaToma = 'NULL';

        if ( !$consultaToma || $consultaToma == '')
            $tipoToma = '0';
        else
            $tipoToma = utf8_decode($consultaToma);

        $folio = PadronAguaPotable::where('id', $idPadron)->value('Cuenta');

        if ($estaPagado) {

            $DatosParaRecibo = Funciones::obtieneDatosLectura($idLectura);

            $mesActual = PadronAguaLectura::select('Mes')->where('id', $idLectura)->value('Mes');
            //return $DatosParaRecibo;

            if ($tieneAnomalia || $esPapa) {
                $lecturaAnterior = $DatosParaRecibo['LecturaAnterior'];
                $lecturaActual   = $DatosParaRecibo['LecturaActual'];
                $lecturaConsumo  = $DatosParaRecibo['Consumo'];
            } else {
                $lecturaAnterior = $DatosParaRecibo['LecturaAnterior'];
                $lecturaActual   = $DatosParaRecibo['LecturaActual'];
                $lecturaConsumo  = $lecturaActual - $lecturaAnterior;
            }
        }else{
            $DatosParaRecibo = $lectura;

            if ($tieneAnomalia || $esPapa) {
                $lecturaAnterior = $DatosParaRecibo->LecturaAnterior;
                $lecturaActual   = $DatosParaRecibo->LecturaActual;
                $lecturaConsumo  = $DatosParaRecibo->Consumo;
            } else {
                $lecturaAnterior = $DatosParaRecibo->LecturaAnterior;
                $lecturaActual   = $DatosParaRecibo->LecturaActual;
                $lecturaConsumo  = $lecturaActual - $lecturaAnterior;
            }
        }
        //return $DatosParaRecibo;
        /*$mesCobro = PadronAguaPotable::select( DB::raw('LPAD(pl.Mes, 2, 0 ) as MesEjercicio') )
            ->join('Padr_onDeAguaLectura as pl','Padr_onAguaPotable.id','=','pl.Padr_onAgua')
            ->join('TipoTomaAguaPotable as t','t.id','=', ( ($DatosPadron->TipoToma == '' || is_null($DatosPadron->TipoToma) ) ? 'Padr_onAguaPotable' : 'pl') . '.TipoToma')
            ->where('pl.id', $idLectura)
            ->orderBy('pl.A_no', 'DESC')
            ->orderBy('pl.Mes', 'DESC')
            ->value('MesEjercicio');
        $a_noCobro = PadronAguaPotable::select('pl.A_no as a_noEjercicio')
            ->join('Padr_onDeAguaLectura as pl','Padr_onAguaPotable.id','=','pl.Padr_onAgua')
            ->join('TipoTomaAguaPotable as t','t.id','=', ( ($DatosPadron->TipoToma == '' || is_null($DatosPadron->TipoToma) ) ? 'Padr_onAguaPotable' : 'pl') . '.TipoToma')
            ->where('pl.id', $idLectura)
            ->orderBy('pl.A_no', 'DESC')
            ->orderBy('pl.Mes', 'DESC')
            ->value('a_noEjercicio');*/

        $mesCobro = $mes;
        $a_noCobro = $a_no;

        $diaLimite = 15;
        if( isset($DatosPadron->TipoToma) && $DatosPadron->TipoToma != '' && $DatosPadron->TipoToma == 4 ){
            $diaLimite = 5;
            $fechaLimite = $diaLimite . '/' . intval($mesCobro + 1) . '/' . date('Y');
            $fechaCorte = $diaLimite + 1 . '/' . intval($mesCobro + 1) . '/' . date('Y');
        }else{
            $fechaLimite = $diaLimite . '/' . intval($mesCobro + 1) . '/' . date('Y');
            $fechaCorte = $diaLimite + 1 . '/' . intval($mesCobro + 1) . '/' . date('Y');
        }

        #return $mesCobro;
        if($mesCobro == 1){
            $fechasPeriodo = PadronAguaLectura::select('FechaLectura')
            ->where('Padr_onAgua', $idPadron)
            ->where(function ($query)  use ($mesCobro) {
                $query->where('Mes', '<=', $mesCobro)
                      ->orWhere('Mes', '=', '12');
            })
            ->where(function ($query)  use ($a_noCobro) {
                $query->where('A_no', '=', $a_noCobro)
                      ->orWhere('A_no', '=', $a_noCobro - 1);
            })
            ->orderBy('id', 'DESC')
            ->take(2)
            ->get();

        }else {

            $fechasPeriodo = PadronAguaLectura::select('FechaLectura')
            ->where('Padr_onAgua', $idPadron)
            ->where('Mes', '<=', $mesCobro)
            ->where('A_no', $a_noCobro)
            ->orderBy('id', 'DESC')
            ->take(2)
            ->get();

        }

        $periodo = '';

        if (count($fechasPeriodo) == 2) {

            $periodo = date_format(new DateTime($fechasPeriodo[1]->FechaLectura), 'd/m/Y') . " a " . date_format(new DateTime($fechasPeriodo[0]->FechaLectura), 'd/m/Y');
        }

        $meses = ["ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE"];

        $DatosHistoricos = PadronAguaLectura::select('Consumo', 'Mes', 'A_no')
            ->where('Mes', $mesCobro)
            ->where('Padr_onAgua', $idPadron)
            ->where('A_no', '<', DB::raw("DATE_FORMAT( CURDATE(), '%Y')" ) )
            ->orderBy('FechaLectura', 'DESC')
            ->take(3)
            ->get();

        $datosHistoricosTabla = '';
        foreach ($DatosHistoricos as $valor) {
            //$lista[] = $fila[$valor->name];
            $datosHistoricosTabla .=
                '<tr>
                    <td>' . $meses[$valor->Mes - 1] . '-' . $valor->A_no . '</td>
                    <td class="derecha">' . intval($valor->Consumo) . ' M3</td>
                </tr>';
        }

        /*$Cotizaciones = DB::table('Cotizaci_on')->select( DB::raw('GROUP_CONCAT(id) as Cotizaci_ones') )
            ->where('Padr_on', $idPadron)
            ->where(DB::raw('( SELECT sum( importe ) FROM ConceptoAdicionalesCotizaci_on WHERE ConceptoAdicionalesCotizaci_on.Cotizaci_on = Cotizaci_on.id AND Padre IS NULL )'), '>', '0')
            ->groupBy('Padr_on')
            ->orderBy('id', 'DESC')
            ->value('Cotizaci_ones');*/

        $Cotizaciones = DB::table('Cotizaci_on')->select( DB::raw('GROUP_CONCAT(id) as Cotizaci_ones') )
            ->where('Padr_on', $idPadron)
            ->where(DB::raw('( SELECT sum( importe ) FROM ConceptoAdicionalesCotizaci_on ca WHERE ca.Cotizaci_on = Cotizaci_on.id AND ca.Padre IS NULL AND ca.A_no ='.$a_no.' AND ca.Mes = '.$mes.')'), '>', '0')
            ->groupBy('Padr_on')
            ->orderBy('id', 'DESC')
            ->value('Cotizaci_ones');

        #return $Cotizaciones;

        if( $estaPagado ) goto sinCalcular;

        if ($Cotizaciones == '') {
            return "Sin Cotizaciones...";
        }

        $DescuentoGeneralCotizaciones = 0;
        $SaldoDescontadoGeneralTodo   = 0;

        $Descuentos = Funciones::ObtenerDescuentoConceptoRecibo($Cotizaciones);
        $SaldosActuales = Funciones::ObtenerDescuentoPorSaldoAnticipadoRecibo($Cotizaciones, $Descuentos['ImporteNetoADescontar'], $Descuentos['Conceptos'], $cliente);

        $ConsultaConceptos="SELECT c.id as idConceptoCotizacion, co.id as ConceptoCobro, co.Cantidad as Cantidad,
                c.Descripci_on as NombreConcepto, co.Importe as total, co.MontoBase as punitario,
                (select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional, co.A_no, COALESCE(co.Mes, '01') as Mes, ct.Tipo, c.TipoToma
            FROM ConceptoAdicionalesCotizaci_on co
                INNER JOIN Cotizaci_on ct ON ( co.Cotizaci_on = ct.id  )
                INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id  )
            WHERE  co.Cotizaci_on IN( " .$Cotizaciones. ") and Estatus=0 ORDER BY co.A_no DESC, COALESCE(co.Mes, '01') DESC ,  co.id ASC ";

        $ResultadoConcepto=DB::select($ConsultaConceptos);

        setlocale(LC_TIME, "es_MX.UTF-8");

        $ConceptosCotizados  = '';
        $totalConcepto       = 0;
        $indexConcepto       = 0;
        $sumaSaldos          = 0;
        $sumaRecargos        = 0;
        $sumaDescuentos      = 0;
        $sumaTotalFinal      = 0;
        $sumaActualizaciones = 0;

        $RegistroConcepto = $ResultadoConcepto[0];

        $consumoMesActual = array();

        if (empty($RegistroConcepto->Adicional)) {
            $consumoMesActual["Consumo"] = str_replace(",", "", $RegistroConcepto->total);
        } else {
            $consumoMesActual[$RegistroConcepto->Adicional] = str_replace(",", "", $RegistroConcepto->total);
        }

        $ActualizacionesYRecargosFunciones = Funciones::ObtenerRecargosYActualizacionesPorConceptoRecibo($RegistroConcepto->ConceptoCobro, $Cotizaciones, $cliente);

        $importeNeto = $sub_total = str_replace(",", "", $RegistroConcepto->total);

        $Actualizaci_on = str_replace(",", "", $ActualizacionesYRecargosFunciones['Actualizaciones']);
        $Recargos = str_replace(",", "", $ActualizacionesYRecargosFunciones['Recargos']);

        $DescuentoGeneralCotizaciones += $Descuento = str_replace(",", "", $Descuentos[$RegistroConcepto->ConceptoCobro]);
        $SaldoDescontadoGeneralTodo += $saldo = str_replace(",", "",$SaldosActuales[$RegistroConcepto->ConceptoCobro]);
        #$sub_total = ($sub_total + $Actualizaci_on + $Recargos) - $Descuento - $saldo;
        $sub_total = ($sub_total);
        #$Auxiliar = "Importeneto=" . $importeNeto . " Actualizacion = " . $Actualizaci_on . " Recargos = " . $Recargos . " Descuento = " . $Descuento . " Saldo Descontado = " . $saldo . " Total a Pagar = " . $sub_total ;
        $sumaActualizaciones=str_replace(",", "",$sumaActualizaciones);
        $sumaRecargos=str_replace(",", "",$sumaRecargos);
        $sumaActualizaciones += $Actualizaci_on;
        $sumaRecargos += $Recargos;

        $Descuento = number_format(str_replace(",", "",$Descuentos[$RegistroConcepto->ConceptoCobro]), 2);
        $sumaDescuentos=str_replace(",", "",$sumaDescuentos);
        $sumaDescuentos += str_replace(",", "",$Descuento);
        $sumaSaldos=str_replace(",", "",$sumaSaldos);
        $sumaSaldos += str_replace(",", "",$saldo);

        $subtotal = number_format(str_replace(",", "", $sub_total) , 2);
        $subtotal = str_replace(",", "", $subtotal);
        $sumaTotalFinal = str_replace(",", "",$sumaTotalFinal);
        $sumaTotalFinal += $subtotal+$Recargos+$Actualizaci_on;

        $totalConcepto                                  = $RegistroConcepto->total;
        $ConceptosCotizados                             = $RegistroConcepto->idConceptoCotizacion . ',';
        $ConceptoPadre[$indexConcepto]['id']            = $RegistroConcepto->idConceptoCotizacion;
        $ConceptoPadre[$indexConcepto]['Mes']           = $RegistroConcepto->Mes;
        $ConceptoPadre[$indexConcepto]['A_no']          = $RegistroConcepto->A_no;
        $ConceptoPadre[$indexConcepto]['Total']         = 0;
        $ConceptoPadre[$indexConcepto]['Nombre']        = $RegistroConcepto->NombreConcepto;
        $ConceptoPadre[$indexConcepto]['TipoPredio']    = $RegistroConcepto->Tipo;
        $ConceptoPadre[$indexConcepto]['idConcepto']    = $RegistroConcepto->ConceptoCobro;
        $ConceptoPadre[$indexConcepto]['FechaConcepto'] = $RegistroConcepto->A_no . "-" . $RegistroConcepto->Mes . "-01";

        $conceptosNombresMes     = array(); #Conceptos del mes actual
        $adicionalesNombresMes   = array(); #Adicionales del mes actual
        $conceptosNombres        = array(); #Conceptos meses de adeudo - Consumo
        $adicionalesNombres      = array(); #Adicionales nombres meses de adeudo
        $adicionalesValores      = array(); #Adicionales valores meses de adeudo
        $conceptosOtrosNombres   = array(); #Otros conceptos nombres
        $conceptosOtrosValores   = array(); #Otros conceptos valores
        $adicionalesOtrosNombres = array(); #Otros adicionales nombres
        $adicionalesOtrosValores = array(); #Otros adicionales valores
        $recargosActualizaciones = array(); # Recargos y actualizaciones

        $sumaConceptosA = 0;
        $sumaAdicionalesA = 0;

        $i = 0;
        foreach($ResultadoConcepto as $RegistroConcepto) {
            if($i != 0){
                $ActualizacionesYRecargosFunciones = Funciones::ObtenerRecargosYActualizacionesPorConceptoRecibo($RegistroConcepto->ConceptoCobro, $Cotizaciones, $cliente);

                $importeNeto = $sub_total = str_replace(",", "", $RegistroConcepto->total);

                $Actualizaci_on = str_replace(",", "", $ActualizacionesYRecargosFunciones['Actualizaciones']);
                $Recargos = str_replace(",", "", $ActualizacionesYRecargosFunciones['Recargos']);

                $DescuentoGeneralCotizaciones += $Descuento = str_replace(",", "", $Descuentos[$RegistroConcepto->ConceptoCobro]);
                $SaldoDescontadoGeneralTodo += $saldo = str_replace(",", "",$SaldosActuales[$RegistroConcepto->ConceptoCobro]);
                #$subTotalRecibo = $sub_total - $Descuento - $saldo;
                #$sub_total = ($sub_total + $Actualizaci_on + $Recargos) - $Descuento - $saldo;
                $sub_total = ($sub_total);
                $sub_total=str_replace(",", "", $sub_total);
                #$Auxiliar = "Importeneto=" . $importeNeto . " Actualizacion = " . $Actualizaci_on . " Recargos = " . $Recargos . " Descuento = " . $Descuento . " Saldo Descontado = " . $saldo . " Total a Pagar = " . $sub_total ;
                $sumaActualizaciones=str_replace(",", "",$sumaActualizaciones);
                $sumaRecargos=str_replace(",", "",$sumaRecargos);
                $sumaActualizaciones += $Actualizaci_on;
                $sumaRecargos += $Recargos;
                #$Actualizaci_on = number_format($ActualizacionesYRecargosFunciones['Actualizaciones'], 2);
                #$Recargos = number_format($ActualizacionesYRecargosFunciones['Recargos'], 2);

                $Descuento = number_format($Descuentos[$RegistroConcepto->ConceptoCobro], 2);
                $sumaDescuentos += $Descuento;
                $sumaSaldos = str_replace(",", "",$sumaSaldos);
                $sumaSaldos += str_replace(",", "",$saldo);

                $subtotal = number_format(str_replace(",", "", $sub_total) , 2);
                $subtotal = str_replace(",", "", $subtotal);
                $sumaSaldos =str_replace(",", "", $sumaSaldos);
                $sumaTotalFinal += $subtotal+$Recargos+$Actualizaci_on;

                if (empty($RegistroConcepto->Adicional)) {
                    //Es concepto
                    $ConceptoPadre[$indexConcepto]['Total'] = $totalConcepto;

                    $totalConcepto = $RegistroConcepto->total;
                    $indexConcepto++;

                    $ConceptoPadre[$indexConcepto]['id']            = $RegistroConcepto->idConceptoCotizacion;
                    $ConceptoPadre[$indexConcepto]['Mes']           = $RegistroConcepto->Mes;
                    $ConceptoPadre[$indexConcepto]['A_no']          = $RegistroConcepto->A_no;
                    $ConceptoPadre[$indexConcepto]['Nombre']        = $RegistroConcepto->NombreConcepto;
                    $ConceptoPadre[$indexConcepto]['TipoPredio']    = $RegistroConcepto->Tipo;
                    $ConceptoPadre[$indexConcepto]['idConcepto']    = $RegistroConcepto->ConceptoCobro;
                    $ConceptoPadre[$indexConcepto]['FechaConcepto'] = $RegistroConcepto->A_no . "-" . $RegistroConcepto->Mes . "-01";

                    if (empty($RegistroConcepto->TipoToma)) {
                        $conceptosOtrosNombres[] = $RegistroConcepto->NombreConcepto;
                        $conceptosOtrosValores[] = $subtotal;
                    }else {
                        if ($RegistroConcepto->A_no == $a_noCobro && $RegistroConcepto->Mes == $mesCobro) {
                            $conceptosNombresMes[$RegistroConcepto->ConceptoCobro] = $subtotal;
                        } else {
                            $sumaConceptosA = str_replace(",", "", $sumaConceptosA);
                            $sumaConceptosA += $subtotal; //Para el consumo de meses anteriores
                        }
                    }
                }else {
                    //Es adicional
                    $totalConcepto += $RegistroConcepto->total;

                    if (empty($RegistroConcepto->TipoToma)) {
                        $adicionalesOtrosNombres[] = $RegistroConcepto->Adicional;
                        $adicionalesOtrosValores[] = $subtotal;
                    } else {
                        if ($RegistroConcepto->A_no == $a_noCobro && $RegistroConcepto->Mes == $mesCobro) {
                            $adicionalesNombresMes[$RegistroConcepto->Adicional] = $subtotal;
                        } else {
                            $adicionalesNombres[] = $RegistroConcepto->Adicional;
                            $adicionalesValores[] = $subtotal;
                        }
                    }
                }
                $ConceptosCotizados .= $RegistroConcepto->idConceptoCotizacion . ',';
            }
            $i++;
        }

        if($SaldoDescontadoGeneralTodo > 0 && $SaldoDescontadoGeneralTodo <= $sumaTotalFinal)
            $sumaTotalFinal=str_replace(",", "",$sumaTotalFinal)-str_replace(",", "",$SaldoDescontadoGeneralTodo)-str_replace(",", "",$sumaDescuentos);
        else if($SaldoDescontadoGeneralTodo>=$sumaTotalFinal)
            $sumaTotalFinal=0;
        else if($SaldoDescontadoGeneralTodo==0)
            $sumaTotalFinal = str_replace(",", "",$sumaTotalFinal) -str_replace(",", "",$sumaDescuentos);

        if ($sumaConceptosA > 0) {
            $conceptosNombres["Consumo"] = $sumaConceptosA;
        }

        $contar = array();
        $i = 0;
        foreach ($adicionalesNombres as $value) {
            if (isset($contar[$value])) {
                // si ya existe, le añadimos uno
                $contar[$value] = str_replace(",", "", $contar[$value]);
                $contar[$value] += str_replace(",", "", $adicionalesValores[$i]);
            } else {
                // si no existe lo añadimos al array
                $contar[$value] = str_replace(",", "", $adicionalesValores[$i]);
            }
            $i++;
        }

        $conceptosOtros = array();
        $j = 0;
        foreach ($conceptosOtrosNombres as $value) {
            $concepto = str_replace(",", "", $value);

            if( isset( $conceptosOtros[$concepto] ) )
                $conceptosOtros[ $concepto ] += str_replace(",", "",$conceptosOtrosValores[$j]);
            else
                $conceptosOtros[ $concepto ] = str_replace(",", "",$conceptosOtrosValores[$j]);

            $j++;
        }

        $adicionalesOtros = array();
        $k = 0;
        foreach ($adicionalesOtrosNombres as $value) {
            $adicional = str_replace(",", "", $value);
            $adicional = str_replace("%", "", $adicional);

            if ( isset($adicionalesOtros[$adicional]) )
                $adicionalesOtros[$adicional] += str_replace( ",", "", $adicionalesOtrosValores[$k] );
            else
                $adicionalesOtros[$adicional] = str_replace( ",", "", $adicionalesOtrosValores[$k] );

            $k++;
        }

        $ConceptoPadre[$indexConcepto]['Total'] = $totalConcepto;
        $ConceptosCotizados = substr_replace($ConceptosCotizados, '', -1);

        //uno los arrays
        $array_mes    = array_merge($consumoMesActual, $adicionalesNombresMes);
        $array_otros  = array_merge($conceptosOtros, $adicionalesOtros);
        $array_rezago = array_merge($conceptosNombres, $contar);

        $totalMes       = 0;
        $totalOtros     = 0;
        $totalRezago    = 0;
        $totalFinal     = $sumaTotalFinal;
        $totalesFinales = $sumaActualizaciones + $sumaRecargos;

        $FilaConceptosTotales = "<br>";
        if (empty($array_rezago)) {
            foreach ($array_mes as $key => $value) {
                $FilaConceptosTotales .= '<tr>
                <td colspan="2">' . utf8_decode($key) . '</td>
                <td class="centrado">' . (number_format($value, 2)) . '</td>
                <td class="centrado">-</td>
                <td class="centrado">' . (number_format($value, 2)) . '</td>
            </tr>';
                $totalMes    += str_replace(",", "", $value);
            }
        } else {
            foreach ($array_mes as $key => $value) {
                $FilaConceptosTotales .= '<tr>
                <td colspan="2">' . utf8_decode($key) . '</td>
                <td class="centrado">' . (number_format(str_replace(",", "", $value), 2)) . '</td>
                <td class="centrado">' . (number_format(str_replace(",", "", $array_rezago[$key]), 2)) . '</td>
                <td class="centrado">' . (number_format(str_replace(",", "", $value) + str_replace(",", "", $array_rezago[$key]), 2)) . '</td>
            </tr>';
                $totalMes     = str_replace(",", "", $totalMes);
                $totalMes    += str_replace(",", "", $value);
                $totalRezago  = str_replace(",", "", $totalRezago);
                $totalRezago += str_replace(",", "", $array_rezago[$key]);
            }
        }

        if (!empty($array_otros)) {
            foreach ($array_otros as $key => $value) {
                if ($key === "Alcantarillado planta tratadora con desalinizadora. (Organismo Público)") {
                    $concepto = 'Alcant. PT. Desalizadora';
                } elseif ($key === "Saneamiento planta tratadora con desalinizadora. (Organismo Público)") {
                    $concepto = 'Saneam. PT. Desalizadora';
                } else
                    $concepto = utf8_decode ($key);

                $FilaConceptosTotales .= '<tr>
                <td colspan="2">' . (substr($concepto, 0, 44)) . '</td>
                <td class="centrado"></td>
                <td class="centrado"></td>
                <td class="centrado">' . (number_format($value, 2)) . '</td>
            </tr>';
                $totalOtros = str_replace(",", "", $totalOtros);
                $totalOtros +=  str_replace(",", "", $value);
            }
        }

        $descuentos  = PadronAguaPotable::select('PrivilegioDescuento')
            ->where('id', $idPadron)
            ->where('PrivilegioDescuento', '!=', '0')
            ->value('PrivilegioDescuento');

        $esDescuento = FALSE;
        $descuento   = 0;
        if ($descuentos != "") {
            #$cantidad * $porcentaje / 100
            $esDescuento = TRUE;
        } else
            $esDescuento = FALSE;

        sinCalcular:

        $fechaActual = date("Y-m-d");
        $auxMes = array("", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");

        if ($estaPagado) {
            $totalFinal = str_replace(",", "", $DatosParaRecibo['AdeudoActual']);
            $FilaConceptosTotales = '<tr>
                <td colspan="2">Adeudo Completo</td>
                <td class="centrado"></td>
                <td class="centrado"></td>
                <td class="centrado">' . (number_format(str_replace(",", "", $totalFinal), 2)) . '</td>
            </tr>';

            $totalRezago = 0;
            $totalMes = 0;

            goto finCalculos;
        }

        $saldo = DB::table('Padr_onAguaHistoricoAbono')->select('SaldoNuevo')->where('idPadron', $idPadron)->orderByDesc('id')->value('SaldoNuevo');

        $saldoNuevo = 0;
        if($saldo != ""){
            //Si el saldo es menor a lo que se debe
            if($saldo < $sumaSaldos){
                $saldoNuevo = 0;
            }
            #Si el saldo es mayor a lo se se debe
            if($saldo > $totalFinal){
                $saldoNuevo = $saldo - $sumaSaldos;
            }
        }

        $estaAjustado = FALSE;
        /*$decimales = 0;
        if (is_float($totalFinal) && $totalFinal > 0) {
            #En caso de que el total sea decimal - Se toma el numero despues del punto
            $exp = explode(".", $totalFinal);
            #Se asigna el numero tomado
            if(isset($exp[1]))
                $decimales = "0." . $exp[1];
            else
                $decimales = "0";
        }

        $estaAjustado = FALSE;
        $ajuste = 0;
        $ajusteFinal = 0;

        if ( is_float( $totalFinal ) && $totalFinal > 0 ){
            $ajuste = $decimales;
            $ajusteFinal = intval($totalFinal);
            $estaAjustado = TRUE;
        }else{
            $ajusteFinal = str_replace(",", "",$totalFinal);
        }*/

        #$totalFinal = intval( $totalFinal );

        if ($totalesFinales > 0) {
            $totalRezago += str_replace(",", "", $totalesFinales);
            $FilaConceptosTotales .= '<tr>
                        <td colspan="2">Actualizaciones y Recargos</td>
                        <td></td>
                        <td class="centrado">
                            ' . number_format($totalesFinales, 2) . '
                        </td>
                        <td class="centrado">
                            ' . number_format($totalesFinales, 2) . '
                        </td>
                    </tr>';
        }

        if ($estaAjustado) {
            $FilaConceptosTotales .= '<tr>
                        <td colspan="2">Redondeo
                        </td>
                        <td></td>
                        <td></td>
                        <td class="centrado">
                            ' . $ajuste . '
                        </td>
                    </tr>';
        }

        if ($saldo != "NULL" && $saldoNuevo > 0) {
            $FilaConceptosTotales .= '<tr>
                        <td colspan="2">Saldo disponible</td>
                        <td></td>
                        <td></td>
                        <td class="centrado">
                            ' . number_format($saldoNuevo, 2) . '
                        </td>
                    </tr>';
        }

        if ($sumaSaldos > 0) {
            $FilaConceptosTotales .= '<tr>
                        <td colspan="2">Aplicacion Ingresos Cobrados por Anticipado</td>
                        <td></td>
                        <td></td>
                        <td class="centrado">-
                            ' . $sumaSaldos . '
                        </td>
                    </tr>';
        }

        $descNombre = "";
        if ($esDescuento && $sumaDescuentos > 0) {
            if ($descuentos == 1)
                $descNombre = "INAPAM";

            if ($descuentos == 2)
                $descNombre = "Pensionados y Jubilados";

            $FilaConceptosTotales .= '<tr>
                        <td colspan="2">Descuento: ' . $descNombre . '</td>
                        <td></td>
                        <td> </td>
                        <td  class="centrado">-
                            ' . $sumaDescuentos . '
                        </td>
                    </tr>';
        }

        #$totalFinal = str_replace(",", "", $ajusteFinal);
        $totalFinal = str_replace(",", "", $totalFinal);

        finCalculos:

        if( $estaPagado )
            $totalFinal = 0;

        if($totalFinal == 0){
            $letras = "Cero pesos M. N.";
        }else{
            $letras = utf8_decode(Funciones::num2letras($totalFinal, 0, 0) . " pesos");
            $ultimoArr = explode(".", number_format($totalFinal, 2)); //recupero lo que este despues del decimal
            $ultimo = $ultimoArr[1];
            if ($ultimo == "")
                $ultimo = "00";
            $letras = $letras . " " . $ultimo . "/100 M. N.";
        }

        if( ($estaPagado && $Cotizaciones == "NULL") )
            return "";

        $nombreComercial = $DatosPadron["NombreORaz_onSocial"];
        if( strlen($nombreComercial) > 0 && strlen($nombreComercial) > 55 ){
            $nombreComercial = substr($nombreComercial, 0, strlen($nombreComercial) / 2) . '<br>' . substr($nombreComercial, strlen($nombreComercial) / 2, strlen($nombreComercial) );
        }

        $rutaBarcode = 'https://suinpac.piacza.com.mx/lib/barcode2.php?f=png&text=' . (isset($DatosPadron['ContratoVigente']) ? $DatosPadron['ContratoVigente'] : '') . '&codetype=Code128&orientation=Horizontal&size=40&print=false "';

        $DIR_RESOURCE = 'recursos/';
        $DIR_IMG = 'imagenes/';

       // return $letras;
        $htmlGlobal = '<style>
        .centrado {
            text-align: center;

        }
        .derecha {
            text-align: right;
        }
        .letras{
            font-family: "Arial", serif;
            font-size: 6pt;
        }
        .numeros{
            font-family: "Arial", serif;
            font-size: 6pt;
        }
        td {
            font-family: "Arial";
            font-size: 10pt;
        }
        th {
            font-family: "Arial", serif;
            font-size: 12pt;
        }
        .negritas{
            font-family: "Arial", serif;
            font-size: 8pt;
            font-weight: bold;
        }
        .total{
            font-family: "Arial", serif;
            font-size: 18pt;
            font-weight: bold;
        }
        table {
            position: relative;
        }
        .sobre {
            position:absolute;
            top:0px;
            left:10px;
            border:none;
        }
        .sobre2 {
            position:absolute;
            top:-15px;
            left:20px;
            border:none;
        }
        .marca {
            position: absolute;
            z-index: 1;
            content: "PAGADO";
            font-size: 53px;
            color: rgba(52, 166, 214, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            position: fixed;
            top: 250;
            right: 0;
            bottom: 0;
            left: 0;
        }
        .marco-turquesa{
            background:#01cbe3;
            border-radius: 15px;
            display: block;
            height: 20px;
            width: 150px;
            margin: 0 auto;
            text-align: center;
            padding-top:5px;
        }
        .marco-borde-izq{
            background:#01cbe3;
            border-radius: 15px 0px 0 15px;
            display: block;
            height: 20px;
            margin: 0 auto;
            text-align: center;
            padding-top:5px;
        }
        .marco-borde-der{
            background:#01cbe3;
            border-radius: 0px 15px 15px 0px;
            display: block;
            height: 20px;
            margin: 0 auto;
            text-align: center;
            padding-top:5px;
        }
        .marco-sin-borde{
            background:#01cbe3;
            display: block;
            height: 20px;
            margin: 0 auto;
            text-align: center;
            padding-top:5px;
        }
        .marco-sin-color{

            border-radius: 15px;
            display: block;
            height: 20px;
            width: 80px;
            margin: 0 auto;
            text-align: center;
            padding-top:5px;
        }

        .marco-turquesa-derecha{
            background:#01cbe3;
            border-radius: 15px;
            display: block;
            height: 20px;
            width: 150px;
            text-align: center;
            padding-top:5px;
        }
        .marco-turquesa-derecha-sin-relleno{
            border-style: solid;
            border-color: #01cbe3;
            border-radius: 15px;
            display: block;
            height: 20px;
            width: 150px;
            text-align: center;
            padding-top:5px;
        }
        .marco-derecha{
            display: block;
            margin:0px;
            padding:0px;
            width: 150px;
            text-align: center;

        }

        .marco-folio{
            background:#A9E2F3;
            border-radius: 15px;
            display: irun-in;
            height: 20px;
            width: 70px;
            text-align: center;
            padding-top:5px;
        }
        .color-gris{
            color:#585858;
            font-weight: bold;
        }
        .letras-resaltadas{
            font-weight: bold;
            font-size: 12pt;
        }
        .flex-container {
            width : 100%;
            background-color : #01cbe3;
            display : flex;
            flex-direction : row;
            flex-wrap : wrap;
            border-radius: 15px;
            height: 25px;
        }

        .flex-items {
            background-color : #01cbe3;
            flex-basis : 21%;
            flex-grow : 1;
            padding : 1%;
            margin : 1%;
            border-radius: 15px;
            height: 5px;
        }

        .flex-container-sin-color {
            width : 100%;
            display : flex;
            flex-direction : row;
            flex-wrap : wrap;
            border-radius: 15px;
            height: 25px;
            margin-top: -11px;
            margin-bottom: -4px;
        }

        .flex-items-sin-color {
            flex-basis : 21%;
            flex-grow : 1;
            padding : 1%;
            margin : 1%;
            border-radius: 15px;
        }

         .portada{
            width:100%;
            height:95%;
            position:absolute;
         }
         .azul{
            background:#A9E2F3;
            width:100%;
            height:80px;
            top:60%;
            opacity: 0.5;
            position:absolute;


        }
        .sin-espacio{
            padding:0px;
            margin:0px;
        }


        .es{

            height:140px;
            width:630px;
            color: red;
            margin:75px;
            opacity: 0.5;
            font-size: 130px;
            font-weight: bold;
            position:absolute;
            top:55%;
            left:8%;
            border-style: solid;
            border-color: red;
        }
        .inline-block{
            display:-moz-inline-stack;
            display:inline-block;
            zoom:1;
            *display:inline;
        }



    </style>

		<img  class="portada" src="'.asset(Storage::url( $DIR_IMG.'reciboCapazFondo.png')).'">
        <div class="azul"></div>

    <div>
    ' . ($estaPagado ? '<div> <span class="es inline-block">PAGADO</span></div>' : '') . '
        <table border="0" width="100%">
            <tr>
                <td rowspan="2" width="45%">
                    <img alt="Smiley face" height="100" width="300" src="'.asset(Storage::url( $DIR_IMG.'capazlogo.png')).'">
                </td>
                <td width="30%" align="right">
                     </br>
                     <span><span class="color-gris marco-derecha">Adeudo anterior</span><span class="marco-turquesa-derecha">' . number_format($totalRezago, 2, '.', ',') . '</span></span>
                </td>
                <td colspan="2" width="10%" class="centrado">
                      </br>
                      <span class="color-gris">Importe del mes</span><span class="marco-turquesa">' . number_format($totalMes, 2, '.', ',') . '</span>
                </td>
                <td width="20%" align="right" class="marco-derecha" >
                      </br>
                      <span class="color-gris">Total a pagar</span><span class="marco-turquesa-derecha">' .floor($totalFinal). '</span>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="centrado" >
                     <span class="color-gris">Fecha l&iacute;mite de pago</span><span style="display: block;">' . $fechaLimite . '</span>

                </td>
                <td colspan="2" class="centrado" >
                     <span class="color-gris">Periodo</span><span style="display: block;">' . ($estaPagado ? $auxMes[$mesActual] : $auxMes[$DatosParaRecibo["Mes"]]) . '</span>
                </td>
            </tr>

            <tr>
                <td  class="letras-resaltadas" colspan="3" >
                  ' . ( ( strlen($contribuyente) <= 70 ) ? $contribuyente : substr($contribuyente, 0, 75) ) .'
                </td>
                <td rowspan="2" colspan="2" align="right">
                    <span  class="color-gris marco-derecha" >N&#176; de contrato </span><span class="marco-turquesa-derecha">' . (isset($DatosPadron["ContratoVigente"]) ? intval($DatosPadron["ContratoVigente"]) : '') . '</span>
                </td>
            </tr>
            <tr>
                    <td colspan="3" class="letras-resaltadas">
                    ' . utf8_decode(( ( strlen(utf8_decode($DatosPadron["Domicilio"]) <= 70 ) ? $DatosPadron["Domicilio"] : substr($DatosPadron["Domicilio"], 0, 75) ))) . '
                    </td>
            </tr>
            <tr>
                    <td colspan="3" class="letras-resaltadas">
                       Col. ' . (isset($DatosPadron["Colonia"]) ? strlen(utf8_decode($DatosPadron["Colonia"]) <= 70 ) ? utf8_decode($DatosPadron["Colonia"]) : substr(utf8_decode($DatosPadron["Colonia"]), 0, 75) : '<br>')  . '

                    </td>
                    <td rowspan="2" colspan="2" class="derecha" >
                        <div>
                        <img  width="220" height="30" src="' . $rutaBarcode . '" >
                        </div>
                    </td>
            </tr>
            <tr>
               <td colspan="3" class="letras-resaltadas"> '.(isset($DatosPadron["SuperManzana"]) ? 'S. Mza. ' . $DatosPadron["SuperManzana"] : '') . (isset($DatosPadron["Manzana"]) ? '&nbsp;&nbsp;&nbsp;S. Mza. ' . $DatosPadron["Manzana"] : '') . (isset($DatosPadron["Lote"]) ? '&nbsp;&nbsp;&nbsp;Lote ' . $DatosPadron["Lote"] : '').'</td>

            </tr>
            <tr>
                    <td colspan="3">
                         <table border=0 width="100%" >
                            <tr class="centrado color-gris">
                                <td>N&#176; de medidor</td>
                                <td>Di&aacute;metro</td>
                                <td>Tipo de serv.</td>
                            </tr>
                            <tr>
                                <td class="sin-espacio"> <span class="marco-borde-izq"> ' . (isset($DatosPadron["Medidor"]) ? intval($DatosPadron["Medidor"]) : '') . '</span></td>
                                <td class="sin-espacio"><span class="marco-sin-borde"> ' . (isset($DatosPadron["Diametro"]) ? $DatosPadron["Diametro"] : '') . '</span></td>
                                <td class="sin-espacio"><span class="marco-borde-der">' . $tipoToma . '</span></td>
                            </tr>
                         </table>
                    </td>
                    <td rowspan="2" colspan="2" align="right">
                       <span class="color-gris marco-derecha">N&#176; de folio </span><span class="marco-turquesa-derecha-sin-relleno"><span class="color-gris" style="font-size:15px;">E</span><font color="red" size=3>'.$idLectura.'</font></span>
                     </td>
            </tr>
            <tr>
               <td colspan="3">
                     <table border=0 width="100%">
                            <tr class="centrado color-gris">
                                <td>Lectura Anterior</td>
                                <td> Lectura Actual</td>
                            </tr>
                            <tr>
                                <td class="sin-espacio"> <span class="marco-borde-izq">' . intval($lecturaAnterior) . '</span></td>
                                <td class="sin-espacio"><span class="marco-borde-der"> ' . intval($lecturaActual) . '</span></td>
                            </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="3">

                    <table border=0 width="100%" >
                            <tr class="centrado color-gris">
                                <td> Consumo M3</td>
                                <td>Fecha de corte del mes</td>
                            </tr>
                            <tr>
                                <td class="sin-espacio"> <span class="marco-borde-izq">' .intval($lecturaConsumo). '</span></td>
                                <td class="sin-espacio"><span class="marco-borde-der"> ' . $fechaCorte . '</span></td>
                            </tr>
                    </table>
                </td>
                <td colspan="2" class="derecha">
                    ' . utf8_decode($anomalia)  . '</br>' . $adeudos  . '
                </td>
            </tr>

            <tr>

                <td colspan="5" >

                        <tr >
                            <td colspan="2" class="color-gris"> Descripci&oacute;n del concepto</td>
                            <td colspan="3" class="color-gris centrado">C&aacute;lculo de su facturaci&oacute;n</td>
                        </tr>

                        <tr>
                            <td colspan="2"><br></td>
                            <td>Mes</td>
                            <td>Rezago</td>
                            <td>Total</td>
                        </tr>' . $FilaConceptosTotales . '

                       <tr>
                            <td colspan="2"><br></td>
                            <td  class="centrado"><span class="marco-turquesa">' .  number_format($totalMes, 2, '.', ','). '</span></td>
                            <td  class="centrado" ><span class="marco-turquesa">' . number_format($totalRezago, 2, '.', ',') . '</span></td>
                            <td  class="centrado"><span class="marco-turquesa">' . floor($totalFinal) . '</span></td>

                        </tr>
                        <tr>
                            <td colspan="2"><br></td>
                            <td>Sector</td>
                            <td  class="centrado" >Ruta</td>
                            <td  class="centrado">Prog.</td>
                        </tr>
                        <tr>
                            <td colspan="2"><br></td>
                            <td>' . (isset($DatosPadron["Sector"]) ? $DatosPadron["Sector"] : '') . '</td>
                            <td  class="centrado" >' . (isset($DatosPadron["Ruta"]) ? $DatosPadron["Ruta"] : '') . '</td>
                            <td  class="centrado">' . intval($folio) . '</td>
                        </tr>
                        <tr>
                            <td colspan="2"><br></td>
                            <td>Uso:</td>
                            <td colspan="2">' . $DatosPadron["Giro"] . '</td>
                        </tr>
                        <tr>
                            <td colspan="2"><br></td>
                            <td >Periodo de consumo:</td>
                            <td colspan="2">' . $periodo . '</td>
                        </tr>
                        <tr>
                            <td colspan="5" class="color-gris sin-espacio">Datos fiscales:</td>
                        </tr>
                        <tr>
                            <td colspan="5">
                                <tr>
                                    <td colspan="2">

                                        <table border="0" >
                                            <tbody>
                                                <tr>
                                                    <td>RFC: ' . (isset($DatosPadron["RFC"]) ? $DatosPadron["RFC"] : '') . '</td>
                                                </tr>
                                                <tr >
                                                    <td>'. utf8_decode($nombreComercial) .'</td>
                                                </tr>
                                                <tr>
                                                    <td>
                                                        ( ' . $letras . ' )

                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>

                                    <td colspan="3" class="color-gris">
                                        <table border="0" width="100%">
                                            <tbody class="derecha">
                                                <tr>
                                                    <td >Datos Historicos</td>

                                                </tr>
                                                ' . $datosHistoricosTabla . '
                                            </tbody>
                                        </table>
                                    </td>

                                </tr>
                            </td>
                        </tr>
                </td>
            </tr>



            <tr>

                <td colspan="1">
                </td>

                <td class="centrado">
                    <span class="color-gris">Per&iacute;odo</span><span class="marco-sin-color">' . ($estaPagado ? $auxMes[$mesActual] : $auxMes[$DatosParaRecibo["Mes"]]) . '</span>
                </td>
                <td colspan="2" class="centrado">
                      <span class="color-gris">Importe del mes</span><span class="marco-turquesa">' . number_format($totalMes, 2, '.', ',') . '</span>
                </td>
                <td class="centrado">
                     <span class="color-gris">Total a pagar</span><span class="marco-turquesa">' . floor($totalFinal) . '</span>
                </td>

            </tr>

            <tr>


            <tr>
                <td colspan="5" class="color-gris">Importe con lectura:</td>
            </tr>
            <tr>
                    <td colspan="5">
                        <tr>
                            <td colspan="2">

                                <table border="0" width="100%" cellspacing="0" cellpadding="0">
                                    <tbody>
                                        <tr>
                                            <td colspan=3>
                                                ( ' . $letras . ' )

                                            </td>
                                        </tr>

                                        <tr>
                                            <td colspan=3>
                                            ' . ( ( strlen($contribuyente) <= 70 ) ? $contribuyente : substr($contribuyente, 0, 75) ) .'
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan=3>
                                                ' . utf8_decode(( ( strlen(utf8_decode($DatosPadron["Domicilio"]) <= 70 ) ? $DatosPadron["Domicilio"] : substr($DatosPadron["Domicilio"], 0, 75) )))  . '
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan=3>
                                                Col. ' . (isset($DatosPadron["Colonia"]) ? strlen(utf8_decode($DatosPadron["Colonia"]) <= 70 ) ? utf8_decode($DatosPadron["Colonia"]) : substr(utf8_decode($DatosPadron["Colonia"]), 0, 75) : '<br>')  . '

                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan=3> '.(isset($DatosPadron["SuperManzana"]) ? 'S. Mza. ' . $DatosPadron["SuperManzana"] : '') . (isset($DatosPadron["Manzana"]) ? '&nbsp;&nbsp;&nbsp;S. Mza. ' . $DatosPadron["Manzana"] : '') . (isset($DatosPadron["Lote"]) ? '&nbsp;&nbsp;&nbsp;Lote ' . $DatosPadron["Lote"] : '').'</td>
                                        </tr>
                                        <tr>
                                            <td >Sector</td>
                                            <td class="centrado">Ruta</td>
                                            <td class="centrado">Prog.</td>
                                        </tr>
                                        <tr>
                                            <td >' . (isset($DatosPadron["Sector"]) ? $DatosPadron["Sector"] : '') . '</td>
                                            <td class="centrado">' . (isset($DatosPadron["Ruta"]) ? $DatosPadron["Ruta"] : '') . '</td>
                                            <td class="centrado">' . intval($folio) . '</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>

                            <td colspan="3" class="color-gris centrado">
                                <table border="0" width="100%" cellspacing="0" cellpadding="0">
                                    <tbody>
                                        <tr>
                                            <td align="right" >
                                                 <span  class="color-gris marco-derecha">N&#176; de contrato </span><span class="marco-turquesa-derecha">' . (isset($DatosPadron["ContratoVigente"]) ? intval($DatosPadron["ContratoVigente"]) : '') . '</span>
                                           </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                </br>
                                                <div align="right" padding-top="5px">
                                                <img  width="220" height="25" src="' . $rutaBarcode . '" >
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td rowspan="2" colspan="4" align="right">

                                                <span class="color-gris marco-derecha">N&#176; de folio </span><div class="marco-turquesa-derecha-sin-relleno"><span class="color-gris" style="font-size:15px;">E</span><font color="red" size=3>'.$idLectura.'</font></div>
                                            </td>
                                        </tr>

                                    </tbody>
                                </table>
                            </td>

                        </tr>
                    </td>
            </tr>

        </table>
   <!--de aqui hacia arriba -->

    </div>
';


 // return $htmlGlobal;


        #include_once("libPDF.php");
        include( app_path() . '/Libs/Wkhtmltopdf.php' );
        try {
            $nombre = uniqid() . "_" . $idLectura;
            #$wkhtmltopdf = new Wkhtmltopdf(array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'page_width'=>120,'page_height'=>200,'margins'=>array('top'=>1,'left'=>1,'right'=>1,'bottom'=>1)));
            $wkhtmltopdf = new Wkhtmltopdf(array('path' => 'repositorio/temporal/', 'lowquality' => true, 'margins' => ['top' => 10, 'left' => 10,'right'=>10,'bottom'=>10]));
            $wkhtmltopdf->setHtml($htmlGlobal);
            //$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, $nombre.".pdf");
            $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $nombre . ".pdf");
            //return "repositorio/temporal/" . $nombre . ".pdf";
            return response()->json([
                'success' => '1',
                'ruta' => "repositorio/temporal/" . $nombre . ".pdf",
            ]);
        } catch (Exception $e) {
            echo "<script>alert('Hubo un error al generar el PDF: " . $e->getMessage() . "');</script>";
        }

    }

    /**
     * 61.10    5.21
     * 96.40    5.55
     * 85.70    2.31
     * 69.62    0
     *
     * 312.82   13.07
     */

    public static function ObtenerDescuentoConceptoRecibo($Cotizaci_on){
        $TotalAPagar=0;
        $conceptos="";
        #$TotalCotizaci_on= ObtenValor("SELECT sum(Importe) as Total FROM ConceptoAdicionalesCotizaci_on  WHERE Cotizaci_on IN (".$Cotizaci_on. ") ",'Total');
        $TotalCotizaci_on = DB::table('ConceptoAdicionalesCotizaci_on')->select(DB::raw("sum(Importe) as Total"))
            ->whereIn('Cotizaci_on', explode(',', $Cotizaci_on) )
            ->value('Total');

        $TotalAPagar = $TotalCotizaci_on;
        #$obtenerXMLIngreso = ObtenValor("SELECT * FROM XMLIngreso WHERE idCotizaci_on IN (".$Cotizaci_on.")");
        $obtenerXMLIngreso = DB::table("XMLIngreso")->whereIn('idCotizaci_on', explode(',', $Cotizaci_on) )->take(1)->get();
        #return $obtenerXMLIngreso;

        #$DatosExtraDescuento=json_decode($obtenerXMLIngreso['DatosExtra'], true);
        $DatosExtraDescuento=json_decode($obtenerXMLIngreso[0]->DatosExtra, true);
        #return $DatosExtraDescuento;
        #$ConsultaConeptos = "SELECT id,Importe,Mes,A_no FROM ConceptoAdicionalesCotizaci_on  WHERE Cotizaci_on IN (".$Cotizaci_on.") AND Origen!='PAGO'";
        $ResultadoConceptos = DB::table('ConceptoAdicionalesCotizaci_on')->select('id','Importe','Mes','A_no')
            ->whereIn('Cotizaci_on', explode(',', $Cotizaci_on) )
            ->where('Origen', '!=', 'PAGO')->get();

        #$ResultadoConceptos = $Conexion->query($ConsultaConeptos);
        /*Consulta para ver el Privilegio descuento */
        #$ConsultaPadr_on = "SELECT p.PrivilegioDescuento FROM Padr_onAguaPotable p INNER JOIN Cotizaci_on c ON(p.id=c.Padr_on AND c.Tipo=9) WHERE c.id IN (".$Cotizaci_on.") ";
        #$ResultadoPadr_on= ObtenValor($ConsultaPadr_on,'PrivilegioDescuento');
        $ResultadoPadr_on= PadronAguaPotable::select('Padr_onAguaPotable.PrivilegioDescuento')
            ->join('Cotizaci_on as c', 'Padr_onAguaPotable.id', '=', 'c.Padr_on')
            ->whereIn('c.id', explode(',', $Cotizaci_on))
            ->where('c.Tipo', '9')
            ->value('PrivilegioDescuento');

        if( array_key_exists('Descuento', $DatosExtraDescuento) )
            $TotalAPagar = $TotalAPagar - floatval( $DatosExtraDescuento['Descuento']);

        if((array_key_exists('Descuento', $DatosExtraDescuento) && $DatosExtraDescuento['Descuento']!="" && $DatosExtraDescuento['Descuento'] != 0) || ($ResultadoPadr_on !=0 )){
            if($ResultadoPadr_on == 0){
                $Descuentos          = array();
                $DescuentoAcumulados = 0;
                $Descuento2          = 0;
                $descuento           = 0;
                $ultimoConcepto      = 0;
                $ultimoDescuento     = 0;

                #while ($RegistroConceptos = $ResultadoConceptos->fetch_assoc()) {
                foreach($ResultadoConceptos as $RegistroConceptos){
                    $descuento = number_format(($RegistroConceptos->Importe/$TotalCotizaci_on)*$DatosExtraDescuento['Descuento'], 2);
                    if(isset($RegistroConceptos->A_no) && $RegistroConceptos->A_no!="" && isset($RegistroConceptos->A_no) && $RegistroConceptos->A_no!="")
                        $Descuento2 = Funciones::DescuentoPorMesRecibo($RegistroConceptos->A_no,$RegistroConceptos->Mes,($RegistroConceptos->Importe-$descuento),$Cotizaci_on);

                    return $Descuento2;
                    $DescuentoAcumulados+=$descuento;
                    $arr[$RegistroConceptos->id] =$descuento+$Descuento2;
                    $TotalAPagar=$TotalAPagar-$arr[$RegistroConceptos->id];
                    $conceptos.=$arr[$RegistroConceptos->id].",";
                    $ultimoConcepto=$RegistroConceptos->id;
                    $ultimoDescuento=$descuento+$Descuento2;
                }

                if($DescuentoAcumulados!=$DatosExtraDescuento['Descuento']){
                    $arr[$ultimoConcepto] = $ultimoDescuento-($DescuentoAcumulados-$DatosExtraDescuento['Descuento']);
                }
            }else if($ResultadoPadr_on!=0){

                #while ($RegistroConceptos = $ResultadoConceptos->fetch_assoc()) {
                foreach($ResultadoConceptos as $RegistroConceptos){
                    $Descuento2 = 0;

                    if(isset($RegistroConceptos->A_no) && $RegistroConceptos->A_no!="" && isset($RegistroConceptos->A_no) && $RegistroConceptos->A_no!="")
                        $Descuento2 = Funciones::DescuentoPorMesRecibo($RegistroConceptos->A_no,$RegistroConceptos->Mes,($RegistroConceptos->Importe),$Cotizaci_on);

                    if($Descuento2==0)
                        $Descuento2 = $RegistroConceptos->Importe;

                    $arr[$RegistroConceptos->id] = $RegistroConceptos->Importe - $Descuento2;
                    $TotalAPagar =$TotalAPagar-$arr[$RegistroConceptos->id];
                    $conceptos.=$arr[$RegistroConceptos->id].",";
                }
            }
        }else{
            #while ($RegistroConceptos = $ResultadoConceptos->fetch_assoc()) {
            foreach($ResultadoConceptos as $RegistroConceptos){
                $arr[$RegistroConceptos->id] =0;
                  $conceptos.=$arr[$RegistroConceptos->id].",";
            }
        }

        $arr['ImporteNetoADescontar'] = $TotalAPagar;
        $arr['Conceptos'] = $conceptos;

        return $arr;
    }

    public static function ObtenerDescuentoPorSaldoAnticipadoRecibo($Cotizaci_on,$importe,$Conceptos, $cliente){
        $actualizacionesYRecargos = 0;
        $actualizacionesYRecargos = Funciones::ObtenerRecargosYActualizacionesCotizaci_onRecibo($Cotizaci_on, $cliente);

        $descuentos= explode(',',$Conceptos);
        #$Cotizacion= ObtenValor("SELECT * FROM  Cotizaci_on WHERE id IN( ".$Cotizaci_on. ") ");
        $Cotizacion = DB::table('Cotizaci_on')->whereIn('id', explode(',', $Cotizaci_on) )->take(1)->get()[0];

        $TotalSaldo=0;

        if(isset($Cotizacion->Padr_on) && $Cotizacion->Padr_on!=""){
            if($Cotizacion->Tipo==9){
                #$TotalSaldo = ObtenValor("SELECT SaldoNuevo as Total FROM Padr_onAguaHistoricoAbono  WHERE idPadron=".$Cotizacion->Padr_on." ORDER BY id DESC",'Total');
                $TotalSaldo = DB::table('Padr_onAguaHistoricoAbono')->select(DB::raw("SaldoNuevo as Total") )
                    ->where('idPadron', $Cotizacion->Padr_on)->orderByDesc('id')->value('Total');

                if($TotalSaldo<=0){
                    $TotalSaldo=0;
                }
            }else{
                $TotalSaldo=0;
            }
        }

        #$TotalCotizaci_on= ObtenValor("SELECT sum(Importe) as Total FROM ConceptoAdicionalesCotizaci_on  WHERE Cotizaci_on IN (".$Cotizaci_on. ")  ",'Total');
        $TotalCotizaci_on= DB::table('ConceptoAdicionalesCotizaci_on')->select(DB::raw("sum(Importe) as Total") )
            ->whereIn('Cotizaci_on', explode(',', $Cotizaci_on) )->value('Total');

        $TotalCotizaci_on = str_replace(",", "",$importe)+str_replace(",", "",$actualizacionesYRecargos);
        $TotalCotizaci_on= str_replace(",", "",$TotalCotizaci_on);

        if($TotalSaldo>$TotalCotizaci_on)
            $TotalSaldo=$TotalCotizaci_on;

        #$ConsultaConeptos = "SELECT id,Importe FROM ConceptoAdicionalesCotizaci_on  WHERE Cotizaci_on IN (".$Cotizaci_on.") AND Origen!='PAGO' AND Estatus!=-1 ";
        $ResultadoConceptos = DB::table('ConceptoAdicionalesCotizaci_on')
            ->select('id', 'Importe')
            ->whereIn('Cotizaci_on', explode(',', $Cotizaci_on))
            ->where('Origen', '!=', 'PAGO')
            ->where('Estatus', '!=', '-1')->get();
        #$ResultadoConceptos = $Conexion->query($ConsultaConeptos);

        if($TotalSaldo>0){
            $Descuentos = array();
            $DescuentoAcumulados = 0;
            $contador=0;
            $ultimoConcepto=0;
            $ultimoDescuento=0;
            #while ($RegistroConceptos = $ResultadoConceptos->fetch_assoc()) {
            foreach($ResultadoConceptos as $RegistroConceptos) {
            /*Actualizaci_ones y recargos */
                $ayr = 0;
                $actualizacionesYRecargos = Funciones::ObtenerRecargosYActualizacionesPorConceptoRecibo($RegistroConceptos->id, $Cotizaci_on, $cliente);

                $ayr=$actualizacionesYRecargos['Actualizaciones']+$actualizacionesYRecargos['Recargos'];
                $descuento = number_format(((($RegistroConceptos->Importe+$ayr-$descuentos[$contador])/$TotalCotizaci_on)*$TotalSaldo), 2);
                $DescuentoAcumulados=str_replace(",", "",$DescuentoAcumulados);
                $DescuentoAcumulados+=str_replace(",", "",$descuento);
                $arr[$RegistroConceptos->id] = str_replace(",", "",$descuento);
                $ultimoConcepto=$RegistroConceptos->id;
                $ultimoDescuento=$descuento;
                $contador++;
            }

            if($DescuentoAcumulados!=$TotalSaldo){
                $arr[$ultimoConcepto] = number_format(str_replace(",", "",$ultimoDescuento)-(str_replace(",", "",$DescuentoAcumulados)-str_replace(",", "",$TotalSaldo)),2);
            }

        }else{
            #while ($RegistroConceptos = $ResultadoConceptos->fetch_assoc()) {
            foreach($ResultadoConceptos as $RegistroConceptos) {
                $arr[$RegistroConceptos->id] =number_format(0,2);
            }
        }
        return $arr;
    }

    public static function DescuentoPorMesRecibo($A_noConcepto, $MesConcepto, $MotoNeto, $Cotizaci_on){
        $diaLimite = 18;
        global $Conexion;
        $SeAplicaraElDescuento = 0;
        /*Esto nos servira para hacerle descuento por mes de los que tengan INPAM etc etc etc*/
        #$CotizacionDescuentoPorMes = ObtenValor("SELECT p.PrivilegioDescuento FROM Cotizaci_on c INNER JOIN Padr_onAguaPotable p ON(c.Padr_on=p.id AND c.Tipo=9) WHERE c.id IN (".$Cotizaci_on. ") ");
        $CotizacionDescuentoPorMes = DB::table('Cotizaci_on as c')->select("p.PrivilegioDescuento")
            ->join('Padr_onAguaPotable as p',  'c.Padr_on', '=', 'p.id')
            ->whereIn('c.id', explode(',', $Cotizaci_on))
            ->where('c.Tipo', '9')
            ->value('PrivilegioDescuento');

        $DescuentoPorMes=0;
        #if(isset($CotizacionDescuentoPorMes['PrivilegioDescuento']) && $CotizacionDescuentoPorMes['PrivilegioDescuento']!=0  && $CotizacionDescuentoPorMes['PrivilegioDescuento']!=""){
        if(isset($CotizacionDescuentoPorMes) && $CotizacionDescuentoPorMes !=0  && $CotizacionDescuentoPorMes != ""){
           $SeAplicaraElDescuento = 0;

            $FechaActualParaDescuento = date('Y-m-d');
            $FechaActualParaDescuento = explode('-', $FechaActualParaDescuento);
            $a_noActual = $FechaActualParaDescuento[0];
            $mesActual  = $FechaActualParaDescuento[1];
            $diaActual  = $FechaActualParaDescuento[2];

            if($CotizacionDescuentoPorMes != 0){
                $SeAplicaraElDescuento = 1;
            }
            $A_noADescontar = $a_noActual;
            $MesADescontar =20;
            if($diaActual>$diaLimite && $mesActual == $MesConcepto && $a_noActual == $A_noConcepto ){
                $buscarMesVigente = 1;//Cero de que no va aplicar ya se caduco
                #Los meses se quedan normales
                $MesADescontar =$mesActual;
            }else if($diaActual<$diaLimite && $mesActual-1 == $MesConcepto && $a_noActual == $A_noConcepto){
                $buscarMesVigente =1;
                $MesADescontar =$mesActual-1;
            }
            $DescuentoPorMes = 0;
            if($MesConcepto==$MesADescontar && $A_noConcepto==$A_noADescontar){
                $DescuentoPorMes = $MotoNeto>0?$MotoNeto/2:$MotoNeto;
            }
        }
        return $DescuentoPorMes;
    }

    public static function ObtenerRecargosYActualizacionesCotizaci_onRecibo($Cotizaci_on, $cliente){
        $idCotizacion = $Cotizaci_on;
        /*$ConsultaConceptos = "SELECT c.id as idConceptoCotizacion, co.id as ConceptoCobro, co.Cantidad as Cantidad, c.Descripci_on as NombreConcepto, co.Importe as total, co.MontoBase as punitario,
                (select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional, co.A_no, COALESCE(co.Mes, '01') as Mes, ct.Tipo, c.TipoToma
            FROM ConceptoAdicionalesCotizaci_on co
                INNER JOIN Cotizaci_on ct ON ( co.Cotizaci_on = ct.id  )
                INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id  )
            WHERE  co.Cotizaci_on IN (" . $idCotizacion . ") and Estatus=0 ORDER BY co.A_no DESC, COALESCE(co.Mes, '01') DESC ,  co.id ASC ";*/

        $ResultadoConcepto = DB::table('ConceptoAdicionalesCotizaci_on as co')
            ->select('c.id as idConceptoCotizacion', 'co.id as ConceptoCobro', 'co.Cantidad as Cantidad',
                'c.Descripci_on as NombreConcepto', 'co.Importe as total', 'co.MontoBase as punitario',
                DB::raw("(select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional"),
                DB::raw("COALESCE(co.Mes, '01') as Mes"), 'co.A_no', 'ct.Tipo', 'c.TipoToma'
            )
            ->join('Cotizaci_on as ct', 'co.Cotizaci_on', '=', 'ct.id')
            ->join('ConceptoCobroCaja as c', 'co.ConceptoAdicionales', '=', 'c.id')
            ->whereIn('co.Cotizaci_on', explode(',', $idCotizacion))
            ->where('Estatus', '0')
            ->orderByDesc('co.A_no')
            ->orderByDesc(DB::raw("COALESCE(co.Mes, '01')"))
            ->orderBy('co.id', 'ASC')
            ->get();

        $ConceptosCotizados = '';
        $totalConcepto = 0;
        $indexConcepto = 0;
        setlocale(LC_TIME, "es_MX.UTF-8");
        //Leermos el primer concepto.
        $RegistroConcepto = $ResultadoConcepto[0];

        $totalConcepto                                  = $RegistroConcepto->total;
        $ConceptosCotizados                             = $RegistroConcepto->idConceptoCotizacion . ',';
        $ConceptoPadre[$indexConcepto]['id']            = $RegistroConcepto->idConceptoCotizacion;
        $ConceptoPadre[$indexConcepto]['Mes']           = $RegistroConcepto->Mes;
        $ConceptoPadre[$indexConcepto]['A_no']          = $RegistroConcepto->A_no;
        $ConceptoPadre[$indexConcepto]['Total']         = 0;
        $ConceptoPadre[$indexConcepto]['Nombre']        = $RegistroConcepto->NombreConcepto;
        $ConceptoPadre[$indexConcepto]['TipoPredio']    = $RegistroConcepto->Tipo;
        $ConceptoPadre[$indexConcepto]['idConcepto']    = $RegistroConcepto->ConceptoCobro;
        $ConceptoPadre[$indexConcepto]['FechaConcepto'] = $RegistroConcepto->A_no . "-" . $RegistroConcepto->Mes . "-01";

        $recargosActualizaciones = array();

        $i = 0;
        #while ($RegistroConcepto = $ResultadoConcepto->fetch_assoc()) {
        foreach($ResultadoConcepto as $RegistroConcepto ) {
            //precode($RegistroConcepto,1);
            if($i != 0){
                if (empty($RegistroConcepto->Adicional)) {
                    //Es concepto
                    $ConceptoPadre[$indexConcepto]['Total'] = $totalConcepto;
                    $totalConcepto = $RegistroConcepto->total;
                    $indexConcepto++;

                    $ConceptoPadre[$indexConcepto]['id']            = $RegistroConcepto->idConceptoCotizacion;
                    $ConceptoPadre[$indexConcepto]['Mes']           = $RegistroConcepto->Mes;
                    $ConceptoPadre[$indexConcepto]['A_no']          = $RegistroConcepto->A_no;
                    $ConceptoPadre[$indexConcepto]['Nombre']        = $RegistroConcepto->NombreConcepto;
                    $ConceptoPadre[$indexConcepto]['TipoPredio']    = $RegistroConcepto->Tipo;
                    $ConceptoPadre[$indexConcepto]['idConcepto']    = $RegistroConcepto->ConceptoCobro;
                    $ConceptoPadre[$indexConcepto]['FechaConcepto'] = $RegistroConcepto->A_no . "-" . $RegistroConcepto->Mes . "-01";
                } else {//Es adicional
                    $totalConcepto += $RegistroConcepto->total;
                }
                $ConceptosCotizados .= $RegistroConcepto->idConceptoCotizacion . ',';
            }
            $i++;
        }
        $ConceptoPadre[$indexConcepto]['Total'] = $totalConcepto;

        //Buscamos actualizaciones y recargos para los conceptos a pagar
        $PagoActualizaciones = 0;
        $ActualizacionesYRecargos = "";
        $fechaActual = date("Y-m-d");
        $auxMes = array("", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");

        for ($iC = 0; $iC < count($ConceptoPadre); $iC++) {
            $ConceptoExcluidosParaActualizacionesRecargos = array(5461, 5462, 5467, 5469, 2489, 5084);
            if (!in_array($ConceptoPadre[$iC]['id'], $ConceptoExcluidosParaActualizacionesRecargos)) {

                if ($ConceptoPadre[$iC]['FechaConcepto'] != "--01") {
                    if (date("Y-m", strtotime($fechaActual)) > date('Y-m', strtotime($ConceptoPadre[$iC]['FechaConcepto']))) {
                        //Obtenemos las multas del concepto

                        $ResultadoMultas = DB::table('MultaCategor_ia as mi')
                            ->select(
                                'mi.Categor_ia as Categoria', 'm.id as idMulta', 'm.Descripci_on as DescripcionMulta',
                                'c.id as idConcepto', 'c.Descripci_on as DescripcionConcepto'
                            )
                            ->join('Multa as m', 'mi.Multa', '=', 'm.id')
                            ->join('ConceptoCobroCaja as c', 'mi.Concepto', '=', 'c.id')
                            ->where('mi.Categor_ia', DB::raw("(select Categor_ia from ConceptoCobroCaja where id = " . $ConceptoPadre[$iC]['id'] . ")" ))
                            ->get();

                        #while ($RegistroMultas = $ResultadoMultas->fetch_assoc()) {
                        foreach ($ResultadoMultas as $RegistroMultas) {
                            $fechainicial = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);
                            $elmes        = $fechainicial[1];
                            $elanio       = $fechainicial[0];

                            if ($RegistroMultas->idMulta == 1) {

                                if ($ConceptoPadre[$iC]['TipoPredio'] == 3) {
                                    //3 es para predial
                                } else { //Caso normal que no es predial
                                    $fechainicial = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);

                                    $montoconcepto =  $ConceptoPadre[$iC]['Total'];

                                    $mes  = ($fechainicial[1]) + 1;
                                    $anio = $fechainicial[0];

                                    if (intval($mes) > 12) {
                                        $mes  = 1;
                                        $anio = $anio + 1;
                                    }

                                    $mes = str_pad($mes, 2, "0", STR_PAD_LEFT);
                                    $dia = 18;
                                    $fechaVencimiento = $anio . "-" . $mes . "-" . $dia;

                                    if (date('D', intval($fechaVencimiento)) == "Sat") {
                                        $dia = $dia + 2;
                                    }
                                    if (date('D', intval($fechaVencimiento)) == "Sun") {
                                        $dia = $dia + 1;
                                    }

                                    $fechaVencimiento = $anio . "-" . $mes . "-" . $dia;
                                    $fecha_actual     = strtotime(date("Y-m-d H:i:00", time()));
                                    $fecha_entrada    = strtotime(date($fechaVencimiento . " H:i:00"));

                                    if ($fecha_actual > $fecha_entrada) {
                                        $actualizacionesOK = Funciones::CalculoActualizacionFecha($fechaVencimiento, $montoconcepto, $fechaActual);
                                    } else {
                                        $actualizacionesOK = 0;
                                    }
                                }

                                if ($actualizacionesOK > 0) {
                                    $PagoActualizaciones += $actualizacionesOK;
                                }
                            }

                            if ($RegistroMultas->idMulta == 2) { //Es Multa
                            }

                            if ($RegistroMultas->idMulta == 3) {
                                //Es Recargo
                                if ($ConceptoPadre[$iC]['TipoPredio'] == 3) {
                                    //3 es para predial
                                } else {
                                    //Caso normal que no es predial
                                    $fechainicial = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);

                                    $montoconcepto =  $ConceptoPadre[$iC]['Total'];

                                    $mes  = ($fechainicial[1]) + 1;
                                    $anio = $fechainicial[0];

                                    if ($ConceptoPadre[$iC]['TipoPredio'] == 9) { }

                                    if (intval($mes) > 12) {
                                        $mes  = 1;
                                        $anio = $anio + 1;
                                    }

                                    $mes = str_pad($mes, 2, "0", STR_PAD_LEFT);
                                    $dia = 18;

                                    $fechaVencimiento = $anio . "-" . $mes . "-" . $dia;

                                    if (date('D', intval($fechaVencimiento)) == "Sat") {
                                        $dia = $dia + 2;
                                    }
                                    if (date('D', intval($fechaVencimiento)) == "Sun") {
                                        $dia = $dia + 1;
                                    }

                                    $fechaVencimiento = $anio . "-" . $mes . "-" . $dia;
                                    $fecha_actual     = strtotime(date("Y-m-d H:i:00", time()));
                                    $fecha_entrada    = strtotime(date($fechaVencimiento . " H:i:00"));

                                    if ($fecha_actual > $fecha_entrada) {
                                        $fechaVencimiento . $montoconcepto;
                                        $recargosOK = Funciones::CalculoRecargosFechaAgua($fechaVencimiento, $montoconcepto, $fechaActual, $cliente);
                                    } else {
                                        $recargosOK = 0;
                                    }
                                }
                                $recargosActualizaciones[] = (round($actualizacionesOK, 2) + round($recargosOK, 2));
                            }
                        }
                    }
                } //if si es fecha valida
            }
        }//for
        return array_sum($recargosActualizaciones);
    }

    /*public static function CalculoActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg=NULL){
		//Es Recargo
		if(is_null($fechaActualArg)){
			$fechaActualArg=date('Y-m-d');
		}

		//Es Actualizacion
		$fechaHoy = $fechaActualArg;

		$Recargoschecked="";
		$mesConocido=1;
		while(true){
			 $fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ( $fechaConcepto ) ) ;
			setlocale(LC_TIME,"es_MX.UTF-8");
			$mes = ucwords(strftime("%B", $fecha ));
			$a_no = strftime("%Y", $fecha );
			#precode($a_no."-".$mes,1);
			#$INPCCotizacion=ObtenValor("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,$mes);
            $INPCCotizacion = DB::table('IndiceActualizaci_on')->select("$mes" )->where('A_no', $a_no)->value("$mes");

			if(empty($INPCCotizacion) || $INPCCotizacion=='NULL')
				$mesConocido++;
			else
				break;
		}

		$mesConocido=1;
		while(true){
			$fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ($fechaHoy)) ;
			setlocale(LC_TIME,"es_MX.UTF-8");
			$mes = ucwords(strftime("%B", $fecha ));
			$a_no = strftime("%Y", $fecha );
			#precode($a_no."-".$mes,1);
			#precode("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,1,1);
            #$INPCPago=ObtenValor("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,$mes);
            $INPCPago = DB::table('IndiceActualizaci_on')->select("$mes" )->where('A_no', $a_no)->value("$mes");

			if(empty($INPCPago) || $INPCPago=='NULL')
				$mesConocido++;
			else
				break;
		}

        $FactorActualizacion=$INPCPago/$INPCCotizacion;

		if($FactorActualizacion<1){
			$FactorActualizacion=1;
		}

        $Actualizacion=($ImporteConcepto*$FactorActualizacion)-$ImporteConcepto;

		return  $Actualizacion;
	}*/

    /*public static function CalculoRecargosFechaAgua($fechaConcepto, $ImporteConcepto, $fechaActualArg=NULL , $cliente){
        //Es Recargo
        if(is_null($fechaActualArg)){
            $fechaActualArg=date('Y-m-d');
        }

        $Actualizacion = Funciones::CalculoActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg);
        $FactorActualizacion = Funciones::CalculofactorActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg);

        $mesConocido=0;
        $SumaDeTasa=0;

        $fechaHoy= date("Y-m-d", strtotime ( '-1 month' , strtotime (  $fechaActualArg )) );
        $fechaConcepto= date("Y-m-d", strtotime ( '-1 month' , strtotime (  $fechaConcepto )) );

        $fechafinal = explode('-', $fechaHoy);
        $fechainicial = explode('-', $fechaConcepto);

        $fechainicialdif = new DateTime($fechaConcepto);
        $fechafinaldif = new DateTime($fechaHoy);
        $elmes=$fechainicial[1];
        $elanio=$fechainicial[0];
        $diferencia = $fechainicialdif->diff($fechafinaldif);
        $meses = ( $diferencia->y * 12 ) + $diferencia->m;

        while($mesConocido<=$meses){
            $fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ( $fechaHoy)) ;
            setlocale(LC_TIME,"es_MX.UTF-8");
            $mes = (date("m", $fecha ));
            $a_no = strftime("%Y", $fecha );

            #$SumaDeTasa+=floatval(ObtenValor("select Recargo from PorcentajeRecargo where A_no=".$a_no." and Cliente=".$cliente." and Mes=".$mes,"Recargo"));
            $SumaDeTasa+=floatval( DB::table('PorcentajeRecargo')->select('Recargo')
                ->where('A_no', $a_no)->wheere('Cliente', $cliente)->where('Mes', $mes)->value('Recargo') );
            $mesConocido++;
        }

        if($Actualizacion>0)
            $Recargo=(($ImporteConcepto*$FactorActualizacion)*round($SumaDeTasa, 2))/100;
        else
            $Recargo=(($ImporteConcepto)*$SumaDeTasa)/100;

        return $Recargo;
    }*/

    /*public static function CalculofactorActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg=NULL){

        if(is_null($fechaActualArg)){
            $fechaActualArg=date('Y-m-d');
        }

        //Es Actualizacion
        $fechaHoy=$fechaActualArg;
        #$fechaHoy= date("Y-m-d", strtotime ( '-1 month' , strtotime (  date('Y-m-d') )) );
        #$fechaConcepto= date("Y-m-d", strtotime ( '-1 month' , strtotime (  $fechaConcepto )) );

        #precode($fechaHoy,1);
        #precode($fechaConcepto,1);
        $Recargoschecked="";
        $mesConocido=1;
        while(true){
            $fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ( $fechaConcepto ) ) ;
            setlocale(LC_TIME,"es_MX.UTF-8");
            $mes = ucwords(strftime("%B", $fecha ));
            $a_no = strftime("%Y", $fecha );

            #$INPCCotizacion=ObtenValor("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,$mes);
            $INPCCotizacion = DB::table('IndiceActualizaci_on')->select("$mes" )->where('A_no', $a_no)->value("$mes");

            if(empty($INPCCotizacion) || $INPCCotizacion=='NULL')
                $mesConocido++;
            else
                break;
        }

        $mesConocido=1;
        while(true){
            $fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ($fechaHoy)) ;
            setlocale(LC_TIME,"es_MX.UTF-8");
            $mes = ucwords(strftime("%B", $fecha ));
            $a_no = strftime("%Y", $fecha );

            #$INPCPago=ObtenValor("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,$mes);
            $INPCPago = DB::table('IndiceActualizaci_on')->select("$mes" )->where('A_no', $a_no)->value("$mes");

            if(empty($INPCPago) || $INPCPago=='NULL')
                $mesConocido++;
            else
                break;
        }
        $FactorActualizacion=$INPCPago/$INPCCotizacion;

        if($FactorActualizacion<1){
            $FactorActualizacion=1;
        }
        return $FactorActualizacion;
    }*/


    public static function ObtenerRecargosYActualizacionesPorConceptoRecibo($idConcepto, $idCotizacion, $cliente){
        $ActualizacionesYRecargosConcepto = array('Actualizaciones'=>0,'Recargos'=>0 );

        $ConsultaConceptos="SELECT c.id as idConceptoCotizacion, co.id as ConceptoCobro, co.Cantidad as Cantidad, c.Descripci_on as NombreConcepto, co.Importe as total, co.MontoBase as punitario,
        (select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional, co.A_no, COALESCE(co.Mes, '01') as Mes, ct.Tipo, c.TipoToma
        FROM ConceptoAdicionalesCotizaci_on co
        INNER JOIN Cotizaci_on ct ON ( co.Cotizaci_on = ct.id  )
        INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id  )
        WHERE  co.id=".$idConcepto." AND  co.Cotizaci_on IN (".$idCotizacion.") and Estatus=0 ORDER BY co.A_no DESC, COALESCE(co.Mes, '01') DESC ,  co.id ASC  ";

        $ResultadoConcepto = DB::select($ConsultaConceptos);

        $FilaConceptos      = '';
        $FilaActualizacion  = '';
        $ConceptosCotizados = '';
        $totalConcepto      = 0;
        $idsConceptos       = '';
        $Contador           = 0;
        $ConceptoActual     = 0;
        $Conceptos          = '';
        $indexConcepto      = 0;
        $inicio             = 0;
        setlocale(LC_TIME,"es_MX.UTF-8");

        //Leermos el primer concepto.
        #$RegistroConcepto = $ResultadoConcepto->fetch_assoc();
        $RegistroConcepto = $ResultadoConcepto[0];

        $totalConcepto=$RegistroConcepto->total;
        $idsConceptos=$RegistroConcepto->ConceptoCobro.',';
        $ConceptosCotizados=$RegistroConcepto->idConceptoCotizacion.',';
        $ConceptoPadre[$indexConcepto]['id']=$RegistroConcepto->idConceptoCotizacion;
        $ConceptoPadre[$indexConcepto]['TipoPredio']=$RegistroConcepto->Tipo;
        $ConceptoPadre[$indexConcepto]['Mes']=$RegistroConcepto->Mes;
        $ConceptoPadre[$indexConcepto]['A_no']=$RegistroConcepto->A_no;
        $ConceptoPadre[$indexConcepto]['idConcepto']=$RegistroConcepto->ConceptoCobro;
        $ConceptoPadre[$indexConcepto]['Nombre']=$RegistroConcepto->NombreConcepto;
        $ConceptoPadre[$indexConcepto]['Total']=0;
        $ConceptoPadre[$indexConcepto]['FechaConcepto']=$RegistroConcepto->A_no."-".$RegistroConcepto->Mes."-01";

        $recargosActualizaciones = array();

        $i = 0;
        #while($RegistroConcepto=$ResultadoConcepto->fetch_assoc()){
        foreach($ResultadoConcepto as $RegistroConcepto){
        //precode($RegistroConcepto,1);
            if($i != 0){
                if( empty($RegistroConcepto->Adicional) ){
                    //Es concepto
                    $FilaConceptos=str_replace('TotalConcepto'.$ConceptoActual, round($totalConcepto,2 ), $FilaConceptos);
                    $FilaConceptos=str_replace('ConceptoRetenciones'.$ConceptoActual, substr_replace($idsConceptos, '', -1), $FilaConceptos);
                    $ConceptoPadre[$indexConcepto]['Total']=$totalConcepto;

                    $totalConcepto=$RegistroConcepto->total ;
                    $idsConceptos=$RegistroConcepto->ConceptoCobro.',';
                    $Contador=0;
                    $ConceptoActual++;
                    $indexConcepto++;

                    $ConceptoPadre[$indexConcepto]['id']=$RegistroConcepto->idConceptoCotizacion;
                    $ConceptoPadre[$indexConcepto]['TipoPredio']=$RegistroConcepto->Tipo;
                    $ConceptoPadre[$indexConcepto]['Mes']=$RegistroConcepto->Mes;
                    $ConceptoPadre[$indexConcepto]['A_no']=$RegistroConcepto->A_no;

                    $ConceptoPadre[$indexConcepto]['idConcepto']=$RegistroConcepto->ConceptoCobro;
                    $ConceptoPadre[$indexConcepto]['Nombre']=$RegistroConcepto->NombreConcepto;
                    $ConceptoPadre[$indexConcepto]['FechaConcepto']=$RegistroConcepto->A_no."-".$RegistroConcepto->Mes."-01";

                }else{
                    //Es adicional
                    $totalConcepto +=$RegistroConcepto->total;
                    $idsConceptos .= $RegistroConcepto->ConceptoCobro.',';
                }
                $Contador++;
                $ConceptosCotizados.=$RegistroConcepto->idConceptoCotizacion . ',';
            }
            $i++;
        }

        $ConceptoPadre[$indexConcepto]['Total'] = $totalConcepto;

        //Buscamos actualizaciones y recargos para los conceptos a pagar
        $ActualizacionesYRecargos = "";
        $PagoActualizaciones      = 0;
        $sumatotalActyRec         = 0;
        $fechaActual              = date("Y-m-d");

        $auxMes = array("", "Enero", "Febrero","Marzo","Abril","Mayo","Junio","Julio", "Agosto","Septiembre","Octubre","Noviembre","Diciembre");

        for($iC=0;$iC<count($ConceptoPadre);$iC++){
            $ConceptoExcluidosParaActualizacionesRecargos = array(5461, 5462,5467, 5469, 2489, 5084);
            if (!in_array($ConceptoPadre[$iC]['id'], $ConceptoExcluidosParaActualizacionesRecargos)) {

                //Obtenemos las actualizaciones y recargos.
                if($ConceptoPadre[$iC]['FechaConcepto']!="--01"){
                    if(date("Y-m", strtotime( $fechaActual ) ) > date('Y-m', strtotime($ConceptoPadre[$iC]['FechaConcepto']))){
                        //Obtenemos las multas del concepto
                        $ConsultaMultas= " SELECT mi.Categor_ia as Categoria, m.id as idMulta, m.Descripci_on as DescripcionMulta, c.id as idConcepto, c.Descripci_on as DescripcionConcepto
                        FROM MultaCategor_ia mi
                        INNER JOIN Multa m ON ( mi.Multa = m.id  )
                        INNER JOIN ConceptoCobroCaja c ON ( mi.Concepto = c.id  )
                        WHERE mi.Categor_ia = (select Categor_ia from ConceptoCobroCaja where id = ".$ConceptoPadre[$iC]['id'].")";
                        #precode($ConsultaMultas,1);
                        #$ResultadoMultas=$Conexion->query($ConsultaMultas);
                        $ResultadoMultas=DB::select($ConsultaMultas);

                        #while($RegistroMultas=$ResultadoMultas->fetch_assoc()){
                        foreach($ResultadoMultas as $RegistroMultas){
                            $fechainicial = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);
                            $elmes=$fechainicial[1];
                            $elanio=$fechainicial[0];

                            if($RegistroMultas->idMulta==1){
                                //Es Actualizacion

                                if($ConceptoPadre[$iC]['TipoPredio']==3)//3 es para predial
                                {
                                }// termina tipo 3 predial
                                else{
                                    //Caso normal que no es predial
                                    $fechainicial = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);

                                    $descripcionActyRec='Actualizaciones y Recargos de: '.$ConceptoPadre[$iC]['Nombre'];

                                    $montoconcepto=  $ConceptoPadre[$iC]['Total'];

                                    $mes=($fechainicial[1])+1;
                                    $anio=$fechainicial[0];

                                    if(intval($mes)>12){
                                        $mes=1;
                                        $anio=$anio+1;
                                    }

                                    $mes=str_pad($mes, 2, "0", STR_PAD_LEFT);

                                    $dia=18;

                                    $fechaVencimiento= $anio."-".$mes."-".$dia;

                                    if(date('D',intval($fechaVencimiento))=="Sat"){
                                        $dia=$dia+2;
                                    }
                                    if(date('D',intval($fechaVencimiento))=="Sun"){
                                        $dia=$dia+1;
                                    }

                                    $fechaVencimiento= $anio."-".$mes."-".$dia;

                                    $fecha_actual = strtotime(date("Y-m-d H:i:00",time()));
                                    $fecha_entrada = strtotime(date($fechaVencimiento." H:i:00"));
                                    if($fecha_actual > $fecha_entrada){
                                        //$recargosOK		   = 	CalculoRecargos($fechaVencimiento, $montopredial);
                                        $actualizacionesOK = Funciones::CalculoActualizacionFecha($fechaVencimiento, $montoconcepto, $fechaActual );
                                    }else{
                                        //$recargosOK		   = 0;
                                        $actualizacionesOK = 0;
                                    }
                                }

                                if($actualizacionesOK>0){
                                    $PagoActualizaciones += $actualizacionesOK;

                                }
                            }
                            if($RegistroMultas->idMulta==2){
                                //Es Multa
                            }
                            if($RegistroMultas->idMulta==3){
                                //Es Recargo

                                if($ConceptoPadre[$iC]['TipoPredio']==3)//3 es para predial
                                {
                                }// termina tipo 3 predial
                                else{
                                    //Caso normal que no es predial
                                    $fechainicial = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);

                                    $descripcionActyRec='Actualizaciones y Recargos de: '.$ConceptoPadre[$iC]['Nombre'];

                                    $montoconcepto=  $ConceptoPadre[$iC]['Total'];
                                    //precode($RegistroConcepto,1);
                                    $mes=($fechainicial[1])+1;
                                    $anio=$fechainicial[0];

                                    if($ConceptoPadre[$iC]['TipoPredio']==9){
                                        $auxMes = array("", "Enero", "Febrero","Marzo","Abril","Mayo","Junio","Julio", "Agosto","Septiembre","Octubre","Noviembre","Diciembre");
                                        $descripcionActyRec='Actualizaciones y Recargos de: '.$ConceptoPadre[$iC]['Nombre']." Correspondiente al mes de ".$auxMes[$fechainicial[1]]." del ".$fechainicial[0].'';
                                    }
                                    if(intval($mes)>12){
                                        $mes=1;
                                        $anio=$anio+1;
                                    }

                                    $mes=str_pad($mes, 2, "0", STR_PAD_LEFT);

                                    $dia=18;

                                    $fechaVencimiento= $anio."-".$mes."-".$dia;

                                    if(date('D',intval($fechaVencimiento))=="Sat"){
                                        $dia=$dia+2;
                                    }
                                    if(date('D',intval($fechaVencimiento))=="Sun"){
                                        $dia=$dia+1;
                                    }

                                    $fechaVencimiento= $anio."-".$mes."-".$dia;

                                    //exit;
                                    $fecha_actual = strtotime(date("Y-m-d H:i:00",time()));
                                    $fecha_entrada = strtotime(date($fechaVencimiento." H:i:00"));
                                    if($fecha_actual > $fecha_entrada){
                                        $fechaVencimiento.$montoconcepto;
                                        $recargosOK = Funciones::CalculoRecargosFechaAgua($fechaVencimiento, $montoconcepto, $fechaActual, $cliente );
                                        //$actualizacionesOK = CalculoActualizacion($fechaVencimiento, $montoconcepto);
                                    }else{
                                        $recargosOK	= 0;
                                    }
                                }

                                $recargosActualizaciones[] = ( round( $actualizacionesOK, 2) + round( $recargosOK, 2 ) );
                                if($actualizacionesOK<=0)
                                    $actualizacionesOK=0;

                                $ActualizacionesYRecargosConcepto=array('Actualizaciones'=>round( $actualizacionesOK, 2),'Recargos'=>round( $recargosOK, 2 ) );
                            }
                        }
                    }
                }//if si es fecha valida
            }
        }//for
        return $ActualizacionesYRecargosConcepto;
    }

    /*function CalculoRecargosFechaAgua($fechaConcepto, $ImporteConcepto, $fechaActualArg=NULL , $cliente){
        //Es Recargo
        if(is_null($fechaActualArg)){
            $fechaActualArg=date('Y-m-d');
        }

        $Actualizacion      = funciones::CalculoActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg);
        $FactorActualizacion= funciones::CalculofactorActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg);


        $mesConocido=0;
        $SumaDeTasa=0;

        $fechaHoy= date("Y-m-d", strtotime ( '-1 month' , strtotime (  $fechaActualArg )) );
        $fechaConcepto= date("Y-m-d", strtotime ( '-1 month' , strtotime (  $fechaConcepto )) );

        $fechafinal = explode('-', $fechaHoy);
        $fechainicial = explode('-', $fechaConcepto);

        $fechainicialdif = new DateTime($fechaConcepto);
        $fechafinaldif = new DateTime($fechaHoy);
        $elmes=$fechainicial[1];
        $elanio=$fechainicial[0];
        $diferencia = $fechainicialdif->diff($fechafinaldif);
        $meses = ( $diferencia->y * 12 ) + $diferencia->m;

        while($mesConocido<=$meses){
            $fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ( $fechaHoy)) ;
            setlocale(LC_TIME,"es_MX.UTF-8");
            $mes = (date("m", $fecha ));
            $a_no = strftime("%Y", $fecha );

            $SumaDeTasa+=floatval(ObtenValor("select Recargo from PorcentajeRecargo where A_no=".$a_no." and Cliente=".$cliente." and Mes=".$mes,"Recargo"));
            $mesConocido++;
        }

        if($Actualizacion>0)
            $Recargo=(($ImporteConcepto*$FactorActualizacion)*round($SumaDeTasa, 2))/100;
        else
            $Recargo=(($ImporteConcepto)*$SumaDeTasa)/100;

        return $Recargo;
    }*/

    public static function generaReciboOficialIndividual($idPadron, $idLectura, $cliente){
        #$ServerNameURL = ObtenValor("SELECT Valor FROM CelaConfiguraci_on WHERE Nombre='URLSitio'","Valor");

        $DatosPadron = PadronAguaPotable::select('Padr_onAguaPotable.*', 'Padr_onAguaPotable.Cuenta as CuentaOK', 'e.Nombre as Estado',
            DB::raw('(SELECT Nombre FROM Municipio WHERE id = c.Municipio_c) AS Municipio')
        )
        ->join('Contribuyente as c',     'c.id', '=', 'Padr_onAguaPotable.Contribuyente')
        ->join('DatosFiscales as d',     'd.id', '=', 'c.DatosFiscales')
        ->join('EntidadFederativa as e', 'e.id', '=', 'd.EntidadFederativa')
        ->where('Padr_onAguaPotable.id', $idPadron)
        ->first();

        if( ! $DatosPadron )
            return '';

        $DatosCliente = Cliente::select('Cliente.*', 'm.Nombre as Municipio', 'e.Nombre as Estado',
            'cr.Ruta as Logotipo'
        )
        ->join('DatosFiscales AS d',     'd.id', '=', 'Cliente.DatosFiscales')
        ->join('EntidadFederativa AS e', 'e.id', '=', 'd.EntidadFederativa')
        ->join('Municipio AS m',         'm.id', '=', 'd.Municipio')
        ->join('CelaRepositorio AS cr',  'cr.idRepositorio', '=', 'Cliente.Logotipo')
        ->where('Cliente.id', $cliente)
        ->first();
        #->get();

        #return $DatosCliente;

        $DatosParaRecibo = Funciones::obtieneDatosLectura($idLectura, 3);

        if($DatosParaRecibo == "")
            return "";

        $totalApagar = $DatosParaRecibo['AdeudoCompleto'];
        //cantidad con letra y total
        $letras = utf8_decode( Funciones::num2letras($totalApagar,0,0) . " pesos");
        $ultimoArr = explode(".", number_format($totalApagar,2) ); //recupero lo que este despues del decimal
        $ultimo = $ultimoArr[1];

        if($ultimo=="")
            $ultimo="00";
        $letras = $letras." ".$ultimo."/100 M. N.";

        //cuenta de deposito
        $ejecutaCuentas = DB::table('CuentaBancaria as c')->select('c.N_umeroCuenta', 'c.Clabe', 'b.Nombre as Banco')
            ->join('Banco as b', 'b.id', '=', 'c.Banco')
            ->where('c.Cliente', $cliente)
            ->where('c.CuentaDeRecaudacion', '1')
            ->get();

        $lascuentas='';

        #while($registroCuentas=$ejecutaCuentas->fetch_assoc()){
        foreach($ejecutaCuentas as $registroCuentas){
            $lascuentas.='<tr>
                <td colspan="2" align="center">
                    '.$registroCuentas->Banco.'
                </td>
                <td colspan="2" align="center">
                    '.$registroCuentas->N_umeroCuenta.'
                </td>
                <td colspan="2" align="center">
                    '.$registroCuentas->Clabe.'
                </td>
            </tr>';
        }

        #return $_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".$DatosCliente->Logotipo;

        $htmlGlobal = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml">
        <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title>ServicioEnLinea.mx</title>
        </head>
        <body>
            <table style="padding:-35px 0 0 0;margin:-10px 0 0 0;" border="0" width="787px">
                <tr>
                    <td colspan="2" width="33.5%">
                        <img height="200px" src="'.$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".$DatosCliente->Logotipo.'">
                    </td>
                    <td  colspan="4"  width="66.5%" align="right">
                        '.$DatosCliente->NombreORaz_onSocial.'<br />
                        Domicilio Fiscal: Calle '.$DatosCliente->Calle.' No. '.$DatosCliente->N_umeroExterior.'<br />
                        Col. '.$DatosCliente->Colonia.', C.P. '.$DatosCliente->C_odigoPostal.'<br />
                        '.$DatosCliente->Municipio.', '.$DatosCliente->Estado.'<br />
                        RFC: '.$DatosCliente->RFC.'
                        <br /><br /><br />
                        <span  style="font-size: 12px;">N&uacute;mero de Recibo: <span  style="color:#ff0000; font-size: 20px;">'.$DatosParaRecibo['numRecibo'].'</span></span>
                    </td>
                </tr>
                <tr><td colspan="6" align="right">
                            <img width="787px"  height="1px" src="'.$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".'img/barraColores.png" </td></tr>
                <tr>
                    <td colspan="6">
                        <h3>'.$DatosPadron->NombreORaz_onSocial.'</h3>
                        '.$DatosPadron->RFC.'<br />
                        Calle '.$DatosPadron->Calle.' No. '.$DatosPadron->N_umeroExterior.'
                        Col. '.$DatosPadron->Colonia.', C.P. '.$DatosPadron->C_odigoPostal.'<br />
                        '.$DatosPadron->Municipio.', '.$DatosPadron->Estado.'.
                        <br /><br />
                    </td>
                </tr>
                <tr><td colspan="6"><img width="787px" height="1px" src="'.$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".'img/barraColores.png" </td></tr>
                <tr><td colspan="6">&nbsp;</td></tr>
                <tr>
                    <td colspan="2" >
                        <h3>Estado de Cuenta</h3>
                        Mes de Facturaci&oacute;n<br />
                        Lectura Anterior<br />
                        Lectura Actual<br />
                        Consumo<br />
                        Cuenta<br />
                        Referencia de pago<br />
                        Pagar antes de
                    </td>
                    <td  colspan="2" align="left"  style="border-right: 1px solid black;">
                        <h4 style="font-style:normal !important; font-weight: lighter;">'.$DatosParaRecibo['TipoTomaTexto'].'</h4>
                        <b>'.$DatosParaRecibo['MesActual'] .'</b>&nbsp;&nbsp;&nbsp;<br />
                        '.$DatosParaRecibo['LecturaAnterior'].'<br />
                        '.$DatosParaRecibo['LecturaActual'].'<br />
                        <b>'.$DatosParaRecibo['Consumo'].' m<sup>3</sup></b>&nbsp;&nbsp;&nbsp;<br />
                        '.$DatosParaRecibo['Cuenta'].'&nbsp;&nbsp;&nbsp;<br />
                        '.$DatosParaRecibo['Cuenta'].'&nbsp;&nbsp;&nbsp;<br />
                        '.$DatosParaRecibo['MesDeCorte'].' &nbsp;&nbsp;&nbsp;
                    </td>
                    <td colspan="2" align="right">
                        <span style="font-size: 70px; font-weight: bold;">$'.number_format($DatosParaRecibo['AdeudoActual'],2).'</span>
                    </td>
                </tr>
                <tr><td colspan="6" align="right">'.($DatosParaRecibo['EstatusPagado']==2?'<span style="color:#ff0000; font-size:35px;">[Pagado]</span>':'&nbsp;' ).'</td></tr>

                <tr><td colspan="6"><img width="787px" height="1px" src="'.$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".'img/barraColores.png" </td></tr>
                <tr>
                    <td colspan="3">
                        <h3>Adeudos Anteriores</h3>
                        '.$DatosParaRecibo['Rango'].'
                    </td>
                    <td colspan="3" align="right">
                        <br/> <br/> <br/>
                        <span style="font-size: 20px; font-weight: bold;">$ '.number_format($DatosParaRecibo['SumaAdeudosAnteriores'], 2).'</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        &nbsp;
                    </td>
                    <td colspan="2" align="right">
                        <span style="font-size: 20px; font-weight: bold;">Total a Pagar</span>
                    </td>
                    <td colspan="2" align="right">

                        <span style="font-size: 20px; font-weight: bold;">$ '.number_format($totalApagar,2).'</span>
                    </td>
                </tr>
                <tr><td colspan="6">&nbsp;</td></tr>
                <tr>
                    <td colspan="6" align="right">('.$letras.')</td>
                </tr>
                <tr><td colspan="6"><img width="787px" height="1px" src="'.$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".'img/barraColores.png" </td></tr>
                <tr><td colspan="6"><h3>Información para pagos</h3></td></tr>
                <tr>
                    <td colspan="2" align="center">
                        Banco
                    </td>
                    <td colspan="2" align="center">
                        Cuenta
                    </td>
                    <td colspan="2" align="center">
                        Clabe
                    </td>
                </tr>
                    '.$lascuentas.'
                    <tr><td colspan="6">&nbsp;</td></tr>
                <tr>
                    <td colspan="6" align="right">
                        <span style="font-size:12px;">Expedici&oacute;n '.date('d/m/Y', strtotime($DatosParaRecibo['FechaLectura'])).'</span>
                    </td>
                </tr>
                <tr><td colspan="6"><img width="787px" height="1px" src="'.$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".'img/barraColores.png" </td></tr>
                <tr>
                    <td colspan="6" align="center">
                        <span style="font-size:12px;">El importe de las actualizaciones y recargos se determina al momento en el que se realiza el pago del adeudo</span>
                    </td>
                </tr>
            </table>
        </body>
        </html>';

        #return $htmlGlobal;


        #include_once("Libs/libPDF.php");
        #include "Libs/libPDF.php";
        include( app_path() . '/Libs/Wkhtmltopdf.php' );
        try{
            $nombre = uniqid()."_".$idPadron;
            $params = array('path' =>'repositorio/temporal/', 'lowquality'=>true, 'FooterStyleLeft'=>'servicioenlinea.mx', 'FooterStyleCenter'=>'Pag. [page] de [toPage]');
            $wkhtmltopdf = new Wkhtmltopdf($params);
            $wkhtmltopdf->setHtml($htmlGlobal);
            #$wkhtmltopdf->output(Wkhtmltopdf::MODE_EMBEDDED, $nombre.".pdf");
            $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $nombre.".pdf");
            return "repositorio/temporal/".$nombre.".pdf";
        }catch (Exception $e){
            return response()->json([
                'response'    => 'error',
                'menssage'  => 'Hubo un error al generar el PDF: ' . $e->getMessage(),
            ]);
        }
        # /mnt/sata/reposuinpac/repositorio
        # /home/piacza/web/api.suinpac.piacza.com.mx/public_html
    }

    //! +-+-+-+-+-+-+-+ +-+-+
    //! |G|E|N|E|R|A|R| |Q|R|
    //! +-+-+-+-+-+-+-+ +-+-+
    public static function GenerarQR($cadenaAcodificar, $rutaArchivo, $urlServidor, $tipo='M', $var1=4, $var2=2){
        $ruta = pathinfo($rutaArchivo);
        $contador = 0;
        $ban = 0;
        mkdir($ruta['dirname'], 0755, true);
        return $ruta['dirname'];
        $start = microtime(true);//Iniciamos el conteo del tiempo

        while (!file_exists($rutaArchivo)) {
            //Contenido del QR
            QRcode::png( $cadenaacodificar, $rutaArchivo, $tipo, $var1, $var2 );

            $time_elapsed_secs = microtime(true) - $start;
            if ($time_elapsed_secs >= 60) { //Si ejecuto por mas de 1 minuto terminar
                $ban = 1;
                break;
            }
        }

        if ($ban == 0) {
            chmod($rutaArchivo, 0644); //Corregimos permisos a "rw-r--r--"
            $contador = 0;
            //Verifica que exista la imagen mandandola a traer con CURL
            $start = microtime(true);//Iniciamos el conteo del tiempo
            do {
                $ch = curl_init();
                // set url
                curl_setopt($ch, CURLOPT_URL, $urlservidor . $rutaArchivo);
                //return the transfer as a string
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                // $output contains the output string
                $output = curl_exec($ch);
                //info
                $info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                // close curl resource to free up system resources
                // sleep(1);//Espera 1 segundo antes de intentar de nuevo
                //Intentar 10 segundos la generacion si no termina
                //if($contador==10)
                // break;
                // $contador++;
                //}while(strlen($output)<=0);
                curl_close($ch);
                if ($time_elapsed_secs >= 360) { //Si ejecuto por mas de 1 minuto terminar
                    $ban = 1;
                    break;
                }
            } while ($info['http_code'] == 200);
        }
        return $ban;
    }

    //! +-+-+-+-+-+-+ +-+ +-+-+-+-+-+-+
    //! |N|u|m|e|r|o| |A| |L|e|t|r|a|s|
    //! +-+-+-+-+-+-+ +-+ +-+-+-+-+-+-+
    public static function num2letras($num, $fem = true, $dec = true) {
        if ($num == 0) return 'Cero';
		$matuni[2]  = "dos";
		$matuni[3]  = "tres";
		$matuni[4]  = "cuatro";
		$matuni[5]  = "cinco";
		$matuni[6]  = "seis";
		$matuni[7]  = "siete";
		$matuni[8]  = "ocho";
		$matuni[9]  = "nueve";
		$matuni[10] = "diez";
		$matuni[11] = "once";
		$matuni[12] = "doce";
		$matuni[13] = "trece";
		$matuni[14] = "catorce";
		$matuni[15] = "quince";
		$matuni[16] = "dieciseis";
		$matuni[17] = "diecisiete";
		$matuni[18] = "dieciocho";
		$matuni[19] = "diecinueve";
		$matuni[20] = "veinte";
		$matunisub[2] = "dos";
		$matunisub[3] = "tres";
		$matunisub[4] = "cuatro";
		$matunisub[5] = "quin";
		$matunisub[6] = "seis";
		$matunisub[7] = "sete";
		$matunisub[8] = "ocho";
		$matunisub[9] = "nove";

		$matdec[2] = "veint";
		$matdec[3] = "treinta";
		$matdec[4] = "cuarenta";
		$matdec[5] = "cincuenta";
		$matdec[6] = "sesenta";
		$matdec[7] = "setenta";
		$matdec[8] = "ochenta";
		$matdec[9] = "noventa";
		$matsub[3]  = 'mill';
		$matsub[5]  = 'bill';
		$matsub[7]  = 'mill';
		$matsub[9]  = 'trill';
		$matsub[11] = 'mill';
		$matsub[13] = 'bill';
		$matsub[15] = 'mill';
		$matmil[4]  = 'millones';
		$matmil[6]  = 'billones';
		$matmil[7]  = 'de billones';
		$matmil[8]  = 'millones de billones';
		$matmil[10] = 'trillones';
		$matmil[11] = 'de trillones';
		$matmil[12] = 'millones de trillones';
		$matmil[13] = 'de trillones';
		$matmil[14] = 'billones de trillones';
		$matmil[15] = 'de billones de trillones';
		$matmil[16] = 'millones de billones de trillones';

		$num= trim((string)@$num);
		if ($num[0] == '-') {
			$neg = 'menos ';
			$num = substr($num, 1);
		}else
		$neg = '';
		while ($num[0] == '0') $num = substr($num, 1);
		if ($num[0] < '1' or $num[0] > 9) $num = '0' . $num;
		$zeros = true;
		$punt = false;
		$ent = '';
		$fra = '';
		for ($c = 0; $c < strlen($num); $c++) {
			$n = $num[$c];
			if (! (strpos(".,'''", $n) === false)) {
				if ($punt) break;
				else{
					$punt = true;
					continue;
				}

			}elseif (! (strpos('0123456789', $n) === false)) {
				if ($punt) {
					if ($n != '0') $zeros = false;
						$fra .= $n;
				}else

				$ent .= $n;
			}else
				break;
		}
		$ent = '     ' . $ent;
		if ($dec and $fra and ! $zeros) {
			$fin = ' punto';
			for ($n = 0; $n < strlen($fra); $n++) {
				if (($s = $fra[$n]) == '0')
					$fin .= ' cero';
				elseif ($s == '1')
					$fin .= $fem ? ' una' : ' un';
				else
					$fin .= ' ' . $matuni[$s];
			}
		}else
			$fin = '';
		if ((int)$ent === 0) return 'Cero ' . $fin;
		$tex = '';
		$sub = 0;
		$mils = 0;
		$neutro = false;
		while ( ($num = substr($ent, -3)) != '   ') {
			$ent = substr($ent, 0, -3);
			if (++$sub < 3 and $fem) {
					$matuni[1] = 'una';
					$subcent = 'as';
			}else{
				$matuni[1] = $neutro ? 'un' : 'uno';
				$subcent = 'os';
			}
			$t = '';
			$n2 = substr($num, 1);
			if ($n2 == '00') {
			}elseif ($n2 < 21)
				$t = ' ' . $matuni[(int)$n2];
			elseif ($n2 < 30) {
				$n3 = $num[2];
				if ($n3 != 0) $t = 'i' . $matuni[$n3];
					$n2 = $num[1];
				$t = ' ' . $matdec[$n2] . $t;
			}else{
				$n3 = $num[2];
				if ($n3 != 0) $t = ' y ' . $matuni[$n3];
				$n2 = $num[1];
				$t = ' ' . $matdec[$n2] . $t;
			}
			$n = $num[0];
			if ($n == 1) {
				if($num == 100){
                                    $t = ' cien' . $t;
                                }else{
                                    $t = ' ciento' . $t;
                                }
			}elseif ($n == 5){
				$t = ' ' . $matunisub[$n] . 'ient' . $subcent . $t;
			}elseif ($n != 0){
				$t = ' ' . $matunisub[$n] . 'cient' . $subcent . $t;
			}
			if ($sub == 1) {
			}elseif (! isset($matsub[$sub])) {

				if ($num == 1) {
					$t = ' mil';
				}elseif ($num > 1){
					$t .= ' mil';
				}
			}elseif ($num == 1) {
				$t .= ' ' . $matsub[$sub] . 'on';
			}elseif ($num > 1){
				$t .= ' ' . $matsub[$sub] . 'ones';
			}
			if ($num == '000') $mils ++;
			elseif ($mils != 0) {
				if (isset($matmil[$sub])) $t .= ' ' . $matmil[$sub];
				$mils = 0;
			}
			$neutro = true;
			$tex = $t . $tex;
		}
		$tex = $neg . substr($tex, 1) . $fin;
		return ucfirst($tex);
    }
    public static function ObtenValor($SQL, $Value=""){
        //print $SQL;

        if($Value==""){
            if($Result = DB::select($SQL)){
                if( count($Result) == 0 )
                    return array('result'=>'NULL');

                if(count($Result) > 0 ){
                    $Result[0]->result = 'OK';
                    $Result = $Result[0];
                }else
                    $Result = ['result' => "ERROR"];
            }else{
                $Result = ['result' => "ERROR"];

            }
            return $Result;
        }
        else{
            //print "<br/>1.-".$SQL;
            if($Result = DB::select($SQL)){
                //print "<br/>2.-".$SQL;

                if( count($Result) == 0 )
                    $Value ="NULL";
                else{

                    $Value=$Result[0]->$Value;
                }
                return $Value;
            }else{
               // return "ERROR-->".$Conexion->error;
            }
        }
        //print_r($Conexion);
        //$Conexion->close();
    }

    public static function GetSQLValueString($Value, $Type, $DefinedValue = "", $NotDefinedValue = ""){
        //echo "<script>console.log(".$Value."); </script>"
        $Value = addslashes($Value);
        switch($Type){
            case "text":
            case "binary":
            case "varbinary":
            case "varchar":
            case "date":
            case "timestamp" :
            case "datetime":
             case "time":
                $Value = ($Value != "") ? "'" . trim($Value) . "'" : "NULL";
                break;
            case "long":
            case "bit":
            case "bool":
            case "tinyint":
            case "smallint":
            case "mediumint":
            case "longint":
            case "smallint unsigned":
            case "mediumint unsigned":
            case "longint unsigned":
            case "int":
            case "long unsigned":
            case "bit unsigned":
            case "bool unsigned":
            case "tinyint unsigned":
            case "int unsigned":
                $Value = ($Value != "") ? intval($Value) : "NULL";
                break;
            case "double":
            case "float":
            case "double unsigned":
            case "float unsigned":
            case "decimal":
                //Redondea el numero decimal
                //$Value=round($Value,2);
                $Value = ($Value != "") ? "'" . round(doubleval(str_replace(',', '', $Value)),2) . "'" : "NULL";
                break;
            case "defined":
                $Value = ($Value != "") ? $DefinedValue : $NotDefinedValue;
                break;
            default:
                $Value = ($Value != "") ? $DefinedValue : $NotDefinedValue;
                break;
        }
        return $Value;
    }

    public static function LimpiarNumero($numero){
        return str_replace(",", "",$numero);
   }

   public static function obtenerCotizacion($cliente,$valor,$filtro){


        /*
         * Numeros correspondientes al filtro de busqueda para ajaxs
         * 1-Contribuyente
         * 2-FolioCotizacion
         * 3-CuentaPredial
         * 4-ContratoAgua
         * 5-Licencia Funcionamiento
       */
        $condicion='';
        $Filas ='';
        $casoParaAgua='';
        switch ($filtro) {
            case 1:
                $condicion = ' AND c.Contribuyente='.$valor." ".verificarAdeudoPredial(0,1,$valor);
                break;
            case 2:
                $condicion = " AND c.FolioCotizaci_on='".$valor."'";
                break;
            case 3:
                $condicion = ' AND c.Tipo=3  AND c.Padr_on='.$valor.' '. verificarAdeudoPredial($valor,0);
                $Mensaje = VerificarEstatusDeCuentaPredial($valor);
                if($Mensaje!=""){
                    $arr['Mensaje']=$Mensaje;
                    return json_encode($arr);;
                }
                break;
            case 4: // Agua Potable OPD
            $IdPadronAgua=ObtenValor("SELECT id FROM Padr_onAguaPotable WHERE Estatus!=4 AND   ContratoVigente=".intval($valor)." AND Cliente = ".$_SESSION['CELA_Cliente'.$_SESSION['CELA_Aleatorio']],'id');
                if($IdPadronAgua!='')
                    $condicion = ' AND c.Padr_on='.$IdPadronAgua;
                if($grupo==1){ // Si se quiere buscar por grupo
                    $Padr_onPapa = $IdPadronAgua;
                    $IdPadronAgua=ObtenValor("SELECT
                        (SELECT GROUP_CONCAT(pa1.id)  FROM Padr_onAguaPotable pa1 WHERE pa1.CuentaPapa=pa.id) as id
                        FROM Padr_onAguaPotable pa WHERE pa.Estatus!=4 AND   pa.ContratoVigente=". intval($valor)." AND pa.Cliente= ".$_SESSION['CELA_Cliente'.$_SESSION['CELA_Aleatorio']],'id');
                    $IdPadronAgua.=",".$Padr_onPapa; // Tambien se agrega la cuenta Padre en dado caso que debiera algo
                if($IdPadronAgua!='')
                    $condicion = ' AND c.Padr_on in('.$IdPadronAgua.")";
                }

                break;
            case 5:

                $IdLicencia = ObtenValor("SELECT id FROM Padr_onLicencia WHERE CAST(Folio AS UNSIGNED) = ".intval($valor)." AND Cliente = ".$_SESSION['CELA_Cliente'.$_SESSION['CELA_Aleatorio']],'id');

                if($IdLicencia != '')
                    $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$IdLicencia;
                else
                    $condicion = ' AND c.Tipo=4 AND c.Padr_on='.$valor;

                break;
            }
            $Datos=array();
        #    precode($condicion,1,1);
            if($condicion!=''){
                $auxiliarCondicion ="";
            #  if($_SESSION['CELA_Cliente'.$_SESSION['CELA_Aleatorio']]==29)
            #    $auxiliarCondicion=" AND c.FechaLimite IS NULL";
                $SQL = "SELECT * FROM (SELECT c.id,  c.FolioCotizaci_on, c.Padr_on, COALESCE(IF (c.Padr_on IS NULL OR c.Padr_on='', '-',  (SELECT ContratoVigente FROM Padr_onAguaPotable WHERE id=c.Padr_on LIMIT 1) ), '-') as ContratoAgua,
                    COALESCE(IF (c.Padr_on IS NULL OR c.Padr_on='', '-',  (SELECT CONCAT(CuentaAnterior,' / \n',Cuenta)  FROM Padr_onCatastral WHERE id=c.Padr_on LIMIT 1) ), '-') as CuentaPredial,
                    (SELECT  COALESCE(Bloquear,0)  FROM Padr_onCatastral WHERE id=c.Padr_on LIMIT 1) AS Bloquear,
                        (SELECT  concat_ws(' ', Rfc, ' (',NombreORaz_onSocial,')') FROM DatosFiscales WHERE  DatosFiscales.id=( select Contribuyente.DatosFiscales from  Contribuyente where Contribuyente.id = c.Contribuyente)) AS Contribuyente,
                        DATE_FORMAT(c.Fecha, '%e-%m-%Y') as FechaCot, COALESCE(IF (c.Padr_on IS NULL OR c.Padr_on='', 0,  (SELECT ph.SaldoNuevo FROM Padr_onAguaHistoricoAbono ph WHERE ph.idPadron=c.Padr_on ORDER BY ph.id DESC LIMIT 1) ), 0) as SaldoNuevo,
                        (SELECT sum(importe) FROM ConceptoAdicionalesCotizaci_on WHERE ConceptoAdicionalesCotizaci_on.Cotizaci_on=c.id AND Padre is NULL) AS importe,
                        (SELECT Descripci_on FROM AreasAdministrativas WHERE AreasAdministrativas.id = c.AreaAdministrativa) AS AreaAdmin,
                        (SELECT  COALESCE( CONCAT(COALESCE (p.Colonia,''),' - ', COALESCE (p.Domicilio,'') ), '-' ) FROM Padr_onAguaPotable  p where p.id=c.Padr_on) as Domicilio,
                        (SELECT  COALESCE(CONCAT_WS( ' COL. ', Ubicaci_on, Colonia ),'') FROM Padr_onCatastral  p where p.id=c.Padr_on) as DomicilioPredial,
                        c.Tipo,
                        (select coalesce(count(id), '0') as NoPagados from ConceptoAdicionalesCotizaci_on where ConceptoAdicionalesCotizaci_on.Cotizaci_on = c.id AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS PorPagar,
                        ( SELECT COALESCE ( Domicilio, '' ) FROM Padr_onLicencia p WHERE p.id = c.Padr_on ) AS DomicilioLicencia,
                        COALESCE( IF( c.Padr_on IS NULL OR c.Padr_on = '', '-', ( SELECT Folio FROM Padr_onLicencia WHERE id = c.Padr_on LIMIT 1 ) ), '-' ) AS CuentaLicencia,
                        c.Contribuyente as ContribuyenteId,
                        (SELECT  GROUP_CONCAT( DISTINCT A_no,'-', Mes) FROM ConceptoAdicionalesCotizaci_on WHERE ConceptoAdicionalesCotizaci_on.Cotizaci_on=c.id AND Padre is NULL AND ConceptoAdicionalesCotizaci_on.Estatus = 0) AS Periodo
                        FROM Cotizaci_on c
                        WHERE c.Cliente=".$cliente.$condicion.$auxiliarCondicion.") x WHERE x.PorPagar!=0;";
                        #" AND  SUBSTR(c.FolioCotizaci_on, 1, 4)=".$_SESSION['CELA_EjercicioFiscal'.$_SESSION['CELA_Aleatorio']].

        #precode($SQL,1,1);

                #$Datos['Mensaje']="-";
                $cotizaciones=DB::select($SQL);
                return $cotizaciones;
            }


   }



   //
    public static function obtenerCotizacionCopia($cliente,$folio){

            $SQL= "SELECT id from Cotizaci_on WHERE FolioCotizaci_on='".$folio. "' and Cliente=".$cliente.";";

            $cotizacion = DB::select($SQL);

            return $cotizacion;
    }
    //

   public static function leyendaFirmaElectronica($cotizacion,$cliente){


    $tipoPago=ObtenValor("select Tipo from PagoTicket PT join Pago P on P.id=PT.Pago where P.Cotizaci_on=".$cotizacion,"Tipo");

   if($cliente==35 && $tipoPago==2 ){

       return '<tr>
                   <td>
                       <div class="letraGeneral">
                            <p class="text-justify" >
                               <strong>Nota respecto a la utilización de la Firma Electrónica.</strong>
                               Ley de Ingresos número 433 del Municipio Municipio de Chilpancingo de los Bravo Guerrero.
                               ARTÍCULO DECIMO SEXTO. Con objeto de modernizar la administración pública
                               municipal se podrán hacer uso de conformidad con el artículo 7 y 8 de la LEY DE
                               FIRMA ELECTRÓNICA AVANZADA publicada el 11 de enero de 2012, de La
                               firma electrónica avanzada para ser utilizada en documentos electrónicos y, en su
                               caso, en mensajes de datos. Los documentos electrónicos y los mensajes de
                               datos que cuenten con firma electrónica avanzada producirán los mismos efectos
                               que los presentados con firma autógrafa y, en consecuencia, tendrán el mismo
                               valor probatorio que las disposiciones aplicables les otorgan a éstos.
                           </p>
                       </div>
                   </td>

               </tr>';
   }
}


public static function Configuraci_onAdicionales($Adicional,$Cliente){

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

public static function tipoDescuento($descuentos){
    $nombre = '';

    switch ($descuentos){
        case 5:
            $nombre = "INAPAM";
            break;
        case 6:
            $nombre = "Pensionados y Jubilados";
            break;
        case 7:
            $nombre = "Madres y Padres Solteros";
            break;
        case 8:
            $nombre = "Persona con Discapacidad";
            break;
        case 9:
            $nombre = "Insen";
            break;
        case 10:
            $nombre = "Dif";
            break;
        case 11:
            $nombre = "Ayuntamiento";
            break;
        default :
            $nombre = "---";
    }

    return $nombre;
}

public static function getAreaRecaudadora($cliente,$ano,$concepto){
    $areaRecaudadora=Funciones::ObtenValor("SELECT
        ca.AreaRecaudadora as AreaRecaudadora
    FROM
        ConceptoCobroCaja ccc
        INNER JOIN ConceptoRetencionesAdicionales cra ON ( cra.Concepto = ccc.id )
        INNER JOIN ConceptoRetencionesAdicionalesCliente crac ON ( crac.ConceptoRetencionesAdicionales = cra.id )
        INNER JOIN ConceptoAdicionales ca ON ( ca.ConceptoRetencionesAdicionalesCliente = crac.id )
    WHERE
        crac.Cliente =$cliente
        AND ca.Cliente = $cliente
        AND ca.EjercicioFiscal = $ano
        AND ccc.id = $concepto
    GROUP BY ccc.id","AreaRecaudadora");
    return $areaRecaudadora;
}
    public static function ObtenerMultaDinamica($datos){
        $CondicionDinamica="EjercicioFiscal=$datos->EjercicioFiscal";
        if(isset($datos->Tipo) && $datos->Tipo!=""){	
            $arrValores=array();
            $CondicionDinamica.="  and  TipoCotizaci_on=$datos->Tipo and Concepto=$datos->idConceptoCotizacion";
            $ResultadoDato=Funciones::ObtenValor("SELECT * FROM Configuraci_onDeMultas where $CondicionDinamica");
            $datos->consulta="SELECT * FROM Configuraci_onDeMultas where $CondicionDinamica";
            $datos->estatus='Paso tipo';
            //$datos->Recargo="";
            //$datos->$ResultadoDato=$ResultadoDato->id;
             #precode($ResultadoDato,1,1);
            if(isset($ResultadoDato->id) && $ResultadoDato->id!=""){
                $DatosValidados = Funciones::CondicionarMultasValidaciones($datos);	
                $datos->estatus="paso condiciones  -";
                #precode($DatosValidados,1,1);
                if(isset($DatosValidados['Aplica']) && $DatosValidados['Aplica']==1){
                    $datos->estatus='if 1=1';
                    $datos->Multa_Concepto=$ResultadoDato->ConceptoRetornar;
                    $datos->Multa_Importe=number_format(isset($ResultadoDato->Porcentaje) && $ResultadoDato->Porcentaje!=""?($datos->total*$ResultadoDato->Porcentaje):$DatosValidados->Importe,2,'.','');
                    if(isset($ResultadoDato->AplicaRecargos) && $ResultadoDato->AplicaRecargos==1){
                        $datos->estatus='AplicaRecargos- '.$DatosValidados['FechaAValidarV5'].' > '.$DatosValidados['FechaAValidar'].'  -  '.$DatosValidados['FechaDetalle'].$DatosValidados['Status'].'    ';
                        $datos->recargo = Funciones::CalculoRecargosFechaV6_Multas("".$DatosValidados['A_no']."-".$DatosValidados['Mes']."-01-", $datos->total, $datos->FechaActualV5,$datos->Cliente);
                    }
                }else {
                    return $datos;
                }	
            }
        }
        return $datos;
    }

    public static function CondicionarMultasValidaciones($DatosConcepto){
        #precode($DatosConcepto,1);
        $arr=array('Aplica'=>0,'A_no'=>0,'Mes'=>0);
        switch($DatosConcepto->Tipo){
            case 11:
                $Datos=Funciones::ObtenValor("SELECT pt.DatosExtra AS DatosExtra FROM Cotizaci_on c INNER JOIN Padr_onCatastral pc ON(pc.id=c.Padr_on)  INNER JOIN  Padr_onCatastralTramitesISAINotarios pt ON(pt.IdPadron=pc.id) INNER JOIN TipoISAIClienteDescuento tcd ON(tcd.id=pc.Origen) WHERE pt.idCotizacionISAI is not null and   tcd.Descuento!=100 and  c.Tipo=11 and  pt.DatosExtra is not null and  c.id in($DatosConcepto->Cotizaci_on)");
                #precode($Datos,1,1);
                if(isset($Datos->DatosExtra) && $Datos->DatosExtra!="")
                    $DatosExtraTramite=json_decode( ($Datos->DatosExtra), true);
                #precode($DatosExtraTramite,1,1)
                $FechaDetalle = (isset($DatosExtraTramite['fechaEscritura'])? $DatosExtraTramite['fechaEscritura'] : date('Y-m-d'));
                #precode($FechaDetalle,1);
                $FechaAValidar=date("Y-m-d",strtotime($FechaDetalle."+6 month"));
                //1386822
                $Bandera=true;
                #if(isset($DatosConcepto['Padr_on']) && ($DatosConcepto['Padr_on']==435188 || $DatosConcepto['Padr_on']==449838|| $DatosConcepto['Padr_on']==459952|| $DatosConcepto['Padr_on']==459955))
                #$Bandera=false;
                $arr['Aplica']=0;
                $arr['FechaDetalle']=$FechaDetalle;
                $arr['FechaAValidar']=$FechaAValidar;
                $arr['FechaAValidarV5']=$DatosConcepto->FechaActualV5;
                if(($DatosConcepto->FechaActualV5>$FechaAValidar) && $Bandera ) // Se valida si ya se complieron los 2 meses y ahora si se empieza el moviendo 
                    $arr['Aplica']=1;
                $Fecha=explode("-",$FechaDetalle);
                $arr['Status']=(strtotime($DatosConcepto->FechaActualV5)>$FechaAValidar);
                $arr['Mes']=$Fecha[1];
                $arr['A_no']=$Fecha[0];
            break;
            default:
                $arr=array('Aplica'=>0,'A_no'=>0,'Mes'=>0);
            break;
    
        }
        return $arr;
    }

    public static function CalculoRecargosFechaV6_Multas($fechaConcepto, $ImporteConcepto, $fechaActualArg, $cliente=0){
		if ($cliente == 0){
			$cliente = DB::select("select C.id, Descripci_on,DF.RFC,DF.Calle,DF.N_umeroExterior,DF.Colonia,DF.C_odigoPostal, DF.CorreoElectr_onico, (select Nombre from Localidad L where DF.Localidad=L.id) as Localidad, (select Ruta from CelaRepositorioC where CelaRepositorioC.idRepositorio=C.Logotipo) as Logo from Cliente C INNER JOIN DatosFiscalesCliente DF on DF.id=C.DatosFiscales where C.id=$Cliente");
		}
		//Es Recargo
		if(is_null($fechaActualArg)){
			$fechaActualArg=date('Y-m-d');
		}
        $Actualizacion		=0;// $ActualizacionCalculable==1?CalculoActualizacionFechaV6($fechaConcepto, $ImporteConcepto, $fechaActualArg):0;
		$estatus=Funciones::CalculofactorActualizacionFechaV6($fechaConcepto, $ImporteConcepto, $fechaActualArg);
		$mesConocido=0;
		$SumaDeTasa=0;
		//precode($fechaActualArg,1);
		//$fechaHoy= date("Y-m-d", strtotime ( '-1 month' , strtotime (  $fechaActualArg )) );
		$fechaHoy= Funciones::RestarMesesAFecha($fechaActualArg, 1,1);
	 	//$fechaConcepto= date("Y-m-d", strtotime ( '-1 month' , strtotime (  $fechaConcepto )) );
		$fechaConcepto=   Funciones::RestarMesesAFecha($fechaConcepto, 1,1);
		//precode($fechaConcepto,1);
		#precode($fechaHoy,1);
		//Calculamos el numero de meses que hay entre las 2 fechas
		//$fechainicial = explode('-', substr($RegistroCotizacion['FechaCotizacion'], 0, 10));		
		//echo "<br />". $fechaHoy." - ".$fechaConcepto."<br />";
		$fechafinal = explode('-', $fechaHoy);
		$fechainicial = explode('-', $fechaConcepto);
		#precode($fechafinal,1);
		#precode($fechainicial,1);
		$fechainicialdif = new DateTime($fechaConcepto);
		$fechafinaldif = new DateTime($fechaHoy);
		$elmes=$fechainicial[1];
		$elanio=$fechainicial[0];																	
		$diferencia = $fechainicialdif->diff($fechafinaldif);
		$meses = ( $diferencia->y * 12 ) + $diferencia->m;

		#echo "Meses:".$meses;
		//$meses = $fechafinal[1]-$fechainicial[1];
		//$meses-=2;
		#$meses+=1;
		//$mesConocido=$fechainicial[1];
		//Recorremos cada uno de los meses.
		#precode($mesConocido,1);
		#precode($meses,1);
		while($mesConocido<=$meses){
			//precode($mesConocido,1);
			$fecha = Funciones::RestarMesesAFecha ( $fechaHoy,$mesConocido,1) ; //PAcoooooooo
			$fecha = explode("-", $fecha);
			setlocale(LC_TIME,"es_MX.UTF-8");
			$mes = $fecha[1];
			$a_no = $fecha[0];
			$dia =$fecha[2];
			//echo "<br />".ObtenValor("select Recargo from PorcentajeRecargo where A_no=".$a_no." and Mes=".$mes,"Recargo")." ----- "."select Recargo from PorcentajeRecargo where A_no=".$a_no." and Mes=".$mes."<br />";
			//echo $a_no."-".$mes."<br />";
			$SumaDeTasa+= floatval(Funciones::ObtenValor("select Recargo from PorcentajeRecargo where A_no=".$a_no." and Cliente=".$cliente." and Mes=".$mes,"Recargo"));
			$mesConocido++;
		}
		#echo "<br />Suma Tasa:".$SumaDeTasa;
		//Calculamos los recargos
		//$ImporteConcepto*$FactorActualizacion;
		if($Actualizacion>0)
			$Recargo=(($ImporteConcepto*$FactorActualizacion)*round($SumaDeTasa, 2))/100;
		else
			$Recargo=(($ImporteConcepto)*$SumaDeTasa)/100;
		#echo "<br />".$Recargo;
		return $Recargo;
	}

    public static function CalculofactorActualizacionFechaV6($fechaConcepto, $ImporteConcepto, $fechaActualArg=NULL){
		if(is_null($fechaActualArg)){
			$fechaActualArg=date('Y-m-d');
		}
		//Es Actualizacion
		$fechaHoy=$fechaActualArg;
		#$fechaHoy= date("Y-m-d", strtotime ( '-1 month' , strtotime (  date('Y-m-d') )) );
	 	#$fechaConcepto= date("Y-m-d", strtotime ( '-1 month' , strtotime (  $fechaConcepto )) );
		#precode($fechaHoy,1);
		#precode($fechaConcepto,1);
		$Recargoschecked="";
		$mesConocido=1;
		while(true){
            $fecha = Funciones::RestarMesesAFecha ( $fechaConcepto,$mesConocido) ; //PAcoooooooo
            $fecha = explode("-", $fecha);
            setlocale(LC_TIME,"es_MX.UTF-8");
            $mes = $fecha[1];
            $a_no = $fecha[0];
			$INPCCotizacion=Funciones::ObtenValor("select ".$mes." AS mes from IndiceActualizaci_on where A_no=".$a_no);
			if(empty($INPCCotizacion->mes) || $INPCCotizacion->mes=='NULL')
				$mesConocido++;
			else
				break;
		}
		
		$mesConocido=1;
		while(true){
			$fecha = Funciones::RestarMesesAFecha ( $fechaHoy,$mesConocido) ; //PAcoooooooo
            $fecha = explode("-", $fecha);
            setlocale(LC_TIME,"es_MX.UTF-8");
            $mes = $fecha[1];
            $a_no = $fecha[0];
			#precode($a_no."-".$mes,1);
			#precode("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,1,1);
			$INPCPago=Funciones::ObtenValor("select ".$mes." AS mes from IndiceActualizaci_on where A_no=".$a_no);
			if(empty($INPCPago->mes) || $INPCPago->mes=='NULL')
				$mesConocido++;
			else
				break;
		}	
		if($INPCCotizacion->mes>0)
			$FactorActualizacion=$INPCPago->mes/$INPCCotizacion->mes;
		else
			$FactorActualizacion=0; 
		
		if($FactorActualizacion<1){
			$FactorActualizacion=1;
		}
		return $FactorActualizacion;
	}

    public static function RestarMesesAFecha ($FechaActual,$MesesResta,$Tipo=0){
        $meses = array(1=>"Enero",2=>"Febrero",3=>"Marzo",4=>"Abril",5=>"Mayo",6=>"Junio",7=>"Julio",8=>"Agosto",9=>"Septiembre",10=>"Octubre",11=>"Noviembre",12=>"Diciembre");
        $fecha = explode("-", $FechaActual);//Separamos la fecha para proceguir a manipularla
        $A_nosRestados= intval($MesesResta/12);// Vemos cuantos años tiene los meses resta
        $fecha[0]=  $fecha[0] - $A_nosRestados;  //  al  año actual restamos los años que tiene los meses resta 
        $MesesResta= $MesesResta-($A_nosRestados*12);
        $Resultado= $fecha[1]-$MesesResta;
        if($Resultado>0){
            $fecha[1]=$fecha[1]-$MesesResta;
        }else {
            if($MesesResta==1){
                $fecha[1]=12;
                $fecha[0]=$fecha[0]-1;
            }else{
                $fecha[1]=13-$MesesResta;
                $fecha[0]=$fecha[0]-1;
            }
        }
        if($Tipo==0)
            //return "$fecha[0]";
            return "".$fecha[0]."-".$meses[$fecha[1]]."-".$fecha[2]."";
        else 
            return "$fecha[0]-$fecha[1]-$fecha[2]";
    }
}
