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
 * Text Domain:       instagram-token-manager
 * Domain Path:       /languages
 */

// Check if the plugin file is accessed directly.
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Add custom cron schedule for daily execution.
 */
function add_day_cron_interval($schedules)
{
  $schedules['every_day'] = [
    'interval' => 86400,
    'display'  => esc_html__('Every Day'),
  ];
  return $schedules;
}
add_filter('cron_schedules', 'add_day_cron_interval');

// Schedule event if not already scheduled.
if (!wp_next_scheduled('instagram_token_manager_cron_hook')) {
  wp_schedule_event(time(), 'every_day', 'instagram_token_manager_cron_hook');
}
add_action('instagram_token_manager_cron_hook', 'instagram_token_manager_cron_exec');

/**
 * Cron job to refresh Instagram token using ACF field.
 */
function instagram_token_manager_cron_exec()
{
  // Retrieve current token using ACF.
  $current_token = function_exists('get_field') ? get_field('footer_instagram_token', 'option') : '';

  // Use WordPress options to track last update time.
  $last_updated = get_option('instagram_token_last_update');
  $current_time = time();

  // If token not set or invalid, exit.
  if (empty($current_token)) {
    return;
  }

  // Set last update if not already set.
  if (!$last_updated) {
    update_option('instagram_token_last_update', $current_time);
    return;
  }

  // Refresh only if 24 hours have passed.
  if (($current_time - $last_updated) < 86400) {
    return;
  }

  $url = "https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token=" . esc_attr($current_token);

  // Use WordPress HTTP API instead of cURL.
  $response = wp_remote_get($url);

  if (is_wp_error($response)) {
    // Log error.
    error_log('Instagram Token Refresh Error: ' . $response->get_error_message());
    return;
  }

  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, true);

  if (!empty($data['access_token'])) {
    // Update the ACF field with the new token.
    if (function_exists('update_field')) {
      update_field('field_footer_instagram_token', $data['access_token'], 'option');
    }
    // Update last refresh time.
    update_option('instagram_token_last_update', time());
  }
}

