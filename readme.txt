=== BuildHub Maker ===
Contributors: jubelfranz
Tags: deployment, development, freemius, github, wordpress-org
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

A modular internal tool for developers to transform plugins and automate deployment to Freemius, GitHub, and WordPress.org.

== Description ==

BuildHub Maker is a specialized development tool designed to streamline the release process of WordPress plugins. It handles the heavy lifting of code transformation, branch management, and multi-platform deployment.

Main features:
* **Code Transformation:** Automatically strips SDK blocks and premium code to generate a clean "FREE" version.
* **Modular Configuration:** Keep your sensitive credentials in one central `maker-config.php`.
* **GitHub Integration:** Triggers GitHub Actions via Repository Dispatch for automated workflows.
* **Freemius Deployment:** Directly uploads your premium ZIP files to the Freemius API.
* **WordPress.org Ready:** Built-in logic to assist with SVN deployments and reviewer notifications.

== Installation ==

1. Upload the `buildhub-maker` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. **CRITICAL:** Copy `/templates/template-maker-config.php` to the plugin root and rename it to `maker-config.php`.
4. Enter your GitHub Token, Freemius IDs, and SMTP credentials in the `maker-config.php`.
5. Access the tool under 'Tools > BuildHub Maker'.

== Frequently Asked Questions ==

= Is this plugin safe? =
Yes. Access is restricted to Administrators (`manage_options`). Additionally, a `.htaccess` file protects your configuration data from direct browser access.

= Do I need a GitHub Token? =
Yes, you need a Personal Access Token (classic) with `repo` and `workflow` scopes to trigger deployments.

== Changelog ==

= 1.0.0 =
* Initial modular release as a WordPress Plugin.
* Added centralized configuration system.
* Improved cURL diagnostics for server connectivity.
