<?php
/**
 * BuildHub Maker - Plugin Check (PCP) Integration
 * PRO Feature
 */
if ( ! defined( 'ABSPATH' ) ) exit;


add_action('wp_ajax_bh_run_plugin_check', function() {
    check_ajax_referer('bh_maker_secure_nonce', 'security');

    $pcp_file = WP_PLUGIN_DIR . '/plugin-check/plugin.php';
    if (!file_exists($pcp_file)) {
        wp_send_json_error('Plugin Check (PCP) is not installed.');
    }

    $files = bh_get_data('build_files');
    if (empty($files['f'])) {
        wp_send_json_error('No FREE ZIP. Run a build first.');
    }

    $up         = wp_upload_dir();
    $builds_dir = $up['basedir'] . '/buildhub_tmp/Builds/';
    $free_zip   = $builds_dir . basename($files['f']);

    if (!file_exists($free_zip)) {
        wp_send_json_error('FREE ZIP not found: ' . basename($files['f']));
    }

    // Slug aus Dateiname
    $base_name = pathinfo(basename($files['f']), PATHINFO_FILENAME);
    $free_slug = preg_replace('/-\d[\d\.]*$/', '', $base_name);

    // Temporär entpacken
    $tmp_dir = $up['basedir'] . '/buildhub_tmp/pcp_tmp_' . get_current_user_id();
    wm_recursive_rmdir($tmp_dir);
    wp_mkdir_p($tmp_dir);

    $zip = new ZipArchive();
    if ($zip->open($free_zip) !== true) {
        wp_send_json_error('Cannot open ZIP.');
    }
    $zip->extractTo($tmp_dir);
    $zip->close();

    // In WP Plugin-Ordner kopieren
    $plugin_tmp = WP_PLUGIN_DIR . '/' . $free_slug . '-pcptmp';
    if (is_dir($plugin_tmp)) wm_recursive_rmdir($plugin_tmp);

    $dirs   = glob($tmp_dir . '/*', GLOB_ONLYDIR);
    $source = !empty($dirs) ? $dirs[0] : $tmp_dir;
    wm_copy_dir($source, $plugin_tmp);
    wm_recursive_rmdir($tmp_dir);

    // Hauptdatei finden
    $main_file = null;
    foreach (glob($plugin_tmp . '/*.php') as $f) {
        $chunk = file_get_contents($f, false, null, 0, 2048);
        if (preg_match('/^\s*\*\s*Plugin Name:/m', $chunk)) {
            $main_file = basename($f);
            break;
        }
    }

    if (!$main_file) {
        wm_recursive_rmdir($plugin_tmp);
        wp_send_json_error('No main plugin file found.');
    }

    $plugin_basename = basename($plugin_tmp) . '/' . $main_file;

    // PCP Autoloader
    $autoload = WP_PLUGIN_DIR . '/plugin-check/vendor/autoload.php';
    if (file_exists($autoload)) require_once $autoload;

    // Report Header
    $lines   = [];
    $lines[] = '================================================================';
    $lines[] = 'BuildHub Maker - Plugin Check (PCP) Report';
    $lines[] = 'Plugin : ' . basename($files['f']);
    $lines[] = 'Date   : ' . gmdate('Y-m-d H:i:s') . ' UTC';
    $lines[] = '================================================================';
    $lines[] = '';

    $errors   = 0;
    $warnings = 0;

    // Übergib den vollen Pfad - Plugin_Request_Utility::is_directory_valid_plugin()
    // akzeptiert absolute Pfade ohne Installationsprüfung
    $plugin_full_path = $plugin_tmp;

    // AJAX_Runner liest $_REQUEST['plugin'] und $_REQUEST['action']
    $_REQUEST['plugin'] = $plugin_full_path;
    $_REQUEST['action'] = 'plugin_check_run_checks';
    $_POST['plugin']    = $plugin_full_path;

    try {
        $runner  = new WordPress\Plugin_Check\Checker\AJAX_Runner();
        $results = $runner->run();

        if (is_wp_error($results)) {
            $lines[] = 'PCP Error: ' . $results->get_error_message();
        } else {
            foreach ($results as $file_name => $file_results) {
                foreach ($file_results as $result) {
                    $type = ($result->type === 1) ? 'ERROR' : 'WARNING';
                    if ($result->type === 1) $errors++; else $warnings++;
                    $lines[] = sprintf('[%s] %s:%d - %s (%s)',
                        $type, $file_name, $result->line ?? 0,
                        $result->message, $result->code ?? '');
                }
            }
        }
    } catch (Throwable $e) {
        $lines[] = 'Exception: ' . $e->getMessage();
    }

    // REQUEST wiederherstellen
    $_REQUEST['action'] = 'bh_run_plugin_check';
    unset($_REQUEST['plugin'], $_POST['plugin']);

    $lines[] = '';
    $lines[] = '================================================================';
    $lines[] = 'SUMMARY: ' . $errors . ' Errors, ' . $warnings . ' Warnings';
    $lines[] = ($errors === 0) ? 'STATUS: READY (runtime checks only)' : 'STATUS: ISSUES FOUND';
    $lines[] = '----------------------------------------------------------------';
    $lines[] = 'NOTE: This report covers runtime/structural checks only.';
    $lines[] = 'For full PHPCS code analysis (escaping, nonce, I18n, deprecated';
    $lines[] = 'functions), run the manual Plugin Check in WP Admin > Tools.';
    $lines[] = '================================================================';

    wm_recursive_rmdir($plugin_tmp);

    $report_name = 'pcp-report-' . gmdate('YmdHis') . '.txt';
    file_put_contents($builds_dir . $report_name, implode("\n", $lines));

    wp_send_json_success([
        'report_file' => $report_name,
        'errors'      => $errors,
        'warnings'    => $warnings,
    ]);
});

