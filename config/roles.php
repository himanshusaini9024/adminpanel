<?php

/**
 * Admin panel roles and module access.
 * Admin always has every module. Other roles only see/access listed modules.
 */
return [

    'labels' => [
        'admin' => 'Admin',
        'seo' => 'SEO',
        'marketing' => 'Marketing',
        'sales' => 'Sales',
        'accounts' => 'Accounts',
    ],

    // Roles that may log into /admin (not storefront "user")
    'staff' => ['admin', 'seo', 'marketing', 'sales', 'accounts'],

    /**
     * Modules shown on the Roles & Access screen (and used for permission checks).
     */
    'modules' => [
        'dashboard' => 'Dashboard',
        'file_manager' => 'File Manager',
        'users' => 'Users',
        'roles' => 'Roles & Access',
        'settings' => 'Settings',
        'customers' => 'Customers',
        'push' => 'Push Notifications',
        'whatsapp' => 'WhatsApp',
        'orders' => 'Orders & Invoices',
        'returns' => 'Returns',
        'shipping' => 'Shipping',
        'products' => 'Products',
        'merchant_feed' => 'Merchant Feed',
        'categories' => 'Shop Categories',
        'brands' => 'Brands',
        'banners' => 'Banners',
        'posts' => 'Posts / Blog',
        'reviews' => 'Reviews',
        'coupons' => 'Coupons',
        'profile' => 'Profile / Password',
    ],

    /**
     * Default modules each role can access (seeded into DB; Admin is always all).
     * After seeding, admins edit these from the dashboard.
     */
    'permissions' => [
        'seo' => [
            'dashboard', 'file_manager', 'banners', 'brands', 'categories',
            'products', 'merchant_feed', 'posts', 'reviews', 'profile',
        ],
        'marketing' => [
            'dashboard', 'file_manager', 'customers', 'push', 'whatsapp',
            'banners', 'brands', 'products', 'posts', 'reviews', 'coupons', 'profile',
        ],
        'sales' => [
            'dashboard', 'customers', 'orders', 'returns', 'shipping',
            'products', 'reviews', 'coupons', 'profile',
        ],
        'accounts' => [
            'dashboard', 'customers', 'orders', 'returns', 'coupons', 'profile',
        ],
    ],

    /**
     * Map route name prefixes / exact names → module key.
     * First matching prefix wins.
     */
    'route_modules' => [
        'admin' => 'dashboard',
        'file-manager' => 'file_manager',
        's3.' => 'file_manager',
        'users.' => 'users',
        'roles.' => 'roles',
        'customer.' => 'customers',
        'push.' => 'push',
        'whatsapp.' => 'whatsapp',
        'banner.' => 'banners',
        'brand.' => 'brands',
        'category.' => 'categories',
        'product.' => 'products',
        'product.bulk.' => 'products',
        'merchant-feed.' => 'merchant_feed',
        'order.' => 'orders',
        'manual-order.' => 'orders',
        'order.pdf' => 'orders',
        'shipping.' => 'shipping',
        'admin.returns' => 'returns',
        'returns.' => 'returns',
        'review.' => 'reviews',
        'post.' => 'posts',
        'post-category.' => 'posts',
        'post-tag.' => 'posts',
        'comment.' => 'posts',
        'coupon.' => 'coupons',
        'settings' => 'settings',
        'change.password' => 'profile',
        'admin-profile' => 'profile',
        'profile-update' => 'profile',
        'all.notification' => 'dashboard',
        'admin.notification' => 'dashboard',
        'notification.' => 'dashboard',
        'messages.' => 'dashboard',
        'message.' => 'dashboard',
    ],
];
