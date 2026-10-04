<?php

namespace App\Http\Controllers;

use App\Jobs\CalculateUnpaidMonths;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;

class CronController extends Controller
{
    public function run(Request $request, string $task)
    {
        $token  = (string) $request->query('token', '');
        $secret = (string) config('services.cron.token');

        abort_if($secret === '' || ! hash_equals($secret, $token), 404);

        match ($task) {
            'unpaid-months'        => Bus::dispatchSync(new CalculateUnpaidMonths()),
            'unpaid-subscriptions' => Artisan::call('notify:unpaid-subscriptions'),
            'sequential-absences'  => Artisan::call('notify:sequential-absences'),
            default                => abort(404),
        };

        return response('تم', 200);
    }
}