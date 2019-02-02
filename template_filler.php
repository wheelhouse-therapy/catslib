<?php

require_once 'client_code_generator.php';

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
                if( substr($fix,0,6) == '${date' ||
                    substr($fix,0,8) == '${client' ||
                    substr($fix,0,7) == '${staff' ||
                    substr($fix,0,8) == '${clinic' ||
                    substr($fix,0,9) == '${section')
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
    
}

class template_filler {

    private $oApp;
    private $oPeopleDB;
    private $tnrs;

    private $kfrClient = null;
    private $kfrClinic = null;
    private $kfrStaff = null;

    private $kClient = 0;
    private $kStaff = 0;

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
    //TODO implement
    public const RESOURCE_SECTION = 2;
    /**
     * The resource being filled is a part of an assessment write up.
     * Uses predefined file location lookup.
     */
    //TODO implement file lookup
    public const REPORT_SECTION = 3;
    
    public function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        $this->oPeople = new People( $oApp );
        $this->oPeopleDB = new PeopleDB( $oApp );
        $this->tnrs = new TagNameResolutionService($oApp->kfdb);
    }

    private function isResourceTypeValid($resourceType):bool{
        // You Must add a new resource type to this switch statement or it will not validate
        switch($resourceType){
            case self::STANDALONE_RESOURCE:
            case self::RESOURCE_SECTION:
            case self::REPORT_SECTION:
                return true;
            default:
                return false;
        }
    }
    
    /** Replace tags in a resource with their corresponding data values
     * @param String $resourcename - Path of resource to replace tags in
     * @param $resourceType - type of resource that is being filled, must be one of the class constants and effects the file output
     */
    public function fill_resource($resourcename, $resourceType = self::STANDALONE_RESOURCE)
    {
        
        if(!$this->isResourceTypeValid($resourceType)){
            return;
        }
        
        $this->kClient = SEEDInput_Int('client');
        $this->kfrClient = $this->oPeopleDB->getKFR("C", $this->kClient);

        $clinics = new Clinics($this->oApp);
        $this->kfrClinic = (new ClinicsDB($this->oApp->kfdb))->GetClinic($clinics->GetCurrentClinic());

        $this->kStaff = $this->oApp->sess->GetUID();
        $this->kfrStaff = $this->oPeopleDB->getKFRCond("PI","P.uid='{$this->kStaff}'");

        $templateProcessor = new MyPhpWordTemplateProcessor($resourcename);
        foreach($templateProcessor->getVariables() as $tag){
            $v = $this->expandTag($tag);
            $v = $this->tnrs->resolveTag($tag, $v);
            if(substr($tag,0,7) == 'section'){
                $templateProcessor->insertSection($tag, $v);
            }else{
                $templateProcessor->setValue($tag, $v);
            }
        }

/* Aha, the trick for substitution is to just use $templateProcessor->save().
   The reason is that the templateProcessor reads the xml in the docx, str_replaces the tags, and puts the xml back in a temp docx.
   Then below phpword loads that temp docx and saves it again. This screws up complex documents that phpword doesn't know how to handle.
   BUT, the temp docx is the original xml with just our tags replaced so it's exactly what we want anyway.

        $ext = "";
        switch(strtolower(substr($resourcename,strrpos($resourcename, ".")))){
            case '.docx':
                $ext = 'Word2007';
                break;
            case '.html':
                $ext = 'HTML';
                break;
            case '.odt':
                $ext = 'ODText';
                break;
            case '.rtf':
                $ext = 'RTF';
                break;
            case '.doc':
                $ext = 'MsDoc';
                break;
        }
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($templateProcessor->save(),$ext);
        $phpWord->save(substr($resourcename,strrpos($resourcename, '/')+1),$ext,TRUE);
*/

        switch($resourceType){
            case self::STANDALONE_RESOURCE:
                // Took this from PhpOffice\PhpWord\PhpWord::save()
                header('Content-Description: File Transfer');
                header('Content-Disposition: attachment; filename="' . basename($resourcename) . '"');
                header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                header('Content-Transfer-Encoding: binary');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Expires: 0');

                // Save the substituted template to a temp file and pass it to the php://output, then exit so no other output corrupts the file.
                // PHP automatically deletes the temp file when the script ends.
                $tempfile = $templateProcessor->save();
                if( ($fp = fopen( $tempfile, "rb" )) ) {
                    fpassthru( $fp );
                    fclose( $fp );
                }

                die();
                break;
            case self::RESOURCE_SECTION:
            case self::REPORT_SECTION:
                $s = "";
                $tempfile = $templateProcessor->save();
                if( ($fp = fopen( $tempfile, "rb" )) ) {
                    //TODO extract data
                    fwrite($fp, $s);
                    fclose( $fp );
                }
                return $s;
        }
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
            // This is just to test how to inject docx xml into a Word file.
            // There should be variants of this tag for different reports and report formats.
            // Also, the file loaded below should be run through template_filler here, to expand tags in it.
            $s = file_get_contents( CATSLIB."templates/assessment.xml" );
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