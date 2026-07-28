# 9. Client / Partner Project Sharing (Detailed)

Clients and partners need to view project info without logging into your admin system, and the two audiences see different scopes:

- **Client** → one project at a time: basic info, invoices, payments, change requests.
- **Partner** → all projects they're linked to, each with the same level of project detail.

Rather than building a full client-login portal (heavier, needs its own auth/password-reset flow), the simplest fit for a "share this" request is a **secure, revocable share link** — similar to a Google Doc share link. A full portal can always be layered on top later using the same underlying scope logic.

---

## 1. Where it lives

- On a **Project Detail Page**, a "Share with Client" button (visible only if the project has a Client assigned).
- On a **Party Detail Page** (§1.7, type = Partner or Vendor), a "Share with Partner" button.
- Both open the same Create Share Link form (§3), pre-filling the Shareable Record and Audience.

---

## 2. Share Links — List screen (global, plus filtered views on Project/Party pages)

**Columns:** Shareable Record (Project name or Party name), Audience (Client/Partner), Created Date, Created By, Expires, Last Viewed, View Count, Status (Active/Revoked/Expired), Actions (Copy Link / Regenerate / Revoke / Set Expiry / View Log)

**Filters:** Date range (created), Audience, Status, Project, Party

A link can only ever be **Active, Revoked, or Expired** — there's no "edit" on an existing link's scope; changing scope means creating a new one (keeps the audit trail of who saw what, when, unambiguous).

---

## 3. Create Share Link — fields

- Shareable Record *(pre-filled — the Project or the Party, required, read-only once created)*
- Audience *(Client / Partner — auto-set based on where the button was clicked, read-only)*
- Expires On *(optional date — default suggestion: 30 days from creation; "No Expiry" toggle available but discouraged)*
- Password Protect *(toggle, optional — if on, a password field appears; password is hashed, never shown again after creation, only resettable)*
- Allow PDF Downloads *(toggle, default ON — lets the visitor download invoice PDFs; can be turned off if you only want them to view, not download)*
- Notify Me When Viewed *(toggle — sends you a notification the first time the link is opened, and optionally on every subsequent view)*

**On Save:** generates a `token` — a random, non-sequential, 40+ character string (not a UUID derived from the record ID, to prevent guessing) — and builds the shareable URL `https://yourapp.com/share/{token}`.

**Actions after creation:** Copy Link, Send via Email (pre-filled template with the link), Send via WhatsApp (if WhatsApp integration exists elsewhere in the system), Regenerate (invalidates the old token, issues a new one — useful if a link was shared with the wrong person), Revoke, Set/Change Expiry, Reset Password.

---

## 4. What the visitor sees at `/share/{token}` (read-only, no login)

### Resolution logic
1. Look up `token` in `share_links`.
2. If not found → generic "This link is invalid or has expired" page (never reveal *why* — don't distinguish "not found" from "revoked" from "expired," to avoid leaking information to someone probing).
3. If `revoked_at` is set, or `expires_at` has passed → same generic invalid-link page.
4. If `password_hash` is set → show a password prompt first; on success, proceed (this check does not create a new session/account, just unlocks that page load).
5. Resolve `audience` + `shareable_type`/`shareable_id` and render the correct read-only view.

### Client view (`audience = client`, `shareable_type = Project`)
- **Interactive 5-Tab Navigation**:
  1. **Overview**: Project basic info, start/end dates, description, and **Billing Realization Progress Bar** (`% Paid`).
  2. **Invoices**: Invoice #, date, due date, amount, status badges, amber **`CR`** indicator badges for Change Request invoices, inline **View** button, and **PDF Download** button (if Allow PDF Downloads is on).
  3. **Payments**: Payment history date, amount, payment method (without internal bank account numbers).
  4. **Documents**: Project repository downloadable document files.
  5. **Change Requests**: Change request title/description, amount, status, **Uploaded File Attachments** (direct file pills), and **External Documentation Links** (Figma, Loom, Docs pills).
- **Explicitly excluded:** internal Notes, internal Interactions log, Budget figures, Commission Setup, any other project tab not listed above

### Partner view (`audience = partner`, `shareable_type = Party` where type includes Partner)
- Loads every project linked to that partner (via `project_party` where role = partner, or via `project_commissions` if the partner earns via commission — see §5, Project Commission Setup)
- Renders each project as an expandable card/accordion using the same detail block as the Client view above
- **Additionally shows** (partner-specific): their commission summary for each project — Commission Type, Total Commission, Paid, Payable — since a partner has a legitimate interest in seeing what they're owed, unlike a client
- Same exclusions as Client view otherwise (no internal notes/interactions)

### Visual treatment
- A persistent "Shared view — read only" banner at the top, so it never gets confused with the internal admin UI.
- No navigation to anything outside the shared scope — the visitor cannot browse to other projects, other clients, or any admin URL.

---

## 5. Visit Log

Each page load (after successful password check, if applicable) records a row:
- Token, IP Address, User Agent, Viewed At, (optionally) Referrer

Shown on the Share Links list as **View Count** + **Last Viewed**, and available as a full log via the "View Log" action — useful for confirming "did they actually open the invoice I sent them."

---

## 6. Security rules

- Scope is always resolved **server-side from the token** — never from a query parameter, project ID, or anything the visitor can edit in the URL. A client can never widen their view by guessing another project's ID.
- Tokens are single-purpose: one token → one shareable record → one audience. A partner link cannot be repurposed to view a project it isn't linked to, even by URL manipulation.
- Revoking is instant and requires no client-side cache invalidation — the very next request re-checks `revoked_at`.
- Rate-limit password attempts on protected links (e.g., 5 attempts per IP per hour) to prevent brute-forcing a short password.
- Share links should never be indexable — `noindex` meta tag + `robots.txt` disallow on the `/share/` path, so links don't end up in search engines if accidentally made public.

---

## 7. Data model

```
share_links (id, token, shareable_type, shareable_id, audience, expires_at,
             password_hash, allow_downloads, notify_on_view, revoked_at,
             created_by, created_at)
share_link_visits (id, share_link_id, ip_address, user_agent, referrer, viewed_at)
```

---

## 8. Extra suggestions

- **Default auto-expiry** (e.g., 30 days) so stale links don't linger indefinitely — surfaced as a system-wide setting, overridable per link.
- **Bulk revoke** — a "Revoke all links for this project/party" action, useful when a project ends or a client relationship changes.
- **Branded share page** — apply the department's Document Template header image (§1.5) to the top of the shared view too, so it feels consistent with the invoices the client already receives.
- **Upgrade path** — if two-way interaction is ever needed (client approving a change request, uploading a file, commenting), that's the natural trigger to build a full login-based client portal. The share-link version is intentionally read-only by design; don't be tempted to bolt write-actions onto it, since that reintroduces the auth/session complexity this approach was meant to avoid.