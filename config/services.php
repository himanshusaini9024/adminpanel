<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'github' => [
        'client_id' => 'YOUR_GITHUB_API', //Github API
        'client_secret' => 'YOUR_GITHUB_SECRET', //Github Secret
        'redirect' => 'http://localhost:8000/login/github/callback',
     ],
     'google' => [
        'client_id' => 'YOUR_GOOGLE_API', //Google API
        'client_secret' => 'YOUR_GOOGLE_SECRET', //Google Secret
        'redirect' => 'http://localhost:8000/login/google/callback',
     ],
     'facebook' => [
        'client_id' => 'YOUR_FACEBOOK_API', //Facebook API
        'client_secret' => 'YOUR_FACEBOK_SECRET', //Facebook Secret
        'redirect' => 'http://localhost:8000/login/facebook/callback',
     ],

    /*
    |--------------------------------------------------------------------------
    | Native Web Push (VAPID)
    |--------------------------------------------------------------------------
    */
    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:contact@dhirago.com'),
        'storefront_url' => env('STOREFRONT_URL', 'https://dhirago.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Shiprocket
    |--------------------------------------------------------------------------
    | pickup_location = nickname from Shiprocket → Settings → Pickup Addresses
    | (same place you hand parcels to the delivery boy for outbound orders).
    */
    'shiprocket' => [
        'pickup_location' => env('SHIPROCKET_PICKUP_LOCATION', 'Home'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Razorpay
    |--------------------------------------------------------------------------
    | webhook_secret = from Razorpay Dashboard → Webhooks (order.paid, payment.captured)
    */
    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API
    |--------------------------------------------------------------------------
    */
    'meta' => [
        'pixel_id' => env('META_PIXEL_ID'),
        'access_token' => env('META_ACCESS_TOKEN'),
        // Local/LAN only — Events Manager → Test events. Never set on production.
        'test_event_code' => env('META_TEST_EVENT_CODE'),
        // Force overrides (optional): META_FORCE_NON_PRODUCTION=1 | META_FORCE_PRODUCTION=1
        'force_non_production' => (bool) env('META_FORCE_NON_PRODUCTION', false),
        'force_production' => (bool) env('META_FORCE_PRODUCTION', false),
        'graph_version' => env('META_GRAPH_VERSION', 'v23.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Merchant Center product feed
    |--------------------------------------------------------------------------
    | Public feed URLs Google fetches on a schedule. Protect with a secret token.
    */
    'merchant' => [
        'feed_token' => env('MERCHANT_FEED_TOKEN'),
        'storefront_url' => rtrim(env('STOREFRONT_URL', 'https://dhirago.com'), '/'),
        'brand' => env('MERCHANT_BRAND', 'Dhirago'),
        'currency' => env('MERCHANT_CURRENCY', 'INR'),
        'country' => env('MERCHANT_COUNTRY', 'IN'),
        'language' => env('MERCHANT_LANGUAGE', 'en'),
        'gender' => env('MERCHANT_GENDER', 'male'),
        'age_group' => env('MERCHANT_AGE_GROUP', 'adult'),
        // Apparel & Accessories > Clothing > Shirts & Tops
        'google_product_category' => env('MERCHANT_GOOGLE_PRODUCT_CATEGORY', '212'),
        'product_type' => env('MERCHANT_PRODUCT_TYPE', "Apparel & Accessories > Clothing > Shirts & Tops"),
    ],

    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_id' => env('WHATSAPP_PHONE_ID'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', env('WHATSAPP_TOKEN')),
        'api_version' => env('WHATSAPP_API_VERSION', 'v22.0'),
        'reminder_template' => env('WHATSAPP_REMINDER_TEMPLATE', 'dhirago_customer_offer'),
        'reminder_template_lang' => env('WHATSAPP_REMINDER_TEMPLATE_LANG', 'en'),
        // Meta header images: prefer public JPEG/PNG (WebP often fails delivery)
        'reminder_template_image' => env(
            'WHATSAPP_REMINDER_TEMPLATE_IMAGE',
            'https://images.dhirago.com/ecommerce/logo/logo.jpg'
        ),
        // Abandoned-cart crawler (runs 21:00 IST): only carts updated within this many hours
        'cart_reminder_within_hours' => (int) env('WHATSAPP_CART_REMINDER_WITHIN_HOURS', 24),
        'cart_reminder_message' => env(
            'WHATSAPP_CART_REMINDER_MESSAGE',
            'You left something lovely in your cart. Complete your order on dhirago.com before it sells out.'
        ),

        'shipment_booked_template' => env('WHATSAPP_SHIPMENT_BOOKED_TEMPLATE', 'order_shipped'),
        'out_for_delivery_template' => env('WHATSAPP_OUT_FOR_DELIVERY_TEMPLATE', 'out_for_delivery'),
        'delivered_template' => env('WHATSAPP_DELIVERED_TEMPLATE', 'order_delivered'),
        'status_template_lang' => env('WHATSAPP_STATUS_TEMPLATE_LANG', 'en'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast2SMS (DLT OTP)
    |--------------------------------------------------------------------------
    | Must live in config so values work when `php artisan config:cache` is used.
    */
    'fast2sms' => [
        'api_key' => env('FAST2SMS_API_KEY', ''),
        'sender_id' => env('FAST2SMS_SENDER_ID', 'DHRAGO'),
        'entity_id' => env('FAST2SMS_ENTITY_ID', ''),
        'template_id' => env('FAST2SMS_OTP_TEMPLATE_ID', ''),
        'route' => env('FAST2SMS_ROUTE', 'dlt'),
        'template_text' => env(
            'FAST2SMS_OTP_TEMPLATE_TEXT',
            'Your OTP for login to DHIRAGO FASHION is {#num#}. This OTP is valid for 10 minutes. Do not share this OTP with anyone. -DHIRAGO FASHION PRIVATE LIMITED'
        ),
    ],

];
