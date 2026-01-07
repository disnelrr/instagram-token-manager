<?php

/**
 * @file
 * Plugin Name:       Instagram Token Manager
 * Plugin URI:        https://github.com/disnelrr/instagram-token-manager
 * Description:       Handles automatic updates to your Instagram app token so it never gets expired.
 * Version:           0.0.2
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Disnel Rodríguez
 * Author URI:        https://www.drr.nat.cu
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/disnelrr/instagram-token-manager
 * Text Domain:       instagram-token-manager
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
  exit;
}

define('ITM_OPTION_TOKEN', 'instagram_token');
define('ITM_OPTION_LAST_UPDATE', 'instagram_token_last_update');
define('ITM_OPTION_LAST_ERROR', 'instagram_token_last_error');
define('ITM_OPTION_EXPIRES_AT', 'instagram_token_expires_at');
define('ITM_OPTION_LAST_ATTEMPT', 'instagram_token_last_refresh_attempt');
define('ITM_OPTION_FAIL_COUNT', 'instagram_token_refresh_failures');
define('ITM_CRON_HOOK', 'instagram_token_manager_cron_hook');

/**
 * Admin menu.
 */
add_action('admin_menu', 'instagram_token_manager_menu');

function instagram_token_manager_menu()
{
  add_options_page(
    __('Instagram Token Manager', 'instagram-token-manager'),
    __('Instagram Token Manager', 'instagram-token-manager'),
    'manage_options',
    'instagram-token-manager',
    'instagram_token_manager_page'
  );
}

/**
 * Admin page.
 */
function instagram_token_manager_page()
{
  if (!current_user_can('manage_options')) {
    return;
  }

  $token = get_option(ITM_OPTION_TOKEN, '');
  $last_updated = (int) get_option(ITM_OPTION_LAST_UPDATE, 0);
  $last_error = get_option(ITM_OPTION_LAST_ERROR, '');
  $expires_at = (int) get_option(ITM_OPTION_EXPIRES_AT, 0);
  $last_attempt = (int) get_option(ITM_OPTION_LAST_ATTEMPT, 0);
  $fail_count = (int) get_option(ITM_OPTION_FAIL_COUNT, 0);
  $now = time();

?>
  <div class="wrap">
    <h1><?php echo esc_html__('Instagram Token Manager', 'instagram-token-manager'); ?></h1>

    <?php if (!empty($last_error)) : ?>
      <div class="notice notice-warning">
        <p><strong><?php echo esc_html__('Last refresh error:', 'instagram-token-manager'); ?></strong> <?php echo esc_html($last_error); ?></p>
      </div>
    <?php endif; ?>

    <p>
      <strong><?php echo esc_html__('Last updated:', 'instagram-token-manager'); ?></strong>
      <?php echo $last_updated ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_updated)) : esc_html__('Never', 'instagram-token-manager'); ?>
    </p>
    <p>
      <strong><?php echo esc_html__('Expires at:', 'instagram-token-manager'); ?></strong>
      <?php
      if ($expires_at > 0) {
        $remaining = $expires_at - $now;
        $days = (int) floor(abs($remaining) / DAY_IN_SECONDS);
        $human = $remaining >= 0
          ? sprintf(_n('%d day remaining', '%d days remaining', $days, 'instagram-token-manager'), $days)
          : sprintf(_n('%d day ago', '%d days ago', $days, 'instagram-token-manager'), $days);
        echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $expires_at)) . ' (' . esc_html($human) . ')';
      } else {
        echo esc_html__('Unknown', 'instagram-token-manager');
      }
      ?>
    </p>
    <p>
      <strong><?php echo esc_html__('Last refresh attempt:', 'instagram-token-manager'); ?></strong>
      <?php echo $last_attempt ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_attempt)) : esc_html__('Never', 'instagram-token-manager'); ?>
    </p>
    <p>
      <strong><?php echo esc_html__('Consecutive refresh failures:', 'instagram-token-manager'); ?></strong>
      <?php echo esc_html((string) $fail_count); ?>
    </p>

    <form method="post" action="options.php">
      <?php
      settings_fields('instagram-token-manager');
      ?>
      <table class="form-table">
        <tr valign="top">
          <th scope="row"><?php echo esc_html__('Instagram Token', 'instagram-token-manager'); ?></th>
          <td>
            <input
              type="password"
              name="<?php echo esc_attr(ITM_OPTION_TOKEN); ?>"
              value="<?php echo esc_attr($token); ?>"
              size="60"
              autocomplete="off" />
            <p class="description">
              <?php echo esc_html__('Stored securely in the WordPress database. Token is hidden in the UI.', 'instagram-token-manager'); ?>
            </p>
          </td>
        </tr>
      </table>
      <?php submit_button(); ?>
    </form>
  </div>
<?php
}

/**
 * Register settings + sanitize callback.
 */
add_action('admin_init', 'instagram_token_storage_settings');

function instagram_token_storage_settings()
{
  register_setting('instagram-token-manager', ITM_OPTION_TOKEN, [
    'type'              => 'string',
    'sanitize_callback' => 'instagram_token_manager_sanitize_token',
    'default'           => '',
  ]);
}

/**
 * Sanitize/validate token and reset last_update when token changes.
 */
function instagram_token_manager_sanitize_token($new_value)
{
  $new_value = is_string($new_value) ? trim($new_value) : '';
  $new_value = sanitize_text_field($new_value);

  $old_value = (string) get_option(ITM_OPTION_TOKEN, '');
  $now = instagram_token_manager_now();

  if ($new_value !== '') {
    update_option(ITM_OPTION_TOKEN, $new_value, 'no');
  }

  // If token changed, reset last update and clear last error.
  if ($new_value !== '' && $old_value !== $new_value) {
    update_option(ITM_OPTION_LAST_UPDATE, $now, 'no');
    delete_option(ITM_OPTION_EXPIRES_AT);
    update_option(ITM_OPTION_FAIL_COUNT, 0, 'no');
    delete_option(ITM_OPTION_LAST_ATTEMPT);
    delete_option(ITM_OPTION_LAST_ERROR);
  }

  // If token cleared, clear metadata too.
  if ($new_value === '') {
    delete_option(ITM_OPTION_LAST_UPDATE);
    delete_option(ITM_OPTION_EXPIRES_AT);
    delete_option(ITM_OPTION_FAIL_COUNT);
    delete_option(ITM_OPTION_LAST_ATTEMPT);
    delete_option(ITM_OPTION_LAST_ERROR);
  }

  return $new_value;
}

/**
 * Cron schedule (daily).
 */
add_filter('cron_schedules', 'instagram_token_manager_add_day_cron_interval');

function instagram_token_manager_add_day_cron_interval($schedules)
{
  $schedules['every_day'] = [
    'interval' => DAY_IN_SECONDS,
    'display'  => esc_html__('Every Day', 'instagram-token-manager'),
  ];
  return $schedules;
}

/**
 * Activation/deactivation hooks to manage cron lifecycle.
 */
register_activation_hook(__FILE__, 'instagram_token_manager_activate');
register_deactivation_hook(__FILE__, 'instagram_token_manager_deactivate');

function instagram_token_manager_activate()
{
  wp_clear_scheduled_hook(ITM_CRON_HOOK);
  wp_schedule_event(time() + 60, 'every_day', ITM_CRON_HOOK);
}

function instagram_token_manager_deactivate()
{
  wp_clear_scheduled_hook(ITM_CRON_HOOK);
}

/**
 * Cron executor.
 */
add_action(ITM_CRON_HOOK, 'instagram_token_manager_cron_exec');

function instagram_token_manager_cron_exec()
{
  instagram_token_manager_refresh_token_if_needed(false);
}

/**
 * Refresh the token when needed (or forced) and persist metadata.
 */
function instagram_token_manager_refresh_token_if_needed($force = false)
{
  $token = (string) get_option(ITM_OPTION_TOKEN, '');
  if ($token === '') {
    return false;
  }

  $current_time = instagram_token_manager_now();
  $expires_at = (int) get_option(ITM_OPTION_EXPIRES_AT, 0);
  $fail_count = (int) get_option(ITM_OPTION_FAIL_COUNT, 0);
  $last_attempt = (int) get_option(ITM_OPTION_LAST_ATTEMPT, 0);
  $renewal_window = 10 * DAY_IN_SECONDS;

  if ($fail_count > 0) {
    $backoff_schedule = [
      1 => HOUR_IN_SECONDS,
      2 => 3 * HOUR_IN_SECONDS,
      3 => 12 * HOUR_IN_SECONDS,
    ];
    $backoff = isset($backoff_schedule[$fail_count]) ? $backoff_schedule[$fail_count] : DAY_IN_SECONDS;
    if ($last_attempt && ($current_time - $last_attempt) < $backoff) {
      return false;
    }
  }

  if (!$force) {
    $needs_refresh = false;

    if ($expires_at <= 0) {
      $needs_refresh = true;
    } elseif ($current_time >= $expires_at) {
      $needs_refresh = true;
    } elseif ($current_time >= ($expires_at - $renewal_window)) {
      $needs_refresh = true;
    }

    if (!$needs_refresh) {
      return false;
    }
  }

  $url = add_query_arg([
    'grant_type'    => 'ig_refresh_token',
    'access_token'  => $token,
  ], 'https://graph.instagram.com/refresh_access_token');

  update_option(ITM_OPTION_LAST_ATTEMPT, $current_time, 'no');

  $response = instagram_token_manager_http_get($url, [
    'timeout'     => 15,
    'redirection' => 3,
    'headers'     => [
      'Accept' => 'application/json',
    ],
  ]);

  if (is_wp_error($response)) {
    instagram_token_manager_record_error('WP HTTP error: ' . $response->get_error_message());
    update_option(ITM_OPTION_FAIL_COUNT, $fail_count + 1, 'no');
    return $response;
  }

  $status = (int) wp_remote_retrieve_response_code($response);
  $body   = (string) wp_remote_retrieve_body($response);

  if ($status < 200 || $status >= 300) {
    instagram_token_manager_record_error(sprintf('HTTP %d from Instagram refresh endpoint. Body: %s', $status, instagram_token_manager_truncate($body)));
    update_option(ITM_OPTION_FAIL_COUNT, $fail_count + 1, 'no');
    return new WP_Error('instagram_token_http', 'Non-2xx response from refresh endpoint.');
  }

  $data = json_decode($body, true);
  if (!is_array($data)) {
    instagram_token_manager_record_error('Invalid JSON response from Instagram refresh endpoint.');
    update_option(ITM_OPTION_FAIL_COUNT, $fail_count + 1, 'no');
    return new WP_Error('instagram_token_json', 'Invalid JSON response from refresh endpoint.');
  }

  if (!empty($data['access_token']) && is_string($data['access_token'])) {
    update_option(ITM_OPTION_TOKEN, sanitize_text_field($data['access_token']), 'no');
    update_option(ITM_OPTION_LAST_UPDATE, $current_time, 'no');
    update_option(ITM_OPTION_FAIL_COUNT, 0, 'no');
    if (!empty($data['expires_in']) && is_numeric($data['expires_in'])) {
      $expires_in = (int) $data['expires_in'];
      if ($expires_in >= DAY_IN_SECONDS && $expires_in <= (120 * DAY_IN_SECONDS)) {
        update_option(ITM_OPTION_EXPIRES_AT, $current_time + $expires_in, 'no');
        delete_option(ITM_OPTION_LAST_ERROR);
      } else {
        update_option(ITM_OPTION_EXPIRES_AT, 0, 'no');
        instagram_token_manager_record_error('Instagram response had invalid expires_in; will retry to populate expiry.');
      }
    } else {
      update_option(ITM_OPTION_EXPIRES_AT, 0, 'no');
      instagram_token_manager_record_error('Instagram response missing expires_in; will retry to populate expiry.');
    }
    return true;
  }

  // Instagram may return an "error" object; record something useful.
  if (!empty($data['error'])) {
    $err = $data['error'];
    if (is_array($err)) {
      $msg = '';
      if (!empty($err['message'])) {
        $msg .= $err['message'];
      }
      if (!empty($err['type'])) {
        $msg .= ($msg ? ' | ' : '') . 'type=' . $err['type'];
      }
      if (!empty($err['code'])) {
        $msg .= ($msg ? ' | ' : '') . 'code=' . $err['code'];
      }
      instagram_token_manager_record_error('Instagram API error: ' . $msg);
    } else {
      instagram_token_manager_record_error('Instagram API error: ' . instagram_token_manager_truncate(wp_json_encode($err)));
    }
    update_option(ITM_OPTION_FAIL_COUNT, $fail_count + 1, 'no');
    return new WP_Error('instagram_token_api', 'Instagram API error during refresh.');
  }

  instagram_token_manager_record_error('Instagram response did not include access_token.');
  update_option(ITM_OPTION_FAIL_COUNT, $fail_count + 1, 'no');
  return new WP_Error('instagram_token_missing_access_token', 'Missing access_token in refresh response.');
}

/**
 * Record an error for admins and optionally log when WP_DEBUG is enabled.
 */
function instagram_token_manager_record_error($message)
{
  $message = is_string($message) ? trim($message) : 'Unknown error';
  update_option(ITM_OPTION_LAST_ERROR, $message, 'no');

  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[Instagram Token Manager] ' . $message);
  }
}

/**
 * Allow tests to override time for deterministic behavior.
 */
function instagram_token_manager_now()
{
  $now = time();
  $filtered = apply_filters('instagram_token_manager_now', $now);
  return is_numeric($filtered) ? (int) $filtered : $now;
}

/**
 * Allow tests to short-circuit HTTP calls.
 */
function instagram_token_manager_http_get($url, $args)
{
  $override = apply_filters('instagram_token_manager_http_get', null, $url, $args);
  if (null !== $override) {
    return $override;
  }

  return wp_remote_get($url, $args);
}

/**
 * Fetch Instagram media response (no parsing).
 */
function instagram_token_manager_fetch_media_response($limit, $token)
{
  $url = add_query_arg([
    'fields'       => 'id,caption,media_url,permalink,timestamp',
    'limit'        => $limit,
    'access_token' => $token,
  ], 'https://graph.instagram.com/me/media');

  $resp = instagram_token_manager_http_get($url, [
    'timeout'     => 15,
    'redirection' => 3,
    'headers'     => [
      'Accept' => 'application/json',
    ],
  ]);

  if (is_wp_error($resp)) {
    return [
      'ok'       => false,
      'status'   => 0,
      'body'     => '',
      'data'     => null,
      'wp_error' => $resp,
    ];
  }

  $status = (int) wp_remote_retrieve_response_code($resp);
  $body   = (string) wp_remote_retrieve_body($resp);

  $data = null;
  $decoded = json_decode($body, true);
  if (is_array($decoded)) {
    $data = $decoded;
  }

  return [
    'ok'       => ($status >= 200 && $status < 300),
    'status'   => $status,
    'body'     => $body,
    'data'     => $data,
    'wp_error' => null,
  ];
}

/**
 * Parse media items from decoded Graph API response.
 */
function instagram_token_manager_parse_media_items($data)
{
  if (!is_array($data) || empty($data['data']) || !is_array($data['data'])) {
    return [];
  }

  $items = [];
  foreach ($data['data'] as $item) {
    if (!is_array($item)) {
      continue;
    }

    $items[] = [
      'id'        => isset($item['id']) ? (string) $item['id'] : '',
      'mediaUrl'  => isset($item['media_url']) ? esc_url_raw($item['media_url']) : '',
      'permalink' => isset($item['permalink']) ? esc_url_raw($item['permalink']) : '',
      'caption'   => isset($item['caption']) ? sanitize_text_field($item['caption']) : '',
      'timestamp' => isset($item['timestamp']) ? sanitize_text_field($item['timestamp']) : '',
    ];
  }

  return $items;
}

/**
 * Determine whether a Graph API failure is likely an auth/token error.
 */
function instagram_token_manager_is_auth_error($status, $data)
{
  if (!in_array((int) $status, [400, 401], true)) {
    return false;
  }

  $error = (is_array($data) && isset($data['error']) && is_array($data['error'])) ? $data['error'] : [];
  $type = isset($error['type']) ? (string) $error['type'] : '';
  $code = isset($error['code']) ? (int) $error['code'] : 0;
  $message = isset($error['message']) ? (string) $error['message'] : '';

  return (
    $code === 190 ||
    (
      stripos($type, 'OAuth') !== false
      && (
        stripos($message, 'expired') !== false
        || stripos($message, 'invalid') !== false
        || stripos($message, 'access token') !== false
      )
    )
  );
}

/**
 * Helper to avoid storing huge bodies.
 */
function instagram_token_manager_truncate($text, $max = 300)
{
  $text = (string) $text;
  if (strlen($text) <= $max) {
    return $text;
  }
  return substr($text, 0, $max) . '…';
}

/**
 * WPGraphQL: DO NOT expose the token. Provide a safe field with media data instead.
 *
 * Requires the site to have WPGraphQL installed, and the token must have access to /me/media.
 */
add_action('graphql_register_types', function () {

  // Simple object type for the feed items.
  if (function_exists('register_graphql_object_type')) {
    register_graphql_object_type('InstagramMediaItem', [
      'description' => __('Instagram media item (sanitized).', 'instagram-token-manager'),
      'fields'      => [
        'id' => [
          'type' => 'String',
          'description' => __('Media ID', 'instagram-token-manager'),
        ],
        'mediaUrl' => [
          'type' => 'String',
          'description' => __('Media URL', 'instagram-token-manager'),
        ],
        'permalink' => [
          'type' => 'String',
          'description' => __('Permalink', 'instagram-token-manager'),
        ],
        'caption' => [
          'type' => 'String',
          'description' => __('Caption', 'instagram-token-manager'),
        ],
        'timestamp' => [
          'type' => 'String',
          'description' => __('Timestamp', 'instagram-token-manager'),
        ],
      ],
    ]);
  }

  // Expose a query that returns a list of recent media items.
  if (function_exists('register_graphql_field')) {
    register_graphql_field('RootQuery', 'instagramMedia', [
      'type'        => ['list_of' => 'InstagramMediaItem'],
      'description' => __('Recent Instagram media for the site (token never exposed).', 'instagram-token-manager'),
      'args'        => [
        'limit' => [
          'type'        => 'Int',
          'description' => __('Max items to return (1-50).', 'instagram-token-manager'),
        ],
      ],
      'resolve'     => function ($root, $args) {
        $limit = isset($args['limit']) ? (int) $args['limit'] : 12;
        if ($limit < 1) {
          $limit = 1;
        }
        if ($limit > 50) {
          $limit = 50;
        }

        $token = (string) get_option(ITM_OPTION_TOKEN, '');
        if ($token === '') {
          return [];
        }

        $cache_key = 'itm_instagram_media_' . $limit;
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
          return $cached;
        }

        $r1 = instagram_token_manager_fetch_media_response($limit, $token);
        if ($r1['wp_error'] instanceof WP_Error) {
          instagram_token_manager_record_error('WPGraphQL media fetch error: ' . $r1['wp_error']->get_error_message());
          return [];
        }

        if ($r1['ok']) {
          $items = instagram_token_manager_parse_media_items($r1['data']);
          set_transient($cache_key, $items, 10 * MINUTE_IN_SECONDS);
          return $items;
        }

        $is_auth = instagram_token_manager_is_auth_error($r1['status'], $r1['data']);

        if ($is_auth) {
          $refresh_result = instagram_token_manager_refresh_token_if_needed(true);

          if ($refresh_result === true) {
            $token2 = (string) get_option(ITM_OPTION_TOKEN, '');
            if ($token2 !== '') {
              $r2 = instagram_token_manager_fetch_media_response($limit, $token2);

              if (!($r2['wp_error'] instanceof WP_Error) && $r2['ok']) {
                $items = instagram_token_manager_parse_media_items($r2['data']);
                set_transient($cache_key, $items, 10 * MINUTE_IN_SECONDS);
                return $items;
              }

              $status2 = $r2['wp_error'] instanceof WP_Error ? 0 : (int) $r2['status'];
              $body2 = $r2['wp_error'] instanceof WP_Error ? $r2['wp_error']->get_error_message() : (string) $r2['body'];
              instagram_token_manager_record_error(sprintf('Media fetch retry failed (status %d). Body: %s', $status2, instagram_token_manager_truncate($body2)));
              return [];
            }
          }

          if ($refresh_result instanceof WP_Error) {
            instagram_token_manager_record_error('Token refresh during media fetch failed: ' . $refresh_result->get_error_message());
          } else {
            instagram_token_manager_record_error('Token refresh during media fetch was not performed (backoff or not needed).');
          }

          return [];
        }

        instagram_token_manager_record_error(sprintf('Media fetch HTTP %d. Body: %s', (int) $r1['status'], instagram_token_manager_truncate((string) $r1['body'])));
        return [];
      },
    ]);
  }
});
