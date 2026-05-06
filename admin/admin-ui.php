<?php
/**
 * admin-ui.php
 * Finales Layout mit System-Tools (Debug & SMTP)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
global $bh_projects;

$debug_enabled = get_option('bh_debug_mode', '0');
?>

<div id="wm-workspace-root" style="width: 100%; font-family: sans-serif; background: #fff; padding: 20px; box-sizing: border-box;">

    <!-- REIHE 1 -->
    <div style="display: table; width: 100%; margin-bottom: 25px;">
        <div style="display: table-cell; vertical-align: middle;">
            <h1 style="margin: 0; font-size: 26px;">🚀 BuildHub Maker</h1>
        </div>
        <div style="display: table-cell; width: 35%; border: 2px solid #000; padding: 15px; background: #f9f9f9; vertical-align: middle;">
            <strong>Plugin / Addon wählen:</strong><br>
            <form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
                <input type="hidden" name="action" value="bh_switch_project">
                <select name="bh_project_switch" onchange="this.form.submit()" style="width: 100%; margin-top: 5px; height: 35px;">
                    <?php foreach($bh_projects as $key => $proj): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected(ACTIVE_PROJECT_KEY, $key); ?>><?php echo esc_html($proj['PLUGIN_NAME']); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <!-- REIHE 2 -->
    <div style="display: table; width: 100%; border-spacing: 20px 0; margin-left: -20px;">
        <div id="wm-main-action-box" style="display: table-cell; width: 66%; border: 2px solid #000; padding: 25px; vertical-align: top; min-height: 500px; background: #fff; position: relative;">
            
            <div id="wm-content-wrapper" style="min-height: 400px;">
                <div id="step-1">
                    <h3>1. Dateiauswahl & Analyse</h3>
                    <p>Wähle das Plugin- oder AddOn-ZIP zur Analyse.</p>
                    <input type="file" id="wm_plugin_zip" style="margin: 20px 0; display: block;">
                    <button id="btn-analyze" class="button" style="background:#fff; border:1px solid #0073aa; color:#0073aa; font-weight:bold; height:40px; padding: 0 20px;">Analyse starten</button>
                </div>
                <div id="wm-dynamic-area" style="display:none; margin-top: 25px; padding-top: 20px;"></div>
            </div>

            <!-- SYSTEM TOOLS (Bündig am Boden) -->
            <div style="margin-top: 40px; padding-top: 15px; border-top: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #999;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="bh_debug_toggle" <?php checked($debug_enabled, '1'); ?> style="margin:0;"> 
                    <label for="bh_debug_toggle" style="cursor:pointer; font-weight: bold;">Debug-Modus (ajax-debug.log)</label>
                </div>
                <div>
                    <a href="#" id="bh_smtp_test" style="color: #0073aa; text-decoration: none; font-weight: bold;">📧 SMTP Test-Mail senden</a>
                </div>
            </div>
        </div>

        <div style="display: table-cell; width: 33%; border: 2px solid #000; padding: 20px; background: #fdfaf0; vertical-align: top;">
            <h4 style="margin-top: 0; border-bottom: 1px solid #000; padding-bottom: 8px;">📄 readme.txt</h4>
            <div style="font-size: 12px; line-height: 1.4;">
                <pre style="white-space: pre-wrap; font-family: monospace; background: transparent; border: none; padding: 0; margin: 0; color: #444;">
<?php 
$readme_txt = BH_MAKER_PATH . 'readme.txt';
if (file_exists($readme_txt)) { echo esc_html(file_get_contents($readme_txt)); }
?>
                </pre>
            </div>
        </div>
    </div>
</div>
