<?php

require_once SEEDROOT."Keyframe/KeyframeRelation.php";

define( "DBNAME", $catsDefKFDB['kfdbDatabase'] );

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
            fk_leader INTEGER NOT NULL DEFAULT 1)" );

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
        $this->t['P']  = array( "Table" => DBNAME.".people",        "Fields" => 'Auto' );
        $this->t['C']  = array( "Table" => DBNAME.".clients2",      "Fields" => 'Auto' );
        $this->t['PI'] = array( "Table" => DBNAME.".pros_internal", "Fields" => 'Auto' );
        $this->t['PE'] = array( "Table" => DBNAME.".pros_external", "Fields" => 'Auto' );
        $this->t['CX'] = array( "Table" => DBNAME.".clientsxpros",  "Fields" => 'Auto' );

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

        $raKfrel['P']  = $this->newKfrel( $kfdb, $uid, array(                       'P' => $this->t['P'] ), $sLogfile );
        $raKfrel['C']  = $this->newKfrel( $kfdb, $uid, array( 'C' =>$this->t['C'],  'P' => $this->t['P'] ), $sLogfile );
        $raKfrel['PI'] = $this->newKfrel( $kfdb, $uid, array( 'PI'=>$this->t['PI'], 'P' => $this->t['P'] ), $sLogfile );
        $raKfrel['PE'] = $this->newKfrel( $kfdb, $uid, array( 'PE'=>$this->t['PE'], 'P' => $this->t['P'] ), $sLogfile );
        $raKfrel['CX'] = $this->newKfrel( $kfdb, $uid, array( 'CX'=>$this->t['CX'] ),                       $sLogfile );

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

        fk_clients2       INTEGER NOT NULL DEFAULT 0,
        fk_pros_external  INTEGER NOT NULL DEFAULT 0,
        testid            INTEGER NOT NULL DEFAULT 0,
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
}

?>