# Purbalingga Pay Backend

Backend Laravel API untuk `purbalingga-pay-frontend`.

## Fitur

- Login berbasis bearer token
- Dashboard ringkasan saldo, kartu, transaksi, dan voucher
- List kartu, block/unlock kartu
- List transaksi dan create transaksi top up / payment
- List voucher dan redeem voucher
- Seed demo data yang langsung cocok dengan frontend saat ini

## Format QR payment

Frontend scanner membaca payload QR payment dengan tiga field:

- `card_id`
- `nominal`
- `merchant_name`

Format payload yang didukung:

- JSON: `{"card_id":"PBG-001","nominal":18500,"merchant_name":"Warung Kopi"}`
- Key-value: `card_id=PBG-001&nominal=18500&merchant_name=Warung Kopi`
- Compact test format: `PBG-001/18500/WARUNG KOPI`

## Setup cepat

Pastikan MySQL lokal sudah jalan. Cara paling cepat adalah pakai Docker Compose:

```bash
docker compose up -d mysql
```

Lalu jalankan backend:

```bash
composer install
php artisan migrate
php artisan db:seed
php artisan serve
```

## Demo login

- Login dilakukan lewat Purbalingga SSO.
- Akun demo SSO: `admin@purbalingga.id` / `Admin1234!`

## Endpoint utama

- `GET /api/health`
- `POST /api/auth/login`
- `POST /api/auth/sso-login`
- `POST /api/auth/logout`
- `GET /api/auth/me`
- `GET /api/dashboard`
- `GET /api/cards`
- `POST /api/cards/{card}/block`
- `POST /api/cards/{card}/unlock`
- `GET /api/transactions`
- `POST /api/transactions`
- `GET /api/vouchers`
- `POST /api/vouchers/{voucher}/redeem`

## CORS

Set `FRONTEND_URL` di `.env` supaya backend hanya menerima request dari origin frontend produksi.

## Deployment VPS

Kalau backend dan frontend kamu dipasang langsung di VPS `41.216.191.39`, pakai pola ini:

- Frontend publik: `http://41.216.191.39`
- Backend API: `http://41.216.191.39:8000`
- SSO service: `http://41.216.191.39:4000`

Env yang paling penting:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://41.216.191.39:8000
FRONTEND_URL=http://41.216.191.39
SSO_BASE_URL=http://41.216.191.39:4000
SESSION_DOMAIN=41.216.191.39
```

Sesudah deploy, jalankan minimal:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## MySQL lokal

Backend ini memang dirancang untuk MySQL. Kalau kamu tidak punya MySQL lokal, jalankan service dari `docker-compose.yml` di root backend.

Kalau port `3306` sudah dipakai aplikasi lain, ubah `DB_PORT` di `.env` dan sesuaikan port mapping saat menjalankan Docker Compose.

## Production checklist

- Set `APP_ENV=production`
- Set `APP_DEBUG=false`
- Set `APP_URL` ke domain backend publik
- Set `FRONTEND_URL` ke domain frontend publik
- Jalankan `php artisan migrate --force`
- Jalankan `php artisan db:seed --force` hanya jika masih butuh demo data
- Build frontend dengan `npm run build`

Lihat juga panduan root [`DEPLOYMENT.md`](/home/aizar/purbalingga-pay/DEPLOYMENT.md) untuk alur deploy frontend + backend.
