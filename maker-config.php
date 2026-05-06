<?php
/**
 * TEMPLATE-MAKER-CONFIG v1.2.9
 * Path: /wp-content/plugins/buildhub-maker/template-maker-config.php
 * 
 * DESCRIPTION:
 * This file handles the configuration for multiple WordPress projects (Main Plugins & Add-ons).
 * It manages Freemius IDs, specific Bearer Tokens, GitHub repositories, and SMTP settings.
 * 
 * INSTRUCTIONS:
 * 1. Copy this file to 'maker-config.php' in the same directory.
 * 2. Replace the 'your-...' placeholders with your actual credentials.
 * 3. Use the 'FS_TOKEN' for each project as obtained from your Freemius Deployment settings.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1. GLOBAL DEFAULT SELECTION
 * Defines which project from the list below is loaded if no specific project is requested.
 */
if ( ! defined( 'BH_DEFAULT_PROJECT' ) ) {
    define('BH_DEFAULT_PROJECT', 'main-plugin-key');
}

/**
 * 2. PROJECT DATASETS
 * An array of all plugins and add-ons managed by this tool.
 * The key (e.g., 'main-plugin-key') is used in the UI switcher.
 */
$bh_projects = [

    // DATASET: Example for a Main Plugin
    'main-plugin-key' => [
        'FS_ID'         => '12345',                  // Your Freemius Product ID
        'FS_TOKEN'      => 'your-bearer-token-1',    // Product-specific Bearer Token for Deployment
        'GH_REPO'       => 'user/main-repo-pro',     // GitHub Repository (format: owner/repo)
        'PLUGIN_DOMAIN' => 'my-main-plugin',         // Text-domain of the plugin
        'PLUGIN_NAME'   => 'My Professional Plugin', // Human-readable name
        'PLUGIN_AUTHOR' => 'Author Name',
        'PLUGIN_URI'    => 'https://example.com',
        'HAS_FREE'      => true,                     // Build a FREE version for WordPress.org?
    ],

    // DATASET: Example for an Add-on
    'addon-key' => [
        'FS_ID'         => '67890',                  // Different ID for the Add-on
        'FS_TOKEN'      => 'your-bearer-token-2',    // Different Token for the Add-on
        'GH_REPO'       => 'user/addon-repo',
        'PLUGIN_DOMAIN' => 'my-addon',
        'PLUGIN_NAME'   => 'My Plugin Add-on',
        'PLUGIN_AUTHOR' => 'Author Name',
        'PLUGIN_URI'    => 'https://example.com',
        'HAS_FREE'      => false,                    // Add-ons usually don't have separate free versions
    ]
];

/**
 * 3. SHARED CREDENTIALS & SMTP HOOK
 * Global settings applied to the whole system.
 */

// GitHub Personal Access Token (for Repository Dispatch triggers)
if ( ! defined( 'GH_TOKEN' ) ) {
    define('GH_TOKEN', 'ghp_your_personal_access_token_here');
}

// SMTP Mail Server Settings
// Used by the "SMTP Test" button in the UI and for system notifications.
if ( ! defined( 'MAIL_HOST' ) ) define('MAIL_HOST', '://smtp-server.com');
if ( ! defined( 'MAIL_PORT' ) ) define('MAIL_PORT', 587);
if ( ! defined( 'MAIL_USER' ) ) define('MAIL_USER', 'your-sender@domain.com');
if ( ! defined( 'MAIL_PASS' ) ) define('MAIL_PASS', 'your-secure-password');

/**
 * NATIVE WP_MAIL() OVERRIDE:
 * This hook forces WordPress to use the SMTP credentials defined above.
 */
add_action( 'phpmailer_init', function( $phpmailer ) {
    $phpmailer->isSMTP();
    $phpmailer->Host       = MAIL_HOST;
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Port       = MAIL_PORT;
    $phpmailer->Username   = MAIL_USER;
    $phpmailer->Password   = MAIL_PASS;
    $phpmailer->SMTPSecure = 'tls'; // Use 'ssl' for Port 465, 'tls' for Port 587
    $phpmailer->From       = MAIL_USER;
    $phpmailer->FromName   = 'BuildHub Maker Notification';
});

/**
 * 4. DYNAMIC ROUTING LOGIC
 * Determines which project is active based on user selection in the UI.
 * (Do not modify unless you want to change the constant mapping).
 */

// Sanitize and determine active project
$active_key = (isset($_REQUEST['bh_project'])) ? sanitize_text_field($_REQUEST['bh_project']) : BH_DEFAULT_PROJECT;
if ( ! isset($bh_projects[$active_key]) ) {
    $active_key = BH_DEFAULT_PROJECT;
}

$active = $bh_projects[$active_key];

// Define core constants for the current session
if ( ! defined( 'ACTIVE_PROJECT_KEY' ) ) define('ACTIVE_PROJECT_KEY', $active_key);
if ( ! defined( 'FS_ID' ) )              define('FS_ID',              $active['FS_ID']);
if ( ! defined( 'GH_REPO' ) )           define('GH_REPO',            $active['GH_REPO']);
if ( ! defined( 'PLUGIN_DOMAIN' ) )      define('PLUGIN_DOMAIN',      $active['PLUGIN_DOMAIN']);
if ( ! defined( 'PLUGIN_NAME' ) )        define('PLUGIN_NAME',        $active['PLUGIN_NAME']);
if ( ! defined( 'PLUGIN_AUTHOR' ) )      define('PLUGIN_AUTHOR',      $active['PLUGIN_AUTHOR']);
if ( ! defined( 'PLUGIN_URI' ) )         define('PLUGIN_URI',         $active['PLUGIN_URI']);
if ( ! defined( 'HAS_FREE' ) )           define('HAS_FREE',           $active['HAS_FREE']);

// Map the specific project token to the global bearer token constant used by the deployer
if ( ! defined( 'FS_BEARER_TOKEN' ) ) {
    define('FS_BEARER_TOKEN', $active['FS_TOKEN']);
}
