<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Politica de parole a CRM-ului: minim 12 caractere, literă mare + literă
 * mică, cel puțin 2 cifre, cel puțin un caracter special, nu poate conține
 * numele/emailul userului, nu poate fi una dintre ultimele 5 parole
 * folosite. Nu verifică parole compromise (vezi Password::uncompromised(),
 * aplicat separat) — asta rămâne responsabilitatea validatorului Laravel.
 */
class StrongPassword implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    public function __construct(protected ?User $user = null) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Citește o valoare din datele validate. Când regula e folosită direct
     * cu Validator::make(), $this->data e array-ul plat trimis (ex:
     * ['name' => ..., 'password' => ...]). Când vine dintr-un formular
     * Filament cu statePath('data') (cazul obișnuit), Livewire pasează
     * validatorului întreaga stare a componentei, iar câmpurile formularului
     * ajung imbricate sub cheia 'data' (ex: $this->data['data']['name']).
     * Verificăm ambele forme, ca regula să funcționeze identic în teste
     * unitare directe și în formularele reale.
     */
    private function dataValue(string $key): mixed
    {
        return Arr::get($this->data, $key) ?? Arr::get($this->data, "data.{$key}");
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            // Câmp opțional la editare — gol înseamnă „păstrează parola curentă".
            return;
        }

        if (! is_string($value)) {
            $fail('Parola trebuie să fie un text valid.');

            return;
        }

        if (mb_strlen($value) < 12) {
            $fail('Parola trebuie să aibă cel puțin 12 caractere.');
        }

        if (! preg_match('/[A-Z]/', $value) || ! preg_match('/[a-z]/', $value)) {
            $fail('Parola trebuie să conțină cel puțin o literă mare și o literă mică.');
        }

        if (preg_match_all('/\d/', $value) < 2) {
            $fail('Parola trebuie să conțină cel puțin 2 cifre.');
        }

        if (! preg_match('/[^A-Za-z0-9]/', $value)) {
            $fail('Parola trebuie să conțină cel puțin un caracter special.');
        }

        if ($this->containsNameOrEmail($value)) {
            $fail('Parola nu poate conține numele sau adresa de email a utilizatorului.');
        }

        if ($this->isReused($value)) {
            $fail('Nu poți refolosi una dintre ultimele 5 parole folosite anterior.');
        }
    }

    private function containsNameOrEmail(string $password): bool
    {
        $name = (string) ($this->dataValue('name') ?? $this->user?->name ?? '');
        $email = (string) ($this->dataValue('email') ?? $this->user?->email ?? '');
        $emailLocalPart = Str::before($email, '@');

        // Verificăm și fiecare cuvânt din nume separat (ex: "Popescu" din
        // "Maria Popescu"), nu doar numele complet — numele complet, cu
        // spații, apare rareori literal într-o parolă.
        $nameParts = array_filter(preg_split('/\s+/', trim($name)) ?: []);

        $needles = array_filter(
            [...$nameParts, $name, $email, $emailLocalPart],
            fn (string $needle): bool => mb_strlen($needle) >= 3,
        );

        $lowerPassword = mb_strtolower($password);

        foreach ($needles as $needle) {
            if (str_contains($lowerPassword, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifică parola nouă față de parola curentă (dacă există) și
     * ultimele 4 din istoric — adică ultimele 5 parole distincte folosite.
     */
    private function isReused(string $password): bool
    {
        if (! $this->user) {
            return false;
        }

        if (filled($this->user->password) && Hash::check($password, $this->user->password)) {
            return true;
        }

        return $this->user->passwordHistories()
            ->take(4)
            ->get()
            ->contains(fn ($entry): bool => Hash::check($password, $entry->password_hash));
    }
}
