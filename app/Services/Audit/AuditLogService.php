<?php

namespace App\Services\Audit;

use App\Data\Audit\AuditLogData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AuditLogService
{
    public const LOG_CLIENT_AUTH = 'client.auth';
    public const LOG_CLIENT_ACCOUNT = 'client.account';
    public const LOG_CLIENT_ADMIN_COMPANY = 'client.admin.company';
    public const LOG_CLIENT_ADMIN_USER = 'client.admin.user';
    public const LOG_CLIENT_API = 'client.api';
    public const LOG_CLIENT_SECURITY = 'client.security';

    /**
     * @var array<int, string>
     */
    private const ALLOWED_LOG_NAMES = [
        self::LOG_CLIENT_AUTH,
        self::LOG_CLIENT_ACCOUNT,
        self::LOG_CLIENT_ADMIN_COMPANY,
        self::LOG_CLIENT_ADMIN_USER,
        self::LOG_CLIENT_API,
        self::LOG_CLIENT_SECURITY,
    ];

    /**
     * @var array<int, string>
     */
    private const ALLOWED_PROPERTIES = [
        'reason',
        'ip_address',
        'user_agent',
        'route',
        'updated_fields',
        'changed_attributes',
        'status',
        'result',
        'redirect_target',
        'callback_result',
        'provider_error',
        'provider_error_description',
        'target_company_id',
        'target_local_user_id',
        'affected_count',
        'api_endpoint',
        'http_status',
        'reauth_reason',
        'has_nonce',
        'has_sid',
        'has_jti',
        'has_exp',
        'scope_contains_openid',
        'has_issuer',
        'has_client_id',
        'kid',
        'key_count',
        'metadata_url',
        'metadata_key',
    ];

    /**
     * @var array<int, string>
     */
    private const SENSITIVE_PROPERTIES = [
        'password',
        'password_hash',
        'secret',
        'client_secret',
        'access_token',
        'refresh_token',
        'authorization_code',
        'cookie',
        'session',
        'session_id',
        'bearer_token',
    ];

    /**
     * @param array<string, mixed> $properties
     */
    public function log(
        string $logName,
        string $event,
        string $description,
        ?Model $subject = null,
        ?Model $causer = null,
        array $properties = [],
    ): void {
        $entry = new AuditLogData(
            logName: $this->validateLogName($logName),
            event: $this->validateEvent($event),
            description: trim($description),
            subject: $subject,
            causer: $causer,
            properties: $this->sanitizeProperties($properties),
        );

        $activity = activity($entry->logName)->event($entry->event);

        if ($entry->subject instanceof Model) {
            $activity->performedOn($entry->subject);
        }

        if ($entry->causer instanceof Model) {
            $activity->causedBy($entry->causer);
        }

        if ($entry->properties !== []) {
            $activity->withProperties($entry->properties);
        }

        $activity->log($entry->description);
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function logSuccess(
        string $logName,
        string $event,
        string $description,
        ?Model $subject = null,
        ?Model $causer = null,
        array $properties = [],
    ): void {
        $this->log(
            logName: $logName,
            event: $event,
            description: $description,
            subject: $subject,
            causer: $causer,
            properties: [...$properties, 'result' => 'success'],
        );
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function logFailure(
        string $logName,
        string $event,
        string $description,
        ?Model $subject = null,
        ?Model $causer = null,
        array $properties = [],
    ): void {
        $this->log(
            logName: $logName,
            event: $event,
            description: $description,
            subject: $subject,
            causer: $causer,
            properties: [...$properties, 'result' => 'failure'],
        );
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function logClientAdminCrud(
        string $resource,
        string $action,
        string $description,
        ?Model $subject = null,
        ?Model $causer = null,
        array $properties = [],
    ): void {
        $logName = match ($resource) {
            'company' => self::LOG_CLIENT_ADMIN_COMPANY,
            'user' => self::LOG_CLIENT_ADMIN_USER,
            default => throw new InvalidArgumentException(sprintf('Unsupported client admin resource [%s].', $resource)),
        };

        $this->logSuccess(
            logName: $logName,
            event: sprintf('client_admin.%s.%s', $resource, $action),
            description: $description,
            subject: $subject,
            causer: $causer,
            properties: $properties,
        );
    }

    /**
     * @return array{ip_address: string|null, user_agent: string|null, route: string|null}
     */
    public function requestContext(Request $request): array
    {
        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'route' => $request->route()?->getName(),
        ];
    }

    private function validateLogName(string $logName): string
    {
        if (! in_array($logName, self::ALLOWED_LOG_NAMES, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported audit log name [%s].', $logName));
        }

        return $logName;
    }

    private function validateEvent(string $event): string
    {
        if (! preg_match('/^[a-z0-9_]+(?:\.[a-z0-9_]+){2,}$/', $event)) {
            throw new InvalidArgumentException(sprintf('Invalid audit event [%s].', $event));
        }

        return $event;
    }

    /**
     * @param array<string, mixed> $properties
     * @return array<string, scalar|array<int|string, scalar|array<int|string, scalar>|null>|null>
     */
    private function sanitizeProperties(array $properties): array
    {
        $sanitized = [];

        foreach ($properties as $key => $value) {
            if (in_array($key, self::SENSITIVE_PROPERTIES, true)) {
                throw new InvalidArgumentException(sprintf('Sensitive audit property [%s] is not allowed.', $key));
            }

            if (! in_array($key, self::ALLOWED_PROPERTIES, true)) {
                throw new InvalidArgumentException(sprintf('Unsupported audit property [%s].', $key));
            }

            $sanitized[$key] = $this->normalizeValue($value);
        }

        return $sanitized;
    }

    /**
     * @return scalar|array<int|string, scalar|array<int|string, scalar>|null>|null
     */
    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_values(array_map(fn (mixed $item): mixed => $this->normalizeValue($item), $value));
            }

            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[(string) $key] = $this->normalizeValue($item);
            }

            return $normalized;
        }

        return (string) $value;
    }
}
