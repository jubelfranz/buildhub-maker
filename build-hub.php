<?php
/**
 * BUILD-HUB v8.9 (Modular Edition)
 */
session_start();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// 1. Zentrale Konfiguration laden
if (file_exists(__DIR__ . '/maker-config.php')) {
    require_once __DIR__ . '/maker-config.php';
} else {
    die('Kritischer Fehler: maker-config.php wurde nicht gefunden!');
}

// 2. Logik-Dateien laden
if ( file_exists( __DIR__ . '/transformer.php' ) ) { require_once __DIR__ . '/transformer.php'; }
if ( file_exists( __DIR__ . '/deployer.php' ) ) { require_once __DIR__ . '/deployer.php'; }

// Login-Prüfung gegen HUB_PASSWORD aus der Config
if (isset($_POST['pw']) && $_POST['pw'] === HUB_PASSWORD) { $_SESSION['auth'] = true; }

if (!isset($_SESSION['auth'])) {
    die('<html><body style="font-family:sans-serif;text-align:center;padding-top:100px;background:#f4f7f6;"><form method="post"><input type="password" name="pw" style="padding:15px;border-radius:10px;border:1px solid #ccc;"><button type="submit" style="padding:15px;background:#2271b1;color:#fff;border:none;border-radius:10px;margin-left:10px;">Unlock</button></form></body></html>');
}

// AJAX: INITIALE ZIP ANALYSE
if (isset($_FILES['analyze_zip'])) {
    $zip = new ZipArchive;
    $res = ['success' => false, 'version' => '0.0.0', 'type' => 'main'];
    if ($zip->open($_FILES['analyze_zip']['tmp_name']) === TRUE) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i); $filename = basename($name);
            if (preg_match('/^index\./i', $filename) || strpos($name, '__MACOSX') !== false) continue;
            if (substr_count(trim($name, '/'), '/') !== 1) continue;
            $content = $zip->getFromIndex($i);
            if (preg_match('/\.php$/i', $filename)) {
                if (stripos($content, 'Plugin Name:') !== false && preg_match('/Version:\s*([\d\.]+)/i', $content, $m)) {
                    $res['version'] = $m[1]; $res['success'] = true;
                    if (preg_match('/Plugin Name:\s*(.*)/i', $content, $nM)) {
                        $pName = strtolower($nM[1]);
                        $res['type'] = (strpos($pName, 'activity log') !== false) ? 'activitylog-addon' : ((strpos($pName, 'user-roles') !== false) ? 'userroles-addon' : 'main');
                    }
                    break;
                }
            } else if ($filename === 'readme.txt') {
                if (preg_match('/Stable tag:\s*([\d\.]+)/i', $content, $m)) { $res['version'] = $m[1]; $res['success'] = true; }
            }
        }
        $zip->close();
    }
    header('Content-Type: application/json'); echo json_encode($res); exit;
}

// AJAX: DEPLOYMENT ZU FREEMIUS ODER WP.ORG ODER TESTMAIL
if (isset($_POST['target']) && $_POST['target'] === 'testmail') {
    header('Content-Type: application/json');
    echo json_encode(wm_deploy_to_freemius('', '', '', false)); exit;
}

if (isset($_POST['deploy_file'])) {
    // IDs aus der maker-config.php ziehen
    $pID = ($_POST['p_type'] === 'activitylog-addon') ? FS_ID_ACTIVITY : (($_POST['p_type'] === 'userroles-addon') ? FS_ID_ROLES : FS_ID_MAIN);
    $is_wporg = (isset($_POST['target']) && $_POST['target'] === 'wporg');
    header('Content-Type: application/json');
    echo json_encode(wm_deploy_to_freemius($_POST['deploy_file'], $_POST['v'], $pID, $is_wporg)); exit;
}

// DOWNLOAD TRIGGER
if (isset($_GET['download'])) {
    $file = $_GET['download'];
    if (file_exists($file)) {
        header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="'.basename($file).'"');
        readfile($file); exit;
    }
}

// AJAX: BUILD PROZESS
if (isset($_FILES['plugin_zip'])) {
    header('Content-Type: application/json');
    echo json_encode(wm_run_build_process($_FILES['plugin_zip'], $_POST['target_version'], $_POST['plugin_type'])); exit;
}
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{font-family:sans-serif;padding:20px;background:#f4f7f6;display:flex;justify-content:center;}.card{width:100%;max-width:600px;background:#fff;padding:30px;border-radius:20px;box-shadow:0 10px 30px rgba(0,0,0,0.1);}.upload-zone{background:#fafafa;border:2px dashed #2271b1;padding:40px;text-align:center;border-radius:15px;cursor:pointer;position:relative;}#file-input{position:absolute;top:0;left:0;width:100%;height:100%;opacity:0;cursor:pointer;}.btn-main{display:block;width:100%;padding:15px;border-radius:30px;background:#1a1a1a;color:#fff;text-align:center;text-decoration:none;font-weight:bold;margin-top:10px;border:none;cursor:pointer;}.grid-container{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;}.grid-col{display:flex;flex-direction:column;gap:10px;}.link-item{display:flex;align-items:center;text-decoration:none;font-weight:bold;font-size:14px;background:none;border:none;padding:5px 0;cursor:pointer;text-align:left;}.link-blue{color:#2271b1;}.link-orange{color:#d94f00;}.link-green{color:#28a745;}.link-gray{color:#666;justify-content:center;margin-top:20px;width:100%;}</style></head>
<body><div class="card">
    <h2 style="text-align:center;">🛠️ Build Hub v8.9</h2>
    <div id="main-ui">
        <div class="upload-zone" id="zone"><input type="file" id="file-input" accept=".zip"><div id="label">📁 ZIP HIER REIN</div></div>
        <div id="lock" style="display:none;justify-content:center;gap:10px;margin-top:20px;">
            <input type="number" id="v1" style="width:45px;padding:8px;text-align:center;">.
            <input type="number" id="v2" style="width:45px;padding:8px;text-align:center;">.
            <input type="number" id="v3" style="width:45px;padding:8px;text-align:center;">
        </div>
        <button id="btn" class="btn-main" style="display:none;">🚀 JETZT BAUEN</button>
    </div>
    <div id="bar" style="height:10px;background:#eee;border-radius:5px;margin-top:20px;display:none;overflow:hidden;"><div id="fill" style="height:100%;background:#28a745;width:0%;transition:0.2s;"></div></div>
    <div id="actions-ui" style="display:none;">
        <div class="grid-container">
            <div class="grid-col" id="download-group"></div>
            <div class="grid-col">
                <button id="deploy-wporg" class="link-item link-orange">🚀 Senden zu GitHub & wordpress.org</button>
                <button id="deploy-trigger" class="link-item link-green">🚀 Senden zu GitHub & Freemius</button>
                <button id="test-mail-btn" class="link-item link-gray" style="margin-top:10px; font-size:11px;">📧 SMTP Verbindung testen</button>
            </div>
        </div>
        <button onclick="location.reload()" class="link-item link-gray">🔄 NEUER BUILD</button>
    </div>
    <script>
    const inpt=document.getElementById("file-input"),btn=document.getElementById("btn"),ui=document.getElementById("main-ui"),actionsUi=document.getElementById("actions-ui"),dlGroup=document.getElementById("download-group"),label=document.getElementById("label");
    let pType='main', curV='0.0.0', proFilePath='';
    inpt.onchange=()=>{
        if(!inpt.files.length) return; label.innerText = "⏳ Analyse...";
        const fd=new FormData(); fd.append("analyze_zip", inpt.files[0]);
        fetch("?",{method:"POST",body:fd}).then(r=>r.json()).then(res=>{
            if(res.success){
                const p = res.version.split('.'); pType = res.type;
                document.getElementById("v1").value=p[0]||0; document.getElementById("v2").value=p[1]||0; document.getElementById("v3").value=(parseInt(p[2])||0)+1;
                document.getElementById("lock").style.display="flex"; btn.style.display="block"; label.innerText="✅ erkannt: v" + res.version;
            }
        });
    };
    btn.onclick=()=>{
        curV=`${document.getElementById("v1").value}.${document.getElementById("v2").value}.${document.getElementById("v3").value}`;
        const fd = new FormData(); fd.append("plugin_zip", inpt.files[0]); fd.append("target_version", curV); fd.append("plugin_type", pType);
        ui.style.display="none"; document.getElementById("bar").style.display="block";
        const xhr=new XMLHttpRequest(); xhr.open("POST","?",true);
        xhr.upload.onprogress=(e)=>{if(e.lengthComputable)document.getElementById("fill").style.width=(e.loaded/e.total*100)+"%";};
        xhr.onload=()=>{
            const r=JSON.parse(xhr.responseText);
            if(r.success){
                document.getElementById("bar").style.display="none";
                let filesSorted = r.files.sort((a,b)=>a.n.includes('FREE')?-1:1); let dlHtml='';
                filesSorted.forEach(f=>{
                    dlHtml+=`<a href="?download=${encodeURIComponent(f.p)}" class="link-item link-blue">📥 ${f.n}</a>`;
                    if(f.n.includes('PRO')||f.n.includes('ADDON')) proFilePath=f.p;
                });
                dlGroup.innerHTML=dlHtml; actionsUi.style.display="block";
            }
        }; xhr.send(fd);
    };
    function startDeploy(target, btnObj) {
        let msg = ""; if(target==='wporg') { msg = prompt("Review-Nachricht (Englisch):", "I have updated a new version with security fixes."); if(msg===null) return; }
        if(!confirm('Deployment zu ' + target + ' starten?')) return;
        btnObj.innerText = "⏳ Sende..."; btnObj.disabled = true;
        const fd = new FormData(); fd.append("deploy_file", proFilePath); fd.append("v", curV); fd.append("p_type", pType); fd.append("target", target); fd.append("msg", msg);
        fetch("?", {method: "POST", body: fd}).then(r=>r.json()).then(res=>{
            const isSuccess = (res.code === 204 || res.code === 201 || res.code === 200);
            if(isSuccess) {
                alert("✅ Erfolg!\nCode: " + res.code);
                btnObj.innerText = "✅ Fertig";
            } else {
                alert("❌ Fehler ("+res.code+")\nDaten: " + JSON.stringify(res.data));
                btnObj.disabled = false;
                btnObj.innerText = "Erneut versuchen";
            }
        });
    }
    document.getElementById("deploy-trigger").onclick=function(){ startDeploy('freemius', this); };
    document.getElementById("deploy-wporg").onclick=function(){ startDeploy('wporg', this); };
    document.getElementById("test-mail-btn").onclick = function() {
        this.innerText = "⏳ Prüfe...";
        const fd = new FormData(); fd.append("target", "testmail");
        fetch("?", {method: "POST", body: fd}).then(r => r.json()).then(res => {
            alert("SMTP-Test: Code " + res.code + "\nSchau jetzt bei GitHub unter 'Actions' nach dem grünen Haken!");
            this.innerText = "📧 SMTP Verbindung testen";
        });
    };
    </script>
</div></body></html>
