<?php

// contains definitions for ST_BANKDEPOSIT etc
	
// include constants
$path_to_root = "../..";
include_once($path_to_root . "/modules/bank_import/includes/airwallex_csv_config.php");

	//we need to interpret the file and generate a new statement for each day of transactions

class airwallex_csv_parser extends parser
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
		$header = str_getcsv(array_shift($lines));

		$sid = substr($static_data['filename'], 0, 32);

		if (empty($sid))
			return; // error

		// Use regular expression to extract the date
		$date = '';
		if (preg_match(AIRWALLEX_CSV_CONFIG::MATCH_STATEMENT_DATE_FILENAME, $static_data['filename'], $matches)) {
			$date =  $this->convertDate( AIRWALLEX_CSV_CONFIG::STATEMENT_DATE_FORMAT, TARGET_DATE_FORMAT, $matches[0]);
		}

		$smt = new statement;
		$smt->bank = "AIRWLX";
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

			// Sample data
			// Time,Type,Financial Transaction Type,Transaction Id,Description,Wallet Currency,Target Currency,Target Amount,Conversion Rate,Mature Date,Amount,Fee,Debit Net Amount,Credit Net Amount,Account Balance,Available Balance,Created At,Request Id,Reference,Note to Self
  			// 2024-06-24T22:41:36+1000,DEPOSIT,DEPOSIT,5d3c0951-f0ff-48c9-b3b8-a0056f2c07d7,Payment from XYZ Corp for Invoice,EUR,,,,,"4,420.00",,,"4,420.00","4,420.00","4,420.00",2024-06-24T22:40:11+1000,,Invoice No. 001/2024,

			// Parse the current line into an associative array
			$data = str_getcsv($line);
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

			//add transaction data
			$trz = new transaction;
			$trz->valueTimestamp = $this->convertDate( AIRWALLEX_CSV_CONFIG::FILE_DATE_FORMAT, TARGET_DATE_FORMAT, $rowData['Time']);
			$trz->entryTimestamp = $this->convertDate( AIRWALLEX_CSV_CONFIG::FILE_DATE_FORMAT, TARGET_DATE_FORMAT, $rowData['Created At']);
			$trz->account = $smt->account;
			$trz->transactionTitle1 = trim($rowData['Description']);
			$trz->accountName1 =  $rowData['Reference'];
			$trz->transactionType = $rowData['Financial Transaction Type'];
			$trz->transactionCode = $rowData['Transaction Id'];

			// debit/credit
			if (isset($rowData['Debit Net Amount']) && is_numeric(str_replace(',', '', $rowData['Debit Net Amount'])) && str_replace(',', '', $rowData['Debit Net Amount']) > 0) {
				$trz->transactionDC = DC_DEBIT;
				$trz->transactionAmount = str_replace(',', '', $rowData['Debit Net Amount']);
			} elseif (isset($rowData['Credit Net Amount']) && is_numeric(str_replace(',', '', $rowData['Credit Net Amount'])) && str_replace(',', '', $rowData['Credit Net Amount']) > 0) {
				$trz->transactionDC = DC_CREDIT;
				$trz->transactionAmount = str_replace(',', '', $rowData['Credit Net Amount']);
			} else {
				// Handle the case where neither amount is valid
				$trz->transactionDC = null;
				$trz->transactionAmount = 0.00;
			}

			// updating statement opening/closing balance
			if ($lineid == 1) {
				$smt->startBalance = str_replace(',', '', $rowData['Account Balance']);
				if ($trz->transactionDC == DC_CREDIT) {
					$smt->startBalance -= $trz->transactionAmount;
				} else {
					$smt->startBalance += $trz->transactionAmount;
				}
			} else {
				$smt->endBalance = str_replace(',', '', $rowData['Account Balance']);
			}

			$accountingRules =  AIRWALLEX_CSV_CONFIG::getAccountingRules();

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
