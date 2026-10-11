<?php

namespace Tests\Feature;

use App\Livewire\PublicReportForm;
use App\Models\Company;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class BpmEventsTest extends TestCase
{
    use RefreshDatabase;

    private function submit(Company $company)
    {
        return Livewire::test(PublicReportForm::class, ['company' => $company])
            ->set('data.title', 'Irregolarità contabili riservate')
            ->set('data.description', 'Movimenti sospetti nei conti della direzione.')
            ->call('submit');
    }

    private function configure(): void
    {
        config(['services.bpm.url' => 'https://bpm.example.com', 'services.bpm.key' => 'chiave-bpm']);
    }

    public function test_a_new_report_is_communicated_to_bpm_without_any_content(): void
    {
        Notification::fake();
        Http::fake(['https://bpm.example.com/*' => Http::response(['status' => 'processed'], 202)]);
        $this->configure();
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme']);

        $this->submit($company)->assertSet('isSubmitted', true);

        $report = Report::sole();
        Http::assertSent(function ($request) use ($report) {
            $body = $request->body();

            return $request->method() === 'POST' && $request->url() === 'https://bpm.example.com/api/events'
                && $request->header('X-Api-Key') === ['chiave-bpm']
                && $request['event_id'] === "segnalazione.ricevuta:{$report->id}"
                && $request['event'] === 'segnalazione.ricevuta' && $request['source'] === 'whistle' && $request['subject_ref'] === "WB-{$report->id}"
                && $request['data']['company'] === 'Acme'
                && $request['data']['hard_deadline'] === $report->feedback_due_at->toDateString()
                && $request['data']['acknowledgement_due_at'] === $report->acknowledgement_due_at->toDateString()
                // Riservatezza: né contenuto né PIN di tracciamento.
                && ! str_contains($body, 'Irregolarità') && ! str_contains($body, 'Movimenti') && ! str_contains($body, 'WHSL-') && ! str_contains($body, $report->tracking_token);
        });
    }

    public function test_the_event_carries_only_the_agreed_fields(): void
    {
        $company = Company::create(['name' => 'Acme', 'slug' => 'acme']);
        $report = $company->reports()->create(['title' => 'T', 'description' => 'D', 'tracking_token' => 'WHSL-AAAA-BBBB', 'status' => 'new']);

        $event = \App\Services\Bpm\BpmEvents::reportReceived($report);

        $this->assertSame(['report_id', 'company', 'url', 'acknowledgement_due_at', 'feedback_due_at', 'hard_deadline'], array_keys($event['data']));
        $this->assertSame(['event_id', 'event', 'source', 'subject_ref', 'occurred_at', 'data'], array_keys($event));
    }

    public function test_nothing_is_sent_without_configuration(): void
    {
        Notification::fake();
        Http::fake();

        $this->submit(Company::create(['name' => 'Acme', 'slug' => 'acme']))->assertSet('isSubmitted', true);

        Http::assertNothingSent();
    }

    public function test_an_unreachable_bpm_never_breaks_the_submission(): void
    {
        Notification::fake();
        Http::fake(['*' => Http::response('boom', 503)]);
        $this->configure();

        $this->submit(Company::create(['name' => 'Acme', 'slug' => 'acme']))->assertSet('isSubmitted', true);

        $this->assertSame(1, Report::count());
    }
}
