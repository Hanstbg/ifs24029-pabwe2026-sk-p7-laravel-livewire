# ifs24003 - PABWE 2026 Studi Kasus P7: Aplikasi Stock Barang

Aplikasi **inventaris stok barang** dengan arsitektur **Clean Architecture & Feature-First**, dibangun menggunakan **Laravel 13**, **Livewire 4**, **MySQL**, **PhpOffice/PhpSpreadsheet**, dan integrasi **OpenAI**. Pola mengikuti latihan Todolist; domain `Todo` diganti menjadi `Stock`.

## Fitur

| Peran | Kemampuan |
|---|---|
| **Guest** | Login (username/email) & register. Root `/` diarahkan ke login. |
| **Member** | Mengelola **stok miliknya sendiri**: daftar (cari nama/SKU/deskripsi, filter status, pagination), CRUD via modal, **adjust quantity (+/−)** dari list maupun detail, cover image, deskripsi Markdown (Toast UI), import/export Excel, generate item stok dengan AI, profil. |
| **Admin** | `/admin/users` (daftar user + jumlah barang) dan `/admin/users/{user}/stocks` (stok user, **read-only** + export). |

**Status otomatis.** Status tidak dipilih manual, tetapi dihitung dari `quantity` vs `min_stock` setiap kali barang dibuat, diubah, di-import, atau di-adjust:

- `quantity = 0` → `out_of_stock` (Habis, merah)
- `0 < quantity ≤ min_stock` → `low_stock` (Stok Menipis, kuning)
- `quantity > min_stock` → `available` (Tersedia, hijau)

Quantity tidak pernah bisa turun di bawah 0.

## Struktur (Feature-First)

```
app/Features/
├── Admin/   Livewire/{AdminUserStockList,UserList}.php, Services/AdminUserService.php
├── Ai/      Contracts/AiClientInterface.php, DTO/GeneratedStockItem.php, Services/OpenAiStockGeneratorService.php
├── Auth/    Actions/{LoginUserAction,RegisterUserAction}.php, Livewire/{Login,Register}.php
├── Profile/ Livewire/Profile.php, Services/ProfileService.php
└── Stock/   Contracts/StockRepositoryInterface.php, Enums/StockStatus.php,
             Livewire/{StockList,StockDetail}.php, Models/Stock.php, Policies/StockPolicy.php,
             Repositories/StockRepository.php, Services/{StockService,StockExcelService}.php
app/Http/Middleware/{EnsureRole,EnsureStockOwnership}.php   # alias: role, owns.stock
```

## Format Excel

Export dan import memakai kolom yang sama (baris 1 = header):

`No | Name | SKU | Description | Quantity | Unit | Price | Min Stock | Status | Created At`

Saat import, kolom `Status` diabaikan (dihitung ulang). Baris tanpa nama dilewati; baris dengan quantity tidak valid/negatif dilewati dan dilaporkan. Quantity kosong dianggap 0, unit kosong → `pcs`, min stock kosong → `5`.

## Instalasi

```bash
composer install
cp .env.example .env
php artisan key:generate
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS pabwe_p7_stock CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# sesuaikan DB_*, FORCE_HTTPS, OPENAI_ENDPOINT, OPENAI_KEY, OPENAI_MODEL di .env
php artisan migrate:fresh --seed
php artisan storage:link
npm install --ignore-scripts
npm run build
php artisan serve
```

Akun seeder: **admin / admin** dan **member / member** (member sudah punya 3 contoh barang: tersedia, menipis, habis).

## Pengujian

```bash
composer test     # PHPUnit + coverage (target 100% folder app/)
composer audit
```

Hasil: `OK (119 tests, 477 assertions)` tanpa PHPUnit notice, coverage **Classes/Methods/Lines 100%**. Mock AI memakai `createStub()`.

ID form login untuk selektor uji: `login-username`, `login-password`, `login-submit`.

## CI/CD

`Jenkinsfile`: composer install → `composer audit` (gagal jika ada kerentanan) → npm build → PHPUnit dengan gate coverage 100% → Trivy → zip tanpa `vendor/` & `node_modules/`.
