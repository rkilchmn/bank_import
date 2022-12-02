<?php

//we need to interpret the file and generate a new statement for each day of transactions

class native_csv_parser extends parser {

    function parse($content, $static_data = array(), $debug = true) {
		//split content by \n
		$lines = explode("\n", $content);
		//first line is statement fields header so ignore it
		array_shift($lines);

		$line = array_shift($lines);
		$f = str_getcsv($line);

		//cleanup numeric fields
		foreach(array(3,4) as $i) {
			$f[$i] = str_replace(',', '', $f[$i]);
		}

		//keep statements in an array, hashed by statement-id
		$smts = array();
		$sid = trim($f[8]);

		if (empty($sid))
			return; // error

		$smts[$sid] = new statement;
		$smts[$sid]->bank = trim($f[0]);
		$smts[$sid]->account = trim($f[1]);
		$smts[$sid]->currency = trim($f[2]);
		$smts[$sid]->startBalance = trim($f[3]);
		$smts[$sid]->endBalance = trim($f[4]);
		$smts[$sid]->timestamp = trim($f[5]);
		$smts[$sid]->number = trim($f[6]);
		$smts[$sid]->sequence = trim($f[7]);
		$smts[$sid]->statementId = $sid;

		//next line is transaction fields header so ignore it
		array_shift($lines);

		//parse transaction lines
		$lineid = 0;
		foreach($lines as $line) {

			$f = str_getcsv($line);

			if(empty($f[0]))
				continue;

			$lineid++;
		
			//add transaction data
			$trz = new transaction;

			//cleanup numeric fields
			foreach(array(7,9) as $i) {
				$f[$i] = str_replace(',', '', $f[$i]);
			}

			$trz->valueTimestamp = $f[0];
			$trz->entryTimestamp = $f[1];
			$trz->account = $f[2];
			$trz->accountName1 = $f[3];
			$trz->transactionCode = $f[4];
			$trz->transactionCodeDesc = $f[5];
			$trz->transactionDC = $f[6];
			$trz->transactionAmount = $f[7];
			$trz->transactionTitle1 = $f[8];
			$transactionChargeAmount = $f[9]; // charge will be a seperate new transaction
			$transactionChargeTitle1 = $f[10]; // charge will be a seperate new transaction
			
			// chack debit/credit
			if ($trz->transactionDC == "C") {
				$total = $trz->transactionAmount - $transactionChargeAmount;
				if ($total < 0 )
					// negative amount -> bocomes a debit/bank payment
					$trz->transactionDC = "D";
			}

			// enrichment
			$trz->transactionType = 'TRF';
			$smts[$sid]->addTransaction($trz);

			// generate unique transactionCode if empty
			if(empty($trz->transactionCode)) {
				$trz->transactionCode = $smts[$sid]->bank . ":" . $smts[$sid]->statementId . ":" . $lineid;
			}

			if ($transactionChargeAmount > 0) {
				// add additional transaction for charge
				$trz_chg = clone $trz;

				$trz_chg->transactionType = 'COM';
				$trz_chg->transactionDC = "D"; //RK charge always debit
				$trz_chg->transactionAmount = $transactionChargeAmount;
				$trz_chg->transactionTitle1 = $transactionChargeTitle1;
				$trz_chg->account = "";
				$trz_chg->accountName1 = "";

				$smts[$sid]->addTransaction($trz_chg);
			}
		}
		//time to return
		return $smts;
	}

}