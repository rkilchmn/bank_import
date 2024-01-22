
<?php

// include constants
$path_to_root = "../..";
include_once($path_to_root . "/includes/types.inc");
include_once($path_to_root . "/modules/bank_import/includes/includes.inc");

class DBS_CSV_CONFIG
{
    // define quick entries defined in system
    const QE_PAYMENT_BANKCHARGE = 'BankCharge';
    const QE_DEPOSIT_CHARGE_REF = 'BankChargeRefund';
    const QE_DEPOSIT_IRAS_REF = 'IRASRefund';
    const QE_PAYMENT_IRAS = 'IRASInstallment';

    // extract statement date from filename
    const MATCH_STATEMENT_DATE_FILENAME = '/-(\d{8})-/';
    const STATEMENT_DATE_FORMAT = 'Ymd';

    // Example 16-Dec-2023
    const FILE_DATE_FORMAT = 'd-M-Y';

    public static function getAccountingRules()
    {

        $accountingRules = [
            'BAT' => [
                DESCRIPTION => "BUSINESS ADVANCE CARD TRANSACTION",
                DC_CREDIT => [
                    CONDITION => function ($trz) {
                        return stristr($trz->transactionTitle1, 'CASHBACK') !== false ? 'CASHBACK' : DEF;
                    },
                    ACTION => [
                        'CASHBACK' =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_QUICK_ENTRY . DELIM . QE_DEPOSIT . DELIM . self::QE_DEPOSIT_CHARGE_REF;
                        },
                        DEF =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
                        },
                    ],
                ],
                DC_DEBIT => [
                    CONDITION => function ($trz) {
                        return stristr($trz->transactionTitle1, 'ZERO1 PTE LTD') !== false ? 'Zero1' : DEF;
                    },
                    ACTION => [
                        'Zero1' =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_SUPPLIER . DELIM . 'Zero1';
                        },
                        DEF =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
                        },
                    ],
                ],
            ],
            'GRS' => [
                DESCRIPTION => "GIRO PAYROLL",
                DC_DEBIT => [
                    CONDITION => function ($trz) {
                        return DEF;
                    },
                    ACTION => [
                        DEF =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_SUPPLIER . DELIM . 'Employee Roger';
                        },
                    ],
                ],
            ],
            'ICT' => [
                DESCRIPTION => "FAST PAYMENT",
                DC_DEBIT => [
                    CONDITION => function ($trz) {
                        if (stristr($trz->accountName1, 'CENTRAL PROVIDENT FUND BOARD')) {
                            return 'CPF';
                        } elseif (stristr($trz->accountName1, 'ContactOne Professional Services')) {
                            return 'Contact One';
                        } elseif (stristr($trz->accountName1, 'FINANCIAL TECHNOLOGY n SECURITY SOL')) {
                            return 'FINSEC_OCBC';
                        } else {
                            return DEF;
                        }
                    },
                    ACTION => [
                        'CPF' =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_SUPPLIER . DELIM . 'CPF';
                        },
                        'Contact One' =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_SUPPLIER . DELIM . 'Contact One';
                        },
                        'FINSEC_OCBC' =>
                        function ($trz) {
                            // use FA Bank Account field "Number"
                            $trz->transactionCodeDesc = PRT_TRANSFER . DELIM . '713430494001' . DELIM . $trz->transactionAmount . DELIM . '0.00';
                        },
                        DEF =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
                        },
                    ],
                ],
            ],
            'IBG' => [
                DESCRIPTION => "INTERBANK GIRO",
                DC_DEBIT => [
                    CONDITION => function ($trz) {
                        if (stristr($trz->transactionTitle1, 'IRAS')) {
                            return 'IRAS';
                        } else {
                            return DEF;
                        }
                    },
                    ACTION => [
                        'IRAS' =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_QUICK_ENTRY . DELIM . QE_PAYMENT . DELIM . self::QE_PAYMENT_IRAS;
                        },
                        DEF =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
                        },
                    ],
                ],
                DC_CREDIT => [
                    CONDITION => function ($trz) {
                        if (stristr($trz->transactionTitle1, 'IRAS')) {
                            return 'IRAS';
                        } else {
                            return DEF;
                        }
                    },
                    ACTION => [
                        'IRAS' =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_QUICK_ENTRY . DELIM . QE_DEPOSIT . DELIM . self::QE_DEPOSIT_IRAS_REF;
                        },
                        DEF =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
                        },
                    ],
                ],
            ],
            'SCICT' => [
                DESCRIPTION => "SERVICE CHARGE FOR PAYNOW PAYMENTS",
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
            ],
            'ADV' => [
                DESCRIPTION => "PAYMENT ADVICE",
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
            'MER' => [
                DESCRIPTION => "MEPS RECEIPT",
                DC_CREDIT => [
                    CONDITION => function ($trz) {
                        return DEF;
                    },
                    ACTION => [
                        DEF =>
                        function ($trz) {
                            $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
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
                            $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
                        }
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
                            $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
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
                            $trz->transactionCodeDesc = PRT_MANUAL_SETTLEMENT;
                        }
                    ],
                ],
            ]
        ];

        return $accountingRules;
    }
}

?>
