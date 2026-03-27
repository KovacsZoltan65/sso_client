<?php

declare(strict_types=1);

use App\Models\SsoClient;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

if ($argc < 3) {
    fwrite(STDERR, "Usage: php sync-server-client-secret.php <server-root> <shared-secret> [redirect-uri]\n");
    exit(1);
}

$serverRoot = $argv[1];
$sharedSecret = $argv[2];
$redirectUri = $argv[3] ?? null;

if (! is_dir($serverRoot)) {
    fwrite(STDERR, "Server root not found: {$serverRoot}\n");
    exit(1);
}

$autoloadPath = $serverRoot.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
$bootstrapPath = $serverRoot.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';

if (! file_exists($autoloadPath) || ! file_exists($bootstrapPath)) {
    fwrite(STDERR, "Server dependencies are not installed or bootstrap files are missing.\n");
    exit(1);
}

chdir($serverRoot);

require $autoloadPath;

$app = require $bootstrapPath;
$app->make(Kernel::class)->bootstrap();

$client = SsoClient::query()
    ->with('secrets')
    ->where('client_id', 'portal-client')
    ->firstOrFail();

$client->secrets()->update([
    'is_active' => false,
    'revoked_at' => now(),
]);

$client->secrets()->create([
    'name' => 'Browser auth E2E shared secret',
    'secret_hash' => Hash::make($sharedSecret),
    'last_four' => substr($sharedSecret, -4),
    'is_active' => true,
    'revoked_at' => null,
]);

$client->forceFill([
    'client_secret_hash' => Hash::make($sharedSecret),
])->save();

if (is_string($redirectUri) && $redirectUri !== '') {
    $client->forceFill([
        'redirect_uris' => [$redirectUri],
    ])->save();

    $redirectUriHash = hash('sha256', $redirectUri);

    $client->redirectUris()->updateOrCreate(
        ['uri_hash' => $redirectUriHash],
        [
            'uri' => $redirectUri,
            'is_primary' => true,
        ],
    );

    $client->redirectUris()
        ->where('uri_hash', '!=', $redirectUriHash)
        ->delete();
}

fwrite(STDOUT, "Synced portal-client secret for browser E2E.\n");
