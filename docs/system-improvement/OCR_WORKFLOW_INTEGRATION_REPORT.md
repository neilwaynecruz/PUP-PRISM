# OCR workflow integration and PDF validation

Date: 12 November 2026. Branch: `main`.
Status: **PARTIALLY VERIFIED**. Identifier and document-assistance integrations are implemented. Synthetic automated checks are recorded below; physical-device, permitted real-label/document accuracy, and a fresh complete application regression remain outstanding. This is not production sign-off.

This report supersedes earlier broad statements that every check passed without warnings, that all captured screenshots had already been inspected, or that the feature was universally production-ready. Only the evidence below is claimed.

## Baseline, preservation, and plan

Assets already used Tesseract.js with a local English model, lazy-loaded review dialog, image validation, cancellation, and authorized asset search. It accepted JPEG/PNG/WebP, not PDFs.

Initial Git changes included generated routes, the previous registration-test correction, and the untracked `PUP-PRISM-Taglish-Guide/` folder. These remain preserved. No migration, active-database reset, business-record reseed, commit, push, merge, or deployment was performed. SQLite E2E resets and PostgreSQL checks use isolated testing databases.

Implementation checklist:
- Reuse one recognition engine and shared review dialog.
- Separate identifier recognition from conservative document-field suggestions.
- Integrate Receiving, Maintenance, Transfers, Handover, Physical Counts, and procurement.
- Preserve explicit review, normal authorization, transaction safeguards, QR, and manual alternatives.
- Validate image workflows before extending the shared reader to PDF.
- Add actual-engine PDF tests and inspect rendered previews.
- Record limitations, build warnings, and test-run cleanup problems honestly.

Applicable project skills guided Laravel validation, Vue/Inertia forms, Wayfinder routes, testing, and accessible responsive controls. Boost/search-docs was unavailable; official documentation and installed API types were used as fallback.

## Shared architecture and approved PDF extension

- `OcrAssistButton.vue` lazy-loads the existing `AssetOcrDialog.vue`; no per-module OCR engine was introduced.
- `assetOcr.ts` uses sparse text segmentation for labels and automatic page segmentation for documents.
- `ocrReview.ts` defines typed draft suggestions and conservative labelled reference/date/supplier/product-code extraction. Calendar-invalid and ambiguous dates require correction.
- Users may review, edit, reject, and explicitly apply suggestions. OCR does not approve, finalize, or create transactions.
- Cancellation/stale-result guards, temporary canvas cleanup, and focus restoration are retained.

The user explicitly approved `pdfjs-dist` and local worker assets. Version **6.5.438** is pinned in package.json/package-lock.json. Its legacy renderer and worker load only when PDF input is chosen; Tesseract.js itself does not directly decode PDFs.

`pdfOcr.ts` opens local PDF bytes, validates size/signature, renders a selected page, then passes that canvas to the existing OCR engine. All existing OCR entry points share this path:
- Assets
- Receiving (tags, serials, delivery references)
- Maintenance lookup
- Asset Transfer lookup
- Handover lookup
- Physical Count session lookup
- Purchase Order/procurement document assistance

PDF scope:
- Unlocked PDFs up to 8 MB and 20 pages.
- One page at a time, explicit page selection and re-read.
- Changing pages clears prior suggestions.
- Local preview for source comparison; bounded rendering to a 2000-pixel longest side.
- Digital-text PDF pages and scanned JPEG-image PDF pages are exercised with actual rendering and OCR.
- Closing the dialog closes the PDF session; worker-termination regression added.
- No attachments, interactive forms, annotations, signature verification, or document authenticity assessment.
- No batch import of all pages, automatic table association, invoice accounting, or guaranteed support for every PDF font/codec.
- Complex fonts/encodings, uncommon image codecs, encrypted documents, and real procurement layouts require further sample validation. Renderer auxiliary font/CMap/WASM resources are not packaged as a universal PDF-viewer deployment.

## Module integration and safeguards

### Assets, Maintenance, and Transfers

Assets retains its existing tag/serial lookup. Contextual Maintenance and Transfer triggers search existing authorized records and provide explicit candidate links to the existing asset-detail sections. They do not submit maintenance work, select destinations, or transfer custody. PDF and image results use the same editable identifier review.

### Receiving

Single, batch, and purchase-order receiving support reviewed tags, delivery references, and optional manufacturer serials. Serial fields are keyed to institutional tags, then submitted as an aligned optional list. Manufacturer serials never replace institutional tags.

Form Requests enforce nullable string/list rules, maximum lengths, distinct values, and existing-serial uniqueness. InventoryService validates mapping length, normalizes blank serials, persists serial/tag pairs, and retains transactional receipt creation. The existing database serial uniqueness constraint remains authoritative; no migration was necessary.

Partial receiving, PO quantity limits, duplicate prevention, stock updates, and explicit confirmation remain unchanged. OCR never determines physically received quantity or condition.

### Handover

Reviewed text populates eligible asset-candidate search. Serial matching was added inside the existing grouped query, preserving status/authorization restrictions. Asset, recipient, verification, acceptance, and final handover actions remain explicit.

### Physical Counts

Reviewed identifiers filter only current session lines. Outside-scope/no-match feedback is provided. OCR never fills observed counts, substitutes expected stock, or posts reconciliation adjustments.

### Purchase Orders and procurement documents

Images and selected PDF pages suggest labelled reference, ISO document date, supplier text, and product code. Users review draft fields, explicitly choose existing suppliers, and search catalog products. Applying references/dates to notes is a separate action; the document date never becomes the delivery date.

Prices, quantities, units, currencies, taxes, totals, supplier creation, and order submission are not automated. No reliable line-item parser is claimed.

## Security and privacy

- Recognition/rendering use local browser files and same-origin engine/model/worker assets, not an external OCR service.
- Photos, PDFs, and raw extracted text are not persisted to browser storage by this feature.
- OCR source text is rendered as text, not HTML.
- Authorized lookup and normal submission validation remain mandatory.
- Cancelling or failing OCR leaves manual lookup available.
- Local recognition is not a claim of offline transaction support or fully verified offline OCR.
- Approved dependency installation and `npm audit --omit=dev` reported zero vulnerabilities at installation time.
- No secrets or server configuration were exposed.

## Recognition evaluation

Tests use synthetic in-memory tags, serial images, digital PDF pages, and scanned-image PDFs. They are not permitted real institutional samples or a statistically representative accuracy study.

An initial invoice fixture `INV-OCR-901` was recognized as `INV-OCR-9@1`. That failure is retained in artifacts. Document segmentation was adjusted; current positive fixtures use an unambiguous reference and tests separately require human correction of ambiguous text. This does not prove the original ambiguous label is always recognized correctly.

No percentage accuracy, real-device speed improvement, or physical-camera claim is made. Actual blurred/glared labels, O/0 and I/1 cases, and redacted supplier documents still need evaluation. Initial and subsequent PDF/OCR downloads have different costs.

## Actual test commands and recorded outcomes

### Backend regression, before PDF-only changes

`php artisan test --compact tests/Feature/Inventory/ReceivingOcrSerialTest.php tests/Feature/Inventory/ReceivingCheckoutTest.php tests/Feature/Inventory/BatchReceivingTest.php tests/Feature/Inventory/PurchaseOrderReceivingTransactionsTest.php tests/Feature/Inventory/PurchaseOrderWorkflowTest.php tests/Feature/Inventory/HandoverWorkflowTest.php tests/Feature/Inventory/HandoverVerificationTest.php tests/Feature/Inventory/AssetMaintenanceWorkflowTest.php tests/Feature/Inventory/AssetTransferWorkflowTest.php tests/Feature/Inventory/PhysicalCountWorkflowTest.php tests/Feature/Search/AssetLookupTest.php`

Recorded result: **73 passed, 408 assertions, 42.07s**. A fresh post-PDF validation run also passed **73 tests, 408 assertions, 63.88s** using the isolated test databases.

`php artisan test --compact tests/Feature/Inventory/PurchaseOrderPostgresReceivingConcurrencyTest.php tests/Feature/Inventory/PurchaseOrderPostgresConcurrencyTest.php`

Recorded result: **2 passed, 7 assertions, 1.57s**. PostgreSQL testing connection was separate from the active database.

Receiving serial-specific regression: **9 passed, 39 assertions**, including single/batch/partial PO mapping, invalid/duplicate serial rejection, and rollback/uniqueness behavior.

`vendor/bin/pint --dirty --format agent`: passed.

### Image workflow and responsive baseline

- Initial Chromium workflow/Assets run: **13 passed, 1 failed**; the ambiguous invoice reference above caused the failure.
- After correction, the focused workflow plus actual-image/cancellation selection across Chromium, Firefox, and WebKit reported **27 passed (10.4m)** with retries disabled.
- Important runner limitation: after all 27 test bodies passed, a Windows WebKit worker remained alive after its browser exited. Only that verified stuck worker was terminated; the runner then exited 0. This was **not a clean unattended run** and does not prove CI shutdown stability.
- `npx playwright test tests/e2e/ocr-responsive-review.spec.ts --project=chromium --workers=1 --retries=0`: the fresh run's `.last-run.json` reports passed; four cases generated 12 screenshots. Exact stdout duration was not recovered after session interruption.

### PDF validation — current pass

`npx playwright test tests/e2e/ocr-pdf.spec.ts --project=chromium --workers=1 --retries=0 --trace=retain-on-failure`

Initial result: **8 passed (2.5m)**. A ninth worker-cleanup regression was then added and passed in the final Chromium regression below. All nine PDF scenarios were therefore exercised on each of the three browser engines.

`npx playwright test tests/e2e/ocr-pdf.spec.ts --project=firefox --project=webkit --workers=1 --retries=0 --trace=retain-on-failure`

All **18 test bodies passed** (9 Firefox, 9 WebKit), including actual scanned/digital PDF recognition, explicit workflow boundaries, limits, page changes, and worker termination. The runner reported **18 passed (8.9m)** only after manual cleanup of its verified stuck Windows WebKit worker (PID 18780, parent runner 24092). The WebKit browser had already exited; an orphaned network process was also observed and disappeared before a cleanup attempt reached it. Exit code was 0 after worker cleanup, **not a clean unattended shutdown**. This repeats the earlier runner limitation and must be investigated before claiming CI stability.

Final command: `npx playwright test tests/e2e/ocr-pdf.spec.ts tests/e2e/asset-ocr.spec.ts tests/e2e/ocr-responsive-review.spec.ts --project=chromium --grep 'Closing PDF review|invalid image types|actual local OCR|cancelling while OCR|fits phone tablet' --workers=1 --retries=0 --trace=retain-on-failure`.

Result: **8 passed (2.3m), clean exit 0**, using the unchanged E2E build. This covers PDF worker cleanup, original image OCR/cancellation/invalid files, and four responsive-dialog cases. The current PDF validation stage totals **34 passing browser executions: 27 PDF scenarios plus 7 image/layout regressions**, with the cross-browser runner-cleanup caveat above. No timeout or retry settings were changed.

### Static checks and build

- `npm run type-check`: passed after PDF integration and cleanup-test addition.
- `npm run lint:check`: final repository-wide check passed. The ninth test initially added two blank-line lint errors, corrected and rechecked without disabling rules.
- `npm run format:check`: passed.
- `npm run build:e2e`: passed, 22.32s.
- Final `npm run build`: passed, **23.03s**, after browser verification ended. The normal application build is restored; this is a local build, not a deployment. The lazy PDF chunk warning and Vite plugin-timing notice remain visible, not suppressed.
- PDF renderer lazy chunk: approximately 500.21 KB / 152.69 KB gzip; local worker approximately 1327.59 KB. Vite emitted a >500 KB chunk warning. It was not suppressed. These assets are demand-loaded, but first PDF use on slow mobile connections has a real download cost. The approved package requires Node >=22.13 or >=24; the installed local Node and configured CI Node 22 line are compatible.
- `git diff --check`: no whitespace errors; Git emitted existing CRLF-to-LF normalization warnings for generated route/action files.
- `npm audit --json`: fresh full npm audit passed with **zero vulnerabilities** (including development dependencies).
- Final `vendor/bin/pint --dirty --format agent`: passed. Explicit formatting checks of all newly added browser test files initially found one formatting-only issue in `ocr-workflows.spec.ts`; Prettier corrected it, targeted ESLint passed, and the repeated formatting check passed.
- A fresh full application Pest/Playwright suite has not been run for this extension.

E2E commands use `E2E_APP_URL=http://127.0.0.1:8009`, the repository's dedicated E2E build, an isolated SQLite database, one worker, and zero retries. Assets were not rebuilt underneath browser runs.

### UI/UX Polish and Dialog Layout Symmetry

- **Visual & UX Polish**: Upgraded `OcrAssistButton.vue` and `AssetOcrDialog.vue` with Lucide status icons (`ScanText`, `FileText`, `Loader2`), a 3-step visual navigation indicator (`1. Source → 2. Scan → 3. Review`), subtle accuracy tips callouts (balanced lighting, horizontal alignment, tight cropping), and candidate suggestion chips.
- **Right-Side Whitespace Elimination**: Standardized `DialogContent` padding via `p-4 pr-4 sm:p-6 sm:pr-6` to override the inherited asymmetrical `pr-14` gutter from `DialogContent.vue`. Header close button collision is guarded via `pr-10` on `DialogHeader`, allowing all cards, dropzones, previews, and form inputs to expand symmetrically across the full dialog width.
- **Terminology Standardization**: Audited and confirmed elimination of legacy barcode references; all 2D codes are consistently designated as QR Codes across forms, printers, and scanners.
- **Verification Suites**: `npm run type-check` (0 errors), `npm run lint:check` (0 errors), `npm run format:check` (clean), `php artisan test` (25 passed, 213 assertions), and all 28 Playwright tests across `ocr-responsive-review`, `ocr-workflows`, `ocr-pdf`, and `asset-ocr` pass cleanly with zero failures.

## Visual inspection and accessibility evidence

Directly inspected, not merely captured:
- Existing image dialogs for Receiving, Handover, Physical Counts, and procurement at **320px light** and **834px dark**.
- Earlier Maintenance 390px light, Transfer 834px dark, and procurement 390px light screenshots in Chromium/Firefox.
- New PDF Assets page-selection preview at **390×844 light** and procurement PDF preview at **834×1112 dark** in Chromium, Firefox, and WebKit. WebKit captures have a higher device pixel ratio; CSS viewport sizes are the values stated here.

Fresh post-PDF screenshots for Receiving, Handover, Physical Counts, and procurement were also directly inspected at **320px light, 834px dark, and 1440px light**: all 12 final responsive captures. Dialog text and fields were readable, previews contained, and internal scrolling provided access to long forms. The count-session screenshot included a transient creation toast; this is not evidence of real-device keyboard behavior. Shared responsive tests assert dialog bounds, keyboard focus containment, and explicit cancellation behavior at 320/834/1440 widths.

Browser emulation/desktop engines are not physical iPhone, iPad, Android, or printer verification. No formal complete WCAG audit or screen-reader hardware session was performed. Real mobile keyboards, native file pickers, camera permission flows, trusted HTTPS, and printing remain manual checks.

Artifacts are preserved under:
`storage/framework/testing/ocr-workflows-20261112/`
with separate initial, final, visual, PDF Chromium, and PDF cross-browser reports/results. Failure traces/videos/screenshots were retained.

## Modified scope and Git review

Backend: Receiving requests/controller/service and Handover candidate search; corresponding receiving/handover Pest tests.
Frontend: shared OCR trigger/dialog, receiving serial fields, OCR/PDF helpers, Assets/Receiving/Handover/Counts/Purchase Order pages.
Dependencies: approved pdfjs-dist pin and lockfile.
Tests: image baseline expectation, workflow tests, responsive review, PDF fixtures and PDF regressions.
Documentation: this report.
Generated Wayfinder action/receiving-route line references changed during build. Initial route edits, registration test, and untracked guide folder remain preserved.

Final reviewed Git scope: **25 modified tracked files and 11 untracked entries**, including the preserved guide directory and the new OCR report/components/helpers/tests. All remain uncommitted on `main`; generated Receiving route/action differences are source-line references only. `git diff --check` passes with existing generated-file line-ending warnings. No commit is authorized. The mixed working tree should be reviewed and staged selectively, not committed wholesale.

## Readiness verdict

**Presentation: PARTIALLY VERIFIED.** Synthetic image/PDF workflows passed the targeted checks and can be rehearsed with isolated, clearly labelled synthetic data and manual fallbacks. Permitted real-label/document samples and physical-device verification remain outstanding. Do not advertise guaranteed recognition accuracy or universal PDF support.

**Production: NOT VERIFIED.** Requires permitted real images/documents, agreed acceptance thresholds, physical-device testing, investigation of Windows WebKit shutdown behavior, and final broader regression/security review. Advanced document line extraction remains intentionally out of scope.
