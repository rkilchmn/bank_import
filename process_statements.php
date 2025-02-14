<?php

// Button actions
define('ACTION_PROCESS_SINGLE', 'Process Single');
define('ACTION_PROCESS_BULK', 'Bulk Process Selected');
define('ACTION_PROCESS_SELECT_ALL', 'Select All');
define('ACTION_PROCESS_DESELECT_ALL', 'Un-select All');

// when bank account list item needs to be selected
define('BANK_LIST_SELECT', '< please select >');

// all option for statement
define('STATEMENT_LIST_ALL', 'All');

// prefix before memo for bank charges line 
define('PREFIX_CHARGES', 'Total Charges: ');

$page_security = 'SA_SALESTRANSVIEW';
$path_to_root = "../..";
include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/includes/session.inc");

include_once($path_to_root . "/includes/ui/ui_input.inc");
include_once($path_to_root . "/includes/ui/ui_lists.inc");
include_once($path_to_root . "/includes/ui/ui_globals.inc");
include_once($path_to_root . "/includes/ui/ui_controls.inc");
include_once($path_to_root . "/includes/ui/items_cart.inc");
include_once($path_to_root . "/includes/data_checks.inc");
include_once($path_to_root . "/gl/includes/db/gl_db_banking.inc"); // contains add_bank_trans
include_once($path_to_root . "/gl/includes/db/gl_db_bank_accounts.inc"); // contains get_quick_entries
include_once($path_to_root . "/modules/bank_import/includes/includes.inc");
include_once($path_to_root . "/modules/bank_import/includes/pdata.inc");

//RK edit links
include_once($path_to_root . "/includes/ui/db_pager_view.inc");

$js = "";
//RK replace with new logic
// if ($use_popup_windows)
// 	$js .= get_js_open_window(900, 500);
// if ($use_date_picker)
// 		$js .= get_js_date_picker();

function splitFAIntstruction($transactionCodeDesc)
{
	$components = explode(':', $transactionCodeDesc);
	$numComponents = count($components);

	// Fill in empty components if necessary
	if ($numComponents < 4) {
		for ($i = $numComponents; $i < 3; $i++) {
			$components[] = ''; // Add empty component
		}
	}

	return $components;
}

function manageExchangeRate($date, $txn_currency, $rate)
{
	global $SysPrefs;
	$msg = "";
	$update = false;

	if (!$rate) {
		$rate = get_date_exchange_rate($txn_currency, $date);

		if (!$rate) {
			$rate = retrieve_exrate($txn_currency, $date);
			if ($rate) {
				$update = true;
			} else {
				$rate = get_last_exchange_rate($txn_currency, $date);
				$msg = "Rate for date $date could not be retrieved - using last rate $rate";
			}
		}
	} else {
		$update = true;
	}

	if ($update) {
		$existingRate = get_date_exchange_rate($txn_currency, $date);
		if ($existingRate) {
			$msg = "Rate for date $date already exists: $existingRate";
			$rate = $existingRate;
		} else {
		if ($SysPrefs->xr_provider_authoritative) {
			// store rate
				add_exchange_rate($txn_currency, $date, $rate, $rate);
			} else {
				$msg = "Rate determined but not stored: to store rate set configuration in config.php to xr_provider_authoritative=true";
				$rate = '';
			}
		}
	}

	return array($rate, $msg);
}

if ($SysPrefs->use_popup_windows)
	$js .= get_js_open_window(800, 500);
if (user_use_date_picker())
	$js .= get_js_date_picker();

page(_($help_context = "Bank Transactions"), @$_GET['popup'], false, "", $js);

$processingTypes = array(
	PRT_SUPPLIER => 'Supplier',
	PRT_CUSTOMER => 'Customer',
	PRT_QUICK_ENTRY => 'Quick Entry',
	PRT_TRANSFER => 'Funds Transfer',
	PRT_MANUAL_SETTLEMENT => 'Manual settlement',
);


//---------------------------------------------------------------------------------
// action

unset($k, $v);
if ((isset($_POST['action']) && ($_POST['action'] == ACTION_PROCESS_BULK)) || isset(($_POST['ProcessSingleTransaction']))) {
	// RK Initialize count
	$count = array_fill_keys(
		array(PRT_SUPPLIER, PRT_CUSTOMER, PRT_QUICK_ENTRY, PRT_TRANSFER, PRT_MANUAL_SETTLEMENT),
		0
	);

	$processTransactions = null;
	if (isset($_POST['ProcessSingleTransaction'])) {
		$processTransactions = $_POST['ProcessSingleTransaction'];
	} elseif (isset($_POST['ProcessTransaction'])) {
		$processTransactions = $_POST['ProcessTransaction'];
	}

	// fx rates 
	$comp_currency = get_company_currency();

	//RK list($k, $v) = each($_POST['ProcessTransaction']);
	if (isset($processTransactions)) {
		foreach (array_keys($processTransactions) as $k) {
			$v = $processTransactions[$k];
			//RK  if (isset($k) && isset($v) && isset($_POST['processingType'][$k]) && $v) {
			if (isset($k) && isset($v) && isset($_POST['processingType'][$k]) && $v) {
				//check params
				$error = 0;
				// if (!isset($_POST["partnerId_$k"])) {
				// 	$Ajax->activate('doc_tbl');
				// 	display_error('missing partnerId');
				// 	$error = 1;
				// }

				if (!$error) {
					$tid = $k;
					//time to gather data about transaction
					//load $tid
					$trz = get_transaction($tid);
					//display_notification('<pre>'.print_r($trz,true).'</pre>');

					//check bank account
					$txn_account = get_bank_account_by_number($trz['account']);
					if (empty($txn_account)) {
						$Ajax->activate('doc_tbl');
						display_error('the bank account <b>' . $trz['account'] . '</b> is not defined in Bank Accounts');
						$error = 1;
					}
					//display_notification('<pre>'.print_r($ba,true).'</pre>');
				}
				if (!$error) {
					//get charges
					$chgs = array();
					$_cids = array_filter(explode(',', $_POST['cids'][$tid]));
					foreach ($_cids as $cid) {
						$chgs[] = get_transaction($cid);
					}
					//display_notification("tid=$tid, cids=`".$_POST['cids'][$tid]."`");
					//display_notification("cids_array=".print_r($_cids,true));

					//now sum up
					//now group data from tranzaction
					$amount = $trz['transactionAmount'];
					$charge = 0;
					$chargeTitle = $trz['transactionTitle']; //RK default from txn
					foreach ($chgs as $t) {
						$charge += $t['transactionAmount'];
						if (isset($t['transactionTitle']) && $t['transactionTitle']) {
							$chargeTitle = $t['transactionTitle']; // overwrite from charge txn
						}
					}

					//display_notification("amount=$amount, charge=$charge");
					//display_notification("processingType=".$_POST['processingType'][$k]);

					$rate = null;
					$txn_currency = $txn_account['bank_curr_code'];
					$date = sql2date($trz['valueTimestamp']);

					switch (true) {
							// case ($_POST['processingType'][$k] == PRT_SUPPLIER && (splitFAIntstruction($trz['transactionCodeDesc'])[0] == ST_SUPPAYMENT)):
						case ($_POST['processingType'][$k] == PRT_SUPPLIER):
							//supplier payment
							if ($txn_currency != $comp_currency) {
								list($rate, $msg) = manageExchangeRate($date, $txn_currency, "");
								if ($msg) {
									display_notification($msg);
								}
							}
							$transType = ST_SUPPAYMENT;
							$reference = $Refs->get_next($transType);
							if (!is_new_reference($reference, $transType)) {
								display_error("Reference: $reference of Transaction Type: $transType already used.");
								break;
							}

							//RK with write_supp_payment( $trans_no, $supplier_id, $bank_account,$date_, $ref, $supp_amount, $supp_discount, $memo_, $bank_charge=0, $bank_amount=0, $dimension=0, $dimension2=0)
							$payment_id = write_supp_payment(
								0, // new transaction
								$_POST["partnerId_$k"],
								$txn_account['id'],
								sql2date($trz['valueTimestamp']),
								$reference,
								user_numeric($trz['transactionAmount']),
								0,
								$trz['transactionTitle'],
								user_numeric($charge),
								user_numeric($trz['transactionAmount']) //RK required for FX transaction
							);

							// display_notification("payment_id = $payment_id");
							//update trans with payment_id details
							// RK use reference which does not change when is modified (trans_id is changing and link brakes)
							if ($payment_id) {
								update_transactions($tid, $_cids, $status = STATUS_PROCESSED, $payment_id, sql2date($trz['valueTimestamp']), $transType, $reference);
								update_partner_data($partner_id = $_POST["partnerId_$k"], $partner_type = PT_SUPPLIER, $partner_detail_id = ANY_NUMERIC, $account = $trz['account']);
								// RK
								// display_notification('Supplier payment processed');
								$count[PRT_SUPPLIER]++;
							}
							break;
							// RK case ($_POST['processingType'][$k] == PRT_CUSTOMER && (splitFAIntstruction($trz['transactionCodeDesc'])[0] == ST_CUSTPAYMENT)):
						case ($_POST['processingType'][$k] == PRT_CUSTOMER):
							// customer payment
							if ($txn_currency != $comp_currency) {
								list($rate, $msg) = manageExchangeRate($date, $txn_currency, "");
								if ($msg) {
									display_notification($msg);
								}
							}
							$transType = ST_CUSTPAYMENT;
							$reference = $Refs->get_next($transType);
							if (!is_new_reference($reference, $transType)) {
								display_error("Reference: $reference of Transaction Type: $transType already used.");
								break;
							}
							//RK } while (!is_new_reference($reference, ST_BANKDEPOSIT));

							$payment_id = my_write_customer_payment(
								$trans_type = $transType,
								$trans_no = 0,
								$customer_id = $_POST["partnerId_$k"],
								$branch_id = $_POST["partnerDetailId_$k"],
								$bank_account = $txn_account['id'],
								$date_ = sql2date($trz['valueTimestamp']),
								$reference,
								user_numeric($trz['transactionAmount']),
								$discount = 0,
								$memo_ = $trz['transactionTitle'],
								$rate = 0,
								user_numeric($charge),
								user_numeric($trz['transactionAmount']) //RK required for FX transaction
							);
							//display_notification("payment_id = $payment_id");
							//update trans with payment_id details
							if ($payment_id) {
								update_transactions($tid, $_cids, $status = STATUS_PROCESSED, $payment_id, sql2date($trz['valueTimestamp']), $transType, $reference);
								update_partner_data($partner_id = $_POST["partnerId_$k"], $partner_type = PT_CUSTOMER, $partner_detail_id = $_POST["partnerDetailId_$k"], $account = $trz['account']);
								//RK display_notification('Customer deposit processed');
								$count[PRT_CUSTOMER]++;
							}
							break;
						case ($_POST['processingType'][$k] == PRT_QUICK_ENTRY):
							$cart_type = getTransType(splitFAIntstruction($trz['transactionCodeDesc'])[1]);
							$cart = new items_cart($cart_type);
							$cart->order_id = 0;
							$cart->original_amount = $trz['transactionAmount'] + $charge;

							if ($txn_currency != $comp_currency) {
								list($rate, $msg) = manageExchangeRate($date, $txn_currency, "");
								if ($msg) {
									display_notification($msg);
								}
							}

							$cart->reference = $Refs->get_next($cart->trans_type);
							if (!is_new_reference($cart->reference, $cart->trans_type)) {
								display_error("Reference: $cart->reference of Transaction Type: $cart->trans_type already used.");
								break;
							}

							$cart->tran_date = sql2date($trz['valueTimestamp']);
							if (!is_date_in_fiscalyear($cart->tran_date)) {
								$cart->tran_date = end_fiscalyear();
							}
							//this loads the QE into cart!!!
							//$rval = display_quick_entries($cart, $_POST['qeId'][$k], $_POST['amount'][$k], ($_POST['transDC'][$k]=='C') ? QE_DEPOSIT : QE_PAYMENT);
							$rval = qe_to_cart($cart, $_POST["partnerId_$k"], $trz['transactionAmount'], splitFAIntstruction($trz['transactionCodeDesc'])[1], $trz['transactionTitle']);
							$total = $cart->gl_items_total();
							switch ($cart_type) {
								case ST_BANKDEPOSIT:
								case ST_BANKPAYMENT:
									if ($total != 0) {
										//need to add the charge to the cart
										$cart->add_gl_item(get_company_pref('bank_charge_act'), 0, 0, $charge, PREFIX_CHARGES . $chargeTitle);
										//process the transaction

										begin_transaction();

										$trans = write_bank_transaction(
											$cart->trans_type,
											$cart->order_id,
											$txn_account['id'],
											$cart,
											sql2date($trz['valueTimestamp']),
											PT_QUICKENTRY,
											$_POST["partnerId_$k"],
											0,
											$cart->reference,
											$trz['transactionTitle'],
											true,
											null
										);

										if ($trans[1]) {
											update_transactions($tid, $_cids, $status = STATUS_PROCESSED, $trans[1], sql2date($trz['valueTimestamp']), $cart_type, $cart->reference);
											commit_transaction();
											$count[PRT_QUICK_ENTRY]++;
										}
									} else {
										display_notification("QE not loaded: rval=$rval, k=$k, total=$total");
										//display_notification("debug: <pre>".print_r($_POST, true).'</pre>');
									}
									break;

								case ST_JOURNAL;
									$cart->event_date = $cart->tran_date;
									$cart->doc_date = $cart->tran_date;
									if ($txn_currency != $comp_currency) {
										$cart->currency = $txn_currency;
										$cart->rate = $rate;
									}
									$cart->memo_ = $trz['transactionTitle'];
									$trans_no = write_journal_entries($cart);

									if ($trans_no) {
										update_transactions($tid, $_cids, $status = STATUS_PROCESSED, $trans_no, $cart->tran_date, $cart_type, $cart->reference);
										$count[PRT_QUICK_ENTRY]++;
									}
									break;
							}

							break;

						case ($_POST['processingType'][$k] == PRT_TRANSFER):
							$errMsg = "";

							// prepare transfer details
							$debit_account = get_bank_account_by_number($trz['account']);
							$credit_account = get_bank_account(get_post("transferAccountId_$tid"));
							if (isset($credit_account) && ($credit_account)) {
							
								$credit_amount = get_post("transferAmount_$tid");
								$transferCharge = get_post("transferCharge_$tid");
								$forceFXrate = get_post("forceFXrate_$tid");

								// case comp	txn		credit	
								// 1	SGD		SGD		SGD		
								// 2	SGD		SGD		!SGD	
								// 3	SGD		!SGD 	SGD		
								// 4	SGD		!SGD	!SGD	

								// determine fx rates to avoid fx gain/loss for currency convertions
								$credit_currency = $credit_account['bank_curr_code'];

								$txn_smt_amount = $trz['transactionAmount'];
								$txn_amount = $txn_smt_amount;
								// amount debited from source bank account is sum of txn amount and charge
								if ($transferCharge > 0) 
									$txn_amount = $txn_amount - $transferCharge;

								$txn_rate = null;
								$calc_fxrate = null;
								$calc_fxrate_currency = null;
								
								switch (true) {
									case ($txn_currency == $comp_currency && $credit_currency == $comp_currency):
										// case 1: no fx rates involved
										if ($transferCharge > 0) {
											if ($credit_amount + $transferCharge > $txn_smt_amount) {
												$errMsg = "Error in Transfer: The Sum of Credit amount $txn_currency $credit_amount and the Transfer Charge $txn_currency $transferCharge cannot exceed the Transaction Amount $txn_currency $txn_smt_amount!";
											}
										}
											
										break;
									case ($txn_currency == $comp_currency && $credit_currency != $comp_currency):
										// case 2
										$calc_fxrate = $txn_amount / $credit_amount;
										$calc_fxrate_currency = $credit_currency;
										break;

									case ($txn_currency != $comp_currency && $credit_currency == $comp_currency):
										// case 3
											$calc_fxrate = $credit_amount / $txn_amount;
											$calc_fxrate_currency = $txn_currency;
										break;

									case ($txn_currency != $comp_currency && $credit_currency != $comp_currency):
										// case 4:
										// 2 fx rates required, try to get first one to calculate the second
										list($txn_rate, $msg) = manageExchangeRate($date, $txn_currency, "");
										if ($msg) {
											display_notification($msg);
										} else {
											$calc_fxrate =  $txn_amount * $txn_rate / $credit_amount;
											$calc_fxrate_currency = $credit_currency;
										}
										
										break;
								}

								if ($calc_fxrate) {
									list($txn_rate, $msg) = manageExchangeRate($date, $calc_fxrate_currency, $calc_fxrate);
									if ($forceFXrate && ($txn_rate != $calc_fxrate)) {
										// forced rate could net ne applied
										$errMsg = "Cannot apply forced fx rate! ".$msg;
									}
									elseif ($msg) {
										display_notification($msg);
									}
								}
							}
							else {
								$errMsg = "Select valid bank account";
							}

							if ($errMsg) {
								display_error( $errMsg);
								break;
							}

							$transType = ST_BANKTRANSFER;
							$reference = $Refs->get_next($transType);
							// add_bank_transfer( $from_account, $to_account, $date_, $amount, $ref, $memo_, $dim1, $dim2, $charge=0, $target_amount=0)
							$trans_no = add_bank_transfer($debit_account['id'], $credit_account['id'], $date, user_numeric($txn_amount), $reference, $trz['transactionTitle'], "", "", user_numeric($transferCharge), user_numeric($credit_amount));

							if ($trans_no) {
								update_transactions($tid, $_cids, $status = STATUS_PROCESSED, $trans_no, $date, $transType, $reference);
								$count[PRT_TRANSFER]++;
							}
							break;

						case ($_POST['processingType'][$k] == PRT_MANUAL_SETTLEMENT):
							//RK display_notification("tid=$tid, cids=`" . $_POST['cids'][$tid] . "`");
							//RK display_notification("cids_array=" . print_r($_cids, true));
							$transType = $_POST["partnerId_manualTransType_$k"];
							$transRef = $_POST["partnerId_manualTransRef_$k"];
							$transDate = $_POST["partnerId_manualTransDate_$k"];
							if (isset($transType) && isset($transRef) && isset($transDate)) {
								// check if transaction exists
								$txn = retrieve_txn_by_reference($transType, $transRef, $transDate);
								if (isset($txn) && ($transRef == $txn['reference'])) {
									// set fx rate
										if ($txn_currency != $comp_currency) {
										list($rate, $msg) = manageExchangeRate($transDate, $txn_currency, "");
										if ($msg) {
											display_notification($msg);
										}
									}
									update_transactions($tid, $_cids, $status = STATUS_PROCESSED, $txn['trans_no'], $transDate, $transType, $transRef);
									$count[PRT_MANUAL_SETTLEMENT]++;
								} else {
									display_error("Invalid Type '$transType', reference '$transRef' or date '$transDate' for Manual Transaction");
								}
							} else {
								display_notification('Type or Reference for Manual Transaction missing');
							}
							break;
					} // end of switch
					$Ajax->activate('doc_tbl');
				} //end of if !error
			} // end of is set....
		} //RK foreach 
	} else {
		display_notification("No transactions selected for processing");
	}
	//RK show total transaction processed 
	$total = $count[PRT_SUPPLIER] + $count[PRT_CUSTOMER] + $count[PRT_QUICK_ENTRY] + $count[PRT_TRANSFER] + $count[PRT_MANUAL_SETTLEMENT];
	display_notification("Total transactions processed: $total (Supplier Payments: " . $count[PRT_SUPPLIER] . ", Customer Payments: " . $count[PRT_CUSTOMER] . ", Quick Entries: " . $count[PRT_QUICK_ENTRY] . ", Funds Transfer: " . $count[PRT_TRANSFER] . ", Manual Payments: " . $count[PRT_MANUAL_SETTLEMENT] . ")");
} //end of is isset(post[processTranzaction])

/*
// check whether a transaction is ignored
unset($k, $v);
list($k, $v) = each($_POST['IgnoreTrans']);
if (isset($k) && isset($v)) {
    updateTrans($_POST['trans_id'][$k], $_POST['charge_id'][$k], TR_MAN_SETTLED);
    $Ajax->activate('doc_tbl');
    display_notification('Manually processed');
}
*/

// search button pressed
if (get_post('RefreshInquiry')) {
	$Ajax->activate('doc_tbl');
}

//SC: check whether a customer has been changed, so that we can update branch as well
// as there a user can click on one submit button only, there is no need for multiple check
unset($k, $v);
if (isset($_POST['partnerId'])) {
	list($k, $v) = each($_POST['partnerId']);
	if (isset($k) && isset($v)) {
		$Ajax->activate('doc_tbl');
	}
}

//SC: 05.10.2012: whether post['processingType'] exists, refresh
if (isset($_POST['processingType'])) {
	$Ajax->activate('doc_tbl');
}


start_form();

div_start('doc_tbl');

if (1) {
	//------------------------------------------------------------------------------------------------
	// this is filter table
	start_table(TABLESTYLE_NOBORDER);
	start_row();
	if (!isset($_POST['statusFilter']))
		$_POST['statusFilter'] = STATUS_UNPROCESSED;
	if (!isset($_POST['TransAfterDate']))
		$_POST['TransAfterDate'] = begin_month(Today());
	//RK $_POST['TransAfterDate'] = '01/01/2012';

	if (!isset($_POST['TransToDate']))
		$_POST['TransToDate'] = end_month(Today());

	if (!isset($_POST['accountFilter'])) 
		$_POST['accountFilter'] = 0;

	//if (!@$_GET['popup'])
	//	end_form();


	//------------------------------------------------------------------------------------------------
	// this is data display table

	if (!isset($_POST['filterSmtId'])) {
		$_POST['filterSmtId'] = ''; // filter to all statements
	}

	list( $trzs, $statements) = getStatementTransactions(
		$_POST['accountFilter'], $_POST['filterSmtId'] , $_POST['TransAfterDate'], $_POST['TransToDate'], $_POST['statusFilter']);

	// $statements[STATEMENT_LIST_ALL] = STATEMENT_LIST_ALL;
	$statements[''] = STATEMENT_LIST_ALL;

	label_cells(_("Bank Account:"), bank_accounts_list($name = "accountFilter", $selected_id = $_POST['accountFilter'], $submit_on_change = false, $spec_option = false));
	date_cells(_("From:"), 'TransAfterDate', '', null, -30);
	date_cells(_("To:"), 'TransToDate', '', null, 1);

	label_cells(_("Statement:"), array_selector('filterSmtId', $_POST['filterSmtId'], $statements));

	label_cells(_("Status:"), array_selector('statusFilter', $_POST['statusFilter'], array(STATUS_UNPROCESSED => 'not processed', STATUS_PROCESSED => 'processed', 255 => 'All')));

	submit_cells('RefreshInquiry', _("Search"), '', _('Refresh Inquiry'), 'default');
	end_row();
	end_table();

	start_table(TABLESTYLE, "width='100%'");

	table_header(array("Transaction Details", "Processing/Status"));

	foreach ($trzs as $trz_code => $trz_data) {
		//try to match something, interpreting saved info if status=TR_SETTLED
		//$minfo = doMatching($myrow, $coyBankAccounts, $custBankAccounts, $suppBankAccounts);

		//now group data from tranzaction
		$tid = 0;
		$cids = array();

		//bring trans details
		$has_trz = 0;
		$amount = 0;
		$charge = 0;
		foreach ($trz_data as $idx => $trz) {
			if ($trz['transactionType'] != 'COM') {
				$transactionDC = $trz['transactionDC'];
				$smt_account = $trz['smt_account'];
				$smt_statementId = $trz['statementId'];
				$valueTimestamp = $trz['valueTimestamp'];
				$bankAccount = $trz['account'];
				$bankAccountName = $trz['accountName'];
				$transactionTitle = $trz['transactionTitle'];
				$status = $trz['status'];
				$tid = $trz['id'];
				$has_trz = 1;
				$amount = $trz['transactionAmount'];
				// RK some DB fields already translated in loop over results
				$fa_trz_type = $trz['fa_trz_type'];
				$fa_trz_no = $trz['fa_trz_no'];
				$fa_trz_ref = $trz['fa_trz_ref']; 

				// RK
				$transactionCode = $trz['transactionCode'];
				$transactionCodeDesc = $trz['transactionCodeDesc'];
			}
		}
		//if does not have trz aka just charge, take info from charge
		if (!$has_trz) {
			foreach ($trz_data as $idx => $trz) {
				if ($trz['transactionType'] == 'COM') {
					$transactionDC = $trz['transactionDC'];
					$smt_account = $trz['smt_account'];
					$valueTimestamp = $trz['valueTimestamp'];
					$bankAccount = $trz['account'];
					$bankAccountName = $trz['accountName'];
					$transactionTitle = $trz['transactionTitle'];
					$status = $trz['status'];
					$tid = $trz['id']; // tid is from charge
					$amount += $trz['transactionAmount'];
					$fa_trz_type = $trz['fa_trans_type'];
					$fa_trz_no = $trz['fa_trans_no'];
					$fa_trz_ref = $trz['matchinfo']; //RK using matchinfo for FA reference

					// RK
					$transactionCode = $trz['transactionCode'];
					$transactionCodeDesc = $trz['transactionCodeDesc'];
				}
			}
		} else {
			//transaction plus charge => add charge ids into place and sum amounts
			foreach ($trz_data as $idx => $trz) {
				if ($trz['transactionType'] == 'COM') {
					$cids[] = $trz['id'];
					$charge += $trz['transactionAmount'];
				}
			}
		}
		$cids = implode(',', $cids);

		//echo '<pre>'.print_r($trz_data, true).'</pre>';


		start_row();
		echo '<td width="50%">';

		start_table(TABLESTYLE2, "width='100%'");
		label_row("Trans Date:", sql2date($valueTimestamp), "width='25%' class='label'");
		label_row("Trans Type:", ($transactionDC == 'C') ? "Credit" : "Debit");
		label_row("Account:", $bankAccount);
		// label_row("Counterparty:", $bankAccountName);
		label_row("StatementID:", $smt_statementId);
		label_row("Amount/Charge(s):", $amount . ' / ' . $charge);
		label_row("Trans Title:", $transactionTitle);
		end_table();

		echo "</td><td width='50%' valign='top'>";

		start_table(TABLESTYLE2, "width='100%'");

		//now display stuff: forms and information
		switch ($status) {
			case STATUS_PROCESSED:

			// the transaction is settled, we can display full details
			label_row("Status:", "<b>Transaction is settled!</b>", "width='25%' class='label'");
			label_row("Type:", $systypes_array[$fa_trz_type] . " ($fa_trz_type)");
			label_row("Reference (Trans. No):", $fa_trz_ref . "(" . get_trans_view_str($fa_trz_type, $fa_trz_no) . ") " . get_gl_view_str($fa_trz_type, $fa_trz_no) . trans_editor_link($fa_trz_type, $fa_trz_no) . viewer_link(_("Void"), "/admin/void_transaction.php?trans_no=" . $fa_trz_no . "&filterType=" . $fa_trz_type, '','', ICON_DELETE));

			switch ($trz['fa_trans_type']) {
				case ST_SUPPAYMENT:

					// get supplier info

					//label_row("Supplier:", $minfo['supplierName']);
					//label_row("From bank account:", $minfo['coyBankAccountName']);
					break;
				case ST_CUSTPAYMENT:
					//get customer info from transaction details
					if (exists_customer_trans($fa_trz_type, $fa_trz_no)) {
						$fa_trans = get_customer_trans($fa_trz_no, $fa_trz_type);
						label_row("Customer/Branch:", get_customer_name($fa_trans['debtor_no']) . " / " . get_branch_name($fa_trans['branch_code']));
					}
					break;
				case 0:
					// for manual transactions
					break;
				default:
					label_row("Status:", "other transaction type; no info yet");
					break;
				}

				break;
				
			case STATUS_UNPROCESSED:
				//transaction not settled
				// this is a new transaction, but not matched by routine so just display some forms

				if (!isset($_POST['processingType'][$tid])) {
					if (isset($transactionCodeDesc)) {
						$_POST['processingType'][$tid] = splitFAIntstruction($transactionCodeDesc)[0];;
					}
				}

				label_row("Instruction:", $transactionCodeDesc);
				label_row("Processing Type:", array_selector("processingType[$tid]", $_POST['processingType'][$tid], $processingTypes, array('select_submit' => true)));

				if (!isset($_POST["partnerId_$tid"])) {
					$_POST["partnerId_$tid"] = '';
				}

				switch ($_POST['processingType'][$tid]) {
						//supplier payment
					case PRT_SUPPLIER:
						// select if value supplied
						$supplier_shortname = splitFAIntstruction($transactionCodeDesc)[1];
						if (empty($_POST["partnerId_$tid"]) && !empty($supplier_shortname)) {
							$match = search_suppliers($supplier_shortname);
							if (!empty($match) && (count($match) == 1)) {
								// one matching enrty
								$_POST["partnerId_$tid"] = $match[0]['supplier_id'];
							}
						}
						// propose based on bank account
						if (empty($_POST["partnerId_$tid"])) {
							$match = search_partner_by_bank_account(PT_SUPPLIER, $bankAccount);
							if (!empty($match)) {
								$_POST["partnerId_$tid"] = $match['partner_id'];
							}
						}
						label_row(_("Payment To:"), supplier_list("partnerId_$tid", null, false, false));
						break;
						//customer deposit
					case PRT_CUSTOMER:
						//propose customer
						if (empty($_POST["partnerId_$tid"])) {
							$match = search_partner_by_bank_account(PRT_CUSTOMER, $bankAccount);
							if (!empty($match)) {
								$_POST["partnerId_$tid"] = $match['partner_id'];
								$_POST["partnerDetailId_$tid"] = $match['partner_detail_id'];
							}
						}
						$cust_text = customer_list("partnerId_$tid", null, false, true);
						if (db_customer_has_branches($_POST["partnerId_$tid"])) {
							$cust_text .= customer_branches_list($_POST["partnerId_$tid"], "partnerDetailId_$tid", null, false, true, true);
						} else {
							hidden("partnerDetailId_$tid", ANY_NUMERIC);
							$_POST["partnerDetailId_$tid"] = ANY_NUMERIC;
						}
						label_row(_("Customer/Branch:"),  $cust_text);
						//label_row("debug", "customerid_tid=".$_POST["partnerId_$tid"]." branchid[tid]=".$_POST["partnerDetailId_$tid"]);

						break;
						// quick entry
					case PRT_QUICK_ENTRY:
						//label_row("Option:", "<b>Process via quick entry</b>");
						$QEType = get_post("partnerId_QEType_$tid");
						$QEId = get_post("partnerId_$tid");
						if (empty($QEType)) { // initial proposal
							$QEType = splitFAIntstruction($transactionCodeDesc)[1];
							$QEDescr = splitFAIntstruction($transactionCodeDesc)[2];

							// lookup quick entries 
							$qe = get_quick_entries(getQEType(splitFAIntstruction($transactionCodeDesc)[1]));
							$results = $qe->fetch_all();

							$first = "";
							// find a matching quick entry by description
							foreach ($results as $row) {
								if (empty($first)) {
									$first = $row[0];
								}
								if ($row[2] == $QEDescr) {
									$QEId = $row[0];
									break;
								}
							}

							// not found
							if (empty($QEId)) {
								$QEId = $first;
							}
						}

						// display drop downs
						quick_entry_types_list_row($label = "Quick Entry Types", $name = "partnerId_QEType_$tid", $QEType, $submit_on_change = true);
						quick_entries_list_row($label = "Quick Entry Description", $name = "partnerId_$tid", $selected_id = $QEId, $type = $QEType, $submit_on_change = true);
						label_row("Quick Entry Usage", get_quick_entry($QEId)['usage']);
						break;	

					case PRT_TRANSFER:
						$transferAccountId = get_post("transferAccountId_$tid");
						$transferAmount = get_post("transferAmount_$tid");
						$transferCharge = get_post("transferCharge_$tid");
						if (!isset($_POST["forceFXrate_$tid"])) {
							$forceFXrate = true;
						}
						else {
							$forceFXrate = get_post("forceFXrate_$tid");
						}
						
						if (empty($transferAccountId) && (splitFAIntstruction($transactionCodeDesc)[0]) == PRT_TRANSFER) { // initial proposal
							$acct_number = splitFAIntstruction($transactionCodeDesc)[1];
							$transferAmount = splitFAIntstruction($transactionCodeDesc)[2];
							$transferCharge = splitFAIntstruction($transactionCodeDesc)[3];
							if (isset( $acct_number) && ($acct_number != '')) {
								$transferAccountId = get_bank_account_by_number($acct_number)['id'];
							}
						}

						$specOption = "False";
						// show special text to select target account
						if (!($transferAccountId)) {
							$specOption = BANK_LIST_SELECT;
							$transferAccountId = $specOption;
						}

						label_row(_("Credit to Account:"), bank_accounts_list($name = "transferAccountId_$tid", $selected_id = $transferAccountId, $submit_on_change = false, $spec_option = $specOption));
						label_row(_("Amount:"),  text_input("transferAmount_$tid", $transferAmount));
						label_row(_("Bank Charge:"),  text_input("transferCharge_$tid", $transferCharge)."&nbsp;".checkbox("Force FX rate", "forceFXrate_$tid", $forceFXrate, true));
						break;

					case PRT_MANUAL_SETTLEMENT:
						hidden("partnerId_$tid", 'manual');
						
						//function text_input($name, $value=null, $size='', $max='', $title='', $params='')
						$manualTransRef = get_post("partnerId_manualTransRef_$tid");
						$manualTransType = get_post("partnerId_manualTransType_$tid");
						$manualTransDate = get_post("partnerId_manualTransDate_$tid");
						
						if (empty($manualTransDate)) {
							$manualTransDate = sql2date($trz['valueTimestamp']);
						}

						if (empty($manualTransRef) && ($manualTransType >= 0) && $manualTransDate) {
							$trz = get_transaction($tid);
							# try to find a transaction with the same type, date and amount
							$matchingTrans = retrieve_txn_by_type_amount($manualTransType, $manualTransDate, ($trz['transactionDC'] == 'C') ? $trz['transactionAmount'] : -$trz['transactionAmount'], $bankAccount);
							if ($matchingTrans){
								$manualTransRef = $matchingTrans['reference'];
							}
						}

						label_row(_("Transaction Type:"), journal_types_list( "partnerId_manualTransType_$tid", null, true));
						label_row(_("Transaction Date:"),  text_input("partnerId_manualTransDate_$tid", $manualTransDate));
						label_row(_("Transaction Reference:"),  text_input("partnerId_manualTransRef_$tid", $manualTransRef));
						
						break;
				}

				$selected = false;
				if (isset($_POST['ProcessTransaction'][$tid])) {
					$selected = $_POST['ProcessTransaction'][$tid];
				}

				if (isset($_POST['action'])) {
					if ($_POST['action'] == ACTION_PROCESS_SELECT_ALL) {
						$selected = true;
					}
					if ($_POST['action'] == ACTION_PROCESS_DESELECT_ALL) {
						$selected = false;
					}
				}

				label_row("Bulk Processing", checkbox("", "ProcessTransaction[$tid]", $selected) . submit("ProcessSingleTransaction[$tid]", _(ACTION_PROCESS_SINGLE), false, '', 'default'));

				//other common info
				hidden("cids[$tid]", $cids);

			break;
		}
		end_table();
		echo "</td>";
		end_row();
	}

	// show buttions for processing
	if ($trzs) {
		start_row();
		echo '<td align="center" colspan="2">';
		echo submit("action", _(ACTION_PROCESS_SELECT_ALL), false, '');
		echo submit("action", _(ACTION_PROCESS_DESELECT_ALL), false, '');
		echo submit("action", _(ACTION_PROCESS_BULK), false, '');

		echo '</td>';
		end_row();
	}

	end_table();
}

div_end();
end_form();

end_page(@$_GET['popup'], false, false);
