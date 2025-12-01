<?php

// contains definitions for ST_BANKDEPOSIT etc
	
// include constants
$path_to_root = "../..";
include_once($path_to_root . "/modules/bank_import/includes/dbs_csv_config.php");

//we need to interpret the file and generate a new statement for each day of transactions

class dbs_csv_parser extends parser
{
	private function excuteTransactionLogic($transactionLogic, $trz)
	{
		// condition
		$condition = $transactionLogic[$trz->transactionDC][CONDITION];
		$conditionEval = $condition($trz);

		// action
		$action = $transactionLogic[$trz->transactionDC][ACTION][$conditionEval];

		if (!isset($action)) {
			// default action
			$action = $transactionLogic[$trz->transactionDC][ACTION][DEF];
		}

		$action($trz);

		return true;
	}

	function parse($content, $static_data = array(), $debug = true)
	{
		// Split content by \n
		$lines = explode("\n", $content);

		// Extract field names from the first line
		$header = str_getcsv(array_shift($lines), ",", "\"", "\\");

		$sid = substr($static_data['filename'], 0, 32);
		
		if (empty($sid))
			return; // error

		// Use regular expression to extract the date
		$date = '';
		if (preg_match(DBS_CSV_CONFIG::MATCH_STATEMENT_DATE_FILENAME, $static_data['filename'], $matches)) {
			$date =  $this->convertDate( DBS_CSV_CONFIG::STATEMENT_DATE_FORMAT, TARGET_DATE_FORMAT, $matches[1]);
		}

		$smt = new statement;
		$smt->bank = "DBS";
		$smt->account = $static_data['account'];
		$smt->currency = $static_data['currency'];
		$smt->startBalance = 0; // will be updated while processing transactions
		$smt->endBalance = 0; // will be updated while processing transactions
		$smt->timestamp = $date;
		$smt->number = '00000';
		$smt->sequence = '0';
		$smt->statementId = $sid;

		$lineid = 0;

		// Process each line
		foreach ($lines as $line) {
			// Skip empty lines
			if (empty($line)) {
				continue;
			}

			// Parse the current line into an associative array
			$data = str_getcsv($line, ",", "\"", "\\");
			$rowData = array();

			// Combine header and data into an associative array
			foreach ($header as $index => $field) {
				if (isset($data[$index])) {
					$rowData[$field] = $data[$index];
				} else {
					// Handle the case where the data is shorter than the header
					$rowData[$field] = '';
				}
			}

			$lineid++;

			// updating statement opening/closing balance
			if ($lineid == 1) {
				$smt->startBalance = $rowData['Opening Balance'];
			} else {
				$smt->endBalance = $rowData['Running Balance'];
			}

			//add transaction data
			$trz = new transaction;
			$trz->valueTimestamp = $this->convertDate( DBS_CSV_CONFIG::FILE_DATE_FORMAT, TARGET_DATE_FORMAT, $rowData['Value Date']);
			$trz->entryTimestamp = $this->convertDate( DBS_CSV_CONFIG::FILE_DATE_FORMAT, TARGET_DATE_FORMAT, $rowData['Transaction Date']);
			$trz->account = $smt->account;
			$trz->transactionTitle1 = trim($rowData['Transaction Detail']);
			$trz->accountName1 =  trim($rowData['Beneficiary Name']);
			$trz->transactionType = $rowData['Transaction Code'];
			$trz->transactionCode = $sid . DELIM . $lineid . DELIM . $rowData['Transaction Code'];

			// debit/credit
			if ($rowData['Debit']) {
				$trz->transactionDC = DC_DEBIT;
				$trz->transactionAmount = $rowData['Debit'];
			} elseif ($rowData['Credit']) {
				$trz->transactionDC = DC_CREDIT;
				$trz->transactionAmount = $rowData['Credit'];
			} else {
				// Handle the case where neither amount is valid
				$trz->transactionDC = null;
				$trz->transactionAmount = 0.00;
			}
			$accountingRules =  DBS_CSV_CONFIG::getAccountingRules();

			// determine posting logic based on config
			if (isset($accountingRules[$trz->transactionType])) {
				$transactionLogic = $accountingRules[$trz->transactionType];

				if (isset($transactionLogic[$trz->transactionDC])) {
					$this->excuteTransactionLogic($transactionLogic, $trz);
				} else {
					// Apply default logic if transactionDC does not match
					$transactionLogic = $accountingRules[DEF];

					if (isset($transactionLogic[$trz->transactionDC])) {
						$this->excuteTransactionLogic($transactionLogic, $trz);
					}
				}
			} else {
				// Apply default logic if transactionDC does not match
				$transactionLogic = $accountingRules[DEF];

				if (isset($transactionLogic[$trz->transactionDC])) {
					$this->excuteTransactionLogic($transactionLogic, $trz);
				}
			}
			$smt->addTransaction($trz);
		}

		// return statement data
		return $smt;
	}
}
