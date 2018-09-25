<?php
require_once '_config.php';
require_once "therapist-clientlist.php" ;

$oCL = new ClientList($oApp->kfdb);
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