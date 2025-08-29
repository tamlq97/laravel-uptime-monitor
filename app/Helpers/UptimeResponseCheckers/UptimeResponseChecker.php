<?php

namespace App\Helpers\UptimeResponseCheckers;

use Illuminate\Http\Client\Response;
use Spatie\UptimeMonitor\Models\Monitor;

interface UptimeResponseChecker
{
    public function isValidResponse(Response $response, Monitor $monitor): bool;

    public function getFailureReason(Response $response, Monitor $monitor): string;
}
