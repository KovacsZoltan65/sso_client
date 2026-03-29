import { fileURLToPath } from "node:url";
import path from "node:path";

const currentFile = fileURLToPath(import.meta.url);
const supportDir = path.dirname(currentFile);
const e2eDir = path.resolve(supportDir, "..");
const clientRoot = path.resolve(e2eDir, "..", "..");
const configuredServerPath =
    process.env.SSO_E2E_SERVER_PATH ?? path.join("..", "sso_server");
const serverRoot = path.resolve(clientRoot, configuredServerPath);
const runtimeDir = path.resolve(clientRoot, ".e2e-runtime");

const clientPort = Number(process.env.SSO_E2E_CLIENT_PORT ?? 8010);
const serverPort = Number(process.env.SSO_E2E_SERVER_PORT ?? 8020);

const clientBaseUrl =
    process.env.SSO_E2E_CLIENT_BASE_URL ?? `http://127.0.0.1:${clientPort}`;
const serverBaseUrl =
    process.env.SSO_E2E_SERVER_BASE_URL ?? `http://127.0.0.1:${serverPort}`;

const clientDatabase = path.join(runtimeDir, "client-e2e.sqlite");
const serverDatabase = path.join(runtimeDir, "server-e2e.sqlite");
const sharedClientSecret =
    process.env.SSO_E2E_CLIENT_SECRET ?? "e2e-client-secret";
const clientAppKey =
    process.env.SSO_E2E_CLIENT_APP_KEY ??
    "base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=";
const serverAppKey =
    process.env.SSO_E2E_SERVER_APP_KEY ??
    "base64:ZmVkY2JhOTg3NjU0MzIxMGZlZGNiYTk4NzY1NDMyMTA=";

export const authE2eConfig = {
    clientRoot,
    serverRoot,
    runtimeDir,
    clientPort,
    serverPort,
    clientBaseUrl,
    serverBaseUrl,
    clientDatabase,
    serverDatabase,
    sharedClientSecret,
    seededServerUser: {
        email: process.env.SSO_E2E_USER_EMAIL ?? "superadmin@sso.test",
        password: process.env.SSO_E2E_USER_PASSWORD ?? "password",
        name: process.env.SSO_E2E_USER_NAME ?? "SSO Superadmin",
    },
};

export function clientLaravelEnv() {
    return {
        APP_ENV: "e2e",
        APP_DEBUG: "true",
        APP_KEY: clientAppKey,
        APP_URL: authE2eConfig.clientBaseUrl,
        DB_CONNECTION: "sqlite",
        DB_DATABASE: authE2eConfig.clientDatabase,
        SESSION_DRIVER: "file",
        SESSION_COOKIE: "sso_client_e2e_session",
        CACHE_STORE: "array",
        QUEUE_CONNECTION: "sync",
        LOG_CHANNEL: "stderr",
        SSO_SERVER_BASE_URL: authE2eConfig.serverBaseUrl,
        SSO_AUTHORIZE_ENDPOINT: "/oauth/authorize",
        SSO_TOKEN_ENDPOINT: "/api/oauth/token",
        SSO_USERINFO_ENDPOINT: "/api/oauth/userinfo",
        SSO_CLIENT_ID: "portal-client",
        SSO_CLIENT_SECRET: authE2eConfig.sharedClientSecret,
        SSO_REDIRECT_URI: `${authE2eConfig.clientBaseUrl}/auth/sso/callback`,
        SSO_SCOPES: "openid profile email",
        SSO_LOCAL_AUTH_ENABLED: "false",
    };
}

export function serverLaravelEnv() {
    return {
        APP_ENV: "e2e",
        APP_DEBUG: "true",
        APP_KEY: serverAppKey,
        APP_URL: authE2eConfig.serverBaseUrl,
        DB_CONNECTION: "sqlite",
        DB_DATABASE: authE2eConfig.serverDatabase,
        SESSION_DRIVER: "file",
        SESSION_COOKIE: "sso_server_e2e_session",
        CACHE_STORE: "array",
        QUEUE_CONNECTION: "sync",
        LOG_CHANNEL: "stderr",
        SESSION_SECURE_COOKIE: "false",
        SESSION_SAME_SITE: "lax",
    };
}
