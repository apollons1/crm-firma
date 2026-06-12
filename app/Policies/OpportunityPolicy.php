<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OpportunityPolicy
{
    use HandlesAuthorization;

    /**
     * super_admin sare peste toate verificările din metodele de mai jos.
     */
    public function before(AuthUser $authUser, string $ability): ?bool
    {
        if ($authUser->hasRole('super_admin')) {
            return true;
        }
        return null;
    }

    // ── Vizualizare ────────────────────────────────────────────────────────────

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Opportunity');
    }

    public function view(AuthUser $authUser, Opportunity $opportunity): bool
    {
        // Toți cu permisiunea view_ pot vedea detaliile oricărei oportunități.
        return $authUser->can('View:Opportunity');
    }

    // ── Creare ─────────────────────────────────────────────────────────────────

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Opportunity');
    }

    // ── Editare ────────────────────────────────────────────────────────────────

    public function update(AuthUser $authUser, Opportunity $opportunity): bool
    {
        if (! $authUser->can('Update:Opportunity')) {
            return false;
        }

        // sales_rep poate edita DOAR oportunitățile unde el e responsabil.
        // admin și sales_manager pot edita orice.
        if ($authUser->hasRole('sales_rep')) {
            return (int) $opportunity->user_id === $authUser->id;
        }

        return true;
    }

    // ── Ștergere ───────────────────────────────────────────────────────────────

    public function delete(AuthUser $authUser, Opportunity $opportunity): bool
    {
        // sales_rep nu poate șterge oportunități (indiferent de permisiuni).
        if ($authUser->hasRole('sales_rep')) {
            return false;
        }

        // admin și sales_manager pot șterge individual (au Delete:Opportunity).
        return $authUser->can('Delete:Opportunity');
    }

    // Ștergere în masă, forțată, restaurare — doar admin (super_admin prins de before()).
    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->hasRole('admin');
    }

    public function forceDelete(AuthUser $authUser, Opportunity $opportunity): bool
    {
        return $authUser->hasRole('admin');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->hasRole('admin');
    }

    public function restore(AuthUser $authUser, Opportunity $opportunity): bool
    {
        return $authUser->hasRole('admin');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->hasRole('admin');
    }

    // ── Altele ─────────────────────────────────────────────────────────────────

    public function replicate(AuthUser $authUser, Opportunity $opportunity): bool
    {
        return $authUser->hasAnyRole(['admin', 'sales_manager']);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->hasAnyRole(['admin', 'sales_manager']);
    }
}
