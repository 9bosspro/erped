<?php

declare(strict_types=1);

if (! defined('SLAVE_VERSION')) {
    define('SLAVE_VERSION', '7.5.2');
}

if (! defined('SLAVE_PACKAGE_NAME')) {
    define('SLAVE_PACKAGE_NAME', 'ampol-slave');
}

if (! defined('SLAVE_PRODUCT_ID')) {
    define('SLAVE_PRODUCT_ID', '4B690EFA');
}

/** Webhook signature header ที่ Master ส่งมา */
if (! defined('SLAVE_WEBHOOK_SIGNATURE_HEADER')) {
    define('SLAVE_WEBHOOK_SIGNATURE_HEADER', 'X-Slave-Signature');
}

/** Algorithm ที่ใช้คำนวณ HMAC signature */
if (! defined('SLAVE_HMAC_ALGO')) {
    define('SLAVE_HMAC_ALGO', 'sha256');
}
