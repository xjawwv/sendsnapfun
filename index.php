<?php
/**
 * Snap Link - Link Photo Snap Fun (G-Drive API Version)
 * Storage: Google Drive (Tanpa membebani Hosting)
 * UI/UX: Native Gallery Web, GIF Maker, ZIP Download Client-Side
 */

session_start();

// ==========================================
// --- KONFIGURASI UTAMA ---
// ==========================================
$db_file = 'database.json';
$admin_password = 'oresnaporefun'; // GANTI PASSWORD INI UNTUK KEAMANAN
$favicon_url = 'snaplink.png'; // GANTI DENGAN URL/NAMA FILE FAVICON ANDA (contoh: 'https://domainanda.com/logo.png')
$pinpin_anim_url = 'pinpin.gif'; // Animasi loading (jika ada)

// Google Drive API Key (Digunakan untuk Backend & Frontend)
$gdrive_api_key = 'AIzaSyDCLkm6elVRsozVyg48Aejd3K1nEl-7U2g';

// Inisialisasi Database
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

// ==========================================
// --- LOGIKA UTAMA & API ---
// ==========================================

$mode = 'admin_login'; 
$current_album = null;

if (isset($_GET['album'])) {
    $mode = 'customer_view';
    $db = get_db();
    $id = strtoupper($_GET['album']);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    if ($_POST['password'] === $admin_password) {
        $_SESSION['is_admin'] = true;
        header("Location: index.php");
        exit;
    } else {
        $login_error = "Password salah!";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Fungsi Ekstrak ID Folder dari Link Google Drive
function get_drive_folder_id($url) {
    preg_match('/(?:folders\/|id=)([\w-]+)/', $url, $matches);
    return $matches[1] ?? null;
}

// API Backend (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_SESSION['is_admin'])) {
    header('Content-Type: application/json');
    $db = get_db();

    // 1. Buat Single Link Biasa
    if ($_POST['action'] === 'create_album') {
        $album_id = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        $name = $_POST['name'] ?? 'Proyek Tanpa Judul';
        $paket = $_POST['paket'] ?? 'Self Photo';
        $drive_link = $_POST['drive_link'] ?? '';
        $group_name = trim($_POST['group_name'] ?? ''); // FOLDER DASHBOARD
        $hours = (int)($_POST['hours'] ?? 168);
        
        $folder_id = get_drive_folder_id($drive_link);

        if (!$folder_id) {
            echo json_encode(['success' => false, 'message' => 'Link G-Drive tidak valid.']);
            exit;
        }

        $db[$album_id] = [
            'id' => $album_id,
            'name' => $name,
            'paket' => $paket,
            'drive_link' => $drive_link,
            'folder_id' => $folder_id,
            'group_name' => $group_name,
            'expires_at' => time() + ($hours * 3600),
            'created_at' => time()
        ];
        save_db($db);
        echo json_encode(['success' => true, 'album_id' => $album_id]);
        exit;
    }

    // 2. BATCH Create Otomatis dari Folder Utama
    if ($_POST['action'] === 'create_album_batch') {
        $paket = $_POST['paket'] ?? 'Self Photo';
        $drive_link = $_POST['drive_link'] ?? '';
        $custom_group_name = trim($_POST['group_name'] ?? '');
        $hours = (int)($_POST['hours'] ?? 168);
        
        $main_folder_id = get_drive_folder_id($drive_link);

        if (!$main_folder_id) {
            echo json_encode(['success' => false, 'message' => 'Link G-Drive (Folder Utama) tidak valid.']);
            exit;
        }

        $parent_name = "Proyek Batch";
        if (empty($custom_group_name)) {
            $parent_info_url = "https://www.googleapis.com/drive/v3/files/{$main_folder_id}?key={$gdrive_api_key}&fields=name";
            $parent_response = @file_get_contents($parent_info_url);
            if ($parent_response) {
                $parent_data = json_decode($parent_response, true);
                if (isset($parent_data['name'])) {
                    $parent_name = $parent_data['name'];
                }
            }
            $final_group_name = $parent_name;
        } else {
            $final_group_name = $custom_group_name;
        }

        $query = urlencode("'" . $main_folder_id . "' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false");
        $api_url = "https://www.googleapis.com/drive/v3/files?q={$query}&key={$gdrive_api_key}&fields=files(id,name)&pageSize=1000";
        
        $response = @file_get_contents($api_url);
        if (!$response) {
            echo json_encode(['success' => false, 'message' => 'Gagal akses API. Pastikan Folder Utama sudah di-set ke "Siapa saja yang memiliki link".']);
            exit;
        }

        $data = json_decode($response, true);
        if (!isset($data['files']) || count($data['files']) === 0) {
            echo json_encode(['success' => false, 'message' => 'Tidak ada sub-folder klien ditemukan di dalam link tersebut.']);
            exit;
        }

        $count = 0;
        foreach ($data['files'] as $folder) {
            $album_id = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
            $db[$album_id] = [
                'id' => $album_id,
                'name' => $folder['name'],
                'paket' => $paket,
                'drive_link' => 'https://drive.google.com/drive/folders/' . $folder['id'],
                'folder_id' => $folder['id'],
                'group_name' => $final_group_name,
                'expires_at' => time() + ($hours * 3600),
                'created_at' => time()
            ];
            $count++;
        }
        
        save_db($db);
        echo json_encode(['success' => true, 'count' => $count]);
        exit;
    }

    // 3. Get / Update / Delete
    if ($_POST['action'] === 'get_album') {
        echo json_encode(isset($db[$_POST['id']]) ? ['success' => true, 'album' => $db[$_POST['id']]] : ['success' => false]);
        exit;
    }

    if ($_POST['action'] === 'update_album') {
        $id = $_POST['id'];
        if (isset($db[$id])) {
            $folder_id = get_drive_folder_id($_POST['drive_link']);
            if (!$folder_id) { echo json_encode(['success'=>false, 'message'=>'Link Invalid']); exit; }

            $db[$id]['name'] = $_POST['name'];
            $db[$id]['paket'] = $_POST['paket'];
            $db[$id]['drive_link'] = $_POST['drive_link'];
            $db[$id]['folder_id'] = $folder_id;
            $db[$id]['group_name'] = trim($_POST['group_name'] ?? '');
            save_db($db);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    if ($_POST['action'] === 'delete_album') {
        if (isset($db[$_POST['id']])) { unset($db[$_POST['id']]); save_db($db); }
        echo json_encode(['success' => true]);
        exit;
    }

    // 4. HAPUS FOLDER SEKALIGUS
    if ($_POST['action'] === 'delete_group') {
        $group_name = $_POST['group_name'];
        foreach ($db as $id => $album) {
            if (($album['group_name'] ?? '') === $group_name) {
                unset($db[$id]);
            }
        }
        save_db($db);
        echo json_encode(['success' => true]);
        exit;
    }

    // 5. BULK DELETE (HAPUS MASAL BERDASARKAN CHECKBOX)
    if ($_POST['action'] === 'delete_bulk') {
        $ids = json_decode($_POST['ids'], true);
        if (is_array($ids)) {
            foreach ($ids as $id) {
                if (isset($db[$id])) unset($db[$id]);
            }
            save_db($db);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // 6. HAPUS SEMUA YANG KEDALUWARSA
    if ($_POST['action'] === 'delete_all_expired') {
        $now = time();
        foreach ($db as $id => $album) {
            if ($now > $album['expires_at']) {
                unset($db[$id]);
            }
        }
        save_db($db);
        echo json_encode(['success' => true]);
        exit;
    }
} 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Snap Link - Drive Gallery</title>
    <link rel="icon" href="<?= htmlspecialchars($favicon_url) ?>?v=<?= time() ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gifshot/0.3.2/gifshot.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; -webkit-tap-highlight-color: transparent; background-color: #f9fafb; color: #111827; }
        :root { --primary: #355faa; --action: #fbdc00; }
        .dot-grid { background-image: radial-gradient(#d1d5db 1px, transparent 1px); background-size: 20px 20px; }
        .btn-touch { transition: transform 0.1s; }
        .btn-touch:active { transform: scale(0.96); }
        .shadow-glow { box-shadow: 0 10px 40px -10px rgba(53, 95, 170, 0.2); }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .gif-checkbox:checked + div { border-color: var(--primary); background-color: rgba(53, 95, 170, 0.1); }
        .gif-checkbox:checked + div .check-indicator { opacity: 1; transform: scale(1); }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <?php if ($mode === 'admin_login'): ?>
    <!-- 1. HALAMAN LOGIN ADMIN -->
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
                <?php if (isset($login_error)): ?><p class="text-red-500 text-xs font-bold"><?= $login_error ?></p><?php endif; ?>
                <button type="submit" class="w-full bg-[#355faa] text-white py-4 rounded-xl font-bold text-sm uppercase tracking-wider btn-touch">Masuk Dashboard</button>
            </form>
        </div>
    </div>

    <?php elseif ($mode === 'admin_dashboard'): ?>
    <!-- 2. DASHBOARD ADMIN -->
    <?php 
        $db_all = get_db();
        $active_links = 0;
        
        $expired_projects = [];
        $grouped_projects = [];
        $loose_projects = [];
        
        // PENGELOMPOKAN DATA
        foreach($db_all as $id => $album) { 
            if(time() > $album['expires_at']) {
                $expired_projects[$id] = $album;
            } else {
                $active_links++; 
                if(!empty($album['group_name'])) {
                    $grouped_projects[$album['group_name']][$id] = $album;
                } else {
                    $loose_projects[$id] = $album;
                }
            }
        }
        
        $expired_projects = array_reverse($expired_projects, true);
        $loose_projects = array_reverse($loose_projects, true);
        foreach($grouped_projects as $k => $v) {
            $grouped_projects[$k] = array_reverse($v, true);
        }
    ?>
    <div class="flex-1 bg-[#f3f4f6] dot-grid flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="hidden md:flex w-72 bg-white border-r border-gray-200 flex-col z-20 shadow-sm">
            <div class="p-6 border-b border-gray-100">
                <div class="flex items-center gap-3 mb-1">
                    <div class="w-8 h-8 bg-[#355faa] rounded-lg flex items-center justify-center text-white"><i data-lucide="folder-cloud" size="18"></i></div>
                    <span class="font-bold text-lg">Snap Link API</span>
                </div>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider pl-11">Admin Panel</p>
            </div>
            <nav class="flex-1 p-4 space-y-2">
                <div class="bg-blue-50 text-[#355faa] p-3 rounded-xl flex items-center gap-3 font-bold text-sm cursor-pointer">
                    <i data-lucide="folder-open" size="18"></i> Proyek Klien
                </div>
            </nav>
            <div class="p-4 border-t border-gray-100">
                <a href="?logout=true" class="flex items-center gap-3 p-3 rounded-xl text-red-500 hover:bg-red-50 font-bold text-sm"><i data-lucide="log-out" size="18"></i> Keluar</a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col h-full overflow-hidden relative">
            <header class="md:hidden bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center z-20 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-[#355faa] rounded-xl flex items-center justify-center text-white"><i data-lucide="folder-cloud"></i></div>
                    <h2 class="font-bold text-lg leading-none">Snap Link</h2>
                </div>
                <a href="?logout=true" class="p-2 bg-red-50 text-red-500 rounded-lg"><i data-lucide="log-out" size="20"></i></a>
            </header>

            <main class="flex-1 overflow-y-auto p-4 md:p-10 custom-scrollbar relative">
                <!-- Wrapper dengan flex-col gap-8 untuk mencegah bug margin-bottom yang menumpuk -->
                <div class="flex flex-col gap-8 pb-32">
                    
                    <!-- Top Info Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
                        <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                            <p class="text-[10px] md:text-xs text-gray-400 font-bold uppercase mb-2">Total Proyek (Aktif)</p>
                            <p class="text-2xl md:text-3xl font-bold text-gray-800"><?= $active_links ?></p>
                        </div>
                        <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
                            <p class="text-[10px] md:text-xs text-gray-400 font-bold uppercase mb-2">Perlu Dihapus</p>
                            <p class="text-2xl md:text-3xl font-bold text-red-500"><?= count($expired_projects) ?></p>
                        </div>
                        <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm md:col-span-2 flex items-center justify-between">
                            <div>
                                <p class="text-[10px] md:text-xs text-gray-400 font-bold uppercase mb-1">Status Storage Server</p>
                                <p class="text-lg md:text-xl font-bold text-emerald-500 flex items-center gap-2">
                                    <i data-lucide="cloud-check" size="18"></i> 0 MB (G-Drive API)
                                </p>
                            </div>
                            <button onclick="toggleCreate()" class="hidden md:flex bg-[#355faa] text-white px-6 py-3 rounded-xl font-bold text-sm shadow-lg hover:bg-[#2d5191] items-center gap-2 transition-colors">
                                <i data-lucide="plus" size="18"></i> Buat Link Baru
                            </button>
                        </div>
                    </div>

                    <!-- SEKSI PENGINGAT EXPIRED -->
                    <?php if(!empty($expired_projects)): ?>
                    <div class="p-6 bg-red-50 border border-red-200 rounded-[2rem] animate-in zoom-in-95 duration-300">
                        <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4 mb-6">
                            <div>
                                <div class="flex items-center gap-3 mb-2">
                                    <i data-lucide="alert-triangle" class="text-red-600" size="24"></i>
                                    <h3 class="font-bold text-red-800 text-lg uppercase tracking-wider">Perhatian: Hapus Dari Drive</h3>
                                </div>
                                <p class="text-sm text-red-600 font-medium leading-relaxed max-w-3xl">Link klien di bawah ini masa berlakunya sudah habis. Hapus folder fisiknya di Google Drive Anda agar tidak memenuhi kapasitas, lalu hapus riwayatnya dari sini.</p>
                            </div>
                            <button onclick="deleteAllExpired()" class="shrink-0 bg-red-600 text-white px-4 py-3 rounded-xl text-xs font-bold hover:bg-red-700 shadow-md flex items-center justify-center gap-2 btn-touch w-full md:w-auto transition-colors">
                                <i data-lucide="trash-2" size="16"></i> Hapus Semua Riwayat
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($expired_projects as $id => $album): ?>
                            <div class="bg-white p-5 rounded-2xl border border-red-200 shadow-sm flex flex-col gap-3 relative overflow-hidden group hover:border-red-300 transition-colors">
                                <div class="absolute top-0 left-0 w-1 h-full bg-red-500"></div>
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-[10px] font-black text-red-500 uppercase mb-1">Kedaluwarsa</p>
                                        <h4 class="font-bold text-gray-900 truncate max-w-[150px]"><?= htmlspecialchars($album['name']) ?></h4>
                                        <p class="text-xs text-gray-400 font-mono mt-1">ID Folder: <?= htmlspecialchars($album['folder_id']) ?></p>
                                    </div>
                                    <!-- CHECKBOX UNTUK BULK DELETE -->
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" class="bulk-cb w-5 h-5 text-red-500 bg-gray-100 border-gray-200 rounded cursor-pointer transition-all" value="<?= $id ?>">
                                    </div>
                                </div>
                                
                                <div class="flex gap-2 pt-2 mt-auto">
                                    <a href="<?= htmlspecialchars($album['drive_link']) ?>" target="_blank" class="flex-[2] bg-gray-100 text-gray-700 py-2.5 rounded-xl text-xs font-bold btn-touch flex justify-center items-center gap-2 hover:bg-gray-200"><i data-lucide="external-link" size="14"></i> Cek Drive</a>
                                    <button onclick="deleteAlbum('<?= $id ?>')" class="px-4 bg-red-100 text-red-600 rounded-xl hover:bg-red-600 hover:text-white transition-colors btn-touch flex justify-center items-center gap-2 font-bold text-xs" title="Hapus Riwayat Satu Ini"><i data-lucide="check" size="16"></i></button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Form Buat Baru (Sekarang Pop-Up / Modal) -->
                    <div id="create-panel" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-[150] flex items-center justify-center p-4">
                        <div class="bg-white rounded-[2rem] w-full max-w-3xl shadow-2xl p-6 md:p-8 max-h-[90vh] overflow-y-auto custom-scrollbar animate-in zoom-in-95 duration-300">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="font-bold text-xl">Hubungkan Folder G-Drive Baru</h3>
                                <button type="button" onclick="toggleCreate()" class="text-gray-400 hover:bg-gray-100 p-2 rounded-full transition-colors"><i data-lucide="x"></i></button>
                            </div>
                            
                            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" id="formIsBatch" class="w-5 h-5 text-[#355faa] bg-white border-gray-300 rounded focus:ring-[#355faa]">
                                    <span class="text-sm font-bold text-[#355faa]">Tarik Otomatis dari Folder Utama (Batch Processing)</span>
                                </label>
                                <p class="text-xs text-blue-600 mt-2 ml-8">Centang jika G-Drive berisi banyak sub-folder. Sistem akan otomatis mendeteksi dan membuatkan folder & link klien secara instan.</p>
                            </div>

                            <form id="createForm" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="md:col-span-1">
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Paket</label>
                                        <select id="formPaket" class="w-full bg-gray-50 p-4 rounded-xl border border-gray-200 outline-none">
                                            <option value="Self Photo">Self Photo</option>
                                            <option value="Photobox">Photobox</option>
                                            <option value="Pas Photo">Pas Photo</option>
                                        </select>
                                    </div>
                                    <div class="md:col-span-2" id="nameContainer">
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Nama Klien</label>
                                        <input type="text" id="formName" required class="w-full bg-gray-50 p-4 rounded-xl border border-gray-200 outline-none" placeholder="Cth: Sesi Budi & Siska">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Link Google Drive (Folder Utama / Folder Klien)</label>
                                    <input type="url" id="formDriveLink" required class="w-full bg-gray-50 p-4 rounded-xl border border-gray-200 outline-none" placeholder="Paste URL Folder G-Drive disini...">
                                </div>
                                
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Nama Folder di Dashboard (Opsional)</label>
                                    <input type="text" id="formGroupName" class="w-full bg-gray-50 p-4 rounded-xl border border-gray-200 outline-none" placeholder="Kosongkan jika tidak ingin dikelompokkan...">
                                    <p class="text-[10px] text-gray-400 mt-1" id="groupHelperText">Jika mode Tarik Otomatis aktif dan dikosongkan, nama folder Dashboard akan otomatis mengikuti nama folder Google Drive Anda.</p>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-2">Durasi Akses Galeri</label>
                                    <select id="formHours" class="w-full bg-gray-50 p-4 rounded-xl border border-gray-200 outline-none">
                                        <option value="168" selected>1 Minggu</option>
                                        <option value="336">2 Minggu</option>
                                        <option value="720">1 Bulan</option>
                                    </select>
                                </div>
                                <button id="btnSubmitCreate" type="submit" class="w-full bg-[#355faa] text-white py-4 rounded-xl font-bold uppercase tracking-widest flex justify-center items-center gap-2 btn-touch shadow-lg shadow-blue-900/20">
                                    Terbitkan Galeri
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- RENDER FOLDER (GROUPED PROJECTS) -->
                    <?php if(!empty($grouped_projects)): ?>
                    <div>
                        <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wider ml-1 mb-4">Folder Proyek Aktif</h3>
                        <div class="space-y-4">
                            <?php foreach ($grouped_projects as $group_name => $albums): $g_id = md5($group_name); ?>
                            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden group">
                                <div class="p-4 flex justify-between items-center cursor-pointer hover:bg-gray-50 transition-colors btn-toggle-folder" data-target="folder-<?= $g_id ?>">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-[#355faa]">
                                            <i data-lucide="folder" size="24"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($group_name) ?></h4>
                                            <p class="text-xs text-gray-500 font-medium"><?= count($albums) ?> Link Klien Aktif</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button onclick="deleteGroup('<?= htmlspecialchars(addslashes($group_name)) ?>', event)" class="p-2.5 bg-red-50 text-red-500 rounded-xl hover:bg-red-500 hover:text-white transition-colors btn-touch" title="Hapus Seluruh Folder & Isinya">
                                            <i data-lucide="trash-2" size="18"></i>
                                        </button>
                                        <i data-lucide="chevron-down" class="text-gray-400 transition-transform transform duration-300 pointer-events-none"></i>
                                    </div>
                                </div>
                                <div id="folder-<?= $g_id ?>" class="hidden border-t border-gray-100 p-5 bg-gray-50/50">
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                        <?php foreach ($albums as $id => $album): ?>
                                        <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex flex-col gap-3 hover:border-blue-200 transition-colors">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <p class="text-[10px] font-black text-[#355faa] uppercase mb-1"><?= htmlspecialchars($album['paket']) ?></p>
                                                    <h4 class="font-bold text-gray-900 truncate max-w-[150px]"><?= htmlspecialchars($album['name']) ?></h4>
                                                    <p class="text-xs text-gray-400 font-mono mt-1">ID: <?= $id ?></p>
                                                </div>
                                                <!-- CHECKBOX UNTUK BULK DELETE -->
                                                <div class="flex items-center gap-2">
                                                    <span class="bg-emerald-50 text-emerald-600 px-2 py-1 rounded-lg text-[10px] font-bold uppercase dash-countdown" data-expire="<?= $album['expires_at'] ?>">Aktif</span>
                                                    <input type="checkbox" class="bulk-cb w-5 h-5 text-[#355faa] bg-gray-100 border-gray-200 rounded cursor-pointer transition-all" value="<?= $id ?>">
                                                </div>
                                            </div>
                                            <div class="bg-gray-50 p-3 rounded-xl mt-auto text-xs text-gray-500 overflow-hidden text-ellipsis whitespace-nowrap" title="<?= htmlspecialchars($album['folder_id']) ?>">
                                                <i data-lucide="folder" size="14" class="inline text-[#355faa]"></i> <?= htmlspecialchars($album['folder_id']) ?>
                                            </div>
                                            <div class="flex gap-2 pt-1 mt-auto">
                                                <button onclick="copyLink('<?= $id ?>')" class="flex-[2] bg-[#fbdc00] text-gray-900 py-2.5 rounded-xl text-xs font-bold btn-touch flex justify-center gap-2"><i data-lucide="copy" size="14"></i> Link Web</button>
                                                <button onclick="openEdit('<?= $id ?>')" class="flex-1 bg-gray-100 text-gray-600 py-2.5 rounded-xl text-xs font-bold btn-touch hover:bg-gray-200"><i data-lucide="edit-3" size="14" class="mx-auto"></i></button>
                                                <button onclick="deleteAlbum('<?= $id ?>')" class="px-3 bg-red-50 text-red-500 rounded-xl hover:bg-red-500 hover:text-white transition-colors btn-touch"><i data-lucide="trash-2" size="16"></i></button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- RENDER LOOSE PROJECTS (TANPA FOLDER) -->
                    <?php if(!empty($loose_projects) || (empty($db_all) && empty($expired_projects))): ?>
                    <div>
                        <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wider ml-1 mb-4">Proyek Lepas Lainnya</h3>
                        <?php if (empty($loose_projects)): ?>
                            <div class="text-center py-20 bg-white rounded-3xl border border-dashed border-gray-200">
                                <i data-lucide="link-2-off" class="mx-auto text-gray-300 mb-3" size="48"></i>
                                <p class="text-gray-400 text-sm font-bold">Tidak ada link aktif di luar folder.</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="projectsGrid">
                                <?php foreach ($loose_projects as $id => $album): ?>
                                <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex flex-col gap-3 hover:border-blue-200 transition-colors">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="text-[10px] font-black text-[#355faa] uppercase mb-1"><?= htmlspecialchars($album['paket']) ?></p>
                                            <h4 class="font-bold text-gray-900 truncate max-w-[150px]"><?= htmlspecialchars($album['name']) ?></h4>
                                            <p class="text-xs text-gray-400 font-mono mt-1">ID: <?= $id ?></p>
                                        </div>
                                        <!-- CHECKBOX UNTUK BULK DELETE -->
                                        <div class="flex items-center gap-2">
                                            <span class="bg-emerald-50 text-emerald-600 px-2 py-1 rounded-lg text-[10px] font-bold uppercase dash-countdown" data-expire="<?= $album['expires_at'] ?>">Aktif</span>
                                            <input type="checkbox" class="bulk-cb w-5 h-5 text-[#355faa] bg-gray-100 border-gray-200 rounded cursor-pointer transition-all" value="<?= $id ?>">
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 p-3 rounded-xl mt-auto text-xs text-gray-500 overflow-hidden text-ellipsis whitespace-nowrap" title="<?= htmlspecialchars($album['folder_id']) ?>">
                                        <i data-lucide="folder" size="14" class="inline text-[#355faa]"></i> <?= htmlspecialchars($album['folder_id']) ?>
                                    </div>
                                    <div class="flex gap-2 pt-1 mt-auto">
                                        <button onclick="copyLink('<?= $id ?>')" class="flex-[2] bg-[#fbdc00] text-gray-900 py-2.5 rounded-xl text-xs font-bold btn-touch flex justify-center gap-2"><i data-lucide="copy" size="14"></i> Link Web</button>
                                        <button onclick="openEdit('<?= $id ?>')" class="flex-1 bg-gray-100 text-gray-600 py-2.5 rounded-xl text-xs font-bold btn-touch hover:bg-gray-200"><i data-lucide="edit-3" size="14" class="mx-auto"></i></button>
                                        <button onclick="deleteAlbum('<?= $id ?>')" class="px-3 bg-red-50 text-red-500 rounded-xl hover:bg-red-500 hover:text-white transition-colors btn-touch"><i data-lucide="trash-2" size="16"></i></button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                </div>
            </main>

            <!-- ACTION BAR BULK DELETE (Muncul jika ada checkbox yg dicentang) -->
            <div id="bulkActionBar" class="hidden fixed bottom-6 left-1/2 -translate-x-1/2 bg-gray-900 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-5 z-[100] animate-in slide-in-from-bottom-10">
                <span class="text-sm font-bold whitespace-nowrap"><span id="bulkCount">0</span> Terpilih</span>
                <div class="h-6 w-px bg-gray-600"></div>
                <button onclick="deleteBulk()" class="text-red-400 hover:text-red-300 font-bold text-sm flex items-center gap-2 btn-touch transition-colors">
                    <i data-lucide="trash-2" size="18"></i> Hapus Terpilih
                </button>
            </div>

            <!-- Modal Edit -->
            <div id="edit-panel" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-[150] flex items-center justify-center p-4">
                <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-xl">Edit Data</h3>
                        <button onclick="closeEdit()" class="text-gray-400"><i data-lucide="x"></i></button>
                    </div>
                    <form id="editForm" class="space-y-4">
                        <input type="hidden" id="edit_id">
                        <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Paket</label><select id="edit_paket" class="w-full bg-gray-50 p-3 rounded-xl border outline-none"><option value="Self Photo">Self Photo</option><option value="Photobox">Photobox</option><option value="Pas Photo">Pas Photo</option></select></div>
                        <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Nama Klien</label><input type="text" id="edit_name" required class="w-full bg-gray-50 p-3 rounded-xl border outline-none"></div>
                        <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Link Google Drive Baru</label><input type="url" id="edit_drive_link" required class="w-full bg-gray-50 p-3 rounded-xl border outline-none"></div>
                        <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Pindah ke Folder Dashboard</label><input type="text" id="edit_group_name" class="w-full bg-gray-50 p-3 rounded-xl border outline-none" placeholder="Kosongkan jika tak ingin dikelompokkan..."></div>
                        <button type="submit" class="w-full bg-[#355faa] text-white py-3 rounded-xl font-bold btn-touch text-sm mt-4">Simpan</button>
                    </form>
                </div>
            </div>

            <button onclick="toggleCreate()" class="md:hidden fixed bottom-6 right-6 w-14 h-14 bg-[#fbdc00] text-gray-900 rounded-full shadow-glow flex items-center justify-center z-40"><i data-lucide="plus" size="28"></i></button>
        </div>
    </div>

    <?php elseif ($mode === 'customer_view'): ?>
    <!-- 3. HALAMAN CUSTOMER (G-DRIVE API GALLERY) -->
    <?php $paket = isset($current_album['paket']) ? $current_album['paket'] : 'Self Photo'; ?>
    <div class="flex-1 bg-[#f9fafb] flex flex-col h-screen overflow-hidden relative">
        
        <!-- HEADER GIF MODE -->
        <div id="gifSelectionHeader" class="hidden fixed top-0 left-0 right-0 bg-[#fbdc00] text-gray-900 px-5 py-4 z-[60] shadow-lg flex items-center justify-between">
            <div class="flex items-center gap-3">
                <i data-lucide="mouse-pointer-click" size="16" class="text-[#355faa]"></i>
                <span class="text-xs md:text-sm font-bold">Pilih foto untuk GIF</span>
            </div>
            <button onclick="toggleGifMode()" class="p-2 bg-black/5 rounded-full"><i data-lucide="x" size="18"></i></button>
        </div>

        <header class="bg-white/90 backdrop-blur-md px-5 py-4 flex justify-between items-center border-b border-gray-200 shrink-0 z-20 absolute top-0 w-full">
            <div class="overflow-hidden">
                <p class="text-[10px] font-bold text-[#355faa] uppercase tracking-widest mb-0.5"><?= htmlspecialchars($paket) ?></p>
                <h1 class="text-gray-900 font-bold text-lg truncate max-w-[200px]"><?= htmlspecialchars($current_album['name']) ?></h1>
            </div>
            <div class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center text-[#355faa]">
                <i data-lucide="image" size="18"></i>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto pt-20 pb-32 px-4 md:px-8 mt-4 custom-scrollbar bg-[#f9fafb]">
            <div class="mb-8 mt-2 bg-[#355faa] text-white rounded-2xl p-5 flex items-center gap-4">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center"><i data-lucide="clock" size="24" class="text-[#fbdc00]"></i></div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-white/80 mb-1">Akses Galeri Berakhir Dalam</p>
                    <p class="text-lg md:text-2xl font-black" id="timer" data-expire="<?= $current_album['expires_at'] ?>">Menghitung...</p>
                </div>
            </div>

            <!-- Loading State API -->
            <div id="loadingGallery" class="flex flex-col items-center justify-center py-20 text-gray-400">
                <i data-lucide="loader-2" class="animate-spin mb-4" size="40"></i>
                <p class="text-sm font-bold animate-pulse">Menarik foto dari Google Drive...</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3 hidden" id="galleryGrid">
                <!-- Injeksi JS Google Drive API -->
            </div>
            
            <div class="mt-10 text-center px-6 pb-12"><p class="text-gray-400 text-xs font-bold uppercase tracking-[0.3em]">Snap Fun Studio</p></div>
        </main>

        <!-- Bottom Actions -->
        <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 pt-4 pb-6 px-6 z-30 shadow-[0_-5px_20px_rgba(0,0,0,0.05)]">
            <div id="normalActions" class="flex gap-3 hidden">
                <button onclick="toggleGifMode()" class="flex-1 bg-white border border-gray-200 text-gray-700 h-14 rounded-2xl font-bold text-[10px] md:text-xs uppercase shadow-sm flex items-center justify-center gap-2 btn-touch">
                    <i data-lucide="film" size="18"></i> Buat GIF
                </button>
                <button onclick="downloadAll()" class="flex-[2] bg-[#fbdc00] text-gray-900 h-14 rounded-2xl font-bold text-xs md:text-sm uppercase shadow-lg btn-touch flex items-center justify-center gap-2">
                    <i data-lucide="download-cloud" size="20"></i> Simpan Semua
                </button>
            </div>
            
            <div id="gifActions" class="hidden flex gap-3">
                <button onclick="toggleGifMode()" class="flex-1 bg-gray-100 text-gray-600 h-14 rounded-2xl font-bold text-xs uppercase btn-touch">Batal</button>
                <button onclick="generateGIF()" class="flex-[2] bg-[#355faa] text-white h-14 rounded-2xl font-bold text-xs uppercase shadow-lg btn-touch">
                    Proses GIF (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>

        <!-- Progress Modal (Download / ZIP) -->
        <div id="downloadModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-6">
            <div class="bg-white p-8 rounded-[2rem] w-full max-w-sm text-center">
                <img src="<?= htmlspecialchars($pinpin_anim_url) ?>?v=<?= time() ?>" onerror="this.style.display='none'; document.getElementById('fbSpinner').classList.remove('hidden');" class="w-32 h-32 mx-auto mb-4 object-contain">
                <div id="fbSpinner" class="hidden animate-spin rounded-full h-16 w-16 border-4 border-gray-200 border-t-[#355faa] mx-auto mb-6 mt-4"></div>
                <h3 class="font-bold text-xl text-gray-900 mb-2">Tunggu Sebentar...</h3>
                <p id="dlProgressText" class="text-sm text-gray-500 font-medium">Sabar ya pinpin lagi nyiapin foto kamu...</p>
            </div>
        </div>

        <!-- GIF Result Modal -->
        <div id="gifModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-6">
            <div class="bg-white p-6 rounded-[2rem] w-full max-w-sm">
                <div class="flex justify-between items-center mb-4"><h3 class="font-bold text-lg">GIF Anda Siap!</h3><button onclick="closeGifModal()"><i data-lucide="x"></i></button></div>
                <div class="aspect-square bg-gray-100 rounded-xl overflow-hidden mb-4 border flex items-center justify-center relative">
                    <img id="gifResultImage" class="w-full h-full object-contain">
                    <div id="gifLoading" class="absolute inset-0 bg-white/80 flex flex-col items-center justify-center"><i data-lucide="loader-2" class="animate-spin text-[#355faa] mb-2" size="40"></i><p class="text-[10px] font-bold text-[#355faa]">Memproses...</p></div>
                </div>
                <a id="gifDownloadLink" href="#" download="SnapFun_GIF.gif" class="w-full bg-[#355faa] text-white py-3 rounded-xl font-bold flex items-center justify-center gap-2 hidden">
                    <i data-lucide="download" size="18"></i> Unduh GIF
                </a>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- 4. ERROR / EXPIRED -->
    <div class="flex-1 flex flex-col items-center justify-center p-8 text-center bg-[#f9fafb] h-screen">
        <div class="w-24 h-24 bg-red-50 text-red-500 rounded-3xl flex items-center justify-center mb-6"><i data-lucide="clock-4" size="48"></i></div>
        <h2 class="text-2xl font-bold mb-3 text-gray-900">Link Telah Kedaluwarsa</h2>
        <p class="text-gray-500 text-sm">Batas waktu akses galeri proyek ini telah habis.</p>
    </div>
    <?php endif; ?>

    <script>
        lucide.createIcons();

        // SCRIPT ADMIN
        <?php if ($mode === 'admin_dashboard'): ?>
        function toggleCreate() { document.getElementById('create-panel').classList.toggle('hidden'); }

        // SCRIPT BULK DELETE MASAL (CHECKBOX)
        const bulkCbs = document.querySelectorAll('.bulk-cb');
        const bulkActionBar = document.getElementById('bulkActionBar');
        const bulkCount = document.getElementById('bulkCount');

        bulkCbs.forEach(cb => {
            cb.addEventListener('change', () => {
                const count = document.querySelectorAll('.bulk-cb:checked').length;
                if (count > 0) {
                    bulkActionBar.classList.remove('hidden');
                    bulkCount.innerText = count;
                } else {
                    bulkActionBar.classList.add('hidden');
                }
            });
        });

        async function deleteBulk() {
            const selected = Array.from(document.querySelectorAll('.bulk-cb:checked')).map(cb => cb.value);
            if(!confirm(`Hapus ${selected.length} riwayat proyek terpilih secara permanen?`)) return;
            
            const fd = new FormData();
            fd.append('action', 'delete_bulk');
            fd.append('ids', JSON.stringify(selected));
            
            await fetch('index.php', { method: 'POST', body: fd });
            window.location.reload();
        }

        async function deleteAllExpired() {
            if(!confirm('Yakin ingin menghapus SEMUA riwayat link yang telah kedaluwarsa?')) return;
            const fd = new FormData();
            fd.append('action', 'delete_all_expired');
            await fetch('index.php', { method: 'POST', body: fd });
            window.location.reload();
        }

        // Script untuk Animasi Toggle Accordion Foldering
        document.querySelectorAll('.btn-toggle-folder').forEach(btn => {
            btn.addEventListener('click', () => {
                const targetId = btn.getAttribute('data-target');
                const target = document.getElementById(targetId);
                const icon = btn.querySelector('i[data-lucide="chevron-down"]');
                
                if (target.classList.contains('hidden')) {
                    target.classList.remove('hidden');
                    icon.style.transform = 'rotate(180deg)';
                } else {
                    target.classList.add('hidden');
                    icon.style.transform = 'rotate(0deg)';
                }
            });
        });

        // Logic UI untuk Mode Batch (Tarik Otomatis)
        const formIsBatch = document.getElementById('formIsBatch');
        const nameContainer = document.getElementById('nameContainer');
        const formName = document.getElementById('formName');

        if(formIsBatch) {
            formIsBatch.addEventListener('change', function() {
                if (this.checked) {
                    nameContainer.classList.add('hidden'); // Sembunyikan input nama klien
                    formName.required = false; 
                } else {
                    nameContainer.classList.remove('hidden');
                    formName.required = true;
                }
            });
        }

        const createForm = document.getElementById('createForm');
        if(createForm) {
            createForm.onsubmit = async function(e) {
                e.preventDefault();
                const btn = document.getElementById('btnSubmitCreate');
                const isBatchMode = document.getElementById('formIsBatch').checked;
                
                btn.innerHTML = 'Memproses...'; btn.disabled = true;
                
                const fd = new FormData();
                fd.append('action', isBatchMode ? 'create_album_batch' : 'create_album'); 
                
                if(!isBatchMode) {
                    fd.append('name', document.getElementById('formName').value); 
                }
                
                fd.append('paket', document.getElementById('formPaket').value); 
                fd.append('drive_link', document.getElementById('formDriveLink').value); 
                fd.append('group_name', document.getElementById('formGroupName').value); // Foldering
                fd.append('hours', document.getElementById('formHours').value);
                
                try {
                    const res = await fetch('index.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    
                    if(data.success) {
                        if (isBatchMode) {
                            alert(`Sukses! ${data.count} Link berhasil dibuat secara otomatis.`);
                        }
                        window.location.reload(); 
                    } else { 
                        alert(data.message); 
                        btn.innerHTML = 'Coba Lagi'; 
                        btn.disabled = false; 
                    }
                } catch(err) {
                    alert('Terjadi kesalahan jaringan.');
                    btn.innerHTML = 'Coba Lagi'; 
                    btn.disabled = false; 
                }
            };
        }

        async function deleteAlbum(id) {
            if(!confirm('Hapus riwayat proyek ini?')) return;
            const fd = new FormData(); fd.append('action', 'delete_album'); fd.append('id', id);
            await fetch('index.php', { method:'POST', body:fd }); window.location.reload();
        }

        // FUNGSI HAPUS FOLDER SEKALIGUS
        async function deleteGroup(groupName, e) {
            e.stopPropagation(); // Mencegah accordion terbuka saat hapus
            if(!confirm(`PERHATIAN: Apakah Anda yakin ingin menghapus folder "${groupName}" beserta SELURUH link di dalamnya dari riwayat ini?`)) return;
            const fd = new FormData(); 
            fd.append('action', 'delete_group'); 
            fd.append('group_name', groupName);
            await fetch('index.php', { method:'POST', body:fd }); 
            window.location.reload();
        }

        function copyLink(id) {
            navigator.clipboard.writeText(window.location.origin + window.location.pathname + '?album=' + id);
            alert('Link Tersalin! Berikan ke pelanggan.');
        }

        document.querySelectorAll('.dash-countdown').forEach(el => {
            const upd = () => {
                const diff = parseInt(el.dataset.expire) * 1000 - new Date().getTime();
                if(diff < 0) { el.innerText = 'Expired'; return; }
                el.innerText = `${Math.floor(diff / 86400000)} Hr ${Math.floor((diff % 86400000) / 3600000)} Jm`;
            }; upd(); setInterval(upd, 60000); 
        });

        async function openEdit(id) {
            document.getElementById('edit-panel').classList.remove('hidden');
            document.getElementById('edit_id').value = id;
            const fd = new FormData(); fd.append('action', 'get_album'); fd.append('id', id);
            const data = await (await fetch('index.php', { method: 'POST', body: fd })).json();
            if(data.success) { 
                document.getElementById('edit_name').value = data.album.name; 
                document.getElementById('edit_paket').value = data.album.paket; 
                document.getElementById('edit_drive_link').value = data.album.drive_link; 
                document.getElementById('edit_group_name').value = data.album.group_name || ''; 
            }
        }

        function closeEdit() { document.getElementById('edit-panel').classList.add('hidden'); }

        document.getElementById('editForm')?.addEventListener('submit', async(e) => {
            e.preventDefault();
            const fd = new FormData(); 
            fd.append('action', 'update_album'); 
            fd.append('id', document.getElementById('edit_id').value); 
            fd.append('name', document.getElementById('edit_name').value); 
            fd.append('paket', document.getElementById('edit_paket').value); 
            fd.append('drive_link', document.getElementById('edit_drive_link').value);
            fd.append('group_name', document.getElementById('edit_group_name').value);
            const res = await (await fetch('index.php', { method: 'POST', body: fd })).json();
            if(res.success) window.location.reload(); else alert(res.message);
        });
        <?php endif; ?>

        // SCRIPT CUSTOMER (GOOGLE DRIVE API + DOWNLOAD MANAGER + GIFSHOT)
        <?php if ($mode === 'customer_view'): ?>
        const albumData = <?= json_encode(['paket' => $paket, 'name' => $current_album['name']]) ?>;
        // Gunakan API KEY langsung dari variabel PHP backend kita
        const API_KEY = "<?= $gdrive_api_key ?>";
        const FOLDER_ID = "<?= htmlspecialchars($current_album['folder_id']) ?>";
        let drivePhotos = [];

        // Timer
        const timerEl = document.getElementById('timer');
        if(timerEl) {
            const upd = () => {
                const diff = parseInt(timerEl.dataset.expire) * 1000 - new Date().getTime();
                if(diff < 0) { window.location.reload(); return; }
                const d = Math.floor(diff / 86400000), h = Math.floor((diff % 86400000) / 3600000), m = Math.floor((diff % 3600000) / 60000);
                timerEl.innerText = `${d>0?d+'H ':''}${String(h).padStart(2,'0')}j ${String(m).padStart(2,'0')}m`;
            }; upd(); setInterval(upd, 60000);
        }

        // Ambil Data dari Google Drive via API
        async function fetchDriveGallery() {
            try {
                // Query ambil file gambar dari Folder ID
                const query = `https://www.googleapis.com/drive/v3/files?q='${FOLDER_ID}'+in+parents+and+mimeType+contains+'image/'&key=${API_KEY}&fields=files(id,name,thumbnailLink)&pageSize=100`;
                const response = await fetch(query);
                const data = await response.json();

                if (data.error) throw new Error(data.error.message);
                
                if (data.files && data.files.length > 0) {
                    drivePhotos = data.files.map(f => {
                        const rawThumb = f.thumbnailLink || '';
                        return {
                            id: f.id,
                            name: f.name,
                            displayUrl: rawThumb.replace(/=s\d+/, '=s800'),
                            // PENTING: Menggunakan endpoint file API untuk kualitas Original 100% tanpa kompresi
                            downloadUrl: `https://www.googleapis.com/drive/v3/files/${f.id}?alt=media&key=${API_KEY}`
                        };
                    });
                    renderGallery();
                } else {
                    throw new Error("Folder kosong atau tidak disetting 'Siapa saja yang memiliki link'.");
                }
            } catch (error) {
                document.getElementById('loadingGallery').innerHTML = `<i data-lucide="alert-circle" class="text-red-500 mb-2" size="40"></i><p class="text-sm text-red-500 text-center px-4">${error.message}</p>`;
                lucide.createIcons();
            }
        }

        // Render HTML Galeri
        function renderGallery() {
            const grid = document.getElementById('galleryGrid');
            grid.innerHTML = '';
            
            drivePhotos.forEach((photo, i) => {
                const ext = photo.name.split('.').pop() || 'jpg';
                const dlName = `SnapFun_${albumData.paket}_${albumData.name}_${i+1}.${ext}`;
                
                grid.innerHTML += `
                <div class="photo-item relative aspect-[4/5] bg-white rounded-xl overflow-hidden group shadow-sm border border-gray-100">
                    <img src="${photo.displayUrl}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" loading="lazy" crossorigin="anonymous">
                    
                    <div class="normal-overlay absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-3">
                        <button onclick="downloadSingle('${photo.downloadUrl}', '${dlName}')" class="w-full bg-white text-[#355faa] py-2 rounded-lg text-[10px] font-bold uppercase tracking-widest hover:bg-gray-50 shadow-lg btn-touch">Unduh</button>
                    </div>

                    <label class="selection-overlay absolute inset-0 bg-white/0 hidden cursor-pointer">
                        <input type="checkbox" class="gif-checkbox hidden" value="${photo.downloadUrl}">
                        <div class="absolute inset-0 border-4 border-transparent transition-all flex items-start justify-end p-2">
                            <div class="check-indicator w-6 h-6 bg-[#355faa] rounded-full text-white flex items-center justify-center opacity-0 transform scale-50 transition-all"><i data-lucide="check" size="14"></i></div>
                        </div>
                    </label>
                </div>`;
            });
            
            document.getElementById('loadingGallery').classList.add('hidden');
            grid.classList.remove('hidden');
            document.getElementById('normalActions').classList.remove('hidden');
            lucide.createIcons();
            
            document.querySelectorAll('.gif-checkbox').forEach(cb => { cb.addEventListener('change', () => { document.getElementById('selectedCount').innerText = document.querySelectorAll('.gif-checkbox:checked').length; }); });
        }

        fetchDriveGallery();

        // Download All (Dual Logic: ZIP khusus iOS, Langsung Individu untuk Android/Desktop)
        async function downloadAll() {
            // Deteksi Platform Pelanggan (iOS atau Bukan)
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            
            const modal = document.getElementById('downloadModal');
            const dlText = document.getElementById('dlProgressText');
            modal.classList.remove('hidden');
            
            try {
                if (isIOS) {
                    // LOGIKA IOS: HARUS DI ZIP (Karena Safari memblokir banyak download individual)
                    dlText.innerText = "Menyiapkan file ZIP khusus iPhone/iPad...";
                    const zip = new JSZip();
                    for (let i = 0; i < drivePhotos.length; i++) {
                        dlText.innerText = `Menarik foto ${i+1} dari ${drivePhotos.length}...`;
                        const photo = drivePhotos[i];
                        const res = await fetch(photo.downloadUrl);
                        const blob = await res.blob();
                        const ext = photo.name.split('.').pop() || 'jpg';
                        zip.file(`SnapFun_${albumData.paket}_${albumData.name}_${i+1}.${ext}`, blob);
                    }
                    dlText.innerText = `Membuat file ZIP (Bisa memakan waktu)...`;
                    const zipContent = await zip.generateAsync({ type: "blob" });
                    saveAs(zipContent, `SnapFun_${albumData.name}.zip`);
                    
                } else {
                    // LOGIKA ANDROID/DESKTOP: UNDUH LANGSUNG INDIVIDU (Tanpa ZIP)
                    for (let i = 0; i < drivePhotos.length; i++) {
                        dlText.innerText = `Mengunduh foto ${i+1} dari ${drivePhotos.length}...`;
                        const photo = drivePhotos[i];
                        
                        const res = await fetch(photo.downloadUrl);
                        const blob = await res.blob();
                        
                        const ext = photo.name.split('.').pop() || 'jpg';
                        const filename = `SnapFun_${albumData.paket}_${albumData.name}_${i+1}.${ext}`;
                        
                        saveAs(blob, filename); 
                        
                        // Jeda penting agar browser Android tidak memblokir antrean
                        await new Promise(r => setTimeout(r, 600));
                    }
                }
                
                dlText.innerText = `Selesai!`;
                setTimeout(() => modal.classList.add('hidden'), 1500);
            } catch(e) {
                console.error(e);
                dlText.innerText = "Gagal mengunduh foto. Cek koneksi Anda.";
                setTimeout(()=> modal.classList.add('hidden'), 3000);
            }
        }

        // Single File Download via Blob (Menghindari buka tab baru)
        async function downloadSingle(url, filename) {
            try {
                const res = await fetch(url);
                const blob = await res.blob();
                saveAs(blob, filename); // Uses FileSaver.js
            } catch(e) { alert("Gagal mengunduh foto."); }
        }

        // GIF LOGIC
        let gifMode = false;
        function toggleGifMode() {
            gifMode = !gifMode;
            document.getElementById('normalActions').classList.toggle('hidden');
            document.getElementById('gifActions').classList.toggle('hidden');
            document.getElementById('gifSelectionHeader').classList.toggle('hidden');
            document.querySelectorAll('.selection-overlay').forEach(el => el.classList.toggle('hidden'));
            document.querySelectorAll('.normal-overlay').forEach(el => el.classList.toggle('hidden'));
            if(!gifMode) document.querySelectorAll('.gif-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectedCount').innerText = '0';
        }

        function generateGIF() {
            const selected = Array.from(document.querySelectorAll('.gif-checkbox:checked')).map(el => el.value);
            if(selected.length < 2) return alert("Pilih minimal 2 foto!");

            const modal = document.getElementById('gifModal');
            const img = document.getElementById('gifResultImage');
            const loading = document.getElementById('gifLoading');
            const btn = document.getElementById('gifDownloadLink');
            
            modal.classList.remove('hidden'); img.src=''; btn.classList.add('hidden'); loading.classList.remove('hidden');

            const tempImg = new Image();
            tempImg.crossOrigin = "Anonymous"; // WAJIB untuk canvas
            tempImg.src = selected[0];
            tempImg.onload = function() {
                const ratio = tempImg.naturalWidth / tempImg.naturalHeight;
                
                gifshot.createGIF({
                    images: selected,
                    gifWidth: 600,
                    gifHeight: Math.round(600 / ratio),
                    interval: 0.5,
                    crossOrigin: 'Anonymous' // Penting untuk G-Drive images
                }, function(obj) {
                    if(!obj.error) {
                        img.src = obj.image;
                        loading.classList.add('hidden');
                        btn.href = obj.image;
                        btn.classList.remove('hidden');
                    } else {
                        alert("Gagal membuat GIF."); closeGifModal();
                    }
                });
            }
        }
        function closeGifModal() { document.getElementById('gifModal').classList.add('hidden'); }
        <?php endif; ?>
    </script>
</body>
</html>