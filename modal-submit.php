<?php
require_once '_config.php';
require_once "therapist-clientlist.php" ;

$oCL = new ClientList($oApp);
//Get Client Key
$client_key = $_POST['client_key'];
//Clear it from the request variable
unset($_POST['client_key']);
//Also Clear the command from the request variable
unset($_POST['cmd']);
//Convert post key-value array to value array ignoring blank
$ra = array();
foreach ($_POST as $role=>$v){
    if($_POST[$role] != "0"){
        array_push($ra, $_POST[$role]);
    }
}
foreach ($ra as $pro_key){
    $kfr = $oCL->oPeopleDB->KFRel("CX")->CreateRecord();
    $kfr->SetValue("fk_clients2", $client_key);
    $kfr->SetValue("fk_pros_external", $pro_key);
    $kfr->PutDBRow();
}