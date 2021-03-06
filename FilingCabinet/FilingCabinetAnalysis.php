<?php
class FilingCabinetAnalysis {
    
    public const WILDCARD = ResourceRecord::WILDCARD;
    private const PAGE_SIZE = 10;
    private const NOONE = ["No one"=>0];
    
    private $oApp;
    /**
     * Mapping between userid and username
     */
    private $raUsers;
    /**
     * Mapping of users who's data will be listed (for filtering)
     */
    private $raQueryUsers;
    
    public function __construct(SEEDAppConsole $oApp){
        $this->oApp = $oApp;
        $oAccountDB = new SEEDSessionAccountDBRead($oApp->kfdb);
        $raUsers = [];
        // Get all groups to ensure all watchlists are included in the count
        $raGroups = $this->oApp->kfdb->QueryRowsRA1("SELECT _key FROM SEEDSession_Groups");
        foreach ($raGroups as $group){
            // Get a list of all the users.
            // A user may be in more than 1 group so we need to make the list unique
            $raUsers = array_unique(array_merge($raUsers,$oAccountDB->GetUsersFromGroup($group,['eStatus' => "'ACTIVE','INACTIVE','PENDING'",'bDetail' => false])));
        }
        foreach ($raUsers as $user){
            $info = $oAccountDB->GetUserInfo($user,false,true)[1];
            $username = @$info['realname']?:"User #$user";
            $this->raUsers[$user] = $username;
        }
        
        if (isset($_REQUEST['uids'])){
            $_SESSION['analysis_uids'] = $_REQUEST['uids'];
        }
        else if (isset($_REQUEST['filter'])){
            unset($_SESSION['analysis_uids']);
        }
        
        if (isset($_SESSION['analysis_uids'])){
            $this->raQueryUsers = array_intersect_key($this->raUsers, array_flip($_SESSION['analysis_uids']));
        }
        else {
            $this->raQueryUsers = $this->raUsers;
        }
        
    }
    
    public function getDownloadAnalysis(String $cabinet, String $dir,String $subdir = self::WILDCARD,int $page=1):array{
        if ($page < 1){
            $page = 1;
        }
        $raRR = ResourceRecord::GetResources($this->oApp, $cabinet, $dir,$subdir);
        
        $raData = [];
        $raDownloadData = [];
        foreach ($this->raQueryUsers as $user=>$username){
            $oFDL = new FileDownloadsList($this->oApp, $user);
            foreach($raRR as $oRR){
                $name = $this->getName($oRR,$cabinet,$dir,$subdir);
                $raData[$name] = $oRR->getDownloads();
                if ($oFDL->hasDownloaded($oRR->getID())){
                    if (!isset($raDownloadData[$name])){
                        $raDownloadData[$name] = [];
                    }
                    $raDownloadData[$name][$username] = $oFDL->downloadCount($oRR->getID());
                }
            }
        }
        arsort($raData); // Sort the array
        
        // Ensure the requested page is avalible, if not find the last avalible page and return that
        while($page > 1 && count(array_slice($raData, 10*($page-1),self::PAGE_SIZE)) == 0){
            $page -= 1;
        }
        $raDataOut = array_slice($raData, 10*($page-1),self::PAGE_SIZE);
        $raDownloadDataOut = [];
        foreach(array_keys($raDataOut) as $k){
            $raDownloadDataOut[$k] = @$raDownloadData[$k]?:self::NOONE;
        }
        return ['data' => $raDataOut, 'userData' => $raDownloadDataOut,'currPage' => $page,'hasNext' => count(array_slice($raData, 10*($page),self::PAGE_SIZE)) > 0];
    }
    
    public function getViewAnalysis(String $dir,String $subdir = self::WILDCARD,int $page=1):array{
        if($page < 1){
            $page = 1;
        }
        $raData = [];
        $raWatchData = [];
        $raRR = ResourceRecord::GetResources($this->oApp, 'videos', $dir,$subdir);
        foreach($this->raQueryUsers as $user=>$username){
            $oWatchlist = new VideoWatchList($this->oApp, $user);
            foreach($raRR as $oRR){
                if(!isset($raData[$this->getName($oRR,"videos",$dir,$subdir)])){
                    $raData[$this->getName($oRR,"videos",$dir,$subdir)] = 0;
                }
                if(!isset($raWatchData[$this->getName($oRR,"videos",$dir,$subdir)])){
                    $raWatchData[$this->getName($oRR,"videos",$dir,$subdir)] = [];
                }
                if($oWatchlist->hasWatched($oRR->getID())){
                    $raData[$this->getName($oRR,"videos",$dir,$subdir)] += 1;
                    $raWatchData[$this->getName($oRR,"videos",$dir,$subdir)][$username] = 1;
                }
            }
        }
        arsort($raData); // Sort the array
        
        // Ensure the requested page is avalible, if not find the last avalible page and return that
        while($page > 1 && count(array_slice($raData, 10*($page-1),self::PAGE_SIZE)) == 0){
            $page -= 1;
        }
        $raDataOut = array_slice($raData, 10*($page-1),self::PAGE_SIZE);
        $raWatchDataOut = [];
        foreach(array_keys($raDataOut) as $k){
            $raWatchDataOut[$k] = @$raWatchData[$k]?:self::NOONE;
        }
        return ['data' => $raDataOut, 'userData' => $raWatchDataOut,'currPage' => $page,'hasNext' => count(array_slice($raData, 10*($page),self::PAGE_SIZE)) > 0];
    }
    
    private function getName(ResourceRecord $oRR,String $cabinet,String $dir,String $subdir){
        $name = $oRR->getFile();
        if($oRR->getSubDirectory() && $oRR->getSubDirectory() != $subdir){
            $name = $oRR->getSubDirectory()."/".$name;
        }
        if($oRR->getDirectory() != $dir){
            $name = FilingCabinet::GetDirInfo($oRR->getDirectory(),$cabinet)['name']."/".$name;
        }
        return $name;
    }
    
    public function DrawAnalysisUI():String{
        $s = "<a href='?analysis=downloads' style='margin-right:5px;'><button style='[[download]]'>Download Analysis</button></a><a href='?analysis=views'><button style='[[view]]'>Views Analysis</button></a>";
        $analysis = $this->oApp->sess->SmartGPC('analysis',['downloads','views']);
        $page = $this->oApp->sess->SmartGPC('page',[1]);
        
        $cabinet = $this->oApp->sess->SmartGPC('cabinet',FilingCabinet::GetCabinets());
        if($analysis == 'views'){
            // Can only check views on the video cabinet
            $cabinet = 'videos';
        }
        
        $dir = $this->oApp->sess->SmartGPC('directory',array_merge([self::WILDCARD],array_keys(FilingCabinet::GetFilingCabinetDirectories($cabinet))));
        $subdir = $this->oApp->sess->SmartGPC('subdirectory',[self::WILDCARD]);
        if(!in_array($subdir,FilingCabinet::GetSubFolders($dir,$cabinet))){
            $subdir = self::WILDCARD;
        }
        
        $ra = ['data'=>[],'userData'=>[],'currPage'=>1,'hasNext'=>false];
        switch($analysis){
            case 'downloads':
                $ra = $this->getDownloadAnalysis($cabinet, $dir,$subdir,$page);
                $s = str_replace(["[[download]]","[[view]]"], ["background-color:#8f8;",""], $s);
                $s .= "<br /><h2  style='display:inline-block;'>File Downloads Analysis</h2> from July 2021<br />";
                break;
            case 'views':
                $ra = $this->getViewAnalysis($dir,$subdir,$page);
                $s = str_replace(["[[view]]","[[download]]"], ["background-color:#8f8;",""], $s);
                $s .= "<br /><h2 style='display:inline-block;'>Video Views Analysis</h2> from May 2021<br />";
                break;
            default:
                $s = str_replace(["[[download]]","[[view]]"], ["",""], $s);
                return $s;
        }
        if($page != $ra['currPage']){
            // Page requested isn't avalible
            $page = $ra['currPage'];
            $this->oApp->sess->VarSet('page', $page);
        }
        
        $raOptionsDirs = [];
        $raOptionsSubDirs = [];
        $s .= "<form style='margin: 5px 0'>";
        if($analysis == 'downloads'){
            foreach(FilingCabinet::GetCabinets() as $c){
                $raOptionsDirs[$c] = "<option value='".self::WILDCARD."'>All Directories</option>";
                $raOptionsSubDirs[$c."/".self::WILDCARD] = "<option value='".self::WILDCARD."'>All Sub Directories</option>";
                foreach(FilingCabinet::GetFilingCabinetDirectories($c) as $d=>$dirInfo){
                    if($d == $dir){
                        $raOptionsDirs[$c] .= "<option selected value='{$d}'>{$dirInfo['name']}</option>";
                    }
                    else{
                        $raOptionsDirs[$c] .= "<option value='{$d}'>{$dirInfo['name']}</option>";
                    }
                    $raOptionsSubDirs[$c."/".$d] = "<option value='".self::WILDCARD."'>All Sub Directories</option>";
                    foreach(FilingCabinet::GetSubFolders($d,$c) as $sub){
                        if($d == $dir && $sub == $subdir){
                            $raOptionsSubDirs[$c."/".$d] .= "<option selected>{$sub}</option>";
                        }
                        else{
                            $raOptionsSubDirs[$c."/".$d] .= "<option>{$sub}</option>";
                        }
                    }
                }
            }
            $s .= "<select name='cabinet' id='cabinet' onchange='onCabinetChange()'>";
            foreach(['general'=>"General Cabinet",'videos'=>"Videos Cabinet",'reports'=>"Reports Cabinet",'SOP'=>"SOP Cabinet"] as $c=>$v){
                if($cabinet == $c){
                    $s .= "<option selected value='$c' />$v";
                }
                else{
                    $s .= "<option value='$c' />$v";
                }
            }
            $s .= "</select>";
        }
        else if($analysis == 'views'){
            $raOptionsDirs['videos'] = "<option value='".self::WILDCARD."'>All Directories</option>";
            $raOptionsSubDirs["videos/".self::WILDCARD] = "<option value='".self::WILDCARD."'>All Sub Directories</option>";
            foreach(FilingCabinet::GetFilingCabinetDirectories('videos') as $d=>$dirInfo){
                if($d == $dir){
                    $raOptionsDirs['videos'] .= "<option selected value='{$d}'>{$dirInfo['name']}</option>";
                }
                else{
                    $raOptionsDirs['videos'] .= "<option value='{$d}'>{$dirInfo['name']}</option>";
                }
                $raOptionsSubDirs["videos/".$d] = "<option value='".self::WILDCARD."'>All Sub Directories</option>";
                foreach(FilingCabinet::GetSubFolders($d,"videos") as $sub){
                    if($d == $dir && $sub == $subdir){
                        $raOptionsSubDirs["videos/".$d] .= "<option selected>{$sub}</option>";
                    }
                    else{
                        $raOptionsSubDirs["videos/".$d] .= "<option>{$sub}</option>";
                    }
                }
            }
            $s .= "<select name='cabinet' id='cabinet' onchange='onCabinetChange()' disabled><option value='videos' />Videos Cabinet</select>";
        }
        
        $s .= "<select name='directory' id='directory' onchange='onDirChange()' style='margin-left:5px;'>".$raOptionsDirs[$cabinet]."</select>";
        $s .= "<select name='subdirectory' id='subdirectory' style='margin-left:5px;margin-right:5px;'>".$raOptionsSubDirs[$cabinet."/".$dir]."</select>";
        $s .= "<select name='uids[]' id='userSelect' multiple>";
        foreach($this->raUsers as $uid=>$username){
            if(in_array($uid, array_keys($this->raQueryUsers)) && $this->raQueryUsers != $this->raUsers){
                $s .= "<option selected value='$uid'>$username</option>";
            }
            else{
                $s .= "<option value='$uid'>$username</option>";
            }
        }
        $s .= "<input type='submit' name='filter' value='Filter' style='margin-left:5px;vertical-align:middle;' /></form>";
        
        if($page > 1){
            $s .= "<a href='?page=".($page-1)."'><button><i class='fas fa-arrow-left'></i></button></a>";
        }
        else{
            $s .= "<button disabled><i class='fas fa-arrow-left'></i></button>";
        }
        $s .= "<span style='margin:5px;'>Page {$page}</span>";
        if($ra['hasNext']){
            $s .= "<a href='?page=".($page+1)."'><button><i class='fas fa-arrow-right'></i></button></a>";
        }
        else{
            $s .= "<button disabled><i class='fas fa-arrow-right'></i></button>";
        }
        $s .= "<div class='container-fluid' style='margin-top:10px;'>";
        $s .= "<table class='table table-striped table-sm'><tr class='row'><th class='col-md-5'>Filename</th>";
        switch($analysis){
            case 'downloads':
                $s .= "<th class='col-md-2'>Downloads</th>";
                $s .= "<th class='col-md-5'>Downloaded By:</th>";
                break;
            case 'views':
                $s .= "<th class='col-md-2'>Views</th>";
                $s .= "<th class='col-md-5'>Watched By:</th>";
                break;
        }
        $s .= "</tr>";
        foreach($ra['data'] as $k=>$v){
            $s .= "<tr class='row'>";
            $s .= "<td class='col-md-5'>".str_replace("/", "/<wbr>", $k)."</td>";
            $s .= "<td class='col-md-2'>".$v."</td>";
            $s .= "<td class='col-md-5'>";
            $raOut = [];
            foreach($ra['userData'][$k] as $username => $count){
                $sCount = "";
                if($count > 1){
                    $sCount = " x$count";
                }
                array_push($raOut,"$username$sCount");
            }
            $s .= SEEDCore_ArrayExpandSeries($raOut, ", [[]]",true,["sTemplateFirst"=>"[[]]","sTemplateLast"=>", and [[]]"]);
            $s .= "</td>";
            $s .= "</tr>";
        }
        $s .= "</table></div>";
        
        $s .= "<script>const directories = JSON.parse(`".json_encode($raOptionsDirs)."`);const subdirectories = JSON.parse(`".json_encode($raOptionsSubDirs)."`);";
        $s .= <<<JavaScript
            function onCabinetChange(){
                let cabinet = document.getElementById('cabinet');
                let dir = document.getElementById('directory');
                dir.innerHTML = directories[cabinet.value];
            }
            function onDirChange(){
                let cabinet = document.getElementById('cabinet');
                let dir = document.getElementById('directory');
                let subdir = document.getElementById('subdirectory');
                subdir.innerHTML = subdirectories[cabinet.value+"/"+dir.value];
            }
            $(document).ready(function() {
                $('#userSelect').select2({
                    placeholder: "All Users"
                });
                $('#subdirectory').select2();
                $('#directory').select2();
                $('#cabinet').select2();
            });
            </script>
            <style>
                .select2-container{
                    margin-left: 5px;
                }
            </style>
JavaScript;
        
        return $s;
    }
    
}