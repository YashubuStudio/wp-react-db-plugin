<?php
// Run with PHP 7.4+; storage and WP permission boundaries are controlled fixtures.
class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct($code, $message, $data = []) { $this->code = $code; $this->message = $message; $this->data = $data; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function maybe_serialize($value) { return is_array($value) ? serialize($value) : $value; }
function maybe_unserialize($value) { return @unserialize($value); }
function wp_cache_delete($key, $group) { $GLOBALS['cache_deletes'][] = [$key, $group]; }
function current_user_can($capability) { return $capability !== 'unfiltered_html' || $GLOBALS['unfiltered']; }
function wp_kses_post($html) { $GLOBALS['kses_calls']++; return strip_tags($html, '<div><p><a>'); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)); }
function sanitize_text_field($value) { return is_scalar($value) ? strip_tags((string) $value) : ''; }
function get_option($key, $default = false) { return $GLOBALS['wpdb']->raw === null ? $default : maybe_unserialize($GLOBALS['wpdb']->raw); }
function add_option($key, $value, $deprecated = '', $autoload = null) {
    $db = $GLOBALS['wpdb'];
    if ($db->failWrite) { $db->last_error = 'write denied'; return false; }
    if ($db->raw !== null) return false;
    $db->raw = maybe_serialize($value);
    return true;
}
class FixtureDB {
    public $prefix = 'wp_';
    public $options = 'wp_options';
    public $last_error = '';
    public $raw = null;
    public $failWrite = false;
    public $failRead = false;
    public $failTables = false;
    public $raceOnWrite = null;
    public $tables = ['wp_reactdb_papers' => true];
    public function prepare($sql, ...$args) { return ['sql' => $sql, 'args' => $args]; }
    public function esc_like($value) { return addcslashes($value, '_%\\'); }
    public function get_var($query) {
        $this->last_error = '';
        if (strpos($query['sql'], 'SELECT option_value') === 0) {
            if ($this->failRead) { $this->last_error = 'read denied'; return null; }
            return $this->raw;
        }
        if ($this->failTables) { $this->last_error = 'table query denied'; return null; }
        $table = stripslashes($query['args'][0]);
        return isset($this->tables[$table]) ? $table : null;
    }
    public function query($query) {
        $this->last_error = '';
        if ($this->failWrite) { $this->last_error = 'write denied'; return false; }
        if ($this->raceOnWrite !== null) { $this->raw = $this->raceOnWrite; $this->raceOnWrite = null; }
        if ($this->raw !== $query['args'][2]) return 0;
        $this->raw = $query['args'][0];
        return 1;
    }
}
define('ABSPATH', __DIR__);
require __DIR__ . '/../../react-db-plugin/includes/output-handler.php';
require __DIR__ . '/../../react-db-plugin/includes/cleanup.php';
function reset_fixture($settings = null) {
    $GLOBALS['wpdb'] = new FixtureDB();
    $GLOBALS['wpdb']->raw = $settings === null ? null : serialize($settings);
    $GLOBALS['unfiltered'] = true;
    $GLOBALS['kses_calls'] = 0;
    $GLOBALS['cache_deletes'] = [];
}
function check($condition, $message) { if (!$condition) throw new Exception($message); }
function expect_error($result, $code) { check(is_wp_error($result) && $result->code === $code, 'Expected ' . $code); }
function config($html = '') { return ['table' => 'papers', 'format' => 'html', 'html' => $html, 'css' => '', 'filterCss' => '']; }
$tests = [];
$tests['code round trip preserves placeholders, attributes, quotes, slashes and CRLF'] = function () {
    reset_fixture();
    $conf = config("<details data-id=\"{{id}}\"><summary><a href=\"{{world_url}}\">{{title}}</a></summary>\r\n<svg viewBox=\"0 0 10 10\"></svg></details>");
    $conf['css'] = '.x::after { content: "<会場> \\2713"; }';
    $saved = OutputHandler::mutate_task('create', 'papers', $conf, null, false);
    check(!is_wp_error($saved), 'Create failed');
    foreach (['html', 'css', 'filterCss'] as $field) check($saved['papers'][$field] === $conf[$field], $field . ' changed');
    check($GLOBALS['kses_calls'] === 0, 'Privileged code must not be stripped');
    check(OutputHandler::get_task('papers')['html'] === $conf['html'], 'Reload changed code');
};
$tests['duplicate create cannot erase existing code'] = function () {
    $old = config('<p>keep</p>'); reset_fixture(['papers' => $old]);
    expect_error(OutputHandler::mutate_task('create', 'papers', config(''), null, false), 'output_task_exists');
    check(OutputHandler::get_task('papers') === $old, 'Duplicate overwrote code');
};
$tests['saving a task preserves tasks added since the editor loaded'] = function () {
    $old = config('old'); $other = config('other'); reset_fixture(['papers' => $old, 'new_task' => $other]);
    $result = OutputHandler::mutate_task('save', 'papers', config('new'), $old, true);
    check(!is_wp_error($result) && $result['new_task'] === $other && $result['papers']['html'] === 'new', 'Unrelated task lost');
};
$tests['stale same-task save and delete return conflict'] = function () {
    $old = config('old'); reset_fixture(['papers' => config('latest')]);
    expect_error(OutputHandler::mutate_task('save', 'papers', config('mine'), $old, true), 'output_settings_conflict');
    expect_error(OutputHandler::mutate_task('delete', 'papers', null, $old, true), 'output_settings_conflict');
    check(OutputHandler::get_task('papers')['html'] === 'latest', 'Stale client overwrote code');
};
$tests['write failure is reported without changing stored code'] = function () {
    $old = config('old'); reset_fixture(['papers' => $old]); $GLOBALS['wpdb']->failWrite = true;
    expect_error(OutputHandler::mutate_task('save', 'papers', config('mine'), $old, true), 'output_settings_write_failed');
    check(OutputHandler::get_task('papers') === $old, 'Write failure changed data');
};
$tests['first-write failure is reported'] = function () {
    reset_fixture(); $GLOBALS['wpdb']->failWrite = true;
    expect_error(OutputHandler::mutate_task('create', 'papers', config('mine'), null, false), 'output_settings_write_failed');
};
$tests['database race cannot overwrite newer code including case-only changes'] = function () {
    $old = config('Old'); reset_fixture(['papers' => $old]);
    $GLOBALS['wpdb']->raceOnWrite = serialize(['papers' => config('OLD')]);
    expect_error(OutputHandler::mutate_task('save', 'papers', config('mine'), $old, true), 'output_settings_conflict');
    check(OutputHandler::get_task('papers')['html'] === 'OLD', 'Concurrent update overwritten');
};
$tests['unchanged save succeeds and intentional empty code stays empty'] = function () {
    reset_fixture();
    $saved = OutputHandler::mutate_task('create', 'papers', config(''), null, false);
    check(!is_wp_error($saved), 'Create failed');
    $again = OutputHandler::mutate_task('save', 'papers', $saved['papers'], $saved['papers'], true);
    check(!is_wp_error($again) && $again['papers']['filterCss'] === '' && $again['papers']['html'] === '', 'Empty code restored');
};
$tests['restricted HTML returns explicit permission error without altering storage'] = function () {
    reset_fixture(); $GLOBALS['unfiltered'] = false;
    expect_error(OutputHandler::mutate_task('create', 'papers', config('<script>alert(1)</script>'), null, false), 'reactdb_html_permission');
    check($GLOBALS['wpdb']->raw === null, 'Disallowed HTML was stored');
};
$tests['restricted admin can keep an existing template when changing metadata'] = function () {
    $old = config('<script>alert(1)</script>'); reset_fixture(['papers' => $old]); $GLOBALS['unfiltered'] = false;
    $next = $old; $next['format'] = 'json';
    check(!is_wp_error(OutputHandler::mutate_task('save', 'papers', $next, $old, true)), 'Unchanged existing template blocked');
};
$tests['CSS style-tag breakout returns validation error'] = function () {
    reset_fixture(); $next = config(''); $next['css'] = '</style><script>alert(1)</script>';
    expect_error(OutputHandler::mutate_task('create', 'papers', $next, null, false), 'invalid_css');
};
$tests['missing output table blocks save'] = function () {
    reset_fixture(); $next = config(''); $next['table'] = 'missing';
    expect_error(OutputHandler::mutate_task('create', 'papers', $next, null, false), 'output_table_missing');
};
$tests['malformed stored settings cannot be overwritten'] = function () {
    reset_fixture(); $GLOBALS['wpdb']->raw = 'broken';
    expect_error(OutputHandler::mutate_task('create', 'papers', config(''), null, false), 'invalid_stored_settings');
};
$tests['database read failure blocks changes'] = function () {
    reset_fixture(); $GLOBALS['wpdb']->failRead = true;
    expect_error(OutputHandler::mutate_task('create', 'papers', config(''), null, false), 'output_settings_read_failed');
};
$tests['orphan cleanup uses compare-and-swap and preserves live tasks'] = function () {
    $live = config('keep'); $missing = config('gone'); $missing['table'] = 'missing'; reset_fixture(['papers' => $live, 'orphan' => $missing]);
    $saved = reactdb_prune_orphaned_output_settings();
    check(!is_wp_error($saved) && $saved === ['papers' => $live], 'Cleanup changed live settings');
};
$tests['cleanup database failure never deletes settings'] = function () {
    $live = config('keep'); reset_fixture(['papers' => $live]); $GLOBALS['wpdb']->failTables = true;
    expect_error(reactdb_prune_orphaned_output_settings(), 'output_table_read_failed');
    check(OutputHandler::get_task('papers') === $live, 'Cleanup discarded live code');
};
$tests['deleting a task removes only the requested task'] = function () {
    $old = config('old'); $other = config('other'); reset_fixture(['papers' => $old, 'other' => $other]);
    $saved = OutputHandler::mutate_task('delete', 'papers', null, $old, true);
    check(!is_wp_error($saved) && $saved === ['other' => $other], 'Delete modified other task');
};
$tests['legacy bulk writes are rejected rather than resetting all tasks'] = function () {
    $old = config('keep'); reset_fixture(['papers' => $old]);
    expect_error(OutputHandler::mutate_task(null, null, null, null, false), 'output_editor_outdated');
    check(OutputHandler::get_task('papers') === $old, 'Legacy write changed data');
};
$tests['partial metadata updates preserve existing code'] = function () {
    $old = config('<p>keep code</p>'); reset_fixture(['papers' => $old]);
    $saved = OutputHandler::mutate_task('save', 'papers', ['format' => 'json'], $old, true);
    check(!is_wp_error($saved) && $saved['papers']['html'] === $old['html'], 'Partial update erased code');
};
$tests['rendering escapes row data without recursively replacing placeholders'] = function () {
    $rendered = OutputHandler::render_template('<p>{{title}}</p><div>{{next}}</div>', ['title' => '<img onerror="bad"> $& {{next}}', 'next' => '最後']);
    check(strpos($rendered, '&lt;img onerror=&quot;bad&quot;&gt; $&amp; {{next}}</p>') !== false, 'Row data became markup or was recursively substituted');
    check(strpos($rendered, '<div>最後</div>') !== false, 'Normal substitution failed');
};
$tests['numeric task names still serialize as an object at the API boundary'] = function () {
    reset_fixture();
    $saved = OutputHandler::mutate_task('create', '0', config('<p>zero</p>'), null, false);
    check(!is_wp_error($saved) && substr(json_encode((object) $saved), 0, 5) === '{"0":', 'Numeric task name became a JSON list');
};
foreach ($tests as $name => $test) {
    $test();
    echo "PASS: $name\n";
}
echo count($tests) . " PHP regression checks passed.\n";
