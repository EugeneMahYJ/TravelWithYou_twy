<?php

return [
    // OWNER TO CONFIRM LEGAL TAX RATE
    'tax_rate' => (float) env('TRAVEL_TAX_RATE', 0.07),
    'stack_promo_on_deals' => (bool) env('TRAVEL_STACK_PROMO_ON_DEALS', true),
];
