# FTS Training Venue AI

Situs venue pelatihan interaktif dengan **AI Coordinator**. Tamu (tim HR, trainer, sekolah, perusahaan) menjelajahi venue sesi demi sesi seperti mengikuti agenda satu hari pelatihan: sambutan, ruang pelatihan, fasilitas, informasi venue, permintaan booking, dan tim venue, sambil mengobrol dengan coordinator AI. Coordinator menjawab dari data venue yang terkontrol, mengecek ketersediaan ruangan dan tarif harian, membuat permintaan booking, dan menyerahkan percakapan ke staf bila perlu. Staf mengelola semuanya lewat panel admin.

Satu aplikasi bisa melayani beberapa venue. Setiap venue punya halaman sendiri di `/{venueSlug}`.

## Fitur

### Sisi tamu
- **Agenda sebagai navigasi**: setiap bagian adalah satu sesi (01 sambutan, 02 ruang pelatihan, 03 fasilitas & layanan, 04 informasi venue, 05 permintaan booking, 06 tim venue). Menu berupa strip agenda di bawah header, header menampilkan penghitung sesi, dan perpindahan halaman ditandai layar proyektor yang turun dan naik. Di ponsel, strip agenda bisa digeser ke samping.
- **Ruang pelatihan**: tiap ruangan punya tipe (ruang kelas, lab workshop, ruang rapat, aula, studio kreatif), luas, lokasi lantai, kapasitas **per penataan kursi** (theatre, classroom, U-shape, boardroom, banquet, cluster), perlengkapan, opsi katering, dan tarif per hari.
- **Hari penuh**: satu program adalah rangkaian hari; hari pertama dan terakhir sama-sama dihitung, dan acara satu hari memakai hari pertama yang sama dengan hari terakhir. Permintaan online sampai 30 hari.
- **Diskon program**: diskon multi-hari (mulai 3 hari) dan intensif/bootcamp (mulai 5 hari) per venue, diterapkan di satu tempat sehingga wizard, AI Coordinator, dan total booking selalu sama. Katering (makan siang + coffee break) dihitung per peserta per hari.
- **Tiga bahasa**: Indonesia (`id`), Inggris (`en`), dan Jepang (`ja`), dipilih lewat parameter `?lang=`.
- **Chat dengan AI Coordinator**
  - Tahu sesi dan ruangan yang sedang dilihat tamu (juga form booking yang sedang diisi), jadi bisa menjawab "ruangan ini".
  - Menjawab dalam bahasa yang ditulis tamu.
  - Menampilkan kartu ruangan (luas, kapasitas, total dengan diskon) langsung di dalam chat.
- **Permintaan booking**: wizard lima langkah (tanggal, peserta, ruangan, data kontak, ringkasan) dengan kalender dalam bahasa tamu. Hari yang penuh dicoret dan rentang tidak bisa melewati hari yang penuh (data dari `GET /{venueSlug}/reservation/availability`). Ruangan yang terlalu kecil untuk jumlah peserta dinonaktifkan; penataan kursi menampilkan kapasitasnya.

### Desain
Tema "Campus Programme": hijau papan tulis, kuning stabilo, oranye spidol, dan lembar kertas berpola titik. Font Bricolage Grotesque, DM Sans, dan DM Mono. Foto ruang pelatihan dari Unsplash.

### Sisi admin (`/admin`)
- Dashboard: permintaan booking yang menunggu lebih dari 24 jam, handover terbuka, program yang dimulai 7 hari ke depan, dan utilisasi ruangan 30 hari ke depan.
- CRUD **ruangan** (tipe, kapasitas per penataan, katering, tarif, perlengkapan, foto) dan **ketersediaan** per ruangan: buka rentang tanggal, atur jumlah ruangan dan harga per hari lewat kalender bulanan. Jumlah ruangan tidak bisa diturunkan di bawah yang sudah dibooking.
- CRUD **knowledge items**: basis pengetahuan yang menjadi sumber jawaban coordinator (aturan venue, fasilitas, katering, transportasi, FAQ).
- **Pengaturan venue** (khusus `owner`): kontak, jam sesi, bahasa dan zona waktu, diskon multi-hari dan intensif, serta status halaman publik.
- Daftar **booking** dan ubah statusnya: `pending` → `confirmed` atau `cancelled`, `confirmed` → `cancelled`. `cancelled` bersifat final dan mengembalikan ruangan ke inventori.
- **Handover**: percakapan yang diserahkan coordinator ke staf. Staf bisa membalas langsung ke tamu dan menandainya selesai.
- Peran pengguna per venue: `owner` dan `staff`.

### Notifikasi email
- **Ke staf**: permintaan booking baru dan handover baru.
- **Ke penyelenggara** yang memberi alamat email: tanda terima, lalu pemberitahuan saat booking dikonfirmasi atau dibatalkan, dalam bahasa penyelenggara. Yang memilih WhatsApp atau telepon dihubungi langsung oleh staf.
- Email dikirim lewat queue, jadi worker harus jalan (`composer dev` sudah menyertakannya; di produksi jalankan `php artisan queue:work`). Atur `MAIL_*` di `.env`. Bawaannya `MAIL_MAILER=log`.

## Cara kerja AI Coordinator

Kode ada di [app/Services/Coordinator/](app/Services/Coordinator).

- **[CoordinatorService](app/Services/Coordinator/CoordinatorService.php)** menjalankan satu giliran percakapan lewat endpoint chat yang kompatibel dengan OpenAI (LM Studio atau Ollama) dan loop tool-calling (maksimal 6 putaran). Seluruh giliran dibatasi 85 detik supaya muat dalam batas 100 detik Cloudflare.
- **[VenueCoordinatorTools](app/Services/Coordinator/VenueCoordinatorTools.php)** berisi alat yang bisa dipanggil model. Semua fakta venue, ruangan, harga, dan ketersediaan harus lewat sini.

  | Tool | Fungsi |
  |---|---|
  | `search_knowledge` | Mencari di knowledge base venue |
  | `search_rooms` | Mencari ruangan sesuai tanggal, jumlah peserta, penataan, dan tipe, lengkap dengan ketersediaan dan total harga setelah diskon |
  | `get_room_detail` | Detail satu ruangan, termasuk kapasitas per penataan dan diskon program |
  | `check_availability` | Cek ketersediaan dan total harga untuk tanggal tertentu, dengan opsi katering |
  | `create_booking_request` | Membuat permintaan booking |
  | `request_human_handover` | Menyerahkan percakapan ke staf |

- **[ContentGuard](app/Services/Coordinator/ContentGuard.php)** adalah pengaman deterministik: pesan kasar, seksual, atau ilegal (Indonesia, Inggris, Jepang) dijawab dengan balasan baku tanpa sampai ke model, dan balasan model yang memuat kata-kata tersebut diganti.
- **Harga tanpa sumber ditolak**: balasan dengan angka harga yang tidak berasal dari tool akan ditantang satu kali.
- **Handover**: `special_request`, `complaint`, `group_booking`, `negotiated_rate`, `unusual_cancellation`, `payment_issue`, `low_confidence`.

## Teknologi

- PHP 8.3+ (dikembangkan di 8.4), Laravel 13
- SQLite sebagai database bawaan. Session, cache, dan queue memakai driver `database`
- Vite 8 dan Tailwind CSS 4, JavaScript vanilla di `resources/js/`
- LLM lewat HTTP (endpoint kompatibel OpenAI)
- PHPUnit 12 untuk tes, Laravel Pint untuk format kode

## Memulai

```bash
composer setup
php artisan db:seed      # data demo (hanya di environment local dan testing)
composer dev
```

Seeder membuat venue demo **FTS Training Venue AI** (slug `fts-training-venue-ai`) di Kuningan, Jakarta Selatan: lima tipe ruangan (Classroom Studio, Workshop Lab, Executive Boardroom, Grand Hall, Creative Studio), inventori 180 hari, diskon multi-hari 10% dan intensif 20%, serta knowledge base (jam sesi, tarif program, pembatalan, aturan, perlengkapan, katering, penginapan, transportasi, FAQ).

| Halaman | URL |
|---|---|
| Halaman pembuka | `http://localhost:8000/` |
| Venue demo | `http://localhost:8000/fts-training-venue-ai` |
| Admin | `http://localhost:8000/admin` |

Akun admin demo (hanya lokal):

```
email    : owner@ftsvenue.test
password : password
```

### Konfigurasi LLM

```dotenv
LOCAL_LLM_BASE_URL=   # mis. http://127.0.0.1:1234/v1 (LM Studio) atau http://127.0.0.1:11434/v1 (Ollama)
LOCAL_LLM_API_KEY=
LOCAL_LLM_MODEL=      # model dengan function calling
```

Tanpa konfigurasi ini, halaman venue tetap jalan tetapi chat coordinator tidak bisa menjawab. Uji dari terminal:

```bash
php artisan coordinator:chat fts-training-venue-ai --locale=id
```

## Endpoint utama

| Method | Path | Keterangan |
|---|---|---|
| GET | `/{venueSlug}` | Sambutan (sesi 01) |
| GET | `/{venueSlug}/rooms`, `/rooms/{roomSlug}` | Direktori dan detail ruangan (02) |
| GET | `/{venueSlug}/facilities`, `/facilities/{id}` | Fasilitas & layanan (03) |
| GET | `/{venueSlug}/info`, `/reservation`, `/team` | Informasi venue (04), permintaan booking (05), tim venue (06) |
| GET | `/{venueSlug}/reservation/availability` | Hari yang masih punya ruangan kosong |
| POST | `/{venueSlug}/reservation/quote` | Hitung harga, termasuk katering dan diskon program |
| POST | `/{venueSlug}/reservation` | Kirim permintaan booking |
| POST | `/{venueSlug}/coordinator/start`, `/message` | Mulai percakapan, kirim pesan |
| GET | `/{venueSlug}/coordinator/history` | Riwayat percakapan |

## Struktur proyek

```
app/
  Console/Commands/     coordinator:chat
  Http/Controllers/     halaman tamu, chat, reservasi, dan Admin/
  Models/               Venue, Room, RoomInventory, Booking, Conversation, HandoverRequest, ...
  Services/Coordinator/ CoordinatorService, VenueCoordinatorTools, ContentGuard
  Services/Reservation/ ReservationService, ReservationHandover
database/
  migrations/           skema (venue, ruangan, inventori, knowledge, percakapan, booking, handover)
  seeders/              DemoVenueSeeder
resources/
  js/                   stage, narrator, coordinator, reservation, date-picker, sound
  views/                halaman tamu (venue/), admin (admin/), komponen
tests/                  Feature/ dan Unit/
fts_training_venue_ai_documentation/   spesifikasi produk
```

## Pengujian dan gaya kode

```bash
composer test
vendor/bin/pint --dirty
```

GitHub Actions (`.github/workflows/ci.yml`) menjalankan Pint dan seluruh tes di setiap push ke `master` dan pull request.

## Deployment

- Jalankan `npm run build` dan `php artisan migrate --force`; set `APP_ENV=production` dan `APP_DEBUG=false`.
- Seeder demo tidak jalan di produksi. Buat akun owner dan venue Anda sendiri, lalu isi `multiday_discount_percent` dan `intensive_discount_percent` bila ingin memberi tarif program.
- Pastikan server aplikasi bisa menjangkau `LOCAL_LLM_BASE_URL`, jalankan queue worker, dan isi `MAIL_*` dengan SMTP asli.
- Karena ada batas 100 detik dari Cloudflare, jangan menaikkan batas waktu respons coordinator melebihi 85 detik.

## Lisensi

Dibangun di atas [Laravel](https://laravel.com), berlisensi [MIT](https://opensource.org/licenses/MIT).
