<?php

namespace App\Jobs;

use App\Services\HeartbeatService;
use Generator;
use GrahamCampbell\GuzzleFactory\GuzzleFactory;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Promise\EachPromise;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use App\Models\Monitor;

class CheckUptimeJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    private array $headers;
    private array $guzzleOptions;
    private int $timeout;
    private string $checkMethod;

    public function __construct(public Monitor $monitor)
    {
        $this->onQueue('monitor');

        // Cache config values to avoid multiple config() calls
        $this->headers = $this->buildHeaders();
        $this->guzzleOptions = config('uptime-monitor.uptime_check.guzzle_options', []);
        $this->timeout = config('uptime-monitor.uptime_check.timeout_per_site', 10);
        $this->checkMethod = $this->monitor->uptime_check_method ?? 'GET';
    }

    public function handle(HeartbeatService $heartbeatService): void
    {
        try {
            $startTime = microtime(true);

            (new EachPromise($this->getPromises(), [
                'concurrency' => config('uptime-monitor.uptime_check.concurrent_checks'),
                'fulfilled' => function (ResponseInterface $response, $index) use ($heartbeatService, $startTime) {
                    $responseTime = (int) ((microtime(true) - $startTime) * 1000);

                    $this->monitor->uptimeRequestSucceeded($response);
                    $heartbeatService->recordHeartbeat(
                        monitor: $this->monitor,
                        status: 'up',
                        responseTime: $responseTime,
                        statusCode: $response->getStatusCode(),
                        checkMethod: $this->checkMethod
                    );
                },

                'rejected' => function (TransferException $exception, $index) use ($heartbeatService) {
                    $this->monitor->uptimeRequestFailed($exception->getMessage());
                    $heartbeatService->recordHeartbeat(
                        monitor: $this->monitor,
                        status: 'down',
                        errorMessage: $this->monitor->uptime_check_failure_reason,
                        checkMethod: $this->checkMethod
                    );
                },
            ]))->promise()->wait();
        } catch (\Exception $e) {
            Log::error('CheckUptimeJob failed', [
                'monitor_id' => $this->monitor->id,
                'url' => $this->monitor->url,
                'error' => $e->getMessage()
            ]);

            $this->fail($e);
        }
    }

    protected function getPromises(): Generator
    {
        $client = GuzzleFactory::make(
            $this->guzzleOptions,
            config('uptime-monitor.uptime_check.retry_connection_after_milliseconds', 100)
        );

        $promise = $client->requestAsync(
            $this->checkMethod,
            $this->monitor->url,
            array_filter([
                'connect_timeout' => $this->timeout,
                'headers' => $this->headers,
                'body' => $this->monitor->uptime_check_payload,
            ])
        )->then(
            null, // Remove redundant fulfilled callback
            function (TransferException $exception) {
                $additionalStatusCodes = config('uptime-monitor.uptime_check.additional_status_codes', []);

                if (in_array($exception->getCode(), $additionalStatusCodes)) {
                    return $exception->getResponse();
                }

                throw $exception;
            }
        );

        yield $promise;
    }

    private function buildHeaders(): array
    {
        return array_merge(
            ['User-Agent' => config('uptime-monitor.uptime_check.user_agent', 'Laravel Uptime Monitor')],
            config('uptime-monitor.uptime_check.additional_headers', []),
            $this->monitor->uptime_check_additional_headers ?? []
        );
    }
}
