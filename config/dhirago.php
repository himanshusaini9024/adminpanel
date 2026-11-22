<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cash on Delivery fee (INR)
    |--------------------------------------------------------------------------
    | Stored in orders.cod_charge and added to total_amount for COD orders.
    */
    'cod_charge' => (float) env('COD_CHARGE', 200),
];
