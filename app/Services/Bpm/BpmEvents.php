<?php

namespace App\Services\Bpm;

use App\Jobs\SendBpmEvent;
use App\Models\Report;

/**
 * Eventi che Whistle comunica a UnicoBPM (`POST /api/events`). Alla ricezione di una segnalazione parte `segnalazione.ricevuta`: UnicoBPM
 * avvisa per email il responsabile whistleblowing e tiene d'occhio le scadenze (avviso di ricevimento a 7 giorni, riscontro a 3 mesi).
 *
 * Riservatezza: nell'evento ci sono solo il riferimento interno (id), l'azienda, il link al pannello e le scadenze. Mai titolo,
 * descrizione, allegati, PIN di tracciamento (`tracking_token`) né alcun dato di chi segnala.
 */
class BpmEvents
{
    public static function isConfigured(): bool
    {
        return filled(config('services.bpm.url')) && filled(config('services.bpm.key'));
    }

    /** @return array<string,mixed> */
    public static function reportReceived(Report $report): array
    {
        $report->loadMissing('company');

        return [
            'event_id' => "segnalazione.ricevuta:{$report->id}",
            'event' => 'segnalazione.ricevuta',
            'source' => 'whistle',
            'subject_ref' => "WB-{$report->id}",
            'occurred_at' => ($report->created_at ?? now())->toIso8601String(),
            'data' => [
                'report_id' => $report->id,
                'company' => $report->company?->name,
                'url' => url('/admin'),
                'acknowledgement_due_at' => $report->acknowledgement_due_at?->toDateString(),
                'feedback_due_at' => $report->feedback_due_at?->toDateString(),
                'hard_deadline' => $report->feedback_due_at?->toDateString(),
            ],
        ];
    }

    /** Mette in coda la comunicazione (con ritentativi). Mai un errore verso chi sta segnalando: la segnalazione è già salvata. */
    public static function dispatchReceived(Report $report): void
    {
        if (! self::isConfigured()) {
            return;
        }

        try {
            SendBpmEvent::dispatch(self::reportReceived($report));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
