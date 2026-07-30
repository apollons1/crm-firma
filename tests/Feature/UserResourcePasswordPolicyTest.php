<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\PasswordHistory;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserResourcePasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private int $salesRepRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        // api.pwnedpasswords.com — parola de test nu e niciodată "compromisă".
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);

        $superAdminRole = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $salesRepRole = Role::create(['name' => 'sales_rep', 'guard_name' => 'web']);
        $this->salesRepRoleId = $salesRepRole->id;

        // UserResource::canAccess() verifică permisiunea Spatie "ViewAny:User"
        // (nu doar rolul) — în producție e creată/atribuită de shield:generate
        // + seeder; aici o reproducem manual pentru super_admin.
        $superAdminRole->givePermissionTo(
            Permission::create(['name' => 'ViewAny:User', 'guard_name' => 'web'])
        );

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->actingAs($admin);
    }

    public function test_create_user_rejects_weak_password(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'short1!',
                'roles' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);
    }

    public function test_create_user_accepts_strong_password(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'Str0ng!Passw0rd',
                'roles' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_create_user_rejects_password_containing_the_name(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Andrei Ionescu',
                'email' => 'andrei@example.com',
                'password' => 'AndreiIonescu12!',
                'roles' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);
    }

    public function test_edit_user_can_leave_password_blank_to_keep_current_one(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sales_rep');

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'name' => $user->name,
                'email' => $user->email,
                'password' => '',
                'roles' => [$this->salesRepRoleId],
            ])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_edit_user_rejects_reused_password(): void
    {
        $user = User::factory()->create(['password' => 'CurrentStr0ng!Pass']);
        $user->assignRole('sales_rep');

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'CurrentStr0ng!Pass',
                'roles' => [$this->salesRepRoleId],
            ])
            ->call('save')
            ->assertHasFormErrors(['password']);
    }

    public function test_edit_user_archives_old_password_and_updates_timestamp(): void
    {
        $user = User::factory()->create(['password' => 'CurrentStr0ng!Pass', 'password_changed_at' => now()->subDays(50)]);
        $user->assignRole('sales_rep');
        $oldHash = $user->password;

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'BrandNewStr0ng!99',
                'roles' => [$this->salesRepRoleId],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertTrue(Hash::check('BrandNewStr0ng!99', $user->password));
        $this->assertTrue($user->password_changed_at->greaterThan(now()->subMinute()));

        $history = PasswordHistory::where('user_id', $user->id)->first();
        $this->assertNotNull($history);
        $this->assertSame($oldHash, $history->password_hash);
    }

    public function test_edit_user_password_change_sends_notification_from_admin(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => 'CurrentStr0ng!Pass']);
        $user->assignRole('sales_rep');

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'BrandNewStr0ng!99',
                'roles' => [$this->salesRepRoleId],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Notification::assertSentTo(
            $user,
            PasswordChangedNotification::class,
        );
    }

    public function test_password_history_is_trimmed_to_four_entries(): void
    {
        // Tabela password_history păstrează doar ultimele 4 parole VECHI —
        // "ultimele 5 parole" din regula de refolosire = parola curentă
        // (users.password) + aceste 4 din istoric, nu 5 rânduri în istoric.
        $user = User::factory()->create(['password' => 'InitialStr0ng!Pass']);
        $user->assignRole('sales_rep');

        $passwords = [
            'ChangeOneStr0ng!1',
            'ChangeTwoStr0ng!2',
            'ChangeThreeStr0ng!3',
            'ChangeFourStr0ng!4',
            'ChangeFiveStr0ng!5',
            'ChangeSixStr0ng!!6',
        ];

        foreach ($passwords as $password) {
            Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
                ->fillForm([
                    'name' => $user->name,
                    'email' => $user->email,
                    'password' => $password,
                    'roles' => [$this->salesRepRoleId],
                ])
                ->call('save')
                ->assertHasNoFormErrors();
        }

        $this->assertSame(4, PasswordHistory::where('user_id', $user->id)->count());
    }
}
