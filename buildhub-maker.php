<?php
/**
 * Plugin Name: BuildHub Maker
 * Description: Professional tool for plugin transformation with Frontend Workspace.
 * Version: 2.0.2
 * Author: Franz Horvath
 * Text Domain: buildhub-maker-pro
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'BH_MAKER_PATH' ) ) define( 'BH_MAKER_PATH', plugin_dir_path( __FILE__ ) );

// Markiere welche Variante aktuell aktiv ist
$bh_my_slug = dirname( plugin_basename( __FILE__ ) );
if ( strpos( $bh_my_slug, '-pro' ) !== false ) {
    update_option( 'bh_active_variant', 'pro' );
} elseif ( strpos( $bh_my_slug, '-dev' ) !== false ) {
    update_option( 'bh_active_variant', 'dev' );
} else {
    update_option( 'bh_active_variant', 'free' );
}

// Warnings im AJAX-Context unterdrücken
if ( defined('DOING_AJAX') && DOING_AJAX ) {
    @ini_set('display_errors', 0); // phpcs:ignore WordPress.PHP.NoSilencedErrors, Squiz.PHP.DiscouragedFunctions
    error_reporting(E_ERROR); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting
}

require_once BH_MAKER_PATH . 'core/transformer.php';

require_once BH_MAKER_PATH . 'core/deployer.php';

require_once BH_MAKER_PATH . 'inc/step-1-preparation.php';
require_once BH_MAKER_PATH . 'inc/step-2-versioning.php';
require_once BH_MAKER_PATH . 'inc/step-3-deployment.php';

if (file_exists(BH_MAKER_PATH . 'inc/pcp-check.php')) require_once BH_MAKER_PATH . 'inc/pcp-check.php';


if ( ! function_exists( 'bh_get_clean_projects' ) ) {
function bh_get_clean_projects() {
    $projects = get_option('bh_projects_db');
    if ( ! is_array($projects) || empty($projects) ) {
        $file = BH_MAKER_PATH . 'maker-config.php';
        if ( file_exists($file) ) {
            include $file;
            if (isset($bh_projects)) {
                $projects = array_values($bh_projects);
                update_option('bh_projects_db', $projects);
            }
        }
    }
    return array_values(array_filter((array)$projects));
}
}

global $bh_projects;
$bh_projects = bh_get_clean_projects();

$active_slug = get_option('bh_active_project_slug');
if ( isset($_REQUEST['bh_project']) ) { // phpcs:ignore WordPress.Security.NonceVerification
    $active_slug = sanitize_key(wp_unslash($_REQUEST['bh_project'])); // phpcs:ignore WordPress.Security.NonceVerification
    update_option('bh_active_project_slug', $active_slug);
}
if ( empty($active_slug) && !empty($bh_projects) ) {
    $active_slug = isset($bh_projects[0]['PLUGIN_DOMAIN']) ? $bh_projects[0]['PLUGIN_DOMAIN'] : '';
    update_option('bh_active_project_slug', $active_slug);
}
if ( ! defined('ACTIVE_PROJECT_KEY') ) define('ACTIVE_PROJECT_KEY', $active_slug);



if ( ! function_exists( 'bh_set_data' ) ) {
function bh_set_data($key, $value) {
    $option = 'bh_ws_v2_' . get_current_user_id() . '_' . $key;
    update_option($option, $value, false);
    wp_cache_delete($option, 'options');
}
}

if ( ! function_exists( 'bh_get_data' ) ) {
function bh_get_data($key) {
    return get_option('bh_ws_v2_' . get_current_user_id() . '_' . $key);
}
}

if ( ! function_exists( 'bh_ensure_workspace_page' ) ) {
function bh_ensure_workspace_page() {
    $page_check = get_posts(array(
        'post_type'   => 'page',
        'title'       => 'BuildHub Workspace',
        'post_status' => array('publish', 'draft', 'pending', 'trash'),
        'numberposts' => 1
    ));
    if (!empty($page_check)) {
        $p = $page_check[0];
        if ($p->post_status !== 'publish') {
            wp_update_post(array('ID' => $p->ID, 'post_status' => 'publish', 'post_name' => 'buildhub-workspace'));
            return true;
        }
        return false;
    }
    wp_insert_post(array(
        'post_title'   => 'BuildHub Workspace',
        'post_name'    => 'buildhub-workspace',
        'post_content' => '[buildhub_workspace]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_author'  => get_current_user_id()
    ));
    return true;
}
}


// MU-Plugin installieren + Redirect wenn nötig
add_action('admin_init', function() {
    $mu_dir   = WPMU_PLUGIN_DIR;
    $mu_file  = $mu_dir . '/buildhub-conflict-guard.php';
    $template = BH_MAKER_PATH . 'inc/mu-guard-template.php';
    if (!file_exists($template)) return;
    if (!is_dir($mu_dir)) wp_mkdir_p($mu_dir);

    $code     = file_get_contents($template);
    $updated  = false;

    if (!file_exists($mu_file) || md5_file($mu_file) !== md5($code)) {
        file_put_contents($mu_file, $code);
        $updated = true;
    }

    // Wenn MU-Plugin gerade installiert/aktualisiert wurde:
    // Redirect damit es beim nächsten Request aktiv ist
    if ($updated) {
        wp_safe_redirect(admin_url('plugins.php'));
        exit;
    }
});

// Admin Notice wenn FREE und PRO gleichzeitig aktiv sind
add_action('admin_notices', function() {
    $my_slug      = dirname(plugin_basename(__FILE__));
    $base_slug    = preg_replace('/-(?:dev|pro)$/', '', $my_slug);
    $pro_basename = $base_slug . '-pro/' . $base_slug . '-pro.php';
    if (!is_plugin_active($pro_basename)) return;
    $deactivate_url = wp_nonce_url(
        admin_url('plugins.php?action=deactivate&plugin=' . rawurlencode($pro_basename)),
        'deactivate-plugin_' . $pro_basename
    );
    echo '<div class="notice notice-warning"><p>';
    echo '<strong>BuildHub Maker:</strong> ';
    esc_html_e('FREE and PRO versions are both active. Please deactivate one of them.', 'buildhub-maker-pro');
    echo ' <a href="' . esc_url($deactivate_url) . '" class="button button-small">';
    esc_html_e('Deactivate PRO now', 'buildhub-maker-pro');
    echo '</a></p></div>';
});

add_action('admin_menu', function() {
    $icon_svg = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCI+PHBhdGggZmlsbD0iI2EwYTljYyIgZD0iTTE3LjIxIDkuNDFsLTMuMzktMy4zOWMtLjY0LS42NC0xLjUtLjY0LTIuMTIgMGwtNS42NSA1LjY2Yy0uNjQuNjQtLjY0IDEuNSAwIDIuMTJsMy4zOSAzLjM5Yy42NC42NCAxLjUuNjQgMi4xMiAwbDUuNjYtNS42NWMuNjMtLjY0LjYzLTEuNS0uMDEtMi4xMnpNNC45MyAxOS4wMmwtLjQyLS40MmMtLjU5LS41OS0uNTktMS41NCAwLTIuMTNsMi4xMi0yLjEybC40Mi40MmMuNTkuNTkuNTkgMS41NCAwIDIuMTNsLTIuMTIgMi4xMnoiLz48L3N2Zz4=';
    add_menu_page(__('BuildHub Maker', 'buildhub-maker-pro'), __('BuildHub Maker', 'buildhub-maker-pro'), 'manage_options', 'buildhub-maker-pro', function() {
        include BH_MAKER_PATH . 'admin/system-check.php';
    }, $icon_svg, 80);
    add_submenu_page('buildhub-maker-pro', __('Configuration', 'buildhub-maker-pro'), __('Configuration', 'buildhub-maker-pro'), 'manage_options', 'buildhub-config', function() {
        echo '<div class="wrap" style="background:#fff; padding:20px;">';
        include BH_MAKER_PATH . 'admin/config-manager.php';
        echo '</div>';
    });
});

add_action('wp_enqueue_scripts', function() {
    if (!is_page()) return;
    global $post;
    if (!$post || !has_shortcode($post->post_content, 'buildhub_workspace')) return;
    wp_enqueue_script('bh-workspace', plugins_url('assets/js/workspace.js', __FILE__), ['jquery'], '1.1', true);
    wp_localize_script('bh-workspace', 'bhWorkspace', [
        'ajaxurl'  => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('bh_maker_secure_nonce'),
        'projects' => array_values((array)get_option('bh_projects_db', [])),
        'author'   => get_bloginfo('name'),
        'uri'      => get_bloginfo('url'),
    ]);
});

add_shortcode('buildhub_workspace', function() {
    if ( ! current_user_can('manage_options') ) return __('Restricted Access.', 'buildhub-maker-pro');
    if ( is_admin() && ! defined('DOING_AJAX') ) return '<div style="padding:20px; border:2px dashed #ccc; text-align:center;">BuildHub Workspace Preview</div>';
    add_action('wp_footer', 'wm_inject_footer_scripts', 99);
    ob_start();
    include BH_MAKER_PATH . 'admin/admin-ui.php';
    return ob_get_clean();
});

if ( ! function_exists( 'wm_inject_footer_scripts' ) ) {
function wm_inject_footer_scripts() {
    global $post;
    if (!$post || !has_shortcode($post->post_content, 'buildhub_workspace')) return;
    $nonce = wp_create_nonce('bh_maker_secure_nonce');
    ?>
<script type="text/javascript">
(function($) {
    $(document).ready(function() {
        var ajaxurl = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";
        var secureNonce = "<?php echo esc_js($nonce); ?>";
        window.bhGoToStep = function(step) {
            $('#bh-pm-panel').attr('style', 'display:none !important');
            $('#bh-steps-panel').attr('style', 'display:block !important');
            $('.bh-step-container').hide().removeClass('bh-step-active');
            var $box = $('#step-' + step + '-box');
            $box.find('.bh-step-content').html('<p style="text-align:center;padding:40px;font-weight:bold;">&#9203; Loading...</p>');
            $box.show().addClass('bh-step-active');
            $.post(ajaxurl, { action: 'bh_get_step_html', step: step, security: secureNonce }, function(r) {
                if (r.success) $box.find('.bh-step-content').html(r.data.html);
            });
            var guideStep = step === 2 ? 'STEP-ANALYZED' : (step === 3 ? 'STEP-FINISHED' : 'STEP-IDLE');
            $.post(ajaxurl, { action: 'bh_get_readme_step', security: secureNonce, step: guideStep }, function(r) {
                if (r.success) $('#bh-readme-context').html(r.data.html);
            });
        };
    });
})(jQuery);
</script>
<?php
}
}

add_action('wp_ajax_bh_get_step_html', function() {
    check_ajax_referer('bh_maker_secure_nonce', 'security');
    $step = intval($_POST['step'] ?? 0);
    $html = '';
    if ($step === 2) $html = do_shortcode('[bh-2-versioning]');
    elseif ($step === 3) $html = do_shortcode('[bh-3-deployment]');
    wp_send_json_success(['html' => $html]);
});

add_action('wp_ajax_bh_get_readme_step', function() {
    check_ajax_referer('bh_maker_secure_nonce', 'security');
    $step = sanitize_text_field(wp_unslash($_POST['step'] ?? ''));
    $content = @file_get_contents(BH_MAKER_PATH . 'admin/workspace-guide.txt');
    preg_match('~\\[' . $step . '\\](.*?)\\[/' . $step . '\\]~s', $content, $matches);
    wp_send_json_success(['html' => nl2br(trim($matches[1] ?? 'Guide section missing.'))]);
});

add_action('wp_ajax_bh_fix_workspace_page', function() {
    check_ajax_referer('bh_maker_secure_nonce', 'security');
    if (bh_ensure_workspace_page()) wp_send_json_success(__('Workspace page restored.', 'buildhub-maker-pro'));
    else wp_send_json_error(__('Workspace page already exists.', 'buildhub-maker-pro'));
});

add_action('wp_ajax_bh_ajax_step_1_analyse_v268', function() {
    if (!isset($_FILES) || empty($_FILES)) wp_send_json_error('No FILES received.');
    $nonce_ok = check_ajax_referer('bh_step1_v268', 'security', false);
    if (!$nonce_ok) wp_send_json_error('Nonce invalid.');
    if (!isset($_FILES['plugin_zip']) || empty($_FILES['plugin_zip']['tmp_name'])) wp_send_json_error('No file received.');
    $tmp = isset($_FILES['plugin_zip']['tmp_name']) ? $_FILES['plugin_zip']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== TRUE) wp_send_json_error('ZIP open failed.');
    $version = '1.0.0';
    $found_file = '';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!preg_match('/\\.php$/i', $name)) continue;
        if (substr_count($name, '/') > 1) continue;
        if (stripos(basename($name), 'index') !== false) continue;
        $chunk = $zip->getFromIndex($i, 8192);
        if ($chunk === false) continue;
        if (preg_match('/^\\s*\\*\\s*Plugin Name:/m', $chunk)) {
            $found_file = $name;
            if (preg_match('/^\\s*\\*?\\s*Version:\\s*([0-9][0-9\\.]*)\\s*$/im', $chunk, $m)) $version = trim($m[1]);
            break;
        }
    }
    $zip->close();
    if (empty($found_file)) wp_send_json_error('No Plugin Name found in ZIP.');
    $upload_dir = wp_upload_dir();
    $base_path = $upload_dir['basedir'] . '/buildhub_tmp';
    wp_mkdir_p($base_path);
    $persistent = $base_path . '/source_' . get_current_user_id() . '.zip';
    copy($tmp, $persistent);
    bh_set_data('current_version', $version);
    bh_set_data('tmp_zip_path', $persistent);
    wp_send_json_success(['version' => $version, 'tmp_path' => $persistent, 'main_file' => $found_file]);
});

add_action('wp_ajax_bh_ajax_step_2_build_v258', function() {
    check_ajax_referer('bh_step2_v258', 'security');
    $tmp_zip = bh_get_data('tmp_zip_path');
    if (!$tmp_zip || !file_exists($tmp_zip)) wp_send_json_error('Source ZIP missing.');
    $target_version = sanitize_text_field(wp_unslash($_POST['target_version'] ?? ''));
    $upload_dir = wp_upload_dir();
    $base_path = $upload_dir['basedir'] . '/buildhub_tmp';
    $builds_path = $base_path . '/Builds';
    if (is_dir($builds_path)) {
        foreach (glob($builds_path . '/*.zip') as $old_zip) wp_delete_file($old_zip);
    }
    foreach (glob($base_path . '/extract_*') as $dir) wm_recursive_rmdir($dir);
    foreach (glob($base_path . '/pro_*') as $dir) wm_recursive_rmdir($dir);
    foreach (glob($base_path . '/free_*') as $dir) wm_recursive_rmdir($dir);
    $dataset = null;
    foreach (get_option('bh_projects_db', []) as $proj) {
        if (isset($proj['PLUGIN_DOMAIN']) && $proj['PLUGIN_DOMAIN'] === ACTIVE_PROJECT_KEY) { $dataset = $proj; break; }
    }
    $result = wm_run_build_process($tmp_zip, $target_version, ACTIVE_PROJECT_KEY, false, $dataset);
    if ($result['success']) {
        bh_set_data('build_files', $result['data']['files']);
        bh_set_data('final_version', $target_version);
        bh_set_data('current_version', $target_version);
        $response_data = $result['data'];
        $cf = isset($result['data']['compliance_fixes']) ? (int)$result['data']['compliance_fixes'] : 0;
        $response_data['compliance_fixes'] = $cf;
        set_transient('bh_compliance_fixes_' . get_current_user_id(), $cf, 300);
        wp_send_json_success($response_data);
    } else {
        wp_send_json_error($result['data']);
    }
});


add_action('wp_ajax_wm_deploy_process', function() {
    check_ajax_referer('bh_maker_secure_nonce', 'security');
    $dataset = null;
    $bh_project = sanitize_key(wp_unslash($_POST['bh_project'] ?? ''));
    foreach (get_option('bh_projects_db', []) as $proj) {
        if ($proj['PLUGIN_DOMAIN'] === $bh_project) { $dataset = $proj; break; }
    }
    if (!$dataset) wp_send_json_error(__('Dataset not found.', 'buildhub-maker-pro'));
    $up = wp_upload_dir();
    $deploy_file = sanitize_file_name(wp_unslash($_POST['deploy_file'] ?? ''));
    $v = sanitize_text_field(wp_unslash($_POST['v'] ?? ''));
    $target = sanitize_text_field(wp_unslash($_POST['target'] ?? ''));
    $res = bh_dispatch_final_v30($up['basedir'] . '/buildhub_tmp/Builds/' . $deploy_file, $v, $dataset, $target === 'wporg' ? true : ($target === 'github_only' ? 'github_only' : false));
    if ($res['code'] == 200) wp_send_json_success($res['data']); else wp_send_json_error($res['data']);
});



add_action('wp_ajax_bh_init_github_repo', function() {
    check_ajax_referer('bh_maker_secure_nonce', 'security');
    $dataset = null;
    $bh_project = sanitize_key(wp_unslash($_POST['bh_project'] ?? ''));
    foreach (get_option('bh_projects_db', []) as $proj) {
        if ($proj['PLUGIN_DOMAIN'] === $bh_project) { $dataset = $proj; break; }
    }
    if (!$dataset) wp_send_json_error('Dataset not found.');

    $up          = wp_upload_dir();
    $deploy_file = sanitize_file_name(wp_unslash($_POST['deploy_file'] ?? ''));
    $zip_path    = $up['basedir'] . '/buildhub_tmp/Builds/' . $deploy_file;
    if (!file_exists($zip_path)) wp_send_json_error('ZIP not found: ' . $deploy_file);

    $token    = trim($dataset['GH_TOKEN_VAL'] ?? '');
    $repo     = trim($dataset['GH_REPO'] ?? '');
    $version  = trim(bh_get_data('final_version') ?? '1.0.0');

    if (empty($token)) wp_send_json_error('GitHub Token missing.');
    if (empty($repo))  wp_send_json_error('GitHub Repo missing.');

    // Debug Log
    $init_log = WP_CONTENT_DIR . '/bh-init-debug.log';
    file_put_contents($init_log, gmdate('H:i:s') . " | repo=$repo zip=$deploy_file zip_exists=" . (file_exists($zip_path)?'YES':'NO') . " zip_size=" . (file_exists($zip_path)?filesize($zip_path):0) . "\n", FILE_APPEND);

    // Nur Workflow pushen — ZIP wird vom Workflow via zip_url heruntergeladen
    $skip_zip_upload = true;
    $has_free = !empty($dataset['HAS_FREE']);

    // free-Branch anlegen falls HAS_FREE
    if ($has_free) {
        $free_check = wp_remote_get('https://api.github.com/repos/' . $repo . '/git/ref/heads/free', [
            'headers' => $gh_headers
        ]);
        if (is_wp_error($free_check) || wp_remote_retrieve_response_code($free_check) === 404) {
            $main_ref = wp_remote_get('https://api.github.com/repos/' . $repo . '/git/ref/heads/main', ['headers' => $gh_headers]);
            if (!is_wp_error($main_ref) && wp_remote_retrieve_response_code($main_ref) === 200) {
                $ref_data = json_decode(wp_remote_retrieve_body($main_ref), true);
                $sha      = $ref_data['object']['sha'] ?? '';
                if ($sha) {
                    $r = wp_remote_post('https://api.github.com/repos/' . $repo . '/git/refs', [
                        'headers' => $gh_headers,
                        'body'    => wp_json_encode(['ref' => 'refs/heads/free', 'sha' => $sha]),
                    ]);
                    $results[] = 'Branch free: HTTP ' . wp_remote_retrieve_response_code($r);
                }
            }
        } else {
            $results[] = 'Branch free: already exists';
        }
    }

    // Workflow-Datei Name aus Repo-Slug ableiten
    $repo_slug    = basename($repo);
    $workflow_file = 'deploy-freemius-' . $repo_slug . '.yml';
    $workflow_path = BH_MAKER_PATH . 'inc/github-workflows/' . $workflow_file;

    // Falls kein spezifischer Workflow vorhanden, generischen verwenden
    if (!file_exists($workflow_path)) {
        $workflow_path = BH_MAKER_PATH . 'inc/github-workflows/deploy-freemius-generic.yml';
    }
    if (!file_exists($workflow_path)) {
        wp_send_json_error('Workflow template not found: ' . $workflow_file);
    }

    $gh_api  = 'https://api.github.com/repos/' . $repo . '/contents/';
    $headers = [
        'Authorization: token ' . $token,
        'Accept: application/vnd.github.v3+json',
        'User-Agent: BuildHub-Maker',
        'Content-Type: application/json',
    ];

    $results = [];

    // 1. ZIP in pro Branch pushen
    $zip_content  = base64_encode(file_get_contents($zip_path));
    $zip_filename = 'plugin-pro.zip';
    $existing_sha = '';
    $gh_headers   = [
        'Authorization' => 'token ' . $token,
        'Accept'        => 'application/vnd.github.v3+json',
        'User-Agent'    => 'BuildHub-Maker',
        'Content-Type'  => 'application/json',
    ];

    // Prüfe ob pro Branch existiert, sonst erstellen
    $branch_check = wp_remote_get('https://api.github.com/repos/' . $repo . '/git/ref/heads/main', [
        'headers' => $gh_headers
    ]);
    if (is_wp_error($branch_check) || wp_remote_retrieve_response_code($branch_check) === 404) {
        // Branch existiert nicht — erstelle ihn von main/master
        $default_ref = wp_remote_get('https://api.github.com/repos/' . $repo . '/git/ref/heads/main', ['headers' => $gh_headers]);
        if (is_wp_error($default_ref) || wp_remote_retrieve_response_code($default_ref) === 404) {
            $default_ref = wp_remote_get('https://api.github.com/repos/' . $repo . '/git/ref/heads/master', ['headers' => $gh_headers]);
        }
        if (!is_wp_error($default_ref) && wp_remote_retrieve_response_code($default_ref) === 200) {
            $ref_data = json_decode(wp_remote_retrieve_body($default_ref), true);
            $sha      = $ref_data['object']['sha'] ?? '';
            if ($sha) {
                wp_remote_post('https://api.github.com/repos/' . $repo . '/git/refs', [
                    'headers' => $gh_headers,
                    'body'    => wp_json_encode(['ref' => 'refs/heads/main', 'sha' => $sha]),
                ]);
                $results[] = 'Branch main created';
            }
        }
    }

    // Prüfe ob Datei bereits existiert
    $check = wp_remote_get($gh_api . $zip_filename . '?ref=pro', ['headers' => $gh_headers]);
    if (!is_wp_error($check) && wp_remote_retrieve_response_code($check) === 200) {
        $check_data   = json_decode(wp_remote_retrieve_body($check), true);
        $existing_sha = $check_data['sha'] ?? '';
    }

    $zip_body = ['message' => 'Update plugin-pro.zip v' . $version, 'content' => $zip_content, 'branch' => 'main'];
    if ($existing_sha) $zip_body['sha'] = $existing_sha;

    if (!$skip_zip_upload) $zip_response = wp_remote_request($gh_api . $zip_filename, [
        'method'  => 'PUT',
        'headers' => array_combine(
            ['Authorization', 'Accept', 'User-Agent', 'Content-Type'],
            ['token ' . $token, 'application/vnd.github.v3+json', 'BuildHub-Maker', 'application/json']
        ),
        'body' => wp_json_encode($zip_body),
        'timeout' => 60,
    ]);
    $zip_code = $skip_zip_upload ? 201 : wp_remote_retrieve_response_code($zip_response);
    if (!$skip_zip_upload) $results[] = 'ZIP push: HTTP ' . $zip_code;

    // 2. Workflow-Datei in .github/workflows/ pushen
    $wf_content  = base64_encode(file_get_contents($workflow_path));
    $wf_path     = '.github/workflows/deploy-freemius.yml';

    // push-to-github Workflow auch pushen wenn HAS_FREE
    if ($has_free) {
        $push_wf_template = BH_MAKER_PATH . 'inc/github-workflows/push-to-github.yml';
        if (file_exists($push_wf_template)) {
            $push_wf_content = base64_encode(file_get_contents($push_wf_template));
            $push_wf_path    = '.github/workflows/push-to-github.yml';
            $push_wf_sha     = '';
            $push_check = wp_remote_get($gh_api . rawurlencode($push_wf_path) . '?ref=main', ['headers' => $gh_headers]);
            if (!is_wp_error($push_check) && wp_remote_retrieve_response_code($push_check) === 200) {
                $push_data   = json_decode(wp_remote_retrieve_body($push_check), true);
                $push_wf_sha = $push_data['sha'] ?? '';
            }
            $push_body = ['message' => 'Add push-to-github workflow', 'content' => $push_wf_content, 'branch' => 'main'];
            if ($push_wf_sha) $push_body['sha'] = $push_wf_sha;
            $push_r    = wp_remote_request($gh_api . rawurlencode($push_wf_path), [
                'method'  => 'PUT',
                'headers' => $gh_headers,
                'body'    => wp_json_encode($push_body),
                'timeout' => 30,
            ]);
            $results[] = 'Push workflow: HTTP ' . wp_remote_retrieve_response_code($push_r);
        }
    }
    $wf_sha      = '';

    $wf_check = wp_remote_get($gh_api . rawurlencode($wf_path) . '?ref=main', [
        'headers' => array_combine(
            ['Authorization', 'Accept', 'User-Agent'],
            ['token ' . $token, 'application/vnd.github.v3+json', 'BuildHub-Maker']
        )
    ]);
    if (!is_wp_error($wf_check) && wp_remote_retrieve_response_code($wf_check) === 200) {
        $wf_data = json_decode(wp_remote_retrieve_body($wf_check), true);
        $wf_sha  = $wf_data['sha'] ?? '';
    }

    $wf_body = ['message' => 'Add Freemius deploy workflow', 'content' => $wf_content, 'branch' => 'main'];
    if ($wf_sha) $wf_body['sha'] = $wf_sha;

    $wf_response = wp_remote_request($gh_api . rawurlencode($wf_path), [
        'method'  => 'PUT',
        'headers' => array_combine(
            ['Authorization', 'Accept', 'User-Agent', 'Content-Type'],
            ['token ' . $token, 'application/vnd.github.v3+json', 'BuildHub-Maker', 'application/json']
        ),
        'body' => wp_json_encode($wf_body),
        'timeout' => 30,
    ]);
    $wf_code = wp_remote_retrieve_response_code($wf_response);
    $results[] = 'Workflow push: HTTP ' . $wf_code;

    if ($zip_code >= 200 && $zip_code < 300 && $wf_code >= 200 && $wf_code < 300) {
        file_put_contents($init_log, gmdate('H:i:s') . " | SUCCESS zip=$zip_code wf=$wf_code\n", FILE_APPEND);
        wp_send_json_success(['message' => 'Repo initialized!', 'details' => $results]);
    } else {
        file_put_contents($init_log, gmdate('H:i:s') . " | FAILED zip=$zip_code wf=$wf_code results=" . implode(', ', $results) . "\n", FILE_APPEND);
        wp_send_json_error(['message' => 'Init failed', 'details' => $results]);
    }
});



add_action('wp_ajax_bh_save_dataset', function() {
    check_ajax_referer('bh_maker_secure_nonce', 'security');
    $slug = sanitize_key(wp_unslash($_POST['PLUGIN_DOMAIN'] ?? ''));
    if (empty($slug)) wp_send_json_error('Slug missing.');
    $projects = array_values(array_filter((array)get_option('bh_projects_db', [])));
    $mode  = sanitize_text_field(wp_unslash($_POST['mode'] ?? 'edit'));
    $p_idx = intval(wp_unslash($_POST['p_idx'] ?? -1));
    $is_new = ($mode === 'new' || $p_idx < 0 || !isset($projects[$p_idx]));
    $new_data = [
        'PLUGIN_NAME'   => sanitize_text_field(wp_unslash($_POST['PLUGIN_NAME'] ?? '')),
        'PLUGIN_DOMAIN' => $slug,
        'PAID_SLUG'     => sanitize_text_field(wp_unslash($_POST['PAID_SLUG'] ?? '')),
        'GH_REPO'       => sanitize_text_field(wp_unslash($_POST['GH_REPO'] ?? '')),
        'GH_TOKEN_VAL'  => sanitize_text_field(wp_unslash($_POST['GH_TOKEN_VAL'] ?? '')),
        'FS_ID'         => sanitize_text_field(wp_unslash($_POST['FS_ID'] ?? '')),
        'FS_TOKEN'      => sanitize_text_field(wp_unslash($_POST['FS_TOKEN'] ?? '')),
        'PLUGIN_AUTHOR' => sanitize_text_field(wp_unslash($_POST['PLUGIN_AUTHOR'] ?? '')),
        'PLUGIN_URI'    => esc_url_raw(wp_unslash($_POST['PLUGIN_URI'] ?? '')),
        'HAS_FREE'      => ( sanitize_text_field(wp_unslash($_POST['HAS_FREE'] ?? '0')) ) === '1',
    ];
    if ($is_new) $projects[] = $new_data; else $projects[$p_idx] = $new_data;
    update_option('bh_projects_db', array_values($projects));
    update_option('bh_active_project_slug', $slug);
    wp_send_json_success(['slug' => $slug]);
});

add_action('wp_ajax_bh_delete_dataset', function() {
    check_ajax_referer('bh_maker_secure_nonce', 'security');
    $idx = intval(wp_unslash($_POST['p_idx'] ?? -1));
    $projects = array_values(array_filter((array)get_option('bh_projects_db', [])));
    if ($idx < 0 || !isset($projects[$idx])) wp_send_json_error('Not found.');
    array_splice($projects, $idx, 1);
    update_option('bh_projects_db', array_values($projects));
    if (!empty($projects[0]['PLUGIN_DOMAIN'])) update_option('bh_active_project_slug', $projects[0]['PLUGIN_DOMAIN']);
    wp_send_json_success();
});

add_action('wp_ajax_bh_switch_project', function() {
    check_ajax_referer('bh_maker_secure_nonce', 'security');
    $slug = sanitize_key(wp_unslash($_POST['slug'] ?? ''));
    if ($slug) update_option('bh_active_project_slug', $slug);
    wp_send_json_success();
});

add_action('wp_ajax_bh_toggle_debug', function() {
    check_ajax_referer('bh_maker_secure_nonce', 'security');
    update_option('bh_debug_mode', sanitize_text_field(wp_unslash($_POST['debug'] ?? '0')));
    wp_send_json_success();
});

add_action('wp_ajax_bh_smtp_test', function() {
    check_ajax_referer('bh_maker_secure_nonce', 'security');
    $sent = wp_mail(get_option('admin_email'), __('BuildHub Connectivity', 'buildhub-maker-pro'), __('SMTP is working correctly.', 'buildhub-maker-pro'));
    wp_send_json_success($sent ? __('Test email sent.', 'buildhub-maker-pro') : __('SMTP delivery failed.', 'buildhub-maker-pro'));
});

add_action('wp_ajax_bh_export_projects', function() {
    check_ajax_referer('bh_maker_secure_nonce', 'security');
    if (!current_user_can('manage_options')) exit;
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="buildhub-backup-' . gmdate('Y-m-d') . '.json"');
    echo json_encode(get_option('bh_projects_db', []), JSON_PRETTY_PRINT);
    exit;
});

add_action('wp_ajax_bh_import_projects', function() {
    check_ajax_referer('bh_maker_secure_nonce', 'security');
    $data = json_decode(sanitize_text_field(wp_unslash($_POST['import_data'] ?? '')), true);
    if (is_array($data)) {
        update_option('bh_projects_db', array_values(array_filter($data)));
        wp_send_json_success(__('Backup restored.', 'buildhub-maker-pro'));
    }
});

// Download via direktem Link mit Nonce (funktioniert auch auf iOS)
add_action('wp_ajax_wm_download_file', 'bh_handle_download');
add_action('wp_ajax_nopriv_wm_download_file', 'bh_handle_download');

if ( ! function_exists( 'bh_handle_download' ) ) {
function bh_handle_download() {
    // Token-basierte Authentifizierung für nopriv
    $token = sanitize_text_field(wp_unslash($_GET['token'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification
    $file_name = sanitize_file_name(wp_unslash($_GET['file'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification

    // Prüfe Token ODER eingeloggt
    $valid = false;
    if (is_user_logged_in() && current_user_can('manage_options')) {
        $valid = true;
    } elseif (!empty($token) && !empty($file_name)) {
        // Token = md5(secret + filename) - server-side verification
        $expected = md5(AUTH_KEY . $file_name);
        $valid = hash_equals($expected, $token);
    }

    if (!$valid) {
        wp_die('Unauthorized', 403);
    }

    $up   = wp_upload_dir();
    $file = $up['basedir'] . '/buildhub_tmp/Builds/' . $file_name;

    if (!file_exists($file)) {
        wp_die('File not found', 404);
    }

    $filename = basename($file);
    nocache_headers();
    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . filesize($file));
    header('Pragma: public');
    ob_end_clean();
    readfile($file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
    exit;
}
}

// Delete FREE version when PRO is activated
function bh_pro_delete_free_buildhub_maker() {
    if ( ! function_exists( 'deactivate_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    $free = 'buildhub-maker/buildhub-maker.php';
    if ( is_plugin_active( $free ) ) deactivate_plugins( $free );
    if ( function_exists( 'delete_plugins' ) ) delete_plugins( [ $free ] );
}
register_activation_hook( __FILE__, 'bh_pro_delete_free_buildhub_maker' );
