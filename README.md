📖 CMS Starter Guide This guide provides the minimal, essential steps to configure and boot up your CMS application for the first time.
1. Database Connection Symfony 8 manages configuration via environment variables. Open your .env or .env.local file and update the DATABASE_URL string.ConfigurationFor MySQL / MariaDB:envDATABASE_URL="mysql://USER:PASSWORD@127.0.0.1:3306/DB_NAME?serverVersion=8.0.32&charset=utf8mb4"
For PostgreSQL:envDATABASE_URL="postgresql://USER:PASSWORD@127.0.0.1:5432/DB_NAME?serverVersion=16&charset=utf8"
Initialize DatabaseRun this command in your terminal to create the database automatically:bashphp bin/console doctrine:database:create

2. Generate VAPID Keys (Web Push)Web Push notifications require a secure public/private cryptographic key pair.Step 1: Generate the KeysIf you have Node.js installed, run:bashnpx web-push generate-vapid-keys
Step 2: Save to .envCopy the generated keys into your configuration file:envVAPID_PUBLIC_KEY="YOUR_PUBLIC_KEY"
VAPID_PRIVATE_KEY="YOUR_PRIVATE_KEY"
VAPID_SUBJECT="mailto:your-email@example.com"

# 3. Install production dependencies
composer install --no-dev --optimize-autoloader

# 4. Apply database schema changes
php bin/console doctrine:migrations:migrate --no-interaction

# 5. Clear and warm up application cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# 6. Compile asset files (Styles/Scripts)
php bin/console asset-map:compile
