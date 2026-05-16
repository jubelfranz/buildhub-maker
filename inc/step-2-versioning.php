<?php
/**
 * BuildHub Maker - Step 2: Versioning
 * Full vertical structure: 83 Lines.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_shortcode('bh-2-versioning', function() {
    $v = bh_get_data('current_version') ?: '1.0.0';
    $v_parts = explode('.', $v);

    while ( count( $v_parts ) < 3 ) {
        $v_parts[] = '0';
    }

    ob_start();
    ?>
    <div id="bh-step-2-wrapper" style="background:transparent !important; color:#000 !important; padding:20px; border:1px solid #eee; border-radius:8px;">

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="color:#000 !important; margin:0;">2. Versioning</h3>
            <a href="javascript:void(0);" onclick="window.bhGoToStep(1)" style="color:#0073aa; text-decoration:none; font-size:14px; font-weight:bold;">< Back</a>
        </div>

        <p>Current Header Version: <strong style="color:red; font-size:18px;"><?php echo esc_html($v); ?></strong></p>

        <div style="display:flex; gap:15px; justify-content:center; margin:30px 0;">
            <?php foreach ($v_parts as $i => $p) : ?>
                <div style="text-align:center;">
                    <a href="javascript:void(0);" class="v-mod" data-id="v_<?php echo esc_html($i); ?>" data-dir="up" style="color:green; font-weight:bold; text-decoration:none; font-size:24px; display:block;">+</a>
                    
                    <input type="text" id="v_<?php echo esc_html($i); ?>" value="<?php echo intval($p); ?>" style="width:55px; text-align:center; border:1px solid #000; background:none; color:#000; font-weight:bold; font-size:20px;" readonly>
                    
                    <a href="javascript:void(0);" class="v-mod" data-id="v_<?php echo esc_html($i); ?>" data-dir="down" style="color:red; font-weight:bold; text-decoration:none; font-size:24px; display:block;">-</a>
                </div>
                <?php if ( $i < 2 ) : ?>
                    <div style="margin-top:35px; font-weight:bold; font-size:24px; color:#000;">.</div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div style="text-align:center;">
            <a href="javascript:void(0);" id="btn-build-v273" 
               style="display:inline-block !important; color:#0073aa !important; background:transparent !important; font-size:22px !important; font-weight:bold !important; text-decoration:none !important; cursor:pointer !important;">
               Start Transformation & Build
            </a>
        </div>

    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('.v-mod').off('click').on('click', function(e) {
            e.preventDefault();
            var $input = $('#' + $(this).data('id')), 
                val = parseInt($input.val());
            val = $(this).data('dir') === 'up' ? (val + 1 > 99 ? 0 : val + 1) : (val - 1 < 0 ? 99 : val - 1);
            $input.val(val);
        });

        $('#btn-build-v273').off('click').on('click', function(e) {
            e.preventDefault();
            var final_v = $('#v_0').val() + '.' + $('#v_1').val() + '.' + $('#v_2').val();
            var $btn = $(this);
            var ajaxurl = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";
            var buildNonce = "<?php echo esc_js(wp_create_nonce('bh_step2_v258')); ?>";

            // Animierter Button während Build läuft
            var dots = 0;
            var anim = setInterval(function() {
                dots = (dots + 1) % 4;
                $btn.text('🔨 Building' + '.'.repeat(dots + 1));
            }, 500);

            $btn.css('opacity', '0.7');

            // AJAX mit langem Timeout (5 Minuten)
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                timeout: 300000,
                data: {
                    action: 'bh_ajax_step_2_build_v258',
                    target_version: final_v,
                    security: buildNonce
                },
                success: function(response) {
                    clearInterval(anim);
                    if (response.success) {
                        $btn.text('✅ Done!');
                        // compliance_fixes via Transient in Step 3 angezeigt
                        setTimeout(function() { window.bhGoToStep(3); }, 500);
                    } else {
                        $btn.css('opacity', '1').text('Start Transformation & Build');
                        alert('Build Error: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status) {
                    clearInterval(anim);
                    $btn.css('opacity', '1').text('Start Transformation & Build');
                    if (status === 'timeout') {
                        alert('Build is taking very long. Check /buildhub_tmp/Builds/ for results.');
                    } else {
                        alert('AJAX Error: ' + status + ' (' + xhr.status + ')');
                    }
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
});
