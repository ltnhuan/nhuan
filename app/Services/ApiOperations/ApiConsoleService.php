<?php

namespace App\Services\ApiOperations;

use App\Models\ApiAuditLog;
use App\Models\ApiEndpoint;
use Illuminate\Support\Arr;

class ApiConsoleService
{
    public function __construct(private readonly ApiGatewayService $gateway)
    {
    }

    public function testEndpoint(ApiEndpoint|int $endpoint, array $payload = [], array $headers = [], ?int $actorId = null, bool $allowProduction = false): array
    {
        $endpoint = $endpoint instanceof ApiEndpoint ? $endpoint->loadMissing('system') : ApiEndpoint::query()->with('system')->findOrFail($endpoint);
        $validation = $this->validateRequestAgainstSchema($endpoint, $payload);
        if (! $validation['valid']) {
            return [
                'valid' => false,
                'errors' => $validation['errors'],
                'response_preview' => null,
            ];
        }

        $log = $this->gateway->sendRequest($endpoint, $payload, $headers, $actorId, $allowProduction);
        $this->saveTestHistory($endpoint, $payload, $actorId);

        return [
            'valid' => true,
            'errors' => [],
            'request_log_id' => $log->id,
            'status_code' => $log->status_code,
            'duration_ms' => $log->duration_ms,
            'response_preview' => $this->showResponsePreview($log->response_body ?? []),
        ];
    }

    public function validateRequestAgainstSchema(ApiEndpoint $endpoint, array $payload): array
    {
        $schema = $endpoint->request_schema ?? [];
        $required = Arr::wrap($schema['required'] ?? []);
        $errors = [];

        foreach ($required as $field) {
            if (! Arr::has($payload, $field) || blank(Arr::get($payload, $field))) {
                $errors[$field] = 'Required by endpoint schema.';
            }
        }

        return ['valid' => count($errors) === 0, 'errors' => $errors];
    }

    public function showResponsePreview(array $response): array
    {
        return array_slice($response, 0, 20, true);
    }

    public function saveTestHistory(ApiEndpoint $endpoint, array $payload, ?int $actorId = null): void
    {
        ApiAuditLog::query()->create([
            'tenant_id' => $endpoint->tenant_id,
            'actor_id' => $actorId,
            'action' => 'api_console.test_endpoint',
            'module' => 'console',
            'entity_type' => 'api_endpoint',
            'entity_id' => (string) $endpoint->id,
            'before' => null,
            'after' => ['payload_keys' => array_keys($payload)],
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
