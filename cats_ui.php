<?php
include_once('twig_mappings.php');
include_once('view_resources.php');
include_once('share_resources.php');
include_once('Clinics.php');
include_once( SEEDCORE."SEEDTemplateMaker.php" );
include_once( SEEDLIB."SEEDUGP.php" );

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
        if( !$this->oApp->sess->IsLogin() ) {
            echo "<head><meta http-equiv=\"refresh\" content=\"0; URL=".CATSDIR."\"></head><body>You have Been Logged out<br /><a href=".CATSDIR."\"\">Back to Login</a></body>";
            exit;
        }

        $body .=
            "<script> SEEDCore_CleanBrowserAddress(); </script>"
             .($this->oHistory->getScreen()=="home"?"<script> run(); </script>":"");

        $clinics = new Clinics($this->oApp);
        $imagePath = $clinics->getImage(Clinics::LOGO_WIDE);
        $s = $this->oTmpl->ExpandTmpl( 'cats_page',
            ['img_cats_logo'=>base64_encode(getImageData($imagePath, IMAGETYPE_PNG)),
                'CATSDIR'=>CATSDIR,
                'CATSDIR_CSS'=>CATSDIR_CSS,
                'CATSDIR_JS'=>CATSDIR_JS,
                'CATSDIR_IMG'=>CATSDIR_IMG,
                'CATSDIR_AKAUNTING'=>CATSDIR_AKAUNTING,
                'W_CORE_URL'=>W_CORE_URL,
                'ConsoleUserMsg'=>$this->oApp->oC->GetUserMsg(),
                'ConsoleErrMsg'=>$this->oApp->oC->GetErrMsg(),

                'ExtensionTmpl'=>@$GLOBALS["mappings"][$this->oHistory->getScreen()],
                'screen_name'=>$this->oHistory->getScreen(),
                'user_name'=>$this->oApp->sess->GetName(),
                'clinics'=>$clinics->displayUserClinics(), 'body' => $body

            ] );

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
            $s .= "<div style='margin:auto; width:33%; padding: 10px; padding-top: 0px; margin-top:10em;'><h2>Please Select a clinic to continue</h2>"
                 .$clinics->displayUserClinics(true)
                 ."</div>";
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
        }else if($this->pswdIsTemporary()){
            $s .= <<<ResetPassword
<form style='margin:auto;border:1px solid gray; width:33%; padding: 10px; padding-top: 0px; border-radius:10px; margin-top:10em;' method='post'>
         You used a temporary password to login.<br />Please enter a new password to continue.
         <br />We recommend using a strong password that will also be easy for you to remember.
         <br /><br />
         <input type='password' placeholder='Password' style='display:block; font-family: \"Lato\", sans-serif; font-weight: 400; margin:auto; border-radius:5px; border-style: inset outset outset inset;' name='new_pswd' />
         <br />
         <input type='password' placeholder='Confirm Password' style='display:block; font-family: \"Lato\", sans-serif; font-weight: 400; margin:auto; border-radius:5px; border-style: inset outset outset inset;' name='confirm_pswd' />
         <br />
         <input type='submit' value='Change Password' style='border-style: inset outset outset inset; font-family: \"Lato\", sans-serif; font-weight: 400; border-radius:5px; display:block; margin:auto;' />
         </form>"
ResetPassword;
        }
        else if($screen == "documentation" && CATS_DEBUG){
            $s .= $this->drawDocumentation();
        }
        else if($screen == "placeholders"){
            $s .= $this->drawDocumentation();
        }
        else {
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
            .$this->DrawDocumentation()
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
            array( 'therapist-viewSOPs',        "View Standard Operating Procedures" ),
            array( 'therapist-akaunting',       "Akaunting" ),
            array( 'therapist-distributeReports', "Distribute Reports" )
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
            case "therapist-akaunting":
                require_once CATSLIB."AkauntingReports.php";
                $s .= AkauntingReport($this->oApp);
                break;
            case "therapist-distributeReports":
                require_once CATSLIB."DistributeReports.php";
                $s .= distributeReports($this->oApp);
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
                    array( 'admin-users',            "Manage Users" ),
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
                global $config_KFDB;
                $db = $config_KFDB['cats']['kfdbDatabase'];
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
            case 'developer-SEEDBasket':
                include_once( SEEDAPP."basket/basketManager.php" );
                $s .= SEEDBasketManagerApp( $this->oApp );
                break;
            default:
                    $raScreens = array(
                        array( 'developer-confirmdrop',    "Drop Tables"    ),
                        array( 'developer-clinics',        "Manage Clinics" ),
                    );
                    if( CATS_DEBUG ) {
                        $raScreens[] = ['developer-SEEDBasket', "Temporary SEEDBasket Development"];
                    }
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

    public function drawDocumentation(){
        $s = "";
        switch ($this->oHistory->getScreen()){
            case "documentation":
                require_once 'Documentation.php';
                $documentation = new Documentation();
                $s .= $documentation->handleDocs();
                break;
            case "placeholders":
                require_once 'Documentation.php';
                $placeholders = new Placeholders();
                $s .= $placeholders->drawPlaceholderList();
                break;
            default:
                $raScreens = array(
                array( 'documentation',     "View Documentation"),
                array( 'placeholders' ,     "Download Placeholder Images")
                );
                if(!CATS_DEBUG){
                    unset($raScreens['documentation']);
                }
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

    private function pswdIsTemporary(){
        $accountDB = new SEEDSessionAccountDB($this->oApp->kfdb,$this->oApp->sess->GetUID());
        if(($newPswd = @$_POST['new_pswd']) && ($confirmPswd = @$_POST['confirm_pswd'])){
            if($newPswd === $confirmPswd){
                $accountDB->ChangeUserPassword($this->oApp->sess->GetUID(), $newPswd);
                $this->oApp->oC->AddUserMsg("Password Changed");
            }
            else{
                $this->oApp->oC->AddErrMsg("Passwords dont match");
            }
        }
        @list($fname,$lname) = explode(" ", $this->oApp->sess->GetName());
        $lname = $lname?:"";
        $email = $this->oApp->sess->GetEmail();
        $pswd = $accountDB->GetUserInfo($this->oApp->sess->GetUID())[1]["password"];
        return $pswd=="cats" && strtolower($email) == strtolower(substr($fname, 0,1).$lname);
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
