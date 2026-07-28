# Project Documents

Lets a project keep a running library of documents — agreements, change request attachments, proposals, NDAs, etc. — where each entry can be either an **uploaded file** or an **external link** (e.g., a Google Doc, Drive folder, Figma file, Notion page).

---

## 1. Where it lives

- **Project Detail Page** — a "Documents" tab, next to Invoices, Payments, Change Requests, Commission Setup, Invoice Schedule, Payment Milestones.

---

## 2. Documents — List (within a project)
**Columns:** Name, Type (Agreement / Change Request / Proposal / NDA / Other), Source (File / Link), Uploaded/Added By, Date, Tags, Actions (View/Open / Download* / Edit / Delete)

*Download only shown for File-type entries; Link-type entries show "Open" instead, which opens the URL in a new tab.

**Filters:** Date range, Type, Source (File/Link), Tag

---

## 3. Create / Edit Document — fields
- Name / Title *(required)*
- Type *(dropdown: Agreement / Change Request / Proposal / NDA / Invoice-related / Other — required)*
- Source *(radio: Upload File / Add Link — required)*
  - **Upload File**: File upload *(required — PDF, DOCX, XLSX, images, etc.)*
  - **Add Link**: URL *(required, validated as a proper URL)*, Link Label (optional — e.g., "View on Google Drive")
- Linked Change Request *(dropdown, optional — if this document relates to a specific CR from §3.7)*
- Date *(defaults to today, editable — e.g., to reflect the agreement's actual signing date rather than upload date)*
- Tags (multi-select)
- Notes / Description (free text)
- Visibility *(toggle: Internal Only / Visible to Client via Share Link — ties into §10 Client/Partner Sharing, off by default)*

**Delete:** soft-delete; keeps an audit trail entry (who deleted, when) since agreements/CRs are often needed for reference later.

---

## 4. Behavior notes
- A document isn't locked to one type of source forever — editing a File entry can swap it to a Link (and vice versa), replacing the prior value.
- File uploads reuse the same polymorphic attachments pattern already used elsewhere in the system (transactions, loans, invoices) — one storage mechanism, not a separate one just for projects.
- Uploading a new **version** of the same document (e.g., an amended agreement) is handled as a new Document entry rather than overwriting — older versions stay listed and downloadable, sorted newest-first, optionally grouped by Name so version history is visually clear.
- Link-type entries are validated but not crawled/scraped — the system just stores and opens the URL; it doesn't need permission to access whatever's behind it (e.g., a private Drive folder the client controls).

---

## 5. Extra suggestions
- **Expiry reminder**: optional Expiry Date field on Agreement-type documents, feeding into the unified Reminders engine (§7) — e.g., "Contract with Client X expires in 30 days."
- **Quick-access from Change Requests**: when creating/editing a Change Request (§3.7), a "Attach Document" shortcut that either uploads directly or links an existing project document, rather than duplicating the upload flow.
- **Document count badge** on the Documents tab and on the project card in the project list, so it's visible at a glance whether a project has its paperwork in order.
- Respect the same **role/permission** rules as attachments elsewhere — e.g., a Viewer role might see documents but not delete them.

---

## 6. Data model
```
project_documents (project_id, name, type, source_type [file/link], file_path (nullable),
                    url (nullable), link_label (nullable), change_request_id (nullable),
                    date, tags, notes, visible_to_client, created_by, deleted_at)
```