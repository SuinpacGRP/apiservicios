<?php

namespace App\Http\Controllers\PortalPago;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use DateTime;
use App\Cliente;
use App\Funciones;
use App\FuncionesCaja;
use App\Libs\LibNubeS3;
use App\Libs\libPDF;
use App\Libs\Wkhtmltopdf;
use Illuminate\Support\Facades\DB;
use \Illuminate\Support\Facades\Config;

class FirmaDocumentoController extends Controller
{
    
public static function firmarDocumento($idCotizacion,$cliente){

    $DatosDocumento=Funciones::ObtenValor("SELECT  ccc.Descripci_on as Concepto,
    c.id as IdCotizacion, c.FolioCotizaci_on, d.NombreORaz_onSocial as Nombre, ccc.Tiempo, 
    d.id as did, cont.id contid, 
    (SELECT a.Descripci_on FROM AreasAdministrativas a WHERE a.id=c.AreaAdministrativa  ) Area,
    COALESCE((SELECT ec.id FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),(SELECT ec.id FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN EncabezadoContabilidad ec ON(ec.Pago=cac.Pago) WHERE cac.Cotizaci_on=c.id  LIMIT 1)) as idContabilidad ,
    COALESCE((SELECT N_umeroP_oliza FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),(SELECT ec.N_umeroP_oliza FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN EncabezadoContabilidad ec ON(ec.Pago=cac.Pago) WHERE cac.Cotizaci_on=c.id  LIMIT 1)) as NumPoliza , 
    COALESCE((SELECT DATE(ec.FechaP_oliza) FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),(SELECT DATE(ec.FechaP_oliza) FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN EncabezadoContabilidad ec ON(ec.Pago=cac.Pago) WHERE cac.Cotizaci_on=c.id  LIMIT 1)) as Fechapago ,
    (	SELECT cd.Ruta
        FROM CatalogoDocumentos cd
        WHERE 
            ccc.CatalogoDocumento =cd.id  ) DocumentoRuta,
    (	SELECT cd.Nombre
        FROM CatalogoDocumentos cd
        WHERE 
            ccc.CatalogoDocumento =cd.id  ) NombreDocumento,
            x.uuid
    FROM Cotizaci_on c
    INNER JOIN XMLIngreso x ON (x.idCotizaci_on=c.id)
    INNER JOIN ConceptoAdicionalesCotizaci_on cac ON (cac.Cotizaci_on=c.id)
    INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=cac.ConceptoAdicionales)
    INNER JOIN Contribuyente cont ON (cont.id=c.Contribuyente)
    INNER JOIN DatosFiscales d ON (d.id=cont.DatosFiscales)
    WHERE
    c.id=".$idCotizacion." AND 
    ccc.CatalogoDocumento IS NOT NULL AND
    c.Cliente=".$cliente." AND
    cac.Adicional IS NULL AND
    Origen!='PAGO'" );
    $idReporte=0;

    $idTabla=$DatosDocumento->idContabilidad.$DatosDocumento->IdCotizacion;
    if($DatosDocumento->DocumentoRuta=="Padr_onCatastralConstanciaNoAdeudoOK.php"){
               /*$idTabla=ObtenValor("SELECT        
                   COALESCE((SELECT ec.id FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL LIMIT 1),(SELECT ec.id FROM ConceptoAdicionalesCotizaci_on cac INNER JOIN EncabezadoContabilidad ec ON(ec.Pago=cac.Pago) WHERE cac.Cotizaci_on=c.id  LIMIT 1)) as id FROM Cotizaci_on=".$_GET['idCotizacionDocumentos'],"id"); */
                $Tabla="ConstanciaNoAdeudoPredial";
                $docu="Padr_onCatastralConstanciaNoAdeudoOKPrueba.php";
                $idReporte=155;
    }
    else if($_GET['DocumentoOficial']=="CertificadoCatastral.php"){
       $Tabla="CertificadoCatastral";
        $document=ObtenValor("SELECT
		(SELECT ec.id FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL ) idContabilidad
	FROM Cotizaci_on c
	INNER JOIN XMLIngreso x ON (x.idCotizaci_on=c.id)
	INNER JOIN ConceptoAdicionalesCotizaci_on cac ON (cac.Cotizaci_on=c.id)
	INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=cac.ConceptoAdicionales)
	INNER JOIN Contribuyente cont ON (cont.id=c.Contribuyente)
	INNER JOIN DatosFiscales d ON (d.id=cont.DatosFiscales)
	WHERE
	c.id=".$_GET['idCotizacionDocumentos']." AND 
	ccc.CatalogoDocumento IS NOT NULL AND
	c.Cliente=".$idCliente." AND
	cac.Adicional IS NULL AND
	Origen!='PAGO'" );
       #$idTabla=$document['idContabilidad'];
       $docu=$_GET['DocumentoOficial'];
       $idReporte=150;
    }
    else if($_GET['DocumentoOficial']=="ConstanciaDeInscripcionPC.php"){
       $Tabla="ConstanciaDeInscripcion";
        $document=ObtenValor("SELECT
		(SELECT ec.id FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL ) idContabilidad
	FROM Cotizaci_on c
	INNER JOIN XMLIngreso x ON (x.idCotizaci_on=c.id)
	INNER JOIN ConceptoAdicionalesCotizaci_on cac ON (cac.Cotizaci_on=c.id)
	INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=cac.ConceptoAdicionales)
	INNER JOIN Contribuyente cont ON (cont.id=c.Contribuyente)
	INNER JOIN DatosFiscales d ON (d.id=cont.DatosFiscales)
	WHERE
	c.id=".$_GET['idCotizacionDocumentos']." AND 
	ccc.CatalogoDocumento IS NOT NULL AND
	c.Cliente=".$idCliente." AND
	cac.Adicional IS NULL AND
	Origen!='PAGO'" );
       #$idTabla=$document['idContabilidad'];
       $docu=$_GET['DocumentoOficial'];
       $idReporte=151;
    }else if($_GET['DocumentoOficial']=="ConstanciaDeNoPropiedad.php"){
       $Tabla="ConstanciaDeNoPropiedad";
        $document=ObtenValor("SELECT
		(SELECT ec.id FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL ) idContabilidad
	FROM Cotizaci_on c
	INNER JOIN XMLIngreso x ON (x.idCotizaci_on=c.id)
	INNER JOIN ConceptoAdicionalesCotizaci_on cac ON (cac.Cotizaci_on=c.id)
	INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=cac.ConceptoAdicionales)
	INNER JOIN Contribuyente cont ON (cont.id=c.Contribuyente)
	INNER JOIN DatosFiscales d ON (d.id=cont.DatosFiscales)
	WHERE
	c.id=".$_GET['idCotizacionDocumentos']." AND 
	ccc.CatalogoDocumento IS NOT NULL AND
	c.Cliente=".$idCliente." AND
	cac.Adicional IS NULL AND
	Origen!='PAGO'" );
       #$idTabla=$document['idContabilidad'];
       $docu=$_GET['DocumentoOficial'];
       $idReporte=152;
    }
    
    else if($_GET['DocumentoOficial']=="DeslindeCatastralFirma.php"){
        $Tabla="DeslindeCatastral";
       $document=ObtenValor("SELECT
		(SELECT ec.id FROM EncabezadoContabilidad ec WHERE ec.Cotizaci_on=c.id AND ec.Pago IS NOT NULL ) idContabilidad
	FROM Cotizaci_on c
	INNER JOIN XMLIngreso x ON (x.idCotizaci_on=c.id)
	INNER JOIN ConceptoAdicionalesCotizaci_on cac ON (cac.Cotizaci_on=c.id)
	INNER JOIN ConceptoCobroCaja ccc ON (ccc.id=cac.ConceptoAdicionales)
	INNER JOIN Contribuyente cont ON (cont.id=c.Contribuyente)
	INNER JOIN DatosFiscales d ON (d.id=cont.DatosFiscales)
	WHERE
	c.id=".$_GET['idCotizacionDocumentos']." AND 
	ccc.CatalogoDocumento IS NOT NULL AND
	c.Cliente=".$idCliente." AND
	cac.Adicional IS NULL AND
	Origen!='PAGO'" );
        #$idTabla=$document['idContabilidad'];
        $docu=$_GET['DocumentoOficial'];
        $idReporte=149;
    }elseif($_GET['DocumentoOficial']=="ConstanciaUsuariosIrregularesZofemat.php"){
        #$idTabla=ObtenValor("SELECT id FROM EncabezadoContabilidad WHERE Pago IS NULL AND Cotizaci_on=".$_GET['idCotizacionDocumentos'],"id");
        $Tabla="ConstanciaUsuariosIrregularesZofemat";
        $docu="ConstanciaUsuariosIrregularesZofemat.php";
    }elseif($_GET['DocumentoOficial']=="ConstanciaPermisosTransitoriosZofemat.php"){
        #$idTabla=ObtenValor("SELECT id FROM EncabezadoContabilidad WHERE Pago IS NULL AND Cotizaci_on=".$_GET['idCotizacionDocumentos'],"id");
        $Tabla="ConstanciaPermisosTransitoriosZofemat";
        $docu="ConstanciaPermisosTransitoriosZofemat.php";
    }elseif($_GET['DocumentoOficial']=="ConstanciaConcesionariosZofemat.php"){
        #$idTabla=ObtenValor("SELECT id FROM EncabezadoContabilidad WHERE Pago IS NULL AND Cotizaci_on=".$_GET['idCotizacionDocumentos'],"id");
        $Tabla="ConstanciaConcesionariosZofemat";
        $docu="ConstanciaConcesionariosZofemat.php";
    }elseif($_GET['DocumentoOficial']=="Padr_onCatastralConstanciaNoAdeudoZofemat.php"){
        #$idTabla=ObtenValor("SELECT id FROM EncabezadoContabilidad WHERE Pago IS NULL AND Cotizaci_on=".$_GET['idCotizacionDocumentos'],"id");
        $Tabla="ConstanciaNoAdeudoZofemat";
        $docu="Padr_onCatastralConstanciaNoAdeudoZofemat.php";
        
    }elseif($_GET['DocumentoOficial']=="ReporteConstanciaDeUsoDeSuelo.php"){
        #$idTabla=ObtenValor("SELECT id FROM EncabezadoContabilidad WHERE Pago IS NULL AND Cotizaci_on=".$_GET['idCotizacionDocumentos'],"id");
        $Tabla="ReporteConstanciaDeUsoDeSuelo";
        $docu="ReporteConstanciaDeUsoDeSuelo.php";
    }elseif($_GET['DocumentoOficial']=="ReporteConstanciaDeNumeroOficial.php"){
        #$idTabla=ObtenValor("SELECT id FROM EncabezadoContabilidad WHERE Pago IS NULL AND Cotizaci_on=".$_GET['idCotizacionDocumentos'],"id");
        $Tabla="ReporteConstanciaDeNumeroOficial";
        $docu="ReporteConstanciaDeNumeroOficial.php";
    }elseif($_GET['DocumentoOficial']=="ReporteLicenciadeConstruccion.php"){
       # $idTabla=ObtenValor("SELECT id FROM EncabezadoContabilidad WHERE Pago IS NULL AND Cotizaci_on=".$_GET['idCotizacionDocumentos'],"id");
        $Tabla="ReporteLicenciadeConstruccion";
        $docu="ReporteLicenciadeConstruccion.php";
    }elseif($_GET['DocumentoOficial']=="ReporteLicenciaDeOcupacionYUso.php"){
       # $idTabla=ObtenValor("SELECT id FROM EncabezadoContabilidad WHERE Pago IS NULL AND Cotizaci_on=".$_GET['idCotizacionDocumentos'],"id");
        $Tabla="ReporteLicenciaDeOcupacionYUso";
        $docu="ReporteLicenciaDeOcupacionYUso.php";
    }elseif($_GET['DocumentoOficial']=="Reporte_Forma3DCC.php"){
       # $idTabla=ObtenValor("SELECT id FROM EncabezadoContabilidad WHERE Pago IS NULL AND Cotizaci_on=".$_GET['idCotizacionDocumentos'],"id");
        $idReporte=296;
        $Tabla="Reporte_Forma3DCC";
        $docu="Reporte_Forma3DCC.php";
    }elseif($_GET['DocumentoOficial']=="AlineamientoCatastral.php"){
       # $idTabla=ObtenValor("SELECT id FROM EncabezadoContabilidad WHERE Pago IS NULL AND Cotizaci_on=".$_GET['idCotizacionDocumentos'],"id");
        $Tabla="PlanoAlineamiento";
        $docu="AlineamientoCatastral.php";
    }else  if($_GET['DocumentoOficial']=="ConstanciaNoAdeudoAguaOPD.php"){
                $Tabla="ConstanciaNoAdeudoAgua";
                $docu="ConstanciaNoAdeudoAguaOPD.php";
    }else if($_GET['DocumentoOficial']=="ConstanciaInexistenciaOPD.php"){
                $Tabla="InexistenciaOPD";
                $docu="ConstanciaInexistenciaOPD.php";
    }else if($_GET['DocumentoOficial']=="Padr_onAguaPotableContrato.php"){
                $Tabla="ContratoAguaPotable";
                $docu="Padr_onAguaPotableContrato.php";
    }
	else if($_GET['DocumentoOficial']=="DocumentoOficialCertificadoMedico.php"){
                $Tabla="CertificadoMedico";
                $docu="DocumentoOficialCertificadoMedico.php";
    }
       
    
       
        
    $idDocFirma =Funciones::ObtenValor("SELECT id FROM DocumentoParaFirma WHERE idOrigen=".$idTabla." and Origen='".$Tabla."'", "id");

    $status=false;
    $Resultado = DB::SELECT("SELECT
        Reporte_Firmante.id
        FROM PuestoEmpleado
                INNER JOIN Persona ON PuestoEmpleado.Empleado = Persona.id
                INNER JOIN PuestoFirmante ON PuestoEmpleado.PuestoFirmante = PuestoFirmante.id
                INNER JOIN Reporte_Firmante ON PuestoEmpleado.id = Reporte_Firmante.PuestoFirmante
                INNER JOIN Reporte ON Reporte_Firmante.Reporte = Reporte.id 
        WHERE Reporte_Firmante.Cliente = $cliente and
                Reporte.id=$idReporte 
                AND Reporte_Firmante.Estatus = 1 
        ORDER BY Reporte_Firmante.Orden ASC;");
    
    if(count($Resultado) != 0){
        foreach(  $Resultado as $Res){
            $QueryInsert = sprintf(
                'INSERT INTO FirmaEmpleadoDocumento (id, EmpleadoDocumento, Documento) VALUES (%s, %s, %s);',
                Funciones::GetSQLValueString(NULL, 'int'),
                Funciones::GetSQLValueString($Res->id, 'int'),
                Funciones::GetSQLValueString($idDocFirma, 'int')
            );
            
            if( DB::insert($QueryInsert) )
                $status=true;
            else
                $status=false;
        }

    }
    if($status){
        return $Firmado = FirmaDocumentoController::FirmaDocumento2($cliente,$idDocFirma);
    }
}


public static function FirmaDocumento2($cliente,$idDocumento, $Force = false){
   
           
   $s3 = new LibNubeS3($cliente);
    /*Se obtienen los datos del archivo*/
    $Archivo =Funciones::ObtenValor('SELECT * FROM DocumentoParaFirma WHERE id = ' . $idDocumento);
    
            //print_r($Archivo);
    $ArchivoOrigen = Funciones::ObtenValor('SELECT * FROM CelaRepositorio WHERE idRepositorio = ' . $Archivo->DocumentoOriginal);
    //$ArchivoDestino = ObtenValor('SELECT * FROM CelaRepositorio WHERE idRepositorio = ' . $Archivo['Documento']);

    /*!!!Sacar el archivo original y el destino del repositorio con S3¡¡¡*/

    /*Se obtienen el archivo original desde el repositorio*/
    $ArchivoOrigenTemp =  'https://suinpac.piacza.com.mx/OriginalFileToSign_'.rand(1000,9999).'.pdf';
    return $s3->ObtenerArchivoRepo($ArchivoOrigen->Ruta, $ArchivoOrigenTemp, $ArchivoOrigen->Size);
            //InsertaValor("Bitacora",array("Origen"=>"FuncionesFirma","Bitacora"=>"ArchivoOrigenTemp: ".$ArchivoOrigenTemp));
    if($s3->ObtenerArchivoRepo($ArchivoOrigen->Ruta, $ArchivoOrigenTemp, $ArchivoOrigen->Size)){
                /*Se obtienen los firmantes del doucumento*/
                $Firmantes = array();
                $Query = sprintf('SELECT f.id, l.Descripci_on as LeyendaFirmante, p.NombreDelCargo as Cargo, p1.Nombre, p1.ApellidoPaterno, p1.ApellidoMaterno, p1.KeyPEM, p1.CerPEM, f.FechaFirma
                    FROM FirmaEmpleadoDocumento f
                            INNER JOIN Reporte_Firmante rf ON ( f.EmpleadoDocumento = rf.id  )
                                    INNER JOIN LeyendaFirmante l ON ( rf.LeyendaFirmante = l.id  )
                                    INNER JOIN PuestoEmpleado p ON ( rf.PuestoFirmante = p.id  )
                                            INNER JOIN Persona p1 ON ( p.Empleado = p1.id  )
                            INNER JOIN DocumentoParaFirma d ON ( f.Documento = d.id  )
                    WHERE
                        d.id = %s
                    GROUP BY
                            f.EmpleadoDocumento
                    ORDER BY
                        rf.Orden DESC',
                    GetSQLValueString($idDocumento, 'int')
                );
               // InsertaValor("Bitacora",array("Origen"=>"FuncionesFirma","Bitacora"=>"FirmantesDocumento: ".$Query));
        $Result =DB::select($Query);
        if($Result){
            $Error = array(); 
            $Status = false;
            foreach( $Result as $Recod){
                if($Recod->CerPEM != '' && $Recod->KeyPEM != ''){
                    $Firmantes[] = array(
                        'id' => $Recod->id,
                        'Nombre' => ucfirst(strtolower($Recod->Nombre)) . ' ' . ucfirst(strtolower($Recod->ApellidoPaterno)) . ' ' . ucfirst(strtolower($Recod->ApellidoMaterno)) ,
                        'Leyenda' => $Recod->LeyendaFirmante,
                        'Cargo' => base64_encode($Recod->Cargo),
                        'CerPEM' => $Recod->CerPEM,
                        'KeyPEM' => $Recod->KeyPEM,
                        'FechaFirma' => $Recod->FechaFirma,
                        'Location' => $request->root(),
                        'TempDir' =>  env('DIR_TEMP')   
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
                                             ///
                    InsertaValor("Bitacora", array("Origen"=>$_SESSION['CELA_CveUsuario'.$_SESSION['CELA_Aleatorio']],"Bitacora"=> json_encode($Firmantes)));
                    DB::table('Bitacora')->insert([
                        ['Origen' =>23,
                        'Bitacora'=>json_encode($Firmantes)]
                    ]);
                    $FirmaDoc->SetLocation(Funciones::ObtenValor("SELECT Nombre FROM Cliente WHERE id=".$cliente, 'Nombre'));
                    $FirmaDoc->SetInitY(-($FirmaDoc->GetStepOn() * count($Firmantes)));
                    $FirmaDoc->SetTempDir(env('DIR_TEMP') );
                    $FirmaDoc->AppendDocument('repositorio/configuracion/HojaDeFirmas.pdf');
                    $FirmaDoc->SignDoc();

                    /*Se cuentan los firmates para actualizar el estado del documento*/
                    $Doc = Funciones::ObtenValor('SELECT * FROM DocumentoParaFirma WHERE id = ' .$idDocumento . ';');
                    $Query = sprintf('SELECT COUNT(*) as total
                    FROM Reporte_Firmante rf
                    WHERE rf.Cliente = %s AND
                        rf.Estatus = %s AND
                        rf.Reporte = %s
                    ORDER BY rf.Orden ASC;', $Doc->Cliente, 1, $Doc->Reporte);

                    $TotalSign = Funciones::ObtenValor($Query, 'total');
                                            $TotalFirmadas=0;
                                            $Faltantes = $TotalSign-count($Firmantes);
                    if(count($Firmantes) == $TotalSign){
                                                /*Se actualiza el Estado del documento en la base de datos*/
                                                $QueryUpdate = 'UPDATE DocumentoParaFirma SET Estado = 1 WHERE id =' . $idDocumento . ';';
                                                DB::update($QueryUpdate);
                                                //$QueryUpdate = 'DELETE FROM  DocumentoParaFirma WHERE id =' . $idDocumento . ';';
                                              
                                                
                                                $FirmaTexto=" Firmado.";
                                                $TotalFirmadas=1;
                    }
                                            else
                                               $FirmaTexto=" Falta(n) ".$Faltantes." firma(s).";
                                           

                    /*Se elimina el archivo destino aterior del repositorio*/
                    //$s3->EliminarArchivoRepo($ArchivoDestino['Ruta']);

                    /*Se actualiza el archivo destino del repositorio*/
                    $Size = filesize($ArchivoDestinoTemp);
                    $rutaarchivo = $s3->MueveArchivoRepositorio($ArchivoDestinoTemp, $ArchivoOrigen->NombreOriginal);
                                            
                                            if($rutaarchivo!=""){
                                               $QueryUpdateFile = 'UPDATE CelaRepositorio SET Ruta = "' . $rutaarchivo . '", Size = ' . $Size . ' WHERE idRepositorio = ' . $Archivo['Documento'] . ';';
                                               $resultado= ActualizaValor("CelaRepositorio", array("Ruta"=>$rutaarchivo,"Size"=>$Size,"Descripci_on"=>$ArchivoOrigen['Descripci_on'].$FirmaTexto,"NombreOriginal"=>"F_".$ArchivoOrigen['NombreOriginal'],"Reciente"=>1), 'idRepositorio = ' . $Archivo['Documento']);
                                               $resultado=DB::update( $QueryUpdateFile);
                                               if($resultado){
                        /*Se devuelve estatus ok y el archivo firmado*/
                        return array(
                            'Status' => 'OK',
                            'Archivo' => $rutaarchivo,
                                                            'TotalFirmadas'=>$TotalFirmadas,
                                                            'idRepositorio'=>$Archivo['Documento']
                        );
                                                }
                                                else{
                                                    return array(
                                                        'Status' => 'Error',
                                                        'Error' => 'No se pudo actualizar el archivo a firmar. ' . $Conexion->error,
                                                        'idRepositorio'=>$Archivo['Documento']
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

public static function SignPDF($File, $Save, $Signatories, $Options = array()){
return "hola";
}
 
}
