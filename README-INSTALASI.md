# Cara memasang source ini

1. Buat proyek Laravel seperti biasa (`laravel new ... --database=mysql --phpunit --no-node --no-boost`),
   lalu `composer require livewire/livewire phpoffice/phpspreadsheet`, `npm install --ignore-scripts`, `npm run build`.
2. HAPUS migration hasil `make:migration` yang sudah kamu buat sebelumnya (create_stocks_table / add_cover_image_path...)
   dan factory/seeder lama, karena zip ini membawa versinya sendiri.
3. Ekstrak zip ini ke folder proyek, TIMPA file yang sudah ada.
4. Pastikan `.env` berisi DB_DATABASE=pabwe_p7_stock, DB_PASSWORD, FORCE_HTTPS=false, OPENAI_API_KEY=, OPENAI_MODEL=gpt-4o-mini
5. Tambahkan script di `composer.json` bagian "scripts":
       "test": [ "@php artisan config:clear --ansi", "@php artisan test" ]
6. `php artisan storage:link` lalu `php artisan migrate:fresh --seed` lalu `php artisan serve`
7. Test: aktifkan `extension=pdo_sqlite` dan `extension=sqlite3` di php.ini, lalu `composer test`.
   Coverage butuh Xdebug atau PCOV.
