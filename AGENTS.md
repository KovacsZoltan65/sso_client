# 🔐 SSO CLIENT — AGENTS.md

## 🎯 PROJECT PURPOSE

This application is an **SSO CLIENT**.

It does NOT implement authentication logic itself.

It relies entirely on the **SSO SERVER** for:

- authentication
- authorization
- token issuing
- user identity

The client is responsible for:

- initiating SSO login
- handling redirect/callback
- maintaining authenticated session
- consuming protected APIs
- rendering user-specific UI

---

## ⚠️ CORE RULE

🚫 NEVER implement authentication logic locally  
🚫 NEVER duplicate SSO server business logic  
✅ ALWAYS rely on SSO server responses

---

## 🧱 ARCHITECTURE RULES

### Backend

- Use **Controller + Service pattern**
- Controllers must remain **thin**
- Business logic goes into **Services**
- No unnecessary abstraction layers

### Frontend

- Vue 3 + `<script setup>`
- Use **PrimeVue components only**
- Keep components **small and focused**
- Separate UI from API logic

### API Layer

- Centralize all HTTP calls in **services**
- No direct API calls inside components

---

## 🔐 AUTH FLOW (MANDATORY)

The application MUST follow this flow:

### 1. Login Start

- Redirect user to SSO server `/oauth/authorize`

### 2. State Protection

- Generate secure random `state`
- Store in session
- Validate on callback

### 3. Callback Handling

- Validate:
    - `code` exists
    - `state` matches session
- Reject if invalid

### 4. Token Exchange

- Call SSO `/oauth/token`
- NEVER expose tokens to frontend unnecessarily

### 5. User Info

- Call `/oauth/userinfo`
- Build local authenticated session

### 6. Session Handling

- Store only minimal required data
- No sensitive token leakage

### 7. Logout

- Clear session completely
- Optionally redirect to SSO logout later

---

## 🔒 SECURITY RULES (STRICT)

- NEVER store secrets in plain text
- NEVER log tokens or sensitive data
- ALWAYS validate inputs
- ALWAYS validate `state`
- ALWAYS validate redirect responses
- ALWAYS handle failure cases

Treat this as a **security-critical system**

---

## ⚠️ ERROR HANDLING

Handle all API responses consistently:

| Status | Action                 |
| ------ | ---------------------- |
| 401    | Redirect to SSO login  |
| 403    | Show forbidden message |
| 422    | Show validation errors |
| 500    | Show generic error     |

NEVER expose internal errors to users

---

## 🧠 AUTH STATE MANAGEMENT

Keep auth state:

- simple
- centralized
- minimal

Recommended:

- composable OR small service

Avoid:

- complex global stores unless necessary

---

## 🖥️ UX RULES

- Always show clear loading states
- Always handle errors gracefully
- Never expose sensitive data
- Keep UI clean and responsive

---

## 📦 CONFIG RULES

All SSO-related values MUST come from config:

- server_base_url
- authorize_endpoint
- token_endpoint
- userinfo_endpoint
- client_id
- redirect_uri
- scopes

🚫 NEVER hardcode URLs

---

## 🧪 TESTING RULES

### Backend

Must cover:

- redirect flow
- callback validation
- token exchange failure
- successful authentication
- logout

### Frontend (optional but preferred)

- basic auth UI behavior
- login / logout states

---

## 🧹 CODE QUALITY RULES

- Follow Laravel conventions
- Prefer clarity over cleverness
- No dead code
- No TODO spam
- No over-engineering

---

## 🚀 CURRENT DEVELOPMENT PHASE

The project is currently in:

➡️ **SSO FLOW IMPLEMENTATION PHASE**

Priority tasks:

1. redirect → callback flow
2. token exchange
3. user session creation
4. auth state exposure
5. route protection

---

## 🔮 FUTURE GOALS

The system should be extendable to:

- full OAuth2 support
- OpenID Connect
- multiple SSO providers
- role-based UI

---

## 🛑 WHEN UNSURE

If something is unclear:

👉 STOP  
👉 ASK for clarification  
👉 DO NOT GUESS

---

## 📌 FINAL PRINCIPLE

This app is a **thin client over a secure SSO system**

If logic starts to grow here → you're doing it wrong.
