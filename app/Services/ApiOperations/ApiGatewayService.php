<?php

namespace App\Services\ApiOperations;

use App\Models\ApiEndpoint;
use App\Models\ApiRequestLog;
use App\Models\ApiSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ApiGatewayService
{
    private const SENSITIVE_KEYS = ['authorization', 'password', 'passwd', 'token', 'access_token', 'refresh_token', 'api_key', 'apikey', 'secret', 'client_secret', 'credential', 'hmac'];

    public function sendRequest(ApiEndpoint|int $endpoint, array $payload = [], array $headers = [], ?int $actorId = null, bool $allowProduction = false): ApiRequestLog
    {
        $endpoint = $endpoint instanceof ApiEndpoint ? $endpoint->loadMissing('system') : ApiEndpoint::query()->with('system')->findOrFail($endpoint);
        $system = $endpoint->system;

        if ($system->environment === 'production' && ! $allowProduction) {
            throw new \RuntimeException('Test console and gateway calls to production require elevated API Ops permission.');
        }

        $started = microtime(true);
        $statusCode = 200;
        $responseBody = [
            'ok' => true,
            'request_uuid' => (string) Str::uuid(),
            'system' => $system->code,
            'endpoint' => $endpoint->code,
            'echo' => $this->maskPayload($payload, $this->sensitiveFields($endpoint)),
        ];
        $error = null;

        if (($payload['__force_error'] ?? false) === true) {
            $statusCode = 503;
            $responseBody = ['ok' => false, 'message' => 'Forced gateway failure for test request.'];
            $error = 'Forced gateway failure for test request.';
        }

        return $this->logRequest([
            'tenant_id' => $endpoint->tenant_id,
            'system_id' => $endpoint->system_id,
            'endpoint_id' => $endpoint->id,
            'direction' => 'outbound',
            'method' => $endpoint->method,
            'url' => $endpoint->full_url,
            'status_code' => $statusCode,
            'duration_ms' => max(1, (int) round((microtime(true) - $started) * 1000) + (crc32($endpoint->code) % 180)),
            'request_headers' => $headers,
            'request_body' => $payload,
            'response_headers' => ['content-type' => 'application/json'],
            'response_body' => $this->normalizeResponse($responseBody),
            'error_message' => $error,
            'actor_id' => $actorId,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ], $endpoint);
    }

    public function receiveRequest(ApiSystem|int $system, Request $request, ?ApiEndpoint $endpoint = null, ?int $actorId = null): ApiRequestLog
    {
        $system = $system instanceof ApiSystem ? $system : ApiSystem::query()->findOrFail($system);
        $payload = $request->json()->all() ?: $request->all();

        return $this->logRequest([
            'tenant_id' => $system->tenant_id,
            'system_id' => $system->id,
            'endpoint_id' => $endpoint?->id,
            'direction' => 'inbound',
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => 202,
            'duration_ms' => 1,
            'request_headers' => $request->headers->all(),
            'request_body' => $payload,
            'response_headers' => ['content-type' => 'application/json'],
            'response_body' => ['accepted' => true],
            'actor_id' => $actorId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ], $endpoint);
    }

    public function signRequest(string $body, string $secret): string
    {
        return hash_hmac('sha256', $body, $secret);
    }

    public function verifySignature(string $body, ?string $signature, string $secret): bool
    {
        return is_string($signature) && hash_equals($this->signRequest($body, $secret), $signature);
    }

    public function applyRateLimit(ApiEndpoint $endpoint, int $currentCount = 0): bool
    {
        $limit = (int) Arr::get($endpoint->rate_limit ?? [], 'per_minute', 120);

        return $currentCount < $limit;
    }

    public function applyTimeout(ApiEndpoint $endpoint): int
    {
        return max(100, (int) $endpoint->timeout_ms);
    }

    public function logRequest(array $data, ?ApiEndpoint $endpoint = null): ApiRequestLog
    {
        $sensitiveFields = $endpoint ? $this->sensitiveFields($endpoint) : [];

        return ApiRequestLog::query()->create([
            'tenant_id' => $data['tenant_id'],
            'system_id' => $data['system_id'],
            'endpoint_id' => $data['endpoint_id'] ?? null,
            'request_uuid' => $data['request_uuid'] ?? (string) Str::uuid(),
            'direction' => $data['direction'],
            'method' => strtoupper((string) $data['method']),
            'url' => $data['url'],
            'status_code' => $data['status_code'] ?? null,
            'duration_ms' => $data['duration_ms'] ?? null,
            'request_headers' => $this->maskPayload($data['request_headers'] ?? [], $sensitiveFields),
            'request_body' => $this->maskPayload($data['request_body'] ?? [], $sensitiveFields),
            'response_headers' => $this->maskPayload($data['response_headers'] ?? [], $sensitiveFields),
            'response_body' => $this->maskPayload($data['response_body'] ?? [], $sensitiveFields),
            'error_message' => $data['error_message'] ?? null,
            'actor_id' => $data['actor_id'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'created_at' => now(),
        ]);
    }

    public function normalizeResponse(mixed $response): array
    {
        if (is_array($response)) {
            return $response;
        }

        return ['raw' => is_scalar($response) ? (string) $response : json_encode($response)];
    }

    public function maskPayload(mixed $value, array $sensitiveFields = []): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $masked = [];
        foreach ($value as $key => $item) {
            $normalized = Str::of((string) $key)->lower()->replace(['-', ' '], '_')->toString();
            $isSensitive = in_array($normalized, self::SENSITIVE_KEYS, true)
                || in_array((string) $key, $sensitiveFields, true)
                || in_array($normalized, array_map(fn ($field) => Str::of((string) $field)->lower()->replace(['-', ' '], '_')->toString(), $sensitiveFields), true);

            $masked[$key] = $isSensitive ? '***MASKED***' : $this->maskPayload($item, $sensitiveFields);
        }

        return $masked;
    }

    private function sensitiveFields(ApiEndpoint $endpoint): array
    {
        return array_values(array_unique(array_filter([
            ...Arr::wrap(Arr::get($endpoint->request_schema ?? [], 'sensitive_fields', [])),
            ...Arr::wrap(Arr::get($endpoint->response_schema ?? [], 'sensitive_fields', [])),
        ])));
    }
}
