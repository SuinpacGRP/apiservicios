<?php
namespace App;

use JWTAuth;
use Exception;

class FuncionesServidor {

     /**
     * !Retorna un json con el nuevo token y el resultado obtenido psado por parametro.
     * @param string $token
     * @return \Illuminate\Http\JsonResponse
     */
    
    public static function ConfiguracionServer(){
        $dir = __DIR__; //Directorio donde se ejecuta la libreria "lib/"
    //se ejecuta en el directorio raiz, por lo que necesito ir dos directorios atras, para alcanzar el archivo de la configuracion.
    return json_decode(file_get_contents($dir.'/../../servidor.cfg'), true);
    }

    public static function serverCredenciales(){
        $servidor = FuncionesServidor::ConfiguracionServer();
        return response()->json([
            'Usuario'   => $servidor['Usuario'],
            'Contra'    => $servidor['Contra'],
        ]);
    }
}
?>