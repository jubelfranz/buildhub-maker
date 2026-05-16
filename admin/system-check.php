<?php
/**
 * admin/system-check.php
 * Dashboard with side-by-side layout and Workspace-Page Fix Button.
 * Text Domain: buildhub-maker-pro
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$projects = get_option('bh_projects_db', []);
$count = count($projects);

// FIX: Explizite Suche nur nach veröffentlichten Seiten (ignoriert Trash
$ws_pages = get_posts(array(
    'post_type'   => 'page',
    'title'       => 'BuildHub Workspace',
    'post_status' => 'publish',
    'numberposts' => 1
));
$ws_page = !empty($ws_pages) ? $ws_pages[0] : null;

$nonce = wp_create_nonce('bh_maker_secure_nonce');

if (!function_exists('bh_get_dashboard_guide')) {
function bh_get_dashboard_guide() {
    $file = BH_MAKER_PATH . 'admin/workspace-guide.txt';
    if (!file_exists($file)) return __("Guide file missing.", "buildhub-maker-pro");
    $content = file_get_contents($file);
    preg_match("/\[STEP-DASHBOARD\](.*?)\[\/STEP-DASHBOARD\]/s", $content, $matches);
    return isset($matches[1]) ? nl2br(trim($matches[1])) : __("Dashboard documentation not found.", "buildhub-maker-pro");
}
}
?>

<div class="wrap">
    <h1>🚀 BuildHub Maker - System Dashboard</h1>
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: start; margin-top: 20px;">
        <!-- LEFT CONTENT -->
        <div>
            <div style="background:#fff; border:1px solid #000; padding:35px; border-radius:10px;">
                <h2><?php esc_html_e("Infrastructure Health", "buildhub-maker-pro"); ?></h2>
                <table class="widefat striped" style="margin-top:20px; border: 1px solid #eee;">
                    <thead><tr><th style="padding: 15px;"><?php esc_html_e("Component", "buildhub-maker-pro"); ?></th><th style="padding: 15px;"><?php esc_html_e("Status", "buildhub-maker-pro"); ?></th><th style="padding: 15px;"><?php esc_html_e("Action", "buildhub-maker-pro"); ?></th></tr></thead>
                    <tbody>
                        <tr>
                            <td style="padding: 15px;"><strong><?php esc_html_e("Project Database", "buildhub-maker-pro"); ?></strong></td>
                            <td style="padding: 15px;"><span style="color:green; font-weight:bold;">ACTIVE</span> (<?php echo esc_html($count); ?> <?php esc_html_e("Projects", "buildhub-maker-pro"); ?>)</td>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td style="padding: 15px;"><strong><?php esc_html_e("Workspace Page", "buildhub-maker-pro"); ?></strong></td>
                            <td style="padding: 15px;"><?php echo ($ws_page) ? '<span style="color:green; font-weight:bold;">READY</span>' : '<span style="color:red; font-weight:bold;">MISSING</span>'; ?></td>
                            <td>
                                <?php if(!$ws_page): ?>
                                    <button type="button" id="btn-fix-page" class="button button-small">🔧 <?php esc_html_e("Create Page", "buildhub-maker-pro"); ?></button>
                                <?php else: ?>
                                    <a href="<?php echo esc_url(get_permalink($ws_page->ID)); ?>" target="_blank" class="button button-small"><?php esc_html_e("View Page", "buildhub-maker-pro"); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div style="margin-top: 40px; padding: 25px; background: #fdfdfd; border: 1px solid #ddd; border-radius: 8px;">
                    <h3><?php esc_html_e("Data Portability", "buildhub-maker-pro"); ?></h3>
                    <div style="display:flex; gap:12px; margin-top: 20px;">
                        <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=bh_export_projects&security=' . $nonce)); ?>" class="button button-large">📥 <?php esc_html_e("Export JSON", "buildhub-maker-pro"); ?></a>
                        <button type="button" class="button button-large" onclick="jQuery('#import-unit').toggle();">📤 <?php esc_html_e("Import JSON", "buildhub-maker-pro"); ?></button>
                    </div>
                    <div id="import-unit" style="display:none; margin-top:25px;">
                        <textarea id="import_raw_json" style="width:100%; height:120px; font-family:monospace;"></textarea>
                        <button type="button" id="btn-run-import" class="button button-primary" style="margin-top:10px;"><?php esc_html_e("Confirm & Restore", "buildhub-maker-pro"); ?></button>
                    </div>
                </div>
                <div style="margin-top:40px;"><a href="<?php echo esc_url(admin_url('admin.php?page=buildhub-config')); ?>" class="button button-primary button-large">⚙️ <?php esc_html_e("Manage Datasets", "buildhub-maker-pro"); ?></a></div>
            </div>
        </div>

        <!-- RIGHT GUIDE -->
        <div style="border: 2px solid #000; padding: 25px; background: #fdfaf0; border-radius: 4px;">
            <div id="bh-readme-context" style="font-size: 13px; line-height: 1.6; color: #333;">
                <?php echo wp_kses_post( bh_get_dashboard_guide() ); ?>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#btn-fix-page').click(function() {
        $.post(ajaxurl, { action: 'bh_fix_workspace_page', security: '<?php echo esc_js($nonce); ?>' }, function(r) { alert(r.data); if(r.success) location.reload(); });
    });
    $('#btn-run-import').click(function() {
        var raw = $('#import_raw_json').val(); if(!raw || !confirm('<?php esc_html_e("Overwrite all?", "buildhub-maker-pro"); ?>')) return;
        $.post(ajaxurl, { action: 'bh_import_projects', security: '<?php echo esc_js($nonce); ?>', import_data: raw }, function(r) { alert(r.data); if(r.success) location.reload(); });
    });
});
</script>
