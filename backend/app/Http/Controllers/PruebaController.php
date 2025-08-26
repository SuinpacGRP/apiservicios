<?php

namespace App\Http\Controllers;

use App\Modelos\DatosFiscales;
use App\Modelos\Persona;
use Illuminate\Http\Request;
#use Tymon\JWTAuth\PayloadFactory;
use JWTFactory;
use JWTAuth;
use App\Funciones;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Config;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Storage;
use App\User;
use App\Libs\Wkhtmltopdf;

class PruebaController extends Controller
{

    public function __construct()
    {
        #$this->middleware( 'jwt', ['except' => ['getToken']] );
        #$this->request = $request;
        #return $request;
        #Funciones::selecionarBase($db);
    }

    public function datosSEPIF(Request $request)
    {
        $request->validate([
            'Cliente' =>'required',
            'RFC' => 'required'
        ]);

        $cliente = $request->Cliente;
        $rfc = $request->RFC;

        Funciones::selecionarBase($cliente);

        $consulta = "SELECT
                p.Curp as curp,
                df.Calle as calle,
                p.Nombre as nombre,
                dfc.Calle as Ecalle,
                SUBSTR(df.RFC,1,10) as rfc,
                df.Colonia as coloniaLocalidad,
                df.Localidad as ciudadLocalidad,
                df.C_odigoPostal as codigoPostal,
                dfc.Colonia as EcoloniaLocalidad,
                dfc.Localidad as EciudadLocalidad,
                a.Descripci_on as areaAdscripcion,
                dfc.C_odigoPostal as EcodigoPostal,
                p.ApellidoPaterno as primerApellido,
                df.N_umeroExterior as numeroExterior,
                df.N_umeroInterior as numeroInterior,
                p.ApellidoMaterno as segundoApellido,
                ct.Descripci_on as nombreEntePublico,
                pc.Clase as nivelEmpleoCargoComision,
                c.Descripci_on as empleoCargoComision,
                dfc.N_umeroExterior as EnumeroExterior,
                dfc.N_umeroInterior as EnumeroInterior,
                df.EntidadFederativa as estadoProvincia,
                p.Tel_efonoParticular as celularPersonal,
                dfc.EntidadFederativa as EestadoProvincia,
                p.Curp as curp,p.Tel_efonoCelular as casa,
                df.CorreoElectr_onico as correoElectronico,
                SUBSTR(df.Municipio,3,3) as municipioAlcaldia,
                SUBSTR(df.RFC,11, LENGTH(df.RFC)) as homoClave,
                SUBSTR(dfc.Municipio,3,3) as EmunicipioAlcaldia,
                DATE_FORMAT(p.FechaInicio,'%Y-%m-%d') as fechaTomaPosesion,
                UPPER((SELECT m.Nombre FROM Municipio m WHERE m.id=df.Municipio)) as MunicipioNombre,
                trim(REPLACE(REPLACE(REPLACE(REPLACE(p.Tel_efonoCelular,' ',''),'-',''),')',''),'(','')) as casa,
                trim(REPLACE(REPLACE(REPLACE(REPLACE(ct.Tel_efonoInstitucioinal,' ',''),'-',''),')',''),'(','')) as Tel_efonoInstitucioinal,
                trim(REPLACE(REPLACE(REPLACE(REPLACE(p.Tel_efonoParticular,' ',''),'-',''),')',''),'(','')) as celularPersonal,
                ( SELECT
                    FLOOR (
                        ( SELECT SUM(dp.Total) FROM DetalladoPreN_omina dp INNER JOIN ConceptoN_omina cn on(cn.id=dp.ConceptoN_omina) WHERE dp.EncabezadoPreN_omina=ep.id and cn.TipoConceptoN_omina=1)
                        -
                        (SELECT SUM(dp.Total) FROM DetalladoPreN_omina dp INNER JOIN ConceptoN_omina cn on(cn.id=dp.ConceptoN_omina)  WHERE dp.EncabezadoPreN_omina=ep.id and cn.TipoConceptoN_omina=2 and cn.id not in(29,36,26,21,25,85,269,273) )
                    ) as TotalReal
                    FROM CuentaPorPagar c
                        INNER JOIN EncabezadoPreN_omina ep ON(ep.CuentaPorPagar=c.id)
                        INNER JOIN Persona p1 ON(p1.id=ep.Persona)
                        INNER JOIN XMLEgreso x ON(x.id=ep.XMLN_omina)
                    WHERE x.uuid is not null and  c.Tipo=4 and c.Momento=10  and p1.id=p.id ORDER BY c.Fecha DESC limit 1 
                ) as SueldoBase
            FROM Persona p
                INNER JOIN DatosFiscales df ON(df.id=p.DatosFiscales)
                INNER JOIN PuestoEmpleado pe ON(pe.Empleado=p.id)
                INNER JOIN PlantillaN_ominaCliente pc ON(pc.id=pe.PlantillaN_ominaCliente)
                INNER JOIN AreasAdministrativas a ON(a.id=pc.AreaAdministrativa)
                INNER JOIN Cat_alogoPlazaN_omina c ON(c.id=pc.Cat_alogoPlazaN_omina)
                INNER JOIN Cliente ct ON(ct.id=p.Cliente)
                INNER JOIN DatosFiscalesCliente dfc ON(dfc.id=ct.DatosFiscales)
            WHERE pc.Cliente = $cliente
                AND p.Cliente = $cliente
                AND pe.Estatus = 1
                AND df.RFC = '$rfc'; ";

            $datos = DB::select( $consulta );

            return response()->json([
                'success' => $datos
            ], 200);
    }

    public function addLog()
    {
        logger()->error('Error en el log...');

        return response()->json([
            'success' => 'true'
        ], 200);
    }

    public function upload(Request $request){
        /*$request->validate([
            'photo' => 'required'
        ]);*/

        /*
        $archivo = $request->file('photo');
        $name = $archivo->getClientOriginalName();
        $size = $archivo->getSize();
        return "$name => $size";
        */
        #return $request;

        $files = $request->file('photo');
        #var_dump($files);
        #var_dump($files->getClientOriginalName());
        #return $files;

        $salida = "";
        foreach($files as $file){
            var_dump($file);
            $salida .= $file->getClientOriginalName();
        }


        return "Salida: ".$salida;

        //! Gurdar archivo en el repositorio
        $salida = $request->file('photo')->store(
            'Firmas35', 'repositorio'
        );

        //! Gurdar archivo en el repositorio temporal
        /*$salida = $request->file('photo')->store(
            '', 'temporal'
        );*/

        return 'Profile updated! => ' . $salida;
    }

    public function getPersona(Request $request){
        /*$cliente = $request->Cliente;
        $id = $request->Id;
        #return $request;
        Funciones::selecionarBase($cliente);

        #$user = Persona::find($id)->datosFiscales;
        #$user = DatosFiscales::find($id)->persona;
        $user = DatosFiscales::where('RFC', $id)->first()->persona;*/

        $DIR_RESOURCE = 'recursos/';
        $DIR_IMG = 'imagenes/';
        Funciones::selecionarBase(29);

        $consultaCliente = "select *,Descripci_on,
        (SELECT l.Nombre FROM DatosFiscalesCliente d INNER JOIN Localidad l ON (l.id=d.Localidad) WHERE d.id=Cliente.DatosFiscales  ) Localidad,
        (SELECT l.Nombre FROM DatosFiscalesCliente d INNER JOIN EntidadFederativa l ON (l.id=d.EntidadFederativa) WHERE d.id=Cliente.DatosFiscales  ) Entidad, (select Ruta from CelaRepositorioC where CelaRepositorioC.idRepositorio=Cliente.Logotipo) as Logo from Cliente where id=29";

        $Cliente = DB::select($consultaCliente);

        #return asset(Storage::url($DIR_IMG.'capazlogo.png'));
        $array = [app_path(),
            public_path(),
            storage_path(),
            base_path(),
            server_path(),
            asset(Storage::url($DIR_IMG.'capazlogo.png'))
        ];
        return $array;

        $miHTML= '<html><head>
                     <meta charset="utf-8">
                <meta http-equiv="X-UA-Compatible" content="IE=edge">
                <meta name="viewport" content="width=device-width, initial-scale=1">
               
               <link href="'. (Storage::exists($DIR_RESOURCE.'bootstrap.min.css')?asset(Storage::url($DIR_RESOURCE.'bootstrap.min.css')):'') .'" rel="stylesheet">
               
                </head>
                 <div class="container"> 
                    <div class="row">  
                    
                        <table class="table" border="0" width="100%" colspan="12">
                        
                                <tr>
                                    <td colspan="12">
                                       
                                        <table border="0" width="100%">
                                           
                                            <tr>
                                                <td colspan="4" width="20%"><img src="'.asset($Cliente[0]->Logo).'" alt="Logo del cliente"  style="height: 80px;"></td>
                                                <td colspan="4" width="80%"><div class="text-center"><b><h2>'.$Cliente[0]->Descripci_on.'</h2></b></div></td>
                                            </tr>  
                                        </table>
                                    </td>
                            </tr>
                        </table>
                        </div>
                    </div>
                                            
                </html>';

        include( app_path() . '/Libs/Wkhtmltopdf.php' );
        try{
            $DirectorioTemporal = 'repositorio/temporal/';
            $NombreArchivo = "Reporte_Forma3DCC_".uniqid()."123_pdf";
            $wkhtmltopdf = new Wkhtmltopdf(array('path' =>$DirectorioTemporal, 'lowquality'=>true, 'FooterStyleLeft'=>'Grupo Piacza - SUINPAC Contable', 'FooterStyleCenter'=>'Pag. [page] de [toPage]'));
            $wkhtmltopdf->setHtml($miHTML);
            $wkhtmltopdf->output(Wkhtmltopdf::MODE_SAVE, $NombreArchivo);

            return $DirectorioTemporal.$NombreArchivo;
        }catch (Exception $e) {
            //echo "Hubo un error al generar el PDF: " . $e->getMessage();
            return $e->getMessage();
        }
    }

    public function customToken(){
        #$customClaims = ['foo' => 'bar', 'baz' => 'bob'];
        #$payload = JWTFactory::make($customClaims);
        #$payload = JWTFactory::sub(123)->aud('foo')->foo(['bar' => 'baz'])->make();
        #$token = JWTAuth::encode($payload);

        // grab some user
        $user = User::first();
        #$user = User::find(3064);
        $customClaims = ['nombre' => 'Demo', 'rfc' => 'SEGM7707036T7'];
        #$customClaims = ['foo' => 'bar', 'baz' => 'bob'];
        $token = JWTAuth::fromUser($user, $customClaims);

        #$credentials = ['Usuario' => 'jose.manuel', 'password' => 'josemha'];
        #$token = auth()->claims(['foo' => 'bar'])->attempt($credentials);

        #$token = JWTAuth::customClaims($customClaims)->fromUser($user);

        #$customClaims = ['foo' => 'bar', 'baz' => 'bob'];
        #$payload = JWTFactory::make($customClaims);
        #$token = JWTAuth::encode($payload);

        return response()->json([
           'token' => $token,
            'token_type' => 'bearer',
        ]);
    }

    public function getCustomToken(){
        #$datos = JWTAuth::parseToken()->toUser();
        #JWTAuth::setToken('foo.bar.baz');
        #$datos = JWTAuth::parseToken()->authenticate();
        $customClaims = ['nombre' => 'Demo', 'rfc' => 'SEGM7707036T7'];
        #$customClaims = ['foo' => 'bar', 'baz' => 'bob'];
        #JWTAuth::factory()->setDefaultClaims($customClaims);
        JWTAuth::factory()->addClaims($customClaims);
        #JWTAuth::factory()->addClaim("Email", "ejemplo@gmail.com");
        #JWTAuth::factory()->customClaims($customClaims);
        #auth()->setClaims($customClaims);
        #JWTFactory::setPersistentClaims($customClaims);
        #JWTAuth::setPersistentClaims($customClaims);
        #$payload = auth()->payload();

        #return $payload['nombre'];
        #return response()->json( compact('datos') );
        JWTAuth::factory()->setTTL(5);
        return auth()->refresh();
    }

    public function getUserAuth(){
        $user = JWTAuth::parseToken()->authenticate();

        return response()->json( $user );
    }

    public function PruebaDB(){
        Funciones::selecionarBase("suinpac_35");

        $datos = DB::select("SELECT COUNT(id) AS Registros FROM Padr_onLicencia");
        
        return $datos;
    }

    public function obtenerDatosDBRemote(Request $request){
        /*
        $db = General::where('id', $cliente)->value('NombreDB');
        
        Config::set('database.connections.mysql.database', $db); 
        DB::purge('mysql');
        */

        $db = "piacza_recauda";
        
        #Config::set('database.connections.mysql.database', $db);
        #DB::purge('mysql');

        Config::set('database.default', 'mysqlSR');

        #return DB::connection()->getDataBaseName();
    
        dd( DB::connection()->getPdo() );

        #$DatosFiscales = DB::select('select * from transacciones');
    
        #$users = DB::connection('mysqlServicioRemoto')->select('SELECT COUNT(*) FROM transacciones');
        $users = DB::select('SELECT COUNT(*) FROM transacciones');

        return $users;
        /*
        return response()->json([
            'success' => '1',
            'datosFiscales'=>$DatosFiscales
        ], 200);*/
    }

    public function PruebaSubir(Request $request){
        $archivo = $request->file('archivo');
        $valor = $archivo->getClientOriginalName();

        return $valor;
        #Funciones::selecionarBase("suinpac_35");
        #$ruta = $_SERVER["REQUEST_SCHEME"]."://".$_SERVER["HTTP_HOST"];
        #$ruta = storage_path('app');
        #$ruta = Input::file('archivo')->getClientOriginalName();
        #$ruta = Input::file('archivo')->getSize();;

        #return __DIR__;
        return $ruta;
    }
    
    public function datos(){
        Funciones::selecionarBase("29");
        #return "2Suinpac_29";
        $datos = DB::select("SELECT COUNT(id AS Registros FROM Padr_onLicencia");
        return $datos;
    }

    public function getToken(){
        $data = [
            'Cliente' => 'Taxco'
        ];
        JWTAuth::factory()->setTTL(-1);
        #$customClaims = JWTFactory::customClaims($data);
        $payload = JWTFactory::sub('Checador')->aud('datos')->datos($data)->make();
        $token = JWTAuth::encode($payload);
        
        return $token;
    }

    public function refreshToken(){
        $data = [
            'Cliente' => 'Taxco'
        ];

        JWTAuth::factory()->setRefreshTTL(3);
        #$customClaims = JWTFactory::customClaims($data);
        $payload = JWTFactory::sub('Checador')->aud('datos')->datos($data)->make();
        $token = JWTAuth::encode($payload);
        
        return $token;
    }

    public function obtenerBoletoMaestroSuinpac(Request  $request){
        $cliente=$request->Cliente;
        $id=$request->id;

        $url = 'https://irvindev.suinpac.dev/BoletoMaestroVistaPreviaLinea.php';
        $dataForPost = array(
            'Cliente'=> [
                "id"=>$id,
                "cliente"=>$cliente,
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
            'success' => $result,
        ], 200);
    }

    /*Route::get('storage/{filename}', function ($filename) { 
        $path = storage_path('public/' . $filename); 
        if (!File::exists($path)) { 
            abort(404); 
        }
        
        $file = File::get($path); 
        $type = File::mimeType($path); 
        $response = Response::make($file, 200); 
        $response->header("Content-Type", $type); 
        return $response;
    });*/

}
//! Tu dirección IP es 201.160.220.159
