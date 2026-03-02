# S3 Smart Vault

Personal cloud storage built on AWS S3 with intelligent archiving, multi-user support, and file sharing. Built with Laravel and Tailwind CSS.

## What it does

- Upload files via drag & drop or file picker
- Per-user file isolation — each user only sees their own files
- Archive files to S3 Glacier (Freeze) to reduce storage costs up to 80%
- Restore (Thaw) Glacier files with real-time polling status updates
- Preview images, PDFs, and videos directly in the browser
- Generate shareable links with configurable expiration (1h to 7 days)
- Revoke active share links at any time
- Rename files
- Real-time search and sortable file table
- Per-user storage quota with visual progress bar

## Tech stack

- **Backend:** PHP ^8.2, Laravel 12
- **Frontend:** Blade, TailwindCSS, Vanilla JS
- **Database:** MySQL / MariaDB
- **Cloud:** AWS S3 Standard + Glacier (via AWS SDK / Flysystem)
- **Deployment:** GitHub Actions (AWS EC2, dynamic SSH)

## Quick install
```bash
git clone https://github.com/AldoLucchi/s3-smart-vault.git
cd s3-smart-vault
composer run setup
```

The setup script runs: `composer install`, copies `.env.example`, generates app key, runs migrations, installs JS deps, and builds assets.

## Required environment variables
```env
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=s3_smart_vault
DB_USERNAME=...
DB_PASSWORD=...
```

## After deployment
```bash
php artisan migrate --force
php artisan vault:import {user_id}   # Import existing S3 files into the database
php artisan app:sync-storage-classes # Sync S3 storage classes with the database
php artisan optimize
```

## Development
```bash
composer run dev
```

Launches: `php artisan serve`, `queue:listen`, `pail`, and `npm run dev` in parallel.

## Deployment

The `.github/workflows/deploy.yml` workflow opens/closes port 22 by dynamic IP and uses GitHub secrets for AWS and SSH credentials. No hardcoded secrets anywhere.

## Contributing

1. Fork
2. Create branch `feature/x`
3. Commit and run `./vendor/bin/pint` for style
4. Run `composer run test`
5. Open PR
