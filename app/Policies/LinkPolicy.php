<?php

namespace App\Policies;

use App\Models\Link;
use App\Models\User;

class LinkPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Link $link): bool
    {
        return $user->id === $link->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Link $link): bool
    {
        return $user->id === $link->user_id;
    }

    public function delete(User $user, Link $link): bool
    {
        return $user->id === $link->user_id;
    }
}
