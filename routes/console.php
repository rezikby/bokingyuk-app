<?php

use App\Jobs\ExpireUnpaidBookings;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console / Scheduler Routes
|--------------------------------------------------------------------------
*/

// Expire unpaid bookings every 5 minutes
Schedule::job(new ExpireUnpaidBookings)->everyFiveMinutes()
    ->name('expire-unpaid-bookings')
    ->withoutOverlapping();
