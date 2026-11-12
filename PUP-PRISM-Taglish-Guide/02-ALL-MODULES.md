# 02 — Lahat ng PUP-PRISM Modules, Explained in Natural Taglish

Dito natin iisa-isahin ang **40 modules at supporting areas**. Ang goal ay maintindihan mo kung **para saan, paano ginagamit, ano ang epekto sa records, at paano konektado sa ibang modules**. May practical examples din para madaling ma-imagine ang actual process.

**Note sa accuracy:** Base ito sa repository routes, README, selected controllers, at Phase 5 report. Kapag may detalye na hindi pa na-review ang full code, nakalagay ang **“Kailangan pang i-check.”**

---

## 1. Dashboard

**Para saan?** Ito ang main page pagkatapos mag-login. Parang quick overview ng importanteng information at pending work sa system.

**Paano ginagamit?** Buksan ang Dashboard, tingnan ang available summaries at shortcuts, tapos puntahan ang relevant module kung kailangan ng full details.

**Example:** Kung may supplies na paubos, useful ang dashboard para mabilis makita na may kailangang aksyon.

**Tandaan:** Confirmed ang `/dashboard` at `DashboardController`, pero kailangan pang i-check ang exact cards, numbers, at layout ng bawat role sa latest page.

## 2. Global Search

**Para saan?** Para mabilis mahanap ang record nang hindi paikot-ikot sa menus.

**Paano ginagamit?** Mag-type ng code o keyword, tingnan ang matches, tapos buksan ang napiling record.

**Example:** Kailangan hanapin ang isang equipment. Instead na isa-isahin ang mahabang list, Search ang gagamitin.

**Tandaan:** May `/search` route. Kailangan pang i-check kung aling records mismo ang searchable at kung ano lang ang allowed makita ng bawat role.

## 3. Products / Product Catalog

**Para saan?** Dito naka-register ang **uri o klase ng item**: bond paper, ink, laptop model, projector model, at iba pa.

**Paano ginagamit?**

1. Mag-add ng product at ilagay ang name, **SKU** (unique product code), type, category, supplier, at price kung applicable.
2. I-save ang product para magamit sa Purchase Orders, Receiving, Requisitions, at iba pang related pages.
3. Kung may mali, i-edit kung allowed. May actions din para sa activate/deactivate, Trash, Restore, at permanent delete, kasama ang ilang bulk actions.

**Example:** I-register ang “Bond Paper A4” para mapili siya kapag mag-oorder o magre-receive.

**Pinakaimportanteng tandaan:** **Pag-add ng product ay hindi pag-add ng actual stock.** May item record ka na, pero puwedeng zero pa ang quantity. At kahit nabubuksan ang Products page, may separate permissions pa rin para sa edit/delete.

## 4. Categories

**Para saan?** Para ma-group ang related items at mas madaling hanapin o i-report.

**Paano ginagamit?** Admin ang mag-create ng category, pagkatapos puwedeng ilagay roon ang mga products. May edit, delete, at deactivate actions sa routes.

**Example:** Bond paper at ballpen sa “Office Supplies”; computers at projectors sa “IT Equipment.”

**Tandaan:** Hindi pa fully checked ang exact delete restrictions kapag ginagamit na ng existing products ang category.

## 5. Suppliers

**Para saan?** Dito naka-save kung kanino bumibili ang university.

**Paano ginagamit?** I-add ang supplier at contact details, gamitin siya sa related products at Purchase Orders, tapos i-update ang details kapag nagbago. May list, create, view, edit, at delete routes.

**Example:** May regular supplier ng printer ink. Sa halip na hanapin lagi ang contact, naka-record na sa Suppliers.

**Access:** Admin at Supply Head sa main routes.

**Tandaan:** Ang Supplier entry ay **contact record lang**. Hindi pa ito purchase at hindi nakakadagdag ng stock.

## 6. Purchase Orders (PO) / Pag-order

**Para saan?** Para may malinaw na record ng mga **planong bilhin** sa supplier.

**Paano ginagamit?**

1. Pumili ng supplier at ilagay ang products, quantities, at unit prices.
2. I-save. Ang bagong PO ay **Draft** muna at may generated PO number.
3. Kapag ready, may **Send** action ayon sa allowed rules.
4. Kapag may delivery, i-record ang actual dumating through Receiving. Puwedeng **Partial** kung hindi pa kumpleto.
5. May cancel route at options din para gumawa ng PO draft mula sa forecast o stock alerts.

**Example:** Nag-order ng 100 reams. Dumating ang 60, kaya 60 pa lang ang mare-receive at 40 ang remaining.

**Access:** Admin at Supply Head, may additional permission checks pa.

**Tandaan:** **PO ≠ actual stock.** Kahit nag-Send na ng 100, hindi puwedeng agad gawing +100 ang available quantity. Actual Receiving pa lang ang magre-record ng nadeliver. Kailangan pang i-check ang exact final PO statuses at special cases sa full service code.

## 7. Receiving / Pag-record ng Delivery

**Para saan?** Dito nire-record kapag **talagang dumating na** ang items.

**Paano ginagamit?**

1. Pumili ng active product, o isang line ng existing PO na Sent/Partial.
2. I-enter ang actual quantity at details tulad ng reference number, date, expiry, notes, at asset tags kung needed.
3. I-submit para ma-check ng system kung valid ang information.
4. Kapag success, mare-record ang delivery, related stock changes at audit history.

**Example:** Expected 20 printer inks pero 15 lang ang dumating. **15** lang ang ilalagay sa Receiving.

**Nakita sa controller:** May **receiving na connected sa PO**, **direct receiving gamit ang SKU**, at **batch receiving** para sa maraming items sa isang submission.

**Access:** Admin at Supply Head.

**Tandaan:** Kailangan pang i-check ang exact rules para sa duplicate asset tags, sobrang quantity, at iba pang error cases. Hindi safe mag-assume na automatic tatanggapin ang kahit anong input.

## 8. Stock Lots, Expiry, at FEFO

**Para saan?** Para malaman kung aling batch ng consumables ang dapat unahin at kung may expired items na.

**Paano ginagamit?** Sa receiving, puwedeng ma-record ang batch, arrival date, at expiry date. Kapag mag-i-issue ng supplies, pipili ang system ng valid stock lots ayon sa rules.

**FEFO** means **First Expired, First Out**. Sa simple words, **unahin ang valid item na pinakamalapit mag-expire**.

**Example:** Batch A expires sa December; Batch B sa March. Kung pareho pang valid, mas uunahin ang Batch A. Kapag expired na ang lot bago ang transaction date, hindi dapat iyon ma-issue.

**Tandaan:** Ayon sa Phase 5 report, may FEFO at expired-lot exclusion. Pero ang system **hindi literal na nag-aayos ng items sa shelves**. Staff pa rin ang kailangang kumuha ng tamang physical batch.

## 9. Stock Movements at Adjustments

**Para saan?** Ito ang history kung **kailan at bakit nagbago ang stock**.

**Paano ginagamit?** Kapag may receiving, issuing, o allowed correction, puwedeng may movement record. Sa movement page, makikita ang stock changes. May Admin-only actions para sa stock adjustment at cycle count.

**Example:** Dating 50 markers, naging 40. Sa history, mas madaling malaman kung may nag-issue ng 10 at kailan nangyari.

**Tandaan:** Hindi ito hiwalay na stock list. At hindi rin dapat gawing shortcut ang manual adjustment kung normal Receiving o Issuing ang tamang process.

## 10. Requisitions / Supply Requests

**Para saan?** Dito humihingi ng supplies ang office o department bago ito i-release.

**Paano ginagamit?**

1. Gumawa ng request at ilagay ang products at quantities.
2. I-submit para marecord kung sino ang nag-request. May duplicate-submission checking ang controller.
3. Admin o Supply Head ang mag-review at mag-approve o reject kung allowed.
4. Kapag approved, saka puwedeng gawin ang **Issue** action para sa actual release.
5. Mare-record kung ilang items ang na-issue at kung may kulang pa.

**Example:** Humingi ng 10 ballpen, pero 6 lang ang na-release. Puwedeng partial ang issuance at may 4 pang remaining, depende sa workflow rules.

**Other options:** Filters by search/status/date/requester, detail view, bulk approve/issue, templates, Trash at Restore.

**Access:** Admin/Supply Head/Property Custodian sa general request routes, subject pa sa policies. Pero **Admin/Supply Head lang** ang nasa approve/reject/issue routes.

**Pinakaimportanteng tandaan:** **Approved ≠ Issued.** Hindi agad bababa ang stock dahil approved na ang request. Actual issuance pa ang kailangan.

## 11. Requisition Templates

**Para saan?** Para hindi paulit-ulit i-type ang pare-parehong items sa request.

**Paano ginagamit?** Gumawa ng template na may usual na supplies, gamitin bilang starting point ng bagong request, at baguhin kung may kulang o dagdag. May create, edit, duplicate, at delete actions.

**Example:** Every month, laging nagre-request ng 5 reams at 10 markers ang office. Puwedeng gumawa ng template para mabilis sa susunod.

**Tandaan:** Ang template ay **sample list lang**. Hindi pa ito actual requisition, hindi approved, at hindi bumabawas ng stock.

## 12. Assets / Asset Registry

**Para saan?** Para ma-track ang **bawat specific equipment**, hindi lang ang product model.

**Paano ginagamit?** Kapag may asset-type product at actual na equipment, kailangang may unique **asset tag** ang bawat unit. Sa record nito, natutukoy kung aling unit ang tinitingnan at ang related status, location, condition, at assignment details.

**Example:** Dalawang laptop na parehong model, pero isa ay nasa Library at isa ay hawak ng faculty. Kailangan silang ma-track nang magkahiwalay.

**Access:** May list at detail routes para sa Admin, Supply Head, at Property Custodian.

**Tandaan:** Hindi pareho ang asset tracking at consumable stock. **Kailangan pang i-check** ang exact asset create/edit screens at status changes, dahil sa reviewed routes, list/detail at separate asset actions ang nakikita.

## 13. Labels, QR/Tag Scanning, at OCR

**Para saan?** Para mas madaling makilala ang product o equipment gamit ang code o label.

**Paano ginagamit?** Pumili ng product, mag-view o mag-generate ng printable label/PDF sheet, at i-print kung needed. Sa pages na may supported scan/search field, puwedeng gamitin ang tag para mabilis piliin ang item.

**Example:** May unique code sa laptop. Kapag nakita o na-scan ang tag, mas madaling mahanap ang tamang laptop record.

**Ano ang OCR?** Feature na sumusubok **magbasa ng text o code mula sa picture**. May OCR/image-processing changes na nakalista sa latest observed commit `a6450eb`, pero hindi pa full na-check ang flow nito.

**Access:** Product label, sheet, at PDF routes ay nasa Admin/Supply Head group.

**Tandaan:** Ang pag-scan ay **panghanap o pang-identify lang**. Hindi proof na natanggap o na-handover na ang item. Kailangan pang i-check ang OCR errors at supported screens.

## 14. Bookings / Equipment Reservations

**Para saan?** Para mag-reserve ng reusable equipment sa specific schedule.

**Paano ginagamit?**

1. Piliin ang available equipment at ilagay ang date/time na kailangan.
2. Mag-submit ng booking request.
3. Dadaan ito sa review at maaari itong ma-approve o ma-reject.
4. Makikita ang booking records sa list/calendar at pwede itong i-manage ayon sa permissions.

**Example:** Kailangan ng projector para sa seminar next week. Ibo-book ang specific projector para hindi malito sa schedule.

**Nakita sa controller/routes:** Available asset choices, calendar, approval queue, search/filter, create, view, update, delete/restore, at bulk approve/reject.

**Access:** Admin/Supply Head/Property Custodian sa general booking routes; Admin/Property Custodian naman sa update route.

**Tandaan:** **Booking ≠ handover.** Kahit approved ang booking, hindi pa ibig sabihin na aktwal nang nasa requester ang projector. Kailangan pang i-check ang full schedule-conflict rules at exact status changes.

## 15. Handovers at Verification

**Para saan?** Para malinaw kung **sino ang tumanggap at responsible** sa specific equipment.

**Paano ginagamit?**

1. Admin o Property Custodian ang pipili ng asset at receiving person.
2. Iche-check ng system kung allowed i-handover ang asset.
3. Gagawa ng **pending handover** at magpapadala ng verification message/link.
4. Iko-confirm ng recipient ang handover sa allowed verification process.
5. **Kapag successful na ang verification**, saka official na lumilipat ang accountability.

**Example:** Ililipat ang responsibility sa isang laptop mula sa Staff A kay Staff B. May confirmation muna si Staff B para documented na natanggap niya.

**Other options:** Cancel habang pending, resend ng notification kung allowed, at handover receipt route.

**Tandaan:** **“Naisend ang handover” ay hindi pa “tinanggap ang asset.”** Hindi pa complete hangga't hindi successful ang verification. May secure token handling na nakita sa controller para sa verification link.

## 16. Asset Maintenance

**Para saan?** Para ma-record ang repair, checkup, o servicing ng equipment.

**Paano ginagamit?** Mag-log ng maintenance activity sa specific asset, tapos i-update ang maintenance status habang inaasikaso ito.

**Example:** Hindi na gumagana ang projector. May maintenance record na puwedeng balikan kung kailan ito pinaayos.

**Access:** May create at change-status routes para sa Admin/Supply Head/Property Custodian.

**Tandaan:** Kailangan pang i-check ang exact status names, approval requirements, at kung paano naaapektuhan ang asset availability. Hindi natin dapat hulaan ang sequence.

## 17. Asset Transfers

**Para saan?** Para may proper record kapag lilipat ang equipment ng location o assignment.

**Paano ginagamit?** Gumawa ng transfer request; may actions para **approve, reject, at cancel** ayon sa rules.

**Example:** Ililipat ang printer mula sa Office A papunta sa Office B. May recorded request at decision, imbes na basta palitan ang location.

**Tandaan:** Ang **Transfer** ay tungkol sa planned na paglipat ng asset. Ang **Handover** ay confirmation kung sino ang tumanggap ng responsibility. Posibleng related pero **hindi pa confirmed** na automatic silang magkasunod sa code.

## 18. Asset Lifecycle

**Para saan?** Para may history ng importanteng decisions sa equipment habang tumatagal, kasama ang possible retirement o disposal.

**Paano ginagamit?** Mag-register ng lifecycle event, tapos may approve/reject route para sa decision.

**Example:** Sobrang luma na ang isang computer. Hindi dapat basta mawala sa records; kailangan documented ang request at approval bago ang final action.

**Tandaan:** Ang pag-file ng lifecycle event ay **hindi pa automatic approval**. Kailangan pang i-check ang exact event types at epekto ng bawat action sa asset status.

## 19. Physical Inventory Counts

**Para saan?** Para ikumpara ang **actual na bilang ng items** at ang quantity na nakalagay sa system.

**Paano ginagamit?**

1. Gumawa ng count session, piliin ang items/location at assigned personnel kung applicable.
2. I-record ang nabilang na quantity sa bawat line.
3. I-submit para ma-review ang may differences.
4. I-review, approve, at complete/reconcile ayon sa allowed steps.

**Example:** Ang system ay may 20 boxes, pero 18 lang ang nabilang. Difference ay **-2**. Hindi agad nito ibig sabihin na puwedeng diretsong baguhin ang stock; kailangan pang ma-review.

**Nakita sa controller:** Count sessions, consumables at tagged assets, assigned users, discrepancies, submit/review/approve/complete/cancel actions. Ang non-Admin users ay may limited visibility sa sessions na ginawa o assigned sa kanila.

**Tandaan:** **Actual count input ≠ instant stock correction.** May review at authorized reconciliation pa.

## 20. Forecasting / Demand Analysis

**Para saan?** Para makapag-estimate kung **gaano karaming supplies ang posibleng kailanganin sa future**.

**Paano ginagamit?** Tinitingnan ang history ng **actual issued items**, kinukumpara ang past usage, at gumagawa ng estimate para sa susunod na days.

**Example:** Kung madalas 10 boxes ng paper ang nagagamit kada araw, makakatulong ang forecast sa pag-plan kung sapat pa ang stock o kailangang mag-order.

**Nasa Phase 5 report:** Forecast periods na 7, 14, 30, 60, at 90 days, at iba't ibang methods para i-compare ang estimates sa past use. May forecasting list/detail/profile routes para sa Admin at Supply Head.

**Tandaan:** **Forecast ≠ guaranteed na demand.** Guide lang ito sa planning, hindi automatic purchase approval.

## 21. Inventory Optimization / Reorder Suggestions

**Para saan?** Para makatulong sa pag-decide kung **alin ang dapat unahin bilhin** at kung ilang units ang reasonable i-order.

**Paano ginagamit?** Tinitingnan ng system ang past usage, current stock, approved requests na hindi pa fully released, at items na parating pa mula sa open POs. Mula rito, puwedeng makakuha ng reorder suggestion at prefilled PO draft.

**Mga terms sa easy words:**

- **ABC:** Aling items ang may malaking bahagi sa total spending, kaya kailangang tutukan.
- **Safety stock:** Extra stock na reserba kung biglang dumami ang kailangan.
- **Reorder point:** Level kung kailan magandang simulan ang pag-order ulit.
- **EOQ:** Estimate kung ilang units ang practical bilhin per order, base sa costs.

**Example:** May 30 boxes ka, 10 ay approved nang i-release, at may 20 na incoming. Para sa planning, ang available position ay **30 - 10 + 20 = 40**. Pero hindi ibig sabihin na 40 na agad ang physically nasa stockroom. Paparating pa lang ang 20.

**Tandaan:** Ang suggestion ay **hindi approval to buy**. Staff pa rin ang magche-check ng price, budget, supplier, at university rules.

## 22. Alerts

**Para saan?** Para mapansin agad ang issues tulad ng **low stock** o **malapit nang ma-expire** na supplies.

**Paano ginagamit?** Kapag nag-run ang alert checker, puwedeng mag-create ng alert record. Ang Admin/Supply Head ay may actions para **acknowledge**, **assign**, at **resolve**.

**Easy difference:** **Acknowledge** means nakita o napansin na ang warning. **Resolve** means naasikaso na ang issue ayon sa allowed process.

**Example:** Kulang na ang printer ink. Mag-a-alert ang system para ma-check kung kailangan nang mag-order.

**Tandaan:** Para kusang mag-run ang alerts nang walang manual click, kailangang maayos ang server scheduler at related background services. Ang nasa code na schedule ay hindi proof na running ito sa actual server.

## 23. Notifications

**Para saan?** Para makatanggap ang user ng message tungkol sa importanteng events sa system.

**Paano ginagamit?** May action o alert na nangyari, tapos puwedeng mag-save o magpadala ng notification sa allowed channels. May page para tingnan ang notifications at mark as read, isa-isa o lahat.

**Example:** May update sa isang process o may stock warning. Sa notification, hindi na kailangang paulit-ulit i-refresh ang lahat ng pages para malaman ang update.

**Tandaan:** Puwedeng may notification sa system pero hindi nakarating ang email kapag mali ang email settings o hindi running ang worker. Magkaiba ang “na-save” at “na-deliver.”

## 24. Reports at Exports

**Para saan?** Para makagawa ng files na puwedeng gamitin sa checking, reporting, at office paperwork.

**Paano ginagamit?** Pumunta sa relevant report, pumili ng filters kung available, at i-download bilang **CSV** (file na madaling buksan sa spreadsheets) o **PDF** (ready to view/print).

**Mga report na may registered routes:** Products, Bookings, Requisitions, Procurement, Forecasting, Slow-moving Stock, Expirations, Optimization, Stock Movements, at Asset Conditions.

**Example:** Kailangan ng summary kung ilan ang issued supplies sa isang period. Sa reports, mas mabilis i-export ang data para ma-review.

**Access:** Karamihan sa listed report routes ay nasa shared Admin/Supply Head/Property Custodian group, pero **Stock Movement at Asset Condition reports ay Admin-only** sa route level. May possible extra checks din.

**Tandaan:** Hindi pa verified na **lahat** ng reports ay humihingi ng 2FA. May ilang sensitive exports na nabanggit sa Phase 5, pero kailangan pang i-check ang exact controller rules per report.

## 25. Audit Logs

**Para saan?** Ito ang **record ng importanteng ginawa sa system**, para mas madaling malaman kung sino ang nag-action at kailan.

**Paano ginagamit?** Buksan ang Audit Logs, hanapin ang relevant transaction, at i-review ang action details. May export route rin kung allowed.

**Example:** May nag-adjust ng stock o nag-process ng receiving. Makakatulong ang history sa pag-check kung ano ang nangyari.

**Tandaan:** Ang Audit Log ay **pang-review ng history**, hindi pang-edit ng stock. May permissions pa rin kung sino ang pwedeng makakita ng details.

## 26. Attachments / Supporting Files

**Para saan?** Para makapag-attach ng files na supporting proof o reference sa certain record.

**Paano ginagamit?** Sa supported pages, mag-upload ng photo o document at i-connect sa relevant record. Later, puwedeng i-download o i-delete kung allowed.

**Supported record types sa routes:** Asset, Maintenance, Transfer, Lifecycle, at Count Session.

**Example:** May photo ng sirang projector. Puwede itong i-attach sa related maintenance record para makita ng reviewer.

**Tandaan:** Naka-private storage ang attachments ayon sa repository design. Hindi dapat ipagpalagay na publicly accessible ang files. May permission checks pa rin.

## 27. Trash, Restore, at Permanent Delete

**Para saan?** Para puwedeng maibalik ang record na natanggal sa normal list, kung hindi pa permanently deleted.

**Paano ginagamit?** Kapag supported, ang regular Delete ay pwedeng maglagay muna ng record sa **Trash**. Mula roon, puwedeng **Restore** o **Permanently Delete** kung allowed.

**Example:** Accidentally na-delete ang product. Kung nasa Trash pa at allowed ma-restore, hindi kailangang gumawa ulit ng panibagong record.

**Confirmed examples:** Products, Requisitions, at Bookings ay may related Trash/Restore/force-delete routes. May general Trash page din para sa Admin.

**Tandaan:** Hindi ibig sabihin na puwedeng permanently burahin ang kahit anong record. Posibleng may restrictions lalo na kung connected ito sa other transactions.

## 28. User Management

**Para saan?** Para ma-control kung **sino ang may account** at ano ang pwede nilang gawin.

**Paano ginagamit?** Admin ang gumawa ng user, mag-set ng role at organizational details kung applicable, mag-edit ng info, at mag-deactivate/reactivate ng access.

**Example:** May bagong Supply Head. Gagawa ng account na may tamang role para ang relevant pages lang ang magagamit niya.

**Tandaan:** Ang pag-deactivate ng account ay **hindi dapat magbura ng past transaction history**. Kailangan pa rin ang records para ma-trace ang old actions.

## 29. Departments

**Para saan?** Para ma-list ang mga offices o departments ng university.

**Paano ginagamit?** Admin ang mag-add, edit, o deactivate ng department. Nagagamit ito bilang reference sa positions at related records.

**Example:** May IT Department, Library, at ibang university offices. Sa system, mas malinaw kung anong department ang connected sa isang request o position.

**Tandaan:** Department ang **office/unit**, hindi pangalan ng tao. Admin-only ang related master-data routes.

## 30. Positions

**Para saan?** Para malaman ang formal position o assignment ng isang tao sa isang department.

**Paano ginagamit?** Admin ang gumagawa at nag-uupdate ng positions. Kapag linked sa user, nakakatulong ito sa requisition, handover, at other records na kailangang malaman ang office/position context.

**Example:** May “Property Custodian” na position sa isang office; puwedeng mas malinaw kung aling role at department ang involved.

**Tandaan:** Magkaiba ang **position** (work assignment) at **system role** (anong actions ang allowed sa app). May create/edit/delete/deactivate routes para sa Admin.

## 31. Origins

**Para saan?** Para magkaroon ng standard choices tungkol sa **source o pinanggalingan** ng item o record.

**Paano ginagamit?** Admin ang gumagawa ng available origin values, tapos nagagamit ang entries kung saan kailangan sa product/property records.

**Example:** Useful ang source information para mas madaling ma-classify ang records. Pero ang **exact available options at field usage ay kailangan pang i-check**; ayaw nating magbigay ng sample value na baka wala naman sa app.

**Tandaan:** May create/edit/delete/deactivate routes para sa Admin, pero hindi pa fully traced ang paggamit ng bawat origin entry.

## 32. Locations

**Para saan?** Para malaman kung saan naka-assign o naka-store ang equipment, tulad ng building, room, o office area.

**Paano ginagamit?** Admin ang mag-add ng location details at mag-update/delete kung allowed. Nagagamit ang locations sa assets at physical count sessions.

**Example:** Nasa Computer Lab 1 ang projector. Mas madaling mahanap at i-check kung tama ang recorded location.

**Tandaan:** Ang pag-edit ng **location name** ay hindi automatically proof na nailipat ang physical equipment. Kung actual transfer ang nangyari, dapat gamitin ang tamang asset transfer process.

## 33. Account Profile

**Para saan?** Para ma-update ng user ang sariling account information.

**Paano ginagamit?** Open Profile settings, i-edit ang allowed fields, at i-save. May route din para mag-delete ng profile/account, pero may possible restrictions kapag connected sa important records.

**Example:** May correction sa name o ibang personal profile detail na allowed baguhin.

**Tandaan:** Profile settings ay para sa **sariling account**, hindi katulad ng Admin User Management na nagma-manage ng ibang users.

## 34. Security, Password, at 2FA

**Para saan?** Para hindi basta makapasok ang ibang tao o makagawa ng action na hindi allowed.

**Paano ginagamit?** Mag-login gamit ang credentials at sundin ang required email verification o 2FA kung enabled. Sa Security settings, may password change at 2FA-related setup.

**Ano ang 2FA?** Bukod sa password, may **extra confirmation** para mas sure na ikaw ang nagla-login o gumagawa ng protected action.

**Example:** Kahit may nakakaalam ng password, may extra check pa kung enabled ang 2FA.

**Tandaan:** Magkaiba ang **logged in** at **allowed sa action**. May role/permission check pa rin. May rate limit ang password update route para hindi madaling abusuhin.

## 35. Appearance Settings

**Para saan?** Para sa personal na look o theme ng app.

**Paano ginagamit?** Pumunta sa Appearance settings at piliin ang supported visual preference sa current page.

**Example:** Kung may light/dark appearance options sa interface, dito ito ina-adjust.

**Tandaan:** Hindi ito overall System Settings page para sa server o database. Kailangan pang i-check ang exact options sa current Vue page.

## 36. Notification Preferences

**Para saan?** Para mapili ng user kung anong messages ang gusto niyang matanggap, kung supported ng system.

**Paano ginagamit?** Open Notifications settings, i-adjust ang available preferences, at i-save.

**Example:** Kung may choice para sa certain alert types o channels, puwedeng ayusin ayon sa available options.

**Tandaan:** Kahit enabled ang email preference, hindi nito automatic maaayos ang email server kung may setup issue.

## 37. API Tokens at API Access

**Para saan?** Para may ibang approved software o tools na pwedeng kumuha o mag-submit ng **limited data** sa system nang may proper access.

**Paano ginagamit?** Admin o Supply Head ang mag-create/manage ng API token. Kapag may request na may token, chine-check ng system kung valid ito at kung allowed ang action.

**Confirmed API functions:** View products, assets, stock movements, at requisitions; submit a requisition. Hindi lahat ng website actions ay may API.

**Example:** May trusted integration na kailangang magbasa ng product info. Token at permissions ang ginagamit para controlled ang access.

**Tandaan:** **API token ≠ all-access pass.** May read/write restrictions, login/security rules, at request limits pa rin.

## 38. Operations Health at System Health

**Para saan?** Para makita ng Admin kung gumagana ang importanteng parts ng system sa likod ng website.

**Paano ginagamit?** Buksan ang Admin health pages at i-review ang available status info, tulad ng scheduled jobs o background processing, ayon sa nakalagay sa README.

**Confirmed routes:** `/admin/operations/health` at `/admin/health`, Admin-only.

**Example:** May alerts na hindi nag-a-update. Puwedeng i-check ang operations health para makita kung may issue sa background processing.

**Tandaan:** Kahit okay ang health page, hindi automatic proof na completely production-ready na ang app o lahat ng backups ay verified.

## 39. Scheduled Automation, Cleanup, at Monitoring

**Para saan?** Para may routine tasks na naka-schedule at hindi kailangang manwal na i-click araw-araw.

**Naka-register sa README:**

- **12:00 AM daily:** Trash cleanup.
- **1:30 AM daily:** Generate demand forecasts.
- **2:00 AM daily:** Generate inventory alerts.
- **8:00 AM daily:** Send notification summaries.
- **3:30 AM Sunday:** Cleanup ng older operational data ayon sa retention rules.

**Paano gumagana?** May server schedule na tumatawag sa related commands, tapos may services/workers na nagpo-process ng jobs kung needed.

**Example:** Hindi na kailangan manually buksan ang every product para mag-check ng low stock kung naka-set up at tumatakbo nang maayos ang scheduled alert checker.

**Tandaan:** **Naka-schedule sa source code ≠ proven na active sa live server.** Kailangan i-check ang actual server setup at logs.

## 40. Testing at Developer Checks (Support Area)

**Para saan?** Para mahanap ang bugs bago gamitin o i-deploy ang system.

**Paano ginagawa?** Developers run tests for important backend actions at browser steps, tapos chine-check din ang code formatting, build, at security warnings.

**Tools sa repository:** Pest, PHPUnit, Playwright, TypeScript checks, ESLint, Prettier, Pint, at dependency audits.

**Example:** Bago mag-release ng pagbabago sa Receiving, ite-test kung tama ang stock after an actual receipt at kung blocked ang invalid entries.

**Tandaan:** Kapag pumasa ang tests last month, **hindi automatic na pasado pa rin ngayon**. Kailangan ulitin sa latest code.

---

## Madaling tandaan: Alin ang magkaiba?

| Madalas mapaghalo | Simple difference |
|---|---|
| **Product vs Asset** | Product = klase ng item. Asset = specific equipment na may sariling tag. |
| **PO vs Receiving** | PO = in-order pa lang. Receiving = actual na dumating. |
| **Requisition vs Issue** | Requisition = request. Issue = actual na release at bawas stock. |
| **Booking vs Handover** | Booking = reservation sa schedule. Handover = confirmed na pagtanggap ng responsibility. |
| **Count vs Movement** | Count = actual bilang vs system. Movement = history ng stock change. |
| **Transfer vs Handover** | Transfer = paglipat ng location/assignment. Handover = confirmation ng tatanggap. |
| **Alert vs Notification** | Alert = problem/warning. Notification = message na nagsasabing may update o issue. |
| **Forecast vs PO** | Forecast = estimate/suggestion. PO = actual record ng planned purchase. |

**Next:** [03 — Actual Scenarios at Step-by-Step Workflows](03-END-TO-END-WORKFLOWS.md)
