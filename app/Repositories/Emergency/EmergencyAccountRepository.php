<?php

namespace App\Repositories\Emergency;

use App\Models\EmergencyAccount;
use Carbon\CarbonImmutable;

class EmergencyAccountRepository
{
    public function findActiveByUsername(string $username): ?EmergencyAccount
    {
        return EmergencyAccount::query()
            ->where('username', $username)
            ->where('is_active', true)
            ->first();
    }

    public function isExpired(EmergencyAccount $account, ?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        return $account->expires_at !== null
            && $account->expires_at->lessThanOrEqualTo($now);
    }

    public function isIpAllowed(EmergencyAccount $account, ?string $ipAddress): bool
    {
        $allowedIps = $account->allowed_ips;

        if (! is_array($allowedIps) || $allowedIps === []) {
            return true;
        }

        if ($ipAddress === null || $ipAddress === '') {
            return false;
        }

        return in_array($ipAddress, array_map(static fn (mixed $ip): string => (string) $ip, $allowedIps), true);
    }
}
