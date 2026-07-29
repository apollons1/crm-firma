<?php

namespace App\Console\Commands;

use App\Events\OpportunityStuck;
use App\Models\AutomationSetting;
use App\Models\Opportunity;
use Illuminate\Console\Command;

class CheckStuckOpportunitiesCommand extends Command
{
    protected $signature = 'opportunities:check-stuck {--force : Rulează indiferent de ora configurată (util pentru testare/rulare manuală)}';

    protected $description = 'Notifică sales_rep-ul responsabil prin WhatsApp pentru oportunitățile blocate prea mult timp în același status';

    /**
     * Praguri implicite (zile) — suprascrise de automation_settings dacă sunt configurate.
     */
    private const DEFAULT_THRESHOLDS = [
        'lead' => 14,
        'proposal' => 21,
        'negotiation' => 30,
    ];

    public function handle(): int
    {
        if (! AutomationSetting::get('opportunity_stuck.enabled', true)) {
            $this->info('Automatizarea "oportunitate blocată" e dezactivată — nu fac nimic.');

            return self::SUCCESS;
        }

        // Comanda e programată să ruleze orar (vezi routes/console.php) și se
        // auto-limitează la ora configurată — asta permite schimbarea orei de
        // trimitere din pagina de setări, fără redeploy.
        $configuredHour = (int) AutomationSetting::get('opportunity_stuck.send_hour', 9);

        if (! $this->option('force') && (int) now()->format('G') !== $configuredHour) {
            return self::SUCCESS;
        }

        $total = 0;

        foreach (self::DEFAULT_THRESHOLDS as $status => $defaultDays) {
            $days = (int) AutomationSetting::get("opportunity_stuck.days_{$status}", $defaultDays);

            $stuckOpportunities = Opportunity::where('status', $status)
                ->where('updated_at', '<', now()->subDays($days))
                ->get();

            foreach ($stuckOpportunities as $opportunity) {
                // Carbon::diffInDays() întoarce valoare negativă când parametrul
                // e în trecut (vezi și WhatsappMessage::isPhoneWithin24HourWindow) —
                // fără abs(), am fi trimis "-15 zile" în mesaj.
                $daysStuck = (int) abs(now()->diffInDays($opportunity->updated_at));

                OpportunityStuck::dispatch($opportunity, $daysStuck);
                $total++;
            }

            $this->info("Status \"{$status}\" (prag {$days} zile): {$stuckOpportunities->count()} oportunități blocate.");
        }

        $this->info("Total notificări declanșate: {$total}.");

        return self::SUCCESS;
    }
}
