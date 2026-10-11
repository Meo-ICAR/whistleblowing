<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Report;
use App\Notifications\NewReportReceived;
use App\Services\Bpm\BpmEvents;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PublicReportForm extends Component implements HasForms
{
    use InteractsWithForms;

    public Company $company;

    public ?array $data = [];

    // Variabili per la schermata di successo
    public bool $isSubmitted = false;

    public string $trackingPin = '';

    // Variabili per la verifica passcode
    public bool $passcodeVerified = false;

    public string $passcodeInput = '';

    public function mount(Company $company): void
    {
        $this->company = $company;
        // Se non c'è passcode, il form è direttamente accessibile
        if (empty($company->shared_passcode)) {
            $this->passcodeVerified = true;
        }
        $this->form->fill();
    }

    public function verifyPasscode(): void
    {
        if (empty($this->company->shared_passcode)) {
            $this->passcodeVerified = true;

            return;
        }

        $throttleKey = 'passcode-verify:'.$this->company->id.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $this->addError('passcodeInput', 'Troppi tentativi. Riprova tra '.RateLimiter::availableIn($throttleKey).' secondi.');

            return;
        }

        // hash_equals evita timing attack rispetto al confronto diretto con '==='
        if (hash_equals((string) $this->company->shared_passcode, $this->passcodeInput)) {
            RateLimiter::clear($throttleKey);
            $this->passcodeVerified = true;
        } else {
            RateLimiter::hit($throttleKey, 60);
            $this->addError('passcodeInput', 'Codice non valido');
        }
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label('Oggetto della segnalazione')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Descrizione dettagliata dei fatti')
                    ->required()
                    ->rows(6)
                    ->helperText('Non inserire i tuoi dati personali se desideri rimanere anonimo.'),
                // Integrazione nativa con Spatie Media Library
                SpatieMediaLibraryFileUpload::make('attachments')
                    ->label('Allegati e Prove')
                    ->collection('evidence')  // Assicurati che il modello Report gestisca questa collection
                    ->multiple()
                    ->maxFiles(5)
                    ->maxSize(10240)  // 10MB
                    // audio/wav (+ audio/x-wav): formato prodotto dal registratore
                    // vocale in browser dopo l'alterazione del timbro (vedi
                    // voice-recorder.js). Il tipo MIME reale è rilevato sul
                    // server con fileinfo/libmagic, che per i WAV restituisce
                    // "audio/x-wav" e non "audio/wav": senza includere anche
                    // questa variante, l'allegato veniva sempre rifiutato.
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'audio/mpeg', 'audio/wav', 'audio/x-wav'])
                    ->disk('private')  // Fondamentale: usa un disco NON pubblico
                    // Cifratura a riposo: il contenuto reale del file non è mai
                    // leggibile direttamente dal disco, nemmeno da chi ha
                    // accesso al filesystem del server. Il mime/nome originali
                    // sono salvati solo come custom properties del record Media
                    // (nel DB, non nel file), per poterli ricostruire in fase
                    // di download decifrato (vedi routes/web.php).
                    ->saveUploadedFileUsing(function (SpatieMediaLibraryFileUpload $component, TemporaryUploadedFile $file, ?Model $record) {
                        if (! $file->exists()) {
                            return null;
                        }

                        $encrypted = Crypt::encryptString($file->get());

                        $media = $record->addMediaFromString($encrypted)
                            ->usingFileName($component->getUploadedFileNameForStorage($file).'.enc')
                            ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                            ->withCustomProperties([
                                'encrypted' => true,
                                'original_name' => $file->getClientOriginalName(),
                                'original_mime' => $file->getMimeType(),
                            ])
                            ->toMediaCollection($component->getCollection() ?? 'default', $component->getDiskName());

                        return $media->getAttributeValue('uuid');
                    }),
            ])
            ->statePath('data');
    }

    public function submit()
    {
        $throttleKey = 'report-submit:'.$this->company->id.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('data.title', 'Troppi invii. Riprova tra '.RateLimiter::availableIn($throttleKey).' secondi.');

            return;
        }

        RateLimiter::hit($throttleKey, 300);

        $data = $this->form->getState();

        // Genera un PIN univoco e facile da leggere (es. WHSL-A8F2-9K1M),
        // ricontrollando l'unicità nel DB prima di salvare
        do {
            $this->trackingPin = 'WHSL-'.strtoupper(Str::random(4).'-'.Str::random(4));
        } while (Report::where('tracking_token', $this->trackingPin)->exists());

        // Salva nel database associando all'azienda
        $report = $this->company->reports()->create([
            'title' => $data['title'],
            'description' => $data['description'],
            'tracking_token' => $this->trackingPin,
            'status' => 'new',
        ]);

        // Associa i file caricati al modello Report (Spatie Media Library)
        $this->form->model($report)->saveRelationships();

        // Avvisa i gestori dell'azienda: l'email non contiene mai il
        // contenuto della segnalazione, solo l'invito ad accedere al pannello.
        Notification::send($this->company->users, new NewReportReceived($report));

        // Avvisa UnicoBPM (responsabile whistleblowing e scadenze): solo riferimento e date, mai il contenuto.
        BpmEvents::dispatchReceived($report);

        // Mostra la schermata di successo
        $this->isSubmitted = true;
    }

    public function render()
    {
        $showPasscodeStep = ! empty($this->company->shared_passcode) && ! $this->passcodeVerified;

        return view('livewire.public-report-form', compact('showPasscodeStep'))
            ->layout('layouts.guest', ['company' => $this->company]);
    }
}
