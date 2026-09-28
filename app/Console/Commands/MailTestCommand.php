<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envoie un e-mail de test pour vérifier la configuration SMTP (utile depuis
 * l'extension Laravel de Plesk, sans accès SSH). Affiche le transport utilisé
 * et remonte l'erreur exacte en cas d'échec (auth, TLS, hôte injoignable…).
 */
class MailTestCommand extends Command
{
    protected $signature = 'vulcain:mail-test {email : Adresse destinataire du test}';

    protected $description = 'Envoie un e-mail de test et vérifie la configuration SMTP.';

    public function handle(): int
    {
        $to = mb_strtolower(trim((string) $this->argument('email')));

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error("Adresse invalide : {$to}");

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $host = (string) config("mail.mailers.{$mailer}.host");
        $port = config("mail.mailers.{$mailer}.port");
        $from = (string) config('mail.from.address');

        $this->line("Transport : <info>{$mailer}</info>".($host !== '' ? " ({$host}:{$port})" : ''));
        $this->line("Expéditeur : <info>{$from}</info>");
        $this->line("Destinataire : <info>{$to}</info>");

        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER=log : le message sera écrit dans les logs, pas envoyé. Configurez MAIL_MAILER=smtp pour un envoi réel.');
        }

        try {
            Mail::raw(
                "Ceci est un e-mail de test envoyé par Vulkain.\n\n".
                "Si vous le recevez, la configuration SMTP est opérationnelle.\n".
                'Envoyé le '.now()->format('d/m/Y à H:i').'.',
                function ($message) use ($to) {
                    $message->to($to)->subject('Vulkain — test de configuration e-mail');
                },
            );
        } catch (Throwable $e) {
            $this->error('Échec de l’envoi : '.$e->getMessage());
            $this->line('Vérifiez MAIL_HOST / MAIL_PORT / MAIL_USERNAME / MAIL_PASSWORD et le certificat TLS du serveur mail.');

            return self::FAILURE;
        }

        $this->info("E-mail de test remis au transport pour {$to}.");

        if ($mailer !== 'log') {
            $this->line('Vérifiez la boîte de réception (et les indésirables). Sans réception, contrôlez SPF/DKIM/DMARC du domaine.');
        }

        return self::SUCCESS;
    }
}
