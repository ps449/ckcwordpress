<?php
/**
 * Minimal PSR-4 autoloader for the bundled ECPay SDK (Ecpay\Sdk\*).
 *
 * The official "ecpay-ecommerce-for-woocommerce" plugin (which normally
 * ships vendor/autoload.php for this SDK) is not installed on this site.
 * This file loads the same SDK classes directly from the copy bundled
 * inside this theme (includes/ecpay-sdk/src), so the ECPg gateway can
 * fetch a transaction Token without depending on that plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

spl_autoload_register(function ($class) {
    $prefix = 'Ecpay\\Sdk\\';
    $base_dir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
