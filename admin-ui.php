<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
    <h1>🛠️ BuildHub Maker v1.0</h1>
    <p>Transformiere dein Plugin und sende es direkt in die Cloud.</p>

    <div style="max-width: 600px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
        <div id="main-ui">
            <div id="drop-zone" style="border: 2px dashed #2271b1; padding: 40px; text-align: center; cursor: pointer;">
                <input type="file" id="file-input" style="display:none;">
                <span id="label">📁 ZIP Datei hier auswählen oder reinziehen</span>
            </div>

            <div id="version-lock" style="display:none; margin-top: 20px; text-align: center;">
                <strong>Ziel-Version:</strong><br>
                <input type="number" id="v1" style="width:50px;"> . 
                <input type="number" id="v2" style="width:50px;"> . 
                <input type="number" id="v3" style="width:50px;">
                <button id="btn-build" class="button button-primary" style="margin-left:10px;">🚀 JETZT BAUEN</button>
            </div>
        </div>

        <div id="progress-bar" style="display:none; margin-top: 20px;">
            <div style="background: #eee; height: 10px; border-radius: 5px; overflow: hidden;">
                <div id="fill" style="background: #28a745; width: 0%; height: 100%;"></div>
            </div>
        </div>

        <div id="actions-ui" style="display:none; margin-top: 20px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div id="dl-links"></div>
                <div>
                    <button id="deploy-wporg" class="button" style="color:#d94f00; width:100%; margin-bottom:10px;">🚀 Senden zu WP.org</button>
                    <button id="deploy-freemius" class="button" style="color:#28a745; width:100%; margin-bottom:10px;">🚀 Senden zu Freemius</button>
                    <button id="test-smtp" class="button" style="width:100%; font-size: 11px;">📧 SMTP Test</button>
                </div>
            </div>
            <hr>
            <button onclick="location.reload()" class="button">🔄 Neuer Build</button>
        </div>
    </div>
</div>

<script>
// JavaScript Logik angepasst auf WordPress AJAX (jQuery ist in WP Standard)
jQuery(document).ready(function($) {
    let proPath = '', curV = '', pType = '';
    const inpt = $('#file-input'), zone = $('#drop-zone');

    zone.on('click', () => inpt.click());

    inpt.on('change', function() {
        $('#label').text("⏳ Analysiere...");
        let fd = new FormData();
        fd.append('action', 'wm_build_process'); // In diesem Stadium nur für Analyse genutzt
        // Analyse-Logik hier... (wie gehabt)
        $('#version-lock').show();
        $('#label').text("✅ Plugin bereit");
    });

    $('#btn-build').on('click', function() {
        // Build-Logik via jQuery AJAX...
        $('#main-ui').hide();
        $('#progress-bar').show();
        // Sende an action: 'wm_build_process'
    });
});
</script>
