<?php

// include constants
$path_to_root = "../..";
include_once($path_to_root . "/includes/types.inc");
include_once($path_to_root . "/modules/bank_import/includes/includes.inc");

class AIRWALLEX_CSV_CONFIG
{
    // define quick entries defined in system
    // const QE_PAYMENT_BANKCHARGE = 'BankCharge';
    // const QE_DEPOSIT_CHARGE_REF = 'BankChargeRefund';
    // const QE_DEPOSIT_IRAS_REF = 'IRASRefund';
    // const QE_PAYMENT_IRAS = 'IRASInstallment';

    // extract statement date from filename - needs to be match[1   ]
    const MATCH_STATEMENT_DATE_FILENAME = '/(\d{4}-\d{2}-\d{2})$/'; // match last date, sample Balance_Activity_Report_EUR_2024-04-01_to_2024-06-30
    const STATEMENT_DATE_FORMAT = 'Y-m-d';

    // define date format used in file for transactions: Example for this statement format: 20230703
    const FILE_DATE_FORMAT = 'Y-m-d\TH:i:sO'; // sample 2024-06-24T22:41:36+1000

    public static function getAccountingRules()
    {
        $accountingRules = [
            'DEPOSIT' => [
                DESCRIPTION => "DEPOSIT",
                DC_CREDIT => [
                    CONDITION => function ($trz) {
                        if (stristr($trz->transactionTitle1 ?? '', 'FIXTRA')) {
                            return 'FIXTRA';
                        } else {
                            return DEF;
                        }
                    },
                    ACTION => [
                        'FIXTRA' =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_CUSTOMER . DELIM . 'FIXTRA';
                        },
                        DEF =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
                        },
                    ],
                ],
                DC_DEBIT => [
                    CONDITION => function ($trz) {
                        return DEF;         
                    },
                    ACTION => [
                        DEF =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
                        },
                    ],
                ],
            ],
            'PAYOUT' => [
                DESCRIPTION => "PAYOUT",
                DC_DEBIT => [
                    CONDITION => function ($trz) {
                        if (stristr($trz->transactionTitle1 ?? '', 'Financial Technology Consulting Pty Ltd')) {
                            return 'TRA_IBKR';
                        } else {
                            return DEF;
                        }
                    },
                    ACTION => [
                        'TRA_IBKR' =>
                            function ($trz) {
                                $trz->transactionCodeDesc = PRT_TRANSFER . DELIM . 'U15502348 EUR' . DELIM .$trz->transactionAmount. DELIM .'0';
                        },
                        DEF =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
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
                            $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
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
