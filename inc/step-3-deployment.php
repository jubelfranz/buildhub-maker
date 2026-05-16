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
        <?php
        $bh_cf = get_transient('bh_compliance_fixes_' . get_current_user_id());
        if (apply_filters('bh_compliance_fixer_available', false) && $bh_cf !== false) :
            delete_transient('bh_compliance_fixes_' . get_current_user_id());
            $bh_cf = (int)$bh_cf;
        ?>
        <div style="margin-bottom:12px; font-size:14px; font-weight:bold; color:<?php echo $bh_cf > 0 ? '#46b450' : '#0073aa'; ?>;">
            <?php echo $bh_cf > 0 ? '✅ ' . $bh_cf . ' issues auto-fixed' : '☑️ 0 issues — already compliant'; ?>
        </div>
        <?php endif; ?>
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
                <h4 style="color:#000; margin:0;">Sendeauftr&auml;ge</h4>
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
                <br>
                <a href="javascript:void(0);" id="btn-run-pcp"
                   style="color:#6f42c1 !important; text-decoration:none; font-weight:bold; font-size:16px; cursor:pointer; margin-top:8px; display:inline-block;">
                    &#9658; Run Plugin Check (PCP)
                </a>
                <span id="pcp-status" style="display:block; font-size:12px; color:#666; margin-top:4px;"></span>
                <?php

                ?>
                <?php endif; ?>
            </div>
            <div style="padding:16px 0; border-bottom:1px solid #eee;">
                <?php

                ?>
                <?php if (!empty($files['f'])) : ?>
                <a href="javascript:void(0);" class="send-link" id="s-github-only"
                   data-file="<?php echo esc_attr(basename($files['f'])); ?>"
                   style="color:#46b450 !important; text-decoration:none; font-weight:bold; font-size:16px;">
                    &#8599; Senden nach GitHub
                </a>
                <?php if (apply_filters('bh_compliance_fixer_available', false)) : ?>
                <br>
                <?php endif; ?>
                <a href="javascript:void(0);" class="send-link" id="s-wporg"
                   data-file="<?php echo esc_attr(basename($files['f'])); ?>"
                   style="color:#46b450 !important; text-decoration:none; font-weight:bold; font-size:16px;">
                    &#8599; Senden nach GitHub &amp; WP.ORG
                </a>
                <?php endif; ?>
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
                <a href="javascript:void(0);" class="send-link" id="s-fs"
                   data-file="<?php echo esc_attr(basename($files['p'])); ?>"
                   style="color:#0073aa !important; text-decoration:none; font-weight:bold; font-size:16px;">
                    &#8599; Senden nach GitHub &amp; FREEMIUS
                </a>
                <br>
                <a href="javascript:void(0);" id="s-init-repo"
                   data-file="<?php echo esc_attr(basename($files['p'])); ?>"
                   style="color:#999 !important; text-decoration:none; font-size:13px; cursor:pointer; margin-top:6px; display:inline-block;">
                    ⚙️ Initialize GitHub Repo
                </a>
                <span id="init-repo-status" style="display:block; font-size:12px; color:#666; margin-top:4px;"></span>
                <?php

                ?>
            </div>

        </div>
    </div>

    <?php

    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {

        $('.send-link').off('click').on('click', function(e) {
            e.preventDefault();
            var $link = $(this), id = $link.attr('id');
            var target = id === 's-wporg' ? 'wporg' : (id === 's-github-only' ? 'github_only' : 'freemius');
            $link.css('opacity', '0.5').text('📡 Sending...');
            $.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                action:      'wm_deploy_process',
                security:    '<?php echo esc_js(wp_create_nonce("bh_maker_secure_nonce")); ?>',
                deploy_file: $link.data('file'),
                v:           '<?php echo esc_js($version); ?>',
                target:      target,
                bh_project:  '<?php echo esc_js(ACTIVE_PROJECT_KEY); ?>'
            }, function(r) {
                alert(r.success ? '✅ Success' : '❌ Error: ' + r.data);
                $link.css('opacity', '1').text('Finished');
            });
        });
    });
    </script>
    <?php

    ?>

    <?php return ob_get_clean();
});
