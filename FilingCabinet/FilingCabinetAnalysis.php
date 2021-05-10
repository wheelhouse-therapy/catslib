<?php
class FilingCabinetAnalysis {
    
    public const WILDCARD = ResourceRecord::WILDCARD;
    
    private $oApp;
    private $oAccountDB;
    
    public function __construct(SEEDAppConsole $oApp){
        $this->oApp = $oApp;
        $this->oAccountDB = new SEEDSessionAccountDBRead($oApp->kfdb);
    }
    
    public function getDownloadAnalysis(String $cabinet, String $dir,String $subdir = self::WILDCARD,int $limit=10):array{
        $raRR = ResourceRecord::GetResources($this->oApp, $cabinet, $dir,$subdir);
        $raOut = [];
        foreach($raRR as $oRR){
            $name = $this->getName($oRR,$cabinet,$dir,$subdir);
            $raOut[$name] = $oRR->getDownloads();
        }
        arsort($raOut);
        return array_slice($raOut, 0,$limit);
    }
    
    public function getViewAnalysis(String $dir,String $subdir = self::WILDCARD,int $limit=10):array{
        $raOut = [];
        $raRR = ResourceRecord::GetResources($this->oApp, 'videos', $dir,$subdir);
        $raUsers = [];
        $raGroups = $this->oApp->kfdb->QueryRowsRA1("SELECT _key FROM seedsession_groups");
        foreach($raGroups as $group){
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
        arsort($raOut);
        return array_slice($raOut, 0,$limit);
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
    
}