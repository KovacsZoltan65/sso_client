<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Security Regression Suite

The `sso_client` project has a dedicated auth/security regression gate for the SSO client flow. The suite is intentionally narrow and protects redirect/callback validation, token exchange compatibility, session lifecycle, protected-route access, guest/authenticated UI exposure, and the test-environment guard that prevents accidental use of a non-test database.

Run the full gate locally with:

```bash
composer test:security
```

Run only one side when needed:

```bash
composer test:security:backend
npm run test:security
```

Backend tests enter the suite through the PHPUnit `security` group. Frontend tests enter through the curated include list in `vitest.security.config.js`. Add a test to this gate only when it protects auth, authorization, redirect/callback validation, session handling, re-auth behavior, or another security-critical client guarantee. Any auth flow, authorization, shared auth state, routing, validation, or logout change must update or add the corresponding security regression test before merge.

## Integration Contract

Server-client OAuth/SSO integration contract is defined in:

- [`docs/integration-contract.md`](/c:/wamp64/www/sso/sso_client/docs/integration-contract.md)

## Self-Service Profile UI

`/profile` is now a client-side orchestration surface on top of the upstream `sso_server` self-service profile API.

Current boundary:

- the client renders profile and password UI
- the client performs only UX validation and state orchestration
- the server owns final validation, password checks, hashing, persistence, and audit logging
- the client updates its shared auth user after successful remote profile fetch/update so the visible session state does not go stale

Current editable self-service fields:

- `name`

Current read-only self-service fields:

- `email`

Email stays read-only because the client still resolves its local session user by the authoritative upstream email claim.

## Browser Auth E2E

The client now ships with a Playwright auth-flow suite that drives a real browser through the `sso_client` -> `sso_server` redirect, login, callback, session, protected-route, and logout flow.

Local prerequisites:

```bash
cd ../sso_server && composer install && npm ci
cd ../sso_client && composer install && npm ci
npx playwright install chromium
```

Run the auth-focused suite from the client repository:

```bash
npm run e2e:auth
```

Useful overrides:

```bash
SSO_E2E_SERVER_PATH=../custom_server_clone npm run e2e:auth
E2E_BUILD=1 npm run e2e:auth
```

The setup prepares dedicated SQLite databases under [`.e2e-runtime`](/c:/wamp64/www/sso/sso_client/.e2e-runtime), reseeds the server with the seeded `superadmin@sso.test` operator, rewrites the `portal-client` secret to a deterministic E2E-only value, then starts both Laravel apps on `127.0.0.1:8010` and `127.0.0.1:8020`.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
