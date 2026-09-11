-- ============================================================
-- Skema Database BPL x Bidang PA HMI Kabupaten Bekasi
-- Import file ini lewat phpMyAdmin (cPanel) ke database MySQL
-- yang sudah Anda buat. Karakter set: utf8mb4.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- 1. ADMIN / LOGIN PENGURUS ----------
CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Akun default: username "admin", password "Admin123"
-- (hash dibuat dengan password_hash() PHP - bcrypt)
INSERT INTO admins (username, password_hash, display_name) VALUES
('admin', '$2y$10$ZNQbg/8cMLj4ttQe65zkv./SpUXiRSdc/dVASV/G1CkGR74wqh6lC', 'Admin BPL')
ON DUPLICATE KEY UPDATE username = username;

-- ---------- 2. KONTEN SINGLETON (hero, kontak, logo, statistik manual) ----------
CREATE TABLE IF NOT EXISTS site_content (
  k VARCHAR(64) PRIMARY KEY,
  v LONGTEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO site_content (k, v) VALUES
('logo', NULL),
('hero_status_pill', 'BADAN PENGELOLA LATIHAN x BIDANG PEMBINAAN ANGGOTA'),
('hero_headline', 'Merawat mutu perkaderan, satu jenjang latihan pada satu waktu.'),
('hero_subtext', 'Badan Pengelola Latihan (BPL) adalah Badan Khusus yang mengelola seluruh aktivitas pelatihan di lingkungan HMI sesuai dengan Pedoman Perkaderan.'),
('hero_image', NULL),
('stat_komisariat_num', '6'),
('stat_komisariat_label', 'Komisariat'),
('stat_program_label', 'Program Kerja Terlaksana'),
('stat_instruktur_label', 'Instruktur Aktif'),
('stat_kader_label', 'Kader Terdata'),
('kontak_alamat', 'Kp. Rawalintah No. 234, Desa Mekarmukti, Cikarang Utara, 17530'),
('kontak_nohp', '0823-1221-8036'),
('kontak_nohp_nama', 'Faris Muammar'),
('kontak_email', 'bplkab.bekasisatu@gmail.com'),
('kontak_instagram', 'bplkab.bekasi')
ON DUPLICATE KEY UPDATE k = k;

-- ---------- 3. PENGURUS BPL ----------
CREATE TABLE IF NOT EXISTS pengurus (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  role VARCHAR(200) NOT NULL,
  photo VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO pengurus (name, role, sort_order) VALUES
('Faris Muammar', 'Ketua Umum', 0),
('Nabila Syahara Afianti', 'Sekretaris Umum', 1),
('Sirajudin Rumadedey', 'Bendahara Umum', 2),
('Arman Wijaya', 'Ketua Bidang Pembinaan Instruktur dan Kurikulum (PIK)', 3),
('Sadewo Juniantono Hafsanto', 'Ketua Bidang Penelitian dan Pengembangan', 4),
('Fitria Wahyuningsih', 'Ketua Bidang Hubungan Antar Lembaga', 5),
('Kanaya Oktaviani', 'Ketua Bidang Digitalisasi dan Informasi', 6),
('Yogi Ananda', 'Wakil Ketua Bidang Pembinaan Instruktur dan Kurikulum (PIK)', 7),
('Husni Kurniawan', 'Wakil Ketua Bidang Penelitian dan Pengembangan', 8),
('Yazid Ilham Zaky', 'Wakil Ketua Bidang Hubungan Antar Lembaga', 9),
('Mutiara Pertiwi', 'Wakil Ketua Bidang Digitalisasi dan Informasi', 10);

-- ---------- 4. PENGURUS BIDANG PA ----------
CREATE TABLE IF NOT EXISTS pengurus_pa (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  role VARCHAR(200) NOT NULL,
  photo VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO pengurus_pa (name, role, sort_order) VALUES
('Faris Muammar', 'Ketua Bidang Pembinaan Anggota', 0),
('Rizky Harahap', 'Wakil Sekretaris Bidang Pembinaan Anggota', 1);

-- ---------- 5. INSTRUKTUR ----------
CREATE TABLE IF NOT EXISTS instruktur (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  materi TEXT NOT NULL COMMENT 'JSON array string, cth: ["BTQ dan Keislaman","MISSION HMI"]',
  photo VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- 6. JADWAL (Jadwal Kegiatan / Program Kerja BPL & PA - satu sumber) ----------
CREATE TABLE IF NOT EXISTS jadwal (
  id INT AUTO_INCREMENT PRIMARY KEY,
  program VARCHAR(255) NOT NULL,
  jenjang VARCHAR(100) NULL,
  waktu VARCHAR(150) NULL,
  tanggal_urut DATE NULL COMMENT 'dipakai untuk mengurutkan, tidak wajib ditampilkan',
  penyelenggara VARCHAR(150) NULL,
  status ENUM('rencana','jalan','terlaksana','tunggu') NOT NULL DEFAULT 'rencana',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO jadwal (program, jenjang, waktu, penyelenggara, status) VALUES
('Basic Training Angkatan I', 'LK I', 'November 2026', 'Komisariat', 'rencana'),
('Sekolah Instruktur (SUPTT)', 'Non-Formal', 'Oktober 2026', 'BPL Cabang', 'rencana'),
('Monitoring pasca Basic Training', 'Follow up', 'Berjalan', 'BPL Cabang', 'jalan'),
('Intermediate Training Reguler', 'LK II', 'Menunggu kuota Cabang/Badko', 'HMI Cabang', 'tunggu'),
('Rapat koordinasi instruktur', 'Internal', 'Setiap bulan', 'BPL Cabang', 'jalan');

-- ---------- 7. KEGIATAN ----------
CREATE TABLE IF NOT EXISTS kegiatan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  date_text VARCHAR(150) NULL,
  excerpt TEXT NULL,
  image VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO kegiatan (title, date_text, excerpt, sort_order) VALUES
('Pembukaan pendaftaran Basic Training Angkatan I', 'Oktober 2026 · Sekretariat Cabang', 'BPL membuka pendaftaran LK1 bagi calon anggota dari seluruh Komisariat di Kabupaten Bekasi.', 0),
('Rapat koordinasi instruktur se-Kabupaten Bekasi', 'Setiap bulan · Bergilir', 'Forum instruktur untuk menyamakan standar fasilitasi dan menyiapkan kalender training berikutnya.', 1),
('Silaturahmi dan konsolidasi Komisariat se-Cabang', 'Triwulan · Lokasi bergilir', 'Mempertemukan pengurus Komisariat untuk membahas kendala lapangan sebelum jenjang latihan berikutnya.', 2);

-- ---------- 8. BERITA ----------
CREATE TABLE IF NOT EXISTS berita (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  date_text VARCHAR(150) NULL,
  excerpt TEXT NULL,
  image VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO berita (title, date_text, excerpt, sort_order) VALUES
('Mengenal fungsi Badan Pengelola Latihan dalam sistem perkaderan HMI', 'BPL Cabang Bekasi', 'Penjelasan singkat mengapa perkaderan formal memerlukan badan pengelola yang berdiri sendiri.', 0),
('Pentingnya follow up pascatraining bagi kader baru', 'BPL Cabang Bekasi', 'Kader yang tidak dibina lanjut setelah LK1 cenderung berhenti aktif — bagaimana BPL menanganinya.', 1),
('Menyongsong status Cabang definitif: apa yang perlu disiapkan', 'BPL Cabang Bekasi', 'Sekilas syarat kaderisasi yang harus dipenuhi HMI Cabang (P) sebelum naik status.', 2);

-- ---------- 9. DATABASE KADER ----------
CREATE TABLE IF NOT EXISTS kader_db (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nik VARCHAR(20) NOT NULL UNIQUE COMMENT 'primary key bisnis: dibuat otomatis oleh server',
  nama VARCHAR(150) NOT NULL,
  alamat VARCHAR(255) NULL,
  komisariat VARCHAR(100) NOT NULL,
  jurusan VARCHAR(150) NULL,
  kampus VARCHAR(150) NULL,
  lk1_tahun VARCHAR(4) NULL,
  lk1_tempat VARCHAR(100) NULL,
  lk2_tahun VARCHAR(4) NULL,
  lk2_tempat VARCHAR(100) NULL,
  lk3_tahun VARCHAR(4) NULL,
  lk3_tempat VARCHAR(100) NULL,
  sc_tahun VARCHAR(4) NULL,
  sc_tempat VARCHAR(100) NULL,
  lkk_tahun VARCHAR(4) NULL,
  lkk_tempat VARCHAR(100) NULL,
  lpp_tahun VARCHAR(4) NULL,
  lpp_lembaga VARCHAR(150) NULL,
  lpp_tempat VARCHAR(100) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- 10. MONITORING KADER (terhubung ke kader_db via NIK) ----------
CREATE TABLE IF NOT EXISTS kader_monitoring (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nik VARCHAR(20) NOT NULL UNIQUE,
  btq TINYINT(1) NOT NULL DEFAULT 0,
  spisph TINYINT(1) NOT NULL DEFAULT 0,
  ndp TINYINT(1) NOT NULL DEFAULT 0,
  mission TINYINT(1) NOT NULL DEFAULT 0,
  konstitusi TINYINT(1) NOT NULL DEFAULT 0,
  kmo TINYINT(1) NOT NULL DEFAULT 0,
  tps TINYINT(1) NOT NULL DEFAULT 0,
  ideopol TINYINT(1) NOT NULL DEFAULT 0,
  sgi TINYINT(1) NOT NULL DEFAULT 0,
  wanus TINYINT(1) NOT NULL DEFAULT 0,
  ndp2 TINYINT(1) NOT NULL DEFAULT 0,
  kmo2 TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_monitoring_nik FOREIGN KEY (nik) REFERENCES kader_db(nik)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- 11. ADMINISTRASI (surat-menyurat Komisariat -> BPL/PA) ----------
CREATE TABLE IF NOT EXISTS administrasi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  komisariat VARCHAR(150) NOT NULL,
  pengaju VARCHAR(150) NOT NULL,
  tujuan ENUM('BPL','PA') NOT NULL,
  perihal VARCHAR(255) NOT NULL,
  isi TEXT NULL,
  kontak_pengaju VARCHAR(50) NULL,
  status ENUM('diajukan','diproses','selesai') NOT NULL DEFAULT 'diajukan',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
