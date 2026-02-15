# S3 Smart Vault

Cloud storage with intelligent archiving between S3 Standard and Glacier, built with Laravel 12 and Livewire/Volt. Allows secure upload, list, archive (freeze), restore (thaw), download, and delete of files using pre-signed URLs and per-user storage quotas.

## Tech stack

- Backend: PHP ^8.2, Laravel 12, Livewire 3, Volt 1
- Frontend: Vite, TailwindCSS, Blade
- Database: MySQL/MariaDB
- Cloud: AWS SDK S3 (Flysystem), S3 Standard + Glacier
- Cache: Redis (optional)
- Deployment: GitHub Actions (AWS EC2, dynamic SSH)

## Quick Install 

```bash
# Clone and set up
git clone https://github.com/AldoLucchi/s3-smart-vault.git
cd s3-smart-vault
composer run setup
```

The setup script runs: composer install, copies .env.example, generates key, runs migrations, installs JS deps, and builds assets .

## Required environment variables

```
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

## Development
```
# Start server, queue, logs, and HMR together  
composer run dev
```

composer run dev launches: php artisan serve, queue:listen, pail, and npm run dev in parallel .

## Deployment

The .github/workflows/deploy.yml workflow opens/closes port 22 by dynamic IP and uses GitHub secrets for AWS and SSH, with no hardcoded credentials.

## Contributing
1. Fork
2. Create branch feature/x
3. Commit and run ./vendor/bin/pint for style
4. Run composer run test
5. Open PR

## License
MIT (see LICENSE file).
If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
