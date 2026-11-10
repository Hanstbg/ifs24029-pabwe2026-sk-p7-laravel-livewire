# ifs24025 - PABWE 2026 Praktikum 7 (Laravel 13 & Livewire)

Aplikasi Stok modern dengan arsitektur **Clean Architecture & Feature-First Approach** sesuai standar industri, dibangun menggunakan **Laravel 13**, **Livewire 3**, basis data **MySQL**, upload/export spreadsheet via **PhpOffice/PhpSpreadsheet**, integrasi kecerdasan buatan (**OpenAI AI Content Generation**), rate limiting (1000 per detik), proteksi kepemilikan middleware, force HTTPS, dan **100% Test Coverage**.

---


## 🚀 Fitur Utama
s
1. **Clean Architecture & Feature-First Approach**:
   - Modul diorganisir per f
   eature/domain (`App\Features\Auth`, `App\Features\Stock`, `App\Features\Admin`, `App\Features\Ai`).
   - Menerapkan Dependency Inversion Principle (DIP) dengan repository interface dan client contracts.
   - Pemisahan lapisan yang jelas antara Actions, Repositories, Services, DTOs, Policies, dan UI (Livewire).

2. **Autentikasi & Autorisasi Role-Based**:
   - **Login Fleksibel**: Pengguna dapat masuk menggunakan **Username** atau **Email**.
   - **Role `admin`**:
     - Mengakses halaman Manajemen Pengguna (`/admin/users`).
     - Melihat daftar seluruh pengguna beserta username, email, dan jumlah stok mereka.
     - Melihat detail stok pengguna lain dalam mode **Read-Only** (`/admin/users/{user}/stocks`).
     - Admin **tidak dapat** memodifikasi/menghapus stok pengguna lain.
   - **Role `member`**:
     - User yang mendaftar melalui form registrasi (dengan nama, username unik, email, password) otomatis memiliki role `member`.
     - Hanya dapat mengelola (CRUD), menyesuaikan quantity (+/−), mengekspor/mengimpor, serta generate AI untuk stok miliknya sendiri (`/stocks`).
   - **Middleware & Policy**:
     - `EnsureRole`: Membatasi rute khusus admin.
     - `EnsureStockOwnership`: Memastikan member tidak dapat mengakses atau memodifikasi stok milik orang lain.
     - `StockPolicy`: Memvalidasi otorisasi di tingkat model.

3. **Spreadsheet Excel Import & Export**:
   - Menggunakan library aman dari kerentanan: **`phpoffice/phpspreadsheet`**.
   - **Export**: Menghasilkan file `.xlsx` dengan styling profesional (header berwarna, border, text wrap, auto size).
   - **Import**: Mengunggah file `.xlsx`, `.xls`, `.csv` untuk bulk insert stok (kolom: No, Name, SKU, Description, Quantity, Unit, Price, Min Stock); status dihitung otomatis dari quantity vs min_stock.

4. **AI Stock Content Generator (OpenAI)**:
   - Dikonfigurasi secara dinamis melalui `.env`:
     - `OPENAI_ENDPOINT` (mendukung custom proxy atau Azure/Ollama kompatibel)
     - `OPENAI_KEY`
     - `OPENAI_MODEL`
   - Kemampuan generate multiple stok items secara otomatis berdasarkan prompt/permintaan pengguna (misal: "Buatkan daftar stok ATK untuk satu divisi").
   - Output diformat dan divalidasi ke dalam DTO `GeneratedStockItem`.
   - Pengguna dapat melihat preview hasil generate AI, memilih item yang diinginkan, dan menyimpannya langsung ke basis data.

5. **Rate Limiting & Keamanan**:
   - Skrip pembatas akses: **1000 request per detik** via `RateLimiter::for('1000-per-second', ...)`.
   - **Force HTTPS**: Dikonfigurasi melalui `FORCE_HTTPS=true` di `.env` atau otomatis aktif pada environment `production`.
   - Bebas dari kerentanan keamanan (`composer audit` clean 0 advisories).

6. **100% Test Coverage**:
   - Seluruh classes, methods, dan baris kode pada direktori `app/` memiliki **100% coverage**.
   - Unit test dan feature test komprehensif menguji seluruh alur autentikasi, model, repository, policy, middleware, service AI (mocked), dan upload/download spreadsheet.

---

## 📁 Struktur Direktori Feature-First

```
app/
├── Features/
│   ├── Admin/
│   │   ├── Livewire/
│   │   │   ├── AdminUserStockList.php
│   │   │   └── UserList.php
│   │   └── Services/
│   │       └── AdminUserService.php
│   ├── Ai/
│   │   ├── Contracts/
│   │   │   └── AiClientInterface.php
│   │   ├── DTO/
│   │   │   └── GeneratedStockItem.php
│   │   └── Services/
│   │       └── OpenAiStockGeneratorService.php
│   ├── Auth/
│   │   ├── Actions/
│   │   │   ├── LoginUserAction.php
│   │   │   └── RegisterUserAction.php
│   │   └── Livewire/
│   │       ├── Login.php
│   │       └── Register.php
│   └── Stock/
│       ├── Contracts/
│       │   └── StockRepositoryInterface.php
│       ├── Enums/
│       │   └── StockStatus.php
│       ├── Livewire/
│       │   └── StockList.php
│       ├── Models/
│       │   └── Stock.php
│       ├── Policies/
│       │   └── StockPolicy.php
│       ├── Repositories/
│       │   └── StockRepository.php
│       └── Services/
│           ├── StockExcelService.php
│           └── StockService.php
├── Http/
│   └── Middleware/
│       ├── EnsureRole.php
│       └── EnsureStockOwnership.php
├── Models/
│   └── User.php
└── Providers/
    └── AppServiceProvider.php
```


## 🛠️ Instalasi & Menjalankan Proyek

1. **Clone & Masuk ke Direktori Proyek**:
   ```bash
   cd ifs24025-pabwe2026-sk-p7-laravel-livewire
   ```

2. **Salin `.env` & Generate Key**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Migrasi & Seeding Data Awal**:
   Pastikan MySQL sudah berjalan dan database telah dibuat, kemudian jalankan:
   ```bash
   php artisan migrate:fresh --seed
   ```

   **Akun Bawaan Seeder**:
   - **Admin**:
     - Username: `admin` atau Email: `admin@example.com`
     - Password: `admin`
   - **Member**:
     - Username: `member` atau Email: `member@example.com`
     - Password: `member`

4. **Jalankan Aplikasi Web**:
   ```bash
   php artisan serve
   ```
   Akses aplikasi di browser melalui `http://localhost:8000`.

---

## 🧪 Pengujian & Verifikasi 100% Test Coverage

Cukup jalankan satu perintah sederhana berikut untuk menjalankan seluruh pengujian dan melihat kalkulasi coverage 100%:

```bash
composer test
```

Atau di terminal Windows:
```cmd
test
```
(atau `.\test.bat` / `npm test`)

Hasil verifikasi:
```text
Code Coverage Report:
  Summary:
   Classes: 100.00% (20/20)
   Methods: 100.00% (77/77)
   Lines:   100.00% (428/428)

Tests: 76, Assertions: 215, Status: PASSED (100%)
```

Audit keamanan dependencies:
```bash
composer audit
# Output: No security vulnerability advisories found.
```
