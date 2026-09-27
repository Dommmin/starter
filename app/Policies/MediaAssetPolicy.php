<?php

namespace App\Policies;

use App\Models\MediaAsset;
use App\Models\User;

/**
 * The DAM belongs to the panel roles: admins and editors list, upload,
 * describe and download assets; only administrators delete them.
 * Downloading additionally requires a clean asset (checked by the
 * controller), so quarantined or rejected bytes are never served.
 */
class MediaAssetPolicy
{
    /**
     * Determine whether the user can list assets in the panel.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can open the asset details or download it.
     */
    public function view(User $user, MediaAsset $asset): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can upload assets.
     */
    public function create(User $user): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can edit the asset metadata.
     */
    public function update(User $user, MediaAsset $asset): bool
    {
        return $user->canAccessAdminPanel();
    }

    /**
     * Determine whether the user can permanently delete the asset.
     */
    public function delete(User $user, MediaAsset $asset): bool
    {
        return $user->isAdmin();
    }
}
