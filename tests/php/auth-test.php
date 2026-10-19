<?php
class WP_Error {
    public $code, $message, $data;
    public function __construct($code, $message, $data = []) { $this->code = $code; $this->message = $message; $this->data = $data; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function add_action($hook, $callback) {}
function wp_get_current_user() { return $GLOBALS['user']; }
function is_user_logged_in() { return $GLOBALS['user']->ID > 0; }
function current_user_can($capability) { return $capability === 'manage_options' ? $GLOBALS['can_manage'] : false; }
function get_role($role) { return isset($GLOBALS['role_caps'][$role]) ? (object) ['capabilities' => $GLOBALS['role_caps'][$role]] : null; }
function get_current_blog_id() { return 2; }
function home_url($path) { return 'https://example.test/site2' . $path; }
define('ABSPATH', __DIR__);
require __DIR__ . '/../../react-db-plugin/includes/auth.php';
require __DIR__ . '/../../react-db-plugin/includes/api.php';
function reset_auth() {
    $GLOBALS['user'] = (object) ['ID' => 4, 'user_login' => 'admin', 'roles' => ['administrator'], 'caps' => ['administrator' => true], 'allcaps' => ['manage_options' => true]];
    $GLOBALS['role_caps'] = ['administrator' => ['manage_options' => true]];
    $GLOBALS['can_manage'] = true;
}
function check($condition, $message) { if (!$condition) throw new Exception($message); }
$tests = [];
$tests['authenticated administrator with effective capability is allowed'] = function () {
    reset_auth(); check(reactdb_admin_permission() === true, 'Authorized admin rejected');
};
$tests['administrator role label cannot bypass a missing capability'] = function () {
    reset_auth(); $GLOBALS['can_manage'] = false; $GLOBALS['role_caps']['administrator'] = []; $GLOBALS['user']->allcaps = [];
    $error = reactdb_admin_permission();
    check(is_wp_error($error) && $error->code === 'reactdb_forbidden', 'Role label bypassed permission');
    check($error->data['auth']['reason'] === 'administrator_role_incomplete', 'Incomplete role not identified');
    check(strpos($error->message, '欠落') !== false, 'Missing role grant not explained');
};
$tests['explicit per-user denial remains enforced'] = function () {
    reset_auth(); $GLOBALS['can_manage'] = false; $GLOBALS['user']->caps['manage_options'] = false;
    check(reactdb_admin_permission()->data['auth']['reason'] === 'user_capability_denied', 'Explicit denial ignored');
};
$tests['filtered capability is distinguished from an incomplete role'] = function () {
    reset_auth(); $GLOBALS['can_manage'] = false;
    check(reactdb_admin_permission()->data['auth']['reason'] === 'capability_filtered', 'Filtered grant not diagnosed');
};
$tests['a logged-in subscriber can inspect only their own auth state and cannot manage data'] = function () {
    reset_auth(); $GLOBALS['can_manage'] = false; $GLOBALS['user']->roles = ['subscriber']; $GLOBALS['user']->allcaps = [];
    check(reactdb_authenticated_permission() === true, 'Own session denied');
    check(is_wp_error(reactdb_admin_permission()), 'Subscriber gained admin access');
    $state = reactdb_current_auth_state();
    check($state['siteId'] === 2 && $state['userId'] === 4 && $state['reason'] === 'capability_missing', 'Wrong site/user');
    check(!isset($state['nonce']) && !isset($state['password']), 'Unexpected credential exposure');
};
$tests['anonymous users cannot inspect the auth endpoint or manage data'] = function () {
    reset_auth(); $GLOBALS['user']->ID = 0;
    check(reactdb_authenticated_permission()->data['status'] === 401, 'Anonymous diagnostics allowed');
    check(reactdb_admin_permission()->data['status'] === 401, 'Anonymous management allowed');
};
foreach ($tests as $name => $test) { $test(); echo "PASS: $name\n"; }
echo count($tests) . " PHP auth regression checks passed.\n";
