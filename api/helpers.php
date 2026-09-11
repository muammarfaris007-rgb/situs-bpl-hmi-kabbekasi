<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 7, // 7 hari
        'path'     => '/',
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Kirim respons JSON lalu hentikan eksekusi. */
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Ambil isi body request sebagai array (untuk request JSON). */
function body_json() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Wajibkan pengurus sudah login (session aktif), atau hentikan dengan 401. */
function require_login() {
    if (empty($_SESSION['admin_id'])) {
        json_response(['error' => 'Sesi Anda berakhir atau belum login. Silakan login kembali sebagai pengurus.'], 401);
    }
}

function is_logged_in() {
    return !empty($_SESSION['admin_id']);
}

/** Ambil nomor urut berikutnya untuk tabel yang punya kolom sort_order. */
function next_sort_order(PDO $pdo, string $table): int {
    $stmt = $pdo->query("SELECT COALESCE(MAX(sort_order), -1) + 1 AS n FROM `$table`");
    return (int) $stmt->fetch()['n'];
}

/** Ambil satu baris berdasarkan id, atau 404 jika tidak ada. */
function fetch_or_404(PDO $pdo, string $table, int $id): array {
    $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        json_response(['error' => 'Data tidak ditemukan.'], 404);
    }
    return $row;
}

/**
 * Buat Nomor Induk Kader otomatis:
 * 2 digit akhir Tahun LK I + 2 digit kode komisariat + 3 digit acak unik.
 */
function generate_nik(PDO $pdo, string $tahunLk1, string $komisariat): string {
    $komisariatList = ['PELITA BANGSA', 'HUMTEK UPB', 'STEBI GLOBAL MULIA', 'AT-TAQWA'];
    $idx = array_search($komisariat, $komisariatList, true);
    $kode = $idx === false ? '00' : str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT);
    $yearSuffix = str_pad(substr($tahunLk1, -2), 2, '0', STR_PAD_LEFT);

    $check = $pdo->prepare("SELECT COUNT(*) c FROM kader_db WHERE nik = ?");
    for ($tries = 0; $tries < 50; $tries++) {
        $rand = str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
        $nik = $yearSuffix . $kode . $rand;
        $check->execute([$nik]);
        if ((int) $check->fetch()['c'] === 0) {
            return $nik;
        }
    }
    // Fallback ekstrem jika 50x percobaan tetap bentrok (nyaris mustahil).
    return $yearSuffix . $kode . substr((string) round(microtime(true) * 1000), -3);
}

/** Validasi field wajib ada & tidak kosong pada array input. */
function require_fields(array $data, array $fields) {
    $missing = [];
    foreach ($fields as $f) {
        if (!isset($data[$f]) || trim((string) $data[$f]) === '') {
            $missing[] = $f;
        }
    }
    if ($missing) {
        json_response(['error' => 'Field wajib diisi: ' . implode(', ', $missing)], 400);
    }
}
