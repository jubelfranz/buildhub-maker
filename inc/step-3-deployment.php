<?php
/**
 * BuildHub Maker - Step 3: Deployment
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode('bh-3-deployment', function() {
    $files   = bh_get_data('build_files');
    $version = bh_get_data('final_version');
    if (!$files) return '<p style="text-align:center;color:red;padding:20px;">No build data found. Please go back.</p>';

    // Download-Token für iOS-kompatible Downloads
    $dl_token_d = !empty($files['d']) ? md5(AUTH_KEY . basename($files['d'])) : '';
    $dl_token_f = !empty($files['f']) ? md5(AUTH_KEY . basename($files['f'])) : '';
    $dl_token_p = !empty($files['p']) ? md5(AUTH_KEY . basename($files['p'])) : '';

    ob_start(); ?>
    <div id="bh-step-3-wrapper" style="background:transparent !important; color:#000 !important; padding:20px; border:1px solid #eee; border-radius:8px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="color:#000 !important; margin:0;">3. Downloads &amp; Deployment</h3>
            <a href="javascript:void(0);" onclick="window.bhGoToStep(2)" style="color:#0073aa; text-decoration:none; font-size:14px; font-weight:bold;">&lt; Back</a>
        </div>

        <?php
        ?>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0; margin-top:30px;">

            <!-- HEADER ROW -->
            <div style="padding:10px 0; border-bottom:2px solid #ccc;">
                <h4 style="color:#000; margin:0;">Downloads</h4>
            </div>
            <div style="padding:10px 0; border-bottom:2px solid #ccc;">
                <?php
                ?>
            </div>

            <!-- ROW 1: DEV -->
            <div style="padding:16px 0; border-bottom:1px solid #eee;">
                <?php if (!empty($files['d'])) : ?>
                <a href="<?php echo esc_url(admin_url('admin-ajax.php') . '?action=wm_download_file&file=' . rawurlencode(basename($files['d'])) . '&token=' . $dl_token_d); ?>"
                   style="color:#fd7e14 !important; text-decoration:none; font-weight:bold; font-size:16px;">
                    &#8595; DOWNLOAD DEV ZIP
                </a>
                <?php endif; ?>
            </div>
            <div style="padding:16px 0; border-bottom:1px solid #eee;">
                &nbsp;
            </div>

            <!-- ROW 2: FREE -->
            <div style="padding:16px 0; border-bottom:1px solid #eee;">
                <?php if (!empty($files['f'])) : ?>

                <a href="<?php echo esc_url(admin_url('admin-ajax.php') . '?action=wm_download_file&file=' . rawurlencode(basename($files['f'])) . '&token=' . $dl_token_f); ?>"
                   style="color:#46b450 !important; text-decoration:none; font-weight:bold; font-size:16px;">
                    &#8595; DOWNLOAD FREE ZIP
                </a>
                <?php
                ?>
                <?php endif; ?>
            </div>
            <div style="padding:16px 0; border-bottom:1px solid #eee;">
                <?php
                ?>
            </div>

            <!-- ROW 3: PRO -->
            <div style="padding:16px 0;">
                <a href="<?php echo esc_url(admin_url('admin-ajax.php') . '?action=wm_download_file&file=' . rawurlencode(basename($files['p'])) . '&token=' . $dl_token_p); ?>"
                   style="color:#0073aa !important; text-decoration:none; font-weight:bold; font-size:16px;">
                    &#8595; DOWNLOAD PRO ZIP
                </a>
            </div>
            <div style="padding:16px 0;">
                <?php
                ?>
            </div>

        </div>
    </div>

    <?php
    ?>

    <?php return ob_get_clean();
});
