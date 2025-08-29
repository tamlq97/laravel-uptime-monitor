<?php

namespace App\Helpers\UptimeResponseCheckers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Spatie\UptimeMonitor\Models\Monitor;

class LookForStringChecker implements UptimeResponseChecker
{
    public function isValidResponse(Response $response, Monitor $monitor): bool
    {
        if (empty($monitor->look_for_string)) {
            return true;
        }

        return Str::contains((string) $response->getBody(), $monitor->look_for_string);
    }

    public function getFailureReason(Response $response, Monitor $monitor): string
    {
        return "String `{$monitor->look_for_string}` was not found on the response.";
    }
}
