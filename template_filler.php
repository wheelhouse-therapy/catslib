<?php

require(SEEDROOT.'/vendor/autoload.php');

class template_filler {

    private $oApp;
    private $oPeopleDB;

    private $kfrClient = null;
    private $kfrClinic = null;
    private $kfrStaff = null;

    public function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        $this->oPeopleDB = new PeopleDB( $this->oApp );
    }

    public function fill_resource($resourcename)
    {
        $kClient = SEEDInput_Int('client');
        $this->kfrClient = $this->oPeopleDB->getKFR("C", $kClient);

        $clinics = new Clinics($this->oApp);
        $this->kfrClinic = (new ClinicsDB($this->oApp->kfdb))->GetClinic($clinics->GetCurrentClinic());

        $this->kfrStaff = $this->oPeopleDB->getKFRCond("PI","P.uid='".$this->oApp->sess->GetUID()."'");

        $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($resourcename);
        foreach($templateProcessor->getVariables() as $tag){
            $v = $this->expandTag($tag);
            $templateProcessor->setValue($tag, $v);
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
        $col = strtolower($col);

        if( $table == 'clinic' && $this->kfrClinic ) {
            switch( $col ) {
                case 'full_address':
                    $s = $this->kfrClinic->Expand("[[address]]\n[[city]] [[postal_code]]");
                default:
                    $s = $this->kfrClinic->Value( $col );
            }
        }

        if( $table == 'staff' && $this->kfrStaff ) {
            // process common fields of People
            if( ($s = $this->peopleCol( $col, $this->kfrStaff )) ) {
                goto done;
            }
            switch( $col ) {
                case 'role':
                    $s = $this->kfrStaff->Value( 'pro_role' );
                    break;
                default:
                    $s = $this->kfrStaff->Value( $col );
            }
        }

        if( $table == 'client' && $this->kfrClient ) {
            // process common fields of People
            if( ($s = $this->peopleCol( $col, $this->kfrClient )) ) {
                goto done;
            }
            switch( $col ) {
                case 'age':
                    $s = date_diff(date_create($this->kfrClient->Value("P_dob")), date_create('now'))->format("%y Years %m Months");
                    break;
                default:
                    $s = $this->kfrClient->Value( $col );
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
                      'dob'           => 'P_dob',
                      'date_of_birth' => 'P_dob',
                      'phone'         => 'P_phone_number',
                      'phonenumber'   => 'P_phone_number',
                      'phone_number'  => 'P_phone_number',
                      'email'         => 'P_email',
        );

        // Process tags that are in the People table so they have a P_ prefix
        if( ($colP = @$map[$col]) ) {
            return( $kfr->Value($colP) );
        }

        // Process tags that are common to Clients and Staff
        switch( $col ) {
            case 'name':
                return( $kfr->Expand("[[P_first_name]] [[P_last_name]]") );
            case 'full_address':
            case 'fulladdress':
                return( $kfr->Expand("[[P_address]]\n[[P_city]] [[P_postal_code]]") );
            //Process pronoun tags.
            case 'he':
            case 'they':
                return $this->getPronoun("S", $kfr);
            case 'him':
            case 'them':
                return $this->getPronoun("O", $kfr);
            case 'his':
            case 'their':
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
                        return "he";
                    case "O":
                        return "him";
                    case "P":
                        return "his";
                }
            case 'F':
                switch($form){
                    case "S":
                        return "she";
                    case "O":
                        return "her";
                    case "P":
                        return "her";
                }
            case 'O':
                switch($form){
                    case "S":
                        return "they";
                    case "O":
                        return "them";
                    case "P":
                        return "their";
                }
        }
    }
    
}

?>