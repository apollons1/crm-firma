<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class UserPolicy
{
    use HandlesAuthorization;

    public function before(AuthUser $authUser, string $ability): ?bool
    {
        if ($authUser->hasRole('super_admin')) {
            return true;
        }
        return null;
    }

    // sales_manager vede lista utilizatorilor (colegii), dar NU poate modifica.
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:User');
    }

    public function view(AuthUser $authUser, User $user): bool
    {
        return $authUser->can('View:User');
    }

    // Creare, editare, ștergere — doar admin și super_admin (prins de before()).
    public function create(AuthUser $authUser): bool
    {
        return $authUser->hasRole('admin');
    }

    public function update(AuthUser $authUser, User $user): bool
    {
        return $authUser->hasRole('admin');
    }

    public function delete(AuthUser $authUser, User $user): bool
    {
        // Nu poți șterge propriul cont.
        if ($authUser->id === $user->id) {
            return false;
        }
        return $authUser->hasRole('admin');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->hasRole('admin');
    }

    public function forceDelete(AuthUser $authUser, User $user): bool
    {
        return $authUser->hasRole('admin');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->hasRole('admin');
    }

    public function restore(AuthUser $authUser, User $user): bool
    {
        return $authUser->hasRole('admin');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->hasRole('admin');
    }

    public function replicate(AuthUser $authUser, User $user): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
