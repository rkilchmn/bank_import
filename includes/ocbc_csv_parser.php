<?php

// contains definitions for ST_BANKDEPOSIT etc
	
// include constants
$path_to_root = "../..";
include_once($path_to_root . "/modules/bank_import/includes/ocbc_csv_config.php");

	//we need to interpret the file and generate a new statement for each day of transactions

class ocbc_csv_parser extends parser
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

		// keep statements in an array, hashed by statement-id
		$smts = array();
		$sid = substr($static_data['filename'], 0, 32);

		if (empty($sid))
			return; // error

		// Use regular expression to extract the date
		$date = '';
		if (preg_match(OCBC_CSV_CONFIG::MATCH_STATEMENT_DATE_FILENAME, $static_data['filename'], $matches)) {
			$date =  $this->convertDate( OCBC_CSV_CONFIG::STATEMENT_DATE_FORMAT, TARGET_DATE_FORMAT, $matches[0]);
		}

		$smts[$sid] = new statement;
		$smts[$sid]->bank = "OCBC";
		$smts[$sid]->account = $static_data['account'];
		$smts[$sid]->currency = $static_data['currency'];
		$smts[$sid]->startBalance = 0; // will be updated while processing transactions
		$smts[$sid]->endBalance = 0; // will be updated while processing transactions
		$smts[$sid]->timestamp = $date;
		$smts[$sid]->number = '00000';
		$smts[$sid]->sequence = '0';
		$smts[$sid]->statementId = $sid;

		$lineid = 0;

		// Process each line
		foreach ($lines as $line) {
			// Skip empty lines
			if (empty($line)) {
				continue;
			}

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

			// updating statement opening/closing balance
			if ($lineid == 1) {
				$smts[$sid]->startBalance = $rowData['Opening Balance'];
			} else {
				$smts[$sid]->endBalance = $rowData['Closing Book Balance'];
			}

			//add transaction data
			$trz = new transaction;
			$trz->valueTimestamp = $this->convertDate( OCBC_CSV_CONFIG::FILE_DATE_FORMAT, TARGET_DATE_FORMAT, $rowData['Statement Value Date']);
			$trz->entryTimestamp = $this->convertDate( OCBC_CSV_CONFIG::FILE_DATE_FORMAT, TARGET_DATE_FORMAT, $rowData['Statement Date']);
			$trz->account = $smts[$sid]->account;
			$trz->transactionTitle1 = trim($rowData['Statement Details Info']);
			$trz->accountName1 =  trim($rowData['Ref For Account Owner'] . " " . $rowData['Our Ref']);
			$trz->transactionType = $rowData['Transaction Type Code'];
			$trz->transactionCode = $sid . DELIM . $lineid . DELIM . $rowData['Transaction Type Code'];

			// debit/credit
			if ($rowData['Debit Amount']) {
				$trz->transactionDC = DC_DEBIT;
				$trz->transactionAmount = $rowData['Debit Amount'];
			} elseif ($rowData['Credit Amount']) {
				$trz->transactionDC = DC_CREDIT;
				$trz->transactionAmount = $rowData['Credit Amount'];
			}

			$accountingRules =  OCBC_CSV_CONFIG::getAccountingRules();

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
			$smts[$sid]->addTransaction($trz);
		}

		// return statement data
		return $smts;
	}
}
