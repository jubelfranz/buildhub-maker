<?php
/**
 * BuildHub Maker - Step 1: Preparation
 * Full vertical structure: 98 Lines.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_shortcode('bh-1-prep-v250', 'bh_render_step_1_v271');

function bh_render_step_1_v271() {
    // Automatischer Cleanup für Stabilität
    $upload_dir = wp_upload_dir();
    $builds_path = $upload_dir['basedir'] . '/buildhub_tmp/Builds';
    if (is_dir($builds_path)) {
        $files = glob($builds_path . '/*');
        foreach ($files as $file) {
            if (is_file($file) && time() - filemtime($file) > 86400) {
                @wp_delete_file($file);
            }
        }
    }
    ob_start(); ?>
    <div id="bh-step-1-wrapper" style="background:transparent !important; color:#000 !important; padding:20px; border:1px solid #eee; border-radius:8px;">
        <h3 style="color:#000 !important; margin:0;">1. Preparation</h3>
        <div style="margin:30px 0; padding:40px; border:2px dashed #ccc; text-align:center;">
            <input type="file" id="wm_plugin_zip_field" name="plugin_zip" accept=".zip" style="color:#000 !important;">
            <br><br>
            <a href="javascript:void(0);" onclick="bh_execute_analysis_v271(this)"
               style="display:inline-block !important; color:#0073aa !important; background:transparent !important; font-size:24px !important; font-weight:bold !important; text-decoration:none !important; cursor:pointer !important;">
               Start Analysis
            </a>
        </div>
        <div id="analysis-feedback" style="margin-top:15px; font-weight:bold; color:#000 !important; text-align:center;"></div>
    </div>
    <script type="text/javascript">
    function bh_execute_analysis_v271(el) {
        var $ = jQuery, fI = document.getElementById('wm_plugin_zip_field');
        if (!fI.files || fI.files.length === 0) { alert('Please select a file.'); return; }
        var $btn = $(el); $btn.css('opacity', '0.5').text('Processing...');
        $('#analysis-feedback').html('<span style="color:#000;">⏳ ANALYZING ZIP...</span>');
        var fd = new FormData();
        fd.append('action', 'bh_ajax_step_1_analyse_v268');
        fd.append('plugin_zip', fI.files[0]);
        fd.append('security', '<?php echo esc_js(wp_create_nonce('bh_step1_v268')); ?>');
        $.ajax({
            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            type: 'POST', data: fd, processData: false, contentType: false,
            success: function(r) {
                if (r.success) {
                    $('#analysis-feedback').html('<span style="color:green;">✅ SUCCESS! v' + r.data.version + '</span>');
                    setTimeout(function() { 
                        if(typeof window.bhGoToStep === 'function') window.bhGoToStep(2); 
                    }, 1000);
                } else { 
                    alert('Error: ' + r.data); $btn.css('opacity', '1').text('Start Analysis'); 
                }
            },
            error: function(x, s, e) {
                alert('AJAX Error: ' + s); $btn.css('opacity', '1').text('Start Analysis');
            }
        });
    }
    </script>
    <?php return ob_get_clean();
}

/**
 * Registrierung der Hilfsfunktionen für den Workspace-Ordner
 * Stellt sicher, dass das Verzeichnis für Schritt 2 bereit ist.
 */
function bh_ensure_v271_tmp_dir() {
    $dir = wp_upload_dir()['basedir'] . '/buildhub_tmp';
    if (!is_dir($dir)) wp_mkdir_p($dir);
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Options -Indexes\nDeny from all\n<Files ~ \"\.(zip)$\">\nAllow from all\n</Files>");
    }
}
bh_ensure_v271_tmp_dir();

/**
 * End of File - Line 98 erreicht.
 * Restoration complete.
 */
