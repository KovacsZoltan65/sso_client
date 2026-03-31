<?php

namespace App\Services\Emergency;

use App\Data\Emergency\EmergencyLoginData;
use App\Models\EmergencyAccount;
use App\Repositories\Emergency\EmergencyAccountRepository;
use App\Services\Audit\AuditLogService;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmergencyAuthService
{
    public function __construct(
        private readonly AuthManager $auth,
        private readonly EmergencyAccountRepository $accounts,
        private readonly EmergencyModeService $emergencyModeService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function login(EmergencyLoginData $data, Request $request): EmergencyAccount
    {
        if (! $this->emergencyModeService->isEmergencyActive()) {
            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_CLIENT_SECURITY,
                event: 'client_security.emergency_action.blocked',
                description: 'Emergency login blocked.',
                properties: [
                    'reason' => 'emergency_mode_inactive',
                    'status' => 'blocked',
                    ...$this->auditLogService->requestContext($request),
                ],
            );

            throw ValidationException::withMessages([
                'username' => 'Emergency mode is not active.',
            ]);
        }

        $account = $this->accounts->findActiveByUsername($data->username);

        if (! $account instanceof EmergencyAccount || ! Hash::check($data->password, $account->password)) {
            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.emergency_login.failed',
                description: 'Emergency login failed.',
                properties: [
                    'reason' => 'invalid_credentials',
                    'status' => 'denied',
                    ...$this->auditLogService->requestContext($request),
                ],
            );

            throw ValidationException::withMessages([
                'username' => 'Invalid emergency credentials.',
            ]);
        }

        if ($this->accounts->isExpired($account)) {
            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_CLIENT_SECURITY,
                event: 'client_security.emergency_access.denied',
                description: 'Emergency access denied.',
                subject: $account,
                causer: $account,
                properties: [
                    'reason' => 'account_expired',
                    'status' => 'denied',
                    'emergency_account_id' => $account->getKey(),
                    'emergency_role' => $account->role,
                    ...$this->auditLogService->requestContext($request),
                ],
            );

            throw ValidationException::withMessages([
                'username' => 'Emergency account expired.',
            ]);
        }

        if (! $this->accounts->isIpAllowed($account, $request->ip())) {
            $this->auditLogService->logFailure(
                logName: AuditLogService::LOG_CLIENT_SECURITY,
                event: 'client_security.emergency_access.denied',
                description: 'Emergency access denied.',
                properties: [
                    'reason' => 'ip_not_allowed',
                    'status' => 'denied',
                    'emergency_account_id' => $account->getKey(),
                    'emergency_role' => $account->role,
                    ...$this->auditLogService->requestContext($request),
                ],
            );

            throw ValidationException::withMessages([
                'username' => 'Emergency access denied for this network.',
            ]);
        }

        $this->auth->guard('emergency')->login($account);
        $request->session()->regenerate();

        $account->forceFill(['last_used_at' => now()])->save();

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.emergency_login.succeeded',
            description: 'Emergency login succeeded.',
            subject: $account,
            causer: $account,
            properties: [
                'status' => 'authenticated',
                'emergency_account_id' => $account->getKey(),
                'emergency_role' => $account->role,
                ...$this->auditLogService->requestContext($request),
            ],
        );

        $this->auditLogService->logSuccess(
            logName: AuditLogService::LOG_CLIENT_AUTH,
            event: 'client_auth.emergency_session.established',
            description: 'Emergency session established.',
            subject: $account,
            causer: $account,
            properties: [
                'status' => 'authenticated',
                'emergency_account_id' => $account->getKey(),
                'emergency_role' => $account->role,
                ...$this->auditLogService->requestContext($request),
            ],
        );

        return $account;
    }

    public function logout(Request $request): void
    {
        /** @var EmergencyAccount|null $account */
        $account = $this->auth->guard('emergency')->user();

        if ($account instanceof EmergencyAccount) {
            $this->auditLogService->logSuccess(
                logName: AuditLogService::LOG_CLIENT_AUTH,
                event: 'client_auth.emergency_session.cleared',
                description: 'Emergency session cleared.',
                subject: $account,
                causer: $account,
                properties: [
                    'status' => 'logged_out',
                    'emergency_account_id' => $account->getKey(),
                    'emergency_role' => $account->role,
                    ...$this->auditLogService->requestContext($request),
                ],
            );
        }

        $this->auth->guard('emergency')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
