<?php

include( SEEDROOT."seedlib/SEEDGoogleService.php" );

class Calendar
{
    private $oApp;

    function __construct( SEEDAppSessionAccount $oApp )
    {
        $this->oApp = $oApp;
    }

    function DrawCalendar()
    {
        $s = "";

        // Get the command parameter, used for responding to user actions
        $cmd = SEEDInput_Str('cmd');
        // Get the id of the event
        $apptId = SEEDInput_Str('apptId');


        $oGC = new CATS_GoogleCalendar();               // for appointments on the google calendar
        $oApptDB = new AppointmentsDB( $this->oApp );   // for appointments saved in cats_appointments

        /* Get a list of all the calendars that this user can see
         */
        list($raCalendars,$sCalendarIdPrimary) = $oGC->GetAllMyCalendars();

        /* Get the id of the calendar that we're currently looking at. If there isn't one, use the primary.
         */
        $calendarIdCurrent = $this->oApp->sess->SmartGPC( 'calendarIdCurrent', array($sCalendarIdPrimary) );

        /* If the user has booked a free slot, store the booking
         */
        if( $cmd == 'booking' && ($sSummary = SEEDInput_Str("bookingSumary")) ) {
            $oGC->BookSlot( $calendarIdCurrent, $apptId, $sSummary );
            echo("<head><meta http-equiv=\"refresh\" content=\"0; URL=".CATSDIR."\"></head><body><a href=".CATSDIR."\"\">Redirect</a></body>");
            die();
        }
        if( $cmd == 'delete'){
            $this->deleteAppt($calendarIdCurrent,$apptId);
            $s .= "<div class='alert alert-success'> Appointment Deleted</div>";
        }

        /* Show the list of calendars so we can choose which one to look at
         * The current calendar will be selected in the list.
         */
        $oForm = new SEEDCoreForm('Plain');

        $s .= "<form method='post'>"
             .$oForm->Select( 'calendarIdCurrent', $raCalendars, "Calendar",
                              array( 'selected' => $calendarIdCurrent, 'attrs' => "onchange='submit();'" ) )
             ."</form>";


        // Get the dates of the monday-sunday period that includes the current day.
        // Yes, php can do this and a lot of other cool natural-language dates.
        //
        // Note that "this monday" means the monday contained within the next 7 days, "last monday" gives a week ago if today is monday,
        // so "monday this week" is better than those
        $tMonThisWeek = strtotime('monday this week');

        if( !($tMon = SEEDInput_Int('tMon')) ) {
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

                    $sList .= $this->drawEvent( $event, 'nonadmin', null );

                } else {
                    // Admin user: check this google event against our appointment list
                    $kfrAppt = $oApptDB->KFRel()->GetRecordFromDB("google_event_id = '".$event->id."'");

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
                    $invoice = (($cmd == 'invoice' && $apptId == $event->id)?NULL:"true");
                    if($invoice && SEEDInput_Int('tMon')){
                        $invoice = "&tMon=".SEEDInput_Str('tMon');
                    }
                    $sList .= $this->drawEvent( $event, $eType, $kfrAppt, $invoice );
                }

            }
            if( $sList )  $sList .= "</div>";   // end the last <div class='day'>
        }

        $linkGoToThisWeek = ( $tMon != $tMonThisWeek ) ? "<a href='?tMon=$tMonThisWeek'> Back to the current week </a>" : "";
        $sCalendar = "<div class='row'>"
                        ."<div class='col-md-1'><a href='?tMon=".($tMon-3600*24*7)."'><img src='" . CATSDIR_IMG . "arrow.jpg' style='transform: rotate(180deg); height: 20px; position: relative; top: 5px;' alt='->'>  </a></div>"
                        ."<div class='col-md-8'><h3>Appointments from ".date('Y-m-d', $tMon)." to ".date('Y-m-d', $tSun)."</h3></div>"
                        ."<div class='col-md-2'>$linkGoToThisWeek</div>"
                        ."<div class='col-md-1'><a href='?tMon=".($tMon+3600*24*7)."'><img src='" . CATSDIR_IMG . "arrow.jpg' style='height: 20px' alt='->'> </a></div>"
                    ."</div>";
        $sCalendar .= $sList;


        /* Get the list of appointments known in CATS
         */
        $sAppts = "<h3>CATS appointments</h3>";
        $raAppts = $oApptDB->GetList( "eStatus in ('REVIEWED')" );
        foreach( $raAppts as $ra ) {
            $eventId = $ra['google_event_id'];
            $eStatus = $ra['eStatus'];
            $startTime = $ra['start_time'];
            $clientId = $ra['fk_clients'];

            // Now look through the $raEvents that you got from google and try to find the google event with the same event id.
            // If the date/time is different (someone changed it it google calendar), give a warning in $sAppts.
            // If the client is not known clientId==0, give a warning in $sAppts.
//this was just temporary; the CATS appointments will be built into the main calendar now
//            $sAppts .= "<div>$startTime : $clientId</div>";
        }

        //$s .= "<div class='row'><div class='col-md-6'>$sCalendar</div><div class='col-md-6'>$sAppts</div></div>";
        $s .= $sCalendar;

        $s .= "
    <style>
       div.appointment {
           width: 90%;
       }
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
	       width: 105px;
	       padding: 2px;
	       background-color: #99ff99;
	       margin-top: 5px;
	       margin-bottom: 5px;
           box-sizing: content-box;
           height: 150px;
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
    </style>
    <script>
        var x = document.createElement('img');
        x.src = 'https://cdn1.iconfinder.com/data/icons/pixel-perfect-at-16px-volume-2/16/5001-128.png';
        x.className = 'drop-arrow';
        var z = document.getElementsByClassName('day');
        for(y = 0; y < z.length; y++) {
	       var w = x.cloneNode();
           var e = z[y].firstChild;
	       z[y].insertBefore(w, e);
	       w.onclick = rotateMe;
           e.onclick = rotateMe;
        }
        function rotateMe(e) {
           e.preventDefault();
           window.getSelection().removeAllRanges();
	       this.parentElement.classList.toggle('collapsed');
        }
        function expand() {
	       var days = document.getElementsByClassName('day');
	       for (var loop = 0; loop < days.length; loop++) {
		   days[loop].classList.remove('collapsed');
	   }
    }
    function collapse() {
	   var days = document.getElementsByClassName('day');
	   for (var loop = 0; loop < days.length; loop++) {
	       days[loop].classList.add('collapsed');
	   }
    }
</script>";

        return( $s );
    }

    private function drawEvent( $event, $eType, KeyframeRecord $kfrAppt = null, $invoice = null)
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
            $start = substr($start, 0,-6);
        }
        if( !$tz ) $tz = 'America/Toronto';
        $time = new DateTime($start, new DateTimeZone($tz));


        $classFree = strtolower($event->getSummary()) == "free" ? "free" : "busy";
        $sOnClick = strtolower($event->getSummary()) == "free" ? $this->bookable($event->id) : "";

        switch( $eType ) {
            case 'new':
                $sSpecial = $this->formNewAppt( $event );
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
        if($kfrAppt && $kfrAppt->Value('fk_clients')){
            //This string defines the general format of all invoices
            //The correct info for each client is subed in later with sprintf
            //TODO add parameter for session desc
            $sInvoice = "<form><div class='row'><div class='col-md-6'><span>Name:&nbsp </span> <input type='text' value='%1\$s'></div> <div class='col-md-6'> <span>Send invoice to:&nbsp; </span> <input type='email' value='%2\$s'></div></div>"
                        . "<div class='row'><div class='col-md-6'><span>Session length:&nbsp; </span><input type='text' value='%4\$s'></div><div class='col-md-6'><span> Preptime:&nbsp </span> <input type='number' value='%3\$d'></div></div>"
                        . "<div class='row'><div class='col-md-12'><span>Rate: </span> <input type='text' value='$%6\$d'></div></div><input type='submit' value='Confirm'></form>";
            $kfrClient = (new ClientsDB($this->oApp->kfdb))->GetClient($kfrAppt->Value('fk_clients'));
            $session = date_diff(date_create(($event->start->dateTime?$event->start->dateTime:$event->start->date)), date_create(($event->end->dateTime?$event->end->dateTime:$event->end->date)));
            $sInvoice = sprintf($sInvoice,$kfrClient->Value('client_name'),$kfrClient->Value('email'),$kfrAppt->Value('prep_minutes'),$session->format("%h:%i"),$time->format("M jS Y"),100);//TODO Replace 100 fee with code to determine fee
            if($invoice){
                if($invoice == 'true'){
                    $invoice = "";
                }
                $sInvoice = "<a href='?cmd=invoice&apptId=".$event->id.$invoice."'><img src='".CATSDIR_IMG."invoice.png' style='max-width:20px;'/></a>"
                           ."&nbsp;&nbsp;"
                           ."<a href='cats_invoice.php?id=".$kfrAppt->Key()."' target='_blank'>Show Invoice</a>"
                           ."&nbsp;&nbsp;"
                           ."<a href='?cmd=delete&apptId=$event->id$invoice'>Delete Appointment</a>"
                           ."&nbsp;&nbsp;"
                           ."<a href='?cmd=cancel&apptId=$event->id$invoice'><img src='".CATSDIR_IMG."reject-resource.png' style='max-width:20px;'/></a>";
            }
        }
        $s .= "<div class='appointment $classFree' $sOnClick ><div class='row'><div class='col-md-3'>$sAppt</div><div class='col-md-9'>$sInvoice</div></div></div>";

        return $s;
    }

    private function formNewAppt( $event )
    {
        $s = "<h5>This appointment is new:</h5><br />Please Specify client"
            ."<form method='post' action='' class='appt-newform'>"
            ."<input type='hidden' id='appt-gid' name='appt-gid' value='".$event->id."'>"
            ."<select id='appt-clientid' name='appt-clientid'>"
                .SEEDCore_ArrayExpandRows( (new ClientsDB( $this->oApp->kfdb ))->KFRel()->GetRecordSetRA(""), "<option value='[[_key]]'>[[client_name]]</option>" )
            ."</select>"
            ."<input type='submit' value='Save'/>"
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
        $oGC = new CATS_GoogleCalendar();               // for appointments on the google calendar
        $oApptDB = new AppointmentsDB( $this->oApp );   // for appointments saved in cats_appointments

        if( ($googleEventId = @$ra['appt_gid']) &&
            ($catsClientId = @$ra['cid']) &&
            // Assume that the current calendar has already been set in session vars. If not, we can't create an appointment.
            ($calendarIdCurrent = $this->oApp->sess->SmartGPC( 'calendarIdCurrent' )) &&
            ($event = $oGC->getEventByID($calendarIdCurrent,$googleEventId)) )
        {
            $kfr = $oApptDB->KFRel()->CreateRecord();
            $kfr->SetValue("google_event_id", $event->id);
            $kfr->SetValue("start_time", substr($event->start->dateTime, 0, 19) );  // yyyy-mm-ddThh:mm:ss is 19 chars long; trim the timezone part
            $kfr->SetValue("fk_clients",$catsClientId);
            $kfr->PutDBRow();
        }
    }
    
    public function deleteAppt($calendarId, $apptId){
        $oApptDB = new AppointmentsDB( $this->oApp );
        $kfrAppt = $oApptDB->KFRel()->GetRecordFromDB("google_event_id = '".$apptId."'");
        $kfrAppt->StatusSet("Deleted");
        $kfrAppt->PutDBRow();
        $oGC = new CATS_GoogleCalendar();
        $oGC->deleteEvent($calendarId, $apptId);
    }
    
}


class CATS_GoogleCalendar
{
    private $service;

    function __construct()
    {
        $raGoogleParms = array(
                'application_name' => "Google Calendar for CATS",
                // If modifying these scopes, regenerate the credentials at ~/seed_config/calendar-php-quickstart.json
//                'scopes' => implode(' ', array( Google_Service_Calendar::CALENDAR_READONLY ) ),
                'scopes' => implode(' ', array( Google_Service_Calendar::CALENDAR ) ),
                // Downloaded from the Google API Console
                'client_secret_file' => CATSDIR_CONFIG."google_client_secret.json",
                // Generated by getcreds.php
                'credentials_file' => CATSDIR_CONFIG."calendar-php-quickstart.json",
        );

        if( !file_exists($raGoogleParms['client_secret_file']) )  echo "<p>Google client file not found</p>";
        if( !file_exists($raGoogleParms['credentials_file']) )    echo "<p>Google cred file not found</p>";


        $oG = new SEEDGoogleService( $raGoogleParms, false );
        $oG->GetClient();
        $this->service = new Google_Service_Calendar($oG->client);
    }

    function GetAllMyCalendars()
    {
        $raCalendars = array();
        $sCalendarIdPrimary = "";

        if( !$this->service ) goto done;

        $opts = array();
        // calendars are paged; pageToken is not specified on the first time through, then nextPageToken is specified as long as it exists
        while( ($calendarList = $this->service->calendarList->listCalendarList( $opts )) ) {
            foreach ($calendarList->getItems() as $calendarListEntry) {
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
