<?php

require_once 'client_code_generator.php';
require_once 'assessments.php';
require_once 'share_resources.php';
require_once 'Clinics.php';
require_once 'handle_images.php';

class MyPhpWordTemplateProcessor extends \PhpOffice\PhpWord\TemplateProcessor
{
    function __construct( $resourcename )
    {
        parent::__construct( $resourcename );
    }

    protected function fixBrokenMacros($documentPart)
    {
        $fixedDocumentPart = $documentPart;

        $fixedDocumentPart = preg_replace_callback(
            /*'|\$[^{]*\{[^}]*\}|U'*/'|\$(?><.*?>)*\{.*?\}|',
            function ($match) {
                $fix = strip_tags($match[0]);
                
                $isAssessment = False;
                foreach (getAssessmentTypes() as $assmt){
                    $assmt = '${'.$assmt;
                    if(substr($fix, 0,strlen($assmt)) == $assmt){
                        $isAssessment = True;
                    }
                }
                
                if( substr($fix,0,6) == '${date' ||
                    substr($fix,0,8) == '${client' ||
                    substr($fix,0,7) == '${staff' ||
                    substr($fix,0,8) == '${clinic' ||
                    substr($fix,0,9) == '${section' ||
                    $isAssessment)
                {
                    return( $fix );
                } else {
                    return( $match[0] );
                }
            },
            $fixedDocumentPart
        );

        return $fixedDocumentPart;
    }
    
    public function insertSection($tag, $section){
        $regex1 = '/<(w:[^ >]+)[^>\/]*?>(?=.*?<\/\1>)/';
        $regex2 = '/<(w:[^ >]+)[^>\/]*?>/';
        $tempDocument = strstr($this->tempDocumentMainPart, $tag, true);
        preg_match_all($regex1, $tempDocument, $matches1);
        preg_match_all($regex2, $tempDocument, $matches2);
        if($matches1 && $matches2){
            $ra = $this->arrayExclude($matches2[0], $matches1[0]);
            for ($i = 0; $i < count($ra); $i++) {
                if($ra[$i] == "<w:body>"){
                    //We dont want to close the body
                    continue;
                }
                preg_match('/w:[^ >]+/', $ra[$i], $match);
                $section = "</".$match[0].">".$section.$ra[$i];
            }
            $this->setValue($tag, $section);
        }
    }
    
    private function arrayExclude($array1, $array2) {
        $output = array();
        foreach($array1 as $match) {
            $break = false;
            foreach($array2 as $key=>$compare) {
                if ($match == $compare) {
                    unset($array2[$key]);
                    $break = true;
                    break;
                }
            }
            if($break) {
                continue;
            }
            else {
                array_push($output, $match);
            }
        }
        return $output;
    }
    
    /**
     * Get the xml between the body tags. for injection into another document.
     * Use in conjunction with insertSection($tag,$section) for proper injection into document.
     * 
     * @return String containing the xml which lies between the body tags of the document
     */
    public function getSection(){
        preg_match('/(?<=<w:body>).*(?=<\/w:body>)/', $this->tempDocumentMainPart, $matches);
        if($matches){
            return $matches[0];
        }
        return "";
    }
    
}

class template_filler {

    //Variable to cache constants
    private static $constants = NULL;
    
    private $oApp;
    private $oPeopleDB;
    private $tnrs;

    private $kfrClient = null;
    private $kfrClinic = null;
    private $kfrStaff = null;

    private $kClient = 0;
    private $kStaff = 0;

    /** Array of people ids for use with data tags
     * @var array
     */
    private $data = array();
    
    /**
     * Assessments to include in files downloaded through this template filler
     * Since this is defined in the constructor any sections included will also have access to this.
     * This means we can have sections which report on assessments and they will filled with the correct information
     */
    private $assessments;
    
    // Constants for dealing with different types of resources
    /**
     * Default resource type
     * The filled file is sent to user like normal
     */
    public const STANDALONE_RESOURCE = 1;
    /**
     * The resource being filled is actually a part of another resource.
     * The filled file should not be sent to the user as it is not complete
     */
    public const RESOURCE_SECTION = 2;
    /**
     * The resource being filled is part of a batch of resources.
     * The filled file should not be sent to the user as there may be more to follow.
     * It will be sent with the other files in a zip
     */
    public const RESOURCE_GROUP = 3;
    
    public function __construct( SEEDAppSessionAccount $oApp, array $assessments = array(), array $data = array() )
    {
        $this->oApp = $oApp;
        $this->assessments = $assessments;
        $this->data = $data;
        $this->oPeople = new People( $oApp );
        $this->oPeopleDB = new PeopleDB( $oApp );
        $this->tnrs = new TagNameResolutionService($oApp->kfdb);
        
        if(self::$constants == NULL){
            $refl = new ReflectionClass(template_filler::class);
            self::$constants = $refl->getConstants();
        }
        
    }

    private function isResourceTypeValid($resourceType):bool{
        return in_array($resourceType, self::$constants);
    }
    
    /** Replace tags in a resource with their corresponding data values
     * @param String $resourcename - Path of resource to replace tags in
     * @param $resourceType - type of resource that is being filled, must be one of the class constants and effects the file handling
     */
    public function fill_resource($resourcename, $resourceType = self::STANDALONE_RESOURCE)
    {
        
        if(!$this->isResourceTypeValid($resourceType)){
            return;
        }
        
        ensureDirectory("*",TRUE);
        
        $this->kClient = SEEDInput_Int('client');
        $this->kfrClient = $this->oPeopleDB->getKFR("C", $this->kClient);

        $clinics = new Clinics($this->oApp);
        $this->kfrClinic = (new ClinicsDB($this->oApp->kfdb))->GetClinic($clinics->GetCurrentClinic());

        $this->kStaff = $this->oApp->sess->GetUID();
        $this->kfrStaff = $this->oPeopleDB->getKFRCond("PI","P.uid='{$this->kStaff}'");

        $templateProcessor = new MyPhpWordTemplateProcessor($resourcename);
        foreach($templateProcessor->getVariables() as $tag){
            $v = $this->expandTag($tag);
            $v = $this->tnrs->resolveTag($tag, ($v?:""));
            if(substr($tag,0,7) == 'section'){
                $templateProcessor->insertSection($tag, $v);
            }else{
                $templateProcessor->setValue($tag, $this->encode($v));
            }
        }

        switch($resourceType){
            case self::STANDALONE_RESOURCE:
                // Manually fetch the client code using the built in code proccessing system.
                // By hard coding this tag in a call to expandTag we are telling the system to get the clients code
                // This saves us the trouble of duplicating the code to get the client code.
                // Since the system already has that functionality and we just need to tap into it
                $code = $this->expandTag("client:code");
                // Took this from PhpOffice\PhpWord\PhpWord::save()
                header('Content-Description: File Transfer');
                header('Content-Disposition: attachment; filename="' .$code.($code?"-":"").basename($resourcename) . '"');
                header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                header('Content-Transfer-Encoding: binary');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Expires: 0');

                // Save the substituted template to a temp file and pass it to the php://output, then exit so no other output corrupts the file.
                // PHP automatically deletes the temp file when the script ends.
                $tempfile = $templateProcessor->save();
                $this->handleImages($tempfile);
                if( ($fp = fopen( $tempfile, "rb" )) ) {
                    fpassthru( $fp );
                    fclose( $fp );
                }

                die();
                break;
            case self::RESOURCE_SECTION:
                return $templateProcessor->getSection();
            case self::RESOURCE_GROUP:
                // Save the substituted template to a temp file and return the file name so it can be added to the zip
                // PHP automatically deletes the temp file when the script ends.
                $tempfile = $templateProcessor->save();
                $this->handleImages($tempfile);
                return $tempfile;
        }
    }

    private function handleImages(String $fileName){
        $za = new ZipArchive();
        $za->open($fileName);
        $Tree = $pathArray = array(); //empty arrays
        for ($i = 0; $i < $za->numFiles; $i++) {
            $path = $za->getNameIndex($i);
            $pathBySlash = array_values(explode('/', $path));
            $c = count($pathBySlash);
            $temp = &$Tree;
            for ($j = 0; $j < $c - 1; $j++)
                if (isset($temp[$pathBySlash[$j]]))
                    $temp = &$temp[$pathBySlash[$j]];
                else {
                    $temp[$pathBySlash[$j]] = array();
                    $temp = &$temp[$pathBySlash[$j]];
                }
                if (substr($path, -1) == '/')
                    $temp[$pathBySlash[$c - 1]] = array();
                else
                    $temp[] = $pathBySlash[$c - 1];
        }
        if(!array_key_exists("media", $Tree['word'])){
            //There are no images in the document. Skip to the end to prevent memory leaks with open zip
            goto cleanup;
        }
        $array = $Tree['word']['media'];
        $placeholders = array_values(array_diff(scandir(CATSDIR_IMG."placeholders"), [".",".."]));
        $hashes = array();
        foreach ($placeholders as $placeholder){
            $hashes[] = sha1(file_get_contents(CATSDIR_IMG."placeholders/".$placeholder));
        }
        foreach ($array as $img){
            if(in_array(sha1($za->getFromName("word/media/".$img)),$hashes)){
                $clinics = new Clinics($this->oApp);
                $str = $placeholders[array_search(sha1($za->getFromName("word/media/".$img)), $hashes)];
                switch(substr($str,0,strrpos($str, "_"))){
                    case "Footer":
                        $imagePath = $clinics->getImage(Clinics::FOOTER);
                        break;
                    case "Square_logo":
                        $imagePath = $clinics->getImage(Clinics::LOGO_SQUARE);
                        break;
                    case "Wide_logo":
                        $imagePath = $clinics->getImage(Clinics::LOGO_WIDE);
                        break;
                }
                if($imagePath === FALSE){
                    $im = imagecreatefromstring($img);
                    $data = imagecreate(imagesx($im), imagesy($im));
                    imagedestroy($im);
                }
                switch(strtolower(pathinfo($img,PATHINFO_EXTENSION))){
                    case "png":
                        $imageType = IMAGETYPE_PNG;
                        break;
                    case "jpg":
                        $imageType = IMAGETYPE_JPEG;
                        break;
                }
                $za->addFromString("word/media/".$img, getImageData($imagePath?:$data, $imageType,$imagePath === FALSE));
            }
        }
        cleanup:
        $za->close();
    }
    
    private function encode(String $toEncode):String{
        return str_replace(array("&",'"',"'","<",">"), array("&amp;","&quote;","&apos;","&lt;","&gt;"), $toEncode);
    }
    
    private function expandTag($tag)
    {
        $raTag = explode( ':', $tag, 2 );
        switch( count($raTag) ) {
            case 2:  // [0] is a table, [1] is a col
                return( $this->resolveTableTag( $raTag[0], $raTag[1] ) );
            case 1:  // single-name tag
                return( $this->resolveSingleTag( $raTag[0] ) );
            default:
                return( "" );
        }
    }

    private function resolveTableTag( $table, $col )
    {
        $s = "";

        $table = strtolower($table);
        $col = array(strtolower($col),$col);
        if( $table == 'clinic' && $this->kfrClinic ) {
            switch( $col[0] ) {
                case 'full_address':
                    $s = $this->kfrClinic->Expand("[[address]]\n[[city]] [[province]] [[postal_code]]");
                    break;
                default:
                    $s = $col[0];
                    $s = $this->kfrClinic->Value( $col[0] ) ?: "";  // if col[0] is not defined Value() returns null
            }
        }

        if( $table == 'staff' && $this->kfrStaff ) {
            // process common fields of People
            if( ($s = $this->peopleCol( $col, $this->kfrStaff )) ) {
                goto done;
            }
            switch( $col[0] ) {
                case 'role':
                    $s = $this->kfrStaff->Value( 'pro_role' );
                    break;
                case 'credentials':
                    if( ($raStaff = $this->oPeople->GetStaff( $this->kStaff )) ) {
                        $s = @$raStaff['P_extra_credentials'];
                    }
                    break;
                case 'regnumber':
                    $ra = SEEDCore_ParmsURL2RA( $this->kfrStaff->Value('P_extra') );
                    $s = $ra['regnumber' ];
                    break;
                default:
                    $s = $this->kfrStaff->Value( $col[0] ) ?: "";  // if col[0] is not defined Value() returns null
            }
        }

        if( $table == 'client' && $this->kfrClient ) {
            // process common fields of People
            if( ($s = $this->peopleCol( $col, $this->kfrClient )) ) {
                goto done;
            }
            switch( $col[0] ) {
                case 'age':
                    $s = date_diff(date_create($this->kfrClient->Value("P_dob")), date_create('now'))->format("%y Years %m Months");
                    break;
                case 'code':
                    if($this->kClient && $this->kfrClient->Value("P_first_name") && $this->kfrClient->Value("P_last_name")){
                        $s = (new ClientCodeGenerator($this->oApp))->GetClientCode($this->kClient);
                    }
                    else{
                        $s = "";
                    }
                    break;
                default:
                    $s = $this->kfrClient->Value( $col[0] ) ?: "";  // if col[0] is not defined Value() returns null
            }
        }
        if($table == 'section'){
            if(strpos($col[1], "/") == 0){
                if (file_exists(CATSDIR_RESOURCES.substr($col[1], 1)) && pathinfo(CATSDIR_RESOURCES.substr($col[1], 1),PATHINFO_EXTENSION) == "docx"){
                    $s = $this->fill_resource(CATSDIR_RESOURCES.substr($col[1], 1),self::RESOURCE_SECTION);
                }
            }
            else if(file_exists($GLOBALS['directories']['sections']['directory'].$col[1])){
                $s = $this->fill_resource($GLOBALS['directories']['sections']['directory'].$col[1],self::RESOURCE_SECTION);
            }
        }
        if(in_array($table, getAssessmentTypes())){
            if(array_key_exists($table, $this->assessments) && $this->assessments[$table] != AssessmentsCommon::DO_NOT_INCLUDE){
                $assmt = (new AssessmentsCommon($this->oApp))->GetAsmtObject($this->assessments[$table]);
                try{
                    $s = $assmt->getTagValue($col[0]);
                }
                catch(Exception $e){
                    $s = "";
                    error_log($e->getTraceAsString());
                }
            }
        }
        if(preg_match("/data\d+/", $table)){
            $id = $this->data[int(str_replace("data", "", $table))-1];
            if(substr($id, -1) === "p"){
                $id = substr($id, 0,-1);
                if(ClientList::parseID($id)[0] == ClientList::CLIENT && $col[0] == "name"){
                    $col[0] = "parents_name";
                }
            }
            list($type,$key) = ClientList::parseID($id);
            $kfr = $this->oPeopleDB->GetKFR($type, $key);
            if(!$kfr){
                goto done;
            }
            if( ($s = $this->peopleCol( $col, $kfr )) ) {
                goto done;
            }
            switch ($type){
                case ClientList::CLIENT:
                    switch( $col[0] ) {
                        case 'age':
                            $s = date_diff(date_create($kfr->Value("P_dob")), date_create('now'))->format("%y Years %m Months");
                            break;
                        case 'code':
                            if($kfr && $kfr->Value("P_first_name") && $kfr->Value("P_last_name")){
                                $s = (new ClientCodeGenerator($this->oApp))->GetClientCode($key);
                            }
                            else{
                                $s = "";
                            }
                            break;
                        default:
                            $s = $kfr->Value( $col[0] ) ?: "";  // if col[0] is not defined Value() returns null
                    }
                    break;
                case ClientList::INTERNAL_PRO:
                    switch( $col[0] ) {
                        case 'role':
                            $s = $kfr->Value( 'pro_role' );
                            break;
                        case 'credentials':
                            if( ($raStaff = $this->oPeople->GetStaff( $key )) ) {
                                $s = @$raStaff['P_extra_credentials'];
                            }
                            break;
                        case 'regnumber':
                            $ra = SEEDCore_ParmsURL2RA( $kfr->Value('P_extra') );
                            $s = $ra['regnumber' ];
                            break;
                        default:
                            $s = $kfr->Value( $col[0] ) ?: "";  // if col[0] is not defined Value() returns null
                    }
                    break;
                case ClientList::EXTERNAL_PRO:
                    switch($col[0]){
                        case 'spec_ed':
                            if(strtolower($kfr->Value('role')) == 'school'){
                                $s = "ATTN: Special Education Teachers";
                                break;
                            }
                        default:
                            $s = $kfr->Value( $col[0] ) ?: "";
                    }
                    break;
            }
        }
        
        done:
        return( $s );
    }

    private function resolveSingleTag( $tag )
    {
        $s = "";

        switch(strtolower($tag)){
            case 'date':
                $s = date("M d, Y");
                break;
        }
        return( $s );
    }

    private function peopleCol( $col, KeyframeRecord $kfr )
    {
        $map = array( 'first_name'    => 'P_first_name',
                      'firstname'     => 'P_first_name',
                      'last_name'     => 'P_last_name',
                      'lastname'      => 'P_last_name',
                      'address'       => 'P_address',
                      'city'          => 'P_city',
                      'province'      => 'P_province',
                      'postal_code'   => 'P_postal_code',
                      'postalcode'    => 'P_postal_code',
                      'postcode'      => 'P_postal_code',
                      'phone'         => 'P_phone_number',
                      'phonenumber'   => 'P_phone_number',
                      'phone_number'  => 'P_phone_number',
                      'email'         => 'P_email',
        );

        // Process tags that are in the People table so they have a P_ prefix
        if( ($colP = @$map[$col[0]]) ) {
            return( $kfr->Value($colP) );
        }

        // Process tags that are common to Clients and Staff
        switch( $col[0] ) {
            case 'name':
                return( $kfr->Expand("[[P_first_name]] [[P_last_name]]") );
            case 'full_address':
            case 'fulladdress':
                return( $kfr->Expand("[[P_address]]\n[[P_city]] [[P_province]] [[P_postal_code]]") );
            case 'dob':
            case 'date_of_birth':
                return date_format(date_create($kfr->Value("P_dob")), "M d, Y");
        }
        //Process pronoun tags.
        // check against the original column name because we can have
        // different variations of pronoun tags to allow for capitals
        switch( substr($col[1], 0,1).strtolower(substr($col[1], 1)) ) {
            case 'he':
                return $this->getPronoun("s", $kfr);
            case 'He':
                return $this->getPronoun("S", $kfr);
            case 'him':
                return $this->getPronoun("o", $kfr);
            case 'Him':
                return $this->getPronoun("O", $kfr);
            case 'his':
                return $this->getPronoun("p", $kfr);
            case 'His':
                return $this->getPronoun("P", $kfr);
        }


        // Empty string means the col wasn't processed.
        // That will also be returned above if a field is blank e.g. P_address, but that's okay because the calling function only
        // has to do something if the return is non-blank.
        return( "" );
    }

    /**
     * @param String $form - Form of Pronoun
     * S - subjective, O - objective, P - posesive
     * @param KeyframeRecord $kfr - record of person
     */
    private function getPronoun($form, KeyframeRecord $kfr){
        switch($kfr->Value("P_pronouns")){
            case 'M':
                switch($form){
                    case "S":
                        return "He";
                    case "O":
                        return "Him";
                    case "P":
                        return "His";
                    case "s":
                        return "he";
                    case "o":
                        return "him";
                    case "p":
                        return "his";
                }
            case 'F':
                switch($form){
                    case "S":
                        return "She";
                    case "O":
                    case "P":
                        return "Her";
                    case "s":
                        return "she";
                    case "o":
                    case "p":
                        return "her";
                }
            case 'O':
                switch($form){
                    case "S":
                        return "They";
                    case "O":
                        return "Them";
                    case "P":
                        return "Their";
                    case "s":
                        return "they";
                    case "o":
                        return "them";
                    case "p":
                        return "their";
                }
        }
        return( "" );
    }

}

?>