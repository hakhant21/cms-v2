<?php

use App\Models\User;
use App\Support\Chat;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Live chat
|--------------------------------------------------------------------------
|
| Only the team's channel needs authorising here. A conversation channel is
| public on purpose: its name carries a 40-character random token that only the
| visitor's session and the database know, so possession of the token is the
| proof, and a guest can be served without an account or an extra round trip.
| The agents' channel lists everybody's conversations, so it is private and an
| admin has to be signed in to join it.
*/
Broadcast::channel(Chat::AGENTS_CHANNEL, function (?User $user): bool {
    return $user !== null && $user->is_admin;
});
