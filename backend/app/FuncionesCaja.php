<?php


namespace App;

use JWTAuth;
use DateTime;
use Exception;
use App\Cliente;
use App\Libs\Wkhtmltopdf;
use App\PadronAguaPotable;
use App\PadronAguaLectura;
use App\Funciones;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use \Illuminate\Support\Facades\Config;

class FuncionesCaja {

     /**
     * !Retorna un json con el nuevoken y el resultado obtenido psado por parametro.
     * @param string $token
     * @return \Illuminate\Http\JsonResponse
     */
    //terminda
    public static function  ObtenerDescuentoConceptoV3($Cotizaci_on){
       
      $TotalAPagar=0;
        $conceptos="";
        
       //$TotalCotizaci_on= ObtenValor("SELECT sum(Importe) as Total FROM ConceptoAdicionalesCotizaci_on  WHERE  Estatus=0 AND  Cotizaci_on=".$Cotizaci_on,'Total'); 
       $TotalCotizaci_on = DB::table('ConceptoAdicionalesCotizaci_on')
       ->select(DB::raw('sum(Importe) as Total'))
       ->where('Estatus', 0)
       ->where('Cotizaci_on',$Cotizaci_on)
       ->first();
  
       $TotalAPagar=$TotalCotizaci_on->Total;
      
       $obtenerXMLIngreso = DB::table('XMLIngreso')
       ->where('idCotizaci_on',$Cotizaci_on)
       ->get();
     
      if(isset($obtenerXMLIngreso['DatosExtra']) && $obtenerXMLIngreso['DatosExtra']!="")  {
         $obtenerXMLIngreso[0]->DatosExtra =  preg_replace("[\n|\r|\n\r]", "",$obtenerXMLIngreso[0]->DatosExtra);
         $DatosExtraDescuento=json_decode($obtenerXMLIngreso['DatosExtra'], true);
       } else{
          $DatosExtraDescuento['Descuento']=0;
        } 
        
      $ResultadoConceptos = DB::table('ConceptoAdicionalesCotizaci_on')
      ->select('id','Importe','Mes','A_no')
      ->where('Estatus', 0)
      ->where('Cotizaci_on',$Cotizaci_on)
      ->get();
      
      /*Consulta para ver el Privilegio descuento */
      //$ConsultaPadr_on = "SELECT p.PrivilegioDescuento FROM Padr_onAguaPotable p INNER JOIN Cotizaci_on c ON(p.id=c.Padr_on AND c.Tipo=9) WHERE c.id=".$Cotizaci_on;
      $ResultadoPadr_on =DB::table('Padr_onAguaPotable as p')
      ->join('Cotizaci_on as c', 'p.id','=','c.Padr_on' )
      ->where('c.Tipo', 9)
      ->where('c.id',$Cotizaci_on)
      ->value('p.PrivilegioDescuento');
     
        if($DatosExtraDescuento['Descuento']==""){
          $DatosExtraDescuento['Descuento']=0;
        }
       
      $TotalAPagar=$TotalAPagar-$DatosExtraDescuento['Descuento'];
      
      $totalDescuentoMes=0;
      
      if(($DatosExtraDescuento['Descuento']!="" && $DatosExtraDescuento['Descuento']!=0) || ($ResultadoPadr_on!=0)){
        
      if($ResultadoPadr_on==0){   
      $Descuentos = array();
      
      $DescuentoAcumulados = 0;
               $Descuento2=0; 
               $descuento=0;
              $ultimoConcepto=0;
              $ultimoDescuento=0;
              
                  foreach($ResultadoConceptos AS $RegistroConceptos) {
                     
                       $descuento = str_replace(",", "",number_format(($RegistroConceptos->Importe/$TotalCotizaci_on->Total)*$DatosExtraDescuento['Descuento'], 2));  
                       
                       if(isset($RegistroConceptos->A_no) && $RegistroConceptos->A_no!="" && isset($RegistroConceptos->Mes) && $RegistroConceptos->Mes!="")
                       $Descuento2=FuncionesCaja::DescuentoPorMes($RegistroConceptos->A_no,$RegistroConceptos->Mes,($RegistroConceptos->Importe-floatval($descuento)),$Cotizaci_on); 
                       $totalDescuentoMes+=str_replace(",", "",$Descuento2);
                       $DescuentoAcumulados+=str_replace(",", "",$descuento);
                       $arr[$RegistroConceptos->id] =str_replace(",", "",$descuento)+str_replace(",", "",$Descuento2);
                       $TotalAPagar=$TotalAPagar-$arr[$RegistroConceptos->id];
                       $conceptos.=$arr[$RegistroConceptos->id].",";
                       $ultimoConcepto=$RegistroConceptos->id;
                       $ultimoDescuento=floatval($descuento)+floatval($Descuento2);
                       
                  }
         if($DescuentoAcumulados!=$DatosExtraDescuento['Descuento']){
             
              $arr[$ultimoConcepto] = $ultimoDescuento-($DescuentoAcumulados-$DatosExtraDescuento['Descuento']);
       
         }
      }else if($ResultadoPadr_on!=0){
          
              $ultimoConcepto = 0;
              foreach ($ResultadoConceptos AS $RegistroConceptos) {
                       
                       $Descuento2 = 0;  
                       if(isset($RegistroConceptos->A_no) && $RegistroConceptos->A_no!="" && isset($RegistroConceptos->A_no) && $RegistroConceptos->A_no!="")
                       $Descuento2=FuncionesCaja::DescuentoPorMes($RegistroConceptos->A_no,$RegistroConceptos->Mes,($RegistroConceptos->Importe),$Cotizaci_on); 
                       $Descuento2 = str_replace(",", "",number_format($Descuento2,2));
                       $totalDescuentoMes+= floatval(str_replace(",", "",$Descuento2));
                       if($Descuento2==0)
                           $Descuento2 = $RegistroConceptos->Importe;
                       $arr[$RegistroConceptos->id] = floatval($RegistroConceptos->Importe)-floatval($Descuento2);
                       $TotalAPagar =$TotalAPagar-$arr[$RegistroConceptos->id];
                        $conceptos.=$arr[$RegistroConceptos->id].",";
                        $ultimoConcepto = $RegistroConceptos->id;
                  }
                  $total = array_sum($arr);
                  $Diferencia = 0;
                  if($totalDescuentoMes>0){
                      $Diferencia = $total - $totalDescuentoMes;
                      if($Diferencia!=0)
                      $Diferencia = ($total - $totalDescuentoMes)/2;
                      
                      $arr[$ultimoConcepto] =$arr[$ultimoConcepto] - $Diferencia;
                  }
           
      }
      }else{
          
        foreach ($ResultadoConceptos AS $RegistroConceptos) {
              #precode($RegistroConceptos,1);
              $arr[$RegistroConceptos->id] =0;
                $conceptos.=$arr[$RegistroConceptos->id].",";
          }
      }
      
       $arr['ImporteNetoADescontar'] = $TotalAPagar;
        $arr['Conceptos'] = $conceptos;
        $arr['DescuentoPorMes'] = $totalDescuentoMes;;
           return $arr;                                                                             
    }
    
    //terminada
    public static function DescuentoPorMes($A_noConcepto,$MesConcepto,$MotoNeto,$Cotizaci_on){
        $diaLimite = 18;
        
         $SeAplicaraElDescuento = 0;
        /*Esto nos servira para hacerle descuento por mes de los que tengan INPAM etc etc etc*/
        //$CotizacionDescuentoPorMes_Consulta= "SELECT p.PrivilegioDescuento FROM Cotizaci_on c INNER JOIN Padr_onAguaPotable p ON(c.Padr_on=p.id AND c.Tipo=9) WHERE c.id=".$Cotizaci_on;
        $CotizacionDescuentoPorMes=  DB::table('Padr_onAguaPotable as p')
        ->join('Cotizaci_on as c', 'p.id','=','c.Padr_on' )
        ->where('c.Tipo', 9)
        ->where('c.id',$Cotizaci_on)
        ->value('p.PrivilegioDescuento');
     // $CotizacionDescuentoPorMes = DB::select($CotizacionDescuentoPorMes_Consulta);
   
        // precode($CotizacionDescuentoPorMes,1);
           $DescuentoPorMes=0;
           
        if(isset($CotizacionDescuentoPorMes) && $CotizacionDescuentoPorMes!=0  && $CotizacionDescuentoPorMes!="" && $CotizacionDescuentoPorMes!=20){
           $SeAplicaraElDescuento = 0;
            
           $FechaActualParaDescuento=date('Y-m-d');
          
            $FechaActualParaDescuento =explode('-', $FechaActualParaDescuento);
             $a_noActual  = $FechaActualParaDescuento[0];
              $mesActual  = $FechaActualParaDescuento[1];
              $diaActual  = $FechaActualParaDescuento[2];
             
                  if($CotizacionDescuentoPorMes!=0){
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
    /*
    
    */

    //terminada
    public static  function ObtenerDescuentoPorSaldoAnticipadoV3($Cotizaci_on,$importe,$Conceptos,$cotizacionesArray,$cliente){
        $cliente=$cliente;
        $TotalCotizacionesOK=0; 
        $TotalAYROK=0; 
        //$Cotizacion= ObtenValor("SELECT * FROM  Cotizaci_on WHERE id=".$Cotizaci_on);
        $Cotizacion =DB::table('Cotizaci_on')
        ->where('id',$Cotizaci_on)
        ->first();
        $DescuentoPorMes=0;
        foreach ($cotizacionesArray as $Cotizaci_on1) {
        
            $TotalCotizacionesOK=str_replace(",", "",$TotalCotizacionesOK); 
            if(!isset($Cotizacion->Padr_on))
                $Cotizacion->Padr_on=0;
            /*  $TotalCotizacionesOK+= ObtenValor("SELECT COALESCE(sum(cac.Importe),0) as Total FROM 
            ConceptoAdicionalesCotizaci_on  cac inner join Cotizaci_on c ON(c.id=cac.Cotizaci_on)
            WHERE  c.Padr_on=".$Cotizacion['Padr_on']." AND  cac.Estatus=0 and   c.id=".$Cotizaci_on1,'Total'); */
            $TotalCotizacionesOK+=DB::table('ConceptoAdicionalesCotizaci_on as cac')
            ->join('Cotizaci_on as c', 'c.id','=','cac.Cotizaci_on' )
            ->where('c.Padr_on', $Cotizacion->Padr_on)
            ->where('cac.Estatus','0')
            ->where('c.id', $Cotizaci_on1->id)
            ->selectRaw("COALESCE(sum(cac.Importe),0) as Total")
            ->first()->Total;
            
        
            $TotalCotizacionesOK=str_replace(",", "",$TotalCotizacionesOK);
            $DescuentosV3= FuncionesCaja::ObtenerDescuentoConceptoV3($Cotizaci_on1->id);
            

            //$Cotizacion2= ObtenValor("SELECT * FROM  Cotizaci_on WHERE id=".$Cotizaci_on1);
            $Cotizacion2=DB::table('Cotizaci_on')
            ->where('id', $Cotizaci_on1->id)
            ->first();
            $TotalAYROK = str_replace(",", "",$TotalAYROK);
            if($Cotizacion2->Padr_on==$Cotizacion->Padr_on){
        
             $TotalAYROK += FuncionesCaja::ObtenerRecargosYActualizacionesCotizaci_on($Cotizaci_on1->id,$cliente);
            
            }
            if($Cotizacion2->Padr_on==$Cotizacion->Padr_on){
                $DescuentoPorMes+=$DescuentosV3['DescuentoPorMes'];
            
            }
            
        }
            $TotalesOkTodo=str_replace(",", "",$TotalAYROK)+str_replace(",", "",$TotalCotizacionesOK)-$DescuentoPorMes;
            $TotalesOkTodo = str_replace(",", "",$TotalesOkTodo);
            $actualizacionesYRecargos = 0;
            $actualizacionesYRecargos =FuncionesCaja::ObtenerRecargosYActualizacionesCotizaci_on($Cotizaci_on,$cliente);
           
            
            $descuentos= explode(',',$Conceptos);
           
            $Cotizacion=DB::table('Cotizaci_on')
            ->where('id', $Cotizaci_on)
            ->first();
            
            $TotalSaldo=0;
         if(isset($Cotizacion->Padr_on) && $Cotizacion->Padr_on!="" && $Cotizacion->Tipo==9){
             if($Cotizacion->Tipo==9){
                //$TotalSaldo = ObtenValor("SELECT SaldoNuevo as Total FROM Padr_onAguaHistoricoAbono  WHERE idPadron=".$Cotizacion['Padr_on']." ORDER BY id DESC",'Total'); 
                $TotalSaldo=DB::table('Padr_onAguaHistoricoAbono')
                ->where('idPadron',$Cotizacion->Padr_on)
                ->orderBy('id', 'DESC')
                ->value("SaldoNuevo as Total");
                
                if($TotalSaldo<=0){
                    $TotalSaldo=0;
                }
               
            }else{
              $TotalSaldo=0;   
             }
         
         }
        
         //$TotalCotizaci_on= ObtenValor("SELECT sum(Importe) as Total FROM ConceptoAdicionalesCotizaci_on  WHERE  Cotizaci_on=".$Cotizaci_on,'Total'); 
         $TotalCotizaci_on=DB::table('ConceptoAdicionalesCotizaci_on')
         ->where('Cotizaci_on',$Cotizaci_on)
         ->value( DB::raw('sum(Importe) as Total'));
         
         $TotalCotizaci_on = str_replace(",", "",$importe)+str_replace(",", "",$actualizacionesYRecargos);
          
         
         if($TotalSaldo>0){
            $TotalSaldo = number_format((str_replace(",", "",$TotalSaldo)/str_replace(",", "",$TotalesOkTodo))*str_replace(",", "",$TotalCotizaci_on),2);
         }
        $TotalSaldo = str_replace(",", "",$TotalSaldo);
        if($TotalSaldo>$TotalCotizaci_on){//aqui duda mario 
                $TotalSaldo=$TotalCotizaci_on;
                   
        }   

        //$ConsultaConeptos = "SELECT id,Importe FROM ConceptoAdicionalesCotizaci_on  WHERE Cotizaci_on=".$Cotizaci_on." AND Origen!='PAGO' AND Estatus!=-1 ";
        $ResultadoConceptos=DB::table('ConceptoAdicionalesCotizaci_on')
         ->where('Cotizaci_on',$Cotizaci_on)
         ->where('Origen','!=','PAGO')
         ->where('Estatus','!=','-1')
         ->select( 'id','Importe')
         ->get();
        
        //$ResultadoConceptos = $Conexion->query($ConsultaConeptos);
        
        if($TotalSaldo>0){
            
        $Descuentos = array();
        $DescuentoAcumulados = 0;
                $contador=0;   
                $ultimoConcepto=0;
                $ultimoDescuento=0;
                    foreach ($ResultadoConceptos as $RegistroConceptos) {
                        /*Actualizaci_ones y recargos */
                        $ayr = 0;
                            //aqui me quede mario
                           
                         $actualizacionesYRecargos = FuncionesCaja::ObtenerRecargosYActualizacionesPorConcepto($RegistroConceptos->id, $Cotizaci_on,$cliente);
                           
                            //precode($actualizacionesYRecargos,1);
                            $ayr=$actualizacionesYRecargos['Actualizaciones']+$actualizacionesYRecargos['Recargos'];
                           
                         $descuento = number_format((($RegistroConceptos->Importe+$ayr-$descuentos[$contador])/$TotalCotizaci_on)*str_replace(",", "",$TotalSaldo), 2); 
                         
                         #echo "Importe:".($RegistroConceptos['Importe']+$ayr)." Descuento :".$descuentos[$contador]." Total Cotizaci_on:".$TotalCotizaci_on. " Total de saldo:".$TotalSaldo." Descuento por Saldo:".$descuento."<br>";
                         $DescuentoAcumulados = str_replace(",", "",$DescuentoAcumulados);  
                         $DescuentoAcumulados+=str_replace(",", "",$descuento);
           
                         $arr[$RegistroConceptos->id] = str_replace(",", "",$descuento);
                         $ultimoConcepto=$RegistroConceptos->id;
                         $ultimoDescuento=str_replace(",", "",$descuento);
                         $contador++;
                         
                    }
                    #echo "Cotizaci_on".$TotalCotizaci_on." DescuentosAcomulados:".$DescuentoAcumulados." TotalSaldo".$TotalSaldo."<br>";
           if($DescuentoAcumulados!=$TotalSaldo){
               
                $arr[$ultimoConcepto] =  number_format(str_replace(",", "",$ultimoDescuento)-(str_replace(",", "",$DescuentoAcumulados)-str_replace(",", "",$TotalSaldo)),2);
         
           }
          
        }else{
            
            foreach ($ResultadoConceptos as $RegistroConceptos) {
                $arr[$RegistroConceptos->id] =number_format(0,2);
            }
        }
             return $arr;   
    }

    // ya esta terminada
    public static  function ObtenerRecargosYActualizacionesCotizaci_on($Cotizaci_on,$cliente){
        
        $actualizacionesOK = 0;
        $recargosOK = 0;
        $idCotizacion =$Cotizaci_on;
        
        $ConsultaConceptos = "SELECT c.id as idConceptoCotizacion, co.id as ConceptoCobro, co.Cantidad as Cantidad, c.Descripci_on as NombreConcepto, co.Importe as total, co.MontoBase as punitario, 
                (select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional, co.A_no, COALESCE(co.Mes, '01') as Mes, ct.Tipo, c.TipoToma
            FROM ConceptoAdicionalesCotizaci_on co 
                INNER JOIN Cotizaci_on ct ON ( co.Cotizaci_on = ct.id  )
                INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id  )
            WHERE  co.Cotizaci_on =" . $idCotizacion . " and Estatus=0 ORDER BY co.A_no DESC, COALESCE(co.Mes, '01') DESC ,  co.id ASC ";
        #precode($ConsultaConceptos,1);
        // $ResultadoConcepto = $Conexion->query($ConsultaConceptos);
        $ResultadoConcepto=DB::select($ConsultaConceptos);
        $ConceptosCotizados = '';
        $totalConcepto = 0;
        $indexConcepto = 0;
        setlocale(LC_TIME, "es_MX.UTF-8");
        //Leermos el primer concepto.
        //$RegistroConcepto = $ResultadoConcepto->fetch_assoc();
       
        $totalConcepto                                  = $ResultadoConcepto[0]->total;
        $ConceptosCotizados                             = $ResultadoConcepto[0]->idConceptoCotizacion. ',';
        $ConceptoPadre[$indexConcepto]['id']            = $ResultadoConcepto[0]->idConceptoCotizacion;
        $ConceptoPadre[$indexConcepto]['Mes']           = $ResultadoConcepto[0]->Mes;
        $ConceptoPadre[$indexConcepto]['A_no']          = $ResultadoConcepto[0]->A_no;
        $ConceptoPadre[$indexConcepto]['Total']         = 0;
        $ConceptoPadre[$indexConcepto]['Nombre']        = $ResultadoConcepto[0]->NombreConcepto;
        $ConceptoPadre[$indexConcepto]['TipoPredio']    = $ResultadoConcepto[0]->Tipo;
        $ConceptoPadre[$indexConcepto]['idConcepto']    = $ResultadoConcepto[0]->ConceptoCobro;
        $ConceptoPadre[$indexConcepto]['FechaConcepto'] = $ResultadoConcepto[0]->A_no. "-" . $ResultadoConcepto[0]->Mes. "-01";
        
        $recargosActualizaciones = array();
        $i = 0;
        foreach($ResultadoConcepto as $RegistroConcepto) {
            
           if($i != 0){
                //precode($RegistroConcepto,1);
                
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
                        $ConsultaMultas = " SELECT mi.Categor_ia as Categoria, m.id as idMulta, m.Descripci_on as DescripcionMulta, c.id as idConcepto, c.Descripci_on as DescripcionConcepto
                            FROM MultaCategor_ia mi 
                                INNER JOIN Multa m ON ( mi.Multa = m.id  )  
                                INNER JOIN ConceptoCobroCaja c ON ( mi.Concepto = c.id  )  
                            WHERE mi.Categor_ia = (select Categor_ia from ConceptoCobroCaja where id = " . $ConceptoPadre[$iC]['id'] . ")";
    
                       // $ResultadoMultas = $Conexion->query($ConsultaMultas);
                        $ResultadoMultas=DB::select($ConsultaMultas);
    
                        foreach ( $ResultadoMultas as $RegistroMultas) {
                            $fechainicial = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);
                            $elmes        = $fechainicial[1];
                            $elanio       = $fechainicial[0];
    
                            if ($RegistroMultas->idMulta== 1) {
    
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
                                     //aqui voy 
                                    if ($fecha_actual > $fecha_entrada) {
                                        $actualizacionesOK+= FuncionesCaja::CalculoActualizacionFecha($fechaVencimiento, $montoconcepto, $fechaActual);
                                      
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
                                        
                                        $recargosOK = FuncionesCaja::CalculoRecargosFechaAgua($fechaVencimiento, $montoconcepto, $fechaActual, $cliente);
                                    
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
    
   //ya esta terminada 
    public static function CalculoActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg=NULL){
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
			$mes = ucwords(strftime("%B", $fecha ));
			$a_no = strftime("%Y", $fecha );
			#precode($a_no."-".$mes,1);
			//$INPCCotizacion=ObtenValor("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,$mes);
            $INPCCotizacion =DB::table('IndiceActualizaci_on')
            ->where('A_no',$a_no)
            ->value($mes);
            
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
			//$INPCPago=ObtenValor("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,$mes);
            $INPCPago =DB::table('IndiceActualizaci_on')
            ->where('A_no',$a_no)
            ->value($mes);
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
	}
    //ya esta terminada
    public static function CalculoRecargosFechaAgua($fechaConcepto, $ImporteConcepto, $fechaActualArg=NULL , $cliente){
        //Es Recargo
        if(is_null($fechaActualArg)){
            $fechaActualArg=date('Y-m-d');
        }
            $Actualizacion = FuncionesCaja::CalculoActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg);
          
            $FactorActualizacion=FuncionesCaja::CalculofactorActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg);
            
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
            
            
            $SumaDeTasa+=floatval(DB::table('PorcentajeRecargo')
            ->where('A_no',$a_no)
            ->where('Cliente',$cliente)
            ->where('Mes',$mes)
            ->value('Recargo'));
           
            $mesConocido++;
        }
       
        if($Actualizacion>0)
                $Recargo=(($ImporteConcepto*$FactorActualizacion)*round($SumaDeTasa, 2))/100;
        else
                $Recargo=(($ImporteConcepto)*$SumaDeTasa)/100;
            
        return $Recargo;	
    }

   //ya esta terminada
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
                //$INPCCotizacion=ObtenValor("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,$mes);
                $INPCCotizacion =DB::table('IndiceActualizaci_on')
                ->where('A_no',$a_no)
                ->value($mes);
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
               // $INPCPago=ObtenValor("select ".$mes." from IndiceActualizaci_on where A_no=".$a_no,$mes);
                $INPCPago =DB::table('IndiceActualizaci_on')
                ->where('A_no',$a_no)
                ->value($mes);
                
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
            
        }
        

    //terminada
    public static  function ObtenerRecargosYActualizacionesPorConcepto($idConcepto, $idCotizacion, $cliente){
        // $FechaLimiteCotizaci_on= ObtenValor("SELECT FechaLimite  FROM Cotizaci_on  WHERE id=$idCotizacion","FechaLimite");
       
        
        $FechaLimiteCotizaci_on=DB::table('Cotizaci_on')
            ->where('id', $idCotizacion)
            ->value('FechaLimite');
            
            $ActualizacionesYRecargosConcepto=array('Actualizaciones'=>0,'Recargos'=>0 ); 
        
        $ConsultaConceptos="SELECT c.id as idConceptoCotizacion, co.id as ConceptoCobro, co.Cantidad as Cantidad, c.Descripci_on as NombreConcepto, co.Importe as total, co.MontoBase as punitario, 
        (select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional, co.A_no, COALESCE(co.Mes, '01') as Mes, ct.Tipo, c.TipoToma
        FROM ConceptoAdicionalesCotizaci_on co 
        INNER JOIN Cotizaci_on ct ON ( co.Cotizaci_on = ct.id  )
        INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id  )
        WHERE  co.id=".$idConcepto." AND  co.Cotizaci_on =".$idCotizacion." and Estatus=0 ORDER BY co.A_no DESC, COALESCE(co.Mes, '01') DESC ,  co.id ASC  ";
       
        $ResultadoConcepto=DB::select($ConsultaConceptos);
        
    #precode($ConsultaConceptos,1);
    
        $FilaConceptos='';
        $FilaActualizacion='';
        $ConceptosCotizados='';
        $totalConcepto=0;
        $idsConceptos='';
        $Contador=0;
        $ConceptoActual=0;
        $Conceptos='';
        $indexConcepto=0;
        $inicio=0;
        setlocale(LC_TIME,"es_MX.UTF-8");

        //Leermos el primer concepto.
       $RegistroConcepto=$ResultadoConcepto[0];
       
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
        $i2=0;
       
        foreach($ResultadoConcepto as $RegistroConcepto){
        //precode($RegistroConcepto,1);
        
        if($i2 != 0){
            
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
                $totalConcepto +=$RegistroConcepto->total ;
                $idsConceptos .= $RegistroConcepto->ConceptoCobro.',';
            }
            $Contador++;
            $ConceptosCotizados.=$RegistroConcepto->idConceptoCotizacion.',';
        }
        $i2++;
    
        }

        $ConceptoPadre[$indexConcepto]['Total']=$totalConcepto;
        //Buscamos actualizaciones y recargos para los conceptos a pagar
        $ActualizacionesYRecargos="";
        $PagoActualizaciones=0;
        $sumatotalActyRec=0;

        $fechaActual = date("Y-m-d");
        $auxMes = array("", "Enero", "Febrero","Marzo","Abril","Mayo","Junio","Julio", "Agosto","Septiembre","Octubre","Noviembre","Diciembre"); 
    
        for($iC=0;$iC<count($ConceptoPadre);$iC++){
          
        $ConceptoExcluidosParaActualizacionesRecargos = array(5461, 5462,5467, 5469, 2489, 5084);
        if (!in_array($ConceptoPadre[$iC]['id'], $ConceptoExcluidosParaActualizacionesRecargos)) {
            //Obtenemos las actualizaciones y recargos.
            
            if($ConceptoPadre[$iC]['FechaConcepto']!="--01"){
               // return date("Y-m", strtotime( $fechaActual ) ).">". date('Y-m', strtotime($ConceptoPadre[$iC]['FechaConcepto']));
                if(date("Y-m", strtotime( $fechaActual ) ) > date('Y-m', strtotime($ConceptoPadre[$iC]['FechaConcepto']))){
                    //Obtenemos las multas del concepto
                    
                    $ConsultaMultas= " SELECT mi.Categor_ia as Categoria, m.id as idMulta, m.Descripci_on as DescripcionMulta, c.id as idConcepto, c.Descripci_on as DescripcionConcepto
                    FROM MultaCategor_ia mi 
                    INNER JOIN Multa m ON ( mi.Multa = m.id  )  
                    INNER JOIN ConceptoCobroCaja c ON ( mi.Concepto = c.id  )  
                    WHERE mi.Categor_ia = (select Categor_ia from ConceptoCobroCaja where id = ".$ConceptoPadre[$iC]['id'].")";
                    #precode($ConsultaMultas,1);
                    //$ResultadoMultas=$Conexion->query($ConsultaMultas);
                    
                    $ResultadoMultas=DB::select($ConsultaMultas);
                    
                    foreach($ResultadoMultas as $RegistroMultas){
                        $fechainicial = explode('-', $ConceptoPadre[$iC]['FechaConcepto']);
                        $elmes=$fechainicial[1];
                        $elanio=$fechainicial[0];	
                        
                        if($RegistroMultas->idMulta==1){
                            //Es Actualizacion

                        
                            if($ConceptoPadre[$iC]['TipoPredio']==3)//3 es para predial
                            {
                                 $tipoPredio=DB::table('Cotizaci_on as c')
                                ->join('Padr_onCatastral  as pc','pc.id','=','c.Padr_on')
                                ->where('c.id', $idCotizacion)
                                ->value('pc.TipoPredio as TipoPredio');
                            
                                if($tipoPredio==10)// 10 es zofemat
                                {
                                    $descripcionActyRec='Actualizaciones y Recargos de: '.$ConceptoPadre[$iC]['Nombre']." Correspondiente al bimestre ".$elmes." del ".$elanio.'';
                                    $montopredial=  $ConceptoPadre[$iC]['Total'];        
                                    $elmes=$mes=($ConceptoPadre[$iC]['Mes']*2)+1; 

                                    $elanio=$anio=$ConceptoPadre[$iC]['A_no'];
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
                                        //$recargosOK		   = 	CalculoRecargos($fechaVencimiento, $montopredial);
                                        $actualizacionesOK = FuncionesCaja::CalculoActualizacionFecha($fechaVencimiento, $montopredial, $fechaActual);
                            
                                    }else{
                                        //	 $recargosOK		   = 0;
                                        $actualizacionesOK = 0;
                                    }
                                }else{
                                //para predial
                                    $descripcionActyRec='Actualizaciones y Recargos de: '.$ConceptoPadre[$iC]['Nombre']." Correspondiente al bimestre ".$elmes." del ".$elanio.'';
                                    $montopredial=  $ConceptoPadre[$iC]['Total'];   
                                    $elmes=	$mes=($ConceptoPadre[$iC]['Mes']*2)-1; 
                                    $elanio=$anio=$ConceptoPadre[$iC]['A_no'];
                                    if(intval($mes)>12){
                                        $mes=1;
                                        $anio=$anio+1;
                                    }

                                    $mes=str_pad($mes, 2, "0", STR_PAD_LEFT); 

                                    $dia=16;
                                    
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
                                        // $recargosOK		   = 	CalculoRecargos($fechaVencimiento, $montopredial);

                                        $actualizacionesOK = FuncionesCaja::CalculoActualizacionFecha($fechaVencimiento, $montopredial, $fechaActual );
                                    }else{
                                        //  $recargosOK		   = 0;
                                        $actualizacionesOK = 0;
                                    }
                                }
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
                                    $actualizacionesOK = FuncionesCaja::CalculoActualizacionFecha($fechaVencimiento, $montoconcepto, $fechaActual );
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
                        //aqui quedo mario:)
                        
                            if($ConceptoPadre[$iC]['TipoPredio']==3)//3 es para predial
                            {
                                //$tipoPredio=ObtenValor("SELECT pc.TipoPredio FROM Cotizaci_on c INNER JOIN Padr_onCatastral pc ON (pc.id=c.Padr_on) WHERE c.id=".$idCotizacion, "TipoPredio");
                                $tipoPredio=DB::table('Cotizaci_on as c')
                                ->join('Padr_onCatastral  as pc','pc.id','=','c.Padr_on')
                                ->where('c.id', $idCotizacion)
                                ->value('pc.TipoPredio as TipoPredio');
                            
                                if($tipoPredio==10)// 10 es zofemat
                                {
                                    $descripcionActyRec='Actualizaciones y Recargos de: '.$ConceptoPadre[$iC]['Nombre']." Correspondiente al bimestre ".$elmes." del ".$elanio.'';
                                    $montopredial=  $ConceptoPadre[$iC]['Total'];        
                                    $mes=($ConceptoPadre[$iC]['Mes']*2)+1; 
                                    $anio=$ConceptoPadre[$iC]['A_no'];
                                    
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
                                        $recargosOK		   = 	FuncionesCaja::CalculoRecargosFechaZofemat($fechaVencimiento, $montopredial, $fechaActual, $cliente );

                                    }else{
                                        $recargosOK		   = 0;
                                    }
                                }
                                else{
                                    $descripcionActyRec='Actualizaciones y Recargos de: '.$ConceptoPadre[$iC]['Nombre']." Correspondiente al bimestre ".$elmes." del ".$elanio.'';
                                    //para predial
                                    $montopredial=  $ConceptoPadre[$iC]['Total'];   
                                    $mes=($ConceptoPadre[$iC]['Mes']*2)-1; 
                                    $anio=$ConceptoPadre[$iC]['A_no'];
                                    if(intval($mes)>12){
                                        $mes=1;
                                        $anio=$anio+1;
                                    }

                                    $mes=str_pad($mes, 2, "0", STR_PAD_LEFT); 

                                    $dia=16;

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
                                        $recargosOK = FuncionesCaja::CalculoRecargosFecha($fechaVencimiento, $montopredial, $fechaActual,$cliente );
                                        //$actualizacionesOK = CalculoActualizacion($fechaVencimiento, $montopredial);
                                    }else{
                                        $recargosOK	= 0;
                                    }
                                }
                            }// termina tipo 3 predial
                            else if($ConceptoPadre[$iC]['TipoPredio']==4 || $ConceptoPadre[$iC]['TipoPredio']==1){
                                $recargosOK	= 0;
                            }else{
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
                                
                                    if(isset($FechaLimiteCotizaci_on) && $FechaLimiteCotizaci_on!=""){
                                            $arr = explode("-",$FechaLimiteCotizaci_on);
                                            $dia=$arr[2];
                                           /* if(isset($cliente) && $cliente==20)
                                                $fechaActual= nuevaFecha($fechaVencimiento);*/
                                }
                                
                                $fechaVencimiento= $anio."-".$mes."-".$dia;  
                                # InsertaValor('Bitacora', array('origen'=>'Recargos y Actualizaciones','bitacora'=>$fechaVencimiento));
                                #precode($fechaVencimiento,1,1);
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
                                    $fechaVencimiento.$montoconcepto;
                                    $recargosOK		   = 	FuncionesCaja::CalculoRecargosFecha($fechaVencimiento, $montoconcepto, $fechaActual, $cliente );
                                    //$actualizacionesOK = CalculoActualizacion($fechaVencimiento, $montoconcepto);
                                }else{
                                    $recargosOK		   = 0;
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
     
    //terminada
    public static function CalculoRecargosFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg=NULL, $cliente){
        
            
        //Es Recargo
        if(is_null($fechaActualArg)){
        $fechaActualArg=date('Y-m-d');
        }
            
            $Actualizacion		=FuncionesCaja::CalculoActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg);
            
            $FactorActualizacion=FuncionesCaja::CalculofactorActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg);
            
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
        //echo "fecha:".$meses."<br />";
        $fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ( $fechaHoy)) ;
        setlocale(LC_TIME,"es_MX.UTF-8");
        $mes = (date("m", $fecha ));
        $a_no = strftime("%Y", $fecha );
         $SumaDeTasa+= floatval(DB::table('PorcentajeRecargo')
                ->where('A_no',$a_no)
                ->where('Cliente',$cliente)
                ->where('Mes',$mes)
                ->value('Recargo'));
                
        $mesConocido++;
        }
        
        if($Actualizacion>0)
            $Recargo=(($ImporteConcepto*$FactorActualizacion)*round($SumaDeTasa, 2))/100;
        else
         $Recargo=(($ImporteConcepto)*$SumaDeTasa)/100;
       
        return $Recargo;


    }
    //terminada 
    public static function MomentoConcepto ($MomentoConcepto,$FechaConcepto){
        $fecha = explode('-', $FechaConcepto);
        $fechaCotizaci_on = $fecha[0];

        setlocale(LC_TIME,"es_MX.UTF-8");
        $EjericioActual =date("Y" );
        //Devengado                              Recaudado
         # a_no 2018 Momento 4                       a_no 2018 momento 5 el mismo año
         # a_no 2018 Momento 4                       a_no 2019 momento 14
        # a_no 2019 Momento 13                       a_no 2019 momento 14
        # a_no 2018 Momento 13                      a_no 2019 momento 14
        
        
        if($fechaCotizaci_on == $EjericioActual && $MomentoConcepto == 4)
            return 5;
        if($fechaCotizaci_on < $EjericioActual && $MomentoConcepto == 4)
            return 14;
        if($fechaCotizaci_on <= $EjericioActual && $MomentoConcepto == 13)
            return 14;
        
       
         
     }
   
     public static function verificarAdeudoPredial($Padr_on,$Tipo,$Contribuyente=null,$anioFiscal){
        return  $Condici_on=""; 
        if(is_null($Contribuyente)){
       
        #precode($consultaDeudo,1,1);
        $Resultado = DB::table("Padr_onCatastral as pap")
        ->select( DB::raw("(SELECT count(pch.Mes) As Bimestres FROM Padr_onCatastralHistorial as pch WHERE   pch.Padr_onCatastral=pap.id and pch.`Status`=1 and pch.A_no<=$anioFiscal) as Deuda"))
        ->where("pap.id",$Padr_on)
        ->first()->Deuda;
        $Condici_on="";
       
        $Resultado=isset($Resultado)?$Resultado:0;
       
       if($Resultado!=0)
           $Condici_on=$Tipo==0?" and c.FechaLimite IS NULL":1;
        
       
        return $Condici_on;
        
        }else{
            $ConsultaDecuentasPorContribuyente="SELECT DISTINCTROW c.Padr_on,c.id,c.FechaLimite FROM Cotizaci_on c INNER JOIN ConceptoAdicionalesCotizaci_on cac ON(cac.Cotizaci_on=c.id) WHERE c.Contribuyente=$Contribuyente and cac.Estatus=0  and c.Tipo=3 and c.FechaLimite IS NOT NULL";
            
             $id="0";
             $Result =  $Conexion->query($ConsultaDecuentasPorContribuyente);
             while($Record = $Result->fetch_assoc()){
                 if(verificarAdeudoPredial($Record['Padr_on'],1)==1){
                     $id.=",".$Record['id'];
                 }
             }
            $Condici_on=" and c.id NOT IN($id)";
           # precode($Condici_on,1);
            return  $Condici_on;
        }
    }
    

   


     //funciones ticket ------------------------------------------------------------------------------------
     //proceso 
     public static function Cotizaci_onPagarActualizarGeneraTicket2($DatosConceptos,$Total,$cliente,$totalDescuentos){

            $arrCotizacion			= array();
            $arrContribuyente		= array();
            $arrConceptoid			= array();
            $arrPu					= array();
            $arrImporte				= array();
            $arrActualizaciones		= array();
            $arrRecargos			= array();
            $arrDescuentos			= array();
            $arrDescuentoPorsaldo	= array();
            $arrSubtotal			= array();
            $arrConceptos			= array();
            $arrIVA					= array();
            $arrAdicionales			= array();
            $contador=0;
          
            foreach($DatosConceptos  as $concepto){
              
                $conc=explode(",",$concepto);
                $arrCotizacion[]		= $conc[0];
                $arrContribuyente[]		= $conc[1];
                $arrConceptoid[]		= $conc[2];
                $arrPu[]				= $conc[3];
                $arrImporte[]			= $conc[4];
                $arrActualizaciones[]	= $conc[5];
                $arrRecargos[]			= $conc[6];
                $arrDescuentos[]		= $conc[7];
                $arrDescuentoPorsaldo[]	= $conc[8];
                $arrSubtotal[]			= $conc[9];
               ;
                if($conc[10]=="Concepto"){
                    $arrConceptos[]		= $conc[4];
                }else{
                    if($conc[10]=="17" OR $conc[10]=="19" )//es iva
                    $arrIVA[]			= $conc[4];
                    else //es adicional comun
                    $arrAdicionales[]	= $conc[4];
                }
                  $contador++;      
            }
            
            $arrContribuyente=array_unique ( $arrContribuyente );
            $arrCotizacion=array_unique ($arrCotizacion);
            
           
            /*************Registramos el pago**************/
            //Obtenemos 1 contribuyente 
            $cotizacionContribuyente_consulta="SELECT c.id,  c.Contribuyente
            FROM
            Cotizaci_on  c
            INNER JOIN XMLIngreso x ON (x.idCotizaci_on=c.id)
            INNER JOIN ConceptoAdicionalesCotizaci_on cac ON (cac.Cotizaci_on =c.id)
            WHERE c.id IN (
            ".implode(",",$arrCotizacion)."
            )";
           
            //return $cotizacionContribuyente_consulta;
           // SELECT idUsuario FROM CelaUsuario where  ="29@gmail.com" and Cliente=29
            $usuarioCaja=DB::table("CelaUsuario")
            ->where("CorreoElectr_onico",$cliente."@gmail.com")
            ->where("Cliente",$cliente)
            ->select("idUsuario","CajaDeCobro","Cliente")
            ->first();
            $idCorteCaja=DB::select("SELECT cc.id as idCorteCaja FROM 
						CorteDeCaja cc
						INNER JOIN CelaUsuario cu ON (cu.idUsuario=cc.Usuario AND cu.CajaDeCobro=cc.CajaDeCobro)
						INNER JOIN CajaDeCobro cdc ON (cdc.id=cu.CajaDeCobro)
						WHERE
						cc.Usuario=".$usuarioCaja->idUsuario." AND
						cdc.Cliente=".$usuarioCaja->Cliente." AND
						SaldoFinal IS NULL");
           
           $cotizacionContribuyente=DB::select($cotizacionContribuyente_consulta);
           
       
        //Creamos el pago.
            /* $ConsultaInserta = sprintf("INSERT INTO Pago ( id, Monto, Usuario, Contribuyente, Fecha, Cotizaci_on, idCajaCobro, idCorteCaja) VALUES (  %s,  %s,  %s, %s, %s, %s, %s, %s )",
            GetSQLValueString(NULL, "int"),
            GetSQLValueString($_POST['TotalaPagarTotal'], "decimal"),
            GetSQLValueString($_SESSION['CELA_CveUsuario'.$_SESSION['CELA_Aleatorio']], "int"),
            GetSQLValueString($cotizacionContribuyente['Contribuyente'], "int") ,
            GetSQLValueString($_POST['FechaP_oliza'], "date") ,
            GetSQLValueString($cotizacionContribuyente['id'], "int"),
            GetSQLValueString($_SESSION['CELA_idCajaCobro'.$_SESSION['CELA_Aleatorio']], "int") ,
            GetSQLValueString($_SESSION['CELA_idCorteCaja'.$_SESSION['CELA_Aleatorio']], "int") );
            
            if($ResultadoInserta = $Conexion->query($ConsultaInserta)){
            $IdRegistroPago = $Conexion->insert_id;
            }*/
            
            /*$FechaP_oliza=  date('Y-m-d');
           $ResultadoInserta= DB::table('Pago')->insert([
                ['id' => null, 
                'Monto' => $Total,
                'Usuario' => $usuarioCaja->idUsuario,
                'Contribuyente'=>$cotizacionContribuyente[0]->Contribuyente,
                'Fecha'=>$FechaP_oliza,
                'Cotizaci_on'=>$cotizacionContribuyente[0]->id,
                'idCajaCobro'=>$usuarioCaja->CajaDeCobro,
                'idCorteCaja'=>$idCorteCaja[0]->idCorteCaja
                
                ]
            ]);
            
          
            if($ResultadoInserta){
                $IdRegistroPago = DB::getPdo()->lastInsertId();;
            }*/
              
    /************* Termina Registramos el pago**************/	

    

            $clienteDescripcion= DB::table('Cliente')
           ->where('id',$cliente)
           ->value('Descripci_on');
           
           $SelectIdCorte="SELECT cc.id as idCorteCaja FROM CorteDeCaja cc
           INNER JOIN CelaUsuario cu ON (cu.idUsuario=cc.Usuario AND cu.CajaDeCobro=cc.CajaDeCobro)
           INNER JOIN CajaDeCobro cdc ON (cdc.id=cu.CajaDeCobro) WHERE
           cc.Usuario=$usuarioCaja->idUsuario AND
           cdc.Cliente=$cliente AND
           SaldoFinal IS NULL";
           
            $idCorte=DB::select($SelectIdCorte);
            
            
            $numOperacion=DB::table("PagoTicket")
            ->where("Corte",$idCorte[0]->idCorteCaja)
            ->count();
           
            $numOperacion=($numOperacion==0?1:$numOperacion);
            $totale=intval($Total);
            $Redondeo= $Total-$totale;
            $Redondeo=number_format( $Redondeo,2);
           
            $totalDescuento= str_replace(",", "",$totalDescuentos) ;
           
            /****************************** guardamos el ticket ******************************/
            if(ObtenValor("SELECT count(id) as total FROM PagoTicket WHERE idUnico='".$_POST['uniqid']."'", "total")==0 ){
            //Creamos el pago.
            $ConsultaInserta = sprintf("INSERT INTO PagoTicket ( id, Cliente, Pago, Fecha, NumOperacion, Corte, Caja, Cajero, idPadron, Conceptos, Adicionales, IVA, Actualizaciones, Recargos, Descuentos, Anticipo, Total, TotalRecibido, idUnico, Variables) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
                GetSQLValueString(NULL, "int"),
                GetSQLValueString($_SESSION['CELA_Cliente'.$_SESSION['CELA_Aleatorio']], "int"),
                GetSQLValueString($IdRegistroPago, "int"),//pago
                GetSQLValueString($_POST['FechaP_oliza']." ".date("H:i:s"), "date") ,
                GetSQLValueString($numOperacion, "varchar") , //NumOperacion
                GetSQLValueString($idCorte, "int") ,
                GetSQLValueString($_SESSION['CELA_idCajaCobro'.$_SESSION['CELA_Aleatorio']], "int") ,
                GetSQLValueString($_SESSION['CELA_CveUsuario'.$_SESSION['CELA_Aleatorio']], "int"),
                GetSQLValueString(NULL, "int") ,//idPadron
                GetSQLValueString(array_sum ($arrConceptos ) , "decimal") ,
                GetSQLValueString(array_sum ($arrAdicionales ), "decimal") ,
                GetSQLValueString(array_sum ($arrIVA ), "decimal") ,
                GetSQLValueString(array_sum ($arrActualizaciones ) , "decimal") ,
                GetSQLValueString(array_sum ($arrRecargos ), "decimal") ,
                GetSQLValueString($totalDescuento, "decimal") ,
                GetSQLValueString($_POST['PagoAnticipado'], "decimal") ,
                GetSQLValueString($_POST['TotalaPagarTotal'], "decimal") ,
                GetSQLValueString($_POST['TotalRecibido'], "decimal")  ,
                GetSQLValueString($_POST['uniqid'], "varchar")  ,
                GetSQLValueString(json_encode($_POST), "varchar")  );
            #	precode($ConsultaInserta,1,1);
                $Conexion->query($ConsultaInserta);
            }
            $datosTicket=ObtenValor("SELECT * FROM PagoTicket WHERE idUnico='".$_POST['uniqid']."'");
            
             /*Segundo Plano */

                    /*Cunsumir Cupon de Descuento*/
                    //if(isset($_POST['DescuentoCupon']) && $_POST['DescuentoCupon']>0)
                          //      ActualizaValor('CuponesDescuento',array('idUsuarioUtilizo'=>$_SESSION['CELA_CveUsuario'.$_SESSION['CELA_Aleatorio']],'Estado'=>1,'FechaUtilizado'=>date('Y-m-d H:i:s'),'idCotizacion'=>$datosTicket['id']),'Codigo="'.$_POST['CuponDescuento'].'"');
                    //Insertamo la contabilidad de los conceptos.
        
        include_once('lib/LibSegundoPlano.php');
                /*Librerias Para Segundo Plano*/
                $raizdoc= RaizDocumento();
                // Se guardo en el repositorio el documentocompleto
                $EjercicioFiscal = $_SESSION['CELA_EjercicioFiscal'.$_SESSION['CELA_Aleatorio']];
                $Cliente = $_SESSION['CELA_Cliente'.$_SESSION['CELA_Aleatorio']];
                $Usuario = $_SESSION['CELA_CveUsuario'.$_SESSION['CELA_Aleatorio']];
                $AreaRecaudadora = $_SESSION['CELA_AreaRecaudadora' . $_SESSION['CELA_Aleatorio']];
            
                $proceso = $raizdoc . "RecaudoSegundoPlano.php ".$datosTicket['id']." ".$EjercicioFiscal." ".$idCorte;
                #precode($proceso,1,1);
                $idPadre = getmypid();
        #       precode($proceso,1,1);
            //EjecutarTarea("php5", $proceso, 0, $idPadre, "Generando Documentos");
            EjecutarSegundoPlano("php",  $proceso, $idPadre, "Generando Documentos");
                header("Location: Cotizaci_onAguaOPDPagarActualizarVistaPrevia.php?".EncodeThis('clave='.$datosTicket['id']) );


                    $p=uniqid();
                return  $p; 
     }
    

     public static function descuentoPredial($Cotizaci_ons,$Cliente,$ejercicioFiscal){
         
        $array = $Cotizaci_ons;
        $DescuentoGeneralCotizaciones=0;
        $SaldoDescontadoGeneralTodo =0;
        $ContadorDeContaizaciones=0;
        $contizaciones=$Cotizaci_ons[0]->id;
        
        $T_otalImporte = 0;
        $T_otalActualizaciones = 0;
        $T_otalRecargos = 0;
        $T_otalDescuentos = 0;
        $T_otalSaldoDescontado = 0;
        $T_otal =0;
        $DescuentosCotizaci_onV2=0;
        $DatosConceptos;

        $DecuentoDeSaldoPadr_ones = array(); // Actualmente solo aplica para el agua potable Unicamente
            foreach ($array as $Cotizaci_on) {  
                //$Cotizaci_onGeneralInformaci_on=ObtenValor("select Padr_on,Tipo from Cotizaci_on c where c.id=$Cotizaci_on and c.Tipo in(2,9,3)");
                $Cotizaci_onGeneralInformaci_on=DB::table("Cotizaci_on")
                ->select("Padr_on","Tipo")
                ->where("id",$Cotizaci_on->id)
                ->whereIn('Tipo', [2,9,3])
                ->get();
               
                $ContadorDeContaizaciones++;
                $contizaciones.=','.$Cotizaci_on->id;
                $DescuentosCotizaci_onV2 = FuncionesCaja::ObtenerDescuentoConceptoGeneralExtraCotizaci_on($Cotizaci_on->id); //Solo quitar V3 para la version anterior
              
              //  $Padr_on = ObtenValor("select Padr_on from Cotizaci_on c where c.id=$Cotizaci_on and c.Tipo in(2,9)","Padr_on");
                $Padr_on = DB::table("Cotizaci_on")
                ->where("id",$Cotizaci_on->id)
                ->whereIn('Tipo', [2,9])
                ->value("Padr_on");
               
              if(!isset($DecuentoDeSaldoPadr_ones['$Padr_on_'.$Padr_on])){
                  $DecuentoDeSaldoPadr_ones['Padr_on_'.$Padr_on]= FuncionesCaja::DescuentoDeSaldoAGUAOPD($Padr_on);
                 
              }
                 
               // $ContribuyenteCotizaci_on = ObtenValor("SELECT  if(c.Nombres is NOT NULL,CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno),c.NombreComercial)  AS Contribuyente,ct.FolioCotizaci_on   FROM Contribuyente c INNER JOIN Cotizaci_on ct ON(c.id=ct.Contribuyente) WHERE ct.id=".$Cotizaci_on);
               // $AreaAdministrativa=ObtenValor("SELECT a.Descripci_on FROM AreasAdministrativas a  INNER JOIN Cotizaci_on c ON(a.id=c.AreaAdministrativa) WHERE c.id=".$Cotizaci_on,"Descripci_on");
                    $ConsultaConceptos="SELECT ct.id as Cotizaci_on,ct.Cliente, ct.Contribuyente as Contribu, c.id as idConceptoCotizacion, co.id as ConceptoCobro, co.Cantidad as Cantidad, c.Descripci_on as NombreConcepto, co.Importe as total, co.MontoBase as punitario, (select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional,co.Adicional AS idAdicional, co.A_no, COALESCE(co.Mes, '01') as Mes, ct.Tipo, co.Padre, co.MomentoCotizaci_on,ct.Fecha AS FechaMomento,ct.Padr_on,ct.Tipo
                    FROM ConceptoAdicionalesCotizaci_on co 
                    INNER JOIN Cotizaci_on ct ON ( co.Cotizaci_on = ct.id  )
                    INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id  )  
                    WHERE  co.Cotizaci_on =".$Cotizaci_on->id." and co.Estatus=0 ORDER BY  co.A_no DESC, COALESCE(co.Mes, 1) DESC , co.id ASC ";
                    $ResultadoConcepto=DB::select($ConsultaConceptos);
                    
                    $FilaConceptos='';
                    $FilaActualizacion='';
                    $ConceptosCotizados='';
                    $totalConcepto=0;
                    $idsConceptos='';
                    $Contador=0;
                    $ConceptoActual=0;
                    $Conceptos='';
                    $indexConcepto=0;
                    $inicio=0;
                    setlocale(LC_TIME,"es_MX.UTF-8");
                    $FechaActualParaDescuento=date('Y-m-d');
                    $auxEncabezado="";
                    
                    $A_Actualizaci_on = 0;  
                    $R_Recargo = 0;
                //return $ResultadoConcepto;
                  
                    
                    foreach($ResultadoConcepto as $RegistroConcepto) {
                        
                        
                        $RegistroConcepto->DescuentoCotizaci_on=$DescuentosCotizaci_onV2;
                        $RegistroConcepto->SaldoTotalRestante = isset($DecuentoDeSaldoPadr_ones['Padr_on_'.$Padr_on])?$DecuentoDeSaldoPadr_ones['Padr_on_'.$Padr_on]:0;
                       
                       // $SaldoTotalRestante= isset($DecuentoDeSaldoPadr_ones['Padr_on_'.$Padr_on])?$DecuentoDeSaldoPadr_ones['Padr_on_'.$Padr_on]:0;
                      
                        $RegistroConcepto=FuncionesCaja::obtenExtrasV4($RegistroConcepto,$Cliente,$ejercicioFiscal);
                        
                        $DescuentosCotizaci_onV2 = $RegistroConcepto->DescuentoCotizaci_on;
                            if(isset($DecuentoDeSaldoPadr_ones['Padr_on_'.$Padr_on]))
                            $DecuentoDeSaldoPadr_ones['Padr_on_'.$Padr_on] = $RegistroConcepto->SaldoTotalRestante ;
                            if(empty($RegistroConcepto->Adicional)){
                                    
                                $totalConcepto=$RegistroConcepto->total ;
                                $idsConceptos=$RegistroConcepto->ConceptoCobro.',';
                                $Contador=0;

                            }else
                            {
                                $totalConcepto +=$RegistroConcepto->total ;
                                   $idsConceptos .= $RegistroConcepto->ConceptoCobro.',';
                            }
                            
                            $importeNeto=  $sub_total =str_replace(",", "",$RegistroConcepto->total);
                            $T_otalImporte+=$importeNeto;
                            $Actualizaci_on =str_replace(",", "",$RegistroConcepto->Actualizaci_on);
                            $T_otalActualizaciones+=$Actualizaci_on;
                            $Recargos =str_replace(",", "",$RegistroConcepto->Recargo);
                            $T_otalRecargos+=$Recargos;
                            $Descuento =str_replace(",", "",$RegistroConcepto->DescuentoOtorgado);
                            $T_otalDescuentos+=$RegistroConcepto->DescuentoOtorgado;
                            $DescuentoGeneralCotizaciones+=$RegistroConcepto->DescuentoOtorgado;
                            $saldo= str_replace(",", "",$RegistroConcepto->DescuentoSaldo);
                            $T_otalSaldoDescontado+=$saldo;
                            $SaldoDescontadoGeneralTodo+=$saldo;
                            $TotalVersion2= $sub_total=($sub_total+$Actualizaci_on+$Recargos)-$Descuento-$saldo;
                            $T_otal+=$sub_total;
                            $Auxiliar="data-importeneto=".$importeNeto." data-totalpagar=" .$sub_total." data-actualizacion=".$RegistroConcepto->Actualizaci_on." data-recargos=".$RegistroConcepto->Recargo." data-descuento=".$Descuento." data-saldodescontado=".$saldo;
                            $Actualizaci_on=number_format($RegistroConcepto->Actualizaci_on, 2);
                            $Recargos = number_format($RegistroConcepto->Recargo, 2);
                            $Descuento =number_format($RegistroConcepto->DescuentoOtorgado, 2);
                            $subtotal=number_format($sub_total,2);
                            $TipoConcepto= empty($RegistroConcepto->Adicional)?"Concepto":$RegistroConcepto->idAdicional;
                            $RegistroConcepto->MomentoCotizaci_on= FuncionesCaja::MomentoConcepto($RegistroConcepto->MomentoCotizaci_on, $RegistroConcepto->FechaMomento);
                           
                            if(isset($RegistroConcepto->Padr_on) && $RegistroConcepto->Padr_on!="")
                                $RegistroConcepto->Padr_on = $RegistroConcepto->Padr_on;
                             else 
                                $RegistroConcepto->Padr_on = 0;
                             if(isset($RegistroConcepto->Mes) && $RegistroConcepto->Mes!="")
                                 $RegistroConcepto->Mes=$RegistroConcepto->Mes;
                             else 
                                 $RegistroConcepto->Mes =0;
                             if(isset($RegistroConcepto->A_no) && $RegistroConcepto->A_no!="")
                                 $RegistroConcepto->A_no=$RegistroConcepto->A_no;
                             else 
                                 $RegistroConcepto->A_no =0;
                            
                            
                             
                            $DatosConcepto=$Cotizaci_on->id.",".$RegistroConcepto->Contribu.",".$RegistroConcepto->ConceptoCobro.",".$RegistroConcepto->punitario.",".$importeNeto.",".(str_replace(",", "",$Actualizaci_on)).",".(str_replace(",", "",$Recargos)).",".(str_replace(",", "",$Descuento)).",".$saldo.",".$TotalVersion2.",".$TipoConcepto.",".$RegistroConcepto->MomentoCotizaci_on.",".$RegistroConcepto->Padr_on.",".$RegistroConcepto->A_no.",".$RegistroConcepto->Mes.",".$RegistroConcepto->Tipo;
                            $DatosConceptos[]= $DatosConcepto;
                           
                            }
                           
                            return FuncionesCaja::Cotizaci_onPagarActualizarGeneraTicket2($DatosConceptos,$T_otal,$Cliente,$T_otalDescuentos);
                            return response()->json([
                                'success' => '1',
                                'DatosConceptos'=>$DatosConceptos,
                                 'w'=> $T_otal
                                
                            ]);
                            return $DatosConceptos;
                            print $FilaConceptos;
                            
            }//for
     }

    public static  function ObtenerDescuentoConceptoGeneralExtraCotizaci_on($Cotizaci_on){
       
       $TotalAPagar=0;
       $conceptos="";
       $TotalCotizaci_on= DB::table("ConceptoAdicionalesCotizaci_on")
       ->select(DB::raw('sum(Importe) as Total'))
       ->where('Estatus','0')
       ->where('Cotizaci_on',$Cotizaci_on)
       ->first()->Total;
      
       $TotalAPagar=$TotalCotizaci_on;
       
       $obtenerXMLIngreso =DB::table("XMLIngreso")
       ->where("idCotizaci_on",$Cotizaci_on)
       ->value("DatosExtra");
       
       $DescuentoTotalCotizaci_on=0;

       if(isset($obtenerXMLIngreso) && $obtenerXMLIngreso!=""){
        
        $DatosExtra=json_decode($obtenerXMLIngreso, true);
        
        if(isset($DatosExtra['Descuento']) && $DatosExtra['Descuento']!="")
         $DescuentoTotalCotizaci_on = FuncionesCaja::LimpiarNumeroV2(number_format(FuncionesCaja::LimpiarNumeroV2($DatosExtra['Descuento']),2));

       }
       

       return $DescuentoTotalCotizaci_on;
                                                                     
    }

    public static function LimpiarNumeroV2($numero){
        return floatval(str_replace(",", "",$numero));
    }
    public static function LimpiarNumero($numero){
        return str_replace(",", "",$numero);
    }
    public static function DescuentoDeSaldoAGUAOPD($Padr_on){
       
        if(isset($Padr_on) && $Padr_on!=""){
          
         $TotalSaldo = DB::table("Padr_onAguaHistoricoAbono")
        ->where("idPadron",$Padr_on)
        ->orderBy('id', 'DESC')
        ->value("SaldoNuevo as Total");
       
        if($TotalSaldo<=0){
           $TotalSaldo=0;   
          }
       }else{
           $TotalSaldo = 0;
          
       }
       return $TotalSaldo;
   }

   public static function obtenExtrasV4($Concepto,$Cliente,$ejercicioFiscal){
            
        if($Concepto->Tipo==3 && 1==2){
            //preguntar a paco si esta se usa 
            if(!isset($Concepto->Adicional)) {  
                    return $AYR = FuncionesCaja::ObtenerRecargosYActualizacionesPorConceptoAgrupado($Concepto,$Concepto->ConceptoCobro, $Concepto->Cotizaci_on, $Concepto->Cliente);
                    #precode($AYR,1,1);
            }else{
            $AYR['Actualizaciones']= 0;
            $AYR['Recargos'] = 0;
                }
            
        }else{
          
            $AYR = FuncionesCaja::ObtenerRecargosYActualizacionesPorConcepto($Concepto->ConceptoCobro, $Concepto->Cotizaci_on, $Concepto->Cliente); 
        }
       
        $Concepto->Actualizaci_on= $AYR['Actualizaciones'];
        $Concepto->Recargo = $AYR['Recargos'];
       
        $Concepto->totalDesglosado= 0; // Total con descuentos
       
        /*Descontamos el concepto El descuento Otorgado*/
        $Concepto->DescuentoOtorgado = 0;
        $ValorConceptoTotal= FuncionesCaja::LimpiarNumeroV2($Concepto->total);# LimpiarNumeroV2($Concepto['total'])+LimpiarNumeroV2($AYR['Actualizaciones']) + LimpiarNumeroV2($AYR['Recargos']);
       
        if(isset($Concepto->DescuentoCotizaci_on) && $Concepto->DescuentoCotizaci_on>0){
            if(floatval($ValorConceptoTotal)> floatval($Concepto->DescuentoCotizaci_on)){
                $Concepto->DescuentoOtorgado= FuncionesCaja::LimpiarNumeroV2($Concepto->DescuentoCotizaci_on);
                $Concepto->DescuentoCotizaci_on=  FuncionesCaja::LimpiarNumeroV2(number_format( FuncionesCaja::LimpiarNumeroV2($Concepto->DescuentoCotizaci_on) -  FuncionesCaja::LimpiarNumeroV2($Concepto->DescuentoCotizaci_on)));
                $Concepto->totalDesglosado=  FuncionesCaja::LimpiarNumeroV2($ValorConceptoTotal)- FuncionesCaja::LimpiarNumeroV2($Concepto->DescuentoOtorgado);
              
            }else {
                
                $Concepto->DescuentoOtorgado= FuncionesCaja::LimpiarNumeroV2($ValorConceptoTotal);
                $Concepto->DescuentoCotizaci_on= FuncionesCaja::LimpiarNumeroV2(number_format(floatval($Concepto->DescuentoCotizaci_on) - FuncionesCaja::LimpiarNumeroV2($ValorConceptoTotal),2));
                $Concepto->totalDesglosado =0;
               
            }
        }else{
            $Concepto->totalDesglosado =$ValorConceptoTotal;
        }
        
        
        /*Bloque de descuento de promociones etc. etc. etc
        */
          $Resultado = FuncionesCaja::DescuentoPromoci_onV4($Concepto,$Cliente,$ejercicioFiscal);
        
        $Concepto->DescuentoOtorgado = FuncionesCaja::LimpiarNumeroV2($Concepto->DescuentoOtorgado)+FuncionesCaja::LimpiarNumeroV2($Resultado);
        $Concepto->totalDesglosado+= FuncionesCaja::LimpiarNumeroV2( $Concepto->Actualizaci_on) +FuncionesCaja::LimpiarNumeroV2( $Concepto->Recargo);
        $Concepto->totalDesglosado-=FuncionesCaja::LimpiarNumeroV2($Resultado);
        $Concepto->DescuentoSaldo = number_format(0,2);;
        
        /*Bloque de Descuento de saldo de cuentas...*/
       
        if(isset($Concepto->SaldoTotalRestante) && $Concepto->SaldoTotalRestante>0){
            
            if(floatval($Concepto->totalDesglosado)> floatval($Concepto->SaldoTotalRestante)){
                $Concepto->DescuentoSaldo=FuncionesCaja::LimpiarNumeroV2($Concepto->SaldoTotalRestante);
                $Concepto->SaldoTotalRestante = 0;
                $Concepto->totalDesglosado = FuncionesCaja::LimpiarNumeroV2($Concepto->totalDesglosado)-FuncionesCaja::LimpiarNumeroV2($Concepto->SaldoTotalRestante);
                
            }else {
                $Concepto->DescuentoSaldo= FuncionesCaja::LimpiarNumeroV2($Concepto->totalDesglosado);
                $Concepto->SaldoTotalRestante= FuncionesCaja::LimpiarNumeroV2(floatval($Concepto->SaldoTotalRestante) - FuncionesCaja::LimpiarNumeroV2($Concepto->totalDesglosado));
                $Concepto->totalDesglosado =0;
                
            }
        }else{
            $Concepto->totalDesglosado = $Concepto->totalDesglosado;
        }
        
        return $Concepto;

        
    }


    public static  function DescuentoPromoci_onV4($Concepto,$Cliente,$ejercicioFiscal){
        #precode($Concepto,1);
                $Descuento = 0;
                
               if(isset($Concepto->Tipo)  && $Concepto->Tipo==9)
                  $Descuento+= FuncionesCaja::LimpiarNumeroV2(FuncionesCaja::ObtenerDescuentoDeAguaPotable($Concepto,$Cliente));

                   //muy pendientekldkldklskldklkkdlkslkdskdlklkdlkdls
                   //Funciones.phpshdhsjdhjshjdhjshdjh
                   //Funciones.php
                   //Funciones.php
             
               if(isset($Concepto->Tipo)  && $Concepto->Tipo==3  && 1==2 ){
                  
                  $Descuento+= FuncionesCaja::LimpiarNumeroV2(FuncionesCaja::ObtenerDescuentoDePredial($Concepto,$Cliente,$ejercicioFiscal)); // aqii va ir el descuento por Predial
               }
               
               
               /*Buscamos si en el descuento hay Actualizaciones y Recargos Activadas y en perdiodos Vigentes*/
               /*Actualizaciones*/
               $CancelarDescuento="";
               
               if(isset($Concepto->Tipo)  && $Concepto->Tipo==3){
                   //$Zofemat = ObtenValor("SELECT COUNT(pc.id) as Predio FROM Padr_onCatastral pc WHERE pc.id=".$Concepto['Padr_on']." and pc.TipoPredio=10",'Predio');
                   $Zofemat =DB::table("Padr_onCatastral")
                   ->where("id",$Concepto->Padr_on)
                   ->where("TipoPredio",10)
                   ->count();
                    if($Zofemat>0)
                       $CancelarDescuento ="and 1=2";
                   #precode($CancelarDescuento,1,1);
               }
                   
               $Tipo ="Actualizaciones";
               $ConsultaPorcenatejeDescuento = "SELECT COALESCE(SUM(Porcentaje),0) as Descuento FROM ClienteDescuentos c WHERE c.Ejercicio=".$ejercicioFiscal." AND  c.Cliente=$Cliente and c.FechaInicial<='".date('Y-m-d')."' and c.FechaFinal>='".date('Y-m-d')."' and Tipo='".$Tipo."' $CancelarDescuento";
               $PorcenatejeDescuento = DB::select($ConsultaPorcenatejeDescuento);
               $PorcenatejeDescuento=  isset($PorcenatejeDescuento[0]->Descuento)?$PorcenatejeDescuento[0]->Descuento:0;
            
               if(FuncionesCaja::LimpiarNumeroV2($PorcenatejeDescuento)>100)
                  $PorcenatejeDescuento = 100;
                
                $PorcenatejeDescuento = $PorcenatejeDescuento/100;
                $Actualizaci_on = $Concepto->Actualizaci_on;
                if($Actualizaci_on>0){
                    $Descuento+= FuncionesCaja::LimpiarNumeroV2($Actualizaci_on*$PorcenatejeDescuento);
                }
                
                $Tipo ="RecargosV2";
               $ConsultaPorcenatejeDescuento = "SELECT COALESCE(SUM(Porcentaje),0) as Descuento FROM ClienteDescuentos c WHERE c.Ejercicio=".$ejercicioFiscal." AND  c.Cliente=$Cliente and c.FechaInicial<='".date('Y-m-d')."' and c.FechaFinal>='".date('Y-m-d')."' and Tipo='".$Tipo."' $CancelarDescuento";
               $PorcenatejeDescuento = DB::select($ConsultaPorcenatejeDescuento);
               $PorcenatejeDescuento=  isset($PorcenatejeDescuento[0]->Descuento)?$PorcenatejeDescuento[0]->Descuento:0;
            
              if(FuncionesCaja::LimpiarNumeroV2($PorcenatejeDescuento)>100)
                  $PorcenatejeDescuento = 100;
                
                $PorcenatejeDescuento = $PorcenatejeDescuento/100;
               
                $Recargo = $Concepto->Recargo;
               
                if($Recargo>0){
                    $Descuento+= FuncionesCaja::LimpiarNumeroV2($Recargo*$PorcenatejeDescuento);
                }
               
                if(isset($Concepto->Tipo) && $Concepto->Tipo==3){
                    
                    $Tipo ="Buen Fin";
                    $ConsultaPorcenatejeDescuento = "SELECT COALESCE(SUM(Porcentaje),0) as Descuento FROM ClienteDescuentos c WHERE ".$Concepto->A_no."<=c.Ejercicio AND  c.Cliente=$Cliente and c.FechaInicial<='".date('Y-m-d')."' and c.FechaFinal>='".date('Y-m-d')."' and Tipo='".$Tipo."'";
                    $PorcenatejeDescuento = DB::select($ConsultaPorcenatejeDescuento);
                    $PorcenatejeDescuento=  isset($PorcenatejeDescuento[0]->Descuento)?$PorcenatejeDescuento[0]->Descuento:0;
                    
                    if(FuncionesCaja::LimpiarNumeroV2($PorcenatejeDescuento)>100)
                      $PorcenatejeDescuento= 100;

                    $PorcenatejeDescuento = $PorcenatejeDescuento/100;
                    $importeTotal = $Concepto->total;
                    if($importeTotal>0){
                        $Descuento+= FuncionesCaja::LimpiarNumeroV2($importeTotal*$PorcenatejeDescuento);
                    }
                
                /*Promociones de pago Pronto Prediales*/
                
                /*Se verifica si tiene descuento de personas bunerables*/
                
                    $CotizacionDescuentoPorMes=DB::table('Padr_onCatastral as p')
                   ->join('Cotizaci_on as c', 'p.id','=','c.Padr_on' )
                   ->where('c.Tipo', 3)
                   ->where('c.id',$Concepto->Cotizaci_on)
                   ->select('p.TipoDescuento','c.Cliente')
                   ->first();
               
                   $ConsultaPorcenatejeDescuentoPersonaBunerable ="SELECT COALESCE(SUM(Porcentaje),0) as Descuento FROM ClienteDescuentos c WHERE c.Ejercicio=".$ejercicioFiscal." AND  c.Cliente=$Cliente and c.FechaInicial<='".date('Y-m-d')."' and c.FechaFinal>='".date('Y-m-d')."' and c.idTipoDescuentoPersona='".$CotizacionDescuentoPorMes->TipoDescuento."' and Tipo='".FuncionesCaja::tipoDescuentoV2($Concepto->Tipo)."'";
                   $PorcenatejeDescuentoPersonaBunerable=DB::select($ConsultaPorcenatejeDescuentoPersonaBunerable);
                 
                   $PorcenatejeDescuento=0;
                  
                   if(isset($PorcenatejeDescuentoPersonaBunerable[0]->Descuento)&&$PorcenatejeDescuentoPersonaBunerable[0]->Descuento==0){
                      
                       $ConsultaPorcenatejeDescuento = "SELECT COALESCE(SUM(Porcentaje),0) as Descuento FROM ClienteDescuentos c WHERE  c.Ejercicio=".$Concepto->A_no." AND  c.Cliente=$Cliente and c.FechaInicial<='".date('Y-m-d')."' and c.FechaFinal>='".date('Y-m-d')."' and Tipo='".FuncionesCaja::tipoDescuentoV2($Concepto->Tipo)."' and idTipoDescuentoPersona is null";
                   } 
                  
                   $PorcenatejeDescuento = DB::select($ConsultaPorcenatejeDescuento);
                   
                   $PorcenatejeDescuento=  isset($PorcenatejeDescuento[0]->Descuento)?$PorcenatejeDescuento[0]->Descuento:0;
                   
                   if(FuncionesCaja::LimpiarNumeroV2($PorcenatejeDescuento)>100)
                      $PorcenatejeDescuento = 100;
                   $PorcenatejeDescuento = $PorcenatejeDescuento/100;
                   $importeTotal = $Concepto->total;
                   if($importeTotal>0){
                        $Descuento+= FuncionesCaja::LimpiarNumeroV2($importeTotal*$PorcenatejeDescuento);
                    }
                
                
                }
                
                
                
               return $Descuento ;
              
         }

         //pendiente pendiente pendiente  pendiente pendiente  
         public static function ObtenerDescuentoDeAguaPotable($Concepto,$Cliente){
          
             $A_noConcepto = $Concepto->A_no;
             $MesConcepto = $Concepto->Mes;
             $MotoNeto = $Concepto->totalDesglosado;
             $Cotizaci_on =  $Concepto->Cotizaci_on;
            
            //$CotizacionDescuentoPorMes= ObtenValor("SELECT p.PrivilegioDescuento,c.Cliente FROM 
            //Cotizaci_on c INNER JOIN Padr_onAguaPotable p ON(c.Padr_on=p.id AND c.Tipo=9) WHERE c.id=".$Cotizaci_on);
            $CotizacionDescuentoPorMes=DB::table("Cotizaci_on as c")
            ->join("Padr_onAguaPotable as p","c.Padr_on","=","p.id")
            ->where("c.Tipo",9)
            ->where("c.id",$Cotizaci_on)
            ->select("p.PrivilegioDescuento","c.Cliente")
            ->first();
           
            $PorcenatejeDescuento = ObtenValor("SELECT COALESCE(SUM(Porcentaje),0) as Descuento FROM ClienteDescuentos c WHERE c.Ejercicio=".$_SESSION['CELA_EjercicioFiscal'.$_SESSION['CELA_Aleatorio']]." AND  c.Cliente=$Cliente and c.FechaInicial<='".date('Y-m-d')."' and c.FechaFinal>='".date('Y-m-d')."' and c.idTipoDescuentoPersona=".$CotizacionDescuentoPorMes['PrivilegioDescuento']." and Tipo='".tipoDescuentoV2($Concepto['Tipo'])."'");
             if(LimpiarNumeroV2($PorcenatejeDescuento['Descuento'])>100)
                 $PorcenatejeDescuento['Descuento'] = 100;
             $PorcenatejeDescuento['Descuento'] = $PorcenatejeDescuento['Descuento']/100;
             $diaLimite = 18;
               
               $FechaActualParaDescuento=date('Y-m-d');
                $FechaActualParaDescuento =explode('-', $FechaActualParaDescuento);
                 $a_noActual  = $FechaActualParaDescuento[0];
                  $mesActual  = $FechaActualParaDescuento[1];
                  $diaActual  = $FechaActualParaDescuento[2];
              
                      if($CotizacionDescuentoPorMes['PrivilegioDescuento']!=0 ){
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
                                  $DescuentoPorMes = $MotoNeto>0?($MotoNeto*$PorcenatejeDescuento['Descuento']):$MotoNeto;
              
              
                               }
              
                              
            
             return $DescuentoPorMes;
        }
    
        public static function ObtenerDescuentoDePredial($Concepto,$Cliente,$ejercicioFiscal){
          
            $A_noConcepto = $Concepto->A_no;
            $MesConcepto = $Concepto->Mes;
            $MotoNeto = $Concepto->totalDesglosado;
            
            $Cotizaci_on =  $Concepto->Cotizaci_on;
            
           // $CotizacionDescuentoPorMes= ObtenValor("SELECT p.TipoDescuento,c.Cliente FROM Cotizaci_on c INNER JOIN Padr_onCatastral p ON(c.Padr_on=p.id AND c.Tipo=3) WHERE c.id=".$Cotizaci_on);
            #precode($CotizacionDescuentoPorMes,1); 
            //$PorcenatejeDescuento = ObtenValor("SELECT COALESCE(SUM(Porcentaje),0) as Descuento FROM ClienteDescuentos c WHERE c.Ejercicio=".$_SESSION['CELA_EjercicioFiscal'.$_SESSION['CELA_Aleatorio']]." AND  c.Cliente=$Cliente and c.FechaInicial<='".date('Y-m-d')."' and c.FechaFinal>='".date('Y-m-d')."' and c.idTipoDescuentoPersona=".$CotizacionDescuentoPorMes['TipoDescuento']." and Tipo='".tipoDescuentoV2($Concepto['Tipo'])."'");
            
            $CotizacionDescuentoPorMes= DB::table("Cotizaci_on as c")
            ->join("Padr_onCatastral as p","c.Padr_on","=","p.id")
            ->where("c.Tipo",3)
            ->where("c.id",$Cotizaci_on)
            ->select("p.TipoDescuento","c.Cliente")
            ->first();
         
            /*$ConsultaPorcenatejeDescuento ="SELECT COALESCE(SUM(Porcentaje),0) as Descuento
                FROM ClienteDescuentos c 
                WHERE 
                    c.Ejercicio=2020 AND  
                    c.Cliente=$Cliente 
                and 
                c.FechaInicial<='".date('Y-m-d')."' 
                and 
                c.FechaFinal>='".date('Y-m-d')."' and
                c.idTipoDescuentoPersona=".$CotizacionDescuentoPorMes->TipoDescuento." and 
                Tipo='".FuncionesCaja::tipoDescuentoV2($Concepto->Tipo)."'";*/
                $ConsultaPorcenatejeDescuento ="SELECT COALESCE(SUM(Porcentaje),0) as Descuento
                FROM ClienteDescuentos c 
                WHERE 
                    c.Ejercicio=$ejercicioFiscal AND  
                    c.Cliente=$Cliente 
                and 
                c.FechaInicial<='".date('Y-m-d')."' 
                and 
                c.FechaFinal>='".date('Y-m-d')."' and
                c.idTipoDescuentoPersona=".$CotizacionDescuentoPorMes->TipoDescuento." and 
                Tipo='".FuncionesCaja::tipoDescuentoV2($Concepto->Tipo)."'";
                
            $PorcenatejeDescuento=DB::select($ConsultaPorcenatejeDescuento);
         
            $DescuentoPorMes = 0;
            if(isset($PorcenatejeDescuento[0]->Descuento) && $PorcenatejeDescuento[0]->Descuento!=""){
            
            if(FuncionesCaja::LimpiarNumeroV2($PorcenatejeDescuento[0]->Descuento)>100)
               $PorcenatejeDescuento[0]->Descuento = 100;
            $PorcenatejeDescuento[0]->Descuento = $PorcenatejeDescuento[0]->Descuento/100;
            $diaLimite = 18;
           
            $FechaActualParaDescuento=date('Y-m-d');
            $FechaActualParaDescuento =explode('-', $FechaActualParaDescuento);
            $a_noActual  = $FechaActualParaDescuento[0];
            $mesActual  = $FechaActualParaDescuento[1];
            $diaActual  = $FechaActualParaDescuento[2];
           
             
            if($CotizacionDescuentoPorMes->TipoDescuento!=0 ){
                $SeAplicaraElDescuento = 1;
            }
            $A_noADescontar = $a_noActual;
            $MesADescontar =20;
            if($diaActual>$diaLimite &&  $a_noActual == $A_noConcepto ){
                 $buscarMesVigente = 1;//Cero de que no va aplicar ya se caduco
                #Los meses se quedan normales
                 $MesADescontar =$mesActual;
            }  
            $DescuentoPorMes = 0;
            if( $A_noConcepto>=$A_noADescontar){
                 $DescuentoPorMes = $MotoNeto>0?($MotoNeto*$PorcenatejeDescuento[0]->Descuento):$MotoNeto;
            }
              
            }           
            
             return $DescuentoPorMes;
        }

       public static function tipoDescuentoV2($Tipo){
            $resultado ="SinDescuento";
            switch ($Tipo){
               case 2:
                   $resultado ='Agua Potable'; 
                   break;
               case 3: 
                   $resultado ='Predial'; 
                   break;
               case 9:
                   $resultado ='Agua Potable';
                   break;
            }
            return $resultado;
        }
        
     public static function cajaSinDescuento($resultadoCotizaciones,$cliente){
       
        $Cotizaciones;
        $resultadoCotizaciones;
        $SaldosActuales;
        $ResultadoConcepto;
        $T_otalImporte = 0;
        $T_otalActualizaciones = 0;
        $T_otalRecargos = 0;
        $T_otalDescuentos = 0;
        $T_otalSaldoDescontado = 0;
        $T_otal =0;
        $DescuentoGeneralCotizaciones=0;
        $SaldoDescontadoGeneralTodo =0;
        //$DatosConceptos=array();
        $FilaConceptos="";
        $claveAgrupar="";
        $aux="";
        $subtotal;
        $c;
        $DatosConceptos;
        foreach($resultadoCotizaciones as $Cotizacion){
            // return $Cotizacion->id;
           $Descuentos =FuncionesCaja::ObtenerDescuentoConceptoV3($Cotizacion->id);
           
          //return  $Descuentos ;
           $SaldosActuales=  FuncionesCaja::ObtenerDescuentoPorSaldoAnticipadoV3($Cotizacion->id,$Descuentos['ImporteNetoADescontar'],$Descuentos['Conceptos'],$resultadoCotizaciones,$cliente);
          // return $SaldosActuales;
          
           $ConsultaConceptos="SELECT ct.Contribuyente as Contribu, c.id as idConceptoCotizacion, co.id as ConceptoCobro, co.Cantidad as Cantidad, c.Descripci_on as NombreConcepto, co.Importe as total, co.MontoBase as punitario, (select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=co.Adicional) as Adicional,co.Adicional AS idAdicional, co.A_no, COALESCE(co.Mes, '01') as Mes, ct.Tipo, co.Padre, co.MomentoCotizaci_on,ct.Fecha AS FechaMomento,ct.Padr_on,ct.Tipo
           FROM ConceptoAdicionalesCotizaci_on co 
           INNER JOIN Cotizaci_on ct ON ( co.Cotizaci_on = ct.id  )
           INNER JOIN ConceptoCobroCaja c ON ( co.ConceptoAdicionales = c.id  )  
           WHERE  co.Cotizaci_on =".$Cotizacion->id." and co.Estatus=0 ORDER BY  co.A_no DESC, COALESCE(co.Mes, '01') DESC , co.id ASC ";
          $ResultadoConcepto =DB::select($ConsultaConceptos);
           
          foreach($ResultadoConcepto as $RegistroConcepto){
             $ActualizacionesYRecargosFunciones= FuncionesCaja::ObtenerRecargosYActualizacionesPorConcepto($RegistroConcepto->ConceptoCobro,$Cotizacion->id,$cliente);
            
             if(empty($RegistroConcepto->Adicional))
             {
             $totalConcepto=$RegistroConcepto->total ;
             $idsConceptos=$RegistroConcepto->ConceptoCobro.',';
             $Contador=0;
 
             }else
             {
             $totalConcepto +=$RegistroConcepto->total ;
             $idsConceptos .= $RegistroConcepto->ConceptoCobro.',';
             }
 
             $importeNeto=  $sub_total =str_replace(",", "",$RegistroConcepto->total);
             $T_otalImporte+=$importeNeto;
             $Actualizaci_on =str_replace(",", "",$ActualizacionesYRecargosFunciones['Actualizaciones']);
             $T_otalActualizaciones+=$Actualizaci_on;
             $Recargos =str_replace(",", "",$ActualizacionesYRecargosFunciones['Recargos']);
             $T_otalRecargos+=$Recargos;
             
             $Descuento =str_replace(",", "",$Descuentos[$RegistroConcepto->ConceptoCobro]);
             $T_otalDescuentos+=$Descuento;
             $DescuentoGeneralCotizaciones+=$Descuento;
             $saldo= str_replace(",", "",$SaldosActuales[$RegistroConcepto->ConceptoCobro]);
             $T_otalSaldoDescontado+=$saldo;
             $SaldoDescontadoGeneralTodo+=$saldo;
             $TotalVersion2= $sub_total=($sub_total+$Actualizaci_on+$Recargos)-$Descuento-$saldo;
             
             $Auxiliar="data-importeneto=".$importeNeto." data-totalpagar=" .$sub_total." data-actualizacion=".$Actualizaci_on." data-recargos=".$Recargos." data-descuento=".$Descuento." data-saldodescontado=".$saldo;
             $Actualizaci_on=number_format($ActualizacionesYRecargosFunciones['Actualizaciones'], 2);
             $Recargos = number_format($ActualizacionesYRecargosFunciones['Recargos'], 2);
             $Descuento =number_format($Descuentos[$RegistroConcepto->ConceptoCobro], 2);
             $subtotal=number_format($sub_total,2);
             $T_otal+=$sub_total;
            
             $TipoConcepto= empty($RegistroConcepto->Adicional)?"Concepto":$RegistroConcepto->idAdicional;
            
             $RegistroConcepto->MomentoCotizaci_on= FuncionesCaja::MomentoConcepto($RegistroConcepto->MomentoCotizaci_on, $RegistroConcepto->FechaMomento);
            
             if(isset($RegistroConcepto->Padr_on) && $RegistroConcepto->Padr_on!="")
                 $RegistroConcepto->Padr_on = $RegistroConcepto->Padr_on;
              else 
                 $RegistroConcepto->Padr_on = 0;
              if(isset($RegistroConcepto->Mes) && $RegistroConcepto->Mes!="")
                  $RegistroConcepto->Mes=$RegistroConcepto->Mes;
              else 
                  $RegistroConcepto->Mes=0;
              if(isset($RegistroConcepto->A_no) && $RegistroConcepto->A_no!="")
                  $RegistroConcepto->A_no=$RegistroConcepto->A_no;
              else 
                  $RegistroConcepto->A_no =0;
              
             $DatosConcepto=$Cotizacion->id.",".$RegistroConcepto->Contribu.",".$RegistroConcepto->ConceptoCobro.",".$RegistroConcepto->punitario.",".$importeNeto.",".(str_replace(",", "",$Actualizaci_on)).",".(str_replace(",", "",$Recargos)).",".(str_replace(",", "",$Descuento)).",".$saldo.",".$TotalVersion2.",".$TipoConcepto.",".$RegistroConcepto->MomentoCotizaci_on.",".$RegistroConcepto->Padr_on.",".$RegistroConcepto->A_no.",".$RegistroConcepto->Mes.",".$RegistroConcepto->Tipo;
             $DatosConceptos[]= $DatosConcepto;
             //$DatosConceptos[]=$Cotizacion.",".$RegistroConcepto->Contribu.",".$RegistroConcepto->ConceptoCobro.",".$RegistroConcepto->punitario.",".$importeNeto.",".(str_replace(",", "",$Actualizaci_on)).",".(str_replace(",", "",$Recargos)).",".(str_replace(",", "",$Descuento)).",".$saldo.",".$TotalVersion2.",".$TipoConcepto.",".$RegistroConcepto->MomentoCotizaci_on.",".$RegistroConcepto->Padr_on.",".$RegistroConcepto->A_no.",".$RegistroConcepto->Mes.",".$RegistroConcepto->Tipo;
             //array_push($DatosConceptos,$Cotizacion.",".$RegistroConcepto->Contribu.",".$RegistroConcepto->ConceptoCobro.",".$RegistroConcepto->punitario.",".$importeNeto.",".(str_replace(",", "",$Actualizaci_on)).",".(str_replace(",", "",$Recargos)).",".(str_replace(",", "",$Descuento)).",".$saldo.",".$TotalVersion2.",".$TipoConcepto.",".$RegistroConcepto->MomentoCotizaci_on.",".$RegistroConcepto->Padr_on.",".$RegistroConcepto->A_no.",".$RegistroConcepto->Mes.",".$RegistroConcepto->Tipo);
             $Contador++;
            // $ConceptosCotizados.=$RegistroConcepto['idConceptoCotizacion'].',';
         }
      
       }
       
       
       return FuncionesCaja::Cotizaci_onPagarActualizarGeneraTicket2($DatosConceptos,$T_otal,$cliente,$T_otalDescuentos);
       return response()->json([
            'success' => '1',
            'DatosConceptos'=>$DatosConceptos,
             'w'=> $T_otal
            
        ]);
    }


	public static function CalculoRecargosFechaZofemat($fechaConcepto, $ImporteConcepto, $fechaActualArg=NULL, $cliente){
           
        //Es Recargo
        if(is_null($fechaActualArg)){
            $fechaActualArg=date('Y-m-d');
        }
                $Actualizacion		=CalculoActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg);
                $FactorActualizacion=CalculofactorActualizacionFecha($fechaConcepto, $ImporteConcepto, $fechaActualArg);


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
            //echo "fecha:".$meses."<br />";
            $fecha = strtotime ( '-'.$mesConocido.' month' , strtotime ( $fechaHoy)) ;
            setlocale(LC_TIME,"es_MX.UTF-8");
            $mes = (date("m", $fecha ));
            $a_no = strftime("%Y", $fecha );
            //$SumaDeTasa+= floatval(ObtenValor("select Recargo from PorcentajeRecargoZofemat where A_no=".$a_no." and Cliente=".$cliente." and Mes=".$mes,"Recargo"));

            $SumaDeTasa+=floatval(DB::table("PorcentajeRecargoZofemat")
            ->where("A_no",$a_no)
            ->where("Cliente",$cliente)
            ->where("Mes",$mes)
            ->value("Recargo")
             );
            $mesConocido++;
            }
        
        if($Actualizacion>0)
            $Recargo=(($ImporteConcepto*$FactorActualizacion)*round($SumaDeTasa, 2))/100;
        else
            $Recargo=(($ImporteConcepto)*$SumaDeTasa)/100;
        #echo "<br />".$Recargo;
        return $Recargo;


    }


    public static function ProrratiarDescuentoIndividual($DescuentoGeneral, $arr){
        $arrAux=array();
     
      foreach ($arr as $key => $value)
             $arrAux[$key]=$value[0];
             $arr=$arrAux;
                $keys = array_keys($arr);
                $arrAux = array();
                $SumaTotal = array_sum($arr);
                $KeyReajustar = 0;
                    foreach ($arr as $key => $value) {
                        if($DescuentoGeneral>0)
                        $Descuento = (floatval ((FuncionesCaja::LimpiarNumero($DescuentoGeneral)) / floatval(FuncionesCaja::LimpiarNumero($SumaTotal))) * floatval(FuncionesCaja::LimpiarNumero($value)));
                        else
                            $Descuento = 0;
                        $arrAux[$key] = $Descuento;
                            if ($value>= 0)
                                $KeyReajustar = $key;

            }
        $TotalAjustado = array_sum(FuncionesCaja::LimpiarNumero($arrAux));
        $Ajuste= floatval($DescuentoGeneral) - floatval($TotalAjustado); 
        if(isset($arrAux[$KeyReajustar]))
        $arrAux[$KeyReajustar]= $arrAux[$KeyReajustar] - $Ajuste;
        return $arrAux;
    }
    

    public static function ProrratiarDescuentoGeneral($DescuentoGeneral, $arr){
        $keys = array_keys($arr);
        $AuxArr = array();
        $SumaTotal = array_sum($arr);
        $KeyReajustar = 0;
            foreach ($arr as $key => $value) {
                if($DescuentoGeneral>0 && $SumaTotal>0)
                $Descuento =  ((FuncionesCaja::LimpiarNumeroV2($DescuentoGeneral) / FuncionesCaja::LimpiarNumeroV2($SumaTotal)) * FuncionesCaja::LimpiarNumeroV2($value));
                else
                    $Descuento=0;
                $AuxArr[] = $Descuento;
                    if ($value > 0)
                        $KeyReajustar = $key - 1;

            }
            
        $TotalAjustado = array_sum($AuxArr);
        $Ajuste= $DescuentoGeneral - $TotalAjustado;
        if(isset($AuxArr[$KeyReajustar]))
        $AuxArr[$KeyReajustar]= $AuxArr[$KeyReajustar] - $Ajuste;
         return $AuxArr;
    }



    /*Predial Catastro*/
    
    public static function ReciboPredial($idTiket,$arrayConceptos, $Padr_on, $arrContribuyente,$Pago, $Cliente, $DescuentosGenerales, $PagoAnticipado,$NoRecibos=0){
        global $Conexion;
        $CTotalPagar = 0;
         if($NoRecibos>0)
           $NoRecibos--;
        $OtraHoja='class="break"';
        foreach ($arrayConceptos AS $key => $Concepto) {
         $CTotalPagar+=$Concepto[9];
        }
        $TotalPagar= $CTotalPagar;       
        
        /*Recibo de Agua Potable */
        $meses = array("","Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");

        $id=$idTiket;
        $rutacompleta ="https://suinpac.piacza.com.mx/";
        $ExisteDescuento=Funciones::ObtenValor("SELECT (SELECT Nombre FROM TipoDescuentoPersona WHERE id=cd.idTipoDescuentoPersona) TipoDescuento FROM Padr_onCatastral p INNER JOIN ClienteDescuentos cd ON (cd.idTipoDescuentoPersona=p.TipoDescuento) WHERE CURDATE() BETWEEN FechaInicial AND FechaFinal AND cd.Tipo='Predial' AND cd.Cliente=p.Cliente AND p.id=$Padr_on","TipoDescuento");

        $UUIDConsulta="SELECT  x.uuid FROM PagoTicket t
        INNER JOIN EncabezadoContabilidad ec ON (ec.Pago=t.Pago)
        INNER JOIN DetalleContabilidad dc ON (dc.EncabezadoContabilidad=ec.id)
        INNER JOIN Cotizaci_on c ON (c.id=dc.Cotizaci_on)
        INNER JOIN XMLIngreso x ON (x.idCotizaci_on=c.id)
        INNER JOIN XML x2 ON (x2.id=x.`xml`)
        #INNER JOIN 
        WHERE
        t.Pago=$Pago and c.Contribuyente=".$arrContribuyente[0]." GROUP BY x.uuid";
                $uuid ="";
                $uuid = Funciones::ObtenValor($UUIDConsulta,"uuid");
               
        $RegistroPago = Funciones::ObtenValor("SELECT * FROM Pago WHERE id=$Pago");
        
        $Elaboro=Funciones::ObtenValor("SELECT NombreCompleto  FROM Pago INNER JOIN CelaUsuario ON (CelaUsuario.idUsuario=Pago.Usuario) where Pago.id=$Pago ","NombreCompleto");
        $DatosPago=Funciones::ObtenValor("SELECT d.RFC, d.NombreORaz_onSocial, d.EntidadFederativa, d.Localidad, d.Municipio, d.Colonia, d.Calle, d.N_umeroExterior, d.C_odigoPostal, c1.id as Contribuyente
        FROM Contribuyente c1  
        INNER JOIN DatosFiscales d ON ( c1.DatosFiscales = d.id  )  
        WHERE c1.id=$arrContribuyente[0]");
        
        $DatosFiscales=$DatosPago->RFC."<br> Calle ".ucwords(strtolower($DatosPago->Calle))."<br> Colonia ".ucwords(strtolower($DatosPago->Colonia))."<br> ".ucwords(strtolower((is_numeric($DatosPago->Localidad)?Funciones::ObtenValor("select Nombre from Localidad where id=".$DatosPago->Localidad, "Nombre"):$DatosPago->Localidad)))." ".ucwords(strtolower((is_numeric($DatosPago->Municipio)?Funciones::ObtenValor("select Nombre from Municipio where id=".$DatosPago->Municipio, "Nombre"):$DatosPago->Municipio)))." ".ucwords(strtolower((is_numeric($DatosPago->EntidadFederativa)?Funciones::ObtenValor("select Nombre from EntidadFederativa where id=".$DatosPago->EntidadFederativa, "Nombre"):$DatosPago->EntidadFederativa)));
        #$PadronAgua = ObtenValor("SELECT *,(SELECT c.Nombre FROM Municipio c where c.id=Municipio) Municipio,(SELECT c.Nombre FROM Localidad c where c.id=Localidad)AS Localidad,(Select CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno) FROM Contribuyente c where c.id= Contribuyente) AS Propietario,(SELECT Concepto FROM TipoTomaAguaPotable  WHERE id=TipoToma) as TipoDeToma FROM Padr_onAguaPotable WHERE id=$Padr_on");
       
        /*Bloque del Recibo Predial*/

        $datosPadron=Funciones::ObtenValor("SELECT 
        (select   if(c.PersonalidadJur_idica=1,IF( CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno) IS NOT NULL AND CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno)!='',CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno), d.NombreORaz_onSocial),d.NombreComercial) from  Contribuyente c  INNER JOIN DatosFiscales d ON(d.id=c.DatosFiscales) where c.id = pc.Contribuyente ) as Propietario,
        pc.Cuenta, pc.CuentaAnterior, pc.Ubicaci_on, pc.Colonia, pc.id,pc.Manzana,pc.Lote
        ,(SELECT c.Nombre FROM Municipio c where c.id=pc.Municipio) Municipio,(SELECT c.Nombre FROM Localidad c where c.id=pc.Localidad)AS Localidad
        FROM Padr_onCatastral pc  
        WHERE
        pc.id=".$Padr_on);
        #precode($datosPadron,1,1);
        //(select   if(c.PersonalidadJur_idica=1,IF( CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno) IS NOT NULL AND CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno)!='',CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno), d.NombreORaz_onSocial),d.NombreORaz_onSocial) from  Contribuyente c  INNER JOIN DatosFiscales d ON(d.id=c.DatosFiscales) where c.id = Padr_onAguaPotable2.Contribuyente ) /as/ Contribuyente/*/
        $Copropietarios="";
        $ConsultaCopropietarios = "SELECT ( SELECT CONCAT(IF (c.NombreComercial IS NULL OR c.NombreComercial='',CONCAT_WS(' ',c.Nombres,c.ApellidoPaterno,c.ApellidoMaterno),c.NombreComercial)) 
        FROM `Contribuyente` c WHERE c.id=pcc.idContribuyente) AS CoPropietario FROM Padr_onCatastralCopropietarios pcc WHERE pcc.idPadron=".$datosPadron->id;
        $ejecutaCopropietarios=DB::select($ConsultaCopropietarios);
        $row_cnt = count($ejecutaCopropietarios);
        $aux=1;
        foreach($ejecutaCopropietarios as $registroCopropietarios){
            if($aux==$row_cnt){
                $Copropietarios.='<b>'.$registroCopropietarios->CoPropietario.'</b><br /> ';
            }else{
                $Copropietarios.='<b>'.$registroCopropietarios->CoPropietario.',</b> <br /> ';
            }
            $aux++;
        }
        if($Copropietarios!=""){
            $Copropietarios = '<b>Copropietarios</b> <br />'.$Copropietarios ;
        }
        
        $impresiondatos="C. ".$datosPadron->Propietario."<br />".$Copropietarios.
                "Clave: <b>".$datosPadron->Cuenta."</b> &nbsp;&nbsp;&nbsp;&nbsp; Cuenta: <b>".$datosPadron->CuentaAnterior."</b><br />".
                "Ubicaci&oacute;n: <b>".$datosPadron->Ubicaci_on."</b><br />".
                "Colonia: <b>".$datosPadron->Colonia."</b>";
        #precode($datosPadron,1,1);


        $DatosFiscales="<b>RFC</b>: ".$DatosPago->RFC."<br> <b>Direci&oacute;n:</b> ".ucwords(strtolower($DatosPago->Calle))." ".$DatosPago->N_umeroExterior." ".ucwords(strtolower($DatosPago->Colonia)).", C.P.".$DatosPago->C_odigoPostal."<br><b>Localidad:</b>  ".ucwords(strtolower((is_numeric($DatosPago->Localidad)?Funciones::ObtenValor("select Nombre from Localidad where id=".$DatosPago->Localidad, "Nombre"):$DatosPago->Localidad)))." <br><b>Municipio: </b> ". (is_numeric($DatosPago->Municipio)?Funciones::ObtenValor("select Nombre from Municipio where id=".$DatosPago->Municipio, "Nombre"):$DatosPago->Municipio)." "
        . "".ucwords(strtolower((is_numeric($DatosPago->EntidadFederativa)?Funciones::ObtenValor("select Nombre from EntidadFederativa where id=".$DatosPago->EntidadFederativa, "Nombre"):$DatosPago->EntidadFederativa)));


        $hojamembretada=Funciones::ObtenValor("(select Ruta from CelaRepositorio where CelaRepositorio.idRepositorio=(select HojaMembretada from Cliente where id=$Cliente))","Ruta");
        $Cliente=Funciones::ObtenValor("select C.id AS Cliente, Descripci_on,DF.RFC,DF.Calle,DF.N_umeroExterior,DF.Colonia,DF.C_odigoPostal, DF.CorreoElectr_onico, (select Nombre from Localidad L where DF.Localidad=L.id) as Localidad, (select Ruta from CelaRepositorioC where CelaRepositorioC.idRepositorio=C.Logotipo) as Logo from Cliente C INNER JOIN DatosFiscalesCliente DF on DF.id=C.DatosFiscales where C.id=$Cliente");
        $CuentaBancaria=Funciones::ObtenValor("SELECT (SELECT Nombre FROM Banco B WHERE B.id = CB.Banco) as Banco, N_umeroCuenta, Clabe from CuentaBancaria CB WHERE CuentaDeRecaudacion=1 and CB.Cliente=".$Cliente->Cliente." limit 1;");
       
        if($CuentaBancaria->result=='NULL') {
        $Banco = "";
        $N_umeroCuenta = "";
        $Clabe = "";
        }
        else{
        $Banco = $CuentaBancaria->Banco;
        $N_umeroCuenta = $CuentaBancaria->N_umeroCuenta;
        $Clabe = $CuentaBancaria->Clabe;
        }
        $DatosFiscalesC=Funciones::ObtenValor("(select * from DatosFiscalesCliente where DatosFiscalesCliente.id=(select DatosFiscales from Cliente where id=".$Cliente->Cliente."))");
        $LugarDePago=(is_numeric($DatosFiscalesC->Municipio)?Funciones::ObtenValor("select Nombre from Municipio where id=".$DatosFiscalesC->Municipio, "Nombre"):$DatosFiscalesC->Municipio)." ".(is_numeric($DatosFiscalesC->EntidadFederativa)?Funciones::ObtenValor("select Nombre from EntidadFederativa where id=".$DatosFiscalesC->EntidadFederativa, "Nombre"):$DatosFiscalesC->EntidadFederativa);

        $tamanio_dehoja="735px"; //735 ideal
        $N_umeroP_oliza='';

        $Conceptos='';
        $descripcion="";
        $importe="";

        //Numero Maximo de Conceptos 9
        $Conceptototal=0;


        $Actualizaci_on=0;
        $Recargo =0;
        $Descuento = 0;
        $DescuentoDeSaldo = 0;
        $ActualizacionesYRecargos=0;
        $totalConceptos=0;
        $ultimoImporte = 0;
        $ArregloConceptos[][]=NULL;
        $A_noConcepto = array();
        $ConceptoGeneral =array();
        $conceptosss="0";
        if(1==1){
        foreach ($arrayConceptos as $key => $Concepto) {
            $conceptosss.=",".$Concepto[2];
        }
        #precode($conceptosss,1,1);
        $Contador=0;
        $TexotAuxiliar="";
        foreach ($arrayConceptos as $key => $Concepto) {
            $Descuento+=$Concepto[7];
            
        $ActualizacionesYRecargos+=$Concepto[5]+$Concepto[6];
        $DescuentoDeSaldo+=$Concepto[8];
        
        $RegistroConceptos = Funciones::ObtenValor("SELECT DISTINCT cac.Estatus,cac.id,ccc.id as AYR,cac.Importe,(select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=cac.Adicional) as Adicional,cac.Adicional as Adicionalid, ccc.Descripci_on,cac.Mes AS Mes,	cac.A_no AS A_no, cac.ConceptoAdicionales FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN ConceptoCobroCaja ccc ON(ccc.id = cac.ConceptoAdicionales)WHERE cac.id=$Concepto[2] order by cac.A_no DESC, cac.Mes ASC");
        
         
        if($Concepto[10]!='Gastos'){
            if(!is_null($RegistroConceptos->Adicionalid))
                $RegistroConceptos->ConceptoAdicionales=$RegistroConceptos->Adicionalid;
            if(isset($ConceptoGeneral[$Concepto[13]][$RegistroConceptos->ConceptoAdicionales."NombreConcepto"])){
               $ConceptoGeneral[$Concepto[13]][$RegistroConceptos->ConceptoAdicionales."Importe"]+=$RegistroConceptos->Importe;
               if(!isset($RegistroConceptos->Adicionalid))
               $ConceptoGeneral[$Concepto[13]][$RegistroConceptos->ConceptoAdicionales."Meses"].=",".$RegistroConceptos->Mes;
           }else{
               $ConceptoGeneral[$Concepto[13]][$RegistroConceptos->ConceptoAdicionales."NombreConcepto"]= isset($RegistroConceptos->Adicionalid)?$RegistroConceptos->Adicional:$RegistroConceptos->Descripci_on;
                $ConceptoGeneral[$Concepto[13]][$RegistroConceptos->ConceptoAdicionales."Importe"]=$RegistroConceptos->Importe;
                if(!isset($RegistroConceptos->Adicionalid))
                $ConceptoGeneral[$Concepto[13]][$RegistroConceptos->ConceptoAdicionales."Meses"]=$RegistroConceptos->Mes;
           }
            }else if($Concepto[10]=='Gastos'){
                $RegistroConceptosV2 = Funciones::ObtenValor("SELECT  ccc.Descripci_on FROM   ConceptoCobroCaja ccc  WHERE ccc.id=$Concepto[16]","Descripci_on");
              if(isset($ConceptoGeneral[$Concepto[13]][$Concepto[16]."NombreConcepto"])){
               $ConceptoGeneral[$Concepto[13]][$Concepto[16]."Importe"]+=$Concepto[4];
               $ConceptoGeneral[$Concepto[13]][$Concepto[16]."Meses"].=",".$Concepto[14];
           }else{
               $ConceptoGeneral[$Concepto[13]][$Concepto[16]."NombreConcepto"]=$RegistroConceptosV2;
                $ConceptoGeneral[$Concepto[13]][$Concepto[16]."Importe"]=$Concepto[4];
                $ConceptoGeneral[$Concepto[13]][$Concepto[16]."Meses"]=$Concepto[14];
                $Conceptototal+=$Concepto[4];
                
                 $descripcion = '<p style="margin:0 -5px 0 0">
                            ' . FuncionesCaja::Recorta($RegistroConceptosV2) .' 
                        </p>';
            $importe = '<p style="margin:0 -5px 0 0" align="right">'.  '<span>$ '.number_format($Concepto[4], 2).'</span>' .'</span></p>';
                    $TexotAuxiliar .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%">&nbsp;</td><td style="width:15%">'.$importe.'</td></tr>';
           } 
           }
        }
       
        if($Contador==0)
        $ConsultaConceptos="SELECT DISTINCTROW c.ConceptoAdicionales,c.Adicional,c.A_no as A_no from ConceptoAdicionalesCotizaci_on c WHERE c.id in($conceptosss)  GROUP BY A_no,Adicional,	id ORDER BY A_no DESC";
        else
        $ConsultaConceptos="SELECT c.ConceptoAdicionales,c.Adicional,COALESCE(c.A_no,0) as A_no from ConceptoAdicionalesCotizaci_on c WHERE c.id in($conceptosss)   ORDER BY A_no DESC";

        #precode($ConsultaConceptos,1,1);
        $ResultadoConcepto=DB::select($ConsultaConceptos);
        $Conceptos="";
        $Contador=0;
        foreach($ResultadoConcepto as  $RegistroConcepto){
        
        if($RegistroConcepto->A_no==0  && 1==2){
            $Contador++;
                $RegistroConcepto->A_no=$Contador;
        }
        
        $Adicional = !isset($RegistroConcepto->Adicional)?$RegistroConcepto->ConceptoAdicionales:$RegistroConcepto->Adicional;
        #echo $ConceptoGeneral[$RegistroConcepto['A_no']][$Adicional."NombreConcepto"]."<br>"; 
        if(is_null($RegistroConcepto->Adicional) && isset($ConceptoGeneral[$RegistroConcepto->A_no][$Adicional."Meses"])){
                $mifecha=" bimestres <strong Style='Color:red;'> (" . (FuncionesCaja::ReinvertirMeses($ConceptoGeneral[$RegistroConcepto->A_no][$Adicional."Meses"] )). ") </strong><strong Style='Color:black;'>del </strong> <strong Style='Color:red;'> " . $RegistroConcepto->A_no.".</Strong>";
        }else{
            $mifecha="";
        }
        $descripcion = '<p style="margin:0 -5px 0 0">
                        ' . (empty($RegistroConcepto->Adicional) ? FuncionesCaja::Recorta($ConceptoGeneral[$RegistroConcepto->A_no][$Adicional."NombreConcepto"]).' '.$mifecha : "<span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".FuncionesCaja::Recorta($ConceptoGeneral[$RegistroConcepto->A_no][$Adicional."NombreConcepto"])."</span>") .' 
                    </p>';
        $importe = '<p style="margin:0 -5px 0 0" align="right">'.(
                        empty($RegistroConcepto->Adicional) ? '<span>$ '.number_format(($ConceptoGeneral[$RegistroConcepto->A_no][$Adicional."Importe"]), 2).'</span>' : '<span>$ '.number_format($ConceptoGeneral[$RegistroConcepto->A_no][$Adicional."Importe"], 2) ).'</span></p>';
                $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%">&nbsp;</td><td style="width:15%">'.$importe.'</td></tr>';
        $Conceptototal += $ConceptoGeneral[$RegistroConcepto->A_no][$Adicional."Importe"];
        
        }
        }
        if(1==2){
        foreach ($arrayConceptos as $key => $Concepto) {
        $Descuento+=$Concepto[7];
        $ActualizacionesYRecargos+=$Concepto[5]+$Concepto[6];
        $DescuentoDeSaldo+=$Concepto[8];
        $RegistroConceptos = Funciones::ObtenValor("SELECT DISTINCT cac.Estatus,cac.id,ccc.id as AYR,cac.Importe,(select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=cac.Adicional) as Adicional, ccc.Descripci_on,cac.Mes AS Mes,	cac.A_no AS A_no FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN ConceptoCobroCaja ccc ON(ccc.id = cac.ConceptoAdicionales)WHERE cac.id=$Concepto[2]");
        $totalConceptos+=$RegistroConceptos->Importe;
        if(is_null($RegistroConceptos->Mes)){
        $mimes=0;
        $mifecha="";
        }
        else {
        $mimes = $RegistroConceptos->Mes;
        if(is_null($RegistroConceptos->Adicional))
            $mifecha="correspondiente al bimestre <strong Style='Color:red;'>" . $RegistroConceptos->Mes . " del " . $RegistroConceptos->A_no.".</Strong>";
        else
            $mifecha="";
        }
        $descripcion = '<p style="margin:0 -5px 0 0">
                        ' . FuncionesCaja::Recorta(empty($RegistroConceptos->Adicional) ? FuncionesCaja::Recorta($RegistroConceptos->Descripci_on).' '.$mifecha : "<span  >&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".$RegistroConceptos->Adicional."</span>") .' 
                    </p>';
        $importe = '<p style="margin:0 -5px 0 0" align="right">'.(
                        empty($RegistroConceptos->Adicional) ? '<span>$ '.number_format(($RegistroConceptos->Importe), 2).'</span>' : '<span>$ '.number_format($RegistroConceptos->Importe, 2) ).'</span></p>';
                $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%">&nbsp;</td><td style="width:15%">'.$importe.'</td></tr>';
        $Conceptototal = str_replace(",", "",$Conceptototal);       
        $Conceptototal += $RegistroConceptos->Importe;


        }
        }
        $DescuentoDeSaldo = number_format(FuncionesCaja::LimpiarNumero($DescuentoDeSaldo),2);
        $ActualizacionesYRecargos = number_format($ActualizacionesYRecargos,2);
        $ActualizacionesYRecargos = FuncionesCaja::LimpiarNumero($ActualizacionesYRecargos);
        $Descuento = FuncionesCaja::LimpiarNumero($Descuento) + FuncionesCaja::LimpiarNumero($DescuentosGenerales);
        $TotalPagar= ($Conceptototal+$ActualizacionesYRecargos+$PagoAnticipado)-$Descuento-$DescuentoDeSaldo;
        $Diferencia2 = 0;

        $Descuento= number_format($Descuento,2);  
        //$ImportePago=$RegistroPago->Monto;
        $letras=utf8_decode(Funciones::num2letras($TotalPagar,0,0)." pesos  ");
        $ultimo = substr (strrchr ($TotalPagar, "."), 1, 2); //recupero lo que este despues del decimal
        if($ultimo=="")
        $ultimo="00";
        $importePagoLetra = $letras." ".$ultimo."/100 M.N.";
        setlocale(LC_TIME,"es_MX.UTF-8");
       // $FechaCotizacion=strftime("%d de ",strtotime($RegistroPago->Fecha)).ucfirst(strftime("%B de %Y",strtotime($RegistroPago->Fecha)));




        $descripcion = "";
        $importe = "";
        $descripcion = '<p style="margin:0 -5px 0 0">
                    Actualizaciones y Recargos 
                    </p>';
        $importe = "<p style='margin:0 -5px 0 0' align='right'>$ ". number_format($ActualizacionesYRecargos,2)."</p>";

        $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%">&nbsp;</td><td style="width:15%">'.$importe.'</td></tr>';


        $descripcion='<p>Descuentos y Redondeo</p>';
        $importe = "<p style='margin:0 -5px 0 0' align='right'>$ - $Descuento</p>";
        $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15% "><strong style="color:red">'.$importe.'</strong></td><td style="width:15%">&nbsp;</td></tr>';

        $descripcion='<p>Aplicaci&oacute;n de saldo</p>';
        $importe = "<p style='margin:0 -5px 0 0' align='right'>$ - $DescuentoDeSaldo</p>";
        if($DescuentoDeSaldo>0)
        $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%"><strong style="color:red">'.$importe.'</strong></td><td style="width:15%">&nbsp;</td></tr>';
        $descripcion='<p>Anticipo:</p>';
        $importe = "<p style='margin:0 -5px 0 0' align='right'>$ + $PagoAnticipado</p>";
        if($PagoAnticipado>0)
        $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%"><strong style="color:red">'.$importe.'</strong></td><td style="width:15%">&nbsp;</td></tr>';

        $Observacionestxto= 'Observaciones:&nbsp;&nbsp;&nbsp;';
        $DescuentoBunerable="";
        $datosDeContrato ="<b>Propietario: </b> ".$datosPadron->Propietario."<br>"
        . "<b>Ubicaci&oacute;n: </b>".$datosPadron->Ubicaci_on."<br>"
        ."<b>MZA: </b>".$datosPadron->Manzana."&nbsp;&nbsp;&nbsp;&nbsp; <b>Lote:</b> ".$datosPadron->Lote."<br>"
        . "<b>Colonia: </b> ".$datosPadron->Colonia."<br>"
        . "<b>Localidad: </b>".$datosPadron->Localidad."<br>"
        . "<b>Municipio: </b>".$datosPadron->Municipio." Guerrero<br>"
        . "<b>Clave Catastral: </b>".$datosPadron->Cuenta;

        #precode($datosDeContrato,1,1);
        if(isset($ExisteDescuento) && $ExisteDescuento!="" && $ExisteDescuento!="NULL")
          $DescuentoBunerable="<b>Estimulo: </b><strong  style='color:red'> $ExisteDescuento</strong>";

        $DatosFiscales="<b>RFC</b>: ".$DatosPago->RFC."<br> <b>Direci&oacute;n:</b> ".ucwords(strtolower($DatosPago->Calle))." ".$DatosPago->N_umeroExterior." ".ucwords(strtolower($DatosPago->Colonia)).", C.P.".$DatosPago->C_odigoPostal."<br><b>Localidad:</b>  ".ucwords(strtolower((is_numeric($DatosPago->Localidad)?Funciones::ObtenValor("select Nombre from Localidad where id=".$DatosPago->Localidad, "Nombre"):$DatosPago->Localidad)))." <br><b>Municipio: </b> ". (is_numeric($DatosPago->Municipio)?Funciones::ObtenValor("select Nombre from Municipio where id=".$DatosPago->Municipio, "Nombre"):$DatosPago->Municipio)." "
        . "".ucwords(strtolower((is_numeric($DatosPago->EntidadFederativa)?Funciones::ObtenValor("select Nombre from EntidadFederativa where id=".$DatosPago->EntidadFederativa, "Nombre"):$DatosPago->EntidadFederativa)));

        $ImportePago = $Conceptototal;
        $HTML ='
        <html lang="es">
        <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
        .contenedor{
            height:735px;
            width: 975px;
            /*border: 1px solid red;*/
        }
        body{
            font-size: 12px;
        }
        th > div, th > span, th {
            font-size: 10px;
            valign: center;
        }
        .table > tbody > tr > td {
            font-size: 12px;
            vertical-align: top;
            border: hidden;
        }

        .main_container{

            padding-top:15px;
            padding-left:5px;
            z-index: 99;
            background-size: cover;
            width:975px;
            height:735px;
            position:relative;

        }
       
        table{
            font-size: 14px;
        }
        .break{
            display: block;
            clear: both;
            page-break-after: always;
        }
        h1 {
            font-size: 300%;
        }
        thead { display: table-header-group }
        tfoot { display: table-row-group }
        tr { page-break-inside: avoid }
        </style>
        </head>
        
        <div  '.($NoRecibos!=0?$OtraHoja:"").'>
        <body >
        <table style="height: 50px;" width="735px" class="table">
        <tbody>
        <tr>
        <td width="20%" align="center"><img src="'.asset($Cliente->Logo).'" alt="Logo del cliente" style="height: 120px;"></td>
        <td style="text-align: right;">
            <p>'.$Cliente->Descripci_on.'<br>Calle '.$Cliente->Calle.' No. '.$Cliente->N_umeroExterior.'<br>Colonia '.$Cliente->Colonia.' Codigo Postal '.$Cliente->C_odigoPostal.'<br>'.$Cliente->Localidad.', Guerrero<br>RFC: '.$Cliente->RFC.'</p>
            <p><span style="color:red; font-size:18px;">Cuenta Predial: '.($datosPadron->CuentaAnterior?$datosPadron->CuentaAnterior:'').'</span> </p>
            

        </td>
        </tr>
        </tbody>
        </table>

        <img style="width: 735px; height: 1px;" src="https://tixtla.servicioenlinea.mx/express/img/barraColores.png"" alt="Mountain View" />
        <br>
        <br>
        <table style="height: 50px;" width="735px" class="table">
        <tbody>
        <tr>
        <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
            <b>Datos del Predio</b>
                        <br><br>
                        '.$datosDeContrato
        .'
                        </td>
                        <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
            <b>Datos de Facturaci&oacute;n</b><br>
            <p><b>Razon Social: </b>'.$DatosPago->NombreORaz_onSocial.'<br>'.$DatosFiscales.'</p>
        </td>
        </tr>
        </tbody>
        </table>
        <br>
        <img style="width: 735px; height: 1px;" src="https://tixtla.servicioenlinea.mx/express/img/barraColores.png" alt="Mountain View" />
        <br>
        <br>
        <table style="height: 5px;" width="735" class="table">
        <tbody>
        <tr>
        <td>
            <p><strong>Estado de Cuenta -<br>UUID: '.($uuid!="NULL"?$uuid:'').' </strong></p>
        </td>
        <td  style="text-align: right;">
            <p><strong>Expedici&oacute;n<br>  '.strftime("%d de ",strtotime($RegistroPago->Fecha)).ucfirst(strftime("%B del %Y",strtotime($RegistroPago->Fecha))).'</strong></p>
        </td>
        
        </tr>

        </tbody>
        </table>
        <br>
        <table   width="735" class="table">
        <tbody>
        '.$Conceptos.'
        </tbody>
        </table>
        <br>
        <img style="width: 735px; height: 1px;" src="https://tixtla.servicioenlinea.mx/express/img/barraColores.png" alt="Mountain View" />
        <br/>

        <table style="height: 35px;" width="735" >
        <tbody>
        <tr>
            <td width="50%" style="border-right: 1px solid !important; border: thin;"  valign="top">
                <p style="text-align: left;"><center><strong>Firma y Sello del Cajero.</center></strong></p>
                <br>
                <br>
                <p style="text-align: left;"><center><strong>'.$Elaboro.'.</center></strong></p>
            </td>
            <td valign="top">
                <p style="text-align: right;"><strong>Total Pagado</strong></p>
                <h1 style="text-align: right;">$'.number_format($TotalPagar, 2).'</h1>
            </td>
        </tr>
        </tbody>
        </table>
        <img style="width: 735px; height: 1px;" src="https://tixtla.servicioenlinea.mx/express/img/barraColores.png" alt="Mountain View" />
        <p style="text-align: right;"><strong>' . $importePagoLetra . '</strong><br> <strong>' . $DescuentoBunerable . '</strong>  </p>
        <!--table style="height: 5px;" width="735px">
        <tbody>
        <tr>
        <td>
            <p><strong>Adeudos Anteriores</strong></p>
        </td>
        <td style="text-align: right;">
            <p>$</p>
        </td>
        </tr>
        </tbody>
        </table>
        <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" / -->
        
        ';

      
        $Variables=Funciones::ObtenValor("select Variables FROM PagoTicket where id=".$idTiket,"Variables");
        $CuentaBancaria=json_decode($Variables,true);
     
        $bancoNombre=Funciones::ObtenValor("select B.Nombre FROM CuentaBancaria C join Banco B on C.Banco=B.id where C.id=".implode($CuentaBancaria['CuentaBancaria']),"Nombre");

        $HTML.='<br><br><br>
                    <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />
                   <table  width="100%">
                        <tr>
                            <td colspan=4 style="text-align: center;"><strong>INFORMACIÓN DEL PAGO RECIBIDO EN LA INSTITUCIÓN</strong><br><br></td>
                        </tr>
                        
                        <tr>
                            <td>Institución de crédito:</td> 
                            <td><strong>'.$bancoNombre.'</strong></td>
                            <td>Fecha del pago:</td> 
                            <td><strong>'.$RegistroPago->Fecha.'</strong></td>
                        </tr>
                        <tr>
                            <td>Referencia:</td> 
                            <td><strong>'.$CuentaBancaria['Referencia'].'</strong></td>
                            <td>Medio de presentación:</td> 
                            <td><strong>Internet</strong></td>
                        </tr>
                        <tr>
                            <td>Importe de pago:</td> 
                            <td><strong>$'.number_format($TotalPagar, 2).'</strong></td>
                            <td>No. de autorización:</td> 
                            <td><strong>'.$CuentaBancaria['Autorizacion'].'</strong></td>
                        </tr>
                        <tr>
                            <td>Folio:</td> 
                            <td><strong>'.$CuentaBancaria['Folio'].'</strong></td>
                            
                        </tr>
                    </table>
                    <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />

            </body>
           </div>
        </html>';
        
        $arr['html'] = $HTML;
        $arr['Total']= $TotalPagar;
        $arr['NoRecibos'] = $NoRecibos;

       
        return $arr;  
                
                
    } 

    
    public static function ReinvertirMeses( $Meses){
        $array = explode(',', $Meses);
        asort($array);
        $arraOrdenado = implode(",", $array);
        return  $arraOrdenado; 
   }
   public static function Recorta($Cadena){
    if(strlen($Cadena)>200)
        $Cadena = substr($Cadena,0,200)." ...";

    return $Cadena;
}



public static function ReciboDeServicioGeneral($idTiket,$arrayConceptos, $Contribuyente, $Pago, $Cliente, $DescuentosGenerales,$NoRecibos=0)
{
   if($NoRecibos>0)
       $NoRecibos--;
   $OtraHoja='class="break"';
   
                $meses = array("","Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");

    $CTotalPagar = 0;
    foreach ($arrayConceptos AS $key => $Concepto) {
     $CTotalPagar+=$Concepto[9];
    }
    $TotalPagar= $CTotalPagar;       
    $UUIDConsulta="SELECT  x.uuid FROM PagoTicket t
    INNER JOIN EncabezadoContabilidad ec ON (ec.Pago=t.Pago)
    INNER JOIN DetalleContabilidad dc ON (dc.EncabezadoContabilidad=ec.id)
    INNER JOIN Cotizaci_on c ON (c.id=dc.Cotizaci_on)
    INNER JOIN XMLIngreso x ON (x.idCotizaci_on=c.id)
    INNER JOIN XML x2 ON (x2.id=x.`xml`)
    #INNER JOIN 
    WHERE
    t.Pago=$Pago and c.Contribuyente=$Contribuyente GROUP BY x.uuid";
        $UUID ="";
        $UUID = Funciones::ObtenValor($UUIDConsulta,"uuid");
        
        
        $RegistroPago = Funciones::ObtenValor("SELECT * FROM Pago WHERE id=$Pago");
        $Elaboro=Funciones::ObtenValor("SELECT NombreCompleto  FROM Pago INNER JOIN CelaUsuario ON (CelaUsuario.idUsuario=Pago.Usuario) where Pago.id=$Pago ","NombreCompleto");
        $DatosPago=Funciones::ObtenValor("SELECT c1.NombreComercial, CONCAT(c1.Nombres,' ',c1.ApellidoPaterno,' ',c1.ApellidoMaterno) as ContribuyenteNombre, d.RFC, d.NombreORaz_onSocial, d.EntidadFederativa, d.Localidad, d.Municipio, d.Colonia, d.Calle, d.N_umeroExterior, d.C_odigoPostal, c1.id as Contribuyente
            ,c1.Calle_c,c1.Localidad_c,c1.Municipio_c,c1.Colonia_c,c1.Rfc,c1.EntidadFederativa_c
    FROM Contribuyente c1  
                        INNER JOIN DatosFiscales d ON ( c1.DatosFiscales = d.id  )  
        WHERE c1.id=$Contribuyente");
        #precode($DatosPago,1,1);
        

    $DatosFiscales=$DatosPago->RFC."<br> Calle ".ucwords(strtolower($DatosPago->Calle))."<br> Colonia ".ucwords(strtolower($DatosPago->Colonia))."<br> ".ucwords(strtolower((is_numeric($DatosPago->Localidad)?Funciones::ObtenValor("select Nombre from Localidad where id=".$DatosPago->Localidad, "Nombre"):$DatosPago->Localidad)))." ".ucwords(strtolower((is_numeric($DatosPago->Municipio)?Funciones::ObtenValor("select Nombre from Municipio where id=".$DatosPago->Municipio, "Nombre"):$DatosPago->Municipio)))." ".ucwords(strtolower((is_numeric($DatosPago->EntidadFederativa)?Funciones::ObtenValor("select Nombre from EntidadFederativa where id=".$DatosPago->EntidadFederativa, "Nombre"):$DatosPago->EntidadFederativa)));
    $datosDeContribuyente=$DatosPago->Rfc."<br> Calle ".ucwords(strtolower($DatosPago->Calle_c))."<br> Colonia ".ucwords(strtolower($DatosPago->Colonia_c))."<br> ".ucwords(strtolower((is_numeric($DatosPago->Localidad_c)?Funciones::ObtenValor("select Nombre from Localidad where id=".$DatosPago->Localidad_c, "Nombre"):$DatosPago->Localidad_c)))." ".ucwords(strtolower((is_numeric($DatosPago->Municipio_c)?Funciones::ObtenValor("select Nombre from Municipio where id=".$DatosPago->Municipio_c, "Nombre"):$DatosPago->Municipio_c)))." ".ucwords(strtolower((is_numeric($DatosPago->EntidadFederativa_c)?Funciones::ObtenValor("select Nombre from EntidadFederativa where id=".$DatosPago->EntidadFederativa_c, "Nombre"):$DatosPago->EntidadFederativa_c)));

    $hojamembretada=Funciones::ObtenValor("(select Ruta from CelaRepositorio where CelaRepositorio.idRepositorio=(select HojaMembretada from Cliente where id=$Cliente))","Ruta");
    $Cliente=Funciones::ObtenValor("select C.id, Descripci_on,DF.RFC,DF.Calle,DF.N_umeroExterior,DF.Colonia,DF.C_odigoPostal, DF.CorreoElectr_onico, (select Nombre from Localidad L where DF.Localidad=L.id) as Localidad, (select Ruta from CelaRepositorioC where CelaRepositorioC.idRepositorio=C.Logotipo) as Logo from Cliente C INNER JOIN DatosFiscalesCliente DF on DF.id=C.DatosFiscales where C.id=$Cliente");
    
    
       
    //$CuentaBancaria=Funciones::ObtenValor("SELECT (SELECT Nombre FROM Banco B WHERE B.id = CB.Banco) as Banco, N_umeroCuenta, Clabe from CuentaBancaria CB WHERE CuentaDeRecaudacion=1 and CB.Cliente=".$Cliente->id." limit 1;");
   
   /* if($CuentaBancaria->result=='NULL') {
    $Banco = "";
    $N_umeroCuenta = "";
    $Clabe = "";
    }
    else{
    $Banco = $CuentaBancaria->Banco;
    $N_umeroCuenta = $CuentaBancaria->N_umeroCuenta;
    $Clabe = $CuentaBancaria->Clabe;
    }*/
    $DatosFiscalesC=Funciones::ObtenValor("(select * from DatosFiscalesCliente where DatosFiscalesCliente.id=(select DatosFiscales from Cliente where id=".$Cliente->id."))");
    $LugarDePago=(is_numeric($DatosFiscalesC->Municipio)?Funciones::ObtenValor("select Nombre from Municipio where id=".$DatosFiscalesC->Municipio, "Nombre"):$DatosFiscalesC->Municipio)." ".(is_numeric($DatosFiscalesC->EntidadFederativa)?Funciones::ObtenValor("select Nombre from EntidadFederativa where id=".$DatosFiscalesC->EntidadFederativa, "Nombre"):$DatosFiscalesC->EntidadFederativa);

    $tamanio_dehoja="735px"; //735 ideal
    $N_umeroP_oliza='';


    $Actualizaci_on=0;
    $Recargo =0;
    $Descuento = 0;
    $DescuentoDeSaldo = 0;
    $ActualizacionesYRecargos=0;
    $Conceptos="";
    $Conceptototal = 0;
    $totalConceptos=0;
    $keys = array_keys($arrayConceptos);
    $ultimoImporte = 0;
    $ArregloConceptos[][]=NULL;
    foreach ($arrayConceptos as $key => $Concepto) {
    #precode($Concepto,1);
    $nota = FuncionesCaja::Notas($Concepto[0]);
    $Descuento+=$Concepto[7];
    $ActualizacionesYRecargos+=$Concepto[5]+$Concepto[6];
    $DescuentoDeSaldo+=$Concepto[8];
    $tresDCC="";
    $RegistroConceptos = Funciones::ObtenValor("SELECT DISTINCT cac.Estatus,cac.id,ccc.id as AYR,cac.Importe,(select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=cac.Adicional) as Adicional, ccc.Descripci_on,cac.Mes AS Mes,	cac.A_no AS A_no,cac.Cotizaci_on As Cotizaci_onC FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN ConceptoCobroCaja ccc ON(ccc.id = cac.ConceptoAdicionales)WHERE cac.id=$Concepto[2]");
    if(isset($RegistroConceptos->AYR) && ($RegistroConceptos->AYR==697  || $RegistroConceptos->AYR==4328)){

    $tresDCC=$Cliente->id."-".$Concepto[12]."-".$Concepto[0];
    #precode($Concepto[0],1,1);
    }


    $totalConceptos+=$RegistroConceptos->Importe;
    if(is_null($RegistroConceptos->Mes)){
    $mimes=0;
    $mifecha="";
    }
    else {
    $mimes = $RegistroConceptos->Mes;
    if(is_null($RegistroConceptos->Adicional))
    $mifecha="Correspondiente al mes de <strong Style='Color:red;'>" . $meses[$mimes] . " del " . $RegistroConceptos->A_no.".</Strong>";
    else
    $mifecha="";
    }
    $mifecha ="";
    $tresDCCTex="";
    if($tresDCC!="")
    $tresDCCTex="N&uacute;mero de Forma 3DCC: <strong Style='Color:red;'>" .$tresDCC.".</Strong><br> Pagina Web:  <strong Style='Color:red;'>https://servicioenlinea.mx/portalnotarios/</strong>";

    $descripcion = '<p style="margin:0 -5px 0 0">
                ' . FuncionesCaja::Recorta(empty($RegistroConceptos->Adicional) ? FuncionesCaja::Recorta($RegistroConceptos->Descripci_on).' '.$mifecha : "<span  >&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".$RegistroConceptos->Adicional."</span>") .' 
            </p>';
    $importe = '<p style="margin:0 -5px 0 0" align="right">'.(
                empty($RegistroConceptos->Adicional) ? '<span>$ '.number_format(($RegistroConceptos->Importe), 2).'</span>' : '<span>$ '.number_format($RegistroConceptos->Importe, 2) ).'</span></p>';
    
        $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td> <td style="width:15%">&nbsp;</td><td style="width:15%">'.$importe.'</td></tr>';
    $Conceptototal = str_replace(",", "",$Conceptototal);       
    $Conceptototal += $RegistroConceptos->Importe;


    }


    $descripcion = "";
    $importe = "";
    $descripcion = '<p style="margin:0 -5px 0 0">
            Actualizaciones y Recargos 
            </p>';
    $importe = "<p style='margin:0 -5px 0 0' align='right'>$ $ActualizacionesYRecargos</p>";
    if($ActualizacionesYRecargos>0) 
    $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%"><strong style="color:red">'.$importe.'</strong></td><td style="width:15%">&nbsp;</td></tr>';

    $Descuento = $Descuento+$DescuentosGenerales;
    $descripcion='<p>Descuentos y Redondeo</p>';
    $importe = "<p style='margin:0 -5px 0 0' align='right'>$ - ". number_format($Descuento,2)."</p>";
    $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%"><strong style="color:red">'.$importe.'</strong</td><td style="width:15%">&nbsp;</td></tr>';

    $Observacionestxto= '<b style="font-size: 14px;">Observaciones:</b>&nbsp;&nbsp;&nbsp;'.$nota." ".$tresDCCTex;





    $ImportePago = $Conceptototal - $Descuento;



    $ImportePago = $ImportePago;
    $ImportePagoAux= FuncionesCaja::LimpiarNumero(number_format($ImportePago,2));
    $letras=utf8_decode(Funciones::num2letras($ImportePagoAux,0,0)." pesos  ");
    $ultimo = substr (strrchr ($ImportePagoAux, "."), 1, 2); //recupero lo que este despues del decimal
    if($ultimo=="")
    $ultimo="00";
    $importePagoLetra = $letras." ".$ultimo."/100 M.N.";
    setlocale(LC_TIME,"es_MX.UTF-8");
    $FechaCotizacion=strftime("%d de ",strtotime($RegistroPago->Fecha)).ucfirst(strftime("%B de %Y",strtotime($RegistroPago->Fecha)));
    $Variables=Funciones::ObtenValor("select Variables FROM PagoTicket where id=".$idTiket,"Variables");
    $CuentaBancaria=json_decode($Variables,true);
    
    $bancoNombre=Funciones::ObtenValor("select B.Nombre FROM CuentaBancaria C join Banco B on C.Banco=B.id where C.id=".$CuentaBancaria['IdCuentaBancaria'],"Nombre");

    $HTML ='
    <html lang="es">
    <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
    .contenedor{
    height:735px;
    width: 975px;
    /*border: 1px solid red;*/
    }
    body{
    font-size: 12px;
    }


    .main_container{

    padding-top:15px;
    padding-left:5px;
    z-index: 99;
    background-size: cover;
    width:975px;
    height:735px;
    position:relative;

    }
    table{
    font-size: 14px;
    }
    .break{
    display: block;
    clear: both;
    page-break-after: always;
    }
    h1 {
    font-size: 300%;
    }
    .table1 > thead > tr > th, 
    .table1>tbody>tr>td> {
        padding: 2px 5px 2px 2px !important;
    }

    .table-bordered>tbody>tr>td {
        border: 0px solid #ddd;
    }
    thead { display: table-header-group }
    tfoot { display: table-row-group }
    tr { page-break-inside: avoid }

    </style>
    </head>
    <div  '.($NoRecibos!=0?$OtraHoja:"").'>
    <body >

    <table style="height: 50px;" width="735px" class="table">
    <tbody>
    <tr>
    <td width="20%" align="center"><img src="'.asset($Cliente->Logo).'" alt="Logo del cliente" style="height: 120px;"></td>
    <td style="text-align: right;">
    <p>'.$Cliente->Descripci_on.'<br>Domicilio Fiscal: Calle '.$Cliente->Calle.' No. '.$Cliente->N_umeroExterior.'<br>Colonia '.$Cliente->Colonia.' Codigo Postal '.$Cliente->C_odigoPostal.'<br>'.$Cliente->Localidad.', Guerrero<br>RFC: '.$Cliente->RFC.'</p>
    <p><span style="color:red; font-size:18px;">No. Pago: '.$Pago.'</span> </p>
    </td>
    </tr>
    </tbody>
    </table>

    <img style="width: 735px; height: 1px;" src="https://tixtla.servicioenlinea.mx/express/img/barraColores.png" alt="Mountain View" />
    <br>
    <br>
    <table style="height: 50px;" width="735px" class="table">
    <tbody>
    <tr>
    <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
    <b>Datos de Contribuyente</b>
                <br><br>
                <b>Nombre: </b>'.($DatosPago->ContribuyenteNombre?$DatosPago->ContribuyenteNombre:$DatosPago->NombreComercial).'<br>
                '.$datosDeContribuyente
    .'
                </td>
                <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
    <b>Datos de Facturaci&oacute;n</b><br>
    <p><b>Razon Social: </b>'.$DatosPago->NombreORaz_onSocial.'<br>'.$DatosFiscales.'</p>
    </td>
    </tr>
    </tbody>
    </table>
    <br>
    <img style="width: 735px; height: 1px;" src="https://tixtla.servicioenlinea.mx/express/img/barraColores.png" alt="Mountain View" />
    <br>
    <br>
    <table style="height: 5px;" width="735" class="table">
    <tbody>
    <tr>
    <td>
    <p><strong>Estado de Cuenta -<br>UUID: '.($UUID!="NULL"?$UUID:'').' </strong></p>
    </td>
    <td  style="text-align: right;">
    <p><strong>Expedici&oacute;n<br>  '.strftime("%d de ",strtotime($RegistroPago->Fecha)).ucfirst(strftime("%B del %Y",strtotime($RegistroPago->Fecha))).'</strong></p>
    </td>

    </tr>

    </tbody>
    </table>
    <br>
    <table    width="735" class="table">
    <tbody>
    '.$Conceptos.'
    </tbody>
    </table>
    <br>
    <img style="width: 735px; height: 1px;" src="https://tixtla.servicioenlinea.mx/express/img/barraColores.png" alt="Mountain View" />
    <p>'.$Observacionestxto.'</p>
    <img style="width: 735px; height: 1px;" src="https://tixtla.servicioenlinea.mx/express/img/barraColores.png" alt="Mountain View" />
    <br/>
    <table style="height: 35px;" width="735" >
    <br>
    <tbody>
    <tr>
    <td width="50%" style="border-right: 1px solid !important; border: thin;"  valign="top">
        <p style="text-align: left;"><center><strong>Firma y Sello del Cajero.</center></strong></p>
        <br>
        <br>
        <p style="text-align: left;"><center><strong>'.$Elaboro.'.</center></strong></p>
    </td>
    <td valign="top">
        <p style="text-align: right;"><strong>Total Pagado</strong></p>
        <h1 style="text-align: right;">$'.number_format($ImportePago, 2).'</h1>
        
    </td>
    </tr>

    </tbody>
    </table>


    <table style="height: 5px;" width="735px">
    <tbody>
    <tr>
    <td>
    
    </td>
    <td style="text-align: right;">
    <p></p>
    </td>
    </tr>
    </tbody>
    </table>
 
    
    ';

    $Variables=Funciones::ObtenValor("select Variables FROM PagoTicket where id=".$idTiket,"Variables");
        $CuentaBancaria=json_decode($Variables,true);
       

        $bancoNombre=Funciones::ObtenValor("select B.Nombre FROM CuentaBancaria C join Banco B on C.Banco=B.id where C.id=".$CuentaBancaria['IdCuentaBancaria'],"Nombre");

        $HTML.='
                    <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />
                   <table  width="100%">
                        <tr>
                            <td colspan=4 style="text-align: center;"><strong>INFORMACIÓN DEL PAGO RECIBIDO EN LA INSTITUCIÓN</strong><br><br></td>
                        </tr>
                        
                        <tr>
                            <td>Institución de crédito:</td> 
                            <td><strong>'.$bancoNombre.'</strong></td>
                            <td>Fecha del pago:</td> 
                            <td><strong>'.$RegistroPago->Fecha.'</strong></td>
                        </tr>
                        <tr>
                            <td>Referencia:</td> 
                            <td><strong>'.$CuentaBancaria['Referencia'].'</strong></td>
                            <td>Medio de presentación:</td> 
                            <td><strong>Internet</strong></td>
                        </tr>
                        <tr>
                            <td>Importe de pago:</td> 
                            <td><strong>$'.number_format($TotalPagar, 2).'</strong></td>
                            <td>No. de autorización:</td> 
                            <td><strong>'.$CuentaBancaria['Autorizacion'].'</strong></td>
                        </tr>
                        <tr>
                            <td>Folio:</td> 
                            <td><strong>'.$CuentaBancaria['Folio'].'</strong></td>
                            
                        </tr>
                    </table>
                    <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />

            </body>
           </div>
        </html>';
        

    $arr['html'] = $HTML;
    $arr['NoRecibos'] = $NoRecibos;
    return $arr;
}


public static function  Notas($Cotizaci_on){
    $XMLIngreso= Funciones::ObtenValor("SELECT DatosExtra FROM XMLIngreso xi WHERE xi.idCotizaci_on='".$Cotizaci_on."'");
    #precode($XMLIngreso,1,1);
    if(isset($XMLIngreso->DatosExtra) && $XMLIngreso->DatosExtra!=""){
     $XMLIngreso->DatosExtra =  preg_replace("[\n|\r|\n\r]", "",$XMLIngreso->DatosExtra);
     $DatosExtras = json_decode($XMLIngreso->DatosExtra, true);
     #precode($DatosExtras,1,1);
     $Observaci_on = isset($DatosExtras['Observacion']) ?  addslashes( $DatosExtras['Observacion'] ) : '';
     #precode($DatosExtras['Observacion'],1,1);
     $tipoCotizacion = 0;
      if((isset($DatosExtras['TipoCotizacionPredial']) && $DatosExtras['TipoCotizacionPredial']=="Servicio"))
      {
          $Ubicacion= isset($DatosExtras['Ubicacion'])?$DatosExtras['Ubicacion']:'';
          $CuentaVigente= isset($DatosExtras['Cuenta'])?$DatosExtras['Cuenta']:'';
          $CuentaAnterior= isset($DatosExtras['CuentaAnterior'])?$DatosExtras['CuentaAnterior']:'';
        return  $Inferior = '<body><table style="height: 50px;" width="735px" class="table">
            

        <tr  >
            <td colspan="3"><b>Clave Catastral:</b> '.$CuentaVigente.'</td>
            <td colspan="3"><b>Cuenta Predial:</b>  '.$CuentaAnterior.' </td>
            
            
        </tr>
                <tr  >
                
                <td colspan="6" ><b>Ubicaci&oacute;n: </b> '.$Ubicacion.'</td>
            
            
        </tr>

        
        </table></body>';
                
          
      }else if((isset($Observaci_on) && $Observaci_on!="")){
          return $Observaci_on;
      }
     
     
      
      
      
     
     
    }else{
        return "";
    }
}

/*ISAI*/

public static function ReciboPredialISAI($idTiket,$arrayConceptos, $Padr_on, $arrContribuyente,$Pago, $Cliente, $DescuentosGenerales, $PagoAnticipado,$NoRecibos=0){
   global $Conexion;
   $CTotalPagar = 0;
   if($NoRecibos>0)
       $NoRecibos--;
   $OtraHoja='class="break"';
   
   foreach ($arrayConceptos AS $key => $Concepto) {
    $CTotalPagar+=$Concepto[9];
   }
   $TotalPagar= $CTotalPagar;       
   
   /*Recibo de Agua Potable */
   $meses = array("","Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");

   

    $UUIDConsulta="SELECT  x.uuid FROM PagoTicket t
    INNER JOIN EncabezadoContabilidad ec ON (ec.Pago=t.Pago)
    INNER JOIN DetalleContabilidad dc ON (dc.EncabezadoContabilidad=ec.id)
    INNER JOIN Cotizaci_on c ON (c.id=dc.Cotizaci_on)
    INNER JOIN XMLIngreso x ON (x.idCotizaci_on=c.id)
    INNER JOIN XML x2 ON (x2.id=x.`xml`)
    #INNER JOIN 
    WHERE
    t.Pago=$Pago and c.Contribuyente=".$arrContribuyente[0]." GROUP BY x.uuid";
        $UUID ="";
        $UUID = Funciones::ObtenValor($UUIDConsulta,"uuid");
    $RegistroPago = Funciones::ObtenValor("SELECT * FROM Pago WHERE id=$Pago");
    $Elaboro=Funciones::ObtenValor("SELECT NombreCompleto  FROM Pago INNER JOIN CelaUsuario ON (CelaUsuario.idUsuario=Pago.Usuario) where Pago.id=$Pago ","NombreCompleto");
    $DatosPago=Funciones::ObtenValor("SELECT d.RFC, d.NombreORaz_onSocial, d.EntidadFederativa, d.Localidad, d.Municipio, d.Colonia, d.Calle, d.N_umeroExterior, d.C_odigoPostal, c1.id as Contribuyente
    FROM Contribuyente c1  
    INNER JOIN DatosFiscales d ON ( c1.DatosFiscales = d.id  )  
    WHERE c1.id=$arrContribuyente[0]");
    $DatosFiscales=$DatosPago->RFC."<br> Calle ".ucwords(strtolower($DatosPago->Calle))."<br> Colonia ".ucwords(strtolower($DatosPago->Colonia))."<br> ".ucwords(strtolower((is_numeric($DatosPago->Localidad)?Funciones::ObtenValor("select Nombre from Localidad where id=".$DatosPago->Localidad, "Nombre"):$DatosPago->Localidad)))." ".ucwords(strtolower((is_numeric($DatosPago->Municipio)?Funciones::ObtenValor("select Nombre from Municipio where id=".$DatosPago->Municipio, "Nombre"):$DatosPago->Municipio)))." ".ucwords(strtolower((is_numeric($DatosPago->EntidadFederativa)?Funciones::ObtenValor("select Nombre from EntidadFederativa where id=".$DatosPago->EntidadFederativa, "Nombre"):$DatosPago->EntidadFederativa)));
    #$PadronAgua = ObtenValor("SELECT *,(SELECT c.Nombre FROM Municipio c where c.id=Municipio) Municipio,(SELECT c.Nombre FROM Localidad c where c.id=Localidad)AS Localidad,(Select CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno) FROM Contribuyente c where c.id= Contribuyente) AS Propietario,(SELECT Concepto FROM TipoTomaAguaPotable  WHERE id=TipoToma) as TipoDeToma FROM Padr_onAguaPotable WHERE id=$Padr_on");

    /*Bloque del Recibo Predial*/

    $datosPadron=Funciones::ObtenValor("SELECT 
    (select   if(c.PersonalidadJur_idica=1,IF( CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno) IS NOT NULL AND CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno)!='',CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno), d.NombreORaz_onSocial),d.NombreORaz_onSocial) from  Contribuyente c  INNER JOIN DatosFiscales d ON(d.id=c.DatosFiscales) where c.id = pc.Contribuyente ) as Propietario,
    pc.Cuenta, pc.CuentaAnterior, pc.Ubicaci_on, pc.Colonia, pc.id,pc.Manzana,pc.Lote
    ,(SELECT c.Nombre FROM Municipio c where c.id=pc.Municipio) Municipio,(SELECT c.Nombre FROM Localidad c where c.id=pc.Localidad)AS Localidad
    FROM Padr_onCatastral pc  
    WHERE
    pc.id=".$Padr_on);
    //(select   if(c.PersonalidadJur_idica=1,IF( CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno) IS NOT NULL AND CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno)!='',CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno), d.NombreORaz_onSocial),d.NombreORaz_onSocial) from  Contribuyente c  INNER JOIN DatosFiscales d ON(d.id=c.DatosFiscales) where c.id = Padr_onAguaPotable2.Contribuyente ) /as/ Contribuyente/*/
    $Copropietarios="";
    $ConsultaCopropietarios = "SELECT ( SELECT CONCAT(IF (c.NombreComercial IS NULL OR c.NombreComercial='',CONCAT_WS(' ',c.Nombres,c.ApellidoPaterno,c.ApellidoMaterno),c.NombreComercial)) 
    FROM `Contribuyente` c WHERE c.id=pcc.idContribuyente) AS CoPropietario FROM Padr_onCatastralCopropietarios pcc WHERE pcc.idPadron=".$datosPadron->id;
    $ejecutaCopropietarios=DB::select($ConsultaCopropietarios);
    $row_cnt = count($ejecutaCopropietarios);
    $aux=1;
    foreach($ejecutaCopropietarios as $registroCopropietarios){
    if($aux==$row_cnt){
        $Copropietarios.='<b>'.$registroCopropietarios->CoPropietario.'</b><br /> ';
    }else{
        $Copropietarios.='<b>'.$registroCopropietarios->CoPropietario.',</b> <br /> ';
    }
    $aux++;
    }
    if($Copropietarios!=""){
    $Copropietarios = '<b>Copropietarios</b> <br />'.$Copropietarios ;
    }

    $impresiondatos="C. ".$datosPadron->Propietario."<br />".$Copropietarios.
        "Clave: <b>".$datosPadron->Cuenta."</b> &nbsp;&nbsp;&nbsp;&nbsp; Cuenta: <b>".$datosPadron->CuentaAnterior."</b><br />".
        "Ubicaci&oacute;n: <b>".$datosPadron->Ubicaci_on."</b><br />".
        "Colonia: <b>".$datosPadron->Colonia."</b>";
    #precode($datosPadron,1,1);


    $DatosFiscales="<b>RFC</b>: ".$DatosPago->RFC."<br> <b>Direci&oacute;n:</b> ".ucwords(strtolower($DatosPago->Calle))." ".$DatosPago->N_umeroExterior." ".ucwords(strtolower($DatosPago->Colonia)).", C.P.".$DatosPago->C_odigoPostal."<br><b>Localidad:</b>  ".ucwords(strtolower((is_numeric($DatosPago->Localidad)?Funciones::ObtenValor("select Nombre from Localidad where id=".$DatosPago->Localidad, "Nombre"):$DatosPago->Localidad)))." <br><b>Municipio: </b> ". (is_numeric($DatosPago->Municipio)?Funciones::ObtenValor("select Nombre from Municipio where id=".$DatosPago->Municipio, "Nombre"):$DatosPago->Municipio)." "
    . "".ucwords(strtolower((is_numeric($DatosPago->EntidadFederativa)?Funciones::ObtenValor("select Nombre from EntidadFederativa where id=".$DatosPago->EntidadFederativa, "Nombre"):$DatosPago->EntidadFederativa)));


    $hojamembretada=Funciones::ObtenValor("(select Ruta from CelaRepositorio where CelaRepositorio.idRepositorio=(select HojaMembretada from Cliente where id=$Cliente))","Ruta");
    $Cliente=Funciones::ObtenValor("select C.id AS Cliente, Descripci_on,DF.RFC,DF.Calle,DF.N_umeroExterior,DF.Colonia,DF.C_odigoPostal, DF.CorreoElectr_onico, (select Nombre from Localidad L where DF.Localidad=L.id) as Localidad, (select Ruta from CelaRepositorioC where CelaRepositorioC.idRepositorio=C.Logotipo) as Logo from Cliente C INNER JOIN DatosFiscalesCliente DF on DF.id=C.DatosFiscales where C.id=$Cliente");
    $CuentaBancaria=Funciones::ObtenValor("SELECT (SELECT Nombre FROM Banco B WHERE B.id = CB.Banco) as Banco, N_umeroCuenta, Clabe from CuentaBancaria CB WHERE CuentaDeRecaudacion=1 and CB.Cliente=".$Cliente->Cliente." limit 1;");
    if($CuentaBancaria->result=='NULL') {
    $Banco = "";
    $N_umeroCuenta = "";
    $Clabe = "";
    }
    else{
    $Banco = $CuentaBancaria->Banco;
    $N_umeroCuenta = $CuentaBancaria->N_umeroCuenta;
    $Clabe = $CuentaBancaria->Clabe;
    }
    $DatosFiscalesC=Funciones::ObtenValor("(select * from DatosFiscalesCliente where DatosFiscalesCliente.id=(select DatosFiscales from Cliente where id=".$Cliente->Cliente."))");
    $LugarDePago=(is_numeric($DatosFiscalesC->Municipio)?Funciones::ObtenValor("select Nombre from Municipio where id=".$DatosFiscalesC->Municipio, "Nombre"):$DatosFiscalesC->Municipio)." ".(is_numeric($DatosFiscalesC->EntidadFederativa)?Funciones::ObtenValor("select Nombre from EntidadFederativa where id=".$DatosFiscalesC->EntidadFederativa, "Nombre"):$DatosFiscalesC->EntidadFederativa);

    $tamanio_dehoja="735px"; //735 ideal
    $N_umeroP_oliza='';

    $Conceptos='';
    $descripcion="";
    $importe="";

    //Numero Maximo de Conceptos 9
    $Conceptototal=0;
    $uuid="";

    $Actualizaci_on=0;
    $Recargo =0;
    $Descuento = 0;
    $DescuentoDeSaldo = 0;
    $ActualizacionesYRecargos=0;
    $totalConceptos=0;
    $ultimoImporte = 0;
    $ArregloConceptos[][]=NULL;
    $A_noConcepto = array();
    $ConceptoGeneral =array();
    $conceptosss="0";
    if(1==2){
    foreach ($arrayConceptos as $key => $Concepto) {
    $conceptosss.=",".$Concepto[2];
    }
    #precode($conceptosss,1,1);
    $Contador=0;
    foreach ($arrayConceptos as $key => $Concepto) {
    if($Concepto[13]==0 && 1==2){
    $Contador++;
    $Concepto[13]=$Contador;
    }
    $Descuento+=$Concepto[7];
    $ActualizacionesYRecargos+=$Concepto[5]+$Concepto[6];
    $DescuentoDeSaldo+=$Concepto[8];
    $RegistroConceptos = Funciones::ObtenValor("SELECT DISTINCT cac.Estatus,cac.id,ccc.id as AYR,cac.Importe,(select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=cac.Adicional) as Adicional,cac.Adicional as Adicionalid, ccc.Descripci_on,cac.Mes AS Mes,	cac.A_no AS A_no, cac.ConceptoAdicionales FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN ConceptoCobroCaja ccc ON(ccc.id = cac.ConceptoAdicionales)WHERE cac.id=$Concepto[2]");
    if(!is_null($RegistroConceptos->Adicionalid))
    $RegistroConceptos->ConceptoAdicionales=$RegistroConceptos->Adicionalid;
    if(isset($ConceptoGeneral[$Concepto[13]][$RegistroConceptos->ConceptoAdicionales."NombreConcepto"])){
    $ConceptoGeneral[$Concepto[13]][$RegistroConceptos->ConceptoAdicionales."Importe"]+=$RegistroConceptos->Importe;
    if(!isset($RegistroConceptos->Adicionalid))
    $ConceptoGeneral[$Concepto[13]][$RegistroConceptos->ConceptoAdicionales."Meses"].=",".$RegistroConceptos->Mes;
    }else{
    $ConceptoGeneral[$Concepto[13]][$RegistroConceptos->ConceptoAdicionales."NombreConcepto"]= isset($RegistroConceptos->Adicionalid)?$RegistroConceptos->Adicional:$RegistroConceptos->Descripci_on;
    $ConceptoGeneral[$Concepto[13]][$RegistroConceptos->ConceptoAdicionales."Importe"]=$RegistroConceptos->Importe;
    if(!isset($RegistroConceptos->Adicionalid))
    $ConceptoGeneral[$Concepto[13]][$RegistroConceptos->ConceptoAdicionales."Meses"]=$RegistroConceptosMes;
    }
    }
    #precode($ConceptoGeneral,1);
    if($Contador==0)
    $ConsultaConceptos="SELECT c.ConceptoAdicionales,c.Adicional,c.A_no as A_no from ConceptoAdicionalesCotizaci_on c WHERE c.id in($conceptosss) GROUP BY A_no,Adicional ORDER BY A_no DESC";
    else
    $ConsultaConceptos="SELECT c.ConceptoAdicionales,c.Adicional,COALESCE(c.A_no,0) as A_no from ConceptoAdicionalesCotizaci_on c WHERE c.id in($conceptosss)   ORDER BY A_no DESC";

    #precode($ConsultaConceptos,1,1);
    $ResultadoConcepto=DB::select($ConsultaConceptos);
    $Conceptos="";
    $Contador=0;
    foreach($ResultadoConcepto as $RegistroConcepto){
    #precode($RegistroConcepto,1);

    if($RegistroConcepto->A_no==0  && 1==2){
    $Contador++;
        $RegistroConcepto->A_no=$Contador;
    }

    $Adicional = !isset($RegistroConcepto->Adicional)?$RegistroConcepto->ConceptoAdicionales:$RegistroConcepto->Adicional;
    #echo $ConceptoGeneral[$RegistroConcepto['A_no']][$Adicional."NombreConcepto"]."<br>"; 
    if(is_null($RegistroConcepto->Adicional) && isset($ConceptoGeneral[$RegistroConcepto->A_no][$Adicional."Meses"])){
    $mifecha="correspondiente a los bimestres <strong Style='Color:red;'> (" . $ConceptoGeneral[$RegistroConcepto->A_no][$Adicional."Meses"] . ") del " . $RegistroConcepto->A_no.".</Strong>";
    }else{
    $mifecha="";
    }
    $descripcion = '<p style="margin:0 -5px 0 0">
                ' . FuncionesCaja::Recorta(empty($RegistroConcepto->Adicional) ? FuncionesCaja::Recorta($ConceptoGeneral[$RegistroConcepto->A_no][$Adicional."NombreConcepto"]).' '.$mifecha : "<span  >&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".FuncionesCaja::Recorta($ConceptoGeneral[$RegistroConcepto->A_no][$Adicional."NombreConcepto"])."</span>") .' 
            </p>';
    $importe = '<p style="margin:0 -5px 0 0" align="right">'.(
                empty($RegistroConcepto->Adicional) ? '<span>$ '.number_format(($ConceptoGeneral[$RegistroConcepto->A_no][$Adicional."Importe"]), 2).'</span>' : '<span>$ '.number_format($ConceptoGeneral[$RegistroConcepto->A_no][$Adicional."Importe"], 2) ).'</span></p>';
        $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%">&nbsp;</td><td style="width:15%">'.$importe.'</td></tr>';
    $Conceptototal += $ConceptoGeneral[$RegistroConcepto->A_no][$Adicional."Importe"];

    }
    }
    if(1==1){
    foreach ($arrayConceptos as $key => $Concepto) {
    $Descuento+=$Concepto[7];
    $ActualizacionesYRecargos+=$Concepto[5]+$Concepto[6];
    $DescuentoDeSaldo+=$Concepto[8];
    $RegistroConceptos = Funciones::ObtenValor("SELECT DISTINCT cac.Estatus,cac.id,ccc.id as AYR,cac.Importe,(select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=cac.Adicional) as Adicional, ccc.Descripci_on,cac.Mes AS Mes,	cac.A_no AS A_no FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN ConceptoCobroCaja ccc ON(ccc.id = cac.ConceptoAdicionales)WHERE cac.id=$Concepto[2]");
    $totalConceptos+=$RegistroConceptos->Importe;
    if(is_null($RegistroConceptos->Mes)){
    $mimes=0;
    $mifecha="";
    }
    else {
    $mimes = $RegistroConceptos->Mes;
    if(is_null($RegistroConceptos->Adicional))
    $mifecha="correspondiente al bimestre <strong Style='Color:red;'>" . $RegistroConceptos->Mes . " del " . $RegistroConceptos->A_no.".</Strong>";
    else
    $mifecha="";
    }
    $descripcion = '<p style="margin:0 -5px 0 0">
                ' . FuncionesCaja::Recorta(empty($RegistroConceptos->Adicional) ? FuncionesCaja::Recorta($RegistroConceptos->Descripci_on).' '.$mifecha : "<span  >&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".$RegistroConceptos->Adicional."</span>") .' 
            </p>';
    $importe = '<p style="margin:0 -5px 0 0" align="right">'.(
                empty($RegistroConceptos->Adicional) ? '<span>$ '.number_format(($RegistroConceptos->Importe), 2).'</span>' : '<span>$ '.number_format($RegistroConceptos->Importe, 2) ).'</span></p>';
        $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%">&nbsp;</td><td style="width:15%">'.$importe.'</td></tr>';
    $Conceptototal = str_replace(",", "",$Conceptototal);       
    $Conceptototal += $RegistroConceptos->Importe;


    }
    }
    $DescuentoDeSaldo = number_format(FuncionesCaja::LimpiarNumero($DescuentoDeSaldo),2);
    $ActualizacionesYRecargos = number_format($ActualizacionesYRecargos,2);
    $ActualizacionesYRecargos = FuncionesCaja::LimpiarNumero($ActualizacionesYRecargos);
    $Descuento = floatval(FuncionesCaja::LimpiarNumero($Descuento)) + floatval(FuncionesCaja::LimpiarNumero($DescuentosGenerales));
    $TotalPagar= ($Conceptototal+$ActualizacionesYRecargos+$PagoAnticipado)-$Descuento-$DescuentoDeSaldo;
    $Diferencia2 = 0;

    $Descuento= number_format($Descuento,2);  
    $ImportePago=$RegistroPago->Monto;
    $letras=utf8_decode(Funciones::num2letras($TotalPagar,0,0)." pesos  ");

    $ultimo = substr (strrchr ($TotalPagar, "."), 1, 2); //recupero lo que este despues del decimal
    if($ultimo=="")
    $ultimo="00";
    $importePagoLetra = $letras." ".$ultimo."/100 M.N.";
    setlocale(LC_TIME,"es_MX.UTF-8");
    $FechaCotizacion=strftime("%d de ",strtotime($RegistroPago->Fecha)).ucfirst(strftime("%B de %Y",strtotime($RegistroPago->Fecha)));




    $descripcion = "";
    $importe = "";
    $descripcion = '<p style="margin:0 -5px 0 0">
            Actualizaciones y Recargos 
            </p>';
    $importe = "<p style='margin:0 -5px 0 0' align='right'>$ $ActualizacionesYRecargos</p>";

    $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%">&nbsp;</td><td style="width:15%">'.$importe.'</td></tr>';


    $descripcion='<p>Descuentos y Redondeo</p>';
    $importe = "<p style='margin:0 -5px 0 0' align='right'>$ - $Descuento</p>";
    $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15% "><strong style="color:red">'.$importe.'</strong></td><td style="width:15%">&nbsp;</td></tr>';

    $descripcion='<p>Aplicaci&oacute;n de saldo</p>';
    $importe = "<p style='margin:0 -5px 0 0' align='right'>$ - $DescuentoDeSaldo</p>";
    if($DescuentoDeSaldo>0)
    $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%"><strong style="color:red">'.$importe.'</strong></td><td style="width:15%">&nbsp;</td></tr>';
    $descripcion='<p>Anticipo:</p>';
    $importe = "<p style='margin:0 -5px 0 0' align='right'>$ + $PagoAnticipado</p>";
    if($PagoAnticipado>0)
    $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%"><strong style="color:red">'.$importe.'</strong></td><td style="width:15%">&nbsp;</td></tr>';

    $Observacionestxto= 'Observaciones:&nbsp;&nbsp;&nbsp;';

    $datosDeContrato ="<b>Propietario: </b> ".$datosPadron->Propietario."<br>"
    . "<b>Ubicaci&oacute;n: </b>".$datosPadron->Ubicaci_on."<br>"
    ."<b>MZA: </b>".$datosPadron->Manzana."&nbsp;&nbsp;&nbsp;&nbsp; <b>Lote:</b> ".$datosPadron->Lote."<br>"
    . "<b>Colonia: </b> ".$datosPadron->Colonia."<br>"
    . "<b>Localidad: </b>".$datosPadron->Localidad."<br>"
    . "<b>Municipio: </b>".$datosPadron->Municipio." Guerrero<br>"
    . "<b>Clave Catastral: </b>".$datosPadron->Cuenta;

    #precode($datosDeContrato,1,1);
    $DatosFiscales="<b>RFC</b>: ".$DatosPago->RFC."<br> <b>Direci&oacute;n:</b> ".ucwords(strtolower($DatosPago->Calle))." ".$DatosPago->N_umeroExterior." ".ucwords(strtolower($DatosPago->Colonia)).", C.P.".$DatosPago->C_odigoPostal."<br><b>Localidad:</b>  ".ucwords(strtolower((is_numeric($DatosPago->Localidad)?Funciones::ObtenValor("select Nombre from Localidad where id=".$DatosPago->Localidad, "Nombre"):$DatosPago->Localidad)))." <br><b>Municipio: </b> ". (is_numeric($DatosPago->Municipio)?Funciones::ObtenValor("select Nombre from Municipio where id=".$DatosPago->Municipio, "Nombre"):$DatosPago->Municipio)." "
    . "".ucwords(strtolower((is_numeric($DatosPago->EntidadFederativa)?Funciones::ObtenValor("select Nombre from EntidadFederativa where id=".$DatosPago->EntidadFederativa, "Nombre"):$DatosPago->EntidadFederativa)));




    $ImportePago = $Conceptototal;
    $HTML ='
    <html lang="es">
    <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
    .contenedor{
    height:735px;
    width: 975px;
    /*border: 1px solid red;*/
    }
    body{
    font-size: 12px;
    }
    th > div, th > span, th {
    font-size: 10px;
    valign: center;
    }
    .table > tbody > tr > td {
    font-size: 12px;
    vertical-align: top;
    border: hidden;
    }

    .main_container{

    padding-top:15px;
    padding-left:5px;
    z-index: 99;
    background-size: cover;
    width:975px;
    height:735px;
    position:relative;

    }
    table{
    font-size: 14px;
    }
    .break{
    display: block;
    clear: both;
    page-break-after: always;
    }
    h1 {
    font-size: 300%;
    }
    thead { display: table-header-group }
    tfoot { display: table-row-group }
    tr { page-break-inside: avoid }
    </style>
    </head>
    <div  '.($NoRecibos!=0?$OtraHoja:"").'>
    <body>
    <table style="height: 50px;" width="735px" class="table">
    <tbody>
    <tr>
    <td width="20%" align="center"><img src="'.asset($Cliente->Logo).'" alt="Logo del cliente" style="height: 120px;"></td>
    <td style="text-align: right;">
    <p>'.$Cliente->Descripci_on.'<br>Calle '.$Cliente->Calle.' No. '.$Cliente->N_umeroExterior.'<br>Colonia '.$Cliente->Colonia.' Codigo Postal '.$Cliente->C_odigoPostal.'<br>'.$Cliente->Localidad.', Guerrero<br>RFC: '.$Cliente->RFC.'</p>
    <p><span style="color:red; font-size:18px;">Cuenta Predial: '.($datosPadron->CuentaAnterior?$datosPadron->CuentaAnterior:'').'</span> </p><br />
    </td>
    </tr>
    </tbody>
    </table>

    <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />
    <br>
    <br>
    <table style="height: 50px;" width="735px" class="table">
    <tbody>
    <tr>
    <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
    <b>Datos del Predio</b>
                <br><br>
                '.$datosDeContrato
    .'
                </td>
                <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
    <b>Datos de Facturaci&oacute;n</b><br>
    <p><b>Razon Social: </b>'.$DatosPago->NombreORaz_onSocial.'<br>'.$DatosFiscales.'</p>
    </td>
    </tr>
    </tbody>
    </table>
    <br>
    <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />
    <br>
    <br>
    <table style="height: 5px;" width="735" class="table">
    <tbody>
    <tr>
    <td>
    <p><strong>Estado de Cuenta -<br>UUID: '.($UUID!="NULL"?$UUID:'').' </strong></p>
    </td>
    <td  style="text-align: right;">
    <p><strong>Expedici&oacute;n<br>  '.strftime("%d de ",strtotime($RegistroPago->Fecha)).ucfirst(strftime("%B del %Y",strtotime($RegistroPago->Fecha))).'</strong></p>
    </td>

    </tr>

    </tbody>
    </table>
    <br>
    <table    width="735" class="table">
    <tbody>
    '.$Conceptos.'
    </tbody>
    </table>
    <br>
    <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />
    <br/>
    <br/>

    <table style="height: 35px;" width="735" >
    <tbody>
    <tr>
    <td width="50%" style="border-right: 1px solid !important; border: thin;"  valign="top">
        <p style="text-align: left;"><center><strong>Firma y Sello del Cajero.</center></strong></p>
        <br>
        <br>
        <p style="text-align: left;"><center><strong>'.$Elaboro.'.</center></strong></p>
    </td>
    <td valign="top">
        <p style="text-align: right;"><strong>Total Pagado</strong></p>
        <h1 style="text-align: right;">$'.number_format($TotalPagar, 2).'</h1>
    </td>
    </tr>
    </tbody>
    </table>
    <br>
    <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />
    <p style="text-align: right;"><strong>' . $importePagoLetra . '</strong> </p>
    <br>
    <br>
    <!--table style="height: 5px;" width="735px">
    <tbody>
    <tr>
    <td>
    <p><strong>Adeudos Anteriores</strong></p>
    </td>
    <td style="text-align: right;">
    <p>$</p>
    </td>
    </tr>
    </tbody>
    </table>
    <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" / -->
    ';
    $Variables=Funciones::ObtenValor("select Variables FROM PagoTicket where id=".$idTiket,"Variables");
        $CuentaBancaria=json_decode($Variables,true);
       

        $bancoNombre=Funciones::ObtenValor("select B.Nombre FROM CuentaBancaria C join Banco B on C.Banco=B.id where C.id=".$CuentaBancaria['IdCuentaBancaria'],"Nombre");

        $HTML.='<br><br><br>
                    <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />
                   <table  width="100%">
                        <tr>
                            <td colspan=4 style="text-align: center;"><strong>INFORMACIÓN DEL PAGO RECIBIDO EN LA INSTITUCIÓN</strong><br><br></td>
                        </tr>
                        
                        <tr>
                            <td>Institución de crédito:</td> 
                            <td><strong>'.$bancoNombre.'</strong></td>
                            <td>Fecha del pago:</td> 
                            <td><strong>'.$RegistroPago->Fecha.'</strong></td>
                        </tr>
                        <tr>
                            <td>Referencia:</td> 
                            <td><strong>'.$CuentaBancaria['Referencia'].'</strong></td>
                            <td>Medio de presentación:</td> 
                            <td><strong>Internet</strong></td>
                        </tr>
                        <tr>
                            <td>Importe de pago:</td> 
                            <td><strong>$'.number_format($TotalPagar, 2).'</strong></td>
                            <td>No. de autorización:</td> 
                            <td><strong>'.$CuentaBancaria['Autorizacion'].'</strong></td>
                        </tr>
                        <tr>
                            <td>Folio:</td> 
                            <td><strong>'.$CuentaBancaria['Folio'].'</strong></td>
                            
                        </tr>
                    </table>
                    <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />

            </body>
           </div>
        </html>';
        
    $arr['html'] = $HTML;
    $arr['Total']= $TotalPagar;
    $arr['NoRecibos'] = $NoRecibos;
    return $arr;  
    
   
} 


public static function ReciboAguaPotable($idTiket,$arrayConceptos, $Padr_on, $arrContribuyente,$Pago, $Cliente, $DescuentosGenerales, $PagoAnticipado,$NoRecibos=0){
    
    if($NoRecibos>0)
       $NoRecibos--;
    $CTotalPagar = 0;
    foreach ($arrayConceptos AS $key => $Concepto) {
     $CTotalPagar+=$Concepto[9];
    }
    $TotalPagar= $CTotalPagar;       
    
    /*Recibo de Agua Potable */
    $meses = array("","Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");

 

$UUIDConsulta="SELECT  x.uuid FROM PagoTicket t
INNER JOIN EncabezadoContabilidad ec ON (ec.Pago=t.Pago)
INNER JOIN DetalleContabilidad dc ON (dc.EncabezadoContabilidad=ec.id)
INNER JOIN Cotizaci_on c ON (c.id=dc.Cotizaci_on)
INNER JOIN XMLIngreso x ON (x.idCotizaci_on=c.id)
INNER JOIN XML x2 ON (x2.id=x.`xml`)
#INNER JOIN 
WHERE
t.Pago=$Pago and c.Contribuyente=".$arrContribuyente[0]." GROUP BY x.uuid";
     $UUID ="";
     $UUID = Funciones::ObtenValor($UUIDConsulta,"uuid");
$RegistroPago = Funciones::ObtenValor("SELECT * FROM Pago WHERE id=$Pago");
$Elaboro=Funciones::ObtenValor("SELECT NombreCompleto  FROM Pago INNER JOIN CelaUsuario ON (CelaUsuario.idUsuario=Pago.Usuario) where Pago.id=$Pago ","NombreCompleto");
$DatosPago=Funciones::ObtenValor("SELECT d.RFC, d.NombreORaz_onSocial, d.EntidadFederativa, d.Localidad, d.Municipio, d.Colonia, d.Calle, d.N_umeroExterior, d.C_odigoPostal, c1.id as Contribuyente
FROM Contribuyente c1  
INNER JOIN DatosFiscales d ON ( c1.DatosFiscales = d.id  )  
WHERE c1.id=$arrContribuyente[0]");

$DatosFiscales=$DatosPago->RFC."<br> Calle ".ucwords(strtolower($DatosPago->Calle))."<br> Colonia ".ucwords(strtolower($DatosPago->Colonia))."<br> ".ucwords(strtolower((is_numeric($DatosPago->Localidad)?Funciones::ObtenValor("select Nombre from Localidad where id=".$DatosPago->Localidad, "Nombre"):$DatosPago->Localidad)))." ".ucwords(strtolower((is_numeric($DatosPago->Municipio)?Funciones::ObtenValor("select Nombre from Municipio where id=".$DatosPago->Municipio, "Nombre"):$DatosPago->Municipio)))." ".ucwords(strtolower((is_numeric($DatosPago->EntidadFederativa)?Funciones::ObtenValor("select Nombre from EntidadFederativa where id=".$DatosPago->EntidadFederativa, "Nombre"):$DatosPago->EntidadFederativa)));

$PadronAgua = Funciones::ObtenValor("SELECT *,(SELECT CONCAT(pt.Clave,' - ',pt.Marca) as Medidor FROM Padr_onAguaPotableTipoMedidor pt WHERE pt.id=Padr_onAguaPotable.idModeloMedidor) As MedidorV2,(SELECT CONCAT(ps.Sector, ' - ',ps.Nombre) as Sector FROM Padr_onAguaPotableSector  ps WHERE ps.Sector=Padr_onAguaPotable.Sector and ps.Cliente=Padr_onAguaPotable.Cliente)As SectorV2,(SELECT c.Nombre FROM Municipio c where c.id=Municipio) Municipio,(SELECT c.Nombre FROM Localidad c where c.id=Localidad)AS Localidad,(Select CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno) FROM Contribuyente c where c.id= Contribuyente) AS Propietario,(SELECT Concepto FROM TipoTomaAguaPotable  WHERE id=TipoToma) as TipoDeToma FROM Padr_onAguaPotable WHERE id=$Padr_on");

#precode($PadronAgua,1,1);
$datosDeContrato ="<b>Propietario:</b> ".$PadronAgua->Propietario."<br>"
. "<b>Ubicaci&oacute;n:</b> MZA ".$PadronAgua->Manzana." Lote ".$PadronAgua->Lote." ".$PadronAgua->Domicilio."<br>"
. "<b>Colonia:</b> ".$PadronAgua->Colonia."<br>"
. "<b>Localidad: </b>".$PadronAgua->Localidad."<br>"
. "<b>Municipio: </b>".$PadronAgua->Municipio." Guerrero<br>"
. "<b>Sector: </b>".$PadronAgua->SectorV2." <b>Ruta:</b> ".$PadronAgua->Ruta."<br>"
. "<b>Medidor: </b>".$PadronAgua->MedidorV2." <b>No:</b> ".$PadronAgua->Medidor."<br>"
. "<b>Folio: </b>".$PadronAgua->Cuenta." <b>Cuenta Predial:</b> ".$PadronAgua->CuentaPredial."";


$DatosFiscales="<b>RFC</b>: ".$DatosPago->RFC."<br> <b>Direci&oacute;n:</b> ".ucwords(strtolower($DatosPago->Calle))." ".$DatosPago->N_umeroExterior." ".ucwords(strtolower($DatosPago->Colonia)).", C.P.".$DatosPago->C_odigoPostal."<br><b>Localidad:</b>  ".ucwords(strtolower((is_numeric($DatosPago->Localidad)?Funciones::ObtenValor("select Nombre from Localidad where id=".$DatosPago->Localidad, "Nombre"):$DatosPago->Localidad)))." <br><b>Municipio: </b> ". (is_numeric($DatosPago->Municipio)?Funciones::ObtenValor("select Nombre from Municipio where id=".$DatosPago->Municipio, "Nombre"):$DatosPago->Municipio)." "
. "".ucwords(strtolower((is_numeric($DatosPago->EntidadFederativa)?Funciones::ObtenValor("select Nombre from EntidadFederativa where id=".$DatosPago->EntidadFederativa, "Nombre"):$DatosPago->EntidadFederativa)));


$hojamembretada=Funciones::ObtenValor("(select Ruta from CelaRepositorio where CelaRepositorio.idRepositorio=(select HojaMembretada from Cliente where id=$Cliente))","Ruta");
//se cambia la consulta a la tabla DatosFiscalesCliente en lugar de DatosFiscales porque es una version mas actual
$Cliente=Funciones::ObtenValor("select C.id AS Cliente, Descripci_on,DF.RFC,DF.Calle,DF.N_umeroExterior,DF.Colonia,DF.C_odigoPostal, DF.CorreoElectr_onico, (select Nombre from Localidad L where DF.Localidad=L.id) as Localidad, (select Ruta from CelaRepositorioC where CelaRepositorioC.idRepositorio=C.Logotipo) as Logo from Cliente C INNER JOIN DatosFiscalesCliente DF on DF.id=C.DatosFiscales where C.id=$Cliente");

$CuentaBancaria=Funciones::ObtenValor("SELECT (SELECT Nombre FROM Banco B WHERE B.id = CB.Banco) as Banco, N_umeroCuenta, Clabe from CuentaBancaria CB WHERE CuentaDeRecaudacion=1 and CB.Cliente=".$Cliente->Cliente." limit 1;");

if( isset($CuentaBancaria->result)&&$CuentaBancaria->result=='OK') {

$Banco = $CuentaBancaria->Banco;
$N_umeroCuenta = $CuentaBancaria->N_umeroCuenta;
$Clabe = $CuentaBancaria->Clabe;
}
else{
    $Banco = "";
    $N_umeroCuenta = "";
    $Clabe = "";
}//cambio de la tabla Datos Fiscales por Datos Fiscales Cliente debido a que es una tabla mas actual con informacion
$DatosFiscalesC=Funciones::ObtenValor("(select * from DatosFiscalesCliente where DatosFiscalesCliente.id=(select DatosFiscales from Cliente where id=".$Cliente->Cliente."))");
$LugarDePago=(is_numeric($DatosFiscalesC->Municipio)?Funciones::ObtenValor("select Nombre from Municipio where id=".$DatosFiscalesC->Municipio, "Nombre"):$DatosFiscalesC->Municipio)." ".(is_numeric($DatosFiscalesC->EntidadFederativa)?Funciones::ObtenValor("select Nombre from EntidadFederativa where id=".$DatosFiscalesC->EntidadFederativa, "Nombre"):$DatosFiscalesC->EntidadFederativa);

$tamanio_dehoja="735px"; //735 ideal
$N_umeroP_oliza='';

$Conceptos='';
$descripcion="";
$importe="";

//Numero Maximo de Conceptos 9
$Conceptototal=0;
$uuid=""; 

$Actualizaci_on=0;
$Recargo =0;
$Descuento = 0;
$DescuentoDeSaldo = 0;
$ActualizacionesYRecargos=0;
$totalConceptos=0;
$ultimoImporte = 0;
$ArregloConceptos[][]=NULL;
foreach ($arrayConceptos as $key => $Concepto) {
$Descuento+=$Concepto[7];
$ActualizacionesYRecargos+=$Concepto[5]+$Concepto[6];
$DescuentoDeSaldo+=$Concepto[8];
$RegistroConceptos = Funciones::ObtenValor("SELECT DISTINCT cac.Estatus,cac.id,ccc.id as AYR,cac.Importe,(select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=cac.Adicional) as Adicional, ccc.Descripci_on,cac.Mes AS Mes,	cac.A_no AS A_no FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN ConceptoCobroCaja ccc ON(ccc.id = cac.ConceptoAdicionales)WHERE cac.id=$Concepto[2]");
$totalConceptos+=$RegistroConceptos->Importe;
if(is_null($RegistroConceptos->Mes)){
$mimes=0;
$mifecha="";
}
else {
$mimes = $RegistroConceptos->Mes;
if(is_null($RegistroConceptos->Adicional))
  $mifecha="Correspondiente al mes de <strong Style='Color:red;'>" . $meses[$mimes] . " del " . $RegistroConceptos->A_no.".</Strong>";
else
  $mifecha="";
}
$descripcion = '<p style="margin:0 -5px 0 0">
              ' . FuncionesCaja::Recorta(empty($RegistroConceptos->Adicional) ? FuncionesCaja::Recorta($RegistroConceptos->Descripci_on).' '.$mifecha : "<span  >&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".$RegistroConceptos->Adicional."</span>") .' 
          </p>';
$importe = '<p style="margin:0 -5px 0 0" align="right">'.(
              empty($RegistroConceptos->Adicional) ? '<span>$ '.number_format(($RegistroConceptos->Importe), 2).'</span>' : '<span>$ '.number_format($RegistroConceptos->Importe, 2) ).'</span></p>';
      $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%">&nbsp;</td><td style="width:15%">'.$importe.'</td></tr>';
$Conceptototal = str_replace(",", "",$Conceptototal);       
$Conceptototal += $RegistroConceptos->Importe;


}

$DescuentoDeSaldo = number_format(FuncionesCaja::LimpiarNumero($DescuentoDeSaldo),2);
$ActualizacionesYRecargos = number_format($ActualizacionesYRecargos,2);
$ActualizacionesYRecargos = FuncionesCaja::LimpiarNumero($ActualizacionesYRecargos);
$Descuento = FuncionesCaja::LimpiarNumero($Descuento) + FuncionesCaja::LimpiarNumero($DescuentosGenerales);
$TotalPagar= (floatval($Conceptototal)+floatval($ActualizacionesYRecargos)+floatval($PagoAnticipado))-floatval(FuncionesCaja::LimpiarNumero($Descuento))-floatval(FuncionesCaja::LimpiarNumero($DescuentoDeSaldo));
$Diferencia2 = 0;

$Descuento= number_format($Descuento,2);  
$ImportePago=$RegistroPago->Monto;
$letras=utf8_decode(Funciones::num2letras($TotalPagar,0,0)." pesos  ");

$ultimo = substr (strrchr ($TotalPagar, "."), 1, 2); //recupero lo que este despues del decimal
if($ultimo=="")
$ultimo="00";
$importePagoLetra = $letras." ".$ultimo."/100 M.N.";
setlocale(LC_TIME,"es_MX.UTF-8");
$FechaCotizacion=strftime("%d de ",strtotime($RegistroPago->Fecha)).ucfirst(strftime("%B de %Y",strtotime($RegistroPago->Fecha)));


$OtraHoja='class="break"';

$descripcion = "";
$importe = "";
$descripcion = '<p style="margin:0 -5px 0 0">
           Actualizaciones y Recargos 
          </p>';
$importe = "<p style='margin:0 -5px 0 0' align='right'>$ $ActualizacionesYRecargos</p>";

$Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%">&nbsp;</td><td style="width:15%">'.$importe.'</td></tr>';


$descripcion='<p>Descuentos Y Redondeo</p>';
$importe = "<p style='margin:0 -5px 0 0' align='right'>$ - $Descuento</p>";
$Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15% "><strong style="color:red">'.$importe.'</strong></td><td style="width:15%">&nbsp;</td></tr>';

$descripcion='<p>Aplicaci&oacute;n de saldo</p>';
$importe = "<p style='margin:0 -5px 0 0' align='right'>$ - $DescuentoDeSaldo</p>";
if($DescuentoDeSaldo>0)
$Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%"><strong style="color:red">'.$importe.'</strong></td><td style="width:15%">&nbsp;</td></tr>';
$descripcion='<p>Anticipo:</p>';
$importe = "<p style='margin:0 -5px 0 0' align='right'>$ + $PagoAnticipado</p>";
if($PagoAnticipado>0)
$Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%"><strong style="color:red">'.$importe.'</strong></td><td style="width:15%">&nbsp;</td></tr>';

$Observacionestxto= 'Observaciones:&nbsp;&nbsp;&nbsp;';

$ImportePago = $Conceptototal;
$HTML ='
<html lang="es">
<head >
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
.contenedor{
  height:735px;
  width: 975px;
  /*border: 1px solid red;*/
}
body{
  font-size: 12px;
}


.main_container{

  padding-top:15px;
  padding-left:5px;
  z-index: 99;
  background-size: cover;
  width:975px;
  height:735px;
  position:relative;

}
table{
  font-size: 14px;
}
.break{
  display: block;
  clear: both;
  page-break-after: always;
}
h1 {
  font-size: 300%;
}
.table1 > thead > tr > th, 
.table1>tbody>tr>td> {
    padding: 2px 5px 2px 2px !important;
}

.table-bordered>tbody>tr>td {
    border: 0px solid #ddd;
}
thead { display: table-header-group }
tfoot { display: table-row-group }
tr { page-break-inside: avoid }
</style>
</head>
<div '.($NoRecibos!=0?$OtraHoja:"").'>
<body >
<table style="height: 50px;" width="750px" class="table">
<tbody>
<tr style="height:5px;">
<td width="20%" align="center"> <center><img src="'.asset($Cliente->Logo).'" alt="Logo del cliente" style="height: 120px;"></center></td>
<td style="text-align: right;">
 <p>'.$Cliente->Descripci_on.'<br>Domicilio Fiscal: '.$Cliente->Calle.' No. '.$Cliente->N_umeroExterior.'<br>Colonia '.$Cliente->Colonia.' Codigo Postal '.$Cliente->C_odigoPostal.'<br>'.$Cliente->Localidad.', Guerrero<br>RFC: '.$Cliente->RFC.'</p>
 
     
<span style="font-size: 20px;>Contrato</span> <br />
      <span  style="font-size: 12px;"><b>Contrato</b>: <span  style="color:#ff0000; font-size: 20px;">'.$PadronAgua->ContratoVigente.'</span></span>
   <br><br><br>
</td>
</tr>
</tbody>
</table>

<img style="width: 735px; height: 1px;" src="https://tixtla.servicioenlinea.mx/express/img/barraColores.png" alt="Mountain View" />
<br>
<br>
<table style="height: 50px;" width="735px" class="table">
<tbody>
<tr>
<td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
  <b>Datos de Contrato</b>
              <br><br>
              '.$datosDeContrato
.'
              </td>
              <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
  <b>Datos de Facturaci&oacute;n</b><br><br>
   <p><b>Razon Social: </b>'.$DatosPago->NombreORaz_onSocial.'<br>'.$DatosFiscales.'</p>
</td>
</tr>
</tbody>
</table>
<br>
<img style="width: 735px; height: 1px;" src="https://tixtla.servicioenlinea.mx/express/img/barraColores.png" alt="Mountain View" />
<br>
<br>
<table style="height: 5px;" width="735" class="table">
<tbody>
<tr>
<td>
  <p><strong>Estado de Cuenta -<br>UUID: '.($UUID!="NULL"?$UUID:'').' </strong></p>
</td>
<td  style="text-align: right;">
  <p><strong>Expedici&oacute;n<br>  '.strftime("%d de ",strtotime($RegistroPago->Fecha)).ucfirst(strftime("%B del %Y",strtotime($RegistroPago->Fecha))).'</strong></p>
</td>

</tr>

</tbody>
</table>
<br>
<table  width="735" class="table1">
<tbody>
'.$Conceptos.'
</tbody>
</table>
<img style="width: 735px; height: 1px;" src="https://tixtla.servicioenlinea.mx/express/img/barraColores.png" alt="Mountain View" />
<p>'.$Observacionestxto.'</p>
<img style="width: 735px; height: 1px;" src="https://tixtla.servicioenlinea.mx/express/img/barraColores.png" alt="Mountain View" />
<br/>

<table style="height: 35px;" width="735" >
<tbody>
<tr> 
  <td width="50%" style="border-right: 1px solid !important; border: thin;"  valign="top">
      <p style="text-align: left;"><center><strong>Firma y Sello del Cajero.</strong></center></p>
      <br>
      <br>
      <br><br><br>
      <center><strong>'.$Elaboro.'.</strong></center>
  </td>
  <td valign="top">
       <p style="text-align: right;"><strong>Total Pagado</strong></p>
      <h1 style="text-align: right;">$'.number_format(($TotalPagar), 2).'</h1>
          
  </td>
    
</tr>

</tbody>

</table>
<br>
<img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />
<p style="text-align: right;"><strong>' . $importePagoLetra . '</strong> </p>

';
$Variables=Funciones::ObtenValor("select Variables FROM PagoTicket where id=".$idTiket,"Variables");
$CuentaBancaria=json_decode($Variables,true);


$bancoNombre=Funciones::ObtenValor("select B.Nombre FROM CuentaBancaria C join Banco B on C.Banco=B.id where C.id=".$CuentaBancaria['IdCuentaBancaria'],"Nombre");

$HTML.='
            <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />
           <table  width="100%">
                <tr>
                    <td colspan=4 style="text-align: center;"><strong>INFORMACIÓN DEL PAGO RECIBIDO EN LA INSTITUCIÓN</strong><br><br></td>
                </tr>
                
                <tr>
                    <td>Institución de crédito:</td> 
                    <td><strong>'.$bancoNombre.'</strong></td>
                    <td>Fecha del pago:</td> 
                    <td><strong>'.$RegistroPago->Fecha.'</strong></td>
                </tr>
                <tr>
                    <td>Referencia:</td> 
                    <td><strong>'.$CuentaBancaria['Referencia'].'</strong></td>
                    <td>Medio de presentación:</td> 
                    <td><strong>Internet</strong></td>
                </tr>
                <tr>
                    <td>Importe de pago:</td> 
                    <td><strong>$'.number_format($TotalPagar, 2).'</strong></td>
                    <td>No. de autorización:</td> 
                    <td><strong>'.$CuentaBancaria['Autorizacion'].'</strong></td>
                </tr>
                <tr>
                    <td>Folio:</td> 
                    <td><strong>'.$CuentaBancaria['Folio'].'</strong></td>
                    
                </tr>
            </table>
            <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />

    </body>
   </div>
</html>';
  /* 
  $HTML.='
            <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />
           <table  width="100%">
                <tr>
                    <td colspan=4 style="text-align: center;"><strong>INFORMACIÓN DEL PAGO RECIBIDO EN LA INSTITUCIÓN</strong><br><br></td>
                </tr>
                
                <tr>
                    <td>Institución de crédito:</td> 
                    <td><strong>'.$bancoNombre.'</strong></td>
                    <td>Fecha del pago:</td> 
                    <td><strong>'.$RegistroPago->Fecha.'</strong></td>
                </tr>
                <tr>
                    <td>Referencia:</td> 
                    <td><strong>'.$CuentaBancaria['Referencia'].'</strong></td>
                    <td>Medio de presentación:</td> 
                    <td><strong>Internet</strong></td>
                </tr>
                <tr>
                    <td>Importe de pago:</td> 
                    <td><strong>$'.number_format($TotalPagar, 2).'</strong></td>
                    <td>No. de autorización:</td> 
                    <td><strong>'.$CuentaBancaria['Autorizacion'].'</strong></td>
                </tr>
                <tr>
                    <td>Folio:</td> 
                    <td><strong>'.$CuentaBancaria['Folio'].'</strong></td>
                    
                </tr>
            </table>
            <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />

    </body>
   </div>
</html>';
  */
$arr['html'] = $HTML;
$arr['Total']= $TotalPagar;
$arr['NoRecibos'] = $NoRecibos;
return $arr;  
    
    
} 



        
/*Licencia de funcionamiento*/
public static function ReciboLicenciaFuncionamiento($arrayConceptos, $Padr_on, $arrContribuyente,$Pago, $Cliente, $DescuentosGenerales, $PagoAnticipado,$NoRecibos=0){
    global $Conexion;
    
    if($NoRecibos>0)
        $NoRecibos--;
    
    $OtraHoja='class="break"';
    $CTotalPagar = 0;

    foreach ($arrayConceptos AS $key => $Concepto) {
        $CTotalPagar+=$Concepto[9];
    }
    $TotalPagar= $CTotalPagar;       

    /*Recibo de Agua Potable */
    $meses = array("","Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");

   
    
    $UUIDConsulta = "SELECT  x.uuid FROM PagoTicket t
            INNER JOIN EncabezadoContabilidad ec ON (ec.Pago=t.Pago)
            INNER JOIN DetalleContabilidad dc ON (dc.EncabezadoContabilidad=ec.id)
            INNER JOIN Cotizaci_on c ON (c.id=dc.Cotizaci_on)
            INNER JOIN XMLIngreso x ON (x.idCotizaci_on=c.id)
            INNER JOIN XML x2 ON (x2.id=x.`xml`)
            #INNER JOIN 
        WHERE
            t.Pago=$Pago and c.Contribuyente=".$arrContribuyente[0]." GROUP BY x.uuid";
    
    $UUID ="";
    $UUID = Funciones::ObtenValor($UUIDConsulta,"uuid");
 
    $RegistroPago =  Funciones::ObtenValor("SELECT * FROM Pago WHERE id=$Pago");
    $Elaboro =  Funciones::ObtenValor("SELECT NombreCompleto  FROM Pago INNER JOIN CelaUsuario ON (CelaUsuario.idUsuario=Pago.Usuario) where Pago.id=$Pago ","NombreCompleto");
    $DatosPago =  Funciones::ObtenValor("SELECT d.RFC, d.NombreORaz_onSocial, d.EntidadFederativa, d.Localidad, d.Municipio, d.Colonia, d.Calle, d.N_umeroExterior, d.C_odigoPostal, c1.id as Contribuyente
        FROM Contribuyente c1  
            INNER JOIN DatosFiscales d ON ( c1.DatosFiscales = d.id  )  
        WHERE c1.id=$arrContribuyente[0]");
    
    $DatosFiscales=$DatosPago->RFC."<br> Calle ".ucwords(strtolower($DatosPago->Calle))."<br> Colonia ".ucwords(strtolower($DatosPago->Colonia))."<br> ".ucwords(strtolower((is_numeric($DatosPago->Localidad)? Funciones::ObtenValor("select Nombre from Localidad where id=".$DatosPago->Localidad, "Nombre"):$DatosPago->Localidad)))." ".ucwords(strtolower((is_numeric($DatosPago->Municipio)? Funciones::ObtenValor("select Nombre from Municipio where id=".$DatosPago->Municipio, "Nombre"):$DatosPago->Municipio)))." ".ucwords(strtolower((is_numeric($DatosPago->EntidadFederativa)? Funciones::ObtenValor("select Nombre from EntidadFederativa where id=".$DatosPago->EntidadFederativa, "Nombre"):$DatosPago->EntidadFederativa)));
    #$PadronAgua = ObtenValor("SELECT *,(SELECT c.Nombre FROM Municipio c where c.id=Municipio) Municipio,(SELECT c.Nombre FROM Localidad c where c.id=Localidad)AS Localidad,(Select CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno) FROM Contribuyente c where c.id= Contribuyente) AS Propietario,(SELECT Concepto FROM TipoTomaAguaPotable  WHERE id=TipoToma) as TipoDeToma FROM Padr_onAguaPotable WHERE id=$Padr_on");

    /*Bloque del Recibo Predial*/
    $datosPadron= Funciones::ObtenValor("SELECT (SELECT (SELECT Descripci_on FROM Giro WHERE id = GiroDetalle.idGiro) FROM GiroDetalle WHERE GiroDetalle.Cliente=pc.Cliente AND GiroDetalle.id=pc.GiroDetalle) AS Giro,
            (select if(c.PersonalidadJur_idica=1,IF( CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno) IS NOT NULL AND CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno)!='',CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno), d.NombreORaz_onSocial),d.NombreORaz_onSocial) from  Contribuyente c  INNER JOIN DatosFiscales d ON(d.id=c.DatosFiscales) where c.id = pc.Contribuyente ) as Propietario,
            pc.Folio as Cuenta, pc.FolioAnterior as CuentaAnterior, pc.Domicilio , pc.id,
            (SELECT c.Nombre FROM Municipio c where c.id=pc.Municipio) Municipio,(SELECT c.Nombre FROM Localidad c where c.id=pc.Localidad)AS Localidad
            FROM Padr_onLicencia pc  
        WHERE
            pc.id=".$Padr_on);
    //(select   if(c.PersonalidadJur_idica=1,IF( CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno) IS NOT NULL AND CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno)!='',CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno), d.NombreORaz_onSocial),d.NombreORaz_onSocial) from  Contribuyente c  INNER JOIN DatosFiscales d ON(d.id=c.DatosFiscales) where c.id = Padr_onAguaPotable2.Contribuyente ) /as/ Contribuyente/*/
    $Copropietarios = "";
             
    $impresiondatos =   "C. ".$datosPadron->Propietario."<br />".$Copropietarios.
                        "Clave: <b>".$datosPadron->Cuenta."</b> &nbsp;&nbsp;&nbsp;&nbsp; Cuenta: <b>".$datosPadron->CuentaAnterior."</b><br />".
                        "Domicilio: <b>".$datosPadron->Domicilio."</b><br />".
                        " ";
    #precode($datosPadron,1,1);

    $DatosFiscales="<b>RFC</b>: ".$DatosPago->RFC."<br> <b>Direci&oacute;n:</b> ".ucwords(strtolower($DatosPago->Calle))." ".$DatosPago->N_umeroExterior." ".ucwords(strtolower($DatosPago->Colonia)).", C.P.".$DatosPago->C_odigoPostal."<br><b>Localidad:</b>  ".ucwords(strtolower((is_numeric($DatosPago->Localidad)? Funciones::ObtenValor("select Nombre from Localidad where id=".$DatosPago->Localidad, "Nombre"):$DatosPago->Localidad)))." <br><b>Municipio: </b> ". (is_numeric($DatosPago->Municipio)? Funciones::ObtenValor("select Nombre from Municipio where id=".$DatosPago->Municipio, "Nombre"):$DatosPago->Municipio)." "
        . "".ucwords(strtolower((is_numeric($DatosPago->EntidadFederativa)? Funciones::ObtenValor("select Nombre from EntidadFederativa where id=".$DatosPago->EntidadFederativa, "Nombre"):$DatosPago->EntidadFederativa)));

    $hojamembretada= Funciones::ObtenValor("(select Ruta from CelaRepositorio where CelaRepositorio.idRepositorio=(select HojaMembretada from Cliente where id=$Cliente))","Ruta");
    $Cliente= Funciones::ObtenValor("select C.id AS Cliente, Descripci_on,DF.RFC,DF.Calle,DF.N_umeroExterior,DF.Colonia,DF.C_odigoPostal, DF.CorreoElectr_onico, (select Nombre from Localidad L where DF.Localidad=L.id) as Localidad, (select Ruta from CelaRepositorioC where CelaRepositorioC.idRepositorio=C.Logotipo) as Logo from Cliente C INNER JOIN DatosFiscalesCliente DF on DF.id=C.DatosFiscales where C.id=$Cliente");
    $CuentaBancaria= Funciones::ObtenValor("SELECT (SELECT Nombre FROM Banco B WHERE B.id = CB.Banco) as Banco, N_umeroCuenta, Clabe from CuentaBancaria CB WHERE CuentaDeRecaudacion=1 and CB.Cliente=".$Cliente->Cliente." limit 1;");
    
   
    if( isset($CuentaBancaria->result)&&$CuentaBancaria->result=='OK') {

        $Banco = $CuentaBancaria->Banco;
        $N_umeroCuenta = $CuentaBancaria->N_umeroCuenta;
        $Clabe = $CuentaBancaria->Clabe;
        }
        else{
            $Banco = "";
            $N_umeroCuenta = "";
            $Clabe = "";
    }


    $DatosFiscalesC= Funciones::ObtenValor("(select * from DatosFiscalesCliente where DatosFiscalesCliente.id=(select DatosFiscales from Cliente where id=".$Cliente->Cliente."))");
    $LugarDePago=(is_numeric($DatosFiscalesC->Municipio)? Funciones::ObtenValor("select Nombre from Municipio where id=".$DatosFiscalesC->Municipio, "Nombre"):$DatosFiscalesC->Municipio)." ".(is_numeric($DatosFiscalesC->EntidadFederativa)? Funciones::ObtenValor("select Nombre from EntidadFederativa where id=".$DatosFiscalesC->EntidadFederativa, "Nombre"):$DatosFiscalesC->EntidadFederativa);

    $tamanio_dehoja="735px"; //735 ideal
    $N_umeroP_oliza='';

    $Conceptos='';
    $descripcion="";
    $importe="";

    //Numero Maximo de Conceptos 9
    $Conceptototal=0;
    $uuid="";

    $Actualizaci_on=0;
    $Recargo =0;
    $Descuento = 0;
    $DescuentoDeSaldo = 0;
    $ActualizacionesYRecargos=0;
    $totalConceptos=0;
    $ultimoImporte = 0;
    $ArregloConceptos[][]=NULL;
    $A_noConcepto = array();
    $ConceptoGeneral =array();
    $conceptosss="0";
    
        foreach ($arrayConceptos as $key => $Concepto) {
            $Descuento += $Concepto[7];
            $ActualizacionesYRecargos+=$Concepto[5]+$Concepto[6];
            $DescuentoDeSaldo+=$Concepto[8];
            $RegistroConceptos =  Funciones::ObtenValor("SELECT DISTINCT cac.Estatus,cac.id,ccc.id as AYR,cac.Importe,(select Descripci_on from RetencionesAdicionales where RetencionesAdicionales.id=cac.Adicional) as Adicional, ccc.Descripci_on,cac.Mes AS Mes, cac.A_no AS A_no FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN ConceptoCobroCaja ccc ON(ccc.id = cac.ConceptoAdicionales)WHERE cac.id=$Concepto[2]");
            $totalConceptos+=$RegistroConceptos->Importe;
            
            if(is_null($RegistroConceptos->A_no)){
                $mimes=0;
                $mifecha="";
            }else {
                $mimes = $RegistroConceptos->Mes;
                if(is_null($RegistroConceptos->Adicional))
                    $mifecha = "<strong Style='Color:red;'>".$RegistroConceptos->A_no.".</Strong>";
                else
                    $mifecha="";
            }

            $descripcion = '<p style="margin:0 -5px 0 0">
                            ' . FuncionesCaja::Recorta(empty($RegistroConceptos->Adicional) ? FuncionesCaja::Recorta($RegistroConceptos->Descripci_on).' '.$mifecha : "<span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".$RegistroConceptos->Adicional."</span>") .' 
                        </p>';
            $importe = '<p style="margin:0 -5px 0 0" align="right">'.(
                            empty($RegistroConceptos->Adicional) ? '<span>$ '.number_format(($RegistroConceptos->Importe), 2).'</span>' : '<span>$ '.number_format($RegistroConceptos->Importe, 2) ).'</span></p>';
            $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%">&nbsp;</td><td style="width:15%">'.$importe.'</td></tr>';
            $Conceptototal = str_replace(",", "",$Conceptototal);       
            $Conceptototal += $RegistroConceptos->Importe;
        }
    
    
    $DescuentoDeSaldo = number_format(Funciones::LimpiarNumero($DescuentoDeSaldo),2);
    $ActualizacionesYRecargos = number_format($ActualizacionesYRecargos,2);
    $ActualizacionesYRecargos = Funciones::LimpiarNumero($ActualizacionesYRecargos);
    $Descuento = Funciones::LimpiarNumero($Descuento) + Funciones::LimpiarNumero($DescuentosGenerales);
    $TotalPagar= ($Conceptototal+$ActualizacionesYRecargos+$PagoAnticipado)-$Descuento-$DescuentoDeSaldo;
    $Diferencia2 = 0;
 
    $Descuento= number_format($Descuento,2);  
    $ImportePago=$RegistroPago->Monto;
    $letras=utf8_decode(Funciones::num2letras($TotalPagar,0,0)." pesos  ");

    $ultimo = substr (strrchr ($TotalPagar, "."), 1, 2); //recupero lo que este despues del decimal
    if($ultimo=="")
        $ultimo="00";
    
    $importePagoLetra = $letras." ".$ultimo."/100 M.N.";
    setlocale(LC_TIME,"es_MX.UTF-8");
    $FechaCotizacion=strftime("%d de ",strtotime($RegistroPago->Fecha)).ucfirst(strftime("%B de %Y",strtotime($RegistroPago->Fecha)));

    $descripcion = "";
    $importe = "";
    $descripcion = '<p style="margin:0 -5px 0 0">
                     Actualizaciones y Recargos 
                    </p>';
    $importe = "<p style='margin:0 -5px 0 0' align='right'>$ $ActualizacionesYRecargos</p>";
 
    $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%">&nbsp;</td><td style="width:15%">'.$importe.'</td></tr>';

    $descripcion='<p>Descuentos y Redondeo</p>';
    $importe = "<p style='margin:0 -5px 0 0' align='right'>$ - $Descuento</p>";
    $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15% "><strong style="color:red">'.$importe.'</strong></td><td style="width:15%">&nbsp;</td></tr>';

    $descripcion='<p>Aplicaci&oacute;n de saldo</p>';
    $importe = "<p style='margin:0 -5px 0 0' align='right'>$ - $DescuentoDeSaldo</p>";
    
    if($DescuentoDeSaldo>0)
        $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%"><strong style="color:red">'.$importe.'</strong></td><td style="width:15%">&nbsp;</td></tr>';
    
    $descripcion='<p>Anticipo:</p>';
    $importe = "<p style='margin:0 -5px 0 0' align='right'>$ + $PagoAnticipado</p>";
    
    if($PagoAnticipado>0)
        $Conceptos .= '<tr><td style="width:70%">'.$descripcion.'</td><td style="width:15%"><strong style="color:red">'.$importe.'</strong></td><td style="width:15%">&nbsp;</td></tr>';
 
    $Observacionestxto= 'Observaciones:&nbsp;&nbsp;&nbsp;';

    $datosDeContrato ="<b>Propietario: </b> ".$datosPadron->Propietario."<br>"
        . "<b>Ubicaci&oacute;n: </b>".$datosPadron->Domicilio."<br>"
        . "<b>Localidad: </b>".$datosPadron->Localidad."<br>"
        . "<b>Municipio: </b>".$datosPadron->Municipio." Guerrero<br>"
        ;

    #precode($datosDeContrato,1,1);
    $DatosFiscales="<b>RFC</b>: ".$DatosPago->RFC."<br> <b>Direci&oacute;n:</b> ".ucwords(strtolower($DatosPago->Calle))." ".$DatosPago->N_umeroExterior." ".ucwords(strtolower($DatosPago->Colonia)).", C.P.".$DatosPago->C_odigoPostal."<br><b>Localidad:</b>  ".ucwords(strtolower((is_numeric($DatosPago->Localidad)?Funciones::ObtenValor("select Nombre from Localidad where id=".$DatosPago->Localidad, "Nombre"):$DatosPago->Localidad)))." <br><b>Municipio: </b> ". (is_numeric($DatosPago->Municipio)?Funciones::ObtenValor("select Nombre from Municipio where id=".$DatosPago->Municipio, "Nombre"):$DatosPago->Municipio)." "
        . "".ucwords(strtolower((is_numeric($DatosPago->EntidadFederativa)?Funciones::ObtenValor("select Nombre from EntidadFederativa where id=".$DatosPago->EntidadFederativa, "Nombre"):$DatosPago->EntidadFederativa)));

    $Conceptos .= '<tr><td colspan="3" style="width:100%"><br></td></tr><tr><td colspan="3" style="width:100%"><b>Giro: '.$datosPadron->Giro.'</b></td></tr>';

    $ImportePago = $Conceptototal;
    $HTML ='
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            .contenedor{
                height:735px;
                width: 975px;
                /*border: 1px solid red;*/
            }
            body{
                font-size: 12px;
            }
            th > div, th > span, th {
                font-size: 10px;
                valign: center;
            }
            .table > tbody > tr > td {
                font-size: 12px;
                vertical-align: top;
                border: hidden;
            }

            .main_container{

                padding-top:15px;
                padding-left:5px;
                z-index: 99;
                background-size: cover;
                width:975px;
                height:735px;
                position:relative;

            }
            table{
                font-size: 14px;
            }
            .break{
                display: block;
                clear: both;
                page-break-after: always;
            }
            h1 {
                font-size: 300%;
            }
        </style>
    </head>
    <div  '.($NoRecibos!=0?$OtraHoja:"").'>
    <body>
    <table style="height: 50px;" width="735px" class="table">
        <tbody>
        <tr>
            <td width="20%" align="center"><img src="'.asset($Cliente->Logo).'" alt="Logo del cliente" style="height: 120px;"></td>
            <td style="text-align: right;">
               <p>'.$Cliente->Descripci_on.'<br>Calle '.$Cliente->Calle.' No. '.$Cliente->N_umeroExterior.'<br>Colonia '.$Cliente->Colonia.' Codigo Postal '.$Cliente->C_odigoPostal.'<br>'.$Cliente->Localidad.', Guerrero<br>RFC: '.$Cliente->RFC.'</p>
               <p><span style="color:red; font-size:18px;">Licencia: '.($datosPadron->Cuenta?$datosPadron->Cuenta:'').''.($datosPadron->CuentaAnterior?(" - ".$datosPadron->CuentaAnterior):'').'</span> </p><br />
            </td>
        </tr>
        </tbody>
    </table>

    <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />
    <br>
    <br>
    <table style="height: 50px;" width="735px" class="table">
       <tbody>
       <tr>
            <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
                           <b>Datos de Licencia</b>
                           <br><br>
                           '.$datosDeContrato.'
                           </td>
                           <td colspan="3" width="50%" style="vertical-align:top;" v-align="top">
                           <b>Datos de Facturaci&oacute;n</b><br>
                <p><b>Razon Social: </b>'.$DatosPago->NombreORaz_onSocial.'<br>'.$DatosFiscales.'</p>
           </td>
       </tr>
       </tbody>
    </table>
    <br>
    <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />
    <br>
    <br>
    <table style="height: 5px;" width="735" class="table">
       <tbody>
       <tr>
           <td>
               <p><strong>Estado de Cuenta -<br>UUID: '.($UUID!="NULL"?$UUID:'').' </strong></p>
           </td>
            <td  style="text-align: right;">
               <p><strong>Expedici&oacute;n<br>  '.strftime("%d de ",strtotime($RegistroPago->Fecha)).ucfirst(strftime("%B del %Y",strtotime($RegistroPago->Fecha))).'</strong></p>
           </td>

       </tr>

       </tbody>
    </table>
    <br>
    <table    width="735" class="table">
        <tbody>
        '.$Conceptos.'
        </tbody>
    </table>
    
    <br>
    <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />
    <br/>
    <br/>

    <table style="height: 35px;" width="735" >
        <tbody>
            <tr>
                <td width="50%" style="border-right: 1px solid !important; border: thin;"  valign="top">
                    <p style="text-align: left;"><center><strong>Firma y Sello del Cajero.</center></strong></p>
                    <br>
                    <br>
                    <p style="text-align: left;"><center><strong>'.$Elaboro.'.</center></strong></p>
                </td>
                <td valign="top">
                     <p style="text-align: right;"><strong>Total Pagado</strong></p>
                    <h1 style="text-align: right;">$'.number_format($TotalPagar, 2).'</h1>
                </td>
            </tr>
        </tbody>
    </table>
    <br>
    <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" />
         <p style="text-align: right;"><strong>' . $importePagoLetra . '</strong> </p>
    <br>
    <br>
    <!--table style="height: 5px;" width="735px">
        <tbody>
        <tr>
            <td>
                <p><strong>Adeudos Anteriores</strong></p>
            </td>
            <td style="text-align: right;">
                <p>$</p>
            </td>
        </tr>
        </tbody>
    </table>
    <img style="width: 735px; height: 1px;" src="'.asset(Storage::url(env('IMAGES') . 'barraColores.png')).'" alt="Mountain View" / -->
    </body>
    </div>
    </html>';
          
    $arr['html'] = $HTML;
    $arr['Total']= $TotalPagar;
    $arr['NoRecibos'] = $NoRecibos;
    
    return $arr;                           
}
  
    
}