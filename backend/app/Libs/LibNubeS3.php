<?php

namespace App\Libs;

use App\Modelos\Cliente;

include_once app_path().'/Libs/LibProcesaCadenas.php';

// Es necesario Instalada la herramienta s3cmd versions 2.01 o superior en el servidor
class LibNubeS3 {
    private $prefijo;
    private $cubeta;
    public  $host;
    private $s3cfgbase;
    private $origen;
    private $medida;
    private $id;

    function __construct($idCliente = '') {
        /*if($idcliente == '')
            $idcliente = $_SESSION['CELA_Cliente'.$_SESSION['CELA_Aleatorio']];*/
        
        $Resultado = Cliente::select('id', 's3accesskey', 's3secretkey', 's3tokenkey', 'cubeta', 'host', 'origen')
            ->where('id', $idCliente)
            ->first();
        #return $Resultado;
        $this->id = $Resultado->id;

        $this->origen = $Resultado->origen; //0 - local, 1 - repositorio servidor remoto, 2 - s3 repositorio
        if($Resultado->origen == 1){ //Verificar que este disponible el repositorio remoto...
            $archivo = fopen ("http://repo.piacza.com.mx/", "r");
            if(!$archivo)
                $this->origen = 0;
        }

        $this->prefijo = "s3://";
        $this->cubeta = $Resultado->cubeta;
        $this->host = $Resultado->host;
        $Conf_Opcional = "";

        if($Resultado->s3tokenkey != '' or $Resultado->s3tokenkey != Null)
            $Conf_Opcional = "--access_token=".$Resultado->s3tokenkey;

        if($Resultado->host != '' or $Resultado->host!=Null)
            $Conf_Opcional .=  " --host=".$Resultado->host;

        $this->s3cfgbase = $Conf_Opcional." --access_key=".$Resultado->s3accesskey." --secret_key=".$Resultado->s3secretkey." --host-bucket=".$Resultado->cubeta;
    }

    public function OrigenRepo(){
        return $this->origen;
    }

    private function ClearNameString($String){
        $String = trim($String);

        $String = str_replace(
            array('á', 'à', 'ä', 'â', 'ª', 'Á', 'À', 'Â', 'Ä','Ã', 'Ã¡', '&Aacute;', '&aacute;'),
            'a',
            $String
        );

        $String = str_replace(
            array('é', 'è', 'ë', 'ê', 'É', 'È', 'Ê', 'Ë', 'Ã‰', 'Ã©', '&Eacute;', '&eacute;'),
            'e',
            $String
        );

        $String = str_replace(
            array('í', 'ì', 'ï', 'î', 'Í', 'Ì', 'Ï', 'Î', 'Ã', 'Ã­', '&Iacute;', '&iacute;'),
            'i',
            $String
        );

        $String = str_replace(
            array('ó', 'ò', 'ö', 'ô', 'Ó', 'Ò', 'Ö', 'Ô', 'Ã“', 'Ã³', '&Oacute;', '&oacute;'),
            'o',
            $String
        );

        $string = str_replace(
            array('ú', 'ù', 'ü', 'û', 'Ú', 'Ù', 'Û', 'Ü', 'Ãš', 'Ãº', '&Uacute;', '&uacute;'),
            'u',
            $String
        );

        $String = str_replace(
            array('ñ', 'Ñ', 'ç', 'Ç', '&Ntilde', '&ntilde;'),
            'n',
            $String
        );

        //Esta parte se encarga de eliminar cualquier caracter extraño
        $String = str_replace(
            array("\\", "¨", "º", "~",
                "#", "@", "|", "!",
                "·", "$", "&", "/",
                "(", ")", "?", "¡",
                "¿", "[", "^", "`", "]",
                "+", "}", "{", "¨", "´",
                ">", "<", ";", ",", ":", " "),
            '_',
            $String
        );

        return trim($String);
    }

    public function ObtenerArchivoRepo($RutaArchivo, $RutaLocal, $MedidaArchivo = 0){
        
        if($this->origen == 0){
            #return server_path().$RutaArchivo;
            if(!file_exists(server_path().$RutaArchivo)){ //! No Existe El Archivo;

                $resultado = shell_exec("/usr/bin/s3cmd ".$this->s3cfgbase." --skip-existing get ". $this->prefijo.$this->cubeta."/".$RutaArchivo." ".$this->info->server_path().$RutaLocal);
                //print $resultado;
                $correcto = strpos($resultado, strval($MedidaArchivo)); //Busca en la cadena el tama?o del archivo, que se  le pasa como paramatro, para verificar que el archivo este correcto
                echo $correcto;

                if($correcto !== false)
                    return 0; //1 correcto, 0 error
                else
                    return 1;//1 archivo encontrado S3
            }else{ //! Existe El Archivo
                return copy("https://suinpac.com/".$RutaArchivo,$RutaLocal);
               
            }
        }
        else if($this->origen==1){
            #return $this->CopiaArchivoRemoto($RutaArchivo, server_path().$RutaLocal);
        }
        else if($this->origen==2){
            /*$bytes = (int) $MedidaArchivo;
            console_log("/usr/bin/s3cmd ".$this->s3cfgbase." --force get ".  $this->prefijo.$this->cubeta."/".$RutaArchivo." ".server_path().$RutaLocal);
            $resultado = shell_exec("/usr/bin/s3cmd ".$this->s3cfgbase." --force get ".  $this->prefijo.$this->cubeta."/".$RutaArchivo." ".server_path().$RutaLocal);
            console_log($resultado);
            
            $correcto = strpos($resultado,strval($bytes));//Busca en la cadena el tama?o del archivo, que se  le pasa como paramatro, para verificar que el archivo este correcto
            
            return $correcto;*/
        }
    }

    public function MueveArchivoRepositorio($archtmp, $archnuevo, $ruta="", $codificado=1, $copy=false){
        $salida = "";
        $partes_ruta = pathinfo($archnuevo);

        if($copy===false)
            $archnuevo=trim(base64_encode($partes_ruta['filename']."_".rand(10,99).".".$partes_ruta['extension']));
        else
            $archnuevo=trim(base64_encode($partes_ruta['filename']."_".rand(100,999)));

        if($this->origen == 0)
            $salida = $this->MueveArchivoRepo($archtmp, $archnuevo, $ruta, $copy);
        else if($this->origen == 1)
            $salida = $this->MueveArchivoRepoRemoto($archtmp, $archnuevo, $ruta);

        return trim($salida);
    }

    public function EliminarArchivoRepo($RutaArchivo){
        $salida="";
        if($RutaArchivo!=""){
            if(strpos($RutaArchivo,"s3://")!==false){
                EliminaValor2("CelaRepositorio"," Ruta='".$RutaArchivo."'");
                $salida= exec("/usr/bin/s3cmd ".$this->s3cfgbase." rm ".$RutaArchivo);
            }
            else if(strpos($RutaArchivo,"http://")!==false){
                EliminaValor2("CelaRepositorio"," Ruta='".$RutaArchivo."'");
                $salida = $this->EliminaArchivoServidor($RutaArchivo);
            }
            else{
                EliminaValor2("CelaRepositorio"," Ruta='".$RutaArchivo."'");
                $salida=unlink(server_path().$RutaArchivo);
            }
        }
        return $salida;
    }
}