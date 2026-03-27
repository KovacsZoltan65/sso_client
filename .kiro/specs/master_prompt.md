# 🔥 SSO CLIENT — MASTER PROMPT (AI DEV MODE)

You are working on an **SSO CLIENT application**.

This is a **security-critical system**.

---

# 🎯 PRIMARY GOAL

Build a **clean, secure, minimal SSO client** that:

- delegates ALL authentication to the SSO server
- correctly implements redirect + callback flow
- maintains a safe authenticated session
- consumes protected APIs
- renders user-aware UI

---

# ⚠️ NON-NEGOTIABLE RULES

## ❌ NEVER DO THESE

- NEVER implement local authentication logic
- NEVER store passwords
- NEVER duplicate SSO server logic
- NEVER trust frontend data
- NEVER expose tokens in logs or responses
- NEVER hardcode SSO URLs
- NEVER over-engineer the solution
- NEVER introduce unnecessary libraries
- NEVER bypass validation
- NEVER ignore security checks (state, code, etc.)

---

## ✅ ALWAYS DO THESE

- ALWAYS validate `state` in callback
- ALWAYS validate inputs
- ALWAYS handle failure scenarios
- ALWAYS keep controllers thin
- ALWAYS move logic into services
- ALWAYS centralize API calls
- ALWAYS use configuration for endpoints
- ALWAYS treat tokens as sensitive data
- ALWAYS fail safely

---

# 🧱 ARCHITECTURE

## Backend

- Controller → Service pattern
- Controllers: orchestration only
- Services: business logic
- No repository layer unless strictly needed

## Frontend

- Vue 3 Composition API
- `<script setup>`
- PrimeVue components only
- No monolithic components
- API calls in service layer

---

# 🔐 AUTH FLOW (MANDATORY)

You MUST implement this flow:

## 1. Redirect

- Generate secure random `state`
- Store in session
- Redirect to SSO `/oauth/authorize`

## 2. Callback

Validate:

- `code` exists
- `state` exists
- `state === session_state`

Reject if invalid.

## 3. Token Exchange

- Call `/oauth/token`
- Handle failure properly
- NEVER expose token unnecessarily

## 4. User Info

- Call `/oauth/userinfo`
- Extract identity

## 5. Local Session

- Create authenticated session
- Store minimal data only

## 6. Logout

- Clear session
- Do not leave residual auth state

---

# 🔒 SECURITY MODEL

Treat everything as hostile input.

## MUST PROTECT AGAINST:

- CSRF (state mismatch)
- invalid redirect responses
- token leakage
- session fixation
- replay attacks (basic level)

---

# ⚙️ CONFIGURATION RULES

All SSO values MUST come from config:

- server_base_url
- authorize_endpoint
- token_endpoint
- userinfo_endpoint
- client_id
- redirect_uri
- scopes

❌ No hardcoded values in code

---

# 🧠 AUTH STATE DESIGN

Keep auth state:

- minimal
- centralized
- predictable

Preferred:

- simple service OR composable

Avoid:

- complex global state unless justified

---

# ⚠️ ERROR HANDLING

You MUST handle:

- missing `code`
- missing `state`
- invalid `state`
- token failure
- userinfo failure
- session missing
- unauthorized access

## RESPONSE RULES

| Status | Behavior          |
| ------ | ----------------- |
| 401    | redirect to login |
| 403    | show forbidden    |
| 422    | validation errors |
| 500    | generic error     |

Never expose sensitive details.

---

# 🖥️ FRONTEND RULES

- Show loading states
- Show user-friendly errors
- Never display sensitive data
- Separate UI from logic
- Use PrimeVue consistently

---

# 🧪 TESTING REQUIREMENTS

## Backend (required)

- redirect flow
- callback validation
- invalid state rejection
- token failure handling
- successful login
- logout

## Frontend (optional but preferred)

- login button behavior
- logout behavior
- auth state rendering

---

# 🧹 CODE QUALITY

- Follow Laravel conventions
- Use clean naming
- Avoid duplication
- No dead code
- No TODO spam
- Prefer readability over cleverness

---

# 🚧 CURRENT DEVELOPMENT PHASE

You are in:

➡️ **SSO CLIENT AUTH IMPLEMENTATION PHASE**

Focus ONLY on:

1. redirect flow
2. callback validation
3. token exchange
4. session creation
5. auth state exposure
6. route protection

---

# 🔮 FUTURE EXTENSIONS

Design must allow:

- OAuth2 expansion
- OpenID Connect support
- multiple providers
- role-based UI

---

# 🛑 DECISION RULE

If unsure:

1. Choose the **simplest secure solution**
2. If still unclear → STOP and ask

---

# 📌 FINAL PRINCIPLE

This app must remain a **thin, secure client**.

If logic becomes complex here → you are doing it wrong.
