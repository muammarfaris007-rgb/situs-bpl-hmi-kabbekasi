# Online-kan Situs Ini Gratis Lewat GitHub (Tanpa cPanel)

## Arsitektur yang dipakai

GitHub **tidak bisa** menjalankan PHP maupun database — GitHub Pages hanya untuk
file statis. Jadi susunannya begini:

- **GitHub** = tempat menyimpan kode + memicu deploy otomatis (lewat GitHub Actions)
- **InfinityFree** = tempat PHP + MySQL-nya *benar-benar berjalan*, gratis selamanya
- Setiap kali Anda `git push`, GitHub Actions otomatis mengirim file ke InfinityFree lewat FTP

Panel kontrol InfinityFree namanya **VistaPanel** — bukan cPanel, tapi fungsinya
serupa (bikin database, lihat file, dsb). Nama beda, kebutuhan tetap sama karena
PHP+MySQL memang butuh semacam panel untuk dikelola, di hosting manapun.

## Batasan jujur hosting gratis ini (baca dulu sebelum lanjut)

Dibanding hosting cPanel berbayar, InfinityFree membatasi: sekitar 30–50 ribu
kunjungan/hari, database MySQL maksimal 50MB per database (lebih dari cukup untuk
situs organisasi seperti ini), tidak ada SSH, dan dukungan pelanggan bisa lambat
(hitungan jam, bukan menit). Situs yang lama tidak dikunjungi berisiko ditangguhkan.
Untuk situs organisasi dengan trafik wajar, ini biasanya cukup — tapi bukan pengganti
hosting berbayar untuk kebutuhan serius/bisnis. Rutin **backup database & folder
uploads** (caranya ada di `README.md`).

---

## Langkah 1 — Buat akun InfinityFree & domain

1. Daftar di **infinityfree.com** (gratis, tanpa kartu kredit).
2. Klik **Create Account**, pilih:
   - Subdomain gratis dari mereka (misal `bplhmibekasi.infinityfreeapp.com`), **atau**
   - Domain sendiri kalau sudah punya (arahkan nameserver sesuai instruksi mereka).
3. Tunggu beberapa menit sampai status akun **Active**.

## Langkah 2 — Buat database MySQL

1. Masuk ke **Control Panel** (VistaPanel) dari akun InfinityFree Anda.
2. Cari menu **MySQL Databases**, buat database baru — catat **nama database**,
   **username**, dan **password** yang muncul (formatnya biasanya `epizXXXXXXXX_namadb`).
3. Buka **phpMyAdmin** dari Control Panel yang sama, pilih database tadi, klik
   tab **Import**, unggah `database/schema.sql` dari proyek ini, klik **Go**.

## Langkah 3 — Siapkan repositori GitHub

1. Ekstrak seluruh isi paket proyek ini (jangan sertakan folder pembungkus
   `bpl-backend/` — file `index.html`, folder `api/`, dll. harus langsung ada
   di **akar repo**).
2. Buat repository baru di GitHub. **Boleh publik atau privat** — aman, karena
   `config.php` asli (berisi kredensial) sudah otomatis dikecualikan lewat
   `.gitignore` dan tidak akan pernah ikut ter-commit.
3. Upload semua file ke repo tsb (lewat GitHub Desktop, atau command line):
   ```
   git init
   git add .
   git commit -m "Inisialisasi situs BPL x Bidang PA"
   git branch -M main
   git remote add origin https://github.com/USERNAME/NAMA-REPO.git
   git push -u origin main
   ```

## Langkah 4 — Ambil kredensial FTP InfinityFree

Di Control Panel InfinityFree, buka menu **FTP Accounts**, catat:
- **FTP Server** (biasanya `ftpupload.net` — tapi cek juga bagian "FTP Details"
  di akun Anda untuk memastikan, kadang tiap akun sedikit berbeda)
- **Username** dan **Password** FTP
- **Folder tujuan**, formatnya `/namadomainanda.com/htdocs/` — lihat persis
  namanya di File Manager InfinityFree bagian domain Anda

## Langkah 5 — Simpan kredensial itu sebagai GitHub Secrets

Di repo GitHub Anda: **Settings → Secrets and variables → Actions → New repository secret**.
Buat 4 secret ini satu per satu:

| Nama secret | Isi |
|---|---|
| `FTP_SERVER` | contoh: `ftpupload.net` |
| `FTP_USERNAME` | username FTP dari Langkah 4 |
| `FTP_PASSWORD` | password FTP dari Langkah 4 |
| `FTP_SERVER_DIR` | contoh: `/namadomainanda.com/htdocs/` |

Workflow otomatis (`.github/workflows/deploy.yml`, sudah disertakan di proyek ini)
akan memakai keempat secret ini setiap kali Anda push ke branch `main`.

## Langkah 6 — Push dan tunggu deploy otomatis

```
git add .
git commit -m "Deploy pertama"
git push
```

Buka tab **Actions** di repo GitHub Anda — akan muncul proses "Deploy ke InfinityFree"
berjalan (sekitar 1-2 menit). Kalau centang hijau ✅, file sudah masuk ke InfinityFree.

## Langkah 7 — Isi config.php langsung di server (sekali saja, manual)

File `api/config.php` **sengaja tidak ikut ter-deploy otomatis** (demi keamanan —
lihat penjelasan `.gitignore` di atas). Isi sekali secara manual:

1. Buka **File Manager** InfinityFree, masuk ke folder `htdocs/api/`.
2. Upload `api/config.sample.php` dari proyek ini, lalu **rename** jadi `config.php` di sana.
3. Edit file itu langsung di File Manager, isi 3 baris kredensial database dari Langkah 2.
4. Simpan.

## Langkah 8 — Buka situsnya

Akses domain/subdomain InfinityFree Anda di browser. Login pengurus default masih
sama: **admin / Admin123** — segera ganti (caranya ada di `README.md`, bagian
"Mengganti password admin").

---

## Alur kerja setelah ini

Setiap kali ingin mengubah kode (bukan konten — konten diedit lewat situsnya
sendiri di mode Login Pengurus), cukup edit file lokal, lalu:
```
git add .
git commit -m "Perubahan apa"
git push
```
GitHub Actions otomatis mengirim perubahan itu ke InfinityFree dalam 1-2 menit.
`uploads/` dan `api/config.php` di server **tidak akan pernah tertimpa** oleh proses ini.
