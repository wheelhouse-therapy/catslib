<?php

require_once SEEDROOT."DocRep/DocRepUI.php";
include_once "share_resources.php";

class CATSDocTabSet extends Console02TabSet
{
    private $oApp;
    private $kSelectedDoc;

    function __construct( SEEDAppConsole $oApp, $kSelectedDoc )
    {
        $this->oApp = $oApp;
        $this->kSelectedDoc = $kSelectedDoc;

        $raConfig = [
               'main'=> ['tabs' => [ 'documents' => ['label'=>'Documents'],
                                     'versions'  => ['label'=>'Versions'],
                                     'files'     => ['label'=>'Files'],
                                     'ghost'     => ['label'=>'Ghost']
                                   ],
                         // this doubles as sessPermsRequired and console::TabSetPermissions
                         'perms' =>[ 'documents' => [],
                                     'versions'  => [],
                                     'files'     => [],
                                     'ghost'     => ['A notyou'],
                                                    '|'  // allows screen-login even if some tabs are ghosted
                                   ],
        ] ];

        parent::__construct( $oApp->oC, $raConfig );
    }

    function TabSet_main_documents_ControlDraw() { return( "<br/>" ); }
    function TabSet_main_documents_ContentDraw()
    {
        $s = "";

        $o = new CATSDocumentManager( $this->oApp, $this->kSelectedDoc );

        $s .= $o->Style();

        $s .= "<div class='cats_doctree'>"
             ."<div class='container-fluid'>"
                 ."<div class='row'>"
                     ."<div class='col-md-6'>".$o->oDocMan->DrawDocTree( 0 )."</div>"
                     ."<div class='col-md-6'>"
                         .($o->oDocMan->GetSelectedDocKey() ? ("<div class='cats_doctreetabs'>".$o->DrawTreeTabs()."</div>") : "")
                         ."<div class='cats_docform'>".$o->oDocMan->TreeForms()."</div>"
                     ."</div>"
                 ."</div>"
            ."</div></div>";

        $s = str_replace( "[[DocRepApp_TreeForm_View_Text]]", $o->oDocMan->GetDocHTML(), $s );

        return( $s );
    }

}


class CATSDocApp extends DocRepApp1
{
    private $oApp;
    private $oDocRepDB;
    private $oDocRepUI;

    function __construct( SEEDAppSessionAccount $oApp, $kSelectedDoc, DocRepDB2 $oDB, CATSDocRepUI $oUI )
    {
        parent::__construct( $oUI, $oDB, $kSelectedDoc );
        $this->oApp = $oApp;
    }

    function Edit0()
    {
        $s = "";

        $s = "PUT THE EDIT FORM HERE";

        return( $s );
    }

    function Rename0()
    {
        $s = "";

        if( ($k = $this->GetSelectedDocKey()) ) {
            $oForm = new SEEDCoreForm( 'Plain' );
            $s .= "<form method='post'>"
                 .$oForm->Hidden( 'k', array( 'value' => $k ) )                // so the UI knows the current doc in the tree
                 .$oForm->Hidden( 'action', array( 'value' => 'rename2' ) )
                 .$oForm->Text( 'doc_name', '' )           // the new document name
                 ."<input type='submit' value='Rename'/>"
                 ."</form>";
        }

        return( $s );
    }

}

class CATSDocRepUI extends DocRepUI
{
    function __construct( DocRepDB2 $oDocRepDB )
    {
        parent::__construct( $oDocRepDB );
    }

    function DrawTree_title( DocRepDoc2 $oDoc, $raTitleParms )
    /*********************************************************
        This is called from DocRepUI::DrawTree for every item in the tree. It writes the content of <div class='DocRepTree_title'>.

        raTitleParms:
            bSelectedDoc:   true if this document is selected in the tree and should be highlighted
            sExpandCmd:     the command to be issued if the user clicks on a tree-expand-collapse control associated with this item
     */
    {
        $kDoc = $oDoc->GetKey();

        $s = "<a href='${_SERVER['PHP_SELF']}?k=$kDoc'><nobr>"
        .( $raTitleParms['bSelectedDoc'] ? "<span class='cats_doctree_titleSelected'>" : "" )
        .($oDoc->GetTitle('') ?: ($oDoc->GetName() ?: "Untitled"))
        .( $raTitleParms['bSelectedDoc'] ? "</span>" : "" )
        ."</nobr></a>";

        return( $s );
    }
}

class CATSDocumentManager
{
    private $oApp;
    public  $oDocMan;

    function __construct( SEEDAppSessionAccount $oApp, $kSelectedDoc )
    {
        $this->oApp = $oApp;
        $oDocRepDB = new DocRepDB2( $oApp->kfdb, $oApp->sess->GetUID(), array( 'raPermClassesR'=>array(1), 'logdir'=>CATSDIR_LOG ) );
        $oDocRepUI = new CATSDocRepUI( $oDocRepDB );

        $this->oDocMan = new CATSDocApp( $oApp, $kSelectedDoc, $oDocRepDB, $oDocRepUI );
    }

    function DrawTreeTabs()
    {
        $s = $this->oDocMan->TreeTabs();
        return( $s );
        $s = "<div class='row'>"
            .$this->tab( "View",   "view0" )
            .$this->tab( "Edit",   "edit0" )
            .$this->tab( "Rename", "rename0" )
            //.$this->tab( "Tell a Joke", "joke0" )
            ."</div>";
        return( $s );
    }

    private function tab( $label, $tabcmd )
    {
        return( "<div class='col-md-2'>"
               ."<a href='{$_SERVER['PHP_SELF']}?tab=$tabcmd&k=".$this->oDocMan->GetSelectedDocKey()."'>$label</a>"
               ."</div>" );

/*
            ."<form method='post'>"
               ."<input type='hidden' name='k' value='".$this->oDocMan->GetSelectedDocKey()."'/>"
               ."<input type='hidden' name='action' value='$action'/>"
               ."<input type='submit' value='$label'/>"
               ."</form></div>" );
*/
    }

    function Style()
    {
        return( $this->oDocMan->Style()."
<style>
.DocRepTree_level { margin-left:30px; }

.cats_doctree {
        border-radius:10px;
        margin:20px;
}

.cats_doctreetabs {
        margin-bottom:10px;
}

.cats_doctree_titleSelected {
        font-weight: bold;
}

.cats_docpreview_folder {
}
.cats_docform {
        background-color:#eee;
        border:1px solid #777;
        border-radius: 10px;
        padding:20px;
}

</style>
" );
    }
}



function DocumentManager( SEEDAppSessionAccount $oApp )
{
    $s = "";

    $kSelectedDoc = SEEDInput_Int('k');

/*
    // default "action" is to show the PreviewDoc
    $sRight = $sRightClass = "";
    switch( ($pTab = $oApp->sess->SmartGPC( 'tab' )) ) {
        case "rename0":
            //$sRight = $o->oDocMan->Rename0();
            //$sRightClass = "cats_docform";
            break;
        case "edit0":
            //$sRight = $o->oDocMan->Edit0();
            //$sRightClass = "cats_docform";
            break;
        case "joke0":
            //$sRight = "Did you hear the one about the cat who needed OT? He got therapurr.";
            //$sRightClass = "cats_docform";
            break;

        default:
            if( $o->oDocMan->oDoc ) {
                switch( $o->oDocMan->oDoc->GetType() ) {
                    case 'FOLDER':
                        //$sRightClass= 'cats_docpreview_doc';
                        break;
                    case 'DOC':
                        //$sRight = $o->oDocMan->PreviewDoc();
                        //$sRightClass = "cats_docpreview_doc";
                        break;
                    case 'IMAGE':
                        break;
                    case 'WHATEVER':
                        break;
                }
            }
            break;
    }
*/

    $oTS = new CatsDocTabSet( $oApp, $kSelectedDoc );
    $s .= "<br/><br/>".$oTS->TabSetDraw( 'main' );

    return( $s );
}

function ManageResources( SEEDAppSessionAccount $oApp ) {
    $s = "<h2>Manage Resources</h2>";

    $script = <<<JavaScript
<script>
    var displayed = [];
    function toggleDisplay(block){
        if(displayed.indexOf(block) !== -1)
            displayed.splice( displayed.indexOf(block), 1 );
        else
            displayed.push(block);
        $(document.getElementById(block)).slideToggle(400);
        updateServer();
    }

    function setContents(block, contents){
    	block = document.getElementById(block);
    	contents = document.getElementById(contents);
    	if(block.innerHTML == contents.innerHTML){
    		if(block.style.display == 'none'){
    			block.style.display = 'block';
    		}
    		else {
    			block.style.display = 'none';
    		}
    	}
    	else{
    		block.innerHTML = contents.innerHTML;
    		block.style.display = 'block';
    	}
    }
    
    function updateServer(){
        $.ajax({
            type: "POST",
            data: {cmd:'admin-ResourceTrees',open:displayed},
            url: 'jx.php',
            error: function(jqXHR, status, error) {
                console.log(status + ": " + error);
            }
        });
    }
</script>
JavaScript;

    $style = <<<CSS
<style>
    .cats_doctree_level { margin-left:30px; }
    .cats_doctree {
        border:1px solid #888;
        background-color:#ddd;
        border-radius:10px;
        margin:20px;
        padding:20px;
    }
    .cats_docform {
        background-color:#eee;
        border:1px solid #777;
        border-radius: 10px;
        padding:20px;
    }
    .cats_form {
        width:180px;

        -ms-box-sizing:content-box;
        -moz-box-sizing:content-box;
        box-sizing:content-box;
        -webkit-box-sizing:content-box;
    }
</style>
CSS;

    $s .= $script
       .  $style;

    $oResources = new ResourceManager($oApp);

    $s .= $oResources->ManageResources();

    return $s;

}

class ResourceManager{

    private $oApp;
    private $selected_File = 0;
    private $openTrees;

    public function __construct(SEEDAppSessionAccount $oApp){
        $this->oApp = $oApp;
    }

    public function ManageResources(){
        $this->selected_File = SEEDInput_Str("file");
        if(isset($_SESSION['ResourceCMDResult'])){
            $cmdResult = $_SESSION['ResourceCMDResult'];
            unset($_SESSION['ResourceCMDResult']);
        }
        else{
            $cmdResult = "";
        }
        $this->openTrees = $this->oApp->sess->SmartGPC("open", array(array()));

        foreach ($this->openTrees as $file){
            if(!file_exists(CATSDIR_RESOURCES.$file)){
                unset($this->openTrees[array_search($file, $this->openTrees)]);
            }
        }

        $script = "<script>displayed = Object.values(JSON.parse('".json_encode($this->openTrees)."'));</script>";
        return $script.$cmdResult."<div class='cats_doctree'>".$this->listResources(CATSDIR_RESOURCES)."</div>";
    }

    private function listResources($dir){
        FilingCabinet::EnsureDirectory("*");
        $s = "";
        $directory_iterator = new DirectoryIterator($dir);

        if(iterator_count($directory_iterator) == 2){
            $s .= "No Resources<br />";
            return $s;
        }
        foreach ($directory_iterator as $fileinfo){
            if($fileinfo->isDot()){
                continue;
            }
            if($this->selected_File && $this->selected_File == $this->getPathRelativeTo($fileinfo->getRealPath(),CATSDIR_RESOURCES)){
                $this->processCommands($fileinfo);
            }
            $s .= "<a href='javascript:void(0)' onclick='toggleDisplay(\"".$this->getPathRelativeTo($fileinfo->getRealPath(),CATSDIR_RESOURCES)."\")'>".$fileinfo->getFilename()."</a><br />";
            $s .= "<div class='[style]' id='".$this->getPathRelativeTo($fileinfo->getRealPath(),CATSDIR_RESOURCES)."' style='".(in_array($this->getPathRelativeTo($fileinfo->getRealPath(),CATSDIR_RESOURCES), $this->openTrees)?"":"display:none;")." width: [width];'>";
            if($fileinfo->isDir()){
                $s = str_replace(array("[style]","[width]"), array("cats_doctree_level","100%"), $s);
                $s .= $this->listResources($fileinfo->getRealPath());
            }
            elseif($fileinfo->isFile()){
                $s = str_replace(array("[style]","[width]"), array("cats_docform","50%"), $s);
                $s .= $this->drawCommands($fileinfo->getRealPath());
            }
            $s .= "</div>";
        }
        return $s;
    }

    private function processCommands(DirectoryIterator $file_info){
        $cmd = SEEDInput_Str("cmd");
        switch($cmd){
            case "move":
                $directory = $this->getPartPath($file_info->getRealPath(), -2);
                if(!$directory){
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>Error determining resource subfolder for file ".$file_info->getFilename()."</div>";
                    break;
                }
                if(rename((substr($directory, 0,9) == "resources"?CATSDIR:CATSDIR_RESOURCES).$directory."/".$file_info->getFilename(), CATSDIR_RESOURCES.SEEDInput_Str("folder").$file_info->getFilename())){
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-success alert-dismissible'>Successfully Moved ".$file_info->getFilename()." to ".SEEDInput_Str("folder")."</div>";
                    if(!$this->oApp->kfdb->Execute("UPDATE resources_files SET folder = '".addslashes(rtrim(SEEDInput_Str("folder"),"/"))."' WHERE folder='".addslashes(rtrim($directory,"/\\"))."' AND filename='".addslashes($file_info->getFilename())."'")){
                        $_SESSION['ResourceCMDResult'] .= "<div class='alert alert-danger alert-dismissible'>Unable to migrate tags for ".$file_info->getFilename()."<br /> Contact system administrator to complete this operation</div>";
                    }
                }
                else{
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>Error Moving file ".$file_info->getFilename()." to ".SEEDInput_Str("folder")."</div>";
                }
                break;
            case "rename":
                $path = substr($file_info->getPathname(), 0,strrpos($file_info->getPathname(), DIRECTORY_SEPARATOR));
                if(!$path){
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>Error determining start of path for file ".$file_info->getFilename()."</div>";
                    break;
                }
                if(rename($file_info->getPathname(), $path.DIRECTORY_SEPARATOR.SEEDInput_Str("name").".".SEEDInput_Str("ext"))){
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-success alert-dismissible'>File ".$file_info->getFilename()." renamed to ".SEEDInput_Str("name").".".SEEDInput_Str("ext")."</div>";
                    $directory = $this->getPartPath($file_info->getRealPath(), -2);
                    if(!$this->oApp->kfdb->Execute("UPDATE resources_files SET filename = '".addslashes(SEEDInput_Str("name").".".SEEDInput_Str("ext"))."' WHERE folder='".addslashes($directory)."' AND filename='".addslashes($file_info->getFilename())."'")){
                        $_SESSION['ResourceCMDResult'] .= "<div class='alert alert-danger alert-dismissible'>Unable to migrate tags for ".$file_info->getFilename()."<br /> Contact system administrator to complete this operation</div>";
                    }
                }
                else {
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>Error renaming file ".$file_info->getFilename()." to ".SEEDInput_Str("name").".".SEEDInput_Str("ext")."</div>";
                }
                break;
            case "delete":
                if(unlink($file_info->getRealPath())){
                    $directory = $this->getPartPath($file_info->getRealPath(),-2);
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-success alert-dismissible'>File ".$file_info->getFilename()." has been deleted</div>";
                    if(!$this->oApp->kfdb->Execute("DELETE FROM resources_files WHERE folder='".addslashes($directory)."' AND filename='".addslashes($file_info->getFilename()."' "))){
                        $_SESSION['ResourceCMDResult'] .= "<div class='alert alert-danger alert-dismissible'>Unable to delete tags for ".$file_info->getFilename()."<br /> Contact system administrator to complete this operation</div>";
                    }
                }
                else{
                    $_SESSION['ResourceCMDResult'] = "<div class='alert alert-danger alert-dismissible'>Error deleting file ".$file_info->getFilename()."</div>";
                }
                break;
        }
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: ?");
        exit();
    }

    private function drawCommands($file_path){
        if(!$this->oApp->sess->CanAdmin("admin")){
            return "You don't have permission to edit files";
        }
        $directory = $this->getPartPath($file_path,-2);
        $move = "<a href='javascript:void(0)' onclick='setContents(\"".$this->getPathRelativeTo($file_path,CATSDIR_RESOURCES)."_command\",\"".$this->getPathRelativeTo($file_path,CATSDIR_RESOURCES)."_move\")'>move</a>";
        $move .= "<div id='".$this->getPathRelativeTo($file_path,CATSDIR_RESOURCES)."_move' style='display:none'>"
                ."<br /><form>
                  <input type='hidden' name='cmd' value='move' />
                  <input type='hidden' name='file' value='".$this->getPathRelativeTo($file_path,CATSDIR_RESOURCES)."' />
                  <select name='folder' class='cats_form' required><option value='' selected>-- Select Folder --</option>";
        foreach (FilingCabinet::GetDirectories() as $k=>$v){
            if($v['directory'] != $directory."/"){
               $move .="<option value='".$v['directory']."'>".$v['name']."</option>";
            }
        }
        $move .= "</select>&nbsp&nbsp<input type='submit' value='move' /></form></div>";

        $rename = "<a href='javascript:void(0)' onclick='setContents(\"".$this->getPathRelativeTo($file_path,CATSDIR_RESOURCES)."_command\",\"".$this->getPathRelativeTo($file_path,CATSDIR_RESOURCES)."_rename\")'>rename</a>";
        $rename .= "<div id='".$this->getPathRelativeTo($file_path,CATSDIR_RESOURCES)."_rename' style='display:none'>"
                  ."<br /><form>"
                  ."<input type='hidden' name='cmd' value='rename' />"
                  ."<input type='hidden' name='file' value='".$this->getPathRelativeTo($file_path,CATSDIR_RESOURCES)."' />"
                  ."<input type='text' class='cats_form' name='name' required value='".explode(".",$this->getPartPath($file_path,-1))[0]."' />.";
        $dir_key = "old";
        foreach (FilingCabinet::GetDirectories() as $k=>$v){
            if($v['directory'] == $this->getPartPath($file_path,-2)."/"){
                $dir_key = $k;
                break;
            }
        }
        $rename .= "<select name='ext' required><option value=''>Select Extension</option>";

        if( ($exts = @FilingCabinet::GetDirInfo($dir_key)['extensions']) ) {
            $file_ext = pathinfo($this->getPartPath($file_path,-1),PATHINFO_EXTENSION);
            foreach ($exts as $k=>$v){
                $rename .= "<option ".($file_ext == $v ? "selected":"").">$v</option>";
            }
        }
        $rename .= "</select>&nbsp&nbsp<input type='submit' value='rename' />"
                  ."</form>"
                  ."</div>";

                  $delete = "<a href='?cmd=delete&file=".$this->getPathRelativeTo($file_path,CATSDIR_RESOURCES)."' data-tooltip='Delete Resource'><img src='".CATSDIR_IMG."delete-resource.png'/></a>";

                  $s = "<div style='display: flex;justify-content: space-around;'>".$move.$rename.$delete."</div><div id='".$this->getPathRelativeTo($file_path,CATSDIR_RESOURCES)."_command' style='display:none'></div>";
        return $s;
    }

    private function getPartPath($path = '', $depth = 0) {
        $pathArray = array();
        $pathArray = explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR));
        if($depth < 0)
            $depth = count($pathArray)+$depth;

            if(!isset($pathArray[$depth]))
                return false;
                return $pathArray[$depth];
    }

    private function getPathRelativeTo($path, $relativeTo = ""){
        $output = "";
        if($relativeTo = realpath($relativeTo)){
            $relativeTo = $this->getPartPath($relativeTo,-1);
            $pathArray = explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR));
            for($i = array_search($relativeTo, $pathArray)+1;$i<count($pathArray);$i++){
                if($output){
                    $output .= "/";
                }
                $output .= $pathArray[$i];
            }
        }
        return ($output?:false);
    }

}

?>