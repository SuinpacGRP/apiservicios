<?php

namespace App\Http\Controllers\Checador;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use JWTAuth;
use JWTFactory;
use App\Cliente;
use App\Funciones;
use App\User;


class ChecadorController extends Controller
{
    /**
     * !Se asigna el middleware  al costructor
     */

     
    public function __construct()
    {
      //  $this->middleware( 'jwt', ['except' => ['getToken']] );
    }



   public function verificar_usuario_sistema(Request $request){

        $cadenaMovil =  "kcjMRg2kYw8vbnfWHtBTh59BzPKfQz";

        if($request->cadena === $cadenaMovil){

            $usuario = "suinpacmovilasistencia";
            $pass = "Android13072020$";

            $user =  User::where('Usuario','=',$usuario)->first();
            $token = JWTAuth::fromUser($user);

            return response()->json([
                "estatus"   => "ok",
                "token" => $token
            ]);
        }
        return "Error";
   } 


   public function verificarUsuario2(Request $request){
        $idEmpleado = $request->idEmpleado;
        $idCliente  = $request->idCliente;
        Funciones::selecionarBase($idCliente);
         $resultado =  DB::table('Persona')
                         ->join('Cliente','Persona.Cliente','=','Cliente.id')
                         ->select('Persona.id',
                                   DB::raw('CONCAT(Persona.Nombre," ", Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) as nombreEmpleado'),
                                   'Persona.Tel_efonoCelular',
                                   'Cliente.Descripci_on')
                         ->where('Persona.N_umeroDeEmpleado','=',$idEmpleado)
                         ->where('Persona.Cliente','=',$idCliente)
                         ->where('Persona.Estatus','=','1')
                         ->first();
        
        if($resultado){
                $idPuestoEmpleado = DB::table('PuestoEmpleado')
                ->select('PuestoEmpleado.id')
                ->where('PuestoEmpleado.Empleado','=',$resultado->id)
                ->where('PuestoEmpleado.Estatus','=',"1")
                ->first();

                if($idPuestoEmpleado){

                        $asistenciaEmpleadoLaboral = DB::table('AsistenciaEmpleadoLaboral')
                        ->select('AsistenciaEmpleadoLaboral.id','UbicacionEmpresa.latitude','UbicacionEmpresa.longitude','UbicacionEmpresa.radio','Contribuyente.NombreComercial')
                        ->join('AsistenciaEmpleadoLaboralEmpresa','AsistenciaEmpleadoLaboral.id','=','AsistenciaEmpleadoLaboralEmpresa.asistenciaEmpleadoLaboral_id')
                        ->join('UbicacionEmpresa','AsistenciaEmpleadoLaboralEmpresa.ubicacionEmpresa_id','=','UbicacionEmpresa.idUbicacionEmpresa')
                        ->join('Contribuyente','UbicacionEmpresa.contribuyente_id','=','Contribuyente.id')
                        ->where('AsistenciaEmpleadoLaboral.idPuestoEmpleado','=',$idPuestoEmpleado->id)
                       // ->where('AsistenciaEmpleadoLaboral.'.$this->nombreDelDia(),'=','1')
                        ->first();


                        if($asistenciaEmpleadoLaboral){

                                // $asistenciaHorarioEmpleado = DB::table('AsistenciaEmpleadoHorario')
                                // ->join('ubicacionEmpresa','ubicacionEmpresa.id','=','AsistenciaEmpleadoHorario.idUbicacionEmpresa')
                                // ->select('AsistenciaEmpleadoHorario.*','ubicacionEmpresa.*')
                                // ->where('AsistenciaEmpleadoHorario.idEmpleadoLaboral',$asistenciaEmpleadoLaboral->id)
                                // ->first();
                                
                                $asistenciaHorarioEmpleado =  DB::table('AsistenciaEmpleadoHorario')
                                ->select(DB::raw("GROUP_CONCAT(DISTINCT CONCAT(AsistenciaEmpleadoHorario.HoraEntrada)) as HoraEntrada"),
                                         DB::raw("GROUP_CONCAT(DISTINCT CONCAT(AsistenciaEmpleadoHorario.HoraSalida)) as HoraSalida"))
                                ->where('AsistenciaEmpleadoHorario.idEmpleadoLaboral',$asistenciaEmpleadoLaboral->id)
                                ->get();

                                if($asistenciaHorarioEmpleado){                                    
                        
                                    return response()->json(
                                        [
                                            "estatus"   => "Existe",
                                            "resultado" => $resultado,
                                            "latitude"   => $asistenciaEmpleadoLaboral->latitude,
                                            "longitude" => $asistenciaEmpleadoLaboral->longitude,
                                            "nombreEmpresa" =>  $asistenciaEmpleadoLaboral->NombreComercial,
                                            "radio"   => $asistenciaEmpleadoLaboral->radio,
                                            "horariosEntrada"  => $asistenciaHorarioEmpleado[0]->HoraEntrada,
                                            "horariosSalida"   => $asistenciaHorarioEmpleado[0]->HoraSalida
                                        ]
                                    );        
                                }else{
                                    return response()->json([
                                        "estatus"   => "error",
                                        "Error" => "Aun no tiene horarios asignados"
                                    ]);
                                }
                               
                        } else {
                            return response()->json([
                                "estatus"   => "error",
                                "Error" => "No se puede registrar su asistencia porque no son dias laboral "
                            ]);
                        }

                }else{
                    return response()->json([
                        "estatus"   => "error",
                        "Error" => "RFC/Entidad Federativa incorrectos"
                    ]);
                }
        }else{
            return response()->json([
                "estatus"   => "error",
                "Error" => "RFC/Entidad Federativa incorrectos"
            ]);
        }
        return response()->json('Error');
   }


   public function verificarUsuario(Request $request){
        $idEmpleado = $request->idEmpleado;
        $idCliente  = $request->idCliente;
        Funciones::selecionarBase($idCliente);
        
        //autentificacion con RFC
        $resultado =  DB::table('Persona')
                        ->join('Cliente','Persona.Cliente','=','Cliente.id')
                        ->join('DatosFiscales','DatosFiscales.id','=','Persona.DatosFiscales')
                        ->select('Persona.id',
                                DB::raw('CONCAT(Persona.Nombre," ", Persona.ApellidoPaterno," ",Persona.ApellidoMaterno) as nombreEmpleado'),
                                'Persona.Tel_efonoCelular',
                                'Cliente.Descripci_on')
                        ->where('DatosFiscales.RFC','=',$idEmpleado)
                        ->where('Persona.Cliente','=',$idCliente)
                        ->where('Persona.Estatus','=','1')
                        ->first();
        //Se valida si existe resultados de la persona
        if($resultado){
            //Se realiza la consulta para obtener los datos del puestoEmpleado
            $puestoEmpleado = DB::table('PuestoEmpleado')->select('PuestoEmpleado.id')->where('PuestoEmpleado.Empleado','=',$resultado->id)->where('PuestoEmpleado.Estatus','=',"1")->first();
            //Valida si exsite el resultado del puestoEmpleado
            if($puestoEmpleado){
               //se realiza una consulta para buscar las asistencias del empleado
                

                    // $asistenciaEmpleadoLaboral = DB::table('AsistenciaEmpleadoLaboral')
                    // ->select('AsistenciaEmpleadoLaboral.id','AsistenciaEmpleadoLaboral.jornada_id','UbicacionEmpresa.latitude','UbicacionEmpresa.longitude','UbicacionEmpresa.radio','Contribuyente.NombreComercial')
                    // ->join('AsistenciaEmpleadoLaboralEmpresa','AsistenciaEmpleadoLaboral.id','=','AsistenciaEmpleadoLaboralEmpresa.asistenciaEmpleadoLaboral_id')
                    // ->join('UbicacionEmpresa','AsistenciaEmpleadoLaboralEmpresa.ubicacionEmpresa_id','=','UbicacionEmpresa.idUbicacionEmpresa')
                    // ->join('Contribuyente','UbicacionEmpresa.contribuyente_id','=','Contribuyente.id')
                    // ->where('AsistenciaEmpleadoLaboral.idPuestoEmpleado','=',$puestoEmpleado->id)
                    // ->first();

                    $asistenciaEmpleadoLaboral = DB::table('AsistenciaEmpleadoLaboral')
                    ->select('AsistenciaEmpleadoLaboral.id','AsistenciaEmpleadoLaboral.jornada_id')              
                    ->where('AsistenciaEmpleadoLaboral.idPuestoEmpleado','=',$puestoEmpleado->id)
                    ->first();


                   
                    //verificamos si el empelado tiene asignado ubicacion y una empresa destino
                    if($asistenciaEmpleadoLaboral){

                        $jornada = DB::table('AsistenciaJornada')->select('*')->where('idJornada','=',$asistenciaEmpleadoLaboral->jornada_id)->first();

                        if($jornada){
                            //por medio de la jornada laboral se debea saber a que tipo pertence: el num 8 es tipo otro, eso quiere decir que puede ser 24x24,24x7 etc.
                            //si tiene tipo 1,2...hasta 7 significa que es un personalizado
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
                                                    ->select('UbicacionEmpresa.latitude','UbicacionEmpresa.longitude','UbicacionEmpresa.radio','UbicacionEmpresa.descripcion as NombreComercial')
                                                    ->join('AsistenciaEmpleadoLaboralEmpresa','UbicacionEmpresa.idUbicacionEmpresa','=','AsistenciaEmpleadoLaboralEmpresa.ubicacionEmpresa_id')
                                                    ->where('AsistenciaEmpleadoLaboralEmpresa.puestoEmpleado_id','=',$puestoEmpleado->id)
                                                    ->first();

                            if($ubicacionEmpresa){
                                    return response()->json(
                                        [
                                            "estatus"   => "Existe",
                                            "resultado" => $resultado,
                                            "latitude"   => $ubicacionEmpresa->latitude,
                                            "longitude" => $ubicacionEmpresa->longitude,
                                            "nombreEmpresa" =>  $ubicacionEmpresa->NombreComercial,
                                            "radio"   => $ubicacionEmpresa->radio,
                                            "horariosEntrada"  => $asistenciaHorarioEmpleado[0]->HoraEntrada,
                                            "horariosSalida"   => $asistenciaHorarioEmpleado[0]->HoraSalida
                                        ]
                                    ); 
                            }else{
                                return response()->json([
                                    "estatus"   => "error",
                                    "Error" => "No tiene asignado una ubicación para registrar asistencia"
                                ]);
                            }
                                                                
                        }
                    }
                    



                   // return response()->json($asistenciaEmpleadoLaboral);

                
                
                 
            } else {
                return response()->json([
                    "estatus"   => "error",
                    "Error" => "Los datos del empleado no existen"
                ]);
            }
          

        } else{
            return response()->json([
                "estatus"   => "error",
                "Error" => "Los datos del empleado no existen 1"
            ]);
        }
        
        
    
   }
   

   public function registrarAsistencia(Request $request) {
       //idEmpleado es remplazado por el RFC que esta en la tabla DatosFiscales
        $idEmpleado = $request->idEmpleado;
        $idCliente = $request->idCliente;
        Funciones::selecionarBase($idCliente);
        $fechaRegistro = new \DateTime();

        // $idPersona =  DB::table('Persona')
        //                     ->select('Persona.id')
        //                     ->where('Persona.N_umeroDeEmpleado','=',$idEmpleado)
        //                     ->where('Persona.Cliente','=',$idCliente)
        //                     ->where('Persona.Estatus','=','1')
        //                     ->first();
        $idPersona =  DB::table('Persona')
        ->join('Cliente','Persona.Cliente','=','Cliente.id')
        ->join('DatosFiscales','DatosFiscales.id','=','Persona.DatosFiscales')
        ->select('Persona.id')
        ->where('DatosFiscales.RFC','=',$idEmpleado)
        ->where('Persona.Cliente','=',$idCliente)
        ->where('Persona.Estatus','=','1')
        ->first();
        
        if($idPersona){
            //obtener el idPuestoEmpleado de persona
            $idPuestoEmpleado = DB::table('PuestoEmpleado')
                                    ->select('PuestoEmpleado.id')
                                    ->where('PuestoEmpleado.Empleado','=',$idPersona->id)
                                    ->where('PuestoEmpleado.Estatus','=',"1")
                                    ->first();
            
            $asistenciaEmpleadoLaboral = DB::table('AsistenciaEmpleadoLaboral')
                                            ->select('AsistenciaEmpleadoLaboral.*')
                                            ->where('AsistenciaEmpleadoLaboral.idPuestoEmpleado','=',$idPuestoEmpleado->id)
                                          //  ->where('AsistenciaEmpleadoLaboral.'.$this->nombreDelDia(),'=','1')
                                            ->first();
            
            //valida si el usuario puede registrar entre los dias de semana habil de trabajo
            if($asistenciaEmpleadoLaboral){

                    $fechaAsistencia = $fechaRegistro->format('Y-m-d H:i:s');
                    $resultado = DB::table('AsistenciaHorario')
                                ->insert(
                                         ['idEmpleadoLaboral' => $asistenciaEmpleadoLaboral->id, 
                                          'Fecha' => $fechaAsistencia,
                                          'Observacion' => 0
                                         ]);


                return response()->json(
                    [
                        "estatus"   => "registrado",
                        "titulo"    => "Mensaje de confirmación ",
                        "mensaje"   => "Su asistencia a sido registrada"
                    ]
                );

            }else{
                return response()->json('No se puede registrar');
            }

        }else{
            //la persona no existe
        }
   }
   
   public function historialAsistencia(Request $request){
        $idEmpleado = $request->idEmpleado;
        $idCliente = $request->idCliente;
        Funciones::selecionarBase($idCliente);

        $persona =  DB::table('Persona')
                        ->join('Cliente','Persona.Cliente','=','Cliente.id')
                        ->join('DatosFiscales','DatosFiscales.id','=','Persona.DatosFiscales')
                        ->select('Persona.id')
                        ->where('DatosFiscales.RFC','=',$idEmpleado)
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
                DB::raw("CONCAT(DATE_FORMAT(AsistenciaHorario.Fecha,'%h:%i:%s %p')) as horaRegistro"))
        ->where('AsistenciaHorario.idEmpleadoLaboral','=',$asistenciaEmpleadoLaboral->id)
        ->orderBy('AsistenciaHorario.Fecha','DESC')
        ->limit(30)
        ->get();
        
        if($resultadoHistorial){
            return response()->json($resultadoHistorial);
        }
     return response()->json("error");
   }

   public function getDatosChecador(Request $request){
        $idCliente = $request->idCliente;
        Funciones::selecionarBase($idCliente);

        $checadores = DB::table('AsistenciaChecador')
                        ->select('*')
                        ->get();
        
        if($checadores){
        return response()->json($checadores);
        }
        return response()->json(['Error' => "Error en la consulta"]);
   }

   public function getUbicacionEmpresa(){

   }


   public function obtenerURLLogo(Request $request){
        $idEmpleado = $request->idEmpleado;
        $idCliente = $request->idCliente;
        Funciones::selecionarBase($idCliente);

        $urlImage = DB::table('CelaRepositorioC')
                    ->join('Cliente','CelaRepositorioC.idRepositorio','=','Cliente.Logotipo')
                    ->select('CelaRepositorioC.Ruta')
                    ->where('Cliente.id','=',$idCliente)
                    ->first();
        return response()->json($urlImage);
   }


    public function validarDiaRegistro(){
        $fechaHoyTime  = new \DateTime();
        $fechaHoy =  $fechaHoyTime->format('Y-m-d');

        switch (date('w', strtotime($fechaHoy) )){
            case 0: 
                return "Domingo"; 
            break;
            case 1: 
                return "Lunes";
             break;
            case 2: 
                return "Martes";
             break;
            case 3: 
                return "Miercoles"; 
            break;
            case 4: 
                return "Jueves"; 
            break;
            case 5: 
                return "Viernes";
             break;
            case 6: 
                return "Sabado";
            break;
        } 
    
    }

    public function nombreDelDia(){
        $fechaHoyTime  = new \DateTime();
        $fechaHoy =  $fechaHoyTime->format('Y-m-d');
        switch (date('w', strtotime($fechaHoy) )){
            case 0: return "Domingo"; break;
            case 1: return "Lunes"; break;
            case 2: return "Martes"; break;
            case 3: return "Miercoles"; break;
            case 4: return "Jueves"; break;
            case 5: return "Viernes"; break;
            case 6: return "Sabado"; break;
        }
    }


    public function asistencias(Request $request){
        $idEmpleado = $request->idEmpleado;
        $idCliente = $request->idCliente;

        Funciones::selecionarBase(36);
        $persona =  DB::table('Persona')
                        ->join('Cliente','Persona.Cliente','=','Cliente.id')
                        ->join('DatosFiscales','DatosFiscales.id','=','Persona.DatosFiscales')
                        ->select('Persona.id')
                        ->where('DatosFiscales.RFC','=',$idEmpleado)
                        ->where('Persona.Cliente','=',$idCliente)
                        ->where('Persona.Estatus','=','1')
                        ->first();
                

        $resultado = DB::table('AsistenciaHorario')
                ->select('AsistenciaHorario.Fecha as fechaRegistro','AsistenciaHorario.Observacion')
                ->where('AsistenciaHorario.idPersona','=',$persona->id)
                ->orderBy('AsistenciaHorario.Fecha','DESC')
                ->get();

        if($resultado){
           return response()->json($resultado);
        }
        return response()->json("error");
    }


    public function registrarAsistenciaChecador(Request $request){
       $idCliente = $request->idCliente;
       $idChecador = $request->idChecador;
       $idEmpleadoChecador = $request->idEmpleadoChecador;
       $fechaRegistro = $request->fechaRegistro;

       Funciones::selecionarBase($idCliente);

       $idPuestoEmpleado = DB::table('PuestoEmpleado')
       ->select('PuestoEmpleado.id')
       ->where('PuestoEmpleado.EnChecador','=',$idEmpleadoChecador)
       ->where('PuestoEmpleado.idChecador','=',$idChecador)
       ->where('PuestoEmpleado.Estatus','=',"1")
       ->first();

       if($idPuestoEmpleado){
            $asistenciaEmpleadoLaboral = DB::table('AsistenciaEmpleadoLaboral')
            ->select('AsistenciaEmpleadoLaboral.*')
            ->where('AsistenciaEmpleadoLaboral.idPuestoEmpleado','=',$idPuestoEmpleado->id)
            //  ->where('AsistenciaEmpleadoLaboral.'.$this->nombreDelDia(),'=','1')
            ->first();

            if($asistenciaEmpleadoLaboral){
                $resultado = DB::table('AsistenciaHorario')
                ->insert(
                         ['idEmpleadoLaboral' => $asistenciaEmpleadoLaboral->id, 
                          'Fecha' => $fechaRegistro,
                          'Observacion' => 0
                         ]);
         
                    if($resultado){
                        return response()->json(
                            ["estatus"   => "registrado",
                            "titulo"    => " ",
                            "mensaje"   => "Asistencia registrado",
                            "idEmpleadoLaboral" => $asistenciaEmpleadoLaboral->id
                            ]);
                    }else{
                        return response()->json(
                            ["estatus"   => "error",
                             "titulo"    => "Error ",
                             "mensaje"   => "No se pudo registrar la asistencia a la base de datos"
                             ]);
                    }
                 
               }else{
                return response()->json(
                    ["estatus"   => "error",
                     "titulo"    => "Error ",
                     "mensaje"   => "El id checador  no esta registrado en la base de datos"
                     ]);
               }
       }else{
        return response()->json(
            ["estatus"   => "error",
             "titulo"    => "Error ",
             "mensaje"   => "El id checador  no esta registrado en la base de datos"
             ]);
       }

    }



    
    function getClientes(Request $request){
        $claveEncry = $request->clave;
        if($claveEncry == 'p5D46-kc8)'){
            $cliente=Funciones::DecodeThis2($request->Cliente);
            //return $cliente;
            if($cliente==="cliente!@$"){
                
                $Cliente = Cliente::select('id', 'Descripci_on','nombre')
                    ->where('Estatus', '1')
                    ->orderBy('Descripci_on','asc')
                    ->get();
                    //return $cliente;
                    return response()->json([
                        'success' => '1',
                        'cliente'=> $Cliente
                    ], 200);
            }else{
                return response()->json([
                    'success' => '0',
                ], 300);
            }    
        }

    }

    
    function getClientesChecador(Request $request){
        $claveEncry = $request->clave;
        if($claveEncry == 'p5D46-kc8)'){
            $cliente=Funciones::DecodeThis2($request->Cliente);
            //return $cliente;
            if($cliente==="cliente!@$"){
                
                $Cliente = Cliente::select('id', 'Descripci_on','nombre')
                    ->where('Estatus', '1')
                    ->orderBy('Descripci_on','asc')
                    ->get();
                    //return $cliente;
                    return response()->json($Cliente);
            }else{
                return response()->json([
                    'success' => '0',
                ], 300);
            }    
        }

    }


    function getRetardosYfaltas2(Request $request){
        $idEmpleado = $request->idEmpleado;
        $idCliente = $request->idCliente;
        Funciones::selecionarBase($idCliente);

        $persona =  DB::table('Persona')
                        ->join('Cliente','Persona.Cliente','=','Cliente.id')
                        ->join('DatosFiscales','DatosFiscales.id','=','Persona.DatosFiscales')
                        ->select('Persona.id')
                        ->where('DatosFiscales.RFC','=',$idEmpleado)
                        ->where('Persona.Cliente','=',$idCliente)
                        ->where('Persona.Estatus','=','1')
                        ->first();
        //obtenemos la fecha actual
        $fecha = new \DateTime();
        $fechaHoy = $fecha->format('Y-m-d');
        $numero_del_mes =  date('m');
        $anio_actual = date('Y');
        $numero_del_dia = date("d");
        $ultimo_dia_del_mes = cal_days_in_month(CAL_GREGORIAN, $numero_del_mes, $anio_actual); 

        
        
        $empleado =  DB::table('PlantillaN_ominaCliente')
                       ->select("AsistenciaEmpleadoLaboral.id AS idEmpleadoLaboral","PuestoEmpleado.id AS idPuestoEmpleado",
                                DB::raw("CONCAT (Persona.Nombre ,' ',Persona.ApellidoPaterno,' ',Persona.ApellidoMaterno) AS nombreCompleto"),
                                DB::raw("CONCAT(AsistenciaEmpleadoLaboral.Lunes,',',AsistenciaEmpleadoLaboral.Martes,',',AsistenciaEmpleadoLaboral.Miercoles,',',AsistenciaEmpleadoLaboral.Jueves,',',AsistenciaEmpleadoLaboral.Viernes,',',AsistenciaEmpleadoLaboral.Sabado,',',AsistenciaEmpleadoLaboral.Domingo) as diasLaborales"),
                                'AsistenciaEmpleadoLaboral.tolerancia','AsistenciaEmpleadoLaboral.retardo')
                       ->join('PuestoEmpleado','PuestoEmpleado.PlantillaN_ominaCliente','=','PlantillaN_ominaCliente.id')
                       ->join('Persona','Persona.id','=','PuestoEmpleado.Empleado')
                       ->join('AsistenciaEmpleadoLaboral','AsistenciaEmpleadoLaboral.idPuestoEmpleado','=','PuestoEmpleado.id')
                       ->where('PuestoEmpleado.Estatus','=',1)
                       ->where('Persona.Cliente','=',$idCliente)
                       ->where('Persona.id','=',$persona->id)
                       ->groupBy('AsistenciaEmpleadoLaboral.id')
                       ->first();

        
        $diasLaborales = explode(",", $empleado->diasLaborales);
        $horarios = DB::table('AsistenciaEmpleadoHorario')
                     ->select('*')
                     ->where('AsistenciaEmpleadoHorario.idEmpleadoLaboral','=',$empleado->idEmpleadoLaboral)
                     ->get();

        //obtenemos el numero del dia
        $fecha_dia = date("d",strtotime($fechaHoy));

        if($fecha_dia <= 15){
            $fechaInicio = $anio_actual.'-'.$numero_del_mes.'-01';
            $fechaFinal =  $anio_actual.'-'.$numero_del_mes.'-'.$fecha_dia;
         }else{
            $fechaInicio = $anio_actual.'-'.$numero_del_mes.'-15';
            $fechaFinal =  $anio_actual.'-'.$numero_del_mes.'-'.$fecha_dia;
         }


        $registro_de_asistencias = DB::table('AsistenciaHorario')
                                    ->select('*')
                                    ->where('AsistenciaHorario.idEmpleadoLaboral','=',$empleado->idEmpleadoLaboral)
                                    ->where(DB::raw('date(AsistenciaHorario.Fecha)'),'>=',$fechaInicio)
                                    ->where(DB::raw('date(AsistenciaHorario.Fecha)'),'<=',$fechaFinal)
                                    ->orderBy('AsistenciaHorario.Fecha','DESC')
                                    ->get();

        $diasFeriados = DB::table('AsistenciaEmpleadoFeriados')
                          ->select('AsistenciaDiasFeriados.fecha')
                          ->join('AsistenciaDiasFeriados','AsistenciaDiasFeriados.idDiaFeriado','=','AsistenciaEmpleadoFeriados.diaFeriado_id')
                          ->where('AsistenciaEmpleadoFeriados.empleadoLaboral_id','=',$empleado->idEmpleadoLaboral)
                          ->get();
        
        $tolerancia = '+ '.$empleado->tolerancia.' minute';
        $retardo = '+ '.$empleado->retardo.' minute';        
        $arrayFaltas = Array();
        $arrayRetardos = Array();
        $arrayDiaHoraRetardo = Array();
        $arrayEmpleadoFaltas = Array();
        $arrayAsisTem = Array();
        foreach($this->arrayFechas($fechaInicio,$fechaFinal) as $fecha){

               if($this->esDiaLaboral($fecha,$diasLaborales)){
                      
                      if($this->existeRegistroFechaDeAsistencia($fecha,$registro_de_asistencias)){
                          
                            $asistenciasTemp =  DB::table('AsistenciaHorario')
                                                ->select('*')
                                                ->where('AsistenciaHorario.idEmpleadoLaboral','=',$empleado->idEmpleadoLaboral)
                                                ->where(DB::raw('date(AsistenciaHorario.Fecha)'),'=',$fecha)
                                                ->orderBy(DB::raw('date(AsistenciaHorario.Fecha)'),'DESC')
                                                ->get();

                            foreach ($horarios as $horario){
                                    $array_registros_asistencias = Array();
                                    $tipoAsistencia =  "";
                                    foreach ($asistenciasTemp as $asistencia){
                                        //validamos que la fecha de registro de asistencia sea menor a la hora de entrada + tolerancia, y que sea mayor a la hora de entrada(haciendo una operacion de resta de 1 hora)
                                        $horaRegistro = strtotime(substr($asistencia->Fecha, 11,19));
                                        $horaEntrada =  strtotime($horario->HoraEntrada.$tolerancia);
                                        $horaEntradaRetardo = strtotime(date('H:i:s', $horaEntrada).$retardo);
                                        $horaMinimoEntrada = strtotime($horario->HoraEntrada.'-40 minute');
                                        
                                        if($horaRegistro >= $horaMinimoEntrada && $horaRegistro <= $horaEntradaRetardo){
                                            array_push($array_registros_asistencias, $horaRegistro);
                                        }
                                    } 

                                    if(count($array_registros_asistencias) > 0 ){
                                            
                                        //del arreglo buscamos el primer registro en caso que se registro muchas asistencias al mismo tiempo o rangos entre minutos
                                        $asistenciaMin =  min($array_registros_asistencias);
                                        
                                        //aplica si el empleado llega tarde para la hora de entrada
                                        if($asistenciaMin > $horaEntrada && $asistenciaMin < $horaEntradaRetardo){
                                            
                                            array_push($arrayRetardos, $fecha.' '.date('H:i:s',$asistenciaMin));
                                         //   $tipoAsistencia ="R";
                                            
                                        }
                                        
                                    }else{
                                        //Entra cuando el empleado registra su asistencia despues de 1 hora despues o mas de  la hora de entrada,
                                        // entonces se toma como una falta, se puede cambiar a retardo dependiendo cada empresa;
                                        
                                        $tipoAsistencia =  "F";
                                      //  array_push($arrayDiaHoraRetardo, 'Retardo '.$fecha.' en la hora de entrada: '.$horario->HoraEntrada);
                                    }
                            }
                            
                      }else{
                                    $esFalta = false;
                                    // primero verificamos si hay datos en la consulta $arrayFechasFeriados,
                                    if($diasFeriados){
                                        
                                            //como no existe la fecha en la condicion if, entonces quiere decir que puede que sea un dia feriado, sino entonces si es una falta
                                            if($this->esDiaFeriado($fecha,$diasFeriados)){
                                                $esFalta = false;
                                            }else{
                                                $esFalta = true;
                                            }                      
                                    }else{
                                        $esFalta = true;
                                    }

                                    if($esFalta){
                                        //antes de verificar si es falta comprobamos si tiene justificaciones pendiente
                                        
                                        //Es falta
                                        array_push($arrayFaltas, $fecha);
                                    }else{
                                        //es asistencia
                                    }  
                            }
               }
        }

        $nuevo_array_retardos = $this->formato_fecha($arrayRetardos,1);
        $nuevo_array_faltas   = $this->formato_fecha($arrayFaltas  ,2);
         return response()->json([
            'arrayRetardos' => $nuevo_array_retardos,
            'arrayFaltas' => $nuevo_array_faltas,
            'totalRetardos' => count($arrayRetardos),
            'totalFaltas'=> count($arrayFaltas)
         ]);
        
    }


    //get_retardos_y_faltas
    function getRetardosYfaltas(Request $request){
        $idEmpleado = $request->idEmpleado;
        $idCliente = $request->idCliente;
        Funciones::selecionarBase($idCliente);


        //obtenemos la fecha actual
        $fecha = new \DateTime();
        $fechaHoy = $fecha->format('Y-m-d');
        $numero_del_mes =  date('m');
        $anio_actual = date('Y');
        $numero_del_dia = date("d");
        $ultimo_dia_del_mes = cal_days_in_month(CAL_GREGORIAN, $numero_del_mes, $anio_actual); 

        $persona =  DB::table('Persona')
                        ->select('Persona.id')
                        ->where('Persona.N_umeroDeEmpleado','=',$idEmpleado)
                        ->where('Persona.Cliente','=',$idCliente)
                        ->where('Persona.Estatus','=','1')
                        ->first();

        $empleado =  DB::table('PlantillaN_ominaCliente')
                        ->select("AsistenciaEmpleadoLaboral.id AS idEmpleadoLaboral","PuestoEmpleado.id AS idPuestoEmpleado",
                                 DB::raw("CONCAT (Persona.Nombre ,' ',Persona.ApellidoPaterno,' ',Persona.ApellidoMaterno) AS nombreCompleto"),
                                 'AsistenciaEmpleadoLaboral.tolerancia','AsistenciaEmpleadoLaboral.retardo','AsistenciaEmpleadoLaboral.jornada_id')
                        ->join('PuestoEmpleado','PuestoEmpleado.PlantillaN_ominaCliente','=','PlantillaN_ominaCliente.id')
                        ->join('Persona','Persona.id','=','PuestoEmpleado.Empleado')
                        ->join('AsistenciaEmpleadoLaboral','AsistenciaEmpleadoLaboral.idPuestoEmpleado','=','PuestoEmpleado.id')
                        ->where('PuestoEmpleado.Estatus','=',1)
                        ->where('Persona.Cliente','=',$idCliente)
                        ->where('Persona.id','=',$persona->id)
                        ->groupBy('AsistenciaEmpleadoLaboral.id')
                        ->first();
        
        $tolerancia = '+ '.$empleado->tolerancia.' minute';
        $retardo = '+ '.$empleado->retardo.' minute';        
        $arrayFaltas = Array();
        $arrayRetardos = Array();
        $arrayDiaHoraRetardo = Array();
        $arrayEmpleadoFaltas = Array();

        //obtenemos el numero del dia
         $fecha_dia = date("d",strtotime($fechaHoy));
       //  $fecha_dia = date("d",strtotime('2020-01-19'));

        if($fecha_dia <= 15){
            $fechaInicio = $anio_actual.'-'.$numero_del_mes.'-01';
            $fechaFinal =  $anio_actual.'-'.$numero_del_mes.'-'.$fecha_dia;
        }else{
            $fechaInicio = $anio_actual.'-'.$numero_del_mes.'-15';
            $fechaFinal =  $anio_actual.'-'.$numero_del_mes.'-'.$fecha_dia;
         #   $fechaInicio =  '2020-01-15';
         #   $fechaFinal  =  '2020-01-29'; 
        }

        $tipo = "personalizado";
        $arrayFechas = $this->arrayFechasV1($fechaInicio, $fechaFinal, $tipo);

        $tipoJornada =  DB::table('AsistenciaJornada')->select('AsistenciaJornada.*')->where('AsistenciaJornada.idJornada','=',$empleado->jornada_id)->first();
        $registros_de_asistencias = DB::table('AsistenciaHorario')->select('*')->where('AsistenciaHorario.idEmpleadoLaboral','=',$empleado->idEmpleadoLaboral)->where(DB::raw('date(AsistenciaHorario.Fecha)'),'>=',$fechaInicio)->where(DB::raw('date(AsistenciaHorario.Fecha)'),'<=',$fechaFinal)->orderBy('AsistenciaHorario.Fecha','DESC')->get();

        if( $tipoJornada->nombre === '24x24' ){            
            // $horariosAsigandos = DB::table('AsistenciaJornadaHorario')->Select('AsistenciaJornadaHorario.horaEntrada','AsistenciaJornadaHorario.horaSalida')
            //                        ->join('AsistenciaDia','AsistenciaDia.idAsistenciaDia','=','AsistenciaJornadaHorario.asistenciaDia_id')->join('AsistenciaJornada','AsistenciaJornada.idJornada','=','AsistenciaJornadaHorario.asistenciaJornada_id')
            //                        ->where('AsistenciaJornadaHorario.asistenciaJornada_id','=',$empleado->jornada_id)->where('AsistenciaJornadaHorario.asistenciaDia_id','=','8')->get();
            #SELECT AsistenciaEmpleadoHorario.HoraEntrada as horaEntrada , AsistenciaEmpleadoHorario.HoraSalida as horaSalida FROM AsistenciaEmpleadoHorario  WHERE  idEmpleadoLaboral = ".$empleado['idEmpleadoLaboral'] ." AND dia_id = 8"

            $horariosAsigandos = DB::table('AsistenciaEmpleadoHorario')->select('AsistenciaEmpleadoHorario.HoraEntrada as horaEntrada','AsistenciaEmpleadoHorario.HoraSalida as horaSalida')
                                    ->where('idEmpleadoLaboral','=',$empleado->idEmpleadoLaboral)
                                    ->where('dia_id','=',8)->get();

            $arrayDiasLaborales = $this->arrayFechasV1($fechaInicio, $fechaFinal, $tipoJornada->nombre);

        }else if( $tipoJornada->nombre === 'personalizado' ){
           $arrayDiasLaborales = DB::table('AsistenciaEmpleadoHorario')->select( DB::raw('DISTINCT (CONCAT (AsistenciaEmpleadoHorario.dia_id))  AS asistenciaDia_id') )->where('AsistenciaEmpleadoHorario.idEmpleadoLaboral','=',$empleado->idEmpleadoLaboral)->get();

            #return response()->json($arrayDiasLaborales);
        }


        foreach ($arrayFechas as $fechaLaboral) {

            if(  $this->esDiaLaboralV2($fechaLaboral, $arrayDiasLaborales, $tipoJornada->nombre) )  {

                if(  $this->existeFechaRegistroDeAsistencia($fechaLaboral, $registros_de_asistencias)  ) {

                    $registros_de_asistencias_fechaLaboral =  DB::table('AsistenciaHorario')
                                                                                ->select('AsistenciaHorario.*')
                                                                                ->where('AsistenciaHorario.idEmpleadoLaboral','=',$empleado->idEmpleadoLaboral)
                                                                                ->where(DB::raw('date(AsistenciaHorario.Fecha)'),'=',$fechaLaboral)
                                                                                ->orderBy(DB::raw('date(AsistenciaHorario.Fecha)'),'DESC')
                                                                                ->get();

                    

                    if($tipoJornada->nombre === 'personalizado'){
                        $numeroDelDia = date('w', strtotime($fechaLaboral)) + 1;
                        $horariosAsigandos = DB::table('AsistenciaEmpleadoHorario')
                        ->select('AsistenciaEmpleadoHorario.HoraEntrada as horaEntrada','AsistenciaEmpleadoHorario.HoraSalida as horaSalida')
                        ->where('AsistenciaEmpleadoHorario.dia_id','=',$numeroDelDia)
                        ->where('AsistenciaEmpleadoHorario.idEmpleadoLaboral','=',$empleado->idEmpleadoLaboral)->get();
                    }

                    if( count($horariosAsigandos) > 0  ){

                        foreach( $horariosAsigandos as $horario  ){

                            $array_registros_asistencias = Array();
                            $tipoAsistencia =  "";

                            $horaEntrada =  strtotime($horario->horaEntrada.$tolerancia);
                            $horaEntradaRetardo = strtotime(date('H:i:s', $horaEntrada).$retardo);
                            $horaMinimoEntrada = strtotime($horario->horaEntrada.'-40 minute');

                            foreach ( $registros_de_asistencias_fechaLaboral as $asistencia ) {
                                //validamos que la fecha de registro de asistencia sea menor a la hora de entrada + tolerancia, y que sea mayor a la hora de entrada(haciendo una operacion de resta de 1 hora)
                                $horaRegistro = strtotime(substr($asistencia->Fecha, 11,19));
                                                              
                                if($horaRegistro >= $horaMinimoEntrada && $horaRegistro <= $horaEntradaRetardo){
                                    array_push($array_registros_asistencias, $horaRegistro);
                                }

                            }


                            if(count($array_registros_asistencias) > 0 ){
                                            
                                //del arreglo buscamos el primer registro en caso que se registro muchas asistencias al mismo tiempo o rangos entre minutos
                                $asistenciaMin =  min($array_registros_asistencias);
                                
                                //aplica si el empleado llega tarde para la hora de entrada
                                if($asistenciaMin > $horaEntrada && $asistenciaMin < $horaEntradaRetardo){
                                    
                                    array_push($arrayRetardos, $fechaLaboral.' '.date('H:i:s',$asistenciaMin));
                                 //   $tipoAsistencia ="R";
                                    
                                }
                                
                            }else{
                                //Entra cuando el empleado registra su asistencia despues de 1 hora despues o mas de  la hora de entrada,
                                // entonces se toma como una falta, se puede cambiar a retardo dependiendo cada empresa;
                                
                               // $tipoAsistencia =  "F";
                              //  array_push($arrayDiaHoraRetardo, 'Retardo '.$fecha.' en la hora de entrada: '.$horario->HoraEntrada);
                            }


                        
                        }// fin del for horarios

                    }//if si el contador de horarios es mayor a cero

                } else {
                    array_push($arrayFaltas, $fechaLaboral);
                }
                    


            }
        }


        $nuevo_array_retardos = $this->formato_fecha($arrayRetardos,1);
        $nuevo_array_faltas   = $this->formato_fecha($arrayFaltas  ,2);
         return response()->json([
            'arrayRetardos' => $nuevo_array_retardos,
            'arrayFaltas' => $nuevo_array_faltas,
            'totalRetardos' => count($arrayRetardos),
            'totalFaltas'=> count($arrayFaltas)
         ]);
        



    }//fin de la funcion get retardos y faltas

    

    function arrayFechas($fechaInicial, $fechaFinal){
        $arrayFechas = Array();
        $fechaaamostar = $fechaInicial; 
        array_push($arrayFechas, $fechaaamostar);
        while(strtotime($fechaFinal) >= strtotime($fechaInicial)) { 
            if(strtotime($fechaFinal) != strtotime($fechaaamostar)) { 
                $fechaaamostar = date("Y-m-d", strtotime($fechaaamostar . " + 1 day")); 
                array_push($arrayFechas, $fechaaamostar);
            } else { 
                break; 
            } 
        }
        return $arrayFechas;
    }


    



    function arrayFechasV1($fechaInicial,$fechaFinal,$tipoJornada){
        $arrayFechas = Array();
        $fechaNueva = $fechaInicial; 
        array_push($arrayFechas, $fechaNueva);
        if($tipoJornada === '24x24'){
            $dias = " + 2 day";
        }else if($tipoJornada === 'personalizado'){
            $dias = " + 1 day";
        }
        while( strtotime($fechaFinal) > strtotime($fechaNueva) ){
            $fechaNueva = date('Y-m-d', strtotime($fechaNueva.$dias));
            if( strtotime($fechaNueva) <= strtotime($fechaFinal) ){
                array_push($arrayFechas, $fechaNueva);
            }else{
                break;
            }     
        }
        
         return $arrayFechas;
    }


    function existeFechaRegistroDeAsistencia($fechaBuscar, $registroDeAsistencias){
        foreach ($registroDeAsistencias as $fechaAsistencia){
                if (strtotime(substr($fechaAsistencia->Fecha, 0,10))  == strtotime($fechaBuscar)){
                    return true;
                }
           }
           return false;
    }





    function esDiaLaboral($fecha,$diasLaborales){
        $nombreDia = "";
        switch (date('w', strtotime($fecha) )){
            case 0:$nombreDia = "Domingo";break;
            case 1:$nombreDia = "Lunes";break;
            case 2:$nombreDia = "Martes";break;
            case 3:$nombreDia = "Miercoles";break;
            case 4:$nombreDia = "Jueves";break;
            case 5:$nombreDia = "Viernes";break;
            case 6:$nombreDia = "Sabado";break;
        }
        if($nombreDia === 'Lunes' && $diasLaborales[0] == 1 ){
            return true;
        }else if($nombreDia == 'Martes' && $diasLaborales[1] == 1){
            return true;
        }else if($nombreDia == 'Miercoles' && $diasLaborales[2] == 1){
            return true;
        }else if($nombreDia == 'Jueves' && $diasLaborales[3] == 1){
            return true;
        }else if($nombreDia == 'Viernes' && $diasLaborales[4] == 1){
            return true;
        }else if($nombreDia == 'Sabado' && $diasLaborales[5] == 1){
            return true;
        }else if($nombreDia == 'Domingo' && $diasLaborales[6] == 1){
            return true;
        }
        return false;
    }


    function esDiaLaboralV2($fechaBuscar,$arrayDiasLaborales,$tipo){
        if($tipo === '24x24'){
            foreach ($arrayDiasLaborales  as $fecha){
                if( strtotime($fecha) === strtotime($fechaBuscar) ){
                    return true;
                }
            }
            return false;
            
        }else if( $tipo === 'personalizado' ){
            $numeroDelDia = date('w', strtotime($fechaBuscar)) + 1;
            
            foreach ($arrayDiasLaborales as $dia){
                if(intval( $dia->asistenciaDia_id) ==  $numeroDelDia  ){
                    return true;
                }       
            }
            return false;
        }   
    }


    function existeRegistroFechaDeAsistencia($fechaBuscar,$arrayHistorialAsistencias){
        foreach ($arrayHistorialAsistencias as $fechaAsistencia){
            if(strtotime($fechaBuscar) == strtotime(substr($fechaAsistencia->Fecha, 0,10) ) ){
                return true;
            }
        }
        return false;
    }


    function  esDiaFeriado($fechaBuscar,$arrayFechasFeriados){
        foreach ($arrayFechasFeriados as $fechaFeriado){
            if(strtotime($fechaBuscar) == strtotime($fechaFeriado->fecha)){
                return true;
            }
        }
        return false;
    }



    function formato_fecha($arrayFecha,$tipo){
        $dias_ES = array("Lunes", "Martes", "Miercoles", "Jueves", "Viernes", "Sabado", "Domingo");
        $dias_EN = array("Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday");
        $meses_ES = array("Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");
        $meses_EN = array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
        $arrayNuevoFormato = Array();
        
        
        foreach($arrayFecha as $fecha){          
             $f_fecha = substr($fecha, 0, 10);
             $f_hora  =  substr($fecha, 11,19);
             $numeroDia = date('d', strtotime($f_fecha));
             $dia = date('l', strtotime($f_fecha));
             $mes = date('F', strtotime($f_fecha));
             $anio = date('Y', strtotime($f_fecha));
             $nombredia = str_replace($dias_EN, $dias_ES, $dia);
             $nombreMes = str_replace($meses_EN, $meses_ES, $mes);
             $fechaNuevo =  $numeroDia." de ". $nombreMes." del ".$anio;
             $horaNuevo  =  date('g:i:s A', strtotime($f_hora));
     
             
 
             if($tipo == 1){
                 $nuevoFormato = Array('fecha'=>$fechaNuevo,'hora'=>$horaNuevo);
                 array_push($arrayNuevoFormato,$nuevoFormato);
             }else if ($tipo == 2){
                 $nuevoFormato = Array('fecha'=>$fechaNuevo,'hora'=>'');
                 array_push($arrayNuevoFormato,$nuevoFormato);
             }
        }
 
        
        return $arrayNuevoFormato;
     }
}
