<?php
/**
 * Plugin Name: Association Manager
 * Description: Flexible association and member management for WordPress.
 * Version: 0.3.0-alpha.1
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: AIAS Project
 * Text Domain: association-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AM_PLUGIN_FILE', __FILE__ );
define( 'AM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AM_PLUGIN_VERSION', '0.3.0-alpha.1' );

$autoload = AM_PLUGIN_DIR . 'vendor/autoload.php';

if ( ! file_exists( $autoload ) ) {
    return;
}

require_once $autoload;

register_activation_hook( AM_PLUGIN_FILE, [ AssociationManager\Core\Activator::class, 'activate' ] );
register_deactivation_hook( AM_PLUGIN_FILE, [ AssociationManager\Core\Deactivator::class, 'deactivate' ] );

AssociationManager\Core\Plugin::boot();
