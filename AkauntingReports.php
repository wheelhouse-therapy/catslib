<?php

class AkauntingReportBase
{
    public $oApp;
    public $raClinics;
    public $raReportParms;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oApp = $oApp;
        $this->raClinics = new Clinics($this->oApp);
        $this->raReportParms = $this->GetReportParmsFromRequest();
    }

    function GetReportParmsFromRequest()
    /***********************************
        Parms that start with Ak_ are cycled through http. Others are for internal use only.
     */
    {
        $raParms = [];

        // Ak_sort sorts a ledger by date or account name
        // sortdb is the sql ORDER BY
        switch( ($raParms['Ak_sort'] = $this->oApp->sess->SmartGPC('Ak_sort', ['date'])) ) {
            case 'date':
                $raParms['sortdb'] = 'date,name,d,c';
                break;
            case 'name':
                $raParms['sortdb'] = 'code,date,d,c';
                break;
        }

        // Ak_clinic is the clinic selector
        $raParms['Ak_clinic'] = $this->oApp->sess->SmartGPC('Ak_clinic', array($this->raClinics->GetCurrentClinic()));

        // Ak_report is the report type
        $raParms['Ak_report'] = $this->oApp->sess->SmartGPC('Ak_report', array('monthly'));

        // Make an urlencoded string that can reproduce the current state
        $raParms['parmsForLink'] = "";
        foreach( $raParms as $k => $v ) {
            if( SEEDCore_StartsWith( $k, "Ak_" ) ) {
                if( $raParms['parmsForLink'] ) $raParms['parmsForLink'] .= "&";
                $raParms['parmsForLink'] .= "$k=".urlencode($v);
            }
        }

        return( $raParms );
    }

    function Style()
    {
        return( "
            <style>
            .AkReportTable td { border:1px solid #aaa }
            </style>
            " );
    }
}

class AkauntingReports
{
    private $oAkReport;

    private $oApp;
    private $oAppAk = null;     // SEEDAppDB
    private $akDb;
    private $akTablePrefix;
    private $clinics;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oAkReport = new AkauntingReportBase( $oApp );

        global $config_KFDB;

        $this->oApp = $oApp;
        $this->clinics = new Clinics($this->oApp);

        if( isset($config_KFDB['akaunting']) ) {
            $this->oAppAk = new SEEDAppDB( $config_KFDB['akaunting'] );
            $this->akDb = $config_KFDB['akaunting']['kfdbDatabase'];
            $this->akTablePrefix = $config_KFDB['akaunting']['ak_tableprefix'];
        } else {
            $this->oApp->oC->AddErrMsg( "config_KFDB['akaunting'] not defined" );   // cats_page will show this at the top of the screen
        }

    }

    function GetLedgerRA( $raParms = array() )
    {
        $raRows = array();

        $sOrderBy = @$raParms['sortdb'] ? " ORDER BY {$raParms['sortdb']} " : "";

        $cid =(new ClinicsDB($this->oApp->kfdb))->GetClinic(@$raParms['Ak_clinic']?:$this->clinics->GetCurrentClinic())->Value("akaunting_company");

        if(!$cid){
            $this->oApp->oC->AddErrMsg("Clinic does not have an accounting ID set");
            goto done;
        }

        $sql =
            "select A.account_id,A.entry_type,A.debit as d,A.credit as c,LEFT(A.issued_at,10) as date, "
                  ."B.company_id as company_id, B.type_id as type_id, B.code as code, B.name as name "
            ."from {$this->akTablePrefix}_double_entry_ledger A, {$this->akTablePrefix}_double_entry_accounts B "
            ."where A.account_id=B.id AND B.company_id='$cid'"
            .$sOrderBy;

        $raRows = $this->oAppAk->kfdb->QueryRowsRA( $sql );

        done:
        return( $raRows );
    }

    function GetLedgerRAForDisplay( $raParms = array() )
    /***************************************************
        Same as GetLedgerRA but if sorting by account put a total after each account
     */
    {
        $raOut = array();
        $raRows = $this->GetLedgerRA( $raParms );

        $raAcctLast = "";
        $total = $dtotal = $ctotal = 0;
        foreach( $raRows as $ra ) {
            if( $raParms['Ak_sort'] == 'name' ) {
                if( $raAcctLast && $raAcctLast != $ra['code'] ) {
                    $raOut[] = ['total'=>$total, 'dtotal'=>$dtotal, 'ctotal'=>$ctotal];
                    $total = $dtotal = $ctotal = 0;
                }
                $dtotal += $ra['d'];
                $ctotal += $ra['c'];
                $total += $ra['d'] - $ra['c'];
                $raAcctLast = $ra['code'];
            }

            $ra['acct'] = $ra['code']." : ".$ra['name'];
            $raOut[] = $ra;
        }

        return( $raOut );
    }

    private function clinicSelector( $reportParms )
    {
        $sForm = "";

        if( ($clinics = $this->clinics->getClinicsWithAkaunting()) ) {
            $clinicsDB = new ClinicsDB($this->oApp->kfdb);
            $sForm = "<form style='display:inline' id='companyForm'><select name='Ak_clinic' onChange=\"document.getElementById('companyForm').submit()\">";
            foreach($clinics as $option){
                $selected = $option==$reportParms['Ak_clinic'] ? "selected" : "";
                $sForm .= $clinicsDB->GetClinic($option)->Expand( "<option value='[[_key]]' $selected>[[clinic_name]]</option>" );
            }
            $sForm .= "</select></form>";
        }

        return( $sForm );
    }

    private function reportSelector( $reportParms )
    {
        $sForm = "<form style='display:inline'>"
                ."<select name='Ak_report' onChange='submit();'>"
                ."<option value='monthly' "    .($reportParms['Ak_report']=='monthly'     ? "selected" : "").">Monthly</option>"
                ."<option value='monthly_sum' ".($reportParms['Ak_report']=='monthly_sum' ? "selected" : "").">Monthly Sum</option>"
                ."<option value='detail' "     .($reportParms['Ak_report']=='detail'      ? "selected" : "").">Detail</option>"
                ."<option value='ledger' "     .($reportParms['Ak_report']=='ledger'      ? "selected" : "").">Ledger</option>"
                ."</select>"
                ."</form>";

        return( $sForm );
    }

    function DrawReport()
    {
        $s = "";

        if( !$this->oAppAk ) goto done;

        $reportParms = $this->oAkReport->GetReportParmsFromRequest();
        if( !$reportParms['Ak_clinic'] ) {
            $this->oApp->oC->ErrMsg( "No clinic defined" );
            goto done;
        }

        $s .= "<div style='clear:both;float:right; border:1px solid #aaa;border-radius:5px;padding:10px'>"
                 ."<a href='jx.php?cmd=therapist-akaunting-xlsx&{$reportParms['parmsForLink']}'><button>Download</button></a>"
                 ."&nbsp;&nbsp;&nbsp;&nbsp;"
                 ."<img src='".W_CORE_URL."img/icons/xls.png' height='30'/>"
                 ."&nbsp;&nbsp;&nbsp;&nbsp;"
             ."</div>";

        $s .= "<div id='companyFormContainer'>".$this->clinicSelector( $reportParms )."</div>"
             ."<div id='companyFormContainer'>".$this->reportSelector( $reportParms )."</div>";

        switch( $reportParms['Ak_report'] ) {
            case 'ledger':       $s .= $this->drawLedgerReport();      break;
            case 'monthly':      $s .= $this->drawMonthlyReport();     break;
            case 'monthly_sum':  $s .= $this->drawMonthlySumReport();  break;
            case 'detail':       $s .= $this->drawDetailReport();      break;
        }

        done:
        return( $s );
    }

    private function drawLedgerReport()
    {
        $raRows = $this->GetLedgerRAForDisplay( $this->oAkReport->raReportParms );

        $s = "<table cellpadding='10' border='1'>"
            ."<tr><th><a href='{$_SERVER['PHP_SELF']}?Ak_sort=date'>Date</a></th>"
                ."<th><a href='{$_SERVER['PHP_SELF']}?Ak_sort=name'>Account</a></th>"
                ."<th>Debit</th>"
                ."<th>Credit</th>"
                ."<th>&nbsp;</th>"
            ."</tr>";
        foreach( $raRows as $ra ) {
            if( isset($ra['total']) ) {
                // sometimes we insert a special row with a total element
                $s .= SEEDCore_ArrayExpand( $ra, "<tr><td>&nbsp;</td><td>&nbsp;</td>"
                                                    ."<td><span style='color:gray'>[[dtotal]]</span></td>"
                                                    ."<td><span style='color:gray'>[[ctotal]]</span></td>"
                                                    ."<td><strong>[[total]]</strong></td></tr>" );
                $s .= "<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>";
            } else {
                $s .= SEEDCore_ArrayExpand( $ra, "<tr><td>[[date]]</td><td>[[acct]]</td>"
                                                    ."<td>[[d]]</td><td>[[c]]</td><td>[[total]]</tr>" );
            }
        }
        $s .= "</table>";

        return( $s );
    }

    private function drawMonthlyReport()
    {
        $s = $this->oAkReport->Style();

        $raRows = $this->GetLedgerRA();
        $raM = [];
        $raMonths = [];
        $raAccts = [];
        foreach( $raRows as $ra ) {
            $month = substr( $ra['date'], 0, 7 );
            $acct = $ra['code']." : ".$ra['name'];
            if( !isset($raM[$month][$acct]) )  $raM[$month][$acct] = 0.0;
            $raM[$month][$acct] += $ra['d'] ?: $ra['c'];

            $raMonths[$month] = 1;
            $raAccts[$acct] = 1;
        }
        ksort($raMonths);
        ksort($raAccts);

        $s .= "<table class='AkReportTable'>"
             ."<tr><td>&nbsp;</td>".SEEDCore_ArrayExpandSeries( $raMonths, "<td><strong>[[k]]</strong></td>", true, ['bUseKeys'=>true] )."</tr>";

        foreach( $raAccts as $acct => $dummy ) {
            $s .= "<tr><td><strong>$acct</strong></td>";
            foreach( $raMonths as $month => $dummy ) {
                $s .= "<td>".(@$raM[$month][$acct] ?: "")."</td>";
            }
            $s .= "</tr>";
        }
        $s .= "</table>";

        return( $s );
    }

    private function drawMonthlySumReport()
    {
        $s = "";

        return( $s );
    }

    private function drawDetailReport()
    {
        $s = "";

        return( $s );
    }
}

function AkauntingReport( SEEDAppConsole $oApp )
{
    $s = "";

    $o = new AkauntingReports( $oApp );

    return( $o->DrawReport() );
}

function AkauntingReport_OutputXLSX( SEEDAppConsole $oApp )
{
    $o = new AkauntingReports( $oApp );

    $oXls = new AkauntingReportSpreadsheet( $oApp );
    $oXls->OutputXLSX();
}


class AkauntingReportSpreadsheet
{
    private $oAkReport;
    private $oApp;
    private $oPeopleDB;
    private $clinics;

    function __construct( SEEDAppConsole $oApp )
    {
        $this->oAkReport = new AkauntingReportBase( $oApp );

        $this->oApp = $oApp;
        $this->oPeopleDB = new PeopleDB( $this->oApp );
        $this->clinics = new Clinics($oApp);
        $this->clinics->GetCurrentClinic();
    }

    function OutputXLSX()
    {
        $raParms = $this->oAkReport->GetReportParmsFromRequest();

        // Initialize the spreadsheet with three sheets (one is created by default)
        $oXls = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $oXls->createSheet();
        $oXls->createSheet();

        // Set document properties
        $oXls->getProperties()->setCreator('Collaborative Approach Therapy Services')
            ->setLastModifiedBy('CATS')
            ->setTitle('Clients / Staff / Providers')
            ->setSubject('Clients / Staff / Providers')
            ->setDescription('Spreadsheet containing Akaunting details')
            ->setKeywords('')
            ->setCategory('CATS Akaunting');

        $filename = "CATS Akaunting.xlsx";

        $o = new AkauntingReports( $this->oApp );
        $raRows = $o->GetLedgerRAForDisplay( $raParms );

        $raCols = array(
            'date'  => 'Date',
            'acct'  => 'Account',
            'd'     => 'Debit',
            'c'     => 'Credit'
        );
        if( $raParms['Ak_sort'] == 'name' ) {
            $raCols['total'] = 'Total';

            $ra2 = $raRows;
            $raRows = array();
            foreach( $ra2 as $ra ) {
                if( isset($ra['total']) ) {
                    $raRows[] = ['date'=>'','acct'=>'','d'=>$ra['dtotal'],'c'=>$ra['ctotal'],'total'=>$ra['total']];
                    $raRows[] = ['date'=>'','acct'=>'','d'=>'','c'=>'','total'=>''];
                } else {
                    $ra['total'] = '';
                    $raRows[] = $ra;
                }
            }
        }

        $this->storeSheet( $oXls, 0, "Akaunting", $raRows, $raCols );

        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $oXls->setActiveSheetIndex(0);

        // Redirect output to a client’s web browser (Xlsx)
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($oXls, 'Xlsx');
        $writer->save('php://output');
    }

    private function storeSheet( $oXls, $iSheet, $sSheetName, $raRows, $raCols )
    {
        $oSheet = $oXls->setActiveSheetIndex( $iSheet );
        $oSheet->setTitle( $sSheetName );

        // Set the headers in row 1
        $c = 'A';
        foreach( $raCols as $dbfield => $label ) {
            $oSheet->setCellValue($c.'1', $label );
            $c = chr(ord($c)+1);    // Change A to B, B to C, etc
        }

        // Put the data starting at row 2
        $row = 2;
        foreach( $raRows as $ra ) {
            $col = 'A';
            foreach( $raCols as $dbfield => $label ) {
                $oSheet->setCellValue($col.$row, $ra[$dbfield] );
                $col = chr(ord($col)+1);    // Change A to B, B to C, etc
            }
            ++$row;
        }
    }
}

//TODO Move to better location
<<<JournalEntryForm
<form method="POST" action="https://catherapyservices.ca/akaunting/double-entry/journal-entry" accept-charset="UTF-8" role="form" enctype="multipart/form-data"><input name="_token" type="hidden" value="o5U4YTeQsy8iB38dnbKL42QbXwl2NiMCBQdJjD5L">

    <div class="box-body">
        <div class="form-group col-md-6 required ">
    <label for="paid_at" class="control-label">Date</label>
    <div class="input-group">
        <div class="input-group-addon"><i class="fa fa-calendar"></i></div>
        <input class="form-control" placeholder="Enter Date" id="paid_at" required="required" data-inputmask="'alias': 'yyyy-mm-dd'" data-mask="" name="paid_at" type="text" value="2019-06-24">
    </div>
    
</div>



        <div class="form-group col-md-6  ">
    <label for="reference" class="control-label">Reference</label>
    <div class="input-group">
        <div class="input-group-addon"><i class="fa fa-file-text-o"></i></div>
        <input class="form-control" placeholder="Enter Reference" name="reference" type="text" id="reference">
    </div>
    
</div>



        <div class="form-group col-md-12 required ">
    <label for="description" class="control-label">Description</label>
    <textarea class="form-control" placeholder="Enter Description" rows="3" required="required" name="description" cols="50" id="description"></textarea>
    
</div>



        <div class="form-group col-md-12">
            <label for="items" class="control-label">Items</label>
            <div class="table-responsive">
                <table class="table table-bordered" id="items">
                    <thead>
                    <tr style="background-color: #f9f9f9;">
                                                <th width="5%" class="text-center" required="">Actions</th>
                                                                        <th width="20%" class="text-left required">Account</th>
                                                                        <th width="20%" class="text-right">Debit</th>
                                                                        <th width="20%" class="text-right">Credit</th>
                                            </tr>
                    </thead>
                    <tbody>
                                                                <tr id="item-row-0">
        <td class="text-center" style="vertical-align: middle;">
                <button type="button" onclick="$(this).tooltip('destroy'); $('#item-row-0').remove(); totalItem();" data-toggle="tooltip" title="Delete" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
            </td>
            <td>
                <select id="item-account-id-0" class="form-control account-select2 input-account select2-hidden-accessible" name="item[0][account_id]" tabindex="-1" aria-hidden="true"><option selected="selected" value="">- Select Account -</option><optgroup label="Bank &amp; Cash"><option value="61">836 - RBC Cats account</option><option value="65">837 - Cash</option></optgroup><optgroup label="Current Asset"><option value="1">120 - Accounts Receivable</option></optgroup><optgroup label="Current Liability"><option value="7">200 - Accounts Payable</option><option value="158">201 - CREDIT CARD RBC</option><option value="8">205 - Accruals</option><option value="9">210 - Unpaid Expense Claims - Sue</option><option value="62">211 - Unpaid Expense Claims - Alison</option><option value="10">215 - Wages Payable</option><option value="13">230 - Employee Tax Payable</option><option value="15">236 - Employee income tax deductions payable</option><option value="16">240 - Corporate Income Tax Payable</option><option value="18">255 - Reconciliation Adjustment</option><option value="19">260 - Rounding</option><option value="20">835 - Revenue Received in Advance</option><option value="21">835 - Clearing Account from CATS Brant</option></optgroup><optgroup label="Depreciation"><option value="45">700 - Depreciation</option></optgroup><optgroup label="Equity"><option value="57">300 - Owners Contribution</option><option value="58">310 - Owners Draw</option><option value="59">320 - Retained Earnings</option></optgroup><optgroup label="Expense"><option value="24">600 - Advertising</option><option value="181">601 - Wages and Salaries</option><option value="25">605 - Bank Service Charges</option><option value="26">610 - Janitorial Expenses</option><option value="27">615 - Consulting - Legal &amp; Accounting</option><option value="28">620 - Meals &amp; Entertainment</option><option value="29">624 - Postage - for reports (not advertising)</option><option value="30">628 - Therapy Supplies - toys/small items</option><option value="63">629 - Therapy Supplies -- Assessment tools</option><option value="64">630 - Therapy supplies -- books and manuals</option><option value="162">631 - Therapy Equipment</option><option value="31">632 - Insurance</option><option value="32">636 - Education Expenses</option><option value="33">640 - Home Office Expense</option><option value="173">643 - Travel Expense</option><option value="34">644 - Mileage Expenses</option><option value="35">648 - Office Supplies</option><option value="37">656 - Rent</option><option value="38">660 - Repairs &amp; Maintenance</option><option value="40">668 - Payroll Tax Expense</option><option value="41">672 - Professional Dues &amp; Memberships</option><option value="42">676 - Telephone &amp; Internet</option><option value="166">677 - Software and small IT devices (non capital)</option><option value="44">684 - Bad Debts</option><option value="46">710 - Corporate Income Tax Expense</option><option value="47">715 - Employee Benefits Expense</option><option value="48">800 - Interest Expense</option></optgroup><optgroup label="Fixed Asset"><option value="3">150 - Office Equipment</option><option value="4">151 - Less Accumulated Depreciation on Office Equipment</option><option value="5">160 - Computer Equipment</option><option value="6">161 - Less Accumulated Depreciation on Computer Equipment</option></optgroup><optgroup label="Liability"><option value="176">840 - Clearing Account from CATS Guelph</option></optgroup><optgroup label="Non-current Liability"><option value="22">290 - Loan</option></optgroup><optgroup label="Revenue"><option value="53">400 - Sales</option><option value="175">408 - Franchisee CATS Core Contributions</option><option value="54">460 - Interest Income</option><option value="55">470 - Other Revenue</option></optgroup></select><span class="select2 select2-container select2-container--default" dir="ltr" style="width: 276px;"><span class="selection"><span class="select2-selection select2-selection--single" role="combobox" aria-haspopup="true" aria-expanded="false" tabindex="0" aria-labelledby="select2-item-account-id-0-container"><span class="select2-selection__rendered" id="select2-item-account-id-0-container" title="- Select Account -">- Select Account -</span><span class="select2-selection__arrow" role="presentation"><b role="presentation"></b></span></span></span><span class="dropdown-wrapper" aria-hidden="true"></span></span>
        
            </td>
            <td>
                <input value="" class="form-control text-right input-price" required="required" name="item[0][debit]" type="text" id="item-debit-0">
        
            </td>
            <td>
                <input value="" class="form-control text-right input-price" required="required" name="item[0][credit]" type="text" id="item-credit-0">
        
            </td>
    </tr>
                                                                                <tr id="addItem">
                        <td class="text-center"><button type="button" id="button-add-item" data-toggle="tooltip" title="Add" class="btn btn-xs btn-primary" data-original-title="Add"><i class="fa fa-plus"></i></button></td>
                        <td class="text-right" colspan="3"></td>
                    </tr>
                                                            <tr>
                        <td class="text-right" colspan="2"><strong>Subtotal</strong></td>
                        <td class="text-right"><span id="debit-sub-total">0</span></td>
                        <td class="text-right"><span id="credit-sub-total">0</span></td>
                    </tr>
                                                            <tr>
                        <td class="text-right" colspan="2"><strong>Total</strong></td>
                        <td class="text-right"><span id="debit-grand-total">0</span></td>
                        <td class="text-right"><span id="credit-grand-total">0</span></td>
                    </tr>
                                        </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- /.box-body -->

    <div class="box-footer">
        <div class="col-md-12">
    <div class="form-group no-margin">
        <button type="submit" class="btn btn-success  button-submit" data-loading-text="Loading..." disabled=""><span class="fa fa-save"></span> &nbsp;Save</button>
        <a href="https://catherapyservices.ca/akaunting/double-entry/journal-entry" class="btn btn-default"><span class="fa fa-times-circle"></span> &nbsp;Cancel</a>
    </div>
</div>


    </div>
    <!-- /.box-footer -->

    <input id="currency_code" name="currency_code" type="hidden" value="CAD">
    </form>
JournalEntryForm;

?>