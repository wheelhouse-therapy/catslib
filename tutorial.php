<?php
// THERE SHOULD BE NO REASON TO EDIT THIS FILE DIRECTLY TO IMPLEMENT A TUTORIAL
/**
 * Class responsible for handling showing users a tutorial if they havent already sceen it.
 * @author Eric Wildfong
 * @version 1.0
 * @copyright Eric Wildfong 2020
 * 
 */
class TutorialManager {
    
    // Metadata information
    private const METADATA_SEPARATOR = '&';
    
    // Tutorial Status
    private const NOT_SEEN = 0;
    private const SEEN = 1;
    private const SEEN_PREVIOUS = 2;
    
    // Keys
    private const STATUS_KEY = 'status';
    private const VERSION_KEY = 'version';
    private const METADATA_KEY = 'tutorial';
    
    /**
     * Tutorials definition
     * @var array
     */
    private static $raTutorials = array();
    
    private static $initialized = false;
    
    /**
     * Check if the given screen has a tutorial.
     * @param String $screen - screen to check
     * @return bool - True if a tutorial exists for the given page.
     */
    public static function hasTutorial(String $screen):bool{
        if(!self::$initialized){
            self::init();
        }
        $screen = strtolower($screen);
        return array_key_exists($screen, self::$raTutorials);
    }
    
    /**
     * Checks if the given user has seen the tutorial for the screen.
     * @param SEEDAppConsole $oApp - for checking user metadata
     * @param String $screen - screen to check
     * @param int $user - user to check, 0 will check the current user.
     * @return bool - true if user has seen the tutorial.
     */
    public static function hasSeen(SEEDAppConsole $oApp,String $screen,int $user=0):int{
        
        if(!self::$initialized){
            self::init();
        }
        
        $screen = strtolower($screen);
        
        if(!self::hasTutorial($screen)){
            return self::NOT_SEEN;
        }
        
        if($user <= 0){
            $user = $oApp->sess->GetUID();
        }
        
        $data = self::processMetadata($oApp, $screen,$user);
        
        return $data[self::VERSION_KEY];
        
    }
    
    public static function runTutorial(SEEDAppConsole $oApp, String $screen, int $user=0):String{
        
        if(!self::$initialized){
            self::init();
        }
        
        if($user <= 0){
            $user = $oApp->sess->GetUID();
        }
        
        $s = <<<TutorialScript
<script>
    var tour = new Tour({
        backdrop:true,
        steps: [
            [[steps]]
        ],
        onEnd: function(tour){
            $.post("jx.php",{cmd: 'tutorialComplete',screen: '[[screen]]'});
        }
    });

    // Initialize the tour
    tour.init();

    // Start the tour
    tour.start();
</script>
TutorialScript;
        
        $screen = strtolower($screen);
        
        $data = self::processMetadata($oApp, $screen, $user);
        
        $sceenStatus = $data[self::STATUS_KEY];
        $last = $data[self::VERSION_KEY];
        
        if(!self::hasTutorial($screen) || $sceenStatus == self::SEEN){
            return "";
        }
        
        $steps = "";
        
        if($sceenStatus == self::SEEN_PREVIOUS){
            $steps .= "{orphan:true,title:'Welcome back!',content:'We made some changes while you were gone.'}";
        }
        
        foreach (self::$raTutorials[$screen]->getNewSteps($last) as $step){
            if($steps){
                $steps .= ",";
            }
            $orphanOrElement = 'orphan:true,';
            if(array_key_exists(Tutorial::ELEMENT_KEY, $step)){
                $orphanOrElement = "element:'{$step[Tutorial::ELEMENT_KEY]}',";
            }
            $steps .= "{title:'".addslashes($step[Tutorial::TITLE_KEY])."',content:'".addslashes($step[Tutorial::CONTENT_KEY])."',$orphanOrElement placement:'".(@$step[Tutorial::PLACEMENT_KEY]?:Placement::RIGHT)."'}";
        }
        
        return str_replace(array('[[steps]]','[[screen]]'), array($steps,$screen), $s);
        
    }
    
    public static function setComplete(SEEDAppConsole $oApp, String $screen, int $user=0):void{
        
        if($user <= 0){
            $user = $oApp->sess->GetUID();
        }
        
        if(!self::hasTutorial($screen)){
            return;
        }
        
        $version = self::$raTutorials[strtolower($screen)]->getVersion();
        
        $accountDB = new SEEDSessionAccountDB($oApp->kfdb, $oApp->sess->GetUID());
        $metadata = @$accountDB->GetUserMetadata($user)[self::METADATA_KEY]?:"";
        $ra = self::parseMetadata($metadata);
        $s = "";
        $stored = false;
        foreach ($ra as $element){
            if($s){
                $s .= self::METADATA_SEPARATOR;
            }
            if(strtolower($screen) == $element->GetScreen()){
                $s .= TutorialComplete::createNew($screen, $version)->write();
                $stored = true;
            }
            else{
                $s .= $element->write();
            }
        }
        if(!$stored){
            $s .= TutorialComplete::createNew($screen, $version)->write();
        }
        var_dump($s);
        $accountDB->SetUserMetadata($user, self::METADATA_KEY, $s);
        
    }
    
    private static function parseMetadata(String $metadata):array{
        $ra = explode(self::METADATA_SEPARATOR, $metadata);
        foreach ($ra as $k=>$v){
            if($ra[$k]){
                $ra[$k] = TutorialComplete::createFromMetadata($v);
            }
            else{
                unset($ra[$k]);
            }
        }
        return $ra;
    }
    
    private static function processMetadata(SEEDAppConsole $oApp,String $screen,int $user):array{
        if(!self::hasTutorial($screen)){
            return array(self::STATUS_KEY => self::NOT_SEEN,self::VERSION_KEY => -1);
        }
        $accountDB = new SEEDSessionAccountDB($oApp->kfdb, $oApp->sess->GetUID());
        $metadata = @$accountDB->GetUserMetadata($user)[self::METADATA_KEY]?:"";
        $ra = self::parseMetadata($metadata);
        $version = self::$raTutorials[$screen]->getVersion();
        $out = [self::STATUS_KEY => self::NOT_SEEN,self::VERSION_KEY => -1];
        foreach ($ra as $data ){
            if($data->GetScreen() == $screen && $data->GetVersion() == $version){
                $out[self::STATUS_KEY] =  self::SEEN;
            }
            else if ($data->GetScreen() == $screen && $data->GetVersion() < $version){
                $out[self::STATUS_KEY] = self::SEEN_PREVIOUS;
            }
            if($data->GetVersion() <= $version){
                $out[self::VERSION_KEY] = $data->GetVersion();
            }
        }
        return $out;
    }
    
    public static function init(){
        
        if(self::$initialized){
            // Already Initialized
            return;
        }
        
        $classes = get_declared_classes();
        foreach($classes as $klass) {
            $reflect = new ReflectionClass($klass);
            if($reflect->isSubclassOf("Tutorial")){
                $inst = $reflect->newInstance();
                self::$raTutorials[strtolower($inst->getScreen())] = $inst;
            }
        }
        self::$initialized = true;
    }
    
}

/**
 * Class representing a completed / viewed tutorial.
 * Also handles parsing and from and creating the metadata strings
 * @author Eric Wildfong
 * @version 1.0
 * @copyright Eric Wildfong 2020
 */
class TutorialComplete {
    
    private const VERSION_SEPARATOR = '|';
    
    private $screen;
    private $version;
    
    public static final function createFromMetadata(String $metadata):TutorialComplete{
        $ra = explode(self::VERSION_SEPARATOR, $metadata);
        return new TutorialComplete($ra[0], $ra[1]);
    }
    
    public static final function createNew(String $screen,int $version):TutorialComplete{
        return new TutorialComplete(strtolower($screen), $version);
    }
    
    private function __construct(String $screen, int $version){
        $this->screen = $screen;
        $this->version = $version;
    }
    
    public final function GetScreen():String{
        return $this->screen;
    }
    
    public final function GetVersion():int{
        return $this->version;
    }
    
    public final function write():String{
        return $this->screen.self::VERSION_SEPARATOR.$this->version;
    }
    
}

/**
 * All Screen Tutorials Extend this class.
 * This is automatically intitialized when the TutorialManager is initialized
 * @author Eric Wildfong
 * @copyright Eric Wildfong 2020
 */
abstract class Tutorial{
    
    // Step Keys
    public const TITLE_KEY = 'title';
    public const CONTENT_KEY = 'content';
    public const VERSION_KEY = 'version';
    public const ELEMENT_KEY = 'element';
    public const PLACEMENT_KEY = 'placement';
    
    /**
     * All steps must include these keys in order to be considered as a step and retured by getNewSteps
     * @var array
     */
    private static $REQUIRED_PARAMS = array(self::TITLE_KEY,self::CONTENT_KEY);
    
    protected abstract function getSteps():array;
    public abstract function getScreen():string;
    // Abstract constructor to simulate an interface with defalut methods
    public abstract function __construct();
    
    /**
     * Get the steps which the user has not sceen
     * @param int $last_version - last version the user has sceen, default = -1 to get all defined steps
     * @return array contaning arrays of step data
     */
    public final function getNewSteps(int $last_version = -1): array{
        $steps = array();
        foreach ($this->getSteps() as $step){
            if(array_intersect_key($step, array_flip(self::$REQUIRED_PARAMS))){
                if($last_version < (@$step[self::VERSION_KEY]?:0)){
                    $steps[] = $step;
                }
            }
        }
        return $steps;
    }
    
    /**
     * The tutorial is calculated from the versions in the steps
     * version can't be less then 1
     * @return int - curent tutorial version
     */
    public final function getVersion():int{
        $version = 1;
        foreach ($this->getSteps() as $step){
            if(array_intersect_key($step, array_flip(self::$REQUIRED_PARAMS))){
                if($version < (@$step[self::VERSION_KEY]?:0)){
                    $version = $step[self::VERSION_KEY];
                }
            }
        }
        if($version < 1){
            $version = 1;
        }
        return $version;
    }
    
}

/**
 * Placement position Constants
 * @author Eric Wildfong
 * @copyright Eric Wildfong 2020
 */
class Placement{
    
    const AUTO = 'auto';
    const BOTTOM = 'bottom';
    const LEFT = 'left';
    const RIGHT = 'right';
    const TOP = 'top';
    
}