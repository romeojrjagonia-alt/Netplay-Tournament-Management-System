<<<<<<< HEAD
web: php artisan migrate --force; mkdir -p "${PUBLIC_STORAGE_PATH:-storage/app/public}" && php artisan storage:link --force; php artisan queue:work --tries=3 --timeout=90 --sleep=3 --max-time=3600 & PHP_CLI_SERVER_WORKERS=4 exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
=======
release: php artisan migrate --force
web: mkdir -p "${PUBLIC_STORAGE_PATH:-storage/app/public}" && php artisan storage:link --force; (while true; do php artisan queue:work --tries=3 --timeout=90 --sleep=3 --max-time=3600; sleep 1; done) & PHP_CLI_SERVER_WORKERS=4 exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
>>>>>>> b3fba7491ef8b6b723bbe1e5b8d215f1867431ae
