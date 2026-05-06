<?php
/**
 * Plugin Name: BuildHub Maker
 * Description: Professional tool for plugin transformation with Frontend Workspace.
 * Version: 1.1.4
 * Author: Franz Horvath
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1. DEFINITION DER PFADE
 */
$bh_maker_path = plugin_dir_path( __FILE__ );
if ( ! defined( 'BH_MAKER_PATH' ) ) {
    define( 'BH_MAKER_PATH', $bh_maker_path );
}

/**
 * 2. LADEN DER DATEIEN
 */
if ( file_exists( BH_MAKER_PATH . 'maker-config.php' ) ) {
    require_once BH_MAKER_PATH . 'maker-config.php';
}

require_once BH_MAKER_PATH . 'core/transformer.php';
// [PRO]
require_once BH_MAKER_PATH . 'core/deployer.php';
// [/PRO]
require_once BH_MAKER_PATH . 'admin/admin-menu.php';

/**
 * 3. ROUTING
 */
add_action('admin_post_bh_switch_project', function() {
    if ( ! current_user_can('manage_options') ) wp_die('No access.');
    $target = sanitize_text_field($_POST['bh_project_switch']);
    $url = remove_query_arg('bh_project', wp_get_referer());
    wp_redirect(add_query_arg('bh_project', $target, $url));
    exit;
});

function wm_generate_pages() {
    $pages = ['BuildHub Workspace' => '[buildhub_workspace]', 'BuildHub Maker Docs' => ''];
    foreach ($pages as $title => $content) {
        $query = new WP_Query(['post_type' => 'page', 'title' => $title, 'posts_per_page' => 1]);
        if ( ! $query->have_posts() ) {
            wp_insert_post(['post_title' => $title, 'post_content' => $content, 'post_status' => 'publish', 'post_type' => 'page', 'post_author' => 1]);
        }
    }
}
register_activation_hook(__FILE__, 'wm_generate_pages');

/**
 * 4. ADMIN MENU (Backend CRUD)
 */
add_action('admin_menu', function() {
    add_submenu_page(
        'options-general.php',
        'BuildHub Config Manager',
        'BuildHub Config',
        'manage_options',
        'buildhub-config',
        function() { require_once BH_MAKER_PATH . 'admin/project-manager.php'; }
    );
});

/**
 * 5. FRONTEND SHORTCODE
 */
add_shortcode('buildhub_workspace', function() {
    if ( ! current_user_can('manage_options') ) return 'Admin Login erforderlich.';
    add_action('wp_footer', 'wm_inject_footer_scripts', 99);
    ob_start();
    require_once BH_MAKER_PATH . 'admin/admin-ui.php';
    return ob_get_clean();
});

function wm_inject_footer_scripts() {
    $has_free = (defined('HAS_FREE') && HAS_FREE === true) ? 'true' : 'false';
    $project_key = defined('ACTIVE_PROJECT_KEY') ? ACTIVE_PROJECT_KEY : 'whoismember';
    $nonce = wp_create_nonce('bh_maker_secure_nonce');
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
        var secureNonce = "<?php echo $nonce; ?>";
        var hasFreeConfig = <?php echo $has_free; ?>;
        var currentFiles = [];
        var savedVersion = "";

        $(document).on('change', '#bh_debug_toggle', function() {
            var state = $(this).is(':checked') ? '1' : '0';
            $.post(ajaxurl, { action: 'bh_toggle_debug', security: secureNonce, debug: state });
        });

        $(document).on('click', '#bh_smtp_test', function(e) {
            e.preventDefault();
            var $link = $(this);
            $link.text('⏳ Sende...').css('color', 'orange');
            $.post(ajaxurl, { action: 'bh_smtp_test', security: secureNonce }, function(r) {
                alert(r.data);
                $link.text('📧 SMTP Test-Mail senden').css('color', '#0073aa');
            });
        });

        $(document).on('click', '#btn-analyze', function(e) {
            e.preventDefault();
            var fileInput = document.getElementById('wm_plugin_zip');
            if (!fileInput || !fileInput.files.length) return alert('Bitte ZIP wählen!');
            var fd = new FormData();
            fd.append('action', 'wm_build_process');
            fd.append('security', secureNonce);
            fd.append('plugin_zip', fileInput.files[0]);
            fd.append('analyze_only', '1');
            $(this).text('Analysiere...').prop('disabled', true);
            $.ajax({
                url: ajaxurl, type: 'POST', data: fd, contentType: false, processData: false,
                success: function(r) {
                    if (r.success) {
                        $('#step-1').hide();
                        var h = '<h2>2. Versionierung & Build</h2>' +
                                '<div style="background:#e7f5fe;padding:20px;border:1px solid #b3d4fc;">' +
                                '<label><strong>Ziel-Version:</strong></label><br>' +
                                '<input type="text" id="target_version" value="'+r.version+'" style="width:100px;text-align:center;font-size:1.2em;margin:10px 0;"> <br>' +
                                '<button id="btn-build" class="button" style="background:#fff; border:1px solid #0073aa; color:#0073aa; font-weight:bold; height:40px; padding: 0 20px;">Build starten</button></div>';
                        $('#wm-dynamic-area').html(h).show();
                    }
                }
            });
        });

        $(document).on('click', '#btn-build', function() {
            var fileInput = document.getElementById('wm_plugin_zip');
            savedVersion = $('#target_version').val(); 
            var fd = new FormData();
            fd.append('action', 'wm_build_process');
            fd.append('security', secureNonce);
            fd.append('plugin_zip', fileInput.files[0]);
            fd.append('target_version', savedVersion);
            fd.append('plugin_type', '<?php echo $project_key; ?>');
            $('#wm-dynamic-area').html('<p>🔨 Baue Pakete...</p>');
            $.ajax({
                url: ajaxurl, type: 'POST', data: fd, contentType: false, processData: false,
                success: function(r) {
                    if (r.success) {
                        currentFiles = r.files;
                        var html = '<h2>3. Downloads & Deployment</h2>' +
                                   '<div style="display:table; width:100%; border-collapse:separate; border-spacing:0 15px; margin-top:20px;">';
                        
                        if (hasFreeConfig) {
                            html += '<div style="display:table-row;"><div style="display:table-cell; width:50%; padding-right:15px;"><a href="admin-ajax.php?action=wm_download_file&file='+r.files[0].p+'" class="button" style="background:#fff; border:1px solid #0073aa; color:#0073aa; width:100%; text-align:center; height:45px; line-height:43px; font-weight:bold;">FREE ('+savedVersion+')</a></div>' +
                            // [PRO]
                            '<div style="display:table-cell; width:50%;"><button id="btn-deploy-wporg" class="button" style="background:#fff; border:1px solid #46b450; color:#46b450; width:100%; height:45px; font-weight:bold;">Sende an WP.ORG</button></div>' +
                            // [/PRO]
                            '</div>';
                        }
                        
                        html += '<div style="display:table-row;"><div style="display:table-cell; width:50%; padding-right:15px;"><a href="admin-ajax.php?action=wm_download_file&file='+r.files[r.files.length-1].p+'" class="button" style="background:#fff; border:1px solid #0073aa; color:#0073aa; width:100%; text-align:center; height:45px; line-height:43px; font-weight:bold;">PRO ('+savedVersion+')</a></div>' +
                        // [PRO]
                        '<div style="display:table-cell; width:50%;"><button id="btn-deploy-fs" class="button" style="background:#fff; border:1px solid #ffa500; color:#ffa500; width:100%; height:45px; font-weight:bold;">Sende an FREEMIUS</button></div>' +
                        // [/PRO]
                        '</div>';

                        html += '</div><div style="margin-top:40px;"><button onclick="location.reload()" class="button">🔄 NEU</button></div>';
                        $('#wm-dynamic-area').html(html);
                    }
                }
            });
        });

        // [PRO]
        $(document).on('click', '#btn-deploy-wporg', function() { deploy('wporg', this); });
        $(document).on('click', '#btn-deploy-fs', function() { deploy('freemius', this); });

        function deploy(target, btn) {
            var $btn = $(btn);
            $btn.text('Übertrage...').prop('disabled', true);
            var fileToDeploy = (target === 'wporg') ? currentFiles[0].p : currentFiles[currentFiles.length-1].p;
            $.post(ajaxurl, { action: 'wm_deploy_process', security: secureNonce, deploy_file: fileToDeploy, v: savedVersion, target: target, bh_project: '<?php echo $project_key; ?>' }, function(r) {
                alert('Ergebnis: ' + r.code);
                $btn.text('Senden').prop('disabled', false);
            });
        }
        // [/PRO]
    });
    </script>
    <?php
}

/**
 * 6. AJAX HANDLER
 */
add_action('wp_ajax_bh_toggle_debug', function() {
    check_ajax_referer('bh_maker_secure_nonce', 'security');
    update_option('bh_debug_mode', $_POST['debug'] === '1' ? '1' : '0');
    wp_send_json_success();
});

add_action('wp_ajax_bh_smtp_test', function() {
    check_ajax_referer('bh_maker_secure_nonce', 'security');
    $to = get_option('admin_email');
    $sent = wp_mail($to, "BuildHub Test", "Funktioniert!");
    wp_send_json_success($sent ? "Mail gesendet an $to" : "Fehler.");
});

add_action('wp_ajax_wm_build_process', function() {
    check_ajax_referer('bh_maker_secure_nonce', 'security');
    $result = wm_run_build_process($_FILES['plugin_zip'], $_POST['target_version'], $_POST['plugin_type']);
    wp_send_json($result);
});

// [PRO]
add_action('wp_ajax_wm_deploy_process', function() {
    check_ajax_referer('bh_maker_secure_nonce', 'security');
    $filename = basename($_POST['deploy_file']);
    $up = wp_upload_dir();
    $result = wm_deploy_to_freemius($up['basedir'] . '/buildhub_tmp/Builds/' . $filename, $_POST['v'], FS_ID, ($_POST['target'] === 'wporg'));
    wp_send_json($result);
});
// [/PRO]

add_action('wp_ajax_wm_download_file', 'wm_ajax_handler_download');
function wm_ajax_handler_download() {
    if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
    $filename = basename($_GET['file']);
    $up = wp_upload_dir();
    $file = $up['basedir'] . '/buildhub_tmp/Builds/' . $filename;
    if ( file_exists($file) ) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        readfile($file); exit;
    }
}
