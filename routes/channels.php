<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('inventory.role.{role}', function (User $user, string $role): bool {
    return $user->getRoleNames()
        ->map(fn (string $name): string => (string) Str::of($name)->lower()->replace(' ', '-'))
        ->contains($role);
});
