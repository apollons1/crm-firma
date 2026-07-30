<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rules\StrongPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StrongPasswordRuleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $extraData
     */
    private function fails(string $password, ?User $user = null, array $extraData = []): bool
    {
        $validator = Validator::make(
            array_merge(['password' => $password], $extraData),
            ['password' => [new StrongPassword($user)]],
        );

        return $validator->fails();
    }

    public function test_blank_password_is_allowed(): void
    {
        $this->assertFalse($this->fails(''));
    }

    public function test_rejects_password_shorter_than_twelve_characters(): void
    {
        $this->assertTrue($this->fails('Abcd12345!'));
    }

    public function test_rejects_password_without_uppercase(): void
    {
        $this->assertTrue($this->fails('abcdefgh123!@'));
    }

    public function test_rejects_password_without_lowercase(): void
    {
        $this->assertTrue($this->fails('ABCDEFGH123!@'));
    }

    public function test_rejects_password_with_fewer_than_two_digits(): void
    {
        $this->assertTrue($this->fails('Abcdefghijk1!'));
    }

    public function test_rejects_password_without_special_character(): void
    {
        $this->assertTrue($this->fails('Abcdefghij12'));
    }

    public function test_accepts_password_meeting_all_requirements(): void
    {
        $this->assertFalse($this->fails('Str0ng!Passw0rd'));
    }

    public function test_rejects_password_containing_users_name(): void
    {
        $this->assertTrue($this->fails('MariaPopescu123!', extraData: ['name' => 'Maria Popescu', 'email' => 'x@example.com']));
    }

    public function test_rejects_password_containing_users_email_local_part(): void
    {
        $this->assertTrue($this->fails('IonPopescu!234', extraData: ['name' => 'Ion', 'email' => 'ionpopescu@example.com']));
    }

    public function test_does_not_falsely_reject_on_short_name_fragments(): void
    {
        // Nume scurt (< 3 caractere) nu ar trebui verificat — altfel prea
        // multe parole valide ar fi respinse din cauza unei coincidențe.
        $this->assertFalse($this->fails('Str0ng!Passw0rd', extraData: ['name' => 'Al', 'email' => 'al@example.com']));
    }

    public function test_rejects_password_matching_current_password(): void
    {
        $user = User::factory()->create(['password' => 'CurrentStr0ng!Pass']);

        $this->assertTrue($this->fails('CurrentStr0ng!Pass', $user, ['name' => $user->name, 'email' => $user->email]));
    }

    public function test_rejects_password_found_in_history(): void
    {
        $user = User::factory()->create();
        $user->passwordHistories()->create(['password_hash' => bcrypt('OldStr0ng!Pass1')]);

        $this->assertTrue($this->fails('OldStr0ng!Pass1', $user, ['name' => $user->name, 'email' => $user->email]));
    }

    public function test_allows_password_not_in_history(): void
    {
        $user = User::factory()->create();
        $user->passwordHistories()->create(['password_hash' => bcrypt('OldStr0ng!Pass1')]);

        $this->assertFalse($this->fails('BrandNew!Pass99', $user, ['name' => $user->name, 'email' => $user->email]));
    }

    public function test_only_checks_last_four_history_entries_plus_current(): void
    {
        $user = User::factory()->create();

        // 5 parole vechi în istoric, inserate în ordine — cea mai veche
        // (Old1) iese din fereastra de 5 (parola curentă + ultimele 4 din
        // istoric = Old2..Old5), deci nu mai trebuie respinsă.
        foreach (['Old1Str0ng!Pass', 'Old2Str0ng!Pass', 'Old3Str0ng!Pass', 'Old4Str0ng!Pass', 'Old5Str0ng!Pass'] as $old) {
            $user->passwordHistories()->create(['password_hash' => bcrypt($old)]);
        }

        $this->assertFalse($this->fails('Old1Str0ng!Pass', $user, ['name' => $user->name, 'email' => $user->email]));
        $this->assertTrue($this->fails('Old2Str0ng!Pass', $user, ['name' => $user->name, 'email' => $user->email]));
    }
}
