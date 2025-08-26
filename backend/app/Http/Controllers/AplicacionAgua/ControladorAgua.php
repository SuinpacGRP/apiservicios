<?php

namespace App\Http\Controllers\AplicacionAgua;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JWTAuth;
use JWTFactory;
use App\User;
use App\Cliente;
use App\Funciones;
use Validator;

class ControladorAgua extends Controller
{

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
        if($idCliente==69){
             $consulta = DB::select("SELECT pa.M_etodoCobro, pl.LecturaActual, pl.LecturaAnterior, pl.Mes, pl.A_no, pl.TipoToma, ( CONCAT_WS(' ',pa.Domicilio , 'Manzana:',if ( pa.Manzana is null, 'S/N', pa.Manzana), 'Lote:', if (pa.Lote is null, 'S/N',pa.Lote ) ) ) as Direccion , m.Nombre as Municipio, l.Nombre as Localidad, toma.Concepto AS Toma
                                    FROM Padr_onAguaPotable pa
                                    LEFT JOIN Padr_onDeAguaLectura pl on  pl.Padr_onAgua = pa.id
                                    LEFT JOIN Municipio m on m.id = pa.Municipio
                                    LEFT JOIN Localidad l on l.id = pa.Localidad
                                    LEFT JOIN TipoTomaAguaPotable toma on pa.TipoToma = toma.id
                                    WHERE pa.id = $idBusqueda
                                    ORDER BY A_no DESC , Mes DESC LIMIT 1;");
        $extraerAnomalias = DB::select("SELECT paca.*,pacal.Acci_on AS Accion, pacal.ActualizarAtras, pacal.ActualizarAdelante, pacal.Minima FROM Padr_onAguaCatalogoAnomalia paca LEFT JOIN Padr_onAguaPotableCalculoPorAnomalias pacal ON ( pacal.Anomalia = paca.clave )
        WHERE pacal.Cliente = 69 AND pacal.Estatus = 1"); //FIXME: cambiar por el id del cliente
        }else{
        $consulta = DB::select("SELECT pa.M_etodoCobro, pl.LecturaActual, pl.LecturaAnterior, pl.Mes, pl.A_no, pl.TipoToma, ( CONCAT_WS(' ',pa.Domicilio , 'Manzana:',if ( pa.Manzana is null, 'S/N', pa.Manzana), 'Lote:', if (pa.Lote is null, 'S/N',pa.Lote ) ) ) as Direccion , m.Nombre as Municipio, l.Nombre as Localidad, toma.Concepto AS Toma
                                    FROM Padr_onAguaPotable pa
                                    LEFT JOIN Padr_onDeAguaLectura pl on  pl.Padr_onAgua = pa.id
                                    LEFT JOIN Municipio m on m.id = pa.Municipio
                                    LEFT JOIN Localidad l on l.id = pa.Localidad
                                    LEFT JOIN TipoTomaAguaPotable toma on pa.TipoToma = toma.id
                                    WHERE pa.id = $idBusqueda
                                    ORDER BY pl.id DESC , A_no DESC , Mes DESC LIMIT 1;");
        $extraerAnomalias = DB::select("SELECT paca.*,pacal.Acci_on AS Accion, pacal.ActualizarAtras, pacal.ActualizarAdelante, pacal.Minima FROM Padr_onAguaCatalogoAnomalia paca LEFT JOIN Padr_onAguaPotableCalculoPorAnomalias pacal ON ( pacal.Anomalia = paca.clave )
        WHERE pacal.Cliente = 32 AND pacal.Estatus = 1"); //FIXME: cambiar por el id del cliente
        }
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
                'Mensaje'=>"OK",
            ]);
        }
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
        $consulta = "SELECT pas.id,CONCAT(pas.Sector,'-',pas.Nombre) as Sector FROM Padr_onAguaPotableSectoresLecturistas pasl
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
        if( $dAnomalia != 0){
            if($dConsumoFinal < $tipoToma[0]->Consumo){
                $dConsumoFinal = $tipoToma[0]->Consumo;
            }
            $consumoReal = $dConsumoFinal;

        }else{
            $consumoReal = $dConsumoFinal;
            if($dConsumoFinal < $tipoToma[0]->Consumo){
                $dConsumoFinal = $tipoToma[0]->Consumo;
            }
        }

        $obtenTarifa = $this->ObtenConsumo($tipoToma[0]->TipoToma, $dConsumoFinal, $dIdCliente, $dAnioCaptura);//Mensaje 223 campos incorrectos
        if($obtenTarifa == 0){
            $obtenTarifa = $dConsumoFinal;
        }
        $consulta = DB::table('Padr_onDeAguaLectura')->insert([
            'Padr_onAgua'=>$dIdToma,
            'LecturaAnterior'=>$dLecturaAnterior ,
            'LecturaActual'=> (!($dAnomalia == "2" || $dAnomalia ==  "5" || $dAnomalia ==  "24" || $dAnomalia == "28" || $dAnomalia ==  "31" || $dAnomalia ==  "40" || $dAnomalia ==  "41" || $dAnomalia ==  "45" || $dAnomalia ==  "97" || $dAnomalia ==  "98" || $dAnomalia ==  "99") && $dLecturaActual == "0") ? $dLecturaAnterior : $dLecturaActual, //FIXME: parche temporal para el error de lectura actual 0
            'Consumo'=>$consumoReal == 0 ? 20 : $consumoReal,
            'Mes'=>$dMesCaptura,
            'A_no'=>$dAnioCaptura,
            'Observaci_on'=>$dAnomalia,
            'FechaLectura'=>$fechaRegistro,
            'TipoToma'=>$tipoToma[0]->TipoToma,
            'EstadoToma' => $tipoToma[0]->Estatus,
            'Tarifa' => $obtenTarifa,
            'TipoInsert' => 3

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
        $rules = [
        'Usuario' =>'required|numeric',
        'Cliente'=>'required|numeric',
        'Mes'=>'required|numeric',
        'Anio'=>'required|'];
        $validator = Validator::make($datosRequest, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false ,
                'Mensaje' => $validator->messages()
            ]);
        }
        //creamos nuestras variables con la informacion que extraemos en el request
        $Cliente = $request->Cliente;
        $Usuario = $request->Usuario;
        $Mes = $request->Mes;
        $Anio = $request->Anio;

        //Cambiamos de base de datos a la que pertenece el cliente
        Funciones::selecionarBase($Cliente);

        //Extraemos el historial de datos
        /*$historialConsulta = DB::select("SELECT
        pr.idLectura, p.id,
        pal.FechaLectura Fecha, p.Medidor, p.ContratoVigente, TipoTomaAguaPotable.Concepto  as Toma,
        COALESCE( c.NombreComercial, CONCAT( c.Nombres,' ', c.ApellidoPaterno,' ', c.ApellidoMaterno ) ) Contribuyente FROM Padr_onAguaPotableRLecturas pr
        INNER JOIN Padr_onDeAguaLectura pal ON (pal.id=pr.idLectura)
        INNER JOIN Padr_onAguaPotable p ON (p.id=pal.Padr_onAgua)
        INNER JOIN Contribuyente c ON (c.id=p.Contribuyente)
        INNER JOIN TipoTomaAguaPotable on (TipoTomaAguaPotable.id = pal.TipoToma)
        WHERE idUsuario = $idUsuario AND pal.Mes = $Mes AND pal.A_no = $Anio");*/
        //NOTE: cambiamos por eloquent
        $historialConsulta = DB::table('Padr_onAguaPotableRLecturas as pr')->select(
                                'pr.idLectura',
                                'p.id',
                                'pal.FechaLectura as Fecha',
                                'p.Medidor',
                                'p.ContratoVigente',
                                'TipoTomaAguaPotable.Concepto  as Toma',
                                DB::raw('COALESCE( c.NombreComercial, CONCAT( c.Nombres," ", c.ApellidoPaterno," ", c.ApellidoMaterno ) ) Contribuyente'))
                                ->join('Padr_onDeAguaLectura as pal','pal.id','=','pr.idLectura')
                                ->join('Padr_onAguaPotable as p','p.id','=','pal.Padr_onAgua')
                                ->join('Contribuyente as c','c.id','=','p.Contribuyente')
                                ->join('TipoTomaAguaPotable','TipoTomaAguaPotable.id','=','pal.TipoToma')
                                ->where('idUsuario','=',$Usuario)
                                ->where('pal.Mes','=',$Mes)
                                ->where('pal.A_no','=',$Anio)->get();

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
        WHERE  pe.Estatus=1 and c.EstadoActual=1 and (pc.Cat_alogoPlazaN_omina in(72,73,318,583,297,587,602,603,626) OR c.Rol=1 OR c.idUsuario in (3800,5333, 5334, 5452,5451, 5450, 5460, 5497, 5840, 5452, 6053, 6054, 6079, 4795, 6152, 3813)) AND c.idUsuario='.$usuario);

        if($esLecturista){
            //NOTE: Veriicamos si es usuario de cortes
            //  SELECT * FROM CorteUsuarioConfiguracion W;
            //$configuracionCortes = DB::table('CorteUsuarioConfiguracion')->select('id')->where('idUsuario','=',$esLecturista[0]->idUsuario)->get();
            return response()->json([
                'Status'=> true,
                'Mensaje' => $esLecturista,
                //'Corte'=>$configuracionCortes[0]->id
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
                                            AND M_etodoCobro in(2,3)
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
        $mes = $request->mes;
        $anio = $request->a_no;
        $usuario = $request -> usuario;
        Funciones::selecionarBase($cliente);
        $cuentasPapas = Funciones::ObtenValor("SELECT GROUP_CONCAT(p.id) as CuentasPapas FROM Padr_onAguaPotable p WHERE p.id=(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1) and p.Cliente = $cliente", "CuentasPapas");
        if($cliente ==69){
            $sectoresLecturista = DB::select("SELECT GROUP_CONCAT(CAST(Sector AS INT)) as sectores, Padr_onAguaPotableSectoresLecturistas.Ruta as Ruta FROM Padr_onAguaPotableSector JOIN Padr_onAguaPotableSectoresLecturistas ON ( Padr_onAguaPotableSector.id = Padr_onAguaPotableSectoresLecturistas.idSector ) WHERE idLecturista = $usuario");
            $result = DB::select('SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,Estatus,
                                (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
                                JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
                                WHERE Cliente = '.$cliente.'
                                AND ( ContratoVigente LIKE "%'.$contrato.'" or ContratoVigente = '.intval($contrato).')
                                AND Padr_onAguaPotable.Cliente = '.$cliente.'
                                AND Padr_onAguaPotable.Ruta in('.$sectoresLecturista[0]->Ruta.')
                                AND Estatus in(1,2,10) AND Padr_onAguaPotable.Sector IN ( '.( $sectoresLecturista[0]->sectores == "" ? "0" : $sectoresLecturista[0]->sectores ).')');
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
        $mes = $request->mes;
        $anio = $request->a_no;
        $usuario = $request->usuario;
        Funciones::selecionarBase($cliente);
        $cuentasPapas = Funciones::ObtenValor("SELECT GROUP_CONCAT(p.id) as CuentasPapas FROM Padr_onAguaPotable p WHERE p.id=(SELECT p2.CuentaPapa FROM Padr_onAguaPotable p2 WHERE p2.CuentaPapa=p.id limit 1) and p.Cliente = $cliente", "CuentasPapas");
        $sectoresLecturista = DB::select("SELECT GROUP_CONCAT(CAST(Sector AS INT)) as sectores FROM Padr_onAguaPotableSector JOIN Padr_onAguaPotableSectoresLecturistas ON ( Padr_onAguaPotableSector.id = Padr_onAguaPotableSectoresLecturistas.idSector ) WHERE idLecturista = $usuario");
        $result = DB::select('SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,Estatus,
                            (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
                            JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
                            WHERE Cliente = '.$cliente.'
                            AND (Medidor LIKE "%'.$contrato.'" or Medidor = '.intval($contrato).')
                            AND Padr_onAguaPotable.Cliente = '.$cliente.'
                            AND Padr_onAguaPotable.id NOT IN ( SELECT Padr_onAgua FROM Padr_onDeAguaLectura WHERE Mes = '.$mes.' AND A_no = '.$anio.' )
                            AND Estatus in(1,2,10)  AND Padr_onAguaPotable.Sector IN ( '.( $sectoresLecturista[0]->sectores == "" ? "0" : $sectoresLecturista[0]->sectores ).')');

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
    public function MultarToma(Request $request){
        $Padron=$request->Padron;
        $Cliente = $request->Cliente;
        $Motivo = $request->Motivo;
        $ejercicioFiscal = $request->Ejercicio;
        $Persona = $request->Persona;
        #$FechaTupla = $request->FechaTupla; //NOTE: se calcula en el API
        $Usuario = $request->Usuario;
        $Estatus = $request->Estado;
        $Latitud = $request->Latitud;
        $Longitud = $request->Longitud;
        $Evidencia = $request->Evidencia;
        $FechaTupla = date('Y-m-d H:i:s');
        $FechaMulta = date('Y-m-d');
        Funciones::selecionarBase($Cliente);
        

        $insertarMulta = DB::table('Padr_onAguaPotableMultas')
                            ->insertGetId([
                                'id'=>null,
                                'Padr_on'=>$Padron,
                                'Motivo'=>$Motivo,
                                'Persona'=>$Persona,
                                'FechaTupla'=>$FechaTupla,
                                'Usuario'=>$Usuario,
                                'Estatus'=>10,
                                'FechaMulta'=>$FechaMulta,
                                'Evidencia'=>'',
                                'Latitud'=>$Latitud,
                                'Longitud'=>$Longitud
                                
                            ]);
                            
        if($insertarMulta){

            $actualizarEstatus = DB:: table('Padr_onAguaPotable')
            ->where('id', $Padron)
            ->update(['Estatus'=>10]);

            if($actualizarEstatus){
                //NOTE: Ingresamos los datos de la toma por separado
            $idfotoToma = $this->SubirImagenV3($Evidencia['Toma'],$insertarMulta,$Cliente,$Usuario,"FotoHistorialMulta","Toma");
            $idFotoFachada = $this->SubirImagenV3($Evidencia['Fachada'],$insertarMulta,$Cliente,$Usuario,"FotoHistorialMulta","Fachada");
            $idFotoCalle = $this->SubirImagenV3($Evidencia['Calle'],$insertarMulta,$Cliente,$Usuario,"FotoHistorialMulta","Calle");
            //NOTE: Creamos el el objeto que se va a guardar
            $arregloFotosCela = array("Toma"=>$idfotoToma, "Fachada"=>$idFotoFachada, "Calle"=>$idFotoCalle);
            //$idsRepo =  $this->SubirImagenV2($Evidencia,$respuestaObjeto->Corte,$Cliente,$Usuario,"FotoHistorialCorte");
            $actualizarDatos = DB:: table('Padr_onAguaPotableMultas')
                            ->where('id', $insertarMulta)
                            ->update(['Evidencia'=>$arregloFotosCela]);
            //NOTE: actualizamos el estatus de las tareas
            if($actualizarDatos){
                return [
                    'Status'=> true,
                    'Mensaje'=>"Multa realizada...",
                    'Code'=>200
                ];
            }else{
                return [
                    'Status'=> false,
                    'Mensaje'=>"Multa realizada...",
                    'Code'=>200
                ];
            }
            }
            
        }else{
            return [
                'Status'=> false,
                'Mensaje'=>"Multa realizada...",
                'Code'=>200
            ];
    
        }
       

    }
    public function InspeccionarToma(Request $request){
        $Padron=$request->Padron;
        $Cliente = $request->Cliente;
        $Motivo = $request->Motivo;
        $ejercicioFiscal = $request->Ejercicio;
        $Persona = $request->Persona;
        #$FechaTupla = $request->FechaTupla; //NOTE: se calcula en el API
        $Usuario = $request->Usuario;
        $Estatus = $request->Estado;
        $Latitud = $request->Latitud;
        $Longitud = $request->Longitud;
        $Evidencia = $request->Evidencia;
        $FechaTupla = date('Y-m-d H:i:s');
        $FechaMulta = date('Y-m-d');
        Funciones::selecionarBase($Cliente);
        $insertarMulta = DB::table('Padr_onAguaPotableInspecciones')
                            ->insertGetId([
                                'id'=>null,
                                'Padr_on'=>$Padron,
                                'Motivo'=>$Motivo,
                                'Persona'=>$Persona,
                                'FechaTupla'=>$FechaTupla,
                                'Usuario'=>$Usuario,
                                'Estatus'=>10,
                                'FechaMulta'=>$FechaMulta,
                                'Evidencia'=>'',
                                'Latitud'=>$Latitud,
                                'Longitud'=>$Longitud
                                
        ]);

        if($insertarMulta){
        //NOTE: Ingresamos los datos de la toma por separado
        $idfotoToma = $this->SubirImagenV3($Evidencia['Toma'],$insertarMulta,$Cliente,$Usuario,"FotoHistorialInspeccion","Toma");
        $idFotoFachada = $this->SubirImagenV3($Evidencia['Fachada'],$insertarMulta,$Cliente,$Usuario,"FotoHistorialInspeccion","Fachada");
        $idFotoCalle = $this->SubirImagenV3($Evidencia['Calle'],$insertarMulta,$Cliente,$Usuario,"FotoHistorialInspeccion","Calle");
        //NOTE: Creamos el el objeto que se va a guardar
        $arregloFotosCela = array("Toma"=>$idfotoToma, "Fachada"=>$idFotoFachada, "Calle"=>$idFotoCalle);
        //$idsRepo =  $this->SubirImagenV2($Evidencia,$respuestaObjeto->Corte,$Cliente,$Usuario,"FotoHistorialCorte");
        $actualizarDatos = DB:: table('Padr_onAguaPotableInspecciones')
        ->where('id', $insertarMulta)
        ->update(['Evidencia'=>$arregloFotosCela]);

        if($actualizarDatos){
            return [
                'Status'=> true,
                'Mensaje'=>"Multa realizada...",
                'Code'=>200
            ];
        }else{
            return [
                'Status'=> false,
                'Mensaje'=>"Multa realizada...",
                'Code'=>200
            ];
        }
        
        }
       
    }
    public function InstalarToma(Request $request){
        $Padron=$request->Padron;
        $Cliente = $request->Cliente;
        $Motivo = $request->Motivo;
        $ejercicioFiscal = $request->Ejercicio;
        $Persona = $request->Persona;
        #$FechaTupla = $request->FechaTupla; //NOTE: se calcula en el API
        $Usuario = $request->Usuario;
        $Estatus = $request->Estado;
        $Latitud = $request->Latitud;
        $Longitud = $request->Longitud;
        $Evidencia = $request->Evidencia;
        $FechaTupla = date('Y-m-d H:i:s');
        $FechaMulta = date('Y-m-d');
        Funciones::selecionarBase($Cliente);
        $insertarMulta = DB::table('Padr_onAguaPotableInstalaciones')
                            ->insertGetId([
                                'id'=>null,
                                'Padr_on'=>$Padron,
                                'Motivo'=>$Motivo,
                                'Persona'=>$Persona,
                                'FechaTupla'=>$FechaTupla,
                                'Usuario'=>$Usuario,
                                'Estatus'=>10,
                                'FechaMulta'=>$FechaMulta,
                                'Evidencia'=>'',
                                'Latitud'=>$Latitud,
                                'Longitud'=>$Longitud
                                
        ]);

        if($insertarMulta){
        //NOTE: Ingresamos los datos de la toma por separado
        $idfotoToma = $this->SubirImagenV3($Evidencia['Toma'],$insertarMulta,$Cliente,$Usuario,"FotoHistorialInstalacion","Toma");
        $idFotoFachada = $this->SubirImagenV3($Evidencia['Fachada'],$insertarMulta,$Cliente,$Usuario,"FotoHistorialInstalacion","Fachada");
        $idFotoCalle = $this->SubirImagenV3($Evidencia['Calle'],$insertarMulta,$Cliente,$Usuario,"FotoHistorialInstalacion","Calle");
        //NOTE: Creamos el el objeto que se va a guardar
        $arregloFotosCela = array("Toma"=>$idfotoToma, "Fachada"=>$idFotoFachada, "Calle"=>$idFotoCalle);
        //$idsRepo =  $this->SubirImagenV2($Evidencia,$respuestaObjeto->Corte,$Cliente,$Usuario,"FotoHistorialCorte");
        $actualizarDatos = DB:: table('Padr_onAguaPotableInstalaciones')
        ->where('id', $insertarMulta)
        ->update(['Evidencia'=>$arregloFotosCela]);

        if($actualizarDatos){
            return [
                'Status'=> true,
                'Mensaje'=>"Instalacion realizada...",
                'Code'=>200
            ];
        }else{
            return [
                'Status'=> false,
                'Mensaje'=>"Instalacion realizada...",
                'Code'=>200
            ];
        }
        
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
                //NOTE: Ingresamos los datos de la toma por separado
                $idfotoToma = $this->SubirImagenV3($Evidencia['Toma'],$respuestaObjeto->Corte,$Cliente,$Usuario,"FotoHistorialCorte","Toma");
                $idFotoFachada = $this->SubirImagenV3($Evidencia['Fachada'],$respuestaObjeto->Corte,$Cliente,$Usuario,"FotoHistorialCorte","Fachada");
                $idFotoCalle = $this->SubirImagenV3($Evidencia['Calle'],$respuestaObjeto->Corte,$Cliente,$Usuario,"FotoHistorialCorte","Calle");
                //NOTE: Creamos el el objeto que se va a guardar
                $arregloFotosCela = array("Toma"=>$idfotoToma, "Fachada"=>$idFotoFachada, "Calle"=>$idFotoCalle);
                //$idsRepo =  $this->SubirImagenV2($Evidencia,$respuestaObjeto->Corte,$Cliente,$Usuario,"FotoHistorialCorte");
                $actualizarDatos = DB:: table('Padr_onAguaPotableCorteUbicacion')
                                ->where('idAguaPotableCorte', $respuestaObjeto->Corte)
                                ->update(['Evidencia'=>$arregloFotosCela]);
                //NOTE: actualizamos el estatus de las tareas
                $actualiarEstatus = DB::table('CorteUsuarioTarea')
                                        ->where('Padr_on','=',$Padron)
                                        ->where('Estatus','=',1)
                                        ->update(['Estatus'=>0]);

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
                'Mensaje'=>$jsonRespuesta,
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

    public function crearReporteV3(Request $request){
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


        //NOTE: insertamos las imagenes
        //INDEV: cambiamos los datos a cuota fija
        $idfotoToma = $this->SubirImagenV3($Fotos['Toma'],$idReporte,$Cliente,$Usuario);
        $idFotoFachada = $this->SubirImagenV3($Fotos['Fachada'],$idReporte,$Cliente,$Usuario);
        $idFotoCalle = $this->SubirImagenV3($Fotos['Calle'],$idReporte,$Cliente,$Usuario);
        $arregloFotosCela = array("Toma"=>$idfotoToma, "Fachada"=>$idFotoFachada, "Calle"=>$idFotoCalle);
        //INDEV: actualizamos el campo de fotos en la tabla de reporte
        $actualizarDatos = DB::table('Padr_onAguaPotableReportes')->where('id', $idReporte)->update([
            'Fotos'=>json_encode($arregloFotosCela)
        ]);
        if( $actualizarDatos ){
            return [
                'Status'=>true,
                'Code'=>200,
                'Mensaje'=>$idReporte,
            ];
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
        $configuracion = DB::table('ClienteDatos')->select('Valor')->where('Indice','=','Activar Cuota Fija')->where('Cliente','=',$Cliente)->get();
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
    public function GuardarLecturaCuotaFija(Request $request){
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
            $consumoReal=$Consumo;
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

                //INDEV: cambiamos los datos a cuota fija
                $idfotoToma = $this->SubirImagenV3($fotos['Toma'],$idLectura,$Cliente,$usuario);
                $idFotoFachada = $this->SubirImagenV3($fotos['Fachada'],$idLectura,$Cliente,$usuario);
                $idFotoCalle = $this->SubirImagenV3($fotos['Calle'],$idLectura,$Cliente,$usuario);
                $arregloFotosCela = array("Toma"=>$idfotoToma, "Fachada"=>$idFotoFachada, "Calle"=>$idFotoCalle);

                //INDEV: insertamo en padr_onRlectura
                $fechaRegistroCela =date('y-m-d H:i:s');
                $guadarusaurioLectura = DB::table('Padr_onAguaPotableRLecturas')->insertGetId([
                    'idLectura'=> $idLectura,
                    'idUsuario'=> $usuario,
                    'Padr_onAgua'=> $padronRequest,
                    'longitud' => $longitud,
                    'latitud' => $longitud,
                    'fotos'=>json_encode($arregloFotosCela),
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
    public function obtenerConfiguracionEvidencia( Request $request ){
        $data = $request->all();
        $Rules = [
            "Cliente"=>"required|string"
        ];
        $Cliente = $request->Cliente;
        $validator = Validator::make($data, $Rules);
        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => 'Error',
                'Error' => $validator->messages(),
            ]);
        }
        //NOTE: obtenemos los datos de la configuracion (SELECT * FROM  ClienteDatos WHERE Cliente = 40 AND Indice = "InsercionAsistencias";)
        $Condfiguracion = DB::table('ClienteDatos')->select('Valor')
                                ->where('Cliente','=',$Cliente)
                                ->where('Indice','=',"ConfiguracionFotoAppAgua")->get();

        if($Condfiguracion){
            return [
                'Status'=> true,
                'Mensaje'=>$Condfiguracion[0]->Valor,
                'Code'=>200,
            ];
        }else{
            return [
                'Status'=> true,
                'Mensaje'=>"0",
                'Code'=>403,
            ];
        }
    }
    //Metodos para guardar la lectura con orden

    public function guardarLecturaV3(Request $request){
        // {"idToma":173907,"cliente":"32","lecturaAnterior":40,"lecturaActual":64,"consumoFinal":24,"mesCaptura":1,"anhioCaptura":2023,"fechaCaptura":"Mon Jan 16 2023 13:47:29 GMT-0600 (hora estándar central)","anomalia":"NaN","idUsuario":"3813","latitud":"17.566802","longitud":"-99.5085438","tipoCoordenada":1,"fotos":[],"arregloFotos":{"Calle":"data:image/jpeg;base64,/9j/4QS0RXhpZgAATU0AKgAAAAgADwEDAAMAAAABAAYAAAIBAAQAAAABAAAEHAICAAQAAAABAABBGQEBAAMAAAABC7gAAAEPAAIAAAAIAAAAwgESAAMAAAABAAEAAAEyAAIAAAAUAAAAyoglAAQAAAABAAADHwEbAAUAAAABAAAA3gEaAAUAAAABAAAA5gEAAAMAAAABD6AAAAEQAAIAAAAKAAAA7gITAAMAAAABAAEAAIdpAAQAAAABAAAA+AEoAAMAAAABAAIAAAAAAABzYW1zdW5nADIwMjM6MDE6MTMgMTA6NTc6NDYAAAAASAAAAAEAAABIAAAAAVNNLUc5ODhVMQAAIZICAAUAAAABAAACipAAAAIAAAAFAAACkqMBAAIAAAACMQAAAJIEAAoAAAABAAACl4giAAMAAAABAAIAAKABAAMAAAABAAEAAJIFAAUAAAABAAACn6ADAAMAAAABC7gAAJIDAAoAAAABAAACp5ADAAIAAAAUAAACr6AAAAIAAAAFAAACw5KRAAIAAAAHAAACyKQDAAMAAAABAAAAAKAFAAQAAAABAAADsKQCAAMAAAABAAAAAIKaAAUAAAABAAACz5AQAAIAAAAHAAAC15IJAAMAAAABAAAAAJKQAAIAAAAHAAAC3oKdAAUAAAABAAAC5aACAAMAAAABD6AAAJEBAAIAAAAEPz8/AIgnAAMAAAABAPoAAKQFAAMAAAABABkAAJKSAAIAAAAHAAAC7ZAEAAIAAAAUAAAC9JIBAAoAAAABAAADCJIKAAUAAAABAAADEJIHAAMAAAABAAIAAJARAAIAAAAHAAADGKQGAAMAAAABAAAAAJIIAAMAAAABAAAAAKIXAAMAAAABAAEAAAAAAAAAAACpAAAAZDAyMjAAAAAAAAAAAAoAAACpAAAAZAAAAIEAAABkMjAyMzowMToxMyAxMDo1Nzo0NgAwMTAwADA3Njg5OQAAAACmAAAnEC0wNjowMAAwNzY4OTkAAABGUAAAJxAwNzY4OTkAMjAyMzowMToxMyAxMDo1Nzo0NgAAABcSAAAD6AAAG1gAAAPoLTA2OjAwAAAGAAYABQAAAAEAAANtAAUAAQAAAAEAAAAAAAMAAgAAAAJFAAAAAAcABQAAAAMAAAN1AB0AAgAAAAsAAAONAAQABQAAAAMAAAOYAAAAAAAAAAAAAAPoAAAAAAAAAAEAAAAAAAAAAQAAAAAAAAABMTk3MDowMTowMQAAAAAAAAAAAQAAAAAAAAABAAAAAAAAJxAAAQABAAIAAAAEUjk4AAAAAAAADQEDAAMAAAABAAYAAAIBAAQAAAABAAAEHAICAAQAAAABAABBGQEBAAMAAAABC7gAAAEPAAIAAAAIAAAEZAESAAMAAAABAAEAAAEyAAIAAAAUAAAEbAEbAAUAAAABAAAEgAEaAAUAAAABAAAEiAEAAAMAAAABD6AAAAEQAAIAAAAKAAAEkAITAAMAAAABAAEAAAEoAAMAAAABAAIAAAAAAABzYW1zdW5nADIwMjM6MDE6MTMgMTA6NTc6NDYAAAAASAAAAAEAAABIAAAAAVNNLUc5ODhVMQAAAQA3AAMAAAABAAEAAAAAAAD/4AAQSkZJRgABAQAAAQABAAD/4gIoSUNDX1BST0ZJTEUAAQEAAAIYAAAAAAIQAABtbnRyUkdCIFhZWiAAAAAAAAAAAAAAAABhY3NwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAA9tYAAQAAAADTLQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAlkZXNjAAAA8AAAAHRyWFlaAAABZAAAABRnWFlaAAABeAAAABRiWFlaAAABjAAAABRyVFJDAAABoAAAAChnVFJDAAABoAAAAChiVFJDAAABoAAAACh3dHB0AAAByAAAABRjcHJ0AAAB3AAAADxtbHVjAAAAAAAAAAEAAAAMZW5VUwAAAFgAAAAcAHMAUgBHAEIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAFhZWiAAAAAAAABvogAAOPUAAAOQWFlaIAAAAAAAAGKZAAC3hQAAGNpYWVogAAAAAAAAJKAAAA+EAAC2z3BhcmEAAAAAAAQAAAACZmYAAPKnAAANWQAAE9AAAApbAAAAAAAAAABYWVogAAAAAAAA9tYAAQAAAADTLW1sdWMAAAAAAAAAAQAAAAxlblVTAAAAIAAAABwARwBvAG8AZwBsAGUAIABJAG4AYwAuACAAMgAwADEANv/bAEMAKBweIx4ZKCMhIy0rKDA8ZEE8Nzc8e1hdSWSRgJmWj4CMiqC05sOgqtqtiozI/8va7vX///+bwf////r/5v3/+P/bAEMBKy0tPDU8dkFBdviljKX4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+P/AABEIAfQBdwMBIgACEQEDEQH/xAAaAAADAQEBAQAAAAAAAAAAAAAAAQIDBAUG/8QAJBABAQACAgIDAQEBAQEBAAAAAAECERIxAyETQVEEFCJhQjL/xAAXAQEBAQEAAAAAAAAAAAAAAAAAAQID/8QAGhEBAQEBAQEBAAAAAAAAAAAAABEBEgIhMf/aAAwDAQACEQMRAD8A5B2dhdRybFRVUvoCIwoAYiAv4jK/S71tEntQ5PSp6EIAAABXoyoCAyAgAAAANPBjvLenXpl/PjrHbZvHPf1I+zJUIGQAjAFojAEAABGAIwAIGAIGAABgWhowBAwDGinZtNcnVP2YK0AAQHPYpz0VBOVEnou6tQAGBFo6ECACgIwBDRgCok3ZBV+HHlmYa6vHjrHSz1oOjkklFQLRaMARGAIAAQMgH0RgCMAARgAAAAMAADAgADK9IrTJnXJ1LLpK8okBAPsaAfZZX6PpPdUOQ50cOoJB6H2ApQDQEYEAgYAgKAJ0/wA2P253b4ceODXln1+NCMNsJBkBFpRARKICBkBAwBEY0BAwBAwBGAAAMAAYEDAMaiTdXS6c3VOSVX2VQI9FO1W6gIzKC7tOT0ocB6CBUEoC6I7ACac6KnAAAAgZArx47zjvxmo5P58d57drflj0RKJpkiMARGASDICBgEg9ACIwBAwBAwBGAABgAAYEDAMU0yc3UJqr2WgSMuhZpO/YCQ4VsPGzQGVGwgAc9AC2OgVAqePQP6AgYAgB9g3/AJf/ANuxxfy3Xkdrp5/GPX6QMKykGQEDICLSisAiUQESiAgYAgYAgeiAAwBAwABgCBgHPYPpGXkk+2OXmYzHStrlIi+WRhc7RMcqsxKu+VPMTx+2nw6Lgz5Dk1nihXxFwjOZrxzK+KxFxsPmn1vM5Vbjl3YqZ2JyV0EznkXjlKkaPRwjiBAwKkHZohFePLj5JXo43eMry3b/ADeXeOr235Z9Y6CMNMERgCIxoCJRARKIC0RgCBgCI9ACBjQEegYENGALRmAIGA

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
        if($request->cliente!=69 && $request->cliente != 32){
            $validator = Validator::make($datos, $rules);
            if ($validator->fails()) {
                return response()->json([
                    'Status' => false,
                    'Mensaje' => 223 //Mensaje 223 campos incorrectos
                ]);
            }
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
        //$fotos = $request->fotos;
        $arregloFotos = $request->arregloFotos;
        $ruta = $request->ruta;
        $arregloNombre = array();
        $arregloSize = array();
        $arregloRuta = array();

        Funciones::selecionarBase($dIdCliente); //antes de entregar debes colocar $dIdCliente dentro del metodo
        if ($dIdCliente == 69) {
            if ($dAnomalia == null) {
                $dAnomalia = 0;
            }
            if ($dConsumoFinal == null) {
                $dConsumoFinal = 0;
            }
            if ($dLecturaAnterior == 0) {
                $ultimaLectura = DB::select("SELECT LecturaActual as LecturaActual, id as id FROM Padr_onDeAguaLectura WHERE Padr_onAgua = $dIdToma AND LecturaActual!=0.00 ORDER BY A_no DESC, Mes DESC LIMIT 1");
                if($ultimaLectura){
                    $mesito = $ultimaLectura[0]->id;
                    $numMeses = DB::select("SELECT count(*) as Meses FROM Padr_onDeAguaLectura WHERE Padr_onAgua = $dIdToma and id>$mesito");
                    if ($ultimaLectura[0]->LecturaActual != "") {
                        $dLecturaAnterior = $ultimaLectura[0]->LecturaActual;
                        $dConsumoFinal = intval(($dLecturaActual - $dLecturaAnterior) / $numMeses[0]->Meses);
                    }
                }else{
                    $dLecturaAnterior=0.00;
                }

            }
        }

        $mesBD = DB::select("SELECT MAX(Mes) as Mes, id FROM Padr_onDeAguaLectura  WHERE Padr_onAgua = $dIdToma AND A_no = $dAnioCaptura");
        if($dIdCliente!=69){
            if ($mesBD[0]->Mes == $dMesCaptura) {
                return response()->json([
                    'Status' => false,
                    'Mensaje' => 400   //mensaje 224 error al intentar guardar los datos
                ]);
            }
        }

        $tipoToma = DB::select("SELECT TipoToma, Estatus, Consumo, M_etodoCobro FROM Padr_onAguaPotable WHERE id=$dIdToma");

        //REVIEW: se cambio por el consumo 20 en caso de que el consumo sea menor a 20

        //INDEV: Cambio por la nueva tarifa consumo ( todas las tomas en promedio y de consumo se cambia con un consumo munimo 20 )
            if($dIdCliente==32){
            $consumoReal = $dConsumoFinal;
                if($dAnomalia!=31 && $dAnomalia!=47 && $dAnomalia!=99 && $dAnomalia!="" && $dAnomalia!=0 && $dAnomalia!=100 && $dAnomalia != 41 && $dAnomalia !=5 && $dAnomalia!=24){
                $tipo = $tipoToma[0]->TipoToma;
                //$numMeses = DB::select('SELECT * FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ' . $dIdToma . ' ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12');
                $numMeses = DB::select('SELECT COUNT(*) as num_registros FROM (SELECT * FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ? ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12) as subquery', [$dIdToma]);
                if($numMeses[0]->num_registros == 0){
                    $consumoReal = 20.00;
                }else{
                    if ($numMeses[0]->num_registros > 12) {
                        $promedio12 = DB::select('SELECT ROUND(SUM(Consumo)/12) as Promedio FROM ( SELECT pt.Consumo FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ' . ($dIdToma) . ' ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12 ) AS A');
                    } else {
                        $promedio12 = DB::select('SELECT ROUND(SUM(Consumo)/' . $numMeses[0]->num_registros . ') as Promedio FROM ( SELECT pt.Consumo FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ' . ($dIdToma) . ' ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12 ) AS A');
                    }
                    if ($dConsumoFinal <= ($promedio12[0]->Promedio)) {
                        $dConsumoFinal = $promedio12[0]->Promedio;
                    }

                    if ($dConsumoFinal <= 20) {
                        $consumoReal = 20.00;
                    } else {
                        $consumoReal = $dConsumoFinal;
                    }
                }
                //Este codigo se comentó porque se hizo una nueva implementación arriba para calcular el consumo de las lecturas
                //que tienen anomalía
                /*$mesAnterior = DB::select("SELECT Observaci_on as Observacion, Consumo as Consumo, A_no as A_no, Mes as Mes FROM Padr_onDeAguaLectura WHERE Padr_onAgua=$dIdToma ORDER BY A_no DESC, Mes DESC LIMIT 1");
                if (!$mesAnterior) {
                    if (($dLecturaActual - $dLecturaAnterior) > 20) {
                        $consumoReal = $dLecturaActual - $dLecturaAnterior;
                    } else {
                        $consumoReal = 20.00;
                    }
                }else{
                if($mesAnterior[0]->Observacion==NULL || $mesAnterior[0]->Observacion == 0 || $mesAnterior[0]->Observacion==""){
                    if($dLecturaActual-$dLecturaAnterior<=20){
                        $consumoReal = 20.00;
                    }else{
                        $consumoReal=$dLecturaActual-$dLecturaAnterior;
                    }
                }else{
                    $consumoReal=$mesAnterior[0]->Consumo;
                    if ($consumoReal >= 20) {
                        $consumoReal = $this->truncateFloat($consumoReal, 2);
                    } else {
                        $consumoReal = 20.00;
                    }
                }
                }*/
                }else{
                //Primero Validamos si han seleccionado la anomalia 31 en la aplicacion
                if($dAnomalia == 31){

                    //Sacamos la sumatoria de los consumos que tienen anomalia de las ultimas 12 lecturas que se tomaron para ese contrato
                    $sumaConsumos = DB::select('SELECT ROUND(SUM(Consumo)) as Promedio FROM ( SELECT pt.Consumo FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ' . ($dIdToma) . ' and (pt.Observaci_on!=0 or pt.Observaci_on!=null) ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12 ) AS A');
                    $consumido=$dLecturaActual-$dLecturaAnterior;
                    if($consumido<=0){
                        $consumoReal = $tipoToma[0]->Consumo;
                    }else{
                    //Validamos si la sumatoria de los consumos es mayor que el consumo real, si es así promediamos el consumo mediante el siguiente código
                    if (($sumaConsumos[0]->Promedio) > $consumido) {
                        $numMeses = DB::select('SELECT COUNT(*) as num_registros FROM (SELECT * FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ? ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12) as subquery', [$dIdToma]);
                        if ($numMeses[0]->num_registros == 0) {
                            $consumoReal = $tipoToma[0]->Consumo;
                        } else {
                            if ($numMeses[0]->num_registros >= 12) {
                                $promedio12 = DB::select('SELECT ROUND(SUM(Consumo)/12) as Promedio FROM ( SELECT pt.Consumo FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ' . ($dIdToma) . ' ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12 ) AS A');
                            } else {
                                $promedio12 = DB::select('SELECT ROUND(SUM(Consumo)/' . $numMeses[0]->num_registros . ') as Promedio FROM ( SELECT pt.Consumo FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ' . ($dIdToma) . ' ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12 ) AS A');
                            }
                            $dConsumoFinal = $promedio12[0]->Promedio;
                            $consumoTipoToma = $tipoToma[0]->Consumo;
                            if ($dConsumoFinal <= $consumoTipoToma) {
                                $dConsumoFinal = $consumoTipoToma;
                            }
                            $consumoReal = $dConsumoFinal;
                        }
                    } else {
                        if ($sumaConsumos[0]->Promedio > 0) {
                            //Creamos una variable para sacar la diferencia del consumo real de la toma, menos la sumatoria de los consumos(modificacion pedida por Doña Martha, CAPAZ 2023)
                            $diferenciaConsumos = $consumoReal - $sumaConsumos[0]->Promedio;
                            //Si la sumatoria de los consumos no es mayor al consumo real realizamos lo siguiente:
                            //Asignamos a la variable consumoTipoToma el consumo del tipo de toma obtenido en la consulta anterior
                            $consumoTipoToma = $tipoToma[0]->Consumo;

                            //Validamos si la diferencia de los consumos obtenida anteriormente es menor al consumo del tipo de toma
                            if ($diferenciaConsumos <= $consumoTipoToma) {
                                //El consumo calculado será el consumo del tipo de toma
                                $consumoReal = $consumoTipoToma;
                            } else {
                                //En caso contrario el consumo calculado será la diferencia obtenida anteriormente
                                $consumoReal = $diferenciaConsumos;
                            }
                        } else {
                            $consumoReal = $tipoToma[0]->Consumo;
                        }
                    }
                    }
                }else if($dAnomalia == 99){
                    if($dLecturaActual == $dLecturaAnterior){
                        $numMeses = DB::select('SELECT COUNT(*) as num_registros FROM (SELECT * FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ? ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12) as subquery', [$dIdToma]);
                        if ($numMeses[0]->num_registros > 12) {
                            $promedio12 = DB::select('SELECT ROUND(SUM(Consumo)/12) as Promedio FROM ( SELECT pt.Consumo FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ' . ($dIdToma) . ' ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12 ) AS A');
                        } else {
                            $promedio12 = DB::select('SELECT ROUND(SUM(Consumo)/' . $numMeses[0]->num_registros . ') as Promedio FROM ( SELECT pt.Consumo FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ' . ($dIdToma) . ' ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12 ) AS A');
                        }
                        $dConsumoFinal = $promedio12[0]->Promedio;
                        $consumoTipoToma = $tipoToma[0]->Consumo;
                        if ($dConsumoFinal <= $consumoTipoToma) {
                            $dConsumoFinal = $consumoTipoToma;
                        }
                        $consumoReal = $dConsumoFinal;
                        $dLecturaActual=0;
                    }else{
                        $dConsumoFinal=$dLecturaActual;
                        $consumoTipoToma = $tipoToma[0]->Consumo;
                        if ($dConsumoFinal <= $consumoTipoToma) {
                            $dConsumoFinal = $consumoTipoToma;
                        }
                        $consumoReal = $dConsumoFinal;
                    }

                }else if($dAnomalia == 100){
                    $consumoTipoToma = $tipoToma[0]->Consumo;
                    $consumoReal = $consumoTipoToma;
                }else if($dAnomalia == 41){
                    $consumoReal=$dLecturaActual-$dLecturaAnterior;
                    $id= DB::select('SELECT GROUP_CONCAT(id) as id FROM Padr_onAguaPotable where CuentaPapa='. $dIdToma);
                    $sumaConsumos = DB::select('SELECT ROUND(SUM(Consumo)) as consumo FROM Padr_onDeAguaLectura where Padr_onAgua in('.$id[0]->id.') and Mes='.$dMesCaptura.' and A_no='.$dAnioCaptura);
                    $diferenciaConsumos=$consumoReal-($sumaConsumos[0]->consumo);
                    $consumoReal=$diferenciaConsumos;
                }else if($dAnomalia == 47){
                    $consumoReal = $dConsumoFinal;
                    if($consumoReal<=20){
                        $consumoReal=20.00;
                    }
                }else if ($dAnomalia == "" || $dAnomalia == 0) {
                    $consumoTipoToma = $tipoToma[0]->Consumo;
                    if ($dConsumoFinal <= $consumoTipoToma) {
                        $consumoReal = $tipoToma[0]->Consumo;
                    }else{
                        $consumoReal= $dConsumoFinal;
                    }
                }else if($dAnomalia == 5 || $dAnomalia == 24){
                    $numMeses = DB::select('SELECT COUNT(*) as num_registros FROM (SELECT * FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ? ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12) as subquery', [$dIdToma]);
                    if ($numMeses[0]->num_registros >= 12) {
                        $promedio12 = DB::select('SELECT ROUND(SUM(Consumo)/12) as Promedio FROM ( SELECT pt.Consumo FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ' . ($dIdToma) . ' ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12 ) AS A');
                    } else {
                        $promedio12 = DB::select('SELECT ROUND(SUM(Consumo)/' . $numMeses[0]->num_registros . ') as Promedio FROM ( SELECT pt.Consumo FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ' . ($dIdToma) . ' ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12 ) AS A');
                    }
                    $dConsumoFinal = $promedio12[0]->Promedio;
                    if($dConsumoFinal<=20){
                        $dConsumoFinal=20.00;
                    }
                    $consumoReal = $dConsumoFinal;
                }else{
                $tipo = $tipoToma[0]->TipoToma;
                    $mesAnterior = DB::select("SELECT Observaci_on as Observacion, Consumo as Consumo, A_no as A_no, Mes as Mes FROM Padr_onDeAguaLectura WHERE Padr_onAgua=$dIdToma ORDER BY A_no DESC, Mes DESC LIMIT 1");
                    if(!$mesAnterior){
                        if(($dLecturaActual-$dLecturaAnterior)>$tipoToma[0]->Consumo){
                            $consumoReal= $dLecturaActual - $dLecturaAnterior;
                        }else{
                            $consumoReal = $tipoToma[0]->Consumo;
                        }
                    }else{
                    if ($mesAnterior[0]->Observacion == NULL || $mesAnterior[0]->Observacion == 0 || $mesAnterior[0]->Observacion == "") {
                        if($dLecturaActual-$dLecturaAnterior< $tipoToma[0]->Consumo){
                        $consumoReal = $tipoToma[0]->Consumo;
                        }else{
                            $consumoReal= $dLecturaActual - $dLecturaAnterior;
                        }
                        if($consumoReal<$mesAnterior[0]->Consumo && $mesAnterior[0]->A_no==2023 && $mesAnterior[0]->Mes == ($dMesCaptura-1)){
                            $consumoReal= $mesAnterior[0]->Consumo;
                        }
                    } else {
                        $consumoReal = $mesAnterior[0]->Consumo;
                        if ($consumoReal >= $tipoToma[0]->Consumo) {
                            $consumoReal = $this->truncateFloat($consumoReal, 2);
                        } else {
                            $consumoReal = $tipoToma[0]->Consumo;
                        }
                    }
                }
                }
                }
            }else{
            $consumoReal = $dConsumoFinal;
            if ($dConsumoFinal < $tipoToma[0]->Consumo) {
                $consumoReal = $tipoToma[0]->Consumo;
            }
            }


        $obtenTarifa = $this->ObtenConsumo($tipoToma[0]->TipoToma, $consumoReal, $dIdCliente, $dAnioCaptura);//Mensaje 223 campos incorrectos
        if($obtenTarifa == 0){
            $obtenTarifa = $dConsumoFinal;
        }
        if($dIdCliente==69){
            $tipo = $tipoToma[0]->TipoToma;
            $obtenTarifa = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=" . $dIdCliente . " AND EjercicioFiscal=" . $dAnioCaptura . " AND  TipoTomaAguaPotable=" . $tipo . " AND  mts3>0 AND mts3<=" . intval($dConsumoFinal), "Suma");
            if($dAnomalia==1){
                $consumoAnterior = DB::select("SELECT Tarifa, Consumo FROM Padr_onDeAguaLectura  WHERE Padr_onAgua = $dIdToma AND A_no = $dAnioCaptura order by Mes desc limit 1");
                $obtenTarifa=$consumoAnterior[0]->Tarifa;
                $dLecturaAnterior=0.00;
                $dLecturaActual=0.00;
                $consumoReal= $consumoAnterior[0]->Consumo;
            }else if($dAnomalia==2){
                $tipo=$tipoToma[0]->TipoToma;
                $numMeses = DB::select('SELECT count(*) as num FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ' . $dIdToma . ' ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12');
                if($numMeses[0]->num>12){
                $promedio12 = DB::select('SELECT ROUND(SUM(Consumo)/12) as Promedio FROM ( SELECT pt.Consumo FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ' . ($dIdToma) . ' ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12 ) AS A');
                }else{
                $promedio12 = DB::select('SELECT ROUND(SUM(Consumo)/' . $numMeses[0]->num . ') as Promedio FROM ( SELECT pt.Consumo FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ' . ($dIdToma) . ' ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12 ) AS A');
                }
                $dConsumoFinal=$promedio12[0]->Promedio;
                $consumoReal=$dConsumoFinal;
                $consumoMinimo = DB::select("SELECT CuotaMinima FROM TipoTomaAguaPotableCliente WHERE TipoToma = $tipo");
                $max = DB::select("SELECT MAX(mts3) as Maximo  FROM ConsumosAgua WHERE Cliente=" . $dIdCliente . " AND EjercicioFiscal=" . $dAnioCaptura . " AND  TipoTomaAguaPotable=" . $tipo . "");
                $valorMax = $max[0]->Maximo;
                $ImporteBase = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=" . $dIdCliente . " AND EjercicioFiscal=" . $dAnioCaptura . " AND  TipoTomaAguaPotable=" . $tipo . " AND  mts3>0 AND mts3=1", "Suma");
                if ($consumoReal > ($valorMax - 1)) {
                    $Importe = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=" . $dIdCliente . " AND EjercicioFiscal=" . $dAnioCaptura . " AND  TipoTomaAguaPotable=" . $tipo . " AND  mts3>0 AND mts3=" . $valorMax . "", "Suma");
                } else {
                    $Importe = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=" . $dIdCliente . " AND EjercicioFiscal=" . $dAnioCaptura . " AND  TipoTomaAguaPotable=" . $tipo . " AND  mts3>0 AND mts3=$consumoReal", "Suma");
                }
                $total = (($Importe * ($consumoReal - $consumoMinimo[0]->CuotaMinima))) + $ImporteBase;
                $obtenTarifa = $this->truncateFloat($total, 2);
                $dLecturaActual = $dLecturaAnterior;
            }else if($dAnomalia==3 || $dAnomalia==5 || $dAnomalia==8 || $dAnomalia==9){
                $tipo=$tipoToma[0]->TipoToma;
                $numMeses = DB::select('SELECT count(*) as num FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = '.$dIdToma.' ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12');
                if ($numMeses[0]->num > 12) {
                    $promedio12 = DB::select('SELECT ROUND(SUM(Consumo)/12) as Promedio FROM ( SELECT pt.Consumo FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ' . ($dIdToma) . ' ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12 ) AS A');
                } else {
                    $promedio12 = DB::select('SELECT ROUND(SUM(Consumo)/' . $numMeses[0]->num . ') as Promedio FROM ( SELECT pt.Consumo FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = ' . ($dIdToma) . ' ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12 ) AS A');
                }
                $dConsumoFinal=$promedio12[0]->Promedio;
                $consumoReal=$dConsumoFinal;
                $consumoMinimo = DB::select("SELECT CuotaMinima FROM TipoTomaAguaPotableCliente WHERE TipoToma = $tipo");
                $max = DB::select("SELECT MAX(mts3) as Maximo  FROM ConsumosAgua WHERE Cliente=" . $dIdCliente . " AND EjercicioFiscal=" . $dAnioCaptura . " AND  TipoTomaAguaPotable=" . $tipo . "");
                $valorMax = $max[0]->Maximo;
                $ImporteBase = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=" . $dIdCliente . " AND EjercicioFiscal=" . $dAnioCaptura . " AND  TipoTomaAguaPotable=" . $tipo . " AND  mts3>0 AND mts3=1", "Suma");
                if ($consumoReal > ($valorMax - 1)) {
                    $Importe = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=" . $dIdCliente . " AND EjercicioFiscal=" . $dAnioCaptura . " AND  TipoTomaAguaPotable=" . $tipo . " AND  mts3>0 AND mts3=" . $valorMax . "", "Suma");
                } else {
                    $Importe = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=" . $dIdCliente . " AND EjercicioFiscal=" . $dAnioCaptura . " AND  TipoTomaAguaPotable=" . $tipo . " AND  mts3>0 AND mts3=$consumoReal", "Suma");
                }
                $total = (($Importe * ($consumoReal - $consumoMinimo[0]->CuotaMinima))) + $ImporteBase;
                $obtenTarifa = $this->truncateFloat($total, 2);
                $dLecturaActual=$dLecturaAnterior;
            }else if($dAnomalia==4){
                $tipo=$tipoToma[0]->TipoToma;
                $consumoMinimo = DB::select("SELECT CuotaMinima FROM TipoTomaAguaPotableCliente WHERE TipoToma = $tipo");
                $dConsumoFinal=$consumoMinimo[0]->CuotaMinima;
                $consumoReal=$dConsumoFinal;
                $consumoMinimo = DB::select("SELECT CuotaMinima FROM TipoTomaAguaPotableCliente WHERE TipoToma = $tipo");
                $max = DB::select("SELECT MAX(mts3) as Maximo  FROM ConsumosAgua WHERE Cliente=" . $dIdCliente . " AND EjercicioFiscal=" . $dAnioCaptura . " AND  TipoTomaAguaPotable=" . $tipo . "");
                $valorMax = $max[0]->Maximo;
                $ImporteBase = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=" . $dIdCliente . " AND EjercicioFiscal=" . $dAnioCaptura . " AND  TipoTomaAguaPotable=" . $tipo . " AND  mts3>0 AND mts3=1", "Suma");
                if ($consumoReal > ($valorMax - 1)) {
                    $Importe = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=" . $dIdCliente . " AND EjercicioFiscal=" . $dAnioCaptura . " AND  TipoTomaAguaPotable=" . $tipo . " AND  mts3>0 AND mts3=" . $valorMax . "", "Suma");
                } else {
                    $Importe = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=" . $dIdCliente . " AND EjercicioFiscal=" . $dAnioCaptura . " AND  TipoTomaAguaPotable=" . $tipo . " AND  mts3>0 AND mts3=$consumoReal", "Suma");
                }
                $total = (($Importe * ($consumoReal - $consumoMinimo[0]->CuotaMinima))) + $ImporteBase;
                $obtenTarifa = $this->truncateFloat($total, 2);
                $dLecturaActual=$dLecturaAnterior;
            }else if($dAnomalia==6 || $dAnomalia == 7 || $dAnomalia==0){
                $tipo = $tipoToma[0]->TipoToma;
                $consumoMinimo = DB::select("SELECT CuotaMinima FROM TipoTomaAguaPotableCliente WHERE TipoToma = $tipo");
                if ($dConsumoFinal < $consumoMinimo[0]->CuotaMinima) {
                    $consumoReal = $consumoMinimo[0]->CuotaMinima;
                } else {
                    $consumoReal = $dConsumoFinal;
                }
                $max = DB::select("SELECT MAX(mts3) as Maximo  FROM ConsumosAgua WHERE Cliente=" . $dIdCliente . " AND EjercicioFiscal=" . $dAnioCaptura . " AND  TipoTomaAguaPotable=" . $tipo . "");
                $valorMax = $max[0]->Maximo;
                $ImporteBase = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=" . $dIdCliente . " AND EjercicioFiscal=" . $dAnioCaptura . " AND  TipoTomaAguaPotable=" . $tipo . " AND  mts3>0 AND mts3=1", "Suma");
                if ($consumoReal > ($valorMax - 1)) {
                    $Importe = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=" . $dIdCliente . " AND EjercicioFiscal=" . $dAnioCaptura . " AND  TipoTomaAguaPotable=" . $tipo . " AND  mts3>0 AND mts3=" . $valorMax . "", "Suma");
                } else {
                    $Importe = Funciones::ObtenValor("SELECT sum(Importe) as Suma FROM ConsumosAgua WHERE  Cliente=" . $dIdCliente . " AND EjercicioFiscal=" . $dAnioCaptura . " AND  TipoTomaAguaPotable=" . $tipo . " AND  mts3>0 AND mts3=$consumoReal", "Suma");
                }
                $total = (($Importe * ($consumoReal - $consumoMinimo[0]->CuotaMinima))) + $ImporteBase;
                $obtenTarifa = $this->truncateFloat($total, 2);

            }
        }
        if($dIdCliente==69){
            if($mesBD[0]->Mes >= $dMesCaptura){
                $result = DB::table('Padr_onDeAguaLectura')
                    ->where('Mes', $dMesCaptura)
                    ->where('A_no', $dAnioCaptura)
                    ->update(['LecturaAnterior' => $dLecturaAnterior, 'LecturaActual' => $dLecturaActual]);
                $idLectura = $mesBD[0]->id;

            }else{
                $idLectura = DB::table('Padr_onDeAguaLectura')->insertGetId([
                    'Padr_onAgua' => $dIdToma,
                    'LecturaAnterior' => $dLecturaAnterior,
                    'LecturaActual' => $dLecturaActual, //FIXME: parche temporal para el error de lectura actual 0
                    'Consumo' => $consumoReal,
                    'Mes' => $dMesCaptura,
                    'A_no' => $dAnioCaptura,
                    'Observaci_on' => $dAnomalia,
                    'FechaLectura' => $fechaRegistro,
                    'TipoToma' => $tipoToma[0]->TipoToma,
                    'EstadoToma' => $tipoToma[0]->Estatus,
                    'Tarifa' => $obtenTarifa,
                    'TipoInsert' => 3

                ]);
            }

        }else{
            if($dAnomalia=="" || $dAnomalia==0){
                $idLectura = DB::table('Padr_onDeAguaLectura')->insertGetId([
                    'Padr_onAgua' => $dIdToma,
                    'LecturaAnterior' => $dLecturaAnterior,
                    'LecturaActual' => $dLecturaActual, //FIXME: parche temporal para el error de lectura actual 0
                    'Consumo' => $dConsumoFinal,
                    'Mes' => $dMesCaptura,
                    'A_no' => $dAnioCaptura,
                    'Observaci_on' => $dAnomalia,
                    'FechaLectura' => $fechaRegistro,
                    'TipoToma' => $tipoToma[0]->TipoToma,
                    'EstadoToma' => $tipoToma[0]->Estatus,
                    'Tarifa' => $obtenTarifa,
                    'TipoInsert' => 3

                ]);
            }else{
                $idLectura = DB::table('Padr_onDeAguaLectura')->insertGetId([
                    'Padr_onAgua' => $dIdToma,
                    'LecturaAnterior' => $dLecturaAnterior,
                    'LecturaActual' => $dLecturaActual, //FIXME: parche temporal para el error de lectura actual 0
                    'Consumo' => $consumoReal,
                    'Mes' => $dMesCaptura,
                    'A_no' => $dAnioCaptura,
                    'Observaci_on' => $dAnomalia,
                    'FechaLectura' => $fechaRegistro,
                    'TipoToma' => $tipoToma[0]->TipoToma,
                    'EstadoToma' => $tipoToma[0]->Estatus,
                    'Tarifa' => $obtenTarifa,
                    'TipoInsert' => 3

                ]);
            }

        }

        if($idLectura){
            if($dIdCliente==69){
                $fechaRegistroCela = date('y-m-d H:i:s');
                $idRlecturas = DB::table('Padr_onAguaPotableRLecturas')->insertGetId([
                    'idLectura' => $idLectura,
                    'idUsuario' => $didUsuario,
                    'Padr_onAgua' => $dIdToma,
                    'longitud' => $longitud,
                    'latitud' => $latitud,
                    'Observacion' => $tipoCoordenada
                ]);
            }else{
                $fechaRegistroCela = date('y-m-d H:i:s');
                $idRlecturas = DB::table('Padr_onAguaPotableRLecturas')->insertGetId([
                    'idLectura' => $idLectura,
                    'idUsuario' => $didUsuario,
                    'Padr_onAgua' => $dIdToma,
                    'longitud' => $longitud,
                    'latitud' => $latitud,
                    'tipoCoordenada' => $tipoCoordenada
                ]);
            }
            //Creamos el objeto
            if ($dIdCliente == 69) {
                if($dAnomalia !=0){
                $idfotoToma = $this->SubirImagenV3($arregloFotos['Toma'], $idLectura, $dIdCliente, $didUsuario);
                $idFotoFachada = $this->SubirImagenV3($arregloFotos['Fachada'], $idLectura, $dIdCliente, $didUsuario);
                $idFotoCalle = $this->SubirImagenV3($arregloFotos['Calle'], $idLectura, $dIdCliente, $didUsuario);
                //INDEV: aqui quitamos toda la basura
                $arregloFotosCela = array("Toma" => $idfotoToma, "Fachada" => $idFotoFachada, "Calle" => $idFotoCalle);
                $actualizarDatos = DB::table('Padr_onAguaPotableRLecturas')->where('id', $idRlecturas)->update([
                    'Fotos' => json_encode($arregloFotosCela)
                ]);
                }
            }else{
                $idfotoToma = $this->SubirImagenV3($arregloFotos['Toma'], $idLectura, $dIdCliente, $didUsuario);
                $idFotoFachada = $this->SubirImagenV3($arregloFotos['Fachada'], $idLectura, $dIdCliente, $didUsuario);
                $idFotoCalle = $this->SubirImagenV3($arregloFotos['Calle'], $idLectura, $dIdCliente, $didUsuario);
                //INDEV: aqui quitamos toda la basura
                $arregloFotosCela = array("Toma" => $idfotoToma, "Fachada" => $idFotoFachada, "Calle" => $idFotoCalle);
                $actualizarDatos = DB::table('Padr_onAguaPotableRLecturas')->where('id', $idRlecturas)->update([
                    'Fotos' => json_encode($arregloFotosCela)
                ]);
            }
            $guardarUsuario = DB::table('CelaAccesos')->insert([
                'FechaDeAcceso'=> $fechaRegistroCela,
                'idUsuario' => $didUsuario,
                'Tabla' => 'Padr_onDeAguaLectura',
                'IdTabla' => $dIdToma,
                'Acci_on' => 2
            ]);
            //NOTE: verificamos lecturas dobles
            //INDEV: SELECT COUNT(id) FROM Padr_onDeAguaLectura WHERE Padr_onAgua = 173907 AND Mes = 11 AND A_no = 2022;
            $VerificaDobles = DB::table("Padr_onDeAguaLectura")->select('id')
                ->where('Padr_onAgua','=',$dIdToma)
                ->where('Mes','=',$dMesCaptura)
                ->where('A_no','=',$dAnioCaptura)->count();
            if($VerificaDobles>1){
                //Obtenemos el id de la lectura extra
                $idDoble = DB::table('Padr_onDeAguaLectura')->select('id')
                ->where('Padr_onAgua','=',$dIdToma)
                ->where('Mes','=',$dMesCaptura)
                ->where('A_no','=',$dAnioCaptura)->orderBy('id')->limit(1)->get();
                DB::table('Padr_onDeAguaLectura')->where('id','=',$idDoble[0]->id)->delete();
            }
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
    public function SubirImagenV3($arregloFoto,$idRegistro,$Cliente,$usuario,$nombreTabla = 'Padr_onAguaPotableRLecturas',$descripcion = "Fotos de la aplicacion Agua"){
        $arregloNombre = array();
        $arregloSize = array();
        $arregloRuta = array();
        $datosRepo = "";
        $ruta = date("Y/m/d");
        $fechaHora = date("Y-m-d H:i:s");
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

        Funciones::selecionarBase($Cliente);
        //insertamos las rutas en celaRepositorio
        //NOTE: se inserta en las evidencias del reporte
        foreach($arregloRuta as $ruta){
            $idRepositorio = DB::table("CelaRepositorio")->insertGetId([
                'Tabla'=>$nombreTabla,
                'idTabla'=>$idRegistro,
                'Ruta'=> $ruta,
                'Descripci_on'=>$descripcion,
                'idUsuario'=>$usuario,
                'FechaDeCreaci_on'=>$fechaHora,
                'Estado'=>1,
                'Reciente'=>1,
                'NombreOriginal'=>$arregloNombre[0],
                'Size'=>$arregloSize[0]
            ]);
        }
        return $idRepositorio;
    }
    public function verificarUsuarioCortes( Request $request ){
        $datos = $request->all();
        $rules = [
            'cliente'=>'required|string',
            'usuario'=>'required|numeric'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje'=>$validator->messages(),
                'Code' => 223 //Mensaje 223 campos incorrectos
            ]);
        }
        $Cliente = $request->cliente;
        $Usuario = $request->usuario;
        Funciones::selecionarBase($Cliente);
        $usuarioCorte = DB::table('CorteUsuarioConfiguracion')->select('id')->where('idUsuario','=',$Usuario)->get();
        if(sizeof($usuarioCorte) > 0){
            return [
                'Status'=> true,
                'Corte' => $usuarioCorte
            ];
        }else{
            return [
                'Status'=> false,
                'Corte' => '-1'
            ];
        }
    }
    public function ObtenerListaTareas( Request $request ){
        $datos = $request->all();
        $rules = [
            'Cliente'=>'required|numeric',
            'Configuracion'=>'required|numeric',
        ];
        $Cliente = $request->Cliente;
        $Configuracion = $request->Configuracion;
        Funciones::selecionarBase($Cliente);
        $tareas = DB::table('Padr_onAguaPotable')->select('Padr_onAguaPotable.id',
                    'ContratoVigente',
                    'Medidor',
                    'TipoTomaAguaPotable.Concepto as Toma',
                    'M_etodoCobro',
                    'Padr_onAguaPotable.Estatus',
                    DB::raw('( SELECT COALESCE(CONCAT_WS(" ", c.Nombres,c.ApellidoPaterno,c.ApellidoMaterno),NombreComercial) FROM Contribuyente c WHERE c.id = Padr_onAguaPotable.Contribuyente )  as Contribuyente'))
                    ->join('CorteUsuarioTarea','Padr_onAguaPotable.id','=','CorteUsuarioTarea.Padr_on')
                    ->join('TipoTomaAguaPotable','TipoTomaAguaPotable.id','=','Padr_onAguaPotable.TipoToma')
                    ->where('idCorteUsuarioConfiguracion','=',$Configuracion)
                    ->where('CorteUsuarioTarea.Estatus','=',1)->get();
        if(sizeof($tareas) > 0){
            return [
                'Status'=>true,
                'Tareas'=>$tareas
            ];
        }else{
            return [
                'Status'=>false,
                'Tareas'=>[]
            ];
        }
    }
    public function BuscarContratoTarea( Request $request ){
        $datos = $request->all();
        $rules = [
            'Cliente'=>"required|string",
            'Configuracion'=>"required|string",
            'Indicio'=>"required|string",
            'Mes'=>"required|numeric",
            'Anio'=>"required|numeric"
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje'=>$validator->messages(),
                'Code' => 223 //Mensaje 223 campos incorrectos
            ]);
        }
        $Cliente = $request->Cliente;
        $Configuracion = $request->Configuracion;
        $Indicio = $request->Indicio;
        $Mes = $request->Mes;
        $Anio = $request->Anio;
        Funciones::selecionarBase($Cliente);
        /*$listaSectoresUsuarioCortes = DB::table('CorteUsuarioSector')
            ->select(DB::raw('GROUP_CONCAT(CAST(Padr_onAguaPotableSector.Sector AS INT)) as Sectores'))
            ->join('CorteUsuarioConfiguracion','CorteUsuarioConfiguracion.id','=','CorteUsuarioSector.idUsuarioCorteConfiguracion')
            ->join('Padr_onAguaPotableSector','Padr_onAguaPotableSector.id','=','CorteUsuarioSector.idSector')
            ->where('idUsuarioCorteConfiguracion','=',$Configuracion)
            ->where('CorteUsuarioConfiguracion.Activo','=',1)
            ->where('Cliente','=',$Cliente)->get();*/
        $consulta = "SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,Padr_onAguaPotable.Estatus,
            (SELECT COALESCE(CONCAT(Nombres,' ',ApellidoPaterno,' ',ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente,
            if(COALESCE((SELECT COUNT(ppc.id) FROM Padr_onAguaPotableCorte as ppc INNER JOIN ConceptoAdicionalesCotizaci_on as cac ON(cac.Cotizaci_on=ppc.Cotizaci_on) WHERE ppc.EstatusTupla=1 and  cac.Estatus in(0) and  ppc.Padr_on=Padr_onAguaPotable.id and ppc.Estatus=2),0) >0 ,2,1) as Pagado
            FROM Padr_onAguaPotable
            JOIN TipoTomaAguaPotable toma on ( Padr_onAguaPotable.TipoToma = toma.id )
            #JOIN CorteUsuarioTarea tarea on ( Padr_onAguaPotable.id = tarea.Padr_on )
            WHERE Cliente = $Cliente
            AND ( ContratoVigente LIKE '%$Indicio' or ContratoVigente = '$Indicio')
            AND Padr_onAguaPotable.Cliente = $Cliente
            AND Padr_onAguaPotable.Estatus in(1,2,10)";
            //AND tarea.idCorteUsuarioConfiguracion = $Configuracion";
            //AND Padr_onAguaPotable.Sector IN ( ".( $listaSectoresUsuarioCortes[0]->Sectores == "" ? "0" : $listaSectoresUsuarioCortes[0]->Sectores ).")";
        $listaContratos = DB::select($consulta);
        return [
            'Status'=>true,
            'Datos'=>$listaContratos
        ];
    }
    public function BuscarMedidorContrato(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente'=>"required|string",
            'Configuracion'=>"required|string",
            'Indicio'=>"required|string"
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status' => false,
                'Mensaje'=>$validator->messages(),
                'Code' => 223 //Mensaje 223 campos incorrectos
            ]);
        }
        $Cliente = $request->Cliente;
        $Configuracion = $request->Configuracion;
        $Indicio = $request->Indicio;
        Funciones::selecionarBase($Cliente);
        $listaSectoresUsuarioCortes = DB::table('CorteUsuarioSector')
            ->select(DB::raw('GROUP_CONCAT(CAST(Padr_onAguaPotableSector.Sector AS INT)) as Sectores'))
            ->join('CorteUsuarioConfiguracion','CorteUsuarioConfiguracion.id','=','CorteUsuarioSector.idUsuarioCorteConfiguracion')
            ->join('Padr_onAguaPotableSector','Padr_onAguaPotableSector.id','=','CorteUsuarioSector.idSector')
            ->where('idUsuarioCorteConfiguracion','=',$Configuracion)
            ->where('CorteUsuarioConfiguracion.Activo','=',1)
            ->where('Cliente','=',$Cliente)->get();
        $consulta = 'SELECT DISTINCT Padr_onAguaPotable.id, ContratoVigente, Medidor, M_etodoCobro,toma.Concepto as Toma,Padr_onAguaPotable.Estatus,
            (SELECT COALESCE(CONCAT(Nombres," ",ApellidoPaterno," ",ApellidoMaterno),NombreComercial) FROM Contribuyente WHERE Contribuyente.id=Padr_onAguaPotable.Contribuyente) as Contribuyente FROM Padr_onAguaPotable
            JOIN TipoTomaAguaPotable toma on Padr_onAguaPotable.TipoToma = toma.id
            JOIN CorteUsuarioTarea on ( CorteUsuarioTarea.Padr_on = Padr_onAguaPotable.id )
            WHERE Cliente = '.$Cliente.'
            AND (Medidor LIKE "%'.$Indicio.'%" or Medidor = '.intval($Indicio).')
            AND Padr_onAguaPotable.Cliente = '.$Cliente.'
            AND Padr_onAguaPotable.Estatus in(1,2,10)  AND Padr_onAguaPotable.Sector IN ( '.( $listaSectoresUsuarioCortes[0]->Sectores == "" ? "0" : $listaSectoresUsuarioCortes[0]->Sectores ).')
            AND CorteUsuarioTarea.idCorteUsuarioConfiguracion = '.$Configuracion;

        $result = DB::select($consulta);
        return [
            'Status'=>true,
            'Datos'=>$result,
        ];
    }
   
    //NOTE: metodos para la vercion local de la app del agua
    public function ObtenerSectoresConfigurados ( Request $request ) {
        /**
         * SELECT Padr_onAguaPotableSector.Sector FROM Padr_onAguaPotableSectoresLecturistas
            JOIN Padr_onAguaPotableSector on (Padr_onAguaPotableSector.id = Padr_onAguaPotableSectoresLecturistas.idSector )
            WHERE idLecturista = 4001 AND Padr_onAguaPotableSectoresLecturistas.`Local` = 1;
            Padr_onAguaPotableSector.Sector as id, CONCAT_WS(" - ",Padr_onAguaPotableSector.Sector,Padr_onAguaPotableSector.Nombre) as Sector
         */
        $datos = $request->all();
        $rules = [
            'Cliente' => 'required|numeric',
            'Usuario' => 'required|numeric'
        ];
        $validator = Validator::make($datos, $rules);
        if($validator->fails()){
            return response()->json([
                'Status'=>false,
                'Mensaje'=>$validator->messages()."\n Algunos datos del contrato no fueron enviados correctamente",
                'Code'=>223
            ]);
        }
        $Cliente = $request->Cliente;
        $Usuario = $request->Usuario;
        Funciones::selecionarBase($Cliente);
        $listaCliente = DB::table('Padr_onAguaPotableSectoresLecturistas')->
                            select(DB::raw('Padr_onAguaPotableSector.Sector as id'),DB::raw('CONCAT_WS(" - ",Padr_onAguaPotableSector.Sector,Padr_onAguaPotableSector.Nombre) as Sector'))->
                            join('Padr_onAguaPotableSector','Padr_onAguaPotableSector.id','=','Padr_onAguaPotableSectoresLecturistas.idSector')->
                            where('idLecturista','=',$Usuario)->where('Padr_onAguaPotableSectoresLecturistas.Local','=',1)->get();
        if(sizeof($listaCliente)>0){
            return response()->json([
                'Status'=>true,
                'Mensaje'=>$listaCliente,
                'Code'=>200
            ]);
        }else{
            return response()->json([
                'Status'=>false,
                'Mensaje'=>$listaCliente,
                'Code'=>100
            ]);
        }

    }
    public function ObtenerAnomaliasAgua( Request $request ){
        $datos = $request->all();
        $rules = [
            'Cliente' => 'required'
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
        Funciones::selecionarBase($Cliente);
        $resultAnomalias = DB::table('Padr_onAguaCatalogoAnomalia')->select("id","clave","descripci_on","AplicaFoto")->get();
        if(sizeof($resultAnomalias) > 0){
            return response()->json([
                'Status'=>true,
                'Datos'=>$resultAnomalias,
                'Code'=>200
            ]);
        }else{
            return response()->json([
                'Status'=>false,
                'Mesaje'=>"Datos no encontrados",
                'Code'=>200
            ]);
        }
    }
    public function ObtenerConfiguracionesAgua(Request $request){
        $datos = $request->all();
        $rules = [
            'Cliente' => 'required'
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
        Funciones::selecionarBase($Cliente);
        $TipoLectura = DB::table('ClienteDatos')->select('valor')->where('Cliente','=',$Cliente)->where('Indice','=',"ConfiguracionFechaDeLectura")->get();
        $bloquearCampos = DB::table('ClienteDatos')->select('valor')->where('Cliente','=',$Cliente)->where('Indice','=',"BloquerComposAppLecturaAgua")->get();
        return response()->json([
            'Status'=>true,
            'TipoLectura'=>$TipoLectura[0]->Valor,
            'BloquarCampos'=>$bloquearCampos[0]->Valor,
            'Code'=>200
        ]);
    }
    public function ObtenerSectorCompleto( Request $request ){
        $datos = $request->all();
        $rules = [
            'Cliente' => 'required'
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
        $Sector = $request->Sector;
        $Usuario =$request->Usuario;
        //$Usuario =$request->Usuario;
        if($Cliente ==69){
            $Usuario =$request->Usuario;
        }
        //$Mes = $request->Mes;
        //$Anio = $request->Anio;
        Funciones::selecionarBase($Cliente);
        #
        #SELECT * FROM Padr_onAguaPotable WHERE Estatus = 1 AND Sector = 63
        if($Cliente==69){
            $idUsuario= DB::select("SELECT idUsuario FROM CelaUsuario WHERE Usuario='".$Usuario."'");
            $Ruta = DB::select("SELECT Ruta FROM Padr_onAguaPotableSectoresLecturistas WHERE idLecturista=".$idUsuario[0]->idUsuario." and idSector=".$Sector);
            $ContratosSector = DB::table('Padr_onAguaPotable')
                ->select("Padr_onAguaPotable.id",
                    "Padr_onAguaPotable.Cuenta as ContratoVigente",
                    "Padr_onAguaPotable.ContratoAnterior",
                    "Padr_onAguaPotable.Medidor",
                    "Padr_onAguaPotable.Domicilio",
                    "Padr_onAguaPotable.NumeroDomicilio",
                    "Padr_onAguaPotable.Ruta",
                    "Padr_onAguaPotable.M_etodoCobro",
                    "Padr_onAguaPotable.Consumo",
                    "Padr_onAguaPotable.Estatus",
                    "Padr_onAguaPotable.Cuenta",
                    "Padr_onAguaPotable.Manzana",
                    "Padr_onAguaPotable.Lote",
                    DB::raw('COALESCE(CONCAT(Contribuyente.Nombres," ",Contribuyente.ApellidoPaterno," ",Contribuyente.ApellidoMaterno),Contribuyente.NombreComercial) as Contribuyente'),
                    DB::raw('(SELECT ROUND(SUM(pttt.Consumo)/12) FROM Padr_onDeAguaLectura pttt WHERE pttt.Padr_onAgua = Padr_onAguaPotable.id ORDER BY pttt.A_no DESC, pttt.Mes DESC LIMIT 12) as Promedio'),
                    "Municipio.Nombre as Municipio",
                    "Localidad.Nombre as Localidad",
                    "TipoTomaAguaPotable.Concepto as TipoToma",
                    DB::raw("(SELECT Padr_onDeAguaLectura.TipoToma FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as Toma"),
                    DB::raw("(SELECT Padr_onDeAguaLectura.A_no FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as A_no"),
                    DB::raw("(SELECT Padr_onDeAguaLectura.Mes FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as Mes"),
                    DB::raw("(SELECT Padr_onDeAguaLectura.Consumo FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as Consumo"),
                    DB::raw("(SELECT Padr_onDeAguaLectura.Status FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as Status"),
                    DB::raw("(SELECT Padr_onDeAguaLectura.LecturaAnterior FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id and Padr_onDeAguaLectura.LecturaAnterior is not null ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as LecturaAnterior"),
                    DB::raw('(SELECT if((SELECT Padr_onDeAguaLectura.LecturaActual FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id and Padr_onDeAguaLectura.LecturaActual is not null and Padr_onDeAguaLectura.LecturaActual!= 0.00 ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1) = "", (SELECT Padr_onDeAguaLectura.LecturaActual FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id and Padr_onDeAguaLectura.LecturaActual is not null ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1),(SELECT Padr_onDeAguaLectura.LecturaActual FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id and Padr_onDeAguaLectura.LecturaActual is not null and Padr_onDeAguaLectura.LecturaActual!= 0.00 ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1))) as LecturaActual'),
                    DB::raw('(SELECT CONCAT_WS(" ",Padr_onAguaCatalogoAnomalia.clave,"-",Padr_onAguaCatalogoAnomalia.descripci_on) FROM Padr_onAguaCatalogoAnomalia WHERE  Padr_onAguaCatalogoAnomalia.id = (SELECT Padr_onDeAguaLectura.Observaci_on FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no,Padr_onDeAguaLectura.Mes DESC LIMIT 1 )) as Observaci_on'),
                    DB::raw("(SELECT TipoTomaAguaPotableCliente.CuotaMinima FROM TipoTomaAguaPotableCliente  WHERE TipoTomaAguaPotableCliente.TipoToma = Padr_onAguaPotable.TipoToma) as Diametro")
                    )
                    #->join('Padr_onDeAguaLectura','Padr_onDeAguaLectura.Padr_onAgua','=','Padr_onAguaPotable.id') #JOIN Padr_onDeAguaLectura on ( Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id )
                    ->join('Contribuyente','Padr_onAguaPotable.Contribuyente','=','Contribuyente.id' )
                    ->join('Municipio','Padr_onAguaPotable.Municipio','=','Municipio.id')
                    ->join('Localidad','Padr_onAguaPotable.Localidad','=','Localidad.id' )
                    ->join('TipoTomaAguaPotable','TipoTomaAguaPotable.id','=','Padr_onAguaPotable.TipoToma')
                    #->leftjoin('Padr_onAguaCatalogoAnomalia','Padr_onAguaCatalogoAnomalia.id','=','Padr_onDeAguaLectura.Observaci_on')
                        #->where('Padr_onAguaPotable.Estatus','=',1)
                        ->where('Sector','=',$Sector)
                        ->where('Ruta','=',$Ruta[0]->Ruta)
                        ->where('M_etodoCobro','!=',1)
                        #->where('Mes','=',$Mes)
                        #->where('A_no','=',$Anio)
                        ->get();
        }else{
            $idUsuario = DB::table('CelaUsuario')
            ->where('Usuario', $Usuario)
            ->value('idUsuario');

        $sectoresRaw = DB::table('Padr_onAguaPotableSectoresLecturistas')
            ->where('idLecturista', $idUsuario)
            ->pluck('idSector');

        $sectores = $sectoresRaw->toArray();
            //$Sectores = DB::select("SELECT GROUP_CONCAT(idSector) as FROM Padr_onAguaPotableSectoresLecturistas WHERE idLecturista=".$idUsuario[0]->idUsuario);
                    $ContratosSector = DB::table('Padr_onAguaPotable')
                        ->select("Padr_onAguaPotable.id",
                            "Padr_onAguaPotable.ContratoVigente",
                            "Padr_onAguaPotable.ContratoAnterior",
                            "Padr_onAguaPotable.Medidor",
                            "Padr_onAguaPotable.Domicilio",
                            "Padr_onAguaPotable.NumeroDomicilio",
                            "Padr_onAguaPotable.Ruta",
                            "Padr_onAguaPotable.M_etodoCobro",
                            "Padr_onAguaPotable.Consumo",
                            "Padr_onAguaPotable.Estatus",
                            "Padr_onAguaPotable.Cuenta",
                            "Padr_onAguaPotable.Diametro",
                            "Padr_onAguaPotable.Manzana",
                            "Padr_onAguaPotable.Lote",
                            DB::raw("(SELECT Padr_onDeAguaLectura.Observaci_on FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as Prom"),
                            DB::raw('COALESCE(CONCAT(Contribuyente.Nombres," ",Contribuyente.ApellidoPaterno," ",Contribuyente.ApellidoMaterno),Contribuyente.NombreComercial) as Contribuyente'),
                            DB::raw('(SELECT if((SELECT ROUND(SUM(pt.Consumo)/12) FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = Padr_onAguaPotable.id ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12) < 20, (SELECT ptt.Consumo FROM Padr_onAguaPotable ptt WHERE ptt.id = Padr_onAguaPotable.id),(SELECT ROUND(SUM(pttt.Consumo)/12) FROM Padr_onDeAguaLectura pttt WHERE pttt.Padr_onAgua = Padr_onAguaPotable.id ORDER BY pttt.A_no DESC, pttt.Mes DESC LIMIT 12))) as Promedio'),
                            "Municipio.Nombre as Municipio",
                            "Localidad.Nombre as Localidad",
                            "TipoTomaAguaPotable.Concepto as TipoToma",
                            DB::raw("(SELECT Padr_onDeAguaLectura.TipoToma FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as Toma"),
                            DB::raw("(SELECT Padr_onDeAguaLectura.A_no FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as A_no"),
                            DB::raw("(SELECT Padr_onDeAguaLectura.Mes FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as Mes"),
                            DB::raw("(SELECT Padr_onDeAguaLectura.Consumo FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as consumoAnterior"),
                            DB::raw("(SELECT Padr_onDeAguaLectura.Status FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as Status"),
                            DB::raw("(SELECT Padr_onDeAguaLectura.LecturaAnterior FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as LecturaAnterior"),
                            DB::raw("(SELECT Padr_onDeAguaLectura.LecturaActual FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no DESC ,Padr_onDeAguaLectura.Mes DESC LIMIT 1 ) as LecturaActual"),
                            DB::raw('(SELECT CONCAT_WS(" ",Padr_onAguaCatalogoAnomalia.clave,"-",Padr_onAguaCatalogoAnomalia.descripci_on) FROM Padr_onAguaCatalogoAnomalia WHERE  Padr_onAguaCatalogoAnomalia.id = (SELECT Padr_onDeAguaLectura.Observaci_on FROM Padr_onDeAguaLectura WHERE Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id ORDER BY Padr_onDeAguaLectura.A_no,Padr_onDeAguaLectura.Mes DESC LIMIT 1 )) as Observaci_on')
                            )
                            #->join('Padr_onDeAguaLectura','Padr_onDeAguaLectura.Padr_onAgua','=','Padr_onAguaPotable.id') #JOIN Padr_onDeAguaLectura on ( Padr_onDeAguaLectura.Padr_onAgua = Padr_onAguaPotable.id )
                            ->join('Contribuyente','Padr_onAguaPotable.Contribuyente','=','Contribuyente.id' )
                            ->join('Municipio','Padr_onAguaPotable.Municipio','=','Municipio.id')
                            ->join('Localidad','Padr_onAguaPotable.Localidad','=','Localidad.id' )
                            ->join('TipoTomaAguaPotable','TipoTomaAguaPotable.id','=','Padr_onAguaPotable.TipoToma')
                            #->leftjoin('Padr_onAguaCatalogoAnomalia','Padr_onAguaCatalogoAnomalia.id','=','Padr_onDeAguaLectura.Observaci_on')
                                #->where('Padr_onAguaPotable.Estatus','=',1)
                                
                                //->whereIn('Sector', [4, 6])
                                ->where('Sector','=',$Sector)   
                                #->where('Mes','=',$Mes)
                                #->where('A_no','=',$Anio)
                            ->get();
        }
        //NOTE: recorremos la lista para ingresar el primerdio
        $Prune = 0;
        $PadronContratos = [];
        foreach ($ContratosSector as $Padron) {
            //NOTE: Obtememos el promedio de la toma
            #SELECT ROUND(SUM(Consumo)/12) as Promedio FROM Padr_onDeAguaLectura WHERE Padr_onAgua = 149325 ORDER BY A_no DESC, Mes DESC LIMIT 12;
            //$objPromedio = DB::select('SELECT ROUND(SUM(Consumo)/12) as Promedio FROM ( SELECT pt.Consumo FROM Padr_onDeAguaLectura as pt WHERE pt.Padr_onAgua = '.($Padron->id).' ORDER BY pt.A_no DESC, pt.Mes DESC LIMIT 12 ) AS A');
            //$promedio = $objPromedio[0]->Promedio;
            $promedio = 0;
            if ($promedio == 0) {
                $tipoToma = DB::select("SELECT Consumo FROM Padr_onAguaPotable WHERE id=" . $Padron->id);
                $promedio = $tipoToma[0]->Consumo;
            }
            if(is_null($Padron->LecturaActual)){
                $Padron->LecturaActual=0;
            }
            $data = array(
                "A_no" => $Padron->A_no,
                "Consumo" => $Padron->Consumo,
                "ContratoAnterior" => $Padron->ContratoAnterior,
                "ContratoVigente" => $Padron->ContratoVigente,
                "Contribuyente" => $Padron->Contribuyente,
                "Cuenta" => $Padron->Cuenta,
                "Diametro" => $Padron->Diametro,
                "Domicilio" => $Padron->Domicilio,
                "Estatus" => $Padron->Estatus,
                "LecturaActual" => $Padron->LecturaActual,
                "LecturaAnterior" => $Padron->Prom,
                "Localidad" => $Padron->Localidad,
                "Lote" => $Padron->Lote,
                "M_etodoCobro" => $Padron->M_etodoCobro,
                "Manzana" => $Padron->Manzana,
                "Medidor" => $Padron->Medidor,
                "Mes" => $Padron->Mes,
                "Municipio" => $Padron->Municipio,
                "NumeroDomicilio" => $Padron->NumeroDomicilio,
                "Observaci_on" => $Padron->Observaci_on,
                "Ruta" => $Padron->Ruta,
                "Status" => $Padron->Status,
                "TipoToma" => $Padron->TipoToma,
                "id" => $Padron->id,
                "Toma" => $Padron->Toma,
                "Promedio" => $promedio,
                "Padron" => $Padron->id
            );
            array_push($PadronContratos, $data);
        }
        return response()->json([
            'Status'=>true,
            'Mensaje'=> $PadronContratos,
            #'test' => $Prune,
            'Code'=>200
        ]);
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
