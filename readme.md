## WP Mail MailGun

* Contributors: stewarty, poweredbycoffee
* Donate link: http://poweredbycoffee.co.uk/
* Tags: wpmail mailgun transational-email
* Requires at least: 4.6
* Tested up to: 5.5
* Stable tag: 3.0
* License: GPLv2 or later
* License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send email from WordPress via MailGun's transactional email service.

### Description

Sometime you don't want to send email from your own webserver. Some hosts don't allow it. It takes alot of resources and isn't always reliable.

In cases like its usually a great idea to get someone else to send it. 

MailGun is a great transational email service that currently sends 10,000 free emails on your behalf a month.

This plugin will integrate MailGun with WordPress so it takes over sending all of the mail in WordPress.

Any mail sent via `wp_mail()` will be delivered via MailGun

If no stable tag is provided, it is assumed that trunk is stable, but you should specify "trunk" if that's where you put the stable version, in order to eliminate any doubt.

### Installation

1. Upload the plugin to the `wp-content/plugins` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Sign up for a MailGun account - https://mailgun.com/signup
4. Add your MailGun API Key and MailGun Domain to your wp-config.php as so
    ```
    define( 'MAILGUN_API_KEY', 'key-xxxxxxxxx');
    define( 'MAILGUN_DOMAIN', 'your Mailgun domain');
    ```
5. Generate a few test emails in WordPress and you should see them in MailGun's Logs


### Changelog

= 1.0 =
Inital Launch

