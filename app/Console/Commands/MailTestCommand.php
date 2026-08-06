<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Prueba la configuración SMTP del .env (útil en el VPS tras configurar Hostinger).
 *
 * Uso: php artisan mail:test correo@ejemplo.com
 */
class MailTestCommand extends Command
{
    protected $signature = 'mail:test
                            {email? : Destinatario de la prueba}
                            {--from= : Remitente override (opcional)}';

    protected $description = 'Envía un correo de prueba para validar MAIL_* / SMTP';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Correo destino');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email inválido.');

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $host = (string) config('mail.mailers.smtp.host');
        $from = $this->option('from') ?: config('mail.from.address');

        $this->info("Mailer: {$mailer}");
        $this->info("From: {$from}");
        if ($mailer === 'smtp') {
            $this->info('SMTP host: ' . ($host ?: '(vacío)'));
            $this->info('SMTP port: ' . config('mail.mailers.smtp.port'));
            $this->info('SMTP scheme: ' . (config('mail.mailers.smtp.scheme') ?: '(null)'));
        }

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("MAIL_MAILER={$mailer}: el mensaje se registrará en log, no saldrá por Internet.");
            $this->warn('En el VPS configure SMTP (ver .env.example) y ejecute: php artisan config:cache');
        }

        $appName = config('app.name');
        $body = "Prueba SMTP de {$appName}\n\n"
            . 'Enviado: ' . now()->toDateTimeString() . "\n"
            . "Mailer: {$mailer}\n"
            . "Si recibes este correo, la configuración es correcta.\n";

        try {
            Mail::raw($body, function ($message) use ($email, $appName, $from) {
                $message->to($email)
                    ->subject("Prueba de correo - {$appName}");
                if ($from) {
                    $message->from($from, config('mail.from.name'));
                }
            });
        } catch (Throwable $e) {
            $this->error('Fallo al enviar: ' . $e->getMessage());
            $this->line('Revisa MAIL_HOST/PORT/SCHEME/USERNAME/PASSWORD y firewall del VPS.');

            return self::FAILURE;
        }

        $this->info("Correo de prueba despachado a {$email}.");
        if ($mailer === 'log') {
            $this->line('Revisa storage/logs/laravel.log (MAIL_MAILER=log).');
        } else {
            $this->line('Revisa la bandeja (y spam) del destinatario.');
        }

        return self::SUCCESS;
    }
}
