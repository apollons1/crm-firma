<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PREVIEW — nu e încă funcțional. Nu există model Meeting/tabelă meetings
 * în aplicație și evenimentul nu e dispatch-uit de nicăieri.
 *
 * Când se implementează programarea de întâlniri, acest eveniment ar urma
 * să fie declanșat la crearea unei întâlniri, iar un listener dedicat
 * (ex: ScheduleMeetingReminders) ar trebui să programeze — nu să trimită
 * direct, ca să respecte ora exactă — două reminder-e WhatsApp către
 * contact, folosind Queue::later()/joburi amânate:
 *
 *   ScheduleMeetingReminder::dispatch($meeting)
 *       ->delay($meeting->scheduled_at->subHours(24));
 *   ScheduleMeetingReminder::dispatch($meeting)
 *       ->delay($meeting->scheduled_at->subHour());
 *
 * Ambele reminder-e ar trebui trimise prin WhatsAppAutomationSender (ca și
 * OpportunityWon/OpportunityStuck), respectând aceeași regulă de 24h.
 *
 * public function __construct(
 *     public readonly Meeting $meeting,
 * ) {}
 */
class MeetingScheduled
{
    use Dispatchable, SerializesModels;
}
