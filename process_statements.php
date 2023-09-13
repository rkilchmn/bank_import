<?php

//RK define processing types - better term than Partner Type
define( 'PRT_SUPPLIER', 'SP');
define( 'PRT_CUSTOMER', 'CU');
define( 'PRT_QUICK_ENTRY', 'QE');
define( 'PRT_TRANSFER', 'TR');
define( 'PRT_MANUAL_SETTLEMENT', 'MA');

// Button actions
define( 'ACTION_PROCESS_SINGLE', 'Process Single');
define( 'ACTION_PROCESS_BULK', 'Bulk Process Selected');
define( 'ACTION_PROCESS_SELECT_ALL', 'Select All');
define( 'ACTION_PROCESS_DESELECT_ALL', 'Un-select All');

// prefix before memo for bank charges line 
define( 'PREFIX_CHARGES', 'Total Charges: ');

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
include_once($path_to_root . "/gl/includes/db/gl_db_banking.inc"); // contains add_bank_transfer

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

function retrieve_txn_by_reference( $type, $reference, $date) {
	$sql = get_sql_for_journal_inquiry($type, $date, $date, $reference, '', true);
			
	$result = db_query($sql, 'unable to get transactions data');

	if (db_num_rows($result) == 1) {
		return db_fetch_assoc($result);
	}
	else
		return null;
}

function getProcessingType( $paymentType) {
	switch ($paymentType) {
		case PT_CUSTOMER: return PRT_SUPPLIER;
		case PT_SUPPLIER: return PRT_SUPPLIER; 
		case PT_QUICKENTRY: return PRT_QUICK_ENTRY;
		default: PRT_MANUAL_SETTLEMENT;
	}
}

function getQEType($transType) {
	switch ($transType) {
		case ST_BANKDEPOSIT: return QE_DEPOSIT;
		case ST_BANKPAYMENT: return QE_PAYMENT; 
		case ST_JOURNAL: return QE_JOURNAL;
	}
}

function getTransTypeDescription($transType) {
	global $systypes_array;
	return $systypes_array[$transType];
} 

function splitTransactionCodeDesc($transactionCodeDesc) {
    $components = explode(':', $transactionCodeDesc);
    $numComponents = count($components);

    // Fill in empty components if necessary
    if ($numComponents < 3) {
        for ($i = $numComponents; $i < 3; $i++) {
            $components[] = ''; // Add empty component
        }
    }

    return $components;
}

function manageExchangeRate($date, $txn_currency, $rate) {
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
	}
	else {
		$update = true;
	}

	if ($update) {
		if ($SysPrefs->xr_provider_authoritative) {
			// store rate
			add_exchange_rate($txn_currency, $date, $rate, $rate);
		} else {
			$msg = "Rate determined but not stored: to store rate set configuration in config.php to xr_provider_authoritative=true";
		}
	}
	
    return array($rate, $msg);
}


if ($SysPrefs->use_popup_windows)
	$js .= get_js_open_window(800, 500);
if (user_use_date_picker())
	$js .= get_js_date_picker();

page(_($help_context = "Bank Transactions"), @$_GET['popup'], false, "", $js);

$optypes = array(
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
		array(PRT_SUPPLIER, PRT_CUSTOMER, PRT_QUICK_ENTRY, PRT_TRANSFER, PRT_MANUAL_SETTLEMENT), 0);

	$processTransactions = null; 
	if (isset($_POST['ProcessSingleTransaction'])) {
		$processTransactions = $_POST['ProcessSingleTransaction'];
	}
	elseif (isset($_POST['ProcessTransaction'])) {
		$processTransactions = $_POST['ProcessTransaction'];
	}

	// fx rates 
	$comp_currency = get_company_currency();

	//RK list($k, $v) = each($_POST['ProcessTransaction']);
	if (isset($processTransactions)) {
		foreach (array_keys($processTransactions) as $k) {
			$v = $processTransactions[$k];
			//RK  if (isset($k) && isset($v) && isset($_POST['partnerType'][$k]) && $v) {
			if (isset($k) && isset($v) && isset($_POST['partnerType'][$k]) && $v) {
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
					$smt_account = get_bank_account_by_number($trz['account']);
					if (empty($smt_account)) {
						$Ajax->activate('doc_tbl');
						display_error('the bank account <b>' . $trz['smt_account'] . '</b> is not defined in Bank Accounts');
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
					//display_notification("partnerType=".$_POST['partnerType'][$k]);

					$rate = null;
					$txn_currency = $smt_account['bank_curr_code'];
					$date = sql2date($trz['valueTimestamp']);

					switch (true) {
						case ($_POST['partnerType'][$k] == PRT_SUPPLIER && (splitTransactionCodeDesc($trz['transactionCodeDesc'])[0] == ST_SUPPAYMENT)):
							//supplier payment
							if ($txn_currency != $comp_currency) {
								list ($rate, $msg) = manageExchangeRate( $date, $txn_currency, "");
								if ($msg) {
									display_notification( $msg);
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
									$smt_account['id'],
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
							//RK use reference which does not change when is modified (trans_id is changing and link brakes)
							if ($payment_id) {
								update_transactions($tid, $_cids, $status = 1, $payment_id, ST_SUPPAYMENT, $reference);
								update_partner_data($partner_id = $_POST["partnerId_$k"], $partner_type = PT_SUPPLIER, $partner_detail_id = ANY_NUMERIC, $account = $trz['account']);
								// RK
								// display_notification('Supplier payment processed');
								$count[PRT_SUPPLIER]++;
							}
							break;
						case ($_POST['partnerType'][$k] == PRT_CUSTOMER && (splitTransactionCodeDesc($trz['transactionCodeDesc'])[0] == ST_CUSTPAYMENT)):
							// customer payment
							if ($txn_currency != $comp_currency) {
								list ($rate, $msg) = manageExchangeRate( $date, $txn_currency, "");
								if ($msg) {
									display_notification( $msg);
								}
							}
							$transType = ST_BANKDEPOSIT;
							$reference = $Refs->get_next($transType);
							if (!is_new_reference($reference, $transType)) {
								display_error("Reference: $reference of Transaction Type: $transType already used.");
								break;
							}
							//RK } while (!is_new_reference($reference, ST_BANKDEPOSIT));

							$deposit_id = my_write_customer_payment(
								$trans_no = 0,
								$customer_id = $_POST["partnerId_$k"],
								$branch_id = $_POST["partnerDetailId_$k"],
								$bank_account = $smt_account['id'],
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
							if ($deposit_id) {
								update_transactions($tid, $_cids, $status = 1, $deposit_id, ST_BANKDEPOSIT, $reference);
								update_partner_data($partner_id = $_POST["partnerId_$k"], $partner_type = PT_CUSTOMER, $partner_detail_id = $_POST["partnerDetailId_$k"], $account = $trz['account']);
								//RK display_notification('Customer deposit processed');
								$count[PRT_CUSTOMER]++;
							}
							break;
						case ($_POST['partnerType'][$k] == PRT_QUICK_ENTRY):
							$cart_type = splitTransactionCodeDesc($trz['transactionCodeDesc'])[0]; // first component 
							$cart = new items_cart($cart_type);
							$cart->order_id = 0;
							$cart->original_amount = $trz['transactionAmount'] + $charge;

							if ($txn_currency != $comp_currency) {
								list ($rate, $msg) = manageExchangeRate( $date, $txn_currency, "");
								if ($msg) {
									display_notification( $msg);
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
							$rval = qe_to_cart($cart, $_POST["partnerId_$k"], $trz['transactionAmount'], getQEType( splitTransactionCodeDesc($trz['transactionCodeDesc'])[0]), $trz['transactionTitle']);
							$total = $cart->gl_items_total();
							switch ($cart_type) {
								case ST_BANKDEPOSIT:
								case ST_BANKPAYMENT:
									if ($total != 0) {
										//need to add the charge to the cart
										$cart->add_gl_item(get_company_pref('bank_charge_act'), 0, 0, $charge, PREFIX_CHARGES . $chargeTitle );
										//process the transaction
	
										begin_transaction();
		
										$trans = write_bank_transaction(
											$cart->trans_type,
											$cart->order_id,
											$smt_account['id'],
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
		
										if ( $trans[1]) {
											update_transactions($tid, $_cids, $status = 1, $trans[1], $cart_type, $cart->reference);
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
									
									if ( $trans_no) {
										update_transactions($tid, $_cids, $status = 1, $trans_no, $cart_type, $cart->reference);
										$count[PRT_QUICK_ENTRY]++;
									}
									break;					
							}
					
							break;

						case ($_POST['partnerType'][$k] == PRT_TRANSFER):
							// prepare transfer details
							$debit_account = get_bank_account_by_number($trz['account']);
							list($credit_account, $credit_amount) = explode(":", $trz['accountName']);
							$credit_account = get_bank_account_by_number($credit_account);

							// case comp	txn		credit	
							// 1	SGD		SGD		SGD		
							// 2	SGD		SGD		!SGD	
							// 3	SGD		!SGD 	SGD		
							// 4	SGD		!SGD	!SGD	

							// determine fx rates to avoid fx gain/loss for currency convertions
							$credit_currency = $credit_account['bank_curr_code'];
							$txn_amount = $trz['transactionAmount'];
							$txn_rate = null;
							$credit_rate = null;
							switch (true) {
								case ($txn_currency == $comp_currency && $credit_currency == $comp_currency):
									// case 1: no fx rates involved
									break;
								case ($txn_currency == $comp_currency && $credit_currency != $comp_currency):
									$credit_rate = $txn_amount / $credit_amount;
									// case 2: calc credit_rate
									list ($txn_rate, $msg) = manageExchangeRate( $date, $credit_currency, $credit_rate);
									if ($msg) {
										display_notification( $msg);
									}
									break;

								case ($txn_currency != $comp_currency && $credit_currency == $comp_currency):
									$txn_rate = $credit_amount / $txn_amount;
									// case 3: calc txn rate
									list ($txn_rate, $msg) = manageExchangeRate( $date, $txn_currency, $txn_rate);
									if ($msg) {
										display_notification( $msg);
									}
									break;

								case ($txn_currency != $comp_currency && $credit_currency != $comp_currency):
									// case 4:
									// retrive txn_rate
									list ($txn_rate, $msg) = manageExchangeRate( $date, $txn_currency, "");
									if ($msg) {
										display_notification( $msg);
									}
									else {
										$credit_rate = $txn_amount * $txn_rate / $credit_amount;
										// calc credit_rate
										list ($txn_rate, $msg) = manageExchangeRate( $date, $credit_currency, $credit_rate);
										if ($msg) {
											display_notification( $msg);
										}
									}
									break;
									
							}

							$reference = $Refs->get_next(ST_BANKTRANSFER);
							// add_bank_transfer( $from_account, $to_account, $date_, $amount, $ref, $memo_, $dim1, $dim2, $charge=0, $target_amount=0)
							$trans_no = add_bank_transfer($debit_account['id'], $credit_account['id'], $date, user_numeric($txn_amount), $reference, $trz['transactionTitle'], "","", 0, user_numeric($credit_amount));
							
							if ( $trans_no) {
								update_transactions($tid, $_cids, $status = 1, $trans_no, ST_BANKTRANSFER, $reference);
								$count[PRT_TRANSFER]++;
							}
							break;		

						case ($_POST['partnerType'][$k] == PRT_MANUAL_SETTLEMENT):
							//RK display_notification("tid=$tid, cids=`" . $_POST['cids'][$tid] . "`");
							//RK display_notification("cids_array=" . print_r($_cids, true));
							$transType = $_POST["transType"][$k];
							$transRef = $_POST["transRef"][$k];
							$transDate = sql2date( $trz['valueTimestamp']);
							if (isset( $transType) && isset( $transRef)) {
								// check if transaction exists
								$txn = retrieve_txn_by_reference($transType,$transRef,$transDate);
								if (isset($txn) && ($transRef == $txn['reference'])) { 
									update_transactions($tid, $_cids, $status = 1, $txn['trans_no'], $transType, $transRef);
									$count[PRT_MANUAL_SETTLEMENT]++;
								} 
								else {
									display_error("Invalid Type '$transType', reference '$transRef' or date '$transDate for Manual Transaction");
								}
							}
							else {
								display_notification('Type or Reference for Manual Transaction missing');
							}
							break;
					} // end of switch
					$Ajax->activate('doc_tbl');
				} //end of if !error
			} // end of is set....
		} //RK foreach 
	}
	else {
		display_notification( "No transactions selected for processing");
	}
	//RK show total transaction processed 
	$total = $count[PRT_SUPPLIER]+$count[PRT_CUSTOMER]+$count[PRT_QUICK_ENTRY]+$count[PRT_TRANSFER]+$count[PRT_MANUAL_SETTLEMENT];
	display_notification("Total transactions processed: $total (Supplier Payments: ".$count[PRT_SUPPLIER].", Customer Payments: ".$count[PRT_CUSTOMER].", Quick Entries: ".$count[PRT_QUICK_ENTRY].", Funds Transfer: ".$count[PRT_TRANSFER].", Manual Payments: ".$count[PRT_MANUAL_SETTLEMENT].")");
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

//SC: 05.10.2012: whether post['partnerType'] exists, refresh
if (isset($_POST['partnerType'])) {
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
		$_POST['statusFilter'] = 0;
	if (!isset($_POST['TransAfterDate']))
		$_POST['TransAfterDate'] = begin_month(Today());
	//RK $_POST['TransAfterDate'] = '01/01/2012';

	if (!isset($_POST['TransToDate']))
		$_POST['TransToDate'] = end_month(Today());

	date_cells(_("From:"), 'TransAfterDate', '', null, -30);
	date_cells(_("To:"), 'TransToDate', '', null, 1);
	label_cells(_("Status:"), array_selector('statusFilter', $_POST['statusFilter'], array(0 => 'Unsettled', 1 => 'Settled', 255 => 'All')));

	submit_cells('RefreshInquiry', _("Search"), '', _('Refresh Inquiry'), 'default');
	end_row();
	end_table();

	//if (!@$_GET['popup'])
	//	end_form();


	//------------------------------------------------------------------------------------------------
	// this is data display table

	$sql = "
	SELECT t.*, s.account smt_account, s.currency from " . TB_PREF . "bi_transactions t
    	    LEFT JOIN " . TB_PREF . "bi_statements as s ON t.smt_id = s.id
	WHERE
	    t.valueTimestamp >= " . db_escape(date2sql($_POST['TransAfterDate'])) . " AND
	    t.valueTimestamp <=  " . db_escape(date2sql($_POST['TransToDate']));

	if ($_POST['statusFilter'] != 255) {
		$sql .= " AND t.status = " . db_escape($_POST['statusFilter']);
	}

	$sql .= " ORDER BY t.valueTimestamp ASC";
	$res = db_query($sql, 'unable to get transactions data');


	//load data
	$trzs = array();
	while ($myrow = db_fetch($res)) {
		// generate unique key
		$trz_code =  $myrow['smt_account'].$myrow['smt_id'].$myrow['transactionCode'] ; 
		if (!isset($trzs[$trz_code])) {
			$trzs[$trz_code] = array();
		}
		$trzs[$trz_code][] = $myrow;
	}

	start_table(TABLESTYLE, "width='100%'");

	table_header(array("Transaction Details", "Operation/Status"));

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
				$valueTimestamp = $trz['valueTimestamp'];
				$bankAccount = $trz['account'];
				$bankAccountName = $trz['accountName'];
				$transactionTitle = $trz['transactionTitle'];
				$currency = $trz['currency'];
				$status = $trz['status'];
				$tid = $trz['id'];
				$has_trz = 1;
				$amount = $trz['transactionAmount'];
				$fa_trz_type = $trz['fa_trans_type'];
				$fa_trz_no = $trz['fa_trans_no'];
				$fa_trz_ref = $trz['matchinfo']; //RK using matchinfo for FA reference

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
					$currency = $trz['currency'];
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
		label_row("Trans Date:", $valueTimestamp, "width='25%' class='label'");
		label_row("Trans Type:", ($transactionDC == 'C') ? "Credit" : "Debit");
		label_row("Account:", $bankAccount);
		label_row("Counterparty:", $bankAccountName);
		label_row("Amount/Charge(s):", $amount . ' / ' . $charge . " (" . $currency . ")");
		label_row("Trans Title:", $transactionTitle);
		end_table();

		echo "</td><td width='50%' valign='top'>";

		start_table(TABLESTYLE2, "width='100%'");

		//now display stuff: forms and information

			if ($status == 1) {
			
			// determine trans_no by reference (logic form journal_inquiry.php)
			if (isset($fa_trz_ref) && $fa_trz_ref) {
				$txn = retrieve_txn_by_reference( $fa_trz_type, $fa_trz_ref,sql2date( $valueTimestamp));
				if (isset($txn) && ($fa_trz_ref == $txn['reference'])){
					$fa_trz_no = $txn['trans_no'];
				}
			}
			else {
					$fa_trz_ref = '-';
			}
	
			// the transaction is settled, we can display full details
			label_row("Status:", "<b>Transaction is settled!</b>", "width='25%' class='label'");
			label_row("Type:", $systypes_array[$fa_trz_type] . " ($fa_trz_type)");
			label_row("Reference (Trans. No):", $fa_trz_ref."(".get_trans_view_str($fa_trz_type, $fa_trz_no).") ".get_gl_view_str($fa_trz_type, $fa_trz_no).trans_editor_link($fa_trz_type, $fa_trz_no));
			

			switch ($trz['fa_trans_type']) {
				case ST_SUPPAYMENT:

					// get supplier info

					//label_row("Supplier:", $minfo['supplierName']);
					//label_row("From bank account:", $minfo['coyBankAccountName']);
					break;
				case ST_BANKDEPOSIT:
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
		} else {
			//transaction not settled
			// this is a new transaction, but not matched by routine so just display some forms

			if (!isset($_POST['partnerType'][$tid])) {
				if (isset( $transactionCodeDesc)) {
					list( $transType, $paymentType, $value ) = splitTransactionCodeDesc($transactionCodeDesc);

					switch ($transType) {
						case ST_BANKDEPOSIT:
						case ST_BANKPAYMENT:
						case ST_CUSTPAYMENT:
						case ST_SUPPAYMENT:
							$partnerType = getProcessingType($paymentType);
							break;
						case ST_BANKTRANSFER:
							$partnerType = PRT_TRANSFER;
							break;
						default:
							$partnerType = PRT_MANUAL_SETTLEMENT;
					}

					$_POST['partnerType'][$tid] = $partnerType;
					
					switch ($partnerType) {
						case PRT_QUICK_ENTRY:
							// lookup quick entries 
							$qe = get_quick_entries(getQEType(splitTransactionCodeDesc($trz['transactionCodeDesc'])[1]));
							$results =$qe->fetch_all();

							// find a matching quick entry
							foreach ($results as $row) {
								if ($row[2] == $value) {
									$_POST["partnerId_$tid"] = $row[0];
									break;
								}
							}
							break;
					}
				}
			}

			label_row("Operation:", getTransTypeDescription( splitTransactionCodeDesc($trz['transactionCodeDesc'])[0]), "width='25%' class='label'");
			label_row("Partner:", array_selector("partnerType[$tid]", $_POST['partnerType'][$tid], $optypes, array('select_submit' => true)));

			if (!isset($_POST["partnerId_$tid"])) {
				$_POST["partnerId_$tid"] = '';
			}

			switch ($_POST['partnerType'][$tid]) {
				//supplier payment
				case PRT_SUPPLIER:
					//propose supplier
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
						$match = search_partner_by_bank_account(PT_CUSTOMER, $bankAccount);
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
					label_row(_("From Customer/Branch:"),  $cust_text);
					//label_row("debug", "customerid_tid=".$_POST["partnerId_$tid"]." branchid[tid]=".$_POST["partnerDetailId_$tid"]);

					break;
					// quick entry
				case PRT_QUICK_ENTRY:
					//label_row("Option:", "<b>Process via quick entry</b>");
					$qe_text = quick_entries_list("partnerId_$tid", null, getQEType( splitTransactionCodeDesc($trz['transactionCodeDesc'])[0]), true);
					$qe = get_quick_entry(get_post("partnerId_$tid"));
					$qe_text .= " " . $qe['base_desc'];

					label_row("Quick Entry:", $qe_text);
					break;
				case PRT_MANUAL_SETTLEMENT:
					hidden("partnerId_$tid", 'manual'); 
					journal_types_list_cells("Transaction Type:", "transType[$tid]");
					//function text_input($name, $value=null, $size='', $max='', $title='', $params='')
					label_row(_("Transaction Reference:"),  text_input( "transRef[$tid]"));

					break;
			}
			
			$selected = false;
			if (isset($_POST['ProcessTransaction'][$tid])) {
				$selected = $_POST['ProcessTransaction'][$tid];
			}
			
			if (isset($_POST['action'])) {
				if ($_POST['action'] == ACTION_PROCESS_SELECT_ALL) {
					// not autoselct manual
					if ($_POST['partnerType'][$tid] != PRT_MANUAL_SETTLEMENT) {
						$selected = true;
					}
				}
				if ($_POST['action'] == ACTION_PROCESS_DESELECT_ALL) {
					$selected = false;
				}
			}	

			label_row("", checkbox( "Selected for Bulk Processing", "ProcessTransaction[$tid]", $selected));
			label_row("", submit("ProcessSingleTransaction[$tid]", _(ACTION_PROCESS_SINGLE), false, '', 'default'));

			//other common info
			hidden("cids[$tid]", $cids);
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
?>