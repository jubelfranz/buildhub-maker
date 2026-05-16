<?php
/**
 * BuildHub Transformer Core
 * Version: 2.8.0
 *
 * Changelog:
 * - 2.8.0: FREE/PRO Trennung via [PRO]...[/PRO]-Tags
 *           Author-Injection aus Dataset
 *           Stable Tag Sync in readme.txt
 *           Korrekte Ordner-Struktur im ZIP (slug als Root-Verzeichnis)
 * - 2.7.4: Regex-Fix fuer robustes Versions-Auslesen und -Patchen
 */

if ( ! defined( 'ABSPATH' ) ) exit;


// =============================================================================
// HAUPT-FUNKTION
// =============================================================================

function wm_run_build_process($file_input, $target_version = '', $plugin_type = '', $analyze_only = false, $dataset = null) {
    @set_time_limit(0); // phpcs:ignore WordPress.PHP.NoSilencedErrors, Squiz.PHP.DiscouragedFunctions
    @ini_set('memory_limit', '512M'); // phpcs:ignore WordPress.PHP.NoSilencedErrors, Squiz.PHP.DiscouragedFunctions
    @ini_set('max_execution_time', 0); // phpcs:ignore WordPress.PHP.NoSilencedErrors, Squiz.PHP.DiscouragedFunctions

    $upload_dir        = wp_upload_dir();
    $base_path         = $upload_dir['basedir'] . '/buildhub_tmp';
    $extract_path      = $base_path . '/extract_' . get_current_user_id();
    $builds_path       = $base_path . '/Builds';
    $persistent_source = $base_path . '/source_' . get_current_user_id() . '.zip';

    if (!is_dir($builds_path)) wp_mkdir_p($builds_path);

    $source_zip = is_array($file_input) ? $file_input['tmp_name'] : $file_input;
    if (!$source_zip || !file_exists($source_zip)) {
        return ['success' => false, 'data' => 'Source ZIP missing. tmp_name=' . ($source_zip ?: 'empty')];
    }

    // Entpacken
    wm_recursive_rmdir($extract_path);
    if (!wp_mkdir_p($extract_path)) {
        return ['success' => false, 'data' => 'Cannot create extract dir: ' . $extract_path];
    }
    $zip = new ZipArchive;
    $zip_result = $zip->open($source_zip);
    if ($zip_result !== TRUE) {
        return ['success' => false, 'data' => 'ZIP open failed. Code: ' . $zip_result . ' File: ' . $source_zip];
    }
    if (!$zip->extractTo($extract_path)) {
        $zip->close();
        return ['success' => false, 'data' => 'ZIP extract failed to: ' . $extract_path];
    }
    $zip->close();

    // Haupt-Plugin-Datei finden
    $main_file = wm_find_main_plugin_file($extract_path);
    if (!$main_file) {
        return ['success' => false, 'data' => 'Keine Plugin-Hauptdatei mit "Plugin Name:" gefunden.'];
    }

    // Version auslesen (zeilengebunden - kein Falsch-Treffer aus Description moeglich)
    $file_content     = file_get_contents($main_file);
    $detected_version = '1.0.0';
    $header_part      = substr($file_content, 0, 8192);
    if (preg_match('/^\s*\*?\s*Version:\s*([0-9][0-9\.]*)\s*$/im', $header_part, $matches)) {
        $detected_version = trim($matches[1]);
    }

    // Nur analysieren: ZIP sichern und zurueckgeben
    if ($analyze_only) {
        @copy($source_zip, $persistent_source);
        return ['success' => true, 'data' => [
            'version'  => $detected_version,
            'tmp_path' => $persistent_source,
        ]];
    }

    // Zielversion setzen
    $build_version = !empty($target_version) ? trim($target_version) : $detected_version;

    // Dataset laden (Fallback: leeres Array)
    if (empty($dataset)) {
        $projects = get_option('bh_projects_db', []);
        foreach ($projects as $proj) {
            if (isset($proj['PLUGIN_DOMAIN']) && $proj['PLUGIN_DOMAIN'] === $plugin_type) {
                $dataset = $proj;
                break;
            }
        }
    }
    if (empty($dataset)) $dataset = [];

    $has_free  = !empty($dataset['HAS_FREE']);
    $free_slug = $plugin_type ?: sanitize_title($dataset['PLUGIN_DOMAIN'] ?? 'plugin');
    $pro_slug  = !empty($dataset['PAID_SLUG']) ? $dataset['PAID_SLUG'] : $free_slug . '-pro';

    // Compliance Auto-Fix (falls Addon aktiv)
    $compliance_fixes = 0;
    $compliance_fixer_active = apply_filters('bh_compliance_fixer_available', false);

    // -------------------------------------------------------------------------
    // DEV-Version bauen (Original + DEV Suffix)
    // -------------------------------------------------------------------------
    $dev_extract = $base_path . '/dev_' . get_current_user_id();
    wm_recursive_rmdir($dev_extract);
    wm_copy_dir($extract_path, $dev_extract);

    $dev_main = wm_find_main_plugin_file($dev_extract);
    if ($dev_main) {
        $dev_content = file_get_contents($dev_main);
        // Version patchen
        $dev_content = wm_patch_version($dev_content, $build_version);
        // Plugin Name DEV Suffix
        $dev_content = preg_replace(
            '/^(\s*\*\s*Plugin Name:\s*)(.+?)\s*(DEV|PRO|FREE)?\s*$/m',
            '${1}${2} DEV',
            $dev_content
        );
        // Text Domain auf -dev setzen (nur wenn noch nicht -dev)
        $dev_content = preg_replace(
            '/^(\s*\*\s*Text Domain:\s*)(.+?)(-dev)?\s*$/m',
            '${1}${2}-dev',
            $dev_content
        );
        file_put_contents($dev_main, $dev_content);
    }

    $dev_zip_name = "{$free_slug}-dev-{$build_version}.zip";
    $dev_zip_path = $builds_path . '/' . $dev_zip_name;
    if ($compliance_fixer_active) {
        $compliance_fixes += wm_apply_compliance_fixes($dev_extract);
    }
    wm_create_zip_from_dir($dev_extract, $dev_zip_path, $free_slug . '-dev');
    wm_recursive_rmdir($dev_extract);

    $final_files = ['d' => $dev_zip_name];

    // -------------------------------------------------------------------------
    // PRO-Version bauen
    // Tags werden entfernt, Inhalt bleibt, Author + Version werden injiziert
    // -------------------------------------------------------------------------
    $pro_extract = $base_path . '/pro_' . get_current_user_id();
    wm_recursive_rmdir($pro_extract);
    wm_copy_dir($extract_path, $pro_extract);

    $pro_main = wm_find_main_plugin_file($pro_extract);
    if (!$pro_main) {
        return ['success' => false, 'data' => 'No main plugin file found in: ' . $pro_extract];
    }
    $pro_content = file_get_contents($pro_main);
    $pro_content = wm_patch_version($pro_content, $build_version);
    $pro_content = wm_inject_author($pro_content, $dataset);
    // Schreibe in eine neue Datei und ersetze dann die alte
    $tmp_write = $pro_main . '.tmp';
    file_put_contents($tmp_write, $pro_content);
    rename($tmp_write, $pro_main); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
    clearstatcache(true, $pro_main);
    if (function_exists('opcache_invalidate')) opcache_invalidate($pro_main, true);
    wm_sync_stable_tag($pro_extract, $build_version);
    wm_strip_pro_tags_only($pro_extract);
    // Text-Domain und Plugin Name auf PRO anpassen
    if (!empty($pro_slug) && $pro_slug !== $free_slug) {
        wm_patch_textdomain($pro_extract, $free_slug, $pro_slug);
        wm_patch_plugin_name_suffix($pro_extract, 'PRO');
    }
    // PRO-Version: FREE beim Aktivieren löschen
    wm_inject_pro_delete_free($pro_extract, $free_slug);

    $pro_zip_name = "{$pro_slug}-{$build_version}.zip";
    $pro_zip_path = $builds_path . '/' . $pro_zip_name;
    if ($compliance_fixer_active) {
        $compliance_fixes += wm_apply_compliance_fixes($pro_extract);
    }
    wm_create_zip_from_dir($pro_extract, $pro_zip_path, $pro_slug);
    wm_recursive_rmdir($pro_extract);

    $final_files['p'] = $pro_zip_name;

    // -------------------------------------------------------------------------
    // FREE-Version bauen (nur wenn HAS_FREE = true im Dataset)
    // [PRO]-Bloecke werden komplett entfernt, Freemius-SDK wird entfernt
    // -------------------------------------------------------------------------
    if ($has_free) {
        $free_extract = $base_path . '/free_' . get_current_user_id();
        wm_recursive_rmdir($free_extract);
        wm_copy_dir($extract_path, $free_extract);

        $free_main = wm_find_main_plugin_file($free_extract);
        if ($free_main) {
            $content = file_get_contents($free_main);
            $content = wm_patch_version($content, $build_version);
            $content = wm_inject_author($content, $dataset);
            file_put_contents($free_main, $content);
        }
        wm_sync_stable_tag($free_extract, $build_version);
        wm_strip_pro_blocks($free_extract);
        wm_remove_freemius_sdk($free_extract);
        wm_remove_htaccess($free_extract);
        wm_patch_plugin_name_suffix($free_extract, '');
        // Textdomain -dev Suffix entfernen
        $free_main2 = wm_find_main_plugin_file($free_extract);
        if ($free_main2) {
            $fc = file_get_contents($free_main2);
            $fc = preg_replace(
                '/^(\s*\*\s*Text Domain:\s*)(.+?)-dev\s*$/m',
                '${1}${2}',
                $fc
            );
            file_put_contents($free_main2, $fc);
        }

        $free_zip_name = "{$free_slug}-{$build_version}.zip";
        $free_zip_path = $builds_path . '/' . $free_zip_name;
        if ($compliance_fixer_active) {
            $compliance_fixes += wm_apply_compliance_fixes($free_extract);
        }
        wm_create_zip_from_dir($free_extract, $free_zip_path, $free_slug);
        wm_recursive_rmdir($free_extract);

        $final_files['f'] = $free_zip_name;
    }

    // Persistente Quelle aktualisieren (PRO als Master)
    @copy($pro_zip_path, $persistent_source);

    return ['success' => true, 'data' => [
        'version'          => $build_version,
        'files'            => $final_files,
        'compliance_fixes' => $compliance_fixes,
    ]];
}



// =============================================================================
// HELPER: TEXT-DOMAIN PATCHEN (nur fuer PRO-Build)
// =============================================================================

/**
 * Ersetzt die Text-Domain in allen PHP-Dateien eines Verzeichnisses.
 * Betrifft alle WP-Übersetzungsfunktionen: __(), esc_html_e(), _x(), _n(), esc_html__(), etc.
 */
function wm_patch_textdomain($dir, $old_domain, $new_domain) {
    if (empty($old_domain) || empty($new_domain) || $old_domain === $new_domain) return;

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        if ($file->getExtension() !== 'php') continue;

        $content = file_get_contents($file->getPathname());
        // Ersetze Text-Domain in allen WP-i18n-Funktionen
        // Pattern: 'alte-domain' als letzter String-Parameter
        $new = str_replace(
            ["'" . $old_domain . "'", '"' . $old_domain . '"'],
            ["'" . $new_domain . "'", '"' . $new_domain . '"'],
            $content
        );
        // Auch im Plugin-Header (Text Domain: whoismember)
        $new = preg_replace(
            '/^(\s*\*?\s*Text Domain:\s*)(.+)$/m',
            '${1}' . $new_domain,
            $new
        );
        if ($new !== $content) file_put_contents($file->getPathname(), $new);
    }
}


// =============================================================================
// HELPER: PRO-AKTIVIERUNGS-HOOK EINBAUEN
// =============================================================================

/**
 * Fügt in die Haupt-PHP-Datei der PRO-Version einen Aktivierungs-Hook ein,
 * der die FREE-Version automatisch deaktiviert und optional löscht.
 */
function wm_inject_pro_activation_hook($dir, $free_slug, $pro_slug) {
    $main_file = wm_find_main_plugin_file($dir);
    if (!$main_file) return;

    $plugin_dir = dirname($main_file);
    $inc_dir    = $plugin_dir . '/inc';
    if (!is_dir($inc_dir)) wp_mkdir_p($inc_dir);

    $func_name  = 'bh_deactivate_and_delete_free_' . preg_replace('/[^a-z0-9_]/', '_', $pro_slug);
    $hook_file  = $inc_dir . '/pro-activation.php';

    $code  = "<?php\n";
    $code .= "if ( ! defined( 'ABSPATH' ) ) exit;\n";
    $code .= "if ( ! function_exists( '" . $func_name . "' ) ) {\n";
    $code .= "    function " . $func_name . "() {\n";
    $code .= "        if ( ! function_exists( 'deactivate_plugins' ) ) {\n";
    $code .= "            require_once ABSPATH . 'wp-admin/includes/plugin.php';\n";
    $code .= "        }\n";
    $code .= "        \$free_plugin = '" . $free_slug . "/" . $free_slug . ".php';\n";
    $code .= "        if ( is_plugin_active( \$free_plugin ) ) {\n";
    $code .= "            deactivate_plugins( \$free_plugin );\n";
    $code .= "        }\n";
    $code .= "        \$free_path = WP_PLUGIN_DIR . '/" . $free_slug . "';\n";
    $code .= "        if ( is_dir( \$free_path ) ) {\n";
    $code .= "            \$it = new RecursiveIteratorIterator(\n";
    $code .= "                new RecursiveDirectoryIterator( \$free_path, RecursiveDirectoryIterator::SKIP_DOTS ),\n";
    $code .= "                RecursiveIteratorIterator::CHILD_FIRST\n";
    $code .= "            );\n";
    $code .= "            foreach ( \$it as \$f ) {\n";
    $code .= "                \$f->isDir() ? rmdir( \$f->getRealPath() ) : unlink( \$f->getRealPath() );\n";
    $code .= "            }\n";
    $code .= "            rmdir( \$free_path );\n";
    $code .= "        }\n";
    $code .= "    }\n";
    $code .= "}\n";

    file_put_contents($hook_file, $code);

    // require_once ans Ende der Hauptdatei anfügen
    $main_content = file_get_contents($main_file);
    if (strpos($main_content, 'pro-activation.php') === false) {
        $main_content .= "\nrequire_once plugin_dir_path( __FILE__ ) . 'inc/pro-activation.php';\n";
        $main_content .= "register_activation_hook( __FILE__, '" . $func_name . "' );\n";
        file_put_contents($main_file, $main_content);
    }
}



// =============================================================================
// HELPER: PRO-VERSION LÖSCHT FREE BEIM AKTIVIEREN
// =============================================================================

function wm_inject_pro_delete_free($dir, $free_slug) {
    $main_file = wm_find_main_plugin_file($dir);
    if (!$main_file) {
        // Fallback: direkt nach PHP-Dateien im Root suchen
        $files = glob(rtrim($dir, '/') . '/*/*.php');
        foreach ($files as $f) {
            $chunk = file_get_contents($f, false, null, 0, 512);
            if (strpos($chunk, 'Plugin Name:') !== false) { $main_file = $f; break; }
        }
        if (!$main_file) return;
    }

    $content = file_get_contents($main_file);
    if (strpos($content, 'bh_pro_delete_free') !== false) return;

    $func = 'bh_pro_delete_free_' . preg_replace('/[^a-z0-9_]/', '_', $free_slug);

    $code  = "
";
    $code .= "// Delete FREE version when PRO is activated
";
    $code .= "function {$func}() {
";
    $code .= "    if ( ! function_exists( 'deactivate_plugins' ) ) {
";
    $code .= "        require_once ABSPATH . 'wp-admin/includes/plugin.php';
";
    $code .= "        require_once ABSPATH . 'wp-admin/includes/file.php';
";
    $code .= "    }
";
    $code .= "    \$free = '{$free_slug}/{$free_slug}.php';
";
    $code .= "    if ( is_plugin_active( \$free ) ) deactivate_plugins( \$free );
";
    $code .= "    if ( function_exists( 'delete_plugins' ) ) delete_plugins( [ \$free ] );
";
    $code .= "}
";
    $code .= "register_activation_hook( __FILE__, '{$func}' );
";

    // Ans Ende der Datei anhängen
    $content = rtrim($content) . "
" . $code;
    file_put_contents($main_file, $content);
}

// =============================================================================
// HELPER: CONFLICT GUARD IN PRO-VERSION EINBAUEN
// =============================================================================

function wm_inject_conflict_guard($dir, $free_slug) {
    $main_file = wm_find_main_plugin_file($dir);
    if (!$main_file) return;

    $content = file_get_contents($main_file);
    if (strpos($content, 'BH_CONFLICT_GUARD') !== false) return;

    $guard  = "
// Conflict guard: deactivate FREE version if active
";
    $guard .= "if ( ! defined( 'BH_CONFLICT_GUARD_" . strtoupper(preg_replace('/[^a-z0-9]/i', '_', $free_slug)) . "' ) ) {
";
    $guard .= "    define( 'BH_CONFLICT_GUARD_" . strtoupper(preg_replace('/[^a-z0-9]/i', '_', $free_slug)) . "', true );
";
    $guard .= "}
";
    $guard .= "if ( function_exists( 'is_plugin_active' ) && is_plugin_active( '" . $free_slug . "/" . $free_slug . ".php' ) ) {
";
    $guard .= "    deactivate_plugins( '" . $free_slug . "/" . $free_slug . ".php' );
";
    $guard .= "}
";

    // Nach dem ABSPATH-Check einfügen (erste Zeile nach exit;)
    $content = preg_replace(
        '/(if\s*\(\s*!\s*defined\s*\(\s*["\']ABSPATH["\']\s*\)\s*\)\s*\{\s*exit\s*;\s*\})/s',
        '$1' . $guard,
        $content,
        1
    );

    file_put_contents($main_file, $content);
}



// =============================================================================
// HELPER: PLUGIN NAME SUFFIX SETZEN (DEV entfernen, PRO/FREE sauber)
// =============================================================================

function wm_patch_plugin_name_suffix($dir, $suffix = '') {
    $main_file = wm_find_main_plugin_file($dir);
    if (!$main_file) return;
    $content = file_get_contents($main_file);
    // Entferne bestehende Suffixe (DEV, PRO, FREE) und setze neuen
    $content = preg_replace(
        '/^(\s*\*\s*Plugin Name:\s*)(.+?)\s*(DEV|PRO|FREE)?\s*$/m',
        '$1$2' . ($suffix ? ' ' . $suffix : ''),
        $content
    );
    file_put_contents($main_file, $content);
}

// =============================================================================
// HELPER: PLUGIN NAME IM PRO-BUILD MIT " PRO" SUFFIX VERSEHEN
// =============================================================================

function wm_patch_plugin_name_pro($dir) {
    $main_file = wm_find_main_plugin_file($dir);
    if (!$main_file) return;
    $content = file_get_contents($main_file);
    // Füge " PRO" zum Plugin Name hinzu wenn noch nicht vorhanden
    $content = preg_replace(
        '/^(\s*\*\s*Plugin Name:\s*)(.+?)(?<!\s*PRO)$/m',
        '$1$2 PRO',
        $content
    );
    file_put_contents($main_file, $content);
}


// =============================================================================
// HELPER: COMPLIANCE AUTO-FIX (wenn BuildHub Maker - Compliance Fixer aktiv)
// =============================================================================

function wm_apply_compliance_fixes($dir) {
    $result = apply_filters('bh_compliance_run_fixes', 0, $dir);
    // Debug
    $log = gmdate('H:i:s') . " | wm_apply_compliance_fixes: dir=" . $dir . " result=" . $result . "\n";
    file_put_contents(WP_CONTENT_DIR . '/bh-compliance-debug.log', $log, FILE_APPEND);
    return $result;
}

// =============================================================================
// HELPER: VERSION PATCHEN
// =============================================================================

function wm_patch_version($content, $new_version) {
    return preg_replace(
        '/^(\s*\*?\s*Version:\s*)([0-9\.]+)\s*$/m',
        '${1}' . $new_version,
        $content
    );
}


// =============================================================================
// HELPER: AUTHOR INJECTION
// =============================================================================

/**
 * Ueberschreibt "Author:" und "Author URI:" im Plugin-Header mit Dataset-Werten.
 * Nur die echten Header-Zeilen werden ersetzt (Zeilengebunden).
 */
function wm_inject_author($content, $dataset) {
    if (!empty($dataset['PLUGIN_AUTHOR'])) {
        $content = preg_replace(
            '/^(\s*\*?\s*Author:\s*)(.*)$/m',
            '${1}' . $dataset['PLUGIN_AUTHOR'],
            $content
        );
    }
    if (!empty($dataset['PLUGIN_URI'])) {
        $content = preg_replace(
            '/^(\s*\*?\s*Author URI:\s*)(.*)$/m',
            '${1}' . $dataset['PLUGIN_URI'],
            $content
        );
    }
    return $content;
}


// =============================================================================
// HELPER: STABLE TAG IN readme.txt SYNCHRONISIEREN
// =============================================================================

function wm_sync_stable_tag($dir, $version) {
    // readme.txt direkt oder eine Ebene tiefer suchen
    $readme = $dir . '/readme.txt';
    if (!file_exists($readme)) {
        $files = glob($dir . '/*/readme.txt');
        if (!empty($files)) $readme = $files[0];
    }
    if (!file_exists($readme)) return;

    $content = file_get_contents($readme);
    $content = preg_replace(
        '/^(Stable tag:\s*)(.+)$/im',
        '${1}' . $version,
        $content
    );
    file_put_contents($readme, $content);
}


// =============================================================================
// HELPER: [PRO]-TAGS ENTFERNEN, INHALT BLEIBT (fuer PRO-Build)
// =============================================================================

/**
 * Entfernt nur die Markierungs-Zeilen selbst.
 * Unterstuetzte Formate:
 *   // [PRO]
 *   // [/PRO]
 *   /* [PRO] * /
 *   /* [/PRO] * /
 */
function wm_strip_pro_tags_only($dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['php', 'js', 'css', 'html', 'txt'])) continue;

        $content = file_get_contents($file->getPathname());
        // Entferne nur exakte Tag-Zeilen allein auf einer Zeile
        $new = preg_replace('/^[ \t]*\/\/[ \t]*\[PRO\][ \t]*$/m', '', $content);
        $new = preg_replace('/^[ \t]*\/\/[ \t]*\[\/PRO\][ \t]*$/m', '', $new);
        $new = preg_replace('/^[ \t]*\/\*[ \t]*\[PRO\][ \t]*\*\/[ \t]*$/m', '', $new);
        $new = preg_replace('/^[ \t]*\/\*[ \t]*\[\/PRO\][ \t]*\*\/[ \t]*$/m', '', $new);
        if ($new !== $content) file_put_contents($file->getPathname(), $new);
    }
}


// =============================================================================
// HELPER: [PRO]-BLOECKE KOMPLETT ENTFERNEN (fuer FREE-Build)
// =============================================================================

/**
 * Entfernt alles zwischen und inklusive den [PRO]-Tag-Zeilen.
 * Unterstuetzte Formate:
 *   // [PRO]
 *   ... beliebiger Code ...
 *   // [/PRO]
 *
 *   /* [PRO] * /
 *   ... beliebiger Code ...
 *   /* [/PRO] * /
 */
function wm_strip_pro_blocks($dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['php', 'js', 'css', 'html', 'txt'])) continue;

        $content = file_get_contents($file->getPathname());

        // Entferne echte [PRO]...[/PRO] Bloecke
        $new = preg_replace('/^[ \t]*\/\/[ \t]*\[PRO\][ \t]*$.*?^[ \t]*\/\/[ \t]*\[\/PRO\][ \t]*$\n?/ms', '', $content);
        $new = preg_replace('/^[ \t]*\/\*[ \t]*\[PRO\][ \t]*\*\/[ \t]*$.*?^[ \t]*\/\*[ \t]*\[\/PRO\][ \t]*\*\/[ \t]*$\n?/ms', '', $new);

        if ($new !== $content) file_put_contents($file->getPathname(), $new);
    }
}


// =============================================================================
// HELPER: FREEMIUS SDK AUS FREE-VERSION ENTFERNEN
// =============================================================================

function wm_remove_freemius_sdk($dir) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $freemius_dir = null;
    foreach ($it as $file) {
        if (!$file->isDir()) continue;
        if (strtolower($file->getFilename()) === 'freemius') {
            // Den obersten Freemius-Ordner nehmen
            if ($freemius_dir === null || strlen($file->getPathname()) < strlen($freemius_dir)) {
                $freemius_dir = $file->getPathname();
            }
        }
    }
    if ($freemius_dir) {
        wm_recursive_rmdir($freemius_dir);
    }
}



// =============================================================================
// HELPER: .HTACCESS AUS FREE-VERSION ENTFERNEN
// =============================================================================

function wm_remove_htaccess($dir) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->isFile() && strtolower($file->getFilename()) === '.htaccess') {
            wp_delete_file($file->getPathname());
        }
    }
}

// =============================================================================
// HELPER: PLUGIN-HAUPTDATEI FINDEN
// =============================================================================

function wm_find_main_plugin_file($dir) {
    // Suche nur max. 2 Ebenen tief (root und ein Unterordner)
    // um zu vermeiden dass core/transformer.php gefunden wird
    $dir = rtrim($dir, '/\\');
    
    // Erst direkt im Root suchen
    foreach (glob($dir . '/*.php') as $file) {
        if (stripos(basename($file), 'index') !== false) continue;
        $chunk = file_get_contents($file, false, null, 0, 8192);
        // Muss "Plugin Name:" als echten WP-Header haben (nicht als String in Code)
        if (preg_match('/^\s*\*\s*Plugin Name:/m', $chunk)) return $file;
    }
    
    // Dann eine Ebene tiefer (z.B. buildhub-maker/buildhub-maker.php)
    foreach (glob($dir . '/*', GLOB_ONLYDIR) as $subdir) {
        $subname = basename($subdir);
        // Bekannte Nicht-Plugin-Ordner ueberspringen
        if (in_array($subname, ['core', 'inc', 'admin', 'assets', 'vendor', 'languages', 'includes', 'js', 'css'])) continue;
        foreach (glob($subdir . '/*.php') as $file) {
            if (stripos(basename($file), 'index') !== false) continue;
            $chunk = file_get_contents($file, false, null, 0, 8192);
            if (preg_match('/^\s*\*\s*Plugin Name:/m', $chunk)) return $file;
        }
    }
    
    return false;
}


// =============================================================================
// HELPER: ZIP MIT KORREKTER ORDNERSTRUKTUR ERSTELLEN
// =============================================================================

/**
 * Erstellt ein ZIP mit $slug als Root-Verzeichnis.
 * WordPress erwartet: mein-plugin/mein-plugin.php
 * Vorhandene Root-Ordner werden durch $slug ersetzt.
 */
function wm_create_zip_from_dir($source, $dest, $slug = '') {
    $zip = new ZipArchive();
    if (!$zip->open($dest, ZipArchive::CREATE | ZipArchive::OVERWRITE)) return false;
    $source = realpath($source);

    // Pruefen ob bereits ein einzelner Root-Ordner vorhanden ist
    $root_items = array_diff(scandir($source), ['.', '..']);
    $has_single_root = (count($root_items) === 1 && is_dir($source . DIRECTORY_SEPARATOR . reset($root_items)));

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        if (!$file->isFile()) continue;
        $real_path = $file->getRealPath();
        $relative  = substr($real_path, strlen($source) + 1);

        if (!empty($slug)) {
            if ($has_single_root) {
                // Bestehenden Root-Ordner durch $slug ersetzen
                $relative = preg_replace('/^[^\/\\\\]+/', $slug, $relative);
            } else {
                $relative = $slug . DIRECTORY_SEPARATOR . $relative;
            }
        }

        // Pfad-Trenner vereinheitlichen
        $relative = str_replace('\\', '/', $relative);
        $zip->addFile($real_path, $relative);
    }

    return $zip->close();
}


// =============================================================================
// HELPER: VERZEICHNIS REKURSIV KOPIEREN
// =============================================================================

function wm_copy_dir($src, $dst) {
    wp_mkdir_p($dst);
    $dir = opendir($src);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') continue;
        $s = $src . DIRECTORY_SEPARATOR . $file;
        $d = $dst . DIRECTORY_SEPARATOR . $file;
        is_dir($s) ? wm_copy_dir($s, $d) : copy($s, $d);
    }
    closedir($dir);
}


// ============================================================================= // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
// HELPER: VERZEICHNIS REKURSIV LOESCHEN
// =============================================================================

function wm_recursive_rmdir($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        is_dir($path) ? wm_recursive_rmdir($path) : wp_delete_file($path);
    }
    rmdir($dir); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
}