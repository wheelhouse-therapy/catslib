<?php

require_once SEEDROOT."Keyframe/KeyframeRelation.php";

define( "DBNAME", $config_KFDB['cats']['kfdbDatabase'] );

class ClientsDB
{
    private $kfrel;
    private $raClients;

    private $kfreldef = array(
        "Tables" => array( "Clients" => array( "Table" => DBNAME.'.clients',
                                               "Fields" => "Auto",
    )));

    function KFRel()  { return( $this->kfrel ); }

    function __construct( KeyframeDatabase $kfdb, $uid = 0 )
    {
        $this->kfrel = new KeyFrame_Relation( $kfdb, $this->kfreldef, $uid, array('logfile'=>CATSDIR_LOG."clients-pros.log") );
    }

    function GetClient( $key )
    {
        return( $this->kfrel->GetRecordFromDBKey( $key ) );
    }

}

class ProsDB
{
    private $kfrel;
    private $raPros;

    private $kfreldef = array(
        "Tables" => array( "Pros" => array( "Table" => DBNAME.'.professionals',
            "Fields" => "Auto",
        )));

    function KFRel()  { return( $this->kfrel ); }

    function __construct( KeyframeDatabase $kfdb, $uid = 0 )
    {
        $this->kfrel = new KeyFrame_Relation( $kfdb, $this->kfreldef, $uid, array('logfile'=>CATSDIR_LOG."clients-pros.log") );
    }

    function GetPro( $key )
    {
        return( $this->kfrel->GetRecordFromDBKey( $key ) );
    }
}

class Clients_ProsDB
/*******************
    The connections between clients and professionals
 */
{
    private $kfrel;     // just the clients_pros table
    private $kfrel_X;   // the join of clients X clients_pros X professionals
    private $raPros;

    private $kfreldef = array(
        "Tables" => array( "Pros" => array( "Table" => DBNAME.'.clients_pros',
                                            "Fields" => "Auto",
        )));

    private $kfreldef_X = array(
        "Tables" => array( "Clients" => array( "Table" => DBNAME.'.clients',
                                               "Fields" => "Auto" ),
                           "Pros"    => array( "Table" => DBNAME.'.professionals',
                                               "Fields" => "Auto" ),
                           "CxP"     => array( "Table" => DBNAME.'.clients_pros',
                                               "Fields" => "Auto" )
        ));

    function KFRelBase()  { return( $this->kfrel ); }       // just the base table
    function KFRel()      { return( $this->kfrel_X ); }     // the whole join of three tables

    function __construct( KeyframeDatabase $kfdb, $uid = 0 )
    {
        $this->kfrel   = new KeyFrame_Relation( $kfdb, $this->kfreldef,   $uid, array('logfile'=>CATSDIR_LOG."clients-pros.log") );
        $this->kfrel_X = new KeyFrame_Relation( $kfdb, $this->kfreldef_X, $uid, array('logfile'=>CATSDIR_LOG."clients-pros.log") );
    }

    function GetClientInfoForProfessional( $pro_key )
    {
        return( $this->kfrel_X->GetRecordFromDB( "Pros._key='$pro_key'" ) );
    }

    function GetProfessionalInfoForClient( $client_key )
    {
        return( $this->kfrel_X->GetRecordFromDB( "Clients._key='$client_key'" ) );
    }
}

class AppointmentsDB
{
    private $kfrel;
    private $raAppts;

    private $kfreldef = array(
        "Tables" => array( "Appts" => array( "Table" => DBNAME.'.cats_appointments',
            "Fields" => "Auto",
        )));

    function KFRel()  { return( $this->kfrel ); }

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->kfrel = new KeyFrame_Relation( $oApp->kfdb, $this->kfreldef, $oApp->sess->GetUID(), array('logfile'=>CATSDIR_LOG."appt.log") );
    }

    function GetList( $sCond )
    {
        return( $this->kfrel->GetRecordSetRA( $sCond, $raKFParms = array() ) );
    }

    function GetKFR( $k )
    {
        return( $k ? $this->kfrel->GetRecordFromDBKey( $k ) : $this->kfrel->CreateRecord() );
    }
}

class ClinicsDB
{
    private $kfrel;
    private $raClinics;

    private $kfreldef = array(
        "Tables" => array( "Clinics" => array( "Table" => DBNAME.'.clinics',
            "Fields" => "Auto",
        )));

    function KFRel()  { return( $this->kfrel ); }

    function __construct( KeyframeDatabase $kfdb, $uid = 0 )
    {
        $this->kfrel = new KeyFrame_Relation( $kfdb, $this->kfreldef, $uid, array('logfile'=>CATSDIR_LOG."clinics.log") );
    }

    function GetClinic( $key )
    {
        return( $this->kfrel->GetRecordFromDBKey( $key ) );
    }
}

class Users_ClinicsDB
/*******************
 The connections between users and clinics
 */
{
    private $kfrel;     // just the users_clinics table
    private $kfrel_X;   // the join of users X users_clinics X clinics
    private $raPros;

    private $kfreldef = array(
        "Tables" => array( "Clinics" => array( "Table" => DBNAME.'.users_clinics',
            "Fields" => "Auto",
        )));

    private $kfreldef_X = array(
        "Tables" => array( "Users" => array( "Table" => DBNAME.'.SEEDSession_Users',
            "Fields" => "Auto" ),
            "Clinics"    => array( "Table" => DBNAME.'.clinics',
                "Fields" => "Auto" ),
            "UxC"     => array( "Table" => DBNAME.'.users_clinics',
                "Fields" => "Auto" )
        ));

    function KFRelBase()  { return( $this->kfrel ); }       // just the base table
    function KFRel()      { return( $this->kfrel_X ); }     // the whole join of three tables

    function __construct( KeyframeDatabase $kfdb, $uid = 0 )
    {
        $this->kfrel   = new KeyFrame_Relation( $kfdb, $this->kfreldef,   $uid, array('logfile'=>CATSDIR_LOG."users-clinics.log") );
        $this->kfrel_X = new KeyFrame_Relation( $kfdb, $this->kfreldef_X, $uid, array('logfile'=>CATSDIR_LOG."users-clinics.log") );
    }

    function GetUserInfoForClinic( $clinic_key )
    {
        return( $this->kfrel_X->GetRecordFromDB( "Clinics._key='$clinic_key'" ) );
    }

    function GetClinicInfoForUser( $user_key )
    {
        return( $this->kfrel_X->GetRecordFromDB( "Users._key='$user_key'" ) );
    }
}

function createTables( KeyframeDatabase $kfdb )
{
    /* When you add or change tables:
     *     1: increment $dbVersion below to represent your new version of the database structure
     *     2: put your create/alter commands in "if( $currDBVersion < NNN )" where NNN is the new $dbVersion number.
     *
     * That way, the first time anybody loads a page with an out-of-date database, the necessary create/alter commands will run
     * and stringbucket will be updated (so it doesn't happen twice).
     */
    $dbVersion = 6;     // update all tables to this version if the SEEDMetaTable_StringBucket:cats:dbVersion is less

    if( !tableExists( $kfdb, "SEEDMetaTable_StringBucket") ) {
        $kfdb->SetDebug(2);
        $kfdb->Execute( SEEDMetaTable_StringBucket::SqlCreate );
        $kfdb->SetDebug(0);
    }
    $oBucket = new SEEDMetaTable_StringBucket( $kfdb );
    $currDBVersion = intval($oBucket->GetStr( 'cats', 'dbVersion') );
    if( $currDBVersion != $dbVersion ) {
        $oBucket->PutStr( 'cats', 'dbVersion', $dbVersion );
    }

    /* Create / alter tables if the currDBVersion (the number stored in stringbucket) was less than $dbVersion
     */
    if( $currDBVersion < 1 ) {
        // Changed assessments_score.testid from integer to string and rename to testType
        $kfdb->SetDebug(2);
        $kfdb->Execute( "ALTER TABLE assessments_scores CHANGE testid testType VARCHAR(20) NOT NULL DEFAULT ''" );
        $kfdb->Execute( "UPDATE assessments_scores SET testType='spm' WHERE testType='0'" );
        $kfdb->SetDebug(0);
    }
    if( $currDBVersion < 2){
        // Add akaunting_company to clinics table for akaunting Hook
        $kfdb->SetDebug(2);
        $kfdb->Execute( "ALTER TABLE clinics ADD akaunting_company INTEGER NOT NULL DEFAULT 0" );
        $kfdb->SetDebug(0);
    }
    if( $currDBVersion < 3){
        // Add mailing_address to clinics table
        $kfdb->SetDebug(2);
        $kfdb->Execute( "ALTER TABLE clinics ADD mailing_address VARCHAR(200) NOT NULL DEFAULT ''" );
        $kfdb->SetDebug(0);
    }
    if( $currDBVersion < 4){
        // Add date and respondent to assessments table
        $kfdb->SetDebug(2);
        $kfdb->Execute( "ALTER TABLE assessments_scores ADD date VARCHAR(200) NOT NULL DEFAULT ''" );
        $kfdb->Execute( "ALTER TABLE assessments_scores ADD respondent VARCHAR(200) NOT NULL DEFAULT ''" );
        $kfdb->SetDebug(0);
    }
    if( $currDBVersion < 5){
        // Add signature to staff table
        $kfdb->SetDebug(2);
        $kfdb->Execute("ALTER TABLE pros_internal ADD signature longblob NOT NULL");
        $kfdb->SetDebug(0);
    }
    if( $currDBVersion < 6){
        // Add code to clients table
        $kfdb->SetDebug(2);
        $kfdb->Execute("ALTER TABLE clients2 ADD code VARCHAR(20) NOT NULL DEFAULT ''");
        $kfdb->SetDebug(0);
    }

    /* Old createTables code.
     * This should be updated to reflect dbVersion, but it's also nice to just create tables based on existence so they can be dropped
     * and recreated.
     */

    echo DRSetup( $kfdb );      // returns "" if tables don't have to be created

    // this query will return blank if the gid_inherited column isn't there
    if( !$kfdb->Query1( "select table_schema from information_schema.columns "
                       ."where table_schema='".DBNAME."' and table_name='SEEDSession_Groups' and column_name='gid_inherited'" ) ) {
        $kfdb->Execute( "alter table SEEDSession_Groups add gid_inherited integer not null default '0'" );
    }

    if( !tableExists( $kfdb, DBNAME.".cats_appointments" ) ) {
        echo "Creating the Appointment table";

        $kfdb->SetDebug(2);
        $kfdb->Execute( "CREATE TABLE ".DBNAME.".cats_appointments (
            _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
            _created    DATETIME,
            _created_by INTEGER,
            _updated    DATETIME,
            _updated_by INTEGER,
            _status     INTEGER DEFAULT 0,

            google_cal_ev_id VARCHAR(200) NOT NULL DEFAULT '',
            eStatus          ENUM('REVIEWED','COMPLETED','CANCELLED','PAID') NOT NULL DEFAULT 'REVIEWED',
            start_time       DATETIME NULL,
            session_minutes  INTEGER NOT NULL DEFAULT 0,  -- initially calculated from calendar
            prep_minutes     INTEGER NOT NULL DEFAULT 10,
         -- total_time       INTEGER NOT NULL DEFAULT 0,    just session+prep
            rate             INTEGER NOT NULL DEFAULT 0,  -- from pros but editable
            invoice_email    VARCHAR(200) NOT NULL DEFAULT '',
            invoice_date     VARCHAR(200) NOT NULL DEFAULT '',
            fk_clients       INTEGER NOT NULL DEFAULT 0,
            fk_professionals INTEGER NOT NULL DEFAULT 0,
            note             TEXT,
            session_desc     TEXT,
            fk_cats_invoices INTEGER NOT NULL DEFAULT 0)" );

        $kfdb->SetDebug(0);
    }

    if( !tableExists( $kfdb, DBNAME.".clinics" ) ) {
        echo "Creating the Clinics table";

        $kfdb->SetDebug(2);
        $kfdb->Execute( "CREATE TABLE ".DBNAME.".clinics (
            _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
            _created    DATETIME,
            _created_by INTEGER,
            _updated    DATETIME,
            _updated_by INTEGER,
            _status     INTEGER DEFAULT 0,

            clinic_name VARCHAR(200) NOT NULL DEFAULT '',
            address VARCHAR(200) NOT NULL DEFAULT '',
            city VARCHAR(200) NOT NULL DEFAULT '',
            email VARCHAR(200) NOT NULL DEFAULT '',
            postal_code VARCHAR(200) NOT NULL DEFAULT '',
            phone_number VARCHAR(200) NOT NULL DEFAULT '',
            fax_number VARCHAR(200) NOT NULL DEFAULT '',
            rate INTEGER NOT NULL DEFAULT 110,
            associated_business VARCHAR(200) NOT NULL DEFAULT '',
            fk_leader INTEGER NOT NULL DEFAULT 1
            akaunting_company INTEGER NOT NULL DEFAULT 0 " );

        $kfdb->Execute( "INSERT INTO ".DBNAME.".clinics (_key,clinic_name,rate,associated_business) values (null,'Core',110,'CATS')" );
        $kfdb->SetDebug(0);
    }

    if( !tableExists( $kfdb, DBNAME.".users_clinics" ) ) {
        echo "Creating the Users X Clinics table";

        $kfdb->SetDebug(2);
        $kfdb->Execute( "CREATE TABLE ".DBNAME.".users_clinics (
            _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
            _created    DATETIME,
            _created_by INTEGER,
            _updated    DATETIME,
            _updated_by INTEGER,
            _status     INTEGER DEFAULT 0,

            fk_SEEDSession_Users       INTEGER NOT NULL DEFAULT 0,
            fk_clinics INTEGER NOT NULL DEFAULT 0 )" );
        $kfdb->Execute( "INSERT INTO ".DBNAME.".users_clinics (_key,fk_SEEDSession_Users,fk_clinics) values (null,1,1)" );  // Dev leads the Core clinic
        $kfdb->Execute( "INSERT INTO ".DBNAME.".users_clinics (_key,fk_SEEDSession_Users,fk_clinics) values (null,2,1)" );  // Sue is a member of the Core clinic
        $kfdb->SetDebug(0);
    }

    if( !tableExists( $kfdb, DBNAME.".people" ) ) {
        echo "Creating the People table";

        $kfdb->SetDebug(2);
        $kfdb->Execute( CATSDB_SQL::people_create );
        $kfdb->Execute( "INSERT INTO ".DBNAME.".people (_key,uid,first_name) values (1,0,'Eric')" );
        $kfdb->Execute( "INSERT INTO ".DBNAME.".people (_key,uid,first_name) values (2,0,'Joe')" );
        $kfdb->Execute( "INSERT INTO ".DBNAME.".people (_key,uid,first_name) values (3,0,'Jose')" );
        $kfdb->Execute( "INSERT INTO ".DBNAME.".people (_key,uid,first_name) values (4,0,'Darth Vader')" );

        $kfdb->SetDebug(0);
    }
    if( !tableExists( $kfdb, DBNAME.".clients2" ) ) {
        echo "Creating the Clients table";

        $kfdb->SetDebug(2);
        $kfdb->Execute( CATSDB_SQL::clients_create );
        $kfdb->Execute( "INSERT INTO ".DBNAME.".clients2 (_key,fk_people) values (1,1)" );
        $kfdb->Execute( "INSERT INTO ".DBNAME.".clients2 (_key,fk_people) values (2,2)" );
        $kfdb->SetDebug(0);
    }
    if( !tableExists( $kfdb, DBNAME.".pros_internal" ) ) {
        echo "Creating the Professionals Internal table";

        $kfdb->SetDebug(2);
        $kfdb->Execute( CATSDB_SQL::pros_internal_create );
        $kfdb->Execute( "INSERT INTO ".DBNAME.".pros_internal (_key,fk_people) values (1,3)" );
        $kfdb->SetDebug(0);
    }
    if( !tableExists( $kfdb, DBNAME.".pros_external" ) ) {
        echo "Creating the Professionals External table";

        $kfdb->SetDebug(2);
        $kfdb->Execute( CATSDB_SQL::pros_external_create );
        $kfdb->Execute( "INSERT INTO ".DBNAME.".pros_external (_key,fk_people) values (1,4)" );
        $kfdb->SetDebug(0);
    }

    if( !tableExists( $kfdb, DBNAME.".clientsxpros" ) ) {
        echo "Creating the Client X Pros table";

        $kfdb->SetDebug(2);
        $kfdb->Execute( CATSDB_SQL::clientsxpros_create );
        $kfdb->Execute( "INSERT INTO ".DBNAME.".clientsxpros (_key,fk_clients2,fk_pros_internal,fk_pros_external) values (null,1,1,0)" );
        $kfdb->Execute( "INSERT INTO ".DBNAME.".clientsxpros (_key,fk_clients2,fk_pros_internal,fk_pros_external) values (null,2,0,1)" );
        $kfdb->SetDebug(0);
    }

    ensureTable( $kfdb, "assessments_scores" );
    ensureTable( $kfdb, "resources_files" );
    if(!tableExists( $kfdb, DBNAME.".tag_name_resolution")){
        echo "Creating the TNRS Resolution table";

        $kfdb->SetDebug(2);
        $kfdb->Execute( CATSDB_SQL::tag_name_resolution_create );
        $tnrs = new TagNameResolutionService($kfdb);
        $tnrs->defineComplexResolution("staff", "role", "ot", "occupational therapist");
        $tnrs->defineComplexResolution("staff", "role", "slp", "speech-language pathologist");
        $kfdb->SetDebug(0);
    }



    // Also make the SEEDSession tables
    if( !tableExists( $kfdb, DBNAME.".SEEDSession_Users" ) ) {
        echo "Creating the Session tables";

        $kfdb->SetDebug(2);
        SEEDSessionAccountDBCreateTables( $kfdb, DBNAME );
        $kfdb->SetDebug(0);

                        //uid      realname                 username/email      group
        foreach( array( 1 => array('Developer',             'dev',              1),
                        2 => array('Sue Wahl',              'sue',              2),
                        3 => array('Jose the Group Leader', 'jose',             3),
                        4 => array('A. Therapist',          'therapist',        4),
                        5 => array('Mr. Client',            'client',           5) )  as $uid => $ra )
        {
            $kfdb->Execute( "INSERT INTO SEEDSession_Users (_key,_created,_updated,realname,email,password,gid1,eStatus) "
                           ."VALUES ($uid, NOW(), NOW(), '{$ra[0]}', '{$ra[1]}', 'cats', {$ra[2]}, 'ACTIVE')");
        }

        foreach( array( 1 => 'Admin Group',
                        2 => 'Owners Group',
                        3 => 'Leaders Group',
                        4 => 'Therapists Group',
                        5 => 'Clients Group' )  as $uid => $sGroup )
        {
            $bRet = $kfdb->Execute( "INSERT INTO SEEDSession_Groups (_key,_created,_updated,groupname) "
                                           ."VALUES ($uid, NOW(), NOW(), '$sGroup')");
        }

        foreach( array( array(1,2), array(1,3), array(1,4), array(1,5), // dev (uid 1) is in all groups
                        array(2,3), array(2,4), array(2,5),             // sue (2) is in all groups except Developer
                        array(3,4), array(3,5),
                        array(4,5)
                      ) as $ra )
        {
            $bRet = $kfdb->Execute( "INSERT INTO SEEDSession_UsersXGroups (_key,_created,_updated,uid,gid) "
                                           ."VALUES (NULL, NOW(), NOW(), '{$ra[0]}', '{$ra[1]}')");
        }
                          //  perm              modes   uid     gid
        foreach( array( array('SEEDSessionUGP', 'RWA',       1,  'NULL'),
                        array('SEEDPerms',      'RWA',       1,  'NULL'),
                        array('DocRepMgr',      'A',         1,  'NULL'),
                        array('DocRepMgr',      'W',    'NULL',       2),
                        array('DocRepMgr',      'R',    'NULL',       3),
                        array('admin',          'RWA',  'NULL',       1),
                        array('owner',          'RWA',  'NULL',       2),
                        array('leader',         'RWA',  'NULL',       3),
                        array('therapist',      'RWA',  'NULL',       4),
                        array('client',         'RWA',  'NULL',       5),
                        array('administrator',  'RWA',       1,  'NULL'),
                        array('catsappt',       'RW',   'NULL',       4),
                        array('Calendar',       'RW',   'NULL',       5),
                        array('Calendar',       'A',    'NULL',       4),
                      ) as $ra )
        {
            $bRet = $kfdb->Execute( "INSERT INTO SEEDSession_Perms (_key,_created,_updated,perm,modes,uid,gid) "
                                           ."VALUES (NULL, NOW(), NOW(), '{$ra[0]}', '{$ra[1]}', {$ra[2]}, {$ra[3]})");
        }
    }
}

function ensureTable( KeyframeDatabase $kfdb, $tablename )
{
    $ok = false;

    if( !$kfdb->TableExists( DBNAME.'.'.$tablename ) ) {
        echo "Creating the $tablename table";

        $kfdb->SetDebug(2);
        $ok = $kfdb->Execute( constant("CATSDB_SQL::{$tablename}_create") );
        $kfdb->SetDebug(0);
    }
    return( $ok );
}

function tableExists( KeyframeDatabase $kfdb, $tablename )
{
    return( $kfdb->TableExists( $tablename ) );
}


class CATSBaseDB extends Keyframe_NamedRelations
/***************
    Basic definitions of CATS database tables and methods to create kfrels
 */
{
    protected $t = array();  // table definitions used by all derived db classes in CATS

    function __construct( SEEDAppSession $oApp, $raConfig = array() )
    {
        // People tables
        $this->t['P']                      = array( "Table" => DBNAME.".people",        "Fields" => 'Auto' );
        $this->t[ClientList::CLIENT]       = array( "Table" => DBNAME.".clients2",      "Fields" => 'Auto' );
        $this->t[ClientList::INTERNAL_PRO] = array( "Table" => DBNAME.".pros_internal", "Fields" => 'Auto' );
        $this->t[ClientList::EXTERNAL_PRO] = array( "Table" => DBNAME.".pros_external", "Fields" => 'Auto' );
        $this->t['CX']                     = array( "Table" => DBNAME.".clientsxpros",  "Fields" => 'Auto' );

        // Assessment tables
        $this->t['A']  = array( "Table" => DBNAME.".assessments_scores", "Fields" => 'Auto' );

        // set up $this->t first because KeyFrame_NamedRelations calls initKfrel which needs that
        parent::__construct( $oApp->kfdb, $oApp->sess->GetUID(), $oApp->logdir );
    }

    protected function newKfrel( $kfdb, $uid, $raTableDefs, $sLogfile )
    /******************************************************************
        $raTableDefs is an array('Alias'=>array('Table'=>...), ... )
     */
    {
        $parms = $sLogfile ? array('logfile'=>$sLogfile) : array();
        return( new KeyFrame_Relation( $kfdb, array( "Tables" => $raTableDefs ), $uid, $parms ) );
    }
}


class PeopleDB extends CATSBaseDB
{
    function __construct( SEEDAppSession $oApp, $raConfig = array() )
    {
        parent::__construct( $oApp, $raConfig );
    }

    protected function initKfrel( KeyframeDatabase $kfdb, $uid, $logdir )
    {
        $raKfrel = array();
        $sLogfile = $logdir ? "$logdir/people.log" : "";

        $raKfrel['P']                      = $this->newKfrel( $kfdb, $uid, array(                                                               'P' => $this->t['P'] ), $sLogfile );
        $raKfrel[ClientList::CLIENT]       = $this->newKfrel( $kfdb, $uid, array( ClientList::CLIENT =>$this->t[ClientList::CLIENT],            'P' => $this->t['P'] ), $sLogfile );
        $raKfrel[ClientList::INTERNAL_PRO] = $this->newKfrel( $kfdb, $uid, array( ClientList::INTERNAL_PRO=>$this->t[ClientList::INTERNAL_PRO], 'P' => $this->t['P'] ), $sLogfile );
        $raKfrel[ClientList::EXTERNAL_PRO] = $this->newKfrel( $kfdb, $uid, array( ClientList::EXTERNAL_PRO=>$this->t[ClientList::EXTERNAL_PRO], 'P' => $this->t['P'] ), $sLogfile );
        $raKfrel['CX']                     = $this->newKfrel( $kfdb, $uid, array( 'CX'=>$this->t['CX'] ),                                                               $sLogfile );

        return( $raKfrel );
    }
}

class AssessmentsDB extends CATSBaseDB
{
    function __construct( SEEDAppSession $oApp, $raConfig = array() )
    {
        parent::__construct( $oApp, $raConfig );
    }

    protected function initKfrel( KeyframeDatabase $kfdb, $uid, $logdir )
    {
        $raKfrel = array();
        $sLogfile = $logdir ? "$logdir/assessments.log" : "";

        $raKfrel['A']     = $this->newKfrel( $kfdb, $uid, array( 'A' => $this->t['A'] ), $sLogfile );
        $raKfrel['AxCxP'] = $this->newKfrel( $kfdb, $uid, array( 'A' => $this->t['A'], 'C' => $this->t['C'], 'P' => $this->t['P'] ), $sLogfile );

        return( $raKfrel );
    }
}

class TagNameResolutionService {

    private $kfrel;

    private $kfreldef = array(
        "Tables" => array( "TagNameResolution" => array( "Table" => DBNAME.'.tag_name_resolution',
            "Fields" => "Auto",
        )));

    function KFRel()  { return( $this->kfrel ); }

    function __construct( KeyframeDatabase $kfdb, $uid = 0 )
    {
        $this->kfrel = new KeyFrame_Relation( $kfdb, $this->kfreldef, $uid, array('logfile'=>CATSDIR_LOG."TRS.log") );
    }

    /** Attempt to resolve a tag for a particular value
     * ONLY WORKS WITH COMPLEX TAGS
     * Invokes resolveTag(String $tag, String $value) with $table and $col joined
     * @param String $table - table portion of complex tag
     * @param String $col - column portion of complex tag
     * @param String $value - value to be replaced
     * @see resolveTag(String $tag, String $value)
     */
    function resolveComplexTag(String $table, String $col, String $value):String{
        return $this->resolveTag($table.":".$col, $value);
    }

    /** Attempt to resolve a tag for a particular value.
     * If Resolution fails the original value is returned.
     * Can be used with single or complex tags
     * @param String $tag - Tag to resolve
     * @param String $value - Value to replace
     * @return String - Replacement value or original value if resolution faills
     */
    function resolveTag(String $tag, String $value):String{

        $kfr = $this->kfrel->GetRecordFromDB("tag='".addslashes(strtolower($tag))."' AND name='".addslashes(strtolower($value))."'");

        if($kfr){
            $v = $kfr->Value("value");
            $ra = preg_split("/[\s-]/", $v, null, PREG_OFFSET_CAPTURE);
            for ($i = 0; $i < count($ra) && $i < strlen($value); $i++) {
                if(ctype_upper($value[$i])){
                    $ra[$i][0] = ucwords($ra[$i][0]);
                }
            }
            $sResult = "";
            foreach($ra as $raResult){
                if($raResult[1] != 0){
                    $sResult .= $v[$raResult-1];
                }
                $sResult .= $raResult[0];
            }
            $value = $sResult;
        }

        return $value;

    }

    function defineComplexResolution(String $table, String $col, String $name, String $value):array{
        return $this->defineResolution($table.":".$col, $name, $value);
    }

    function defineResolution(String $tag, String $name, String $value):array{
        $out = array("bOk" => true, "sError" => "");
        if($this->resolveTag($tag, $name) != $name){
            $out["bOk"] = false;
            $out["sError"] = "Could not define new Resolution because the tag already has a definition for ".$name;
            goto done;
        }
        $kfr = $this->kfrel->CreateRecord();
        $kfr->SetValue("tag", $tag);
        $kfr->SetValue("name", $name);
        $kfr->SetValue("value", $value);
        $out['bOk'] = $kfr->PutDBRow();

        done:
        return $out;

    }


    /**
     * Check if resolution is defined
     * @param String $tag - tag to use in tag name pair.
     * @param String $name - name to use in tag name pair.
     * @param $kfr - record to use for self compare.
     * @return bool - true if and only if the tag name pair is not defined or maped to itself.
     * Such that the tag name entry is null or its _key $kfr->Value('_key')
     */
    function isAvailable(String $tag, String $name, KeyframeRecord $kfr = null):bool{
        $record = $this->kfrel->GetRecordFromDB("tag='".addslashes(strtolower($tag))."' AND name='".addslashes(strtolower($name))."'");

        if(!$record){
            return true;
        }

        return $kfr && $record->Key() == $kfr->Key(); // If the keys match than its the same

    }

    function listResolution(){

        //Set up the output templates
        $sOut = "<h1>Manage Tag Name Resolution Service</h1>
                 <h6>This system substitutes other values for defined tag value pairs.
                 The values which are replaced are called Names and the replacements are Values.</h6>
                 <style>
                    td,th {
	                   text-align: center;
                    }
                 </style>
                 <table class='sticky-header better-table-striped' id='tagTable' style='width:100%;'>
                    <thead>
                        <tr><th colspan='4'>[[status]]</th></tr>
                        <tr><th colspan='3'><!--Place Holder--></th><th><a href='?cmd=new'><button>Add New</button></a></th></tr>
                        <tr><th>Tag</th><th>Name</th><th>Value</th><th>Options</th></tr>
                    </thead>
                    <tbody id='tagTableBody'>
                        [[resolutions]]
                    </tbody>
                 </table>
                 [[form]]
                        </div>
                    </div>
                 <script>
                    $(document).ready(function () {
                        $('.sticky-header').floatThead({
                            scrollingTop: 50
                        });

                    });
                 </script>";

        $key = SEEDInput_Int("key");
        $cmd = SEEDInput_Str("cmd");

        switch($cmd){
            case "save":
                $tag = SEEDInput_Str("tag");
                $name = SEEDInput_Str("name");
                $value = SEEDInput_Str("value");
                if(!$tag || !$name){
                    $sOut = $this->report($sOut, "danger", "Tag and/or Name cannot be empty");
                    break;
                }
                $kfr = $this->kfrel->GetRecordFromDBKey($key);
                if(!$kfr){
                    $result = $this->defineResolution($tag, $name, $value);
                    if(!$result['bOk']){
                        $sOut = $this->report($sOut, 'warning', $result['sError']);
                    }
                    else{
                        $sOut = $this->report($sOut, "success", "Configuration Successful");
                    }
                    break;
                }
                if(!$this->isAvailable($tag, $name, $kfr)){
                    $sOut = $this->report($sOut, 'warning', "The Tag Name pair already has a resolution");
                }
                $kfr->SetValue("tag",$tag);
                $kfr->SetValue("name",$name);
                $kfr->SetValue("value",$value);
                if($kfr->PutDBRow()){
                    $sOut = $this->report($sOut, "success", "Configuration Successful");
                }
                else{
                    $sOut = $this->report($sOut, "warning", "An Error occured while saving to database");
                }
                break;
            case "delete":
                $kfr = $this->kfrel->GetRecordFromDBKey($key);
                if(!$kfr){
                    $sOut = $this->report($sOut, "danger", "Cannot Delete Entry with key of 0");
                    break;
                }
                if($kfr->DeleteRow()){
                    $sOut = $this->report($sOut, "success", "Deleted Successfully, Note: THIS CANNOT BE UNDONE");
                }
                else{
                    $sOut = $this->report($sOut, "warning", "An Error occured while deleteing from database");
                }
                break;
        }

        $ra = $this->kfrel->GetRecordSetRA("");
        $sOut = str_replace("[[resolutions]]", SEEDCore_ArrayExpandRows($ra, "<tr><td>[[tag]]</td><td>[[name]]</td><td>[[value]]</td><td><a href='?cmd=edit&key=[[_key]]'><button>Edit</button></a>&nbsp<a href='?cmd=delete&key=[[_key]]'><button>Delete</button></a></td></tr>"),$sOut);
        $form = "";
        if($cmd == "edit" || $cmd == "new"){
            if($cmd == "edit"){
                $kfr = $this->kfrel->GetRecordFromDBKey($key);
            }
            else{
                $kfr = $this->kfrel->CreateRecord();
            }
            $form .= "<div class='container'><div class='container-fluid' id='formRoot'>
                      <div class='row' style='justify-content: space-around'><h4>Add a Tag</h4></div>
                      <div class='row'>
                      <div class='col-md-12'>
                      <table class='table better-table-striped' style='margin-bottom:0'><form>"
                    ."<input type='hidden' name='key' value=".$kfr->Value("_key")." />"
                    ."<input type='hidden' name='cmd'value='save' />"
                    ."<tr class='row'><td class='col-md-5'><label for='tag'>Tag:</label></td><td class='col-md-7'><input type='text' id='tag' name='tag' value='".$kfr->Value("tag")."' autofocus required /></td></tr>"
                    ."<tr class='row'><td class='col-md-5'><label for='name'>Name:</label></td><td class='col-md-7'><input type='text' id='name' name='name' value='".$kfr->Value("name")."' required /></td></tr>"
                    ."<tr class='row'><td class='col-md-5'><label for='value'>Value:</label></td><td class='col-md-7'><input type='text' id='value' name='value' value='".$kfr->Value("value")."' /></td></tr>"
                    ."<tr class='row'><td class='col-md-5'><input type='submit' value='Save' />"
                    ."</form></td></tr></table></div></div></div>";
        }

        $sOut = str_replace("[[form]]", $form, $sOut);

        //Remove the status place holder if its not needed
        $sOut = str_replace("[[status]]", "", $sOut);

        return $sOut;

    }

    private function report(String $sOut,String $type, String $message):String{
        $alertTemplate = "<div class='alert alert-[[state]]'>[[message]]</div>";
        return str_replace("[[status]]", str_replace(array("[[state]]","[[message]]"), array($type,$message), $alertTemplate), $sOut);
    }

}

class CATSDB_SQL
{
const people_create =
    "CREATE TABLE ".DBNAME.".people (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

        uid          INTEGER NOT NULL DEFAULT 0,

        pronouns     ENUM ('','M','F','O') NOT NULL DEFAULT '',
        first_name   VARCHAR(200) NOT NULL DEFAULT '',
        last_name    VARCHAR(200) NOT NULL DEFAULT '',
        address      VARCHAR(200) NOT NULL DEFAULT '',
        city         VARCHAR(200) NOT NULL DEFAULT '',
        province     VARCHAR(200) NOT NULL DEFAULT 'ON',
        postal_code  VARCHAR(200) NOT NULL DEFAULT '',
        dob          VARCHAR(200) NOT NULL DEFAULT '',
        phone_number VARCHAR(200) NOT NULL DEFAULT '',
        email        VARCHAR(200) NOT NULL DEFAULT '',
        extra        TEXT)                              -- we keep getting asked to add more fields so feel free to put them here urlencoded
";

const clients_create =
    "CREATE TABLE ".DBNAME.".clients2 (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

        fk_people        INTEGER NOT NULL DEFAULT 0,
        parents_name     VARCHAR(200) NOT NULL DEFAULT '',
        parents_separate TINYINT NOT NULL DEFAULT 0,        # should be able to use BIT(1) type, but fails on MariaDb (linux)
        school           VARCHAR(200) NOT NULL DEFAULT '',  # blank for not aplicable
        referral         VARCHAR(500) NOT NULL DEFAULT '',
        background_info  VARCHAR(500) NOT NULL DEFAULT '',
        clinic           INTEGER NOT NULL DEFAULT 1,
        code             VARCHAR(20) NOT NULL DEFAULT '')
    ";

const pros_internal_create =
    "CREATE TABLE ".DBNAME.".pros_internal (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

        fk_people   INTEGER NOT NULL DEFAULT 0,
        pro_role    VARCHAR(200) NOT NULL DEFAULT '',
        fax_number  VARCHAR(200) NOT NULL DEFAULT '',
        rate        INTEGER NOT NULL DEFAULT 0,
        clinic      INTEGER NOT NULL DEFAULT 1)
    ";

const pros_external_create =
    "CREATE TABLE ".DBNAME.".pros_external (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

        fk_people  INTEGER NOT NULL DEFAULT 0,
        pro_role   VARCHAR(200) NOT NULL DEFAULT '',
        fax_number VARCHAR(200) NOT NULL DEFAULT '',
        rate       INTEGER NOT NULL DEFAULT 0,
        clinic     INTEGER NOT NULL DEFAULT 1)
    ";

const clientsxpros_create =
    "CREATE TABLE ".DBNAME.".clientsxpros (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

        fk_clients2       INTEGER NOT NULL DEFAULT 0,
        fk_pros_internal  INTEGER NOT NULL DEFAULT 0,
        fk_pros_external  INTEGER NOT NULL DEFAULT 0)
    ";

const assessments_scores_create =
    "CREATE TABLE ".DBNAME.".assessments_scores (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

        date              VARCHAR(200) NOT NULL DEFAULT '',
        respondent        VARCHAR(200) NOT NULL DEFAULT '',
        fk_clients2       INTEGER NOT NULL DEFAULT 0,
        fk_pros_external  INTEGER NOT NULL DEFAULT 0,
        testType          VARCHAR(20) NOT NULL DEFAULT '',
        results           TEXT)
    ";

const resources_files_create =
    /* tags contains strings separated by tab characters
     * e.g. '\tfoo\tbar\tblart\t'
     *      so you can search using LIKE '%\t$search\t%'
     */
    "CREATE TABLE ".DBNAME.".resources_files (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

        folder            TEXT NOT NULL,
        filename          TEXT NOT NULL,
        tags              TEXT NOT NULL)
    ";

const tag_name_resolution_create =
    "CREATE TABLE ".DBNAME.".tag_name_resolution (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

        tag                     TEXT NOT NULL, -- tag to resolve
        name                    TEXT NOT NULL, -- value to be replaced
        value                   TEXT NOT NULL) -- value to replace with
    ";
}

?>