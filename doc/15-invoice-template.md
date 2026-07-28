# Invoice PDF Template & Download

Every project generates its downloadable invoice PDF using its **department's Document Template** — so the header image, footer image, bank details, and description are configured once per department and automatically reused for every project/invoice under it.

---

## 1. Where the branding comes from (Document Template — §1.5, master data)

This isn't new — it reuses the template already configured in master data. Recap of the fields that feed into the PDF:

- Header Image (upload)
- Footer Image (upload)
- Company Details block (name, address, phone, email, tax ID)
- Bank Details block (bank name, account no., branch, SWIFT code, account holder)
- Description / Terms & Conditions (rich text — this is the "payments can be transferred to..." line)
- Language (English/Tamil)
- Is Default (per company/department/type)

A department can have more than one template (e.g., different language versions); the invoice picks whichever template is marked Default unless the invoice explicitly overrides it.

---

## 2. Invoice PDF — what actually renders

| Section | Source |
|---|---|
| Header banner (full width, top) | Department's Header Image |
| "INVOICE" title | Static |
| Project / Client / Date / Invoice # | Live invoice + project data |
| Line items table (Description, Amount) | Invoice's line items (§3.5) |
| Total row | Sum of line items |
| Advance Paid row *(shown only if non-zero)* | Invoice's recorded advance/prior payment |
| Grand Total row | Total − Advance Paid |
| Bank details paragraph | Department's Bank Details + Description |
| Signature block | Signee Name, Signee Title, optional Signature Image |
| Footer banner (full width, bottom) | Department's Footer Image |

---

## 3. Download action — where it lives

- On the **Project Detail Page → Invoices tab**, each invoice row has a **Download PDF** action.
- On the **All Invoices Page** (§6), same action is available per row, plus a bulk "Export Selected as PDF" (zips multiple invoices).
- Download uses the invoice's locked-in template snapshot (see §4) — never the live/current template — so old invoices always render exactly as they looked when issued, even if the department's header/footer/bank details change later.

---

## 4. Template snapshot rule (important)

When an invoice is generated (manually or via the Auto-Generation Schedule), the system copies the department's **current** template data (header image reference, footer image reference, bank details, description) onto the invoice record itself — not just a foreign key to the template.

- Editing or replacing a department's template later has **zero effect** on already-issued invoices.
- Only new invoices generated after the change pick up the updated branding.
- This mirrors the versioning rule already noted in §1.5.1 of the master data spec.

---

## 5. Fields required on the Invoice record to support this

```
invoice_number, invoice_date, due_date, currency
project_id, client_id (denormalized at generation time for the snapshot)
line_items[] (description, quantity, unit_price, tax_percent, line_total)
subtotal, advance_paid, grand_total
signee_name, signee_title, signature_image (nullable)
template_snapshot (json: header_image_path, footer_image_path, bank_details, description, company_details)
```

---

## 6. Implementation

- **File:** `resources/views/invoices/pdf.blade.php` — a Blade template rendering the layout above, built to work with `barryvdh/laravel-dompdf`.
- **Trigger:** `PDF::loadView('invoices.pdf', ['invoice' => $invoice])->download("invoice-{$invoice->invoice_number}.pdf")`
- Data passed to the view is read entirely from the invoice's own `template_snapshot` + line items — no live joins to the department template table needed at render time, keeping historical invoices stable per §4.
- Header/footer images are stored (e.g., S3 or local disk) and referenced by path/URL in the snapshot; dompdf needs either a public URL or a local file path it can read directly.

---

## 7. Extra suggestions

- **Preview before download** — an in-app modal rendering the same Blade view as HTML (not yet as PDF) so the user can sanity-check line items before generating the final file.
- **Re-send vs re-download** — downloading never regenerates the snapshot; if a genuine correction is needed on a sent invoice, that should go through a proper "Cancel + Reissue" flow rather than silently editing a sent PDF's source data.
- **Multi-language invoices** — since Document Templates already support a Language field, the invoice could store which language template was used, so a client-facing re-download always matches what was originally sent.
