<?php

// Minimal WordPress stubs for unit testing.

if (!defined('DAY_IN_SECONDS')) {
  define('DAY_IN_SECONDS', 86400);
}
if (!defined('HOUR_IN_SECONDS')) {
  define('HOUR_IN_SECONDS', 3600);
}
if (!defined('MINUTE_IN_SECONDS')) {
  define('MINUTE_IN_SECONDS', 60);
}
if (!defined('ABSPATH')) {
  define('ABSPATH', __DIR__ . '/');
}

$GLOBALS['wp_options'] = [];
$GLOBALS['wp_filters'] = [];
$GLOBALS['wp_actions'] = [];
$GLOBALS['graphql_fields'] = [];
$GLOBALS['graphql_types'] = [];
$GLOBALS['wp_cron'] = [
  'scheduled' => [],
  'cleared' => [],
];

class WP_Error
{
  private $message;

  public function __construct($code = '', $message = '')
  {
    $this->message = (string) $message;
  }

  public function get_error_message()
  {
    return $this->message;
  }
}

function add_action($hook, $callback)
{
  if (!isset($GLOBALS['wp_actions'][$hook])) {
    $GLOBALS['wp_actions'][$hook] = [];
  }
  $GLOBALS['wp_actions'][$hook][] = $callback;
}

function do_action($hook)
{
  $args = func_get_args();
  if (empty($GLOBALS['wp_actions'][$hook])) {
    return;
  }
  foreach ($GLOBALS['wp_actions'][$hook] as $callback) {
    call_user_func_array($callback, array_slice($args, 1));
  }
}

function add_filter($hook, $callback)
{
  if (!isset($GLOBALS['wp_filters'][$hook])) {
    $GLOBALS['wp_filters'][$hook] = [];
  }
  $GLOBALS['wp_filters'][$hook][] = $callback;
}

function apply_filters($hook, $value)
{
  $args = func_get_args();
  if (empty($GLOBALS['wp_filters'][$hook])) {
    return $value;
  }
  foreach ($GLOBALS['wp_filters'][$hook] as $callback) {
    $args[1] = $value;
    $value = call_user_func_array($callback, array_slice($args, 1));
  }
  return $value;
}

function register_activation_hook($file, $callback)
{
  // No-op for tests.
}

function register_deactivation_hook($file, $callback)
{
  // No-op for tests.
}

function wp_next_scheduled($hook)
{
  return false;
}

function wp_schedule_event($timestamp, $recurrence, $hook)
{
  $GLOBALS['wp_cron']['scheduled'][] = [
    'timestamp' => $timestamp,
    'recurrence' => $recurrence,
    'hook' => $hook,
  ];
  return true;
}

function wp_unschedule_event($timestamp, $hook)
{
  return true;
}

function wp_clear_scheduled_hook($hook)
{
  $GLOBALS['wp_cron']['cleared'][] = $hook;
  return true;
}

function get_option($key, $default = false)
{
  if (array_key_exists($key, $GLOBALS['wp_options'])) {
    return $GLOBALS['wp_options'][$key];
  }
  return $default;
}

function update_option($key, $value, $autoload = null)
{
  $GLOBALS['wp_options'][$key] = $value;
  return true;
}

function delete_option($key)
{
  unset($GLOBALS['wp_options'][$key]);
  return true;
}

function sanitize_text_field($text)
{
  return trim((string) $text);
}

function esc_html__($text, $domain = null)
{
  return $text;
}

function __($text, $domain = null)
{
  return $text;
}

function _n($single, $plural, $number, $domain = null)
{
  return $number === 1 ? $single : $plural;
}

function date_i18n($format, $timestamp)
{
  return date($format, $timestamp);
}

function esc_html($text)
{
  return $text;
}

function esc_attr($text)
{
  return $text;
}

function esc_url_raw($text)
{
  return $text;
}

function submit_button()
{
  // No-op for tests.
}

function settings_fields($group)
{
  // No-op for tests.
}

function register_graphql_object_type($name, $config)
{
  $GLOBALS['graphql_types'][$name] = $config;
}

function register_graphql_field($type, $field_name, $config)
{
  if (!isset($GLOBALS['graphql_fields'][$type])) {
    $GLOBALS['graphql_fields'][$type] = [];
  }
  $GLOBALS['graphql_fields'][$type][$field_name] = $config;
}

function is_wp_error($thing)
{
  return $thing instanceof WP_Error;
}

function wp_remote_get($url, $args = [])
{
  return [
    'response' => ['code' => 200],
    'body' => '{}',
  ];
}

function wp_remote_retrieve_response_code($response)
{
  return isset($response['response']['code']) ? (int) $response['response']['code'] : 0;
}

function wp_remote_retrieve_body($response)
{
  return isset($response['body']) ? (string) $response['body'] : '';
}

function wp_json_encode($data)
{
  return json_encode($data);
}

function add_query_arg($args, $url)
{
  $query = http_build_query($args);
  if ($query === '') {
    return $url;
  }
  $separator = strpos($url, '?') !== false ? '&' : '?';
  return $url . $separator . $query;
}

function get_transient($key)
{
  return false;
}

function set_transient($key, $value, $expiration)
{
  return true;
}

require_once __DIR__ . '/../instagram-token-manager.php';

$GLOBALS['wp_actions_base'] = $GLOBALS['wp_actions'];
