=== BuildHub Maker ===
Contributors: franzhorvath
Donate link: https://einfachalles.at
Tags: deployment, plugin development, freemius, developer tools, automation
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 2.0.5
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A professional transformation and deployment suite for WordPress developers — split one codebase into FREE and PRO versions automatically.

== Description ==

BuildHub Maker is a professional-grade transformation and deployment suite designed for WordPress developers who want to streamline their workflow. It acts as a central hub to manage, transform, and deploy multiple WordPress plugins from a single frontend interface.

The core strength of BuildHub Maker lies in its ability to take a single "Master" ZIP file and split it into separate FREE and PRO versions. By using specific comment tags like `// [PRO]` and `// [/PRO]`, developers can maintain one codebase while BuildHub automatically handles the stripping of premium features for the official WordPress.org repository.

= Key Features =

* **Multi-Project Management:** Store and switch between different plugin datasets (Slugs, IDs, Author data) directly in the database.
* **Smart Transformation:** Automatic stripping of PRO blocks, text domain patching, and header updates based on the target version.
* **Frontend Workspace:** A dedicated, clean workspace for building and analyzing plugins without backend clutter.
* **Project Manager:** Edit credentials, slugs, and author metadata directly in the frontend — no backend required.
* **Persistent Storage:** All datasets are securely stored in the WordPress options table.
* **Data Portability:** JSON Export/Import functionality for migrating project data between environments.
* **Context Guide:** Step-by-step documentation directly inside the workspace.

= How It Works =

1. Upload your master ZIP file in Step 1.
2. Set the target version in Step 2 and click Build.
3. Download your FREE and/or PRO ZIPs in Step 3.

= PRO Version =

BuildHub Maker Pro adds direct deployment capabilities:

* GitHub Repository Dispatch to trigger automated GitHub Actions workflows.
* Direct WP.org SVN deployment via GitHub Actions integration.
* Freemius API upload for premium version distribution.

== Installation ==

1. Upload the `buildhub-maker` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the Plugins menu in WordPress.
3. Navigate to **BuildHub Maker → Dashboard** in your WordPress Admin to verify your setup.
4. Create or visit the **BuildHub Workspace** page on your frontend to start your first build.

== Frequently Asked Questions ==

= How do I mark code as PRO only? =

Wrap your premium code blocks with `// [PRO]` and `// [/PRO]` comment lines. BuildHub will remove these entire sections when generating the FREE version, and strip only the tag lines when generating the PRO version.

= Where are my project datasets stored? =

All datasets are stored in the WordPress database (`wp_options`). Use the Export tool in the System Dashboard to create JSON backups.

= Can I use this for multiple plugins? =

Yes! The Project Manager allows you to create an unlimited number of datasets. Switch the active project using the dropdown in the Workspace header at any time.

= Does the FREE version support GitHub deployment? =

No — GitHub and Freemius deployment is a PRO-only feature. The FREE version fully supports local transformation, building, and downloading of FREE and PRO ZIPs.

== Screenshots ==

1. The Frontend Workspace with the Build process in Step 2.
2. The System Dashboard showing infrastructure health and backup tools.
3. The Project Manager for editing credentials and author metadata.

== Changelog ==

= 2.0.1 =
* Fixed critical regex bug: PRO tag stripping now only matches exact tag lines, not comments mentioning [PRO].
* FREE version of BuildHub Maker itself now fully functional for building other plugins.
* Improved async build handling and cleanup of temporary files before each build.
* GitHub API URL corrected in deployer.
* All wp_unslash(), esc_url(), gmdate() compliance fixes for WP Plugin Check.

= 2.0.0 =
* Complete rewrite with modular Frontend Workspace (Step 1 / Step 2 / Step 3).
* External JavaScript via wp_enqueue_script for CSP compliance.
* Project Manager fully integrated in the frontend — no backend required for dataset CRUD.
* Context-sensitive guide text per step via workspace-guide.txt.
* Auto-deactivation of FREE version when PRO is activated.
* Text domain patching for PRO builds.

= 1.6.1 =
* Separated internal workflow guide from the official readme.txt for WP.org compliance.
* Improved AJAX performance during the transformation process.

= 1.0.0 =
* Initial release of BuildHub Maker.

== Upgrade Notice ==

= 2.0.1 =
Critical fix for FREE version functionality. Highly recommended upgrade for all users.
