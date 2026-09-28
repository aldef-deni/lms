<p align="center">
  <a href="https://lms.aldeftech.com" target="_blank">
    <img src="public/assets/logo/aldef-landscape02.png" width="520" alt="Logo Aldef Tech">
  </a>
</p>

<p align="center">
  <strong>Belajar, Berkembang, dan Tersertifikasi bersama Aldef Tech.</strong>
</p>

<p align="center">
  <a href="https://lms.aldeftech.com"><img src="https://img.shields.io/badge/Website-lms.aldeftech.com-0ea5e9?style=flat-square" alt="Website ALDEF LMS"></a>
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.2 atau lebih baru">
  <img src="https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4">
</p>

## Tentang ALDEF LMS

**ALDEF LMS** adalah platform learning management system milik **Aldef Tech** untuk menyelenggarakan kelas online secara terstruktur — mulai dari katalog kursus, materi pembelajaran, kuis dan ujian, tugas, hingga sertifikat digital yang dapat diverifikasi publik.

Platform ini dirancang untuk akademi, lembaga pelatihan, dan organisasi yang membutuhkan alur belajar yang rapi, pemantauan progres peserta, serta pengelolaan konten yang aman berdasarkan peran pengguna.

### Fitur utama

- **Akademi publik** — katalog kursus yang dapat dicari, outline kursus, dan preview lesson.
- **Autentikasi & profil** — login, registrasi, reset password, avatar profil, penonaktifan akun, dan rate limiting. Portal login manajemen dan portal belajar dipisahkan.
- **Role-based access** — Super Admin, Admin LMS, Instructor, Student, dan Organization. Registrasi selalu membuat akun Student; instruktur hanya dapat mengelola konten miliknya.
- **Manajemen kursus** — kategori/subkategori, metadata dan thumbnail, chapter, lesson berurutan, lampiran privat, video, embed YouTube/Vimeo, dokumen, tautan, dan live meeting.
- **Enrollment & progres** — pendaftaran mandiri maupun oleh admin, progres lesson/chapter, lanjutkan belajar, dan penyelesaian kursus.
- **Asesmen** — bank soal, kuis dan ujian per kursus/lesson, batas waktu yang divalidasi server, batas percobaan, riwayat nilai, pilihan ganda, benar/salah, dan isian singkat.
- **Tugas** — unggah tugas, tenggat waktu, penilaian dan feedback mentor. Tugas wajib memerlukan nilai minimal 70 untuk sertifikasi.
- **Sertifikat digital** — terbit otomatis setelah syarat terpenuhi, nomor unik, verifikasi publik, QR code lokal, unduhan PDF landscape, template yang dapat dikustomisasi, dan pencabutan sertifikat.
- **Komunitas & laporan** — pengumuman, diskusi kursus, laporan sesuai peran, pengaturan sistem, dan log aktivitas administratif.
- **Akun demo terisolasi** — workspace demo yang di-reset otomatis setiap hari tanpa menyentuh data produksi.

## Teknologi

| Area | Teknologi |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12 |
| Frontend | Blade, Tailwind CSS 4 |
| Database | MySQL |
| Otorisasi | Spatie Laravel Permission |
| PDF & QR | Laravel DomPDF, Bacon QR Code |
| Asset bundler | Vite 7 |
| Testing | PHPUnit 11 |

## Instalasi & Deployment

Arahkan web server (aaPanel) **hanya ke direktori `public/`**. Dibutuhkan PHP 8.2+ dan Node 20.19+ (atau 22.12+), beserta ekstensi standar Laravel serta DOM/XML, mbstring, dan GD untuk PDF dan gambar.

```sh
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize
```

Pertahankan `.env` dan `APP_KEY` yang sudah ada. Atur `APP_NAME="ALDEF LMS"`, `APP_URL=https://lms.aldeftech.com`, `APP_ENV=production`, `APP_DEBUG=false`, kredensial MySQL, dan cookie sesi HTTPS. Gunakan mail transport sungguhan agar email reset password terkirim. Pastikan `storage/` dan `bootstrap/cache/` dapat ditulis oleh PHP, dan batasi akses ke `.env`.

### Akun Super Admin

Isi variabel berikut sebelum menjalankan seeder:

```dotenv
LMS_ADMIN_EMAIL=admin@aldeftech.com
LMS_ADMIN_PASSWORD=<password unik minimal 12 karakter>
```

Super Admin hanya dibuat jika password tersebut sudah diatur. Jalankan `php artisan config:clear` setelah mengubahnya, lalu `php artisan db:seed --force`.

### Akun demo & reset otomatis

```sh
php artisan db:seed --class=Database\\Seeders\\DemoAccountSeeder --force
```

| Role | Username | Password |
| --- | --- | --- |
| Admin LMS | `admindemo` | `admindemo` |
| Instructor | `instructordemo` | `instructordemo` |
| Student | `studentdemo` | `studentdemo` |

Seluruh data milik akun demo dibersihkan dan diisi ulang oleh `php artisan demo:reset`, yang dijadwalkan sekali sehari oleh Laravel scheduler. Tambahkan cron berikut di aaPanel:

```cron
* * * * * cd /www/wwwroot/lms.aldeftech.com && php artisan schedule:run >> /dev/null 2>&1
```

## Alur Penggunaan

1. Buat kategori dan kursus, lalu tetapkan instruktur dan template sertifikat.
2. Tambahkan chapter dan lesson berurutan, kemudian kuis/soal dan tugas.
3. Publikasikan kursus. Student dapat mendaftar sendiri atau didaftarkan oleh admin.
4. Nilai tugas di **Submission review** dan pantau progres di **Reports & analytics**.
5. Sertifikat terbit otomatis. Verifikasi publik tersedia di `/verify` atau `/verify/{token}`.

## Kustomisasi

<p align="center">
  <strong>JIKA BERMINAT UNTUK KUSTOMISASI BISA MENGHUBUNGI DENI AFRIZAL</strong>
</p>

<p align="center">
  <a href="https://wa.me/628128968609" target="_blank">
    <img src="https://img.shields.io/badge/WhatsApp-Hubungi_Deni_Afrizal-25D366?style=for-the-badge&logo=whatsapp&logoColor=white" alt="Hubungi Deni Afrizal melalui WhatsApp">
  </a>
</p>

## Kontak

Butuh platform pembelajaran, sistem, aplikasi, SaaS, integrasi, atau otomasi AI? Kunjungi [aldeftech.com/contact](https://aldeftech.com/contact) untuk mendiskusikan kebutuhan bisnis Anda bersama Aldef Tech.

---

<p align="center">
  © Aldef Tech. Seluruh hak cipta dilindungi.
</p>
