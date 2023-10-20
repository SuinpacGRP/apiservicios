<?php
namespace App\Http\Controllers\Presidentes;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use JWTAuth;
use JWTFactory;
use App\User;
use App\Cliente;
use App\Funciones;
use Validator;
class PresidentesController extends Controller
{
    public function __construct(){
        $this->middleware( 'jwt', ['except' => ['verificar_Usuario']] );
    }

    //Metodo para verificar usuario
    public function verificar_Usuario(Request $request){
        $usuario =$request->usuario;
        $pass = $request->passwd;

        $credentials = $request->all();

        $rules = [
            'usuario'     => 'required|string',
            'passwd' => 'required|string',

        ];

        $validator = Validator::make($credentials, $rules);

        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => 'Error',
                'Error' => $validator->messages()
            ]);
        }
        #echo Carbon::now()->addMinutes(2)->timestamp;
        JWTAuth::factory()->setTTL(600);
        /*$token = auth()->attempt( ['Usuario' => $credentials['Usuario'], 'password' => $credentials['Contrase_na'], 'EstadoActual' => 1] );
        return $token;*/

        if ( !$token = auth()->attempt( ['Usuario' => $credentials['usuario'], 'password' => $credentials['passwd'], 'EstadoActual' => 1] ) ) {
            return response()->json([
                'Status' => false,
                'msj' => 'Usuario o Contraseña Incorrectos',
            ]);
        }

        $usuario = auth()->user();

            //Actualizas el valor de tu array usuario en cliente, si tenias el -1 te pones el 20
            // $usuario->Cliente = 40;
            return response()->json([
                'Status' => true,
                'msj' => 'Inicio de sesión',
                'token' => $token ,
                'datosUsuario' =>$usuario,
                'cliente'=>$usuario->Cliente,
                'idUsuario'=>$usuario->idUsuario
            ]);



    }


    /*==================================================
     |Fin del metodo para verificar al usuario         |
     |=================================================|
    */



    public function buscarDatosContribullente(Request $request){
        //request idCliente
        //$idCliente = $request->nCliente;
        //capach
        $idCliente = $request->nCliente;
        Funciones::selecionarBase($idCliente); //regresar $idCliente antes de

        $datos = $request->all();

        $rules = [
            'nCliente' => 'required|string',
            'datoBusqueda' => 'required|string',
        ];

        $validator = Validator::make($datos, $rules);


        if($validator->fails() ){
             return response()->json([
                 'Status' => false,
                 'mensaje'=>'Campos vacios'
             ]);
        }

        $busqueda = $request->datoBusqueda;
        $cliente = $request->nCliente;

        //JWTAuth Sirve para poner tiempo de vida a un token

        //JWTAuth::factory()->setTTL(600);

        $consultaDatos = DB::select("SELECT DISTINCT id, ContratoVigente, Medidor, M_etodoCobro,
        (SELECT if(NombreComercial IS NULL, Nombres, NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
        WHERE Cliente=".$cliente." AND M_etodoCobro != 1 AND (ContratoVigente LIKE '%$busqueda%' OR Cuenta LIKE '%$busqueda%' OR Medidor LIKE '%$busqueda%'
        OR (SELECT NombreComercial FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) LIKE '%$busqueda%' OR (SELECT Nombres FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) LIKE '%$busqueda%')");


    if($consultaDatos){


    return response()->json([
        'Status' => true,
        'mensaje'=> $consultaDatos
    ]);
    }else{
        return response()->json([
        'Status' => true,
        'mensaje' => "No se encontraron registros"
    ]);
    }
}



public function extrarLectura(Request $request){

    $datos = $request->all();
    $rules =["idLectura" => 'required|string',
             "nCliente" => 'required|string'];

    $idCliente = $request->nCliente;
    $idBusqueda = $request->idLectura;



    $validator = Validator::make($datos, $rules);

    if($validator->fails()){
        return response()->json([
            'Status'=>false,
            'Mensaje'=>'Campos vacios'
            ]);
    }

    Funciones::selecionarBase($idCliente); //regresar al metodo $idCliente antes de entregar


    if($idCliente==69){
        $consulta = DB::select("SELECT LecturaActual, LecturaAnterior, Mes, A_no, TipoToma FROM Padr_onDeAguaLectura
    WHERE Padr_onAgua=$idBusqueda ORDER BY A_no DESC , Mes DESC LIMIT 1");
    }else{
        $consulta = DB::select("SELECT LecturaActual, LecturaAnterior, Mes, A_no, TipoToma FROM Padr_onDeAguaLectura
    WHERE Padr_onAgua=$idBusqueda ORDER BY id DESC , A_no DESC , Mes DESC LIMIT 1");
    }

     $extraerAnomalias = DB::select("SELECT *FROM Padr_onAguaCatalogoAnomalia");
    if($consulta){

        $extraerTipoLectura =  DB::select("SELECT  valor FROM ClienteDatos WHERE Cliente=$idCliente AND Indice = 'ConfiguracionFechaDeLectura'");


        $bloquearCampos = DB::select("SELECT  valor FROM ClienteDatos WHERE Cliente=$idCliente AND Indice = 'BloquerComposAppLecturaAgua'");
       return response()->json([
           'Status'=>true,
           'Mensaje'=>$consulta,
           'Anomalias'=>$extraerAnomalias,
           'ValorLectura' => $extraerTipoLectura,
           'BloquearCampos' => $bloquearCampos
       ]);
    }else{
        return response()->json([
            'Status'=>false,
            'Mensaje'=>300
        ]);
    }
}

public function guardarLectura(Request $request){

    $datos = $request->all();

    //creamos las reglas para validar los datos que llegane en el post
    $rules =[
    'anhioCaptura'=>'required|numeric',
    'cliente'=>'required|string',
    'consumoFinal'=>'required|numeric',
    'fechaCaptura'=>'required|string',
    'idToma'=>'required|string',
    'lecturaActual'=>'required|numeric',
    'lecturaAnterior'=>'required|numeric',
    'mesCaptura'=>'required|numeric',
    'idUsuario'=>'required|numeric',
    'anomalia'=>'',
    'latitud' => 'required',
    'longitud' =>'required',
    'fotos'=>'required',
    'tipoCoordenada' => ''];

    //Usando la clase validator verificamos que los las reglas establecidas se cumplan
    $validator = Validator::make($datos, $rules);
    if($validator->fails()){
        return response()->json([
            'Status' => false,
            'Mensaje' => 223 //Mensaje 223 campos incorrectos
        ]);
    }





    //Extraemos los datos del request y los almacenamos en las variables
    $dIdCliente =$request->cliente;
    $dAnioCaptura = $request->anhioCaptura;
    $dConsumoFinal = $request->consumoFinal;
    $dFechaCaptura = $request->fechaCaptura;
    $dIdToma = $request->idToma;  //id Padrón
    $dLecturaActual = $request->lecturaActual;
    $dLecturaAnterior = $request->lecturaAnterior;
    $dMesCaptura = $request->mesCaptura;
    $dAnomalia = $request->anomalia;
    $fechaRegistro =date('y-m-d');
    $didUsuario = $request->idUsuario;
    $latitud = $request->latitud;
    $longitud = $request->longitud;
    $tipoCoordenada = $request->tipoCoordenada;
    $fotos = $request->fotos;



    //Metodo que sirve para cambiar de tabla en la BD
    Funciones::selecionarBase($dIdCliente); //antes de entregar debes colocar $dIdCliente dentro del metodo

    $tipoToma = DB::select("SELECT TipoToma, Estatus, Consumo FROM Padr_onAguaPotable WHERE id=$dIdToma");



    if($dConsumoFinal < $tipoToma[0]->Consumo){
        $dConsumoFinal = $tipoToma[0]->Consumo;
    }

    $obtenTarifa =PresidentesController::ObtenConsumo($tipoToma[0]->TipoToma, $dConsumoFinal, $dIdCliente, $dAnioCaptura);//Mensaje 223 campos incorrectos

     if($obtenTarifa == 0){
       $obtenTarifa = $dConsumoFinal;
     }


     //insertamos los datos que llegaron en el post
    $consulta = DB::table('Padr_onDeAguaLectura')->insert([

          'Padr_onAgua'=>$dIdToma,
          'LecturaAnterior'=>$dLecturaAnterior,
          'LecturaActual'=>$dLecturaActual,
          'Consumo'=>$dConsumoFinal,
          'Mes'=>$dMesCaptura,
          'A_no'=>$dAnioCaptura,
          'Observaci_on'=>$dAnomalia,
          'FechaLectura'=>$fechaRegistro,
          'TipoToma'=>$tipoToma[0]->TipoToma,
          'EstadoToma' => $tipoToma[0]->Estatus,
          'Tarifa' => $obtenTarifa

    ]);

    //extraemos

    $ultimoId = DB::table('Padr_onDeAguaLectura')->orderBy('id', 'desc')->first();

    if($consulta){

        $guadarusaurioLectura = DB::table('Padr_onAguaPotableRLecturas')->insert([
            'idLectura'=> $ultimoId->id,
            'idUsuario'=> $didUsuario,
            'Padr_onAgua'=> $ultimoId->Padr_onAgua,
            'longitud' => $longitud,
            'latitud' => $latitud,
            'tipoCoordenada' => $tipoCoordenada,
            'fotos' => $fotos
        ]);

        $fechaRegistroCela =date('y-m-d H:i:s');
        $guardarUsuario = DB::table('CelaAccesos')->insert([
            'FechaDeAcceso'=> $fechaRegistroCela,
            'idUsuario' => $didUsuario,
            'Tabla' => 'Padr_onDeAguaLectura',
            'IdTabla' => $ultimoId->Padr_onAgua,
            'Acci_on' => 2
        ]);


        return response()->json([
            'Status' => true,
            'Mensaje'=> 200 ,  //mensaje 200 la accion se realizo con exito
            'consulta'=>$consulta
        ]);

    }else{

        return response()->json([
            'Status' => false,
            'Mensaje'=> 224   //mensaje 224 error al intentar guardar los datos
        ]);
    }
}



//Metodo que sirve para extraer el historial de lecturas para el cliente
public function extraerHistorilaDelecturas(Request $request){

     $datosRequest = $request->all(); //extraemos los datos del request

     //creamos nuestras reglas para el request

     $rules = [
         'idUsuario' =>'required|numeric',
         'nCliente'=>'required|numeric',
         'fechaInicioH'=>'required|string',
         'fechaFinH'=>'required|string'
     ];

     $validator = Validator::make($datosRequest, $rules);

     if($validator->fails()){

         return response()->json([
             'Status' => false ,
             'Mensaje' => 'Error, verifique que los campos esten rellenados de manera correcta'
         ]);

        }

        //creamos nuestras variables con la informacion que extraemos en el request
    $nCliente = $request->nCliente;
    $idUsuario = $request->idUsuario;
    $fechaI = $request->fechaInicioH;
    $fechaF = $request->fechaFinH;

    //Cambiamos de base de datos a la que pertenece el cliente
    Funciones::selecionarBase($nCliente);

    //Extraemos el historial de datos
    $historialConsulta = DB::select("SELECT
	pr.idLectura, p.id,
	pal.FechaLectura Fecha, p.Medidor, p.ContratoVigente,
    COALESCE( c.NombreComercial, CONCAT( c.Nombres, c.ApellidoPaterno, c.ApellidoMaterno ) ) Contribuyente FROM Padr_onAguaPotableRLecturas pr
    INNER JOIN Padr_onDeAguaLectura pal ON (pal.id=pr.idLectura)
    INNER JOIN Padr_onAguaPotable p ON (p.id=pal.Padr_onAgua)
    INNER JOIN Contribuyente c ON (c.id=p.Contribuyente)
    WHERE idUsuario = $idUsuario AND pal.FechaLectura>='$fechaI' AND pal.FechaLectura<='$fechaF' ");

    if($historialConsulta){
        return response()->json([
            'Status' => true,
            'Mensaje' => $historialConsulta,

        ]);
    }else{
         return response()->json([
             'Status' => false,
             'Mensaje' => 'Error al intentar conectar con la BD'
         ]);
    }
}


//Metodo que extrae los datos que van a ser editados
public function extraerDatosEditar(Request $request){
    $datos = $request->all();

    $rules = ['nCliente'=>'required|numeric',
               'idConsulta' => 'required|numeric'
    ];

    $validator = Validator::make($datos, $rules);

    if($validator->fails()){
        return response()->json([
            'Status'=>false,
            'Mensaje' => 'Verifique que los datos esten rellenados de manera correcta'
        ]);
    }

    $cliente = $request->nCliente;
    $idLectura = $request->idConsulta;

    Funciones::selecionarBase($cliente);


    $consultaDatos = DB::select('SELECT Padr_onAgua, LecturaAnterior, LecturaActual, Consumo, Mes, A_no, Observaci_on, FechaLectura
    FROM Padr_onDeAguaLectura WHERE id='.$idLectura);

    if($consultaDatos){

        $extraerTipoLectura =  DB::select("SELECT  valor FROM ClienteDatos WHERE Cliente=$cliente AND Indice = 'ConfiguracionFechaDeLectura'");
        $bloquearCampos = DB::select("SELECT  valor FROM ClienteDatos WHERE Cliente=$cliente AND Indice = 'BloquerComposAppLecturaAgua'");

        $extraerAnomalias = DB::select("SELECT *FROM Padr_onAguaCatalogoAnomalia");

        return response()->json([
            'Status'=>true,
            'Mensaje'=>$consultaDatos,
            'Anomalias'=>$extraerAnomalias,
            'tipoLectura' => $extraerTipoLectura,
            'bloqueoCampos' => $bloquearCampos
         ]);
    }else{
       return response()->json([
           'Status' =>false,
           'Mensaje' => 'Error a intentar conectar a la BD'
       ]);
    }
}


public function actualiarDatos(Request $request){

    $datos = $request->all();

    $rules =[
        'anhioCaptura'=>'required|numeric',
        'cliente'=>'required|string',
        'consumoFinal'=>'required|numeric',
        'fechaCaptura'=>'required|string',
        'idToma'=>'required|string',
        'lecturaActual'=>'required|numeric',
        'lecturaAnterior'=>'required|numeric',
        'mesCaptura'=>'required|numeric',
        'anomalia'=>''];

    $validator = Validator::make($datos, $rules);

    if($validator->fails()){
        return response()->json([
           'Satatus' => false,
           'Mensaje' => 'Asegurese que los datos se hayan rellenado de manera correcta'
        ]);
    }

    $dAnhioCaptura = $request->anhioCaptura;
    $dCliente = $request->cliente;
    $dConsumo = $request->consumoFinal;
    $dFechaCaptura = $request->fechaCaptura;
    $dIdToma = $request->idToma;
    $dLecturaActual = $request->lecturaActual;
    $dLecturaAnterior = $request->lecturaAnterior;
    $dMesCaptura = $request->mesCaptura;
    $dAnomalia = $request->anomalia;

    Funciones::selecionarBase($dCliente);

    //LecturaAnterior, LecturaActual, Consumo, Mes, A_no, Observaci_on, FechaLectura
    $actualizarDatos = DB:: table('Padr_onDeAguaLectura')->where('id', $dIdToma)->update([
        'LecturaAnterior'=>$dLecturaAnterior,
        'LecturaActual'=>$dLecturaActual,
        'Consumo'=>$dConsumo,
        'Mes'=>$dMesCaptura,
        'A_no'=>$dAnhioCaptura,
        'Observaci_on'=>$dAnomalia,
        'FechaLectura'=>$dFechaCaptura
    ]);


    if($actualizarDatos){

        return response()->json([
            'Status'=> true,
            'Mensaje' => $actualizarDatos
            ]);

    }else{
        return response()->json([
          'Status' => false,
          'Mensaje' => 'Sin cambios por realizar'
        ]);
    }
}


/*******************************************************
 ** Metodo para verificar si un usuario es lecturista **
 *******************************************************/

public function verificarUsuarioLecturista(Request $request){

    $datos = $request->all();

    $rules = ['usuario'=>'required|numeric',
          'cliente' => 'required|numeric'
        ];

    $validator = Validator::make($datos, $rules);

    if($validator->fails()){
        return response()->json([
           'Status'=> false,
           'Mensaje' => 'Asegures que los datos se hayan rellenado de manera correcta'
        ]);
    }

    $cliente = $request->cliente;
    $usuario = $request->usuario;

    Funciones::selecionarBase($cliente);

    $esLecturista= DB::select('SELECT c.idUsuario FROM CelaUsuario c INNER JOIN  PuestoEmpleado pe ON(c.idEmpleado= pe.Empleado)
    INNER JOIN PlantillaN_ominaCliente pc ON(pe.PlantillaN_ominaCliente=pc.id)
    WHERE    pe.Estatus=1 and c.EstadoActual=1 and (pc.Cat_alogoPlazaN_omina in(72,73,318) OR c.Rol=1) AND c.idUsuario='.$usuario);

    if($esLecturista){
       return response()->json([
        'Status'=> true,
        'Mensaje' => $esLecturista
       ]);
    }else{

        return response()->json([
            'Status' => false,
            'Mensaje' => 'No puedes iniciar sesion con este usuario'
        ]);
    }
}


public function crearReporte(Request $request){

    $datos = $request->all();
    $rules = ['cliente'=>'required',
            'usuario'=>'required',
            'colonia'=>'required',
            'calle'=>'required',
            'numero'=>'',
            'descripcion'=>'required'
        ];

        $validator = Validator::make($datos, $rules);

        if($validator->fails()){
            return response()->json([
              'Status'=>false,
              'Mensaje'=>"Asegurese de que los campos se hayan rellenado de manera correcta"
            ]);
        }

        $cliente = $request->cliente;
        $usuario = $request->usuario;
        $colonia = $request->colonia;
        $calle = $request->calle;
        $numero = $request->numero;
        $descripcion = $request->descripcion;
        $fechaReporte = date('y-m-d');

        Funciones::selecionarBase($cliente);


      $insertarDatos = DB::table('Padr_onAguaPotable_ReportesLecturistas')->insert([
         'Colonia'=>$colonia,
         'Calle'=>$calle,
         'Numero'=>$numero,
         'Descripcion'=>$descripcion,
         'Usuario'=>$usuario,
         'Fecha'=>$fechaReporte
      ]);

      if($insertarDatos){
          return response()->json([
            'Status'=>true,
            'Mensaje'=>"Lo datos se almacenaron de manera correcta"
          ]);
      }else{
           return response()->json([
            'Status'=>false,
            'Mensaje' => "Error al intentar comunicarse con la Base de datos"
           ]);
      }
}




/*************************************************
 ** Metodos para calcular el consumo ¡NO TOCAR! **
 *************************************************/

function ObtenConsumo($TipoToma, $Cantidad, $Cliente, $ejercicioFiscal){

	$ConfiguracionCalculo= PresidentesController::ObtenValorPorClave("CalculoAgua", $Cliente);
	if($ConfiguracionCalculo==1){


		//echo "<p>".$Cantidad."</p>";
	//if($ejercicioFiscal==2013 OR $ejercicioFiscal==2014 OR $ejercicioFiscal==2015 )
	//	$ejercicioFiscal=2012;
	$maximoValor=Funciones::ObtenValor("SELECT MAX(mts3) as Maximo FROM ConsumosAgua WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND  TipoTomaAguaPotable=".$TipoToma, "Maximo");
	//if($Cantidad<10){
	//	$Cantidad=10;
	//}
	if($Cantidad>($maximoValor-1)){
		$restante=$Cantidad-($maximoValor-1);
		$Cantidad=$maximoValor;
		//echo "<p>"."SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND TipoTomaAguaPotable=".$TipoToma." AND mts3<=".intval($Cantidad)."</p>";

		$Importe100 = Funciones::ObtenValor("SELECT Importe as Suma FROM ConsumosAgua WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND   TipoTomaAguaPotable=".$TipoToma." AND mts3=".intval($Cantidad), "Suma");
		$ValorDeMasDe100=$Importe100*$restante;

		$cantidad100 = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua  WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND TipoTomaAguaPotable=".$TipoToma." AND mts3>0 AND mts3<".intval($Cantidad), "Suma");

		$ImporteTotal = $cantidad100+$ValorDeMasDe100;

	}else{

		if($Cantidad!=0){
		$ImporteTotal = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND  TipoTomaAguaPotable=".$TipoToma." AND  mts3>0 AND mts3<=".intval($Cantidad), "Suma");
		}
		else{
			$ImporteTotal = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND  TipoTomaAguaPotable=".$TipoToma." AND  mts3=0", "Suma");

		}

	}

	return PresidentesController::truncateFloat($ImporteTotal,2);

	}else
	{
		$maximoValor=Funciones::ObtenValor("SELECT MAX(mts3) as Maximo FROM ConsumosAgua WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND  TipoTomaAguaPotable=".$TipoToma, "Maximo");

		if($Cantidad>$maximoValor){
			$Importe = Funciones::ObtenValor("SELECT Importe as Suma FROM ConsumosAgua WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND   TipoTomaAguaPotable=".$TipoToma." AND mts3=".intval($maximoValor), "Suma");
			$ImporteTotal=$Importe*$Cantidad;

		}else{
			$Importe = Funciones::ObtenValor("SELECT Importe as Suma FROM ConsumosAgua WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND   TipoTomaAguaPotable=".$TipoToma." AND mts3=".intval($Cantidad), "Suma");
			$ImporteTotal=$Importe*$Cantidad;
		}
		if($Cantidad==0){
			$ImporteTotal = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=".$Cliente." AND EjercicioFiscal=".$ejercicioFiscal." AND  TipoTomaAguaPotable=".$TipoToma." AND  mts3=0", "Suma");
		}

		return PresidentesController::truncateFloat($ImporteTotal,2);


	}
}


function ObtenValorPorClave($Clave, $Cliente){
	return Funciones::ObtenValor("SELECT Valor FROM ClienteDatos WHERE Cliente=".$Cliente." AND Indice='".$Clave."'", "Valor");

}


function truncateFloat($number, $digitos)
{
    $raiz = 10;
    $multiplicador = pow ($raiz,$digitos);
    $resultado = ((int)($number * $multiplicador)) / $multiplicador;
    return number_format($resultado, $digitos);

}

}
