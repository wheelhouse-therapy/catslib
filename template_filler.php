<?php

require(SEEDROOT.'/vendor/autoload.php');

class template_filler {

    private $oApp;
    private $oPeopleDB;

    public function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        $this->oPeopleDB = new PeopleDB( $this->oApp );
    }

    public function fill_resource($resourcename){

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

        switch(strtolower($table)){
            case 'clinic':
                $clinics = new Clinics($this->oApp);
                $kfr = (new ClinicsDB($this->oApp->kfdb))->GetClinic($clinics->GetCurrentClinic());
                break;
            case 'therapist':
                $kfr = $this->oPeopleDB->getKFRCond("PI","P_uid=".$this->oApp->sess->GetUID());
                beak;
            case 'client':
                $key = SEEDInput_Int("client");
                $kfr = $this->oPeopleDB->getKFR("C", $key);
                break;
            default:
                goto done; // Unknown Table
        }

        if($table == 'client' && (strtolower($col) == 'name' || strtolower($col) == 'clients_name' || strtolower($col) == 'client_name')){
            $s = $kfr->Expand("[[P_first_name]] [[P_last_name]]");
        }
        if($table == 'client' && (strtolower($col) == 'age' || strtolower($col) == 'clients_age' || strtolower($col) == 'client_age')){
            $s = date_diff(new DateTime('now'), new DateTime($kfr->Value('P_dob')))->format('%y Years');
        }
        if(strtolower($col) == 'full_address' && ($table == 'client' || $table == 'therapist')){
            $s = $kfr->Expand("[[P_address]]\n[[P_city]] [[P_postal_code]]");
        }
        if(strtolower($col) == 'full_address' && $table == 'clinic'){
            $s = $kfr->Expand("[[address]]\n[[city]] [[postal_code]]");
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
}

?>