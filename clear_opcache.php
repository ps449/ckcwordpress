<?php
require_once('wp-load.php');
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache reset successfully.\n";
} else {
    echo "OPcache is not enabled or opcache_reset is disabled.\n";
}
wp_cache_flush();
echo "WP Object Cache flushed.\n";
