<?php

require_once SEEDROOT."DocRep/DocRepUI.php";

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

    function DrawMainTabs()
    {
        return( "" );
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

.cats_docmaintabs {
        margin: 30px 0 -20px 30px;
}

.cats_doctree {
        border:1px solid #888;
        background-color:#ddd;
        border-radius:10px;
        margin:20px;
        padding:20px;
}

.cats_doctreetabs {
        margin-bottom:10px;
}

.cats_doctree_titleSelected {
        font-weight: bold;
}

.cats_docpreview_folder {
}
.cats_docpreview_doc {
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

    $o = new CATSDocumentManager( $oApp, $kSelectedDoc );

    $s .= $o->Style();

    // default "action" is to show the PreviewDoc
    $sRight = $sRightClass = "";
    switch( ($pTab = $oApp->sess->SmartGPC( 'tab' )) ) {
        case "rename0":
            $sRight = $o->oDocMan->Rename0();
            $sRightClass = "cats_docform";
            break;
        case "edit0":
            $sRight = $o->oDocMan->Edit0();
            $sRightClass = "cats_docform";
            break;
        case "joke0":
            $sRight = "Did you hear the one about the cat who needed OT? He got therapurr.";
            $sRightClass = "cats_docform";
            break;

        default:
            if( $o->oDocMan->oDoc ) {
                switch( $o->oDocMan->oDoc->GetType() ) {
                    case 'FOLDER':
                        $sRightClass= 'cats_docpreview_folder';
                        break;
                    case 'DOC':
                        $sRight = $o->oDocMan->PreviewDoc();
                        $sRightClass = "cats_docpreview_doc";
                        break;
                    case 'IMAGE':
                        break;
                    case 'WHATEVER':
                        break;
                }
            }
            break;
    }

    $s .= "<div class='cats_docmaintabs'><div class='container-fluid'>".$o->DrawMainTabs()."</div></div>";
    $s .= "<div class='cats_doctree'>"
         ."<div class='container-fluid'>"
             ."<div class='row'>"
                 ."<div class='col-md-6'>".$o->oDocMan->DrawDocTree( 0 )."</div>"
                 ."<div class='col-md-6'>"
                     .($o->oDocMan->GetSelectedDocKey() ? ("<div class='cats_doctreetabs'>".$o->DrawTreeTabs()."</div>") : "")
                     ."<div class='$sRightClass'>".$o->oDocMan->TreeForms()."</div>"
                 ."</div>"
             ."</div>"
        ."</div></div>";

    $s = str_replace( "[[DocRepApp_TreeForm_View_Text]]", $o->oDocMan->GetDocHTML(), $s );


    return( $s );
}

?>
