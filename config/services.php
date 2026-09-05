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
    | WhatsApp Cloud API
    |--------------------------------------------------------------------------
    */
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
        'shipment_booked_template' => env('WHATSAPP_SHIPMENT_BOOKED_TEMPLATE', 'order_shipped'),
        'out_for_delivery_template' => env('WHATSAPP_OUT_FOR_DELIVERY_TEMPLATE', 'out_for_delivery'),
        'delivered_template' => env('WHATSAPP_DELIVERED_TEMPLATE', 'order_delivered'),
        'status_template_lang' => env('WHATSAPP_STATUS_TEMPLATE_LANG', 'en'),
    ],

];
