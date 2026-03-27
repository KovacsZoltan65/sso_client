import { spawn } from 'node:child_process';
import { authE2eConfig, clientLaravelEnv, serverLaravelEnv } from '../support/authE2eConfig.mjs';

const target = process.argv[2];

if (! ['client', 'server'].includes(target)) {
    console.error('Usage: node serve-auth-app.mjs <client|server>');
    process.exit(1);
}

const appRoot = target === 'client' ? authE2eConfig.clientRoot : authE2eConfig.serverRoot;
const port = String(target === 'client' ? authE2eConfig.clientPort : authE2eConfig.serverPort);
const envOverrides = target === 'client' ? clientLaravelEnv() : serverLaravelEnv();

const child = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${port}`], {
    cwd: appRoot,
    env: {
        ...process.env,
        ...envOverrides,
    },
    stdio: 'inherit',
});

child.on('exit', (code, signal) => {
    if (signal) {
        process.kill(process.pid, signal);
        return;
    }

    process.exit(code ?? 0);
});

process.on('SIGINT', () => child.kill('SIGINT'));
process.on('SIGTERM', () => child.kill('SIGTERM'));
