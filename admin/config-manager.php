<?php
/**
 * admin/config-manager.php
 * Dataset management with Side-by-Side Guide Layout.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. DATA CORE
$raw_projects = get_option('bh_projects_db', []);
$projects = array_values(array_filter((array)$raw_projects));
$count = count($projects);

// phpcs:disable WordPress.Security.NonceVerification
$p_idx = isset($_POST['p_idx']) ? intval($_POST['p_idx']) : (isset($_GET['p_idx']) ? intval($_GET['p_idx']) : 0); // phpcs:ignore
$mode  = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : (isset($_GET['mode']) ? sanitize_key(wp_unslash($_GET['mode'])) : '');
$is_new = ($mode === 'new');

if (!$is_new && $count > 0 && isset($projects[$p_idx])) {
    $p = $projects[$p_idx];
} else {
    $p = ['PLUGIN_NAME'=>'','PLUGIN_DOMAIN'=>'','PAID_SLUG'=>'','FS_ID'=>'','FS_TOKEN'=>'','GH_REPO'=>'','GH_TOKEN_VAL'=>'','HAS_FREE'=>false,'PLUGIN_AUTHOR'=>'Franz Horvath','PLUGIN_URI'=>'https://einfachalles.at'];
}

// 2. ACTION: SAVE
if (isset($_POST['sub_action']) && sanitize_key(wp_unslash($_POST['sub_action'])) === 'save') {
    $p_data = isset($_POST['p_data']) ? array_map('sanitize_text_field', wp_unslash((array)$_POST['p_data'])) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
    $slug_to_save = isset($_POST['p_slug']) ? sanitize_key(wp_unslash($_POST['p_slug'])) : sanitize_key($p_data['title'] ?? '');
    if (!empty($slug_to_save)) {
        $new_data = [
            'PLUGIN_NAME'   => sanitize_text_field($p_data['title'] ?? ''),
            'PLUGIN_DOMAIN' => $slug_to_save,
            'PAID_SLUG'     => sanitize_text_field($p_data['paid_slug'] ?? ''),
            'FS_ID'         => sanitize_text_field($p_data['id'] ?? ''),
            'FS_TOKEN'      => sanitize_text_field($p_data['token'] ?? ''),
            'GH_REPO'       => sanitize_text_field($p_data['repo'] ?? ''),
            'GH_TOKEN_VAL'  => sanitize_text_field( wp_unslash( $p_data['gh_token'] ?? '' ) ),
            'HAS_FREE'      => (isset($p_data['has_free']) && $p_data['has_free'] == '1'),
            'PLUGIN_AUTHOR' => sanitize_text_field($p_data['author'] ?? ''),
            'PLUGIN_URI'    => esc_url_raw($p_data['uri'] ?? ''),
        ];
        if ($is_new) { $projects[] = $new_data; } else { $projects[$p_idx] = $new_data; }
        update_option('bh_projects_db', array_values(array_filter($projects)));
    }
}

// Helper: Get Guide Content
// Guard gegen doppelte Definition (config-manager.php wird mehrfach eingebunden
if (!function_exists('bh_get_config_guide')) {
    function bh_get_config_guide() {
        $file = BH_MAKER_PATH . 'admin/workspace-guide.txt';
        if (!file_exists($file)) return "Guide file missing.";
        $content = file_get_contents($file);
        preg_match("/\[STEP-CONFIG\](.*?)\[\/STEP-CONFIG\]/s", $content, $matches);
        return isset($matches[1]) ? nl2br(trim($matches[1])) : "Configuration guide not found.";
    }
}
?>

<div style="width: 100%; font-family: sans-serif;">

    <!-- PROJEKT-LISTE -->
    <div style="background:#f9f9f9; border:1px solid #ddd; border-radius:6px; padding:15px; margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <strong style="font-size:14px;">&#128193; Project Datasets (<?php echo esc_html($count); ?>)</strong>
            <a href="<?php echo esc_url(admin_url('admin.php?page=buildhub-config&mode=new')); ?>"
               style="background:#0073aa; color:#fff; padding:6px 14px; border-radius:4px; text-decoration:none; font-size:13px; font-weight:bold;">
               &#43; New Dataset
            </a>
        </div>
        <?php if ($count > 0): ?>
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="background:#eee;">
                    <th style="padding:8px; text-align:left;">Plugin Name</th>
                    <th style="padding:8px; text-align:left;">Slug</th>
                    <th style="padding:8px; text-align:left;">Free?</th>
                    <th style="padding:8px; text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($projects as $i => $proj): ?>
                <tr style="border-bottom:1px solid #eee; <?php echo ($i === $p_idx && !$is_new) ? 'background:#fff3cd;' : ''; ?>">
                    <td style="padding:8px;"><strong><?php echo esc_html($proj['PLUGIN_NAME']); ?></strong></td>
                    <td style="padding:8px; font-family:monospace; color:#666;"><?php echo esc_html($proj['PLUGIN_DOMAIN']); ?></td>
                    <td style="padding:8px;"><?php echo !empty($proj['HAS_FREE']) ? '&#10003;' : '-'; ?></td>
                    <td style="padding:8px; text-align:center;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=buildhub-config&p_idx=' . $i)); ?>"
                           style="color:#0073aa; margin-right:8px; text-decoration:none;">&#9998; Edit</a>
                        <?php if ($count > 1): ?>
                        <a href="#" class="bh-delete-row" data-idx="<?php echo esc_html($i); ?>"
                           style="color:#dc3545; text-decoration:none;">&#128465; Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:#999; text-align:center; padding:20px;">No datasets yet. Create your first one!</p>
        <?php endif; ?>
    </div>

    <!-- HEADER -->
    <div style="background: #f1f1f1; padding: 15px; border-bottom: 1px solid #ccc; display: flex; justify-content: space-between; align-items: center; border-radius: 4px 4px 0 0;">
        <strong style="color: #0073aa; text-transform: uppercase; letter-spacing: 1px;">
            <?php echo $is_new ? '&#10024; Create New Project Dataset' : '&#9998; Editing Dataset: ' . esc_html($p['PLUGIN_NAME']); ?>
        </strong>
        <button type="button" onclick="location.reload()" style="background:transparent; color:#0073aa; border:none; font-weight:bold; cursor:pointer; font-size:14px;">Cancel / Exit Manager</button>
    </div>

<!-- 66/33 GRID LAYOUT -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-top: 20px; align-items: start;">
    
    <!-- LEFT: FORM (66%) -->
    <div style="background: #fff; border: 1px solid #eee; border-radius: 4px; padding: 20px;">
        <form id="bh-config-form" method="post">
            <input type="hidden" name="p_idx" value="<?php echo esc_attr($p_idx); ?>">
            <input type="hidden" name="mode" value="<?php echo $is_new ? 'new' : ''; ?>">
            
            <table class="form-table" style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #f9f9f9;">
                    <th style="width: 250px; padding: 10px; text-align: left; font-size: 13px;"><strong>Product Title</strong></th>
                    <td><input type="text" name="p_data[title]" value="<?php echo esc_attr($p['PLUGIN_NAME']); ?>" style="width: 100%;" required></td>
                </tr>
                <tr style="border-bottom: 1px solid #f9f9f9;">
                    <th style="padding: 10px; text-align: left; font-size: 13px;"><strong>Slug</strong></th>
                    <td><input type="text" name="p_slug" value="<?php echo esc_attr($p['PLUGIN_DOMAIN']); ?>" style="width: 100%; font-family:monospace;" required></td>
                </tr>
                <tr style="border-bottom: 1px solid #f9f9f9;">
                    <th style="padding: 10px; text-align: left; font-size: 13px;"><strong>Paid version slug</strong></th>
                    <td><input type="text" name="p_data[paid_slug]" value="<?php echo esc_attr($p['PAID_SLUG'] ?? ''); ?>" style="width: 100%; font-family:monospace;"></td>
                </tr>
                <tr style="border-bottom: 1px solid #f9f9f9;">
                    <th style="padding: 10px; text-align: left; font-size: 13px;"><strong>GitHub Repo</strong></th>
                    <td><input type="text" name="p_data[repo]" value="<?php echo esc_attr($p['GH_REPO']); ?>" style="width: 100%;" placeholder="user/repository"></td>
                </tr>
                <tr style="border-bottom: 1px solid #f9f9f9;">
                    <th style="padding: 10px; text-align: left; font-size: 13px;"><strong>GitHub PAT</strong></th>
                    <td><input type="text" name="p_data[gh_token]" value="<?php echo esc_attr($p['GH_TOKEN_VAL'] ?? ''); ?>" style="width: 100%; font-family:monospace;"></td>
                </tr>
                <tr style="border-bottom: 1px solid #f9f9f9;">
                    <th style="padding: 10px; text-align: left; font-size: 13px;"><strong>Freemius ID</strong></th>
                    <td><input type="text" name="p_data[id]" value="<?php echo esc_attr($p['FS_ID']); ?>" style="width: 100%;" required></td>
                </tr>
                <tr style="border-bottom: 1px solid #f9f9f9;">
                    <th style="padding: 10px; text-align: left; font-size: 13px;"><strong>Freemius Token</strong></th>
                    <td><input type="text" name="p_data[token]" value="<?php echo esc_attr($p['FS_TOKEN']); ?>" style="width: 100%;" required></td>
                </tr>
                <tr style="border-bottom: 1px solid #f9f9f9;">
                    <th style="padding: 10px; text-align: left; font-size: 13px;"><strong>Author Name</strong></th>
                    <td><input type="text" name="p_data[author]" value="<?php echo esc_attr($p['PLUGIN_AUTHOR']); ?>" style="width: 100%;" required></td>
                </tr>
                <tr style="border-bottom: 1px solid #f9f9f9;">
                    <th style="padding: 10px; text-align: left; font-size: 13px;"><strong>Author URI</strong></th>
                    <td><input type="url" name="p_data[uri]" value="<?php echo esc_attr($p['PLUGIN_URI']); ?>" style="width: 100%;"></td>
                </tr>
                <tr>
                    <th style="padding: 10px; text-align: left; font-size: 13px;"><strong>Logic</strong></th>
                    <td><label style="font-weight: bold;"><input type="checkbox" name="p_data[has_free]" value="1" <?php checked($p['HAS_FREE'], true); ?>> Create FREE Version</label></td>
                </tr>
            </table>

            <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                <button type="submit" name="bh_save_cfg" style="background:#28a745; color:#fff; border:none; padding: 10px 20px; border-radius: 4px; font-weight:bold; cursor:pointer;">&#128190; Save Dataset</button>
                <?php if (!$is_new && $count > 1): ?>
                    <button type="button" class="bh-delete-config" data-idx="<?php echo esc_attr($p_idx); ?>" style="background:transparent; color:#dc3545; border:none; font-weight:bold; cursor:pointer;">&#128465; Delete</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- RIGHT: GUIDE (33%) -->
    <div style="border: 2px solid #000; padding: 25px; background: #fdfaf0; border-radius: 4px; position: sticky; top: 20px;">
        <div id="bh-readme-context" style="font-size: 13px; line-height: 1.6; color: #333;">
            <?php echo wp_kses_post(bh_get_config_guide()); ?>
        </div>
    </div>

</div>

</div>
// phpcs:enable
