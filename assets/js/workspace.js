(function($) {
    $(document).ready(function() {
        var pmIsOpen = false;

        $(document).on('click', '#btn-open-pm', function() {
            if (pmIsOpen) {
                closePM();
            } else {
                var idx = parseInt($('#bh-project-select').find(':selected').data('idx'));
                openPM(idx, 'edit');
            }
        });

        $(document).on('click', '#btn-new-dataset', function() {
            openPM(-1, 'new');
        });

        $(document).on('click', '#btn-pm-save', function() {
            var slug = $('#pm-slug').val().trim().toLowerCase().replace(/ /g, '-');
            if (!slug) { $('#bh-pm-feedback').css('color','red').text('Slug is required!'); return; }
            $('#bh-pm-feedback').css('color','#666').text('Saving...');
            $.post(bhWorkspace.ajaxurl, {
                action: 'bh_save_dataset', security: bhWorkspace.nonce,
                mode: $('#bh-pm-mode').val(), p_idx: $('#bh-pm-idx').val(),
                PLUGIN_NAME: $('#pm-plugin-name').val(), PLUGIN_DOMAIN: slug,
                PAID_SLUG: $('#pm-paid-slug').val(), GH_REPO: $('#pm-repo').val(),
                GH_TOKEN_VAL: $('#pm-gh-token').val(), FS_ID: $('#pm-fs-id').val(),
                FS_TOKEN: $('#pm-fs-token').val(), PLUGIN_AUTHOR: $('#pm-author').val(),
                PLUGIN_URI: $('#pm-uri').val(), HAS_FREE: $('#pm-has-free').is(':checked') ? '1' : '0'
            }, function(r) {
                if (r.success) { $('#bh-pm-feedback').css('color','green').text('Saved!'); setTimeout(function() { location.reload(); }, 800); }
                else { $('#bh-pm-feedback').css('color','red').text('Error: ' + r.data); }
            });
        });

        $(document).on('click', '#btn-pm-delete', function() {
            if (!confirm('Delete this dataset?')) return;
            $.post(bhWorkspace.ajaxurl, { action: 'bh_delete_dataset', security: bhWorkspace.nonce, p_idx: $('#bh-pm-idx').val() }, function(r) {
                if (r.success) location.reload();
            });
        });

        $(document).on('change', '#bh-project-select', function() {
            $.post(bhWorkspace.ajaxurl, { action: 'bh_switch_project', security: bhWorkspace.nonce, slug: $(this).val() }, function(r) {
                if (r.success) location.reload();
            });
        });

        $(document).on('change', '#bh_debug_toggle', function() {
            $.post(bhWorkspace.ajaxurl, { action: 'bh_toggle_debug', security: bhWorkspace.nonce, debug: $(this).is(':checked') ? '1' : '0' });
        });

        $(document).on('click', '#bh_smtp_test', function(e) {
            e.preventDefault();
            $.post(bhWorkspace.ajaxurl, { action: 'bh_smtp_test', security: bhWorkspace.nonce }, function(r) {
                alert(r.success ? r.data : 'Error: ' + r.data);
            });
        });

        function closePM() {
            pmIsOpen = false;
            $('#bh-pm-panel').attr('style', 'display:none !important');
            $('#bh-steps-panel').attr('style', 'display:block !important');
            $('#btn-open-pm').html('&#9881;&#65039; PROJECT MANAGER').css('min-width', '');
            $.post(bhWorkspace.ajaxurl, { action: 'bh_get_readme_step', security: bhWorkspace.nonce, step: 'STEP-IDLE' }, function(r) {
                if (r.success) $('#bh-readme-context').html(r.data.html);
            });
        }

        function openPM(idx, mode) {
            pmIsOpen = true;
            $('#bh-pm-mode').val(mode);
            $('#bh-pm-idx').val(idx);
            $('#bh-pm-feedback').text('');
            if (mode === 'new') {
                $('#bh-pm-title').text('+ New Dataset');
                $('#btn-pm-delete').hide();
                $('#pm-plugin-name,#pm-slug,#pm-paid-slug,#pm-repo,#pm-gh-token,#pm-fs-id,#pm-fs-token').val('');
                $('#pm-author').val(bhWorkspace.author);
                $('#pm-uri').val(bhWorkspace.uri);
                $('#pm-has-free').prop('checked', false);
            } else {
                var p = bhWorkspace.projects[idx];
                if (!p) return;
                $('#bh-pm-title').text('Edit: ' + p.PLUGIN_NAME);
                $('#btn-pm-delete').show();
                $('#pm-plugin-name').val(p.PLUGIN_NAME || '');
                $('#pm-slug').val(p.PLUGIN_DOMAIN || '');
                $('#pm-paid-slug').val(p.PAID_SLUG || '');
                $('#pm-repo').val(p.GH_REPO || '');
                $('#pm-gh-token').val(p.GH_TOKEN_VAL || '');
                $('#pm-fs-id').val(p.FS_ID || '');
                $('#pm-fs-token').val(p.FS_TOKEN || '');
                $('#pm-author').val(p.PLUGIN_AUTHOR || '');
                $('#pm-uri').val(p.PLUGIN_URI || '');
                $('#pm-has-free').prop('checked', p.HAS_FREE == true || p.HAS_FREE == '1');
            }
            $('#bh-steps-panel').attr('style', 'display:none !important');
            $('#bh-pm-panel').attr('style', 'display:block !important');
            var pmWidth = $('#btn-open-pm').outerWidth();
            $('#btn-open-pm').css('min-width', pmWidth + 'px').html('&#128640; BUILDHUB MAKER');
            $.post(bhWorkspace.ajaxurl, { action: 'bh_get_readme_step', security: bhWorkspace.nonce, step: 'STEP-CONFIG' }, function(r) {
                if (r.success) $('#bh-readme-context').html(r.data.html);
            });
        }

        window.bhGoToStep = function(step) {
            if (pmIsOpen) closePM();
            $('.bh-step-container').hide().removeClass('bh-step-active');
            var $box = $('#step-' + step + '-box');
            $box.find('.bh-step-content').html('<p style="text-align:center;padding:40px;font-weight:bold;">&#9203; Loading Step ' + step + '...</p>');
            $box.show().addClass('bh-step-active');
            $.post(bhWorkspace.ajaxurl, { action: 'bh_get_step_html', step: step, security: bhWorkspace.nonce }, function(r) {
                if (r.success) {
                    $box.find('.bh-step-content').html(r.data.html);

                }
            });
            var guideStep = step === 2 ? 'STEP-ANALYZED' : (step === 3 ? 'STEP-FINISHED' : 'STEP-IDLE');
            $.post(bhWorkspace.ajaxurl, { action: 'bh_get_readme_step', security: bhWorkspace.nonce, step: guideStep }, function(r) {
                if (r.success) $('#bh-readme-context').html(r.data.html);
            });
        };
    // Initialize GitHub Repo Button
    $(document).on('click', '#s-init-repo', function() {
        var $btn    = $(this);
        var $status = $('#init-repo-status');
        var file    = $btn.data('file');
        $btn.css('opacity', '0.5').text('⏳ Initializing...');
        $status.text('');
        $.ajax({
            url:     bhWorkspace.ajaxurl,
            type:    'POST',
            timeout: 120000,
            data: {
                action:      'bh_init_github_repo',
                security:    bhWorkspace.nonce,
                bh_project:  $('#bh-project-select').val(),
                deploy_file: file
            },
            success: function(r) {
                $btn.css('opacity', '1').text('⚙️ Initialize GitHub Repo');
                if (r && r.success) {
                    $status.html('✅ ' + r.data.message + '<br><small>' + (r.data.details || []).join(' | ') + '</small>');
                } else {
                    var details = (r && r.data && r.data.details) ? r.data.details.join(' | ') : '';
                    $status.html('❌ ' + (r && r.data && r.data.message ? r.data.message : 'Failed') + (details ? '<br><small>' + details + '</small>' : ''));
                }
            },
            error: function(xhr) {
                $btn.css('opacity', '1').text('⚙️ Initialize GitHub Repo');
                $status.text('❌ Request failed: ' + xhr.status + ' ' + xhr.responseText.substring(0, 200));
            }
        });
    });

    // PCP Check Button (dynamisch geladen via Event Delegation)
    $(document).on('click', '#btn-run-pcp', function() {
        var $btn    = $(this);
        var $status = $('#pcp-status');
        $btn.css('opacity', '0.5').text('⏳ Running PCP...');
        $status.text('');
        $.ajax({
            url:     bhWorkspace.ajaxurl,
            type:    'POST',
            timeout: 120000,
            data: {
                action:   'bh_run_plugin_check',
                security: bhWorkspace.nonce
            },
            success: function(r) {
                $btn.css('opacity', '1').text('▶ Run Plugin Check (PCP)');
                if (r && r.success && r.data) {
                    var e = r.data.errors || 0;
                    var w = r.data.warnings || 0;
                    var icon = (e === 0) ? '✅' : '❌';
                    var dl_url = bhWorkspace.ajaxurl + '?action=wm_download_file&file=' + encodeURIComponent(r.data.report_file);
                    $status.html(icon + ' ' + e + ' Errors, ' + w + ' Warnings &mdash; <a href="' + dl_url + '" style="color:#6f42c1;font-weight:bold;">&#8595; Download Report</a>');
                } else {
                    $status.text('Error: ' + (r && r.data ? r.data : 'No data in response. Success=' + r.success));
                }
            },
            error: function(xhr, status) {
                $btn.css('opacity', '1').text('▶ Run Plugin Check (PCP)');
                $status.text('Request failed: ' + status);
            },

        });
    });

    });
})(jQuery);
