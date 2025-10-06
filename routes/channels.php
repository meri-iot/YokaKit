<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('alarm', function (User $user) {
    return $user;
});

Broadcast::channel('onoff', function (User $user) {
    return $user;
});

Broadcast::channel('summary', function (User $user) {
    return $user;
});

Broadcast::channel('gantt-chart', function (User $user) {
    return $user;
});
