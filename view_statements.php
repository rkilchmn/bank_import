<?php
/**********************************************************************
    Copyright (C) FrontAccounting, LLC.
	Released under the terms of the GNU General Public License, GPL, 
	as published by the Free Software Foundation, either version 3 
	of the License, or (at your option) any later version.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
    See the License here <http://www.gnu.org/licenses/gpl-3.0.html>.
***********************************************************************/
$page_security = 'SA_SALESTRANSVIEW';
$path_to_root = "../..";
include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/includes/session.inc");

include_once($path_to_root . "/includes/ui/ui_input.inc");
include_once($path_to_root . "/includes/ui/ui_lists.inc");
include_once($path_to_root . "/includes/ui/ui_globals.inc");
include_once($path_to_root . "/includes/data_checks.inc");

include_once($path_to_root . "/modules/bank_import/includes/includes.inc");


$js = "";
//RK replace with new logic
// if ($use_popup_windows)
// 	$js .= get_js_open_window(900, 500);
// if ($use_date_picker)
// 		$js .= get_js_date_picker();

if ($SysPrefs->use_popup_windows)
	$js .= get_js_open_window(800, 500);
if (user_use_date_picker())
	$js .= get_js_date_picker();

page(_($help_context = "View Bank Statements"), @$_GET['popup'], false, "", $js);

//--------------------------------------------------------------------

// search button pressed
if(get_post('RefreshInquiry')) {
	$Ajax->activate('doc_tbl');
}

if (isset($_POST['delete'])) {
    foreach (array_keys($_POST['delete']) as $k) {
        $smt_id = $k;
		$v = $_POST['delete'][$smt_id];
        if ($v = "delete") {
            // check if no processed transactions
            list( $processedTransactions, $statements) = getStatementTransactions(
                '', $smt_id , '', '', STATUS_PROCESSED);
            $numProcessed = count($processedTransactions);

            if ($numProcessed == 0) {
                // delete all transactions and statement
                $sql = "DELETE FROM ".TB_PREF."bi_transactions WHERE smt_id=".$smt_id;
                $res=db_query($sql, 'unable to delete transactions data');

                $sql = "DELETE FROM ".TB_PREF."bi_statements WHERE id=".$smt_id;
                $res=db_query($sql, 'unable to delete statement data');

                // refresh page
                $Ajax->activate('doc_tbl');
            }     
        }
    }
}

start_form();

//------------------------------------------------------------------------------------------------
// this is filter table
start_table(TABLESTYLE_NOBORDER);
start_row();
if (!isset($_POST['TransAfterDate']))
	$_POST['TransAfterDate'] = begin_month(Today());

if (!isset($_POST['TransToDate']))
	$_POST['TransToDate'] = end_month(Today());

if (!isset($_POST['accountFilter'])) 
    $_POST['accountFilter'] = 0;

label_cells(_("Bank Account:"), bank_accounts_list($name = "accountFilter", $selected_id = $_POST['accountFilter'], $submit_on_change = false, $spec_option = false));
date_cells(_("From:"), 'TransAfterDate', '', null, -30);
date_cells(_("To:"), 'TransToDate', '', null, 1);

submit_cells('RefreshInquiry', _("Search"),'',_('Refresh Inquiry'), 'default');
end_row();
end_table();


//------------------------------------------------------------------------------------------------
// this is data display table
$bankAccount = get_bank_account($_POST['accountFilter']);

$sql = " SELECT id, bank, account, currency, startBalance, endBalance, smtDate, number, seq, statementId,
    (SELECT count(id) FROM ".TB_PREF."bi_transactions WHERE smt_id=smt.id) as numTrans
    FROM
	".TB_PREF."bi_statements smt WHERE smtDate >= ".db_escape(date2sql($_POST['TransAfterDate']))." AND smtDate <= ".
	db_escape(date2sql($_POST['TransToDate']))." AND account = " . db_escape($bankAccount['bank_account_number']) . " ORDER BY smtDate ASC";

$res=db_query($sql, 'unable to get transactions data');

div_start('doc_tbl');
start_table(TABLESTYLE, "width='100%'");
table_header(array("Bank", "Statement#", "Date", "Account(Currency)", "Start Balance", "End Balance", "Delta", "Transactions", "Processed", "Allow Delete"));
while($myrow = db_fetch($res)) {
    // get processed transactions
    list( $processedTransactions, $statements) = getStatementTransactions(
		$_POST['accountFilter'], $myrow['id'] , '', '', STATUS_PROCESSED);
    $numProcessed = count($processedTransactions);

    start_row();
    echo "<td>". $myrow['bank'] . "</td>";
    echo "<td>" . $myrow['statementId']."</td>";
    echo "<td>" . $myrow['smtDate'] . "</td>";
    echo "<td>" . $myrow['account']. '(' . $myrow['currency'] . ')' . "</td>";
    amount_cell($myrow['startBalance']);
    amount_cell($myrow['endBalance']);
    amount_cell($myrow['endBalance'] - $myrow['startBalance']);
    echo "<td>" . $myrow['numTrans'] . "</td>";
    echo "<td>" . $numProcessed . "</td>";
    echo "<td>";
    if ($numProcessed == 0) {
         echo submit("delete[".$myrow['id']."]", _("Delete"), false, '', 'default');
    }
    echo"</td>";
    end_row();
}
end_table();
div_end();

end_form();

end_page(@$_GET['popup'], false, false);
?>
