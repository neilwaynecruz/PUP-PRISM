# PUP-PRISM — Complete System Guide (Natural Taglish Edition)

**Repository:** [neilwaynecruz/PUP-PRISM](https://github.com/neilwaynecruz/PUP-PRISM) (private)  
**Base ng guide:** main branch, commit `a6450eb` (nakita noong November 9, 2026)  
**Style:** Simple Taglish, conversational, at may examples.

## Ano itong guide na ito?

Kung gusto mong maintindihan ang buong PUP-PRISM, dito ka magsimula. Hindi lang ito listahan ng features. Ipapaliwanag natin **para saan ang bawat module, paano ito ginagamit, ano ang nangyayari sa records, at paano ito konektado sa ibang modules.**

Sa madaling salita, ang **PUP-PRISM (Property, Resource, Inventory, and Supply Management)** ay isang system para malaman ng university kung **anong supplies at equipment ang meron, ilang piraso ang available, saan nakalagay, sino ang may hawak, ano ang kailangang bilhin, at ano ang history ng bawat transaction.**

## Recommended na reading order

1. **[01 — System Overview and User Roles](01-SYSTEM-OVERVIEW.md)** — Basic idea ng system, users, terms, at overall process.
2. **[02 — Lahat ng Modules, Explained in Taglish](02-ALL-MODULES.md)** — Lahat ng 40 modules/supporting areas, isa-isa at may simple examples.
3. **[03 — Actual Scenarios at Step-by-Step Workflows](03-END-TO-END-WORKFLOWS.md)** — Paano magkakabit-kabit ang modules sa isang real-life situation.
4. **[04 — Paano Gumagana sa Likod, Security, at Verification](04-TECHNICAL-SECURITY-AND-VERIFICATION.md)** — Simple explanation ng backend, database, permissions, at kung alin pa ang kailangang i-check.

## Importanteng note tungkol sa accuracy

Based ang guide sa **actual repository README, web/API/settings routes, selected backend controllers**, at Phase 5 report. Hindi pa ito full, line-by-line review ng *lahat* ng files at tests. Kaya kapag may detail na hindi pa natin na-confirm sa source code, malinaw itong nakalagay bilang **“Kailangan pang i-check.”**

Iba ito sa grocery/minimart PRISM project. Ang PUP-PRISM na repository ay para sa **university inventory at property**, at **PostgreSQL** ang database na nakalagay sa current README. Huwag itong ihalo sa separate na MySQL/JawsDB grocery project.

**Goal ng guide:** Madali mo itong basahin, maipaliwanag sa classmates/panel, at magamit bilang reference habang nagre-review o nagde-develop ng system.
