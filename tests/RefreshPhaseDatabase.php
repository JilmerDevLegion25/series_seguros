<?php

namespace Tests;

use App\Services\Sms\FakeSmsGateway;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;

trait RefreshPhaseDatabase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:fresh', ['--force' => true]);
        FakeSmsGateway::clearSentMessages();
        RateLimiter::clear('login:account:'.sha1('1234567890'));
        RateLimiter::clear('login:ip:'.sha1('127.0.0.1'));
    }
}
