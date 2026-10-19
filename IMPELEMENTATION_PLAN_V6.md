# Implementation Plan — RS Elisabeth CMS

Versi: 6 (status eksekusi MVP diperbarui; MVP-2.5 Marketing Reorder ditambahkan)

Sumber temuan:
- `improvement_and_scalability_review.md` (laporan review)
- Analisis mandiri B1 s/d B13 (lihat bagian 9)

Prinsip kerja:
- **MVP dulu**: sistem harus berjalan normal dan aman untuk operasional harian.
- Setiap item dikerjakan satu per satu, dengan test yang **gagal sebelum perbaikan** dan **lulus sesudahnya**.
- Setiap item dalam commit terpisah agar mudah di-review dan di-rollback.
- Tidak ada eksekusi sebelum instruksi eksplisit dari pemilik proyek.
- Status eksekusi: **MVP-0 s/d MVP-3.6 selesai**.
- MVP-2.5 Marketing Reorder: **selesai**.

---

## 1. Keputusan Final

| # | Keputusan | Dampak teknis |
|---|---|---|
| K-1 | Staff IT **tidak** boleh mengelola User. | Cabut `*:User` dari Staff IT. |
| K-2 | Manajemen Role dan User **hanya super_admin**: membuat, mengubah, menghapus, assign role, mengatur permission role, membuat role baru, reset password. | Cabut `*:Role` dari Staff IT (termasuk `View:Role`). Policy mengecek `hasRole('super_admin')`. |
| K-3 | Hanya **satu** user dengan role `super_admin`. | Dijaga di model (observer), policy, dan seeder. |
| K-4 | Pencabutan akses staf memakai kolom **`is_active`** (role tidak dihapus). | Kolom boolean `is_active` default `true`. `canAccessPanel()` mengecek `is_active` dan keberadaan role. |
| K-5 | Pemulihan super_admin **hanya lewat terminal** di server. Tanpa SMTP. | Lihat **Next Phase N-1**. |
| K-6 | Password super_admin **acak** saat instalasi (opsi A), dicetak sekali di terminal. | Command `app:super-admin:init`. Tidak ada password tetap di kode. |
| K-7 | Role `super_admin` **dikunci**: permission tidak bisa diubah, role tidak bisa dihapus, termasuk lewat UI Shield. | Observer `Role` dan policy. |
| K-8 | `must_change_password` **diblokir penuh** di server (middleware). Halaman ganti password terpisah, form tampil di modal yang tidak bisa ditutup. | Berlaku untuk semua user termasuk super_admin. |
| K-9 | Password default **marketing dan IT = `123456`**, wajib diganti saat login pertama (K-8). | Hanya untuk akun marketing dan IT. Reset oleh super_admin memakai `123456` sementara. |
| K-10 | **Disk media tunggal** untuk semua environment. | Satu config `media_disk`, dipakai form, tabel, dan trait. |
| K-11 | Widget feedback **dummy dibiarkan** sampai diskusi setelah MVP. | Dicatat sebagai known issue. |
| K-12 | Aturan tanggal promosi di **API**. Tanggal kosong: `start_date` kosong = langsung berlaku, `end_date` kosong = tanpa batas akhir dengan checkbox wajib. | Lihat P-PROMO. |
| K-13 | Topologi **single host**. Target downtime maksimal **1 jam per bulan**. | Multi-replika tidak dikerjakan. |
| K-14 | **Backup manual sebelum setiap deploy** yang berisi migration. Dikelola **Staff IT**. | Lihat bagian 4. |
| K-15 | **Pemegang akun super_admin: Bagian IT** (saat ini penanggung jawab project = developer). Modul Role, User, dan pemulihan dipegang Bagian IT. Kepala Bagian Marketing **tidak** memiliki wewenang atas role dan user. | Lihat bagian 3 dan R-1. |
| K-17 | **Satu super_admin** untuk saat ini (K-3 tetap berlaku). | Tidak ada akun darurat kedua. |
| K-18 | **Password awal super_admin** dicetak oleh `app:super-admin:init` dan disimpan oleh **penanggung jawab project** (developer). | Tidak ada password manager dua pemegang untuk MVP. |
| K-19 | **Runbook `tinker` ditunda** dari MVP. Tidak ada prosedur reset manual sampai Next Phase. | Lihat N-1 dan bagian 3. |
| K-20 | **Approval reset, berita acara, dan backup terjadwal** dibahas di Next Phase. Tidak ada approval khusus di MVP. | Lihat N-1, N-2. |
| K-21 | **Migrate dan seed dipindah dari entrypoint** ke one-off container, dijalankan sekali per rilis mulai **R-MVP-1**. Entrypoint tidak lagi menjalankan migrate atau seed. | Lihat bagian 11.3 dan 11.7. |
| K-22 | **Commit format tersendiri (`pint`) sebelum R-MVP-1**, tanpa perubahan logika, agar diff logika mudah di-review. | Lihat bagian 11.2 dan 11.7. |
| K-16 | Checkbox wajib untuk `end_date` kosong (bukan popup). | Lihat P-PROMO. |

### 1.1 Asumsi
- Staff IT boleh melihat (view) modul konten.
- Role `marketing` tanpa Delete.
- Staff IT adalah pemilik akses server dan pemantau sistem.

---

## 2. Ringkasan Pembagian

| Bagian | Isi | Tujuan |
|---|---|---|
| **MVP** (bagian 5) | Perbaikan wajib sebelum sistem dianggap berjalan normal | Operasional aman dan stabil |
| **Next Phase** (bagian 6) | Pemulihan terminal, backup terjadwal, audit log lengkap, RPO, temuan menengah, skala | Dikerjakan setelah MVP berjalan normal |
| **Prasyarat operasional** (bagian 4) | Langkah yang dilakukan manusia, bukan kode | Harus selesai sebelum deploy MVP |

### 2.1 Status Eksekusi Aktual

| Kelompok | Status | Referensi commit utama |
|---|---|---|
| K-22 formatting | ✅ selesai | `50781cb` |
| MVP-0 | ✅ selesai | `710c7a3`, `f3d0b3e`, `76bfe33` |
| K-21 / MVP-3.2 | ✅ selesai | `68f0cea` |
| MVP-1 | ✅ selesai | `ad277f0`, `70d6a7e`, `5cdbaf0` |
| MVP-2.1 s/d MVP-2.4 | ✅ selesai | `99c2ffb`, `50e7408`, `3f8f52c`, `e44cdfa` |
| MVP-3.1 | ✅ selesai | `0ef133e` |
| MVP-3.3 | ✅ selesai | `70d6a7e` |
| MVP-3.4 | ✅ selesai | `d49f3d0` |
| MVP-3.5 | ✅ selesai secara operasional | `.env.production` tidak tracked Git |
| MVP-3.6 | ✅ selesai | `1403aaf` |
| MVP-2.5 Marketing Reorder | ✅ selesai | `b7cdb3a` |

Baseline setelah MVP-2.5: **239 test lulus** dan `pint --test` lulus.

Validasi Docker terakhir: build image berhasil, one-off migrate dan seed berhasil,
`cms_app` healthy, `cms_worker` running, Nginx `/up` HTTP 200, halaman admin HTTP
200, dan API CMS HMAC HTTP 200. Nginx juga diperbaiki agar tidak memakai IP
PHP-FPM lama setelah container `cms_app` direcreate.

---

## 3. Mitigasi Super_admin Sementara (sampai Next Phase selesai)

**Keputusan (K-15, K-17, K-18, K-19):** pemegang akun super_admin adalah Bagian IT. Saat ini penanggung jawab project (developer) yang menyimpan password awal. Satu super_admin saja. Runbook `tinker` **ditunda** dan tidak termasuk MVP.

**Konsekuensi untuk MVP:**
- Tidak ada prosedur reset password super_admin resmi sampai Next Phase (N-1). Jika password hilang, pemulihan bergantung pada penyimpanan password awal oleh penanggung jawab project.
- Pemulihan dengan `tinker` tidak didokumentasikan sebagai prosedur. Ini sengaja, supaya tidak menjadi jalur tanpa kontrol.

**Risiko R-1 (diterima sadar, diturunkan dari versi 3):** akun super_admin hanya satu, dan hanya satu orang (penanggung jawab project) yang menyimpan password awalnya. Jika orang itu tidak tersedia dan password hilang, sistem tidak bisa dikelola sampai pemulihan dibuat di Next Phase. Mitigasi: penyimpanan password awal harus aman (password manager pribadi penanggung jawab, dengan cadangan tertulis di tempat aman yang diketahui Bagian IT).

Kontrol yang tetap berjalan di MVP:
1. **Tidak ada pembuatan super_admin baru** lewat UI atau command selain `app:super-admin:init` (K-3 dijaga model).
2. **Password acak** dibuat oleh `app:super-admin:init`, ditampilkan sekali, dan `must_change_password = true`.
3. **Forced change penuh** (MVP-1.3): super_admin wajib mengganti password pada login pertama.
4. **Audit minimal** (MVP-3.6): perubahan password super_admin tercatat.

Yang sengaja **tidak** ada di MVP: runbook `tinker`, approval reset, berita acara, dan password manager dua pemegang. Semuanya masuk Next Phase (N-1).

---

## 4. Prasyarat Operasional (dikerjakan manusia, sebelum deploy MVP)

| ID | Langkah | Pelaksana | Kapan |
|---|---|---|---|
| OP-1 | **Ganti password `admin@123456`** pada akun super_admin produksi saat ini. Password lama dianggap bocor karena sudah ada di repo dan riwayat git. | Staff IT | **Paling awal**, hari ini bila memungkinkan |
| OP-2 | Simpan password awal super_admin (dari `app:super-admin:init`) di password manager pribadi penanggung jawab project, dengan cadangan tertulis di tempat aman yang diketahui Bagian IT (K-18). | Penanggung jawab project | Saat `app:super-admin:init` dijalankan pertama kali |
| OP-3 | **Backup pertama** database dan volume MinIO sebelum perubahan apa pun. | Staff IT | Sebelum Tahap 0 |
| OP-4 | **Staging**: salinan database produksi, dijalankan migration dan seeder MVP, lalu uji smoke test (bagian 8). | Staff IT | Sebelum Tahap 0 dimerge ke produksi |
| OP-5 | Verifikasi `docker-compose` dan `.env.production` tidak berisi kredensial lama di repo. | Staff IT | Sebelum deploy |
| OP-6 | Siapkan jendela deploy di luar jam layanan utama. Target downtime maksimal 1 jam. | Staff IT | Setiap deploy |
| OP-7 | Konfirmasi kebijakan jam layanan utama RS untuk jendela deploy. | Manajemen | Sebelum deploy pertama |

**Prosedur backup manual sebelum setiap deploy yang berisi migration (K-14):**

```
# Dijalankan Staff IT di server, sebelum deploy
docker exec rses_cms_db pg_dump -U <DB_USERNAME> <DB_DATABASE> > backup_<YYYYMMDD_HHMM>.sql
```

- File disimpan di lokasi **terpisah dari server** dan **terenkripsi** (berisi hash password dan data pribadi).
- Tanggal dan nama file dicatat di buku operasional.
- Restore hanya dilakukan dengan persetujuan dua orang (Staff IT dan pemegang kedua).
- Uji restore minimal sekali ke staging sebelum MVP dinyatakan siap.

---

## 5. MVP

Urutan eksekusi MVP: **Tahap 0 → Tahap 1 → Tahap 2 → Tahap 3**. Setiap tahap selesai (test lulus, tidak ada regresi) sebelum tahap berikutnya dimulai.

### 5.1 Tahap 0 — Kritis (data dan akses)

#### MVP-0.1 (P1-J). Kolom `is_active` pada user

**Severity**: 🔴 kritis (prasyarat MVP-0.2)

**Lokasi**
- Migration baru: `..._add_is_active_to_users_table.php` (boolean, default `true`, index)
- `app/Models/User.php` → `canAccessPanel()`, `casts()`, `$fillable`
- `UserForm.php` → toggle "Aktif"
- `UsersTable.php` → kolom status, aksi Nonaktifkan dan Aktifkan (hanya super_admin)

**Rencana**
1. Migration menambah `is_active`. User yang sudah ada otomatis aktif.
2. `canAccessPanel()` mengembalikan true hanya bila `is_active` true dan user punya minimal satu role.
3. Aksi nonaktifkan dan aktifkan hanya tampil untuk super_admin.
4. Tidak boleh menonaktifkan diri sendiri.
5. Tidak boleh menonaktifkan satu-satunya super_admin (K-3).
6. Sesi aktif user yang dinonaktifkan ditolak di request berikutnya (karena `canAccessPanel()` dipanggil tiap request).

**Verifikasi (test wajib)**
- User aktif dengan role → `canAccessPanel()` true.
- `is_active=false` dengan role → false, role tetap ada.
- Aktifkan kembali → true, role tidak berubah.
- Nonaktifkan diri sendiri → ditolak.
- Nonaktifkan super_admin tunggal → ditolak.
- Request dari sesi yang baru dinonaktifkan → ditolak.

---

#### MVP-0.2 (B1 / P1-H1). Pencabutan akses dibatalkan setiap boot

**Severity**: 🔴 kritis

**Lokasi**
- `database/seeders/RoleAndPermissionSeeder.php` → `assignRolesToUsers()`
- `database/seeders/AdminUserSeeder.php`

**Masalah**
- Loop "staff umum" memberi `marketing` ke user `role='staff'` tanpa role, setiap boot.
- Loop "super_admin lama" memberi `super_admin` ke **setiap** user berkolom `role='super_admin'` (melanggar K-3, lihat B7).
- `syncRoles()` untuk tiga akun default dijalankan tiap boot.

**Rencana**
1. Hapus kedua loop dari seeder.
2. Migration versi `..._migrate_legacy_user_roles.php`: berjalan sekali. Hanya memberi `marketing` ke user `role='staff'` tanpa role Spatie. **Tidak pernah** memberi `super_admin`.
3. Tiga akun default: role hanya diberikan saat akun **baru dibuat** (`wasRecentlyCreated`).
4. Akun yang sudah ada tidak disentuh oleh seeder.

**Verifikasi (test wajib)**
- User `staff` tanpa role → `db:seed` dua kali → tetap tanpa role.
- Role marketing dicabut manual → `db:seed` → tidak kembali.
- Migration legacy: memberi `marketing` tepat sekali; migrate ulang tidak mengubah apa pun.
- Dua user berkolom `role='super_admin'` → tidak ada yang diberi role super_admin oleh migration atau seeder.

---

#### MVP-0.3 (B3 + B4 / P1-I). File asli dihapus sebelum DB terupdate; disk tidak konsisten

**Severity**: 🔴 kritis (kehilangan data)

**Lokasi**
- `app/Traits/ConvertsImagesToWebp.php` → `convertFieldsToWebp()`, `getStorageDisk()`
- Semua form dan tabel Filament dengan `->disk('s3')` (13 tempat)
- `config/filesystems.php`

**Masalah**
- Urutan saat ini: upload `.webp` → **hapus file asli** → update DB. Tanpa transaksi. Jika update gagal, file asli hilang dan record menunjuk file yang tidak ada.
- Trait memakai `filesystems.default`, form memakai `disk('s3')`. Jika `FILESYSTEM_DISK=local`, konversi diam-diam dilewati.

**Rencana (K-10)**
1. Urutan baru: upload `.webp` → update DB → **baru** hapus file asli.
2. Jika update DB gagal: hapus `.webp` yang baru dibuat, log error, file asli tetap ada.
3. Jika konversi gagal (`null`): file asli tidak dihapus.
4. Satu config `filesystems.media_disk` (default `s3`). Semua form, tabel, dan trait memakai config ini. Tidak ada lagi literal `'s3'`.
5. Lokal dev memakai MinIO di docker-compose. Test memakai `Storage::fake`.

**Verifikasi (test wajib)**
- Konversi sukses → `.webp` ada, file asli hilang, DB menunjuk `.webp`.
- Update DB gagal → file asli tetap, `.webp` baru dibersihkan.
- Decode gagal → file asli tetap, DB tidak berubah.
- Tidak ada literal `->disk('s3')` tersisa di `app/`.

---

### 5.2 Tahap 1 — Perilaku sistem yang benar

#### MVP-1.1 (P1-A). Seeder menimpa permission role di setiap boot

**Severity**: 🔴 tinggi

**Rencana**
- Role baru: `syncPermissions()` untuk set awal.
- Role yang sudah ada: `givePermissionTo()` hanya untuk permission yang belum ada. Seeder tidak me-revoke.
- `super_admin` selalu mendapat semua permission (aditif).
- Pencabutan permission lama dilakukan lewat migration (MVP-2.1), bukan seeder.
- Komentar "add-only" di entrypoint diperbaiki sesuai perilaku nyata.

**Verifikasi**
- Permission custom ditambahkan ke `marketing` → `db:seed` → bertahan.
- Role `marketing` dihapus → `db:seed` → dibuat ulang dengan permission default.

---

#### MVP-1.2 (P1-B). Error handler API mengubah semua exception jadi 500

**Severity**: 🟠 sedang

**Lokasi**: `bootstrap/app.php` (`withExceptions`).

**Rencana**
- `HttpExceptionInterface` → status asli.
- `AuthenticationException` → 401.
- `ValidationException` → 422.
- Lainnya → 500 generik.
- `env('APP_DEBUG')` → `config('app.debug')`.

**Verifikasi**
- `POST /api/v1/cms/news` → 405 JSON.
- `GET /api/user` tanpa token → 401 JSON.
- Route tidak ada → 404 JSON.
- Error internal (APP_DEBUG=false) → 500 tanpa detail.

---

#### MVP-1.3 (K-6, K-8, K-9 / P1-K + B8, B9). Password dan forced change

**Severity**: 🔴 tinggi

**Lokasi**
- `AdminUserSeeder.php` (hapus `admin@123456` dan `123456` tetap untuk super_admin)
- `UserForm.php`, `UsersTable.php` (reset password)
- `app/Http/Middleware/` (middleware baru, mengganti peringatan `CheckDefaultPassword`)
- Halaman baru: `app/Filament/Pages/Auth/ForcePasswordChange.php`

**Rencana**
1. **super_admin**: command `app:super-admin:init` (idempoten). Jika belum ada super_admin, membuat akun dengan password acak (`Str::password(20)`), `must_change_password = true`, menampilkan password **sekali**. Jika sudah ada, gagal tanpa perubahan. Seeder tidak membuat super_admin.
2. **marketing dan IT**: dibuat dengan password `123456` dan `must_change_password = true` oleh seeder. Tidak pernah diganti oleh seeder setelah akun ada.
3. **Reset password oleh super_admin**: marketing dan IT mendapat `123456` sementara dan wajib diganti. Super_admin mendapat password acak (yang ditampilkan sekali).
4. **Middleware blokir penuh** pada panel: jika `must_change_password = true`, semua request selain whitelist diarahkan ke halaman ganti password.
   - Whitelist: halaman ganti password, logout, dan request Livewire dari komponen halaman itu.
5. **Halaman ganti password terpisah** (bukan popup biasa). Form tampil di modal yang tidak bisa ditutup dengan klik di luar atau Esc. Ada tombol Simpan dan Keluar.
6. **Validasi password baru**: minimal 8 karakter; tidak boleh `123456`, `admin@123456`, atau sama dengan password lama; konfirmasi harus cocok.
7. Setelah berhasil: `must_change_password = false`, redirect ke dashboard.

**Verifikasi (test wajib)**
- Tidak ada string `123456` atau `admin@123456` di `database/seeders/` untuk super_admin.
- User dengan `must_change_password=true` → request ke dashboard diarahkan ke halaman ganti password.
- Request Livewire dari komponen lain → ditolak.
- Password baru `123456` → ditolak. Password sama dengan lama → ditolak.
- Setelah ganti password → dashboard dapat diakses.
- `app:super-admin:init` kedua kali → gagal, tidak ada perubahan.

---

### 5.3 Tahap 2 — Hak akses dan promosi

#### MVP-2.1 (B2 / P1-H2). Staff IT dan manajemen User/Role

**Severity**: 🟠 tinggi

**Rencana**
1. Migration `..._revoke_staff_it_user_and_role_management.php`: hapus seluruh `*:User` dan `*:Role` dari role Staff IT. Idempoten.
2. Seeder: hapus `ADMIN_SUBJECTS` dari daftar Staff IT.
3. Policy `UserPolicy` dan `RolePolicy`: izinkan hanya bila `hasRole('super_admin')`.
4. `UserResource` dan `RoleResource` (Shield): hanya super_admin.
5. Form user: opsi role `super_admin` tidak pernah ditampilkan.
6. **K-3 di tiga lapis**:
   - Form: tolak jika akan ada lebih dari satu super_admin.
   - Observer `User` (`saving`, `deleting`): tolak bila hasil akhir jumlah super_admin ≠ 1.
   - Tombol hapus dan nonaktifkan disembunyikan untuk super_admin.
7. **K-7**: observer `Role` menolak `deleting` untuk `super_admin`. Permission role `super_admin` tidak bisa diubah lewat UI.

**Verifikasi (test wajib)**
- Staff IT → `GET /admin/users` → 403.
- Staff IT → `UserPolicy` dan `RolePolicy` (viewAny, create, update) → false.
- Staff IT tidak bisa membuat user, mengubah role, reset password, atau mengubah permission role lewat HTTP langsung.
- Membuat super_admin kedua → ditolak. Mencabut super_admin tunggal → ditolak. Menghapus super_admin → ditolak.
- Migration revoke idempoten.

---

#### MVP-2.2 (P-PROMO). Aturan tanggal promosi di API dan form

**Severity**: 🟠 sedang

**Keputusan**: K-12, K-16.

**Lokasi**
- `app/Models/Promotion.php` (scope `activeOn`)
- `app/Http/Controllers/Api/Cms/PromotionApiController.php` (daftar dan detail)
- `app/Filament/Resources/Promotions/Schemas/PromotionForm.php`

**Rencana**
1. Migration: `start_date` dan `end_date` menjadi nullable (kolom sudah nullable, cek tanpa perubahan skema).
2. Form:
   - `start_date` tidak wajib. Kosong berarti langsung berlaku.
   - `end_date` tidak wajib. Kosong berarti tanpa batas akhir.
   - `end_date` harus sama atau setelah `start_date` (`afterOrEqual`).
   - Bila `end_date` kosong: hint di bawah field, dan checkbox wajib "Saya memahami promo ini akan tampil terus sampai saya menonaktifkannya secara manual lewat toggle Aktif". Validasi di server.
3. API (daftar dan detail sama):
   - Tampil bila `is_active = true` **dan** `(start_date IS NULL OR start_date <= hari_ini)` **dan** `(end_date IS NULL OR end_date >= hari_ini)`.
   - "Hari ini" memakai `APP_TIMEZONE` (Asia/Jakarta).
   - Tanggal hari ini ikut di cache key daftar (`Y-m-d`), agar cache berganti saat pergantian hari.
4. Istilah di form: "Aktif" (konsisten dengan kolom `is_active`), bukan "publish".

**Verifikasi (test wajib)**
- `start_date` masa depan → tidak tampil.
- `end_date` kemarin → tidak tampil (daftar dan detail).
- `end_date` hari ini → tampil (inklusif).
- Kedua tanggal kosong → tampil.
- `end_date` kosong → checkbox wajib; tanpa centang → ditolak.
- `end_date < start_date` → ditolak.
- Pergantian hari → cache daftar berubah (uji dengan manipulasi waktu).

---

#### MVP-2.3 (P1-C). Slug Room Facility berubah saat edit

**Severity**: 🟠 sedang

**Rencana**: `afterStateUpdated` hanya men-set slug saat `create` atau slug kosong (pola `NewsForm`).

**Verifikasi**: edit nama → slug tetap. Buat baru → slug terisi.

---

#### MVP-2.4 (P1-D). Reorder tidak invalidasi cache dan webhook

**Severity**: 🟠 sedang

**Lokasi**: `BannerPromotionsTable.php`, `RoomFacilitiesTable.php`, `FacilityServiceTable.php` (`afterReordering`).

**Rencana**: helper `InvalidatesApiCache::forModule()` yang me-`forget` key `rs_web_cms_api_*` dan dispatch `SendCacheInvalidationWebhookJob`. Hapus key `local_cms_*`.

**Verifikasi**: callback reorder → job ter-dispatch dan key cache hilang.

---

#### MVP-2.5. Marketing boleh melakukan drag-and-drop reorder

**Status**: ✅ selesai · **Severity**: 🟠 fungsional

**Latar belakang**
- UI tabel Banner Promotion, Facility Service, dan Room Facility sudah memiliki
  `reorderable('sort_order')`.
- Namun role `marketing` belum memiliki permission `Reorder:*`, sehingga
  `isReorderable()` bernilai false dan kontrol drag-and-drop tidak dapat dipakai.
- Kebutuhan bisnis: Marketing harus dapat mengatur urutan konten yang mereka kelola.

**Rencana**
1. Tambahkan `Reorder:BannerPromotion`, `Reorder:FacilityService`, dan
   `Reorder:RoomFacility` sebagai permission awal role marketing.
2. Buat migration idempoten untuk menambahkan permission tersebut ke role marketing
   yang sudah ada, tanpa mencabut permission lain.
3. Pertahankan perilaku Staff IT: tidak otomatis mendapat permission Reorder kecuali
   ada keputusan bisnis terpisah.
4. Validasi drag-and-drop melalui Livewire dan API:
   - urutan database berubah,
   - cache API langsung dihapus,
   - webhook modul dikirim,
   - API mengembalikan urutan baru.
5. Pastikan seeder aditif tidak menghapus permission Reorder yang sudah ada.

**Verifikasi wajib**
- Role marketing memiliki ketiga permission Reorder setelah migration.
- Migration dapat dijalankan ulang tanpa duplikasi atau perubahan permission lain.
- Marketing dapat reorder Banner Promotion, Facility Service, dan Room Facility.
- Setelah reorder, cache API dan webhook di-invalidasi.
- Test suite dan `pint --test` tetap lulus.

---

### 5.4 Tahap 3 — Perbaikan kecil yang masuk MVP

| ID | Item | Rencana singkat | Severity |
|---|---|---|---|
| MVP-3.1 | Rate limiting API (P1-E) | `throttle` group `v1/cms` (key `X-Cons-ID`, fallback IP). `page` di-clamp 1–1000. | 🟠 |
| MVP-3.2 | Sentinel healthcheck (P1-F bagian 1) | `rm -f /tmp/app_healthy` di awal entrypoint. | 🟡 |
| MVP-3.3 | Referensi command mati (P1-G) | Hapus `RefreshGlossaryCache` dari `withCommands`. | 🟡 |
| MVP-3.4 | Webhook: `cons_id` dan `Throwable` (P1-N) | Cek `cons_id`; ganti `catch (\Exception)` menjadi `\Throwable`. | 🟡 |
| MVP-3.5 | `.env.production` duplikat (P1-T) | Hapus blok duplikat. | 🟡 |
| MVP-3.6 | Audit minimal password super_admin (lihat bagian 3 poin 5) | Tabel `audit_logs` sederhana: `actor`, `action`, `subject`, `created_at`, tanpa field password. | 🟠 |

Catatan: MVP-3.6 hanya tabel dan satu jenis aksi (perubahan password super_admin). Audit lengkap dipindah ke Next Phase.

---

## 6. Next Phase (setelah MVP berjalan normal)

| ID | Item | Alasan ditunda | Prasyarat |
|---|---|---|---|
| N-1 | **Prosedur pemulihan super_admin resmi** (`app:super-admin:recover`) dengan nomor berita acara dan passphrase approver, tanpa SMTP | Butuh desain operasional dan pemisahan tugas yang lengkap | Keputusan pemegang dan approver. Runbook MVP sudah berjalan. |
| N-2 | **Backup terjadwal**: dump harian, mingguan, bulanan; backup MinIO; retensi | Butuh jadwal cron dan penyimpanan terpisah. Dikelola Staff IT. | Lokasi penyimpanan terenkripsi |
| N-3 | **Audit log lengkap**: create/update/delete user, assign/revoke role, perubahan permission role, reset password, perubahan `is_active`, penggunaan N-1 | Melengkapi MVP-3.6 | — |
| N-4 | **RPO** ditetapkan dan diuji | Bergantung pada N-2 | Keputusan RPO |
| N-5 | **Uji restore berkala** (bulanan) ke staging | Bagian dari N-2 | Staging tersedia |
| N-6 | Konversi WebP ke queue | Mengubah perilaku (gambar baru sedikit lebih lambat tampil) | Persetujuan |
| N-7 | P1-L (rekomendasi operasional lengkap) dan pemisahan tugas Staff IT | Keputusan organisasi | Lihat R-1 |
| N-8 | Temuan menengah: slug soft-delete (P1-P), kolom `users.role` legacy (P1-Q), `sort_order` banner race (P1-R), `articles.views` (P1-S), verifikasi `CheckDefaultPassword` berulang (P1-U) | Tidak memblokir operasional | — |
| N-9 | Versi cache `time()` → `Str::uuid()` (P1-O) | Tidak memblokir | — |
| N-10 | CI dengan PostgreSQL (P1-V) | Perbedaan SQLite vs PostgreSQL perlu ditinjau | — |
| N-11 | Pin dependensi dan Dockerfile (P1-F bagian 2) | Tidak memblokir | — |
| N-12 | Hapus stage Node di Dockerfile (setelah verifikasi `grep`) | Tidak memblokir | — |
| N-13 | Widget feedback dummy (K-11) | Keputusan Anda setelah MVP | Diskusi |
| N-14 | Rate limit lanjutan, header cache HTTP (ETag), konsistensi pola 404 | Perbaikan kualitas | — |
| N-15 | Pemisahan migrate/seed dari entrypoint dan `container_name` | Hanya jika skala berubah | Lihat K-13 |
| N-16 | Redis, scheduler, rotasi secret API, fan-out webhook | Hanya jika multi-replika | Keputusan skala |

---

## 7. Urutan Eksekusi Ringkas

```
Prasyarat (bagian 4): OP-2 → OP-3 → OP-4 → OP-1 → OP-5 → OP-6
MVP Tahap 0: MVP-0.1 → MVP-0.2 → MVP-0.3
MVP Tahap 1: MVP-1.1 → MVP-1.2 → MVP-1.3
MVP Tahap 2: MVP-2.1 → MVP-2.2 → MVP-2.3 → MVP-2.4
MVP Tahap 3: MVP-3.1 → MVP-3.6 (urutan lain bebas)
Deploy MVP: backup pra-deploy (K-14) → migrate → seed → uji smoke test
Next Phase: N-1 sampai N-16 sesuai prioritas setelah MVP stabil
```

OP-1 (ganti password `admin@123456`) dianjurkan **paling awal**, karena password tersebut sudah ada di repo dan riwayat git, dan tidak perlu menunggu kode selesai.

---

## 8. Verifikasi Umum

- Baseline: `php artisan test` 65 test lulus.
- Setiap item MVP menambah test yang gagal sebelum perbaikan dan lulus sesudahnya.
- Sebelum setiap tahap ditutup:
  - `php artisan test`
  - `php vendor/bin/pint --test` untuk file yang diubah
  - `php artisan migrate:fresh --seed --force` di lokal
  - migrate dan seed diuji di staging dengan salinan data produksi (OP-4)
- Smoke test setelah deploy:
  - login super_admin (dengan password yang sudah diganti)
  - login marketing dan IT → dipaksa ganti password
  - akses API `GET /api/v1/cms/news` dengan HMAC → 200
  - promosi dengan tanggal kedaluwarsa tidak tampil
  - restart container dua kali → akses staf yang dinonaktifkan tidak kembali
- Tambah job CI PostgreSQL di N-10.

---

## 9. Temuan Analisis Mandiri (ringkas, dipetakan ke item)

| ID | Temuan | Dipetakan ke | Fase |
|---|---|---|---|
| B1 | Pencabutan akses dibatalkan tiap boot | MVP-0.2 | MVP |
| B2 | Staff IT bisa mengelola user dan menaikkan hak akses | MVP-2.1 | MVP |
| B3 | File asli dihapus sebelum DB terupdate | MVP-0.3 | MVP |
| B4 | Disk `s3` literal vs `filesystems.default` | MVP-0.3 | MVP |
| B5 | Race `sort_order` banner | N-8 | Next |
| B6 | Webhook `TypeError` tidak tertangkap | MVP-3.4 | MVP |
| B7 | Migrasi "super_admin lama" bisa membuat banyak super_admin | MVP-0.2 | MVP |
| B8 | Password tetap `123456` dan `admin@123456` | MVP-1.3 | MVP |
| B9 | `must_change_password` hanya peringatan | MVP-1.3 | MVP |
| B10 | Role super_admin bisa dihapus atau permission diubah | MVP-2.1 | MVP |
| B11 | Tidak ada audit log | MVP-3.6 (minimal), N-3 (lengkap) | MVP + Next |
| B12 | Tidak ada cara mencabut akses tanpa menghapus role | MVP-0.1 | MVP |
| B13 | `shield:super-admin` bawaan bisa membuat super_admin kedua | Kebijakan: tidak dipakai di produksi. Dicatat di runbook. | MVP (kebijakan) |

---

## 10. Risiko yang Diterima

| ID | Risiko | Mitigasi | Pemilik keputusan |
|---|---|---|---|
| R-1 | Satu super_admin dan satu penyimpan password awal (penanggung jawab project). Jika hilang, tidak ada jalur pemulihan resmi sampai Next Phase. | Password manager pribadi, cadangan tertulis (OP-2), audit minimal (MVP-3.6). N-1 menutup risiko ini. | Pemilik proyek (keputusan sadar) |
| R-2 | Backup tidak terjadwal sampai N-2 selesai. RPO efektif bisa mencapai beberapa minggu bila tidak ada backup pra-deploy. | Backup pra-deploy wajib (K-14). N-2 segera setelah MVP. | Pemilik proyek |
| R-3 | Widget feedback menampilkan data palsu | Diberi label known issue (K-11) | Pemilik proyek |
| R-4 | Single host: satu kegagalan server berarti downtime sampai restore selesai | Target 1 jam per bulan (K-13), backup pra-deploy, runbook rollback | Pemilik proyek |

---

## 11. Strategi CI/CD

Bagian ini menjelaskan bagaimana perubahan MVP sampai ke produksi tanpa melanggar target downtime 1 jam per bulan (K-13), tanpa membuat data hilang, dan tanpa membuka jalur deploy yang tidak tercatat.

### 11.1 Prinsip

1. **Satu jalur deploy**: semua perubahan masuk lewat branch dan merge request, tidak ada perubahan langsung di server.
2. **Pipeline yang sama** untuk staging dan produksi. Yang berbeda hanya data dan variabel environment.
3. **Migration dijalankan sekali, terpisah dari start container**, bukan di tiap boot. Ini menutup risiko race dan menghilangkan ketergantungan entrypoint pada migrate (sesuai N-15, dipercepat sebagian karena MVP-0 mengubah perilaku seeder).
4. **Backup pra-deploy wajib** bila ada migration (K-14).
5. **Rollback selalu tersedia**: image sebelumnya disimpan dengan tag, dan rollback kode tidak menyentuh data.

### 11.2 Tahap pipeline

| Tahap | Dijalankan di | Isi | Gagal bila |
|---|---|---|---|
| 1. Lint dan format | CI | `pint --test` pada seluruh `app/`, `database/`, `routes/`, `tests/` (saat ini banyak file belum sesuai, jadi perlu diformat dulu dalam commit tersendiri) | Ada file tidak sesuai format |
| 2. Test SQLite | CI | `php artisan test` (baseline 65 test) | Ada test gagal |
| 3. Test PostgreSQL | CI | Job terpisah dengan service PostgreSQL 15. Jalankan `migrate:fresh --seed` lalu `php artisan test` (P1-V / N-10) | Perbedaan perilaku SQLite vs PostgreSQL |
| 4. Build image | CI | Build image `fpm_app`, beri tag commit SHA dan versi. Tidak ada `.env` di image (sesuai `.dockerignore`). | Build gagal |
| 5. Scan dependensi | CI | `composer audit` dan `npm audit` (opsional, dicatat sebagai peringatan dulu) | — (peringatan) |
| 6. Deploy staging | Server staging | Pull image, jalankan migration, seed, smoke test (bagian 8) | Smoke test gagal |
| 7. Persetujuan produksi | Manual | Penanggung jawab project menyetujui setelah staging lulus dan jendela deploy ditentukan (OP-6, OP-7) | — |
| 8. Backup pra-deploy | Server produksi | `pg_dump` ke lokasi terpisah (K-14). Dicatat. | Backup gagal → deploy dibatalkan |
| 9. Deploy produksi | Server produksi | Lihat 11.3 | Lihat 11.4 |

Catatan tahap 1 (K-22): pint saat ini gagal di banyak file lama. Perbaikan format dilakukan di **commit tersendiri, sebelum R-MVP-1**, tanpa perubahan logika. Commit ini hanya boleh berisi hasil `pint` (tidak ada perubahan logika, tidak ada perubahan test). Setelah commit format lulus `pint --test`, tahap 1 pipeline menjadi gate yang wajib.

Verifikasi commit format: `php artisan test` harus tetap 65 test lulus dan hasil test tidak berubah. Jika ada perbedaan, commit dibatalkan dan penyebabnya dicari.

### 11.3 Urutan deploy produksi (sekali per rilis)

```
1. Backup pra-deploy (tahap 8) dan catat nama file backup.
2. Pull image baru (tag SHA). Image lama tetap ada untuk rollback.
3. Matikan cms_worker agar job tidak berjalan di tengah migration.
4. Jalankan one-off container dari image baru:
     php artisan migrate --force
     php artisan db:seed --force        (hanya seeder idempoten MVP)
5. Jalankan container cms_app baru dan tunggu healthcheck.
6. Nyalakan kembali cms_worker.
7. Smoke test (bagian 8).
```

Langkah 4 dijalankan sebagai one-off container, bukan lewat entrypoint setiap boot. Entrypoint tetap menjalankan cache dan asset sync, tetapi migrate dan seed dipindahkan keluar. Untuk MVP, perubahan ini diperlukan agar seeder dan migration tidak berjalan tiap restart container (mendukung MVP-0.2 dan MVP-1.1).

**Perubahan entrypoint (bagian dari R-MVP-1, K-21):**
- Hapus `php artisan migrate --force` dan `php artisan db:seed --force` dari `docker/entrypoint.sh`.
- Entrypoint tetap menjalankan cache, asset sync, pembersihan sentinel (`rm -f /tmp/app_healthy`), dan `touch` sentinel setelah siap.
- Sebelum R-MVP-1, entrypoint lama masih berjalan. Urutan aman: deploy image yang sudah tanpa migrate di entrypoint, lalu jalankan one-off migrate dan seed. Tidak boleh ada kondisi di mana aplikasi baru berjalan dengan skema lama.
- Di lingkungan lokal, perintah `php artisan migrate` dan `php artisan db:seed` dijalankan manual.

Command one-off (contoh):
```
docker compose run --rm cms_app php artisan migrate --force
docker compose run --rm cms_app php artisan db:seed --force
```
Catatan: `docker compose run` memakai entrypoint image. Karena entrypoint hanya menjalankan bootstrap jika argumen pertama `php-fpm`, perintah ini langsung dieksekusi tanpa bootstrap penuh. Perilaku ini perlu diuji di staging sebelum R-MVP-1.

### 11.4 Rollback

| Kondisi | Tindakan | Waktu target |
|---|---|---|
| Migration gagal di langkah 4 | Hentikan deploy. Container lama tetap jalan karena cms_app belum diganti. Nyalakan kembali cms_worker. | < 5 menit |
| cms_app baru gagal healthcheck | Kembali ke image sebelumnya (tag SHA lama). Migration **tidak** di-rollback otomatis kecuali migration itu destruktif. | < 15 menit |
| Migration sudah berjalan lalu aplikasi bermasalah | Kembali ke image lama. Bila skema tidak kompatibel dengan image lama, restore dari backup langkah 1 dengan persetujuan penanggung jawab project. | < 60 menit (batas K-13) |
| Data rusak | Restore dari backup pra-deploy ke database kosong dulu, verifikasi, baru ganti. | < 60 menit |

Aturan migration untuk memudahkan rollback:
- Migration MVP bersifat **additive** (menambah kolom, menambah tabel). Kolom lama tidak dihapus dalam rilis yang sama.
- Penghapusan kolom legacy (P1-Q) dilakukan di rilis terpisah, setelah rilis MVP stabil.
- Migration data (MVP-0.2, MVP-2.1) ditulis idempoten dan tidak bergantung pada urutan.

### 11.5 Pengelolaan secret dan konfigurasi

- `.env.production` tidak masuk git dan tidak masuk image (sudah dijaga `.dockerignore`).
- Secret disimpan di server dan dimuat lewat `env_file`. Daftar variabel yang wajib diisi dicatat di dokumen operasi.
- `APP_KEY` berbeda antara staging dan produksi.
- Kredensial lama di repo (`admin@123456`, `CMS_API_SECRET_WEB`, `WEB_FRONTEND_WEBHOOK_SECRET` yang ada di `.env.production` yang tercatat) harus diganti sebelum deploy MVP. Ini bagian OP-1 dan OP-5. Kredensial yang sudah masuk riwayat git dianggap bocor.
- Variabel di `docker-compose.yml` (seperti `DB_HOST`, `APP_ENV=production`) tidak boleh diubah tanpa tiket di catatan rilis.

### 11.6 Kontrol akses pipeline

- Branch `main` dilindungi: merge hanya setelah tahap 1 sampai 3 lulus dan satu peninjau menyetujui.
- Deploy produksi dijalankan manual oleh penanggung jawab project. Tidak ada deploy otomatis ke produksi di MVP.
- Log setiap deploy (tanggal, SHA image, nama backup, hasil smoke test) dicatat di `CHANGELOG.md` atau buku operasional.
- Registry image privat `172.17.12.17:5051` (sudah dipakai di `docker-compose.yml`) dengan akses terbatas.

### 11.7 Pemisahan tahap MVP ke rilis

Agar risiko tiap deploy kecil, MVP dikirim dalam rilis bertahap, bukan sekali besar:

| Rilis | Isi | Alasan |
|---|---|---|
| R-MVP-1 | Tahap 0 (MVP-0.1, 0.2, 0.3) **dan pemindahan migrate/seed dari entrypoint (K-21)** | Kritis, perubahan data dan akses. Perlu backup dan verifikasi paling ketat. Pemindahan entrypoint dilakukan di rilis ini karena MVP-0.2 bergantung pada seed yang tidak lagi berjalan tiap boot. |
| R-MVP-2 | Tahap 1 (MVP-1.1, 1.2, 1.3) | Perilaku seeder, error API, forced change. Ada perubahan UX yang perlu diinformasikan ke staf. |
| R-MVP-3 | Tahap 2 (MVP-2.1 sampai 2.4) | Hak akses dan promosi. Perlu komunikasi ke Bagian IT dan Marketing. |
| R-MVP-4 | Tahap 3 (MVP-3.x) | Perbaikan kecil. |

Setiap rilis mengikuti urutan 11.2 dan 11.3 secara penuh. Jendela deploy untuk tiap rilis dikonfirmasi lewat OP-6 dan OP-7.

Catatan: forced change (MVP-1.3) berdampak langsung ke staf karena login pertama memaksa ganti password. Staf perlu diberi tahu sebelum R-MVP-2.

### 11.8 Hal yang sengaja belum dibuat

- Deploy otomatis ke produksi (menunggu keputusan skala dan tim).
- Blue-green atau rolling deployment (multi-replika, K-13 dan N-15).
- Scanner keamanan image yang wajib lulus (dimulai sebagai peringatan).

---

## 12. Urutan Rilis Final

```
Prasyarat operasional (bagian 4): OP-1 s/d OP-7
Commit format (K-22): pint, tanpa perubahan logika  → gate tahap 1 aktif
R-MVP-1: Tahap 0 + pemindahan migrate/seed (K-21)
R-MVP-2: Tahap 1
R-MVP-3: Tahap 2
R-MVP-4: Tahap 3
Next Phase: N-1 s/d N-16
```

## 13. Status

- Kode MVP-2.5: **selesai**.
- Migration `2026_10_20_000005_grant_marketing_reorder_permissions.php`: **selesai**.
- Test: **239 lulus (737 assertions)**.
- `pint --test`: **lulus**.
- Commit MVP-2.5: `b7cdb3a`.
- Validasi terakhir: **239 test lulus (737 assertions)**, `pint --test` lulus, dan smoke test Docker berhasil.
- Dokumen ini versi 6.
