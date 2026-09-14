# Implementatiespec — Google Analytics 4 in het Groei-overzicht

**Doel.** De GA4-cijfers binnentrekken in de bestaande admin, zodat je niet naar
Google hoeft. Geen apart scherm: alles landt op de bestaande **Verkeer**-pagina
(`/admin/search-console`), die daarmee twee vragen beantwoordt in plaats van één.

| | Vraag | Bron |
|---|---|---|
| Tab "Uit Google Zoeken" | Hoe vonden ze ons? | Search Console (bestaand) |
| Tab "Op de site" | Wat deden ze hier? | GA4 (nieuw) |
| Leads-scherm | Wat leverde het op? | eigen meting (bestaand) |

De kerncijfers van beide bronnen staan bóven de tabs en blijven dus altijd
zichtbaar; alleen de detailtabellen zitten achter een klik. Zie 7.1.

**Uitgangspunt.** De OAuth-machinerie in `GoogleSearchConsoleService` is bijna
volledig generiek. Alleen `SCOPE`, `API_BASE` en de setting-sleutels zijn
GSC-specifiek. Die laag hergebruiken we; er komt geen tweede tokenafhandeling bij.

---

## 1. Architectuurbeslissingen

### 1.1 Eén Google-koppeling, niet twee

Search Console en Analytics draaien op dezelfde Google Cloud-app en hetzelfde
account. We vragen daarom **één toestemming voor twee rechten** in plaats van
twee losse koppelingen:

```
scope = webmasters.readonly + analytics.readonly
```

`authorizationUrl()` zet al `include_granted_scopes=true`, dus incrementele
toestemming werkt. Gevolg voor bestaande installaties: het huidige refresh token
is uitgegeven **zonder** het Analytics-recht. Die moeten één keer opnieuw op
"Verbinden met Google" klikken. Dat is geen bug maar een eenmalige stap, en de UI
moet het uitleggen in plaats van een lege grafiek te tonen.

Bij het inwisselen van de code geeft Google de toegekende rechten terug in
`scope`. Die bewaren we in `Setting` `google_oauth_scopes`, zodat we exact weten
of Analytics meegekoppeld is zonder een API-call te moeten doen die faalt.

### 1.2 Gedeelde basisklasse, geen kopie — ✅ gebouwd

`GoogleSearchConsoleService` telde 549 regels waarvan ruwweg 250 tokenafhandeling:
ophalen, cachen, vernieuwen, `invalid_grant` afvangen, JWT ondertekenen voor het
service-account. Die code een tweede keer schrijven betekent dat een bugfix in de
ene helft de andere nooit bereikt.

Daarom: abstracte `App\Services\Google\GoogleApiClient` met het token- en
HTTP-gedeelte, geparametriseerd op scope, basis-URL, setting-voorvoegsel en
label. Beide diensten erven ervan.

```
app/Services/Google/GoogleApiClient.php        (nieuw, uit GSC gelicht, 392 regels)
app/Services/GoogleSearchConsoleService.php    (erft; 549 → 243 regels)
app/Services/GoogleAnalyticsService.php        (fase 2; erft)
```

De subklasse vult drie methodes in: `serviceAccountScope()`, `apiBase()` en
`label()`. Het setting-voorvoegsel staat in de basis op `google` en is
overschrijfbaar; daaruit leidt ze alle sleutels af (`<prefix>_oauth_client_id`,
`_oauth_client_secret`, `_refresh_token`, `_service_account_json`,
`_oauth_scopes`) én de tokencache.

Dit is bewust géén overtreding van de "pas abstraheren na drie keer"-regel: de
twee gebruikers bestaan allebei concreet en het gedeelde oppervlak is exact
hetzelfde, geen gok over een derde geval.

**Risico afgedekt.** `tests/Feature/SearchConsoleTest.php` is geen letter
gewijzigd en blijft groen: 12 tests voor én na. Daarbovenop bewijst
`tests/Feature/GoogleApiClientTest.php` (8 tests) op een verzonnen subklasse dat
de laag echt losstaat van Search Console — scope, basis-URL, sleutels en
tokencache volgen de subklasse, en `disconnect()` raakt enkel de eigen dienst.

**De setting-namen zijn pas in fase 3 hernoemd, bewust niet in fase 1.** Tijdens
de extractie was het verleidelijk om ze meteen neutraal te maken, maar de
bestaande tests leggen die namen vast. In dezelfde stap hernoemen betekent de
test aanpassen die net moet bewijzen dat er niets veranderde, en dan is de
vangrail weg. Fase 1 is daarom een zuivere extractie gebleven met `gsc_*` intact;
fase 3 verhuisde ze naar `google_*` met de migratie
`move_google_credentials_to_shared_keys`, die de oude rijen ook weghaalt zodat er
geen tweede kopie van een refresh token blijft rondslingeren.

### 1.3 Property-ID is niet het meet-ID

De `G-XXXXXXX` uit `google_analytics_id` is voor het meetscript op de site. De
Data API werkt met een **numeriek property-ID** (`properties/123456789`). Dat is
een tweede setting, `ga4_property_id`, en een klassieke struikelsteen bij het
koppelen.

We kiezen die net als bij Search Console automatisch: na het koppelen halen we de
lijst properties op en matchen op domein. Eén property, of één die bij het domein
past, wordt stilzwijgend gekozen. Meerdere en geen match: niets kiezen en de
gebruiker laten aanwijzen. Liever vragen dan de cijfers van een andere site
binnentrekken.

### 1.4 Wat we niet bouwen in v1

**Conversiegraad per landingspagina** (leads gedeeld door bezoeken) is het meest
waardevolle afgeleide cijfer en de echte brug tussen beide systemen. Toch nu niet:
de teller komt uit onze eigen meting en telt iedereen, de noemer komt uit GA4 en
telt alleen wie cookies aanvaardde. Dat geeft een percentage dat structureel te
hoog is, en een fout cijfer is erger dan geen cijfer. Pas zinvol als we de noemer
uit een eigen paginateller halen.

---

## 2. Op te leveren bestanden

### Nieuw

```
app/Services/Google/GoogleApiClient.php              gedeelde token- en HTTP-laag
app/Services/GoogleAnalyticsService.php              runReport + property-lijst
app/Services/Ga4Collector.php                        sync + uitleesmethodes
app/Models/Ga4DailyMetric.php
app/Models/Ga4DimensionMetric.php
app/Console/Commands/SyncAnalyticsCommand.php        seo:sync-analytics
app/Filament/Widgets/Ga4StatsOverview.php
database/migrations/…_create_ga4_daily_metrics_table.php
database/migrations/…_create_ga4_dimension_metrics_table.php
tests/Feature/AnalyticsCollectorTest.php
```

### Gewijzigd

```
app/Services/GoogleSearchConsoleService.php          erft van GoogleApiClient
app/Http/Controllers/SearchConsoleOAuthController.php  scope-lijst + property kiezen
app/Filament/Pages/SearchConsole.php                 GA4-blok, acties, $tab-state
resources/views/filament/pages/search-console.blade.php  cijferrij + tabstrip
routes/console.php                                   dagelijkse sync 6:15
CLAUDE.md                                            Groei-sectie bijwerken
```

---

## 3. Datamodel

Spiegelt bewust de GSC-tabellen, zodat collector en scherm dezelfde vorm houden.

### `ga4_daily_metrics`

| kolom | type | opmerking |
|---|---|---|
| property_id | string(64) | numeriek ID als string |
| date | date | |
| sessions | unsignedInteger | |
| active_users | unsignedInteger | |
| page_views | unsignedInteger | `screenPageViews` |
| engaged_sessions | unsignedInteger | |
| avg_session_seconds | decimal(8,2) | `averageSessionDuration` |

`unique(property_id, date)`.

### `ga4_dimension_metrics`

| kolom | type | opmerking |
|---|---|---|
| property_id | string(64) | |
| period_start / period_end | date | rollend venster van 28 dagen |
| dimension | string(16) | `page` of `channel` |
| value | text | pad of kanaalnaam |
| value_hash | char(32) | md5, draagt de uniciteit (paden zijn te lang voor een index) |
| sessions, page_views, engaged_sessions | unsignedInteger | |
| avg_session_seconds | decimal(8,2) | |

`unique(property_id, period_start, period_end, dimension, value_hash)` en
`index(property_id, dimension, period_end)`. Zelfde 191/char(32)-constructie als
bij `gsc_dimension_metrics`, om dezelfde reden.

---

## 4. De API-laag

### 4.1 `GoogleAnalyticsService`

```
API_BASE  https://analyticsdata.googleapis.com/v1beta
ADMIN_BASE https://analyticsadmin.googleapis.com/v1beta
SCOPE     https://www.googleapis.com/auth/analytics.readonly
```

**`runReport(string $start, string $end, array $dimensions, array $metrics, int $limit, int $offset): ?array`**

```json
POST /properties/{id}:runReport
{
  "dateRanges": [{ "startDate": "2026-08-01", "endDate": "2026-09-12" }],
  "dimensions": [{ "name": "date" }],
  "metrics":    [{ "name": "sessions" }, { "name": "activeUsers" }],
  "limit": 100000,
  "offset": 0
}
```

**`listProperties(): ?array`** — `GET /accountSummaries` op de Admin API, geeft
per account de properties met `property` (`properties/123`) en `displayName`.
Voedt de keuzelijst en de automatische keuze.

### 4.2 Drie verschillen met de Search Console-API

Deze zijn de reden dat de collector niet één op één te kopiëren is:

1. **Antwoordvorm.** GSC geeft `rows[].keys[]` plus benoemde velden. GA4 geeft
   `rows[].dimensionValues[].value` en `rows[].metricValues[].value`, allemaal
   als string, in de volgorde waarin je ze opvroeg. Positioneel uitlezen dus,
   met een vaste volgorde-constante in de service.
2. **Datumnotatie.** De `date`-dimensie komt terug als `20260912`, zonder
   streepjes. Bij Search Console is dat `2026-09-12`. Parsen met
   `Carbon::createFromFormat('Ymd', …)`.
3. **Geen `dataState`.** Search Console kent `final` om de laatste onvolledige
   dagen weg te laten. GA4 heeft dat niet, maar herziet de laatste dagen wel.
   We vangen dat op met hetzelfde rollende venster: elke sync haalt de laatste
   7 dagen opnieuw op en overschrijft per dag.

Let ook op de **`(other)`-rij**: bij veel verschillende paden bundelt GA4 de
staart in één rij met de waarde `(other)`. Die slaan we over bij het wegschrijven,
anders staat er een onzinnige "pagina" in de tabel.

---

## 5. `Ga4Collector`

Zelfde vorm als `GscCollector`.

```php
const BACKFILL_MONTHS = 16;      // parallel met GSC; ouder is zelden relevant
const REFETCH_DAYS = 7;          // Google herziet recente dagen
const DIMENSION_WINDOW_DAYS = 28;
const PAGE_SIZE = 100000;        // Data API staat tot 250.000 toe
```

**`sync(): array`** — eerste run doet de backfill, daarna het rollende venster.
Daarna de twee dimensies. Geeft terug hoeveel dagen, pagina's en kanalen.

**Dagcijfers** — dimensie `date`, metrics `sessions`, `activeUsers`,
`screenPageViews`, `engagedSessions`, `averageSessionDuration`.

**Dimensie `page`** — dimensie `pagePath`, zelfde metrics zonder `activeUsers`,
gesorteerd op `screenPageViews` aflopend, limiet 250 rijen. Dit is het cijfer
waar het je om te doen was: welke pagina's gelezen worden en hoe lang.

**Dimensie `channel`** — dimensie `sessionDefaultChannelGroup`, metric `sessions`.
Dit dicht het gat dat Search Console laat: verkeer dat níet uit Google Zoeken komt.

**Uitlezen voor het scherm** — `summary()`, `weeklyTrend()`, `top($dimension)`,
exact de tegenhangers van wat `GscCollector` al biedt, zodat de widgets hetzelfde
patroon volgen. `summary()` vergelijkt 28 dagen met de 28 ervoor; gemiddelde
sessieduur en engagement wegen we op sessies, niet als plat gemiddelde, om
dezelfde reden als de gewogen positie bij GSC.

**`isConfigured()`** checkt óók `Schema::hasTable('ga4_daily_metrics')`, zodat een
installatie die nog niet gemigreerd heeft een nette melding krijgt in plaats van
een 500.

---

## 6. Koppelen en instellen

### 6.1 OAuth-controller

`SearchConsoleOAuthController` blijft de enige consent-flow, maar vraagt voortaan
beide rechten. Na een geslaagde uitwisseling:

1. `google_oauth_scopes` wegschrijven uit het antwoord van Google.
2. Property van Search Console kiezen (bestaand gedrag).
3. **Nieuw:** GA4-property kiezen via `listProperties()` + domeinmatch.
4. De melding vertelt wat er gekoppeld is en wat er eventueel nog handmatig moet.

Route en omleidings-URI blijven ongewijzigd, dus er hoeft niets aan te passen in
Google Cloud behalve **de Analytics Data API en de Analytics Admin API aanzetten**.

### 6.2 Op de Verkeer-pagina

Een ingeklapte sectie "Google Analytics" naast de bestaande Google-koppeling, met
alleen het property-veld en een keuzelijst. Client-ID en secret worden gedeeld en
staan er al.

Extra kopacties:

- **Analytics verversen** — handmatige sync, zichtbaar als er een property staat.
- **Andere property kiezen** — keuzelijst uit `listProperties()`, alfabetisch.

De bestaande knop "Verbinden met Google" krijgt een aangepaste hulptekst wanneer
`google_oauth_scopes` het Analytics-recht mist: dan is opnieuw koppelen nodig.

---

## 7. Het scherm

Titel gaat van "Google-verkeer" naar **"Verkeer"**, want de pagina dekt nu meer
dan Search Console.

De opbouw: **kerncijfers altijd zichtbaar, detail achter tabs.**

```
Verkeer
├─ cijferrij  Uit Google Zoeken   clicks · vertoningen · CTR · positie
├─ cijferrij  Op de site          sessies · bezoekers · weergaven · engagement
├─ grafiek    weekverloop
└─ tabs
   ├─ Uit Google Zoeken   zoektermen · pagina's · kansen
   └─ Op de site          meest bekeken pagina's · kanalen
```

De GA4-kant voegt dus toe: één cijferrij (`Ga4StatsOverview`) en één tabblad met
twee tabellen. De pagina's-tabel toont pad, weergaven, sessies en gemiddelde
tijd; de kanalen-tabel toont kanaal, sessies en aandeel in procent.

### 7.1 Waarom tabs, en waarom de cijfers erbuiten

Alles onder elkaar zetten leek eerst logisch, maar dat levert geen vergelijking
op. De tweede helft staat dan een halve pagina lager, achter een cijferrij, een
grafiek en drie tabellen. Je moet dus scrollen langs dingen die je op dat moment
niet zocht. Dat is niet beter dan klikken.

Tabs zijn bovendien het huispatroon in deze admin: het pagina-formulier gebruikt
al Basis, SEO en Secties. En omdat de pagina een Livewire-component is, kan ze
enkel de cijfers ophalen van het tabblad dat openstaat. Op shared hosting scheelt
dat elke keer een paar queries.

Wat wél boven de tabs blijft staan zijn de twee cijferrijen. Dat is het enige
waarvoor je beide bronnen echt in één oogopslag wil: steeg het bezoek uit Google,
en deden die mensen dan ook iets. De detailtabellen lees je nooit tegelijk, dus
die mogen achter een klik.

Implementatie: publieke property `$tab` op de page-class, standaard `search`, met
`wire:click` op de tabknoppen. De blade roept `tables()` enkel aan voor het
actieve tabblad.

### 7.2 Geen tweede grafiek

Het weekverloop staat maar één keer op de pagina. Sessies volgen de clicks-lijn
vrij strak, dus een tweede grafiek voegt vorm toe zonder inzicht.

### 7.3 Mogelijke verbetering later

De pagina's-tabel bestaat straks twee keer: in het ene tabblad de pagina's die
clicks uit Google krijgen, in het andere de pagina's die het meest bekeken
worden. Dat is grotendeels dezelfde lijst. Die twee samenvoegen tot één tabel
(pagina, clicks uit Google, totale weergaven, gemiddelde tijd) leest beter dan
twee aparte, en zou meteen een deel van de reden voor het tweede tabblad
wegnemen. Nu niet gedaan omdat het de twee collectors in de view aan elkaar
knoopt. Eerst zien of de losse tabellen in de praktijk storen.

### 7.4 Lege toestanden en kleine lettertjes

Onder de GA4-cijferrij één regel in kleine grijze tekst: deze cijfers tellen
alleen bezoekers die analytische cookies aanvaardden, dus ze liggen lager dan de
Search Console-cijfers en dan het aantal leads. Niet omdat het verwarrend is voor
jou, maar omdat een klant die meekijkt anders de verkeerde vraag stelt.

Lege toestanden volgen het bestaande patroon: tabellen ontbreken, niet gekoppeld,
gekoppeld zonder property, gekoppeld zonder data. Elk met de concrete
vervolgstap, niet met een lege grafiek.

Styling blijft inline in de blade, want Filament laadt de app-Tailwind niet.

---

## 8. Sync-planning

```php
// GA4-cijfers ophalen. Kwartier na Search Console, zodat de twee syncs
// elkaar niet in de weg zitten op een trage shared host.
Schedule::command('seo:sync-analytics')
    ->dailyAt('6:15')
    ->withoutOverlapping();
```

Op Combell draait de scheduler-worker al elke minuut, dus hier is niets extra
nodig.

---

## 9. Tests

`tests/Feature/AnalyticsCollectorTest.php`, met `Http::fake()` op de Data API:

1. Eerste sync schrijft dagrijen weg en zet de juiste datums, inclusief het
   `20260912`-formaat.
2. Tweede sync overschrijft bestaande dagen in plaats van te verdubbelen.
3. Paden en kanalen landen in `ga4_dimension_metrics`; de `(other)`-rij wordt
   overgeslagen.
4. `summary()` rekent het verschil met de vorige periode correct uit.
5. De Verkeer-pagina rendert zonder property en zonder tabellen, zonder fout.
6. Een mislukte API-call laat de bestaande cijfers staan en logt.

`SearchConsoleTest.php` moet ongewijzigd groen blijven, dat is de vangrail onder
de extractie uit 1.2.

---

## 10. Volgorde van werken

| Fase | Wat | Klaar als | Stand |
|---|---|---|---|
| 0 | Meetcode + meet-ID-veld op de site | zonder ID geen enkel verzoek naar Google | ✅ |
| 1 | `GoogleApiClient` extraheren, GSC laten erven | `SearchConsoleTest` groen, niets aan gedrag veranderd | ✅ |
| 2 | Migraties, modellen, `GoogleAnalyticsService`, `Ga4Collector` | sync-command haalt echte cijfers op | ✅ |
| 3 | Scope uitbreiden, sleutels hernoemen, property kiezen bij de callback | opnieuw koppelen levert property automatisch op | ✅ |
| 4 | Widget, tabstrip, twee tabellen op de Verkeer-pagina | scherm toont cijfers, lege toestanden kloppen | ✅ |
| 5 | Scheduler, tests, CLAUDE.md | dagelijkse sync draait | ✅ |

**Fase 0 kwam erbij tijdens het bouwen.** De spec ging ervan uit dat de meetcode
al ergens draaide, maar op webgoeroe stond ze nergens — enkel in raaminzicht. En
omdat Analytics niets toont van vóór de dag dat het script draait, is dat het
enige stuk dat écht niet kan wachten. Daarom eerst gebouwd.

146 tests groen. Wat er nog met de hand moet gebeuren staat in 12.

---

## 11. Daarna

Werkt dit op webgoeroe, dan hoort het terug in de **`seo-analytics`-skill**, zoals
de Search Console-module ook gegaan is. Pas dan rolt het mee naar elpablo,
kizombabelgium, ark-van-noe en raaminzicht zonder handwerk.

**Voorwaarde die los staat van deze spec:** op elke site moet het meetscript al
draaien voordat er iets te tonen valt. GA4 heeft geen geheugen met terugwerkende
kracht, anders dan de zestien maanden die Search Console cadeau gaf. De meetcode
zetten is dus het eerste, dit scherm het tweede.

---

## 12. Wat jij nog met de hand moet doen

De code is klaar; deze vier stappen kan geen enkele deploy voor je doen.

**1. In Google Analytics** — maak een property aan voor dewebgoeroe.be als die
er nog niet is, en noteer twee nummers uit twee verschillende schermen:

| Nummer | Waar | Waarvoor |
|---|---|---|
| `G-XXXXXXXXXX` | Beheer → Gegevensstreams → de webstream | Instellingen → Algemeen |
| een getal | Beheer → Property-instellingen | Verkeer → Google Analytics |

Ze door elkaar halen is de klassieke fout: het eerste laat de site meten, het
tweede laat de admin de cijfers ophalen.

**2. In Google Cloud** — zet in hetzelfde project waar je OAuth-client al staat
de **Analytics Data API** én de **Analytics Admin API** aan. Zonder die twee
geeft Google wel toestemming, maar faalt elke call.

**3. In de admin** — vul het `G-`-ID in bij Instellingen → Algemeen en sla op.
Vanaf dat moment meet de site, mits de bezoeker cookies aanvaardt. Doe dit
eerst: elke dag dat je wacht is een dag die je later niet kan opvragen.

**4. Analytics mee koppelen** — op de Verkeer-pagina verschijnt bovenaan de knop
**"Analytics mee koppelen"**. Die stuurt je naar het toestemmingsscherm van
Google, dat nu beide rechten tegelijk vraagt.

Je moet de bestaande koppeling dus **niet** eerst verbreken. Google kan een
uitgegeven token niet achteraf uitbreiden, dus je moet wél opnieuw door het
toestemmingsscherm — maar je Search Console-cijfers en -instellingen blijven
gewoon staan. Na afloop kiest hij de Analytics-property zelf en verschijnt de
knop "Analytics verversen".

Daarna draait de sync elke ochtend om 6:15 vanzelf mee.

### Let op bij de eerste dagen

Het tabblad "Op de site" blijft leeg tot er iets te meten valt, en dat is
normaal. Analytics heeft geen geheugen met terugwerkende kracht: de eerste
cijfers verschijnen pas een dag nadat de meetcode live staat. Search Console gaf
je bij het koppelen zestien maanden cadeau; hier begin je op nul.
