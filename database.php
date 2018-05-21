<?php

require_once SEEDROOT."Keyframe/KeyframeRelation.php";

define( "DBNAME", "ot" );

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
        $this->kfrel = new KeyFrame_Relation( $kfdb, $this->kfreldef, $uid );
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
        $this->kfrel = new KeyFrame_Relation( $kfdb, $this->kfreldef, $uid );
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
        $this->kfrel   = new KeyFrame_Relation( $kfdb, $this->kfreldef,   $uid );
        $this->kfrel_X = new KeyFrame_Relation( $kfdb, $this->kfreldef_X, $uid );
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
        $this->kfrel = new KeyFrame_Relation( $oApp->kfdb, $this->kfreldef, $oApp->sess->GetUID() );
    }

    function GetList( $sCond )
    {
        return( $this->kfrel->GetRecordSetRA( $sCond, $raKFParms = array() ) );
    }
}


function createTables( KeyframeDatabase $kfdb )
{
    DRSetup( $kfdb );

    if( !tableExists( $kfdb, DBNAME.".clients" ) ) {
        echo "Creating the Client table";

        $kfdb->SetDebug(2);
        $kfdb->Execute( "CREATE TABLE ".DBNAME.".clients (
            _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
            _created    DATETIME,
            _created_by INTEGER,
            _updated    DATETIME,
            _updated_by INTEGER,
            _status     INTEGER DEFAULT 0,

            client_name VARCHAR(200) NOT NULL DEFAULT '',
            parents_name VARCHAR(200) NOT NULL DEFAULT '',
            parents_separate BIT(1) NOT NULL DEFAULT b'0',
            address VARCHAR(200) NOT NULL DEFAULT '',
            city VARCHAR(200) NOT NULL DEFAULT '',
            postal_code VARCHAR(200) NOT NULL DEFAULT '',
            dob VARCHAR(200) NOT NULL DEFAULT '',
            phone_number VARCHAR(200) NOT NULL DEFAULT '',
            email VARCHAR(200) NOT NULL DEFAULT '',
            referal VARCHAR(500) NOT NULL DEFAULT '',
            background_info VARCHAR(500) NOT NULL DEFAULT '')" );

        $kfdb->Execute( "INSERT INTO ".DBNAME.".clients (_key,client_name) values (null,'Eric')" );
        $kfdb->Execute( "INSERT INTO ".DBNAME.".clients (_key,client_name) values (null,'Joe')" );
        $kfdb->SetDebug(0);
    }

    if( !tableExists( $kfdb, DBNAME.".professionals" ) ) {
        echo "Creating the Pros table";

        $kfdb->SetDebug(2);
        $kfdb->Execute( "CREATE TABLE ".DBNAME.".professionals (
            _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
            _created    DATETIME,
            _created_by INTEGER,
            _updated    DATETIME,
            _updated_by INTEGER,
            _status     INTEGER DEFAULT 0,

            pro_name VARCHAR(200) NOT NULL DEFAULT '',
            pro_role VARCHAR(200) NOT NULL DEFAULT '',
            address VARCHAR(200) NOT NULL DEFAULT '',
            city VARCHAR(200) NOT NULL DEFAULT '',
            postal_code VARCHAR(200) NOT NULL DEFAULT '',
            phone_number VARCHAR(200) NOT NULL DEFAULT '',
            fax_number VARCHAR(200) NOT NULL DEFAULT '',
            rate INTEGER NOT NULL DEFAULT 0,
            email VARCHAR(200) NOT NULL DEFAULT '')" );

        $kfdb->Execute( "INSERT INTO ".DBNAME.".professionals (_key,pro_name,pro_role) values (null,'Jose','Dentist')" );
        $kfdb->Execute( "INSERT INTO ".DBNAME.".professionals (_key,pro_name,pro_role) values (null,'Darth Vader','Surgeon')" );
        $kfdb->SetDebug(0);
    }

    if( !tableExists( $kfdb, DBNAME.".clients_pros" ) ) {
        echo "Creating the Client X Pros table";

        $kfdb->SetDebug(2);
        $kfdb->Execute( "CREATE TABLE ".DBNAME.".clients_pros (
            _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
            _created    DATETIME,
            _created_by INTEGER,
            _updated    DATETIME,
            _updated_by INTEGER,
            _status     INTEGER DEFAULT 0,

            # when these foreign keys equal the _key fields of tables 'clients' and 'professionals', it means
            # that client and professional are connected.
            #
            # e.g. if fk_clients==2 and fk_professionals==3 it means the professional with _key==3 is a provider
            #      for the client with _key=2.
            #
            # Notice this means every client can have any number of providers because you can put any
            # number of rows in this table for a particular client.

            fk_clients       INTEGER NOT NULL DEFAULT 0,
            fk_professionals INTEGER NOT NULL DEFAULT 0 )" );

        $kfdb->Execute( "INSERT INTO ".DBNAME.".clients_pros (_key,fk_clients,fk_professionals) values (null,1,1)" );  // Jose is Eric's dentist
        $kfdb->Execute( "INSERT INTO ".DBNAME.".clients_pros (_key,fk_clients,fk_professionals) values (null,2,2)" );  // Darth Vader is Joe's surgeon
        $kfdb->SetDebug(0);
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

            google_event_id  VARCHAR(200) NOT NULL DEFAULT '',
            eStatus          ENUM('REVIEWED','COMPLETED','CANCELLED','MISSED') NOT NULL DEFAULT 'REVIEWED',
            start_time       DATETIME NULL,
            session_minutes  INTEGER NOT NULL DEFAULT 0,  -- initially calculated from calendar
            prep_minutes     INTEGER NOT NULL DEFAULT 10,
         -- total_time       INTEGER NOT NULL DEFAULT 0,    just session+prep
            rate             INTEGER NOT NULL DEFAULT 0,  -- from pros but editable  
            invoice_email    VARCHAR(200) NOT NULL DEFAULT 0,
            fk_clients       INTEGER NOT NULL DEFAULT 0,
            fk_professionals INTEGER NOT NULL DEFAULT 0,
            note             TEXT,
            session_desc     TEXT,
            fk_cats_invoices INTEGER NOT NULL DEFAULT 0)" );

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
                        array('DropTables',     'RWA',       1,  'NULL'),
                        array('Calendar',       'RW',   'NULL',       5),
                        array('Calendar',       'A',    'NULL',       4),
                      ) as $ra )
        {
            $bRet = $kfdb->Execute( "INSERT INTO SEEDSession_Perms (_key,_created,_updated,perm,modes,uid,gid) "
                                           ."VALUES (NULL, NOW(), NOW(), '{$ra[0]}', '{$ra[1]}', {$ra[2]}, {$ra[3]})");
        }
    }
}

function tableExists( KeyframeDatabase $kfdb, $tablename )
{
    return( $kfdb->TableExists( $tablename ) );
}

?>