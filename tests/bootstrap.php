<?php

/**
 * PHPUnit bootstrap. There is no real WordPress installation available
 * for these tests - just enough of the WP function/class surface for
 * the plugin's own code to run against, plus AssociationManager\Tests\Support\FakeWpdb
 * standing in for $wpdb. This mirrors the ad-hoc smoke scripts used
 * throughout development (Sprints 9-13); this file is the permanent,
 * committed version of that same approach.
 */

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/Support/fakewp/');
define('AM_PLUGIN_DIR', __DIR__ . '/../');
define('AM_PLUGIN_URL', 'http://example.test/wp-content/plugins/association-manager/');
define('AM_PLUGIN_VERSION', 'test');
define('ARRAY_A', 'ARRAY_A');
define('DAY_IN_SECONDS', 86400);

require __DIR__ . '/../vendor/autoload.php';

// --- i18n / escaping (identity stubs - these tests exercise business
// logic, not WordPress's own escaping/translation behavior) ---
function __($text, $domain = 'default') { return $text; }
function _e($text, $domain = 'default') { echo $text; }
function esc_html__($text, $domain = 'default') { return $text; }
function esc_html_e($text, $domain = 'default') { echo $text; }
function esc_attr__($text, $domain = 'default') { return $text; }
function esc_attr_e($text, $domain = 'default') { echo $text; }
function esc_html($text) { return (string) $text; }
function esc_attr($text) { return (string) $text; }
function esc_url($text) { return (string) $text; }
function esc_textarea($text) { return (string) $text; }
function selected($a, $b) { return $a === $b ? ' selected' : ''; }
function checked($a, $b) { return $a === $b ? ' checked' : ''; }
function submit_button($text = '') { echo "<button>{$text}</button>"; }
function wp_kses_post($text) { return (string) $text; }
function dbDelta($sql) { /* no-op: FakeWpdb doesn't model real schema DDL */ }
function sanitize_text_field($value) { return trim((string) $value); }
function wp_json_encode($data) { return json_encode($data); }

// --- nonces / auth ---
function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true) { return ''; }
function wp_create_nonce($action = -1) { return 'test-nonce'; }
function check_admin_referer($action = -1, $name = '_wpnonce') { return true; }
function check_ajax_referer($action = -1, $name = false, $stop = true) { return true; }
function current_user_can($capability) { return $GLOBALS['__am_test_current_user_can'] ?? true; }
function is_user_logged_in() { return $GLOBALS['__am_test_logged_in'] ?? false; }
function get_current_user_id() { return $GLOBALS['__am_test_current_user_id'] ?? 0; }
function wp_die($message = '') { throw new \RuntimeException('wp_die: ' . (is_string($message) ? $message : 'error')); }

// --- URLs / redirects ---
function admin_url($path = '') { return 'http://example.test/wp-admin/' . ltrim((string) $path, '/'); }
function home_url($path = '') { return 'http://example.test/' . ltrim((string) $path, '/'); }
function add_query_arg($args, $url = '') { return $url . '?' . http_build_query((array) $args); }
function wp_safe_redirect($location, $status = 302) { $GLOBALS['__am_test_last_redirect'] = $location; }

// --- hooks ---
$GLOBALS['__am_test_fired_actions'] = [];
function add_action(...$args) {}
function add_filter(...$args) {}
function do_action($hook, ...$args) { $GLOBALS['__am_test_fired_actions'][] = ['hook' => $hook, 'args' => $args]; }
function apply_filters($hook, $value, ...$args) { return $value; }
function add_shortcode($tag, $callback) {}
function add_menu_page(...$args) {}
function add_submenu_page(...$args) {}
function remove_submenu_page(...$args) {}
function register_rest_route(...$args) {}
function is_singular() { return false; }
function has_shortcode(...$args) { return false; }

// --- cron ---
$GLOBALS['__am_test_scheduled_hooks'] = [];
function wp_next_scheduled($hook) { return $GLOBALS['__am_test_scheduled_hooks'][$hook] ?? false; }
function wp_schedule_event($timestamp, $recurrence, $hook) { $GLOBALS['__am_test_scheduled_hooks'][$hook] = $timestamp; }
function wp_clear_scheduled_hook($hook) { unset($GLOBALS['__am_test_scheduled_hooks'][$hook]); }

// --- assets ---
function wp_enqueue_script(...$args) {}
function wp_enqueue_style(...$args) {}
function wp_localize_script(...$args) {}

// --- transients ---
$GLOBALS['__am_test_transients'] = [];
function get_transient($key) { return $GLOBALS['__am_test_transients'][$key] ?? false; }
function set_transient($key, $value, $expiration = 0) { $GLOBALS['__am_test_transients'][$key] = $value; return true; }
function delete_transient($key) { unset($GLOBALS['__am_test_transients'][$key]); return true; }

// --- time ---
function current_time($type, $gmt = 0) { return $GLOBALS['__am_test_now'] ?? '2026-01-01 00:00:00'; }

// --- uuid ---
$GLOBALS['__am_test_uuid_counter'] = 0;
function wp_generate_uuid4() {
    $GLOBALS['__am_test_uuid_counter']++;
    return sprintf('00000000-0000-0000-0000-%012d', $GLOBALS['__am_test_uuid_counter']);
}

// --- media (file field uploads) - tests configure the return value ---
$GLOBALS['__am_test_media_upload_result'] = 1;
function media_handle_upload($fieldKey, $postId) {
    return $GLOBALS['__am_test_media_upload_result'];
}

// --- WP REST / error primitives ---
if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(
            public $code = '',
            public $message = '',
            public $data = []
        ) {
        }

        public function get_error_message()
        {
            return $this->message;
        }
    }
}

function is_wp_error($thing) { return $thing instanceof WP_Error; }

function wp_send_json_success($data = null) {
    echo json_encode(['success' => true, 'data' => $data]);
    throw new \AssociationManager\Tests\Support\JsonResponseSent();
}

function wp_send_json_error($data = null, $statusCode = null) {
    echo json_encode(['success' => false, 'data' => $data]);
    throw new \AssociationManager\Tests\Support\JsonResponseSent();
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        public function __construct(private array $params = [])
        {
        }

        public function get_param($key)
        {
            return $this->params[$key] ?? null;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        public function __construct(
            public $data,
            public $status = 200
        ) {
        }
    }
}
