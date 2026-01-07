<?php

use PHPUnit\Framework\TestCase;

class InstagramTokenManagerTest extends TestCase
{
  protected function setUp(): void
  {
    $GLOBALS['wp_options'] = [];
    $GLOBALS['wp_filters'] = [];
    $GLOBALS['wp_actions'] = isset($GLOBALS['wp_actions_base']) ? $GLOBALS['wp_actions_base'] : [];
    $GLOBALS['graphql_fields'] = [];
    $GLOBALS['graphql_types'] = [];
    $GLOBALS['wp_cron'] = [
      'scheduled' => [],
      'cleared' => [],
    ];
  }

  public function test_cron_skips_when_not_expiring()
  {
    $fixed_now = 1700000000;
    add_filter('instagram_token_manager_now', function () use ($fixed_now) {
      return $fixed_now;
    });

    update_option(ITM_OPTION_TOKEN, 'token');
    update_option(ITM_OPTION_EXPIRES_AT, $fixed_now + (20 * DAY_IN_SECONDS));

    $called = false;
    add_filter('instagram_token_manager_http_get', function () use (&$called) {
      $called = true;
      return [
        'response' => ['code' => 200],
        'body' => '{"access_token":"new","expires_in":5184000}',
      ];
    });

    instagram_token_manager_cron_exec();

    $this->assertFalse($called, 'HTTP refresh should not be called when expiry is far away.');
    $this->assertSame(0, (int) get_option(ITM_OPTION_LAST_ATTEMPT, 0));
  }

  public function test_cron_refreshes_and_stores_expiry()
  {
    $fixed_now = 1700000000;
    add_filter('instagram_token_manager_now', function () use ($fixed_now) {
      return $fixed_now;
    });

    update_option(ITM_OPTION_TOKEN, 'token');
    update_option(ITM_OPTION_EXPIRES_AT, 0);

    add_filter('instagram_token_manager_http_get', function () {
      return [
        'response' => ['code' => 200],
        'body' => '{"access_token":"new-token","expires_in":86400}',
      ];
    });

    instagram_token_manager_cron_exec();

    $this->assertSame('new-token', get_option(ITM_OPTION_TOKEN));
    $this->assertSame($fixed_now, (int) get_option(ITM_OPTION_LAST_UPDATE));
    $this->assertSame($fixed_now + 86400, (int) get_option(ITM_OPTION_EXPIRES_AT));
    $this->assertSame(0, (int) get_option(ITM_OPTION_FAIL_COUNT));
  }

  public function test_cron_refreshes_when_expiring_soon()
  {
    $fixed_now = 1700000000;
    add_filter('instagram_token_manager_now', function () use ($fixed_now) {
      return $fixed_now;
    });

    update_option(ITM_OPTION_TOKEN, 'token');
    update_option(ITM_OPTION_EXPIRES_AT, $fixed_now + (5 * DAY_IN_SECONDS));

    $called = false;
    add_filter('instagram_token_manager_http_get', function () use (&$called) {
      $called = true;
      return [
        'response' => ['code' => 200],
        'body' => '{"access_token":"new-token","expires_in":86400}',
      ];
    });

    instagram_token_manager_cron_exec();

    $this->assertTrue($called, 'HTTP refresh should be called when expiring soon.');
    $this->assertSame($fixed_now + 86400, (int) get_option(ITM_OPTION_EXPIRES_AT));
  }

  public function test_cron_refreshes_when_expired()
  {
    $fixed_now = 1700000000;
    add_filter('instagram_token_manager_now', function () use ($fixed_now) {
      return $fixed_now;
    });

    update_option(ITM_OPTION_TOKEN, 'token');
    update_option(ITM_OPTION_EXPIRES_AT, $fixed_now - 10);

    $called = false;
    add_filter('instagram_token_manager_http_get', function () use (&$called) {
      $called = true;
      return [
        'response' => ['code' => 200],
        'body' => '{"access_token":"new-token","expires_in":86400}',
      ];
    });

    instagram_token_manager_cron_exec();

    $this->assertTrue($called, 'HTTP refresh should be called when expiry has passed.');
  }

  public function test_cron_failure_increments_fail_count()
  {
    $fixed_now = 1700000000;
    add_filter('instagram_token_manager_now', function () use ($fixed_now) {
      return $fixed_now;
    });

    update_option(ITM_OPTION_TOKEN, 'token');
    update_option(ITM_OPTION_EXPIRES_AT, 0);

    add_filter('instagram_token_manager_http_get', function () {
      return new WP_Error('http', 'Failed');
    });

    instagram_token_manager_cron_exec();

    $this->assertSame(1, (int) get_option(ITM_OPTION_FAIL_COUNT));
    $this->assertSame($fixed_now, (int) get_option(ITM_OPTION_LAST_ATTEMPT));
  }

  public function test_cron_non_2xx_increments_fail_count()
  {
    $fixed_now = 1700000000;
    add_filter('instagram_token_manager_now', function () use ($fixed_now) {
      return $fixed_now;
    });

    update_option(ITM_OPTION_TOKEN, 'token');
    update_option(ITM_OPTION_EXPIRES_AT, 0);

    add_filter('instagram_token_manager_http_get', function () {
      return [
        'response' => ['code' => 500],
        'body' => '{"error":"bad"}',
      ];
    });

    instagram_token_manager_cron_exec();

    $this->assertSame(1, (int) get_option(ITM_OPTION_FAIL_COUNT));
  }

  public function test_cron_invalid_json_increments_fail_count()
  {
    $fixed_now = 1700000000;
    add_filter('instagram_token_manager_now', function () use ($fixed_now) {
      return $fixed_now;
    });

    update_option(ITM_OPTION_TOKEN, 'token');
    update_option(ITM_OPTION_EXPIRES_AT, 0);

    add_filter('instagram_token_manager_http_get', function () {
      return [
        'response' => ['code' => 200],
        'body' => 'not-json',
      ];
    });

    instagram_token_manager_cron_exec();

    $this->assertSame(1, (int) get_option(ITM_OPTION_FAIL_COUNT));
  }

  public function test_cron_missing_access_token_increments_fail_count()
  {
    $fixed_now = 1700000000;
    add_filter('instagram_token_manager_now', function () use ($fixed_now) {
      return $fixed_now;
    });

    update_option(ITM_OPTION_TOKEN, 'token');
    update_option(ITM_OPTION_EXPIRES_AT, 0);

    add_filter('instagram_token_manager_http_get', function () {
      return [
        'response' => ['code' => 200],
        'body' => '{"expires_in":3600}',
      ];
    });

    instagram_token_manager_cron_exec();

    $this->assertSame(1, (int) get_option(ITM_OPTION_FAIL_COUNT));
  }

  public function test_cron_error_object_increments_fail_count()
  {
    $fixed_now = 1700000000;
    add_filter('instagram_token_manager_now', function () use ($fixed_now) {
      return $fixed_now;
    });

    update_option(ITM_OPTION_TOKEN, 'token');
    update_option(ITM_OPTION_EXPIRES_AT, 0);

    add_filter('instagram_token_manager_http_get', function () {
      return [
        'response' => ['code' => 200],
        'body' => '{"error":{"message":"bad","type":"OAuthException","code":190}}',
      ];
    });

    instagram_token_manager_cron_exec();

    $this->assertSame(1, (int) get_option(ITM_OPTION_FAIL_COUNT));
    $this->assertNotSame('', (string) get_option(ITM_OPTION_LAST_ERROR, ''));
  }

  public function test_cron_missing_expires_in_updates_token_and_sets_expiry_zero()
  {
    $fixed_now = 1700000000;
    add_filter('instagram_token_manager_now', function () use ($fixed_now) {
      return $fixed_now;
    });

    update_option(ITM_OPTION_TOKEN, 'token');
    update_option(ITM_OPTION_EXPIRES_AT, 0);

    add_filter('instagram_token_manager_http_get', function () {
      return [
        'response' => ['code' => 200],
        'body' => '{"access_token":"new-token"}',
      ];
    });

    instagram_token_manager_cron_exec();

    $this->assertSame('new-token', get_option(ITM_OPTION_TOKEN));
    $this->assertSame(0, (int) get_option(ITM_OPTION_EXPIRES_AT));
    $this->assertNotSame('', (string) get_option(ITM_OPTION_LAST_ERROR, ''));
  }

  public function test_backoff_skips_until_window()
  {
    $fixed_now = 1700000000;
    add_filter('instagram_token_manager_now', function () use ($fixed_now) {
      return $fixed_now;
    });

    update_option(ITM_OPTION_TOKEN, 'token');
    update_option(ITM_OPTION_EXPIRES_AT, 0);
    update_option(ITM_OPTION_FAIL_COUNT, 2);
    update_option(ITM_OPTION_LAST_ATTEMPT, $fixed_now - HOUR_IN_SECONDS);

    $called = false;
    add_filter('instagram_token_manager_http_get', function () use (&$called) {
      $called = true;
      return [
        'response' => ['code' => 200],
        'body' => '{"access_token":"new","expires_in":86400}',
      ];
    });

    instagram_token_manager_cron_exec();

    $this->assertFalse($called, 'HTTP refresh should be skipped during backoff window.');
  }

  public function test_backoff_allows_after_window()
  {
    $fixed_now = 1700000000;
    add_filter('instagram_token_manager_now', function () use ($fixed_now) {
      return $fixed_now;
    });

    update_option(ITM_OPTION_TOKEN, 'token');
    update_option(ITM_OPTION_EXPIRES_AT, 0);
    update_option(ITM_OPTION_FAIL_COUNT, 2);
    update_option(ITM_OPTION_LAST_ATTEMPT, $fixed_now - (3 * HOUR_IN_SECONDS) - 1);

    $called = false;
    add_filter('instagram_token_manager_http_get', function () use (&$called) {
      $called = true;
      return [
        'response' => ['code' => 200],
        'body' => '{"access_token":"new","expires_in":86400}',
      ];
    });

    instagram_token_manager_cron_exec();

    $this->assertTrue($called, 'HTTP refresh should proceed after backoff window.');
  }

  public function test_backoff_caps_at_one_day()
  {
    $fixed_now = 1700000000;
    add_filter('instagram_token_manager_now', function () use ($fixed_now) {
      return $fixed_now;
    });

    update_option(ITM_OPTION_TOKEN, 'token');
    update_option(ITM_OPTION_EXPIRES_AT, 0);
    update_option(ITM_OPTION_FAIL_COUNT, 5);
    update_option(ITM_OPTION_LAST_ATTEMPT, $fixed_now - (DAY_IN_SECONDS - 10));

    $called = false;
    add_filter('instagram_token_manager_http_get', function () use (&$called) {
      $called = true;
      return [
        'response' => ['code' => 200],
        'body' => '{"access_token":"new","expires_in":86400}',
      ];
    });

    instagram_token_manager_cron_exec();

    $this->assertFalse($called, 'HTTP refresh should be skipped when 24h backoff not elapsed.');
  }

  public function test_cron_no_token_returns_early()
  {
    $called = false;
    add_filter('instagram_token_manager_http_get', function () use (&$called) {
      $called = true;
      return [
        'response' => ['code' => 200],
        'body' => '{"access_token":"new","expires_in":86400}',
      ];
    });

    instagram_token_manager_cron_exec();

    $this->assertFalse($called, 'HTTP refresh should not be called when token is missing.');
  }

  public function test_refresh_forced_still_respects_backoff()
  {
    $fixed_now = 1700000000;
    add_filter('instagram_token_manager_now', function () use ($fixed_now) {
      return $fixed_now;
    });

    update_option(ITM_OPTION_TOKEN, 'token');
    update_option(ITM_OPTION_FAIL_COUNT, 1);
    update_option(ITM_OPTION_LAST_ATTEMPT, $fixed_now - 10);

    $called = false;
    add_filter('instagram_token_manager_http_get', function () use (&$called) {
      $called = true;
      return [
        'response' => ['code' => 200],
        'body' => '{"access_token":"new","expires_in":86400}',
      ];
    });

    $result = instagram_token_manager_refresh_token_if_needed(true);

    $this->assertFalse($result, 'Forced refresh should still respect backoff window.');
    $this->assertFalse($called, 'HTTP refresh should not be called during backoff.');
  }

  public function test_graphql_resolver_refreshes_and_retries_on_auth_error()
  {
    do_action('graphql_register_types');

    $resolver = $GLOBALS['graphql_fields']['RootQuery']['instagramMedia']['resolve'];

    update_option(ITM_OPTION_TOKEN, 'old-token');

    $queue = [
      [
        'response' => ['code' => 401],
        'body' => '{"error":{"message":"Invalid OAuth access token.","type":"OAuthException","code":190}}',
      ],
      [
        'response' => ['code' => 200],
        'body' => '{"access_token":"new-token","expires_in":86400}',
      ],
      [
        'response' => ['code' => 200],
        'body' => '{"data":[{"id":"1","media_url":"https://example.com/1.jpg","permalink":"https://example.com/1","caption":"Hi","timestamp":"2023-01-01T00:00:00Z"}]}',
      ],
    ];

    add_filter('instagram_token_manager_http_get', function () use (&$queue) {
      return array_shift($queue);
    });

    $items = $resolver(null, ['limit' => 1]);

    $this->assertCount(1, $items);
    $this->assertSame('new-token', get_option(ITM_OPTION_TOKEN));
  }

  public function test_graphql_resolver_auth_error_refresh_skipped_by_backoff()
  {
    do_action('graphql_register_types');

    $resolver = $GLOBALS['graphql_fields']['RootQuery']['instagramMedia']['resolve'];

    update_option(ITM_OPTION_TOKEN, 'old-token');
    update_option(ITM_OPTION_FAIL_COUNT, 1);
    update_option(ITM_OPTION_LAST_ATTEMPT, 1700000000 - 10);

    add_filter('instagram_token_manager_now', function () {
      return 1700000000;
    });

    add_filter('instagram_token_manager_http_get', function () {
      return [
        'response' => ['code' => 401],
        'body' => '{"error":{"message":"Invalid OAuth access token.","type":"OAuthException","code":190}}',
      ];
    });

    $items = $resolver(null, ['limit' => 1]);

    $this->assertSame([], $items);
    $this->assertSame('Token refresh during media fetch was not performed (backoff or not needed).', get_option(ITM_OPTION_LAST_ERROR));
  }

  public function test_graphql_resolver_non_auth_error_does_not_refresh()
  {
    do_action('graphql_register_types');

    $resolver = $GLOBALS['graphql_fields']['RootQuery']['instagramMedia']['resolve'];

    update_option(ITM_OPTION_TOKEN, 'old-token');

    $calls = 0;
    add_filter('instagram_token_manager_http_get', function () use (&$calls) {
      $calls += 1;
      return [
        'response' => ['code' => 400],
        'body' => '{"error":{"message":"Some other error","type":"Other","code":42}}',
      ];
    });

    $items = $resolver(null, ['limit' => 1]);

    $this->assertSame([], $items);
    $this->assertSame(1, $calls, 'Should not retry when error is not auth-related.');
  }

  public function test_graphql_resolver_auth_error_refresh_fails()
  {
    do_action('graphql_register_types');

    $resolver = $GLOBALS['graphql_fields']['RootQuery']['instagramMedia']['resolve'];

    update_option(ITM_OPTION_TOKEN, 'old-token');

    $queue = [
      [
        'response' => ['code' => 401],
        'body' => '{"error":{"message":"Invalid OAuth access token.","type":"OAuthException","code":190}}',
      ],
      new WP_Error('http', 'Failed'),
    ];

    add_filter('instagram_token_manager_http_get', function () use (&$queue) {
      return array_shift($queue);
    });

    $items = $resolver(null, ['limit' => 1]);

    $this->assertSame([], $items);
    $this->assertSame('Token refresh during media fetch failed: Failed', get_option(ITM_OPTION_LAST_ERROR));
  }

  public function test_graphql_resolver_auth_error_retry_fails()
  {
    do_action('graphql_register_types');

    $resolver = $GLOBALS['graphql_fields']['RootQuery']['instagramMedia']['resolve'];

    update_option(ITM_OPTION_TOKEN, 'old-token');

    $queue = [
      [
        'response' => ['code' => 401],
        'body' => '{"error":{"message":"Invalid OAuth access token.","type":"OAuthException","code":190}}',
      ],
      [
        'response' => ['code' => 200],
        'body' => '{"access_token":"new-token","expires_in":86400}',
      ],
      [
        'response' => ['code' => 500],
        'body' => 'oops',
      ],
    ];

    add_filter('instagram_token_manager_http_get', function () use (&$queue) {
      return array_shift($queue);
    });

    $items = $resolver(null, ['limit' => 1]);

    $this->assertSame([], $items);
    $this->assertSame('Media fetch retry failed (status 500). Body: oops', get_option(ITM_OPTION_LAST_ERROR));
  }

  public function test_is_auth_error_requires_expired_or_invalid_message()
  {
    $data = [
      'error' => [
        'type' => 'OAuthException',
        'code' => 42,
        'message' => 'Some other OAuth error',
      ],
    ];

    $this->assertFalse(instagram_token_manager_is_auth_error(400, $data));
  }

  public function test_is_auth_error_detects_invalid_access_token_message()
  {
    $data = [
      'error' => [
        'type' => 'OAuthException',
        'code' => 42,
        'message' => 'Invalid access token',
      ],
    ];

    $this->assertTrue(instagram_token_manager_is_auth_error(401, $data));
  }

  public function test_activation_reschedules_cron()
  {
    instagram_token_manager_activate();

    $this->assertSame([ITM_CRON_HOOK], $GLOBALS['wp_cron']['cleared']);
    $this->assertCount(1, $GLOBALS['wp_cron']['scheduled']);
    $this->assertSame('every_day', $GLOBALS['wp_cron']['scheduled'][0]['recurrence']);
    $this->assertSame(ITM_CRON_HOOK, $GLOBALS['wp_cron']['scheduled'][0]['hook']);
  }

  public function test_sanitize_token_change_resets_metadata()
  {
    $fixed_now = 1700000000;
    add_filter('instagram_token_manager_now', function () use ($fixed_now) {
      return $fixed_now;
    });

    update_option(ITM_OPTION_TOKEN, 'old-token');
    update_option(ITM_OPTION_EXPIRES_AT, 123);
    update_option(ITM_OPTION_FAIL_COUNT, 2);
    update_option(ITM_OPTION_LAST_ATTEMPT, 999);
    update_option(ITM_OPTION_LAST_ERROR, 'error');

    $new_value = instagram_token_manager_sanitize_token('new-token');

    $this->assertSame('new-token', $new_value);
    $this->assertSame($fixed_now, (int) get_option(ITM_OPTION_LAST_UPDATE));
    $this->assertSame(0, (int) get_option(ITM_OPTION_EXPIRES_AT, 0));
    $this->assertSame(0, (int) get_option(ITM_OPTION_FAIL_COUNT, 0));
    $this->assertSame('', (string) get_option(ITM_OPTION_LAST_ATTEMPT, ''));
    $this->assertSame('', (string) get_option(ITM_OPTION_LAST_ERROR, ''));
  }

  public function test_sanitize_token_clear_deletes_metadata()
  {
    update_option(ITM_OPTION_TOKEN, 'old-token');
    update_option(ITM_OPTION_LAST_UPDATE, 123);
    update_option(ITM_OPTION_EXPIRES_AT, 456);
    update_option(ITM_OPTION_FAIL_COUNT, 2);
    update_option(ITM_OPTION_LAST_ATTEMPT, 999);
    update_option(ITM_OPTION_LAST_ERROR, 'error');

    $new_value = instagram_token_manager_sanitize_token('');

    $this->assertSame('', $new_value);
    $this->assertSame('', (string) get_option(ITM_OPTION_LAST_UPDATE, ''));
    $this->assertSame('', (string) get_option(ITM_OPTION_EXPIRES_AT, ''));
    $this->assertSame('', (string) get_option(ITM_OPTION_FAIL_COUNT, ''));
    $this->assertSame('', (string) get_option(ITM_OPTION_LAST_ATTEMPT, ''));
    $this->assertSame('', (string) get_option(ITM_OPTION_LAST_ERROR, ''));
  }
}
