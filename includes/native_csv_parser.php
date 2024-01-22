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

		$sid = trim($f[8]);

		if (empty($sid))
			return; // error

		$smt = new statement;
		$smt->bank = trim($f[0]);
		$smt->account = trim($f[1]);
		$smt->currency = trim($f[2]);
		$smt->startBalance = trim($f[3]);
		$smt->endBalance = trim($f[4]);
		$smt->timestamp = trim($f[5]);
		$smt->number = trim($f[6]);
		$smt->sequence = trim($f[7]);
		$smt->statementId = $sid;

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
			$smt->addTransaction($trz);

			// generate unique transactionCode if empty
			if(empty($trz->transactionCode)) {
				$trz->transactionCode = $smt->bank . ":" . $smt->statementId . ":" . $lineid;
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

				$smt->addTransaction($trz_chg);
			}
		}
		//time to return
		return $smt;
	}

}