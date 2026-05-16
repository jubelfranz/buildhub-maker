<?php
/**
 * admin/admin-ui.php
 * Workspace Shell - Dataset CRUD im FE
 */
if ( ! defined( 'ABSPATH' ) ) exit;
global $bh_projects;

$active_idx = 0;
if (is_array($bh_projects)) {
    foreach($bh_projects as $idx => $proj) {
        if(ACTIVE_PROJECT_KEY === $proj['PLUGIN_DOMAIN']) { $active_idx = $idx; break; }
    }
}

if (!function_exists('bh_get_guide_init')) {
    function bh_get_guide_init($section = 'STEP-IDLE') {
        $file = BH_MAKER_PATH . 'admin/workspace-guide.txt';
        if (!file_exists($file)) return 'Guide missing.';
        $content = file_get_contents($file);
        preg_match("/\[$section\](.*?)\[\/$section\]/s", $content, $matches);
        return isset($matches[1]) ? nl2br(trim($matches[1])) : 'Start build process...';
    }
}
?>
<style>
    #bh-master-grid { display:grid; grid-template-columns:2fr 1fr; gap:40px; align-items:start; width:100%; }
    .bh-btn-yellow { display:block; width:100%; text-align:center; cursor:pointer; font-size:13px; font-weight:bold; color:#000; background:#ffc107; padding:12px 0; border:2px solid #000; border-radius:4px; box-shadow:4px 4px 0px #000; text-decoration:none; }
    .bh-step-container { display:none; }
    .bh-step-active { display:block !important; }
    #bh-pm-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; overflow-y:auto; }
    #bh-pm-box { background:#fff; max-width:700px; margin:40px auto; border-radius:8px; padding:30px; border:2px solid #000; }
    .bh-pm-field { width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box; font-family:monospace; }
    .bh-pm-label { font-weight:bold; font-size:12px; display:block; margin-bottom:3px; color:#333; }
    .bh-pm-row { margin-bottom:12px; }
</style>

<div id="wm-workspace-root" style="width:100%; font-family:sans-serif; background:#fff; padding:30px; box-sizing:border-box; border:2px solid #000; border-radius:8px;">

    <!-- HEADER -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; padding-bottom:20px; border-bottom:2px solid #000;">
        <div>
            <h1 style="margin:0; font-size:32px;">&#128640; BUILDHUB MAKER</h1>
            <small style="color:#666; font-weight:bold; text-transform:uppercase;">Professional Deployment Suite</small>
        </div>

        <div style="display:flex; flex-direction:row; align-items:center; gap:12px;">
            <!-- Dataset Selector -->
            <!-- Dataset Selector als Button-Style -->
            <div style="display:flex; align-items:center; gap:6px; background:#e7f5fe; padding:0 18px; border:2px solid #000; border-radius:4px; box-shadow:4px 4px 0px #000; white-space:nowrap; height:43px; box-sizing:border-box;">
                <span style="font-weight:bold; font-size:13px; color:#0073aa;">ACTIVE PROJECT:</span>
                <select id="bh-project-select" style="font-weight:bold; border:none; background:transparent; color:#000; font-size:13px; cursor:pointer; outline:none;">
                    <?php foreach((array)$bh_projects as $i => $proj): ?>
                        <option value="<?php echo esc_attr($proj['PLUGIN_DOMAIN']); ?>" data-idx="<?php echo esc_html($i); ?>" <?php selected(ACTIVE_PROJECT_KEY, $proj['PLUGIN_DOMAIN']); ?>><?php echo esc_html($proj['PLUGIN_NAME']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button id="btn-open-pm" style="cursor:pointer; font-size:13px; font-weight:bold; color:#000; background:#ffc107; padding:0 18px; border:2px solid #000; border-radius:4px; box-shadow:4px 4px 0px #000; white-space:nowrap; height:43px; box-sizing:border-box;">&#9881;&#65039; PROJECT MANAGER</button>

        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div id="bh-main-content">
        <div id="bh-master-grid">
            <!-- LEFT: STEPS + PM (umschaltbar) -->
            <div>
                <!-- STEP-BEREICH -->
                <div id="bh-steps-panel">
                    <div id="bh-modular-wrapper">
                        <div id="step-1-box" class="bh-step-container bh-step-active">
                            <div class="bh-step-content"><?php echo do_shortcode('[bh-1-prep-v250]'); ?></div>
                        </div>
                        <div id="step-2-box" class="bh-step-container">
                            <div class="bh-step-content"></div>
                        </div>
                        <div id="step-3-box" class="bh-step-container">
                            <div class="bh-step-content"></div>
                        </div>
                    </div>
                    <!-- FOOTER TOOLS -->
                    <div style="margin-top:50px; padding-top:20px; border-top:1px solid #eee; display:flex; align-items:center; justify-content:space-between; font-size:11px;">
                        <div style="display:flex; align-items:center; gap:12px; background:#f9f9f9; padding:8px 15px; border-radius:4px; border:1px solid #ddd;">
                            <input type="checkbox" id="bh_debug_toggle" <?php checked(get_option('bh_debug_mode','0'),'1'); ?>>
                            <label for="bh_debug_toggle" style="cursor:pointer; font-weight:bold; color:#444; text-transform:uppercase;">Debug Mode</label>
                        </div>
                        <a href="#" id="bh_smtp_test" style="color:#0073aa; text-decoration:none; font-weight:bold; border-bottom:1px dashed #0073aa;">&#128231; SMTP Test</a>
                    </div>
                </div>

                <!-- PM-BEREICH (versteckt bis Button geklickt) -->
                <div id="bh-pm-panel" style="display:none;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:2px solid #000; padding-bottom:10px;">
                        <strong style="font-size:15px;" id="bh-pm-title">&#9998; Edit Dataset</strong>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <button id="btn-new-dataset" style="background:#28a745; color:#fff; border:none; padding:5px 12px; border-radius:4px; font-weight:bold; cursor:pointer; font-size:12px;">&#43; New</button>
                        </div>
                    </div>

                    <input type="hidden" id="bh-pm-idx" value="">
                    <input type="hidden" id="bh-pm-mode" value="edit">

                    <?php
                    $pm_fields = [
                        'pm-plugin-name' => 'Plugin Name',
                        'pm-slug'        => 'Slug (free version)',
                        'pm-paid-slug'   => 'Paid Slug (pro version)',
                        'pm-repo'        => 'GitHub Repo (user/repo)',
                        'pm-gh-token'    => 'GitHub PAT (ghp_...)',
                        'pm-fs-id'       => 'Freemius ID',
                        'pm-fs-token'    => 'Freemius Bearer Token',
                        'pm-author'      => 'Author Name',
                        'pm-uri'         => 'Author URI',
                    ];
                    foreach ($pm_fields as $fid => $flabel): ?>
                    <div style="margin-bottom:10px;">
                        <label style="font-weight:bold; font-size:12px; display:block; margin-bottom:3px; color:#333;"><?php echo esc_html($flabel); ?></label>
                        <input type="text" id="<?php echo esc_html($fid); ?>" style="width:100%; padding:7px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box; font-family:monospace; font-size:12px;">
                    </div>
                    <?php endforeach; ?>

                    <div style="margin-bottom:12px;">
                        <label style="font-weight:bold; font-size:12px; color:#333; cursor:pointer;">
                            <input type="checkbox" id="pm-has-free" style="margin-right:5px;">
                            Create FREE Version
                        </label>
                    </div>

                    <div style="display:flex; justify-content:space-between; margin-top:15px; padding-top:12px; border-top:1px solid #eee;">
                        <button id="btn-pm-save" style="background:#28a745; color:#fff; border:none; padding:9px 22px; border-radius:4px; font-weight:bold; cursor:pointer; font-size:13px;">&#128190; Save Dataset</button>
                        <button id="btn-pm-delete" style="background:transparent; color:#dc3545; border:none; font-weight:bold; cursor:pointer; display:none; font-size:13px;">&#128465; Delete</button>
                    </div>
                    <div id="bh-pm-feedback" style="margin-top:10px; font-weight:bold; text-align:center; font-size:13px;"></div>
                </div>
            </div>
            <!-- RIGHT: GUIDE -->
            <!-- RIGHT: GUIDE (immer sichtbar) -->
            <div style="border:2px solid #000; padding:25px; background:#fdfaf0; border-radius:4px; min-height:300px;">
                <div id="bh-readme-context"><?php echo wp_kses_post( bh_get_guide_init('STEP-IDLE') ); ?></div>
            </div>
        </div>
    </div>
</div>

