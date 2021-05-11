<?php
class FilingCabinetAnalysis {
    
    public const WILDCARD = ResourceRecord::WILDCARD;
    private const PAGE_SIZE = 10;
    
    private $oApp;
    private $oAccountDB;
    
    public function __construct(SEEDAppConsole $oApp){
        $this->oApp = $oApp;
        $this->oAccountDB = new SEEDSessionAccountDBRead($oApp->kfdb);
    }
    
    public function getDownloadAnalysis(String $cabinet, String $dir,String $subdir = self::WILDCARD,int $page=1):array{
        if($page < 1){
            $page = 1;
        }
        $raRR = ResourceRecord::GetResources($this->oApp, $cabinet, $dir,$subdir);
        $raOut = [];
        foreach($raRR as $oRR){
            $name = $this->getName($oRR,$cabinet,$dir,$subdir);
            $raOut[$name] = $oRR->getDownloads();
        }
        arsort($raOut); // Sort the array
        
        // Ensure the requested page is avalible, if not find the last avalible page and return that
        while($page > 1 && count(array_slice($raOut, 10*($page-1),self::PAGE_SIZE)) == 0){
            $page -= 1;
        }
        return ['data' => array_slice($raOut, 10*($page-1),self::PAGE_SIZE),'currPage' => $page,'hasNext' => count(array_slice($raOut, 10*($page),self::PAGE_SIZE)) > 0];
    }
    
    public function getViewAnalysis(String $dir,String $subdir = self::WILDCARD,int $page=1):array{
        if($page < 1){
            $page = 1;
        }
        $raOut = [];
        $raRR = ResourceRecord::GetResources($this->oApp, 'videos', $dir,$subdir);
        $raUsers = [];
        // Get all groups to ensure all watchlists are included in the count
        $raGroups = $this->oApp->kfdb->QueryRowsRA1("SELECT _key FROM seedsession_groups");
        foreach($raGroups as $group){
            // Get a list of all the users.
            // A user may be in more than 1 group so we need to make the list unique
            $raUsers = array_unique(array_merge($raUsers,$this->oAccountDB->GetUsersFromGroup($group,['eStatus' => "'ACTIVE','INACTIVE','PENDING'",'bDetail' => false])));
        }
        foreach($raUsers as $user){
            $oWatchlist = new VideoWatchList($this->oApp, $user);
            foreach($raRR as $oRR){
                if(!isset($raOut[$this->getName($oRR,"videos",$dir,$subdir)])){
                    $raOut[$this->getName($oRR,"videos",$dir,$subdir)] = 0;
                }
                if($oWatchlist->hasWatched($oRR->getID())){
                    $raOut[$this->getName($oRR,"videos",$dir,$subdir)] += 1;
                }
            }
        }
        arsort($raOut); // Sort the array
        
        // Ensure the requested page is avalible, if not find the last avalible page and return that
        while($page > 1 && count(array_slice($raOut, 10*($page-1),self::PAGE_SIZE)) == 0){
            $page -= 1;
        }
        return ['data' => array_slice($raOut, 10*($page-1),self::PAGE_SIZE),'currPage' => $page,'hasNext' => count(array_slice($raOut, 10*($page),self::PAGE_SIZE)) > 0];
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
        
        $cabinet = $this->oApp->sess->SmartGPC('cabinet',['general','videos','reports','SOP']);
        if($analysis == 'views'){
            // Can only check views on the video cabinet
            $cabinet = 'videos';
        }
        
        $dir = $this->oApp->sess->SmartGPC('directory',array_merge([self::WILDCARD],array_keys(FilingCabinet::GetFilingCabinetDirectories($cabinet))));
        $subdir = $this->oApp->sess->SmartGPC('subdirectory',[self::WILDCARD]);
        if(!in_array($subdir,FilingCabinet::GetSubFolders($dir,$cabinet))){
            $subdir = self::WILDCARD;
        }
        
        $ra = ['data'=>[],'currPage'=>1,'hasNext'=>false];
        switch($analysis){
            case 'downloads':
                $ra = $this->getDownloadAnalysis($cabinet, $dir,$subdir,$page);
                $s = str_replace(["[[download]]","[[view]]"], ["background-color:#8f8;",""], $s);
                $s .= "<br /><h2  style='display:inline-block;'>File Downloads Analysis</h2> from May 2021<br />";
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
        $s .= "<form>";
        if($analysis == 'downloads'){
            foreach(['general','videos','reports','SOP'] as $c){
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
            $s .= "<select name='cabinet'>";
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
            $s .= "<select name='cabinet' disabled><option value='videos' />Videos Cabinet</select>";
        }
        
        $s .= "<select name='directory'>".$raOptionsDirs[$cabinet]."</select>";
        $s .= "<select name='subdirectory'>".$raOptionsSubDirs[$cabinet."/".$dir]."</select>";
        $s .= "<input type='submit' value='Filter' /></form>";
        
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
        
        $s .= "<table class='container-fluid table table-striped table-sm sidebar-table'><tr class='row'><th class='col-md-5'>Filename</th>";
        switch($analysis){
            case 'downloads':
                $s .= "<th class='col-md-5'>Downloads</th>";
                break;
            case 'views':
                $s .= "<th class='col-md-5'>Views</th>";
                break;
        }
        $s .= "</tr>";
        foreach($ra['data'] as $k=>$v){
            $s .= "<tr class='row'>";
            $s .= "<td class='col-md-5'>".str_replace("/", "/<wbr>", $k)."</td>";
            $s .= "<td class='col-md-5'>".$v."</td>";
            $s .= "</tr>";
        }
        $s .= "</table>";
        
        return $s;
    }
    
}