# 04 — Paano Gumagana ang PUP-PRISM sa Likod? (Simple Taglish)

Ang goal ng file na ito ay ma-explain ang **process sa likod ng website** nang hindi masyadong technical. May actual file names din para kung titingnan ninyo ang code during development o defense, alam ninyo kung saan magsisimula.

---

## 1. Ano ang nangyayari kapag nag-click ka ng Save?

Imagine na nasa Receiving page ka at nag-enter ka ng 15 printer inks. Ganito ang simple process:

1. **Page / Form:** Sa website ka muna mag-eenter ng product, quantity, at other details.
2. **Route:** Ito ang daan ng request papunta sa tamang backend function. Parang receptionist na alam kung saan ipapasa ang request.
3. **Controller:** Ito ang tumatanggap ng request at tumatawag sa tamang checks at main processing logic.
4. **Validation:** Iche-check kung valid ang details. Halimbawa, tamang product ba at acceptable ba ang quantity?
5. **Permission Check:** Allowed ba ang user na mag-receive ng stock?
6. **Service:** Ito ang part na gumagawa ng actual business process, gaya ng pag-record ng received stock at related updates.
7. **Database:** Dito sine-save ang records.
8. **Response:** May result sa screen, tulad ng success message o error kung may mali.

**Simpleng idea:** Ang button sa website ay **hindi basta nagdadagdag ng stock on its own**. Dumadaan muna ito sa checks at backend process.

## 2. Saan makikita ang code ng bawat part?

| Folder o file | Easy explanation |
|---|---|
| `resources/js/pages/` | Ito ang pages/forms na nakikita at ginagamit ng staff. |
| `routes/web.php` | Listahan ng website actions at kung aling backend function ang tatawagin. |
| `routes/api.php` | Mga allowed requests mula sa approved apps/tools na gumagamit ng API. |
| `routes/settings.php` | Routes para sa profile, security, appearance, notification settings, at API tokens. |
| `app/Http/Controllers/` | Tumatanggap ng request at nagdedecide kung aling process ang tatawagin. |
| `app/Http/Requests/` | Nagche-check kung tama ang information na sinubmit ng user. |
| `app/Policies/` | Nagche-check kung may permission ang isang user sa particular action/record. |
| `app/Services/` | Dito kadalasang nakalagay ang importanteng business rules. |
| `app/Models/` | Nagre-represent ng data, gaya ng Products, Assets, at Requisitions. |
| `database/migrations/` | Setup/history ng database tables at fields. |
| `app/Console/Commands/` | Mga automatic/routine tasks gaya ng forecasting at alerts. |
| `tests/` | Automated tests para ma-check ang system bago i-release. |

**Hindi kailangang memorize lahat agad.** Mas helpful tandaan ang sequence: **page → backend checks → business process → save → result**.

## 3. Actual example: Paano pinoprocess ang Receiving?

Ito ang flow na makikita sa na-review na `ReceivingController`:

1. Mag-submit ang user sa Receiving form.
2. Iche-check ang submitted details gamit ang `ReceiveStockRequest`.
3. Kung may selected `purchase_order_line_id`, tatawagin ng controller ang `receiveAgainstPurchaseOrderLine(...)`.
4. Kung walang connected PO line, hahanapin ang product gamit ang SKU at tatawagin ang `receive(...)`.
5. Ang `InventoryService` ang gagawa ng main inventory process.
6. Magla-log ng related audit action ang controller kapag successful.
7. May success message; kung may processing error, may error message at puwedeng bumalik sa form.

May separate batch receiving action din para sa multiple lines.

**Bakit important?** Kasi nagpapakita ito na may dalawang supported paths: **PO-linked receiving** at **direct receiving**. Hindi natin dapat sabihing required ang PO sa lahat ng possible receipts.

## 4. Actual example: Bakit may verification ang Handover?

Sa `HandoverController`, hindi automatic na nagpapalit ang responsible user pagkagawa ng handover.

Ganito ang concept:

1. Pumili ng asset at recipient.
2. Iche-check kung allowed i-handover ang asset.
3. Gagawa muna ng **pending** record.
4. Mag-ge-generate ng secure verification link/token at magsa-save ng protected version nito.
5. Ipapaalam sa recipient na kailangan niyang mag-confirm.
6. Kapag successful ang verification, saka magiging official ang change of responsibility.

**Example:** Hindi puwedeng basta sabihing “si Staff B na ang may hawak” dahil lang nag-send ng request si Staff A. Dapat makumpirma muna ng receiver.

**Security detail in easy words:** Hindi raw secret verification code ang basta ini-store ng app. May protected representation nito. May checking din para hindi sabay-sabay ang conflicting handover actions sa parehong asset. Ang cancel ng pending handover ay nag-iinvalidate ng link.

## 5. Sino ang may access sa anong area?

Ito ang nakita sa **route-level access**. Importanteng tandaan na may **extra checks** pa sa individual actions at records.

| Module / Area | Main roles na nasa routes |
|---|---|
| Dashboard, Search, Notifications | Logged-in at verified users |
| User Management, Categories, Departments, Positions, Origins, Locations | Admin |
| Stock Movements at Adjustments | Admin |
| Suppliers, Purchase Orders, Receiving, Forecasting | Admin / Supply Head |
| Requisition Approve, Reject, Issue | Admin / Supply Head |
| Assets, Maintenance, Transfers, Lifecycle, Physical Counts | Admin / Supply Head / Property Custodian |
| Handover Initiate, Cancel, Resend | Admin / Property Custodian |
| Booking List, Create, View at selected bulk actions | Admin / Supply Head / Property Custodian |
| Booking Update | Admin / Property Custodian |
| Alerts management | Admin / Supply Head |
| API Token Management | Admin / Supply Head |
| Operations Health | Admin |

**Important:** Ito ay **hindi pa full permission matrix**. Halimbawa, may `ProductPolicy` na hiwalay na tumitingin kung pwede ang create, update, o delete. Kahit same role, posibleng bawal ang certain action sa certain record.

## 6. Ano ang API? At para saan ang API tokens?

**API** ay paraan para ang ibang approved software/tool ay makapag-request ng data sa PUP-PRISM nang hindi dumadaan sa usual website page.

**API token** naman ay parang access pass na may limitations. Hindi ibig sabihin na puwede na nitong gawin lahat.

**Confirmed sa `routes/api.php`:**

| Action | Supported path | Simple meaning |
|---|---|---|
| View product list | `GET /api/products` | Kumuha ng list ng products. |
| View one product | `GET /api/products/{product}` | Kumuha ng details ng isang product. |
| View asset list/details | `GET /api/assets`, `GET /api/assets/{asset}` | Basahin ang asset records na allowed. |
| View stock movements | `GET /api/stock-movements` | Basahin ang allowed stock history. |
| View requisitions | `GET /api/requisitions`, `GET /api/requisitions/{requisition}` | Basahin ang request records. |
| Create requisition | `POST /api/requisitions` | Mag-submit ng new requisition kung allowed. |

May login/token checking, verified-user requirements, request limits, at read/write abilities sa listed routes.

**Tandaan:** Hindi public API ang buong website. Kung walang API route para sa isang module, hindi puwedeng i-assume na supported ang action na iyon sa API.

## 7. Forecasting at Reorder Advice, without complicated math

Ang **forecasting** ay pag-estimate ng future supply needs gamit ang **actual na na-issue** sa past.

**Bakit issue history ang tinitingnan?** Dahil ang receiving ay nagsasabi kung ilan ang dumating, samantalang ang issue history ay mas connected sa actual na paggamit ng supplies.

Para mas sensible ang planning, tinitingnan din ang:

- **On hand:** Ilang units ang currently recorded sa stockroom.
- **Committed:** Ilang units ang approved nang i-release pero hindi pa fully naibibigay.
- **On order:** Ilang units ang expected pang dumating mula sa open orders.

**Formula sa simple words:** `Planning availability = On hand - Committed + On order`.

**Example:** May 50 boxes, 15 na approved requests pero hindi pa released, at 20 incoming. Ang planning availability ay **55**. Pero **50** pa rin ang on hand sa example, dahil hindi pa dumarating ang incoming 20.

Naka-document din ang comparison ng iba't ibang ways para mag-estimate, kasama ang past-result checking. Ang accuracy numbers ay basically **sukatan kung gaano kalapit ang estimate sa nangyari talaga**.

Sa optimization, may ABC groupings, extra stock reserve, reorder level, at suggested ordering size. Useful lahat sa **decision making**, pero staff pa rin ang magche-check at susunod sa approval process.

## 8. Alerts at automatic tasks: Bakit mahalaga ang server setup?

May scheduled tasks sa repository README:

| Schedule | Ano ang ginagawa? |
|---|---|
| Daily, **12:00 AM** | Trash cleanup. |
| Daily, **1:30 AM** | Generate demand forecasts. |
| Daily, **2:00 AM** | Generate inventory alerts. |
| Daily, **8:00 AM** | Send notification summaries. |
| Sunday, **3:30 AM** | Cleanup ng older operational records ayon sa allowed rules. |

Pero isipin mo ito: Kahit nakasulat sa code na “run every day,” kung **walang active server scheduler**, hindi ito basta magra-run mag-isa. Same idea sa background workers na kailangan para sa ilang notifications o jobs.

**Example:** May low-stock rule pero hindi dumating ang email. Posibleng okay ang rule pero may issue sa worker, mail settings, o scheduler. Kailangan i-check ang actual logs at setup.

## 9. Security checks, explained as normal questions

| Check | Question na sinasagot niya |
|---|---|
| **Login / Authentication** | “Ikaw ba talaga ang account owner na naka-login?” |
| **Permission / Authorization** | “Pwede mo bang gawin itong action?” |
| **Input checking / Validation** | “Tama ba ang product, quantity, date, at ibang details?” |
| **Protected saves / Transaction** | “Kung may error sa gitna, maiiwasan bang half-saved ang importanteng process?” |
| **Conflict protection / Locking** | “Paano kung dalawang tao ang sabay nag-edit ng parehong stock o asset?” |
| **Audit Log** | “Sino ang gumawa, ano ang action, at kailan?” |
| **Private storage** | “Allowed ba talaga ang user na makakuha ng attached file?” |
| **Rate limits** | “Paano iiwasan ang sobrang paulit-ulit na attempts?” |

**Tandaan:** Nakakatulong ang mga protection na ito, pero hindi ibig sabihin na zero bugs o zero security risks na ang system.

## 10. Ano ang directly na-review para sa guide?

Sa earlier repository review, ang mga source na binasa ay:

- `README.md` — purpose, modules, current tech stack, automation at setup notes.
- `routes/web.php` — website functions at role-based route groups.
- `routes/api.php` — available API actions at token-related checks.
- `routes/settings.php` — profile, security, appearance, notification settings, at API tokens.
- `ReceivingController.php` — regular, PO-linked, at batch receiving.
- `RequisitionController.php` — list, detail, at start ng submission flow.
- `BookingController.php` — list, calendar, available assets, at approval queue.
- `HandoverController.php` — pending verification at cancellation flow.
- `InventoryCountController.php` — count sessions, entries, at review-related actions.
- `PurchaseOrderController.php` — draft PO creation at initial PO form details.
- `docs/system-improvement/PHASE_5_IMPLEMENTATION_REPORT.md` — forecasting, FEFO, alerts, at implementation notes.

**Repository snapshot:** `main`, commit `a6450eb806b89dddf8126ce6de9c54076485b852` na nakita noong November 9, 2026. Active development pa ang repository.

## 11. Alin ang kailangan pa nating i-verify?

Para maging **full, exact code-level explanation** ang lahat ng modules, kailangan pang i-check ang mga ito sa latest code at running app:

1. Bawat remaining controller method at service, especially actual status changes at approval restrictions.
2. Database tables, field requirements, at rules para sa duplicate/linked records.
3. Latest OCR/photo-to-tag reading flow, kasama ang invalid input at error handling.
4. Actual Vue pages, available buttons, mobile layout, at what users can see per role.
5. Complete booking conflict logic at asset availability behavior.
6. Kung automatically related ang Transfer approval at Handover, o separate talaga ang steps.
7. Exact maintenance and lifecycle status rules.
8. Full role/permission matrix, including per-record ownership checks.
9. Aling reports ang talagang may required 2FA confirmation.
10. Live server setup: database, scheduler, workers, email, file storage, backups, at recent errors.
11. Current test results on latest commit, including security/dependency warnings.

### Important note sa old Phase 5 report

May **October 21, 2026** note sa Phase 5 report na **“NOT VERIFIED / NOT READY”** dahil may unresolved high-severity dependency advisories noon at may IDE checks na hindi fully confirmed. Mas bago iyon kaysa sa earlier “fully verified” report na nasa same document.

**Hindi ibig sabihin nito na sure na hindi pa fixed ngayon.** Ibig sabihin lang, hindi safe mag-claim na production-ready na ang latest project nang hindi inuulit ang tests at security checks.

## 12. Source links para madali mong ma-open

- [GitHub Repository](https://github.com/neilwaynecruz/PUP-PRISM)
- [README](https://github.com/neilwaynecruz/PUP-PRISM/blob/main/README.md)
- [Web Routes](https://github.com/neilwaynecruz/PUP-PRISM/blob/main/routes/web.php)
- [API Routes](https://github.com/neilwaynecruz/PUP-PRISM/blob/main/routes/api.php)
- [Settings Routes](https://github.com/neilwaynecruz/PUP-PRISM/blob/main/routes/settings.php)
- [Receiving Controller](https://github.com/neilwaynecruz/PUP-PRISM/blob/main/app/Http/Controllers/Inventory/ReceivingController.php)
- [Requisition Controller](https://github.com/neilwaynecruz/PUP-PRISM/blob/main/app/Http/Controllers/Inventory/RequisitionController.php)
- [Purchase Order Controller](https://github.com/neilwaynecruz/PUP-PRISM/blob/main/app/Http/Controllers/Inventory/PurchaseOrderController.php)
- [Booking Controller](https://github.com/neilwaynecruz/PUP-PRISM/blob/main/app/Http/Controllers/Inventory/BookingController.php)
- [Handover Controller](https://github.com/neilwaynecruz/PUP-PRISM/blob/main/app/Http/Controllers/Inventory/HandoverController.php)
- [Physical Count Controller](https://github.com/neilwaynecruz/PUP-PRISM/blob/main/app/Http/Controllers/Inventory/InventoryCountController.php)
- [Phase 5 Report](https://github.com/neilwaynecruz/PUP-PRISM/blob/main/docs/system-improvement/PHASE_5_IMPLEMENTATION_REPORT.md)

## 13. Short explanation na pwede mong sabihin kapag tinanong ka sa defense

> Ang PUP-PRISM ay isang system na nagco-connect ng university supplies at equipment records. Kapag may delivery, sa Receiving muna nare-record ang actual items. Kapag may request ang office, kailangan muna ng approval bago ang actual issuance. Para sa equipment, may booking at verified handover para malinaw kung sino ang may responsibility. May stock history, physical counts, reports, at alerts din para madaling ma-check ang records. Sa likod ng website, bawat importanteng action ay dumadaan sa checking at permission rules bago ma-save. May forecasting din para makatulong magplano ng future stock, pero tao pa rin ang magde-decide kung bibili.
