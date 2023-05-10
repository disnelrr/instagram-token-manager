<?php

/**
 * @file
 * Plugin Name:       Instagram Token Manager
 * Plugin URI:        https://github.com/disnelrr/instagram-token-manager
 * Description:       Handles automatic updates to your Instagram app token so it never gets expired.
 * Version:           0.0.1
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

// Check if the plugin file is accessed directly.
if (!defined('ABSPATH')) {
  exit;
}

// Register the admin menu.
add_action('admin_menu', 'instagram_token_manager_menu');

/**
 * Add option page.
 */
function instagram_token_manager_menu() {
  add_options_page(
    'Instagram Token Manager',
    'Instagram Token Manager',
    'manage_options',
    'instagram-token-manager',
    'instagram_token_manager_page'
  );
}

/**
 * Display the admin page.
 */
function instagram_token_manager_page() {
  ?>
  <div class="wrap">
    <h1>Instagram Token Manager</h1>
    <form method="post" action="options.php">
      <?php
      settings_fields('instagram-token-manager');
      do_settings_sections('instagram-token-manager');
      // If we're posting a new token value, reset time.
      if (isset($_POST["instagram_token"])) {
        update_option('instagram_token_last_update', time());
      }
      ?>
      <table class="form-table">
        <tr valign="top">
          <th scope="row">Instagram Token</th>
          <td>
            <input type="text" name="instagram_token" value="<?php echo esc_attr(get_option('instagram_token')); ?>" size="50" />
          </td>
        </tr>
      </table>
      <?php submit_button(); ?>
    </form>
  </div>
  <?php
}

// Register the setting.
add_action('admin_init', 'instagram_token_storage_settings');

/**
 *
 */
function instagram_token_storage_settings() {
  register_setting('instagram-token-manager', 'instagram_token');
}

/**
 *
 */
function add_day_cron_interval($schedules) {
  $schedules['every_day'] = [
    'interval' => 86400,
    'display'  => esc_html__('Every Day'),
  ];
  return $schedules;
}

// Add new cron interval.
add_filter('cron_schedules', 'add_day_cron_interval');


add_action('instagram_token_manager_cron_hook', 'instagram_token_manager_cron_exec');


if (!wp_next_scheduled('instagram_token_manager_cron_hook')) {
  wp_schedule_event(time(), 'every_day', 'instagram_token_manager_cron_hook');
}

/**
 *
 */
function instagram_token_manager_cron_exec() {
  $last_updated = get_option('instagram_token_last_update');
  $current_time = time();

  if (!last_updated) {
    update_option('instagram_token_last_update', $current_time);
    return;
  }

  if ($current_time - $last_updated < 86400) {
    return;
  }

  $url = "https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token=" . get_option('instagram_token');

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => TRUE,
  ]);

  $response = curl_exec($ch);
  curl_close($ch);

  $data = json_decode($response, TRUE);

  if (isset($data['access_token'])) {
    update_option('instagram_token', $data['access_token'], TRUE);
    update_option('instagram_token_last_update', time());
  }
}
