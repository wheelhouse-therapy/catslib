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

    static private $level = 0;              // just used for debug messages
    static public $bDebug = false;          // turn this on to see what's happening
    static private $state = 0;              // 0 = outside of tag; 1=found $, 2=found {, (state 2 goes to 0 when } found)
    static private $currTagTextNode = null; // in states 1-2 we are assembling the tag in this text node

    function fixTagsInXML( DOMNode $node )
    /*************************************
        Our tags e.g. ${client:firstname) wind up in complicated places
        e.g. <w:r><w:t>some text. ${</w:t><w:proofErr...</w:r>
             <w:r><w:Pr>...</w:Pr><w:t>client</w:t><w:proofErr...</w:r>
             <w:r><w:Pr>...</w:Pr><w:t>:firstname</w:t><w:proofErr...</w:r>
             <w:r><w:Pr>...</w:Pr><w:t>} more text ${</w:t>...

        1) it is difficult to locate the tags, particularly in the last line where an end-} and start-${ occur in the same w:t
        2) it is error-prone to strip-tags between { } without knowing the structure within.
           e.g. track-changes elements can span portions of w:t apparently
        3) some w:t have preserve-space and some don't : we had a bug caused by stripping a <w:t preserve-space='true'>

        So we have found no simple text-parsing or regex solution that works generally.
        Instead we load the xml into a dom, use a state machine to walk through the text nodes to search for the pieces of our tags,
        and reassemble each tag in its canonical form in its first w:t (the one containing the $).

        Theoretically, w:t elements that are thereafter left empty could be deleted but we don't bother.
     */
    {
        ++self::$level;

        $isTextNode = ($node->nodeName == '#text');

        if( self::$bDebug ) {
            echo SEEDCore_NBSP("",self::$level*4)
                .($isTextNode ? "<b>{$node->nodeValue}</b>" : "{$node->nodeName}")
                ."<br>";
        }

        if( $isTextNode ) {
            // scan this text node for the next part of the ${tag}

            $bChanged = false;      // tell whether one or more characters were copied from this node

            // chars are copied to currTagTextNode as needed, or put back into nodeValue if they should stay here
            $raNodeChars = str_split($node->nodeValue);
            $node->nodeValue = "";

            foreach( $raNodeChars as $c ) {
                $bCopyChar = false;     // copy $c to currTagTextNode

                switch( self::$state ) {
                    case 0:     // haven't found a tag yet
                        if( $c == '$' ) {
                            // Found the start of a tag (maybe). If it spans multiple nodes we're going to move the rest of it here.
                            self::$state = 1;
                            self::$currTagTextNode = $node;
                        }
                        break;
                    case 1:     // found a $, expecting { to be the next character
                        if( $c == '{' ) {
                            // looks like one of our tags
                            self::$state = 2;
                            $bCopyChar = true;
                        } else {
                            // this isn't one of our tags after all.
                            self::$state = 0;
                        }
                        break;
                    case 2:     // found ${ so collect contents
                        if( $c == '$' || $c == '{' ) {
                            // uh oh, that shouldn't be here
                            self::$state = 0;
                        } else if( $c == '}' ) {
                            // found the end of the tag
                            $bCopyChar = true;
                            self::$state = 0;       // finalize the tag below and continue in state 0
                        } else {
                            $bCopyChar = true;
                        }
                        break;
                }

                if( $bCopyChar && $node !== self::$currTagTextNode ) {
                    // The current char is part of the tag we're collecting, and we're in a different node than where we found the $.
                    self::$currTagTextNode->nodeValue .= $c;
                    $bChanged = true;
                } else {
                    // The current char is not part of a tag, or if it is we're in the same node as the $ anyway.
                    $node->nodeValue .= $c;
                }
            }

            if( self::$bDebug && $bChanged ) {
                echo SEEDCore_NBSP("",self::$level*4)."<b style='color:orange'>{$node->nodeValue}</b><br/>";
                echo SEEDCore_NBSP("",self::$level*4)."<b style='color:green'>".self::$currTagTextNode->nodeValue."</b><br/>";
            }

            // If chars have been copied out of this node, set the w:t xml:space='preserve' attribute.
            // The reason is that we might leave a leading space (e.g. after the }) that wouldn't be considered significant otherwise.
            //
            // N.B. Here (and in other places) it is not enough to test that node!==currTagTextNode to tell whether chars have been
            // copied. It is possible for the nodeValue to be "tag} ...text... ${" in which case the first part of the node will have
            // been copied to a previous w:t but by the time we get here currTagTextNode will have been updated to refer to this node.
            // Use bChanged to tell whether chars have been copied.
            if( $bChanged && $node->parentNode->nodeName == 'w:t' )
            {
                $node->parentNode->setAttribute( 'xml:space', 'preserve' );
            }
        }

        // walk through the document looking for text nodes
        if( ($children = $node->childNodes) ) {
            foreach( $children as $child ) {
                $this->fixTagsInXML($child);
            }
        }
        self::$level--;
    }

    protected function fixBrokenMacros($documentPart)
    {
        $fixedDocumentPart = $documentPart;

        /* old pattern: '|\$[^{]*\{[^}]*\}|U'  '|\$(?><.*?>)*\{.*?\}|' */
/*
        $patternStartMatch = '|(<w:[^>]+[^>\/]*?>[^<>]*?\$(?><.*?>)*\{.*?\})|'; // '.*?(?<end><\/(w:[^ >]+)>)|'
$matches = [];
        $raPass1 = preg_match_all( $patternStartMatch, $documentPart, $matches, PREG_OFFSET_CAPTURE | PREG_PATTERN_ORDER );
        var_dump($matches[0]);
*/
        $oDom = new DOMDocument();
        if( $oDom->loadXML($documentPart, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING) ) {

            if( MyPhpWordTemplateProcessor::$bDebug ) {
                // this shows whether the oDom can rewrite the xml exactly the same, modulo line ends
                $testOutput = $oDom->saveXML();
                $testOutput = str_replace(["\r","\n"],["".""],$testOutput);
                $orig       = str_replace(["\r","\n"],["".""],$documentPart);
                echo "<div style='margin:30px 0'>saveXML output is"
                    .($orig==$testOutput ? " <span style='color:green'>identical</span>":" <span style='color:red'>NOT identical</span>")
                    ." to the input: ".strlen($orig)."/".strlen($testOutput)."</div>";
            }

            self::$state = 0;
            self::$currTagTextNode = null;
            $this->fixTagsInXML($oDom->documentElement);
        }
return( $oDom->saveXML() );

        $fixedDocumentPart = preg_replace_callback(
            '|(?<start><(w:[^ >]+)[^>\/]*?>[^<>]*?)(?<match>\$(?><.*?>)*\{.*?\}).*?(?<end><\/(w:[^ >]+)>)|',
            function ($match) {
                $fix = strip_tags($match['match']);
                //var_dump($match); var_dump($fix);
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
                    substr($fix,0,6) == '${data' ||
                    substr($fix,0,4) == '${if' ||
                    substr($fix,0,7) == '${endif' ||
                    $isAssessment)
                {
                    // Generate the close tag to fix space and other broken xml issue.
                    $mid = "";
                    if($match[2] != $match[5] || $match[2] == 'w:t'){
                        $mid = "</".$match[2].">";
                        if($match[5] == 'w:t'){
                            $mid .= '<w:t xml:space="preserve">';
                        }
                    }
//var_dump( $match['start'].$fix.$mid.$match['end'] );
                    // Close the existing <w:t> and open one which preserves spaces
                    // This is neccessary since strip_tags can remove the preserve spaces property of the enclosing <w:t>
                    // Thus causing spaces in the same <w:t> as the tag to be lost/not preserved
                    // If there are no spaces to be preserved it has no effect on word or the document
                    return( $match['start'].$fix.$mid.$match['end'] );
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
            $this->setValue($tag, $section,1);
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

    /**
     * Array of people ids for use with data tags
     */
    private $data = array();

    /**
     * Assessments to include in files downloaded through this template filler
     * Since this is defined in the constructor any sections included will also have access to this.
     * This means we can have sections which report on assessments and they will filled with the correct information
     */
    private $assessments;

    /**
     * Boolean controling whether tags are "skiped".
     * Used with if and endif tags.
     * "skiped" tags are replaced with "".
     * if and endif tags are always replaced with "".
     * Only toggled if evalDepth == processingDepth.
     */
    private $skipTags = FALSE;
    /**
     * Depth at which processing is occuring at.
     * This is used when processing endif tags.
     * if tags which evaluate to true increase this when not skiping tags.
     * endif tags decrease this when evalDepth == processingDepth.
     * 0 is the global scope, ie outside all if blocks.
     */
    private $processingDepth = 0;
    /**
     * Depth at which evaluation is occuring at.
     * This is used when processing if/endif tags.
     * if tags which evaluate to true increase this.
     * endif tags decrease this.
     * 0 is the global scope, ie outside all if blocks.
     */
    private $evalDepth = 0;

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
     * @param array  $raParms - parameters that can override default values
     * @param $resourceType - type of resource that is being filled, must be one of the class constants and effects the file handling
     */
    public function fill_resource($resourcename, array $raParms = [], $resourceType = self::STANDALONE_RESOURCE)
    {

        if(!$this->isResourceTypeValid($resourceType)){
            return;
        }

        /* Values are generally obtained from _REQUEST and _SESSION because this method is frequently called indirectly
         * via ajax. Optional parameters can override those.
         */
        $this->kClient = isset($raParms['client']) ? $raParms['client'] : SEEDInput_Int('client');  // use isset to test because the value can be 0 (which means no client)
        // add other raParms here e.g. clinic, kStaff


        FilingCabinet::EnsureDirectory("*",TRUE);

        $this->kfrClient = $this->oPeopleDB->getKFR(ClientList::CLIENT, $this->kClient);

        $clinics = new Clinics($this->oApp);
        $this->kfrClinic = (new ClinicsDB($this->oApp->kfdb))->GetClinic($clinics->GetCurrentClinic());

        $this->kStaff = $this->oApp->sess->GetUID();
        $manageUsers = new ManageUsers($this->oApp);
        $this->kfrStaff = $manageUsers->getClinicRecord($this->kStaff);

        $templateProcessor = new MyPhpWordTemplateProcessor($resourcename);
        $tags = $templateProcessor->getVariables();
        while(count($tags) > 0){
            $tag = $tags[0];
            $isConditional = $this->processConditionalTags($tag);
            if($isConditional || $this->skipTags){
                $templateProcessor->setValue($tag, $this->encode(""),1);
                goto next;
            }
            $v = $this->expandTag($tag);
            $v = $this->tnrs->resolveTag($tag, ($v?:""));
            if(substr($tag,0,7) == 'section'){
                $templateProcessor->insertSection($tag, $v);
            }else{
                $templateProcessor->setValue($tag, $this->encode($v),1);
            }
            next:
            $tags = $templateProcessor->getVariables();
        }

        // the template processor writes debug info if this variable is set so stop here and let us look at it
        if( MyPhpWordTemplateProcessor::$bDebug ) { exit; }

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
            if(is_string($img) && in_array(sha1($za->getFromName("word/media/".$img)),$hashes)){
                $clinics = new Clinics($this->oApp);
                $str = $placeholders[array_search(sha1($za->getFromName("word/media/".$img)), $hashes)];
                $rawData = FALSE;
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
                    case "Signature":
                        $imagePath = FALSE;
                        if($this->kfrStaff){
                            $rawData = $this->kfrStaff->Value("signature");
                        }
                        break;
                }
                if($imagePath === FALSE && $rawData == FALSE){
                    $im = imagecreatefromstring($za->getFromName("word/media/".$img));
                    $data = imagecreate(imagesx($im), imagesy($im));
                    imagefill($data, 0, 0, imagecolorallocate($data, 255, 255, 255));
                    imagedestroy($im);
                }
                else if($imagePath === FALSE){
                    $data = imagecreatefromstring($rawData);
                }
                switch(strtolower(pathinfo($img,PATHINFO_EXTENSION))){
                    case "png":
                        $imageType = IMAGETYPE_PNG;
                        break;
                    case "jpg":
                    case "jpeg":
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

    private function processConditionalTags($tag){
        $raTag = explode( ':', $tag, 2 );
        if(strtolower($raTag[0]) == "if" || strtolower($raTag[0]) == "endif"){
            if(strtolower($raTag[0]) == "endif"){
                $this->evalDepth--;
                if($this->processingDepth == $this->evalDepth || $this->processingDepth == $this->evalDepth+1){
                    if($this->processingDepth > 0){
                        $this->processingDepth--;
                    }
                    $this->skipTags = FALSE;
                }
            }
            else{
                $this->evalDepth++;
                if(!$this->skipTags){
                    // Case Sensative Check
                    if(strpos($raTag[1], "===") !== False){
                        $raTag = explode( '===', $raTag[1], 2 );
                        switch($raTag[0]){
                            case 'mode':
                                $raTag[0] = $this->kClient?"replace":"blank";
                                break;
                            default:
                                $raTag[0] = $this->expandTag($raTag[0]);
                        }
                        $this->skipTags = $raTag[0] != $raTag[1];
                    }
                    // Negative Case Sensative Check
                    else if(strpos($raTag[1], "!==") !== False){
                        $raTag = explode( '!==', $raTag[1], 2 );
                        switch($raTag[0]){
                            case 'mode':
                                $raTag[0] = $this->kClient?"replace":"blank";
                                break;
                            default:
                                $raTag[0] = $this->expandTag($raTag[0]);
                        }
                        $this->skipTags = $raTag[0] == $raTag[1];
                    }
                    // Case InSensative Check
                    else if(strpos($raTag[1], "==") !== False){
                        $raTag = explode( '==', $raTag[1], 2 );
                        switch($raTag[0]){
                            case 'mode':
                                $raTag[0] = $this->kClient?"replace":"blank";
                                break;
                            default:
                                $raTag[0] = $this->expandTag($raTag[0]);
                        }
                        $this->skipTags = strtolower($raTag[0]) != strtolower($raTag[1]);
                    }
                    // Negative Case InSensative Check
                    else if(strpos($raTag[1], "!=") !== False){
                        $raTag = explode( '!=', $raTag[1], 2 );
                        switch($raTag[0]){
                            case 'mode':
                                $raTag[0] = $this->kClient?"replace":"blank";
                                break;
                            default:
                                $raTag[0] = $this->expandTag($raTag[0]);
                        }
                        $this->skipTags = strtolower($raTag[0]) == strtolower($raTag[1]);
                    }
                    // Negative Empty Check, (PHP evaluates to false)
                    else if(substr($raTag[1], 0,1) == "!"){
                        $this->skipTags = ($this->expandTag(substr($raTag[1],1))?True:False);
                    }
                    // Empty Check, (PHP doesn't evaluates to false)
                    else{
                        $this->skipTags = ($this->expandTag($raTag[1])?False:True);
                    }
                    if(!$this->skipTags){
                        $this->processingDepth++;
                    }
                }
            }
            return TRUE;
        }
        return FALSE;
    }

    private function expandTag($tag)
    {
        $tag = trim($tag);
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
                case 'name':
                    $s = $this->kfrClinic->Value("clinic_name");
                    break;
                default:
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
// $parms['client'] is necessary because if it is not specified fill_resource() will get it from _REQUEST but it could have been
// overridden by the original caller. Given that fill_resource is recursive there should be a cleaner way to pass original arguments to it.
// i.e. always explicitly pass the client id to fill_resource() unless it is a recursing call like this.
                    $parms = ['client'=>$this->kClient];
                    $s = $this->fill_resource(CATSDIR_RESOURCES.substr($col[1], 1), $parms, self::RESOURCE_SECTION);
                }
            }
            else if(file_exists(FilingCabinet::GetDirInfo('sections')['directory'].$col[1])){
                $parms = ['client'=>$this->kClient];
                $s = $this->fill_resource(FilingCabinet::GetDirInfo('sections')['directory'].$col[1], $parms, self::RESOURCE_SECTION);
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
            $id = @$this->data[(intval(str_replace("data", "", $table))-1)]?:"C0";
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
                if($this->kClient){
                    $s = date("M d, Y");
                }
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