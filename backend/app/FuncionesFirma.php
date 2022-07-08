<?php

namespace App;

use App\Modelos\Cliente;
use App\Modelos\CelaRepositorio;
use App\Modelos\ReporteFirmante;
use App\Modelos\DocumentoParaFirma;
use App\Modelos\FirmaEmpleadoDocumento;

#require_once(__DIR__.'/../FirmaDocumentos/SetaPDFSigner/library/SetaPDF/Autoload.php');
#require_once(__DIR__.'/../FirmaDocumentos/SignPDF.php');
#require_once(__DIR__.'/../lib/phpqrcode.php');

//setlocale(LC_TIME,"es_MX.UTF-8");
class FuncionesFirma {

    public static function ObtenerDocumentoFirmado($idTabla, $Tabla, $RepoS3, $Reporte = 0, $Cliente){
        global $idDocFirma;
        $RutaTemporal = ""; 

        // Si existe algun Documento pendiente de firmar.
        $Documentos = DocumentoParaFirma::select('Documento', 'DocumentoOriginal', 'Estado')
            ->where([
                ['idOrigen', $idTabla],
                ['Origen',   $Tabla],
                ['Cliente',  $Cliente]
            ])
            ->orderByDesc('id')
            ->first();

        #return $Documentos;

        if(!$Documentos) { // Es documento nuevo para firmar
            return "Es documento nuevo para firmar";
            /*   
            $DocOriginal = CelaRepositorio::select('idRepositorio', 'NombreOriginal', 'Ruta', 'Size')
                ->where([
                    ['idTabla', $idTabla],
                    ['Tabla', $Tabla],
                    ['Estado', 1],
                    ['Reciente', 1]
                ])
                ->first();
            
            if($DocOriginal){ //Existe el Documento original
                $DocumentoFirma = FuncionesFirma::ParaFirma2($DocOriginal->idRepositorio, $Tabla, $idTabla, $Reporte, $Cliente, $RepoS3);
                
                $idFirmadoAnterior = CelaRepositorio::where([
                        ['idTabla', $idTabla],
                        ['Tabla', $Tabla],
                        ['Estado', 1],
                        ['Reciente', 0]
                    ])
                    ->orderDesc('idRepositorio')
                    ->value('idRepositorio');

                $idOriginalAnterior = CelaRepositorio::where([
                        ['idTabla', $idTabla],
                        ['Tabla', $Tabla],
                        ['Estado', 1],
                        ['Reciente', 0]
                    ])
                    ->orderAsc('idRepositorio')
                    ->value('idRepositorio');
                    
                if($DocumentoFirma['Status'] == 'OK'){ 
                    if($idFirmadoAnterior)
                        FuncionesFirma::HistorialArchivos($idFirmadoAnterior, $DocumentoFirma['idRepositorio']);
                    
                    if($idOriginalAnterior)
                        FuncionesFirma::HistorialArchivos($idOriginalAnterior, $DocOriginal->idRepositorio);
                    
                    // SE obtiene una ruta temporal de archivo
                    $RutaTemporal = env('DIR_TEMP')."Cotizacion_Pago_Firmado_".date("His").".pdf";

                    if(!$RepoS3->ObtenerArchivoRepo($DocumentoFirma['Archivo'],$RutaTemporal)) //Obtenemos el archivo firmado del repo
                        $RutaTemporal = "";
                }else{
                    $Documentos = DocumentoParaFirma::select('Documento', 'DocumentoOriginal', 'Estado')
                        ->where([
                            ['idOrigen', $idTabla],
                            ['Origen', $Tabla],
                            ['Cliente', $Cliente]
                        ])
                        ->orderByDesc('id')
                        ->first();
                    
                    if($Documentos){
                        if($idFirmadoAnterior)
                            FuncionesFirma::HistorialArchivos($idFirmadoAnterior, $Documentos->Documento);

                        if($idOriginalAnterior)
                            FuncionesFirma::HistorialArchivos($idOriginalAnterior, $Documentos->DocumentoOriginal);

                        $RutaTemporal = env('DIR_TEMP').$DocOriginal->NombreOriginal;

                        if( !($RepoS3->ObtenerArchivoRepo($DocOriginal->Ruta, $RutaTemporal, number_format($DocOriginal->Size, 0))) )
                            $RutaTemporal = "";
                    }
                }
            }*/
        }else{ //Ya existe en la lista para firmar.
            $DocOriginal = CelaRepositorio::select('idRepositorio', 'NombreOriginal', 'Ruta', 'Size')
                ->where('idRepositorio', $Documentos->DocumentoOriginal)
                ->first();
            #return $DocOriginal;
            if($Documentos->Estado == 0){ // Si todavia no esta firmado.
                /*$DocumentoFirma = FuncionesFirma::ParaFirma2($DocOriginal->idRepositorio, $Tabla, $idTabla, $Reporte, $Cliente, $RepoS3);
                //InsertaValor("Bitacora", array("origen"=>"FuncionesFirma","Bitacora"=>$Tabla." ".$idTabla));
                if($DocumentoFirma['Status'] == 'OK'){ //Si se Firmo Correctamente
                    //SE obtiene una ruta temporal de archivo
                    $RutaTemporal = env('DIR_TEMP')."Cotizacion_Pago_Firmado_".date("His").".pdf";
                    if(!$RepoS3->ObtenerArchivoRepo($DocumentoFirma['Archivo'], $RutaTemporal)) //Obtenemos el archivo firmado del repo
                        $RutaTemporal = "";
                }else{
                    $RutaTemporal = env('DIR_TEMP').$DocOriginal->NombreOriginal;

                    if(!($RepoS3->ObtenerArchivoRepo($DocOriginal->Ruta, $RutaTemporal, number_format($DocOriginal->Size, 0))))
                        $RutaTemporal = ""; 
                }*/

                return $DocOriginal->Ruta;
                #return "Si todavia no esta firmado";
            }else{ //Ya esta Firmado el Documento Recuperamos el archivo para mostrarlo.
                $DocFirmado = CelaRepositorio::select('NombreOriginal', 'Ruta', 'Size')
                    ->where('idRepositorio', $Documentos->Documento)
                    ->first();
                
                $RutaTemporal = env('DIR_TEMP').$DocFirmado->NombreOriginal;
                #return $DocFirmado->Ruta;
                #return $RepoS3->ObtenerArchivoRepo($DocFirmado->Ruta, $RutaTemporal, number_format($DocFirmado->Size, 0));
                if(!( $RepoS3->ObtenerArchivoRepo($DocFirmado->Ruta, $RutaTemporal, number_format($DocFirmado->Size, 0)) ))
                    $RutaTemporal = "";
            }
        }
        return $RutaTemporal; //Regresa la ruta del documento.
    }

    public static function ParaFirma2($idRepositorio, $Origen, $idOrigen, $Reporte, $Cliente, $repo){
        $Documentos = DocumentoParaFirma::where([
                ['idOrigen', $idOrigen],
                ['Origen', $Origen],
                ['Cliente', $Cliente],
            ])
            ->orderByDesc('id')
            ->first();
        #/home/piacza/web/suinpac.piacza.com.mx/public_html/
        if($Documentos) {
            /*Se guarda el archivo dummy en el repositorio y la base de datos*/
            $control = fopen(server_path().env('DIR_TEMP')."noexiste.pdf","w");
            #$control = fopen(RaizDocumento().env('DIR_TEMP')."noexiste.pdf","w");
            fclose($control);

            $Result = new CelaRepositorio();

            #$Result->idRepositorio = ;
            $Result->Tabla            = $Origen;
            $Result->idTabla          = $idOrigen;
            $Result->Ruta             = env('DIR_TEMP')."noexiste.pdf";
            $Result->Descripci_on     = 'Documento InExistente se remplaza al ser firmado el original';
            $Result->idUsuario        = $idUsuario;
            $Result->FechaDeCreaci_on = date('Y-m-d H:i:s');
            $Result->Estado           = 1;
            $Result->Reciente         = 1;
            $Result->NombreOriginal   = "noexiste.pdf";
            $Result->Size             = 0;

            $Result->save();
            $idArchivo = $Result->id;
            /*Se registra el documento para firma*/
            /*se registran los firmates que firman en automatico*/

            $ResultInsert = new DocumentoParaFirma();

            $ResultInsert->Documento = $idArchivo;
            $ResultInsert->Cliente = $Cliente;
            $ResultInsert->idOrigen = $idOrigen;
            $ResultInsert->Origen = $Origen;
            $ResultInsert->DocumentoOriginal = $idRepositorio;
            $ResultInsert->Reporte = $Reporte;

            $ResultInsert->save();

            /*!!!Modificar consulta para sacar el firmado en automatico de la Persona¡¡¡*/
            if($ResultInsert){
                $idDocumento = $ResultInsert->id;
            
                /*Se obtiene los firmates del documento a esta consulta se agrego AND rf.FirmaAutomatico=true para documentos*/  
                $Result = ReporteFirmante::from('Reporte_Firmante as rf')
                    ->select('rf.id')
                    ->join('PuestoEmpleado as p', 'rf.PuestoFirmante', 'p.id')
                    ->join('Persona as p1', 'p.Empleado', 'p1.id')
                    ->where([
                        ['rf.Cliente', $Cliente],
                        ['rf.Estatus', 1],
                        ['p1.FirmaAutomatico', true],
                        ['rf.FirmaAutomatico', true],
                        ['rf.Reporte', $Reporte],
                    ])
                    ->orderBy('rf.Orden', 'asc')
                    ->get();

                $Status = true;
                $Erros = array();
                $Count = 0;

                if($Result){
                    $Status = true;
                    $Firmado=array(
                        'Status' => 'Error',
                        'Error'  => 'No se pudo actualizar el archivo a firmar'
                    );

                    foreach($Result as $Record){
                        /*se registran los firmates que firman en automatico*/
                        $ResultInsert = new FirmaEmpleadoDocumento();
                        
                        #$QueryInsert->id = ;
                        $ResultInsert->EmpleadoDocumento = $Record->id;
                        $ResultInsert->Documento = $idDocumento;

                        $ResultInsert->save();

                        if(!$ResultInsert){
                                /*Se guardan los errores*/
                                $Status = false;
                                $Erros[] = $Conexion->error;
                        }
                        /*try {
                            //tu código
                        }catch (\Illuminate\Database\QueryException $e){
                            //tu código
                        }*/
                        $Count++;
                    }

                    if($Status) 
                        /*Se invoca la función de firma de documento*/
                        $Firmado = FirmaDocumento2($idDocumento);

                    return $Firmado;
                }
            }
        }
        else{
            //print "aqui";
            /*Se obtiene los firmates del documento*/ 
            $Status = true;
            $Erros = array();

            $Result = ReporteFirmante::from('Reporte_Firmante as rf')
                ->select('rf.id')
                ->join('PuestoEmpleado as p', 'rf.PuestoFirmante', 'p.id')
                ->join('Persona as p1', 'p.Empleado', 'p1.id')
                ->where([
                    ['rf.Cliente', $Cliente],
                    ['rf.Estatus', 1],
                    ['p1.FirmaAutomatico', true],
                    ['rf.Reporte', $Reporte],
                ])
                ->orderBy('rf.Orden', 'asc')
                ->get();

            //InsertaValor("Bitacora", array("origen"=>"FuncionesFirma - Para Firma2","Bitacora"=>addslashes($Query)));
            $Count = 0;
            if($Result){
                $Status = true;
                $Firmado=array(
                    'Status' => 'Error',
                    'Error' => 'No se pudo actualizar el archivo a firmar'
                );

                foreach($Result as $Record){
                #while($Record = $Result->fetch_assoc()){
                    /*se registran los firmates que firman en automatico*/
                    
                    $ResultInsert = new FirmaEmpleadoDocumento();
                        
                    #$QueryInsert->id = ;
                    $ResultInsert->EmpleadoDocumento = $Record->id;
                    $ResultInsert->Documento = $idDocumento;

                    $ResultInsert->save();

                    $QueryInsert = sprintf('INSERT INTO FirmaEmpleadoDocumento (id, EmpleadoDocumento, Documento) VALUES (%s, %s, %s);',
                        GetSQLValueString(NULL, 'int'),
                        GetSQLValueString($Record['id'], 'int'),
                        GetSQLValueString($Documentos->id, 'int')
                    );

                    $ResultInsert = $Conexion->query( $QueryInsert );
                   
                    if(!$ResultInsert){
                        /* Se guardan los errores */
                        $Status = false;
                        $Erros[] = $Conexion->error;
                    }
                    $Count++;
                }

                if($Status)
                    /*Se invoca la función de firma de documento*/
                    $Firmado = FirmaDocumento2($Documentos->id);

                return $Firmado;
                
            }
        }
    }

    public static function FirmaDocumento2($idDocumento, $Force = false, $Cliente){
		$s3 = new NubeS3;
        /*Se obtienen los datos del archivo*/
        $Archivo = DocumentoParaFirma::where('id', $idDocumento)->first();
        
        $ArchivoOrigen = CelaRepositorio::where('idRepositorio', $Archivo->DocumentoOriginal)->first();
		/*!!!Sacar el archivo original y el destino del repositorio con S3¡¡¡*/

        /*Se obtienen el archivo original desde el repositorio*/
		$ArchivoOrigenTemp = env('DIR_TEMP') . 'OriginalFileToSign_'.rand(1000,9999).'.pdf';
               
		if($s3->ObtenerArchivoRepo($ArchivoOrigen->Ruta, $ArchivoOrigenTemp, $ArchivoOrigen->Size)){
                    
            /*Se obtienen los firmantes del doucumento*/
            $Result = FirmaEmpleadoDocumento::from('FirmaEmpleadoDocumento as f')
                ->select('f.id', 'l.Descripci_on as LeyendaFirmante', 'p.NombreDelCargo as Cargo', 'p1.Nombre', 'p1.ApellidoPaterno', 'p1.ApellidoMaterno', 'p1.KeyPEM', 'p1.CerPEM', 'f.FechaFirma')
                ->join('Reporte_Firmante as rf', 'f.EmpleadoDocumento', 'rf.id')
                ->join('LeyendaFirmante as l', 'rf.LeyendaFirmante', 'l.id')
                ->join('PuestoEmpleado as p', 'rf.PuestoFirmante', 'p.id')
                ->join('Persona as p1', 'p.Empleado', 'p1.id')
                ->join('DocumentoParaFirma as d', 'f.Documento', 'd.id')
                ->where('d.id', $idDocumento)
                ->groupBY('f.EmpleadoDocumento')
                ->orderByDesc('rf.Orden')
                ->get();
            
			if($Result){
				$Error = array(); 
                $Status = false;
                foreach($Result as $Recod){
				#while($Recod = $Result->fetch_assoc()){
					if($Recod->CerPEM != '' && $Recod->KeyPEM != ''){
						$Firmantes[] = array(
							'id'         => $Recod->id,
							'Nombre'     => ucfirst(strtolower($Recod->Nombre)) . ' ' . ucfirst(strtolower($Recod->ApellidoPaterno)) . ' ' . ucfirst(strtolower($Recod->ApellidoMaterno)) ,
							'Leyenda'    => $Recod->LeyendaFirmante,
							'Cargo'      => base64_encode($Recod->Cargo),
							'CerPEM'     => $Recod->CerPEM,
							'KeyPEM'     => $Recod->KeyPEM,
							'FechaFirma' => $Recod->FechaFirma,
                            'Location'   => $request->root(),
                            'TempDir'    => env('DIR_TEMP')   
						);
                        $Status = true;
					}else{
						/*Se guarda el error de sellos*/
						$Status = false;
						$Error[] =
                        $Recod->Nombre . ' ' . $Recod->ApellidoPaterno . ' ' . $Recod->ApellidoMaterno . ': El firmate no cuenta con sellos';
					}
				}

				if($Status){
					/*Se Invoca la clase del firmado*/
					try{
						/*Se genera el archivo destino temporal*/
						$ArchivoDestinoTemp = env('DIR_TEMP') . '/DestinyFileToSign_'.rand(1000,9999).'.pdf';
						$FirmaDoc = new SignPDF($ArchivoOrigenTemp, $ArchivoDestinoTemp, $Firmantes);

						//$Step = intval(600/(count($Firmantes) + 1));
						//$FirmaDoc->SetStepOn($Step);
						//$FirmaDoc->SetHeight($Step - 10);
                        
                        //InsertaValor("Bitacora", array("Origen"=>$_SESSION['CELA_CveUsuario'.$_SESSION['CELA_Aleatorio']],"Bitacora"=> json_encode($Firmantes)));
                        $FirmaDoc->SetLocation( Cliente::where('id', $Cliente)->value('Nombre') );
						$FirmaDoc->SetInitY(-($FirmaDoc->GetStepOn() * count($Firmantes)));
						$FirmaDoc->SetTempDir( env('DIR_TEMP') );
						$FirmaDoc->AppendDocument('repositorio/configuracion/HojaDeFirmas.pdf');
						$FirmaDoc->SignDoc();

                        /*Se cuentan los firmates para actualizar el estado del documento*/
                        $Doc = DocumentoParaFirma::where('id', $idDocumento)->first();

                        $TotalSign = ReporteFirmante::from('Reporte_Firmante as fr')
                            ->selectRaw('COUNT(*) as total')
                            ->where([
                                ['rf.Cliente', $Doc->Cliente],
                                ['rf.Estatus', 1],
                                ['rf.Reporte', $Doc->Reporte],
                            ])
                            ->orderBy('rf.Orden', 'asc')
                            ->value('total');

                        $TotalFirmadas = 0;
                        $Faltantes = $TotalSign-count($Firmantes);

						if(count($Firmantes) == $TotalSign){
                            /*Se actualiza el Estado del documento en la base de datos*/
                            $QueryUpdate = DocumentoParaFirma::find($idDocumento);

                            $QueryUpdate->Estado = 1;

                            $QueryUpdate->save();
                            
                            $Conexion->query($QueryUpdate);
                            
                            $FirmaTexto = " Firmado.";
                            $TotalFirmadas = 1;
						}
                        else
                            $FirmaTexto = " Falta(n) ".$Faltantes." firma(s).";
                        
						/*Se elimina el archivo destino aterior del repositorio*/
						//$s3->EliminarArchivoRepo($ArchivoDestino['Ruta']);

						/*Se actualiza el archivo destino del repositorio*/
						$Size = filesize($ArchivoDestinoTemp);
						$rutaarchivo = $s3->MueveArchivoRepositorio($ArchivoDestinoTemp, $ArchivoOrigen->NombreOriginal);
                                                
                        if($rutaarchivo!=""){
                            //$QueryUpdateFile = 'UPDATE CelaRepositorio SET Ruta = "' . $rutaarchivo . '", Size = ' . $Size . ' WHERE idRepositorio = ' . $Archivo['Documento'] . ';';
                            $resultado = FuncionesFirma::ActualizaValor("CelaRepositorio", array("Ruta"=>$rutaarchivo,"Size"=>$Size,"Descripci_on"=>$ArchivoOrigen->Descripci_on.$FirmaTexto,"NombreOriginal"=>"F_".$ArchivoOrigen->NombreOriginal,"Reciente"=>1), 'idRepositorio = ' . $Archivo->Documento);
                            if($resultado){
                                /*Se devuelve estatus ok y el archivo firmado*/
                                return array(
                                    'Status'        => 'OK',
                                    'Archivo'       => $rutaarchivo,
                                    'TotalFirmadas' => $TotalFirmadas,
                                    'idRepositorio' => $Archivo->Documento
                                );
                            }
                            else{
                                return array(
                                    'Status'        => 'Error',
                                    'Error'         => 'No se pudo actualizar el archivo a firmar. ' . $Conexion->error,
                                    'idRepositorio' => $Archivo->Documento
                                );
                            }   
                        }
					}catch (Exception $e){
						print_r($e);
					}
				}else{
					return array(
						'Status' => 'Error',
						'Error' => implode(';', $Error)
					);
				}
			}else{
				return array(
					'Status' => 'Error',
					'Error' => $Conexion->error
				);
			}
		}else{
			return array(
				'Status' => 'Error',
				'Error' => 'No se pudo obtener el archivo orignial del repositorio'
			);
		}
	}

    public static function HistorialArchivos($idRepoAnterior, $idRepoNuevo){
        FuncionesFirma::ActualizaValor(
            "CelaRepositorio", 
            array("idTabla"=>$idRepoNuevo), 
            "Tabla='CelaRepositorio' and idTabla=".$idRepoAnterior
        );
        
        FuncionesFirma::ActualizaValor(
            "CelaRepositorio", 
            array("idTabla"=>$idRepoNuevo, "Tabla"=>"CelaRepositorio"), 
            "idRepositorio=".$idRepoAnterior
        ); 
    }

    public static function ActualizaValor($Tabla, $Valores, $Condicion, $depurar = 0){
        $SQL = "UPDATE ".$Tabla." SET ";

        foreach($Valores as $key => $valor)
            $SQL .= $key."='".$valor."',";
        
        $SQL = substr($SQL,0,strlen($SQL)-1)." where ".$Condicion.";";

        if($depurar == 1)
            print $SQL;

        return DB::select($SQL);
        #return ($Conexion->query($SQL));
    }
}