<?php

return [
    'company_name' => env('INVOICE_COMPANY_NAME', 'Dhirago'),
    'address' => env(
        'INVOICE_ADDRESS',
        '3rd floor, New electric market, 120 ft Road, A.M Business Center, Mali Colony, Central Area, Udaipur 313001'
    ),
    'city' => env('INVOICE_CITY', 'Udaipur'),
    'state' => env('INVOICE_STATE', 'Rajasthan'),
    'state_code' => env('INVOICE_STATE_CODE', '08'),
    'pincode' => env('INVOICE_PINCODE', '313001'),
    'country' => env('INVOICE_COUNTRY', 'India'),
    'phone' => env('INVOICE_PHONE', '8805524832'),
    'email' => env('INVOICE_EMAIL', 'contact@dhirago.com'),
    'gstin' => env('INVOICE_GSTIN', ''),
    'website' => env('INVOICE_WEBSITE', 'https://www.dhirago.com'),
    // Default HSN for shirts / apparel (can override per product later)
    'hsn' => env('INVOICE_HSN', '6205'),
    // GST % inclusive in selling price
    'gst_rate' => (float) env('INVOICE_GST_RATE', 5),
];
