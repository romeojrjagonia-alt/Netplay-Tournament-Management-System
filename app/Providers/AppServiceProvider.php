<?php

namespace App\Providers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // For an uploaded file, ":max" in the "too large" message is shown as a
        // friendly size ("2 MB") instead of a raw number of kilobytes.
        Validator::replacer('max', function (string $message, string $attribute, string $rule, array $parameters, $validator): string {
            $limit = $parameters[0] ?? '';

            if ($validator->getValue($attribute) instanceof UploadedFile && is_numeric($limit)) {
                $limit = self::formatKilobytes((int) $limit);
            }

            return str_replace(':max', (string) $limit, $message);
        });
    }

    /**
     * 2048 becomes "2 MB", 1536 becomes "1.5 MB" and 500 stays "500 KB".
     */
    private static function formatKilobytes(int $kilobytes): string
    {
        if ($kilobytes < 1024) {
            return $kilobytes.' KB';
        }

        return rtrim(rtrim(number_format($kilobytes / 1024, 1, '.', ''), '0'), '.').' MB';
    }
}
