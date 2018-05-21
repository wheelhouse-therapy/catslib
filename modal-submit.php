<?php
include_once "_config.php" ;
require_once "database.php" ;
require_once "cats_ui.php" ;
require_once "therapist-clientlist.php" ;
if( !($kfdb = new KeyframeDatabase( "ot", "ot" )) ||
    !$kfdb->Connect( "ot" ) )
{
    die( "Cannot connect to database<br/><br/>You probably have to execute these two MySQL commands<br/>"
        ."CREATE DATABASE ot;<br/>GRANT ALL ON ot.* to 'ot'@'localhost' IDENTIFIED BY 'ot'" );
}
$oCL = new ClientList($kfdb);
//Get Client Key
$client_key = $_POST['client_key'];
//Convert post key-value array to value array ignoring blank
$ra = array();
foreach ($oCL->pro_roles_key as $role){
    if($_POST[$role] != "0"){
        array_push($ra, $_POST[$role]);
    }
}
foreach ($ra as $pro_key){
    $kfr = $oCL->oClients_ProsDB->KFRelBase()->CreateRecord();
    $kfr->SetValue("fk_clients", $client_key);
    $kfr->SetValue("fk_professionals", $pro_key);
    $kfr->PutDBRow();
}