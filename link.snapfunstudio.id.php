<?php
/**
 * TempVault Pro - Snap Edit Style (Manual PHP Version)
 * Mobile First & Separated Interfaces with GIF Generator
 */

session_start();

// --- KONFIGURASI ---
$storage_dir = 'uploads';
$db_file = 'database.json';
$admin_password = 'oresnaporefun'; // GANTI PASSWORD INI UNTUK KEAMANAN
$favicon_url = 'snaplink.png'; // GANTI DENGAN URL/NAMA FILE FAVICON ANDA (contoh: 'https://domainanda.com/logo.png')

// Inisialisasi Sistem (Folder dan Database)
if (!file_exists($storage_dir)) { mkdir($storage_dir, 0777, true); }
if (!file_exists($db_file)) { file_put_contents($db_file, json_encode([])); }

// Fungsi Database
function get_db() { 
    global $db_file; 
    $data = json_decode(@file_get_contents($db_file), true);
    return is_array($data) ? $data : [];
}
function save_db($data) { 
    global $db_file; 
    file_put_contents($db_file, json_encode($data, JSON_PRETTY_PRINT)); 
}

// Fungsi Hitung Penyimpanan Server
function folderSize($dir) {
    $size = 0;
    foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $each) {
        $size += is_file($each) ? filesize($each) : folderSize($each);
    }
    return $size;
}
function formatBytes($bytes) { 
    if($bytes == 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
    $pow = floor(log($bytes) / log(1024)); 
    return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow]; 
}

// ==========================================
// --- FITUR AUTO-CLEANUP ---
// ==========================================
// Menghapus proyek dan foto secara fisik dari server jika sudah melewati batas waktu
function auto_cleanup() {
    global $storage_dir;
    $db = get_db();
    $changed = false;
    $now = time();
    
    foreach ($db as $id => $album) {
        if ($now >= $album['expires_at']) {
            // Hapus semua file foto secara fisik
            foreach ($album['photos'] as $p) {
                if (isset($p['url']) && file_exists($p['url'])) {
                    @unlink($p['url']);
                }
            }
            // Bersihkan juga file zip temporary (jika ada sisa)
            $zip_pattern = $storage_dir . '/temp_' . $id . '_*.zip';
            foreach (glob($zip_pattern) as $zf) {
                @unlink($zf);
            }
            // Hapus dari database
            unset($db[$id]);
            $changed = true;
        }
    }
    
    if ($changed) {
        save_db($db);
    }
}
// Jalankan pembersihan otomatis setiap kali halaman diakses/dipanggil
auto_cleanup();

// ==========================================
// --- LOGIKA UTAMA & API ---
// ==========================================

// 1. Handle Download File Asli (Tanpa Kompresi, Ubah Nama)
if (isset($_GET['download_file'])) {
    $file_name = basename($_GET['download_file']);
    $target_file = $storage_dir . '/' . $file_name;
    
    // Ambil nama kustom jika ada
    $custom_name = isset($_GET['dl_name']) ? basename($_GET['dl_name']) : $file_name;
    
    // Pastikan tidak ada output sebelum header
    if (ob_get_level()) ob_end_clean();
    
    if (file_exists($target_file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$custom_name.'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($target_file));
        readfile($target_file);
        exit;
    } else {
        http_response_code(404);
        echo "Error: File tidak ditemukan.";
        exit;
    }
}

// 1.5 Handle Download Semua File (ZIP Archive - Solusi iOS)
if (isset($_GET['download_zip']) && isset($_GET['album_id'])) {
    $album_id = strtoupper($_GET['album_id']);
    $db = get_db();
    
    if (ob_get_level()) ob_end_clean();

    if (isset($db[$album_id]) && extension_loaded('zip')) {
        $album = $db[$album_id];
        $paket = isset($album['paket']) ? $album['paket'] : 'SelfPhoto';
        $clean_name = preg_replace('/[^a-zA-Z0-9]/', '_', $album['name']);
        $zip_name = "SnapFun_" . $paket . "_" . $clean_name . ".zip";
        
        // Simpan zip sementara di folder uploads
        $zip_file = $storage_dir . '/temp_' . $album_id . '_' . time() . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($album['photos'] as $idx => $photo) {
                $target_file = $storage_dir . '/' . $photo['file'];
                if (file_exists($target_file)) {
                    $ext = pathinfo($photo['file'], PATHINFO_EXTENSION);
                    $custom_name = "Snap Fun_" . $paket . "_" . $album['name'] . "_" . ($idx + 1) . "." . $ext;
                    $zip->addFile($target_file, $custom_name);
                }
            }
            $zip->close();

            if (file_exists($zip_file)) {
                header('Content-Type: application/zip');
                header('Content-disposition: attachment; filename="'.$zip_name.'"');
                header('Content-Length: ' . filesize($zip_file));
                readfile($zip_file);
                unlink($zip_file); // Hapus file temp setelah dikirim ke browser
                exit;
            }
        }
    }
    http_response_code(500);
    echo "Error: Gagal membuat file ZIP. Pastikan ekstensi PHP ZipArchive aktif di server Anda.";
    exit;
}

// 2. Cek Mode Akses (Customer vs Admin)
$mode = 'admin_login'; 
$current_album = null;

if (isset($_GET['album'])) {
    $mode = 'customer_view';
    $db = get_db();
    $id = strtoupper($_GET['album']);
    // Cek keberadaan dan kedaluwarsa proyek
    if (isset($db[$id])) {
        if (time() < $db[$id]['expires_at']) {
            $current_album = $db[$id];
        } else {
            $mode = 'customer_expired';
        }
    } else {
        $mode = 'customer_not_found';
    }
} elseif (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    $mode = 'admin_dashboard';
}

// 3. Handle Login Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    if ($_POST['password'] === $admin_password) {
        $_SESSION['is_admin'] = true;
        header("Location: index.php");
        exit;
    } else {
        $login_error = "Password salah!";
    }
}

// 4. Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// 5. API Backend (Admin Only) - MENGGUNAKAN METODE SEQUENTIAL UPLOAD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_SESSION['is_admin'])) {
    header('Content-Type: application/json');
    $db = get_db();

    // A. Inisialisasi Pembuatan Proyek Baru (Hanya Metadata)
    if ($_POST['action'] === 'init_album') {
        $album_id = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        $name = $_POST['name'] ?? 'Proyek Tanpa Judul';
        $paket = $_POST['paket'] ?? 'Self Photo';
        $hours = (int)($_POST['hours'] ?? 168);
        $expires_at = time() + ($hours * 3600);
        
        $db[$album_id] = [
            'id' => $album_id,
            'name' => $name,
            'paket' => $paket,
            'expires_at' => $expires_at,
            'created_at' => time(),
            'photos' => []
        ];
        save_db($db);
        echo json_encode(['success' => true, 'album_id' => $album_id]);
        exit;
    }

    // B. Upload Satu Foto (Digunakan secara berulang oleh JS)
    if ($_POST['action'] === 'upload_single') {
        $album_id = $_POST['album_id'];
        if (isset($db[$album_id]) && isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            // Tambahkan random string agar nama file benar-benar unik
            $fname = $album_id . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
            $target = $storage_dir . '/' . $fname;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                $db[$album_id]['photos'][] = [
                    'name' => $_FILES['photo']['name'],
                    'file' => $fname,
                    'url' => $target
                ];
                save_db($db);
                echo json_encode(['success' => true]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file']);
        exit;
    }

    // C. Ambil Data Proyek untuk Edit
    if ($_POST['action'] === 'get_album') {
        $id = $_POST['id'];
        if (isset($db[$id])) {
            echo json_encode(['success' => true, 'album' => $db[$id]]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }

    // D. Hapus Proyek Utuh
    if ($_POST['action'] === 'delete_album') {
        $id = $_POST['id'];
        if (isset($db[$id])) {
            foreach ($db[$id]['photos'] as $p) { if (file_exists($p['url'])) @unlink($p['url']); }
            unset($db[$id]);
            save_db($db);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }

    // E. Hapus Satu Foto (Edit Proyek)
    if ($_POST['action'] === 'delete_photo') {
        $id = $_POST['id'];
        $url = $_POST['url'];
        if (isset($db[$id])) {
            foreach ($db[$id]['photos'] as $idx => $p) {
                if ($p['url'] === $url) {
                    if (file_exists($url)) @unlink($url);
                    unset($db[$id]['photos'][$idx]);
                }
            }
            $db[$id]['photos'] = array_values($db[$id]['photos']); // Re-index array
            save_db($db);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
} 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Snap Link - Link Photo Snap Fun</title>
    <!-- Cache Buster Favicon -->
    <link rel="icon" href="<?= htmlspecialchars($favicon_url) ?>?v=<?= time() ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Library GIF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gifshot/0.3.2/gifshot.min.js"></script>
    <style>
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            -webkit-tap-highlight-color: transparent;
            background-color: #f9fafb;
            color: #111827;
        }
        
        :root {
            --primary: #355faa;
            --action: #fbdc00;
        }

        .dot-grid {
            background-image: radial-gradient(#d1d5db 1px, transparent 1px);
            background-size: 20px 20px;
        }

        /* Utilitas Interaktif */
        .btn-touch { transition: transform 0.1s; }
        .btn-touch:active { transform: scale(0.96); }
        .shadow-glow { box-shadow: 0 10px 40px -10px rgba(53, 95, 170, 0.2); }
        
        /* Scrollbar Halus */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        /* Checkbox Custom untuk GIF */
        .gif-checkbox:checked + div {
            border-color: var(--primary);
            background-color: rgba(53, 95, 170, 0.1);
        }
        .gif-checkbox:checked + div .check-indicator {
            opacity: 1;
            transform: scale(1);
        }
        
        /* Animasi Kustom Hostinger Fallback */
        .anim-fallback {
            display: none;
        }
        img.anim-error {
            display: none !important;
        }
        img.anim-error + .anim-fallback {
            display: block;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <?php if ($mode === 'admin_login'): ?>
    <!-- ========================================= -->
    <!-- 1. HALAMAN LOGIN ADMIN                    -->
    <!-- ========================================= -->
    <div class="flex-1 flex flex-col items-center justify-center p-6 bg-gray-50">
        <div class="w-full max-w-sm bg-white p-8 rounded-[2rem] shadow-xl border border-gray-100 text-center">
            <div class="w-16 h-16 bg-[#355faa] rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg shadow-blue-900/20">
                <i data-lucide="lock" class="text-white" size="32"></i>
            </div>
            <h1 class="text-2xl font-bold mb-2 text-gray-900">Portal Admin</h1>
            <p class="text-sm text-gray-500 mb-8">Masukkan sandi untuk mengelola galeri.</p>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="login">
                <input type="password" name="password" class="w-full bg-gray-50 border border-gray-200 p-4 rounded-xl text-center text-lg outline-none focus:ring-2 focus:ring-[#355faa] transition-all" placeholder="••••••" required>
                <?php if (isset($login_error)): ?>
                    <p class="text-red-500 text-xs font-bold"><?= $login_error ?></p>
                <?php endif; ?>
                <button type="submit" class="w-full bg-[#355faa] text-white py-4 rounded-xl font-bold text-sm uppercase tracking-wider btn-touch shadow-lg shadow-blue-900/10">Masuk Dashboard</button>
            </form>
        </div>
    </div>

    <?php elseif ($mode === 'admin_dashboard'): ?>
    <!-- ========================================= -->
    <!-- 2. DASHBOARD ADMIN (DESKTOP & MOBILE)     -->
    <!-- ========================================= -->
    <?php 
        $db_all = get_db();
        $total_files = 0; $active_links = 0;
        foreach($db_all as $a) { 
            $total_files += count($a['photos']); 
            if(time() < $a['expires_at']) $active_links++; 
        }
        $total_storage = formatBytes(folderSize($storage_dir));
    ?>
    <div class="flex-1 bg-[#f3f4f6] dot-grid flex h-screen overflow-hidden">
        
        <!-- Sidebar (Desktop) -->
        <aside class="hidden md:flex w-72 bg-white border-r border-gray-200 flex-col z-20 shadow-sm">
            <div class="p-6 border-b border-gray-100">
                <div class="flex items-center gap-3 mb-1">
                    <div class="w-8 h-8 bg-[#355faa] rounded-lg flex items-center justify-center text-white"><i data-lucide="layout-dashboard" size="18"></i></div>
                    <span class="font-bold text-lg">Snap Link</span>
                </div>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider pl-11">Admin Panel</p>
            </div>
            <nav class="flex-1 p-4 space-y-2">
                <div class="bg-blue-50 text-[#355faa] p-3 rounded-xl flex items-center gap-3 font-bold text-sm cursor-pointer">
                    <i data-lucide="folder-open" size="18"></i> Proyek Saya
                </div>
            </nav>
            <div class="p-4 border-t border-gray-100">
                <a href="?logout=true" class="flex items-center gap-3 p-3 rounded-xl text-red-500 hover:bg-red-50 font-bold text-sm transition-colors">
                    <i data-lucide="log-out" size="18"></i> Keluar
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col h-full overflow-hidden relative">
            <!-- Header Mobile -->
            <header class="md:hidden bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center z-20 shadow-sm shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-[#355faa] rounded-xl flex items-center justify-center text-white"><i data-lucide="layout-dashboard"></i></div>
                    <h2 class="font-bold text-lg leading-none">Snap Link</h2>
                </div>
                <a href="?logout=true" class="p-2 bg-red-50 text-red-500 rounded-lg hover:bg-red-100"><i data-lucide="log-out" size="20"></i></a>
            </header>

            <!-- Scrollable Area -->
            <main class="flex-1 overflow-y-auto p-4 md:p-10 custom-scrollbar relative">
                <!-- Statistik Lengkap -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 mb-8">
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                        <p class="text-[10px] md:text-xs text-gray-400 font-bold uppercase mb-2">Link Aktif</p>
                        <p class="text-2xl md:text-3xl font-bold text-[#355faa]"><?= $active_links ?></p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                        <p class="text-[10px] md:text-xs text-gray-400 font-bold uppercase mb-2">Total File</p>
                        <p class="text-2xl md:text-3xl font-bold text-gray-800"><?= $total_files ?></p>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm md:col-span-2 flex items-center justify-between">
                        <div>
                            <p class="text-[10px] md:text-xs text-gray-400 font-bold uppercase mb-1">Penyimpanan Terpakai</p>
                            <p class="text-lg md:text-xl font-bold text-amber-500 flex items-center gap-2"><i data-lucide="hard-drive" size="18"></i> <?= $total_storage ?></p>
                        </div>
                        <button onclick="toggleCreate()" class="hidden md:flex bg-[#355faa] text-white px-6 py-3 rounded-xl font-bold text-sm shadow-lg shadow-blue-900/20 hover:bg-[#2d5191] transition-colors items-center gap-2">
                            <i data-lucide="plus" size="18"></i> Buat Proyek Baru
                        </button>
                    </div>
                </div>

                <!-- Form Buat Baru (Toggle) -->
                <div id="create-panel" class="hidden bg-white p-6 md:p-8 rounded-[2rem] shadow-xl border border-gray-200 mb-8 animate-in slide-in-from-top-4 max-w-3xl mx-auto relative z-30">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-xl">Buat Proyek Baru</h3>
                        <button onclick="toggleCreate()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x"></i></button>
                    </div>
                    <!-- Form tidak lagi menggunakan method standard POST, diganti dengan JS -->
                    <form id="createForm" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6">
                            <div class="md:col-span-1">
                                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2 tracking-wider">Paket</label>
                                <select id="formPaket" class="w-full bg-gray-50 p-4 rounded-xl border border-gray-200 outline-none cursor-pointer">
                                    <option value="Self Photo">Self Photo</option>
                                    <option value="Photobox">Photobox</option>
                                    <option value="Pas Photo">Pas Photo</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2 tracking-wider">Nama Klien</label>
                                <input type="text" id="formName" required class="w-full bg-gray-50 p-4 rounded-xl border border-gray-200 outline-none focus:border-[#355faa]" placeholder="Cth: Sesi Budi & Siska">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2 tracking-wider">Durasi Akses</label>
                            <select id="formHours" class="w-full bg-gray-50 p-4 rounded-xl border border-gray-200 outline-none cursor-pointer">
                                <option value="168" selected>1 Minggu</option>
                                <option value="336">2 Minggu</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2 tracking-wider">Foto</label>
                            <div onclick="document.getElementById('fileInput').click()" class="border-2 border-dashed border-gray-200 p-10 rounded-2xl text-center cursor-pointer hover:border-[#355faa] hover:bg-blue-50 transition-colors">
                                <i data-lucide="image-plus" class="mx-auto text-gray-400 mb-2" size="32"></i>
                                <p class="text-sm font-bold text-gray-500">Klik untuk pilih foto</p>
                                <input type="file" id="fileInput" multiple hidden accept="image/*">
                            </div>
                            <div id="fileCount" class="text-center text-xs font-bold text-[#355faa] mt-2"></div>
                        </div>
                        <button type="submit" class="w-full bg-[#355faa] text-white py-4 rounded-xl font-bold shadow-lg shadow-blue-900/20 btn-touch text-sm uppercase tracking-widest">Terbitkan Proyek</button>
                    </form>
                </div>

                <!-- Daftar Proyek -->
                <div class="space-y-4 pb-20">
                    <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wider ml-1 mb-4">Riwayat Proyek</h3>
                    <?php if (empty($db_all)): ?>
                        <div class="text-center py-20 bg-white rounded-3xl border border-dashed border-gray-200">
                            <i data-lucide="folder-open" class="mx-auto text-gray-300 mb-3" size="48"></i>
                            <p class="text-gray-400 text-sm font-bold">Belum ada proyek.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="projectsGrid">
                            <?php foreach (array_reverse($db_all, true) as $id => $album): 
                                $is_exp = time() > $album['expires_at'];
                                $paket_name = isset($album['paket']) ? $album['paket'] : 'Reguler';
                            ?>
                            <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow flex flex-col gap-3">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-[10px] font-black text-[#355faa] uppercase tracking-widest mb-1"><?= $paket_name ?></p>
                                        <h4 class="font-bold text-gray-900 truncate max-w-[150px] leading-tight"><?= htmlspecialchars($album['name']) ?></h4>
                                        <p class="text-xs text-gray-400 font-mono mt-1">ID: <?= $id ?></p>
                                    </div>
                                    <?php if($is_exp): ?>
                                        <span class="bg-red-50 text-red-500 px-2 py-1 rounded-lg text-[10px] font-bold uppercase">Expired</span>
                                    <?php else: ?>
                                        <span class="bg-emerald-50 text-emerald-600 px-2 py-1 rounded-lg text-[10px] font-bold uppercase dash-countdown" data-expire="<?= $album['expires_at'] ?>">Aktif</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex items-center gap-4 text-xs text-gray-500 bg-gray-50 p-3 rounded-xl mt-auto">
                                    <div class="flex items-center gap-1"><i data-lucide="image" size="14"></i> <?= count($album['photos']) ?></div>
                                    <div class="flex items-center gap-1"><i data-lucide="clock" size="14"></i> <?= date('d M', $album['created_at']) ?></div>
                                </div>

                                <div class="flex gap-2 pt-1">
                                    <button onclick="copyLink('<?= $id ?>')" class="flex-[2] bg-[#fbdc00] text-gray-900 py-2.5 rounded-xl text-xs font-bold btn-touch flex items-center justify-center gap-2">
                                        <i data-lucide="copy" size="14"></i> Link
                                    </button>
                                    <button onclick="openEdit('<?= $id ?>')" class="flex-1 bg-gray-100 text-gray-600 py-2.5 rounded-xl text-xs font-bold btn-touch flex items-center justify-center hover:bg-gray-200" title="Edit Proyek">
                                        <i data-lucide="edit-3" size="14"></i>
                                    </button>
                                    <button onclick="deleteAlbum('<?= $id ?>')" class="px-3 bg-red-50 text-red-500 rounded-xl hover:bg-red-500 hover:text-white transition-colors btn-touch">
                                        <i data-lucide="trash-2" size="16"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>

            <!-- Modal Edit Proyek -->
            <div id="edit-panel" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-3xl w-full max-w-3xl max-h-[90vh] flex flex-col shadow-2xl animate-in zoom-in duration-300">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center shrink-0">
                        <div>
                            <h3 class="font-bold text-xl">Edit Proyek</h3>
                            <p class="text-xs text-gray-500 font-mono mt-1" id="edit_project_title">ID: Memuat...</p>
                        </div>
                        <button onclick="closeEdit()" class="text-gray-400 hover:text-gray-800 bg-gray-100 p-2 rounded-full"><i data-lucide="x" size="20"></i></button>
                    </div>
                    
                    <div class="p-6 overflow-y-auto custom-scrollbar flex-1 bg-gray-50">
                        <input type="hidden" id="edit_project_id">
                        
                        <div class="mb-6 flex justify-between items-end">
                            <h4 class="font-bold text-sm uppercase tracking-wider text-gray-600">Daftar Foto</h4>
                            <!-- Form Tambah Foto Cepat -->
                            <form id="editAddForm" class="m-0">
                                <input type="file" id="editFileInput" multiple hidden accept="image/*">
                                <button type="button" onclick="document.getElementById('editFileInput').click()" class="bg-[#355faa] text-white px-4 py-2 rounded-lg text-xs font-bold flex items-center gap-2 hover:bg-[#2d5191] btn-touch">
                                    <i data-lucide="plus" size="14"></i> Tambah Foto Baru
                                </button>
                            </form>
                        </div>
                        
                        <div id="editPhotoGrid" class="grid grid-cols-3 md:grid-cols-5 gap-3">
                            <!-- Diisi oleh JS -->
                            <p class="col-span-full text-center text-sm text-gray-400">Memuat foto...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Toasts Container (Multi-Queue) -->
            <div id="toastContainer" class="fixed bottom-6 right-6 z-[60] flex flex-col gap-3">
                <!-- Toasts will be injected here by JS -->
            </div>

            <!-- Floating Action Button (Mobile Only) -->
            <button onclick="toggleCreate()" class="md:hidden fixed bottom-6 right-6 w-14 h-14 bg-[#fbdc00] text-gray-900 rounded-full shadow-glow flex items-center justify-center btn-touch z-40 border-2 border-white">
                <i data-lucide="plus" size="28" class="stroke-[3px]"></i>
            </button>
        </div>
    </div>

    <?php elseif ($mode === 'customer_view'): ?>
    <!-- ========================================= -->
    <!-- 3. HALAMAN CUSTOMER (CLEAN & MOBILE)      -->
    <!-- ========================================= -->
    <?php $paket = isset($current_album['paket']) ? $current_album['paket'] : 'Self Photo'; ?>
    <div class="flex-1 bg-[#f9fafb] flex flex-col h-screen overflow-hidden relative">
        
        <!-- HEADER MODE GIF (FIXED POSITION, WARNA KUNING) -->
        <div id="gifSelectionHeader" class="hidden fixed top-0 left-0 right-0 bg-[#fbdc00] text-gray-900 px-5 py-4 z-[60] shadow-lg flex items-center justify-between animate-in slide-in-from-top-2">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-[#355faa]/10 rounded-full flex items-center justify-center shrink-0">
                    <i data-lucide="mouse-pointer-click" size="16" class="text-[#355faa]"></i>
                </div>
                <span class="text-xs md:text-sm font-bold tracking-wide">Pilih beberapa foto untuk membuat GIF</span>
            </div>
            <button onclick="toggleGifMode()" class="p-2 bg-black/5 rounded-full hover:bg-black/10 transition-all btn-touch shrink-0 text-gray-900">
                <i data-lucide="x" size="18"></i>
            </button>
        </div>

        <!-- Header Customer Normal -->
        <header class="bg-white/90 backdrop-blur-md px-5 py-4 flex justify-between items-center border-b border-gray-200 shrink-0 z-20 absolute top-0 w-full shadow-sm">
            <div class="overflow-hidden">
                <p class="text-[10px] font-bold text-[#355faa] uppercase tracking-widest mb-0.5"><?= htmlspecialchars($paket) ?></p>
                <h1 class="text-gray-900 font-bold text-lg truncate max-w-[200px] leading-tight"><?= htmlspecialchars($current_album['name']) ?></h1>
            </div>
            <div class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center text-[#355faa]">
                <span class="text-xs font-bold"><?= count($current_album['photos']) ?></span>
            </div>
        </header>

        <!-- Galeri Foto -->
        <main class="flex-1 overflow-y-auto pt-20 pb-32 px-4 md:px-8 mt-4 custom-scrollbar bg-[#f9fafb]">
            
            <!-- COUNTDOWN LEBIH MENONJOL -->
            <div class="mb-8 mt-2 bg-[#355faa] text-white rounded-2xl p-5 shadow-lg shadow-blue-900/10 flex items-center gap-4">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
                    <i data-lucide="clock" size="24" class="text-[#fbdc00]"></i>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-white/80 mb-1">Waktu mundur untuk link diakses</p>
                    <p class="text-lg md:text-2xl font-black tracking-wide" id="timer" data-expire="<?= $current_album['expires_at'] ?>">Menghitung...</p>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3" id="galleryGrid">
                <?php foreach ($current_album['photos'] as $idx => $photo): ?>
                <div class="photo-item relative aspect-[4/5] bg-white rounded-xl overflow-hidden group shadow-sm border border-gray-100">
                    <img src="<?= $photo['url'] ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" loading="lazy">
                    
                    <!-- Overlay Download -->
                    <?php 
                        $ext = pathinfo($photo['file'], PATHINFO_EXTENSION);
                        $custom_dl_name = "Snap Fun_" . $paket . "_" . $current_album['name'] . "_" . ($idx + 1) . "." . $ext;
                    ?>
                    <div class="normal-overlay absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-3">
                        <a href="index.php?download_file=<?= urlencode($photo['file']) ?>&dl_name=<?= urlencode($custom_dl_name) ?>" class="w-full bg-white text-[#355faa] py-2 rounded-lg text-[10px] font-bold text-center uppercase tracking-widest hover:bg-gray-50 shadow-lg btn-touch">
                            Unduh
                        </a>
                    </div>

                    <!-- Selection Overlay (GIF Mode) -->
                    <label class="selection-overlay absolute inset-0 bg-white/0 hidden cursor-pointer">
                        <input type="checkbox" class="gif-checkbox hidden" value="<?= $photo['url'] ?>">
                        <div class="absolute inset-0 border-4 border-transparent transition-all flex items-start justify-end p-2">
                            <div class="check-indicator w-6 h-6 bg-[#355faa] rounded-full text-white flex items-center justify-center shadow-lg opacity-0 transform scale-50 transition-all">
                                <i data-lucide="check" size="14"></i>
                            </div>
                        </div>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Footer -->
            <div class="mt-10 text-center px-6 pb-12">
                <p class="text-gray-400 text-xs font-bold uppercase tracking-[0.3em]">Snap Fun Studio</p>
            </div>
        </main>

        <!-- Bottom Bar (Actions) -->
        <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 pt-4 pb-6 px-6 z-30 shadow-[0_-5px_20px_rgba(0,0,0,0.05)]">
            <!-- Normal Mode -->
            <div id="normalActions" class="flex gap-3">
                <button onclick="toggleGifMode()" class="flex-1 bg-white border border-gray-200 text-gray-700 h-14 rounded-2xl font-bold text-[10px] md:text-xs uppercase tracking-widest shadow-sm hover:border-[#355faa] hover:text-[#355faa] btn-touch flex items-center justify-center gap-2 transition-all">
                    <i data-lucide="film" size="18"></i> Buat GIF
                </button>
                <button onclick="downloadAll()" class="flex-[2] bg-[#fbdc00] text-gray-900 h-14 rounded-2xl font-bold text-xs md:text-sm uppercase tracking-widest shadow-lg shadow-yellow-500/20 btn-touch flex items-center justify-center gap-2">
                    <i data-lucide="download-cloud" size="20"></i> Simpan Semua
                </button>
            </div>
            
            <!-- GIF Mode Actions -->
            <div id="gifActions" class="hidden flex gap-3">
                <button onclick="toggleGifMode()" class="flex-1 bg-gray-100 text-gray-600 h-14 rounded-2xl font-bold text-xs uppercase tracking-widest btn-touch">Batal</button>
                <button onclick="generateGIF()" id="btnGenerateGif" class="flex-[2] bg-[#355faa] text-white h-14 rounded-2xl font-bold text-xs uppercase tracking-widest shadow-lg shadow-blue-900/20 btn-touch flex items-center justify-center gap-2">
                    Proses GIF (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>

        <!-- Download Info Modal (Sabar Ya Pinpin) -->
        <div id="downloadModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-6">
            <div class="bg-white p-8 rounded-[2rem] w-full max-w-sm shadow-2xl animate-in zoom-in duration-300 text-center relative">
                <!-- Custom Hostinger Animation Image -->
                <img src="<?= htmlspecialchars($pinpin_anim_url) ?>?v=<?= time() ?>" alt="Pinpin Loading" class="w-32 h-32 mx-auto mb-4 object-contain" onerror="this.onerror=null; this.src=''; this.nextElementSibling.classList.remove('hidden');">
                <!-- Fallback Spinner if Image is Missing -->
                <div class="anim-fallback hidden animate-spin rounded-full h-16 w-16 border-4 border-gray-200 border-t-[#355faa] mx-auto mb-6 mt-4"></div>
                
                <h3 class="font-bold text-xl text-gray-900 mb-2">Tunggu Sebentar...</h3>
                <p class="text-sm text-gray-500 font-medium leading-relaxed">Sabar ya pinpin lagi siapin file kamu buat di download</p>
            </div>
        </div>

        <!-- GIF Result Modal -->
        <div id="gifModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-6">
            <div class="bg-white p-6 rounded-[2rem] w-full max-w-sm shadow-2xl animate-in zoom-in duration-300">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-lg text-gray-900">GIF Anda Siap!</h3>
                    <button onclick="closeGifModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x"></i></button>
                </div>
                <div class="aspect-square bg-gray-100 rounded-xl overflow-hidden mb-4 border border-gray-200 flex items-center justify-center relative">
                    <img id="gifResultImage" class="w-full h-full object-contain">
                    <div id="gifLoading" class="hidden absolute inset-0 bg-white/80 flex flex-col items-center justify-center">
                        <div class="animate-spin rounded-full h-10 w-10 border-4 border-gray-200 border-t-[#355faa] mb-2"></div>
                        <p class="text-[10px] font-bold text-[#355faa] uppercase tracking-widest">Memproses...</p>
                    </div>
                </div>
                <a id="gifDownloadLink" href="#" download="SnapFun_GIF.gif" class="w-full bg-[#355faa] text-white py-3 rounded-xl font-bold flex items-center justify-center gap-2 mb-2 btn-touch">
                    <i data-lucide="download" size="18"></i> Unduh GIF
                </a>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ========================================= -->
    <!-- 4. HALAMAN ERROR / EXPIRED                -->
    <!-- ========================================= -->
    <div class="flex-1 flex flex-col items-center justify-center p-8 text-center bg-[#f9fafb]">
        <div class="w-20 h-20 bg-gray-200 text-gray-400 rounded-full flex items-center justify-center mb-6">
            <i data-lucide="alert-circle" size="40"></i>
        </div>
        <h2 class="text-2xl font-bold mb-2 text-gray-900">Galeri Tidak Tersedia</h2>
        <p class="text-gray-500 text-sm mb-8 leading-relaxed max-w-xs mx-auto">
            Proyek ini mungkin telah kedaluwarsa atau ID yang Anda masukkan salah.
        </p>
        <a href="index.php" class="px-8 py-3 bg-[#355faa] text-white rounded-xl font-bold text-sm shadow-lg shadow-blue-900/20">Kembali</a>
    </div>
    <?php endif; ?>

    <!-- JAVASCRIPT GLOBAL -->
    <script>
        lucide.createIcons();

        // =========================================
        // SCRIPT ADMIN (MULTI-QUEUE SEQUENTIAL UPLOAD)
        // =========================================
        <?php if ($mode === 'admin_dashboard'): ?>
        
        let activeUploads = 0; // Melacak jumlah upload yang sedang berjalan

        // Safe-Lock: Peringatan jika pengguna mencoba pindah halaman saat proses upload belum selesai
        window.addEventListener('beforeunload', function (e) {
            if (activeUploads > 0) {
                e.preventDefault();
                e.returnValue = 'Proses upload sedang berjalan. Jika Anda pindah halaman, upload akan gagal atau terputus.';
            }
        });

        function toggleCreate() {
            document.getElementById('create-panel').classList.toggle('hidden');
        }

        const fileInput = document.getElementById('fileInput');
        if(fileInput) {
            fileInput.onchange = function() {
                const count = this.files.length;
                document.getElementById('fileCount').innerText = count > 0 ? count + " Foto Dipilih" : "";
            };
        }

        // SISTEM UPLOAD BANYAK PROYEK BERSAMAAN (QUEUE)
        const createForm = document.getElementById('createForm');
        if(createForm) {
            createForm.onsubmit = async function(e) {
                e.preventDefault();
                
                // 1. Ambil File & Data
                const filesArray = Array.from(document.getElementById('fileInput').files);
                if(filesArray.length === 0) { alert('Pilih foto terlebih dahulu!'); return; }
                
                const nameVal = document.getElementById('formName').value;
                const paketVal = document.getElementById('formPaket').value;
                const hoursVal = document.getElementById('formHours').value;
                
                // 2. Reset UI Form agar bisa dipakai lagi langsung
                toggleCreate();
                createForm.reset();
                document.getElementById('fileCount').innerText = "";
                
                activeUploads++; // Tambah antrean
                
                // 3. Buat UI Toast Khusus untuk Proyek Ini (Queue)
                const toastId = 'toast_' + Date.now();
                const toastHtml = `
                    <div id="${toastId}" class="bg-white border border-gray-200 p-4 rounded-2xl shadow-2xl w-80 flex items-center gap-4 animate-in slide-in-from-bottom-5">
                        <div class="w-10 h-10 rounded-full border-4 border-gray-100 border-t-[#355faa] animate-spin flex-shrink-0 spinner"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1 truncate title">${nameVal}</p>
                            <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden mb-1">
                                <div class="h-full bg-[#355faa] transition-all duration-300 progress-bar" style="width: 0%"></div>
                            </div>
                            <p class="text-[10px] font-bold text-right text-[#355faa] progress-text">Memulai...</p>
                        </div>
                    </div>
                `;
                document.getElementById('toastContainer').insertAdjacentHTML('beforeend', toastHtml);
                
                const toastEl = document.getElementById(toastId);
                const pBar = toastEl.querySelector('.progress-bar');
                const pText = toastEl.querySelector('.progress-text');
                const spinner = toastEl.querySelector('.spinner');

                try {
                    // LANGKAH 1: Inisialisasi Proyek (Metadata)
                    const fdInit = new FormData();
                    fdInit.append('action', 'init_album');
                    fdInit.append('name', nameVal);
                    fdInit.append('paket', paketVal);
                    fdInit.append('hours', hoursVal);
                    
                    const resInit = await fetch('index.php', { method: 'POST', body: fdInit });
                    const dataInit = await resInit.json();
                    
                    if(!dataInit.success) throw new Error("Gagal inisialisasi");
                    const albumId = dataInit.album_id;

                    // LANGKAH 2: Upload File Satu per Satu
                    for(let i = 0; i < filesArray.length; i++) {
                        pText.innerText = `${i+1} dari ${filesArray.length}`;
                        const percent = Math.round(((i) / filesArray.length) * 100);
                        pBar.style.width = percent + "%";

                        const fdUpload = new FormData();
                        fdUpload.append('action', 'upload_single');
                        fdUpload.append('album_id', albumId);
                        fdUpload.append('photo', filesArray[i]);

                        await fetch('index.php', { method: 'POST', body: fdUpload });
                    }

                    // Selesai
                    pBar.style.width = "100%";
                    pText.innerText = "Selesai!";
                    pText.classList.replace('text-[#355faa]', 'text-emerald-500');
                    pBar.classList.replace('bg-[#355faa]', 'bg-emerald-500');
                    spinner.classList.remove('animate-spin');
                    spinner.classList.add('bg-emerald-500', 'border-none');
                    
                    // Bersihkan toast & Refresh halaman jika semua antrean selesai
                    setTimeout(() => {
                        toastEl.remove();
                        activeUploads--;
                        if (activeUploads === 0) window.location.reload(); 
                    }, 2500);

                } catch (error) {
                    pText.innerText = "Gagal!";
                    pText.classList.replace('text-[#355faa]', 'text-red-500');
                    pBar.classList.replace('bg-[#355faa]', 'bg-red-500');
                    spinner.classList.remove('animate-spin');
                    spinner.classList.add('bg-red-500', 'border-none');
                    
                    setTimeout(() => {
                        toastEl.remove();
                        activeUploads--;
                    }, 5000);
                }
            };
        }

        async function deleteAlbum(id) {
            if(!confirm('Hapus proyek permanen?')) return;
            const fd = new FormData(); fd.append('action', 'delete_album'); fd.append('id', id);
            await fetch('index.php', { method:'POST', body:fd });
            window.location.reload();
        }

        function copyLink(id) {
            const link = window.location.origin + window.location.pathname + '?album=' + id;
            navigator.clipboard.writeText(link);
            alert('Link Tersalin!');
        }

        // Admin Dash Countdown Timer Fix
        document.querySelectorAll('.dash-countdown').forEach(el => {
            const updateTimer = () => {
                const diff = parseInt(el.dataset.expire) * 1000 - new Date().getTime();
                if(diff < 0) { el.innerText = 'Expired'; return; }
                const d = Math.floor(diff / (1000 * 60 * 60 * 24));
                const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                el.innerText = `${d} Hari ${h} Jam`;
            };
            updateTimer();
            setInterval(updateTimer, 60000); 
        });

        // FUNGSI EDIT PROYEK
        async function openEdit(id) {
            document.getElementById('edit-panel').classList.remove('hidden');
            document.getElementById('edit_project_id').value = id;
            document.getElementById('edit_project_title').innerText = "ID: " + id;
            await loadEditPhotos(id);
        }
        
        function closeEdit() {
            if (activeUploads > 0) {
                alert("Harap tunggu hingga proses upload foto baru selesai sebelum menutup panel.");
                return;
            }
            document.getElementById('edit-panel').classList.add('hidden');
            window.location.reload(); 
        }

        async function loadEditPhotos(id) {
            const fd = new FormData(); fd.append('action', 'get_album'); fd.append('id', id);
            const res = await fetch('index.php', { method: 'POST', body: fd });
            const data = await res.json();
            
            const grid = document.getElementById('editPhotoGrid');
            grid.innerHTML = '';
            
            if(data.success && data.album.photos.length > 0) {
                data.album.photos.forEach((p) => {
                    grid.innerHTML += `
                        <div class="relative aspect-square bg-white rounded-xl overflow-hidden group shadow-sm border border-gray-200">
                            <img src="${p.url}" class="w-full h-full object-cover">
                            <button type="button" onclick="deleteSinglePhoto('${id}', '${p.url}')" class="absolute top-2 right-2 bg-red-500 text-white p-2 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity btn-touch" title="Hapus Foto">
                                <i data-lucide="trash-2" size="14"></i>
                            </button>
                        </div>
                    `;
                });
                lucide.createIcons();
            } else {
                grid.innerHTML = '<p class="col-span-full text-center text-sm text-gray-400 py-4">Proyek ini tidak memiliki foto.</p>';
            }
        }

        async function deleteSinglePhoto(id, url) {
            if(!confirm('Hapus foto ini?')) return;
            const fd = new FormData(); fd.append('action', 'delete_photo'); fd.append('id', id); fd.append('url', url);
            await fetch('index.php', { method:'POST', body: fd });
            loadEditPhotos(id);
        }

        // TAMBAH FOTO BARU KE PROYEK YANG SUDAH ADA (SEQUENTIAL + PROTECTED)
        const editFileInput = document.getElementById('editFileInput');
        if(editFileInput) {
            editFileInput.onchange = async function() {
                const filesArray = Array.from(this.files);
                if(filesArray.length === 0) return;
                
                const id = document.getElementById('edit_project_id').value;
                const grid = document.getElementById('editPhotoGrid');
                
                activeUploads++; // Lindungi agar user tidak close panel / refresh
                
                for(let i=0; i<filesArray.length; i++) {
                    grid.innerHTML = `<p class="col-span-full text-center text-sm text-[#355faa] py-4 font-bold animate-pulse">Mengunggah ${i+1} dari ${filesArray.length} foto...</p>`;
                    const fd = new FormData();
                    fd.append('action', 'upload_single');
                    fd.append('album_id', id);
                    fd.append('photo', filesArray[i]);
                    await fetch('index.php', { method: 'POST', body: fd });
                }
                
                this.value = ''; // clear input
                activeUploads--;
                loadEditPhotos(id);
            };
        }
        <?php endif; ?>

        // =========================================
        // SCRIPT CUSTOMER 
        // =========================================
        <?php if ($mode === 'customer_view'): ?>
        const albumData = <?= json_encode(['id' => $current_album['id'], 'paket' => $paket, 'name' => $current_album['name']]) ?>;
        const photos = <?= json_encode($current_album['photos']) ?>;
        
        // Timer
        const timerEl = document.getElementById('timer');
        if(timerEl) {
            const updateTimer = () => {
                const diff = parseInt(timerEl.dataset.expire) * 1000 - new Date().getTime();
                if(diff < 0) { window.location.reload(); return; }
                const d = Math.floor(diff / (1000 * 60 * 60 * 24));
                const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                timerEl.innerText = `${d} Hari ${h} Jam ${m} Menit`;
            };
            updateTimer();
            setInterval(updateTimer, 1000);
        }

        // Logic Deteksi Perangkat & Unduh Semua Cerdas
        async function downloadAll() {
            // Deteksi perangkat Apple (iOS / macOS Touch)
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            
            // Tampilkan Modal "Sabar ya pinpin..."
            const modal = document.getElementById('downloadModal');
            modal.classList.remove('hidden');

            if (isIOS) {
                // PENANGANAN KHUSUS IPHONE (Gunakan ZIP)
                setTimeout(() => {
                    window.location.href = `index.php?download_zip=1&album_id=${albumData.id}`;
                    // Sembunyikan modal setelah file dipanggil (iOS akan handle prompt secara native)
                    setTimeout(() => { modal.classList.add('hidden'); }, 3000);
                }, 1000); 
                
            } else {
                // PENANGANAN ANDROID / WINDOWS (Unduh Sequential Tanpa ZIP)
                for(let i=0; i<photos.length; i++) {
                    const a = document.createElement('a');
                    const ext = photos[i].file.split('.').pop();
                    const customName = `Snap Fun_${albumData.paket}_${albumData.name}_${i+1}.${ext}`;
                    
                    a.href = `index.php?download_file=${encodeURIComponent(photos[i].file)}&dl_name=${encodeURIComponent(customName)}`;
                    a.download = customName;
                    document.body.appendChild(a); 
                    a.click(); 
                    document.body.removeChild(a);
                    
                    // Jeda agar browser Android tidak memblokir spam klik
                    await new Promise(r => setTimeout(r, 600)); 
                }
                modal.classList.add('hidden');
            }
        }

        // Logika GIF
        let gifMode = false;

        function toggleGifMode() {
            gifMode = !gifMode;
            const overlays = document.querySelectorAll('.selection-overlay');
            const normalOverlays = document.querySelectorAll('.normal-overlay');
            const gifHeader = document.getElementById('gifSelectionHeader');
            
            if (gifMode) {
                document.getElementById('normalActions').classList.add('hidden');
                document.getElementById('gifActions').classList.remove('hidden');
                if (gifHeader) gifHeader.classList.remove('hidden');
                overlays.forEach(el => el.classList.remove('hidden'));
                normalOverlays.forEach(el => el.classList.add('hidden')); 
            } else {
                document.getElementById('normalActions').classList.remove('hidden');
                document.getElementById('gifActions').classList.add('hidden');
                if (gifHeader) gifHeader.classList.add('hidden');
                overlays.forEach(el => {
                    el.classList.add('hidden');
                    el.querySelector('input').checked = false; 
                });
                normalOverlays.forEach(el => el.classList.remove('hidden'));
                updateSelectedCount();
            }
        }

        document.querySelectorAll('.gif-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });

        function updateSelectedCount() {
            const count = document.querySelectorAll('.gif-checkbox:checked').length;
            document.getElementById('selectedCount').innerText = count;
        }

        function generateGIF() {
            const selectedEls = document.querySelectorAll('.gif-checkbox:checked');
            if (selectedEls.length < 2) {
                alert("Pilih minimal 2 foto untuk membuat GIF.");
                return;
            }

            const images = Array.from(selectedEls).map(el => el.value);
            
            const modal = document.getElementById('gifModal');
            const img = document.getElementById('gifResultImage');
            const loading = document.getElementById('gifLoading');
            const dlBtn = document.getElementById('gifDownloadLink');
            
            modal.classList.remove('hidden');
            img.classList.add('hidden');
            loading.classList.remove('hidden');
            dlBtn.classList.add('hidden');

            const tempImg = new Image();
            tempImg.src = images[0];
            tempImg.onload = function() {
                const aspectRatio = tempImg.naturalWidth / tempImg.naturalHeight;
                const gifW = 600; 
                const gifH = Math.round(gifW / aspectRatio);

                gifshot.createGIF({
                    images: images,
                    gifWidth: gifW,
                    gifHeight: gifH,
                    interval: 0.5, 
                    numFrames: 10, 
                    sampleInterval: 10 
                }, function(obj) {
                    if(!obj.error) {
                        img.src = obj.image;
                        img.classList.remove('hidden');
                        loading.classList.add('hidden');
                        dlBtn.href = obj.image;
                        dlBtn.classList.remove('hidden');
                    }
                });
            };
        }

        function closeGifModal() {
            document.getElementById('gifModal').classList.add('hidden');
        }
        <?php endif; ?>
    </script>
</body>
</html>