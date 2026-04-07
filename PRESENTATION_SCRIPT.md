# Tirana Solidare — Skript Prezantimi
### ~10 minuta · 4 folës · Demo live i përfshirë

> **Si ta lexosh këtë dokument:** Caktimi i folësve bëhet me etiketat [PERSONI 1] deri [PERSONI 4].  
> Rreshtat me *kursiv* janë udhëzime skenike — nuk thuhen me zë.  
> Koha e sugjeruar për çdo seksion tregohet në kllapa.

---

## PARA SE TË FILLONI

- Hapni shfletuesin, navigoni te `http://localhost/TiranaSolidare/`
- Keni dy skeda gati: një te homepage-i, një te `views/dashboard.php` (të kyçur si admin)
- Keni kredencialet e llogarisë vullnetare gati: `demo.elira@tiranasolidare.local` / `Demo123!`
- Keni kredencialet e adminit gati: `demo.admin@tiranasolidare.local` / `Demo123!`

---

## PJESA 1 — FILLIMI I FORTË [~1 min] [PERSONI 1]

*Dilni para. Mos hapni laptopin ende.*

"Imagjinoni që doni të ndihmoni. Keni kohë, keni energji — por nuk dini ku të shkoni, kë të telefononi, apo çfarë nevojitet realisht tani në qytetin tuaj.

Ose nga ana tjetër: jeni një familje në vështirësi, dhe nuk dini që tre blloqe larg ka një vullnetar gati të ndihmojë — sepse askush nuk ju ka lidhur.

Ky është problemi që ne zgjidhem.

Ndërtuam **Tirana Solidare** — një platformë që lidh bashkinë, vullnetarët dhe qytetarët në nevojë. Jo nëpërmjet fletushkave. Jo nëpërmjet grupeve në Facebook. Nëpërmjet një sistemi web të strukturuar, të moderuar dhe në kohë reale."

---

## PJESA 2 — NJOHJA E SHPEJTË [~1 min] [TË 4]

*Secili thotë emrin e tij dhe një fjali për atë që ndërtoi. Shpejt, me besim, pa mbushës.*

**[PERSONI 1]:** "Jam [Emri] — punova në sistemin e autentifikimit, rolet e përdoruesve dhe sigurinë."

**[PERSONI 2]:** "Jam [Emri] — ndërtova modulin e eventeve dhe faqet publike."

**[PERSONI 3]:** "Jam [Emri] — projektova sistemin e kërkesave për ndihmë dhe hartën."

**[PERSONI 4]:** "Jam [Emri] — ndërtova panelin e adminit, rrjedhën e moderimit dhe njoftimet."

---

## PJESA 3 — PASQYRA E PLATFORMËS [~1.5 min] [PERSONI 2]

*Hapni skedën e homepage-it në ekran.*

"Kjo është ajo që sheh një qytetar kur hyn në platformë — pa pasur nevojë të kyçet.

Shikon evente aktive komunitare, kërkesat më të fundit për ndihmë dhe një hartë ku po ndodhin gjërat tani.

Vendimi i dizajnit këtu ishte i qëllimshëm: **zero pengesë për përshtypjen e parë**. E kupton platformën brenda 10 sekondave. Eventet majtas, nevojat djathtas, harta poshtë.

Tri lloje përdoruesish ndërveprojnë me këtë sistem:
- **Adminët** — nga bashkia ose një organizatë — që krijojnë dhe moderojnë përmbajtje
- **Vullnetarët** — qytetarë që duan të ndihmojnë — që aplikojnë për evente dhe i përgjigjen nevojave
- **Qytetarët në nevojë** — që postojnë kërkesa dhe lidhen me burimet

Secili ka një eksperiencë krejtësisht të ndryshme brenda të njëjtës platformë."

---

## PJESA 4 — MODULI I EVENTEVE [~1.5 min] [PERSONI 2]

*Klikoni te faqja e Eventeve. Nëse keni kohë, krijoni shpejt një event demo.*

"Moduli i eventeve është bërthama e koordinimit të vullnetarëve.

Një admin krijon një event — me datë, vendndodhje, kategori, kapacitet dhe një imazh banner. Vendndodhja plotësohet automatikisht nëpërmjet API-t të gjeokodimit, pa asnjë koordinatë manuale.

Sapo publikohet, vullnetarët e shfletojnë këtu dhe aplikojnë me një klik. Admini shikon të gjithë aplikantët dhe mund t'i shënojë si **prezent** ose **absent** pas eventit — gjë që ushqen historikun publik të vullnetarit.

Çfarë e bën të fuqishëm: nuk është vetëm një kalendar eventesh. Është një **cikël i plotë** — nga krijimi, tek aplikimet, tek gjurmimi i pjesëmarrjes, tek raportet pas eventit."

---

## PJESA 5 — KËRKESAT PËR NDIHMË [~1.5 min] [PERSONI 3]

*Navigoni te faqja e Kërkesave për Ndihmë, pastaj hapni skedën e hartës.*

"Moduli i kërkesave për ndihmë është ai ku gjërat bëhen personale.

Çdo përdorues i verifikuar mund të postojë një kërkesë — 'Kam nevojë për transport te kontrolli mjekësor' — ose një ofertë — 'Mund të siguroj vakte ushqimore për një familje për një javë'. Zgjedhin kategorinë, shtojnë vendndodhjen, vendosin kapacitetin dhe dërgojnë.

Këtu ka një detaj që ka rëndësi për besimin: **adresa e saktë është e fshehur si parazgjedhje**. Shkyçet vetëm pasi të keni aplikuar dhe të jeni pranuar. Përdoruesit anonimë shohin lagjen, jo derën e përparme. Privatësi me dizajn.

Postimet e vullnetarëve hyjnë në moderim përpara se të publikohen. Admini i shqyrton fillimisht. Ky hap i vetëm eliminon abuzimin dhe e mban platformën të besueshme.

Dhe gjithçka është e dukshme në **hartë** — me shënues të koduar me ngjyra sipas kategorisë dhe tipit — kështu që ata që reagojnë mund të shohin menjëherë çfarë nevojitet ku."

---

## PJESA 6 — PANELI I ADMINIT [~1.5 min] [PERSONI 4]

*Kaloni te skeda e panelit të adminit.*

"Kjo është qendra e kontrollit.

Admini shikon statistika live në krye — gjithsej përdorues, evente aktive, kërkesa në pritje, aplikime këtë javë. Poshtë kësaj, çdo panel menaxhimi që ka nevojë institucioni: Evente, Kërkesa, Përdorues, Mesazhe, Njoftime, Raporte.

Disa gjëra që ia vlen të theksohen:

**Hierarkia e roleve** — kemi tre nivele: përdorues i rregullt, admin dhe super admin. Vetëm super admini mund të ndryshojë rolet ose të bllokojë adminë të tjerë. Kjo parandalon përshkallëzimin e privilegjeve brenda sistemit.

**Radha e moderimit** — çdo kërkesë e re ndihme nga jo-admini mbërrin këtu si 'në pritje'. Një klik për të miratuar ose refuzuar, me veprimin të regjistruar.

**Njoftimet masive** — admini mund të dërgojë një njoftim te të gjithë përdoruesit njëherësh, me dorëzim opsional me email. Kjo është ndërtuar mbi një motor njoftimesh me AJAX polling — pa asnjë shërbim të jashtëm.

**Kutia postare e mbështetjes** — mesazhet nga formulari i kontaktit të qytetarëve mbërrijnë direkt këtu. Admini mund t'i lexojë dhe t'u përgjigjet nga ky panel."

---

## PJESA 7 — SIGURIA DHE PIKAT TEKNIKE [~1 min] [PERSONI 1]

"Para se të mbyllim, disa vendime teknike me të cilat jemi të krenuar:

Çdo pyetje në bazën e të dhënave përdor **PDO prepared statements** — asnjë ndërfutje e papërpunuar vargjesh, zero sipërfaqë SQL injection.

Fjalëkalimet ruhen me **bcrypt** — asnjëherë tekst i thjeshtë, asnjëherë MD5.

Ndërtuam **radhën tonë të emailit** — njoftimet nuk bllokojnë kërkesën, ato përpunohen asinkronisht nga një punë në sfond.

**Përdoruesit nën 16 vjeç** kërkojnë miratimin e kujdestarit me email përpara se llogaria të aktivizohet — një veçori reale e pajtueshmërisë që shumica e platformave e anashkalon.

Dhe platforma është plotësisht **responsive për celular** — sepse në Tiranë, njerëzit arrijnë te telefoni i tyre fillimisht."

---

## PJESA 8 — MBYLLJA [~30 sek] [PERSONI 1 ose TË GJITHË]

*Mbyllni laptopin ose largohuni nga ekrani.*

"Tirana Solidare nuk është prototip. Ka një skemë të plotë baze të dhënash, një shtresë REST API, teste unitare dhe integrimi, një listë kontrolli gatishmërie për prodhim dhe hapa të dokumentuar vendosje.

Ajo që ndërtuam është një platformë që e kthen solidaritetin nga një impuls spontan në një sistem të koordinuar, të llogaridhënshëm, në shkallë qyteti.

Faleminderit."

*Ndaloni. Merrni pyetje.*

---

## REFERIM I SHPEJTË: KU FLET KU

| Seksioni | Folësi | Ekrani kryesor |
|---|---|---|
| Fillimi i Fortë | Personi 1 | Pa ekran |
| Njohja e Shpejtë | Të 4 | Pa ekran |
| Pasqyra e Platformës | Personi 2 | Homepage |
| Eventet | Personi 2 | Faqja e Eventeve |
| Kërkesat + Harta | Personi 3 | Kërkesat → Harta |
| Paneli i Adminit | Personi 4 | Dashboard |
| Siguria & Teknike | Personi 1 | Dashboard (opsionale) |
| Mbyllja | Personi 1 | Pa ekran |

---

## FJALI REZERVË (nëse bllokoheni)

- "Kështu bëhet vullnetarizmi i shkallëzueshëm."
- "Çdo fushë këtu ka një arsye — asgjë nuk është dekorative."
- "Bashkia merr mbikëqyrje. Vullnetari merr thjeshtësi. Qytetari në nevojë merr dinjitet."
- "E ndërtuam pa framework — kontroll i plotë, asnjë kuti e zezë."
- "Përdorues realë, të dhëna reale, moderim real — jo një projekt shkollor me butona të rremë."

---

## NËSE KA KOHË SHTESË (ide bonus)

Tregoni **Panelin e Vullnetarit** — të kyçur si `demo.elira@tiranasolidare.local`:
- "Kjo është ajo që sheh vullnetari — aplikimet e tij, postimet e tij për ndihmë, historikun e njoftimeve dhe cilësimet e llogarisë, duke përfshirë preferencat e njoftimeve me email."
- Zgjat ~45 sekonda dhe e bën eksperiencën me shumë role konkrete.
