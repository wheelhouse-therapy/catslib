<?php
include_once('twig_mappings.php');
include_once('view_resources.php');
include_once('Clinics.php');
include_once( SEEDCORE."SEEDTemplateMaker.php" );
include_once( SEEDLIB."SEEDUGP.php" );
include_once 'tutorial.php';

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
        global $mappings;
        if( !$this->oApp->sess->IsLogin() ) {
            echo "<head><meta http-equiv=\"refresh\" content=\"0; URL=".CATSDIR."\"></head><body>You have Been Logged out<br /><a href=".CATSDIR."\"\">Back to Login</a></body>";
            exit;
        }

        $body .= TutorialManager::runTutorial($this->oApp, $this->oHistory->getScreen());

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

                'ExtensionTmpl'=>@$mappings[$this->oHistory->getScreen()],
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
        if( $screen == "logout" ) {     // put this one first so you can logout if you have no clinics defined (so you get stuck in the next case)
            $s .= $this->DrawLogout();
        } else if($clinics->GetCurrentClinic() == NULL){
            $s .= "<div style='margin:auto; width:33%; padding: 10px; padding-top: 0px; margin-top:10em;'><h2>Please Select a clinic to continue</h2>"
                 .$clinics->displayUserClinics(true)
                 ."</div>";
        }
        else if( substr($screen, 0,6) == "system"){
            $s .= $this->drawSystem();
        }
        else if( substr($screen,0,13) == "administrator" ) {
            $s .= $this->DrawDeveloper();
        }
        else if( substr( $screen, 0, 5 ) == 'admin' ) {
            $s .= $this->DrawAdmin();
        }
        else if( substr( $screen, 0, 9 ) == "therapist" ) {
            $s .= $this->DrawTherapist();
        }
        else if( substr($screen, 0, 6 ) == "leader" ){
            $s .= $this->DrawLeader();
        }
        else if( $screen == "clinicImage"){
            //Revert the screen to the actual screen.
            //If we dont users will be stuck on this screen and have to know the screen name to escape.
            //This will be a problem since our screen names aren't exactly straightforward.
            $this->oHistory->restoreScreen();
            (new Clinics($this->oApp))->renderImage(SEEDInput_Int("imageID"),@$_REQUEST['clinic']);
        }
        else if($this->pswdIsTemporary()){
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
        else {
            $s .= $this->DrawHome();
        };

        return( $s );
    }


    function DrawHome()
    {
        $s = "<div id='bubbles' class='container-fluid'>"
            .($this->oApp->sess->CanRead('therapist')     ? $this->DrawTherapist() : "")
            .($this->oApp->sess->CanRead('admin')         ? $this->DrawAdmin()     : "")
            .($this->oApp->sess->CanRead('administrator') ? $this->DrawDeveloper() : "")
            // This Section allows Clinic Leaders to manage clinic specific settings
            .(!CATS_SYSADMIN && in_array((new Clinics($this->oApp))->GetCurrentClinic(),(new Clinics($this->oApp))->getClinicsILead())? $this->DrawLeader() : "")
            .$this->DrawSystem()
            ."</div>";
            $s .= "
        <!-- the div that represents the modal dialog -->
        <div class=\"modal\" id=\"menu_dialog\" role=\"dialog\">
            <div class=\"modal-dialog modal-lg modal-dialog-centered\" style='max-width:1140px' role=\"document\">
                <div class=\"modal-content\">
                    <div class=\"modal-body\">
                        <div class='container-fluid' id='menu_body'>
                        </div>
                    </div>
                </div>
            </div>
        </div>";

            // Unset the mode var for resource download
            $this->oApp->sess->VarSet('resource-mode', 'replace');
            // Unset the assessement filter
            $this->oApp->sess->VarSet("client_key", 0);
            // Unset the drawer of the filing cabinet.
            $this->oApp->sess->VarUnSet("dir");

        return( $s );
    }

    function DrawTherapist()
    {
        //Unimplemented Bubbles have been commented out to clean up display
        $raTherapistScreens = array(
            array( 'therapist-clientlist',      "Client list / External Provider list" ),
            array( 'therapist-filing-cabinet',  "Filing Cabinet"),
            array( 'therapist-reports',         "Print Client Reports"),
            array( 'therapist-assessments',     "Score Assessments"),
            //array( 'therapist-team',            "Meet the Team" ),
            //array( 'therapist-calendar',        "Calendar" ),
            //array( 'therapist-viewSOPs',        "View Standard Operating Procedures" ),
            array( 'therapist-viewVideos',      "CATS College", "Learning!" ),
            //array( 'therapist-akaunting',       "Akaunting" ),
            array( 'therapist-distributeReports', "Distribute Reports" ),
            array( 'link:https://www.catherapyservices.ca/webmail', "Access Webmail" )
        );

        $s = "";
        switch( $this->oHistory->getScreen() ) {
            case "therapist":
            default:
                $s .= $this->drawCircles( $raTherapistScreens, "therapist" );
                break;

            case 'therapist-filing-cabinet':
                $oFC = new FilingCabinetUI( $this->oApp, 'general' );
                $s .= $oFC->DrawFilingCabinet();
                break;
            case 'therapist-reports':
                $s .= "<h3>Reports</h3>"
                     .ResourcesDownload( $this->oApp, "reports/", 'reports' );
                break;
            case "therapist-assessments":
                $s .= "<h3>Assessments</h3>"
                     .AssessmentsScore( $this->oApp );
                break;
            case "therapist-materials":
                $s .= "DOWNLOAD MATERIALS";
                break;
            case "therapist-team":
                $s .= "MEET THE TEAM";
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
//            case "therapist-clinicresources":
//                $s .= "<h3>Clinic Resources</h3>"
//                    .ResourcesDownload( $this->oApp, "clinic/", "n" );
                break;
            case "therapist-viewSOPs":
                $s .= viewSOPs($this->oApp);
                break;
            case "therapist-viewVideos":
                $oFC = new FilingCabinetUI( $this->oApp, 'videos' );
                $s .= $oFC->DrawFilingCabinet();
                break;
            case "therapist-akaunting":
                require_once CATSLIB."AkauntingReports.php";
                $s .= AkauntingReport($this->oApp);
                break;
            case "therapist-distributeReports":
                require_once CATSLIB."DistributeReports.php";
                $s .= distributeReports($this->oApp);
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
                require_once 'manage_users.php';
                $manageUsers = new ManageUsers($this->oApp);
                if(CATS_SYSADMIN){
                    $s .= "<div style='float:right'><a href='".CATSDIR."?screen=admin-users-advanced'>advanced mode ></a></div>";
                }
                $s .= $manageUsers->drawList();
                break;
            case 'admin-users-advanced':
                if(CATS_SYSADMIN){
                    $s .= "<div><a href='".CATSDIR."?screen=admin-users'>< basic mode</a></div><br />";
                }
                $s .= $this->drawAdminUsers();
                break;
            case 'admin-resources':
                $oF = new FilingCabinetReview($oApp);
                $s .= $oF->DrawReviewUI();
                break;
            case 'admin-manageresources':
                $s .= ManageResources($oApp);
                break;
            case 'admin-manageTNRS':
                $tnrs = new TagNameResolutionService($oApp->kfdb);
                $s .= $tnrs->listResolution();
                break;
            case 'admin-analysis':
                $oFA = new FilingCabinetAnalysis($this->oApp);
                $s .= $oFA->DrawAnalysisUI();
                break;
            case "admin":
                $raScreens = array(
                    array( 'admin-users',            "Manage Users" ),
                    array( 'admin-manageresources',  "Manage Resources "),
                    array( 'admin-resources',        "Review Resources" ),
                    array( 'admin-analysis',         "View Resource Analysis"),
                    array( 'admin-manageTNRS',       "Manage Tag Name Resolution Service")
                );
                $s .= $this->drawCircles( $raScreens, "admin" );
                break;
            default:
                $raScreens = [["menu:admin","Admin Tools"]];
                $s .= $this->drawCircles($raScreens, "admin");
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
            case 'administrator-droptable':
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
            case 'administrator-clinics':
                $s .= (new Clinics($this->oApp))->manageClinics();
                break;
            case 'administrator-confirmdrop':
                $s .= "<h3>Are you sure you want to drop the tables?</h3>"
                      ."<br /><h1>THIS CANNOT BE UNDONE</h1>"
                      ."<br /><a href='?screen=developer-droptable'><button>Yes</button></a>"
                      ."&nbsp&nbsp&nbsp&nbsp&nbsp<a href='?screen=home'><button>No</button></a>";
                      break;
            case 'administrator-users':
                $manageUsers = new ManageUsers2($this->oApp);
                $s .= $manageUsers->drawUI();
                break;
            case 'administrator':
                    $raScreens = array(
                        array( 'administrator-confirmdrop',    "Drop Tables"    ),
                        array( 'administrator-clinics',        "Manage Clinics" ),
                        array( 'administrator-users',          "Manage Users (v2)"),
                    );
                    $s .= $this->drawCircles( $raScreens, "developer" );
                    break;
            default:
                $raScreens = [['menu:administrator',"Developer Tools","418 I'm a teapot"]];
                $s .= $this->drawCircles($raScreens, "developer");
                break;
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
                $s .= $this->drawCircles( $raScreens, "leader" );

        }
        return( $s );
    }

    public function drawSystem(){
        $s = "";
        switch ($this->oHistory->getScreen()){
            case "system-documentation":
                require_once 'Documentation.php';
                $documentation = new Documentation();
                $s .= $documentation->handleDocs($this->oApp);
                break;
            case "system-placeholders":
                require_once 'Documentation.php';
                $placeholders = new Placeholders();
                $s .= $placeholders->drawPlaceholderList();
                break;
            case "system":
                $raScreens = array(
                array( 'system-documentation',     "View Documentation"),
                array( 'system-placeholders' ,     "Placeholder Images"),
                array( 'system-footergenerator',   "Generate Clinic Footer"),
                array( 'system-usersettings',      "My Profile")
                );
                $s .= $this->drawCircles($raScreens, "system");
                break;
            case "system-footergenerator":
                $gen = new ImageGenerator($this->oApp);
                $s .= $gen->footerOptions();
                break;
            case "system-usersettings":
                $s .= "<h2>My Profile</h2>";
                $clone = SEEDInput_Str("clone")?true:false;
                $manageUsers = new ManageUsers($this->oApp);
                $s .= $manageUsers->userSettings($this->oApp->sess->getUID(),$clone);
                break;
            default:
                $s .= $this->drawCircles([['menu:system',"Access System Resources"]], "system");
        }
        return( $s );
    }

    /**
     * Get any badges for a section/menu.
     * Valid sections/menus have an associated draw method above.
     * Menus should display a badge with the sum of the badges beneath it
     * @param String $section - section to get badges for
     * @return array with keys of screens with badges and the keys of the numbers to show
     */
    public function GetBadges(String $section){
        switch($section){
            case 'therapist':
                return [];
            case 'admin':
                FilingCabinet::EnsureDirectory("pending");
                $toReview = array_diff(scandir(CATSDIR_RESOURCES."pending/"), array('..', '.'));
                return ['admin-resources' => count($toReview)];
            case 'developer':
                return [];
            case 'leader':
                return [];
            case 'system':
                $manageUsers = new ManageUsers($this->oApp);
                return ['system-usersettings' => $manageUsers->profileValid($this->oApp->sess->GetUID())?"":"!"];
            default:
                return [];
        }
    }

    private function drawCircles( array $raScreens, String $section )
    {
        $s = "";
        $badges = $this->GetBadges($section);
        foreach( $raScreens as $ra ) {
            $circle = "catsCircle".($this->i % 2 + 1);

            if( $this->i % 4 == 0 ) $s .= "<div class='row'>";
            $href = "";
            $target = "";
            $title = "";
            $id = $ra[0];
            $badge = "";
            if (SEEDCore_StartsWith($ra[0], "link:")) {
                $href = substr($ra[0], 5);
                $target = " target='_blank'";
                $id = substr($ra[0], strrpos($ra[0], "/"));
            }
            elseif (SEEDCore_StartsWith($ra[0], "menu:")) {
                $href = "#";
                $target = " onclick='loadMenu(\"".substr($ra[0], 5)."\");return false;'";
                $title = " title='Open menu'";
                $id = substr($ra[0], 5);
                $badgeCount = array_sum($this->GetBadges($id));
                if($badgeCount > 0 || in_array("!", $this->GetBadges($id),true)){
                    if(in_array("!", $this->GetBadges($id),true)){
                        $badgeCount = "!";
                    }
                    $badge = "<span class='badge'>$badgeCount</span>";
                }
            } else {
                $href = "?screen=".$ra[0];
            }
            if(@$ra[2]){
                // The optional title has been set
                $ra[2] = SEEDCore_HSC($ra[2]); //Allow for use of ' in title
                $title = " title='{$ra[2]}'";
            }
            if(array_key_exists($ra[0], $badges)){
                $badgeCount = $badges[$ra[0]];
                if($badgeCount > 0 || $badgeCount === "!"){
                    $badge = "<span class='badge'>$badgeCount</span>";
                }
            }
            $s .= "<div class='col-md-3'><a id='$id' href='{$href}'{$title}{$target} class='toCircle $circle'>{$ra[1]}{$badge}</a></div>";
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
        $userInfo = $accountDB->GetUserInfo($this->oApp->sess->GetUID());
        @list($fname,$lname) = explode(" ", $this->oApp->sess->GetName());
        $lname = $lname?:"";
        $email = $this->oApp->sess->GetEmail();
        $pswd = $userInfo[1]["password"];
        $accountType = array_key_exists(AccountType::KEY, $userInfo[2])?$userInfo[2][AccountType::KEY]:AccountType::NORMAL;
        return $pswd=="cats" && ($accountType == AccountType::STUDENT || strtolower($email) == strtolower(substr($fname, 0,1).$lname));
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

/**
 * Class representing the home screen tutorial
 * @author Eric
 * @version 4.1
 */
class HomeTutorial extends Tutorial {

    protected final function getSteps(): array{
        return array(
            [self::TITLE_KEY => 'Welcome!',self::CONTENT_KEY => 'Welcome to the CATS "backend". Lets show you arround'],
            [self::TITLE_KEY => 'Bubbles', self::CONTENT_KEY => 'These bubbles will take you the different screens you have access to.', self::ELEMENT_KEY => '#bubbles',self::PLACEMENT_KEY => Placement::TOP],
            [self::TITLE_KEY => 'Clinics', self::CONTENT_KEY => 'The current clinic you are viewing will be shown here. If you have access to multiple clinics you will also be able to switch between them here, by clicking on the clinic\'s name', self::ELEMENT_KEY => '#clinics',self::PLACEMENT_KEY => Placement::BOTTOM],
            [self::TITLE_KEY => 'Back Button', self::CONTENT_KEY => 'Your browser back button is not guaranteed to take you back to the previous screen. Please use this back button instead. In most cases the previous screen will be the home screen, however we track your screen history from the moment you log in and you can use the back button to backtrack through it.<br /><br />NOTE: the screen history is only avalible for as long as you are logged in; we don\'t store it permanently',self::ELEMENT_KEY => '#backButton',self::PLACEMENT_KEY => Placement::BOTTOM],
            [self::TITLE_KEY => 'Support Button', self::CONTENT_KEY => 'The developer team can be reached from within the backend at any time though this support button. Please use this button to contact us if you need help with the "backend". We are happy to help.<br /><br />NOTE: use of this feature requires your profile to contain an email address.', self::ELEMENT_KEY => '#supportButton',self::PLACEMENT_KEY => Placement::BOTTOM],
            [self::TITLE_KEY => 'System Resources', self::CONTENT_KEY => 'System resources (eg. documentation and placeholder images), are accessible under the "Access System Resources" bubble.', self::ELEMENT_KEY => '#system', self::PLACEMENT_KEY => Placement::TOP, self::VERSION_KEY => 2],
            [self::TITLE_KEY => 'User Settings', self::CONTENT_KEY => 'Your Profile can also be found under this bubble', self::ELEMENT_KEY => '#system', self::PLACEMENT_KEY => Placement::TOP, self::VERSION_KEY => "4".Tutorial::VERSION_FIRST],
            [self::TITLE_KEY => 'Paper Designs', self::CONTENT_KEY => 'Paper designs (aka. different lined papers), are accessible under the "Filing Cabinet" bubble.', self::ELEMENT_KEY => '#therapist-filing-cabinet', self::PLACEMENT_KEY => Placement::BOTTOM, self::VERSION_KEY => "3".Tutorial::VERSION_FIRST],
            [self::TITLE_KEY => 'Paper Designs', self::CONTENT_KEY => 'Paper designs (aka. different lined papers), are now accessible under the "Filing Cabinet" bubble.', self::ELEMENT_KEY => '#therapist-filing-cabinet', self::PLACEMENT_KEY => Placement::BOTTOM, self::VERSION_KEY => "3".Tutorial::VERSION_UPDATE],
            [self::TITLE_KEY => 'User Settings', self::CONTENT_KEY => 'Your Profile is now available under the "Access System Resources" bubble', self::ELEMENT_KEY => '#system', self::PLACEMENT_KEY => Placement::TOP, self::VERSION_KEY => "4".Tutorial::VERSION_UPDATE],
            [self::TITLE_KEY => 'Placeholder Validation', self::CONTENT_KEY => 'Placeholder images can now be validated against the images recognised by the system under the "Placeholder Images" bubble in this menu.', self::ELEMENT_KEY => '#system', self::PLACEMENT_KEY => Placement::TOP, self::VERSION_KEY => "5".Tutorial::VERSION_UPDATE],
            [self::TITLE_KEY => 'Additional support', self::CONTENT_KEY => 'If you need additional support, contact your clinic leader or the Development team at developer@catherapyservices.ca']
        );
    }

    public final function getScreen(): string{
        return 'home';
    }
    public function __construct(){
        // No need to initiate anything
    }


}