<?php
/**
 * Admin Menu & Repair Logic
 * Path: /buildhub-maker/admin/admin-menu.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1. REGISTER ADMIN MENU
 */
add_action( 'admin_menu', function() {
    add_menu_page(
        'BuildHub Admin',           // Page Title
        'BuildHub Admin',           // Menu Title
        'manage_options',           // Capability
        'buildhub-admin',           // Menu Slug
        'wm_render_admin_page',     // Function
        'dashicons-hammer',         // Icon
        80                          // Position
    );
});

/**
 * 2. RENDER ADMIN PAGE
 */
if (!function_exists('wm_render_admin_page')) {
function wm_render_admin_page() {
    // Check for repair trigger in URL
    if ( isset($_GET['repair']) && $_GET['repair'] === '1' ) { // phpcs:ignore WordPress.Security.NonceVerification
        // Function from buildhub-maker.php
        wm_generate_pages();
        echo '<div class="updated"><p><strong>Success:</strong> Frontend pages (Workspace & Docs) have been regenerated.</p></div>';
    }

    // Check status of pages
    $hub_page_q = new WP_Query(['post_type'=>'page','title'=>'BuildHub Workspace','posts_per_page'=>1,'post_status'=>'publish']);
    $hub_page = $hub_page_q->have_posts() ? $hub_page_q->posts[0] : null;
    $doc_page_q = new WP_Query(['post_type'=>'page','title'=>'BuildHub Maker Docs','posts_per_page'=>1,'post_status'=>'publish']);
    $doc_page = $doc_page_q->have_posts() ? $doc_page_q->posts[0] : null;
    ?>
    <div class="wrap">
        <h1>BuildHub Maker Management</h1>
        <p>This tool is designed to work in the <strong>Frontend</strong> of your website for a better workspace experience.</p>
        
        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-top: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2 style="margin-top:0;">🛠️ Self-Healing & Repair</h2>
            <p>If your Workspace or Documentation pages were accidentally deleted or show 404 errors, use the button below to restore them immediately.</p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=buildhub-admin&repair=1')); ?>" class="button button-primary">Regenerate Frontend Pages Now</a>
        </div>

        <div style="margin-top: 30px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div style="background: #f0f6fb; padding: 15px; border: 1px solid #d1e3f0; border-radius: 4px;">
                <h3 style="margin-top:0;">🚀 Workspace</h3>
                <p>Access your primary build and deployment interface here:</p>
                <?php if($hub_page): ?>
                    <a href="<?php echo esc_url(get_permalink($hub_page->ID)); ?>" target="_blank" class="button">Open Workspace</a>
                <?php else: ?>
                    <span style="color:red;">Page missing! Please use Repair.</span>
                <?php endif; ?>
            </div>

            <div style="background: #f0f6fb; padding: 15px; border: 1px solid #d1e3f0; border-radius: 4px;">
                <h3 style="margin-top:0;">📖 Documentation</h3>
                <p>Read the setup guide and technical instructions here:</p>
                <?php if($doc_page): ?>
                    <a href="<?php echo esc_url(get_permalink($doc_page->ID)); ?>" target="_blank" class="button">Open Documentation</a>
                <?php else: ?>
                    <span style="color:red;">Page missing! Please use Repair.</span>
                <?php endif; ?>
            </div>
        </div>

        <div style="margin-top: 40px; color: #666; font-size: 12px; border-top: 1px solid #ddd; padding-top: 10px;">
            BuildHub Maker v1.1.2 | Security: Access restricted to Administrators.
        </div>
    </div>
    <?php
}
}
