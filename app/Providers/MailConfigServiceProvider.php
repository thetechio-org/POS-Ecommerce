<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use App\Models\MailSetting;

class MailConfigServiceProvider extends ServiceProvider
{
    /** Cache key for the mail settings row; SettingController forgets it on save. */
    public const CACHE_KEY = 'mail_settings.active';

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $mail = $this->cachedMailSettings();

        if (! $mail) {
            return;
        }

        Config::set('mail.default', $mail['mail_mailer']);
        Config::set('mail.mailers.smtp.transport', $mail['mail_mailer']);
        Config::set('mail.mailers.smtp.host', $mail['mail_host']);
        Config::set('mail.mailers.smtp.port', $mail['mail_port']);
        Config::set('mail.mailers.smtp.username', $mail['mail_username']);
        Config::set('mail.mailers.smtp.password', $mail['mail_password']);
        Config::set('mail.mailers.smtp.encryption', $mail['mail_encryption']);
        Config::set('mail.from.address', $mail['mail_username']);
        Config::set('mail.from.name', $mail['sender_name'] ?? 'Laravel');
    }

    /**
     * Read the mail settings once and remember them.
     *
     * boot() runs on every single request, so reading this table directly cost two
     * queries per request — on a database shared with other applications. The cache
     * is cleared by SettingController when the settings are saved.
     */
    private function cachedMailSettings(): ?array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                if (! Schema::hasTable('mail_settings')) {
                    return null;
                }

                $mail = MailSetting::first();

                return $mail ? [
                    'mail_mailer'     => $mail->mail_mailer,
                    'mail_host'       => $mail->mail_host,
                    'mail_port'       => $mail->mail_port,
                    'mail_username'   => $mail->mail_username,
                    'mail_password'   => $mail->mail_password,
                    'mail_encryption' => $mail->mail_encryption,
                    'sender_name'     => $mail->sender_name,
                ] : null;
            });
        } catch (\Throwable $e) {
            // The table may not exist yet (before migrations) or the cache store may
            // be unreachable. Fall back to the .env mail config rather than failing.
            return null;
        }
    }


}
