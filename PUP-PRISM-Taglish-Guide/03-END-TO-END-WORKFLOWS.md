# 03 — Actual Scenarios: Paano Nagkakabit-Kabit ang Modules?

Mas madaling maintindihan ang PUP-PRISM kung titingnan natin ang **actual situations sa university**. Sa bawat example, ipapakita natin kung saan magsisimula, anong module ang gagamitin next, at kailan talaga magbabago ang records.

**Note:** Examples ito ng processes na supported sa reviewed code at documentation. Hindi ibig sabihin na lahat ng university ay may eksaktong parehong approval setup.

---

## Scenario A — Nag-order ng 100 reams ng bond paper

**Situation:** Kailangan ng university ng 100 reams, kaya mag-oorder sa supplier.

1. **Products:** Dapat may existing record ng “Bond Paper A4.” Dito nakalagay ang product code at basic details.
2. **Suppliers:** Naka-register kung saan bibili.
3. **Purchase Orders:** Gagawa ang Supply Head ng PO para sa 100 reams. Sa simula, **Draft** pa ito.
4. **Send PO:** Kapag ready at allowed, isesend ang order. Pero **hindi pa nadadagdagan ang actual stock**.
5. **Receiving:** Dumating muna ang 60 reams. Ire-record ang **60** na actual received quantity, hindi 100.
6. **PO Status / Received Quantity:** Makikita na hindi pa kumpleto ang delivery, kaya puwedeng partial ang order.
7. **Second Delivery:** Dumating ang remaining 40. Ire-receive ulit ang 40. Ang final status ay depende sa exact PO rules ng system.
8. **Stock Movements / Reports:** May history ng received items at updated stock para sa related pages.

**Simpleng explanation:** Ang PO ay parang “ito ang inorder natin.” Ang Receiving naman ay “ito ang totoong dumating.”

**Common mistake:** Sabihing +100 agad ang stock after mag-send ng order. **Hindi tama iyon** kung 60 pa lang ang delivered.

## Scenario B — Nag-request ang office ng 10 ballpen

**Situation:** Kailangan ng isang department ng supplies mula sa stockroom.

1. **Products:** Available sa product list ang ballpen.
2. **Requisitions:** Gagawa ang user ng request para sa 10 ballpen.
3. **Submission:** Ise-save ang request, kasama ang requester at items. May check para maiwasan ang accidental duplicate submission.
4. **Approval:** Admin/Supply Head ang magre-review. Puwede nilang i-approve o i-reject kung allowed.
5. **Issue:** Kapag approved, authorized staff ang magre-release ng actual stock.
6. **Stock Update:** Sa **Issuing** pa magbabawas ng available stock at maglalagay ng movement history.
7. **Partial:** Kung 6 lang ang na-issue sa 10, may 4 pang hindi naibibigay, subject sa allowed rules.

**Simpleng explanation:** Magkaibang steps ang **“pinayagan ang request”** at **“naibigay na ang items.”**

**Common mistake:** Sabihing bababa na agad ang stock pagkatapos ng approval.

## Scenario C — May consumables na malapit nang ma-expire

**Situation:** May dalawang batches ng consumable product: Batch A ay mas maagang mag-e-expire kaysa Batch B.

1. **Receiving:** Recorded ang two batches at expiry dates, kung supplied sa receiving.
2. **Lots:** Alam ng system na magkaiba ang batches kahit same product.
3. **Requisition / Issue:** Kapag may approved request, iche-check ang valid stock lots.
4. **FEFO:** Uunahin ang valid na mas malapit mag-expire, gaya ng Batch A.
5. **Expired Stock:** Kung ang expiry date ay lumipas na bago ang transaction date, hindi iyon dapat i-issue ayon sa documented rule.
6. **Alerts / Expiry Reports:** Makatutulong ang tracking para makita kung anong batch ang nangangailangan ng attention.

**Simpleng explanation:** Sa FEFO, **expiry date** ang mas importante sa pagpili ng batch. Sa FIFO, **arrival date** ang unang tinitingnan. Hindi sila pareho.

**Common mistake:** Sabihing automatic inaayos ng system ang items sa shelf. Staff pa rin ang kukuha ng correct physical batch.

## Scenario D — Magre-reserve ng projector para sa seminar

**Situation:** May staff na nangangailangan ng projector next week.

1. **Assets:** Nakarecord ang projector bilang specific equipment na may asset tag.
2. **Bookings:** Pipiliin ang available equipment at ilalagay ang schedule.
3. **Review:** Dadaan sa booking approval flow ayon sa allowed permissions.
4. **Calendar / Booking List:** Makikita ang planned schedule at booking status.
5. **Actual Turnover:** Kung kailangan talagang ibigay ang projector sa recipient, gagamitin ang **Handover** workflow.
6. **Verification:** Hihingi ng confirmation sa receiving person bago maging official ang bagong accountability.

**Simpleng explanation:** **Booking** ay pag-reserve. **Handover** ay confirmation kung kanino talaga naibigay ang equipment.

**Common mistake:** Sabihing approved booking = nasa kamay na ng requester ang projector.

## Scenario E — Ipapasa ang laptop mula Staff A papunta Staff B

**Situation:** May laptop na kailangan ilipat ang responsibility sa bagong gumagamit.

1. **Assets:** Hanapin ang tamang laptop gamit ang asset tag.
2. **Handover:** Admin/Property Custodian ang pipili ng receiver at mag-iinitiate.
3. **Checking:** Titingnan ng backend kung puwede ang handover sa current asset state.
4. **Pending:** Magse-save muna ng pending record. Hindi pa official ang reassignment.
5. **Notification:** Magpapadala ng verification link sa bagong receiver.
6. **Receiver Confirmation:** Kapag successful ang verification, saka maa-update ang official responsibility.
7. **Receipt / Audit:** May handover receipt route at history ng process.

**Kung hindi natuloy:** Puwedeng i-cancel ang pending handover kung allowed, at i-invalidate ang existing verification link.

**Simpleng explanation:** Hindi enough na i-type lang ang name ng new user. May actual verification step para mas accountable ang transfer ng responsibility.

## Scenario F — May printer na nasira o kailangang ilipat

**Situation:** May equipment na hindi gumagana o ililipat sa ibang office.

- **Kung repair:** Hanapin ang asset, gumawa ng **Maintenance** record, at i-update ang progress/status ayon sa allowed steps.
- **Kung lilipat ng office:** Gumawa ng **Asset Transfer** request na puwedeng ma-approve, ma-reject, o ma-cancel.
- **Kung hindi na magagamit:** Maaaring gumamit ng **Asset Lifecycle** process para sa retirement/disposal decision, subject sa approval.
- **Kung may proof:** Puwedeng mag-attach ng supporting photo/document sa supported record types.

**Simpleng explanation:** Hindi dapat basta burahin ang luma o palitan ang location nang walang record. Ang goal ay may history kung ano ang nangyari sa equipment.

**Kailangan pang i-check:** Exact maintenance status names, kung paano naaapektuhan ang bookability, at kung automatically connected ang transfer at handover.

## Scenario G — Sabi ng system 20, pero 18 lang ang nakita

**Situation:** May kulang na 2 units sa actual counting.

1. **Physical Counts:** Gumawa ng count session at piliin ang item/location.
2. **Expected:** Recorded ng session ang expected quantity na 20.
3. **Actual Count:** Na-count ng staff ang 18; ilalagay niya ang 18 at notes kung kailangan.
4. **Difference:** `18 - 20 = -2` units.
5. **Review:** Ise-submit ang count para ma-check kung tama ang counting at may explanation ang difference.
6. **Approval / Completion:** Susundin ang allowed steps bago mag-post ng official correction.
7. **History:** Kung may approved adjustment, may related movement/audit records para ma-trace.

**Simpleng explanation:** Hindi lahat ng number na tinype sa count form ay dapat diretsong maging new official stock. **May review muna.**

## Scenario H — Paubos na ang stocks, kailangan nang magplano

**Situation:** Napansin ng Supply Head na madalas maubos ang printer ink.

1. **Stock Movements:** May history ng actual released supplies.
2. **Forecasting:** Gagamitin ang issue history para tantiyahin ang future needs.
3. **Optimization:** Ikukumpara ang current stock, pending approved requests, at expected incoming items.
4. **Alerts:** Puwedeng mag-warning kung low stock o near expiry.
5. **PO Suggestion:** Puwedeng mag-open ng prefilled PO draft base sa recommended quantity.
6. **Human Review:** Supply Head ang magche-check kung tama ang recommendation, price, supplier, budget, at purchasing rules.
7. **Receiving:** Kapag dumating ang order, saka pa lang idadagdag ang actual delivered quantity.

**Simple computation:** Kung may **30** currently on hand, **10** approved na pero hindi pa na-release, at **20** incoming, planning estimate ay `30 - 10 + 20 = 40`.

**Tandaan:** Ang 40 ay planning position. **30 pa rin** ang currently on hand sa example; hindi pa physically dumadating ang 20.

## Scenario I — May bagong staff na gagamit ng system

1. **Admin / User Management:** Gumawa ng account at assign ng correct role.
2. **Departments / Positions:** I-connect sa appropriate office at position, kung applicable.
3. **Login / Security:** Mag-login ang staff at kumpletuhin ang required verification steps.
4. **Permissions:** Kahit naka-login na, iche-check pa rin kung allowed siya sa bawat action.
5. **Settings:** Puwedeng ayusin ang profile, password, appearance, at notification preferences ayon sa available options.
6. **Audit / Monitoring:** May history ang importanteng actions para ma-review kung kailangan.

**Example:** Kung Supply Head ang role, hindi automatic na pareho ang access niya sa Admin para sa lahat ng system management pages.

## Scenario J — Dumating ang supply na walang connected PO

**Situation:** May valid delivery na kailangan i-record, pero hindi ito naka-link sa existing PO line.

1. **Products:** Siguraduhin na registered at active ang product.
2. **Receiving:** Gamitin ang supported **direct receiving by SKU** flow.
3. **Details:** Ilagay ang actual quantity, date, at other required information.
4. **Checking:** Iva-validate ng backend at ipapasa sa inventory service.
5. **Record:** Kapag successful, mare-record ang receipt at related audit history.

**Tandaan:** Sa controller na na-review, supported ang direct receiving, pero hindi ibig sabihin na puwede nang balewalain ang school purchasing rules. **System capability** at **office policy** ay magkaibang usapan.

---

## One-page connection map

| May nangyari... | Usually involved ang... |
|---|---|
| May bagong delivery | Products → Supplier/PO (kung meron) → Receiving → Stock/Lots → Movement History |
| May request ang department | Products → Requisition → Approval → Issue → Stock/FEFO → Reports |
| May equipment na gagamitin | Assets → Booking → Approval/Calendar |
| May equipment na ipapasa | Assets → Handover → Recipient Verification → Receipt/History |
| May sirang equipment | Assets → Maintenance → Attachments → History |
| May equipment na lilipat | Assets → Transfer → Approval → Location/Assignment records |
| May count discrepancy | Products/Assets → Physical Count → Review → Approved Correction → Audit |
| May low stock | Movements → Forecast/Alerts → Suggested PO → Receiving |

**Next:** [04 — Paano Gumagana sa Likod, Security, at Verification](04-TECHNICAL-SECURITY-AND-VERIFICATION.md)
