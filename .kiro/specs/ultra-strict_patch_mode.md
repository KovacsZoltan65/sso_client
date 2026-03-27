# 🔒 ULTRA-STRICT PATCH MODE (SSO CLIENT)

You are working on a **security-critical SSO CLIENT application**.

This mode enforces **controlled, minimal, safe code changes**.

---

# 🚨 HARD RULES (NON-NEGOTIABLE)

## ❌ YOU MUST NOT

- DO NOT rewrite entire files unless explicitly required
- DO NOT refactor unrelated code
- DO NOT rename files, classes, or variables unless necessary
- DO NOT introduce new architecture layers
- DO NOT add new libraries
- DO NOT remove existing functionality
- DO NOT change coding style arbitrarily
- DO NOT move files unless explicitly required
- DO NOT touch unrelated modules

---

## ✅ YOU MUST

- Make **minimal, surgical changes only**
- Modify **only files required** for the task
- Preserve **existing behavior**
- Follow **existing patterns in the project**
- Keep controllers thin
- Put logic into services when needed
- Respect all rules from `MASTER PROMPT`

---

# 🧠 THINKING PROCESS (MANDATORY)

Before writing code:

1. Identify EXACTLY what needs to change
2. List affected files
3. Confirm no unrelated files are touched
4. Choose the smallest possible implementation

---

# 📦 OUTPUT FORMAT (STRICT)

You MUST output changes in **PATCH FORMAT ONLY**

For each file:
FILE: path/to/file

--- BEFORE
<only the relevant part of the original code>

+++ AFTER
<modified version>

---

## ⚠️ RULES FOR PATCH OUTPUT

- DO NOT output full files unless absolutely necessary
- DO NOT include unchanged code
- DO NOT explain inside code blocks
- DO NOT mix explanation with patch
- KEEP patches minimal and readable

---

# 🧾 AFTER PATCH SECTION

After all patches, include a short summary:

- What was changed
- Why it was necessary
- Any risks or assumptions

---

# 🔐 SSO-SPECIFIC RULES

You MUST:

- Preserve auth flow integrity
- NEVER break:
    - state validation
    - token exchange
    - session handling

- NEVER expose:
    - tokens
    - secrets

- ALWAYS handle:
    - invalid state
    - missing code
    - API failure

---

# ⚠️ WHEN TO STOP

If the task requires:

- large refactor
- unclear architectural change
- modifying multiple unrelated systems

👉 STOP  
👉 ASK for clarification

DO NOT GUESS

---

# 🎯 GOAL

Make the **smallest possible safe change**  
to achieve the **requested functionality**

---

# 📌 FINAL PRINCIPLE

You are not rewriting the system.

You are performing **precise surgery**.
