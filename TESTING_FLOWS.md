# Rrjedhat e Testimit Para Prezantimit
### Kaloni nëpër gjithçka poshtë ditën para DHE ~30 minuta para prezantimit

> **Legjenda:**  
> ✅ Kalon — funksionon siç pritet  
> ❌ Dështon — i prishur, duhet rregulluar  
> ⚠️ Kujdes — i degraduar por i prezantueshëm  

Kredencialet për të pasur të hapur në shënimet:
- Admin: `demo.admin@tiranasolidare.local` / `Demo123!`
- Vullnetar: `demo.elira@tiranasolidare.local` / `Demo123!`

---

## LISTA E KONTROLLIT TË KONFIGURIMIT (para çdo rrjedhe)

- [ ] XAMPP → Apache **jeshil**, MySQL **jeshil**
- [ ] Navigoni te `http://localhost/TiranaSolidare/` — faqja ngarkohet pa gabime PHP
- [ ] Asnjë `Notice:`, `Warning:`, ose `Fatal error:` i dukshëm në asnjë faqe
- [ ] `config/db.php` — kredencialet e DB-së korrekte për mjedisin lokal
- [ ] Zmadhimi i shfletuesit vendosur në **100%** (që paraqitja të duket mirë gjatë ndarjes së ekranit)
- [ ] Pastroni cache-in e shfletuesit nëse bëni demo në një makinë të re

---

## RRJEDHA 1 — Homepage Publike

| Hapi | Rezultati i Pritur | ✅ / ❌ |
|---|---|---|
| Hapni `http://localhost/TiranaSolidare/` | Seksioni hero ngarkohet, pa faqe boshe | |
| Lëvizni poshtë | Seksioni i eventeve tregon të paktën 1 event të seed-uar | |
| Lëvizni më poshtë | Seksioni i kërkesave tregon të paktën 1 kërkesë të seed-uar | |
| Klikoni "Shiko të gjitha eventet" | Navigon te faqja e listës së eventeve | |
| Klikoni butonin kthehu të shfletuesit | Kthehet te homepage-i saktë | |

---

## RRJEDHA 2 — Harta e Homepage-it

| Hapi | Rezultati i Pritur | ✅ / ❌ |
|---|---|---|
| Hapni `http://localhost/TiranaSolidare/views/map.php` | Harta renderizohet (pllakat Leaflet ngarkohen) | |
| Zmadho / zhvendos hartën | Asnjë gabim JS në konsolë | |
| Shënuesit të dukshëm | Të paktën shënuesit e eventeve të dukshëm në hartë | |
| Klikoni një shënues | Popup hapet me titullin dhe lidhjen e eventit | |
| Përdorues anonim | Shënuesit e kërkesave për ndihmë NUK tregohen (privatësi) | |

---

## RRJEDHA 3 — Regjistrimi i Përdoruesit (Llogari e Re)

| Hapi | Rezultati i Pritur | ✅ / ❌ |
|---|---|---|
| Hapni `views/register.php` | Formulari ngarkohet | |
| Dërgoni me fusha boshe | Gabim validimi i treguar, nuk dërgohet | |
| Dërgoni me fjalëkalime që nuk përputhen | Gabimi tregohet | |
| Regjistroni një llogari të rrituri të vlefshëm (mosha > 16) | Mesazh suksesi ose ridrejtim te hyrja | |
| Tentoni hyrjen para verifikimit të emailit | Bllokuar me mesazh rreth verifikimit të emailit | |

> **Shënim:** Nëse emaili nuk është i konfiguruar lokalisht, kaloni te Rrjedha 4 — vetëm shënoni që verifikimi i emailit funksionon në prodhim.

---

## RRJEDHA 4 — Hyrja si Vullnetar

| Hapi | Rezultati i Pritur | ✅ / ❌ |
|---|---|---|
| Hapni `views/login.php` | Formulari ngarkohet | |
| Vendosni fjalëkalim të gabuar | Mesazh gabimi i treguar, nuk kyçet | |
| Hyni si `demo.elira@tiranasolidare.local` / `Demo123!` | Ridrejtuar te paneli i vullnetarit | |
| Kontrolloni titullin e faqes / kokën | Tregon emrin e vullnetarit, jo "Admin" | |
| Navigoni direkt te `views/dashboard.php` | Ridrejtuar (pa akses admin) | |

---

## RRJEDHA 5 — Paneli i Vullnetarit

| Hapi | Rezultati i Pritur | ✅ / ❌ |
|---|---|---|
| Hapni `views/volunteer_panel.php` | Paneli ngarkohet me skedat (Aplikimet, Postimet, Mesazhet, Cilësimet) | |
| Skeda "Aplikimet" | Tregon eventet për të cilat është aplikuar (ose mesazh gjendja boshe) | |
| Skeda "Postimet" | Tregon postimet e kërkesave (ose mesazh gjendja boshe) | |
| Skeda "Cilësimet" | Tregon formularin e redaktimit të profilit dhe çelësin e njoftimeve | |
| Ndryshoni emrin e parë të profilit → Ruaj | Mesazhi i suksesit shfaqet, emri përditësohet | |
| Çelësi i njoftimeve me email | Ndryshon on/off dhe ruhet pa rifreskim | |

---

## RRJEDHA 6 — Shfletimi i Eventeve (si Vullnetar)

| Hapi | Rezultati i Pritur | ✅ / ❌ |
|---|---|---|
| Navigoni te `views/events.php` | Lista e eventeve ngarkohet | |
| Filtroni sipas kategorisë | Lista filtrohet saktë | |
| Hapni një event të vetëm | Pamja e detajit tregon titull, datë, vendndodhje, përshkrim | |
| Klikoni "Apliko" | Aplikimi dërgohet, butoni ndryshon gjendjen | |
| Tentoni të aplikoni përsëri | Butoni çaktivizuar ose mesazh "tashmë aplikuar" | |
| Kthehuni te Paneli i Vullnetarit → skeda Aplikimet | Aplikimi i ri tregohet si "Në pritje" | |

---

## RRJEDHA 7 — Krijimi i Kërkesës për Ndihmë (si Vullnetar)

| Hapi | Rezultati i Pritur | ✅ / ❌ |
|---|---|---|
| Hapni `views/help_requests.php` | Faqja ngarkohet | |
| Klikoni "Krijo Postim të Ri" | Modalja ose formulari hapet | |
| Dërgoni me titull bosh | Gabim validimi | |
| Plotësoni: Tipi=Kërkesë, Kategoria=Shëndetësi, Titulli, Kapaciteti=2, Vendndodhja=QSUT Tiranë, Përshkrimi | Formulari pranon hyrjen | |
| Dërgoni | Sukses — postimi tregon statusin "Në shqyrtim" (moderim në pritje) | |
| Kontrolloni listën publike `views/help_requests.php` | Kërkesa NUK është ende në listën publike (në pritje) | |

---

## RRJEDHA 8 — Privatësia e Vendndodhjes në Hartë (si Vullnetar)

| Hapi | Rezultati i Pritur | ✅ / ❌ |
|---|---|---|
| Të kyçur si vullnetar, hapni hartën | Shënuesit e kërkesave për ndihmë të dukshëm për përdoruesin e kyçur | |
| Klikoni një shënues kërkese ndihme | Popup tregon vetëm lagjen (jo adresën e saktë) | |
| Aplikoni për një kërkesë ndihme | Pas aplikimit, rifresko hartën — adresa e saktë tani tregohet | |
| Dilni, hapni hartën si përdorues anonim | Shënuesit e kërkesave për ndihmë nuk tregohen | |

---

## RRJEDHA 9 — Hyrja si Admin

| Hapi | Rezultati i Pritur | ✅ / ❌ |
|---|---|---|
| Hyni si `demo.admin@tiranasolidare.local` / `Demo123!` | Ridrejtuar te paneli i adminit | |
| Ngarkohet Dashboard-i | Statistikat (përdorues, evente, kërkesa, aplikime) tregojnë numra | |
| Asnjë gabim JS në konsolë gjatë ngarkimit | Konsolë e pastër | |
| Të gjitha skedat e shiritit anësor përgjigjen | Eventet, Kërkesat, Përdoruesit, Mesazhet, Njoftimet, Raportet të gjitha ngarkojnë përmbajtje | |

---

## RRJEDHA 10 — Admin: Krijoni një Event

| Hapi | Rezultati i Pritur | ✅ / ❌ |
|---|---|---|
| Dashboard → skeda Eventet → "Krijo Event" | Modalja për krijimin e eventit hapet | |
| Plotësoni: Titulli, Data (e ardhshme), Kategoria=Sociale, Kapaciteti=12, Vendndodhja=Laprakë Tiranë, Përshkrimi | Të gjitha fushat pranojnë hyrjen | |
| Plotësim automatik i vendndodhjes | Shkrimi i vendndodhjes tregon sugjerime gjeokodimi | |
| Ngarkoni një imazh banner | Parapamja e imazhit shfaqet | |
| Dërgoni | Mesazh suksesi, eventi shfaqet në listën e eventeve të adminit | |
| Navigoni te `views/events.php` (publik) | Eventi i ri është i dukshëm për publikun | |

---

## RRJEDHA 11 — Admin: Moderimi i Kërkesës për Ndihmë

| Hapi | Rezultati i Pritur | ✅ / ❌ |
|---|---|---|
| Dashboard → skeda Kërkesat | Kërkesat në pritje tregohen me distinktivë "Në shqyrtim" | |
| Klikoni një kërkesë në pritje | Detaji hapet | |
| Klikoni "Mirato" | Statusi ndryshon në "hapur" / miratuar | |
| Navigoni te publik `views/help_requests.php` | Kërkesa e miratuar tani e dukshme publikisht | |
| Testoni "Refuzo" në një kërkesë tjetër | Statusi ndryshon në refuzuar, hiqet nga lista publike | |

---

## RRJEDHA 12 — Admin: Menaxhimi i Përdoruesve

| Hapi | Rezultati i Pritur | ✅ / ❌ |
|---|---|---|
| Dashboard → skeda Përdoruesit | Lista e përdoruesve ngarkohet me rolet dhe statuset | |
| Kërkoni/filtroni sipas emrit | Filtron saktë | |
| Klikoni "Blloko" një përdorues testues | Përdoruesi bllokuar, statusi përditësohet | |
| Tentoni hyrjen si ai përdorues i bllokuar | Hyrja dështon me mesazh "bllokuar" | |
| Çbllokoni përdoruesin | Statusi kthehet në aktiv | |
| Tentoni ndryshimin e rolit të adminit tjetër | Duhet bllokuar (vetëm super_admin mund ta bëjë këtë) | |

---

## RRJEDHA 13 — Admin: Njoftim Masiv

| Hapi | Rezultati i Pritur | ✅ / ❌ |
|---|---|---|
| Dashboard → skeda Njoftimet | Paneli i njoftimeve ngarkohet | |
| Klikoni "Dërgo Njoftim" | Modalja hapet me fushën e mesazhit | |
| Shkruani një mesazh, dërgoni | Mesazh suksesi | |
| Hyni si vullnetar në skedën tjetër | Zilja e njoftimit tregon njoftim të ri | |
| Klikoni njoftimin | Shënohet si i lexuar | |

---

## RRJEDHA 14 — Formulari i Kontaktit & Kutia Postare e Adminit

| Hapi | Rezultati i Pritur | ✅ / ❌ |
|---|---|---|
| Hapni `views/contact.php` (pa qenë i kyçur) | Formulari ngarkohet | |
| Dërgoni me fusha boshe | Gabim validimi | |
| Plotësoni emrin, emailin, mesazhin → Dërgoni | Mesazh suksesi | |
| Hyni si admin → Dashboard → skeda Mesazhet | Mesazhi i ri shfaqet në kutinë postare | |
| Klikoni mesazhin → Përgjigju | Përgjigja dërguar (ose në radhë nëse emaili nuk është i konfiguruar) | |

---

## RRJEDHA 15 — Profili Publik

| Hapi | Rezultati i Pritur | ✅ / ❌ |
|---|---|---|
| Hapni `views/public_profile.php?id=X` (ID-ja e çdo përdoruesi aktiv) | Faqja e profilit ngarkohet me emër, statistika | |
| Shikoni historikun e vullnetarizmit | Eventet e frekuentuara të listuara | |
| Tentoni profilin e përdoruesit të bllokuar | Nuk duhet të jetë i aksesueshëm / tregon gabim | |

---

## RRJEDHA 16 — Tabela e Renditjes

| Hapi | Rezultati i Pritur | ✅ / ❌ |
|---|---|---|
| Hapni `views/leaderboard.php` | Ngarkon listën e vullnetarëve të renditur | |
| Ka të paktën disa hyrje | Jo bosh falë të dhënave të seed-uara | |

---

## RRJEDHA 17 — Responsiveness Mobile (Kontroll i Shpejtë)

| Hapi | Rezultati i Pritur | ✅ / ❌ |
|---|---|---|
| Hapni Chrome DevTools → Çelësi i shiritit të pajisjes (Ctrl+Shift+M) | |
| Vendosni gjerësi iPhone 12 (390px) | Homepage-i renderizohet pa lëvizje horizontale | |
| Kontrolloni faqen e eventeve | Kartat vendosen vertikalisht, pa tejkalim | |
| Kontrolloni dashboard-in në celular | Shiriti anësor mbyllet, ende i përdorshëm | |

---

## LISTA E KONTROLLIT TË FUNDIT NË DITËN E PREZANTIMIT (30 min para)

- [ ] Të dhënat e seed-uara ekzistojnë: të paktën 3 evente, 3 kërkesa ndihme, 2 përdorues të dukshëm
- [ ] `demo.admin@tiranasolidare.local` dhe `demo.elira@tiranasolidare.local` mund të hyjnë
- [ ] Të paktën një kërkesë ndihme është në gjendje **në pritje** (për demo-n live të moderimit)
- [ ] Të paktën një event është i ardhshëm (datë e ardhshme, jo i skaduar)
- [ ] Pllakat e hartës ngarkohen (kërkohet internet për serverin e pllakave Leaflet)
- [ ] Faqeshënuesit e shfletuesit vendosur për: Homepage, Eventet, Kërkesat, Harta, Dashboard, Paneli i Vullnetarit
- [ ] Rritni madhësinë e shkronjave në shfletues në 110–125% për lexueshmëri në projektor
- [ ] Të gjitha gabimet dhe njoftimet PHP të shtypur (kontrolloni `php.ini` `display_errors=Off` ose verifikoni asnjë tekst të kuq në ekran)
- [ ] Asnjë të dhënë personale / email real të dukshëm në llogaritë demo

---

## NËSE DIÇKA ËSHTË E PRISHUR PIKËRISHT PARA PREZANTIMIT

| Problemi | Rregullim i Shpejtë |
|---|---|
| Lista e eventeve bosh | Ekzekutoni: `php seed_sample_content.php` |
| Kërkesat bosh | Ekzekutoni: `php seed_sample_content.php` |
| Pllakat e hartës nuk ngarkohen | Kontrolloni lidhjen me internetin; alternativë — tregoni pamje ekrani |
| Hyrja dështon për llogaritë demo | Kontrolloni DB-në, rivendosni manualisht fjalëkalimin nëpërmjet `password_hash()` |
| Gabime të lidhura me emailin në faqe | Shtypni pamjen: mbështillni thirrjet e postës me `@` ose vendosni `display_errors=0` |
| Dashboard-i tregon 0 statistika | DB-ja mund të jetë bosh — ri-ekzekutoni skriptin e seed-imit |
| Plotësimi automatik i gjeokodimit nuk funksionon | Shkruani vendndodhjen manualisht; shpjegoni që gjeokodimi është një veçori opsionale API |
