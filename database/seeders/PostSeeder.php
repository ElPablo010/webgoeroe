<?php

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        $posts = [
            [
                'slug'         => 'voice-to-text-10-uur-per-week-besparen',
                'title'        => 'Voice-to-text: hoe ik meer dan 10 uur per week bespaar zonder te typen',
                'excerpt'      => 'Hoeveel uur per dag besteed jij aan typen? E-mails, berichten, rapporten, notities — het lijkt onschuldig, maar tel het eens op. Voor de meeste professionals gaat het om twee tot drie uur per dag. Dat is meer dan 10 uur per week aan een activiteit die je brein vertraagt in plaats van versnelt.',
                'tags'         => ['AI', 'Productiviteit'],
                'author_name'  => 'De Webgoeroe',
                'author_bio'   => 'Bij De Webgoeroe helpen we ondernemers groeien online — met slimme websites en AI-tools die echt tijd besparen.',
                'published'    => true,
                'featured'     => true,
                'published_at' => '2026-02-25 09:00:00',
                'meta_title'   => 'Voice-to-text: 10 uur per week besparen zonder te typen',
                'meta_description' => 'Ontdek hoe voice-to-text tools je helpen om meer dan 10 uur per week te besparen. Praktische tips voor e-mails, rapporten en creatief werk.',
                'body'         => <<<'HTML'
<p>Hoeveel uur per dag besteed jij aan typen? E-mails, berichten, rapporten, notities — het lijkt onschuldig, maar tel het eens op. Voor de meeste professionals gaat het om twee tot drie uur per dag. Dat is meer dan 10 uur per week aan een activiteit die je brein vertraagt in plaats van versnelt.</p>

<p>Wat als je die uren terug kon krijgen? Niet door harder te werken, maar door slimmer te communiceren. In dit artikel laat ik zien hoe voice-to-text dat mogelijk maakt — en waarom het een van de meest onderschatte productiviteitstools is.</p>

<h2>Het probleem: typen is traag, maar we zijn het gewend</h2>

<p>De gemiddelde professional typt zo'n 40 woorden per minuut. Spreken? Dat gaat drie tot vier keer sneller. Toch blijven we typen alsof er geen alternatief bestaat.</p>

<p>Het echte probleem is niet alleen de snelheid. Typen vraagt coördinatie: je denkt iets, vertaalt het naar woorden, en tikt die letter voor letter in. Ondertussen verlies je de flow van je gedachte. Je corrigeert spelfouten, herschikt zinnen, raakt afgeleid door autocorrect.</p>

<p>Het gevolg: je brein werkt op halve kracht terwijl je vingers het tempo bepalen.</p>

<h2>Waarom dit probleem blijft bestaan</h2>

<p>De meeste mensen associëren voice-to-text nog met de dicteertools van tien jaar geleden. Onnauwkeurig, geforceerd, en vol fouten. Dat beeld klopt niet meer.</p>

<p>Moderne AI-gedreven tools begrijpen context, herkennen nuance en leveren tekst die je nauwelijks hoeft aan te passen. Toch blijft de adoptie laag — simpelweg omdat mensen niet weten hoe ver de technologie inmiddels is.</p>

<blockquote><p>Typen voelt productief, ook al is het dat objectief gezien niet altijd. We verwarren bezig zijn met effectief zijn.</p></blockquote>

<h2>Hoe voice-to-text dit oplost</h2>

<p>Met een tool als Wispr Flow spreek je gewoon in wat je wilt zeggen. De AI zet je woorden direct om naar vloeiende, contextbewuste tekst — in het juiste register en de juiste toon.</p>

<p>Geen spelfouten. Geen heen-en-weer schuiven van zinnen. Gewoon jouw gedachten, rechtstreeks op het scherm. Het werkt in je e-mailclient, in documenten, in chatapps — overal waar je normaal zou typen.</p>

<p>Het verschil met ouderwetse dicteertools? Wispr Flow begrijpt niet alleen wát je zegt, maar ook hóe je het bedoelt. Het resultaat leest alsof je het zorgvuldig hebt uitgetypt.</p>

<h2>Concrete use-cases</h2>

<p><strong>E-mails en berichten:</strong> In plaats van vijf minuten te typen aan een klantreactie, spreek je het in dertig seconden in. De AI levert een gepolijste versie die je alleen nog hoeft te checken.</p>

<p><strong>Rapporten en notities:</strong> Na een meeting dump je je gedachten via spraak. Geen witte-blad-syndroom, geen uren zwoegen op formuleringen. Je spreekt, de tekst verschijnt.</p>

<p><strong>Creatief werk:</strong> Brainstormen gaat sneller wanneer je niet wordt afgeremd door een toetsenbord. Spreek je ideeën uit, en werk ze daarna uit in je eigen tempo.</p>

<p><strong>Administratie:</strong> CRM-notities, projectupdates, interne communicatie — alles wat je normaal intypt, spreek je nu in. De tijdswinst stapelt zich razendsnel op.</p>

<h2>Wanneer voice-to-text zinvol is</h2>

<p><strong>Het loont wanneer:</strong></p>
<ul>
<li>Je dagelijks meer dan een uur besteedt aan typen (e-mails, berichten, documenten)</li>
<li>Je merkt dat je gedachten sneller gaan dan je vingers</li>
<li>Je regelmatig last hebt van het witte-blad-syndroom</li>
<li>Je veel communiceert via tekst maar liever zou praten</li>
</ul>

<p><strong>Het is minder geschikt wanneer:</strong></p>
<ul>
<li>Je werkt in een lawaaierige omgeving zonder headset</li>
<li>Je voornamelijk technische of code-achtige teksten schrijft</li>
<li>Je al zeer snel typt en weinig frictie ervaart</li>
</ul>

<h2>Onze ervaring</h2>

<p>Eerlijk? Ik had dit zelf niet verwacht. Maar sinds ik voice-to-text gebruik, bespaar ik consequent meer dan 10 uur per week. Die uren gaan nu naar strategie, creatief nadenken en werk dat écht impact maakt.</p>

<p>Het is een van die tools waarvan je achteraf denkt: waarom heb ik dit niet eerder gedaan? Geen complexe setup, geen leercurve. Gewoon inspreken en aan de slag.</p>

<p>Bij De Webgoeroe helpen we ondernemers om dit soort slimme tools te integreren in hun dagelijkse workflow. Niet als gimmick, maar als structurele tijdsbesparing die zich elke dag terugbetaalt.</p>
HTML,
            ],

            [
                'slug'         => 'manuele-notities-meetings-automatiseren',
                'title'        => 'Maak jij nog manuele notities tijdens meetings? Zo automatiseer je het volledig',
                'excerpt'      => 'Je zit in een online meeting. Terwijl je luistert, typ je mee. Na afloop zet je de actiepunten één voor één over naar je to-do-lijst. Klinkt herkenbaar? Dan ben je niet alleen. De meeste professionals doen dit nog dagelijks. Maar het is eerlijk gezegd compleet overbodig.',
                'tags'         => ['AI', 'Automatisering'],
                'author_name'  => 'De Webgoeroe',
                'author_bio'   => 'Bij De Webgoeroe helpen we ondernemers groeien online — met slimme websites en AI-tools die echt tijd besparen.',
                'published'    => true,
                'featured'     => false,
                'published_at' => '2026-02-19 09:00:00',
                'meta_title'   => 'Manuele notities tijdens meetings automatiseren met AI',
                'meta_description' => 'Hoe je meeting-notities, samenvattingen en opvolging volledig automatiseert — van opname tot taak in Notion, zonder tussenkomst.',
                'body'         => <<<'HTML'
<p>Je zit in een online meeting. Terwijl je luistert, typ je mee. Na afloop zet je de actiepunten één voor één over naar je to-do-lijst. Klinkt herkenbaar?</p>

<p>Dan ben je niet alleen. De meeste professionals doen dit nog dagelijks. Maar het is eerlijk gezegd compleet overbodig.</p>

<p>In dit artikel laten we zien hoe je meeting-notities, samenvattingen en opvolging volledig automatiseert — van opname tot taak in Notion, zonder tussenkomst.</p>

<h2>Het probleem: manueel notuleren kost focus én tijd</h2>

<p>Tijdens een meeting wil je scherp zijn. Luisteren, meedenken, de juiste vragen stellen. Maar als je tegelijk notities maakt, splits je je aandacht.</p>

<p>Na de meeting begint het echte werk pas: actiepunten formuleren, verslagen uittypen, taken aanmaken in je projecttool. Dat kost al snel 15 tot 30 minuten per gesprek.</p>

<p>Bij drie meetings per dag ben je dus anderhalf uur kwijt aan administratie die niets toevoegt aan het gesprek zelf.</p>

<h2>Waarom dit probleem blijft bestaan</h2>

<p>De meeste teams weten dat het inefficiënt is. Maar ze denken dat de enige oplossing is: beter notuleren, of een collega vragen om het over te nemen.</p>

<p>Dat lost het structurele probleem niet op. Manueel notuleren schaalt niet. Hoe meer meetings, hoe meer tijd je verliest. En hoe groter de kans dat actiepunten vergeten worden of te laat worden opgepikt.</p>

<p>Bovendien ontbreekt bij veel teams de kennis dat tools als Fireflies niet alleen opnemen en transcriberen, maar ook de brug kunnen slaan naar je projectmanagement — volledig automatisch.</p>

<h2>Hoe AI-workflows dit oplossen</h2>

<p>Met een tool als Fireflies.ai heb je een virtuele assistent die:</p>
<ul>
<li>Je meeting automatisch opneemt</li>
<li>Alles transcribeert (spraak naar tekst)</li>
<li>Een duidelijke samenvatting genereert</li>
<li>De actiepunten automatisch herkent</li>
</ul>

<p>Maar daar stopt het voor de meeste gebruikers. Ze lezen de samenvatting, en zetten de taken alsnog handmatig over.</p>

<p>De echte winst begint pas wanneer je die actiepunten <strong>automatisch in je systeem</strong> laat terechtkomen.</p>

<h2>Concrete use-case: Fireflies + Notion</h2>

<p>Wij hebben deze stap zelf gebouwd. Zo werkt het:</p>
<ol>
<li>Fireflies neemt het gesprek op en detecteert de actiepunten</li>
<li>Een automatisering pikt die actiepunten op zodra de meeting eindigt</li>
<li>Elk actiepunt verschijnt automatisch als taak in Notion, inclusief context en deadline</li>
</ol>

<p>Geen kopiëren. Geen plakken. Geen "ik doe het straks wel".</p>

<p>Het resultaat is een naadloze keten van gesprek → samenvatting → actie, zonder dat je er iets voor hoeft te doen.</p>

<h2>Wanneer deze automatisering zinvol is</h2>

<p><strong>Het loont wanneer:</strong></p>
<ul>
<li>Je meerdere meetings per week hebt (intern of extern)</li>
<li>Actiepunten regelmatig vergeten of te laat opgepakt worden</li>
<li>Je team werkt met een projecttool zoals Notion, Asana of Trello</li>
<li>Je notuleren als tijdverspilling ervaart maar het toch blijft doen</li>
</ul>

<p><strong>Het is nog te vroeg wanneer:</strong></p>
<ul>
<li>Je zelden online meetings hebt</li>
<li>Je nog geen vaste projecttool gebruikt (begin daar eerst mee)</li>
<li>Je meetings geen duidelijke actiepunten opleveren — dan is het probleem structureler</li>
</ul>

<h2>Onze aanpak</h2>

<p>Bij De WebGoeroe geloven we dat tools pas krachtig worden wanneer ze samenwerken als één systeem. Fireflies alleen is handig. Fireflies gekoppeld aan Notion is een gamechanger.</p>

<p>Wij kijken naar jouw dagelijkse workflow en identificeren waar je tijd verliest aan herhaalwerk. Van daaruit bouwen we een automatisering die past bij jouw tools en manier van werken.</p>

<p>Geen maandenlange trajecten. Geen complexe IT-projecten. Gewoon een slimme ingreep die je vanaf dag één tijd bespaart.</p>
HTML,
            ],

            [
                'slug'         => 'onbeantwoorde-telefoon-sauna-109000-per-jaar',
                'title'        => 'Hoe een onbeantwoorde telefoon een sauna €109.000 per jaar kost',
                'excerpt'      => 'Je wilt €300 uitgeven bij een sauna. Een massage bijboeken. Even snel checken of er nog plaats is. De website? Alleen een contactformulier met: "We nemen contact op." Je belt. Geen gehoor. Dan surf je naar de concurrent die wél meteen duidelijkheid geeft. Dit is geen fictie.',
                'tags'         => ['AI', 'Automatisering'],
                'author_name'  => 'De Webgoeroe',
                'author_bio'   => 'Bij De Webgoeroe helpen we ondernemers groeien online — met slimme websites en AI-tools die echt tijd besparen.',
                'published'    => true,
                'featured'     => false,
                'published_at' => '2026-02-10 09:00:00',
                'meta_title'   => 'Hoe een onbeantwoorde telefoon je €109.000 per jaar kost',
                'meta_description' => 'Eén gemiste klant per dag kan meer dan €100.000 per jaar kosten. Ontdek hoe slimme automatisering dat lek moeiteloos dicht.',
                'body'         => <<<'HTML'
<p>Je wilt €300 uitgeven bij een sauna. Een massage bijboeken. Even snel checken of er nog plaats is.</p>

<p>De website? Alleen een contactformulier met: "We nemen contact op."</p>

<p>Je belt. Geen gehoor. Nog eens. Weer niets. En dan?</p>

<p>Dan surf je naar de concurrent die wél meteen duidelijkheid geeft.</p>

<p>Dit is geen fictie. Dit overkwam ons gisteren.</p>

<p>En het legt een probleem bloot dat veel dienstverlenende bedrijven onderschatten: <strong>Bereikbaarheid is omzet.</strong></p>

<p>In dit artikel tonen we hoe één gemiste klant per dag je meer dan €100.000 per jaar kan kosten — en hoe slimme automatisering dat lek moeiteloos dicht.</p>

<h2>Het probleem: klanten willen nú een antwoord</h2>

<p>Klanten nemen geen genoegen meer met "we bellen je terug." Ze willen directe duidelijkheid: is er plek? Kan ik boeken? Wat kost het?</p>

<p>Toch werken veel bedrijven nog met eindeloze contactformulieren, voicemails of telefoonnummers die alleen tijdens kantooruren bereikbaar zijn. Ondertussen scrollt de klant al door naar het volgende zoekresultaat.</p>

<p>Dit zien we keer op keer bij klanten in de wellness-, horeca- en dienstensector. Ze investeren duizenden euro's in marketing, maar laten de telefoon onbeantwoord op het moment dat het ertoe doet.</p>

<h2>Waarom dit probleem blijft bestaan</h2>

<p>De meeste ondernemers wéten dat ze moeilijk bereikbaar zijn. Maar ze denken dat de oplossing is: meer personeel aannemen, of zelf vaker de telefoon opnemen.</p>

<p>Dat werkt niet. Je therapeut zit in een behandeling. Je receptionist is met een andere klant bezig. En na sluitingstijd neemt er simpelweg niemand op.</p>

<p>Het structurele probleem? <strong>Menselijke bereikbaarheid schaalt niet.</strong> Je kunt niet 24/7 iemand aan de lijn hebben voor elke mogelijke vraag. En een contactformulier creëert frictie op het moment dat de klant beslissingsgericht is.</p>

<p>Ondertussen groeit de kloof tussen wat klanten verwachten (direct, altijd, moeiteloos) en wat de meeste bedrijven bieden (traag, beperkt, omslachtig).</p>

<h2>Wat automatisering hier wél oplost</h2>

<p>Automatisering vervangt geen mensen — het vangt de gaten op die mensen niet kunnen vullen.</p>

<p>Een <strong>slimme boekingstool</strong> op je website laat klanten in één oogopslag beschikbare tijdsloten zien en direct boeken. Geen formulier, geen wachttijd, geen heen-en-weer gemail.</p>

<p>Een <strong>AI Voice Agent</strong> beantwoordt telefoontjes wanneer jij dat niet kunt: 's avonds, in het weekend, of gewoon wanneer je handen vol zijn. De agent beantwoordt veelgestelde vragen, checkt beschikbaarheid en legt boekingen vast — terwijl jij je focust op je vak.</p>

<p>De echte winst? Je bent bereikbaar op het moment dat de klant klaar is om te kopen. En dat moment komt maar één keer.</p>

<h2>Concrete voorbeelden uit de praktijk</h2>

<p><strong>Scenario 1: De sauna (dit verhaal)</strong><br>
Klant wil €300 uitgeven. Belt twee keer, geen gehoor. Bijna verloren. Één gemiste klant per dag = €300 × 365 = <strong>€109.500 per jaar</strong> aan misgelopen omzet.</p>

<p><strong>Scenario 2: Een kapsalon met telefonische boekingen</strong><br>
Drie kapsters, één telefoon. Tijdens drukke uren wordt er niet opgenomen. Met een online boekingssysteem boeken klanten zelf, ook om 22:00. Resultaat: 30% meer boekingen buiten kantooruren.</p>

<p><strong>Scenario 3: Een B2B-dienstverlener met een contactformulier</strong><br>
Lead vult formulier in op vrijdagavond. Reactie komt maandagochtend. Tegen die tijd heeft de prospect al met twee concurrenten gesproken. Met een AI Voice Agent had die lead direct een afspraak kunnen inplannen.</p>

<h2>Wanneer automatiseren zinvol is (en wanneer niet)</h2>

<p><strong>Het loont wanneer:</strong></p>
<ul>
<li>Je regelmatig oproepen of aanvragen mist buiten kantooruren</li>
<li>Je klanten boekingen of afspraken moeten maken</li>
<li>Je team tijd verliest aan repetitieve vragen (openingstijden, prijzen, beschikbaarheid)</li>
<li>Je marketing wél leads genereert, maar de opvolging hapert</li>
</ul>

<p><strong>Het is nog te vroeg wanneer:</strong></p>
<ul>
<li>Je nog geen duidelijk aanbod of vaste diensten hebt</li>
<li>Je processen intern nog niet op orde zijn (eerst structuur, dan automatisatie)</li>
<li>Je minder dan 5 aanvragen per week ontvangt — dan is handmatige opvolging prima</li>
</ul>

<h2>Onze aanpak</h2>

<p>Bij De WebGoeroe kijken we eerst naar waar je omzet lekt. Niet naar wat technisch cool is, maar naar wat direct geld oplevert.</p>

<p>Dat begint met één vraag: <em>hoeveel klanten verlies je omdat je niet bereikbaar bent op het juiste moment?</em></p>

<p>Van daaruit bouwen we een oplossing op maat. Dat kan een boekingstool zijn, een AI Voice Agent, of een combinatie. Geen overkill, geen maandenlange trajecten. Gewoon een slimme ingreep die zichzelf terugverdient.</p>
HTML,
            ],

            [
                'slug'         => 'team-administratieve-ruis-ai-oplossingen',
                'title'        => 'Verdrinkt jouw team in administratieve ruis? Zo lost AI dat op',
                'excerpt'      => 'Veel ondernemers denken dat ze een extra werkkracht nodig hebben. De realiteit? Ze hebben een slimmere workflow nodig. We zien dagelijks hoe getalenteerde medewerkers 30% tot 50% van hun tijd verliezen aan taken die een AI-agent in seconden kan klaren.',
                'tags'         => ['AI', 'Automatisering'],
                'author_name'  => 'De Webgoeroe',
                'author_bio'   => 'Bij De Webgoeroe helpen we ondernemers groeien online — met slimme websites en AI-tools die echt tijd besparen.',
                'published'    => true,
                'featured'     => false,
                'published_at' => '2026-02-03 09:00:00',
                'meta_title'   => 'Administratieve ruis in je team? Zo lost AI dat op',
                'meta_description' => 'Vier concrete AI-oplossingen die de administratieve rem van je team afhalen — e-mails, documenten, meetings en content. Met meetbare resultaten.',
                'body'         => <<<'HTML'
<p>Veel ondernemers denken dat ze een extra werkkracht nodig hebben. De realiteit? Ze hebben een slimmere workflow nodig.</p>

<p>We zien dagelijks hoe getalenteerde medewerkers 30% tot 50% van hun tijd verliezen aan taken die een AI-agent in seconden kan klaren.</p>

<p>In dit artikel delen we vier concrete situaties waarin AI de administratieve rem er volledig afhaalt — met meetbare resultaten.</p>

<h2>Het probleem: talent verspild aan robottaken</h2>

<p>Denk aan je beste medewerker. Iemand met ervaring, inzicht en klantgevoel. Hoeveel uur per week besteedt die persoon aan e-mails sorteren, gegevens overtypen of vergaderverslagen uitwerken?</p>

<p>In de meeste KMO's is het antwoord: véél te veel. Niet omdat er geen betere manier is, maar omdat "we het altijd zo gedaan hebben."</p>

<p>Het gevolg? Frustratie bij je team, hoge loonkosten voor laagwaardige taken, en structureel te weinig tijd voor werk dat écht waarde creëert.</p>

<h2>Waarom dit probleem blijft bestaan</h2>

<p>De meeste bedrijven herkennen het probleem wel, maar pakken het verkeerd aan. De reflex is: meer mensen aannemen, een extra admin, een stagiair.</p>

<p>Maar dat schaalt niet. Meer personeel betekent meer coördinatie, meer fouten, en hogere vaste kosten — zonder dat de kern van het probleem verdwijnt.</p>

<p>De echte oorzaak? <strong>Repetitieve taken worden nog steeds manueel uitgevoerd</strong>, terwijl AI die in seconden kan afhandelen. Niet omdat de technologie ontbreekt, maar omdat veel ondernemers niet weten hoe toegankelijk de oplossingen inmiddels zijn.</p>

<h2>Vier AI-oplossingen die direct impact maken</h2>

<h3>1. Het e-mail zwarte gat</h3>

<p><strong>De pijn:</strong> Uren besteed aan het sorteren en beantwoorden van info-mails. Elke ochtend opnieuw dezelfde cyclus van lezen, labelen en reageren.</p>

<p><strong>De AI-fix:</strong> Een AI-agent die inkomende mails automatisch prioriteert en direct een concept-antwoord klaarzet — in jouw unieke schrijfstijl. Jij hoeft alleen nog te reviewen en op verzenden te klikken.</p>

<p><strong>Resultaat:</strong> Halvering van je responstijd en minder mentale belasting aan het begin van de dag.</p>

<h3>2. De copy-paste burn-out</h3>

<p><strong>De pijn:</strong> Facturen, pakbonnen of offertes handmatig overtypen in je CRM, boekhoudsoftware of Excel. Foutgevoelig, traag en frustrerend.</p>

<p><strong>De AI-fix:</strong> AI-visie (OCR + intelligente extractie) die documenten "leest" en de juiste data automatisch op de juiste plek zet. Geen overtypen, geen vergissingen.</p>

<p><strong>Resultaat:</strong> Tot 90% minder menselijke fouten en uren per week terug voor waardevol werk.</p>

<h3>3. De meeting overload</h3>

<p><strong>De pijn:</strong> Na elke vergadering een verslag maken, actiepunten formuleren en opvolging regelen. In de praktijk wordt dit uitgesteld of vergeten.</p>

<p><strong>De AI-fix:</strong> Een digitale notulist die het gesprek samenvat in een overzichtelijke "wie-doet-wat" tabel, inclusief deadlines. Automatisch gedeeld met alle deelnemers.</p>

<p><strong>Resultaat:</strong> De administratie doet zichzelf terwijl je praat. Geen verslag meer dat drie dagen te laat komt.</p>

<h3>4. De contenthonger</h3>

<p><strong>De pijn:</strong> Uren zwoegen op een eerste opzet voor een offerte, vacaturetekst of interne memo. Het witte-blad-syndroom slaat toe.</p>

<p><strong>De AI-fix:</strong> Een op maat ingericht "Master Brain" dat jouw tone of voice, huisstijl en doelgroep kent. In 30 seconden levert het een 80% afgewerkt concept dat je alleen nog hoeft te finetunen.</p>

<p><strong>Resultaat:</strong> Van een wit blad naar bruikbaar resultaat in seconden, niet uren.</p>

<h2>Wanneer AI-automatisering zinvol is</h2>

<p>Niet elk bedrijf is er klaar voor. AI-automatisering loont het meest wanneer:</p>
<ul>
<li>Je team structureel tijd verliest aan repetitieve administratieve taken</li>
<li>Dezelfde type vragen, mails of documenten dagelijks terugkomen</li>
<li>Fouten in die taken je geld of geloofwaardigheid kosten</li>
<li>Je groeit, maar extra personeel aannemen geen optie of geen oplossing is</li>
</ul>

<p>Het is nog te vroeg wanneer je interne processen nog niet gestandaardiseerd zijn. Automatisering werkt het best bovenop een helder proces — niet als pleister op chaos.</p>

<h2>Onze visie</h2>

<p>AI vervangt je personeel niet. Maar een medewerker mét AI vervangt op termijn wel de medewerker zonder.</p>

<p>Bij De WebGoeroe helpen we bedrijven om hun team te versterken met slimme AI-workflows. Geen complexe IT-trajecten, maar praktische oplossingen die je binnen dagen kunt inzetten.</p>

<p>We kijken eerst naar waar je tijd lekt. Daarna bouwen we een oplossing die meetbaar verschil maakt — zonder je processen overhoop te gooien.</p>
HTML,
            ],
        ];

        foreach ($posts as $data) {
            $slug = $data['slug'];
            unset($data['slug']);
            Post::firstOrCreate(['slug' => $slug], $data);
        }
    }
}
