<?php

// include constants
$path_to_root = "../..";
include_once($path_to_root . "/includes/types.inc");
include_once($path_to_root . "/modules/bank_import/includes/includes.inc");

class OCBC_CSV_CONFIG
{
    // define quick entries defined in system
    const QE_PAYMENT_BANKCHARGE = 'BankCharge';
    const QE_DEPOSIT_CHARGE_REF = 'BankChargeRefund';
    const QE_DEPOSIT_IRAS_REF = 'IRASRefund';
    const QE_PAYMENT_IRAS = 'IRASInstallment';

    // extract statement date from filename - needs to be match[1   ]
    const MATCH_STATEMENT_DATE_FILENAME = '/(\d{4}-\d{2}-\d{2})/'; 
    const STATEMENT_DATE_FORMAT = 'Y-m-d';

    // define date format used in file for transactions: Example for this statement format: 20230703
    const FILE_DATE_FORMAT = 'Ymd';

    public static function getAccountingRules()
    {
        $accountingRules = [
            'NTRF' => [
                DESCRIPTION => "FAST TRANSFER",
                DC_DEBIT => [
                    CONDITION => function ($trz) {
                        if (stristr($trz->transactionTitle1, 'PayNow')) {
                            return 'SUP';
                        } else {
                            return DEF;
                        }
                    },
                    ACTION => [
                        'SUP' =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_SUPPLIER;
                        },
                        DEF =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
                        },
                    ],
                ],
                DC_CREDIT => [
                    CONDITION => function ($trz) {
                        if (stristr($trz->accountName1, 'FIN.TEC.&SEC.SOL')) {
                            return 'FINSEC_DBS';
                        } else {
                            return DEF;
                        }
                    },
                    ACTION => [
                        'FINSEC_DBS' =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
                        },
                        DEF =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
                        },
                    ],
                ],
            ],
            'NMSC' => [
                DESCRIPTION => "SERVICE CHARGES",
                DC_DEBIT => [
                    CONDITION => function ($trz) {
                        return DEF;
                    },
                    ACTION => [
                        DEF =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_QUICK_ENTRY . DELIM . QE_PAYMENT . DELIM . self::QE_PAYMENT_BANKCHARGE;
                        },
                    ],
                ],
                DC_CREDIT => [
                    CONDITION => function ($trz) {
                        return DEF;
                    },
                    ACTION => [
                        DEF =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_QUICK_ENTRY . DELIM . QE_DEPOSIT . DELIM . self::QE_DEPOSIT_CHARGE_REF;
                        },
                    ],
                ],
            ],
            DEF => [
                DESCRIPTION => "Default Transaction",
                DC_CREDIT => [
                    CONDITION => function ($trz) {
                        return DEF;
                    },
                    ACTION => [
                        DEF =>
                        function ($trz) {
                            $trz->transactionCodeDesc = $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
                        }
                    ],
                ],
                DC_DEBIT => [
                    CONDITION => function ($trz) {
                        return DEF;
                    },
                    ACTION => [
                        DEF =>
                        function ($trz) {
                            $trz->transactionCodeDesc = $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
                        }
                    ],
                ],
            ]
        ];
        return $accountingRules;
    }
}