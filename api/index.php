<?php
/**
 * ============================================================
 * API ROUTER — BPL x Bidang PA HMI Kabupaten Bekasi
 * ============================================================
 * Semua permintaan API masuk lewat file ini:
 *   /api/index.php?resource=<nama>          GET/POST
 *   /api/index.php?resource=<nama>&id=5     GET/PUT/DELETE (satu data)
 *
 * Tidak butuh konfigurasi .htaccess / mod_rewrite apa pun,
 * jadi aman dipakai di hosting cPanel jenis apapun.
 * ============================================================
 */

require_once __DIR__ . '/helpers.php';

header('X-Content-Type-Options: nosniff');

$pdo      = get_pdo();
$method   = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? '';
$id       = isset($_GET['id']) ? (int) $_GET['id'] : null;

// Tabel-tabel sederhana yang berpola sama: {id, name/title, ..., photo?, sort_order}
// Dipakai untuk menghindari pengulangan kode pada pengurus / pengurus_pa / instruktur / kegiatan / berita.

switch ($resource) {

    // ============================================================
    // AUTH
    // ============================================================
    case 'auth':
        if ($method === 'POST') {
            $data = body_json();
            require_fields($data, ['username', 'password']);
            $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ?');
            $stmt->execute([trim($data['username'])]);
            $admin = $stmt->fetch();
            if (!$admin || !password_verify($data['password'], $admin['password_hash'])) {
                json_response(['error' => 'Username atau password salah.'], 401);
            }
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['display_name'];
            json_response(['ok' => true, 'name' => $admin['display_name']]);
        }
        if ($method === 'DELETE') {
            $_SESSION = [];
            session_destroy();
            json_response(['ok' => true]);
        }
        if ($method === 'GET') {
            json_response(['loggedIn' => is_logged_in(), 'name' => $_SESSION['admin_name'] ?? null]);
        }
        json_response(['error' => 'Method not allowed'], 405);
        break;

    // ============================================================
    // UPLOAD FOTO (logo, pengurus, instruktur, kegiatan, berita, hero)
    // ============================================================
    case 'upload':
        require_login();
        if ($method !== 'POST') json_response(['error' => 'Method not allowed'], 405);
        if (empty($_FILES['file'])) json_response(['error' => 'Tidak ada berkas yang diunggah.'], 400);

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            json_response(['error' => 'Gagal mengunggah berkas.'], 400);
        }
        if ($file['size'] > 4 * 1024 * 1024) {
            json_response(['error' => 'Ukuran berkas maksimal 4MB.'], 400);
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : $file['type'];
        if (!isset($allowed[$mime])) {
            json_response(['error' => 'Format berkas harus JPG, PNG, atau WEBP.'], 400);
        }
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }
        $filename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
            json_response(['error' => 'Gagal menyimpan berkas di server.'], 500);
        }
        json_response(['path' => UPLOAD_URL . $filename]);
        break;

    // ============================================================
    // KONTEN SINGLETON: hero, kontak, logo, label & angka statistik manual
    // ============================================================
    case 'content':
        if ($method === 'GET') {
            $rows = $pdo->query('SELECT k, v FROM site_content')->fetchAll();
            $out = [];
            foreach ($rows as $r) { $out[$r['k']] = $r['v']; }
            json_response($out);
        }
        if ($method === 'PUT') {
            require_login();
            $data = body_json();
            $stmt = $pdo->prepare('UPDATE site_content SET v = ? WHERE k = ?');
            foreach ($data as $k => $v) {
                $stmt->execute([$v === null ? null : (string) $v, $k]);
            }
            json_response(['ok' => true]);
        }
        json_response(['error' => 'Method not allowed'], 405);
        break;

    // ============================================================
    // STATISTIK RINGKAS BERANDA (dihitung otomatis, kecuali komisariat)
    // ============================================================
    case 'stats':
        $get = function ($k) use ($pdo) {
            $stmt = $pdo->prepare('SELECT v FROM site_content WHERE k = ?');
            $stmt->execute([$k]);
            $row = $stmt->fetch();
            return $row ? $row['v'] : '';
        };
        $programSelesai = (int) $pdo->query("SELECT COUNT(*) c FROM jadwal WHERE status = 'terlaksana'")->fetch()['c'];
        $instrukturAktif = (int) $pdo->query('SELECT COUNT(*) c FROM instruktur')->fetch()['c'];
        $kaderTerdata = (int) $pdo->query('SELECT COUNT(*) c FROM kader_db')->fetch()['c'];
        json_response([
            ['key' => 'komisariat',      'num' => $get('stat_komisariat_num'), 'label' => $get('stat_komisariat_label')],
            ['key' => 'programSelesai',  'num' => (string) $programSelesai,    'label' => $get('stat_program_label')],
            ['key' => 'instrukturAktif', 'num' => (string) $instrukturAktif,   'label' => $get('stat_instruktur_label')],
            ['key' => 'kaderTerdata',    'num' => (string) $kaderTerdata,      'label' => $get('stat_kader_label')],
        ]);
        break;

    // ============================================================
    // JUMLAH KADER PER JENJANG TRAINING (dihitung dari Database Kader)
    // ============================================================
    case 'jenjang-stats':
        $row = $pdo->query("
            SELECT
              SUM(CASE WHEN lk2_tahun IS NOT NULL AND lk2_tahun <> '' THEN 1 ELSE 0 END) AS lk2,
              SUM(CASE WHEN lk3_tahun IS NOT NULL AND lk3_tahun <> '' THEN 1 ELSE 0 END) AS lk3,
              SUM(CASE WHEN sc_tahun  IS NOT NULL AND sc_tahun  <> '' THEN 1 ELSE 0 END) AS sc,
              SUM(CASE WHEN lkk_tahun IS NOT NULL AND lkk_tahun <> '' THEN 1 ELSE 0 END) AS lkk
            FROM kader_db
        ")->fetch();
        json_response([
            ['key' => 'lk2', 'label' => 'LK II',  'count' => (int) ($row['lk2'] ?? 0)],
            ['key' => 'lk3', 'label' => 'LK III', 'count' => (int) ($row['lk3'] ?? 0)],
            ['key' => 'sc',  'label' => 'SC',     'count' => (int) ($row['sc']  ?? 0)],
            ['key' => 'lkk', 'label' => 'LKK',    'count' => (int) ($row['lkk'] ?? 0)],
        ]);
        break;

    // ============================================================
    // PENGURUS BPL / PENGURUS BIDANG PA / INSTRUKTUR
    // (pola sama: name, role/materi, photo, sort_order)
    // ============================================================
    case 'pengurus':
    case 'pengurus_pa':
        handle_person_table($pdo, $resource, $method, $id);
        break;

    case 'instruktur':
        handle_instruktur($pdo, $method, $id);
        break;

    // ============================================================
    // JADWAL (Jadwal Kegiatan / Program Kerja BPL & PA — satu tabel)
    // ============================================================
    case 'jadwal':
        handle_jadwal($pdo, $method, $id);
        break;

    // ============================================================
    // KEGIATAN & BERITA (pola sama: title, date_text, excerpt, image)
    // ============================================================
    case 'kegiatan':
    case 'berita':
        handle_post_table($pdo, $resource, $method, $id);
        break;

    // ============================================================
    // DATABASE KADER
    // ============================================================
    case 'kader':
        handle_kader($pdo, $method, $id);
        break;

    // ============================================================
    // MONITORING KADER
    // ============================================================
    case 'monitoring':
        handle_monitoring($pdo, $method, $id);
        break;

    // ============================================================
    // ADMINISTRASI (surat-menyurat Komisariat -> BPL / PA)
    // ============================================================
    case 'administrasi':
        handle_administrasi($pdo, $method, $id);
        break;

    default:
        json_response(['error' => 'Resource tidak dikenal.'], 404);
}

// ================================================================
// FUNGSI-FUNGSI HANDLER
// ================================================================

/** Pengurus BPL & Pengurus Bidang PA: {name, role, photo, sort_order}. */
function handle_person_table(PDO $pdo, string $table, string $method, ?int $id) {
    if ($method === 'GET') {
        $rows = $pdo->query("SELECT * FROM `$table` ORDER BY sort_order ASC, id ASC")->fetchAll();
        json_response($rows);
    }
    if ($method === 'POST') {
        require_login();
        $data = body_json();
        require_fields($data, ['name', 'role']);
        $stmt = $pdo->prepare("INSERT INTO `$table` (name, role, photo, sort_order) VALUES (?,?,?,?)");
        $stmt->execute([$data['name'], $data['role'], $data['photo'] ?? null, next_sort_order($pdo, $table)]);
        json_response(['id' => (int) $pdo->lastInsertId()], 201);
    }
    if ($method === 'PUT' && $id) {
        require_login();
        fetch_or_404($pdo, $table, $id);
        $data = body_json();
        if (isset($data['reorder'])) {
            reorder_table($pdo, $table, $data['reorder']);
            json_response(['ok' => true]);
        }
        require_fields($data, ['name', 'role']);
        $stmt = $pdo->prepare("UPDATE `$table` SET name=?, role=?, photo=? WHERE id=?");
        $stmt->execute([$data['name'], $data['role'], $data['photo'] ?? null, $id]);
        json_response(['ok' => true]);
    }
    if ($method === 'DELETE' && $id) {
        require_login();
        fetch_or_404($pdo, $table, $id);
        $pdo->prepare("DELETE FROM `$table` WHERE id=?")->execute([$id]);
        json_response(['ok' => true]);
    }
    json_response(['error' => 'Permintaan tidak valid.'], 400);
}

/** Instruktur: sama seperti person table, tapi "materi" adalah array JSON. */
function handle_instruktur(PDO $pdo, string $method, ?int $id) {
    if ($method === 'GET') {
        $rows = $pdo->query('SELECT * FROM instruktur ORDER BY sort_order ASC, id ASC')->fetchAll();
        foreach ($rows as &$r) { $r['materi'] = json_decode($r['materi'], true) ?: []; }
        json_response($rows);
    }
    if ($method === 'POST') {
        require_login();
        $data = body_json();
        require_fields($data, ['name']);
        if (empty($data['materi']) || !is_array($data['materi'])) {
            json_response(['error' => 'Pilih minimal satu spesialisasi materi.'], 400);
        }
        $stmt = $pdo->prepare('INSERT INTO instruktur (name, materi, photo, sort_order) VALUES (?,?,?,?)');
        $stmt->execute([$data['name'], json_encode($data['materi'], JSON_UNESCAPED_UNICODE), $data['photo'] ?? null, next_sort_order($pdo, 'instruktur')]);
        json_response(['id' => (int) $pdo->lastInsertId()], 201);
    }
    if ($method === 'PUT' && $id) {
        require_login();
        fetch_or_404($pdo, 'instruktur', $id);
        $data = body_json();
        if (isset($data['reorder'])) {
            reorder_table($pdo, 'instruktur', $data['reorder']);
            json_response(['ok' => true]);
        }
        require_fields($data, ['name']);
        if (empty($data['materi']) || !is_array($data['materi'])) {
            json_response(['error' => 'Pilih minimal satu spesialisasi materi.'], 400);
        }
        $stmt = $pdo->prepare('UPDATE instruktur SET name=?, materi=?, photo=? WHERE id=?');
        $stmt->execute([$data['name'], json_encode($data['materi'], JSON_UNESCAPED_UNICODE), $data['photo'] ?? null, $id]);
        json_response(['ok' => true]);
    }
    if ($method === 'DELETE' && $id) {
        require_login();
        fetch_or_404($pdo, 'instruktur', $id);
        $pdo->prepare('DELETE FROM instruktur WHERE id=?')->execute([$id]);
        json_response(['ok' => true]);
    }
    json_response(['error' => 'Permintaan tidak valid.'], 400);
}

/** Kegiatan & Berita: {title, date_text, excerpt, image, sort_order}. */
function handle_post_table(PDO $pdo, string $table, string $method, ?int $id) {
    if ($method === 'GET') {
        $rows = $pdo->query("SELECT * FROM `$table` ORDER BY sort_order ASC, id ASC")->fetchAll();
        json_response($rows);
    }
    if ($method === 'POST') {
        require_login();
        $data = body_json();
        require_fields($data, ['title']);
        $stmt = $pdo->prepare("INSERT INTO `$table` (title, date_text, excerpt, image, sort_order) VALUES (?,?,?,?,?)");
        $stmt->execute([$data['title'], $data['date_text'] ?? '', $data['excerpt'] ?? '', $data['image'] ?? null, next_sort_order($pdo, $table)]);
        json_response(['id' => (int) $pdo->lastInsertId()], 201);
    }
    if ($method === 'PUT' && $id) {
        require_login();
        fetch_or_404($pdo, $table, $id);
        $data = body_json();
        if (isset($data['reorder'])) {
            reorder_table($pdo, $table, $data['reorder']);
            json_response(['ok' => true]);
        }
        require_fields($data, ['title']);
        $stmt = $pdo->prepare("UPDATE `$table` SET title=?, date_text=?, excerpt=?, image=? WHERE id=?");
        $stmt->execute([$data['title'], $data['date_text'] ?? '', $data['excerpt'] ?? '', $data['image'] ?? null, $id]);
        json_response(['ok' => true]);
    }
    if ($method === 'DELETE' && $id) {
        require_login();
        fetch_or_404($pdo, $table, $id);
        $pdo->prepare("DELETE FROM `$table` WHERE id=?")->execute([$id]);
        json_response(['ok' => true]);
    }
    json_response(['error' => 'Permintaan tidak valid.'], 400);
}

/** Jadwal: dibaca terurut dari tanggal_urut terdekat (kosong = paling bawah). */
function handle_jadwal(PDO $pdo, string $method, ?int $id) {
    $statuses = ['rencana', 'jalan', 'terlaksana', 'tunggu'];
    if ($method === 'GET') {
        $rows = $pdo->query('
            SELECT * FROM jadwal
            ORDER BY (tanggal_urut IS NULL) ASC, tanggal_urut ASC, id ASC
        ')->fetchAll();
        json_response($rows);
    }
    if ($method === 'POST') {
        require_login();
        $data = body_json();
        require_fields($data, ['program']);
        $status = in_array($data['status'] ?? '', $statuses, true) ? $data['status'] : 'rencana';
        $stmt = $pdo->prepare('INSERT INTO jadwal (program, jenjang, waktu, tanggal_urut, penyelenggara, status) VALUES (?,?,?,?,?,?)');
        $stmt->execute([
            $data['program'], $data['jenjang'] ?? '', $data['waktu'] ?? '',
            !empty($data['tanggal_urut']) ? $data['tanggal_urut'] : null,
            $data['penyelenggara'] ?? '', $status,
        ]);
        json_response(['id' => (int) $pdo->lastInsertId()], 201);
    }
    if ($method === 'PUT' && $id) {
        require_login();
        fetch_or_404($pdo, 'jadwal', $id);
        $data = body_json();
        require_fields($data, ['program']);
        $status = in_array($data['status'] ?? '', $statuses, true) ? $data['status'] : 'rencana';
        $stmt = $pdo->prepare('UPDATE jadwal SET program=?, jenjang=?, waktu=?, tanggal_urut=?, penyelenggara=?, status=? WHERE id=?');
        $stmt->execute([
            $data['program'], $data['jenjang'] ?? '', $data['waktu'] ?? '',
            !empty($data['tanggal_urut']) ? $data['tanggal_urut'] : null,
            $data['penyelenggara'] ?? '', $status, $id,
        ]);
        json_response(['ok' => true]);
    }
    if ($method === 'DELETE' && $id) {
        require_login();
        fetch_or_404($pdo, 'jadwal', $id);
        $pdo->prepare('DELETE FROM jadwal WHERE id=?')->execute([$id]);
        json_response(['ok' => true]);
    }
    json_response(['error' => 'Permintaan tidak valid.'], 400);
}

/** Database Kader: NIK dibuat & dikunci di server. */
function handle_kader(PDO $pdo, string $method, ?int $id) {
    if ($method === 'GET') {
        require_login(); // data pribadi -> khusus pengurus
        if (!empty($_GET['template'])) {
            json_response([
                'headers' => ['NAMA LENGKAP','ALAMAT','KOMISARIAT','JURUSAN','KAMPUS','TAHUN LK I','TEMPAT LK I','TAHUN LK II','TEMPAT LK II','TAHUN LK III','TEMPAT LK III','TAHUN SC','TEMPAT SC','TAHUN LKK','TEMPAT LKK','TAHUN LPP','NAMA LEMBAGA LPP','TEMPAT LPP'],
            ]);
        }
        $rows = $pdo->query('SELECT * FROM kader_db ORDER BY id ASC')->fetchAll();
        json_response($rows);
    }
    if ($method === 'POST') {
        require_login();
        $data = body_json();

        // Impor massal (dari Excel yang sudah diparse SheetJS di sisi frontend)
        if (!empty($data['bulk']) && is_array($data['bulk'])) {
            $added = 0; $skipped = 0;
            $komisariatList = ['PELITA BANGSA', 'HUMTEK UPB', 'STEBI GLOBAL MULIA', 'AT-TAQWA'];
            $stmt = $pdo->prepare('INSERT INTO kader_db
                (nik, nama, alamat, komisariat, jurusan, kampus, lk1_tahun, lk1_tempat, lk2_tahun, lk2_tempat, lk3_tahun, lk3_tempat, sc_tahun, sc_tempat, lkk_tahun, lkk_tempat, lpp_tahun, lpp_lembaga, lpp_tempat)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($data['bulk'] as $row) {
                $komisariat = strtoupper(trim($row['komisariat'] ?? ''));
                $matched = null;
                foreach ($komisariatList as $k) { if (strtoupper($k) === $komisariat) { $matched = $k; break; } }
                $nama = trim($row['nama'] ?? '');
                $lk1Tahun = preg_replace('/\D/', '', substr((string)($row['lk1_tahun'] ?? ''), 0, 4));
                if (!$nama || !$matched || strlen($lk1Tahun) !== 4) { $skipped++; continue; }
                $nik = generate_nik($pdo, $lk1Tahun, $matched);
                $stmt->execute([
                    $nik, $nama, $row['alamat'] ?? '', $matched, $row['jurusan'] ?? '', $row['kampus'] ?? '',
                    $lk1Tahun, $row['lk1_tempat'] ?? '',
                    $row['lk2_tahun'] ?? '', $row['lk2_tempat'] ?? '',
                    $row['lk3_tahun'] ?? '', $row['lk3_tempat'] ?? '',
                    $row['sc_tahun'] ?? '', $row['sc_tempat'] ?? '',
                    $row['lkk_tahun'] ?? '', $row['lkk_tempat'] ?? '',
                    $row['lpp_tahun'] ?? '', $row['lpp_lembaga'] ?? '', $row['lpp_tempat'] ?? '',
                ]);
                $added++;
            }
            json_response(['added' => $added, 'skipped' => $skipped]);
        }

        require_fields($data, ['nama', 'komisariat', 'lk1_tahun']);
        if (!preg_match('/^\d{4}$/', $data['lk1_tahun'])) {
            json_response(['error' => 'Tahun LK I harus 4 digit angka.'], 400);
        }
        $nik = generate_nik($pdo, $data['lk1_tahun'], $data['komisariat']);
        $stmt = $pdo->prepare('INSERT INTO kader_db
            (nik, nama, alamat, komisariat, jurusan, kampus, lk1_tahun, lk1_tempat, lk2_tahun, lk2_tempat, lk3_tahun, lk3_tempat, sc_tahun, sc_tempat, lkk_tahun, lkk_tempat, lpp_tahun, lpp_lembaga, lpp_tempat)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $nik, $data['nama'], $data['alamat'] ?? '', $data['komisariat'], $data['jurusan'] ?? '', $data['kampus'] ?? '',
            $data['lk1_tahun'], $data['lk1_tempat'] ?? '',
            $data['lk2_tahun'] ?? '', $data['lk2_tempat'] ?? '',
            $data['lk3_tahun'] ?? '', $data['lk3_tempat'] ?? '',
            $data['sc_tahun'] ?? '', $data['sc_tempat'] ?? '',
            $data['lkk_tahun'] ?? '', $data['lkk_tempat'] ?? '',
            $data['lpp_tahun'] ?? '', $data['lpp_lembaga'] ?? '', $data['lpp_tempat'] ?? '',
        ]);
        json_response(['id' => (int) $pdo->lastInsertId(), 'nik' => $nik], 201);
    }
    if ($method === 'PUT' && $id) {
        require_login();
        $existing = fetch_or_404($pdo, 'kader_db', $id);
        $data = body_json();
        require_fields($data, ['nama', 'komisariat']);
        // NIK tidak pernah diubah lewat form edit — tetap dikunci sebagai identitas permanen.
        $stmt = $pdo->prepare('UPDATE kader_db SET
            nama=?, alamat=?, komisariat=?, jurusan=?, kampus=?,
            lk1_tahun=?, lk1_tempat=?, lk2_tahun=?, lk2_tempat=?, lk3_tahun=?, lk3_tempat=?,
            sc_tahun=?, sc_tempat=?, lkk_tahun=?, lkk_tempat=?, lpp_tahun=?, lpp_lembaga=?, lpp_tempat=?
            WHERE id=?');
        $stmt->execute([
            $data['nama'], $data['alamat'] ?? '', $data['komisariat'], $data['jurusan'] ?? '', $data['kampus'] ?? '',
            $data['lk1_tahun'] ?? '', $data['lk1_tempat'] ?? '',
            $data['lk2_tahun'] ?? '', $data['lk2_tempat'] ?? '',
            $data['lk3_tahun'] ?? '', $data['lk3_tempat'] ?? '',
            $data['sc_tahun'] ?? '', $data['sc_tempat'] ?? '',
            $data['lkk_tahun'] ?? '', $data['lkk_tempat'] ?? '',
            $data['lpp_tahun'] ?? '', $data['lpp_lembaga'] ?? '', $data['lpp_tempat'] ?? '',
            $id,
        ]);
        json_response(['ok' => true]);
    }
    if ($method === 'DELETE' && $id) {
        require_login();
        $row = fetch_or_404($pdo, 'kader_db', $id);
        $stmt = $pdo->prepare('SELECT COUNT(*) c FROM kader_monitoring WHERE nik = ?');
        $stmt->execute([$row['nik']]);
        $linked = (int) $stmt->fetch()['c'];
        $pdo->prepare('DELETE FROM kader_db WHERE id=?')->execute([$id]); // FK CASCADE ikut hapus monitoring
        json_response(['ok' => true, 'monitoring_removed' => $linked]);
    }
    json_response(['error' => 'Permintaan tidak valid.'], 400);
}

/** Monitoring Kader: terhubung ke kader_db lewat NIK, satu kader satu baris monitoring. */
function handle_monitoring(PDO $pdo, string $method, ?int $id) {
    $fields = ['btq','spisph','ndp','mission','konstitusi','kmo','tps','ideopol','sgi','wanus','ndp2','kmo2'];
    if ($method === 'GET') {
        require_login();
        $rows = $pdo->query('
            SELECT m.*, k.nama, k.komisariat
            FROM kader_monitoring m
            LEFT JOIN kader_db k ON k.nik = m.nik
            ORDER BY m.id ASC
        ')->fetchAll();
        json_response($rows);
    }
    if ($method === 'POST') {
        require_login();
        $data = body_json();
        require_fields($data, ['nik']);
        $kader = $pdo->prepare('SELECT id FROM kader_db WHERE nik = ?');
        $kader->execute([$data['nik']]);
        if (!$kader->fetch()) {
            json_response(['error' => 'NIK tidak ditemukan di Database Kader.'], 400);
        }
        $dup = $pdo->prepare('SELECT id FROM kader_monitoring WHERE nik = ?');
        $dup->execute([$data['nik']]);
        if ($dup->fetch()) {
            json_response(['error' => 'Data monitoring untuk kader ini sudah ada. Gunakan ubah, bukan tambah baru.'], 409);
        }
        $cols = array_map(fn($f) => !empty($data[$f]) ? 1 : 0, $fields);
        $stmt = $pdo->prepare('INSERT INTO kader_monitoring (nik,' . implode(',', $fields) . ') VALUES (?,' . implode(',', array_fill(0, count($fields), '?')) . ')');
        $stmt->execute(array_merge([$data['nik']], $cols));
        json_response(['id' => (int) $pdo->lastInsertId()], 201);
    }
    if ($method === 'PUT' && $id) {
        require_login();
        fetch_or_404($pdo, 'kader_monitoring', $id);
        $data = body_json();
        $cols = array_map(fn($f) => !empty($data[$f]) ? 1 : 0, $fields);
        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $stmt = $pdo->prepare("UPDATE kader_monitoring SET $set WHERE id=?");
        $stmt->execute(array_merge($cols, [$id]));
        json_response(['ok' => true]);
    }
    if ($method === 'DELETE' && $id) {
        require_login();
        fetch_or_404($pdo, 'kader_monitoring', $id);
        $pdo->prepare('DELETE FROM kader_monitoring WHERE id=?')->execute([$id]);
        json_response(['ok' => true]);
    }
    json_response(['error' => 'Permintaan tidak valid.'], 400);
}

/** Administrasi: siapa saja boleh mengajukan surat; hanya pengurus yang boleh melihat/mengubah status. */
function handle_administrasi(PDO $pdo, string $method, ?int $id) {
    if ($method === 'GET') {
        require_login(); // daftar surat masuk khusus pengurus
        $rows = $pdo->query('SELECT * FROM administrasi ORDER BY created_at DESC')->fetchAll();
        json_response($rows);
    }
    if ($method === 'POST') {
        // PUBLIK: siapa pun (komisariat) boleh mengajukan surat tanpa login.
        $data = body_json();
        require_fields($data, ['komisariat', 'pengaju', 'tujuan', 'perihal']);
        if (!in_array($data['tujuan'], ['BPL', 'PA'], true)) {
            json_response(['error' => 'Tujuan surat tidak valid.'], 400);
        }
        $stmt = $pdo->prepare('INSERT INTO administrasi (komisariat, pengaju, tujuan, perihal, isi, kontak_pengaju) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$data['komisariat'], $data['pengaju'], $data['tujuan'], $data['perihal'], $data['isi'] ?? '', $data['kontak_pengaju'] ?? '']);
        json_response(['ok' => true, 'id' => (int) $pdo->lastInsertId()], 201);
    }
    if ($method === 'PUT' && $id) {
        require_login();
        fetch_or_404($pdo, 'administrasi', $id);
        $data = body_json();
        $status = in_array($data['status'] ?? '', ['diajukan','diproses','selesai'], true) ? $data['status'] : 'diajukan';
        $pdo->prepare('UPDATE administrasi SET status=? WHERE id=?')->execute([$status, $id]);
        json_response(['ok' => true]);
    }
    if ($method === 'DELETE' && $id) {
        require_login();
        fetch_or_404($pdo, 'administrasi', $id);
        $pdo->prepare('DELETE FROM administrasi WHERE id=?')->execute([$id]);
        json_response(['ok' => true]);
    }
    json_response(['error' => 'Permintaan tidak valid.'], 400);
}

/** Terapkan urutan baru (dipakai fitur naik/turun & drag di frontend). */
function reorder_table(PDO $pdo, string $table, array $ids) {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE `$table` SET sort_order = ? WHERE id = ?");
    foreach (array_values($ids) as $i => $rid) {
        $stmt->execute([$i, (int) $rid]);
    }
    $pdo->commit();
}
