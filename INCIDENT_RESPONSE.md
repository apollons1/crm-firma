# Incident Response Playbook — CRM AktivTherm

Document acțional pentru situații de urgență. Citește doar scenariul relevant, nu tot documentul.

**Infrastructură de bază:**
- Site: `crm.aktivtherm.com`, în spatele Cloudflare
- Server: Forge (server ID 1231887) → Hetzner *(de verificat — vezi nota de la finalul documentului)*
- Repo: `github.com/apollons1/crm-firma`, branch `main` = producție (push pe `main` declanșează deploy automat)
- Backup: Spatie Backup, zilnic (01:00 curățare, 02:00 backup nou), stocat local (`storage/app/private/CRM Firma/`) și pe Backblaze B2 (bucket `crm-firma-backups`)
- Monitorizare: `/health` (verifică DB, Redis, storage, backup, Stripe — vizitează direct în browser sau `curl`), Sentry (tracking erori), UptimeRobot (monitorizare activă, dar alertele automate prin webhook NU sunt conectate — vezi nota de mai jos)

---

## Scenariul 1: Site-ul nu răspunde

1. **Verifică `https://crm.aktivtherm.com/health`** direct din browser sau `curl` — răspunsul îți spune exact ce e stricat (`database`, `redis`, `storage`, `backup` sau `stripe`), nu doar "e jos". Status 503 = ceva e stricat; vezi câmpul `checks` din JSON pentru detalii.
   - *Notă: UptimeRobot monitorizează activ site-ul, dar alertele automate prin webhook nu sunt conectate (cost suplimentar, nejustificat la volumul actual) — deci nu vei primi automat un email/notificare de la UptimeRobot fără să verifici manual dashboard-ul lor.*
2. **Login Forge** (forge.laravel.com) → serverul → verifică status-ul (CPU/RAM/disk, dacă serverul răspunde la SSH).
3. **Dacă serverul e down**: restart din Forge UI (Server → butonul de reboot) sau direct din consola Hetzner.
4. **Dacă serverul e up dar aplicația nu răspunde**: verifică `storage/logs/laravel.log` pe server (Forge → Site → SSH, sau prin comanda Forge de rulat comenzi de la distanță) pentru eroarea exactă. Verifică și Sentry (dacă e o excepție necapturată, apare acolo automat).
5. **Dacă un deploy recent a stricat ceva**: Forge → Site → Deployments → poți reface deploy-ul unui commit anterior din istoric. Dacă opțiunea nu e disponibilă direct din UI, alternativa sigură: `git revert <commit-ul problematic>` pe `main` și `git push` — asta declanșează automat un deploy nou cu codul revertit.
6. **Comunică echipei** prin email/grupul WhatsApp intern — spune ce s-a întâmplat, ce faci, și o estimare de timp.

## Scenariul 2: Cineva s-a logat fără autorizare

1. **URGENT: schimbă parola** contului compromis (și a oricărui alt cont admin, dacă nu ești sigur ce anume a fost accesat). Middleware-ul de securitate al panoului (`AuthenticateSession`) delogează automat orice sesiune activă a acelui cont la prima cerere ulterioară, pentru că detectează schimbarea parolei — nu ai nevoie de o comandă separată de "logout all" pentru contul respectiv.
2. **Pentru delogarea FORȚATĂ a tuturor userilor** (măsură radicală, dacă nu știi cât de larg e compromisul): sesiunile sunt stocate în Redis în producție — repornirea serviciului Redis pe server (`sudo service redis-server restart`, prin SSH/Forge) elimină toate sesiunile active, forțând pe toată lumea să se logheze din nou. (Atenție: asta golește și cache-ul aplicației, nu doar sesiunile — e un efect secundar acceptabil într-un incident de securitate.)
3. **Confirmă că 2FA e activ** pe contul afectat — e deja obligatoriu pentru `super_admin`/`admin`; dacă vreun cont important NU are 2FA configurat, e un semnal suplimentar de investigat (cont creat/modificat suspect).
4. **Verifică Sentry** pentru orice eroare/activitate neobișnuită din jurul momentului suspect, și tabelul `failed_login_attempts` (poți rula manual `php artisan security:detect-suspicious-logins` pentru un raport imediat de brute-force/credential stuffing).
5. **Notifică toți userii** să-și schimbe parolele, mai ales dacă nu ești sigur ce cont anume a fost compromis.
6. **Audit modificări recente**: verifică `updated_at` pe tabelele critice (`users`, `opportunities`, `payments`) pentru modificări din jurul incidentului, plus `storage/logs/laravel.log`.

## Scenariul 3: Stripe webhook nu mai funcționează

1. **Verifică `status.stripe.com`** — poate fi o problemă globală Stripe, nu a ta.
2. **Verifică Sentry** — dar reține: dacă webhook-ul respinge un request din cauza semnăturii invalide, controller-ul prinde excepția intern și răspunde cu 400 fără să arunce mai departe — deci NU apare automat în Sentry. Pentru acest tip de eșec, **Stripe Dashboard → Developers → Webhooks → Recent deliveries** e sursa de adevăr, nu Sentry.
3. **Verifică `STRIPE_WEBHOOK_SECRET`** din `.env` de pe Forge — dacă a fost regenerat din greșeală în Stripe Dashboard fără să actualizezi și aici, toate semnăturile pică.
4. **Important**: acest cont Stripe e **partajat cu Selgora** — webhook-ul procesează DOAR evenimentele care au `metadata.opportunity_id` (create din acest CRM). Dacă vezi în Stripe Dashboard evenimente eșuate/ignorate care NU au acest metadata, sunt de la Selgora și e comportament normal, nu o eroare de investigat aici.
5. **Procesează manual plățile primite în interval**: Stripe Dashboard → Payments, filtrează pe interval orar, verifică fiecare plată relevantă (cu `metadata.opportunity_id`) și actualizează manual oportunitatea corespunzătoare din CRM dacă webhook-ul nu a apucat s-o proceseze.

## Scenariul 4: Backup-ul nu s-a făcut de 2+ zile

1. **Verifică rapid din `/health`** (câmpul `checks.backup`) — îți spune direct dacă ultimul backup e mai vechi de 25 de ore, fără să te loghezi pe server.
2. **SSH pe server** → `php artisan schedule:list` → confirmă că `backup:clean` (01:00) și `backup:run` (02:00) apar programate corect.
3. **Verifică local**: `storage/app/private/CRM Firma/` (pe disk, sub directorul site-ului din Forge) — are fișiere recente?
4. **Verifică Backblaze B2** (bucket `crm-firma-backups`) — care e data ultimului upload?
5. **Forțează manual**: `php artisan backup:run` din SSH, și urmărește output-ul pentru eroarea exactă dacă eșuează.
6. **Investighează cauza** în `storage/logs/laravel.log` din jurul orei 02:00 (spațiu insuficient pe disk, credențiale Backblaze expirate/greșite, etc.).

## Scenariul 5: Hard disk plin

1. **Verifică rapid din `/health`** — dacă spațiul liber a scăzut sub prag, comanda automată de monitorizare (`monitoring:check-server-health`, rulează la fiecare 5 minute) ar fi trebuit deja să trimită o alertă email către `super_admin` când spațiul liber a scăzut sub 5GB. Dacă n-ai primit-o, verifică și că scheduler-ul rulează (`php artisan schedule:list`).
2. **SSH** → `df -h` pentru procentul de ocupare pe partiție.
3. **`du -sh /home/forge/crm.aktivtherm.com/storage/*`** pentru a vedea exact ce ocupă spațiu (de obicei `logs/` sau backup-uri locale vechi necurățate).
4. **Curăță**: log-uri vechi din `storage/logs/` (rotește/șterge fișierele `laravel-*.log` mai vechi), backup-uri locale din `storage/app/private/CRM Firma/` (le ai deja în siguranță pe Backblaze B2, deci pot fi șterse local după verificare).
5. **Dacă persistă**: Forge → Server → Resize, pentru upgrade de plan Hetzner.

## Scenariul 6: Atac DDoS detectat

1. **Activează Cloudflare "Under Attack Mode"** (Cloudflare Dashboard → domeniul → Overview, sau tab-ul Security).
2. **Verifică Cloudflare → Analytics/Security Events** pentru IP-urile/țările sursă ale traficului anormal.
3. **Blochează IP-urile suspecte** — ideal direct din Cloudflare (Security → WAF → IP Access Rules), care oprește traficul înainte să ajungă la server; alternativ la nivel de firewall Hetzner dacă e nevoie.
4. **Notifică suportul Hetzner** dacă atacul e amplu — au propria protecție DDoS la nivel de rețea care poate fi activată/escaladată.

## Scenariul 7: Dezastru total (server pierdut)

**NU intra în panică — ai backup pe Backblaze B2, separat de server.**

1. **Provizionează server nou** prin Forge (5-10 minute).
2. **Configurează domeniul** `crm.aktivtherm.com` să pointeze la noul IP (actualizare DNS — la providerul unde ai domeniul, sau în Cloudflare dacă DNS-ul e gestionat acolo).
3. **Restaurează**:
   - **Cod**: `git clone git@github.com:apollons1/crm-firma.git` (sau configurează site-ul nou direct din Forge, care face acest pas automat la conectarea repo-ului).
   - **Bază de date**: descarcă cel mai recent backup din Backblaze B2 (bucket `crm-firma-backups`), dezarhivează, restaurează dump-ul SQL (`.sql` din arhiva Spatie Backup) în noua bază de date.
   - **Fișiere uploadate**: descarcă din Backblaze B2 orice fișiere din `storage/app` incluse în backup.
4. **Așteaptă propagarea DNS** (5-30 minute, uneori mai mult în funcție de TTL).
5. **Verifică funcționalitatea**: `/health` întâi (confirmă DB/Redis/storage/Stripe), apoi login manual în panou, apoi un test complet (creează un client de test, verifică WhatsApp/email dacă e cazul).

**Pierdere maximă de date**: intervalul dintre ultimul backup reușit (verificabil din `/health`) și momentul incidentului — în mod normal sub 24h, dat fiind backup-ul zilnic.

---

## Contacte de urgență

- **Hosting (Hetzner sau Contabo? — de confirmat, vezi nota de mai jos)**: _de completat_
- **Forge**: suport prin dashboard-ul Forge (forge.laravel.com → Help), sau `help@laravel.com` — _de confirmat_
- **Stripe**: dashboard.stripe.com → Help (chat live pentru conturi active)
- **Twilio**: support.twilio.com

---

## Comanda în caz de panică

Dacă chiar nu știi ce să faci, deschide Claude Code și scrie:

> 🤖 URGENT: am următoarea situație [descrie]. Ghidează-mă pas cu pas să rezolv. Sunt în panică, dă-mi pași clari și mici.

Claude Code e antrenat să răspundă calm și clar la situații de urgență.

---

**Notă despre acest document**: două detalii din draftul inițial nu au putut fi confirmate din contextul tehnic disponibil și au fost marcate mai sus cu "_de completat_"/"_de confirmat_": (1) furnizorul de hosting real (Hetzner a fost menționat explicit într-o conversație anterioară, dar draftul acestui document lista "Contabo" la contacte — verifică și corectează manual), și (2) adresa de suport Forge exactă. Completează-le direct în acest fișier data viitoare când ai răspunsul la îndemână.
