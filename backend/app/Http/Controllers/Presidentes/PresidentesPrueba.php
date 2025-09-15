<?php

namespace App\Http\Controllers\Presidentes;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
require_once 'Conexion.php';
use JWTAuth;
use JWTFactory;
use App\User;
use App\Cliente;
use App\Funciones;
use Validator;

class PresidentesPrueba extends Controller
{
    public function __construct(){
    $this->middleware( 'jwt', ['except' =>
    ['actualizarConfiguracion',
    'obtenerEmpleadoTarea',
    'bitacoraTareaCompleta',
    'horarioEmpleado',
    'pruebaAsistencias',
    'obtenerCotizacionesAIFA',
    'obtenerClienteAIFA',
    'obtenerTicketAIFA',
    'obtenerTicketAIFAV2',
    'obtenerTicketPatio',
    'CotizaEstacionamiento',
    'FacturarEstacionamiento',
    'FacturarEstacionamientoV2',
    'FacturarPatio',
    'validarRegimenFiscalAIFA',
    'verificarUsuarioAIFA',
    'EnviarDatosTicket',
    'ValidarRFCAIFA',
    'CortePruebaCAPAZ',
    'pruebaCombustibleQR',
    'pruebaCombustibleQRValidar',
    'actualizarConexion',
    'obtenerLogotipoClienteChecador',
    'MultarToma',
    'InspeccionarToma',
    'obtenerLogotipo',
    'recuperarChecador',
    'obtenerLogoCliente',
    'obtenerListaCientes',
    'obtenerEmpleados',
    'verificar_Usuario',
    'verificar_UsuarioAIFA',
    'guardarLecturaV2',
    'configurarChecador',
    'verificarChecador',
    'actualizarBanner',
    'obtenerSectores',
    'registrarAsistenciaChecador',
    #'checaAsistenciaExistente',
    'obtenerBitacoraChecador',
    'obtenerRecurso',
    'obtenerEmpleadosGeneral',
    'verificarAsistenciaActual',
    'verificarAsistencia',
    'verificarAsistenciaV2']] );
    }

    public function verificar_UsuarioAIFA(Request $request){
        return response()->json( ['Message' => 'Prueba Entrante'] );
    }

    //Metodo para verificar usuario Prueba de modificado
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
            #$usuario->Cliente = 40;
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
        /*******************************************************
    ** Metodos para la aplicacion del lectura de agua **
    *******************************************************/
    public function datosLecturaPorSector(Request $request){
        //request idCliente
        //$idCliente = $request->nCliente;
        //capach
        $idCliente = $request->nCliente;
        Funciones::selecionarBase($idCliente); //regresar $idCliente antes de

        $datos = $request->all();

        $rules = [
            'nCliente' => 'required|string',
            'Sector' => 'required|string',
            'Offset' => 'required'
        ];

        $validator = Validator::make($datos, $rules);


        if($validator->fails() ){
            return response()->json([
                'Status' => false,
                'mensaje'=>'Campos vacios'
            ]);
        }

        $sector = $request->Sector;
        $cliente = $request->nCliente;
        $mes = $request->mes;
        $a_no = $request->a_no;
        $offset = $request->Offset;
        //JWTAuth Sirve para poner tiempo de vida a un token

        //JWTAuth::factory()->setTTL(600);
        $cuentasPapas = Funciones::ObtenValor("SELECT GROUP_CONCAT(p.id) as CuentasPapas FROM Padr_onAguaPotable p WHERE p.id=(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1) and p.Cliente = $cliente", "CuentasPapas");

        /*$consultaDatos = DB::select("SELECT id,
                                            ContratoVigente,
                                            Medidor,M_etodoCobro,
                                            ( SELECT COALESCE(CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno),c.NombreComercial) FROM Contribuyente c WHERE c.id = Contribuyente ) AS Contribuyente
                                            FROM
                                            Padr_onAguaPotable p
                                            WHERE
                                            p.Sector = $sector
                                            AND p.Cliente = $cliente
                                            AND id NOT IN ( SELECT Padr_onAgua FROM Padr_onDeAguaLectura WHERE Mes = 3 AND A_no = 2021)
                                            AND Estatus in(1,2)
                                            AND M_etodoCobro in(2,3)
                                            ORDER BY
                                            p.Ruta ASC
                                            ,CAST(p.Cuenta AS INT) ASC
                                            ,CAST(p.ContratoVigente AS INT) ASC");

        */
        //Se quito el filtro [AND Estatus in(1,2,10)]
        $consultaDatos = DB::select("SELECT
                                            Estatus,
                                            p.id,
                                            p.ContratoVigente,
                                            p.Medidor,p.M_etodoCobro,toma.Concepto as Toma,
                                            ( SELECT COALESCE(CONCAT(c.Nombres,' ',c.ApellidoPaterno,' ',c.ApellidoMaterno),c.NombreComercial) FROM Contribuyente c WHERE c.id = Contribuyente ) AS Contribuyente
                                        FROM
                                            Padr_onAguaPotable p
                                        JOIN TipoTomaAguaPotable toma on p.TipoToma = toma.id
                                        WHERE
                                            p.Sector = ".$sector." AND p.id NOT IN(".($cuentasPapas!=""?$cuentasPapas:0).")
                                            AND p.Cliente = $cliente
                                            AND Estatus in(1,2)
                                            AND p.id NOT IN ( SELECT Padr_onAgua FROM Padr_onDeAguaLectura WHERE Mes = " .$mes. " AND A_no = ".$a_no." )
                                        ORDER BY
                                            p.Ruta ASC
                                            ,CAST(p.Cuenta AS INT) ASC
                                            ,CAST(p.ContratoVigente AS INT) ASC limit 20 offset $offset");

        if($consultaDatos){
            return response()->json([
                'Status' => true,
                'mensaje'=> $consultaDatos,
                'Papas'=>$cuentasPapas
            ]);
        }else{
            return response()->json([
                'Status' => true,
                'mensaje' => "No se encontraron registros",
                'Error' => $consultaDatos
            ]);
        }
    }
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
            'Offset'=>'required'
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
        $mes = $request->mes;
        $a_no = $request->a_no;
        $offset = $request->Offset;
        $cuentasPapas = Funciones::ObtenValor("SELECT GROUP_CONCAT(p.id) as CuentasPapas FROM Padr_onAguaPotable p WHERE p.id=(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1) and p.Cliente = $cliente", "CuentasPapas");
        //Se quito este filtro [ AND Estatus in(1,2,10) ]
        //JWTAuth Sirve para poner tiempo de vida a un token

        //JWTAuth::factory()->setTTL(600);

        $consultaDatos = DB::select("SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,
        (SELECT COALESCE(CONCAT(Nombres,' ',ApellidoPaterno,' ',ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
        JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
        WHERE Cliente=".$cliente." AND M_etodoCobro != 1 AND (ContratoVigente LIKE '%$busqueda%' OR Cuenta LIKE '%$busqueda%' OR Medidor LIKE '%$busqueda%'
        OR (SELECT NombreComercial FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) LIKE '%$busqueda%' OR (SELECT Nombres FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) LIKE '%$busqueda%')
        AND Padr_onAguaPotable.id NOT IN(".($cuentasPapas!=""?$cuentasPapas:0).")
                                                AND Padr_onAguaPotable.Cliente = $cliente
                                                AND Padr_onAguaPotable.id NOT IN ( SELECT Padr_onAgua FROM Padr_onDeAguaLectura WHERE Mes = " .$mes. " AND A_no = ".$a_no." )
                                                AND M_etodoCobro in(2,3)
                                                limit 20 offset $offset");


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
            //Consulta anterior
        /*     $consulta = DB::select("SELECT LecturaActual, LecturaAnterior, Mes, A_no, TipoToma FROM Padr_onDeAguaLectura
        WHERE Padr_onAgua=$idBusqueda ORDER BY id DESC , A_no DESC , Mes DESC LIMIT 1"); */
        $consulta = DB::select("SELECT pa.M_etodoCobro, pl.LecturaActual, pl.LecturaAnterior, pl.Mes, pl.A_no, pl.TipoToma, pl.Observaci_on, ( CONCAT_WS(' ',pa.Domicilio , 'Manzana:',if ( pa.Manzana is null, 'S/N', pa.Manzana), 'Lote:', if (pa.Lote is null, 'S/N',pa.Lote ) ) ) as Direccion , m.Nombre as Municipio, l.Nombre as Localidad, toma.Concepto AS Toma
                                    FROM Padr_onAguaPotable pa
                                    LEFT JOIN Padr_onDeAguaLectura pl on  pl.Padr_onAgua = pa.id
                                    LEFT JOIN Municipio m on m.id = pa.Municipio
                                    LEFT JOIN Localidad l on l.id = pa.Localidad
                                    LEFT JOIN TipoTomaAguaPotable toma on pa.TipoToma = toma.id
                                    WHERE pa.id = $idBusqueda
                                    ORDER BY A_no DESC , Mes DESC LIMIT 1;");
        $extraerAnomalias = DB::select("SELECT paca.*,pacal.Acci_on AS Accion, pacal.ActualizarAtras, pacal.ActualizarAdelante, pacal.Minima FROM Padr_onAguaCatalogoAnomalia paca LEFT JOIN Padr_onAguaPotableCalculoPorAnomalias pacal ON ( pacal.Anomalia = paca.clave )
        WHERE pacal.Cliente = $idCliente AND pacal.Estatus = 1"); //FIXME: cambiar por el id del cliente
        foreach($extraerAnomalias as $item){
            if($item->clave == 2 || $item->clave == 40 ){
                $item->ActualizarAtras = "1";
                $item->ActualizarAdelante = "1";
            }
        }

        $extraerTipoLectura =  DB::select("SELECT  valor FROM ClienteDatos WHERE Cliente=$idCliente AND Indice = 'ConfiguracionFechaDeLectura'");
        $bloquearCampos = DB::select("SELECT  valor FROM ClienteDatos WHERE Cliente=$idCliente AND Indice = 'BloquerComposAppLecturaAgua'");

        if($consulta){
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
                'Anomalias'=>$extraerAnomalias,
                'ValorLectura' => $extraerTipoLectura,
                'BloquearCampos' => $bloquearCampos,
                'Mensaje'=>"SELECT pa.M_etodoCobro, pl.LecturaActual, pl.LecturaAnterior, pl.Mes, pl.A_no, pl.TipoToma, ( CONCAT_WS(' ',pa.Domicilio , 'Manzana:',if ( pa.Manzana is null, 'S/N', pa.Manzana), 'Lote:', if (pa.Lote is null, 'S/N',pa.Lote ) ) ) as Direccion , m.Nombre as Municipio, l.Nombre as Localidad, toma.Concepto AS Toma
                FROM Padr_onDeAguaLectura pl
                JOIN Padr_onAguaPotable pa on pa.id = pl.Padr_onAgua
                LEFT JOIN Municipio m on m.id = pa.Municipio
                LEFT JOIN Localidad l on l.id = pa.Localidad
                LEFT JOIN TipoTomaAguaPotable toma on pa.TipoToma = toma.id
                WHERE pa.id = $idBusqueda
                ORDER BY pl.id DESC , A_no DESC , Mes DESC LIMIT 1",
            ]);
        }
    }
    public function MultarToma(Request $request){
        return response()->json([
            'Status'=>false,
            'Mensaje'=>'Campos vacios'
        ]);
    }
    public function InspeccionarToma(Request $request){
        return response()->json([
            'Status'=>false,
            'Mensaje'=>'Campos vacios'
        ]);
    }
    public function buscarSectores(Request $request){

        $datos = $request->all();
        $rules =[
            "nCliente" => 'required|string',
            "idUsuario" => 'required|string'];

        $idCliente = $request->nCliente;
        $idUsuario = $request->idUsuario;

        $validator = Validator::make($datos, $rules);

        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>'Campos vacios'
            ]);
        }

        Funciones::selecionarBase($idCliente); //regresar al metodo $idCliente antes de entregar
        $consulta = "SELECT pas.Sector as id ,CONCAT(pas.Sector,'-',pas.Nombre) as Sector FROM Padr_onAguaPotableSectoresLecturistas pasl
                                INNER JOIN Padr_onAguaPotableSector pas ON (pasl.idSector = pas.id)
                                WHERE pasl.idLecturista = $idUsuario AND pas.Cliente = $idCliente";
        $extraerSectores = DB::select($consulta);
        if($extraerSectores){


            return response()->json([
                'Status'=>true,
                'Mensaje'=>"OK",
                'Sectores'=>$extraerSectores

            ]);

        }else{
            return response()->json([
                'Status'=>false,
                'Mensaje'=>300,
                'consulta'=>$consulta

            ]);
        }
    }
    public function guardarLecturaV2(Request $request){
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
            'idUsuario'=>'required|numeric',
            'anomalia'=>'',
            'latitud' => 'required',
            'longitud' =>'required',
            #'fotos'=>'required',
            'tipoCoordenada' => '',
            'arregloFotos'=>''];

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
        $dIdToma = $request->idToma; //id Padrón
        $dLecturaActual = $request->lecturaActual;
        $dLecturaAnterior = $request->lecturaAnterior;
        $dMesCaptura = $request->mesCaptura;
        $dAnomalia = $request->anomalia;
        $fechaRegistro = date('y-m-d');
        $didUsuario = $request->idUsuario;
        $latitud = $request->latitud;
        $longitud = $request->longitud;
        $tipoCoordenada = $request->tipoCoordenada;
        $fotos = $request->fotos;
        $arregloFotos = $request->arregloFotos;
        $ruta = $request->ruta;
        $arregloNombre = array();
        $arregloSize = array();
        $arregloRuta = array();
        Funciones::selecionarBase($dIdCliente); //antes de entregar debes colocar $dIdCliente dentro del metodo
        $mesBD = DB::select("SELECT MAX(Mes) as Mes FROM Padr_onDeAguaLectura  WHERE Padr_onAgua = $dIdToma AND A_no = $dAnioCaptura");
        if($mesBD[0]->Mes == $dMesCaptura){
            return response()->json([
                'Status' => false,
                'Mensaje'=> 400   //mensaje 224 error al intentar guardar los datos
            ]);
        }
        $image_base64 = "";
        foreach ($arregloFotos as $arregloFoto){
            $image_64 = $arregloFoto;
            $extension = explode('/', explode(':', substr($image_64, 0, strpos($image_64, ';')))[1])[1];   // .jpg .png .pdf
            $replace = substr($image_64, 0, strpos($image_64, ',')+1);
            $image = str_replace($replace, '', $image_64);
            $image = str_replace(' ', '+', $image);
            $imageName = 'FotoAgua'.uniqid().'.'.$extension;
            $size_in_bytes = (int) (strlen(rtrim($image_64, '=')) * 3 / 4);
            $size_in_kb    = $size_in_bytes / 1024;
            $size_in_mb    = $size_in_kb / 1024;
            Storage::disk('repositorio')->put("/FotosAgua/".$ruta."/".$imageName, base64_decode($image));
            array_push($arregloNombre,$imageName);
            array_push($arregloSize,$size_in_bytes);
            array_push($arregloRuta,"repositorio/FotosAgua/".$ruta."".$imageName);
        }
        $tipoToma = DB::select("SELECT TipoToma, Estatus, Consumo, M_etodoCobro FROM Padr_onAguaPotable WHERE id=$dIdToma");
        //REVIEW: se cambio por el consumo 20 en caso de que el consumo sea menor a 20

        //INDEV: Cambio por la nueva tarifa consumo ( todas las tomas en promedio y de consumo se cambia con un consumo munimo 20 )


            $consumoReal = $dConsumoFinal;
            if($dConsumoFinal < $tipoToma[0]->Consumo){
                $dConsumoFinal = $tipoToma[0]->Consumo;
            }


        $obtenTarifa = $this->ObtenConsumo($tipoToma[0]->TipoToma, $dConsumoFinal, $dIdCliente, $dAnioCaptura);//Mensaje 223 campos incorrectos
        if($obtenTarifa == 0){
            $obtenTarifa = $dConsumoFinal;
        }
        $consulta = DB::table('Padr_onDeAguaLectura')->insert([
            'Padr_onAgua'=>$dIdToma,
            'LecturaAnterior'=>$dLecturaAnterior ,
            'LecturaActual'=> $dLecturaActual, //FIXME: parche temporal para el error de lectura actual 0
            'Consumo'=> $consumoReal,
            'Mes'=>$dMesCaptura,
            'A_no'=>$dAnioCaptura,
            'Observaci_on'=>$dAnomalia,
            'FechaLectura'=>$fechaRegistro,
            'TipoToma'=>$tipoToma[0]->TipoToma,
            'EstadoToma' => $tipoToma[0]->Estatus,
            'Tarifa' => $obtenTarifa,
            'Tarifa' => 3

        ]);
        $ultimoId = DB::table('Padr_onDeAguaLectura')->orderBy('id', 'desc')->first();
        $consulta = true;

        if($consulta){
            $fechaRegistroCela =date('y-m-d H:i:s');
            $guadarusaurioLectura = DB::table('Padr_onAguaPotableRLecturas')->insert([
                'idLectura'=> $ultimoId->id,
                'idUsuario'=> $didUsuario,
                'Padr_onAgua'=> $ultimoId->Padr_onAgua,
                'longitud' => $longitud,
                'latitud' => $latitud,
                'tipoCoordenada' => $tipoCoordenada
            ]);

            $ultimoIdAguaR = DB::table('Padr_onAguaPotableRLecturas')->orderBy('id', 'desc')->first();
            $contador = 0;
            $idCela = "";
            foreach ($arregloRuta as $value){
                $agregarRutas = DB::table('CelaRepositorio')->insert([
                    'Tabla'=>'Padr_onAguaPotableRLecturas',
                    'idTabla'=>$ultimoIdAguaR->id,
                    'Ruta'=> $value,
                    'Descripci_on'=>'Fotos de la aplicacion',
                    'idUsuario'=>$didUsuario,
                    'FechaDeCreaci_on'=>$fechaRegistroCela,
                    'Estado'=>1,
                    'Reciente'=>1,
                    'NombreOriginal'=>$arregloNombre[$contador],
                    'Size'=>$arregloSize[$contador]
                ]);
                $ultimoCela=Funciones::ObtenValor("SELECT idRepositorio FROM CelaRepositorio ORDER BY idRepositorio DESC","idRepositorio");
                $idCela.= $ultimoCela.",";
                $contador++;
            }

            $actualizarDatos = DB:: table('Padr_onAguaPotableRLecturas')->where('id', $ultimoIdAguaR->id)->update([
                'Fotos'=>$idCela
            ]);
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
            ]);

        }else{

            return response()->json([
                'Status' => false,
                'Mensaje'=> 224   //mensaje 224 error al intentar guardar los datos
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
            #'fotos'=>'required',
            'tipoCoordenada' => ''];

        //Usando la clase validator verificamos que los las reglas establecidas se cumplan
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje' => 2234 //Mensaje 223 campos incorrectos
            ]);
        }





        //Extraemos los datos del request y los almacenamos en las variables
        $dIdCliente =$request->cliente;
        $dAnioCaptura = $request->anhioCaptura;
        $dConsumoFinal = $request->consumoFinal;
        $dFechaCaptura = $request->fechaCaptura;
        $dIdToma = $request->idToma; //id Padrón
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

        $obtenTarifa = $this->ObtenConsumo($tipoToma[0]->TipoToma, $dConsumoFinal, $dIdCliente, $dAnioCaptura);//Mensaje 223 campos incorrectos
        #return $obtenTarifa;
        #print_r($obtenTarifa);
        if($obtenTarifa == 0){
            $obtenTarifa = $dConsumoFinal;
        }
        /*
               $archivo = $request->file('photo');
               $name = $archivo->getClientOriginalName();
               $size = $archivo->getSize();
               return "$name => $size";
               */

        $files = $request->file('photo');

        $salida = "";
        foreach($files as $file){
            $salida .= $file->getClientOriginalName();
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
    //INDEV: metodo de prueba para la subida de archivos
    public function guardarLecturaPruebas(Request $request){

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
            #'fotos'=>'required',
            'tipoCoordenada' => '',
            'arregloFotos'=>''];

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
        $dIdToma = $request->idToma; //id Padrón
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
        $arregloFotos = $request->arregloFotos;

        $arregloNombre = array();
        $arregloSize = array();
        $arregloRuta = array();


        #var_dump($arregloFotos);
        #return $request;
        #$image_base64 = "";
        #$foto = $arregloFotos[0];
        #$indexFoto = 1;
        #foreach($arregloFotos as $foto){
        #    $arregloNombre = $foto;
        #    Storage::disk('repositorio')->put("/FotosAgua/prueba00".$indexFoto.".png", base64_decode($foto));
        #    $indexFoto = $indexFoto+1;
        #}
        return response() ->download(server_path('/repositorio/temporal/upload_60b7a4cee603f'));
    }
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
        pal.FechaLectura Fecha, p.Medidor, p.ContratoVigente, TipoTomaAguaPotable.Concepto  as Toma,
        COALESCE( c.NombreComercial, CONCAT( c.Nombres,' ', c.ApellidoPaterno,' ', c.ApellidoMaterno ) ) Contribuyente FROM Padr_onAguaPotableRLecturas pr
        INNER JOIN Padr_onDeAguaLectura pal ON (pal.id=pr.idLectura)
        INNER JOIN Padr_onAguaPotable p ON (p.id=pal.Padr_onAgua)
        INNER JOIN Contribuyente c ON (c.id=p.Contribuyente)
        INNER JOIN TipoTomaAguaPotable on (TipoTomaAguaPotable.id = pal.TipoToma)
        WHERE idUsuario = $idUsuario AND pal.FechaLectura>='$fechaI' AND pal.FechaLectura<='$fechaF'");

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
        /*
        $consultaDatos = DB::select('SELECT Padr_onAgua, LecturaAnterior, LecturaActual, Consumo, Mes, A_no, Observaci_on, FechaLectura
        FROM Padr_onDeAguaLectura WHERE id='.$idLectura);
        */
        $consultaDatos = DB::table('Padr_onDeAguaLectura')
                        ->select('Padr_onDeAguaLectura.Padr_onAgua',
                                'Padr_onDeAguaLectura.LecturaAnterior',
                                'Padr_onDeAguaLectura.LecturaActual',
                                'Padr_onDeAguaLectura.Consumo',
                                'Padr_onDeAguaLectura.Mes',
                                'Padr_onDeAguaLectura.A_no',
                                'Padr_onDeAguaLectura.Observaci_on',
                                'Padr_onDeAguaLectura.FechaLectura',
                                DB::raw('COALESCE(CONCAT(Contribuyente.Nombres," ",Contribuyente.ApellidoPaterno," ",Contribuyente.ApellidoMaterno),Contribuyente.NombreComercial) as Nombre'),
                                'Contribuyente.Colonia_c as Colonia',
                                'Contribuyente.Calle_c as Calle',
                                'Contribuyente.N_umeroExterior_c as Exterior',
                                'Contribuyente.N_umeroInterior_c as Interior',
                                'Contribuyente.C_odigoPostal_c as Postal',
                                'Municipio.Nombre as Municipio',
                                'Padr_onAguaPotable.Medidor',
                                'Padr_onAguaPotable.ContratoVigente',
                                'Localidad.Nombre as Localidad',
                                'TipoTomaAguaPotable.Concepto as Toma')
                        ->join('Padr_onAguaPotable','Padr_onDeAguaLectura.Padr_onAgua','=','Padr_onAguaPotable.id')
                        ->join('Contribuyente','Contribuyente.id','=','Padr_onAguaPotable.Contribuyente')
                        ->join('Municipio','Contribuyente.Municipio_c','=','Municipio.id')
                        ->join('Localidad','Localidad.id','=','Contribuyente.Localidad_c')
                        ->join('TipoTomaAguaPotable','TipoTomaAguaPotable.id','=','Padr_onAguaPotable.TipoToma')
                        ->where('Padr_onDeAguaLectura.id','=',$idLectura)
                        ->get();
        if($consultaDatos){

        $extraerTipoLectura =  DB::select("SELECT  valor FROM ClienteDatos WHERE Cliente=$cliente AND Indice = 'ConfiguracionFechaDeLectura'");
        $bloquearCampos = DB::select("SELECT  valor FROM ClienteDatos WHERE Cliente=$cliente AND Indice = 'BloquerComposAppLecturaAgua'");

        $extraerAnomalias = DB::select("SELECT paca.*,pacal.Acci_on AS Accion, pacal.ActualizarAtras, pacal.ActualizarAdelante, pacal.Minima FROM Padr_onAguaCatalogoAnomalia paca LEFT JOIN Padr_onAguaPotableCalculoPorAnomalias pacal ON ( pacal.Anomalia = paca.clave )
        WHERE pacal.Cliente = ".$cliente." AND pacal.Estatus = 1");

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
        'idPadronLetura'=>'required|string',
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
        $dConsumoFinal = $request->consumoFinal;
        $dFechaCaptura = $request->fechaCaptura;
        $dIdToma = $request->idToma;
        $dLecturaActual = $request->lecturaActual;
        $dLecturaAnterior = $request->lecturaAnterior;
        $dMesCaptura = $request->mesCaptura;
        $dAnomalia = $request->anomalia;
        $idLectura = $request->idPadronLetura;
        Funciones::selecionarBase($dCliente);
        $tipoToma = DB::select("SELECT TipoToma, Estatus, Consumo FROM Padr_onAguaPotable WHERE id=$dIdToma");
        if($dConsumoFinal < $tipoToma[0]->Consumo){
            $dConsumoFinal = $tipoToma[0]->Consumo;
        }
        $obtenTarifa = $this->ObtenConsumo($tipoToma[0]->TipoToma, $dConsumoFinal, $dCliente, $dAnhioCaptura);
        //LecturaAnterior, LecturaActual, Consumo, Mes, A_no, Observaci_on, FechaLectura
        $actualizarDatos = DB::table('Padr_onDeAguaLectura')->where('id', $idLectura)->update([
        'LecturaAnterior'=>$dLecturaAnterior,
        'LecturaActual'=>$dLecturaActual,
        'Consumo'=>$dConsumoFinal,
        'A_no'=>$dAnhioCaptura,
        'Observaci_on'=>$dAnomalia,
        'FechaLectura'=>$dFechaCaptura,
        'Tarifa' => $obtenTarifa
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
    public function getSectorBusquedaPalabraClave(Request $request){
        //request idCliente
        //$idCliente = $request->nCliente;
        //capach
        $idCliente = $request->nCliente;
        Funciones::selecionarBase($idCliente); //regresar $idCliente antes de

        $datos = $request->all();

        $rules = [
            'nCliente' => 'required|string',
            'datoBusqueda' => 'required|string',
            'sector'=>'required|string',
            'Offset'=>'required'
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
        $sector = "-1";
        $mes = $request->mes;
        $a_no = $request->a_no;
        $offset = $request->Offset;
        $cuentasPapas = Funciones::ObtenValor("SELECT GROUP_CONCAT(p.id) as CuentasPapas FROM Padr_onAguaPotable p WHERE p.id=(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1) and p.Cliente = $cliente", "CuentasPapas");
        //Se quito este filtro [ AND Estatus in(1,2,10) ]
        //JWTAuth Sirve para poner tiempo de vida a un token

        //JWTAuth::factory()->setTTL(600);

        $consultaDatos = DB::select("SELECT DISTINCT  Padr_onAguaPotable.Sector, Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,
        (SELECT COALESCE(CONCAT(Nombres,' ',ApellidoPaterno,' ',ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
        JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
        WHERE Cliente=".$cliente." AND M_etodoCobro != 1 AND (ContratoVigente LIKE '%$busqueda%' OR Cuenta LIKE '%$busqueda%' OR Medidor LIKE '%$busqueda%'
        OR (SELECT NombreComercial FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) LIKE '%$busqueda%' OR (SELECT CONCAT(Nombres,' ',ApellidoPaterno,' ',ApellidoMaterno) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) LIKE '%$busqueda%')
        AND Padr_onAguaPotable.id NOT IN(".($cuentasPapas!=""?$cuentasPapas:0).")
                                                AND Padr_onAguaPotable.Cliente = $cliente
                                                AND Padr_onAguaPotable.id NOT IN ( SELECT Padr_onAgua FROM Padr_onDeAguaLectura WHERE Mes = " .$mes. " AND A_no = ".$a_no." )
                                                AND M_etodoCobro in(2,3)
                                                ".($sector != "-1" ? ("AND Sector = $sector"):(""))."
                                                limit 20 offset $offset");


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
    public function numeroPaginasSectorBusqueda(Request $request){
        $datos = $request->all();
        $rules = [
            'cliente'=>'required',
            'busqueda'=>'required',
            'sector'=>'required'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de que los campos se hayan rellenado de manera correcta",
                'Code'=>223
            ]);
        }
        $cliente = $request->cliente;
        $busqueda = $request->busqueda;
        $sector = $request->sector;
        $mes = $request->mes;
        $anio = $request->anio;
        //Consulta de cuentas Con lectura
        Funciones::selecionarBase($cliente);
        $cuentasPapas = Funciones::ObtenValor("SELECT GROUP_CONCAT(p.id) as CuentasPapas FROM Padr_onAguaPotable p WHERE p.id=(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1) and p.Cliente = $cliente", "CuentasPapas");
        $result = DB::select("SELECT DISTINCT COUNT(Padr_onAguaPotable.id) as Total
                                FROM
                                    Padr_onAguaPotable
                                    JOIN TipoTomaAguaPotable toma ON Padr_onAguaPotable.TipoToma = toma.id
                                WHERE
                                    Cliente = $cliente
                                    AND M_etodoCobro != 1
                                    AND (
                                        ContratoVigente LIKE '%$busqueda%'
                                        OR Cuenta LIKE '%$busqueda%'
                                        OR Medidor LIKE '%$busqueda%'
                                        OR ( SELECT NombreComercial FROM Contribuyente WHERE Contribuyente.id = Padr_onAguaPotable.Contribuyente ) LIKE '%$busqueda%'
                                        OR ( SELECT Nombres FROM Contribuyente WHERE Contribuyente.id = Padr_onAguaPotable.Contribuyente ) LIKE '%$busqueda%'
                                    )
                                    AND Padr_onAguaPotable.id NOT IN (".($cuentasPapas!=""?$cuentasPapas:0).")
                                    AND Padr_onAguaPotable.Cliente = $cliente
                                    AND Padr_onAguaPotable.id NOT IN ( SELECT Padr_onAgua FROM Padr_onDeAguaLectura WHERE Mes = ".$mes." AND A_no = ".$anio." )
                                    AND M_etodoCobro IN ( 2, 3 )
                                    ".($sector != "-1" ? ("AND Sector = $sector"):("")));
        return response()->json([
            'Status'=>true,
            'Mensaje'=>$result,
            'Code'=> 200
            ]);

    }
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
        WHERE pe.Estatus=1 and c.EstadoActual=1 and (pc.Cat_alogoPlazaN_omina in(72,73,318,583,297,587) OR c.Rol=1 OR c.idUsuario in (3800,4833,5334,5333, 5452,5451, 5450, 5840, 3813, 5452)) AND c.idUsuario='.$usuario);

        if($esLecturista){
        return response()->json([
        'Status'=> true,
        'Mensaje' => $esLecturista
        ]);
        }else{

        return response()->json([
            'Status' => false,
            'Mensaje' => 'No puedes iniciar sesion con este usuarioss ' . $esLecturista
        ]);
        }
    }

    public function verificarUsuarioAIFA(Request $request)
    {
        $datos = $request->all();
    
        $rules = [
            'usuario'   => 'required|numeric',
            'cliente'   => 'required|numeric',
            'password'  => 'required|string'
        ];
    
        $validator = Validator::make($datos, $rules);
    
        if ($validator->fails()) {
            return response()->json([
                'Status'=> false,
                'Mensaje' => 'Asegúrese de que los datos se hayan rellenado correctamente'
            ]);
        }
    
        
        $cliente  = $request->cliente;
        $usuario  = $request->usuario;
        $password = $request->password;
    
        
        $conexionAIFA = conectarBDSuinpac();
    
        
        $usuario  = mysqli_real_escape_string($conexionAIFA, $usuario);
        $password = mysqli_real_escape_string($conexionAIFA, $password);
    
        
        $validarUsuario = "
            SELECT c.idUsuario, c.Password
            FROM CelaUsuario c
            INNER JOIN PuestoEmpleado pe ON(c.idEmpleado = pe.Empleado)
            INNER JOIN PlantillaN_ominaCliente pc ON(pe.PlantillaN_ominaCliente = pc.id)
            WHERE pe.Estatus=1 
              AND c.EstadoActual=1 
              AND c.idUsuario = '$usuario'
            LIMIT 1
        ";
    
        if ($resultado = mysqli_query($conexionAIFA, $validarUsuario)) {
            if (mysqli_num_rows($resultado) > 0) {
                $row = mysqli_fetch_assoc($resultado);
    
                
                if (password_verify($password, $row['Password'])) {
                    return response()->json([
                        'Status'  => true,
                        'Mensaje' => 'Inicio de sesión correcto',
                        'Usuario' => $row['idUsuario']
                    ]);
                } 
                
             
                return response()->json([
                    'Status' => false,
                    'Mensaje' => 'Contraseña incorrecta'
                ]);
            } else {
                return response()->json([
                    'Status' => false,
                    'Mensaje' => 'Usuario no encontrado o sin permisos'
                ]);
            }
        } else {
            return response()->json([
                'Status' => false,
                'Mensaje' => 'Error en la consulta: ' . mysqli_error($conexionAIFA)
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
            'descripcion'=>'required',
            'contrato' =>'',
            'fallaAdministrativa'=>'required'
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
        $contrato = $request->contrato;
        $fallaAdministrativa = $request->fallaAdministrativa;
        $fechaReporte = date('y-m-d');

        Funciones::selecionarBase($cliente);

        $insertarDatos = DB::table('Padr_onAguaPotable_ReportesLecturistas')->insert([
        'Colonia'=>$colonia,
        'Calle'=>$calle,
        'Numero'=>$numero,
        'Descripcion'=>$descripcion,
        'Usuario'=>$usuario,
        'Fecha'=>$fechaReporte,
        'Contrato'=>$contrato,
        'FallaAdministrativa'=>$fallaAdministrativa,
        ]);

        if($insertarDatos){
        return response()->json([
            'Status'=>true,
            'Mensaje'=>"Los datos se agregaron correctamente"
        ]);
        }else{
        return response()->json([
            'Status'=>false,
            'Mensaje' => "Error al intentar comunicarse con la Base de datos"
        ]);
        }
    }
    //INDEV: metodos para obtener la lista de los reportes
    public function obtenerReportes(Request $request){
        $datos = $request->all();
        $rules = [
            'cliente'=>'required',
            'fechaInicio'=>'required|string',
            'fechaFin'=>'required|string',
            'idUsuario'=>'required'];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de que los campos se hayan rellenado de manera correcta",
                'Code'=> 223
              ]);
        }
        $cliente = $request->cliente;
        $fechaI = $request->fechaInicio;
        $fechaF = $request->fechaFin;
        $idUsuario = $request->idUsuario;
        Funciones::selecionarBase($cliente);
        //$result = DB::select("select * from Padr_onAguaPotable_ReportesLecturistas");
        $result = DB::table('Padr_onAguaPotable_ReportesLecturistas')
            ->whereBetween('Fecha',[$fechaI,$fechaF])
            ->where('Usuario',$idUsuario)
            ->get();
        return response()->json([
            'Status'=>true,
            'Mensaje'=>$result,
            'Code'=> 200
          ]);
    }
    public function obtenerReporte(Request $request){
        $datos = $request->all();
        $rules = [
            'cliente'=>'required',
            'idUsuario'=>'required',
            'id'=>'required'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de que los campos se hayan rellenado de manera correcta",
                'Code'=>223
            ]);
        }
        $idUsuario = $request->idUsuario;
        $cliente = $request->cliente;
        $id = $request->id;
        Funciones::selecionarBase($cliente);
        $result = DB::table('Padr_onAguaPotable_ReportesLecturistas')
            ->where('Usuario',$idUsuario)
            ->where('id',$id)
            ->get();
        return response()->json([
            'Status'=>true,
            'Mensaje'=>$result,
            'Code'=> 200
          ]);
    }
    public function numeroDePaginas(Request $request){
        $datos  = $request->all();
        $rules = [
            'cliente'=>'required',
            'sector'=>'required',
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de que los campos se hayan rellenado de manera correcta",
                'Code'=> 223
              ]);
        }
        $cliente = $request->cliente;
        $sector = $request->sector;
        $mes = $request->mes;
        $a_no = $request->anio;
        //consulta para validar las lecturas ya hechas
        Funciones::selecionarBase($cliente);
        $cuentasPapas = Funciones::ObtenValor("SELECT GROUP_CONCAT(p.id) as CuentasPapas FROM Padr_onAguaPotable p WHERE p.id=(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1) and p.Cliente = $cliente", "CuentasPapas");

        //Numero de resultados
        $consultaDatos = DB::select("SELECT
                                            COUNT(id) as cantidad
                                        FROM
                                            Padr_onAguaPotable p
                                        WHERE
                                            p.Sector = ".$sector." AND p.id NOT IN(".($cuentasPapas!=""?$cuentasPapas:0).")
                                            AND p.Cliente = ".$cliente."
                                            AND id NOT IN ( SELECT Padr_onAgua FROM Padr_onDeAguaLectura WHERE Mes = " .$mes. " AND A_no = ".$a_no." )
                                            AND Estatus in(1,2,10)
                                            #AND M_etodoCobro in(2,3)
                                        ORDER BY
                                            p.Ruta ASC
                                            ,CAST(p.Cuenta AS INT) ASC
                                            ,CAST(p.ContratoVigente AS INT) ASC");

        return response()->json([
            'Status'=>true,
            'Mensaje'=>$consultaDatos,
            'Code'=> 200
          ]);

    }
    public function obtenerPromerdio(Request $request){
        $datos = $request->all();
        $rules = [
            'nCliente'=>'required',
            'idLectura'=>'required',
        ];
        $validator = Validator::make($datos,$rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de que los campos se hayan rellenado de manera correcta",
                'Code'=> 223
              ]);
        }
        $cliente = $request->nCliente;
        $idLectura = $request->idLectura;
        Funciones::selecionarBase($cliente);
        //SELECT Consumo FROM Padr_onDeAguaLectura where Padr_onAgua = 142770 ORDER BY FechaLectura DESC LIMIT 12;
        $result = DB::table("Padr_onDeAguaLectura")
            ->select('Consumo','FechaTupla')
            ->where('Padr_onAgua',$idLectura)
            ->orderBy('FechaLectura','DESC')
            ->limit(12)->get();
        $promedio = 0;
        foreach($result as $item){
            $promedio += $item->Consumo;
        }
        $minimo = false;
        if( $promedio == 0 ){
            $tipoToma = DB::select("SELECT TipoToma, Estatus, Consumo FROM Padr_onAguaPotable WHERE id=$idLectura");
            $promedio = $tipoToma[0]->Consumo;
            $minimo = true;
        }

        return response()->json([
            'Status'=>true,
            'Mensaje'=> $minimo ? $promedio : ($promedio/ 12),
            'Datos'=>$result,
            'Code'=> 200
        ]);
    }
    public function obtenerPromerdioEditar(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required',
            'Padron'=>'required',
            'Lectura'=>'required'
        ];
        $validator = Validator::make($datos,$rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de que los campos se hayan rellenado de manera correcta",
                'Code'=> 223
              ]);
        }
        $cliente = $request->Cliente;
        $padron = $request->Padron;
        $lectura = $request->Lectura;
        //NOTE: Funciona correctamente
        Funciones::selecionarBase($cliente);
        $result = DB::table('Padr_onDeAguaLectura')
                    ->select('Consumo','FechaTupla')
                    ->where('Padr_onAgua','=',$padron)
                    ->where('id','<>',$lectura)
                    ->orderBy('FechaLectura','DESC')
                    ->limit(12)->get();
        $promedio = 0;
        foreach($result as $item){
            $promedio += $item->Consumo;
        }
        return response()->json([
            'Status'=>true,
            'Mensaje'=>($promedio / 12),
            'Datos'=>$result,
            'Code'=> 200
        ]);
    }
    public function obtenerLogotipo(Request $request){
        $datos = $request -> all();
        $rules = [
            'nCliente'=>'required'
        ];
        $validator = Validator::make($datos,$rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de que los campos se hayan rellenado de manera correcta",
                'Code'=> 223
              ]);
        }
        $cliente = $request->nCliente;
        Funciones::selecionarBase($cliente);
        $result = DB::table('Cliente')
            ->select('Ruta','NombreCorto')
            ->join('CelaRepositorioC','Logotipo','idRepositorio')
            ->where('Cliente.id','=',$cliente)->get()->first();
        $rutaLogo = $result->Ruta;
        $nombreCorto = $result->NombreCorto;
        $formatedUrl = str_replace('repositorio',"",$rutaLogo);
        $data = Storage::disk('repositorio') -> get($formatedUrl);
        $encodeLogo = base64_encode($data);
        return response()->json([
            'Status'=>true,
            'Mensaje'=> $encodeLogo,
            'Data'=>$nombreCorto,
            'Code'=> 200
        ]);
    }
    public function obtenerContribuyente(Request $request){
        $datos = $request -> all();
        $rules = [
            'nCliente'=>'required',
            'idBusqueda'=>'requiered',
        ];
        $idBusqueda = $request->idBusqueda;
        $cliente = $request->nCliente;
        Funciones::selecionarBase($cliente);
        $result = DB::table('Padr_onDeAguaLectura')
                            ->select('Padr_onAguaPotable.ContratoVigente',
                                    'Padr_onAguaPotable.Medidor',
                                    'Municipio.Nombre as Municipio',
                                    'Localidad.Nombre as Localidad',
                                    'TipoTomaAguaPotable.Concepto AS Toma',
                                    DB::raw('COALESCE(CONCAT(Contribuyente.Nombres," ",Contribuyente.ApellidoPaterno," ",Contribuyente.ApellidoMaterno),Contribuyente.NombreComercial) as Nombre'),
                                    'Contribuyente.Tel_efonoCelular as Celular',
                                    'Contribuyente.Tel_efonoParticular as Particular',
                                    'Contribuyente.CorreoElectr_onico as Email',
                                    'Contribuyente.Colonia_c as Colonia',
                                    'Contribuyente.Calle_c as Calle',
                                    'Contribuyente.N_umeroExterior_c as Exterior',
                                    'Contribuyente.N_umeroInterior_c as Interior',
                                    'Contribuyente.C_odigoPostal_c as CodigoPostal',
                                    'Contribuyente.id as Contribuyente')
                            ->join('Padr_onAguaPotable','Padr_onAguaPotable.id','=','Padr_onDeAguaLectura.Padr_onAgua')
                            ->leftjoin('Municipio','Municipio.id','=','Padr_onAguaPotable.Municipio')
                            ->leftjoin('Localidad','Localidad.id','=','Padr_onAguaPotable.Localidad')
                            ->join('TipoTomaAguaPotable','Padr_onAguaPotable.TipoToma','=','TipoTomaAguaPotable.id')
                            ->join('Contribuyente','Contribuyente.id','=','Padr_onAguaPotable.Contribuyente')
                            ->where('Padr_onDeAguaLectura.Padr_onAgua','=',$idBusqueda)
                            ->limit(1)->get();
        return response()->json([
            'Status'=>true,
            'Mensaje'=> $result,
            'Code'=> 200
        ]);
    }
    public function actualizarContactoContribuyente(Request $request){
        $datos = $request -> all();
        $rules = [
            'telefono'=> 'required',
            'email'=>'',
            'cliente'=>'required',
            'id'=>'required'
        ];
        $validator = Validator::make($datos,$rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de que los campos se hayan rellenado de manera correcta",
                'Code'=> 223
              ]);
        }
        $telefono = $request->telefono;
        $cliente = $request->cliente;
        $email = $request->email;
        $id = $request->id;
        Funciones::selecionarBase($cliente);
        $result = DB::table('Contribuyente')
                            ->where('id',$id)
                            ->update([ 'Tel_efonoCelular'=>$telefono, 'CorreoElectr_onico'=>$email ]);

        if($result){
        return response()->json([
            'Status'=>true,
            'Mensaje'=>"Los datos se agregaron correctamente",
            'data' =>$result
        ]);
        }else{
        return response()->json([
            'Status'=>false,
            'Mensaje' => "No se encontro el contribuyente",
            'data'=>$result
        ]);
        }
    }
    public function numeroPaginasBusqueda(Request $request){
        $datos = $request->all();
        $rules = [
            'cliente'=>'required',
            'busqueda'=>'required'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de que los campos se hayan rellenado de manera correcta",
                'Code'=>223
            ]);
        }
        $cliente = $request->cliente;
        $busqueda = $request->busqueda;
        $mes = $request->mes;
        $anio = $request->anio;
        //Consulta de cuentas Con lectura
        Funciones::selecionarBase($cliente);
        $cuentasPapas = Funciones::ObtenValor("SELECT GROUP_CONCAT(p.id) as CuentasPapas FROM Padr_onAguaPotable p WHERE p.id=(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1) and p.Cliente = $cliente", "CuentasPapas");
        $result = DB::select("SELECT DISTINCT COUNT(Padr_onAguaPotable.id) as Total
                                FROM
                                    Padr_onAguaPotable
                                    JOIN TipoTomaAguaPotable toma ON Padr_onAguaPotable.TipoToma = toma.id
                                WHERE
                                    Cliente = $cliente
                                    AND M_etodoCobro != 1
                                    AND (
                                        ContratoVigente LIKE '%$busqueda%'
                                        OR Cuenta LIKE '%$busqueda%'
                                        OR Medidor LIKE '%$busqueda%'
                                        OR ( SELECT NombreComercial FROM Contribuyente WHERE Contribuyente.id = Padr_onAguaPotable.Contribuyente ) LIKE '%$busqueda%'
                                        OR ( SELECT Nombres FROM Contribuyente WHERE Contribuyente.id = Padr_onAguaPotable.Contribuyente ) LIKE '%$busqueda%'
                                    )
                                    AND Padr_onAguaPotable.id NOT IN (".($cuentasPapas!=""?$cuentasPapas:0).")
                                    AND Padr_onAguaPotable.Cliente = $cliente
                                    AND Padr_onAguaPotable.id NOT IN ( SELECT Padr_onAgua FROM Padr_onDeAguaLectura WHERE Mes = ".$mes." AND A_no = ".$anio." )
                                    AND M_etodoCobro IN ( 2, 3 )");
        return response()->json([
            'Status'=>true,
            'Mensaje'=>$result,
            'Code'=> 200
            ]);

    }
    public function obtenerPadronContribyente(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required',
            'Busqueda'=>'required'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de que los campos se hayan rellenado de manera correcta",
                'Code'=>223
            ]);
        }
        $busqueda = $request->Busqueda;
        $cliente = $request->Cliente;
        Funciones::selecionarBase($cliente);
        $result = DB::select("SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,
            (SELECT COALESCE(CONCAT(Nombres,' ',ApellidoPaterno,' ',ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
            JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
            WHERE Cliente = $cliente AND (ContratoVigente LIKE '%$busqueda%' OR Cuenta LIKE '%$busqueda%' OR Medidor LIKE '%$busqueda%'
            OR (SELECT NombreComercial FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) LIKE '%$busqueda%' OR (SELECT Nombres FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) LIKE '%$busqueda%')
                                                    AND Padr_onAguaPotable.Cliente = $cliente LIMIT 10");
        if($result){
            return response()->json([
                'Status' => true,
                'mensaje'=> $result
                ]);
        }else{
            return response()->json([
                'Status' => true,
                'mensaje' => "No se encontraron registros"
                ]);
        }

    }
    public function obtenerPadronContribyenteDatos(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required',
            'Padron'=>'required'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
            'Status'=>false,
            'Mensaje'=>"Asegurese de que los campos se hayan rellenado de manera correcta"
            ]);
        }
        $cliente = $request->Cliente;
        $padron = $request->Padron;
        Funciones::selecionarBase($cliente);
        $result = DB::table("Padr_onAguaPotable")->select('Padr_onAguaPotable.id AS Padron',
                                                    DB::raw('CONCAT(Contribuyente.Nombres," ",Contribuyente.ApellidoPaterno," ",Contribuyente.ApellidoMaterno) AS Nombre'),
                                                    'Municipio.Nombre AS Municipio',
                                                    'Localidad.Nombre AS Localidad',
                                                    'TipoTomaAguaPotable.Concepto AS Toma',
                                                    'Contribuyente.Colonia_c as Colonia',
                                                    'Contribuyente.Calle_c AS Calle',
                                                    'Contribuyente.N_umeroExterior_c AS NoExterior',
                                                    'Contribuyente.N_umeroInterior_c AS NoInterior',
                                                    'Contribuyente.C_odigoPostal_c AS CPostal',
                                                    'Contribuyente.id AS Contribuyente',
                                                    'Padr_onAguaPotable.ContratoVigente',
                                                    'Padr_onAguaPotable.Medidor')
                                                ->join("Contribuyente","Contribuyente.id","=","Padr_onAguaPotable.Contribuyente")
                                                ->join("Municipio","Municipio.id","=","Padr_onAguaPotable.Municipio")
                                                ->join("Localidad","Localidad.id","=","Padr_onAguaPotable.Localidad")
                                                ->join("TipoTomaAguaPotable","TipoTomaAguaPotable.id","=","Padr_onAguaPotable.TipoToma")
                                                ->where("Padr_onAguaPotable.id","=",$padron)->get();
        if($result){
            return response()->json([
                'Status' => true,
                'mensaje'=> $result
                ]);
        }else{
            return response()->json([
                'Status' => true,
                'mensaje' => "No se encontraron registros"
                ]);
        }

    }
    public function buscarPorContrato (Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required',
            'Contrato'=>'required'
        ];
        if($request->Cliente==32){
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de que los campos se hayan rellenado de manera correcta",
                'Code'=>223
            ]);
        }
        }

        $cliente = $request->Cliente;
        $contrato = $request->Contrato;
        $mes = $request->mes;
        $anio = $request->a_no;
        $usuario = $request -> usuario;
        $estatus = $request -> estatus;
        Funciones::selecionarBase($cliente);
        $cuentasPapas = Funciones::ObtenValor("SELECT GROUP_CONCAT(p.id) as CuentasPapas FROM Padr_onAguaPotable p WHERE p.id=(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1) and p.Cliente = $cliente", "CuentasPapas");
        if($cliente==69){
            if($estatus==1){
            $sectoresLecturista = DB::select("SELECT GROUP_CONCAT(CAST(Sector AS INT)) as sectores, Padr_onAguaPotableSectoresLecturistas.Ruta as Ruta FROM Padr_onAguaPotableSector JOIN Padr_onAguaPotableSectoresLecturistas ON ( Padr_onAguaPotableSector.id = Padr_onAguaPotableSectoresLecturistas.idSector ) WHERE idLecturista = $usuario");
            $result = DB::select('SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,Estatus,
                                (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
                                JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
                                WHERE Cliente = '.$cliente.'
                                AND ( ContratoVigente LIKE "%'.$contrato.'%" or ContratoVigente = '.intval($contrato).')
                                AND Padr_onAguaPotable.Cliente = '.$cliente.'
                                AND Padr_onAguaPotable.Ruta in('.$sectoresLecturista[0]->Ruta.')
                                AND Estatus in(1,2,10) AND Padr_onAguaPotable.Sector IN ( '.( $sectoresLecturista[0]->sectores == "" ? "0" : $sectoresLecturista[0]->sectores ).')
                                ORDER BY ContratoVigente ASC');
            }else{
                $sectoresLecturista = DB::select("SELECT GROUP_CONCAT(CAST(Sector AS INT)) as sectores, Padr_onAguaPotableSectoresLecturistas.Ruta as Ruta FROM Padr_onAguaPotableSector JOIN Padr_onAguaPotableSectoresLecturistas ON ( Padr_onAguaPotableSector.id = Padr_onAguaPotableSectoresLecturistas.idSector ) WHERE idLecturista = $usuario");
                $result = DB::select('SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,Estatus,
                                    (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
                                    JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
                                    WHERE Cliente = '.$cliente.'
                                    AND ( ContratoVigente LIKE "%'.$contrato.'%" or ContratoVigente = '.intval($contrato).')
                                    AND Padr_onAguaPotable.Cliente = '.$cliente.'
                                    AND Padr_onAguaPotable.Ruta in('.$sectoresLecturista[0]->Ruta.')
                                    AND Estatus in(1,2,10) AND Padr_onAguaPotable.Sector IN ( '.( $sectoresLecturista[0]->sectores == "" ? "0" : $sectoresLecturista[0]->sectores ).')
                                    ORDER BY ContratoVigente DESC');
            }
        }else{
            $sectoresLecturista = DB::select("SELECT GROUP_CONCAT(CAST(Sector AS INT)) as sectores FROM Padr_onAguaPotableSector JOIN Padr_onAguaPotableSectoresLecturistas ON ( Padr_onAguaPotableSector.id = Padr_onAguaPotableSectoresLecturistas.idSector ) WHERE idLecturista = $usuario");
            $result = DB::select('SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,Estatus,
                                (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
                                JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
                                WHERE Cliente = '.$cliente.'
                                AND ( ContratoVigente LIKE "%'.$contrato.'" or ContratoVigente = '.intval($contrato).')
                                AND Padr_onAguaPotable.Cliente = '.$cliente.'
                                AND Padr_onAguaPotable.id NOT IN ( SELECT Padr_onAgua FROM Padr_onDeAguaLectura WHERE Mes = '.$mes.' AND A_no = '.$anio.' )
                                AND Estatus in(1,2,10) AND Padr_onAguaPotable.Sector IN ( '.( $sectoresLecturista[0]->sectores == "" ? "0" : $sectoresLecturista[0]->sectores ).')');
        }

            if( sizeof($result) > 0 ){
                return response()->json([
                    'Status'=>true,
                    'Mensaje'=>$result,
                    'Papas'=>$cuentasPapas,
                    'Code'=> 200
                ]);
            }else{
                if($sectoresLecturista[0]->sectores == null){
                    return response()->json([
                        'Status'=>false,
                        'Mensaje'=>"Favor de verificar sus sectores asignados",
                        'Code'=> 403
                    ]);
                }else{
                    return response()->json([
                        'Status'=>false,
                        'Respuesta'=>$result,
                        'listaSectores'=>$sectoresLecturista,
                        'Mensaje'=>"Contrato no encontrado, verifique que el contrato se encuentre sus sectores asignados",
                        'Code'=> 403
                    ]);
                }

            }
    }
    public function buscarPorMedidor (Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required',
            'Contrato'=>'required'
        ];
        if($request->Cliente==32){
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de que los campos se hayan rellenado de manera correcta",
                'Code'=>223
            ]);
        }
        }

        $cliente = $request->Cliente;
        $contrato = $request->Contrato;
        $mes = $request->mes;
        $anio = $request->a_no;
        $usuario = $request->usuario;
        $estatus = $request->estatus;
        Funciones::selecionarBase($cliente);
        $cuentasPapas = Funciones::ObtenValor("SELECT GROUP_CONCAT(p.id) as CuentasPapas FROM Padr_onAguaPotable p WHERE p.id=(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1) and p.Cliente = $cliente", "CuentasPapas");
        if($cliente ==69){
            if($estatus==1){
            $sectoresLecturista = DB::select("SELECT GROUP_CONCAT(CAST(Sector AS INT)) as sectores, Padr_onAguaPotableSectoresLecturistas.Ruta as Ruta FROM Padr_onAguaPotableSector JOIN Padr_onAguaPotableSectoresLecturistas ON ( Padr_onAguaPotableSector.id = Padr_onAguaPotableSectoresLecturistas.idSector ) WHERE idLecturista = $usuario");
            $result = DB::select('SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,Estatus,
                                (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
                                JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
                                WHERE Cliente = '.$cliente.'
                                AND ( Medidor LIKE "%'.$contrato.'%" or Medidor = '.intval($contrato).')
                                AND Padr_onAguaPotable.Cliente = '.$cliente.'
                                AND Padr_onAguaPotable.Ruta in('.$sectoresLecturista[0]->Ruta.')
                                AND Estatus in(1,2,10) AND Padr_onAguaPotable.Sector IN ( '.( $sectoresLecturista[0]->sectores == "" ? "0" : $sectoresLecturista[0]->sectores ).')
                                ORDER BY Medidor ASC');
            }else{
            $sectoresLecturista = DB::select("SELECT GROUP_CONCAT(CAST(Sector AS INT)) as sectores, Padr_onAguaPotableSectoresLecturistas.Ruta as Ruta FROM Padr_onAguaPotableSector JOIN Padr_onAguaPotableSectoresLecturistas ON ( Padr_onAguaPotableSector.id = Padr_onAguaPotableSectoresLecturistas.idSector ) WHERE idLecturista = $usuario");
            $result = DB::select('SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,Estatus,
                                (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
                                JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
                                WHERE Cliente = '.$cliente.'
                                AND ( Medidor LIKE "%'.$contrato.'%" or Medidor = '.intval($contrato).')
                                AND Padr_onAguaPotable.Cliente = '.$cliente.'
                                AND Padr_onAguaPotable.Ruta in('.$sectoresLecturista[0]->Ruta.')
                                AND Estatus in(1,2,10) AND Padr_onAguaPotable.Sector IN ( '.( $sectoresLecturista[0]->sectores == "" ? "0" : $sectoresLecturista[0]->sectores ).')
                                ORDER BY Medidor DESC');
            }
        }else{
            $sectoresLecturista = DB::select("SELECT GROUP_CONCAT(CAST(Sector AS INT)) as sectores FROM Padr_onAguaPotableSector JOIN Padr_onAguaPotableSectoresLecturistas ON ( Padr_onAguaPotableSector.id = Padr_onAguaPotableSectoresLecturistas.idSector ) WHERE idLecturista = $usuario");
        $result = DB::select('SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,Estatus,
                            (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
                            JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
                            WHERE Cliente = '.$cliente.'
                            AND ( Medidor LIKE "%'.$contrato.'" or Medidor = '.intval($contrato).')
                            AND Padr_onAguaPotable.Cliente = '.$cliente.'
                            AND Padr_onAguaPotable.id NOT IN ( SELECT Padr_onAgua FROM Padr_onDeAguaLectura WHERE Mes = '.$mes.' AND A_no = '.$anio.' )
                            AND Estatus in(1,2,10) AND Padr_onAguaPotable.Sector IN ( '.( $sectoresLecturista[0]->sectores == "" ? "0" : $sectoresLecturista[0]->sectores ).')');
        }

        if(sizeof($result) > 0){
            return response()->json([
                'Status'=>true,
                'Mensaje'=>$result,
                'Papas'=>$cuentasPapas,
                'Code'=> 200
                ]);
        }else{
            if($sectoresLecturista[0]->sectores == null){
                return response()->json([
                    'Status'=>false,
                    'Mensaje'=>"Favor de verificar sus sectores asignados",
                    'Code'=> 403
                ]);
            }else{
                return response()->json([
                    'Status'=>false,
                    'listaSectores'=>$sectoresLecturista,
                    'Mensaje'=>"Contrato no encontrado, asegurese que el contrato este en su sector",
                    'Code'=> 403
                ]);
            }
        }
    }
    public function buscarPorFolio (Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required',
            'Contrato'=>'required'
        ];
        if($request->Cliente==32){
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de que los campos se hayan rellenado de manera correcta",
                'Code'=>223
            ]);
        }
        }
        $cliente = $request->Cliente;
        $contrato = $request->Contrato;
        $mes = $request->mes;
        $anio = $request->a_no;
        $usuario = $request->usuario;
        $estatus = $request->estatus;
        Funciones::selecionarBase($cliente);
        $cuentasPapas = Funciones::ObtenValor("SELECT GROUP_CONCAT(p.id) as CuentasPapas FROM Padr_onAguaPotable p WHERE p.id=(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1) and p.Cliente = $cliente", "CuentasPapas");
        if($cliente ==69){
            if($estatus==1){
            $sectoresLecturista = DB::select("SELECT GROUP_CONCAT(CAST(Sector AS INT)) as sectores, Padr_onAguaPotableSectoresLecturistas.Ruta as Ruta FROM Padr_onAguaPotableSector JOIN Padr_onAguaPotableSectoresLecturistas ON ( Padr_onAguaPotableSector.id = Padr_onAguaPotableSectoresLecturistas.idSector ) WHERE idLecturista = $usuario");
            $result = DB::select('SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,Estatus,
                                (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
                                JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
                                WHERE Cliente = '.$cliente.'
                                AND ( Cuenta LIKE "%'.$contrato.'%" or Cuenta = '.intval($contrato).')
                                AND Padr_onAguaPotable.Cliente = '.$cliente.'
                                AND Padr_onAguaPotable.Ruta in('.$sectoresLecturista[0]->Ruta.')
                                AND Estatus in(1,2,10) AND Padr_onAguaPotable.Sector IN ( '.( $sectoresLecturista[0]->sectores == "" ? "0" : $sectoresLecturista[0]->sectores ).')
                                ORDER BY Cuenta ASC');
            }else{
            $sectoresLecturista = DB::select("SELECT GROUP_CONCAT(CAST(Sector AS INT)) as sectores, Padr_onAguaPotableSectoresLecturistas.Ruta as Ruta FROM Padr_onAguaPotableSector JOIN Padr_onAguaPotableSectoresLecturistas ON ( Padr_onAguaPotableSector.id = Padr_onAguaPotableSectoresLecturistas.idSector ) WHERE idLecturista = $usuario");
            $result = DB::select('SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,Estatus,
                                (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
                                JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
                                WHERE Cliente = '.$cliente.'
                                AND ( Cuenta LIKE "%'.$contrato.'%" or Cuenta = '.intval($contrato).')
                                AND Padr_onAguaPotable.Cliente = '.$cliente.'
                                AND Padr_onAguaPotable.Ruta in('.$sectoresLecturista[0]->Ruta.')
                                AND Estatus in(1,2,10) AND Padr_onAguaPotable.Sector IN ( '.( $sectoresLecturista[0]->sectores == "" ? "0" : $sectoresLecturista[0]->sectores ).')
                                ORDER BY Cuenta DESC');
            }

        }else{
            $sectoresLecturista = DB::select("SELECT GROUP_CONCAT(CAST(Sector AS INT)) as sectores FROM Padr_onAguaPotableSector JOIN Padr_onAguaPotableSectoresLecturistas ON ( Padr_onAguaPotableSector.id = Padr_onAguaPotableSectoresLecturistas.idSector ) WHERE idLecturista = $usuario");
        $result = DB::select('SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,Estatus,
                            (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
                            JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
                            WHERE Cliente = '.$cliente.'
                            AND ( Cuenta LIKE "%'.$contrato.'" or Cuenta = '.intval($contrato).')
                            AND Padr_onAguaPotable.Cliente = '.$cliente.'
                            AND Padr_onAguaPotable.id NOT IN ( SELECT Padr_onAgua FROM Padr_onDeAguaLectura WHERE Mes = '.$mes.' AND A_no = '.$anio.' )
                            AND Estatus in(1,2,10) AND Padr_onAguaPotable.Sector IN ( '.( $sectoresLecturista[0]->sectores == "" ? "0" : $sectoresLecturista[0]->sectores ).')');
        }

        if(sizeof($result) > 0){
            return response()->json([
                'Status'=>true,
                'Mensaje'=>$result,
                'Papas'=>$cuentasPapas,
                'Code'=> 200
                ]);
        }else{
            if($sectoresLecturista[0]->sectores == null){
                return response()->json([
                    'Status'=>false,
                    'Mensaje'=>"Favor de verificar sus sectores asignados",
                    'Code'=> 403
                ]);
            }else{
                return response()->json([
                    'Status'=>false,
                    'listaSectores'=>$sectoresLecturista,
                    'Mensaje'=>"Contrato no encontrado, asegurese que el contrato este en su sector",
                    'Code'=> 403
                ]);
            }
        }
    }
    public function buscarPorContribuyente (Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required',
            'Contrato'=>'required'
        ];
        if($request->Cliente ==32){
            $validator = Validator::make($datos, $rules);
            if($validator->fails()){
                return response()->json([
                    'Status'=>false,
                    'Mensaje'=>"Asegurese de que los campos se hayan rellenado de manera correcta",
                    'Code'=>223
                ]);
            }
        }

        $cliente = $request->Cliente;
        $contrato = $request->Contrato;
        $mes = $request->mes;
        $anio = $request->a_no;
        $usuario = $request->usuario;
        $estatus = $request->estatus;
        Funciones::selecionarBase($cliente);
        $cuentasPapas = Funciones::ObtenValor("SELECT GROUP_CONCAT(p.id) as CuentasPapas FROM Padr_onAguaPotable p WHERE p.id=(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1) and p.Cliente = $cliente", "CuentasPapas");
        if($cliente ==69){
            if($estatus==1){
            $sectoresLecturista = DB::select("SELECT GROUP_CONCAT(CAST(Sector AS INT)) as sectores, Padr_onAguaPotableSectoresLecturistas.Ruta as Ruta FROM Padr_onAguaPotableSector JOIN Padr_onAguaPotableSectoresLecturistas ON ( Padr_onAguaPotableSector.id = Padr_onAguaPotableSectoresLecturistas.idSector ) WHERE idLecturista = $usuario");
            $result = DB::select('SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,Estatus,
                                (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente,
                                (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as nombreContribuyente
                                FROM Padr_onAguaPotable
                                JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
                                WHERE Cliente = '.$cliente.'
                                #AND ( Contribuyente LIKE "%'.$contrato.'" or Contribuyente = '.$contrato.')
                                AND Padr_onAguaPotable.Cliente = '.$cliente.'
                                AND Padr_onAguaPotable.Ruta in('.$sectoresLecturista[0]->Ruta.')
                                AND Estatus in(1,2,10) AND Padr_onAguaPotable.Sector IN ( '.( $sectoresLecturista[0]->sectores == "" ? "0" : $sectoresLecturista[0]->sectores ).') HAVING nombreContribuyente  LIKE "%'.$contrato.'%"
                                ORDER BY Contribuyente ASC');
            }else{
                $sectoresLecturista = DB::select("SELECT GROUP_CONCAT(CAST(Sector AS INT)) as sectores, Padr_onAguaPotableSectoresLecturistas.Ruta as Ruta FROM Padr_onAguaPotableSector JOIN Padr_onAguaPotableSectoresLecturistas ON ( Padr_onAguaPotableSector.id = Padr_onAguaPotableSectoresLecturistas.idSector ) WHERE idLecturista = $usuario");
            $result = DB::select('SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,Estatus,
                                (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente,
                                (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as nombreContribuyente
                                FROM Padr_onAguaPotable
                                JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
                                WHERE Cliente = '.$cliente.'
                                #AND ( Contribuyente LIKE "%'.$contrato.'" or Contribuyente = '.$contrato.')
                                AND Padr_onAguaPotable.Cliente = '.$cliente.'
                                AND Padr_onAguaPotable.Ruta in('.$sectoresLecturista[0]->Ruta.')
                                AND Estatus in(1,2,10) AND Padr_onAguaPotable.Sector IN ( '.( $sectoresLecturista[0]->sectores == "" ? "0" : $sectoresLecturista[0]->sectores ).') HAVING nombreContribuyente  LIKE "%'.$contrato.'%"
                                ORDER BY Contribuyente DESC');
            }

        }else{
            $sectoresLecturista = DB::select("SELECT GROUP_CONCAT(CAST(Sector AS INT)) as sectores FROM Padr_onAguaPotableSector JOIN Padr_onAguaPotableSectoresLecturistas ON ( Padr_onAguaPotableSector.id = Padr_onAguaPotableSectoresLecturistas.idSector ) WHERE idLecturista = $usuario");
        $result = DB::select('SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,Estatus,
                            (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
                            JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
                            WHERE Cliente = '.$cliente.'
                            AND ( Contribuyente LIKE "%'.$contrato.'" or Contribuyente = '.intval($contrato).')
                            AND Padr_onAguaPotable.Cliente = '.$cliente.'
                            AND Padr_onAguaPotable.id NOT IN ( SELECT Padr_onAgua FROM Padr_onDeAguaLectura WHERE Mes = '.$mes.' AND A_no = '.$anio.' )
                            AND Estatus in(1,2,10) AND Padr_onAguaPotable.Sector IN ( '.( $sectoresLecturista[0]->sectores == "" ? "0" : $sectoresLecturista[0]->sectores ).')');
        }

        if(sizeof($result) > 0){
            return response()->json([
                'Status'=>true,
                'Mensaje'=>$result,
                'Papas'=>$cuentasPapas,
                'Code'=> 200
                ]);
        }else{
            if($sectoresLecturista[0]->sectores == null){
                return response()->json([
                    'Status'=>false,
                    'Mensaje'=>"Favor de verificar sus sectores asignados",
                    'Code'=> 403
                ]);
            }else{
                return response()->json([
                    'Status'=>false,
                    'listaSectores'=>$sectoresLecturista,
                    'Mensaje'=>"Contrato no encontrado, asegurese que el contrato este en su sector",
                    'Code'=> 403
                ]);
            }
        }
    }
    /** NOTE: consulta para obtener los datos de las tomas en corte */
    public function extraerDatosTomaCorte(Request $request){
        $datos = $request->all();
        $Padron = $request->Padron;
        $Cliente = $request->Cliente;
        $Usuario = $request->Usuario;
        $Rules = [
            'Padron' => "required|string",
            'Cliente' => "required|numeric",
            'Usuario'=> "required|numeric",
        ];
        $validator = Validator::make($datos, $Rules);
        if ( $validator->fails() ) {
            return [
                'Status'=>false,
                'Error'=> $validator->messages() ,
                'code'=> 403
            ];
        }
        Funciones::selecionarBase($Cliente);
        //NOTE: obtenemos los datos de la persona mediante el id del usuario
        /**
         * SELECT CONCAT_WS(' ',Persona.Nombre ,Persona.ApellidoPaterno,Persona.ApellidoMaterno) as PersonaNombre, Cat_alogoPlazaN_omina.Descripci_on as Puesto FROM CelaUsuario
            JOIN PuestoEmpleado on (PuestoEmpleado.Empleado = CelaUsuario.idEmpleado)
            JOIN Persona on ( Persona.id = PuestoEmpleado.Empleado )
            JOIN PlantillaN_ominaCliente on ( PlantillaN_ominaCliente.id = PuestoEmpleado.PlantillaN_ominaCliente )
            JOIN Cat_alogoPlazaN_omina on ( Cat_alogoPlazaN_omina.id = PlantillaN_ominaCliente.Cat_alogoPlazaN_omina )
            WHERE idUsuario = 3872 AND PuestoEmpleado.Estatus = 1 #3800;*/
        $datosPersona = DB::table('CelaUsuario')
                        ->select(
                            "Cat_alogoPlazaN_omina.Descripci_on as Puesto",
                            DB::raw("CONCAT_WS(' ',Persona.Nombre ,Persona.ApellidoPaterno,Persona.ApellidoMaterno) as PersonaNombre"),
                            "Persona.id as Persona",
                            "CelaUsuario.idUsuario as Usuario")
                        ->join('PuestoEmpleado','PuestoEmpleado.Empleado','=','CelaUsuario.idEmpleado')
                        ->join('Persona', 'Persona.id', '=' ,'PuestoEmpleado.Empleado' )
                        ->join('PlantillaN_ominaCliente','PlantillaN_ominaCliente.id','=','PuestoEmpleado.PlantillaN_ominaCliente')
                        ->join('Cat_alogoPlazaN_omina','Cat_alogoPlazaN_omina.id','=','PlantillaN_ominaCliente.Cat_alogoPlazaN_omina')
                        ->where('idUsuario','=',$Usuario)
                        ->where('PuestoEmpleado.Estatus','=',1)->get();

        $datos = DB::table('Padr_onAguaPotable as pa')
                        ->select(
                            "pa.id as Padron",
                            "pa.ContratoVigente",
                            "pa.M_etodoCobro",
                            DB::raw("( CONCAT_WS(' ',pa.Domicilio , 'Manzana:',if ( pa.Manzana is null, 'S/N', pa.Manzana), 'Lote:', if (pa.Lote is null, 'S/N',pa.Lote ) ) ) as Direccion"),
                            "m.Nombre as Municipio",
                            "l.Nombre as Localidad",
                            "toma.Concepto AS Toma",
                            DB::raw("CONCAT_WS( ' ',Contribuyente.Nombres,Contribuyente.ApellidoPaterno,Contribuyente.ApellidoMaterno) as Nombre"),
                            "pa.ContratoVigente",
                            "pa.Medidor",
                            "pa.Estatus",
                            "pa.Cuenta",
                            "Contribuyente.Rfc",
                            "pa.M_etodoCobro",
                            "pa.Ruta",
                            DB::raw("(SELECT count(*) from Padr_onDeAguaLectura pl where pl.Padr_onAgua=pa.id and pl.Status=1) as MesesConsumo"),
                            DB::raw("(SELECT SUM(pl.Tarifa) from Padr_onDeAguaLectura pl where pl.Padr_onAgua=pa.id and pl.Status=1) as ImporteAdeudo"),
                            DB::raw("COALESCE((SELECT COALESCE(ph.SaldoNuevo,0) FROM Padr_onAguaHistoricoAbono ph  WHERE  ph.TipoAbono=1 and ph.idPadron=pa.id ORDER BY ph.id DESC limit 1),0) AS Saldo"),
                            DB::raw("if(COALESCE((SELECT COUNT(ppc.id) FROM Padr_onAguaPotableCorte as ppc INNER JOIN ConceptoAdicionalesCotizaci_on as cac ON(cac.Cotizaci_on=ppc.Cotizaci_on) WHERE ppc.EstatusTupla=1 and  cac.Estatus in(0) and  ppc.Padr_on=pa.id and ppc.Estatus=2),0) >0 ,2,1) as Pagado"))
                            ->join('Contribuyente','Contribuyente.id','=','pa.Contribuyente')
                            ->leftjoin('Municipio as m','m.id','=', 'pa.Municipio')
                            ->leftjoin('Localidad as l', 'l.id','=','pa.Localidad')
                            ->join('TipoTomaAguaPotable as toma','pa.TipoToma','toma.id')
                            ->where('pa.id','=',$Padron)->get();
        if(sizeof($datos) > 0){
            return [
                'Status'=>true,
                'Mensaje'=> $datos,
                'Usuario'=> $datosPersona,
                'code'=> 200
            ];
        }else{
            return [
                'Status'=>true,
                'Mensaje'=> "Sin datos que mostrar",
                'Error'=>$datos,
                'code'=> 404
            ];
        }
    }
    public function RealizarCorteTomaSuinpac(Request $request){
        $datos = $request->all();
        $Cliente = $request->Cliente;
        $Motivo = $request->Motivo;
        $ejercicioFiscal = $request->Ejercicio;
        #$FechaCorte = $request->FechaCorte; //NOTE: se calcula en el API
        $Padron = $request->Padron;
        $Persona = $request->Persona;
        #$FechaTupla = $request->FechaTupla; //NOTE: se calcula en el API
        $Usuario = $request->Usuario;
        $Estatus = $request->Estado;
        $Latitud = $request->Latitud;
        $Longitud = $request->Longitud;
        $Evidencia = $request->Evidencia;
        $FechaTupla = date('Y-m-d H:i:s');
        $FechaCorte = date('Y-m-d');
        $Rules = [
            'Longitud'=>"required|string",
            'Latitud'=>"required|string",
            'Cliente'=> "required|numeric",
            'Motivo'=> "required|string",
            'Padron'=>"required|numeric",
            'Persona'=>"required|numeric",
            'Usuario'=>"required|numeric",
            'Estado'=>"required|numeric",
            'Ejercicio'=>"required|numeric",
            'Evidencia'=>"required",
        ];
        $validator = Validator::make($datos, $Rules);
        if ( $validator->fails() ) {
            return [
                'Status'=>false,
                'Error'=> $validator->messages() ,
                'code'=> 403
            ];
        }

        $url = "https://hectordev.suinpac.dev/Padr_onCortarTomaAplicacion.php";
        $datosPost = array(
                "Estatus" => $Estatus,
                "Motivo" => $Motivo,
                "FechaCorte" => $FechaCorte,
                "Padr_on" => $Padron,
                "Persona" => $Persona,
                "FechaTupla" => $FechaTupla,
                "Usuario" => $Usuario,
                "Cliente" => $Cliente,
                'Ejercicio' => $ejercicioFiscal
            );
            $options = array(
                'http' => array(
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($datosPost),
                )
            );

        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $jsonRespuesta = explode("\n",$result);
        $respuesta = $jsonRespuesta[sizeof($jsonRespuesta)-1];
        $respuestaObjeto = json_decode($respuesta);
        if($respuestaObjeto->Estatus){
            Funciones::selecionarBase($Cliente);
            //NOTE: insertamos el corte en la tabla de locaciones
            $insertarUbicacion = DB::table('Padr_onAguaPotableCorteUbicacion')
                            ->insert([
                                'id'=>null,
                                'idAguaPotableCorte'=>$respuestaObjeto->Corte,
                                'idUsuario'=>$Usuario,
                                'Padron'=>$Padron,
                                'Longitud'=>$Longitud,
                                'Latitud'=>$Latitud,
                                'Evidencia'=>'',
                                'FechaTuplaCorte'=>$FechaTupla
                            ]);
            if($insertarUbicacion){
                $idsRepo =  $this->SubirImagenV2($Evidencia,$respuestaObjeto->Corte,$Cliente,$Usuario,"FotoHistorialCorte");
                $actualizarDatos = DB:: table('Padr_onAguaPotableCorteUbicacion')
                                ->where('idAguaPotableCorte', $respuestaObjeto->Corte)
                                ->update(['Evidencia'=>$idsRepo]);
                return [
                    'Status'=> true,
                    'Mensaje'=>"Corte realizado",
                    'Code'=>200
                ];
            }else{
                return [
                    'Status'=> true,
                    'Mensaje'=>"Carga de evidencia",
                    'Code'=>206
                ];
            }
            //NOTE: subimos las fotos de las inspecciones
            //SubirImagenV2($imagenes,$idRegistro,$Cliente,$usuario)
        }else{
            return [
                'Status'=> true,
                'Mensaje'=>$result,
                'Code'=>400
            ];
        }
    }
    public function ObtenerListaCortes(Request $request){
        $datos = $request->all();
        $Cliente = $request->Cliente;
        $Usuario = $request->Usuario;
        $Ejercicio = $request->Ejercicio;
        $Mes = $request->Mes;
        $Rules = [
            "Cliente"=> "required|numeric",
            "Usuario"=> "required|numeric",
            "Ejercicio"=> "required|numeric",
            "Mes"=> "required|string"
        ];
        $validator = Validator::make($datos, $Rules);
        if ( $validator->fails() ) {
            return [
                'Status'=>false,
                'Error'=> $validator->messages() ,
                'code'=> 403
            ];
        }
        Funciones::selecionarBase($Cliente);
        $listaCortes = DB::table("Padr_onAguaPotableCorteUbicacion")
                            ->select("Motivo", "ContratoVigente", "FechaCorte", "Padr_onAguaPotable.Estatus",
                            DB::raw("(SELECT if( CONCAT_WS(' ',Nombres,ApellidoPaterno,ApellidoMaterno) = '', NombreComercial ,CONCAT_WS(' ',Nombres,ApellidoPaterno,ApellidoMaterno)) as Nombre FROM Contribuyente WHERE Contribuyente.id = Padr_onAguaPotable.Contribuyente ) as Nombre"))
                            ->join("Padr_onAguaPotableCorte","Padr_onAguaPotableCorte.id","=","Padr_onAguaPotableCorteUbicacion.idAguaPotableCorte")
                            ->join("Padr_onAguaPotable","Padr_onAguaPotableCorteUbicacion.Padron","=","Padr_onAguaPotable.id")
                            ->where('FechaCorte','LIKE',$Ejercicio."-".$Mes."%")
                            ->where("idUsuario","=",$Usuario)
                            ->get();
                            //WHERE FechaCorte LIKE "2022-02%"
        if(sizeof($listaCortes) > 0){
            return [
                'Status'=>true,
                'Code'=>200,
                'Mensaje'=>$listaCortes
            ];
        }else{
            return [
                'Status'=>false,
                'Code'=>404,
                'Mensaje'=>"Sin datos"
            ];
        }



    }
    public function buscarPorContratoSinFiltro(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required',
            'Contrato'=>'required'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de que los campos se hayan rellenado de manera correcta",
                'Code'=>223
            ]);
        }
        $cliente = $request->Cliente;
        $contrato = $request->Contrato;
        Funciones::selecionarBase($cliente);
        $result = DB::select('SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,Estatus,
                            (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
                            JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
                            WHERE Cliente = '.$cliente.'
                            AND ( ContratoVigente LIKE "%'.$contrato.'" or ContratoVigente = '.intval($contrato).')
                            AND Padr_onAguaPotable.Cliente = '.$cliente);
        return response()->json([
            'Status'=>true,
            'Mensaje'=>$result,
            'Code'=> 200
            ]);
    }
    public function buscarPorMedidorSinFiltro(Request  $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required',
            'Contrato'=>'required'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de que los campos se hayan rellenado de manera correcta",
                'Code'=>223
            ]);
        }
        $cliente = $request->Cliente;
        $contrato = $request->Contrato;
        Funciones::selecionarBase($cliente);
        $result = DB::select('SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,Estatus,
                            (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
                            JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
                            WHERE Cliente = '.$cliente.'
                            AND (Medidor LIKE "%'.$contrato.'" or Medidor = '.intval($contrato).')
                            AND Padr_onAguaPotable.Cliente = '.$cliente);

        return response()->json([
            'Status'=>true,
            'Mensaje'=>$result,
            'Code'=> 200
            ]);
    }
    public function crearReporteV2(Request $request){
        $datos = $request->all();
        $Rules = [
            'Padron' => "required|string",
            'Cliente' => "required|numeric",
            'Calle'=>"required|string",
            'Colonia'=>"required|string",
            'Numero'=>"required|string",
            'Descripcion'=>"required|string",
            'Latitud'=>"required|string",
            'Longitud'=> "required|string",
            'Usuario'=>'required|string'
        ];
        $validator = Validator::make($datos, $Rules);
        if ( $validator->fails() ) {
            return [
                'Status'=>false,
                'Error'=> $validator->messages() ,
                'code'=> 403
            ];
        }
        $Cliente = $request->Cliente;
        $Padron = $request->Padron;
        $Calle = $request->Calle;
        $Colonia = $request->Colonia;
        $Numero = $request->Numero;
        $Descripcion = $request->Descripcion;
        $Latitud = $request->Latitud;
        $Longitud = $request->Longitud;
        $Usuario = $request->Usuario;
        $Fotos = $request->Fotos;
        //NOTE:  insertamos los datos del reporte
        Funciones::selecionarBase($Cliente);
        $idReporte = DB::table('Padr_onAguaPotableReportes')
                    ->insertGetId([
                        'id'=>null,
                        'Usuario'=>$Usuario,
                        'Cliente'=>$Cliente,
                        'Colonia'=>$Colonia,
                        'Calle'=>$Calle,
                        'Numero'=>$Numero,
                        'Descripci_on'=>$Descripcion,
                        'FallaAdministrativa'=>0,
                        'Padr_on'=> $Padron,
                        'Latitud'=>$Latitud,
                        'Longitud'=>$Longitud,
                        'Fotos'=>'',
                        "Estatus"=>1, /** 0 = cancelado  ,1 = reportado, 2 = espera , 3 = resuelto*/
                    ]);
        $fotos = $this->SubirImagenV2($Fotos,$idReporte,$Cliente,$Usuario,"Padr_onAguaPotableReportes");
        //NOTE: insertamos las imagenes
        $actualizado = DB::table('Padr_onAguaPotableReportes')->where('id', $idReporte)->update([
            'Fotos'=> $fotos
        ]);
        if( $actualizado ){
            if($fotos == "" ){
                return [
                    'Status'=>false,
                    'Code'=>423,
                    'Mensaje'=>"Error al subir las imagenes",
                ];
            }else{
                return [
                    'Status'=>true,
                    'Code'=>200,
                    'Mensaje'=>$idReporte,
                ];
            }
        }else{
            return [
                'Status'=>false,
                'Code'=>403,
                'Mensaje'=>'No se inserto el registro'
            ];
        }
    }
    public function ConfiguracionCoutaFija(Request $request){
        $datos = $request->all();
        $Rules = [
            'Cliente'=>"required|string"
        ];
        $validator = Validator::make($datos, $Rules);
        if ( $validator->fails() ) {
            return [
                'Status'=>false,
                'Error'=> $validator->messages() ,
                'code'=> 403
            ];
        }
        $Cliente = $request->Cliente;
        //INDEV: obtenemos la configuracion de cuotafija SELECT * FROM ClienteDatos WHERE Indice = "Activar Cuota Fija";
        $configuracion = DB::table('ClienteDatos')->select('Valor')->where('Indice','=','ActivarCuotaFija')->where('Cliente','=',$Cliente)->get();
        if($configuracion){
            return [
                'Status'=> true,
                'Configuracion'=>$configuracion[0]->Valor,
                'Code'=>200
            ];
        }else{
            return [
                'Status'=> false,
                'Configuracion'=>$configuracion,
                'Code'=>403
            ];
        }
    }

    /*NOTE: TESTING: funciones para calcular el consumo  cuota fijo*/
    public function calcularConsumo(Request $request){
        $datos = $request->all();
        $Cliente = $request->Cliente;
        $Consumo = $request->Consumo;
        $ejercicioFiscal = $request->Anio;
        $padronRequest = $request->padron;
        $mes = $request->mes;
        $anomalia = $request->anomalia;
        $usuario = $request->idUsuario;
        $fechaRegistro = date('y-m-d');
        $fotos = $request->Fotos;
        $latitud = $request->Latidude;
        $longitud = $request->Longitude;
        $tipoCoordenada = $request->tipoCoordenada;
        $LecturaActual = $request->LecturaActual;
        $LecturaAnterior = $request->LecturaAnterior;
        $consumoFinal = $request->Consumo;
        //NOTE: Fechas para  verificar las lecturar que se pasan del anio
        $anioActual = date('Y');
        $mesActual = date('m');
        Funciones::selecionarBase($Cliente);

        //NOTE: Verificamos los datos del request
        $rules = [
            'Cliente'=>"required|string",
            'Consumo'=>"required|numeric",
            'Anio'=>"required|numeric",
            'padron'=>"required|string",
            'mes'=>"required|numeric",
            #'anomalia'=>"required|numeric",
            'idUsuario'=>"required|string",
            'Latidude'=>"required|string",
            'Longitude'=>"required|string",
            'tipoCoordenada'=>"required|numeric"
        ];
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return [
                'Status'=>false,
                'Error'=> $validator->messages() ,
                'code'=> 403
            ];
        }
        //NOTE: verificamos que sea cuota fija
        $padron = DB::table('Padr_onAguaPotable')->select('Consumo','M_etodoCobro','TipoToma','Estatus')->where('id','=',$padronRequest)->get();
        //NOTE: buscamos la lectura anterior del contrato
        $CambiarEstado = DB::table('Padr_onAguaPotable')->where('id','=',$padronRequest)->update(['M_etodoCobro'=> 2]);
        if($CambiarEstado){
            $consumoReal = $Consumo;
            //Obtenemmos la tarifa del consumo
            $obtenTarifa = $this->ObtenConsumo($padron[0]->TipoToma, $Consumo ,$Cliente , $ejercicioFiscal);//Mensaje 223 campos incorrectos
            $idLectura = DB::table('Padr_onDeAguaLectura')->insertGetId([
                'Padr_onAgua'=>$padronRequest,
                'LecturaAnterior'=>$LecturaAnterior,
                'LecturaActual'=> $LecturaActual,
                'Consumo'=>$consumoReal,
                'Mes'=>$mes,
                'A_no'=>$ejercicioFiscal,
                'Observaci_on'=>$anomalia,
                'FechaLectura'=>$fechaRegistro,
                'TipoToma'=>$padron[0]->TipoToma,
                'EstadoToma' => $padron[0]->Estatus,
                'Tarifa' => $obtenTarifa]);
                $cadenaFotos = $this->SubirImagenV2($fotos,$idLectura,$Cliente,$usuario);
                //INDEV: insertamo en padr_onRlectura
                $fechaRegistroCela =date('y-m-d H:i:s');
                $guadarusaurioLectura = DB::table('Padr_onAguaPotableRLecturas')->insertGetId([
                    'idLectura'=> $idLectura,
                    'idUsuario'=> $usuario,
                    'Padr_onAgua'=> $padronRequest,
                    'longitud' => $longitud,
                    'latitud' => $longitud,
                    'fotos'=>$cadenaFotos,
                    'tipoCoordenada' => 1
                ]);
                //INDEV: insertamos a celaRepositorio
                $guardarUsuario = DB::table('CelaAccesos')->insert([
                    'FechaDeAcceso'=> $fechaRegistroCela,
                    'idUsuario' => $usuario,
                    'Tabla' => 'Padr_onDeAguaLectura',
                    'IdTabla' => $padronRequest,
                    'Acci_on' => 2
                ]);
                return [
                    'Status'=>true,
                    'Mensaje'=> "Guardado",
                    'Code'=> 200
                ];


        }else{
            $RevertirCambio = DB::table('Padr_onAguaPotable')->where('id','=',$padronRequest)->update(['M_etodoCobro'=> 1]);
            if( $RevertirCambio ){
                return [
                    'Status'=>false,
                    'Mensaje'=> "Error al guardar lectura CF01",
                    'Code'=> 403
                ];
            }
        }

    }
    //NOTE: TESTING: Vercion 2 de subir imagne s
    public function SubirImagenV2($imagenes,$idRegistro,$Cliente,$usuario,$nombreTabla = 'Padr_onAguaPotableRLecturas'){
        $arregloNombre = array();
        $arregloSize = array();
        $arregloRuta = array();
        $datosRepo = "";
        $ruta = date("Y/m/d");
        $fechaHora = date("Y-m-d H:i:s");
        foreach ($imagenes as $arregloFoto){
            #return $arregloFoto;
            $image_64 = $arregloFoto; //your base64 encoded data
            $extension = explode('/', explode(':', substr($image_64, 0, strpos($image_64, ';')))[1])[1];   // .jpg .png .pdf
            $replace = substr($image_64, 0, strpos($image_64, ',')+1);
            // find substring fro replace here eg: data:image/png;base64,
            $image = str_replace($replace, '', $image_64);
            $image = str_replace(' ', '+', $image);
            $imageName = 'FotoAgua'.uniqid().'.'.$extension;
            #return base64_decode($image);
            $size_in_bytes = (int) (strlen(rtrim($image_64, '=')) * 3 / 4);
            $size_in_kb    = $size_in_bytes / 1024;
            $size_in_mb    = $size_in_kb / 1024;
            #return $size_in_bytes;
            Storage::disk('repositorio')->put($Cliente."/".$ruta."/".$imageName, base64_decode($image));
            #return $size;
            #return "repositorio/FotosAgua".$imageName;
            array_push($arregloNombre,$imageName);
            array_push($arregloSize,$size_in_bytes);
            array_push($arregloRuta,"repositorio/".$Cliente."/".$ruta."/".$imageName);
        }
        Funciones::selecionarBase($Cliente);
        //insertamos las rutas en celaRepositorio
        $contador = 0;
        //NOTE: se inserta en las evidencias del reporte
        foreach($arregloRuta as $ruta){
            $datos = DB::table("CelaRepositorio")->insert([
                'Tabla'=>$nombreTabla,
                'idTabla'=>$idRegistro,
                'Ruta'=> $ruta,
                'Descripci_on'=>'Fotos de la aplicacion Agua',
                'idUsuario'=>$usuario,
                'FechaDeCreaci_on'=>$fechaHora,
                'Estado'=>1,
                'Reciente'=>1,
                'NombreOriginal'=>$arregloNombre[$contador],
                'Size'=>$arregloSize[$contador]
            ]);
            $ultimoCela=Funciones::ObtenValor("SELECT idRepositorio FROM CelaRepositorio ORDER BY idRepositorio DESC","idRepositorio");
            $datosRepo .= $ultimoCela.",";
            $contador++;
        }
        return $datosRepo;
    }

    /***********************************************
     * METODOS DE PRUEBA PARA LAS ASISNTENCIAS DEL CONGRESO
     ***********************************************/
    public function obtenerListaCientes(Request $request){
        //Obtenemos la lista de los clientes
        //Funciones::selecionarBase("43");
        $result = DB::table('Cliente')
                            ->select('id','Descripci_on as Descripcion' )
                            ->where('Estatus','=',1)->get();
        if(sizeof($result)){
            return $result;
        }else{
            $arrayTemp = array();
            $data = [
                "id"=> -1,
                "Descripcion"=> "null"
            ];
            array_push($arrayTemp,$data);
            return $arrayTemp;
        }
    }
    ///METODA PARA LA NUEVA VERSION
    //FIXME: Cambiar al id de la persona
    public function obtenerEmpleadosOLD(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=> 'required|string',
            'idChecador'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Datos incompleto",
                'Code'=>223
            ]);
        }
        $cliente = $request->Cliente;
        $device = $request->uid;
        $Checador = $request->idChecador;
        Funciones::selecionarBase($cliente);
        //if($device == "TEST1"){ //El codigo se obtendar desde la tabla controlador de los checadores
            //$result = DB::table("Persona")->select("*")->where("id_checador")->get(); consulta de prueba
            $table = 'EmpleadoFotografia';
            $result = DB::table('Persona')->select("PuestoEmpleado.id as idEmpleado",DB::raw('CONCAT(Persona.Nombre," ",Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) AS Nombre'),'Persona.Nfc_uid','Persona.idChecadorApp as idChecador','Persona.N_umeroDeEmpleado as NoEmpleado','PuestoEmpleado.NombreDelCargo As Cargo','AreasAdministrativas.Descripci_on as AreaAdministrativa','Cat_alogoPlazaN_omina.Descripci_on AS NombrePlaza','TipoTrabajador.Descripci_on as Trabajador' ,DB::raw('CONCAT("https://suinpac.com/",CelaRepositorio.Ruta) as Foto'))
                ->join('PuestoEmpleado','Persona.id','=','PuestoEmpleado.Empleado')
                ->join('TipoTrabajador','TipoTrabajador.id','=','PuestoEmpleado.TipoTrabajador')
                ->join('PlantillaN_ominaCliente','PlantillaN_ominaCliente.id','=','PuestoEmpleado.PlantillaN_ominaCliente')
                ->join('Cat_alogoPlazaN_omina','Cat_alogoPlazaN_omina.id','=','PlantillaN_ominaCliente.Cat_alogoPlazaN_omina')
                ->join('AreasAdministrativas','AreasAdministrativas.id','=','PlantillaN_ominaCliente.AreaAdministrativa')
                ->leftjoin('ChecadorEmpleadoFotografia','ChecadorEmpleadoFotografia.idEmpleado','=','PuestoEmpleado.id')
                ->leftjoin('CelaRepositorio','CelaRepositorio.idRepositorio','=',"ChecadorEmpleadoFotografia.idRepositorio")
                ->where('Persona.Cliente','=',$cliente)->where('PuestoEmpleado.Estatus','=','1')
                ->where('Persona.idChecadorApp','=',$Checador)
                ->get();
            //Regresar respuesta
            if(sizeof($result)>0){
                return $result;
            }else{
                $arrayTemp = array();
                $data = [
                    "idEmpleado" => "-1",
                    "Nombre" => "null",
                    "Nfc_uid"=> "-1",
                    "idChecador"=> "-1",
                    "NoEmpleado"=> "-1",
                    "Cargo"=> "null",
                    "AreaAdministrativa"=> "null",
                    "NombrePlaza"=> "null",
                    "Trabajador"=> "null",
                    "Foto"=> "null"
                ];
                array_push($arrayTemp,$data);
                return $arrayTemp;
            }
    }
    //FIXED: Verificando los cambios del is de la persona
    public function obtenerEmpleados(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=> 'required|string',
            'idChecador'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Datos incompleto",
                'Code'=>223
            ]);
        }
        $cliente = $request->Cliente;
        $device = $request->uid;
        $Checador = $request->idChecador;
        Funciones::selecionarBase($cliente);
        //if($device == "TEST1"){ //El codigo se obtendar desde la tabla controlador de los checadores
            //$result = DB::table("Persona")->select("*")->where("id_checador")->get(); consulta de prueba
            $result = DB::table('Persona')->select("Persona.id as idEmpleado",DB::raw('CONCAT(Persona.Nombre," ",Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) AS Nombre'),'Persona.Nfc_uid','Persona.idChecadorApp as idChecador','Persona.N_umeroDeEmpleado as NoEmpleado','PuestoEmpleado.NombreDelCargo As Cargo','AreasAdministrativas.Descripci_on as AreaAdministrativa','Cat_alogoPlazaN_omina.Descripci_on AS NombrePlaza','TipoTrabajador.Descripci_on as Trabajador' ,DB::raw('CONCAT("https://suinpac.com/",CelaRepositorio.Ruta) as Foto'))
                ->join('PuestoEmpleado','Persona.id','=','PuestoEmpleado.Empleado')
                ->join('TipoTrabajador','TipoTrabajador.id','=','PuestoEmpleado.TipoTrabajador')
                ->join('PlantillaN_ominaCliente','PlantillaN_ominaCliente.id','=','PuestoEmpleado.PlantillaN_ominaCliente')
                ->join('Cat_alogoPlazaN_omina','Cat_alogoPlazaN_omina.id','=','PlantillaN_ominaCliente.Cat_alogoPlazaN_omina')
                ->join('AreasAdministrativas','AreasAdministrativas.id','=','PlantillaN_ominaCliente.AreaAdministrativa')
                ->leftjoin('ChecadorEmpleadoFotografia','ChecadorEmpleadoFotografia.idEmpleado','=','PuestoEmpleado.id')
                ->leftjoin('CelaRepositorio','CelaRepositorio.idRepositorio','=',"ChecadorEmpleadoFotografia.idRepositorio")
                ->where('Persona.Cliente','=',$cliente)
                ->where('PuestoEmpleado.Estatus','=','1')
                #->where('Persona.Estatus','=','1')
                ->where('Persona.idChecadorApp','=',$Checador)
                ->get();
            //Regresar respuesta
            if(sizeof($result)>0){
                return $result;
            }else{
                $arrayTemp = array();
                $data = [
                    "idEmpleado" => "-1",
                    "Nombre" => "null",
                    "Nfc_uid"=> "-1",
                    "idChecador"=> "-1",
                    "NoEmpleado"=> "-1",
                    "Cargo"=> "null",
                    "AreaAdministrativa"=> "null",
                    "NombrePlaza"=> "null",
                    "Trabajador"=> "null",
                    "Foto"=> "null"
                ];
                array_push($arrayTemp,$data);
                return $arrayTemp;
            }
    }
    //FIXME: Cambiar al id de la persona
    public function obtenerEmpleadosGeneralOLD(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=> 'required|string',
            'idChecador'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Datos incompleto",
                'Code'=>223
            ]);
        }
        $cliente = $request->Cliente;
        $device = $request->uid;
        $Checador = $request->idChecador;
        Funciones::selecionarBase($cliente);
        $table = 'EmpleadoFotografia';
        $result = DB::table('Persona')->select("PuestoEmpleado.id as idEmpleado",DB::raw('CONCAT(Persona.Nombre," ",Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) AS Nombre'),'Persona.Nfc_uid','Persona.idChecadorApp as idChecador','Persona.N_umeroDeEmpleado as NoEmpleado','PuestoEmpleado.NombreDelCargo As Cargo','AreasAdministrativas.Descripci_on as AreaAdministrativa','Cat_alogoPlazaN_omina.Descripci_on AS NombrePlaza','TipoTrabajador.Descripci_on as Trabajador' ,DB::raw('CONCAT("https://suinpac.com/",CelaRepositorio.Ruta) as Foto'))
            ->join('PuestoEmpleado','Persona.id','=','PuestoEmpleado.Empleado')
            ->join('TipoTrabajador','TipoTrabajador.id','=','PuestoEmpleado.TipoTrabajador')
            ->join('PlantillaN_ominaCliente','PlantillaN_ominaCliente.id','=','PuestoEmpleado.PlantillaN_ominaCliente')
            ->join('Cat_alogoPlazaN_omina','Cat_alogoPlazaN_omina.id','=','PlantillaN_ominaCliente.Cat_alogoPlazaN_omina')
            ->join('AreasAdministrativas','AreasAdministrativas.id','=','PlantillaN_ominaCliente.AreaAdministrativa')
            ->leftjoin('ChecadorEmpleadoFotografia','ChecadorEmpleadoFotografia.idEmpleado','=','PuestoEmpleado.id')
            ->leftjoin('CelaRepositorio','CelaRepositorio.idRepositorio','=',"ChecadorEmpleadoFotografia.idRepositorio")
            ->where('Persona.Cliente','=',$cliente)->where('PuestoEmpleado.Estatus','=','1')
            ->get();
        //Regresar respuesta
        if(sizeof($result)>0){
            //return $result;
            return sizeof($result);
        }else{
            $arrayTemp = array();
            $data = [
                "idEmpleado" => "-1",
                "Nombre" => "null",
                "Nfc_uid"=> "-1",
                "idChecador"=> "-1",
                "NoEmpleado"=> "-1",
                "Cargo"=> "null",
                "AreaAdministrativa"=> "null",
                "NombrePlaza"=> "null",
                "Trabajador"=> "null",
                "Foto"=> "null"
            ];
            array_push($arrayTemp,$data);
            return $arrayTemp;
        }
    }
    //FIXED: Se camibio por los el id de la persona
    public function obtenerEmpleadosGeneral(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=> 'required|string',
            'idChecador'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Datos incompleto",
                'Code'=>223
            ]);
        }
        $cliente = $request->Cliente;
        $device = $request->uid;
        $Checador = $request->idChecador;
        Funciones::selecionarBase($cliente);
        $table = 'EmpleadoFotografia';
        $result = DB::table('Persona')->select("Persona.id as idEmpleado",DB::raw('CONCAT(Persona.Nombre," ",Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) AS Nombre'),'Persona.Nfc_uid','Persona.idChecadorApp as idChecador','Persona.N_umeroDeEmpleado as NoEmpleado','PuestoEmpleado.NombreDelCargo As Cargo','AreasAdministrativas.Descripci_on as AreaAdministrativa','Cat_alogoPlazaN_omina.Descripci_on AS NombrePlaza','TipoTrabajador.Descripci_on as Trabajador' ,DB::raw('CONCAT("https://suinpac.com/",CelaRepositorio.Ruta) as Foto'))
            ->join('PuestoEmpleado','Persona.id','=','PuestoEmpleado.Empleado')
            ->join('TipoTrabajador','TipoTrabajador.id','=','PuestoEmpleado.TipoTrabajador')
            ->join('PlantillaN_ominaCliente','PlantillaN_ominaCliente.id','=','PuestoEmpleado.PlantillaN_ominaCliente')
            ->join('Cat_alogoPlazaN_omina','Cat_alogoPlazaN_omina.id','=','PlantillaN_ominaCliente.Cat_alogoPlazaN_omina')
            ->join('AreasAdministrativas','AreasAdministrativas.id','=','PlantillaN_ominaCliente.AreaAdministrativa')
            ->leftjoin('ChecadorEmpleadoFotografia','ChecadorEmpleadoFotografia.idEmpleado','=','PuestoEmpleado.id')
            ->leftjoin('CelaRepositorio','CelaRepositorio.idRepositorio','=',"ChecadorEmpleadoFotografia.idRepositorio")
            ->where('Persona.Cliente','=',$cliente)
            ->where('Persona.Estatus','=',"1")
            ->where('PuestoEmpleado.Estatus','=','1')
            ->get();
        //Regresar respuesta
        if(sizeof($result)>0){
            return $result;
            //return sizeof($result);
        }else{
            $arrayTemp = array();
            $data = [
                "idEmpleado" => "-1",
                "Nombre" => "null",
                "Nfc_uid"=> "-1",
                "idChecador"=> "-1",
                "NoEmpleado"=> "-1",
                "Cargo"=> "null",
                "AreaAdministrativa"=> "null",
                "NombrePlaza"=> "null",
                "Trabajador"=> "null",
                "Foto"=> "null"
            ];
            array_push($arrayTemp,$data);
            return $arrayTemp;
        }
    }
    //FIXME: Cambiar al id de la persona
    public function horarioEmpleadoOLD(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required|string',
            'Empleado'=>'required|int '
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Empleado no valido",
                'Code'=>223
            ]);
        }
        $Cliente = $request->Cliente;
        $Empleado = $request->Empleado;
        Funciones::selecionarBase($Cliente);
        $result = DB::table('Asistencia_GrupoPersona')
                    ->select('Asistencia_GrupoPersona.id as Grupo',
                        'Asistencia_EmpleadoHorarioDetalle.id as GrupoDetalle',
                        'Asistencia_GrupoPersona.PuestoEmpleado',
                        'Asistencia_Grupo.Nombre as GrupoNombre',
                        'Asistencia_Grupo.Jornada',
                        'Asistencia_EmpleadoHorario.Dia',
                        'Asistencia_EmpleadoHorarioDetalle.HoraEntrada',
                        'Asistencia_EmpleadoHorarioDetalle.HoraSalida',
                        'Asistencia_EmpleadoHorarioDetalle.Retardo',
                        'Asistencia_EmpleadoHorarioDetalle.Tolerancia',
                        'Asistencia_EmpleadoHorarioDetalle.Estatus',
                        'Asistencia_Configuraci_on.LimiteFaltas',
                        'Asistencia_Configuraci_on.Retardos as LimiteRetardos'
                        )
                    ->join('Asistencia_Grupo','Asistencia_GrupoPersona.Grupo','Asistencia_Grupo.id')
                    ->join('Asistencia_EmpleadoHorario','Asistencia_EmpleadoHorario.idAsistencia_GrupoPersona','Asistencia_GrupoPersona.id')
                    ->join('Asistencia_EmpleadoHorarioDetalle','Asistencia_EmpleadoHorario.id','Asistencia_EmpleadoHorarioDetalle.idAsistencia_EmpleadoHorario')
                    ->join('PuestoEmpleado','PuestoEmpleado.id','Asistencia_GrupoPersona.PuestoEmpleado')
                    ->join('Persona','Persona.id','PuestoEmpleado.Empleado')
                    ->join('Asistencia_Configuraci_on','Asistencia_Configuraci_on.id','=','Asistencia_Grupo.Configuraci_on')
                    ->where('Asistencia_GrupoPersona.PuestoEmpleado','=',$Empleado)
                    ->where('PuestoEmpleado.id','=',$Empleado)
                    ->where('Persona.Cliente','=',$Cliente)
                    ->where('Asistencia_GrupoPersona.Estatus','=',"1")
                    ->where('Persona.Estatus','=','1')->get();
        if(sizeof($result)>0){
            return $result;
        }else{
            $arrayTemp = array();
            $data = [
                "Grupo" => "-1",
                "GrupoDetalle"=> "-1",
                "PuestoEmpleado"=> "-1",
                "GrupoNombre"=> "null",
                "Jornada"=> "1",
                "Dia"=> -1,
                "HoraEntrada"=> "null",
                "HoraSalida"=> "null",
                "Retardo"=> -1,
                "Tolerancia"=> -1,
                "Estatus"=> "-1"
            ];
            array_push($arrayTemp,$data);
            return $arrayTemp;
        }

    }
    //FIXED: Cambiando el id de la persona
    public function horarioEmpleado(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required|string',
            'Empleado'=>'required|int '
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Empleado no valido",
                'Code'=>223
            ]);
        }
        $Cliente = $request->Cliente;
        $Empleado = $request->Empleado;
        Funciones::selecionarBase($Cliente);
        $result = DB::table('Asistencia_GrupoPersona')
                    ->select('Asistencia_GrupoPersona.id as Grupo',
                        'Asistencia_EmpleadoHorarioDetalle.id as GrupoDetalle',
                        'Asistencia_GrupoPersona.Empleado as PuestoEmpleado',
                        'Asistencia_Grupo.Nombre as GrupoNombre',
                        'Asistencia_Grupo.Jornada',
                        'Asistencia_EmpleadoHorario.Dia',
                        'Asistencia_EmpleadoHorarioDetalle.HoraEntrada',
                        'Asistencia_EmpleadoHorarioDetalle.HoraSalida',
                        'Asistencia_EmpleadoHorarioDetalle.Retardo',
                        'Asistencia_EmpleadoHorarioDetalle.Tolerancia',
                        'Asistencia_EmpleadoHorarioDetalle.Estatus',
                        'Asistencia_Configuraci_on.LimiteFaltas',
                        'Asistencia_Configuraci_on.Retardos as LimiteRetardos',
                        'Asistencia_Grupo.AplicaAsistencia')
                    ->join('Asistencia_Grupo','Asistencia_GrupoPersona.Grupo','Asistencia_Grupo.id')
                    ->join('Asistencia_EmpleadoHorario','Asistencia_EmpleadoHorario.idAsistencia_GrupoPersona','Asistencia_GrupoPersona.id')
                    ->join('Asistencia_EmpleadoHorarioDetalle','Asistencia_EmpleadoHorario.id','Asistencia_EmpleadoHorarioDetalle.idAsistencia_EmpleadoHorario')
                    ->join('Persona','Persona.id','Asistencia_GrupoPersona.Empleado')
                    ->join('Asistencia_Configuraci_on','Asistencia_Configuraci_on.id','=','Asistencia_Grupo.Configuraci_on')
                    ->where('Asistencia_GrupoPersona.Empleado','=',$Empleado)
                    ->where('Persona.id','=',$Empleado)
                    ->where('Persona.Cliente','=',$Cliente)
                    ->where('Asistencia_GrupoPersona.Estatus','=',"1")
                    ->where('Persona.Estatus','=','1')->get();
        if(sizeof($result)>0){
            return $result;
        }else{
            $arrayTemp = array();
            $data = [
                "Grupo" => "-1",
                "GrupoDetalle"=> "-1",
                "PuestoEmpleado"=> "-1",
                "GrupoNombre"=> "null",
                "Jornada"=> "1",
                "Dia"=> -1,
                "HoraEntrada"=> "null",
                "HoraSalida"=> "null",
                "Retardo"=> -1,
                "Tolerancia"=> -1,
                "Estatus"=> "-1",
                "LimiteFaltas"=>0,
                "LimiteRetardos"=>0,
                "AplicaAsistencia"=>0
            ];
            array_push($arrayTemp,$data);
            return $arrayTemp;
        }

    }

    
public function pruebaCombustibleQRValidar(Request $request){
    $qr=$request->qrData;
    $cliente=$request->cliente;
    Funciones::selecionarBase(14);
    $result = DB::table('CuponesDescuentoCombustible') ->select('id', 'Estado', 'Importe') ->where('Codigo','=',$qr)->get();
    if(sizeof($result)>0){
        if($result[0]->Estado==1){
            return response()->json([
                'Mensaje'=>"CUPÓN NO DISPONIBLE",
                'Valor'=>$qr,
                'Importe'=>"IMPORTE: ".$result[0]->Importe,
            ]);
        }else{
            return response()->json([
                'Mensaje'=>"CUPÓN DISPONIBLE",
                'Valor'=>$qr,
                'Importe'=>"IMPORTE: ".$result[0]->Importe,
            ]);
        }
    }else{
        return response()->json([
            'Mensaje'=>"CÓDIGO NO ENCONTRADO",
            'Valor'=>$qr,
            'Importe'=>"",
        ]);
    }
    
}

public function pruebaCombustibleQR(Request $request)
{
    // Verificar si se enviaron las imágenes en base64
    $qr = $request->qrData;
    Funciones::selecionarBase(14);
    $result = DB::table('CuponesDescuentoCombustible')
        ->select('id', 'Estado', 'Importe')
        ->where('Codigo', '=', $qr)
        ->get();

    if (sizeof($result) > 0) {
        if ($result[0]->Estado == 1) {
            return response()->json([ 'ERROR: ' => 'El código ya fue utilizado - Importe: '.$result[0]->Importe ]);
        } else {
            $ActualizarEstado = DB::table('CuponesDescuentoCombustible')
                ->where('Codigo', '=', $qr)
                ->update(['Estado' => 1]);

            if ($ActualizarEstado) {
                if ($request->has('images') && is_array($request->images)) {
                    $imagenes = $request->images;
                    $Cliente = 14; // Cliente
                    $arregloNombre = array();
                    $arregloSize = array();
                    $arregloRuta = array();
                    $datosRepo = "";
                    $fechaHora = date("Y-m-d H:i:s");

                    $ruta = date("Y/m/d");  // Carpeta por fecha

                    foreach ($imagenes as $arregloFoto) {
                        $image_64 = $arregloFoto; // Base64 de la imagen enviada
                        $extension = explode('/', explode(':', substr($image_64, 0, strpos($image_64, ';')))[1])[1]; // Extrae la extensión

                        // Elimina la parte de encabezado del base64 (data:image/png;base64,...)
                        $replace = substr($image_64, 0, strpos($image_64, ',') + 1);
                        $image = str_replace($replace, '', $image_64);
                        $image = str_replace(' ', '+', $image); // Reemplazar espacios por '+'

                        // Convertir la imagen base64 a JPG
                        $imageData = base64_decode($image);
                        
                        // Crear una imagen desde los datos binarios
                        $imageResource = imagecreatefromstring($imageData);
                        if (!$imageResource) {
                            throw new \Exception('No se pudo crear la imagen desde los datos base64');
                        }

                        // Crear un nombre único para la imagen
                        $imageName = 'CuponCombustible' . uniqid() . '.jpg';

                        // Comprimir la imagen y convertirla a formato JPG
                        ob_start(); // Iniciar el buffer de salida
                        imagejpeg($imageResource, null, 85); // Guardar la imagen en JPG con calidad 85
                        $compressedImage = ob_get_contents(); // Obtener la imagen comprimida
                        ob_end_clean(); // Limpiar el buffer

                        // Guardar la imagen comprimida en el repositorio
                        Storage::disk('repositorio')->put("CuponesCombustible/{$Cliente}/{$ruta}/{$imageName}", $compressedImage);

                        // Calcular el tamaño de la imagen
                        $size_in_bytes = strlen($compressedImage);
                        $size_in_kb = $size_in_bytes / 1024;  // Tamaño en KB
                        $size_in_mb = $size_in_kb / 1024;    // Tamaño en MB

                        // Almacenar la información de la imagen
                        array_push($arregloNombre, $imageName);
                        array_push($arregloSize, $size_in_bytes);
                        array_push($arregloRuta, "repositorio/CuponesCombustible/{$Cliente}/{$ruta}/{$imageName}");

                        // Liberar la memoria de la imagen
                        imagedestroy($imageResource);
                    }

                    $contador = 0;
                    // Insertar las rutas de las imágenes en la base de datos
                    foreach ($arregloRuta as $ruta) {
                        $datos = DB::table("CelaRepositorio")->insert([
                            'Tabla' => 'CuponesDescuentoCombustible',
                            'idTabla' => $result[0]->id,
                            'Ruta' => $ruta,
                            'Descripci_on' => 'Cupones de Descuento de Combustible',
                            'idUsuario' => 4867,
                            'FechaDeCreaci_on' => $fechaHora,
                            'Estado' => 1,
                            'Reciente' => 1,
                            'NombreOriginal' => $arregloNombre[$contador],
                            'Size' => $arregloSize[$contador]
                        ]);
                        $ultimoCela = Funciones::ObtenValor("SELECT idRepositorio FROM CelaRepositorio ORDER BY idRepositorio DESC", "idRepositorio");
                        $datosRepo .= $ultimoCela . ",";
                        $contador++;
                    }

                    return response()->json([ 'FINALIZADO: ' => 'Cupón vínculado con éxito - Importe: '. $result[0]->Importe ]);
                } else {
                    return response()->json([ 'ERROR: ' => 'No se pudieron subir las imagenes' ]);
                }
            } else {
                return response()->json([ 'ERROR: ' => 'No se pudo activar cupón' ]);
            }
        }
    } else {
        return response()->json([ 'ERROR: ' => 'Cupón no encontrado' ]);
    }
}

public function ValidarRFCAIFA(Request $request){
    // Obtener el token del encabezado 'Authorization'
    $token = $request->header('Authorization');

    // Verificar si el token está presente
    if (!$token) {
        return response()->json([
            'error' => 'Token de autenticación no proporcionado.'
        ], 401); // Código de error 401 Unauthorized
    }

    // Verificar si el token tiene el prefijo 'Bearer'
    if (preg_match('/Bearer\s(\S+)/', $token, $matches)) {
        $token = $matches[1]; // Token sin el prefijo 'Bearer'
    } else {
        return response()->json([
            'error' => 'Token de autenticación no válido.'
        ], 401); // Si no es un Bearer Token válido, devolvemos un 401
    }

    // Validación del token (puedes hacer esto de diferentes maneras, dependiendo de la implementación de tu token)
    // Si es un JWT, podrías validarlo utilizando una librería como firebase/php-jwt (usando una clave secreta para verificar su firma)
    // O si es un token simple, puedes validarlo directamente.

    // Por ejemplo, si el token es un string conocido (simplificado):
    if ($token !== "tu_token_valido_aqui") {
        return response()->json([
            'error' => 'Token de autenticación inválido.'
        ], 401);
    }

    $datos = $request->all();
    $RFC = $request->RFC;
    $sqlConsulta="SELECT c.id as id, df.NombreORaz_onSocial as nombre, df.C_odigoPostal as codigo, df.R_egimenFiscal as regimen, c.CorreoElectr_onico as correo, c.Tel_efonoCelular as telefono FROM aifa_grp01.Contribuyente c INNER JOIN aifa_grp01.DatosFiscales df ON(df.id=c.DatosFiscales) WHERE c.RFC='".$RFC."'";
    if($respuesta=DB::select($sqlConsulta)){
        return response()->json([
            'message' => 'Envío exitoso.',
            'idTicket' => $respuesta[0]->id,
            'nombre' => $respuesta[0]->nombre,
            'codigoP' => $respuesta[0]->codigo,
            'regimen' => $respuesta[0]->regimen,
            'correo' => $respuesta[0]->correo,
            'telefono' => $respuesta[0]->telefono,
        ]);
    }else{
        return response()->json([
            'message' => 'Envío fallido.',
        ]);
    }

}
public function EnviarDatosTicket(Request $request){
    // Obtener el token del encabezado 'Authorization'
    $token = $request->header('Authorization');

    // Verificar si el token está presente
    if (!$token) {
        return response()->json([
            'error' => 'Token de autenticación no proporcionado.'
        ], 401); // Código de error 401 Unauthorized
    }

    // Verificar si el token tiene el prefijo 'Bearer'
    if (preg_match('/Bearer\s(\S+)/', $token, $matches)) {
        $token = $matches[1]; // Token sin el prefijo 'Bearer'
    } else {
        return response()->json([
            'error' => 'Token de autenticación no válido.'
        ], 401); // Si no es un Bearer Token válido, devolvemos un 401
    }

    // Validación del token (puedes hacer esto de diferentes maneras, dependiendo de la implementación de tu token)
    // Si es un JWT, podrías validarlo utilizando una librería como firebase/php-jwt (usando una clave secreta para verificar su firma)
    // O si es un token simple, puedes validarlo directamente.

    // Por ejemplo, si el token es un string conocido (simplificado):
    if ($token !== "tu_token_valido_aqui") {
        return response()->json([
            'error' => 'Token de autenticación inválido.'
        ], 401);
    }

    // Si el token es válido, puedes proceder con el proceso de facturación
    $datos = $request->all(); // Obtener los datos de la solicitud
    $idRecibo = $request->IdRecibo;
    $fechaFactura = $request->FechaFactura;
    $Importe = $request->Importe;
    $Matricula = $request->Matricula;
    $Descripci_onBreve = $request->DescripcionBreve;
    $conexionAIFA = conectarBDSuinpac();
    $fechaTupla = date('Y-m-d H:i:s');

    $validarTicket="SELECT * FROM TicketEstacionamiento where idTicket=".$idRecibo;
    if ($resultado2 = mysqli_query($conexionAIFA, $validarTicket)) {
        if (mysqli_num_rows($resultado2) > 0) {
            $fila = mysqli_fetch_assoc($resultado2);
            return response()->json([
                'message' => 'Recibo'.$idRecibo.' duplicado',
            ]);
        }else{
            $insertar_ticket = "INSERT INTO TicketEstacionamiento (id, idTicket, Fecha, NumPlacas, ImportePagado, FechaTupla, TipoPago) 
            VALUES (NULL, '$idRecibo', '$fechaFactura', '$Matricula', '$Importe', '$fechaTupla', '$Descripci_onBreve')";

            if (mysqli_query($conexionAIFA, $insertar_ticket)) {
                return response()->json([
                    'message' => 'Recibo'.$idRecibo.' insertado correctamente',
                ]);
            }
        }
    }

    
    /*$ConsultaInserta = sprintf("INSERT INTO aifa_grp01.TicketEstacionamiento (  id , idTicket , Fecha , ImportePagado , FechaTupla)
    VALUES (  %s, %s, %s, %s, %s)",
    Funciones::GetSQLValueString(NULL, "int"), Funciones::GetSQLValueString($idRecibo, "int"), Funciones::GetSQLValueString($fechaFactura, "varchar"), Funciones::GetSQLValueString($Importe, "decimal"), Funciones::GetSQLValueString(date('Y-m-d H:i:s'), "date"));
    // Aquí puedes agregar tu lógica de facturación usando $datos, por ejemplo:
    //$sqlConsulta = "SELECT ..."; // Consulta a la base de datos
    //$respuesta = DB::select($sqlConsulta); 
    if(DB::insert($ConsultaInserta)){
        $IdRegistroAsignaci_onPresupuestal = DB::getPdo()->lastInsertId();
        return response()->json([
            'id' => 22,
            'message' => 'Envío exitoso.',
            'idTicket' => $IdRegistroAsignaci_onPresupuestal,
        ]);
    }*/
    
    }

    public function validarRegimenFiscalAIFA(Request $request){
        $token = $request->header('Authorization');
        // Verificar si el token está presente
        if (!$token) {
            return response()->json([
                'error' => 'Token de autenticación no proporcionado.'
            ], 401); // Código de error 401 Unauthorized
        }

        // Verificar si el token tiene el prefijo 'Bearer'
        if (preg_match('/Bearer\s(\S+)/', $token, $matches)) {
            $token = $matches[1]; // Token sin el prefijo 'Bearer'
        } else {
            return response()->json([
                'error' => 'Token de autenticación no válido.'
            ], 401); // Si no es un Bearer Token válido, devolvemos un 401
        }

        // Por ejemplo, si el token es un string conocido (simplificado):
        if ($token !== "tu_token_valido_aqui") {
            return response()->json([
                'error' => 'Token de autenticación inválido.'
            ], 401);
        }

        $datos = $request->all(); // Obtener los datos de la solicitud
        $regimen = $request->regimen;

        //Obtenemos la conexion de suinpac para hacer las consultas correspondientes
        $conexionAIFA = conectarBDSuinpac();

        $validarRegimen="SELECT Clave as Clave FROM RegimenFiscal where id=".$regimen;
        if ($resultado2 = mysqli_query($conexionAIFA, $validarRegimen)) {
            if (mysqli_num_rows($resultado2) > 0) {
                $fila = mysqli_fetch_assoc($resultado2);
                $claveRegimen=$fila['Clave'];
                $validarRegimenUso="SELECT GROUP_CONCAT(ClaveUso) as clave FROM RegimenFiscal_UsoCFDI where ClaveRegimen=".$claveRegimen;
                if ($resultado3 = mysqli_query($conexionAIFA, $validarRegimenUso)) {
                    if (mysqli_num_rows($resultado3) > 0) {
                        $fila2 = mysqli_fetch_assoc($resultado3);
                        $claveuso=$fila2['clave'];
                        return response()->json([
                            'message' => $claveuso,
                            'Estatus' =>1,
                        ]);
                    }
                }
            }
        } 

    }

    public function FacturarPatio(Request $request){
        // Obtener el token del encabezado 'Authorization'
        $token = $request->header('Authorization');
    
        // Verificar si el token está presente
        if (!$token) {
            return response()->json([
                'error' => 'Token de autenticación no proporcionado.'
            ], 401); // Código de error 401 Unauthorized
        }
    
        // Verificar si el token tiene el prefijo 'Bearer'
        if (preg_match('/Bearer\s(\S+)/', $token, $matches)) {
            $token = $matches[1]; // Token sin el prefijo 'Bearer'
        } else {
            return response()->json([
                'error' => 'Token de autenticación no válido.'
            ], 401); // Si no es un Bearer Token válido, devolvemos un 401
        }
    
        // Validación del token (puedes hacer esto de diferentes maneras, dependiendo de la implementación de tu token)
        // Si es un JWT, podrías validarlo utilizando una librería como firebase/php-jwt (usando una clave secreta para verificar su firma)
        // O si es un token simple, puedes validarlo directamente.
    
        // Por ejemplo, si el token es un string conocido (simplificado):
        if ($token !== "tu_token_valido_aqui") {
            return response()->json([
                'error' => 'Token de autenticación inválido.'
            ], 401);
        }
    
        // Si el token es válido, puedes proceder con el proceso de facturación
        $datos = $request->all(); // Obtener los datos de la solicitud
        $nombre = $request->nombre;
        $RFC = strtoupper($request->RFC);
        $CP = $request->CP;
        $regimen = $request->Regimen;
        $usoCFDI = $request->usoCDFI;
        $telefono = $request->telefono;
        $correo = $request->correo;
        $ticket = $request->ticket;
        $id = $request->id;
        $longitud = strlen($RFC);
    
        /*$url = "https://aifa.suinpac.com/FacturarEstacionamiento.php";
        $datosPost = array(
                "nombre" => $nombre,
                "rfc" => $RFC, 
                "cp" => $CP,
                "regimen" => $regimen, 
                "usoCFDI" => $usoCFDI,
                "telefono" => $telefono,
                "correo" => $correo,
                "ticket" => $ticket,
            );
            $options = array(
                'http' => array(
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($datosPost),
                )
            );
    
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $jsonRespuesta = explode("\n",$result);
        $respuesta = $jsonRespuesta[sizeof($jsonRespuesta)-1];
        $respuestaObjeto = json_decode($respuesta);
        if($respuestaObjeto){
            return response()->json([
                'message'=>"Facturación exitosa",
            ]);
        }*/
    
        $conexionAIFA = conectarBDSuinpac();
        
        $validarTicket="SELECT * FROM TicketPatioRegulador where id=".$id;
        if ($resultado2 = mysqli_query($conexionAIFA, $validarTicket)) {
            if (mysqli_num_rows($resultado2) > 0) {
                $fila = mysqli_fetch_assoc($resultado2);
                $validarCotizaci_on="SELECT * FROM Cotizaci_on where id=".$fila['idCotizacion'];
                if ($resultado3 = mysqli_query($conexionAIFA, $validarCotizaci_on)) {
                    if (mysqli_num_rows($resultado3) > 0) {
                        $fila2 = mysqli_fetch_assoc($resultado3); 
                        $validarContribuyente="SELECT * FROM Contribuyente where RFC='".$RFC."'";
                        if ($resultado4 = mysqli_query($conexionAIFA, $validarContribuyente)) {
                            if (mysqli_num_rows($resultado4) > 0) {
                                $fila3 = mysqli_fetch_assoc($resultado4); 
                                $actualizar_cotizaci_on = "UPDATE Cotizaci_on 
                                SET 
                                    Contribuyente = '" . $fila3['id'] . "'
                                WHERE id = '" . $fila2['id'] . "';";
                                if (mysqli_query($conexionAIFA, $actualizar_cotizaci_on)) {
                                    $actualizar_ticket = "UPDATE  TicketPatioRegulador 
                                    SET 
                                        Correo = '$correo' ,
                                        Tel_efono = '$telefono',
                                        RFC = '$RFC',
                                        Nombre = '$nombre',
                                        CodigoPostal = '$CP',
                                        R_egimenFiscal = '$regimen',
                                        UsoCFDI = '$usoCFDI'
                                    WHERE id = '" . $fila['id'] . "';";
    
                                if (mysqli_query($conexionAIFA, $actualizar_ticket)) {
                                    $url = "https://aifa.suinpac.com/XMLATimbrarprocesarEstacionamiento40CAL.php";
                                    $datosPost = array(
                                            "Boleto" => $fila['id'],
                                        );
                                        $options = array(
                                            'http' => array(
                                                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                                                'method'  => 'POST',
                                                'content' => http_build_query($datosPost),
                                            )
                                        );
    
                                    $context  = stream_context_create($options);
                                    $result = file_get_contents($url, false, $context);
                                    $jsonRespuesta = explode("\n",$result);
                                    $respuesta = $jsonRespuesta[sizeof($jsonRespuesta)-1];
                                    $respuestaObjeto = json_decode($respuesta);
                                    return response()->json([
                                        'message' => $respuesta,
                                        'Estatus' =>1,
                                    ]);
                                }
                                }
                            }else{
                                if($longitud>=13){
                                    $PersonalidadJuridica=1;
                                }else{
                                    if($longitud<=12){
                                        $PersonalidadJuridica=2;
                                    }
                                }
                                $insertarDatosFiscales = "INSERT INTO DatosFiscales (id, RFC, NombreORaz_onSocial, C_odigoPostal, R_egimenFiscal, CorreoElectr_onico) 
                                VALUES (NULL, '$RFC', '$nombre', '$CP', '$regimen', '$correo')";
                                if (mysqli_query($conexionAIFA, $insertarDatosFiscales)) {
                                    $ultimo_id = mysqli_insert_id($conexionAIFA);
                                    $insertar_detalle = "INSERT INTO Contribuyente (id, RFC, Cliente, DatosFiscales, EnLinea, PersonalidadJur_idica) 
                                    VALUES (NULL, '$RFC', '1', '$ultimo_id','1', '$PersonalidadJuridica')";
                                     if (mysqli_query($conexionAIFA, $insertar_detalle)) {
                                        $idContribuyente = mysqli_insert_id($conexionAIFA);
                                        $actualizar_cotizaci_on = "UPDATE Cotizaci_on 
                                        SET 
                                            Contribuyente = '" . $idContribuyente . "'
                                        WHERE id = '" . $fila2['id'] . "';";   
                                        if (mysqli_query($conexionAIFA, $actualizar_cotizaci_on)) {
                                            $actualizar_ticket = "UPDATE TicketPatioRegulador 
                                            SET 
                                                Correo = '$correo' ,
                                                Tel_efono = '$telefono',
                                                RFC = '$RFC',
                                                Nombre = '$nombre',
                                                CodigoPostal = '$CP',
                                                R_egimenFiscal = '$regimen',
                                                UsoCFDI = '$usoCFDI'
                                            WHERE id = '" . $fila['id'] . "';";
            
                                        if (mysqli_query($conexionAIFA, $actualizar_ticket)) {
                                            $url = "https://aifa.suinpac.com/XMLATimbrarprocesarEstacionamiento40CAL.php";
                                            $datosPost = array(
                                                    "Boleto" => $fila['id'],
                                                );
                                                $options = array(
                                                    'http' => array(
                                                        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                                                        'method'  => 'POST',
                                                        'content' => http_build_query($datosPost),
                                                    )
                                                );
    
                                            $context  = stream_context_create($options);
                                            $result = file_get_contents($url, false, $context);
                                            $jsonRespuesta = explode("\n",$result);
                                            $respuesta = $jsonRespuesta[sizeof($jsonRespuesta)-1];
                                            $respuestaObjeto = json_decode($respuesta);
                                            return response()->json([
                                                'message' => $respuesta,
                                                'Estatus' =>1,
                                            ]);
                                        }
                                            
                                        }
                                     }
                                }
    
                            }
                        }else{
                            $insertarDatosFiscales = "INSERT INTO DatosFiscales (id, RFC, NombreORaz_onSocial, C_odigoPostal, R_egimenFiscal, CorreoElectr_onico) 
                            VALUES (NULL, '$RFC', '$nombre', '$CP', '$regimen', '$correo')";
                            if (mysqli_query($conexionAIFA, $insertarDatosFiscales)) {
                                $ultimo_id = mysqli_insert_id($conexionAIFA);
                                $insertar_detalle = "INSERT INTO Contribuyente (id, RFC, Cliente, DatosFiscales, EnLinea, PersonalidadJur_idica) 
                                VALUES (NULL, '$RFC', '1', '$ultimo_id', '1', '$PersonalidadJuridica')";
                                 if (mysqli_query($conexionAIFA, $insertar_detalle)) {
                                    $idContribuyente = mysqli_insert_id($conexionAIFA);
                                    $actualizar_cotizaci_on = "UPDATE Cotizaci_on 
                                    SET 
                                    Contribuyente = '" . $idContribuyente . "'
                                    WHERE id = '" . $fila2['id'] . "';";   
                                    if (mysqli_query($conexionAIFA, $actualizar_cotizaci_on)) {
                                            $actualizar_ticket = "UPDATE TicketPatioRegulador 
                                            SET 
                                                Correo = '$correo' ,
                                                Tel_efono = '$telefono',
                                                RFC = '$RFC',
                                                Nombre = '$nombre',
                                                CodigoPostal = '$CP',
                                                R_egimenFiscal = '$regimen',
                                                UsoCFDI = '$usoCFDI'
                                            WHERE id = '" . $fila['id'] . "';";
    
                                            if (mysqli_query($conexionAIFA, $actualizar_ticket)) {
                                                $url = "https://aifa.suinpac.com/XMLATimbrarprocesarEstacionamiento40CAL.php";
                                                $datosPost = array(
                                                        "Boleto" => $fila['id'],
                                                    );
                                                    $options = array(
                                                        'http' => array(
                                                            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                                                            'method'  => 'POST',
                                                            'content' => http_build_query($datosPost),
                                                        )
                                                    );
    
                                                $context  = stream_context_create($options);
                                                $result = file_get_contents($url, false, $context);
                                                $jsonRespuesta = explode("\n",$result);
                                                $respuesta = $jsonRespuesta[sizeof($jsonRespuesta)-1];
                                                $respuestaObjeto = json_decode($respuesta);
                                                return response()->json([
                                                    'message' => $respuesta,
                                                    'Estatus' =>1,
                                                ]);
                                            }
                                            
                                    }
                                 }
                            }
                                     
                                
                        }
                    }else{
                        return response()->json([
                            'message' => 'Recibo '.$ticket.' no se encontró cotización',
                            'Estatus' =>1,
                        ]);
                    }
                }else{
                    return response()->json([
                        'message' => 'Recibo '.$ticket.' no se encontró cotización',
                        'Estatus' =>1,
                    ]);
                }
                
                
            }
        }
    }



    public function FacturarEstacionamiento(Request $request){
    // Obtener el token del encabezado 'Authorization'
    $token = $request->header('Authorization');

    // Verificar si el token está presente
    if (!$token) {
        return response()->json([
            'error' => 'Token de autenticación no proporcionado.'
        ], 401); // Código de error 401 Unauthorized
    }

    // Verificar si el token tiene el prefijo 'Bearer'
    if (preg_match('/Bearer\s(\S+)/', $token, $matches)) {
        $token = $matches[1]; // Token sin el prefijo 'Bearer'
    } else {
        return response()->json([
            'error' => 'Token de autenticación no válido.'
        ], 401); // Si no es un Bearer Token válido, devolvemos un 401
    }

    // Validación del token (puedes hacer esto de diferentes maneras, dependiendo de la implementación de tu token)
    // Si es un JWT, podrías validarlo utilizando una librería como firebase/php-jwt (usando una clave secreta para verificar su firma)
    // O si es un token simple, puedes validarlo directamente.

    // Por ejemplo, si el token es un string conocido (simplificado):
    if ($token !== "tu_token_valido_aqui") {
        return response()->json([
            'error' => 'Token de autenticación inválido.'
        ], 401);
    }

    // Si el token es válido, puedes proceder con el proceso de facturación
    $datos = $request->all(); // Obtener los datos de la solicitud
    $nombre = $request->nombre;
    $RFC = strtoupper($request->RFC);
    $CP = $request->CP;
    $regimen = $request->Regimen;
    $usoCFDI = $request->usoCDFI;
    $telefono = $request->telefono;
    $correo = $request->correo;
    $ticket = $request->ticket;
    $id = $request->id;
    $longitud = strlen($RFC);

    /*$url = "https://aifa.suinpac.com/FacturarEstacionamiento.php";
    $datosPost = array(
            "nombre" => $nombre,
            "rfc" => $RFC, 
            "cp" => $CP,
            "regimen" => $regimen, 
            "usoCFDI" => $usoCFDI,
            "telefono" => $telefono,
            "correo" => $correo,
            "ticket" => $ticket,
        );
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($datosPost),
            )
        );

    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $jsonRespuesta = explode("\n",$result);
    $respuesta = $jsonRespuesta[sizeof($jsonRespuesta)-1];
    $respuestaObjeto = json_decode($respuesta);
    if($respuestaObjeto){
        return response()->json([
            'message'=>"Facturación exitosa",
        ]);
    }*/

    $conexionAIFA = conectarBDSuinpac();
    $nombre   = mysqli_real_escape_string($conexionAIFA, $nombre);
    $validarTicket="SELECT * FROM TicketEstacionamiento where id=".$id;
    if ($resultado2 = mysqli_query($conexionAIFA, $validarTicket)) {
        if (mysqli_num_rows($resultado2) > 0) {
            $fila = mysqli_fetch_assoc($resultado2);
            $validarCotizaci_on="SELECT * FROM Cotizaci_on where id=".$fila['idCotizacion'];
            if ($resultado3 = mysqli_query($conexionAIFA, $validarCotizaci_on)) {
                if (mysqli_num_rows($resultado3) > 0) {
                    $fila2 = mysqli_fetch_assoc($resultado3); 
                    $validarContribuyente="SELECT * FROM Contribuyente where RFC='".$RFC."'";
                    if ($resultado4 = mysqli_query($conexionAIFA, $validarContribuyente)) {
                        if (mysqli_num_rows($resultado4) > 0) {
                            $fila3 = mysqli_fetch_assoc($resultado4); 
                            $actualizar_cotizaci_on = "UPDATE Cotizaci_on 
                            SET 
                                Contribuyente = '" . $fila3['id'] . "'
                            WHERE id = '" . $fila2['id'] . "';";
                            if (mysqli_query($conexionAIFA, $actualizar_cotizaci_on)) {
                                $actualizar_ticket = "UPDATE TicketEstacionamiento 
                                SET 
                                    Correo = '$correo' ,
                                    Tel_efono = '$telefono',
                                    RFC = '$RFC',
                                    Nombre = '$nombre',
                                    CodigoPostal = '$CP',
                                    R_egimenFiscal = '$regimen',
                                    UsoCFDI = '$usoCFDI'
                                WHERE id = '" . $fila['id'] . "';";

                            if (mysqli_query($conexionAIFA, $actualizar_ticket)) {
                                $url = "https://aifa.suinpac.com/XMLATimbrarprocesarEstacionamiento40.php";
                                $datosPost = array(
                                        "Boleto" => $fila['id'],
                                    );
                                    $options = array(
                                        'http' => array(
                                            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                                            'method'  => 'POST',
                                            'content' => http_build_query($datosPost),
                                        )
                                    );

                                $context  = stream_context_create($options);
                                $result = file_get_contents($url, false, $context);
                                $jsonRespuesta = explode("\n",$result);
                                $respuesta = $jsonRespuesta[sizeof($jsonRespuesta)-1];
                                $respuestaObjeto = json_decode($respuesta);
                                return response()->json([
                                    'message' => $respuesta,
                                    'Estatus' =>1,
                                ]);
                            }
                            }
                        }else{
                            if($longitud>=13){
                                $PersonalidadJuridica=1;
                            }else{
                                if($longitud<=12){
                                    $PersonalidadJuridica=2;
                                }
                            }
                            $insertarDatosFiscales = "INSERT INTO DatosFiscales (id, RFC, NombreORaz_onSocial, C_odigoPostal, R_egimenFiscal, CorreoElectr_onico) 
                            VALUES (NULL, '$RFC', '$nombre', '$CP', '$regimen', '$correo')";
                            if (mysqli_query($conexionAIFA, $insertarDatosFiscales)) {
                                $ultimo_id = mysqli_insert_id($conexionAIFA);
                                $insertar_detalle = "INSERT INTO Contribuyente (id, RFC, Cliente, DatosFiscales, EnLinea, PersonalidadJur_idica) 
                                VALUES (NULL, '$RFC', '1', '$ultimo_id','1', '$PersonalidadJuridica')";
                                 if (mysqli_query($conexionAIFA, $insertar_detalle)) {
                                    $idContribuyente = mysqli_insert_id($conexionAIFA);
                                    $actualizar_cotizaci_on = "UPDATE Cotizaci_on 
                                    SET 
                                        Contribuyente = '" . $idContribuyente . "'
                                    WHERE id = '" . $fila2['id'] . "';";   
                                    if (mysqli_query($conexionAIFA, $actualizar_cotizaci_on)) {
                                        $actualizar_ticket = "UPDATE TicketEstacionamiento 
                                        SET 
                                            Correo = '$correo' ,
                                            Tel_efono = '$telefono',
                                            RFC = '$RFC',
                                            Nombre = '$nombre',
                                            CodigoPostal = '$CP',
                                            R_egimenFiscal = '$regimen',
                                            UsoCFDI = '$usoCFDI'
                                        WHERE id = '" . $fila['id'] . "';";
        
                                    if (mysqli_query($conexionAIFA, $actualizar_ticket)) {
                                        $url = "https://aifa.suinpac.com/XMLATimbrarprocesarEstacionamiento40.php";
                                        $datosPost = array(
                                                "Boleto" => $fila['id'],
                                            );
                                            $options = array(
                                                'http' => array(
                                                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                                                    'method'  => 'POST',
                                                    'content' => http_build_query($datosPost),
                                                )
                                            );

                                        $context  = stream_context_create($options);
                                        $result = file_get_contents($url, false, $context);
                                        $jsonRespuesta = explode("\n",$result);
                                        $respuesta = $jsonRespuesta[sizeof($jsonRespuesta)-1];
                                        $respuestaObjeto = json_decode($respuesta);
                                        return response()->json([
                                            'message' => $respuesta,
                                            'Estatus' =>1,
                                        ]);
                                    }
                                        
                                    }
                                 }
                            }

                        }
                    }else{
                        $insertarDatosFiscales = "INSERT INTO DatosFiscales (id, RFC, NombreORaz_onSocial, C_odigoPostal, R_egimenFiscal, CorreoElectr_onico) 
                        VALUES (NULL, '$RFC', '$nombre', '$CP', '$regimen', '$correo')";
                        if (mysqli_query($conexionAIFA, $insertarDatosFiscales)) {
                            $ultimo_id = mysqli_insert_id($conexionAIFA);
                            $insertar_detalle = "INSERT INTO Contribuyente (id, RFC, Cliente, DatosFiscales, EnLinea, PersonalidadJur_idica) 
                            VALUES (NULL, '$RFC', '1', '$ultimo_id', '1', '$PersonalidadJuridica')";
                             if (mysqli_query($conexionAIFA, $insertar_detalle)) {
                                $idContribuyente = mysqli_insert_id($conexionAIFA);
                                $actualizar_cotizaci_on = "UPDATE Cotizaci_on 
                                SET 
                                Contribuyente = '" . $idContribuyente . "'
                                WHERE id = '" . $fila2['id'] . "';";   
                                if (mysqli_query($conexionAIFA, $actualizar_cotizaci_on)) {
                                        $actualizar_ticket = "UPDATE TicketEstacionamiento 
                                        SET 
                                            Correo = '$correo' ,
                                            Tel_efono = '$telefono',
                                            RFC = '$RFC',
                                            Nombre = '$nombre',
                                            CodigoPostal = '$CP',
                                            R_egimenFiscal = '$regimen',
                                            UsoCFDI = '$usoCFDI'
                                        WHERE id = '" . $fila['id'] . "';";

                                        if (mysqli_query($conexionAIFA, $actualizar_ticket)) {
                                            $url = "https://aifa.suinpac.com/XMLATimbrarprocesarEstacionamiento40.php";
                                            $datosPost = array(
                                                    "Boleto" => $fila['id'],
                                                );
                                                $options = array(
                                                    'http' => array(
                                                        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                                                        'method'  => 'POST',
                                                        'content' => http_build_query($datosPost),
                                                    )
                                                );

                                            $context  = stream_context_create($options);
                                            $result = file_get_contents($url, false, $context);
                                            $jsonRespuesta = explode("\n",$result);
                                            $respuesta = $jsonRespuesta[sizeof($jsonRespuesta)-1];
                                            $respuestaObjeto = json_decode($respuesta);
                                            return response()->json([
                                                'message' => $respuesta,
                                                'Estatus' =>1,
                                            ]);
                                        }
                                        
                                }
                             }
                        }
                                 
                            
                    }
                }else{
                    return response()->json([
                        'message' => 'Recibo '.$ticket.' no se encontró cotización',
                        'Estatus' =>1,
                    ]);
                }
            }else{
                return response()->json([
                    'message' => 'Recibo '.$ticket.' no se encontró cotización',
                    'Estatus' =>1,
                ]);
            }
            
            
        }
    }
}

public function FacturarEstacionamientoV2(Request $request){
    // Obtener el token del encabezado 'Authorization'
    $token = $request->header('Authorization');

    // Verificar si el token está presente
    if (!$token) {
        return response()->json([
            'error' => 'Token de autenticación no proporcionado.'
        ], 401); // Código de error 401 Unauthorized
    }

    // Verificar si el token tiene el prefijo 'Bearer'
    if (preg_match('/Bearer\s(\S+)/', $token, $matches)) {
        $token = $matches[1]; // Token sin el prefijo 'Bearer'
    } else {
        return response()->json([
            'error' => 'Token de autenticación no válido.'
        ], 401); // Si no es un Bearer Token válido, devolvemos un 401
    }

    // Validación del token (puedes hacer esto de diferentes maneras, dependiendo de la implementación de tu token)
    // Si es un JWT, podrías validarlo utilizando una librería como firebase/php-jwt (usando una clave secreta para verificar su firma)
    // O si es un token simple, puedes validarlo directamente.

    // Por ejemplo, si el token es un string conocido (simplificado):
    if ($token !== "tu_token_valido_aqui") {
        return response()->json([
            'error' => 'Token de autenticación inválido.'
        ], 401);
    }

    // Si el token es válido, puedes proceder con el proceso de facturación
    $datos = $request->all(); // Obtener los datos de la solicitud
    $nombre = $request->nombre;
    $RFC = strtoupper($request->RFC);
    $CP = $request->CP;
    $regimen = $request->Regimen;
    $usoCFDI = $request->usoCDFI;
    $telefono = $request->telefono;
    $correo = $request->correo;
    $ticket = $request->ticket;
    $id = $request->id;
    $longitud = strlen($RFC);
    $TipoTarjeta=$request->TipoTarjeta;


    if($TipoTarjeta=="1"){
        $FormaPago=5;
    }else{
        $FormaPago=14;
    }

    /*$url = "https://aifa.suinpac.com/FacturarEstacionamiento.php";
    $datosPost = array(
            "nombre" => $nombre,
            "rfc" => $RFC, 
            "cp" => $CP,
            "regimen" => $regimen, 
            "usoCFDI" => $usoCFDI,
            "telefono" => $telefono,
            "correo" => $correo,
            "ticket" => $ticket,
        );
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($datosPost),
            )
        );

    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $jsonRespuesta = explode("\n",$result);
    $respuesta = $jsonRespuesta[sizeof($jsonRespuesta)-1];
    $respuestaObjeto = json_decode($respuesta);
    if($respuestaObjeto){
        return response()->json([
            'message'=>"Facturación exitosa",
        ]);
    }*/

    $conexionAIFA = conectarBDSuinpac();
    $nombre   = mysqli_real_escape_string($conexionAIFA, $nombre);
    $validarTicket="SELECT * FROM TicketEstacionamiento where id=".$id;
    if ($resultado2 = mysqli_query($conexionAIFA, $validarTicket)) {
        if (mysqli_num_rows($resultado2) > 0) {
            $fila = mysqli_fetch_assoc($resultado2);
            $validarCotizaci_on="SELECT * FROM Cotizaci_on where id=".$fila['idCotizacion'];
            if ($resultado3 = mysqli_query($conexionAIFA, $validarCotizaci_on)) {
                if (mysqli_num_rows($resultado3) > 0) {
                    $fila2 = mysqli_fetch_assoc($resultado3); 
                    $validarContribuyente="SELECT * FROM Contribuyente where RFC='".$RFC."'";
                    if ($resultado4 = mysqli_query($conexionAIFA, $validarContribuyente)) {
                        if (mysqli_num_rows($resultado4) > 0) {
                            $fila3 = mysqli_fetch_assoc($resultado4); 
                            $actualizar_cotizaci_on = "UPDATE Cotizaci_on 
                                    SET 
                                        Contribuyente = '" . $fila3['id'] . "',
                                        FormaPagoId=".$FormaPago.",
                                        FormaPago=(SELECT  tt.Clave from TipoCobroCaja  tt WHERE tt.id in(".$FormaPago."))
                                    WHERE id = '" . $fila2['id'] . "'";  
                            if (mysqli_query($conexionAIFA, $actualizar_cotizaci_on)) {
                                $actualizar_ticket = "UPDATE TicketEstacionamiento 
                                        SET 
                                            Correo = '$correo' ,
                                            Tel_efono = '$telefono',
                                            RFC = '$RFC',
                                            Nombre = '$nombre',
                                            CodigoPostal = '$CP',
                                            R_egimenFiscal = '$regimen',
                                            UsoCFDI = '$usoCFDI',
                                            TipoTarjeta = '$TipoTarjeta',
                                            TipoPagoID = '$FormaPago'
                                        WHERE id = '" . $fila['id'] . "'";

                            if (mysqli_query($conexionAIFA, $actualizar_ticket)) {
                                $url = "https://aifa.suinpac.com/XMLATimbrarprocesarEstacionamiento40.php";
                                $datosPost = array(
                                        "Boleto" => $fila['id'],
                                    );
                                    $options = array(
                                        'http' => array(
                                            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                                            'method'  => 'POST',
                                            'content' => http_build_query($datosPost),
                                        )
                                    );

                                $context  = stream_context_create($options);
                                $result = file_get_contents($url, false, $context);
                                $jsonRespuesta = explode("\n",$result);
                                $respuesta = $jsonRespuesta[sizeof($jsonRespuesta)-1];
                                $respuestaObjeto = json_decode($respuesta);
                                return response()->json([
                                    'message' => $respuesta,
                                    'Estatus' =>1,
                                ]);
                            }
                            }
                        }else{
                            if($longitud>=13){
                                $PersonalidadJuridica=1;
                            }else{
                                if($longitud<=12){
                                    $PersonalidadJuridica=2;
                                }
                            }
                            $insertarDatosFiscales = "INSERT INTO DatosFiscales (id, RFC, NombreORaz_onSocial, C_odigoPostal, R_egimenFiscal, CorreoElectr_onico) 
                            VALUES (NULL, '$RFC', '$nombre', '$CP', '$regimen', '$correo')";
                            if (mysqli_query($conexionAIFA, $insertarDatosFiscales)) {
                                $ultimo_id = mysqli_insert_id($conexionAIFA);
                                $insertar_detalle = "INSERT INTO Contribuyente (id, RFC, Cliente, DatosFiscales, EnLinea, PersonalidadJur_idica) 
                                VALUES (NULL, '$RFC', '1', '$ultimo_id','1', '$PersonalidadJuridica')";
                                 if (mysqli_query($conexionAIFA, $insertar_detalle)) {
                                    $idContribuyente = mysqli_insert_id($conexionAIFA);
                                    $actualizar_cotizaci_on = "UPDATE Cotizaci_on 
                                    SET 
                                        Contribuyente = '" . $fila3['id'] . "',
                                        FormaPagoId=".$FormaPago.",
                                        FormaPago=(SELECT  tt.Clave from TipoCobroCaja  tt WHERE tt.id in(".$FormaPago."))
                                    WHERE id = '" . $fila2['id'] . "'";  
                                    if (mysqli_query($conexionAIFA, $actualizar_cotizaci_on)) {
                                        $actualizar_ticket = "UPDATE TicketEstacionamiento 
                                        SET 
                                            Correo = '$correo' ,
                                            Tel_efono = '$telefono',
                                            RFC = '$RFC',
                                            Nombre = '$nombre',
                                            CodigoPostal = '$CP',
                                            R_egimenFiscal = '$regimen',
                                            UsoCFDI = '$usoCFDI',
                                            TipoTarjeta = '$TipoTarjeta',
                                            TipoPagoID = '$FormaPago'
                                        WHERE id = '" . $fila['id'] . "'";
        
                                    if (mysqli_query($conexionAIFA, $actualizar_ticket)) {
                                        $url = "https://aifa.suinpac.com/XMLATimbrarprocesarEstacionamiento40.php";
                                        $datosPost = array(
                                                "Boleto" => $fila['id'],
                                            );
                                            $options = array(
                                                'http' => array(
                                                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                                                    'method'  => 'POST',
                                                    'content' => http_build_query($datosPost),
                                                )
                                            );

                                        $context  = stream_context_create($options);
                                        $result = file_get_contents($url, false, $context);
                                        $jsonRespuesta = explode("\n",$result);
                                        $respuesta = $jsonRespuesta[sizeof($jsonRespuesta)-1];
                                        $respuestaObjeto = json_decode($respuesta);
                                        return response()->json([
                                            'message' => $respuesta,
                                            'Estatus' =>1,
                                        ]);
                                    }
                                        
                                    }
                                 }
                            }

                        }
                    }else{
                        $insertarDatosFiscales = "INSERT INTO DatosFiscales (id, RFC, NombreORaz_onSocial, C_odigoPostal, R_egimenFiscal, CorreoElectr_onico) 
                        VALUES (NULL, '$RFC', '$nombre', '$CP', '$regimen', '$correo')";
                        if (mysqli_query($conexionAIFA, $insertarDatosFiscales)) {
                            $ultimo_id = mysqli_insert_id($conexionAIFA);
                            $insertar_detalle = "INSERT INTO Contribuyente (id, RFC, Cliente, DatosFiscales, EnLinea, PersonalidadJur_idica) 
                            VALUES (NULL, '$RFC', '1', '$ultimo_id', '1', '$PersonalidadJuridica')";
                             if (mysqli_query($conexionAIFA, $insertar_detalle)) {
                                $idContribuyente = mysqli_insert_id($conexionAIFA);
                                $actualizar_cotizaci_on = "UPDATE Cotizaci_on 
                                    SET 
                                        Contribuyente = '" . $fila3['id'] . "',
                                        FormaPagoId=".$FormaPago.",
                                        FormaPago=(SELECT  tt.Clave from TipoCobroCaja  tt WHERE tt.id in(".$FormaPago."))
                                    WHERE id = '" . $fila2['id'] . "'";    
                                if (mysqli_query($conexionAIFA, $actualizar_cotizaci_on)) {
                                    $actualizar_ticket = "UPDATE TicketEstacionamiento 
                                        SET 
                                            Correo = '$correo' ,
                                            Tel_efono = '$telefono',
                                            RFC = '$RFC',
                                            Nombre = '$nombre',
                                            CodigoPostal = '$CP',
                                            R_egimenFiscal = '$regimen',
                                            UsoCFDI = '$usoCFDI',
                                            TipoTarjeta = '$TipoTarjeta',
                                            TipoPagoID = '$FormaPago'
                                        WHERE id = '" . $fila['id'] . "'";

                                        if (mysqli_query($conexionAIFA, $actualizar_ticket)) {
                                            $url = "https://aifa.suinpac.com/XMLATimbrarprocesarEstacionamiento40.php";
                                            $datosPost = array(
                                                    "Boleto" => $fila['id'],
                                                );
                                                $options = array(
                                                    'http' => array(
                                                        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                                                        'method'  => 'POST',
                                                        'content' => http_build_query($datosPost),
                                                    )
                                                );

                                            $context  = stream_context_create($options);
                                            $result = file_get_contents($url, false, $context);
                                            $jsonRespuesta = explode("\n",$result);
                                            $respuesta = $jsonRespuesta[sizeof($jsonRespuesta)-1];
                                            $respuestaObjeto = json_decode($respuesta);
                                            return response()->json([
                                                'message' => $respuesta,
                                                'Estatus' =>1,
                                            ]);
                                        }
                                        
                                }
                             }
                        }
                                 
                            
                    }
                }else{
                    return response()->json([
                        'message' => 'Recibo '.$ticket.' no se encontró cotización',
                        'Estatus' =>1,
                    ]);
                }
            }else{
                return response()->json([
                    'message' => 'Recibo '.$ticket.' no se encontró cotización',
                    'Estatus' =>1,
                ]);
            }
            
            
        }
    }
}

public function obtenerTicketAIFAV2(Request $request){
    $datos = $request->all();
    $ticket = $request->Ticket;
    $numC_odigo = $request->NumC_odigo;
    if($numC_odigo!=""){
        $sqlConsulta="SELECT  t.* FROM aifa_grp01.TicketEstacionamiento t WHERE
    t.id not in(SELECT tt.idRegistroPadre from aifa_grp01.TicketEstacionamiento tt WHERE tt.TipoFacturacion  in(3,4)  and tt.idRegistroPadre=t.id) 
    and t.idTicket=".$ticket." AND C_odigoFacturaci_on='".$numC_odigo."' AND (DATE(t.Fecha)>='2025-07-01' or t.TipoFacturacion IN(3, 4))";
    }else{
        $sqlConsulta="SELECT  t.* FROM aifa_grp01.TicketEstacionamiento t WHERE
    t.id not in(SELECT tt.idRegistroPadre from aifa_grp01.TicketEstacionamiento tt WHERE tt.TipoFacturacion  in(3,4)  and tt.idRegistroPadre=t.id) 
    and t.idTicket=".$ticket." AND (DATE(t.Fecha)>='2025-07-01' or t.TipoFacturacion IN(3, 4))";
    }
//    $sqlConsulta="SELECT  * FROM aifa_grp01.TicketEstacionamiento WHERE idTicket=".$ticket." AND NumPlacas='".$matricula."' AND DATE(Fecha)>='2025-06-01'";

    //$sqlConsulta="SELECT  * FROM aifa_grp01.TicketEstacionamiento WHERE idTicket=".$ticket;
        $respuesta=DB::select($sqlConsulta);
        if($respuesta){
            $conexionAIFA = conectarBDSuinpac();
            $validarTimbrado="SELECT * FROM XMLIngreso where uuid is not null and  idCotizaci_on=".$respuesta[0]->idCotizacion;
            if ($resultado2 = mysqli_query($conexionAIFA, $validarTimbrado)) {
                if (mysqli_num_rows($resultado2) > 0) {
                    return response()->json([
                        'idTicket'=>0,
                        'message'=>"El ticket ya fue timbrado",
                    ]);
                }else{
                    if($respuesta){
                        return response()->json([
                            'idTicket'=>$ticket, 
                            'Fecha'=>$respuesta[0]->Fecha,
                            'Estatus'=>$respuesta[0]->Estatus,
                            'Placas'=>$respuesta[0]->NumPlacas,
                            'Importe'=>$respuesta[0]->ImportePagado,
                            'TipoPago'=>$respuesta[0]->TipoPago,
        
                        ]);
                
                    }else{
                        return response()->json([
                            'idTicket'=>0
                        ]);
                    }
                }
            }else{
                if($respuesta){
                    return response()->json([
                        'idTicket'=>$ticket, 
                        'Fecha'=>$respuesta[0]->Fecha,
                        'Estatus'=>$respuesta[0]->Estatus,
                        'Placas'=>$respuesta[0]->NumPlacas,
                        'Importe'=>$respuesta[0]->ImportePagado,
    
                    ]);
            
                }else{
                    return response()->json([
                        'idTicket'=>0
                    ]);
                }
            }
        }else{
            return response()->json([
                'idTicket'=>0
            ]);
        }
       
       

}

    public function CortePruebaCAPAZ(Request $request){
        $datos = $request->all();
        $Cliente = $request->Cliente;
        $Motivo = $request->Motivo;
        $ejercicioFiscal = $request->Ejercicio;
        #$FechaCorte = $request->FechaCorte; //NOTE: se calcula en el API
        $Padron = $request->Padron;
        $Persona = $request->Persona;
        #$FechaTupla = $request->FechaTupla; //NOTE: se calcula en el API
        $Usuario = $request->Usuario;
        $Estatus = $request->Estado;
        $Latitud = $request->Latitud;
        $Longitud = $request->Longitud;
        $Evidencia = $request->Evidencia;
        $FechaTupla = date('Y-m-d H:i:s');
        $FechaCorte = date('Y-m-d');
        $Rules = [
            'Longitud'=>"required|string",
            'Latitud'=>"required|string",
            'Cliente'=> "required|numeric",
            'Motivo'=> "required|string",
            'Padron'=>"required|numeric",
            'Persona'=>"required|numeric",
            'Usuario'=>"required|numeric",
            'Estado'=>"required|numeric",
            'Ejercicio'=>"required|numeric",
            'Evidencia'=>"required",
        ];
        $validator = Validator::make($datos, $Rules);
        if ( $validator->fails() ) {
            return [
                'Status'=>false,
                'Error'=> $validator->messages() ,
                'code'=> 403
            ];
        }
        $conexion = conectarBD();
        $tipo="prueba v2";
        $insertar_detalle = "INSERT INTO testCorteAplicaci_onAgua (id, motivo) 
        VALUES (NULL, '$tipo')";

        if (mysqli_query($conexion, $insertar_detalle)) {
            $url = "https://suinpac.com/Padr_onCortarTomaAplicacion.php";

            $datosPost = array(
                "Estatus" => $Estatus,
                "Motivo" => $Motivo,
                "FechaCorte" => $FechaCorte,
                "Padr_on" => $Padron,
                "Persona" => $Persona,
                "FechaTupla" => $FechaTupla,
                "Usuario" => $Usuario,
                "Cliente" => $Cliente,
                "Ejercicio" => $ejercicioFiscal
            );
            
            $options = array(
                'http' => array(
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($datosPost),
                    'timeout' => 30
                ),
                'ssl' => array(
                    "verify_peer" => false,
                    "verify_peer_name" => false
                )
            );
            
            $context  = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            
            if ($result === FALSE) {
                die("Error al conectar con la API");
            }
            
            // Depurar la respuesta completa
            // echo "<pre>"; var_dump($result); echo "</pre>"; exit;
            
            $respuestaObjeto = json_decode(trim($result), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                die("Error en el JSON: " . json_last_error_msg() . " | Respuesta: " . $result);
            }
            
            print_r($respuestaObjeto);
            
        }
       
       

}

    public function obtenerTicketPatio(Request $request){
        $datos = $request->all();
        $ticket = $request->Ticket;
        $Fecha = $request->Fecha;
        $Importe = $request->Importe;
        if($Importe>0){
            $bandera=false;
        $sqlConsulta="SELECT  t.* FROM aifa_grp01.TicketPatioRegulador t WHERE
        EstatusDuplicado=1 and 
        C_odigoBarras='$ticket' AND ImportePagado=$Importe AND DATE(Fecha)='$Fecha' AND ImportePagado>0
        AND t.C_odigoBarras NOT IN(SELECT tv.NumBoleto FROM aifa_grp01.TicketPatioV2 tv WHERE tv.NumBoleto=t.C_odigoBarras)";
        //$sqlConsulta="SELECT  * FROM aifa_grp01.TicketEstacionamiento WHERE idTicket=".$ticket;
            $respuesta=DB::select($sqlConsulta);
            if($respuesta){
                $conexionAIFA = conectarBDSuinpac();
                    $validarTimbrado="SELECT * FROM XMLIngreso where uuid is not null and  idCotizaci_on=".$respuesta[0]->idCotizacion;
                    if ($resultado2 = mysqli_query($conexionAIFA, $validarTimbrado)) {
                        if (mysqli_num_rows($resultado2) > 0) {
                            return response()->json([
                                'idTicket'=>0,
                                'message'=>"El recibo ya fue timbrado",
                            ]);
                        }else{
                            if($respuesta){
                                return response()->json([
                                    'id'=>$respuesta[0]->id,
                                    'idTicket'=>$ticket, 
                                    'Fecha'=>$respuesta[0]->Fecha,
                                    'Estatus'=>$respuesta[0]->Estatus,
                                    'Placas'=>$respuesta[0]->NumPlacas,
                                    'Importe'=>$respuesta[0]->ImportePagado,
                
                                ]);
                        
                            }else{
                                return response()->json([
                                    'idTicket'=>0
                                ]);
                            }
                        }
                    }else{
                        if($respuesta){
                            return response()->json([
                                'id'=>$respuesta[0]->id,
                                'idTicket'=>$ticket, 
                                'Fecha'=>$respuesta[0]->Fecha,
                                'Estatus'=>$respuesta[0]->Estatus,
                                'Placas'=>$respuesta[0]->NumPlacas,
                                'Importe'=>$respuesta[0]->ImportePagado,
            
                            ]);
                    
                        }else{
                            return response()->json([
                                'idTicket'=>0
                            ]);
                        }
                    }
            }else{
                return response()->json([
                    'idTicket'=>0
                ]);
            }
        }else{
            return response()->json([
                'idTicket'=>0,
                'message'=>"Los recibos con importe igual 0 no pueden ser timbrados",
                'message2'=>"LOS RECIBOS CON IMPORTE IGUAL A 0 NO PUEDEN SER TIMBRADOS.",
            ]);
        }
        
    }

    public function obtenerTicketAIFA(Request $request){
        $datos = $request->all();
        $ticket = $request->Ticket;
        $matricula = $request->Matricula;
        $bandera=false;
        if($matricula!=""){
            $sqlConsulta="SELECT  t.* FROM aifa_grp01.TicketEstacionamiento t WHERE
        t.id not in(SELECT tt.idRegistroPadre from aifa_grp01.TicketEstacionamiento tt WHERE tt.TipoFacturacion  in(3,4)  and tt.idRegistroPadre=t.id) 
        and t.idTicket=".$ticket." AND replace(t.C_odigoFacturaci_on,' ','')=replace('".$matricula."',' ','') AND (DATE(t.Fecha)>='2025-08-01' or t.TipoFacturacion IN(3, 4))";
        }else{
            $sqlConsulta="SELECT  t.* FROM aifa_grp01.TicketEstacionamiento t WHERE
        t.id not in(SELECT tt.idRegistroPadre from aifa_grp01.TicketEstacionamiento tt WHERE tt.TipoFacturacion  in(3,4)  and tt.idRegistroPadre=t.id) 
        and t.idTicket=".$ticket." AND (DATE(t.Fecha)>='2025-08-01' or t.TipoFacturacion IN(3, 4))";
        }
        //$sqlConsulta="SELECT  * FROM aifa_grp01.TicketEstacionamiento WHERE idTicket=".$ticket;
            $respuesta=DB::select($sqlConsulta);
            if($respuesta){
                
                    $conexionAIFA = conectarBDSuinpac();
                    $validarTimbrado="SELECT * FROM XMLIngreso x
                     INNER JOIN Cotizaci_on c ON(c.id=x.idCotizaci_on)
                     where x.uuid is not null and c.Vigente=1 and  x.idCotizaci_on=".$respuesta[0]->idCotizacion;
                    if ($resultado2 = mysqli_query($conexionAIFA, $validarTimbrado)) {
                        if (mysqli_num_rows($resultado2) > 0) {
                            return response()->json([
                                'idTicket'=>0,
                                'message'=>"El ticket ya fue timbrado",
                            ]);
                        }else{
                            if($respuesta){
                                return response()->json([
                                    'id'=>$respuesta[0]->id,
                                    'idTicket'=>$ticket, 
                                    'Fecha'=>$respuesta[0]->Fecha,
                                    'Estatus'=>$respuesta[0]->Estatus,
                                    'Placas'=>$respuesta[0]->NumPlacas,
                                    'Importe'=>$respuesta[0]->ImportePagado,
                                    'TipoPago'=>$respuesta[0]->TipoPago,
                
                                ]);
                        
                            }else{
                                return response()->json([
                                    'idTicket'=>0
                                ]);
                            }
                        }
                    }else{
                        if($respuesta){
                            return response()->json([
                                'id'=>$respuesta[0]->id,
                                'idTicket'=>$ticket, 
                                'Fecha'=>$respuesta[0]->Fecha,
                                'Estatus'=>$respuesta[0]->Estatus,
                                'Placas'=>$respuesta[0]->NumPlacas,
                                'Importe'=>$respuesta[0]->ImportePagado,
                                'TipoPago'=>$respuesta[0]->TipoPago,
            
                            ]);
                    
                        }else{
                            return response()->json([
                                'idTicket'=>0
                            ]);
                        }
                    }
                
                
            }else{
                return response()->json([
                    'idTicket'=>0
                ]);
            }
           
           

    }

    public function obtenerCotizacionesAIFA(Request $request){
        $datos = $request->all();
        $sqlConsulta="SELECT * FROM aifa_grp01.Cotizaci_on WHERE Cliente=1 ORDER BY id DESC LIMIT 10";
        $respuesta=DB::select($sqlConsulta);
        return response()->json($respuesta);
    }

    public function obtenerClienteAIFA(Request $request){
        $datos = $request->all();
        $sqlConsulta="SELECT cr.Ruta as Logotipo, c.Descripci_on as Nombre FROM aifa_grp01.Cliente c INNER JOIN aifa_grp01.CelaRepositorioC cr ON (cr.idRepositorio=c.Logotipo) WHERE c.id=1";
            $respuesta=DB::select($sqlConsulta);
        return response()->json([
            'id'=>22,
            'ruta'=>$_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"]."/".$respuesta[0]->Logotipo,
            'Descripci_on'=>$respuesta[0]->Nombre
        ]);

    }
    

    public function pruebaAsistencias(Request $request){
        $conexion = conectarBD();
        $empleado = $request->empleado_id;
        $fecha = $request->fecha;
        $terminal = $request->terminal;
        $fechaformat = $request->fechaformat;
        $time = $request->time;
            
            $persona="SELECT * FROM Persona where N_umeroDeEmpleado=".$empleado;
            if ($resultado2 = mysqli_query($conexion, $persona)) {
                if (mysqli_num_rows($resultado2) > 0) {
                    $fila = mysqli_fetch_assoc($resultado2);
                    // Asignar el valor del id de la persona
                    $fechaFormat = $fechaformat;
                    $idPersona = $fila['id'];
                    $coincidencia="SELECT * FROM Asistencia_ where Empleado=".$idPersona." and Fecha='".$fechaFormat."'";
                    if ($resultado = mysqli_query($conexion, $coincidencia)) {
                        if (mysqli_num_rows($resultado) == 0) {
                            // Ejemplo de consulta para obtener todos los empleados
                        $fechaTupla = date('Y-m-d H:i:s'); // Asumiendo que quieres insertar la fecha y hora actuales
                         // Reemplaza con el valor correspondiente
                        $insertar_asistencia = "INSERT INTO Asistencia_ (id, Fecha, FechaTupla, Empleado, NumEmpleado) 
                        VALUES (NULL, '$fecha', '$fechaTupla', '$idPersona', '$empleado')";
        
                        if (mysqli_query($conexion, $insertar_asistencia)) {
                            $ultimo_id = mysqli_insert_id($conexion);
                            $insertar_detalle = "INSERT INTO Asistencia_Detalle (id, Hora, FechaTupla, idAsistencia, Empleado, Terminal, RelojBiometrico) 
                            VALUES (NULL, '$fecha', '$fechaTupla', '$ultimo_id', '$idPersona', '$terminal', 1)";

                            if (mysqli_query($conexion, $insertar_detalle)) {
                                return response()->json([
                                    'id'=>$empleado,
                                    'fecha'=>$fechaFormat
                                ]);
                            }else{
                                return response()->json([
                                    'Status'=>false,
                                    'Mensaje'=>mysqli_error($conexion)." del empleado: ".$empleado,
                                    'Code'=>224
                                ]);
                            }
                        }else{
                            return response()->json([
                                'Status'=>false,
                                'Mensaje'=>mysqli_error($conexion)." del empleado: ".$empleado,
                                'Code'=>224
                            ]);
                        }
        
                        }else{
                            $hora = $time;
                            $fechaTupla = date('Y-m-d H:i:s');
                            $fila2=mysqli_fetch_assoc($resultado);
                            $idAsistencia=$fila2['id'];
                            // Asignar el valor del id de la persona
                            $coincidencia2="SELECT * FROM Asistencia_Detalle where Empleado=".$idPersona." and Hora='".$hora."'";
                            if ($resultado3 = mysqli_query($conexion, $coincidencia2)) {
                                if (mysqli_num_rows($resultado3) == 0) {
                                    $insertar_detalle = "INSERT INTO Asistencia_Detalle (id, Hora, FechaTupla, idAsistencia, Empleado, Terminal, RelojBiometrico) 
                                    VALUES (NULL, '$fecha', '$fechaTupla', '$idAsistencia', '$idPersona', '$terminal', 1)";

                                    if (mysqli_query($conexion, $insertar_detalle)) {
                                        return response()->json([
                                            'id'=>$empleado,
                                            'fecha'=>$hora
                                        ]);
                                    }else{
                                        return response()->json([
                                            'Status'=>false,
                                            'Mensaje'=>mysqli_error($conexion)." del empleado: ".$empleado,
                                            'Code'=>224
                                        ]);
                                    }
                                }else{
                                    return response()->json([
                                        'Status'=>false,
                                        'Mensaje'=>mysqli_error($conexion)." del empleado: ".$empleado,
                                        'Code'=>224
                                    ]);
                                }
                            }    
                            
                        }
        
                    }else{
                        return response()->json([
                            'Status'=>false,
                            'Mensaje'=>mysqli_error($conexion)." del empleado: ".$empleado,
                            'Code'=>224
                        ]);
                    }
                }else{
                    $coincidencia3="SELECT * FROM Registros_Asistencia2 where num_empleado=".$empleado." and FechaTupla='".$fecha."'";
                    if ($resultado = mysqli_query($conexion, $coincidencia3)) {
                        if (mysqli_num_rows($resultado) == 0) {
                            $insertar_registrosAsistencia = "INSERT INTO Registros_Asistencia2 (id, num_empleado, Fecha, FechaTupla) 
                            VALUES (NULL, '$empleado', '$fecha', '$fecha')";

                            if (mysqli_query($conexion, $insertar_registrosAsistencia)) {
                                return response()->json([
                                    'id'=>$empleado,
                                    'fecha'=>$fecha
                                ]);
                            }else{
                                return response()->json([
                                    'Status'=>false,
                                    'Mensaje'=>mysqli_error($conexion)." del empleado: ".$empleado,
                                    'Code'=>224
                                ]);
                            }

                        }else{
                            return response()->json([
                                'Status'=>false,
                                'Mensaje'=>mysqli_error($conexion)." del empleado: ".$empleado,
                                'Code'=>224
                            ]);
                        }
                    }else{
                        return response()->json([
                            'Status'=>false,
                            'Mensaje'=>mysqli_error($conexion)." del empleado: ".$empleado,
                            'Code'=>224
                        ]);
                    }
                    
                }
            }
    
    
    
    // Convertir el resultado en un array asociativo
    //$empleados = [];
    //while ($fila = mysqli_fetch_assoc($resultado)) {
    //$empleados[] = $fila;
    //}
    
    // Cerrar la conexión
    mysqli_close($conexion);

    }

    public function obtenerRecurso(Request $request){
        $datos = $request->all();
        $rules = [
            'cliente'=>'required|string',
            'recurso'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Empleado no valido",
                'Code'=>223
            ]);
        }
        $cliente = $request->cliente;
        $recurso = $request->recurso;
        Funciones::selecionarBase($cliente);
        //Verificamos si existe en CelaReposotorioC
        $result = DB::table('CelaRepositorioC')->select(DB::raw('CONCAT("https://suinpac.com/",Ruta) AS Ruta'))->where('idRepositorio','=',$recurso)->get();
        if(sizeof($result)>0){
            return $result[0]->Ruta;
        }else{
            //Buscamos en CelaRepositorio
            $result = DB::table('CelaRepositorio')->select(DB::raw('CONCAT("https://suinpac.com/",Ruta) AS Ruta'))->where('idRepositorio','=',$recurso)->get();
            if(sizeof($result)>0){
                return $result[0]->Ruta;
            }else{
                return "null";
            }
        }
    }
    public function recuperarChecador(Request $request){
        $datos = $request->all();
        $rules = [
            'cliente'=>'required|string',
            'nombre'=>'required|string',
            'password'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Datos incompleto",
                'Code'=>223
            ]);
        }
        $cliente = $request->cliente;
        $nombre = $request->nombre;
        $UserPassword = $request->password;
        Funciones::selecionarBase($cliente);
        $password = $this->encript($UserPassword);

        $result = DB::table('Checador')->select('Checador.id','Checador.Nombre','Checador.Cliente','Checador.UID','ChecadorSector.Nombre AS NombreSector','ChecadorSector.id AS Sector','Checador.AplicaSector','Checador.HoraRelleno')
                                       ->leftjoin('ChecadorSector','ChecadorSector.idChecador','=','Checador.id')
                                       ->where('Checador.Nombre','=',$nombre)->where('Checador.Password','=',$password)
                                       ->where('Checador.Cliente','=',$cliente)->where('Checador.Estado','=',1)->get();
        if(sizeof($result)){
            return response()->json([
                'id'=>$result[0]->id,
                'Nombre'=>$result[0]->Nombre,
                'Cliente'=>$result[0]->Cliente,
                'UID'=>$result[0]->UID,
                'NombreSector'=>$result[0]->NombreSector,
                'Sector'=>$result[0]->Sector,
                'AplicaSector'=>$result[0]->AplicaSector,
                'HoraRelleno'=>$result[0]->HoraRelleno
                ]);
        }else{
        //de esta manera se puede manejar mejor los errores en c#
        //Se pude hacer una busqueda de los checadores por nombre para dar feedback al usuario
        return response()->json([
            'id'=>-1,
            'Nombre'=>"null",
            'Cliente'=>-1,
            'UID'=>"null",
            'NombreSector'=>"null",
            'Sector'=>-1
            ]);
        }

    }
    public function configurarChecador(Request $request){
        $datos =  $request->all();
        $rules = [
            'Cliente'=>"required|numeric",
            'Sector'=>"required|numeric", // me falta configura el sector
            'Nombre'=>'required|string',
            'UserPass'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de completar los campo",
                'Code'=>223
            ]);
        }
        $date =  new \DateTime();
        //Generamos el identificador para el checador con php
        $uid = Str::uuid(); //identificador del checador
        $cliente = $request->Cliente;
        $Sector = $request->Sector;
        $Nombre = $request->Nombre;
        $Pass = $request->UserPass;
        $Alta = $date->format('Y-m-d');
        //insertamos la configuracion del cehcador a la base de datos
        $aplicaSector = 0;
        if($Sector == -1){
            $aplicaSector = 0;
        }else{
            $aplicaSector = 1;
        }
        Funciones::selecionarBase($cliente);
        $Password = $this->encript($Pass);
        $result = DB::table('Checador')
                    ->insert(['Nombre'=>$Nombre,
                             'Cliente'=>$cliente,
                             'UID'=>$uid,
                             'Estado'=> 1,
                             'FechaAlta'=>$Alta,
                             'UltimaConexion' =>$Alta,
                             'Password' =>$Password,
                            'AplicaSector'=> $aplicaSector]);
        if($result){
            //Obtenemos el id del checador para guardarlo en local
            $id = DB::table('Checador')->max('id');
            //Asignar Sector (Aun fata)
            if($Sector != -1){
                $result = DB::table('ChecadorSector')->where('id','=',$Sector)->update(['idChecador'=>$id]);
                if($result){
                    return $uid. ",".$id;
                }else{
                    $result = DB::table('Checador')->where('id','=',$id)->delete();
                    return "-1";
                }
            }else{
                return $uid. ",".$id;
            }

        }else{
            return "0";
        }
    }
    function verificarChecador(Request $request){
        $datos = $request->all();
        $rules = [
            'Password'=>'required|string',
            'Checador'=>'required|string',
            'NombreChecador'=>'required|string',
            'Cliente'=>'required|string',
            'uid'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de completar los campo",
                'Code'=>223
            ]);
        }
        $Cliente = $request->Cliente;
        $Pass = $request->Password;
        $idChecador = $request->Checador;
        $Nombre = $request->NombreChecador;
        $uid = $request->uid;
        Funciones::selecionarBase($Cliente);
        $Password = $this->encript($Pass);
        $result = DB::table('Checador')
                        ->select('Estado')
                        ->where('Cliente','=',$Cliente)
                        ->where('Nombre','=',$Nombre)
                        ->where('UID','=',$uid)
                        ->where('Password','=',$Password)->get();
        if(sizeof($result)>0){
            return $result[0]->Estado;
        }else{
            return 0;
        }
    }
    function actualizarBanner(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required|numeric',
            'uid'=>'required|string',
            'Checador'=>'required|numeric',
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de completar los campo",
                'Code'=>223
            ]);
        }
        $Cliente = $request->Cliente;
        $uid = $request->uid;
        $Checador = $request->Checador;
        Funciones::selecionarBase($Cliente);
        $result = DB::table("ChecadorBanner")->select("ChecadorBanner.*")
                                             ->join("Checador","Checador.id","=",DB::raw("ChecadorBanner.idChecador OR ChecadorBanner.General = 1"))
                                             ->where("Checador.id","=",$Checador)
                                             ->where("Checador.Cliente","=",$Cliente)
                                             ->get();
        if(sizeof($result)>0){
            return $result;
        }else{
            $bannerArray = array();
            $item = [
                "id"=> -1,
                "Tipo"=> -1,
                "idRepositorio"=> -1,
                "FechaLimite"=> "null",
                "FechaAlta"=> "null",
                "General"=> -1,
                "idChecador"=> -1,
                "Recurso"=> "null"
            ];
            array_push($bannerArray,$item);
            return $bannerArray;
        }
    }
    function obtenerSectores(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required|numeric',
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de completar los campo",
                'Code'=>223
            ]);
        }
        $Cliente = $request->Cliente;
        $Key = $request->Key;
        Funciones::selecionarBase($Cliente);
        $result = DB::table('ChecadorSector')->select('*')->get();
        if($result){
            return $result;
        }else{
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Datos no encontrados",
                'Code'=>204
            ]);
        }
    }
    //FIXME: Cambiar por el id de la persona
    function registrarAsistenciaChecadorOLD(Request $request){
        $inserted = "0";
        $datos = $request->all();
        $rules = [
            'cliente'=>'required|string',
            'Fecha'=>'required|string',
            'FechaTupla'=>'required|string',
            'idGrupoPersona'=>'required|string',
            'MultipleHorario'=>'required|string',
            'Detalles'=>'' //Los detalles de la asistencia
        ];
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => 'Error',
                'Error' => $validator->messages()
            ]);
        }
        //obtenemos los datos del request
        $Cliente = $request->cliente;
        $Fecha = $request->Fecha;
        $FechaTupla = $request->FechaTupla;
        $idGrupoPersona = $request->idGrupoPersona;
        $multiple = $request->MultipleHorario;
        $arregloDetalles = json_decode($request->Detalles,true);
        Funciones::selecionarBase($Cliente,$Cliente);
        $detalleHora = "";
        foreach($arregloDetalles as $detalle){
            $detalle = $detalle['HoraAsistencia'];
        }

        $idAsistencia = null;
        $verificarActual = "-1";
        //Aqui checamos si ya existe una asistencia en el intervalo actual (ruegale a dios que me salga porque no se ni por donde entrarle alv)
        $verificar = $this->verificarAsistencia($idGrupoPersona,$Cliente,$detalle);
        if(!$verificar){
            $verificarActual = $this->verificarAsistenciaActual($idGrupoPersona,$Cliente,$Fecha);
            if($verificarActual == "-1"){
                 DB::table('Asistencia_')->insert([
                    'id'=>null,
                    'Fecha'=>$Fecha,
                    'FechaTupla'=> $FechaTupla,
                    'Usuario'=>null,
                    'idAsistenciaGrupoPersona'=>$idGrupoPersona,
                    'MultipleHorario'=>$multiple
                ]);
                $idAsistencia = DB::table('Asistencia_')->max('id');
            }else{
                $idAsistencia = $verificarActual;
            }
            $result = false;
            foreach($arregloDetalles as $detalle){
                $result = DB::table('Asistencia_Detalle')->insert([
                'id'=>null,
                'Hora'=>$detalle['HoraAsistencia'],
                'EstatusAsistencia'=> $detalle['EstatusAsistencia'],
                'FechaTupla'=>$detalle['FechaTupla'],
                'Tipo'=>$detalle['Tipo'],
                'idAsistencia'=>$idAsistencia,
                'EstatusAsistenciaCalculado'=>$detalle['EstatusAsistencia'],
                'Descripci_on'=>null,
                'EstatusA'=>1,
                'UsuarioRegistro'=>null,
                'UsuarioCancelo'=>null,
                'AsistenciaPapa'=>null,
                'FechaActualizaci_on'=>null
            ]);
            }
            if($result){
                $inserted =  "1";
            }else{
                $inserted =  "0";
            }
        }else{
            $inserted = "1";
        }
        return $inserted;



    }
    //FIXED: Cambiando por el id de la persona
    function registrarAsistenciaChecador(Request $request){
        $inserted = "0";
        $datos = $request->all();
        $rules = [
            'cliente'=>'required|string',
            'Fecha'=>'required|string',
            'FechaTupla'=>'required|string',
            'idGrupoPersona'=>'required|string',
            'MultipleHorario'=>'required|string',
            'Detalles'=>'' //Los detalles de la asistencia
        ];
        $validator = Validator::make($datos, $rules);
        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => 'Error',
                'Error' => $validator->messages(),
            ]);
        }
        //obtenemos los datos del request
        $Cliente = $request->cliente;
        $Fecha = $request->Fecha;
        $FechaTupla = $request->FechaTupla;
        $idGrupoPersona = $request->idGrupoPersona;
        $multiple = $request->MultipleHorario;
        $arregloDetalles = json_decode($request->Detalles,true);
        Funciones::selecionarBase($Cliente);
        $detalleHora = "";
        #SELECT Empleado FROM Asistencia_GrupoPersona WHERE id = 7;
        $Empleado = DB::table('Asistencia_GrupoPersona')->select('Empleado')->where('id','=',$idGrupoPersona)->get();
        foreach($arregloDetalles as $detalle){
            $detalle = $detalle['HoraAsistencia'];
        }
        $idAsistencia = null;
        $verificarActual = "-1";
        //Aqui checamos si ya existe una asistencia en el intervalo actual (ruegale a dios que me salga porque no se ni por donde entrarle alv)
        $verificar = $this->verificarAsistencia($idGrupoPersona,$Cliente,$detalle);
        if(!$verificar){
            $verificarActual = $this->verificarAsistenciaActual($idGrupoPersona,$Cliente,$Fecha);
            if($verificarActual == "-1"){
                    DB::table('Asistencia_')->insert([
                    'id'=>null,
                    'Fecha'=>$Fecha,
                    'FechaTupla'=> $FechaTupla,
                    'Usuario'=>null,
                    'idAsistenciaGrupoPersona'=>$idGrupoPersona,
                    'MultipleHorario'=>$multiple,
                    'Empleado'=>$Empleado[0]->Empleado
                ]);
                $idAsistencia = DB::table('Asistencia_')->max('id');
            }else{
                $idAsistencia = $verificarActual;
            }
            $result = false;
            foreach($arregloDetalles as $detalle){
                $result = DB::table('Asistencia_Detalle')->insert([
                'id'=>null,
                'Hora'=>$detalle['HoraAsistencia'],
                'EstatusAsistencia'=> $detalle['EstatusAsistencia'],
                'FechaTupla'=>$detalle['FechaTupla'],
                'Tipo'=>$detalle['Tipo'],
                'idAsistencia'=>$idAsistencia,
                'EstatusAsistenciaCalculado'=>$detalle['EstatusAsistencia'],
                'Descripci_on'=>null,
                'EstatusA'=>1,
                'UsuarioRegistro'=>null,
                'UsuarioCancelo'=>null,
                'AsistenciaPapa'=>null,
                'FechaActualizaci_on'=>null
            ]);
            }
            if($result){
                $inserted =  "1";
            }else{
                $inserted =  "0";
            }
        }else{
            $inserted = "1";
        }
        return $inserted;



    }
    //FIXME: Cambiar por el id de la persona Metodo para verificar asitencia anterior
    public function verificarAsistenciaOLD($idGrupo,$Cliente,$Hora){
        //Datos que necesito idGrupoEmpleado
        $found = false;
        $actual = strtotime(date('Y-m-d').' '.$Hora);
        Funciones::selecionarBase($Cliente);


        //Buscamos al empleado por medio del idGrupoPersona
        $idEmpleado = DB::table('Asistencia_GrupoPersona')
                        ->select('PuestoEmpleado')
                        ->where('id','=',$idGrupo)->get();


        //Buscamos el horario del empleaos
        $dia = date('N');
        $result = DB::table('Asistencia_GrupoPersona')
            ->select(
                'Asistencia_EmpleadoHorarioDetalle.HoraEntrada',
                'Asistencia_EmpleadoHorarioDetalle.HoraSalida',
                )
            ->leftjoin('Asistencia_EmpleadoHorario','Asistencia_EmpleadoHorario.idAsistencia_GrupoPersona','Asistencia_GrupoPersona.id')
            ->leftjoin('Asistencia_EmpleadoHorarioDetalle','Asistencia_EmpleadoHorario.id','Asistencia_EmpleadoHorarioDetalle.idAsistencia_EmpleadoHorario')
            ->where('Asistencia_GrupoPersona.PuestoEmpleado','=',$idEmpleado[0]->PuestoEmpleado)
            ->where('Asistencia_GrupoPersona.Estatus','=',"1")
            ->where('Asistencia_EmpleadoHorario.Dia','=',$dia)->get();


        //Obtenemos el historial de asistencias de hoy
        $historial = DB::table('Asistencia_')
                        ->select(DB::raw("CONCAT(DATE_FORMAT(NOW(),'%Y-%m-%d'),' ',Asistencia_Detalle.Hora) as Historial"),'Asistencia_Detalle.Tipo')
                        ->join('Asistencia_Detalle','Asistencia_.id','=','Asistencia_Detalle.idAsistencia')
                        ->where('Asistencia_.Fecha','=',date("Y-m-d"))
                        ->where('Asistencia_.idAsistenciaGrupoPersona','=',$idGrupo)
                        ->get();
        $horaSalidaAuxiliar = null;
        $registroFinal = null;
        foreach($result as $item){
            $horaEntrada = strtotime(date('Y-m-d').' '.$item->HoraEntrada. " - 30 minutes");
            $horaSalida = strtotime(date('Y-m-d').' '.$item->HoraSalida);
            foreach($historial as $historia){
                $registro = strtotime($historia->Historial);
                if($registro >= $horaEntrada && $registro <= $horaSalida ){


                    //Se verifican las entradas
                    if($actual >= $horaEntrada && $actual <= $horaSalida){
                        $found =  true;
                    }
                }else{
                    if($horaSalidaAuxiliar != null){
                        if($registro >= $horaSalidaAuxiliar && $registro <= $horaEntrada){



                            //Funciona para salidas del multiple horarrio
                            if($actual >= $horaSalidaAuxiliar && $actual <= $horaEntrada){
                                $found = true;
                            }
                        }
                    }
                }
                $registroFinal = $historia;
            }
            $horaSalidaAuxiliar = $horaSalida;
        }
        if( $registroFinal != null ){
            $verificacionFinal = $registroFinal->Historial;
            //Validamos la salida anterior
            if(!$found){
                if(strtotime($verificacionFinal) >= $horaSalidaAuxiliar ){
                    //Verificamos el registro actual
                    if($actual >= $horaSalidaAuxiliar){
                        $found = true;
                    }
                }
            }
        }
        return $found;

    }
    //FIXED: Se camio por el id de la persona
    public function verificarAsistencia($idGrupo,$Cliente,$Hora){
        //Datos que necesito idGrupoEmpleado
        $found = false;
        $actual = strtotime(date('Y-m-d').' '.$Hora);
        Funciones::selecionarBase($Cliente);
        //Buscamos al empleado por medio del idGrupoPersona
        $idEmpleado = DB::table('Asistencia_GrupoPersona')
                        ->select('Empleado')
                        ->where('id','=',$idGrupo)->get();
        //Buscamos el horario del empleaos
        $dia = date('N');
        $result = DB::table('Asistencia_GrupoPersona')
            ->select(
                'Asistencia_EmpleadoHorarioDetalle.HoraEntrada',
                'Asistencia_EmpleadoHorarioDetalle.HoraSalida',
                )
            ->leftjoin('Asistencia_EmpleadoHorario','Asistencia_EmpleadoHorario.idAsistencia_GrupoPersona','Asistencia_GrupoPersona.id')
            ->leftjoin('Asistencia_EmpleadoHorarioDetalle','Asistencia_EmpleadoHorario.id','Asistencia_EmpleadoHorarioDetalle.idAsistencia_EmpleadoHorario')
            ->where('Asistencia_GrupoPersona.Empleado','=',$idEmpleado[0]->Empleado)
            ->where('Asistencia_GrupoPersona.Estatus','=',"1")
            ->where('Asistencia_EmpleadoHorario.Dia','=',$dia)->get();
        //Obtenemos el historial de asistencias de hoy
        $historial = DB::table('Asistencia_')
                        ->select(DB::raw("CONCAT(DATE_FORMAT(NOW(),'%Y-%m-%d'),' ',Asistencia_Detalle.Hora) as Historial"),'Asistencia_Detalle.Tipo')
                        ->join('Asistencia_Detalle','Asistencia_.id','=','Asistencia_Detalle.idAsistencia')
                        ->where('Asistencia_.Fecha','=',date("Y-m-d"))
                        ->where('Asistencia_.idAsistenciaGrupoPersona','=',$idGrupo)
                        ->get();
        $horaSalidaAuxiliar = null;
        $registroFinal = null;
        foreach($result as $item){
            $horaEntrada = strtotime(date('Y-m-d').' '.$item->HoraEntrada. " - 30 minutes");
            $horaSalida = strtotime(date('Y-m-d').' '.$item->HoraSalida);
            foreach($historial as $historia){
                $registro = strtotime($historia->Historial);
                if($registro >= $horaEntrada && $registro <= $horaSalida ){
                    //Se verifican las entradas
                    if($actual >= $horaEntrada && $actual <= $horaSalida){
                        $found =  true;
                    }
                }else{
                    if($horaSalidaAuxiliar != null){
                        if($registro >= $horaSalidaAuxiliar && $registro <= $horaEntrada){
                            //Funciona para salidas del multiple horarrio
                            if($actual >= $horaSalidaAuxiliar && $actual <= $horaEntrada){
                                $found = true;
                            }
                        }
                    }
                }
                $registroFinal = $historia;
            }
            $horaSalidaAuxiliar = $horaSalida;
        }
        if( $registroFinal != null ){
            $verificacionFinal = $registroFinal->Historial;
            //Validamos la salida anterior
            if(!$found){
                if(strtotime($verificacionFinal) >= $horaSalidaAuxiliar ){
                    //Verificamos el registro actual
                    if($actual >= $horaSalidaAuxiliar){
                        $found = true;
                    }
                }
            }
        }
        //TESTING:
        return $found;
    }
    //FIXME: Cambiar por el id de la persona
    public function verificarAsistenciaActualOLD($idGrupoPersona,$Cliente,$Fecha){
        //Datos que necesito idGrupoEmpleado
        $found = false;

        Funciones::selecionarBase($Cliente);
        //Buscamos si existe alguna asistencia ya registrada el dia de hoy
        $result = DB::table('Asistencia_')
                        ->select('id')
                        ->where('idAsistenciaGrupoPersona','=',$idGrupoPersona)
                        ->where('Fecha','=',$Fecha)->get();

        if(sizeof($result)>0){
            return $result[0]->id;
        }else{
            return "-1";
        }
    }
    //FIXED: sin cambios
    public function verificarAsistenciaActual($idGrupoPersona,$Cliente,$Fecha){
        //Datos que necesito idGrupoEmpleado
        $found = false;

        Funciones::selecionarBase($Cliente);
        //Buscamos si existe alguna asistencia ya registrada el dia de hoy
        $result = DB::table('Asistencia_')
                        ->select('id')
                        ->where('idAsistenciaGrupoPersona','=',$idGrupoPersona)
                        ->where('Fecha','=',$Fecha)->get();

        if(sizeof($result)>0){
            return $result[0]->id;
        }else{
            return "-1";
        }
    }
    public function verificarAsistenciaV2(Request $request){
        $idGrupoPersona = $request->Grupo;
        $Cliente = $request->Cliente;
        $Fecha = $request->Fecha;

        Funciones::selecionarBase($Cliente);
        //Buscamos si existe alguna asistencia ya registrada el dia de hoy
        $result = DB::table('Asistencia_')
                        ->select('id')
                        ->where('idAsistenciaGrupoPersona','=',$idGrupoPersona)
                        ->where('Fecha','=',$Fecha)->get();

        if(sizeof($result)>0){
            return $result[0]->id;
        }else{
            //Insertamos uno de prueba
            DB::table('Asistencia_')->insert([
                'id'=>null,
                'Fecha'=>$Fecha,
                'FechaTupla'=> $Fecha,
                'Usuario'=>null,
                'idAsistenciaGrupoPersona'=>$idGrupoPersona,
                'MultipleHorario'=>'1'
            ]);
            $id =  DB::table('Asistencia_')->max('id');
            return $id;
        }
    }
    function obtenerBitacoraChecador(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required|string',
            'UID'=>'required|string',
            'Checador'=>'required|string',
            'Nombre'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de completar los campo",
                'Code'=>223
            ]);
        }
        $checador = $request->Checador;
        $uid = $request->UID;
        $cliente = $request->Cliente;
        $nombre  =  $request->Nombre;
        Funciones::selecionarBase($cliente);
        //hacemos la consulta de los datos
        $result = DB::table('ChecadorBitacora')->select('ChecadorBitacora.*')->leftjoin('Checador','Checador.id','=','ChecadorBitacora.idChecador')
                                               ->where('ChecadorBitacora.idChecador','=',$checador)
                                               ->limit(1)->get();
                                               #->orwhere('ChecadorBitacora.idChecador',NULL)
                                               #->where('Checador.UID','=',$uid)
                                               #->where('Checador.Nombre','=',$nombre)
                                              # ->where('Checador.Cliente','=',$cliente)->get();
        if($result){
            if(sizeof($result)>0){
                return $result;
            }else{
                //de esta manera se puede manejar mejor los errores en c#
                $errorData = array();
                $error = ["id"=> -1,
                            "idChecador"=> -1,
                            "Tarea"=>-1,
                            "Descripcion"=>"null"];
                array_push($errorData,$error);
                            return $errorData;
            }
        }else{
            $errorData = array();
            $error = ["id"=> -1,
                        "idChecador"=> -1,
                        "Tarea"=> "Error al hacer la consulta",
                        "Descripcion"=>"Error al hacer la consulta"];
            array_push($errorData,$error);
                        return $errorData;
        }
    }
    function obtenerLogotipoClienteChecador(Request $request){
        $datos = $request->all();
        $rules = [
            'cliente'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails() ){
            return "Datos no validos";
        }
        $cliente = $request->cliente;
        Funciones::selecionarBase($cliente);
        $result = DB::table('Cliente')
            ->select('Ruta','NombreCorto')
            ->join('CelaRepositorioC','Logotipo','idRepositorio')
            ->where('Cliente.id','=',$cliente)->get()->first();
        $rutaLogo = $result->Ruta;
        $nombreCorto = $result->NombreCorto;
        $formatedUrl = str_replace('repositorio',"",$rutaLogo);
        $data = Storage::disk('repositorio') -> get($formatedUrl);
        $encodeLogo = base64_encode($data);
        if($result){
            ///retornamos los datos de la consulta
            return $encodeLogo;
        }else{
            return "null";
        }
    }
    function actualizarConexion(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required|string',
            'Nombre'=>'required|string',
            'Checador'=>'required|string',
            'UID'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails() ){
            return "Datos no validos";
        }
        $cliente = $request->Cliente;
        $nombre = $request->Nombre;
        $checador = $request->Checador;
        $uid = $request->UID;
        $ultimaConexion =date('Y-m-d H:i:s');
        Funciones::selecionarBase($cliente);
        $result = DB::table('Checador')->where('Checador.id','=',$checador)
                                       ->where('Checador.Nombre','=',$nombre)
                                       ->where('Checador.UID','=',$uid)
                                       ->where('Checador.Cliente','=',$cliente)
                                       ->update(['Checador.UltimaConexion'=>$ultimaConexion]);
        if($result){
            return $ultimaConexion;
        }else{
            return "null";
        }
    }
    function bitacoraTareaCompleta(Request $request){
        $datos = $request->all();
        $rules = [
            'idTarea'=>'required|string',
            'Cliente'=>'required|string',
            'Checador'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails() ){
            return response()->json([
                'Status' => false,
                'mensaje'=>'Campos vacios'
            ]);
        }
        $idTarea = $request->idTarea;
        $cliente = $request->Cliente;
        $checador = $request->Checador;
        Funciones::selecionarBase($cliente);
        $result = DB::table('ChecadorBitacora')->where('id','=',$idTarea)->where('idChecador','=',$checador)->delete();
        if($result){
            return "1";
        }else{
            return "0";
        }
    }
    //FIXME: Cambiar por el id de la persona
    function obtenerEmpleadoTareaOLD(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required',
            'Checador'=>'required',
            'Sector'=>'required',
            'UID'=>'required|string',
            'Nombre'=>'required|string',
            'Empleado'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de completar los campo",
                'Code'=>223
            ]);
        }
        $cliente = $request->Cliente;
        $checador = $request->Checador;
        $sector = $request->Sector;
        $uid = $request->UID;
        $nombre = $request->Nombre;
        $PuestoEmpleado = $request->Empleado;
        Funciones::selecionarBase($cliente);
        $table = 'EmpleadoFotografia';
        $result = DB::table('Persona')->select("PuestoEmpleado.id as idEmpleado",DB::raw('CONCAT(Persona.Nombre," ",Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) AS Nombre'),'Persona.Nfc_uid','Persona.idChecadorApp as idChecador','Persona.N_umeroDeEmpleado as NoEmpleado','PuestoEmpleado.NombreDelCargo As Cargo','AreasAdministrativas.Descripci_on as AreaAdministrativa','Cat_alogoPlazaN_omina.Descripci_on AS NombrePlaza','TipoTrabajador.Descripci_on as Trabajador' ,DB::raw('CONCAT("https://suinpac.com/",CelaRepositorio.Ruta) as Foto'))
            ->join('PuestoEmpleado','Persona.id','=','PuestoEmpleado.Empleado')
            ->join('TipoTrabajador','TipoTrabajador.id','=','PuestoEmpleado.TipoTrabajador')
            ->join('PlantillaN_ominaCliente','PlantillaN_ominaCliente.id','=','PuestoEmpleado.PlantillaN_ominaCliente')
            ->join('Cat_alogoPlazaN_omina','Cat_alogoPlazaN_omina.id','=','PlantillaN_ominaCliente.Cat_alogoPlazaN_omina')
            ->join('AreasAdministrativas','AreasAdministrativas.id','=','PlantillaN_ominaCliente.AreaAdministrativa')
            ->leftjoin('ChecadorEmpleadoFotografia','ChecadorEmpleadoFotografia.idEmpleado','=',"PuestoEmpleado.id")
            ->leftjoin('CelaRepositorio','CelaRepositorio.idRepositorio','=',"ChecadorEmpleadoFotografia.idRepositorio")
            ->where('Persona.Cliente','=',$cliente)->where('PuestoEmpleado.Estatus','=','1')
            #->where('Persona.idChecadorApp','=',$checador)
            ->where('PuestoEmpleado.id','=',$PuestoEmpleado)
            ->get();
        if(sizeof($result)>0){
            return $result;
        }else{

            $arrayTemp = array();
            $data = [
                "idEmpleado"=>-1,
                "Nombre"=>"null",
                "Nfc_uid"=>"null",
                "idChecador"=>-1,
                "NoEmpleado"=>-1,
                "Cargo"=>"null",
                "AreaAdministrativa"=>"null",
                "NombrePlaza"=>"null",
                "Trabajador"=>"null",
                "Foto"=>"null",
            ];
            array_push($arrayTemp, $data);
            return $arrayTemp;
        }
    }
    //FIXED: Se cambio el id
    function obtenerEmpleadoTarea(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required',
            'Checador'=>'required',
            'Sector'=>'required',
            'UID'=>'required|string',
            'Nombre'=>'required|string',
            'Empleado'=>'required|string'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de completar los campo",
                'Code'=>223
            ]);
        }
        $cliente = $request->Cliente;
        $checador = $request->Checador;
        $sector = $request->Sector;
        $uid = $request->UID;
        $nombre = $request->Nombre;
        $PuestoEmpleado = $request->Empleado;
        Funciones::selecionarBase($cliente);
        $table = 'EmpleadoFotografia';
        $result = DB::table('Persona')->select("Persona.id as idEmpleado",DB::raw('CONCAT(Persona.Nombre," ",Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) AS Nombre'),'Persona.Nfc_uid','Persona.idChecadorApp as idChecador','Persona.N_umeroDeEmpleado as NoEmpleado','PuestoEmpleado.NombreDelCargo As Cargo','AreasAdministrativas.Descripci_on as AreaAdministrativa','Cat_alogoPlazaN_omina.Descripci_on AS NombrePlaza','TipoTrabajador.Descripci_on as Trabajador' ,DB::raw('CONCAT("https://suinpac.com/",CelaRepositorio.Ruta) as Foto'))
            ->join('PuestoEmpleado','Persona.id','=','PuestoEmpleado.Empleado')
            ->join('TipoTrabajador','TipoTrabajador.id','=','PuestoEmpleado.TipoTrabajador')
            ->join('PlantillaN_ominaCliente','PlantillaN_ominaCliente.id','=','PuestoEmpleado.PlantillaN_ominaCliente')
            ->join('Cat_alogoPlazaN_omina','Cat_alogoPlazaN_omina.id','=','PlantillaN_ominaCliente.Cat_alogoPlazaN_omina')
            ->join('AreasAdministrativas','AreasAdministrativas.id','=','PlantillaN_ominaCliente.AreaAdministrativa')
            ->leftjoin('ChecadorEmpleadoFotografia','ChecadorEmpleadoFotografia.idEmpleado','=',"PuestoEmpleado.id")
            ->leftjoin('CelaRepositorio','CelaRepositorio.idRepositorio','=',"ChecadorEmpleadoFotografia.idRepositorio")
            ->where('Persona.Cliente','=',$cliente)->where('PuestoEmpleado.Estatus','=','1')
            #->where('Persona.idChecadorApp','=',$checador)
            ->where('Persona.id','=',$PuestoEmpleado)
            ->get();
        if(sizeof($result)>0){
            return $result;
        }else{

            $arrayTemp = array();
            $data = [
                "idEmpleado"=>-1,
                "Nombre"=>"null",
                "Nfc_uid"=>"null",
                "idChecador"=>-1,
                "NoEmpleado"=>-1,
                "Cargo"=>"null",
                "AreaAdministrativa"=>"null",
                "NombrePlaza"=>"null",
                "Trabajador"=>"null",
                "Foto"=>"null",
            ];
            array_push($arrayTemp, $data);
            return $arrayTemp;
        }
    }
    function actualizarConfiguracion(Request $request){
        $datos = $request->all();
        $rules = [
            'cliente'=>'required',
            'idChecador'=>'required',
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>"Asegurese de completar los campo",
                'Code'=>223
            ]);
        }
        $cliente = $request->cliente;
        $idChecador = $request->idChecador;
        Funciones::selecionarBase($cliente);
        $result = DB::table('Checador')->select(DB::raw('Checador.HoraRelleno ,Checador.id,Checador.cliente as Cliente ,Checador.Nombre, Checador.Estado, Checador.UID, ChecadorSector.id AS Sector'))
                                       ->leftjoin('ChecadorSector','ChecadorSector.idChecador','=','Checador.id')
                                       ->where('Checador.Cliente','=',$cliente)
                                       ->where('Checador.id','=',$idChecador)->get();
        #$result = DB::table('Checador')->select('UID')->where('Checador.id','=',$idChecador)->where('Checador.Cliente','=',$cliente)->get();
        if(sizeof($result)>0){
            return $result;
        }else{
            $arrayTemp = array();
            $data = [
                "id"=> -1,
                "Nombre"=> "null",
                "Cliente"=> -1,
                "Estado"=> -1,
                "UID"=> "null",
                "Sector"=> -1,
                'Relleno'=> "NULL"];
                array_push($arrayTemp,$data);
            return $arrayTemp;
        }
    }
    const SLT = '4839eafada9b36e4e43c832365de12de';
    function encript($texto){
        return hash('sha256',self::SLT . $texto);
    }
     //FIN
    /*************************************************
    ** Metodos para calcular el consumo ¡NO TOCAR! **
    *************************************************/

    function ObtenConsumo($TipoToma, $Cantidad, $Cliente, $ejercicioFiscal){

        $ConfiguracionCalculo= $this->ObtenValorPorClave("CalculoAgua", $Cliente);
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

        return $this->truncateFloat($ImporteTotal,2);

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

        return $this->truncateFloat($ImporteTotal,2);

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
            return number_format($resultado, $digitos,'.','');

        }

}
