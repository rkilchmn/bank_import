<?php

include_once($path_to_root . "/modules/bank_import/includes/parser.php");
// include_once($path_to_root . "/modules/bank_import/includes/mt940_parser.php");
// include_once($path_to_root . "/modules/bank_import/includes/ro_brd_mt940_parser.php");
// include_once($path_to_root . "/modules/bank_import/includes/ro_bcr_csv_parser.php");
// include_once($path_to_root . "/modules/bank_import/includes/ro_ing_csv_parser.php");
include_once($path_to_root . "/modules/bank_import/includes/native_csv_parser.php");
include_once($path_to_root . "/modules/bank_import/includes/dbs_csv_parser.php");
include_once($path_to_root . "/modules/bank_import/includes/ocbc_csv_parser.php");
include_once($path_to_root . "/modules/bank_import/includes/airwallex_csv_parser.php");

function getParsers() {
    return array(
	'native_csv' => array('name' => 'Native, CSV format', 'select' => array('bank_account' => 'Select bank account')),
	'dbs_csv' => array('name' => 'DBS.SG, CSV format (Cash Management Details Report)', 'select' => array('bank_account' => 'Select bank account')),
	'ocbc_csv' => array('name' => 'OCBC.SG, CSV format (with header)', 'select' => array('bank_account' => 'Select bank account')),
	'airwallex_csv' => array('name' => 'Airwallex, CSV format (with header)', 'select' => array('bank_account' => 'Select bank account'))
	// 'ro_brd_mt940' => array('name' => 'BRD-RO, MT940 format'),
	// 'ro_bcr_csv' => array('name' => 'BCR-RO, CSV format'),
	// 'ro_ing_csv' => array('name' => 'ING-RO, CSV format', 'select' => array('bank_account' => 'Select bank account'))
	);
}

?>