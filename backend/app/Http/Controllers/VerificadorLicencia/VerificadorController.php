<?php
namespace App\Http\Controllers\VerificadorLicencia;
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
use Hamcrest\Core\HasToString;
use Illuminate\Support\Facades\DB;
use \Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use LDAP\Result;

class VerificadorController extends Controller{
   

    public function getDatos(Request $request){  

        // print $request;
              
        $cliente = $request->Cliente; 
        $folio = $request->Folio;
        $curp = $request->Curp;

        Funciones::selecionarBase($cliente);
        $Respuesta = '';
        $ConsultaLicencia = Funciones::ObtenValor("SELECT l.id,l.FolioLicencia AS Folio , (SELECT CONCAT_WS(' - ', Tipo, NOMBRE) AS Tipo FROM TipoLicenciaManejo WHERE  id = l.TipoLicencia) AS Tipo,
            c.Nombres, CONCAT_WS(' ', c.ApellidoPaterno, c.ApellidoMaterno) AS Apellidos, c.Curp, l.FechaFinal AS Vence, l.FechaInicial, d.Extra_Donante AS Donante,
            d.Extra_TipoSangre AS TipoSangre, CONCAT('Calle ', c.Calle_c, ' Nº Int ', COALESCE(c.N_umeroInterior_c, 'S/N'), ' Colonia ', c.Colonia_c, '. C.P. ', C_odigoPostal_c, ' ',
            (SELECT Nombre FROM Municipio where id = c.Municipio_c), ', ', (SELECT Nombre FROM EntidadFederativa WHERE id = c.EntidadFederativa_c) ) AS Direccion,
            TIMESTAMPDIFF(YEAR, l.FechaInicial, l.FechaFinal) AS Vigencia, l.FotoFrente, d.Extra_ContactoEmergNombre AS NombreContacto,
            d.Extra_ContactoEmergTelefono AS TelefonoContacto, d.Extra_ContactoEmergDireccion AS DireccionContacto
        FROM LicenciaConducir l 
            INNER JOIN Contribuyente c ON (c.id = l.Contribuyente)
            INNER JOIN DatosFiscales d ON (d.id = c.DatosFiscales)
        WHERE l.FolioLicencia = $folio AND c.Curp = '$curp'");       
         
        $Respuesta = $ConsultaLicencia;
        
        return response()->json([
            'data' => $Respuesta
        ],200);
    }
    
}