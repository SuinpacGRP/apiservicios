<?php

namespace App\Http\Controllers\Checador;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use JWTAuth;
use JWTFactory;
use App\Cliente;
use App\Funciones;
use App\User;
use App\Modelos\Persona;
use Validator;
use Illuminate\Support\Facades\Auth;

class AsistenciaController extends Controller
{
    public function __construct(){
        $this->middleware( 'jwt', ['except' => ['verificar_usuario_sistema','verificar_acceso']] );
    }


    public function prueba(){

        $cadenanueva =  strtoupper('segm7707036T7');
        return "nueva rfc: ".$cadenanueva;
    }



    public function verificar_acceso(Request $request){
        $usuario = "suinpacmovilasistencia";
        $pass = "Android13072020$";

        $credentials = $request->all();
                
        $rules = [
            'Usuario'     => 'required|string',
            'Contrase_na' => 'required|string',
        ];

        $validator = Validator::make($credentials, $rules);
        
        if ( $validator->fails() ) {
            return response()->json([
                'Status'  => false,
                'Mensaje' => 'Los campos no deben estar vacios'
            ]);
        }
        #echo Carbon::now()->addMinutes(2)->timestamp;
        JWTAuth::factory()->setTTL(600);
        /*$token = auth()->attempt( ['Usuario' => $credentials['Usuario'], 'password' => $credentials['Contrase_na'], 'EstadoActual' => 1] );
        return $token;*/

        if ( !$token = auth()->attempt( ['Usuario' => $credentials['Usuario'], 'password' => $credentials['Contrase_na'], 'EstadoActual' => 1] ) ) {
            return response()->json([
                'Status' => false,
                'Mensaje' => 'Usuario o Contraseña Incorrectos',
            ]);
        }

        $usuario = auth()->user();

        return response()->json([
            'token' => $token
        ]);
    }

 
    public function verificar_usuario_sistema(Request $request){
        $cadenaMovil =  "kcjMRg2kYw8vbnfWHtBTh59BzPKfQz";
        if($request->cadena === $cadenaMovil){
            $usuario = "suinpacmovilasistencia";
            $pass = "Android13072020$";
            JWTAuth::factory()->setTTL(600);
            if ( !$token = auth()->attempt( ['Usuario' => $usuario, 'password' => $pass, 'EstadoActual' => 1] ) ) {
                return response()->json([
                    "Status"   => "false",
                    "Estatus" => false,
                    'Mensaje' => 'RFC/Entidad Incorrectos',
                ]);
            }
            return response()->json([
                "Status"   => "true",
                "Estatus" => true,
                "token" => $token
            ]);
        }
    } 




/*     public function verificar_usuario_sistema(Request $request){
        $cadenaMovil =  "kcjMRg2kYw8vbnfWHtBTh59BzPKfQz";
        if($request->cadena === $cadenaMovil){
            $usuario = "suinpacmovilasistencia";
            $pass = "Android13072020$";
            $user =  User::where('Usuario','=',$usuario)->where('EstadoActual','=',1)->first();
            if($user){
                JWTAuth::factory()->setTTL(600);
                $token = JWTAuth::fromUser($user);
                return response()->json([
                    "Status"   => "true",
                    "Estatus" => true,
                    "token" => $token
                ]);
            }else{
                return response()->json([ 
                    "Status"   => "false",
                    "Estatus" => true,
                    "Mensaje" => "Datos incorrectos"
                ]);
            }
            
        }
        return response()->json([ 
            "Status"   => "false",
            "Estatus" => true,
            "Mensaje" => "Datos incorrectos"
        ]);
    } 
 */

    public function existe_empleado(Request $request){
        $rfc = strtoupper($request->rfc);
        $idCliente =  $request->idCliente;
        Funciones::selecionarBase($idCliente);
      
        $persona =   DB::table('Persona')
                        ->join('Cliente','Persona.Cliente','=','Cliente.id')
                        ->join('DatosFiscales','DatosFiscales.id','=','Persona.DatosFiscales')
                        ->select('Persona.id',
                                DB::raw('CONCAT(Persona.Nombre," ", Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) as nombreEmpleado'),
                                'Persona.Tel_efonoCelular',
                                'Cliente.Descripci_on')
                        ->where('DatosFiscales.RFC','=',$rfc)
                        ->where('Persona.Cliente','=',$idCliente)
                        ->where('Persona.Estatus','=','1')
                        ->first();  
        if($persona){
           
            //buscamos si existe el puesto empleado
            $puestoEmpleado = DB::table('PuestoEmpleado')->select('PuestoEmpleado.id')->where('PuestoEmpleado.Empleado','=',$persona->id)->where('PuestoEmpleado.Estatus','=',"1")->first();
            if($puestoEmpleado){

                //Buscamos si existe registro del puestoEmpleado en la tabla AsistenciaEmpleadoLoral
                $asistenciaEmpleadoLaboral = DB::table('AsistenciaEmpleadoLaboral')
                                                ->select('AsistenciaEmpleadoLaboral.id','AsistenciaEmpleadoLaboral.jornada_id')              
                                                ->where('AsistenciaEmpleadoLaboral.idPuestoEmpleado','=',$puestoEmpleado->id)
                                                ->first(); 
                if($asistenciaEmpleadoLaboral){

                    //buscamos que tipo de jornada tiene el empleado
                    $jornada = DB::table('AsistenciaJornada')->select('*')->where('idJornada','=',$asistenciaEmpleadoLaboral->jornada_id)->first();
                    if($jornada){
                                if($jornada->idJornada == 8){
                                        $asistenciaHorarioEmpleado =  DB::table('AsistenciaEmpleadoHorario')
                                        ->select(DB::raw("GROUP_CONCAT(DISTINCT CONCAT(AsistenciaEmpleadoHorario.horaEntrada)) as HoraEntrada"),
                                                DB::raw("GROUP_CONCAT(DISTINCT CONCAT(AsistenciaEmpleadoHorario.horaSalida)) as HoraSalida"))
                                        ->where('AsistenciaEmpleadoHorario.idEmpleadoLaboral',$asistenciaEmpleadoLaboral->id)
                                        ->where('AsistenciaEmpleadoHorario.dia_id','=', 8 )
                                        ->get(); 
                                        if(!$asistenciaHorarioEmpleado){
                                            return response()->json([
                                                "Status"   => "false",
                                                "Estatus" => false,
                                                "Mensaje" => "No tiene registrado horarios"
                                            ]); 
                                        }
                                }else{
                                        //horario personalizado
                                        $fecha = new \DateTime();
                                        $fechaHoy = $fecha->format('Y-m-d');
                                        $asistenciaHorarioEmpleado =  DB::table('AsistenciaEmpleadoHorario')
                                            ->select(DB::raw("GROUP_CONCAT(DISTINCT CONCAT(AsistenciaEmpleadoHorario.horaEntrada)) as HoraEntrada"),
                                                    DB::raw("GROUP_CONCAT(DISTINCT CONCAT(AsistenciaEmpleadoHorario.horaSalida)) as HoraSalida"))
                                            ->where('AsistenciaEmpleadoHorario.idEmpleadoLaboral',$asistenciaEmpleadoLaboral->id)
                                            ->where('AsistenciaEmpleadoHorario.dia_id','=', (date('w', strtotime($fechaHoy)) + 1) )
                                            // ->where('AsistenciaEmpleadoHorario.dia_id','=', 5 )
                                            ->get();  
                                        if(!$asistenciaHorarioEmpleado){
                                            return response()->json([
                                                "Status"   => "false",
                                                "Estatus" => false,
                                                "Mensaje" => "No tiene registrado horarios"
                                            ]); 
                                        }
                                }


                                $ubicacionEmpresa =  DB::table("UbicacionEmpresa")
                                                    ->select('UbicacionEmpresa.latitude','UbicacionEmpresa.longitude','UbicacionEmpresa.radio','UbicacionEmpresa.descripcion as NombreComercial')
                                                    ->join('AsistenciaEmpleadoLaboralEmpresa','UbicacionEmpresa.idUbicacionEmpresa','=','AsistenciaEmpleadoLaboralEmpresa.ubicacionEmpresa_id')
                                                    ->where('AsistenciaEmpleadoLaboralEmpresa.puestoEmpleado_id','=',$puestoEmpleado->id)
                                                    ->first();
                                                    
                                if(!$ubicacionEmpresa){
                                    return response()->json([
                                        "Status"   => "false",
                                        "Estatus" => false,
                                        "Mensaje" => "No tiene asignado una ubicación. Comuníquese con su administrador"
                                    ]);  
                                }else{
                                    return response()->json([
                                        "Status"   => "true",
                                        "Estatus" => true,
                                        "Mensaje" => "Bienvenido"
                                    ]);  
                                }
                    }
 
                }else{
                    return response()->json([
                        "Status"   => "false",
                        "Estatus" => false,
                        "Mensaje" => "Sus datos aun no han sido dados de alta para la aplicación"
                    ]);  
                }

            //si no existe puesto empleado
            }else{
                return response()->json([
                    "Status"   => "false",
                    "Estatus" => false,
                    "Mensaje" => "RFC/Entidad Federativa son incorrectos"
                ]);
            }


        //Si persona no existe
        }else{
            return response()->json([
                "Status"   => "false",
                "Estatus" => false,
                "Mensaje" => "RFC/Entidad Federativa son incorrectos"
            ]);
        }
    
    
    }


    public function agregarNumeroDeTelefono(Request $request){
         $rfc = strtoupper($request->rfc);
         $idCliente = $request->idCliente;
         $telefono = $request->telefono;
         $lada = $request->lada;

         Funciones::selecionarBase($idCliente);

         $persona =  DB::table('Persona')
         ->select('Persona.id','Persona.Tel_efonoCelular')
         ->join('Cliente','Persona.Cliente','=','Cliente.id')
         ->join('DatosFiscales','DatosFiscales.id','=','Persona.DatosFiscales')
         ->where('DatosFiscales.RFC','=',$rfc)
         ->where('Persona.Cliente','=',$idCliente)
         ->where('Persona.Estatus','=','1')
         ->first();
         
         if($persona->Tel_efonoCelular === null || $persona->Tel_efonoCelular === ''){
                if(strlen($telefono) == 7 && strlen($lada) == 3){

                        $nuevoTelefonoCelular = '('.$lada.') '.substr($telefono,0,3).'-'.substr($telefono,3,-2).'-'.substr($telefono,5);
                        $actualizarTelCelularPersona  = Persona::find($persona->id);
                        $actualizarTelCelularPersona->Tel_efonoCelular = $nuevoTelefonoCelular;

                        if($actualizarTelCelularPersona->save()){
                            return response()->json(
                                [
                                    "Status"   => "true",
                                    "Mensaje" => "Su número de teléfono ".$actualizarTelCelularPersona->Tel_efonoCelular." ha sido agregado."
                                ]
                            ); 
                        }else{
                            return response()->json(
                                [
                                    "Status"   => "false",
                                    "Mensaje" => "Ocurrio un error al registrar su número de teléfono.  Vuelva intentarlo de nuevo"
                                ]
                            ); 
                        }
                } else {
                    return response()->json(
                        [
                            "Status"   => "false",
                            "Mensaje" => "Su número de teléfono no es valido. Vuelva intentarlo de nuevo"
                        ]
                    ); 
                }
         } else {
            return response()->json(
                [
                    "Status"   => "false",
                    "Mensaje" => "Su número de teléfono ya esta registrado"
                ]
            ); 
         }

    }

    public function datosPersonalesv2(Request $request){
        $rfc = strtoupper($request->rfc);
        $idCliente =  $request->idCliente;
        Funciones::selecionarBase($idCliente);
        
        $persona =   DB::table('Persona')
                        ->join('Cliente','Persona.Cliente','=','Cliente.id')
                        ->join('DatosFiscales','DatosFiscales.id','=','Persona.DatosFiscales')
                        ->select('Persona.id',
                                DB::raw('CONCAT(Persona.Nombre," ", Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) as nombreEmpleado'),
                                'Persona.Tel_efonoCelular',
                                'Cliente.Descripci_on')
                        ->where('DatosFiscales.RFC','=',$rfc)
                        ->where('Persona.Cliente','=',$idCliente)
                        ->where('Persona.Estatus','=','1')
                        ->first();       
           
        $puestoEmpleado = DB::table('PuestoEmpleado')->select('PuestoEmpleado.id')->where('PuestoEmpleado.Empleado','=',$persona->id)->where('PuestoEmpleado.Estatus','=',"1")->first();
        $asistenciaEmpleadoLaboral = DB::table('AsistenciaEmpleadoLaboral')
            ->select('AsistenciaEmpleadoLaboral.id','AsistenciaEmpleadoLaboral.jornada_id')              
            ->where('AsistenciaEmpleadoLaboral.idPuestoEmpleado','=',$puestoEmpleado->id)
            ->first();
        $jornada = DB::table('AsistenciaJornada')->select('*')->where('idJornada','=',$asistenciaEmpleadoLaboral->jornada_id)->first();
        if($jornada->idJornada == 8){
                $asistenciaHorarioEmpleado =  DB::table('AsistenciaEmpleadoHorario')
                ->select(DB::raw("GROUP_CONCAT(DISTINCT CONCAT(AsistenciaEmpleadoHorario.horaEntrada)) as HoraEntrada"),
                        DB::raw("GROUP_CONCAT(DISTINCT CONCAT(AsistenciaEmpleadoHorario.horaSalida)) as HoraSalida"))
                ->where('AsistenciaEmpleadoHorario.idEmpleadoLaboral',$asistenciaEmpleadoLaboral->id)
                ->where('AsistenciaEmpleadoHorario.dia_id','=', 8 )
                ->get(); 
        }else{
            //horario personalizado
            $fecha = new \DateTime();
            $fechaHoy = $fecha->format('Y-m-d');
            $asistenciaHorarioEmpleado =  DB::table('AsistenciaEmpleadoHorario')
                ->select(DB::raw("GROUP_CONCAT(DISTINCT CONCAT(AsistenciaEmpleadoHorario.horaEntrada)) as HoraEntrada"),
                        DB::raw("GROUP_CONCAT(DISTINCT CONCAT(AsistenciaEmpleadoHorario.horaSalida)) as HoraSalida"))
                ->where('AsistenciaEmpleadoHorario.idEmpleadoLaboral',$asistenciaEmpleadoLaboral->id)
                ->where('AsistenciaEmpleadoHorario.dia_id','=', (date('w', strtotime($fechaHoy)) + 1) )
                // ->where('AsistenciaEmpleadoHorario.dia_id','=', 5 )
                ->get();  
        } 

        $ubicacionEmpresa =  DB::table("UbicacionEmpresa")
        ->select('UbicacionEmpresa.idUbicacionEmpresa','UbicacionEmpresa.latitude','UbicacionEmpresa.longitude','UbicacionEmpresa.radio','UbicacionEmpresa.descripcion as NombreComercial')
        ->join('AsistenciaEmpleadoLaboralEmpresa','UbicacionEmpresa.idUbicacionEmpresa','=','AsistenciaEmpleadoLaboralEmpresa.ubicacionEmpresa_id')
        ->where('AsistenciaEmpleadoLaboralEmpresa.puestoEmpleado_id','=',$puestoEmpleado->id)
        ->get();
        if($ubicacionEmpresa){
                return response()->json(
                    [
                        "Status"         => "true",
                        "Estatus"        => true,
                        "persona"        => $persona,
                        "horariosEntrada"=> $asistenciaHorarioEmpleado[0]->HoraEntrada,
                        "horariosSalida" => $asistenciaHorarioEmpleado[0]->HoraSalida,
                        'fechaHoy'       => date('Y/d/m',strtotime($fechaHoy)),
                        "ubicacionEmpresas"=> $ubicacionEmpresa
                    ]
                ); 
        }else{
            return response()->json([
                "Status"   => "false",
                "Mensaje" => "No tiene asignado una ubicación para registrar asistencia"
            ]);
        }
        

    }

    /**
     * Metodo para registrar asistencia de un empleado
     */

    public function registrarAsistenciaV2(Request $request ){
        $rfc = strtoupper($request->rfc);
        $idCliente = $request->idCliente;
        $idUbicacion =  $request->idUbicacion;

        Funciones::selecionarBase($idCliente);
        //obtenemos la fecha a la que se realiza la peticion
        $fechaHoy = new \DateTime();
        $fechaRegistroHoy = $fechaHoy->format('Y-m-d');
        $horaRegistroHoy =  $fechaHoy->format('H:i:s');



        $persona =  DB::table('Persona')->join('Cliente','Persona.Cliente','=','Cliente.id')->join('DatosFiscales','DatosFiscales.id','=','Persona.DatosFiscales')
                       ->select('Persona.id')->where('DatosFiscales.RFC','=',$rfc)->where('Persona.Cliente','=',$idCliente)->where('Persona.Estatus','=','1')->first();

        if($persona){
             //obtener el idPuestoEmpleado de persona
             $idPuestoEmpleado = DB::table('PuestoEmpleado')->select('PuestoEmpleado.id')->where('PuestoEmpleado.Empleado','=',$persona->id)
             ->where('PuestoEmpleado.Estatus','=',"1")->first();

             $asistenciaEmpleadoLaboral = DB::table('AsistenciaEmpleadoLaboral')
                                           ->select('AsistenciaEmpleadoLaboral.*')
                                           ->where('AsistenciaEmpleadoLaboral.idPuestoEmpleado','=',$idPuestoEmpleado->id)
                                         //  ->where('AsistenciaEmpleadoLaboral.'.$this->nombreDelDia(),'=','1')
                                           ->first();
            // si es 24x24 o personalizado
           $tipoPerfil =  DB::table('AsistenciaJornadaHorario')->select('AsistenciaJornadaHorario.*')->join('AsistenciaEmpleadoLaboral','AsistenciaJornadaHorario.asistenciaJornada_id','=','AsistenciaEmpleadoLaboral.jornada_id')
           ->where('AsistenciaEmpleadoLaboral.id','=',$asistenciaEmpleadoLaboral->id)->first();

           if($tipoPerfil->asistenciaDia_id == 8){

           }else{
                //modificar porque estoy usndo una fecha en especifico
                $numeroDelDia = date('w', strtotime($fechaRegistroHoy)) + 1;
                $horarioAsignado =  DB::table('AsistenciaEmpleadoHorario')->select('AsistenciaEmpleadoHorario.*')->where('idEmpleadoLaboral','=',$asistenciaEmpleadoLaboral->id)->where('dia_id','=',$numeroDelDia)->get();
           }

    

           $horaRegistro =  strtotime($horaRegistroHoy);

           if($horarioAsignado){
                    //buscamos si hay entradas
                    foreach ($horarioAsignado as $horario)  {
                        $horaEntrada =  strtotime($horario->HoraEntrada.'+ 16 minute');
                        //sera el rango para obtener las horas de registros
                        $horaMinimoEntrada = strtotime($horario->HoraEntrada.' -48 minute');
                        $horaEntradaRetardo = strtotime($horario->HoraEntrada.' + 60 minute');

                        if($horaRegistro >= $horaMinimoEntrada && $horaRegistro <= $horaEntradaRetardo){
                            $tipoDeAsistencia =  "Entrada";
                            $horaDeRegistro = date('H:i:s',$horaRegistro);
                            $horaAsignado =  $fechaRegistroHoy.' '.$horario->HoraEntrada;
                                //es retardo 1= asistencia, 2= retardo, 3= Falta
                                if($horaRegistro > $horaEntrada && $horaRegistro < $horaEntradaRetardo){
                                $EstatusAsistencia = 2;
                                }else if($horaRegistro <= $horaEntrada){
                                $EstatusAsistencia = 1;
                                }
                                
                                
                            
                            break;
                        }else if($horaRegistro >= $horaEntradaRetardo && $horaRegistro < strtotime($horario->HoraSalida)){
                            $tipoDeAsistencia =  "Entrada";
                            $horaDeRegistro = date('H:i:s',$horaRegistro);
                            $horaAsignado =   $fechaRegistroHoy.' '.$horario->HoraEntrada;
                            $EstatusAsistencia = 3;        
                            break;
                        } 
                }//for 

                //si no encontro horas de entrada....
                if( !isset($tipoDeAsistencia) ){
                        //return response()->json($horarioAsignado);
                        foreach ($horarioAsignado as $horario)  {
    
                                $horaEntrada =  strtotime($horario->HoraEntrada.'+ 16 minute'); 
                                //sera el rango para obtener las horas de registros
                                $horaMinimoEntrada = strtotime($horario->HoraEntrada.' -50 minute');
                                $horaEntradaRetardo = strtotime($horario->HoraEntrada.' + 60 minute');

                                if($horaRegistro >= strtotime($horario->HoraSalida) && $horaRegistro <= strtotime($horario->HoraSalida.' +55 minute' )) {
                                    $tipoDeAsistencia =  "Salida";
                                    $horaSalida = $horario->HoraSalida;
                                    $horaAsignado =  $fechaRegistroHoy.' '.$horario->HoraSalida;
                                    $EstatusAsistencia = 0;
                                break;

                                }else{

                                    if($horaRegistro >=  strtotime($horario->HoraSalida)){
                                        $tipoDeAsistencia =  "Salida";
                                        $horaSalida = $horario->HoraSalida;
                                        $horaAsignado =  $fechaRegistroHoy.' '.$horario->HoraSalida;
                                        $EstatusAsistencia = 0;
                                    }else if($horaRegistro <= $horaMinimoEntrada){
                                        //como es el dia siguiente pero el registro es del dia anterior resto 1 dia a la fecha actual
                                    // $horaAsignado =  date('Y-m-j', strtotime($fechaRegistroHoy.' -1 day' )).' '.$horario->HoraSalida;

                                        $tipoDeAsistencia =  "Salida";
                                        $horaSalida = $horario->HoraSalida;
                                        $horaAsignado =  date('Y-m-j', strtotime($fechaRegistroHoy.' -1 day' )).' '.$horario->HoraSalida;
                                        $horaAsignado =  $fechaRegistroHoy.' '.$horario->HoraSalida;
                                        $EstatusAsistencia = 0;
                                    }
                                
                                }
                                
                        }//for 
                }

                    $fechaAsistencia = $fechaHoy->format('Y-m-d H:i:s');
                    $resultado = DB::table('AsistenciaHorario')
                                ->insert(
                                            ['idEmpleadoLaboral' => $asistenciaEmpleadoLaboral->id, 
                                            'Fecha' => $fechaAsistencia,
                                            'idUbicacion' => $idUbicacion,
                                            'FechaAsistencia' => $horaAsignado,
                                            'Status' => $EstatusAsistencia,
                                            'tipo'  => $tipoDeAsistencia                       
                                            ]);
                    if($resultado){
                        return response()->json(
                            [
                                "Status"   => "true",
                                "Estatus"  => true,
                                "Titulo"    => "Mensaje de confirmación",
                                "Mensaje"   => "Su asistencia de ".$tipoDeAsistencia." a sido registrada"
                            ]
                        );
                    }


           }else{
           //esto es temporal
            $fechaAsistencia = $fechaHoy->format('Y-m-d H:i:s');
            $resultado = DB::table('AsistenciaHorario')
                        ->insert(
                                    ['idEmpleadoLaboral' => $asistenciaEmpleadoLaboral->id, 
                                    'Fecha' => $fechaAsistencia,
                                    'idUbicacion' => $idUbicacion,
                                    'FechaAsistencia' => $fechaAsistencia,
                                    'Status' => 1,
                                    'tipo'  => 'Entrada'                       
                                    ]);
            if($resultado){
                return response()->json(
                    [ 
                        "Status"   => "true",
                        "Estatus"  => true,
                        "Titulo"    => "Mensaje de confirmación",
                        "Mensaje"   => "Su asistencia de ".$tipoDeAsistencia." a sido registrada"
                    ]
                );
            }

           }
        }//if existe persona?
    }//fin del metodo registrar asistencia



    public function get_ubicacionMapa(Request $request){
        $rfc = strtoupper($request->rfc);
        $idCliente =  $request->idCliente;
        Funciones::selecionarBase($idCliente);

        $fechaHoy = new \DateTime();
        $fechaRegistroHoy = $fechaHoy->format('Y-m-d');
        $horaRegistroHoy =  $fechaHoy->format('H:i:s');
        
        $persona =   DB::table('Persona')
                        ->join('Cliente','Persona.Cliente','=','Cliente.id')
                        ->join('DatosFiscales','DatosFiscales.id','=','Persona.DatosFiscales')
                        ->select('Persona.id',
                                DB::raw('CONCAT(Persona.Nombre," ", Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) as nombreEmpleado'),
                                'Persona.Tel_efonoCelular',
                                'Cliente.Descripci_on')
                        ->where('DatosFiscales.RFC','=',$rfc)
                        ->where('Persona.Cliente','=',$idCliente)
                        ->where('Persona.Estatus','=','1')
                        ->first();       
        
        $puestoEmpleado = DB::table('PuestoEmpleado')->select('PuestoEmpleado.id')->where('PuestoEmpleado.Empleado','=',$persona->id)->where('PuestoEmpleado.Estatus','=',"1")->first();
        $asistenciaEmpleadoLaboral = DB::table('AsistenciaEmpleadoLaboral')
            ->select('AsistenciaEmpleadoLaboral.id','AsistenciaEmpleadoLaboral.jornada_id')              
            ->where('AsistenciaEmpleadoLaboral.idPuestoEmpleado','=',$puestoEmpleado->id)
            ->first();

        $ubicacionEmpresa =  DB::table("UbicacionEmpresa")
        ->select('UbicacionEmpresa.idUbicacionEmpresa','UbicacionEmpresa.latitude','UbicacionEmpresa.longitude','UbicacionEmpresa.radio','UbicacionEmpresa.descripcion as NombreComercial')
        ->join('AsistenciaEmpleadoLaboralEmpresa','UbicacionEmpresa.idUbicacionEmpresa','=','AsistenciaEmpleadoLaboralEmpresa.ubicacionEmpresa_id')
        ->where('AsistenciaEmpleadoLaboralEmpresa.puestoEmpleado_id','=',$puestoEmpleado->id)
        ->get();


        // si es 24x24 o personalizado
        $tipoPerfil =  DB::table('AsistenciaJornadaHorario')->select('AsistenciaJornadaHorario.*')->join('AsistenciaEmpleadoLaboral','AsistenciaJornadaHorario.asistenciaJornada_id','=','AsistenciaEmpleadoLaboral.jornada_id')
        ->where('AsistenciaEmpleadoLaboral.id','=',$asistenciaEmpleadoLaboral->id)->first();

        if($tipoPerfil->asistenciaDia_id == 8){

        }else{
                //modificar porque estoy usndo una fecha en especifico
                $numeroDelDia = date('w', strtotime($fechaRegistroHoy)) + 1;
                $horarioAsignado =  DB::table('AsistenciaEmpleadoHorario')->select('AsistenciaEmpleadoHorario.*')->where('idEmpleadoLaboral','=',$asistenciaEmpleadoLaboral->id)->where('dia_id','=',$numeroDelDia)->get();
        }

        $horaRegistro =  strtotime($horaRegistroHoy);
        if($horarioAsignado){

                //buscamos si hay entradas
                foreach ($horarioAsignado as $horario)  {
                        $horaEntrada =  strtotime($horario->HoraEntrada.'+ 16 minute');
                        //sera el rango para obtener las horas de registros
                        $horaMinimoEntrada = strtotime($horario->HoraEntrada.' -48 minute');
                        $horaEntradaRetardo = strtotime($horario->HoraEntrada.' + 60 minute');

                        if($horaRegistro >= $horaMinimoEntrada && $horaRegistro <= $horaEntradaRetardo){
                            $tipoDeAsistencia =  "Entrada";
                            $horaDeRegistro = date('H:i:s',$horaRegistro);
                            $horaAsignado =  $horario->HoraEntrada;
                            break;
                            
                        }else if($horaRegistro >= $horaEntradaRetardo && $horaRegistro < strtotime($horario->HoraSalida)){
                            $tipoDeAsistencia =  "Entrada";
                            $horaDeRegistro = date('H:i:s',$horaRegistro);
                            $horaAsignado =  $horario->HoraEntrada;
                
                            break;
                        } 
                }//for 

                //si no encontro horas de entrada....
                if( !isset($tipoDeAsistencia) ){
                        foreach ($horarioAsignado as $horario)  {
                                $horaEntrada =  strtotime($horario->HoraEntrada.'+ 16 minute');
                                //sera el rango para obtener las horas de registros
                                $horaMinimoEntrada = strtotime($horario->HoraEntrada.' -50 minute');
                                $horaEntradaRetardo = strtotime($horario->HoraEntrada.' + 60 minute');

                                if($horaRegistro >= strtotime($horario->HoraSalida) ){
                                    $tipoDeAsistencia =  "Salida";
                                    $horaDeRegistro = date('H:i:s',$horaRegistro);
                                    $horaAsignado =  $horario->HoraSalida;        
                                    break;
                                }else if($horaRegistro <= $horaMinimoEntrada){
                                    $tipoDeAsistencia =  "Salida";
                                    $horaDeRegistro = date('H:i:s',$horaRegistro);
                                    $horaAsignado =  $horario->HoraSalida;
                
                                    break;
                                }
                        }//for 
                }
                if($ubicacionEmpresa){
                        return response()->json(
                            [
                                "Status"         => "true",
                                "Estatus"        => true,
                                'tipoAsistencia' => $tipoDeAsistencia,
                                "ubicacionEmpresas"=> $ubicacionEmpresa
                            ]
                        ); 
                }else{
                    return response()->json([
                        "Status"   => "false",
                        "Mensaje" => "No tiene asignado una ubicación para registrar asistencia"
                    ]);
                }
        }else{
            if($ubicacionEmpresa){
                    return response()->json(
                        [
                            "Status"         => "true",
                            "Estatus"        => true,
                            'tipoAsistencia' => 'Asistencia',
                            "ubicacionEmpresas"=> $ubicacionEmpresa
                        ]
                    ); 
            }else{
                return response()->json([
                    "Status"   => "false",
                    "Mensaje" => "No tiene asignado una ubicación para registrar asistencia"
                ]);
            }
        }

        
    }



    public function historialAsistenciaUbicacion(Request $request){
        $rfc = strtoupper($request->rfc);
        $idCliente = $request->idCliente;
        Funciones::selecionarBase($idCliente);

            $persona =  DB::table('Persona')
                            ->join('Cliente','Persona.Cliente','=','Cliente.id')
                            ->join('DatosFiscales','DatosFiscales.id','=','Persona.DatosFiscales')
                            ->select('Persona.id')
                            ->where('DatosFiscales.RFC','=',$rfc)
                            ->where('Persona.Cliente','=',$idCliente)
                            ->where('Persona.Estatus','=','1')
                            ->first();

            $asistenciaEmpleadoLaboral =  DB::table('AsistenciaEmpleadoLaboral')
                                            ->select('AsistenciaEmpleadoLaboral.id')
                                            ->join('PuestoEmpleado','AsistenciaEmpleadoLaboral.idPuestoEmpleado','=','PuestoEmpleado.id')
                                            ->join('Persona','PuestoEmpleado.Empleado','=','Persona.id')
                                            ->where('Persona.id','=',$persona->id)
                                            ->first();

            $resultadoHistorial = DB::table('AsistenciaHorario')
            ->select(DB::raw("DISTINCT CONCAT(DATE_FORMAT(AsistenciaHorario.Fecha,'%d de '),
            (SELECT Nombre FROM Mes WHERE id = DATE_FORMAT( AsistenciaHorario.Fecha, '%c' )), 
                                ' del ',DATE_FORMAT( AsistenciaHorario.Fecha, '%Y')
                                ) as fechaRegistro"),
                    DB::raw("CONCAT(DATE_FORMAT(AsistenciaHorario.Fecha,'%h:%i:%s %p')) as horaRegistro"),
                    'UbicacionEmpresa.descripcion','AsistenciaHorario.tipo','AsistenciaHorario.Status')
            ->join('UbicacionEmpresa','UbicacionEmpresa.idUbicacionEmpresa','=','AsistenciaHorario.idUbicacion')
            ->where('AsistenciaHorario.idEmpleadoLaboral','=',$asistenciaEmpleadoLaboral->id)
            ->where(DB::raw('date(AsistenciaHorario.Fecha)'),'>=','2020-11-15')
            ->orderBy('AsistenciaHorario.Fecha','DESC')
            ->limit(40)
            ->get();
            
            if($resultadoHistorial){
                return response()->json(
                    [
                        "Status"   => "true",
                        "Estatus"  => true,
                        "historial" => $resultadoHistorial
                    ]
                ); 
            }
        return response()->json("error");
    }



    public function registrarAsistenciaV3(Request $request ){
        $rfc = strtoupper($request->rfc);
        $idCliente = $request->idCliente;
        $idUbicacion =  $request->idUbicacion;

        Funciones::selecionarBase($idCliente);
        //obtenemos la fecha a la que se realiza la peticion
        $fechaHoy = new \DateTime();
        $fechaRegistroHoy = $fechaHoy->format('Y-m-d');
        $horaRegistroHoy =  $fechaHoy->format('H:i:s');



        $persona =  DB::table('Persona')->join('Cliente','Persona.Cliente','=','Cliente.id')->join('DatosFiscales','DatosFiscales.id','=','Persona.DatosFiscales')
                       ->select('Persona.id')->where('DatosFiscales.RFC','=',$rfc)->where('Persona.Cliente','=',$idCliente)->where('Persona.Estatus','=','1')->first();

        if($persona){
             //obtener el idPuestoEmpleado de persona
             $idPuestoEmpleado = DB::table('PuestoEmpleado')->select('PuestoEmpleado.id')->where('PuestoEmpleado.Empleado','=',$persona->id)
             ->where('PuestoEmpleado.Estatus','=',"1")->first();

             $asistenciaEmpleadoLaboral = DB::table('AsistenciaEmpleadoLaboral')
                                           ->select('AsistenciaEmpleadoLaboral.*')
                                           ->where('AsistenciaEmpleadoLaboral.idPuestoEmpleado','=',$idPuestoEmpleado->id)
                                         //  ->where('AsistenciaEmpleadoLaboral.'.$this->nombreDelDia(),'=','1')
                                           ->first();
            // si es 24x24 o personalizado
           $tipoPerfil =  DB::table('AsistenciaJornadaHorario')->select('AsistenciaJornadaHorario.*')->join('AsistenciaEmpleadoLaboral','AsistenciaJornadaHorario.asistenciaJornada_id','=','AsistenciaEmpleadoLaboral.jornada_id')
           ->where('AsistenciaEmpleadoLaboral.id','=',$asistenciaEmpleadoLaboral->id)->first();

           if($tipoPerfil->asistenciaDia_id == 8){

           }else{
                //modificar porque estoy usndo una fecha en especifico
                $numeroDelDia = date('w', strtotime($fechaRegistroHoy)) + 1;
                $horarioAsignado =  DB::table('AsistenciaEmpleadoHorario')->select('AsistenciaEmpleadoHorario.*')->where('idEmpleadoLaboral','=',$asistenciaEmpleadoLaboral->id)->where('dia_id','=',$numeroDelDia)->get();
           }

    

         //  $horaRegistro =  strtotime($horaRegistroHoy);
           $horaRegistro =  strtotime('15:58:00');

           if($horarioAsignado){
                    //buscamos si hay entradas
                    foreach ($horarioAsignado as $horario)  {
                        $horaEntrada =  strtotime($horario->HoraEntrada.'+ 16 minute');
                        //sera el rango para obtener las horas de registros
                        $horaMinimoEntrada = strtotime($horario->HoraEntrada.' -45 minute');
                        $horaEntradaRetardo = strtotime($horario->HoraEntrada.' + 60 minute');

                        if($horaRegistro >= $horaMinimoEntrada && $horaRegistro <= $horaEntradaRetardo){
                            $tipoDeAsistencia =  "Entrada";
                            $horaDeRegistro = date('H:i:s',$horaRegistro);
                            $horaAsignado =  $fechaRegistroHoy.' '.$horario->HoraEntrada;
                            //es retardo 1= asistencia, 2= retardo, 3= Falta
                            if($horaRegistro > $horaEntrada && $horaRegistro < $horaEntradaRetardo){
                                $EstatusAsistencia = 2;
                            }else if($horaRegistro <= $horaEntrada){
                              $EstatusAsistencia = 1;
                            }                         
                            break;

                        }else if($horaRegistro >= $horaEntradaRetardo && $horaRegistro < strtotime($horario->HoraSalida)){
                            $tipoDeAsistencia =  "Entrada";
                            $horaDeRegistro = date('H:i:s',$horaRegistro);
                            $horaAsignado =   $fechaRegistroHoy.' '.$horario->HoraEntrada;
                            $EstatusAsistencia = 3;        
                            break;
                        } 
                }//for 

                //si no encontro horas de entrada.... ahora buscamos salidas 3pm,4pm, 5pm == 5
                if( !isset($tipoDeAsistencia) ){
                        $horaSalidaTemporal = 0;

                        //return response()->json($horarioAsignado);
                        foreach ($horarioAsignado as $horario)  {
 
                                $horaEntrada =  strtotime($horario->HoraEntrada.'+ 16 minute'); 
                                //sera el rango para obtener las horas de registros
                                $horaMinimoEntrada = strtotime($horario->HoraEntrada.' -50 minute');
                                $horaEntradaRetardo = strtotime($horario->HoraEntrada.' + 60 minute');

                                if($horaRegistro >= strtotime($horario->HoraSalida) && $horaRegistro <= strtotime($horario->HoraSalida.' +55 minute' )) {
                                    $tipoDeAsistencia =  "Salida";
                                    $horaSalida = $horario->HoraSalida;
                                    $horaAsignado =  $fechaRegistroHoy.' '.$horario->HoraSalida;
                                    $EstatusAsistencia = 0;
                                break;

                                }else{

                                    if($horaRegistro >=  strtotime($horario->HoraSalida)){
                                        $tipoDeAsistencia =  "Salida 7";
                                        $horaSalida = $horario->HoraSalida;
                                        $horaAsignado =  $fechaRegistroHoy.' '.$horario->HoraSalida;
                                        $EstatusAsistencia = 0;
                                    }else if($horaRegistro <= $horaMinimoEntrada){
                                        //como es el dia siguiente pero el registro es del dia anterior resto 1 dia a la fecha actual
                                       // $horaAsignado =  date('Y-m-j', strtotime($fechaRegistroHoy.' -1 day' )).' '.$horario->HoraSalida;

                                        $tipoDeAsistencia =  "Salida 9";
                                        $horaSalida = $horario->HoraSalida;
                                        $horaAsignado =  date('Y-m-j', strtotime($fechaRegistroHoy.' -1 day' )).' '.$horario->HoraSalida;
                                        $horaAsignado =  $fechaRegistroHoy.' '.$horario->HoraSalida;
                                        $EstatusAsistencia = 0;
                                    }
                                  
                                }
                                
                        }//for 
                }
                   // $fechaAsistencia = $fechaHoy->format('Y-m-d H:i:s');
                    return response()->json(
                        ['idEmpleadoLaboral' => $asistenciaEmpleadoLaboral->id, 
                        'Fecha' => $fechaRegistroHoy.' '.date('H:i:s',$horaRegistro),
                        'idUbicacion' => $idUbicacion,
                        'FechaAsistencia' => $horaAsignado,
                        'Status' => $EstatusAsistencia,
                        'tipo'  => $tipoDeAsistencia                       
                        ]
                    );

                    // $resultado = DB::table('AsistenciaHorario')
                    //             ->insert(
                    //                         ['idEmpleadoLaboral' => $asistenciaEmpleadoLaboral->id, 
                    //                         'Fecha' => $fechaAsistencia,
                    //                         'idUbicacion' => $idUbicacion,
                    //                         'FechaAsistencia' => $horaAsignado,
                    //                         'Status' => $EstatusAsistencia,
                    //                         'tipo'  => $tipoDeAsistencia                       
                    //                         ]);
                    // if($resultado){
                    //     return response()->json(
                    //         [
                    //             "Status"   => "true",
                    //             "Estatus"  => true,
                    //             "Titulo"    => "Mensaje de confirmación",
                    //             "Mensaje"   => "Su asistencia de ".$tipoDeAsistencia." a sido registrada"
                    //         ]
                    //     );
                    // }
           }else{
        //    //esto es temporal
        //     $fechaAsistencia = $fechaHoy->format('Y-m-d H:i:s');
        //     $resultado = DB::table('AsistenciaHorario')
        //                 ->insert(
        //                             ['idEmpleadoLaboral' => $asistenciaEmpleadoLaboral->id, 
        //                             'Fecha' => $fechaAsistencia,
        //                             'idUbicacion' => $idUbicacion,
        //                             'FechaAsistencia' => $fechaAsistencia,
        //                             'Status' => 1,
        //                             'tipo'  => 'Entrada'                       
        //                             ]);
        //     if($resultado){
        //         return response()->json(
        //             [ 
        //                 "Status"   => "true",
        //                 "Estatus"  => true,
        //                 "Titulo"    => "Mensaje de confirmación",
        //                 "Mensaje"   => "Su asistencia de ".$tipoDeAsistencia." a sido registrada"
        //             ]
        //         );
        //     }

           }
        }//if existe persona?
    }//fin del metodo registrar asistencia


    public function obtenerURLLogo(Request $request){
        $idCliente = $request->idCliente;
        Funciones::selecionarBase($idCliente);

        $urlImage = DB::table('CelaRepositorioC')
                    ->join('Cliente','CelaRepositorioC.idRepositorio','=','Cliente.Logotipo')
                    ->select('CelaRepositorioC.Ruta')
                    ->where('Cliente.id','=',$idCliente)
                    ->first();
        return response()->json($urlImage);
   }



    public function verificar_usuario_sistema2(Request $request){
            $cadenaMovil =  "kcjMRg2kYw8vbnfWHtBTh59BzPKfQz";
            if($request->cadena === $cadenaMovil){
                $usuario = "suinpacmovilasistencia";
                $pass = "Android13072020$";
                $user =  User::where('Usuario','=',$usuario)->where('EstadoActual','=',1)->first();
                if($user){
                    JWTAuth::factory()->setTTL(600);
                    $token = JWTAuth::fromUser($user);
                    return response()->json([
                        "Status"   => "true",
                        "Estatus" => true,
                        "token" => $token
                    ]);
                }else{
                    return response()->json([ 
                        "Status"   => "false",
                        "Estatus" => true,
                        "Mensaje" => "Datos incorrectos"
                    ]);
                }
                
            }
            return response()->json([ 
                "Status"   => "false",
                "Estatus" => true,
                "Mensaje" => "Datos incorrectos"
            ]);
    } 


    public function existe_empleado2(Request $request){ 
        try {
            //code...
    
                $rfc = strtoupper($request->rfc);
                $idCliente =  $request->idCliente;
                Funciones::selecionarBase($idCliente);

                $fecha = new \DateTime();
                $fechaHoy = $fecha->format('Y-m-d');
            
                $persona =   DB::table('Persona')
                                ->join('Cliente','Persona.Cliente','=','Cliente.id')
                                ->join('DatosFiscales','DatosFiscales.id','=','Persona.DatosFiscales')
                                ->select('Persona.id',
                                        DB::raw('CONCAT(Persona.Nombre," ", Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) as nombreEmpleado'),
                                        'Persona.Tel_efonoCelular',
                                        'Cliente.Descripci_on')
                                ->where('DatosFiscales.RFC','=',$rfc)
                                ->where('Persona.Cliente','=',$idCliente)
                                ->where('Persona.Estatus','=','1')
                                ->first();  
                if($persona){
                
                    //buscamos si existe el puesto empleado
                    $puestoEmpleado = DB::table('PuestoEmpleado')->select('PuestoEmpleado.id')->where('PuestoEmpleado.Empleado','=',$persona->id)->where('PuestoEmpleado.Estatus','=',"1")->first();
                    if($puestoEmpleado){

                        //Buscamos si existe registro del puestoEmpleado en la tabla AsistenciaEmpleadoLoral
                        $asistenciaEmpleadoLaboral = DB::table('AsistenciaEmpleadoLaboral')
                                                        ->select('AsistenciaEmpleadoLaboral.id','AsistenciaEmpleadoLaboral.jornada_id')              
                                                        ->where('AsistenciaEmpleadoLaboral.idPuestoEmpleado','=',$puestoEmpleado->id)
                                                        ->first(); 
                        if($asistenciaEmpleadoLaboral){

                            //Buscamos el empleado tiene jornada horario extra
                            $tipoJornadaHorarioExtra  = DB::select('select GROUP_CONCAT(DISTINCT CONCAT(aehe.HoraEntrada)) as HoraEntrada, GROUP_CONCAT(DISTINCT CONCAT(aehe.HoraSalida)) as HoraSalida, aheed.jornada_id
                                                                            FROM AsistenciaEmpleadoHorarioExtra aehe 
                                                                            INNER JOIN AsistenciaEmpleadoExtra_has_Detalle aeehd ON(aehe.id = aeehd.HorarioExtra_id) 
                                                                            INNER JOIN AsistenciaHorarioEmpleadoExtraDetalle aheed ON (aheed.id = aeehd.HorarioExtraDetalle_id) 
                                                                            WHERE "'.$fechaHoy.'" BETWEEN aheed.FechaInicio AND aheed.FechaFinal AND aehe.idEmpleadoLaboral = '.$asistenciaEmpleadoLaboral->id.' LIMIT 1');   
                            
                            if($tipoJornadaHorarioExtra[0]->jornada_id > 0){
                                    //si tiene alguna el tipo de dia para diferenciar si es 24x24(etc) o personalizado(semanal) SELECT * FROM AsistenciaJornadaHorario WHERE asistenciaJornada_id = 4
                                    $tipoDia = DB::table('AsistenciaJornadaHorario')->select('asistenciaDia_id')->where('asistenciaJornada_id','=',$tipoJornadaHorarioExtra[0]->jornada_id)->first();
                                    
                                    
                                    if($tipoDia->asistenciaDia_id == 8 || $tipoDia->asistenciaDia_id ==='8'){

                                        //para horario 24x24

                                    } else {
                                        $horarioExtras = DB::select('select GROUP_CONCAT(DISTINCT CONCAT(aehe.HoraEntrada)) as HoraEntrada, GROUP_CONCAT(DISTINCT CONCAT(aehe.HoraSalida)) as HoraSalida 
                                        FROM AsistenciaEmpleadoHorarioExtra aehe 
                                        INNER JOIN AsistenciaEmpleadoExtra_has_Detalle aeehd ON(aehe.id = aeehd.HorarioExtra_id) 
                                        INNER JOIN AsistenciaHorarioEmpleadoExtraDetalle aheed ON (aheed.id = aeehd.HorarioExtraDetalle_id) 
                                        WHERE "'.$fechaHoy.'" BETWEEN aheed.FechaInicio AND aheed.FechaFinal AND aehe.idEmpleadoLaboral = '.$asistenciaEmpleadoLaboral->id);  
                                    }


                                    $ubicacionEmpresa =  DB::table("UbicacionEmpresa")
                                    ->select('UbicacionEmpresa.idUbicacionEmpresa','UbicacionEmpresa.latitude','UbicacionEmpresa.longitude','UbicacionEmpresa.radio','UbicacionEmpresa.descripcion as NombreComercial')
                                    ->join('AsistenciaEmpleadoLaboralEmpresa','UbicacionEmpresa.idUbicacionEmpresa','=','AsistenciaEmpleadoLaboralEmpresa.ubicacionEmpresa_id')
                                    ->where('AsistenciaEmpleadoLaboralEmpresa.puestoEmpleado_id','=',$puestoEmpleado->id)
                                    ->get();

                                    if(!$ubicacionEmpresa){
                                        return response()->json([
                                            "Status"   => "false",
                                            "Estatus" => false,
                                            "Mensaje" => "No tiene asignado una ubicación. Comuníquese con su administrador"
                                        ]);  

                                    } else {
                                        return response()->json(
                                            [
                                                "Estatus"        => true,
                                                "nombreEmpleado" => $persona->nombreEmpleado,
                                                "telefono"       => $persona->Tel_efonoCelular,
                                                "empresa"        => $persona->Descripci_on,
                                                "horariosEntrada"=> $horarioExtras[0]->HoraEntrada,
                                                "horariosSalida" => $horarioExtras[0]->HoraSalida,
                                                'fechaHoy2'       => date('Y/d/m',strtotime($fechaHoy)),
                                                'fechaHoy'      => strftime("%d de %b", strtotime($fechaHoy)),
                                                "ubicacionEmpresas"=> $ubicacionEmpresa
                                            ]
                                        ); 
                                    }



                            } else {
                                    //para horarios normales que no son extras
                                    //buscamos que tipo de jornada tiene el empleado
                                    //SELECT asistenciaDia_id from AsistenciaJornadaHorario WHERE asistenciaJornada_id = 4 limit 1
                                    $jornada = DB::table('AsistenciaJornadaHorario')->select('AsistenciaJornadaHorario.asistenciaDia_id')
                                    ->where('asistenciaJornada_id',$asistenciaEmpleadoLaboral->jornada_id)
                                    ->first();


                                    if($jornada){
                                                if($jornada->asistenciaDia_id == 8 || $jornada->asistenciaDia_id === "8"){
                                                    //perfil de 24x24
                                                        $asistenciaHorarioEmpleado =  DB::table('AsistenciaEmpleadoHorario')
                                                        ->select(DB::raw("GROUP_CONCAT(DISTINCT CONCAT(AsistenciaEmpleadoHorario.horaEntrada)) as HoraEntrada"),
                                                                DB::raw("GROUP_CONCAT(DISTINCT CONCAT(AsistenciaEmpleadoHorario.horaSalida)) as HoraSalida"))
                                                        ->where('AsistenciaEmpleadoHorario.idEmpleadoLaboral',$asistenciaEmpleadoLaboral->id)
                                                        ->where('AsistenciaEmpleadoHorario.dia_id','=', 8 )
                                                        ->get(); 
                                                        if(!$asistenciaHorarioEmpleado){
                                                            return response()->json([
                                                                "Status"   => "false",
                                                                "Estatus" => false,
                                                                "Mensaje" => "No tiene registrado horarios"
                                                            ]); 
                                                        }
                                                } else {

                                                        $asistenciaHorarioEmpleado =  DB::table('AsistenciaEmpleadoHorario')
                                                            ->select(DB::raw("GROUP_CONCAT(DISTINCT CONCAT(AsistenciaEmpleadoHorario.horaEntrada)) as HoraEntrada"),
                                                                    DB::raw("GROUP_CONCAT(DISTINCT CONCAT(AsistenciaEmpleadoHorario.horaSalida)) as HoraSalida"))
                                                            ->where('AsistenciaEmpleadoHorario.idEmpleadoLaboral',$asistenciaEmpleadoLaboral->id)
                                                            ->where('AsistenciaEmpleadoHorario.dia_id','=', (date('w', strtotime($fechaHoy)) + 1) )
                                                            // ->where('AsistenciaEmpleadoHorario.dia_id','=', 5 )
                                                            ->get();  
                                                        if(!$asistenciaHorarioEmpleado){
                                                            return response()->json([
                                                                "Status"   => "false",
                                                                "Estatus" => false,
                                                                "Mensaje" => "No tiene registrado horarios"
                                                            ]); 
                                                        }
                                                }

                                                $ubicacionEmpresa =  DB::table("UbicacionEmpresa")
                                                ->select('UbicacionEmpresa.idUbicacionEmpresa','UbicacionEmpresa.latitude','UbicacionEmpresa.longitude','UbicacionEmpresa.radio','UbicacionEmpresa.descripcion as NombreComercial')
                                                ->join('AsistenciaEmpleadoLaboralEmpresa','UbicacionEmpresa.idUbicacionEmpresa','=','AsistenciaEmpleadoLaboralEmpresa.ubicacionEmpresa_id')
                                                ->where('AsistenciaEmpleadoLaboralEmpresa.puestoEmpleado_id','=',$puestoEmpleado->id)
                                                ->get(); 
                                                                    
                                                if(!$ubicacionEmpresa){
                                                    return response()->json([
                                                        "Status"   => "false",
                                                        "Estatus" => false,
                                                        "Mensaje" => "No tiene asignado una ubicación. Comuníquese con su administrador"
                                                    ]);  

                                                } else {
                                                    return response()->json(
                                                        [
                                                            "Estatus"        => true,
                                                            "nombreEmpleado" => $persona->nombreEmpleado,
                                                            "telefono"       => $persona->Tel_efonoCelular,
                                                            "empresa"        => $persona->Descripci_on,
                                                            "horariosEntrada"=> $asistenciaHorarioEmpleado[0]->HoraEntrada,
                                                            "horariosSalida" => $asistenciaHorarioEmpleado[0]->HoraSalida,
                                                            'fechaHoy'       => date('Y/d/m',strtotime($fechaHoy)),
                                                            "ubicacionEmpresas"=> $ubicacionEmpresa
                                                        ]
                                                    ); 
                                                }
                                    }
                                
                            }//fin if (si existe un horario extra para calcular horiario)

                        } else {
                            return response()->json([
                                "Status"   => "false",
                                "Estatus" => false,
                                "Mensaje" => "Sus datos aun no han sido dados de alta para la aplicación"
                            ]);  
                        }

                    //si no existe puesto empleado
                    } else {
                        return response()->json([
                            "Status"   => "false",
                            "Estatus" => false,
                            "Mensaje" => "RFC/Entidad Federativa son incorrectos"
                        ]);
                    }


                //Si persona no existe
                } else {
                    return response()->json([
                        "Status"   => "false",
                        "Estatus" => false,
                        "Mensaje" => "RFC/Entidad Federativa son incorrectos"
                    ]);
                }

        } catch (\Exception $e) {
            return response()->json([
                "Status"   => "false",
                "Estatus" => false,
                "Mensaje" => "Ocurrio un error, verifiquelo con el administrador.".$e->getMessage()
            ]);
        }

    }//fin del metodo 


    /*
    * Funcion que realiza el registro de asistencia 
    */
    public function registrarAsistenciaMovil(Request $request ){
        $rfc = strtoupper($request->rfc);
        $idCliente = $request->idCliente;
        $idUbicacion =  $request->idUbicacion;

        //tipoAsistencia: 1:HorarioExtra.  2:HorarioNormal
        $tipoHorario =  $request->tipoHorario;
        //tipo de perfil de trabajo: 8 = 24x24, 2=personalizado o semanal
        $tipoPerfil = $request->tipoPerfil; 

        Funciones::selecionarBase($idCliente);
        //obtenemos la fecha a la que se realiza la peticion
        $fechaHoy = new \DateTime();
        $fechaRegistroHoy = $fechaHoy->format('Y-m-d');
        $horaRegistroHoy =  $fechaHoy->format('H:i:s');

        $horaRegistroHoy = strtotime($horaRegistroHoy); 
        //$fechaRegistroHoy = '2020-11-09';
        
        $persona =  DB::table('Persona')->join('Cliente','Persona.Cliente','=','Cliente.id')->join('DatosFiscales','DatosFiscales.id','=','Persona.DatosFiscales')
              ->select('Persona.id')->where('DatosFiscales.RFC','=',$rfc)->where('Persona.Cliente','=',$idCliente)->where('Persona.Estatus','=','1')->first();

        if($persona){
            //obtener el idPuestoEmpleado de persona
            $idPuestoEmpleado = DB::table('PuestoEmpleado')->select('PuestoEmpleado.id')->where('PuestoEmpleado.Empleado','=',$persona->id)
            ->where('PuestoEmpleado.Estatus','=',"1")->first();

            $asistenciaEmpleadoLaboral = DB::table('AsistenciaEmpleadoLaboral')->select('AsistenciaEmpleadoLaboral.*')
                                        ->where('AsistenciaEmpleadoLaboral.idPuestoEmpleado','=',$idPuestoEmpleado->id)
                                        //  ->where('AsistenciaEmpleadoLaboral.'.$this->nombreDelDia(),'=','1')
                                        ->first();
            $numeroDelDia = date('w', strtotime($fechaRegistroHoy)) + 1;
            //horarioExtra:
            if($tipoHorario == 1){
                if($tipoPerfil == 8){

                }else{
                    
                    $horariosExtra = DB::select('select  aehe.HoraEntrada, aehe.HoraSalida, aehe.Tolerancia, aehe.Retardo
                                                FROM AsistenciaEmpleadoHorarioExtra aehe 
                                                INNER JOIN AsistenciaEmpleadoExtra_has_Detalle aeehd ON(aehe.id = aeehd.HorarioExtra_id) 
                                                INNER JOIN AsistenciaHorarioEmpleadoExtraDetalle aheed ON (aheed.id = aeehd.HorarioExtraDetalle_id) 
                                                WHERE "'.$fechaRegistroHoy.'"  BETWEEN aheed.FechaInicio AND aheed.FechaFinal AND aehe.idEmpleadoLaboral = '.$asistenciaEmpleadoLaboral->id.' and aehe.Dia_id = '.$numeroDelDia);
                }            
                
            //Horario normal:
            } else {
                $jornada = DB::table('AsistenciaJornadaHorario')->select('AsistenciaJornadaHorario.asistenciaDia_id')->where('asistenciaJornada_id',$asistenciaEmpleadoLaboral->jornada_id)->first();
                if($jornada){
                        if($jornada->asistenciaDia_id == 8 || $jornada->asistenciaDia_id === "8"){
                            //perfil de 24x24
                            $tipoPerfil =  8;
                                
                        } else {
                            $tipoPerfil =  2;
                            $horariosExtra =  DB::table('AsistenciaEmpleadoHorario')
                                ->select('AsistenciaEmpleadoHorario.HoraEntrada','AsistenciaEmpleadoHorario.HoraSalida','AsistenciaEmpleadoHorario.Tolerancia','AsistenciaEmpleadoHorario.Retardo')
                                ->where('AsistenciaEmpleadoHorario.idEmpleadoLaboral',$asistenciaEmpleadoLaboral->id)
                                ->where('AsistenciaEmpleadoHorario.dia_id','=', (date('w', strtotime($fechaRegistroHoy)) + 1) )
                                // ->where('AsistenciaEmpleadoHorario.dia_id','=', 5 )
                                ->get();

                        }
                }
            }//fin del if tipoHorario
 
           // return response()->json($horariosExtra);
            if(count($horariosExtra) > 0){
                $minimoEntrada =  ' -45 minute';                 
                for ($i=0; $i < count($horariosExtra); $i++) { 
                    $horaEntrada_tolerancia =  strtotime($horariosExtra[$i]->HoraEntrada.' +'.($horariosExtra[$i]->Tolerancia+1).' minute');
                    $horaEntradaRetardo = strtotime(date('H:i:s',$horaEntrada_tolerancia).' + '.$horariosExtra[$i]->Retardo .' minute');
                    $horaSalida = $horariosExtra[$i]->HoraSalida;
                    //verifico si el registro esta entra la hora de entrada minimo y que sea menor a la salida es un rango ejemolo 80:00 am a 3:00 pm
                    if($horaRegistroHoy >= strtotime($horariosExtra[$i]->HoraEntrada.$minimoEntrada) && $horaRegistroHoy <= strtotime($horariosExtra[$i]->HoraSalida) ){
                        $horaAsignado =  $fechaRegistroHoy.' '.date('H:i:s',strtotime($horariosExtra[$i]->HoraEntrada.' +'.$horariosExtra[$i]->Tolerancia.' minute'));
                        $tipoDeAsistencia =  "Entrada";
                        if($horaRegistroHoy <= $horaEntrada_tolerancia){
                            $estatusAsistencia =  'Asistencia';
                        break;
    
                        //valido que la hora de registro sea mayor a la hora_entrada tolerancia y menor a la hora max de retardo para considerar la asistencia tipo retardo
                        }else if($horaRegistroHoy >= $horaEntrada_tolerancia && $horaRegistroHoy <= $horaEntradaRetardo){
                            $estatusAsistencia = "Retardo";
                        break;
    
                        //valido que la hora de registro sea mayor a la hora max retardo y menor a la hora salida para considerar la asistencia tipo Falta
                        }else if ($horaRegistroHoy > $horaEntradaRetardo && $horaRegistroHoy < strtotime($horariosExtra[$i]->HoraSalida)){
                            $estatusAsistencia = "Falta";
                        break;
                        }
    
                    } else  {

                            if($horaRegistroHoy > strtotime($horaSalida) ){
                            
                                $horaAsignado =  $fechaRegistroHoy.' '.$horariosExtra[$i]->HoraSalida;  
                                $tipoDeAsistencia =  "Salida";
                                $estatusAsistencia = "es salida";

                                if($i < count($horariosExtra)-1){
                                    if($horaRegistroHoy < strtotime($horariosExtra[$i+1]->HoraEntrada.$minimoEntrada)){
                                        
                                        $horaAsignado =  $fechaRegistroHoy.' '.$horariosExtra[$i]->HoraSalida;
                                        $tipoDeAsistencia =  "Salida";
                                        $estatusAsistencia = "es salida";
                                    break;
                                    }else if($horaRegistroHoy >= strtotime($horariosExtra[$i+1]->HoraEntrada.$minimoEntrada) ){
                                        continue;
                                    }
                                }

                            //Esta parte es confuso, pero se almacena el horario asingado del dia siguiente
                            } else {
                                $horaAsignado = $fechaRegistroHoy.' '.$horariosExtra[$i]->HoraSalida;
                            }
                    }// fin del for de horarios
    
                }
                
                if(!isset($tipoDeAsistencia) && !isset($estatusAsistencia)){
                    $tipoDeAsistencia =  "Salida";
                    $estatusAsistencia = "es salida";
                }
                
                
              /*   return response()->json(
                    [   'tipoHorario' => $tipoHorario,
                        'tipoAsistencia' => $tipoDeAsistencia,
                        'estatusAsistencia' =>$estatusAsistencia,
                        'HoraRegistro' => date('H:i:s',$horaRegistroHoy),
                        'Hora_entrada_max_retardo' => date('H:i:s',$horaEntradaRetardo),
                        'Hora_entrada_toerancia'=> date('H:i:s',$horaEntrada_tolerancia),
                        'Hora_salida'=>$horaSalida
                    ]
                );
              */
                

                return response()->json(
                    ['idEmpleadoLaboral' => $asistenciaEmpleadoLaboral->id, 
                    'Fecha' => $fechaRegistroHoy.' '.date('H:i:s',$horaRegistroHoy),
                    'idUbicacion' => $idUbicacion,
                    'FechaAsistencia' => $horaAsignado,
                    'Status' => $estatusAsistencia,
                    'tipo'  => $tipoDeAsistencia                       
                    ]
                );
            
            //cuando no encuentra horarios en dias no laborales como los dias sabado y domingo y el horario solo es entre semana
            }else{
                return response()->json(
                   ['tipoHorario' => $tipoHorario,
                    'estatusAsistencia' =>true,
                    'mensaje'=> 'Dia no laboral'
                    ]
                );
            }
            




        }


    }


    /**
     * Obtener los datos de la ubicacion, hora para registrar asistencia
     */

    public function getUbicacionEmpresaNuevo(Request $request){

        $rfc = strtoupper($request->rfc);
        $idCliente =  $request->idCliente;
        Funciones::selecionarBase($idCliente);

        $fechaHoy = new \DateTime();
        $fechaRegistroHoy = $fechaHoy->format('Y-m-d');
        $horaRegistroHoy =  $fechaHoy->format('H:i:s');
        
        
        //$fechaRegistroHoy = '2020-11-14';

        $numeroDelDia = date('w', strtotime($fechaRegistroHoy)) + 1;
       // $horaRegistroHoy = strtotime('22:30:00');


        $horaRegistroHoy = strtotime($horaRegistroHoy);
        $persona =   DB::table('Persona')
                        ->join('Cliente','Persona.Cliente','=','Cliente.id')
                        ->join('DatosFiscales','DatosFiscales.id','=','Persona.DatosFiscales')
                        ->select('Persona.id',DB::raw('CONCAT(Persona.Nombre," ", Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) as nombreEmpleado'),'Persona.Tel_efonoCelular','Cliente.Descripci_on')
                        ->where('DatosFiscales.RFC','=',$rfc)
                        ->where('Persona.Cliente','=',$idCliente)
                        ->where('Persona.Estatus','=','1')
                        ->first();       
        
        $puestoEmpleado = DB::table('PuestoEmpleado')->select('PuestoEmpleado.id')->where('PuestoEmpleado.Empleado','=',$persona->id)->where('PuestoEmpleado.Estatus','=',"1")->first();
        $asistenciaEmpleadoLaboral = DB::table('AsistenciaEmpleadoLaboral')
            ->select('AsistenciaEmpleadoLaboral.id','AsistenciaEmpleadoLaboral.jornada_id')              
            ->where('AsistenciaEmpleadoLaboral.idPuestoEmpleado','=',$puestoEmpleado->id)
            ->first();

        $ubicacionEmpresa =  DB::table("UbicacionEmpresa")
        ->select('UbicacionEmpresa.idUbicacionEmpresa','UbicacionEmpresa.latitude','UbicacionEmpresa.longitude','UbicacionEmpresa.radio','UbicacionEmpresa.descripcion as NombreComercial')
        ->join('AsistenciaEmpleadoLaboralEmpresa','UbicacionEmpresa.idUbicacionEmpresa','=','AsistenciaEmpleadoLaboralEmpresa.ubicacionEmpresa_id')
        ->where('AsistenciaEmpleadoLaboralEmpresa.puestoEmpleado_id','=',$puestoEmpleado->id)
        ->get(); 

        if($asistenciaEmpleadoLaboral){
               //Buscamos el empleado tiene jornada horario extra
               $tipoJornadaHorarioExtra  = DB::select('select GROUP_CONCAT(DISTINCT CONCAT(aehe.HoraEntrada)) as HoraEntrada, GROUP_CONCAT(DISTINCT CONCAT(aehe.HoraSalida)) as HoraSalida, aheed.jornada_id
               FROM AsistenciaEmpleadoHorarioExtra aehe INNER JOIN AsistenciaEmpleadoExtra_has_Detalle aeehd ON(aehe.id = aeehd.HorarioExtra_id) 
               INNER JOIN AsistenciaHorarioEmpleadoExtraDetalle aheed ON (aheed.id = aeehd.HorarioExtraDetalle_id) WHERE "'.$fechaRegistroHoy.'" BETWEEN aheed.FechaInicio AND aheed.FechaFinal AND aehe.idEmpleadoLaboral = '.$asistenciaEmpleadoLaboral->id.' LIMIT 1');   
              
               //OBTENER LOS HORARIOS EXTRAS EN CASO DE QUE HALLA
               if($tipoJornadaHorarioExtra[0]->jornada_id > 0){
                        $tipoHorario =  1;
                         
                        //si tiene alguna el tipo de dia para diferenciar si es 24x24(etc) o personalizado(semanal) SELECT * FROM AsistenciaJornadaHorario WHERE asistenciaJornada_id = 4
                        $tipoDia = DB::table('AsistenciaJornadaHorario')->select('asistenciaDia_id')->where('asistenciaJornada_id','=',$tipoJornadaHorarioExtra[0]->jornada_id)->first();
                        
                        if($tipoDia->asistenciaDia_id == 8 || $tipoDia->asistenciaDia_id ==='8'){
                            $tipoPerfil =  8;
                            //para horario 24x24
                        } else {
                            //OBTENGO LOS HORARIOS SEMANALES
                            $tipoPerfil =  2;
                            $horarios = DB::select('select aehe.HoraEntrada, aehe.HoraSalida, aehe.Tolerancia, aehe.Retardo
                                                FROM AsistenciaEmpleadoHorarioExtra aehe 
                                                INNER JOIN AsistenciaEmpleadoExtra_has_Detalle aeehd ON(aehe.id = aeehd.HorarioExtra_id) 
                                                INNER JOIN AsistenciaHorarioEmpleadoExtraDetalle aheed ON (aheed.id = aeehd.HorarioExtraDetalle_id) 
                                                WHERE "'.$fechaRegistroHoy.'"  BETWEEN aheed.FechaInicio AND aheed.FechaFinal AND aehe.idEmpleadoLaboral = '.$asistenciaEmpleadoLaboral->id.' and aehe.Dia_id = '.$numeroDelDia);
                        }
                //SI NO HAY HORARIOS EXTRAS OBTENEMOS LOS HORARIOS NORMALES
                } else {
                        $tipoHorario =  2;
                        //para horarios normales que no son extras
                        $jornada = DB::table('AsistenciaJornadaHorario')->select('AsistenciaJornadaHorario.asistenciaDia_id')
                                    ->where('asistenciaJornada_id',$asistenciaEmpleadoLaboral->jornada_id)
                                    ->first();
                        if($jornada){

                                if($jornada->asistenciaDia_id == 8 || $jornada->asistenciaDia_id === "8"){
                                    //perfil de 24x24
                                    $tipoPerfil =  8;
                                        
                                } else {
                                    $tipoPerfil =  2;
                                    $horarios =  DB::table('AsistenciaEmpleadoHorario')
                                        ->select('AsistenciaEmpleadoHorario.HoraEntrada','AsistenciaEmpleadoHorario.HoraSalida','AsistenciaEmpleadoHorario.Tolerancia','AsistenciaEmpleadoHorario.Retardo')
                                        ->where('AsistenciaEmpleadoHorario.idEmpleadoLaboral',$asistenciaEmpleadoLaboral->id)
                                        ->where('AsistenciaEmpleadoHorario.dia_id','=', (date('w', strtotime($fechaRegistroHoy)) + 1) )
                                        ->get();
                                }
                        }
                }//fin if (si existe un horario extra para calcular horiario)
                 //SELECT DATE_FORMAT(Fecha,'%H:%i:%s') horaRegistro from AsistenciaHorario WHERE idEmpleadoLaboral = 27933 AND DATE_FORMAT(Fecha,'%Y-%m-%d') = '2020-11-06'



                $minimoEntrada =  ' -45 minute'; 
                $existeRegistroHorario = false;

                if(count($horarios) > 0){

          
                    if($horaRegistroHoy < strtotime($horarios[0]->HoraEntrada.$minimoEntrada)){
                        $horariosDelDiaAnterior = $this->ObtenerHorarioDelDiaAnterior($fechaRegistroHoy,$asistenciaEmpleadoLaboral->id,$asistenciaEmpleadoLaboral->jornada_id);

                        $horaMinimo = strtotime($horariosDelDiaAnterior[(count($horariosDelDiaAnterior)-1)]->HoraSalida);

                        $horaMaximo = strtotime($horarios[0]->HoraEntrada.$minimoEntrada); 

                        $fechaAnterior = date('Y-m-d',strtotime($fechaRegistroHoy.' -1 day'));
                        
                        $horasRegistro =  DB::table('AsistenciaHorario')->select(DB::raw('DATE_FORMAT(Fecha,"%H:%i:%s") horaRegistro'))->where('idEmpleadoLaboral','=',$asistenciaEmpleadoLaboral->id)
                        ->where(DB::raw('DATE_FORMAT(Fecha,"%Y-%m-%d")'),'=',$fechaAnterior)->where('tipo','=','Salida')->get();

                        if(count($horasRegistro) > 0){
                            
                             if(strtotime($horasRegistro[count($horasRegistro)-1]->horaRegistro) > strtotime($horariosDelDiaAnterior[count($horariosDelDiaAnterior)-1]->HoraSalida) ){
                                 
                                 //existe registro
                                 $existeRegistroHorario = true;


                             }else{
                                 //signigica que puede registrar salida
                                 $existeRegistroHorario = false;
                             }

                        }else{
                            $existeRegistroHorario = false; 
                        }

                        $tipoDeAsistencia =  "Salida";

                    //Cuando entran a la app y no es de madrugada entra aqui la condicion
                    }else{
                        //recorrer los horarios asignados
                        for ($i=0; $i < count($horarios); $i++) { 
                            $horaEntrada_tolerancia =  strtotime($horarios[$i]->HoraEntrada.' +'.($horarios[$i]->Tolerancia+1).' minute');                        
                            $horaEntradaRetardo = strtotime(date('H:i:s',$horaEntrada_tolerancia).' + '.$horarios[$i]->Retardo .' minute');
                            $horaSalida = $horarios[$i]->HoraSalida;
                            $horaMinimo = strtotime($horarios[$i]->HoraEntrada.$minimoEntrada);

                            //verifico si el registro esta entra la hora de entrada minimo y que sea menor a la salida es un rango ejemolo 80:00 am a 3:00 pm
                            if($horaRegistroHoy >= strtotime($horarios[$i]->HoraEntrada.$minimoEntrada) && $horaRegistroHoy < strtotime($horarios[$i]->HoraSalida) ){
                                $tipoDeAsistencia =  "Entrada";
                                $horaMaximo = strtotime($horarios[$i]->HoraSalida);
                            break;

                            } else {

                                $horaMinimo =  strtotime($horarios[$i]->HoraSalida);
                            //    return $horarios;
                                                        
                                //con esta condicion verifico si hay un segundo horario, si es asi comparo si el horario de registro es menor a la hora entrada minimo
                                if($i < count($horarios)-1){
                                    // echo $i .'   '.count($horariosExtra);
                                    if($horaRegistroHoy < strtotime($horarios[$i+1]->HoraEntrada.$minimoEntrada)){
                                        $tipoDeAsistencia =  "Salida";
                                        $horaMaximo = strtotime($horarios[$i+1]->HoraEntrada.$minimoEntrada);
                                    break;
                                    }else if($horaRegistroHoy >= strtotime($horarios[$i+1]->HoraEntrada.$minimoEntrada) ){
                                        continue;
                                    }
                                } else {
                                    $tipoDeAsistencia =  "Salida";
                                    //$horaMaximo = $this->obtenerHorarioDelDiaSiguiente($fechaRegistroHoy,$asistenciaEmpleadoLaboral->id,$asistenciaEmpleadoLaboral->jornada_id);
                                    $horaMaximo = strtotime('24:59:59');
                                   // return  date('H:i:s',$horaMaximo);
                                break;
                                }
                            }
                        }//fin for
    
                        $horasRegistro =  DB::table('AsistenciaHorario')->select(DB::raw('DATE_FORMAT(Fecha,"%H:%i:%s") horaRegistro'))->where('idEmpleadoLaboral','=',$asistenciaEmpleadoLaboral->id)
                        ->where(DB::raw('DATE_FORMAT(Fecha,"%Y-%m-%d")'),'=',$fechaRegistroHoy)->get();
    
                        for ($i=0; $i < count($horasRegistro); $i++) {
                            if(strtotime($horasRegistro[$i]->horaRegistro) >= $horaMinimo  && strtotime($horasRegistro[$i]->horaRegistro) <= $horaMaximo ){
                                 $existeRegistroHorario = true;
                            break;
                            }
                        }
                        /* 
                        return response()->json([
                            'horaRegistro'=> date('H:i:s',$horaRegistroHoy),
                            'tipoHorario'=> $tipoHorario,
                            'horarios'   => $horarios,
                            'tipoAsistencia' => $tipoDeAsistencia,
                            'tipoPerfil' => $tipoPerfil,
                            'registros' => $horasRegistro,
                            'horaMinimo' => date('H:i:s',$horaMinimo),
                            'horaMaximo' => date('H:i:s',$horaMaximo),
                            'existeRegistro' =>  $existeRegistroHorario
                            
                        ]); */
                    }


                    if($existeRegistroHorario){
                        $mensajeBoton =  'Asistencia Registrada';
                    }else{
                        if($tipoDeAsistencia === 'Entrada'){
                            $mensajeBoton = 'Registrar Entrada';
                        }else{
                            $mensajeBoton = 'Registrar Salida';
                        }
                    }

                    return response()->json([
                        'Estatus' => true,
                        'registroHoy' => date('H:i:s',$horaRegistroHoy ),
                        'tipoHorario'=> $tipoHorario,
                        'tipoPerfil' => $tipoPerfil,
                        'existeRegistro' =>  $existeRegistroHorario,
                        'mensaje' => $mensajeBoton,
                        "ubicacionEmpresas"=> $ubicacionEmpresa
                    ]); 

                }else{
                    return response()->json([
                    'Estatus' => true,
                    'registroHoy' => date('H:i:s',$horaRegistroHoy ),
                    'tipoHorario'=> 0,
                    'tipoPerfil' => 0,
                    'existeRegistro' =>  true,
                    'mensaje' => 'Dia no laboral',
                    "ubicacionEmpresas"=> $ubicacionEmpresa
                    ]); 
                }              

        }
    }

    function obtenerUltimoHorarioDelSiguienteDia($fechaActual){

        return "389384523";

    }
    

    function obtenerHorarioDelDiaSiguiente($fechaActual,$idEmpleadoLaboral,$jornada_id){
        $fechaSiguiente =  date('y-m-d',strtotime($fechaActual.' +1 day'));
        $numeroDelDia = date('w', strtotime($fechaSiguiente)) + 1;

        $tipoJornadaHorarioExtra  = DB::select('select GROUP_CONCAT(DISTINCT CONCAT(aehe.HoraEntrada)) as HoraEntrada, GROUP_CONCAT(DISTINCT CONCAT(aehe.HoraSalida)) as HoraSalida, aheed.jornada_id
               FROM AsistenciaEmpleadoHorarioExtra aehe INNER JOIN AsistenciaEmpleadoExtra_has_Detalle aeehd ON(aehe.id = aeehd.HorarioExtra_id) 
               INNER JOIN AsistenciaHorarioEmpleadoExtraDetalle aheed ON (aheed.id = aeehd.HorarioExtraDetalle_id) WHERE "'.$fechaSiguiente.'" BETWEEN aheed.FechaInicio AND aheed.FechaFinal AND aehe.idEmpleadoLaboral = '.$idEmpleadoLaboral.' LIMIT 1');   
              
        //OBTENER LOS HORARIOS EXTRAS EN CASO DE QUE HALLA
        if($tipoJornadaHorarioExtra[0]->jornada_id > 0){
                $tipoHorario =  1;
                    
                //si tiene alguna el tipo de dia para diferenciar si es 24x24(etc) o personalizado(semanal) SELECT * FROM AsistenciaJornadaHorario WHERE asistenciaJornada_id = 4
                $tipoDia = DB::table('AsistenciaJornadaHorario')->select('asistenciaDia_id')->where('asistenciaJornada_id','=',$tipoJornadaHorarioExtra[0]->jornada_id)->first();
                
                if($tipoDia->asistenciaDia_id == 8 || $tipoDia->asistenciaDia_id ==='8'){
                    $tipoPerfil =  8;
                    //para horario 24x24
                } else {
                    //OBTENGO LOS HORARIOS SEMANALES
                    $tipoPerfil =  2;
                    $horarios = DB::select('select aehe.HoraEntrada, aehe.HoraSalida, aehe.Tolerancia, aehe.Retardo
                                        FROM AsistenciaEmpleadoHorarioExtra aehe 
                                        INNER JOIN AsistenciaEmpleadoExtra_has_Detalle aeehd ON(aehe.id = aeehd.HorarioExtra_id) 
                                        INNER JOIN AsistenciaHorarioEmpleadoExtraDetalle aheed ON (aheed.id = aeehd.HorarioExtraDetalle_id) 
                                        WHERE "'.$fechaSiguiente.'"  BETWEEN aheed.FechaInicio AND aheed.FechaFinal AND aehe.idEmpleadoLaboral = '.$idEmpleadoLaboral.' and aehe.Dia_id = '.$numeroDelDia);
                }
        //SI NO HAY HORARIOS EXTRAS OBTENEMOS LOS HORARIOS NORMALES
        } else {
                $tipoHorario =  2;
                //para horarios normales que no son extras
                $jornada = DB::table('AsistenciaJornadaHorario')->select('AsistenciaJornadaHorario.asistenciaDia_id')
                            ->where('asistenciaJornada_id',$jornada_id)
                            ->first();
                if($jornada){

                        if($jornada->asistenciaDia_id == 8 || $jornada->asistenciaDia_id === "8"){
                            //perfil de 24x24
                            $tipoPerfil =  8;
                                
                        } else {
                            $tipoPerfil =  2;
                                $horarios =  DB::table('AsistenciaEmpleadoHorario')
                                    ->select('AsistenciaEmpleadoHorario.HoraEntrada','AsistenciaEmpleadoHorario.HoraSalida','AsistenciaEmpleadoHorario.Tolerancia','AsistenciaEmpleadoHorario.Retardo')
                                    ->where('AsistenciaEmpleadoHorario.idEmpleadoLaboral',$idEmpleadoLaboral)
                                    ->where('AsistenciaEmpleadoHorario.dia_id','=', (date('w', strtotime($fechaSiguiente)) + 1) )
                                    // ->where('AsistenciaEmpleadoHorario.dia_id','=', 5 )
                                    ->get();
                        }
                }
        }

        if(count($horarios) > 0){
            $minimoEntrada =  ' -45 minute'; 
            //recorrer los horarios asignados
            $primero = false;
            for ($i=0; $i < count($horarios); $i++) { 
                
                $existeHoraRegistro = false; 
                $horaEntrada_tolerancia =  strtotime($horarios[$i]->HoraEntrada.' +'.($horarios[$i]->Tolerancia+1).' minute');                        
                $horaEntradaRetardo = strtotime(date('H:i:s',$horaEntrada_tolerancia).' + '.$horarios[$i]->Retardo .' minute');
                $horaSalida = $horarios[$i]->HoraSalida;
                $horaEntraMinimo =  strtotime($horarios[$i]->HoraEntrada.$minimoEntrada); 
                //verifico si el registro esta entra la hora de entrada minimo y que sea menor a la salida es un rango ejemolo 80:00 am a 3:00 pm
            break;
            }
        }
      
        if(isset($horaEntraMinimo)){
            return $horaEntraMinimo;
        }else{
            return strtotime('09:31:00');
        }
    }



    function ObtenerHorarioDelDiaAnterior($fechaActual,$idEmpleadoLaboral,$jornada_id){
        $fechaSiguiente =  date('y-m-d',strtotime($fechaActual.' -1 day'));
        $numeroDelDia = date('w', strtotime($fechaSiguiente)) + 1;

        $tipoJornadaHorarioExtra  = DB::select('select GROUP_CONCAT(DISTINCT CONCAT(aehe.HoraEntrada)) as HoraEntrada, GROUP_CONCAT(DISTINCT CONCAT(aehe.HoraSalida)) as HoraSalida, aheed.jornada_id
               FROM AsistenciaEmpleadoHorarioExtra aehe INNER JOIN AsistenciaEmpleadoExtra_has_Detalle aeehd ON(aehe.id = aeehd.HorarioExtra_id) 
               INNER JOIN AsistenciaHorarioEmpleadoExtraDetalle aheed ON (aheed.id = aeehd.HorarioExtraDetalle_id) WHERE "'.$fechaSiguiente.'" BETWEEN aheed.FechaInicio AND aheed.FechaFinal AND aehe.idEmpleadoLaboral = '.$idEmpleadoLaboral.' LIMIT 1');   
              
        //OBTENER LOS HORARIOS EXTRAS EN CASO DE QUE HALLA
        if($tipoJornadaHorarioExtra[0]->jornada_id > 0){
                $tipoHorario =  1;
                    
                //si tiene alguna el tipo de dia para diferenciar si es 24x24(etc) o personalizado(semanal) SELECT * FROM AsistenciaJornadaHorario WHERE asistenciaJornada_id = 4
                $tipoDia = DB::table('AsistenciaJornadaHorario')->select('asistenciaDia_id')->where('asistenciaJornada_id','=',$tipoJornadaHorarioExtra[0]->jornada_id)->first();
                
                if($tipoDia->asistenciaDia_id == 8 || $tipoDia->asistenciaDia_id ==='8'){
                    $tipoPerfil =  8;
                    //para horario 24x24
                } else {
                    //OBTENGO LOS HORARIOS SEMANALES
                    $tipoPerfil =  2;
                    $horarios = DB::select('select aehe.HoraEntrada, aehe.HoraSalida, aehe.Tolerancia, aehe.Retardo
                                        FROM AsistenciaEmpleadoHorarioExtra aehe 
                                        INNER JOIN AsistenciaEmpleadoExtra_has_Detalle aeehd ON(aehe.id = aeehd.HorarioExtra_id) 
                                        INNER JOIN AsistenciaHorarioEmpleadoExtraDetalle aheed ON (aheed.id = aeehd.HorarioExtraDetalle_id) 
                                        WHERE "'.$fechaSiguiente.'"  BETWEEN aheed.FechaInicio AND aheed.FechaFinal AND aehe.idEmpleadoLaboral = '.$idEmpleadoLaboral.' and aehe.Dia_id = '.$numeroDelDia);
                }
        //SI NO HAY HORARIOS EXTRAS OBTENEMOS LOS HORARIOS NORMALES
        } else {
                $tipoHorario =  2;
                //para horarios normales que no son extras
                $jornada = DB::table('AsistenciaJornadaHorario')->select('AsistenciaJornadaHorario.asistenciaDia_id')
                            ->where('asistenciaJornada_id',$jornada_id)
                            ->first();
                if($jornada){

                        if($jornada->asistenciaDia_id == 8 || $jornada->asistenciaDia_id === "8"){
                            //perfil de 24x24
                            $tipoPerfil =  8;
                                
                        } else {
                            $tipoPerfil =  2;
                                $horarios =  DB::table('AsistenciaEmpleadoHorario')
                                    ->select('AsistenciaEmpleadoHorario.HoraEntrada','AsistenciaEmpleadoHorario.HoraSalida','AsistenciaEmpleadoHorario.Tolerancia','AsistenciaEmpleadoHorario.Retardo')
                                    ->where('AsistenciaEmpleadoHorario.idEmpleadoLaboral',$idEmpleadoLaboral)
                                    ->where('AsistenciaEmpleadoHorario.dia_id','=', (date('w', strtotime($fechaSiguiente)) + 1) )
                                    // ->where('AsistenciaEmpleadoHorario.dia_id','=', 5 )
                                    ->get();
                        }
                }
        }

        return $horarios;
    }


    //pruebas 
    public function get_ubicacionMapa3(Request $request){
        $rfc = strtoupper($request->rfc);
        $idCliente =  $request->idCliente;
        Funciones::selecionarBase($idCliente);

        $fechaHoy = new \DateTime();
        $fechaRegistroHoy = $fechaHoy->format('Y-m-d');
        $horaRegistroHoy =  $fechaHoy->format('H:i:s');
        
        $persona =   DB::table('Persona')
                        ->join('Cliente','Persona.Cliente','=','Cliente.id')
                        ->join('DatosFiscales','DatosFiscales.id','=','Persona.DatosFiscales')
                        ->select('Persona.id',
                                DB::raw('CONCAT(Persona.Nombre," ", Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) as nombreEmpleado'),
                                'Persona.Tel_efonoCelular',
                                'Cliente.Descripci_on')
                        ->where('DatosFiscales.RFC','=',$rfc)
                        ->where('Persona.Cliente','=',$idCliente)
                        ->where('Persona.Estatus','=','1')
                        ->first();       
        
        $puestoEmpleado = DB::table('PuestoEmpleado')->select('PuestoEmpleado.id')->where('PuestoEmpleado.Empleado','=',$persona->id)->where('PuestoEmpleado.Estatus','=',"1")->first();
        $asistenciaEmpleadoLaboral = DB::table('AsistenciaEmpleadoLaboral')
            ->select('AsistenciaEmpleadoLaboral.id','AsistenciaEmpleadoLaboral.jornada_id')              
            ->where('AsistenciaEmpleadoLaboral.idPuestoEmpleado','=',$puestoEmpleado->id)
            ->first();

        $ubicacionEmpresa =  DB::table("UbicacionEmpresa")
        ->select('UbicacionEmpresa.idUbicacionEmpresa','UbicacionEmpresa.latitude','UbicacionEmpresa.longitude','UbicacionEmpresa.radio','UbicacionEmpresa.descripcion as NombreComercial')
        ->join('AsistenciaEmpleadoLaboralEmpresa','UbicacionEmpresa.idUbicacionEmpresa','=','AsistenciaEmpleadoLaboralEmpresa.ubicacionEmpresa_id')
        ->where('AsistenciaEmpleadoLaboralEmpresa.puestoEmpleado_id','=',$puestoEmpleado->id)
        ->get();


        // si es 24x24 o personalizado
        $tipoPerfil =  DB::table('AsistenciaJornadaHorario')->select('AsistenciaJornadaHorario.*')->join('AsistenciaEmpleadoLaboral','AsistenciaJornadaHorario.asistenciaJornada_id','=','AsistenciaEmpleadoLaboral.jornada_id')
        ->where('AsistenciaEmpleadoLaboral.id','=',$asistenciaEmpleadoLaboral->id)->first();

        if($tipoPerfil->asistenciaDia_id == 8){

        }else{
                //modificar porque estoy usndo una fecha en especifico
                $numeroDelDia = date('w', strtotime($fechaRegistroHoy)) + 1;
                $horarioAsignado =  DB::table('AsistenciaEmpleadoHorario')->select('AsistenciaEmpleadoHorario.*')->where('idEmpleadoLaboral','=',$asistenciaEmpleadoLaboral->id)->where('dia_id','=',$numeroDelDia)->get();
        }

        $horaRegistro =  strtotime($horaRegistroHoy);
        if($horarioAsignado){

                //buscamos si hay entradas
                foreach ($horarioAsignado as $horario)  {
                        $horaEntrada =  strtotime($horario->HoraEntrada.'+ '.$horario->Tolerancia.' minute');
                        //sera el rango para obtener las horas de registros
                        $horaMinimoEntrada = strtotime($horario->HoraEntrada.' -48 minute');
                        $horaEntradaRetardo = strtotime($horario->HoraEntrada.' +'.$horario->Retardo.' minute');

                        if($horaRegistro >= $horaMinimoEntrada && $horaRegistro <= $horaEntradaRetardo){
                            $tipoDeAsistencia =  "Entrada";
                            $horaDeRegistro = date('H:i:s',$horaRegistro);
                            $horaAsignado =  $horario->HoraEntrada;
                            break;
                            
                        }else if($horaRegistro >= $horaEntradaRetardo && $horaRegistro < strtotime($horario->HoraSalida)){
                            $tipoDeAsistencia =  "Entrada";
                            $horaDeRegistro = date('H:i:s',$horaRegistro);
                            $horaAsignado =  $horario->HoraEntrada;
                
                            break;
                        } 
                }//for 

                //si no encontro horas de entrada....
                if( !isset($tipoDeAsistencia) ){
                        foreach ($horarioAsignado as $horario)  {
                                $horaEntrada =  strtotime($horario->HoraEntrada.'+ 16 minute');
                                //sera el rango para obtener las horas de registros
                                $horaMinimoEntrada = strtotime($horario->HoraEntrada.' -50 minute');
                                $horaEntradaRetardo = strtotime($horario->HoraEntrada.' + 60 minute');

                                if($horaRegistro >= strtotime($horario->HoraSalida) ){
                                    $tipoDeAsistencia =  "Salida";
                                    $horaDeRegistro = date('H:i:s',$horaRegistro);
                                    $horaAsignado =  $horario->HoraSalida;        
                                    break;
                                }else if($horaRegistro <= $horaMinimoEntrada){
                                    $tipoDeAsistencia =  "Salida";
                                    $horaDeRegistro = date('H:i:s',$horaRegistro);
                                    $horaAsignado =  $horario->HoraSalida;
                
                                    break;
                                }
                        }//for 
                }
                if($ubicacionEmpresa){
                        return response()->json(
                            [
                                "Status"         => "true",
                                "Estatus"        => true,
                                'tipoAsistencia' => $tipoDeAsistencia,
                                "ubicacionEmpresas"=> $ubicacionEmpresa
                            ]
                        ); 
                }else{
                    return response()->json([
                        "Status"   => "false",
                        "Mensaje" => "No tiene asignado una ubicación para registrar asistencia"
                    ]);
                }
        }else{
            if($ubicacionEmpresa){
                    return response()->json(
                        [
                            "Status"         => "true",
                            "Estatus"        => true,
                            'tipoAsistencia' => 'Asistencia',
                            "ubicacionEmpresas"=> $ubicacionEmpresa
                        ]
                    ); 
            }else{
                return response()->json([
                    "Status"   => "false",
                    "Mensaje" => "No tiene asignado una ubicación para registrar asistencia"
                ]);
            }
        }

        
    }
/* 
    $fecha1 = new DateTime('24:55:06');//fecha inicial
    $fecha2 = new DateTime('9:15:00');//fecha de cierre

    $intervalo = $fecha2->diff($fecha1);

    echo $intervalo->format('%H:%I:%S') */


        /**
     * Obtener los datos de la ubicacion, hora para registrar asistencia
     */
    public function getUbicacionEmpresaNuevov2(Request $request){

        $rfc = strtoupper($request->rfc);
        $idCliente =  $request->idCliente;
        Funciones::selecionarBase($idCliente);

        $fechaHoy = new \DateTime();
        $fechaRegistroHoy = $fechaHoy->format('Y-m-d');
        $horaRegistroHoy =  $fechaHoy->format('H:i:s');
        
        
        $fechaRegistroHoy = '2020-11-17';

        $numeroDelDia = date('w', strtotime($fechaRegistroHoy)) + 1;
        $horaRegistroHoy = strtotime('18:30:00');


       // $horaRegistroHoy = strtotime($horaRegistroHoy);
        $persona =   DB::table('Persona')
                        ->join('Cliente','Persona.Cliente','=','Cliente.id')
                        ->join('DatosFiscales','DatosFiscales.id','=','Persona.DatosFiscales')
                        ->select('Persona.id',DB::raw('CONCAT(Persona.Nombre," ", Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) as nombreEmpleado'),'Persona.Tel_efonoCelular','Cliente.Descripci_on')
                        ->where('DatosFiscales.RFC','=',$rfc)
                        ->where('Persona.Cliente','=',$idCliente)
                        ->where('Persona.Estatus','=','1')
                        ->first();       
        
        $puestoEmpleado = DB::table('PuestoEmpleado')->select('PuestoEmpleado.id')->where('PuestoEmpleado.Empleado','=',$persona->id)->where('PuestoEmpleado.Estatus','=',"1")->first();
        $asistenciaEmpleadoLaboral = DB::table('AsistenciaEmpleadoLaboral')
            ->select('AsistenciaEmpleadoLaboral.id','AsistenciaEmpleadoLaboral.jornada_id')              
            ->where('AsistenciaEmpleadoLaboral.idPuestoEmpleado','=',$puestoEmpleado->id)
            ->first();

        $ubicacionEmpresa =  DB::table("UbicacionEmpresa")
        ->select('UbicacionEmpresa.idUbicacionEmpresa','UbicacionEmpresa.latitude','UbicacionEmpresa.longitude','UbicacionEmpresa.radio','UbicacionEmpresa.descripcion as NombreComercial')
        ->join('AsistenciaEmpleadoLaboralEmpresa','UbicacionEmpresa.idUbicacionEmpresa','=','AsistenciaEmpleadoLaboralEmpresa.ubicacionEmpresa_id')
        ->where('AsistenciaEmpleadoLaboralEmpresa.puestoEmpleado_id','=',$puestoEmpleado->id)
        ->get(); 

        if($asistenciaEmpleadoLaboral){
               //Buscamos el empleado tiene jornada horario extra
               $tipoJornadaHorarioExtra  = DB::select('select GROUP_CONCAT(DISTINCT CONCAT(aehe.HoraEntrada)) as HoraEntrada, GROUP_CONCAT(DISTINCT CONCAT(aehe.HoraSalida)) as HoraSalida, aheed.jornada_id
               FROM AsistenciaEmpleadoHorarioExtra aehe INNER JOIN AsistenciaEmpleadoExtra_has_Detalle aeehd ON(aehe.id = aeehd.HorarioExtra_id) 
               INNER JOIN AsistenciaHorarioEmpleadoExtraDetalle aheed ON (aheed.id = aeehd.HorarioExtraDetalle_id) WHERE "'.$fechaRegistroHoy.'" BETWEEN aheed.FechaInicio AND aheed.FechaFinal AND aehe.idEmpleadoLaboral = '.$asistenciaEmpleadoLaboral->id.' LIMIT 1');   
              
               //OBTENER LOS HORARIOS EXTRAS EN CASO DE QUE HALLA
               if($tipoJornadaHorarioExtra[0]->jornada_id > 0){
                        $tipoHorario =  1;
                         
                        //si tiene alguna el tipo de dia para diferenciar si es 24x24(etc) o personalizado(semanal) SELECT * FROM AsistenciaJornadaHorario WHERE asistenciaJornada_id = 4
                        $tipoDia = DB::table('AsistenciaJornadaHorario')->select('asistenciaDia_id')->where('asistenciaJornada_id','=',$tipoJornadaHorarioExtra[0]->jornada_id)->first();
                        
                        if($tipoDia->asistenciaDia_id == 8 || $tipoDia->asistenciaDia_id ==='8'){
                            $tipoPerfil =  8;
                            //para horario 24x24
                        } else {
                            //OBTENGO LOS HORARIOS SEMANALES
                            $tipoPerfil =  2;
                            $horarios = DB::select('select aehe.HoraEntrada, aehe.HoraSalida, aehe.Tolerancia, aehe.Retardo
                                                FROM AsistenciaEmpleadoHorarioExtra aehe 
                                                INNER JOIN AsistenciaEmpleadoExtra_has_Detalle aeehd ON(aehe.id = aeehd.HorarioExtra_id) 
                                                INNER JOIN AsistenciaHorarioEmpleadoExtraDetalle aheed ON (aheed.id = aeehd.HorarioExtraDetalle_id) 
                                                WHERE "'.$fechaRegistroHoy.'"  BETWEEN aheed.FechaInicio AND aheed.FechaFinal AND aehe.idEmpleadoLaboral = '.$asistenciaEmpleadoLaboral->id.' and aehe.Dia_id = '.$numeroDelDia);
                        }
                //SI NO HAY HORARIOS EXTRAS OBTENEMOS LOS HORARIOS NORMALES
                } else {
                        $tipoHorario =  2;
                        //para horarios normales que no son extras
                        $jornada = DB::table('AsistenciaJornadaHorario')->select('AsistenciaJornadaHorario.asistenciaDia_id')
                                    ->where('asistenciaJornada_id',$asistenciaEmpleadoLaboral->jornada_id)
                                    ->first();
                        if($jornada){

                                if($jornada->asistenciaDia_id == 8 || $jornada->asistenciaDia_id === "8"){
                                    //perfil de 24x24
                                    $tipoPerfil =  8;
                                        
                                } else {
                                    $tipoPerfil =  2;
                                    $horarios =  DB::table('AsistenciaEmpleadoHorario')
                                        ->select('AsistenciaEmpleadoHorario.HoraEntrada','AsistenciaEmpleadoHorario.HoraSalida','AsistenciaEmpleadoHorario.Tolerancia','AsistenciaEmpleadoHorario.Retardo')
                                        ->where('AsistenciaEmpleadoHorario.idEmpleadoLaboral',$asistenciaEmpleadoLaboral->id)
                                        ->where('AsistenciaEmpleadoHorario.dia_id','=', (date('w', strtotime($fechaRegistroHoy)) + 1) )
                                        ->get();
                                }
                        }
                }//fin if (si existe un horario extra para calcular horiario)
                 //SELECT DATE_FORMAT(Fecha,'%H:%i:%s') horaRegistro from AsistenciaHorario WHERE idEmpleadoLaboral = 27933 AND DATE_FORMAT(Fecha,'%Y-%m-%d') = '2020-11-06'



                //1.- Verificar si cuando el usuario entra a la app en la madrugada, tendremos consultar el horario del dia anterior porque significa que va registrar su salida, sino no se hara nada
                //si el dia en el que se consulta el horario devuelve un array vacio pasa la siguinete condicion, si no se valida. Obtiene el horario del dia actual y hay horario es dia laboral
                $minimoEntrada =  ' -45 minute'; 
                $existeRegistroHorario = false;

                if(count($horarios) > 0){

                    //verifico si el horario es de madrugada o el dia siguiente cuando quiere registrar assistencia. Si entra a la condicion significa que si hay horarios y se valida la h ora de registro
                    // que sea menor a la hora entrada del horario del mismo dia.             
                    if($horaRegistroHoy < strtotime($horarios[0]->HoraEntrada.$minimoEntrada)){

                        //obtener el horario del dia anterior. 
                        $horariosDelDiaAnterior = $this->ObtenerHorarioDelDiaAnterior($fechaRegistroHoy,$asistenciaEmpleadoLaboral->id,$asistenciaEmpleadoLaboral->jornada_id);


                        //ejemplo obtener el ultimo horario de salida
                        $horaMinimo = strtotime($horariosDelDiaAnterior[(count($horariosDelDiaAnterior)-1)]->HoraSalida);

                        //obtener el horario de entrada 
                        $horaMaximo = strtotime($horarios[0]->HoraEntrada.$minimoEntrada); 

                        //si es de madrugada se obtiene la fecha anterior 
                        $fechaAnterior = date('Y-m-d',strtotime($fechaRegistroHoy.' -1 day'));
                        
                        //obtiene el horario del dia anterior
                        $horasRegistro =  DB::table('AsistenciaHorario')->select(DB::raw('DATE_FORMAT(Fecha,"%H:%i:%s") horaRegistro'))->where('idEmpleadoLaboral','=',$asistenciaEmpleadoLaboral->id)
                        ->where(DB::raw('DATE_FORMAT(Fecha,"%Y-%m-%d")'),'=',$fechaAnterior)->where('tipo','=','Salida')->get();

                        //verificamos los registros que tiene del dia anterior. si hay registro buscamos las horas de salida
                        if(count($horasRegistro) > 0){
                            
                             if(strtotime($horasRegistro[count($horasRegistro)-1]->horaRegistro) > strtotime($horariosDelDiaAnterior[count($horariosDelDiaAnterior)-1]->HoraSalida) ){
                                 
                                 //existe registro
                                 $existeRegistroHorario = true;


                             }else{
                                 //si el registro es menor al a la ultima hora de salida significa que no hay registros de salida de la ultima
                                 //signigica que puede registrar salida
                                 $existeRegistroHorario = false;
                             }

                        }else{
                            $existeRegistroHorario = false; 
                        }

                        $tipoDeAsistencia =  "Salida";

                    //Cuando entran a la app y no es de madrugada entra aqui la condicion
                    }else{
                        //recorrer los horarios asignados
                        for ($i=0; $i < count($horarios); $i++) { 
                            $horaEntrada_tolerancia =  strtotime($horarios[$i]->HoraEntrada.' +'.($horarios[$i]->Tolerancia+1).' minute');                        
                            $horaEntradaRetardo = strtotime(date('H:i:s',$horaEntrada_tolerancia).' + '.$horarios[$i]->Retardo .' minute');
                            $horaSalida = $horarios[$i]->HoraSalida;
                            $horaMinimo = strtotime($horarios[$i]->HoraEntrada.$minimoEntrada);

                            //verifico si el registro esta entra la hora de entrada minimo y que sea menor a la salida es un rango ejemolo 80:00 am a 3:00 pm
                            if($horaRegistroHoy >= strtotime($horarios[$i]->HoraEntrada.$minimoEntrada) && $horaRegistroHoy < strtotime($horarios[$i]->HoraSalida) ){
                                $tipoDeAsistencia =  "Entrada";
                                $horaMaximo = strtotime($horarios[$i]->HoraSalida);
                            break;

                            } else {

                                $horaMinimo =  strtotime($horarios[$i]->HoraSalida);
                            //    return $horarios;
                                                        
                                //con esta condicion verifico si hay un segundo horario, si es asi comparo si el horario de registro es menor a la hora entrada minimo
                                if($i < count($horarios)-1){
                                    // echo $i .'   '.count($horariosExtra);
                                    if($horaRegistroHoy < strtotime($horarios[$i+1]->HoraEntrada.$minimoEntrada)){
                                        $tipoDeAsistencia =  "Salida";
                                        $horaMaximo = strtotime($horarios[$i+1]->HoraEntrada.$minimoEntrada);
                                    break;
                                    }else if($horaRegistroHoy >= strtotime($horarios[$i+1]->HoraEntrada.$minimoEntrada) ){
                                        continue;
                                    }
                                } else {
                                    $tipoDeAsistencia =  "Salida";
                                    //$horaMaximo = $this->obtenerHorarioDelDiaSiguiente($fechaRegistroHoy,$asistenciaEmpleadoLaboral->id,$asistenciaEmpleadoLaboral->jornada_id);
                                    $horaMaximo = strtotime('24:59:59');
                                   // return  date('H:i:s',$horaMaximo);
                                break;
                                }
                            }
                        }//fin for
    
                        $horasRegistro =  DB::table('AsistenciaHorario')->select(DB::raw('DATE_FORMAT(Fecha,"%H:%i:%s") horaRegistro'))->where('idEmpleadoLaboral','=',$asistenciaEmpleadoLaboral->id)
                        ->where(DB::raw('DATE_FORMAT(Fecha,"%Y-%m-%d")'),'=',$fechaRegistroHoy)->get();
    
                        for ($i=0; $i < count($horasRegistro); $i++) {
                            if(strtotime($horasRegistro[$i]->horaRegistro) >= $horaMinimo  && strtotime($horasRegistro[$i]->horaRegistro) <= $horaMaximo ){
                                 $existeRegistroHorario = true;
                            break;
                            }
                        }
                        /* 
                        return response()->json([
                            'horaRegistro'=> date('H:i:s',$horaRegistroHoy),
                            'tipoHorario'=> $tipoHorario,
                            'horarios'   => $horarios,
                            'tipoAsistencia' => $tipoDeAsistencia,
                            'tipoPerfil' => $tipoPerfil,
                            'registros' => $horasRegistro,
                            'horaMinimo' => date('H:i:s',$horaMinimo),
                            'horaMaximo' => date('H:i:s',$horaMaximo),
                            'existeRegistro' =>  $existeRegistroHorario
                            
                        ]); */
                    }


                    if($existeRegistroHorario){
                        $mensajeBoton =  'Asistencia Registrada';
                    }else{
                        if($tipoDeAsistencia === 'Entrada'){
                            $mensajeBoton = 'Registrar Entrada';
                        }else{
                            $mensajeBoton = 'Registrar Salida';
                        }
                    }

                    return response()->json([
                        'Estatus' => true,
                        'registroHoy' => date('H:i:s',$horaRegistroHoy ),
                        'tipoHorario'=> $tipoHorario,
                        'tipoPerfil' => $tipoPerfil,
                        'existeRegistro' =>  $existeRegistroHorario,
                        'mensaje' => $mensajeBoton,
                        "ubicacionEmpresas"=> $ubicacionEmpresa
                    ]); 

                }else{
                    return response()->json([
                    'Estatus' => true,
                    'registroHoy' => date('H:i:s',$horaRegistroHoy ),
                    'tipoHorario'=> 0,
                    'tipoPerfil' => 0,
                    'existeRegistro' =>  true,
                    'mensaje' => 'Dia no laboral',
                    "ubicacionEmpresas"=> $ubicacionEmpresa
                    ]); 
                }              

        }
    }



    public function existe_empleado3(Request $request){ 
        try {
            //code...
    
                $rfc = strtoupper($request->rfc);
                $idCliente =  $request->idCliente;
                Funciones::selecionarBase($idCliente);

                $fecha = new \DateTime();
                $fechaHoy = $fecha->format('Y-m-d');
                $fechaHoy = '2020-11-22';
            
                $persona =   DB::table('Persona')
                                ->join('Cliente','Persona.Cliente','=','Cliente.id')
                                ->join('DatosFiscales','DatosFiscales.id','=','Persona.DatosFiscales')
                                ->select('Persona.id',
                                        DB::raw('CONCAT(Persona.Nombre," ", Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) as nombreEmpleado'),
                                        'Persona.Tel_efonoCelular',
                                        'Cliente.Descripci_on')
                                ->where('DatosFiscales.RFC','=',$rfc)
                                ->where('Persona.Cliente','=',$idCliente)
                                ->where('Persona.Estatus','=','1')
                                ->first();  
                if($persona){
                
                    //buscamos si existe el puesto empleado
                    $puestoEmpleado = DB::table('PuestoEmpleado')->select('PuestoEmpleado.id')->where('PuestoEmpleado.Empleado','=',$persona->id)->where('PuestoEmpleado.Estatus','=',"1")->first();
                    if($puestoEmpleado){

                        //Buscamos si existe registro del puestoEmpleado en la tabla AsistenciaEmpleadoLoral
                        $asistenciaEmpleadoLaboral = DB::table('AsistenciaEmpleadoLaboral')
                                                        ->select('AsistenciaEmpleadoLaboral.id','AsistenciaEmpleadoLaboral.jornada_id')              
                                                        ->where('AsistenciaEmpleadoLaboral.idPuestoEmpleado','=',$puestoEmpleado->id)
                                                        ->first(); 
                        if($asistenciaEmpleadoLaboral){

                            //Buscamos el empleado tiene jornada horario extra
                            $tipoJornadaHorarioExtra  = DB::select('select GROUP_CONCAT(DISTINCT CONCAT(aehe.HoraEntrada)) as HoraEntrada, GROUP_CONCAT(DISTINCT CONCAT(aehe.HoraSalida)) as HoraSalida, aheed.jornada_id
                                                                            FROM AsistenciaEmpleadoHorarioExtra aehe 
                                                                            INNER JOIN AsistenciaEmpleadoExtra_has_Detalle aeehd ON(aehe.id = aeehd.HorarioExtra_id) 
                                                                            INNER JOIN AsistenciaHorarioEmpleadoExtraDetalle aheed ON (aheed.id = aeehd.HorarioExtraDetalle_id) 
                                                                            WHERE "'.$fechaHoy.'" BETWEEN aheed.FechaInicio AND aheed.FechaFinal AND aehe.idEmpleadoLaboral = '.$asistenciaEmpleadoLaboral->id.' LIMIT 1');   
                            
                            if($tipoJornadaHorarioExtra[0]->jornada_id > 0){
                                    //si tiene alguna el tipo de dia para diferenciar si es 24x24(etc) o personalizado(semanal) SELECT * FROM AsistenciaJornadaHorario WHERE asistenciaJornada_id = 4
                                    $tipoDia = DB::table('AsistenciaJornadaHorario')->select('asistenciaDia_id')->where('asistenciaJornada_id','=',$tipoJornadaHorarioExtra[0]->jornada_id)->first();
                                    
                                    
                                    if($tipoDia->asistenciaDia_id == 8 || $tipoDia->asistenciaDia_id ==='8'){

                                        //para horario 24x24

                                    } else {
                                        $horarioExtras = DB::select('select GROUP_CONCAT(DISTINCT CONCAT(aehe.HoraEntrada)) as HoraEntrada, GROUP_CONCAT(DISTINCT CONCAT(aehe.HoraSalida)) as HoraSalida 
                                        FROM AsistenciaEmpleadoHorarioExtra aehe 
                                        INNER JOIN AsistenciaEmpleadoExtra_has_Detalle aeehd ON(aehe.id = aeehd.HorarioExtra_id) 
                                        INNER JOIN AsistenciaHorarioEmpleadoExtraDetalle aheed ON (aheed.id = aeehd.HorarioExtraDetalle_id) 
                                        WHERE "'.$fechaHoy.'" BETWEEN aheed.FechaInicio AND aheed.FechaFinal AND aehe.idEmpleadoLaboral = '.$asistenciaEmpleadoLaboral->id);  
                                    }


                                    $ubicacionEmpresa =  DB::table("UbicacionEmpresa")
                                    ->select('UbicacionEmpresa.idUbicacionEmpresa','UbicacionEmpresa.latitude','UbicacionEmpresa.longitude','UbicacionEmpresa.radio','UbicacionEmpresa.descripcion as NombreComercial')
                                    ->join('AsistenciaEmpleadoLaboralEmpresa','UbicacionEmpresa.idUbicacionEmpresa','=','AsistenciaEmpleadoLaboralEmpresa.ubicacionEmpresa_id')
                                    ->where('AsistenciaEmpleadoLaboralEmpresa.puestoEmpleado_id','=',$puestoEmpleado->id)
                                    ->get();

                                    if(!$ubicacionEmpresa){
                                        return response()->json([
                                            "Status"   => "false",
                                            "Estatus" => false,
                                            "Mensaje" => "No tiene asignado una ubicación. Comuníquese con su administrador"
                                        ]);  

                                    } else {
                                        return response()->json(
                                            [
                                                "Estatus"        => true,
                                                "nombreEmpleado" => $persona->nombreEmpleado,
                                                "telefono"       => $persona->Tel_efonoCelular,
                                                "empresa"        => $persona->Descripci_on,
                                                "horariosEntrada"=> $horarioExtras[0]->HoraEntrada,
                                                "horariosSalida" => $horarioExtras[0]->HoraSalida,
                                                'fechaHoy2'       => date('Y/d/m',strtotime($fechaHoy)),
                                                'fechaHoy'      => strftime("%d de %b", strtotime($fechaHoy)),
                                                "ubicacionEmpresas"=> $ubicacionEmpresa
                                            ]
                                        ); 
                                    }



                            } else {
                                    //para horarios normales que no son extras
                                    $jornada = DB::table('AsistenciaJornadaHorario')->select('AsistenciaJornadaHorario.asistenciaDia_id')
                                    ->where('asistenciaJornada_id',$asistenciaEmpleadoLaboral->jornada_id)
                                    ->first();


                                    if($jornada){
                                                if($jornada->asistenciaDia_id == 8 || $jornada->asistenciaDia_id === "8"){
                                                    //perfil de 24x24
                                                        $asistenciaHorarioEmpleado =  DB::table('AsistenciaEmpleadoHorario')
                                                        ->select(DB::raw("GROUP_CONCAT(DISTINCT CONCAT(AsistenciaEmpleadoHorario.horaEntrada)) as HoraEntrada"),
                                                                DB::raw("GROUP_CONCAT(DISTINCT CONCAT(AsistenciaEmpleadoHorario.horaSalida)) as HoraSalida"))
                                                        ->where('AsistenciaEmpleadoHorario.idEmpleadoLaboral',$asistenciaEmpleadoLaboral->id)
                                                        ->where('AsistenciaEmpleadoHorario.dia_id','=', 8 )
                                                        ->get(); 
                                                        if(!$asistenciaHorarioEmpleado){
                                                            return response()->json([
                                                                "Status"   => "false",
                                                                "Estatus" => false,
                                                                "Mensaje" => "No tiene registrado horarios"
                                                            ]); 
                                                        }
                                                } else {

                                                        $asistenciaHorarioEmpleado =  DB::table('AsistenciaEmpleadoHorario')
                                                            ->select(DB::raw("GROUP_CONCAT(DISTINCT CONCAT(AsistenciaEmpleadoHorario.horaEntrada)) as HoraEntrada"),
                                                                    DB::raw("GROUP_CONCAT(DISTINCT CONCAT(AsistenciaEmpleadoHorario.horaSalida)) as HoraSalida"))
                                                            ->where('AsistenciaEmpleadoHorario.idEmpleadoLaboral',$asistenciaEmpleadoLaboral->id)
                                                            ->where('AsistenciaEmpleadoHorario.dia_id','=', (date('w', strtotime($fechaHoy)) + 1) )
                                                            // ->where('AsistenciaEmpleadoHorario.dia_id','=', 5 )
                                                            ->get();  
                                                        if(!$asistenciaHorarioEmpleado){
                                                            return response()->json([
                                                                "Status"   => "false",
                                                                "Estatus" => false,
                                                                "Mensaje" => "No tiene registrado horarios"
                                                            ]); 
                                                        }
                                                }

                                                $ubicacionEmpresa =  DB::table("UbicacionEmpresa")
                                                ->select('UbicacionEmpresa.idUbicacionEmpresa','UbicacionEmpresa.latitude','UbicacionEmpresa.longitude','UbicacionEmpresa.radio','UbicacionEmpresa.descripcion as NombreComercial')
                                                ->join('AsistenciaEmpleadoLaboralEmpresa','UbicacionEmpresa.idUbicacionEmpresa','=','AsistenciaEmpleadoLaboralEmpresa.ubicacionEmpresa_id')
                                                ->where('AsistenciaEmpleadoLaboralEmpresa.puestoEmpleado_id','=',$puestoEmpleado->id)
                                                ->get(); 
                                                                    
                                                if(!$ubicacionEmpresa){
                                                    return response()->json([
                                                        "Status"   => "false",
                                                        "Estatus" => false,
                                                        "Mensaje" => "No tiene asignado una ubicación. Comuníquese con su administrador"
                                                    ]);  

                                                } else {
                                                    return response()->json(
                                                        [
                                                            "Estatus"        => true,
                                                            "nombreEmpleado" => $persona->nombreEmpleado,
                                                            "telefono"       => $persona->Tel_efonoCelular,
                                                            "empresa"        => $persona->Descripci_on,
                                                            "horariosEntrada"=> $asistenciaHorarioEmpleado[0]->HoraEntrada,
                                                            "horariosSalida" => $asistenciaHorarioEmpleado[0]->HoraSalida,
                                                            'fechaHoy'       => date('Y/d/m',strtotime($fechaHoy)),
                                                            "ubicacionEmpresas"=> $ubicacionEmpresa
                                                        ]
                                                    ); 
                                                }
                                    }
                                
                            }//fin if (si existe un horario extra para calcular horiario)

                        } else {
                            return response()->json([
                                "Status"   => "false",
                                "Estatus" => false,
                                "Mensaje" => "Sus datos aun no han sido dados de alta para la aplicación"
                            ]);  
                        }

                    //si no existe puesto empleado
                    } else {
                        return response()->json([
                            "Status"   => "false",
                            "Estatus" => false,
                            "Mensaje" => "RFC/Entidad Federativa son incorrectos"
                        ]);
                    }


                //Si persona no existe
                } else {
                    return response()->json([
                        "Status"   => "false",
                        "Estatus" => false,
                        "Mensaje" => "RFC/Entidad Federativa son incorrectos"
                    ]);
                }

        } catch (\Exception $e) {
            return response()->json([
                "Status"   => "false",
                "Estatus" => false,
                "Mensaje" => "Ocurrio un error, verifiquelo con el administrador.".$e->getMessage()
            ]);
        }

    }//fin del metodo 

}
