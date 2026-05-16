<?php
/**
 * admin/workspace-view.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div id="wm-workspace-gui">
    <!-- STEP 1: UPLOAD -->
    <div id="step-1">
        <h4 style="margin-top:0;"><?php esc_html_e("1. Preparation", "buildhub-maker-pro"); ?></h4>
        <div style="background: #f9f9f9; border: 2px dashed #ccc; padding: 40px; text-align: center; border-radius: 6px;">
            <input type="file" id="wm_plugin_zip" accept=".zip" style="margin-bottom: 20px;"><br>
            <button id="btn-analyze" class="button button-primary button-large" style="height: 45px; padding: 0 40px; font-weight: bold;"><?php esc_html_e("Start Analysis", "buildhub-maker-pro"); ?></button>
        </div>
    </div>

    <!-- STEP 2 & 3: DYNAMIC AREA -->
    <div id="wm-dynamic-area" style="display:none;">
        <!-- Content will be injected by AJAX -->
    </div>
</div>
