<?php

/* Appointments and Invoices are treated as the same data structure (cats_appointments)
 * because we always bill each appointment on separate invoice.
 *
 * Each appointment starts as a Google calender event.
 * CATS notices this, asks the therapist to confirm client id and creates a cats_appointment with status REVIEWED.
 * REVIEWED appointments can be DELETED (not billed).
 * REVIEWED appointments can be COMPLETED (billed) when the therapist provides billing details.
 * COMPLETED appointments have invoices that can be sent by email, etc.
 * COMPLETED appoints become PAID when payment is received.
 *
 * Classes:
 *      CATSAppointments    - handles db changes, manages state transitions.
 *      CATSCalendar        - draws calendar UI, and connects with CATS_GoogleCalendar
 *      CATS_GoogleCalendar - interface to google calendar
 *      CATSInvoices        - formats and sends invoices
 */

include( SEEDROOT."seedlib/SEEDGoogleService.php" );
include( SEEDCORE."SEEDGrid.php" );

class Appointments
{
    public $oApptDB;    // anyone with an Appointments can also use an AppointmentsDB

    private $oApp;
    private $oQ;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        $this->oApptDB = new AppointmentsDB( $this->oApp );
        $this->oQ = new SEEDQ( $oApp );
    }

    static function SessionHoursCalc( KeyframeRecord $kfrAppt )
    {
        $ra['total_minutes'] = intval($kfrAppt->Value('session_minutes'))+intval($kfrAppt->Value('prep_minutes'));

        // G is the hours of 24h time without leading zero. This is really meant for displaying the time
        // of day, but it does what we want for displaying a duration in hours:minutes
        $ra['time_format'] = date("G:i", mktime(0,$ra['total_minutes']) );

        $ra['payment'] = ($ra['total_minutes']/60)*$kfrAppt->Value('rate');

        return( $ra );
    }

    function Cmd( $cmd, $kAppt, $raParms )
    /*************************************
        All code that changes cats_appointments should be called through this interface.
        Why? Because it is the exact interface that ajax can use, therefore allowing internal php code as well as external javascript
        code to execute the same commands.
     */
    {
        $raCmds = array( // If appt is REVIEWED you can review it again, delete it, cancel it, or complete it
                         'catsappt--review'   => array( 'REVIEWED', 'apptReview' ),
                         'catsappt--delete'   => array( 'REVIEWED', 'apptDelete' ),
                         'catsappt--cancel'   => array( 'REVIEWED', 'apptCancel' ),
                         'catsappt--complete' => array( 'REVIEWED', 'apptComplete' ),

                         // If appt is COMPLETED you can't do any of the above anymore
                         // but you can amend info, or you can send/resend an invoice.
                         // When payment is received, the appt becomes PAID
                         'catsappt--completeamend' => array( 'COMPLETED', 'apptCompleteAmend' ),
                         'catsappt--sendinvoice'   => array( 'COMPLETED', 'apptSendInvoice' ),
                         'catsappt--paid'          => array( 'COMPLETED', 'apptPaid' ),

                         // When payment has been received all you can do is send/resend a receipt
                         'catsappt--send receipt'  => array( 'PAID', 'apptSendReceipt' ),
        );

        $rQ = $this->oQ->GetEmptyRQ();

        if( !isset( $raCmds[$cmd] ) )  goto done;

// TODO: enforce these security checks.  IsAllowed() does part of the work, but we also have to check whether the current user
//       is allowed to access the particular kAppt
    if( strpos($cmd,'---') !== false ) {
        /* This is an admin command. Make sure the current user has Admin permission on catsappt OR the given kAppt appointment
         */
    } else if( strpos($cmd,'--') !== false ) {
        /* This is a write command. Make sure the current user has Write permission on catsappt AND the given kAppt appointment
         */
    } else {
        /* This is a read command. Make sure the current user has Read permission on the given kAppt appointment.
         */
    }
        list($ok,$dummy,$sErr) = $this->oApp->sess->IsAllowed( $cmd );
        $dummy;
        if( !$ok ) {
            $rQ['sErr'] = $sErr;
            goto done;
        }

        /* The only command we can do without a kAppt is adding a new appointment.
           Check for this special case; otherwise do other commands if they are allowed for the appt's eStatus
         */
        if( !$kAppt ) {
            if( $cmd == 'catsappt--review' ) {
                $kfrAppt = $this->oApptDB->GetKFR( 0 );     // same as CreateRecord
                $rQ = $this->apptReview( $kfrAppt, $raParms );
            }
        } else if( ($kfrAppt = $this->oApptDB->GetKFR( $kAppt )) ) {

            // kluge: catsappt--complete is only allowed from REVIEWED and
            //        catsappt--completeamend is only allowed from COMPLETED but they both do the same thing and they're called
            //        from the same form (but the first time the eStatus is changed). So choose the cmd based on the eStatus
            if( in_array($cmd, array('catsappt--complete','catsappt--completeamend')) ) {
                $cmd = ($kfrAppt->Value('eStatus') == 'REVIEWED') ? 'catsappt--complete' : 'catsappt--completeamend';
            }

            $onlyAllowedForThisStatus = $raCmds[$cmd][0];
            $fn = $raCmds[$cmd][1];

            if( $kfrAppt->Value('eStatus') == $onlyAllowedForThisStatus ) {
                $rQ = $this->$fn( $kfrAppt, $raParms );
            }
        }

        done:
        return( $rQ );
    }

    private function apptReview( KeyframeRecord $kfrAppt, $raParms )
    /*****************************************************************
        Create or update a cats_appointments record. If this is a new record, kfrAppt is newly created.
     */
    {
        $rQ = $this->oQ->GetEmptyRQ();

        $copyParms = array( 'google_cal_ev_id',
                            'session_minutes',
                            'fk_clients',
                            'fk_professionals',
                            'note'
        );
        foreach( $copyParms as $p ) {
            if( isset( $raParms[$p]) )  $kfrAppt->SetValue( $p, $raParms[$p] );
        }
        // At minimum, the record must have a google_cal_ev_id
        if( !$kfrAppt->Value('google_cal_ev_id') ) {
            $rQ['sErr'] = "Missing event in google calendar";
            goto done;
        }

        $kfrAppt->SetValue( 'google_cal_ev_id', $raParms['google_cal_ev_id'] );
        $cal = new Calendar($this->oApp);
        $raGoogle = $cal->convertDBToGoogle($raParms['google_cal_ev_id'] );
        $calId = $raGoogle['calendarId'];
        $eventId = $raGoogle['eventId'];
        $oGC = new CATS_GoogleCalendar( $this->oApp->sess->SmartGPC('gAccount') );
        $event = $oGC->getEventByID( $calId, $eventId );

        if( !($start = $event->start->dateTime) ) {
            $start = $event->start->date;
        }
        $start = substr( $start, 0, 19 );
        $kfrAppt->SetValue( 'start_time', $start );

        $rQ['bOk'] = $kfrAppt->PutDBRow();
        $rQ['sOut'] = (new Calendar($this->oApp))->drawEvent($calId,$event,'normal',$kfrAppt,'true');

        done:
        return( $rQ );
    }

    private function apptComplete( KeyframeRecord $kfrAppt, $raParms )
    /*****************************************************************
        When treatment is done, the therapist marks the appointment COMPLETED.
        Treatment details are stored.
     */
    {
        $rQ = $this->oQ->GetEmptyRQ();

        // this is the same as completeamend except it changes the eStatus
        $kfrAppt->SetValue( 'eStatus', "COMPLETED" );
        $rQ = $this->apptCompleteAmend( $kfrAppt, $raParms );

        return( $rQ );
    }

    private function apptCompleteAmend( KeyframeRecord $kfrAppt, $raParms )
    /**********************************************************************
        Treatment details for a COMPLETED appointment are amended / saved / re-saved.
     */
    {
        $rQ = $this->oQ->GetEmptyRQ();

        foreach( $this->oApptDB->KFRel()->BaseTableFields() as $field ) {
            if( isset($raParms[$field['alias']]) ) {
                $kfrAppt->SetValue( $field['alias'], $raParms[$field['alias']] );
            }
        }
        if(!$kfrAppt->Value("invoice_date")) {
            $kfrAppt->SetValue("invoice_date", date("Y-M-d"));
        }
        $kfrAppt->PutDBRow();

        return( $rQ );
    }

    private function apptDelete( KeyframeRecord $kfrAppt, $raParms )
    /***************************************************************
        Delete an appointment. This is different than cancel because it carries no penalty to a client and no record is preserved.
     */
    {
        $rQ = $this->oQ->GetEmptyRQ();

// Also delete the google appointment
//        $kfrAppt = $oApptDB->KFRel()->GetRecordFromDB("google_event_id = '".$this->convertGoogleToDB($calendarId, $apptId)."'");
//        $oGC = new CATS_GoogleCalendar( $this->oApp->sess->SmartGPC('gAccount') );
//        $oGC->deleteEvent($calendarId, $apptId);

        $kfrAppt->StatusSet( KeyframeRecord::STATUS_DELETED );
        $kfrAppt->PutDBRow();

        $rQ['sOut'] = "<div class='alert alert-success'>Appointment Deleted</div>";
        $rQ['bOk'] = true;

        return( $rQ );
    }

    private function apptCancel( KeyframeRecord $kfrAppt, $raParms )
    /***************************************************************
        Cancel an appointment. This is different than delete because it represents a missed appointment which might carry a fee,
        and a record of the cancellation is preserved.
     */
    {
        $rQ = $this->oQ->GetEmptyRQ();
        return( $rQ );
    }

    private function apptSendInvoice( KeyframeRecord $kfrAppt, $raParms )
    /********************************************************************
        Send an invoice for a completed (or missed) appointment to the client. This can be repeated any number of times.
     */
    {
        $rQ = $this->oQ->GetEmptyRQ();

        $body = "Dear %s,"
               ."\n"
               ."\n"
               ."Attached is your invoice for services provided for %s.  "
               ."The total owing is $%d.\n\n"
               ."Payment is due by end of day (EOD)."
               ."We accept cash, cheque or e-transfer.  Please make your"
               ." e-transfer payable to %s.\n\n "
               ."Thank you in advance!\n\n"
               ."Sincerely, %s, %s.";
        $body = sprintf( $body,
                         "Bill Name",
                         (new PeopleDB($this->oApp))->GetKFR(ClientList::CLIENT,$kfrAppt->Value("fk_clients"))->Expand("[[client_first_name]] [[client_last_name]]"),
                         SessionHoursCalc($kfrAppt)['payment'],
                         "Clinic accounts receivable",
                         "Therapist",
                         "Designation" );

        include_once( SEEDCORE."SEEDEmail.php" );
        include_once( CATSLIB."invoice/catsinvoice.php" );

        $filename = CATSDIR_FILES.sprintf( "invoices/invoice%04d.pdf", $apptId );
        $oInvoice = new CATSInvoice( $this->oApp, $apptId );
        $oInvoice->InvoicePDF( "F", array('filename'=>$filename) );

        $from = "developer@catherapyservices.ca";
        $to = $kfr->Value('invoice_email');
        $subject = "Your Invoice";
        if( SEEDEmailSend( $from, $to, $subject, "", $body ) ) {
            $rQ['bOk'] = true;
            $rQ['sOut'] = "<p>Invoice was sent to $to</p>";
        }

        return( $rQ );
    }

    private function apptSendReceipt( KeyframeRecord $kfrAppt, $raParms )
    /********************************************************************
        Send an receipt for a paid invoice to the client. This can be repeated any number of times.
     */
    {
        $rQ = $this->oQ->GetEmptyRQ();
        return( $rQ );
    }

    private function apptPaid( KeyframeRecord $kfrAppt, $raParms )
    /*************************************************************
        An invoice has been paid. Change status to PAID.
     */
    {
        $rQ = $this->oQ->GetEmptyRQ();
        return( $rQ );
    }
}


class Calendar
{
    private $oApp;
    private $oAppt;     // Appointments

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
        $this->oAppt = new Appointments( $oApp );
    }

    function DrawCalendar()
    {
        $s = "<div class='row'><div class='col-md-5'>";

        $gAccount = $this->oApp->sess->SmartGPC('gAccount');    // currently selected google account (can be blank if there is only one configured)

        // for appointments on the google calendar
        $oGC = new CATS_GoogleCalendar( $gAccount );
        if( !$oGC->ServiceStarted() ) {
            $s .= $oGC->AccountSelector( $gAccount );
            goto done;
        }
        $s .= $oGC->AccountSelector($gAccount);

        /* Get a list of all the calendars that this user can see
         */
        list($raCalendars,$sCalendarIdPrimary) = $oGC->GetAllMyCalendars($this->oApp);

        /* This user cannot see the calendar we are currently looking at. Clear the Smart GPC
         * of the unavailable calendar so the code below will point to the primary calendar
         */
        if(!in_array($this->oApp->sess->SmartGPC('calendarIdCurrent'), $raCalendars)){
            $this->oApp->sess->VarUnSet('calendarIdCurrent');
        }

        /* Get the id of the calendar that we're currently looking at. If there isn't one, use the primary.
         */
        $calendarIdCurrent = $this->oApp->sess->SmartGPC( 'calendarIdCurrent', array($sCalendarIdPrimary) );

        $s .= $this->processCommands($oGC, $calendarIdCurrent);

        // There are no calendars available for the clinic
        // Show a message to the user that there are no calendars
        // To avoid errors do not access the google api without a calendar id
        if(count($raCalendars) == 0){
            $s .= "<h5>No Calendars Available for this clinic</h5>";
            return $s;
        }

        /* Show the list of calendars so we can choose which one to look at
         * The current calendar will be selected in the list.
         */
        $oForm = new SEEDCoreForm('Plain');

        $s .= "<form method='post'>"
             .$oForm->Select( 'calendarIdCurrent', $raCalendars, "Calendar",
                              array( 'selected' => $calendarIdCurrent, 'attrs' => "onchange='submit();'" ) )
             ."</form></div>";


        // Get the dates of the monday-sunday period that includes the current day.
        // Yes, php can do this and a lot of other cool natural-language dates.
        //
        // Note that "this monday" means the monday contained within the next 7 days, "last monday" gives a week ago if today is monday,
        // so "monday this week" is better than those
        $tMonThisWeek = strtotime('monday this week');

        if( !($tMon = $this->oApp->sess->SmartGPC('tMon')) ) {
            $tMon = $tMonThisWeek;
        }
        $tSun = $tMon + (3600 * 24 * 7 ) - 60;    // Add seven days (in seconds) then subtract a minute. That's the end of next sunday.

        /* Get the google calendar events for the given week
         */
        $raEvents = $oGC->GetEvents( $calendarIdCurrent, $tMon, $tSun );

        /* Get the list of calendar events from Google
         */
        $sList = "";
        if( !count($raEvents) ) {
            $sList .= "No upcoming events found.";
        } else {
            $lastday = "";
            foreach( $raEvents as $event ) {
                /* Surround the events of each day in a <div class='day'> wrapper
                 */
                if( !($start = $event->start->date) ) {
                    $start = strtok( $event->start->dateTime, "T" );    // strtok returns string before T, or whole string if there is no T
                }
                if($start != $lastday){
                    if($lastday != ""){
                        $sList .= "</div>";
                    }
                    $sList .= "<div class='day'>";
                    $time = new DateTime($start);
                    $sList .= "<span class='dayname'>".$time->format("l F jS Y")."</span>";
                    $lastday = $start;
                }

                /* Non-admin users are only allowed to see Free slots and book them
                 */
                if( !$this->oApp->sess->CanAdmin('Calendar') ) {
                    // The current user is only allowed to see Free slots and book them
                    if( strtolower($event->getSummary()) != "free" )  continue;

                    $sList .= $this->drawEvent( $calendarIdCurrent, $event, 'nonadmin', null );

                } else {
                    // Admin user: check this google event against our appointment list
                    $kfrAppt = $this->oAppt->oApptDB->KFRel()->GetRecordFromDB("google_cal_ev_id = '".$this->convertGoogleToDB($calendarIdCurrent,$event->id)."'");

                    if( !$kfrAppt ) {
                        // NEW: this google event is not yet in cat_appointments; show the form to add the appointment
                        $eType = 'new';
                    } else {
                        // Compare the start time of the google event and the cats appointment.
                        // If they're the same, draw the normal appt. If they're different, show a notice.
                        $dGoogle = substr($event->start->dateTime, 0, 19);  // yyyy-mm-ddThh:mm:ss is 19 chars long; trim the timezone part
                        $dCats = $kfrAppt->Value('start_time');
                        if( (substr($dGoogle,0,strpos($dGoogle, "T"))." ".substr($dGoogle,strpos($dGoogle, "T")+1) == $dCats )) {
                            $eType = 'normal';
                        } else {
                            $eType = 'moved';
                        }
                    }
                    // Get the command parameter, used for responding to user actions
                    $cmd = SEEDInput_Str('cmd');
                    $apptId = SEEDInput_Str('apptId');
                    $invoice = (($cmd == 'invoice' && $apptId == $event->id)?null:"true");
                    if($invoice && $this->oApp->sess->SmartGPC('tMon')){
                        $invoice = "&tMon=".$this->oApp->sess->SmartGPC('tMon');
                    }
                    $sList .= $this->drawEvent( $calendarIdCurrent, $event, $eType, $kfrAppt, $invoice );
                }

            }
            if( $sList )  $sList .= "</div>";   // end the last <div class='day'>
        }

        $linkGoToThisWeek = ( $tMon != $tMonThisWeek ) ? "<a href='?tMon=$tMonThisWeek'> Back to the current week </a>" : "";
        $sCalendar = "<div class='col-md-6 row'>"
                        ."<div class='col-md-8'><h3>Appointments from ".date('M d, Y', $tMon)." to ".date('M d, Y', $tSun)."</h3></div>"
                        ."<div class='col-md-2'>$linkGoToThisWeek</div>"
                        ."<div class='col-md-1'><a href='?tMon=".($tMon-3600*24*7)."'><img src='" . CATSDIR_IMG . "arrow.jpg' style='transform: rotate(180deg); height: 20px;' alt='<-'>  </a></div>"
                        ."<div class='col-md-1'><a href='?tMon=".($tMon+3600*24*7)."'><img src='" . CATSDIR_IMG . "arrow.jpg' style='height: 20px' alt='->'> </a></div>"
                    ."</div></div>"
                    ."<div id='weekLinkContainer'>"
                    ."<span>Next 4 weeks from today:</span><br/>";
        for($i=1; $i<5; $i++) {
            $sCalendar .= "<a class='weekLink' href='?tMon=".($tMonThisWeek+($i*3600*24*7))."'> Week of " . date("M d", $tMonThisWeek+($i*3600*24*7)) . "</a> &nbsp;&nbsp;";
        }
        $sCalendar .= "</div></div>";
        $sCalendar .= $sList;
        /*$this->oApp->kfdb->Execute("SELECT * FROM cats_appointments
                INNER JOIN clients ON clients._key = cats_appointments.fk_clients
                WHERE clients.client_first_name = 0 AND clients.client_last_name = 0;");*/

        $s .= $sCalendar;

        $s .= "
    <style>
       div.appt-time,div.appt-summary {
           font-family: 'Roboto', sans-serif;
           display: inline-block;
           margin:0px 20px;
        }
       .drop-arrow {
	       transition: all 0.2s ease-in-out;
	       width: 10px;
	       height: 10px;
	       display: inline;
	       transform: none;
        }
        .collapsed .drop-arrow {
	       transform: rotate(-90deg);
        }
        .appointment {
	       transition: all 0.2s ease-in-out;
	       overflow: hidden;
	       border: 1px dotted gray;
	       border-radius: 5px;
	       padding: 2px;
	       background-color: #63cdfc;
	       margin-top: 5px;
	       margin-bottom: 5px;
           box-sizing: content-box;
           min-height: 180px;
           width: 90%;
        }
        .collapsed .appointment {
	       height: 0;
	       border: none;
	       padding: 0;
	       margin: 0;
        }
        .day {
	       margin: 2px;
        }
        .dayname {
            user-select: none;
        }
        .weekLink {
            margin-bottom: 10px;
        }
        body {
            margin: 8px;
        }
        :root {
            overflow: clip;
        }
        #weekLinkContainer {
            border: 1px dotted black;
            width: fit-content;
            padding: 5px;
            border-radius: 10px;
            position: relative;
            left: 20%;
        }
    </style>
    <script>
        function appt() {
            var x = this;
            while (!x.classList.contains('appointment')) {
                x = x.parentElement;
            }
        return x;
        }
        Object.defineProperty(HTMLElement.prototype, 'appt', {enumerable: false, writable: false, value: appt});
    </script>
    <script src='" . CATSDIR . "w/js/appointments.js'></script>";

        done:
        return( $s );
    }

    function convertGoogleToDB($calendarId,$eventId){
        /*
         * Take a calendar id and event id and convert them into the form used by the DB
         * The method convertDBToGoogle converts the DB form back to the google form
         */
        return $calendarId ." | ". $eventId;
    }

    function  convertDBToGoogle($google_cal_ev_id){
        /*
         * Take a event id from the database and convert it into the form used by google
         * The method convertGoogleToDB converts the google form back into the google form
         */
        $separator = " | ";
        $pos = strpos($google_cal_ev_id, $separator); // get the position of the start of the separator
        $calId = substr($google_cal_ev_id,0, $pos); // Splice off the calendar id
        $pos += strlen($separator); // Advance pos to end of separator
        $evId = substr($google_cal_ev_id, $pos); // Splice off the event id

        return array("calendarId" => $calId, "eventId" => $evId);

    }

    private function processCommands($oGC,$calendarIdCurrent)
    {
        $s = "";

        // Get the command parameter, used for responding to user actions
        $cmd = SEEDInput_Str('cmd');
        // Get the id of the event
        $apptId = SEEDInput_Str('apptId');
        switch($cmd){
            /* If the user has booked a free slot, store the booking
             */
            case "booking":
                if($sSummary = SEEDInput_Str("bookingSumary")) {
                    $oGC->BookSlot( $calendarIdCurrent, $apptId, $sSummary );
                    echo("<head><meta http-equiv=\"refresh\" content=\"0; URL=".CATSDIR."\"></head><body><a href=".CATSDIR."\"\">Redirect</a></body>");
                    die();
                }
                break;
            case 'fulfillAppt':
                $this->oAppt->Cmd( 'catsappt--complete', $apptId, $_REQUEST );   // get the appointment details from $_REQUEST
                $bEmailInvoice = (SEEDInput_Str('submitVal')=="Save and Email Invoice");
                if( $bEmailInvoice ) {
                    $rQ = $this->oAppt->Cmd( 'catsappt--sendinvoice', $apptId, array() );
                    $s .= $rQ['sOut'];
                }
                break;
            case 'cancelFee':
                $kfr = $this->oAppt->oApptDB->KFRel()->GetRecordFromDB("Appts.google_cal_ev_id='".$this->convertGoogleToDB($calendarIdCurrent, $apptId)."'");
                $kfr->SetValue('session_desc',"Cancelation Fee");
                $kfr->SetValue('estatus','CANCELLED');
                $kfr->SetValue('session_minutes',30);
                $kfr->PutDBRow();
                break;
            case '':
                break;
            default:
                return "Unknown Command";
        }

        return( $s );
    }

    function drawEvent( $calendarId, $event, $eType, KeyframeRecord $kfrAppt = null, $invoice = null)
    /***************************************************************************
        eType:
            nonadmin = the user is only allowed to see Free slots and book them. This method is only called for Free slots.
            normal   = this event matches the appointment in cats_appointments
            moved    = this event is in cats_appointments but it has been moved to a different datetime.
            new      = this event is not stored in cats_appointments so show a form for adding it.
     */
    {
        $s = "";

        $admin = $eType != 'nonadmin';

        if(strtolower($event->getSummary()) != "free" && !$admin){
            return "";
        }

        $tz = "";
        if( !($start = $event->start->dateTime) ) {
            $start = $event->start->date;
        }
        if ($event->start->timeZone) {
            $tz = $event->start->timeZone;
        }
        else{
            $tz = substr($start, -6);
            //$start = substr($start, 0,-6);
        }
        if( !$tz ) $tz = 'America/Toronto';
        $time = new DateTime($start, new DateTimeZone($tz));


        $classFree = strtolower($event->getSummary()) == "free" ? "free" : "busy";
        $sOnClick = "";//strtolower($event->getSummary()) == "free" ? $this->bookable($event->id) : "";
        if(strtolower($event->getSummary()) == "free"){
            $eType = "do nothing"; // This prevents the select client form from showing up in free
        }
        switch( $eType ) {
            case 'new':
                $sSpecial = $this->formNewAppt( $calendarId, $event, $start );
                break;
            case 'moved':
                $sSpecial = "NOTICE: THIS APPOINTMENT HAS MOVED - OK";
                break;
            default:
                $sSpecial = "";
                break;
        }

        $sAppt = "<div class='appt-time'>".$time->format("g:ia")."</div>"
             .($admin ? ("<div class='appt-summary'>".$event->getSummary()."</div>") : "")
             ."<div class='appt-special'>$sSpecial</div>";
        $sInvoice = "";
        if( $kfrAppt && $kfrAppt->Value('fk_clients') ) {
            $kfrClient = (new PeopleDB($this->oApp))->GetKFR(ClientList::CLIENT,$kfrAppt->Value('fk_clients'));

            $clientname = $kfrClient->Expand('[[client_first_name]] [[client_last_name]]'); // fixed, not allowed to change in this form

            $session = date_diff(date_create(($event->start->dateTime?$event->start->dateTime:$event->start->date)), date_create(($event->end->dateTime?$event->end->dateTime:$event->end->date)));
            if( $invoice ) {
                // show the information about the invoice/appt
                if($invoice == 'true'){
                    $invoice = "";
                }
                $sInvoice = "<div class='seedjx'>"
                           ."<input type='hidden' name='kAppt' value='".$kfrAppt->Key()."'/>"
                           ."<a href='?cmd=invoice&apptId=".$event->id.$invoice."' data-tooltip='Confirm details and invoice client'>Details &nbsp;<img src='".CATSDIR_IMG."invoice.png' style='max-width:20px; position:relative; top:-5px;'/></a>"
                           ."&nbsp;&nbsp;"
                           ."<a href='?cmd=cancelFee&apptId=$event->id$invoice' data-tooltip='Invoice cancellation fee'> Cancellation fee </a>"
                           ."&nbsp;&nbsp;"
                           ."<button seedjx-cmd='catsappt--delete' class='seedjx-submit' data-tooltip='Delete completely'>Delete Appointment</button>"
                           ."&nbsp;&nbsp;"
                           ."<a href='?cmd=cancel&apptId=$event->id$invoice' data-tooltip='Reload from Google Calendar'><img src='".CATSDIR_IMG."reject-resource.png' style='max-width:20px;'/></a>"
                           ."&nbsp;&nbsp;"
                           ."<a href='cats_invoice.php?id=".$kfrAppt->Key()."' target='_blank'>Show PDF Invoice</a>"
                           ."<div class='seedjx-out'></div>"
                           ."</div>";

                $oGrid = new SEEDBootstrapGrid( array( 'classCol1'=>'col-md-6', 'classCol2'=>'col-md-6') );
                $sInvoice .= $oGrid->Row( "Name: $clientname",
                                          "Send invoice to: ".$kfrAppt->Value('invoice_email') )
                            .$oGrid->Row( "Session length: ".$session->format("%h:%i"),
                                          "Rate ($): ".$kfrAppt->Value('rate') )
                            .$oGrid->Row( "Prep time: ".$kfrAppt->Value('prep_minutes'),
                                          "Session Description: ".$kfrAppt->Value('session_desc') );
            } else {
                // Set default values
                if( !$kfrAppt->Value('rate') )           $kfrAppt->SetValue( 'rate', 110.0 );
                if( !$kfrAppt->Value('session_desc') )   $kfrAppt->SetValue( 'session_desc', "Occupational Therapy Treatment" );
                if( !$kfrAppt->Value('invoice_email') )  $kfrAppt->SetValue( 'invoice_email', $kfrClient->Value('email') );

                //This string defines the general format of all invoices
                //The correct info for each client is subed in later with sprintf
                $oGrid = new SEEDBootstrapGrid( array( 'classCol1'=>'col-md-6', 'classCol2'=>'col-md-6') );
                $sInvoice = "<form>"
                           .$oGrid->Row( "Name: $clientname",
                                         "Send invoice to: <input type='email' name='invoice_email' value='%1\$s'>" )
                           .$oGrid->Row( "Session length (min): <input type='text' name='session_minutes' value='%2\$s' style='width:3em'>",
                                         "Rate ($): <input name='rate' type='text' value='%3\$d' style='width:3em'>" )
                           .$oGrid->Row( "Prep time (min):&nbsp </span> <input type='number' name='prep_minutes' value='%4\$d' style='width:3em'>",
                                         "Session Description: <textarea name='session_desc' rows='1' cols='20'>%5\$s</textarea>" )
                           . "<input type='hidden' name='apptId' value='".$kfrAppt->Key()."'/>"
                           . "<input type='hidden' name='cmd' value='fulfillAppt'/>"
                           . "<input type='submit' name='submitVal' value='Save' />&nbsp;&nbsp;"
                           ."<input type='submit' name='submitVal' value='Save and Email Invoice' />"
                           ."</form>";
                $sInvoice = sprintf($sInvoice,
                                    $kfrAppt->Value('invoice_email'), $session->format("%i"), $kfrAppt->Value('rate'),
                                    $kfrAppt->Value('prep_minutes'), $kfrAppt->ValueEnt('session_desc')
                                    //$session->format("%h:%i"), $time->format("M jS Y")
                                    );
            }
        }
        $s .= "<div class='appointment $classFree' $sOnClick > <div class='row'><div class='col-md-5'>$sAppt</div> <div class='col-md-7'>$sInvoice</div> </div> </div> </div>";

        return $s;
    }

    private function formNewAppt( $sCalendarId, $event )
    {
        $s = "<h5>This appointment is new:</h5><br />Please Specify client"
            ."<form method='post' action='' class='appt-newform'>"
            ."<input type='hidden' id='appt-gid' name='appt-gid' value='".$this->convertGoogleToDB($sCalendarId,$event->id)."'>"
            ."<select id='appt-clientid' name='appt-clientid'>"
                .SEEDCore_ArrayExpandRows( (new PeopleDB( $this->oApp ))->KFRel(ClientList::CLIENT)->GetRecordSetRA(""), "<option value='[[_key]]'>[[client_first_name]] [[client_last_name]]</option>" )
            ."</select>"
            ."<input type='submit' value='Save' onclick='this.appt().style.height=\"150px\"' />"
            ."</form>";

        return( $s );
    }

    private function bookable($id){
        $s = " onclick=\"";
        $s .= "";
        $s .= "window.location='?cmd=booking&apptId=$id&bookingSumary=";
        $s .= "' + prompt('Who is this appointment for?');";
        $s .= "\"";
        return $s;
    }

    public function createAppt($ra){
        //Nessisary variables needed to create new appointments
        // for appointments on the google calendar
        $oGC = new CATS_GoogleCalendar( $this->oApp->sess->SmartGPC('gAccount') );

        if( ($googleEventId = @$ra['appt_gid']) &&
            ($catsClientId = @$ra['cid']) &&
            // Assume that the current calendar has already been set in session vars. If not, we can't create an appointment.
            ($calendarIdCurrent = $this->oApp->sess->SmartGPC( 'calendarIdCurrent' )) &&
            ($event = $oGC->getEventByID($calendarIdCurrent,$googleEventId)) )
        {
            $kfr = $this->oAppt->oApptDB->KFRel()->CreateRecord();
            $kfr->SetValue("google_cal_ev_id", $this->convertGoogleToDB($calendarIdCurrent, $event->id));
            $kfr->SetValue("start_time", substr($event->start->dateTime, 0, 19) );  // yyyy-mm-ddThh:mm:ss is 19 chars long; trim the timezone part
            $kfr->SetValue("fk_clients",$catsClientId);
            $kfr->PutDBRow();
        }
    }

}


class CATS_GoogleCalendar
{
    private $accounts_file;                 // optional file containing list of credentials files
    private $default_creds_file;            // default credentials file if accounts_file doesn't exist
    private $google_client_secret_file;     // the file that authorizes this app as a google client
    private $service = null;

    function __construct( $gAccount = "" )
    {
        $this->accounts_file = CATSDIR_CONFIG."google-accounts.json";
        $this->default_creds_file = CATSDIR_CONFIG."calendar-php-quickstart.json";
        $this->google_client_secret_file = CATSDIR_CONFIG."google_client_secret.json";

        /* Find the credentials file and start the calendar service.
         *      1) use $gAccount to select the credentials file.
         *      2) if there is only one credentials file use that.
         *
         * If there are multiple creds and gAccount is not set, then don't start the service. We use this class to create
         * an account selection control for the user to choose.
         */
        $this->StartService( $gAccount );
    }

    function StartService( $gAccount = "" )
    {
        if( ($creds_file = $this->getCredsFile( $gAccount ) ) ) {
            $this->_startService( $creds_file );
        }
    }

    function ServiceStarted()  { return( $this->service != null ); }

    function AccountSelector( $gAccount )
    /************************************
        Return some html that lets the user choose an account from google-accounts.json
     */
    {
        $s = "";

        if( file_exists( $this->accounts_file ) &&
            ($json = json_decode( file_get_contents( $this->accounts_file ), true )) )
        {
            foreach( array_keys($json) as $name ) {
                if( $s )  $s .= ",";
                    $s .= ($name == $gAccount ? $name : "<a href='?gAccount=$name'>$name</a>");
            }
        }

        return( $s );
    }


    private function getCredsFile( $gAccount )
    /*****************************************
        Look in google-accounts.json to find the matching $gAccount credentials file.

        If google-accounts.json doesn't exist, use the default credentials file.
        If google-accounts.json has only one entry, use it.
        If google-accounts.json has more than one entry use the one specified by gAccount.
     */
    {
        $creds_file = $this->default_creds_file;

        if( file_exists( $this->accounts_file ) &&
            ($json = json_decode( file_get_contents( $this->accounts_file ), true )) )
        {
            if( count($json) == 1 ) {
                /* Only one account listed so use it.
                 */
                foreach( $json as $fname ) {                // there might be a better way to get the value when there's only one item in the array
                    $creds_file = CATSDIR_CONFIG.$fname;
                }
            } elseif( count($json) > 1 ) {
                /* Multiple accounts so get the one named by gAccount
                 */
                if( !$gAccount ) {
                    // error: can't choose a credentials file because the account is not specified
                    $creds_file = "";
                }

                if( ($fname = @$json[$gAccount]) ) {
                    $creds_file = CATSDIR_CONFIG.$fname;
                } else {
                    // error: not found
                    $creds_file = "";
                }
            }
        }

        return( $creds_file );
    }

    private function _startService( $creds_file )
    {
        $raGoogleParms = array(
                'application_name' => "Google Calendar for CATS",
                // If modifying these scopes, regenerate the credentials at ~/seed_config/calendar-php-quickstart.json
                //'scopes' => implode(' ', array( Google_Service_Calendar::CALENDAR_READONLY, Google_Service_Calendar::CALENDAR ) ),
                'scopes' => implode(' ', array( Google_Service_Calendar::CALENDAR ) ),
                // Downloaded from the Google API Console
                'client_secret_file' => $this->google_client_secret_file,
                // Generated by getcreds.php
                'credentials_file' => $creds_file,
        );

        $oG = new SEEDGoogleService( $raGoogleParms, false );
        if( ($client = $oG->GetClient()) ) {                        // this will fail if you happen to be offline
            $this->service = new Google_Service_Calendar($client);
        } else {
            echo $oG->GetErrMsg();
        }
    }

    function GetAllMyCalendars($oApp)
    {
        $raCalendars = array();
        $sCalendarIdPrimary = "";

        if( !$this->service ) goto done;

        $opts = array();
        // calendars are paged; pageToken is not specified on the first time through, then nextPageToken is specified as long as it exists
        while( ($calendarList = $this->service->calendarList->listCalendarList( $opts )) ) {
            foreach ($calendarList->getItems() as $calendarListEntry) {
                if(!(new Clinics($oApp))->isCoreClinic()){
                    if($calendarListEntry["accessRole"] != 'owner' || !$this->checkAssociation($oApp, $calendarListEntry->getID())) continue; // Calendar is not associated with the current clinic
                }
                $raCalendars[$calendarListEntry->getSummary()] = $calendarListEntry->getId();
                if( $calendarListEntry->getPrimary() ) {
                    $sCalendarIdPrimary = $calendarListEntry->getId();
                }
            }
            if( !($opts['pageToken'] = $calendarList->getNextPageToken()) ) {
                break;
            }
        }
        done:
        return( array($raCalendars,$sCalendarIdPrimary) );
    }

    private function checkAssociation($oApp,$calID){
        //Clinics
        $clinics = new Clinics($oApp);
        $clinicsDB = new ClinicsDB($oApp->kfdb);

        $acl = $this->service->acl->listAcl($calID);
        foreach ($acl->getItems() as $rule) {
            $clinic = $clinicsDB->GetClinic($clinics->GetCurrentClinic())->Value('clinic_name');
            if(strtolower($rule->getScope()->getValue()) == strtolower($clinic."@catherapyservices.ca")){
                return TRUE;
            }
        }
        return FALSE;
    }

    function GetEvents( $calendarId, $startdate, $enddate )
    {
        $raEvents = array();

        if( !$this->service ) goto done;

        $optParams = array(
            'orderBy' => 'startTime',
            'singleEvents' => TRUE,
            'timeMin' => date("Y-m-d\TH:i:s\Z", $startdate),
            'timeMax' => date("Y-m-d\TH:i:s\Z", $enddate),
        );
        $results = $this->service->events->listEvents($calendarId, $optParams);

        $raEvents = $results->getItems();

        done:
        return( $raEvents );
    }

    function BookSlot( $calendarId, $slot, $sSummary )
    {
        if( $this->service && ($event = $this->service->events->get($calendarId, $slot)) ) {
            $event->setSummary($sSummary);
            $this->service->events->update($calendarId, $event->getId(), $event);
        }
    }

    function getEventByID($calendarID,$id){
        return( $this->service ? $this->service->events->get($calendarID, $id) : null );
    }

    function deleteEvent($calendarID, $id){
        $this->service->events->delete($calendarID,$id);
    }
}

?>