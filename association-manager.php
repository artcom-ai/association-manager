<?php
/**
 * Plugin Name: Association Manager
 * Description: Flexible association and member management for WordPress.
 * Version: 0.1.0
 * Author: AIAS Project
 * Text Domain: association-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AM_PLUGIN_FILE', __FILE__);
define('AM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AM_PLUGIN_VERSION', '0.1.0');

$autoload = AM_PLUGIN_DIR . 'vendor/autoload.php';

if (!file_exists($autoload)) {
    return;
}

require_once $autoload;

AssociationManager\Core\Plugin::boot();