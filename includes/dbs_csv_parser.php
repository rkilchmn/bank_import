<?php

// contains definitions for ST_BANKDEPOSIT etc
	
// include constants
$path_to_root = "../..";
include_once($path_to_root . "/modules/bank_import/includes/dbs_csv_config.php");

//we need to interpret the file and generate a new statement for each day of transactions

class dbs_csv_parser extends parser
{

	private function convertDate($inputFormat, $targetFormat, $inputDate)
	{
		$dateObject = DateTime::createFromFormat($inputFormat, $inputDate);
		return $dateObject->format( $targetFormat);
	}

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
		$sid =  $static_data['filename'];

		if (empty($sid))
			return; // error

		// Use regular expression to extract the date
		if (preg_match('/-(\d{8})-/', $static_data['filename'], $matches)) {
			$date = $matches[1];
		}

		$smts[$sid] = new statement;
		$smts[$sid]->bank = "DBS";
		$smts[$sid]->account = $static_data['account'];
		$smts[$sid]->currency = $static_data['currency'];
		$smts[$sid]->startBalance = 0; // will be updated while processing transactions
		$smts[$sid]->endBalance = 0; // will be updated while processing transactions
		$smts[$sid]->timestamp = $date;
		$smts[$sid]->number = '00000';
		$smts[$sid]->sequence = '0';
		$smts[$sid]->statementId = $sid;

		// Initialize an array to store the parsed data
		$parsedData = array();

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
				$smts[$sid]->endBalance = $rowData['Running Balance'];
			}

			//add transaction data
			$trz = new transaction;
			$trz->valueTimestamp = $this->convertDate( FILE_DATE_FORMAT, TARGET_DATE_FORMAT, $rowData['Value Date']);
			$trz->entryTimestamp = $this->convertDate( FILE_DATE_FORMAT, TARGET_DATE_FORMAT, $rowData['Transaction Date']);
			$trz->account = $smts[$sid]->account;
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
			}

			// bring in global variables from config into local scope
			global $accountingRules;

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
