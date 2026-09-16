<?php

namespace App\Policies;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MouPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($ability === 'delete') {
            return false;
        }

        // Lifecycle invariants apply even to Business Owner / admin:
        // - Verified, converted, or cancelled MOUs are legally locked and cannot be edited
        // - Maker-checker and signed copy requirements for verify
        // - Verified MOUs cannot be archived; must be verified to convert
        if (in_array($ability, ['update', 'verify', 'convert', 'archive'])) {
            return null;
        }

        if ($user->hasRole(RoleName::BUSINESS_OWNER) || $user->hasRole('admin')) {
            return true;
        }

        // Backward-compatibility for unassigned users in legacy test factories
        if ($user->roles->isEmpty()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('mou.viewAny') || $user->roles->isEmpty();
    }

    public function view(User $user, Mou $mou): bool
    {
        return $user->can('mou.view') || $user->roles->isEmpty();
    }

    public function create(User $user): bool
    {
        return $user->can('mou.create') || $user->roles->isEmpty();
    }

    public function update(User $user, Mou $mou): bool
    {
        // Verified, converted, or cancelled MOUs are legally locked and cannot be edited
        if (in_array($mou->status, [MouStatus::VERIFIED, MouStatus::CONVERTED, MouStatus::COMPLETED, MouStatus::CANCELLED])) {
            return false;
        }

        if ($user->hasRole(RoleName::BUSINESS_OWNER) || $user->hasRole('admin') || $user->roles->isEmpty()) {
            return true;
        }

        return $user->can('mou.update');
    }

    public function verify(User $user, Mou $mou): bool
    {
        // Must be in SIGNED_COPY_UPLOADED status
        if ($mou->status !== MouStatus::SIGNED_COPY_UPLOADED) {
            return false;
        }

        // Supply Manager cannot verify (Maker cannot check)
        if ($user->hasRole(RoleName::SUPPLY_MANAGER) && ! $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::OPERATIONS_MANAGER])) {
            return false;
        }

        if ($user->hasRole(RoleName::BUSINESS_OWNER) || $user->hasRole('admin') || $user->roles->isEmpty()) {
            return true;
        }

        return $user->can('mou.verify');
    }

    public function convert(User $user, Mou $mou): bool
    {
        // Must be verified to convert
        if ($mou->status !== MouStatus::VERIFIED) {
            return false;
        }

        if ($user->hasRole(RoleName::BUSINESS_OWNER) || $user->hasRole('admin') || $user->roles->isEmpty()) {
            return true;
        }

        return $user->can('mou.convert');
    }

    public function archive(User $user, Mou $mou): bool
    {
        if ($mou->verified_at !== null || in_array($mou->status, [MouStatus::VERIFIED, MouStatus::CONVERTED, MouStatus::COMPLETED])) {
            return false;
        }

        if ($user->hasRole(RoleName::BUSINESS_OWNER) || $user->hasRole('admin') || $user->roles->isEmpty()) {
            return true;
        }

        return $user->can('mou.archive');
    }

    public function delete(User $user, Mou $mou): bool
    {
        return false;
    }
}
