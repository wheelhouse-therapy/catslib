<?php
include_once('twig_mappings.php');
include_once('view_resources.php');
include_once('share_resources.php');
include_once('Clinics.php');
include_once( SEEDCORE."SEEDTemplateMaker.php" );

/* Classes to help draw the user interface
 */
class CATS_UI
{
    protected $oApp;
    //screen variable now replaced with screen history management object
    protected $oHistory;


    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;

        $raTmplMaker = array( 'fTemplates'=> [CATSLIB."templates/cats.twig",
                                              CATSLIB."templates/cats_html.twig",
                                              CATSLIB."templates/extensions.twig"] );
        $this->oTmpl = SEEDTemplateMaker2( $raTmplMaker );

        $this->oHistory = new ScreenManager($oApp);
    }

    function Header()
    {

        return "";
    }

     function OutputPage( $body )
     {


    $body .= "<script> SEEDCore_CleanBrowserAddress(); </script>

    <script> run(); </script>
    <script src='w/js/tooltip.js'></script>
    </body></html>";

    if( !$this->oApp->sess->IsLogin() ) {
        echo "<head><meta http-equiv=\"refresh\" content=\"0; URL=".CATSDIR."\"></head><body>You have Been Logged out<br /><a href=".CATSDIR."\"\">Back to Login</a></body>";
        exit;
    }
    $clinics = new Clinics($this->oApp);
    $s = $this->oTmpl->ExpandTmpl( 'cats_page',
        ['img_cats_logo'=>CATS_LOGO,
            'CATSDIR'=>CATSDIR,
            'CATSDIR_CSS'=>CATSDIR_CSS,
            'CATSDIR_JS'=>CATSDIR_JS,
            'CATSDIR_IMG'=>CATSDIR_IMG,
            'W_CORE_URL'=>W_CORE_URL,

            'ExtensionTmpl'=>@$GLOBALS["mappings"][$this->oHistory->getScreen()],
            'screen_name'=>$this->oHistory->getScreen(),
            'user_name'=>$this->oApp->sess->GetName(),
            'clinics'=>$clinics->displayUserClinics(), 'body' => $body ] );

        return( $s );
    }

}


class CATS_MainUI extends CATS_UI
{

    private $i = 0;

    function __construct( SEEDAppConsole $oApp )
    {
        parent::__construct( $oApp );
    }

    function Screen() {
        $screen = $this->oHistory->getScreen();

        if($screen == "goBack"){
            // restore the screen from two screens ago.
            // Since resoring the last screen will have no effect.
            // if the history array looks like this array("screen1","screen2","goBack")
            // then restoring one screen will take you back to screen2
            // while restoring two screens goes back to screen1
            $screen = $this->oHistory->restoreScreen(-2);
        }

        $s = $this->Header();
        $clinics = new Clinics($this->oApp);
        if($clinics->GetCurrentClinic() == NULL){
            $s .= "<h2>Please Select a clinic to continue</h2>"
                 .$clinics->displayUserClinics();
        }
        else if( substr($screen,0,9) == "developer" ) {
            $s .= $this->DrawDeveloper();
        }else if( substr( $screen, 0, 5 ) == 'admin' ) {
            $s .= $this->DrawAdmin();
        } else if( substr( $screen, 0, 9 ) == "therapist" ) {
            $s .= $this->DrawTherapist();
        } else if( substr($screen, 0, 6 ) == "leader" ){
            $s .= $this->DrawLeader();
        } else if( $screen == "logout" ) {
            $s .= $this->DrawLogout();
        } else if( $screen == "clinicImage"){
            //Revert the screen to the actual screen.
            //If we dont users will be stuck on this screen and have to know the screen name to escape.
            //This will be a problem since our screen names aren't exactly straightforward.
            $this->oHistory->restoreScreen();
            (new Clinics($this->oApp))->renderImage(SEEDInput_Int("imageID"),@$_REQUEST['clinic']);
        }else {
            $s .= $this->DrawHome();
        };

        return( $s );
    }


    function DrawHome()
    {
        $s = "<div class='container-fluid'>"
            .($this->oApp->sess->CanRead('therapist')     ? $this->DrawTherapist() : "")
            .($this->oApp->sess->CanRead('admin')         ? $this->DrawAdmin() : "")
            .($this->oApp->sess->CanRead('administrator') ? $this->DrawDeveloper() : "")
            // This Section allows Clinic Leaders to manage clinic specific settings
        .(!$this->oApp->sess->CanRead('administrator') && in_array((new Clinics($this->oApp))->GetCurrentClinic(),(new Clinics($this->oApp))->getClinicsILead())? $this->DrawLeader() : "")
            ."</div>";

            // Unset the mode var for resource download
            $this->oApp->sess->VarSet('resource-mode', 'replace');

        return( $s );
    }

    function DrawTherapist()
    {
        //Unimplemented Bubbles have been commented out to clean up display
        $raTherapistScreens = array(
            array( 'therapist-clientlist',      "Clients, Therapists, and External Providers" ),
            array( 'therapist-reports',         "Print Client Reports"),
            array( 'therapist-formscharts',     "Print Forms for Charts" ),
            array( 'therapist-handouts',        "Print Handouts" ),
            array( 'therapist-linedpapers',     "Print Different Lined Papers" ),
            array( 'therapist-submitresources', "Submit Resources to Share" ),
            array( 'therapist-assessments',     "Score Assessments"),
            //array( 'therapist-ideas',           "Get Ideas" ),
            array( 'therapist-materials',       "Download Marketable Materials" ),
            //array( 'therapist-documents',       "Documents" ),
            //array( 'therapist-team',            "Meet the Team" ),
            //array( 'therapist-calendar',        "Calendar" ),
            array( 'therapist-clinicresources', "Print Clinic Resources"),
            array( 'therapist-viewSOPs',        "View Standard Operating Procedures" )
        );

        $s = "";
        switch( $this->oHistory->getScreen() ) {
            case "therapist":
            default:
                $s .= $this->drawCircles( $raTherapistScreens );
                break;

            case "therapist-handouts":
                $s .= "<h3>Handouts</h3>"
                     .ResourcesDownload( $this->oApp, "handouts/" );
                break;
            case "therapist-formscharts":
                $s .= "<h3>Forms</h3>"
                     .ResourcesDownload( $this->oApp, "forms/" );
                break;
            case 'therapist-reports':
                $s .= "<h3>Reports</h3>"
                     .ResourcesDownload( $this->oApp, "reports/" );
                break;
            case "therapist-linedpapers":
                include("papers.php");
                break;
            case "therapist-entercharts":
                $s .= "ENTER CHARTS";
                break;
            case "therapist-ideas":
                $s .= "GET IDEAS";
                break;
            case "therapist-assessments":
                $s .= "<h3>Assessments</h3>"
                     .AssessmentsScore( $this->oApp );
                break;
            case "therapist-materials":
                $s .= "DOWNLOAD MATERIALS";
                break;
            case "therapist-documents":
                $s .= DocumentManager( $this->oApp );
                break;
            case "therapist-team":
                $s .= "MEET THE TEAM";
                break;
            case "therapist-submitresources":
                $s .= "SUBMIT RESOURCES";
                $s .= share_resources();
                break;
            case 'therapist-resources':
                $this->oHistory->removeFromHistory(-1);
                include('share_resorces_upload.php');
                break;
            case "therapist-clientlist":
                $o = new ClientList( $this->oApp );
                $s .= $o->DrawClientList();
                break;
            case "therapist-calendar":
                require_once CATSLIB."calendar.php";
                $o = new Calendar( $this->oApp );
                $s .= $o->DrawCalendar();
                break;
            case "therapist-clinicresources":
                $s .= "<h3>Clinic Resources</h3>"
                    .ResourcesDownload( $this->oApp, "clinic/", "n" );
                break;
            case "therapist-viewSOPs":
                $s .= viewSOPs($this->oApp);
                break;
        }
        return( $s );
    }

    function DrawAdmin()
    {
        $s = "";

        $oApp = $this->oApp;
        switch( $this->oHistory->getScreen() ) {
            case 'admin-users':
                $s .= $this->drawAdminUsers();
                break;
            case 'admin-resources':
                include('review_resources.php');
                break;
            case 'admin-manageresources':
                $s .= ManageResources($oApp);
                break;
            case 'admin-manageTNRS':
                $tnrs = new TagNameResolutionService($oApp->kfdb);
                $s .= $tnrs->listResolution();
                break;
            default:
                //Unimplemented Bubbles have been commented out to clean up display
                $raScreens = array(
                    //array( 'admin-users',            "Manage Users" ),
                    array( 'admin-resources',        "Review Resources" ),
                    array( 'admin-manageresources',  "Manage Resources "),
                    array( 'admin-manageTNRS',       "Manage Tag Name Resolution Service")
                );
                $s .= $this->drawCircles( $raScreens );

                break;
        }
        return( $s );
    }

    function DrawLogout()
    {
        $this->oApp->sess->LogoutSession();
        header( "Location: ".CATSDIR );
    }

    function DrawDeveloper(){
        $s = "";
        switch($this->oHistory->getScreen()){
            case 'developer-droptable':
                global $catsDefKFDB;
                $db = $catsDefKFDB['kfdbDatabase'];
                $oApp = $this->oApp;
                $oApp->kfdb->Execute("drop table $db.clients2");
                $oApp->kfdb->Execute("drop table $db.pros_internal");
                $oApp->kfdb->Execute("drop table $db.pros_external");
                $oApp->kfdb->Execute("drop table $db.clientsxpros");
// TODO remove soon
$oApp->kfdb->Execute("drop table $db.clients");
$oApp->kfdb->Execute("drop table $db.clients_pros");
$oApp->kfdb->Execute("drop table $db.professionals");
                $oApp->kfdb->Execute("drop table $db.SEEDSession_Users");
                $oApp->kfdb->Execute("drop table $db.SEEDSession_UsersMetadata");
                $oApp->kfdb->Execute("drop table $db.SEEDSession_Groups");
                $oApp->kfdb->Execute("drop table $db.SEEDSession_GroupsMetadata");
                $oApp->kfdb->Execute("drop table $db.SEEDSession_UsersXGroups");
                $oApp->kfdb->Execute("drop table $db.SEEDSession_Perms");
                $oApp->kfdb->Execute("drop table $db.cats_appointments");
                $oApp->kfdb->Execute("drop table $db.clinics");
                $oApp->kfdb->Execute("drop table $db.users_clinics");
                $s .= "<div class='alert alert-success'>Oops I miss placed your data</div>";
                break;
            case 'developer-clinics':
                $s .= (new Clinics($this->oApp))->manageClinics();
                break;
            case 'developer-confirmdrop':
                $s .= "<h3>Are you sure you want to drop the tables?</h3>"
                      ."<br /><h1>THIS CANNOT BE UNDONE</h1>"
                      ."<br /><a href='?screen=developer-droptable'><button>Yes</button></a>"
                      ."&nbsp&nbsp&nbsp&nbsp&nbsp<a href='?screen=home'><button>No</button></a>";
                      break;
            default:
                    $raScreens = array(
                        array( 'developer-confirmdrop',    "Drop Tables"    ),
                        array( 'developer-clinics',        "Manage Clinics" ),
                    );
                    $s .= $this->drawCircles( $raScreens );
        }
        return( $s );
    }

    public function drawLeader(){
        $s = "";
        switch ($this->oHistory->getScreen()){
            case "leader-clinic":
                $s .= (new Clinics($this->oApp))->manageClinics();
                break;
            default:
                $raScreens = array(
                    array( 'leader-clinic',     "Manage Clinic")
                );
                $s .= $this->drawCircles( $raScreens );
        }
        return( $s );
    }

    private function drawCircles( $raScreens )
    {
        $s = "";
        foreach( $raScreens as $ra ) {
            $circle = "catsCircle".($this->i % 2 + 1);

            if( $this->i % 4 == 0 ) $s .= "<div class='row'>";
            $s .= "<div class='col-md-3'><a href='?screen={$ra[0]}' class='toCircle $circle'>{$ra[1]}</a></div>";
            if( $this->i % 4 == 3 ) $s .= "</div>";   // row
            ++$this->i;
        }

        return( $s );
    }

    private function drawAdminUsers()
    {
        $o = new UsersGroupsPermsUI( $this->oApp );
        return( $o->DrawUI() );
    }
}

class ScreenManager{

    private $oApp;
    private $screens;

    public function __construct(SEEDAppSessionAccount $oApp, bool $bLoadFromSession = TRUE){
        $this->oApp = $oApp;
        if ($bLoadFromSession){
            $this->load();
        }
    }

    public function load($screens = NULL){
        if($screens == NULL){
            $this->screens = @$_SESSION['screenHistory']?:array();
            $screen = $this->oApp->sess->SmartGPC("screen", array("home"));
            if($screen != $this->getFromHistory(-1)){
                $this->addToHisory($screen);
            }
        }
        else{
            $this->screens = $screens;
        }
    }

    public function getScreen():String{
        return $this->getFromHistory(-1);
    }

    /**
     * Restore the screen to a screen in the history array
     * The array is wiped such that the screen restored to is the last element in the array
     * @param int $history - index of the screen to restore
     * @return string - screen name of the screen restored to
     */
    public function restoreScreen(int $history = -1){
        $screen = $this->getFromHistory($history-1);
        $this->oApp->sess->VarSet("screen", $screen);
        array_splice($this->screens, $history);
        $this->store();
        return $screen;
    }

    /**
     * Remove the entry from the screen history array
     * @param int $index - The index of the screen to remove
     * Negative values will remove from the end of the array
     * This method does not revert the session variable. It only removes the screen from history
     * to resore the screen use restoreScreen($history)
     */
    public function removeFromHistory(int $index){
        array_splice($this->screens, $index, 1);
        $this->store();
    }

    private function getFromHistory(int $index){
        //Default to home if the index requested does not exist
        if($index < 0){
            $index = count($this->screens)+$index;
        }
        return @$this->screens[$index]?:"home";
    }

    private function addToHisory($screen, $location = NULL){
        if($location == NULL){
            array_push($this->screens, $screen);
        }
        else{
            array_splice($this->screens, $location, 0, $screen);
        }
        $this->store();
    }

    private function store(){
        $_SESSION['screenHistory'] = $this->screens;
    }

}

class UsersGroupsPermsUI
{
    private $oApp;
    private $oAcctDB;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->oAcctDB = new SEEDSessionAccountDBRead2( $this->oApp->kfdb, $this->oApp->sess->GetUID(), array('logdir'=>$this->oApp->logdir) );
    }

    function DrawUI()
    {
        $s = "";

        $mode = $this->oApp->oC->oSVA->SmartGPC( 'adminUsersMode', array('Users','Groups','Permissions') );

        $raListParms = array( 'bUse_key' => true );

        switch( $mode ) {
            case "Users":
                $cid = "U";
                $kfrel = $this->oAcctDB->GetKfrel('U');
                $raListParms['cols'] = array(
                    array( 'label'=>'User #',  'col'=>'_key' ),
                    array( 'label'=>'Name',    'col'=>'realname' ),
                    array( 'label'=>'Email',   'col'=>'email'  ),
                    array( 'label'=>'Status',  'col'=>'eStatus'  ),
                    array( 'label'=>'Group1',  'col'=>'G_groupname'  ),
                );
                $raListParms['fnRowTranslate'] = array($this,"usersListRowTranslate");
                // Not the same format as listcols because these actually need the column names not aliases.
                // For groups and perms it happens to work but when _key is included in the WHERE it is ambiguous
                $raSrchParms['filters'] = array(
                    array( 'label'=>'User #',  'col'=>'U._key' ),
                    array( 'label'=>'Name',    'col'=>'U.realname' ),
                    array( 'label'=>'Email',   'col'=>'U.email'  ),
                    array( 'label'=>'Status',  'col'=>'U.eStatus'  ),
                    array( 'label'=>'Group1',  'col'=>'G.groupname'  ),
                );
                $formTemplate = $this->getUsersFormTemplate();
                break;
            case "Groups":
                $cid = "G";
                $kfrel = $this->oAcctDB->GetKfrel('G');
                $raListParms['cols'] = array(
                    array( 'label'=>'k',          'col'=>'_key' ),
                    array( 'label'=>'Group Name', 'col'=>'groupname'  ),
                    array( 'label'=>'Inherited',  'col'=>'gid_inherited'  ),
                );
                $raSrchParms['filters'] = $raListParms['cols'];     // conveniently the same format
                $formTemplate = $this->getGroupsFormTemplate();
                break;
            case "Permissions":
                $cid = "P";
                $kfrel = $this->oAcctDB->GetKfrel('P');
                $raListParms['cols'] = array(
                    array( 'label'=>'Permission', 'col'=>'perm'  ),
                    array( 'label'=>'Modes',      'col'=>'modes'  ),
                    array( 'label'=>'User',       'col'=>'U_realname'  ),
                    array( 'label'=>'Group',      'col'=>'G_groupname'  ),
                );
                $raSrchParms['filters'] = $raListParms['cols'];     // conveniently the same format
                $formTemplate = $this->getPermsFormTemplate();
                break;
        }

        $oUI = new MySEEDUI( $this->oApp, "Stegosaurus" );
        $oComp = new KeyframeUIComponent( $oUI, $kfrel, $cid );
        $oComp->Update();

//$this->oApp->kfdb->SetDebug(2);
        $oList = new KeyframeUIWidget_List( $oComp );
        $oSrch = new SEEDUIWidget_SearchControl( $oComp, $raSrchParms );
        $oForm = new KeyframeUIWidget_Form( $oComp, array('sTemplate'=>$formTemplate) );

        $oComp->Start();    // call this after the widgets are registered

        list($oView,$raWindowRows) = $oComp->GetViewWindow();
        $sList = $oList->ListDrawInteractive( $raWindowRows, $raListParms );

        $sSrch = $oSrch->Draw();
        $sForm = $oForm->Draw();

        // Have to do this after Start() because it can change things like kCurr
        switch( $mode ) {
            case 'Users':       $sInfo = $this->drawUsersInfo( $oComp );    break;
            case 'Groups':      $sInfo = $this->drawGroupsInfo( $oComp );   break;
            case 'Permissions': $sInfo = $this->drawPermsInfo( $oComp );    break;
        }

        $s = $oList->Style()
            ."<form method='post'>"
                ."<input type='submit' name='adminUsersMode' value='Users'/>&nbsp;&nbsp;"
                ."<input type='submit' name='adminUsersMode' value='Groups'/>&nbsp;&nbsp;"
                ."<input type='submit' name='adminUsersMode' value='Permissions'/>"
            ."</form>"
            ."<h2>$mode</h2>"
            ."<div class='container-fluid'>"
                ."<div class='row'>"
                    ."<div class='col-md-6'>"
                        ."<div>".$sSrch."</div>"
                        ."<div>".$sList."</div>"
                    ."</div>"
                    ."<div class='col-md-6'>"
                        ."<div style='width:90%;padding:20px;border:2px solid #999'>".$sForm."</div>"
                    ."</div>"
                ."</div>"
                .$sInfo
            ."</div>";


//         $s .= "<div class='seedjx' seedjx-cmd='test'>"
//                  ."<div class='seedjx-err alert alert-danger' style='display:none'></div>"
//                  ."<div class='seedjx-out'>"
//                      ."<input name='a'/>"
//                      ."<select name='test'/><option value='good'>Good</option><option value='bad'>Bad</option></select>"
//                      ."<button class='seedjx-submit'>Go</button>"
//                  ."</div>"
//              ."</div>";

        return( $s );
    }

    private function drawUsersInfo( KeyframeUIComponent $oComp )
    {
        $s = "";
        $s .= $this->ugpStyle();

        if( !($kUser = $oComp->Get_kCurr()) )  goto done;

        $raGroups = $this->oAcctDB->GetGroupsFromUser( $kUser, array('bNames'=>true) );
        $raPerms = $this->oAcctDB->GetPermsFromUser( $kUser );
        $raMetadata = $this->oAcctDB->GetUserMetadata( $kUser );

        /* Groups list
         */
        $sG = "<p><b>Groups</b></p>"
             ."<div class='ugpBox'>";
        foreach( $raGroups as $kGroup => $sGroupname ) {
            $sG .= "$sGroupname &nbsp;<span style='float:right'>($kGroup)</span><br/>";
        }
        $sG .= "</div>";

        // group add/remove
        $oFormB = new SEEDCoreForm( "B" );
        $sG .= "<div>"
              ."<form action='{$_SERVER['PHP_SELF']}' method='post'>"
              //.$this->oComp->EncodeHiddenFormParms()
              //.SEEDForm_Hidden( 'uid', $kUser )
              //.SEEDForm_Hidden( 'form', "UsersXGroups" )
              .$oFormB->Text( 'gid', '' )
              ."<input type='submit' name='cmd' value='Add'/><INPUT type='submit' name='cmd' value='Remove'/>"
              ."</form></div>";


        /* Perms list
         */
        $sP = "<p><b>Permissions</b></p>"
             ."<div class='ugpBox'>";
        ksort($raPerms['perm2modes']);
        foreach( $raPerms['perm2modes'] as $k => $v ) {
            $sP .= "$k &nbsp;<span style='float:right'>( $v )</span><br/>";
        }
        $sP .= "</div>";


        /* Metadata list
         */
        $sM = "<p><b>Metadata</b></p>"
             ."<div class='ugpBox'>";
        foreach( $raMetadata as $k => $v ) {
            $sM .= "$k &nbsp;<span style='float:right'>( $v )</span><br/>";
        }
        $sM .= "</div>";
/*
            // Metadata Add/Remove
            $s .= "<BR/>"
                 ."<FORM action='{$_SERVER['PHP_SELF']}' method='post'>"
                 .$this->oComp->EncodeHiddenFormParms()
                 .SEEDForm_Hidden( 'uid', $kUser )
                 .SEEDForm_Hidden( 'form', "UsersMetadata" )
                 ."k ".SEEDForm_Text( 'meta_k', '' )
                 ."<br/>"
                 ."v ".SEEDForm_Text( 'meta_v', '' )
                 ."<INPUT type='submit' name='cmd' value='Set'/><INPUT type='submit' name='cmd' value='Remove'/>"
                 ."</FORM></TD>";
*/

        $s .= "<div class='row'>"
                 ."<div class='col-md-4'>$sG</div>"
                 ."<div class='col-md-4'>$sP</div>"
                 ."<div class='col-md-4'>$sM</div>"
             ."</div>";

        done:
        return( $s );
    }

    private function drawGroupsInfo( KeyframeUIComponent $oComp )
    {
        $s = "";

        return( $s );
    }

    private function drawPermsInfo( KeyframeUIComponent $oComp )
    {
        $s = "";

        return( $s );

    }

    function ugpStyle()
    {
        $s = "<style>"
             .".ugpForm { font-size:14px; }"
             .".ugpBox { height:200px; border:1px solid gray; padding:3px; font-family:sans serif; font-size:11pt; overflow-y:scroll }"
            ."</style>";
        return( $s );
    }

    private function getUsersFormTemplate()
    {
        $s = "|||BOOTSTRAP_TABLE(class='col-md-6',class='col-md-6')\n"
            ."||| User #|| [[Text:_key | readonly]]\n"
            ."||| Name  || [[Text:realname]]\n"
            ."||| Email || [[Text:email]]\n"
            ."||| Status|| <select name='eStatus'>".$this->getUserStatusSelectionFormTemplate()."</select>\n"
            ."||| Group || ".$this->getSelectTemplate("SEEDSession_Groups", "gid1", "groupname")."\n"
            ."||| <input type='submit'>"
                ;

        return( $s );
    }

    private function getGroupsFormTemplate()
    {
        $s = "|||BOOTSTRAP_TABLE(class='col-md-6',class='col-md-6')\n"
            ."||| Name            || [[Text:groupname]]\n"
                ."||| Inherited Group || ".$this->getSelectTemplate("SEEDSession_Groups", "gid_inherited", "groupname", TRUE)."\n"
                    ."||| <input type='submit'>"
                ;

        return( $s );
    }

    private function getPermsFormTemplate()
    {
        $s = "|||BOOTSTRAP_TABLE(class='col-md-6',class='col-md-6')\n"
            ."||| Name  || [[Text:perm]]\n"
            ."||| Mode  || [[Text:modes]]\n"
            ."||| User  || ".$this->getSelectTemplate("SEEDSession_Users", "uid", "realname", TRUE)."\n"
            ."||| Group || ".$this->getSelectTemplate("SEEDSession_Groups", "gid", "groupname", TRUE)."\n"
            ."||| <input type='submit'>"
                ;

        return( $s );
    }

    private function getSelectTemplate($table, $col, $name, $bEmpty = FALSE)
    /****************************************************
     * Generate a template of that defines a select element
     *
     * table - The database table to get the options from
     * col - The database collum that the options are associated with.
     * name - The database collum that contains the user understandable name for the option
     * bEmpty - If a None option with value of NULL should be included in the select
     *
     * eg. table = SEEDSession_Groups, col = gid, name = groupname
     * will result a select element with the groups as options with the gid of kfrel as the selected option
     */
    {
        $options = $this->oApp->kfdb->QueryRowsRA("SELECT * FROM ".$table);
        $s = "<select name='".$col."'>";
        if($bEmpty){
            $s .= "<option value='NULL'>None</option>";
        }
        foreach($options as $option){
            $s .= "<option [[ifeq:[[value:".$col."]]|".$option["_key"]."|selected| ]] value='".$option["_key"]."'>".$option[$name]."</option>";
        }
        $s .= "</select>";
        return $s;
    }

    private function getUserStatusSelectionFormTemplate(){
        global $catsDefKFDB;
        $db = $catsDefKFDB['kfdbDatabase'];
        $options = $this->oApp->kfdb->Query1("SELECT SUBSTRING(COLUMN_TYPE,5) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='".$db."' AND TABLE_NAME='SEEDSession_Users' AND COLUMN_NAME='eStatus'");
        $options = substr($options, 1,strlen($options)-2);
        $options_array = str_getcsv($options, ',', "'");
        $s = "";
        foreach($options_array as $option){
            $s .= "<option [[ifeq:[[value:eStatus]]|".$option."|selected| ]]>".$option."</option>";
        }
        return $s;
    }

    function usersListRowTranslate( $raRow )
    {
        if( $raRow['gid1'] && $raRow['G_groupname'] ) {
            // When displaying the group name it's helpful to show the gid too
            $raRow['G_groupname'] .= " (".$raRow['gid1'].")";
        }

        return( $raRow );
    }

}


require_once SEEDCORE."SEEDUI.php";
class MySEEDUI extends SEEDUI
{
    private $oSVA;

    function __construct( SEEDAppSession $oApp, $sApplication )
    {
        parent::__construct();
        $this->oSVA = new SEEDSessionVarAccessor( $oApp->sess, $sApplication );
    }

    function GetUIParm( $cid, $name )      { return( $this->oSVA->VarGet( "$cid|$name" ) ); }
    function SetUIParm( $cid, $name, $v )  { $this->oSVA->VarSet( "$cid|$name", $v ); }
    function ExistsUIParm( $cid, $name )   { return( $this->oSVA->VarIsSet( "$cid|$name" ) ); }
}


class KeyframeUIComponent extends SEEDUIComponent
{
    private $kfrel;
    private $raViewParms = array();

    function __construct( SEEDUI $o, Keyframe_Relation $kfrel, $cid = "A", $raCompConfig = array() )
    {
         $this->kfrel = $kfrel;     // set this before the parent::construct because that uses the factory_SEEDForm
         parent::__construct( $o, $cid, $raCompConfig );
    }

    protected function factory_SEEDForm( $cid, $raSFParms )
    {
        // Any widget can find this KeyframeForm at $this->oComp->oForm
        return( new KeyframeForm( $this->kfrel, $cid, $raSFParms ) );
    }

    function Start()
    {
        parent::Start();

        /* Now the Component is all set up with its uiparms and widgets, but the oForm is not initialized to
         * the current key (unless it got loaded during Update).
         */

        if( $this->Get_kCurr() && ($kfr = $this->kfrel->GetRecordFromDBKey($this->Get_kCurr())) ) {
            $this->oForm->SetKFR( $kfr );
        }
    }

    function GetViewWindow()
    {
        $raViewParms = array();

        $raViewParms['sSortCol']  = $this->GetUIParm('sSortCol');
        $raViewParms['bSortDown'] = $this->GetUIParm('bSortDown');
        $raViewParms['sGroupCol'] = $this->GetUIParm('sGroupCol');
        $raViewParms['iStatus']   = $this->GetUIParm('iStatus');

        $oView = new KeyframeRelationView( $this->kfrel, $this->sSqlCond, $raViewParms );
        $raWindowRows = $oView->GetDataWindowRA( $this->Get_iWindowOffset(), $this->Get_nWindowSize() );
        return( array( $oView, $raWindowRows ) );
    }
}

class KeyframeUIWidget_List extends SEEDUIWidget_List
{
    function __construct( KeyframeUIComponent $oComp, $raConfig = array() )
    {
        parent::__construct( $oComp, $raConfig );
    }
}

class KeyframeUIWidget_Form extends SEEDUIWidget_Form
{
    function __construct( KeyframeUIComponent $oComp, $raConfig = array() )
    {
        parent::__construct( $oComp, $raConfig );
    }

    function Draw()
    {
        $s = "";

        if( $this->oComp->oForm->GetKey() ) {
            $o = new SEEDFormExpand( $this->oComp->oForm );
            $s = $o->ExpandForm( $this->raConfig['sTemplate'] );
        }
        return( $s );
    }
}


?>