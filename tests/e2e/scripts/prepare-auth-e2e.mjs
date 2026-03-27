import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { authE2eConfig, clientLaravelEnv, serverLaravelEnv } from '../support/authE2eConfig.mjs';

function run(command, args, options = {}) {
    execFileSync(command, args, {
        stdio: 'inherit',
        ...options,
    });
}

function ensureRuntimeFile(filePath) {
    fs.mkdirSync(path.dirname(filePath), { recursive: true });

    if (! fs.existsSync(filePath)) {
        fs.writeFileSync(filePath, '');
    }
}

function ensureLaravelStorage(appRoot) {
    const directories = [
        path.join(appRoot, 'storage', 'logs'),
        path.join(appRoot, 'storage', 'framework', 'cache', 'data'),
        path.join(appRoot, 'storage', 'framework', 'sessions'),
        path.join(appRoot, 'storage', 'framework', 'testing'),
        path.join(appRoot, 'storage', 'framework', 'views'),
        path.join(appRoot, 'bootstrap', 'cache'),
    ];

    for (const directory of directories) {
        fs.mkdirSync(directory, { recursive: true });
    }
}

function removeHotFile(appRoot) {
    const hotFile = path.join(appRoot, 'public', 'hot');

    if (fs.existsSync(hotFile)) {
        fs.rmSync(hotFile, { force: true });
    }
}

function ensureBuild(appRoot) {
    const manifest = path.join(appRoot, 'public', 'build', 'manifest.json');

    if (process.env.E2E_BUILD === '1' || ! fs.existsSync(manifest)) {
        run(process.platform === 'win32' ? 'npm.cmd' : 'npm', ['run', 'build'], {
            cwd: appRoot,
        });
    }
}

function migrateClient() {
    ensureRuntimeFile(authE2eConfig.clientDatabase);

    run('php', ['artisan', 'migrate:fresh', '--seed', '--force'], {
        cwd: authE2eConfig.clientRoot,
        env: {
            ...process.env,
            ...clientLaravelEnv(),
        },
    });
}

function migrateServer() {
    ensureRuntimeFile(authE2eConfig.serverDatabase);

    run('php', ['artisan', 'migrate:fresh', '--seed', '--force'], {
        cwd: authE2eConfig.serverRoot,
        env: {
            ...process.env,
            ...serverLaravelEnv(),
        },
    });

    run('php', [
        path.join(authE2eConfig.clientRoot, 'tests', 'e2e', 'scripts', 'sync-server-client-secret.php'),
        authE2eConfig.serverRoot,
        authE2eConfig.sharedClientSecret,
        `${authE2eConfig.clientBaseUrl}/auth/sso/callback`,
    ], {
        cwd: authE2eConfig.clientRoot,
        env: {
            ...process.env,
            ...serverLaravelEnv(),
        },
    });
}

fs.mkdirSync(authE2eConfig.runtimeDir, { recursive: true });

removeHotFile(authE2eConfig.clientRoot);
removeHotFile(authE2eConfig.serverRoot);
ensureLaravelStorage(authE2eConfig.clientRoot);
ensureLaravelStorage(authE2eConfig.serverRoot);
ensureBuild(authE2eConfig.clientRoot);
ensureBuild(authE2eConfig.serverRoot);
migrateServer();
migrateClient();
