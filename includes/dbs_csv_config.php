
<?php

// include constants
$path_to_root = "../..";
include_once($path_to_root . "/includes/types.inc");
include_once($path_to_root . "/modules/bank_import/includes/includes.inc");

// define quick entries defined in system
define('QE_BANK_CHARGE', 'BankCharge');
define('QE_BANK_CHARGE_REF', 'BankChargeRefund');

// prefined defined constants/keywords -> helps to identify typos
define('_DEFAULT', 'default');
define('_CONDITION', 'condition');
define('_ACTION', 'action');

// Example 16-Dec-2023
define('FILE_DATE_FORMAT', 'd-M-Y');

$accountingRules = [
    'BAT' => [ // BUSINESS ADVANCE CARD TRANSACTION
        DC_CREDIT => [
            _CONDITION => function ($trz) {
                return strpos($trz->transactionTitle1, 'CASHBACK') !== false ? 'CASHBACK' : _DEFAULT;
            },
            _ACTION => [
                'CASHBACK' => 
                    function ($trz) {
                        $trz->transactionCodeDesc = ST_BANKDEPOSIT.DELIM.PRT_QUICK_ENTRY.DELIM.QE_BANK_CHARGE_REF;
                    },
                _DEFAULT => 
                    function ($trz) {
                        $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
                    },
            ],
        ],
        DC_DEBIT => [
            _CONDITION => function ($trz) {
                return strpos($trz->transactionTitle1, 'ZERO1 PTE LTD') !== false ? 'Zero1' : _DEFAULT;
            },
            _ACTION => [
                'Zero1' =>
                    function ($trz) {
                        $trz->transactionCodeDesc = ST_SUPPAYMENT.DELIM.PRT_SUPPLIER.DELIM.'Zero1';
                    },
                _DEFAULT => 
                    function ($trz) {
                        $trz->transactionCodeDesc = ST_SUPPAYMENT.DELIM.PRT_SUPPLIER;
                    },
            ],
        ],
    ],
    'GRS' => [ // GIRO PAYROLL
        DC_DEBIT => [
            _CONDITION => function ($trz) {
                return strpos($trz->transactionTitle1, 'Salary') !== false ? 'Salary_Roger' : _DEFAULT;
            },
            _ACTION => [
                'Salary_Roger' => 
                    function ($trz) {
                        $trz->transactionCodeDesc = ST_SUPPAYMENT.DELIM.PRT_SUPPLIER.DELIM.'Employee Roger';
                    },
                _DEFAULT => 
                    function ($trz) {
                        $trz->transactionCodeDesc = ST_SUPPAYMENT.DELIM.PRT_SUPPLIER;
                    },
            ],
        ],
    ],
    'ADV' => [ // PAYMENT ADVICE
        DC_DEBIT => [
            _CONDITION => function ($trz) {
                return _DEFAULT;
            },
            _ACTION => [
                _DEFAULT => 
                    function ($trz) {
                        $trz->transactionCodeDesc = ST_BANKPAYMENT.DELIM.PRT_QUICK_ENTRY.DELIM.QE_BANK_CHARGE;
                    },
            ],
        ],
        DC_CREDIT => [
            _CONDITION => function ($trz) {
                return _DEFAULT;
            },
            _ACTION => [
                _DEFAULT => 
                    function ($trz) {
                        $trz->transactionCodeDesc = ST_BANKDEPOSIT.DELIM.PRT_QUICK_ENTRY.DELIM.QE_BANK_CHARGE_REF;
                    },
            ],
        ],
    ],
    _DEFAULT => [
        DC_CREDIT => [
            _CONDITION => function ($trz) {
                return _DEFAULT;
            },
            _ACTION => [
                _DEFAULT => 
                    function ($trz) {
                        $trz->transactionCodeDesc = $trz->transactionCodeDesc = ST_BANKDEPOSIT.DELIM.PRT_MANUAL_SETTLEMENT;
                    }
            ],
        ],
        DC_DEBIT => [
            _CONDITION => function ($trz) {
                return _DEFAULT;
            },
            _ACTION => [
                _DEFAULT => 
                    function ($trz) {
                        $trz->transactionCodeDesc = $trz->transactionCodeDesc = ST_BANKPAYMENT.DELIM.PRT_MANUAL_SETTLEMENT;
                    }
            ],
        ],
    ]
];

?>
