<?php

return [
    'methods' => [
        'visa' => (bool) env('PAYMENT_VISA_ENABLED', false),
        'mc' => (bool) env('PAYMENT_MC_ENABLED', false),
        'amex' => (bool) env('PAYMENT_AMEX_ENABLED', false),
        'paypal' => (bool) env('PAYMENT_PAYPAL_ENABLED', false),
        'bank' => (bool) env('PAYMENT_BANK_ENABLED', false),
    ],
];
