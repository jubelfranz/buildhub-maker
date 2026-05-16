<?php
/**
 * DEPLOYER v30.1 - UNKORRUMPIERBAR & URL CLEAN FIX
 * Path: /buildhub-maker/core/deployer.php
 * Text Domain: buildhub-maker-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'bh_dispatch_final_v30' ) ) {
    /**
     * Executes a Repository Dispatch to GitHub.
     * Guaranteed to build a clean API URL regardless of dataset formatting.
     */
    function bh_dispatch_final_v30($zip_path, $version, $dataset, $is_wporg = false) {
        
        $v = "30.1"; 
        @set_time_limit(600); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
        $debug_active = (get_option('bh_debug_mode', '0') === '1'); 
        $log_file = WP_CONTENT_DIR . '/plugins/buildhub-maker/ajax-debug.log';
        
        // 1. REPO-PFAD ABSOLUT REINIGEN
        $raw_repo = isset($dataset['GH_REPO']) ? $dataset['GH_REPO'] : (isset($dataset['repo']) ? $dataset['repo'] : '');
        
        // Entfernt ALLES was nach Domain, Protokoll oder Trennzeichen aussieht
        $clean_repo = str_replace(['https://', 'http://', 'github.com', '://github.com', 'repos', ':', '//'], '', $raw_repo);
        $clean_repo = trim($clean_repo, '/ ');
        
        // 2. URL-AUFBAU (Absolut Statisch ohne Variablen-Verklebung)
        $gh_url = "https://api.github.com/repos/" . $clean_repo . "/dispatches";

        // Sofort-Log für technische Transparenz
        if ($debug_active) {
            file_put_contents($log_file, "\n" . gmdate('H:i:s')." | [v$v] START EXECUTION\n", FILE_APPEND);
            file_put_contents($log_file, gmdate('H:i:s')." | [v$v] RAW FROM DB: " . (string)$raw_repo . "\n", FILE_APPEND);
            file_put_contents($log_file, gmdate('H:i:s')." | [v$v] TARGET URL: " . (string)$gh_url . "\n", FILE_APPEND);
        }

        $token = isset($dataset['GH_TOKEN_VAL']) ? trim($dataset['GH_TOKEN_VAL']) : '';

        // Payload Vorbereitung - event_type ZUERST setzen
        if ($is_wporg === 'github_only') {
            $event_type = 'push_to_github';
        } elseif ($is_wporg) {
            $event_type = 'deploy_to_wporg';
        } else {
            $event_type = 'deploy_to_freemius';
        }

        // Debug: Token-Anfang loggen (nie den ganzen Token!)
        file_put_contents(WP_CONTENT_DIR . '/bh-deploy-debug.log',
            gmdate('H:i:s') . " | token_start=" . substr($token, 0, 8) . "... len=" . strlen($token) . " url=" . $gh_url . " event=" . $event_type . "\n",
            FILE_APPEND
        );
        if (empty($token)) {
            return ['code' => 401, 'data' => __("No GitHub Token found in Dataset.", "buildhub-maker-pro")];
        }
        $plugin_name = isset($dataset['PLUGIN_NAME']) ? $dataset['PLUGIN_NAME'] : basename($zip_path);
        $zip_name    = basename($zip_path);
        $dl_token    = md5(AUTH_KEY . $zip_name);
        $dl_url      = admin_url('admin-ajax.php') . '?action=wm_download_file&file=' . rawurlencode($zip_name) . '&token=' . $dl_token;

        $payload = json_encode([
            'event_type' => (string)$event_type,
            'client_payload' => [
                'version'      => (string)$version,
                'plugin_id'    => (string)($dataset['FS_ID'] ?? '0'),
                'zip_name'     => $zip_name,
                'zip_url'      => $dl_url,
                'triggered_at' => gmdate('Y-m-d H:i:s'),
                'message'      => $plugin_name . ' v' . $version . ' has been submitted for review. Please check the latest commit on the free branch.',
            ]
        ]);

        // 3. WP HTTP API (WordPress-Standard)
        $response = wp_remote_post($gh_url, [
            'timeout'     => 30,
            'headers'     => [
                'Authorization' => 'token ' . $token,
                'Accept'        => 'application/vnd.github.v3+json',
                'User-Agent'    => 'BuildHub-V30',
                'Content-Type'  => 'application/json',
            ],
            'body'        => $payload,
            'sslverify'   => true,
        ]);

        if (is_wp_error($response)) {
            $err  = $response->get_error_message();
            $code = 0;
            $res  = '';
        } else {
            $err  = '';
            $code = wp_remote_retrieve_response_code($response);
            $res  = wp_remote_retrieve_body($response);
        }

        // Immer loggen
        file_put_contents(WP_CONTENT_DIR . '/bh-deploy-debug.log',
            gmdate('H:i:s') . " | code=$code res=" . substr($res ?? '', 0, 100) . "\n",
            FILE_APPEND
        );

        if ($debug_active) {
            file_put_contents($log_file, gmdate('H:i:s')." | [v$v] RESULT CODE: $code\n", FILE_APPEND);
            if($err) {
                file_put_contents($log_file, gmdate('H:i:s')." | [v$v] CURL ERROR: $err\n", FILE_APPEND);
            }
        }

        // GitHub sendet bei Erfolg 204 (No Content)
        if ($code === 204 || $code === 201 || $code === 200) {
            // translators: %s is the GitHub event type name.
        return ['code' => 200, 'data' => sprintf(__("GitHub Action (%s) triggered successfully!", "buildhub-maker-pro"), $event_type)];
        }

        // translators: %1$d is HTTP status code, %2$s is error message.
        /* translators: 1: HTTP status code, 2: error message */
        $msg = sprintf( __( 'GitHub API Error %1$d: %2$s', 'buildhub-maker-pro' ), (int) $code, wp_strip_all_tags( $res ) );
        return ['code' => $code, 'data' => $msg];
    }
}
