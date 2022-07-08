<?php

namespace App\Libs;

//! Description: Clase utilizada para el manejo y firma de documentos PDF

class SignPDF{
    /*Definición de constantes*/
    const URL_QR      = 'http://v.servicioenlinea.mx/firma.php?id=';
    /*Setter al directorio que quiera edgar*/
    const TempDir     = 'repositorio/temporal/';//repositorio/temp/
    const Location    = ''; //http://suinpac.piacza.com.mx
    const InitX       = 50;
    const InitY       = -300;
    const StepOn      = 80;
    const Height      = 70;
    const Width       = 450;
    const HashEncript = 'sha256';

    /*Configuraciones por default para las etiquetas que se muestran en la firma*/
    const FormatDate      = 'd/m/Y H:i:s';
    const ConfigDate      = 'Fecha y Hora: ';
    const ConfigName      = 'Firmado por: ';
    const ConfigReason    = 'Motivo: ';
    const ConfigHash      = 'Firma Digital (RSH sha256): ';
    const ConfigLocation  = 'Origen: ';
    const ConfigReturn    = '';
    const ConfigSeparator = '---------------------------------------------------------------------------------------------------------------------------------------';

    /*Archivo que se firmará y el original*/
    private $File;
    private $Save;
    /*Archivo setasign que se va a firmar*/
    private $Document;

    /*Lector de PDF's*/
    private $Reader;
    /*Escritor  de PDF's*/
    private $Writer;
    /*Escritor temporal de PDF's*/
    private $TempWriter;

    /*Numero de paginas totales del archivo*/
    private $TotalPages;

    /*
    * Arreglo de firmantes
    * array(
    *      string id: Identificador del firmante para uso general.
    *      string Nombre: Nombre completo del firmante
    *      string Leyenda: Leyenda del motivo por el cual se firma el doucmento
    *      string Cargo: Cargo o puesto que ocupa
    *      string CerPEM: Cadena de caracteres del archivo cer.pem
    *      string KeyPEM: Cadena de caracteres del archivo key.pem
    * )
    */
    private $Signatories;

    /*Ruta del directorio temporal*/
    private $TempDir;
    private $URL_QR;
    private $Location;
    private $InitX;
    private $InitY;
    private $StepOn;
    private $HashEncript;

    /*Configuraciones por default para las etiquetas que se muestran en la firma*/
    private $FormatDate;
    private $ConfigDate;
    private $ConfigName;
    private $ConfigReason;
    private $ConfigLocation;
    private $ConfigHash;
    private $ConfigReturn;
    private $ConfigSeparator;


    public function __construct($File, $Save, $Signatories, $Options = array()){
        if(!file_exists($File)){
            throw new ErrorException('The file to sign does not exist.', 0);
        }

        /*Se establece la ruta del arhivo a firmar*/
        $this->File = $File;
        /*Se establece la ruta del arhivo destio dirmado*/
        $this->Save = $Save;

        /*SE establece el arreglo de firmates*/
        $this->Signatories = $Signatories;

        if (array_key_exists('ConfigLocation', $Options)) {
            $this->SetConfigLocation($Options['ConfigLocation']);
        } else {
            $this->SetConfigLocation(self::ConfigLocation);
        }
        if (array_key_exists('ConfigReason', $Options)) {
            $this->SetConfigReason($Options['ConfigReason']);
        } else {
            $this->SetConfigReason(self::ConfigReason);
        }
        if (array_key_exists('ConfigName', $Options)) {
            $this->SetConfigName($Options['ConfigName']);
        } else {
            $this->SetConfigName(self::ConfigName);
        }
        if (array_key_exists('ConfigDate', $Options)) {
            $this->SetConfigDate($Options['ConfigDate']);
        } else {
            $this->SetConfigDate(self::ConfigDate);
        }
        if (array_key_exists('StepOn', $Options)) {
            $this->SetStepOn($Options['StepOn']);
        } else {
            $this->SetStepOn(self::StepOn);
        }
        if (array_key_exists('InitY', $Options)) {
            $this->SetInitY($Options['InitY']);
        } else {
            $this->SetInitY(self::InitY);
        }
        if (array_key_exists('InitX', $Options)) {
            $this->SetInitX($Options['InitX']);
        } else {
            $this->SetInitX(self::InitX);
        }
        if (array_key_exists('Location', $Options)) {
            $this->SetLocation($Options['Location']);
        } else {
            $this->SetLocation(self::Location);
        }
        if (array_key_exists('URL_QR', $Options)) {
            $this->SetURL_QR($Options['URL_QR']);
        } else {
            $this->SetURL_QR(self::URL_QR);
        }
        if (array_key_exists('FormatDate', $Options)) {
            $this->SetFormatDate($Options['FormatDate']);
        } else {
            $this->SetFormatDate(self::FormatDate);
        }
        if (array_key_exists('TempDir', $Options)) {
            $this->SetTempDir($Options['TempDir']);
        } else {
            $this->SetTempDir(self::TempDir);
        }
        if (array_key_exists('Width', $Options)) {
            $this->SetWidth($Options['Width']);
        } else {
            $this->SetWidth(self::Width);
        }
        if (array_key_exists('Height', $Options)) {
            $this->SetHeight($Options['Height']);
        } else {
            $this->SetHeight(self::Height);
        }
        if (array_key_exists('HashEncript', $Options)) {
            $this->SetHashEncript($Options['HashEncript']);
        } else {
            $this->SetHashEncript(self::HashEncript);
        }
        if (array_key_exists('ConfigHash', $Options)) {
            $this->SetConfigHash($Options['ConfigHash']);
        } else {
            $this->SetConfigHash(self::ConfigHash);
        }
        if (array_key_exists('ConfigReturn', $Options)) {
            $this->SetConfigReturn($Options['ConfigReturn']);
        } else {
            $this->SetConfigReturn(self::ConfigReturn);
        }
        if (array_key_exists('ConfigSeparator', $Options)) {
            $this->SetConfigSeparator($Options['ConfigSeparator']);
        } else {
            $this->SetConfigSeparator(self::ConfigSeparator);
        }

        if(!file_exists($this->TempDir)){
            mkdir($this->TempDir, 0755, true);
        }
    }

    /* Función que intenta poner la firma digital del arreglo de firmates
    * @params void
    * @return void
    **/
    public function SignDoc(){
        $TotalSing = count($this->Signatories);

        $this->Reader = new SetaPDF_Core_Reader_File($this->Save);
        /*Se crea el archivo temporal de escritura*/
        $this->TempWriter = new SetaPDF_Core_Writer_TempFile($this->TempDir);

        /*Se carga el archivo*/
        $this->Document = SetaPDF_Core_Document::load($this->Reader, $this->TempWriter);

        if($TotalSing == 1){
            /*Se firma normal con una sola firma*/
            $this->SetSign(0);
        }else{
            $i = 1;
            /*Se firma multiple*/
            for ($f = 0; $f < $TotalSing; $f++) {
                /*Si es la ultima firma, se lee del reader anterior y se manda al writer final */
                if ($i == $TotalSing) {
                    $this->Reader = new SetaPDF_Core_Reader_String($this->Writer);
                    $this->Writer = $this->TempWriter;
                } elseif ($i != 1) {
                    /*Si no es la ultima ni la primer firma, se crea el reader y el writer*/
                    $this->Reader = new SetaPDF_Core_Reader_String($this->Writer);
                    $this->Writer = new SetaPDF_Core_Writer_String();
                } else {
                    /*Si es la primer firma se lee del reader principal y se manda al writer temporal*/
                    $this->Writer = new SetaPDF_Core_Writer_String();
                }

                /*Crea una instancia del documento*/
                $this->Document = SetaPDF_Core_Document::load($this->Reader, $this->Writer);

                $this->SetSign($f);

                $i++;
            }
        }

        /*Se copia el archivo temporal al original y se elimina el temporal*/
        copy($this->TempWriter->getPath(), $this->Save);
        unlink($this->TempWriter->getPath());
    }

    /*Funcion que genera la firma del documento*/
    private function SetSign($Item){
        $PosY = $this->InitY + ($this->StepOn * $Item);
        /*Se agrega el campo de la firma*/
        SetaPDF_Signer_SignatureField::add(
            $this->Document,
            $this->Signatories[$Item]['Cargo'], /*Nombre del campo*/
            $this->TotalPages, /*Numero de la pagina donde se ponen las firmas*/
            SetaPDF_Signer_SignatureField::POSITION_LEFT_TOP, /*Posición de la firma*/
            array('x' => $this->InitX, 'y' => $PosY),
            $this->Width, /*Ancho*/
            $this->Height /*Alto*/
        );

        /*Se crea la instancia del firmante*/
        $Signer = new SetaPDF_Signer($this->Document);

        /*Se seleccionan las propiedades de la firma*/
        $Signer->setLocation($this->Location);
        $Signer->setReason($this->Signatories[$Item]['Leyenda']); /*Leyenda del firmante*/
        $Signer->setSignatureFieldName($this->Signatories[$Item]['Cargo']); /*Nombre del campo de la firma*/
        $Signer->setTimeOfSigning(date('Y-m-d H:i:s', strtotime($this->Signatories[$Item]['FechaFirma']))); /*Se selecciona la fecha y hora de la firma*/

        /*Se crea una instancia del modulo SSL*/
        $Module = new SetaPDF_Signer_Signature_Module_OpenSsl();

        /*Se pasa el cettificado y la llave privada*/
        $Module->setCertificate($this->Signatories[$Item]['CerPEM']);
        $Module->setPrivateKey(array($this->Signatories[$Item]['KeyPEM'], ''/* no password */));

        /*Se crea el objeto para mostrar la firma*/
        $VisibleSign = new SetaPDF_Signer_Signature_Appearance_Dynamic($Module);

        /*Se crea el pdf con el codigo QR*/
        $xObject = $this->CreateQR($this->URL_QR . $this->Signatories[$Item]['id']);

        /*Se genera el "HASH" de la firma*/
        $Hash = strtoupper(
            base64_encode(
                hash($this->HashEncript, file_get_contents($this->File)) .
                hash($this->HashEncript, $Module->getCertificate()) .
                hash($this->HashEncript, uniqid())
            )
        );

        /*Se deshabilita el mostrar el nombre completo del firmante*/
        $VisibleSign->setShow(
            SetaPDF_Signer_Signature_Appearance_Dynamic::CONFIG_DISTINGUISHED_NAME, false
        );

        $VisibleSign->setShowTpl(SetaPDF_Signer_Signature_Appearance_Dynamic::CONFIG_NAME, $this->ConfigName .
            ' %s  -  ' . base64_decode($this->Signatories[$Item]['Cargo'])
        );

        /*Se cambian las etiquetas que se muestran en la firma*/
        $VisibleSign->setShowTpl(
            SetaPDF_Signer_Signature_Appearance_Dynamic::CONFIG_REASON, $this->ConfigReason . ' %s'
        );

        $VisibleSign->setShowTpl(
            SetaPDF_Signer_Signature_Appearance_Dynamic::CONFIG_LOCATION, $this->ConfigLocation . ' %s'
        );

        $VisibleSign->setShowTpl(
            SetaPDF_Signer_Signature_Appearance_Dynamic::CONFIG_DATE, $this->ConfigDate . ' %s.' .
            $this->ConfigReturn .
            $this->ConfigSeparator . $this->ConfigReturn .
            $this->ConfigHash .  $this->ConfigReturn .
            $Hash . $this->ConfigReturn .
            $this->ConfigSeparator . $this->ConfigReturn
        );

        /*Formato de la fecha y hora*/
        $VisibleSign->setShowFormat(
            SetaPDF_Signer_Signature_Appearance_Dynamic::CONFIG_DATE, $this->FormatDate
        );

        /*Se aliniea todo el texto a la izquierda*/
        $VisibleSign->setTextAlign(SetaPDF_Core_Text::ALIGN_LEFT);

        /*Se crea la imagen grafica de la firma*/
        $VisibleSign->setGraphic($xObject);
        $Signer->setAppearance($VisibleSign);

        /*Se firma el documento*/
        $Signer->sign($Module);
    }

    /* Función que agrega paginas de un documento existente a el documento existente
    * @params $DocumentToAppend (string): ruta del documento del que se obtendran las paginas para agregarlas al documento
    * @return void
    **/
    public function AppendDocument($DocumentToAppend){
        if(file_exists($DocumentToAppend)){
            /*Se crea el archivo de lectura*/
            $this->Reader = new SetaPDF_Core_Reader_File($this->File);
            /*Se crea el archivo temporal de escritura*/
            $this->TempWriter = new SetaPDF_Core_Writer_TempFile($this->TempDir);

            /*Se carga el archivo el el documento*/
            $this->Document = SetaPDF_Core_Document::load($this->Reader, $this->TempWriter);

            /*Se obtiene las paginas del arhivo orignal*/
            $pagesToAppendTo = $this->Document->getCatalog()->getPages();

            /*Se obtiene el archivo que contiene la hoja de firmas*/
            $DocumentToAppend = SetaPDF_Core_Document::loadByFilename($DocumentToAppend);
            $pagesToAppend = $DocumentToAppend->getCatalog()->getPages();

            $pageCount = $pagesToAppend->count();

            $this->TotalPages = $pagesToAppendTo->count() + $pageCount;

            for($p = 1; $p <= $pageCount; $p++){
                $pageToAppend = $pagesToAppend->getPage($p);
                $pageToAppend->flattenInheritedAttributes();
                $pagesToAppendTo->append($pageToAppend);
            }

            /*Se salva los cambios*/
            $this->Document->save()->finish();

            /*Se copia el archivo temporal al original*/
            copy($this->TempWriter->getPath(), $this->Save);
            unlink($this->TempWriter->getPath());
        }else{
            /*Se lanza un error de sentencia no encontrada*/
            throw new ErrorException('The pages to add do not exist.', 0);
        }
    }

    /* Función para generar la representación grafica de la firma
    * @params $EncodeString (string): cadena de caracteres que se codificará en el qr
    * @return (Objet xObjet setaSign)
    **/
    private function CreateQR($EncodeString){
        /*---Se genera el codigo QR---*/
        $PNGTempDir = $this->TempDir . 'Signature' . rand(1000000, 9999999) . '.png';

        if(!file_exists($this->TempDir)){
            mkdir($this->TempDir, 0755, true);
        }

        /*Se genera el codigo QR*/
        QRcode::png($EncodeString, $PNGTempDir, 'H', 4, 2);
        /*Se carga el codigo QR de la firma*/
        $Image = SetaPDF_Core_Image::getByPath($PNGTempDir);
        /*Se obtiene una instancia xObject del documento*/
        $xObject = $Image->toXObject($this->Document);
        unlink($PNGTempDir);

        return $xObject;
    }

    /*Se definen las funciones Set y Get*/
    public function SetConfigLocation($ConfigLocation){
        $this->ConfigLocation = $ConfigLocation;
    }

    public function GetConfigLocation(){
        return $this->ConfigLocation;
    }

    public function SetConfigReason($ConfigReason){
        $this->ConfigReason = $ConfigReason;
    }

    public function GetConfigReason(){
        return $this->ConfigReason;
    }

    public function SetConfigName($ConfigName){
        $this->ConfigName = $ConfigName;
    }

    public function GetConfigName(){
        return $this->ConfigName;
    }

    public function SetConfigDate($ConfigDate){
        $this->ConfigDate = $ConfigDate;
    }

    /**
     * @return mixed
     */
    public function getTotalPages(){
        return $this->TotalPages;
    }

    /**
     * @param mixed $TotalPages
     */
    public function setTotalPages($TotalPages){
        $this->TotalPages = $TotalPages;
    }
    public function GetConfigDate(){
        return $this->ConfigDate;
    }

    public function SetStepOn($StepOn){
        $this->StepOn = $StepOn;
    }

    public function GetStepOn(){
        return $this->StepOn;
    }

    public function SetInitY($InitY){
        $this->InitY = $InitY;
    }

    public function GetInitY(){
        return $this->InitY;
    }

    public function SetInitX($InitX){
        $this->InitX = $InitX;
    }

    public function GetInitX(){
        return $this->InitX;
    }

    public function SetLocation($Location){
        $this->Location = $Location;
    }
    public function GetLocation(){
        return $this->Location;
    }

    public function SetURL_QR($URL_QR){
        $this->URL_QR = $URL_QR;
    }

    public function GetURL_QR(){
        return $this->URL_QR;
    }

    public function SetFormatDate($FormatDate){
        $this->FormatDate = $FormatDate;
    }

    public function GetFormatDate(){
        return $this->FormatDate;
    }

    public function SetTempDir($TempDir){
        $this->TempDir = $TempDir;
    }

    public function GetTempDir(){
        return $this->TempDir;
    }

    public function SetHeight($Height){
        $this->Height = $Height;
    }

    public function GetHeight(){
        return $this->Height;
    }

    public function SetWidth($Width){
        $this->Width = $Width;
    }

    public function GetWidth(){
        return $this->Width;
    }

    public function GetHashEncript(){
        return $this->HashEncript;
    }

    public function SetHashEncript($HashEncript){
        $this->HashEncript = $HashEncript;
    }

    public function GetConfigHash(){
        return $this->ConfigHash;
    }

    public function SetConfigHash($ConfigHash){
        $this->ConfigHash = $ConfigHash;
    }

    public function GetConfigReturn(){
        return $this->ConfigReturn;
    }
    public function SetConfigReturn($ConfigReturn){
        $this->ConfigReturn = $ConfigReturn;
    }

    public function GetConfigSeparator(){
        return $this->ConfigSeparator;
    }

    public function SetConfigSeparator($ConfigSeparator){
        $this->ConfigSeparator = $ConfigSeparator;
    }
}
