<?php

namespace App\Console\Commands;

use App\Domain\Webhooks\Webhooks;
use Illuminate\Console\Command;

/**
 * Nouvelles tentatives des notifications sortantes en échec (lien intra-Chatel).
 * Planifiée toutes les 5 minutes (routes/console.php) ; aussi appelable par URL
 * (/cron/webhooks/{token}) sur un hébergement sans PHP CLI dans le cron.
 */
class RetryWebhooks extends Command
{
    protected $signature = 'vulcain:retry-webhooks';

    protected $description = 'Renvoie les notifications sortantes (webhooks) dont la tentative suivante est due.';

    public function handle(): int
    {
        $this->info(Webhooks::retryDue().' notification(s) retentée(s).');

        return self::SUCCESS;
    }
}
