# Instagram Token Manager

**Version:** 0.0.2  
**Author:** Disnel Rodríguez  
**License:** GPL v2 or later

## Overview

The **Instagram Token Manager** plugin automatically manages the Instagram Access Token associated with your site, ensuring it is refreshed every 24 hours to prevent expiration. This updated version integrates with an existing ACF (Advanced Custom Fields) options page that stores the Instagram token, eliminating the need for a separate settings page.

## Features

- **Automatic Token Refresh:** Cron-based process to refresh the Instagram Access Token daily.
- **ACF Integration:** Uses an existing ACF field (`footer_instagram_token`) on the options page for token storage.
- **GraphQL Field:** Provides a GraphQL field (`brsGetInstagramToken`) for retrieving the current Instagram token.
- **Improved Error Handling:** Utilizes the WordPress HTTP API for network requests and includes error logging for improved reliability.

## Requirements

- WordPress 5.2 or higher.
- PHP 7.2 or higher.
- Advanced Custom Fields (ACF) plugin with an options page configured for storing the Instagram token.

## Installation

1. **Upload the Plugin:**  
   Download the latest version of the Instagram Token Manager plugin and place its folder in the `/wp-content/plugins/` directory, or install it via the WordPress plugin installer.

2. **Activate the Plugin:**  
   In the WordPress admin dashboard, navigate to **Plugins > Installed Plugins**, find **Instagram Token Manager**, and click **Activate**.

## Configuration

### ACF Options Page Setup

Ensure you have an ACF options page that includes a field for the Instagram Access Token. The plugin expects the token to be stored in a field with the name/key `footer_instagram_token` on an options page. Verify that your ACF options page includes this field, or adjust the plugin code accordingly if you use a different field name.

### GraphQL Usage

The plugin registers a GraphQL field named `brsGetInstagramToken` on the `RootQuery` type. Use this field within your GraphQL queries to retrieve the current Instagram access token.

## How It Works

1. **Cron Job Setup:**  
   On plugin activation, a cron event (`instagram_token_manager_cron_hook`) is scheduled to run daily.

2. **Token Refresh Process:**  
   - The cron callback checks if a token exists in the ACF options.
   - If the token exists and 24 hours have passed since the last update, it makes a request to Instagram's Graph API to refresh the token.
   - On successful refresh, the new token is stored back in the ACF field (`footer_instagram_token`) and the last update time is recorded using a WordPress option.

3. **Error Handling:**  
   - The plugin uses the WordPress HTTP API to perform network requests.
   - If errors occur during the request, error details are logged using `error_log()`.

## Additional Improvements & Customizations

- **Error Logging:**  
  The plugin logs HTTP errors with `error_log()`. This behavior can be customized to integrate with other logging mechanisms if needed.

- **Field Adjustments:**  
  If your field name for the Instagram token in ACF differs from `footer_instagram_token`, update the plugin code accordingly in the token refresh function and GraphQL registration.

- **Token Update Frequency:**  
  The current refresh interval is set to 24 hours. If a different frequency is needed, adjust the cron schedule or add new intervals as necessary.

## Troubleshooting

- **ACF Not Found:**  
  Ensure the ACF plugin is installed and activated. The plugin checks for the existence of ACF functions before accessing fields.

- **GraphQL Errors:**  
  Verify that the GraphQL field registration corresponds with your ACF field setup and that your GraphQL schema is up-to-date.

- **Cron Not Running:**  
  Confirm that WordPress cron is functioning on your site. If using a persistent cron service or external cron, ensure it triggers WP-Cron.

For further details, visit the [GitHub repository](https://github.com/disnelrr/instagram-token-manager) or open an issue if you encounter any problems.
