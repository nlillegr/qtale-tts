=== Q-Tale TTS ===
Contributors: qtale, activeweb
Tags: text-to-speech, tts, accessibility, audio, podcast, voice, narration, norsk, ai
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.6.25
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Profesjonell norsk talesyntese for WordPress — Q-Tale-spilleren i 13 designs, 25+ språk, server-side caching.

== Description ==

Q-Tale TTS gjør innleggene dine om til talesyntese-lyd med ett klikk. Bruk shortcoden `[qtale]` eller la pluginen auto-generere når du publiserer.

**Funksjoner:**

* 13 spiller-designs (Odin, Tor, Frigg, Baldr, Idunn, Beaivi, og 7 flere)
* Norsk premium AI-tale + 25 språk via Q-Tale (Azure + ElevenLabs)
* Server-side audio-caching i WordPress transients (1-365 dagers TTL)
* Auto-generering ved publisering (valgfritt)
* Kostnadskontroll: maks tegn per post, daglig grense, tier-baserte begrensninger
* Lys / mørk / auto-tema som matcher temaet ditt
* Kun for innlogga brukere (valgfritt)
* Innholdstype-velger (poster, sider, custom post types)
* Embedder qtale-player.js fra qtale.no CDN — alltid nyeste versjon

**Krever et Q-Tale-abonnement** på app.qtale.no for å fungere. Bestill prøveperiode på [qtale.no](https://qtale.no/).

== Installation ==

1. Last ned ZIP-en fra appen din på app.qtale.no — den kommer ferdig konfigurert med din API-nøkkel og tier-baserte standardvalg.
2. WordPress Admin → Plugins → Last opp Plugin → Velg ZIP-fil → Installer → Aktiver.
3. Innstillinger → Q-Tale TTS for å justere designs, atferd og kostnadsgrenser.
4. Skriv `[qtale]Velkommen til siden min[/qtale]` i et innlegg, eller skru på Auto-generer i innstillingene.

== Frequently Asked Questions ==

= Trenger jeg en konto hos Q-Tale? =

Ja. Pluginen genererer lyd via Q-Tales API (app.qtale.no). Du trenger en konto med en aktiv plan.

= Sendes innleggsinnholdet mitt til Q-Tale? =

Ja — kun teksten som skal narrateres. Vi lagrer ikke innleggsinnholdet permanent. MP3-filen som genereres lagres hos Q-Tale så lenge abonnementet ditt er aktivt.

= Hvordan ser jeg hvor mange tegn jeg har brukt? =

Logg inn på app.qtale.no — dashboardet viser månedlig forbruk og resterende kvote.

= Kan jeg bruke pluginen uten å sende data til en ekstern server? =

Nei — Q-Tale genererer lyden i skyen. Lokal generering er ikke tilgjengelig.

= Fungerer pluginen med Gutenberg-blokker? =

Ja — shortcoden `[qtale]` fungerer i alle blokk-typer som rendrer shortcodes (klassisk-blokk, gjenbrukbar shortcode-blokk, m.fl.).

= Kan jeg endre standard stemme? =

Innstillinger → Q-Tale TTS → Standard stemme-ID. Du finner stemme-ID-er på app.qtale.no/voices.

== Screenshots ==

1. Hovedinnstillingssiden — Q-Tale-merket Pro design.
2. Spiller-design-velger med 13 alternativer (tilgjengelige avhenger av pakke).
3. Auto-generer + atferdsinnstillinger.
4. Eksempel på spiller embedded i et innlegg (Odin-design).

== Privacy ==

* Pluginen sender innleggstekst (kun det som rendres via shortcoden eller auto-genereres) til Q-Tales API på app.qtale.no.
* Spilleren (qtale-player.js) lastes fra qtale.no CDN.
* MP3-filer lagres på Q-Tales infrastruktur og leveres via signerte URL-er.
* Du kan når som helst slette genererte filer fra app.qtale.no.
* Se [qtale.no/personvern](https://qtale.no/personvern) for full personvernerklæring.

== External services ==

This plugin connects to the Q-Tale TTS API (api.qtale.no) and CDN (qtale.no) to provide text-to-speech functionality. A Q-Tale subscription is required — sign up at [qtale.no](https://qtale.no/priser).

**api.qtale.no** — Q-Tale TTS REST API

* **What is sent:** Post content (only the text wrapped in the `[qtale]` shortcode or auto-generated), API key (X-API-Key header), voice and design preferences, language, and content hash for deduplication.
* **When it is sent:** When a post containing the shortcode is rendered or saved (with auto-generation enabled), and when admin settings are configured (Cloud Storage credentials test/save).
* **Why:** To generate or look up cached MP3 audio for the post.
* **What is returned:** MP3 audio URL (or job ID for asynchronous generation), tier info, voice list, design list, translation results.
* Operated by: ActiveWEB AS (Norway, org. nr. 982 259 428)
* [Terms of Service](https://qtale.no/vilkar) · [Privacy Policy](https://qtale.no/personvern)

**qtale.no (CDN)** — Player JavaScript

* **What is sent:** Standard HTTP request (browser User-Agent, IP for CDN routing) when the visitor's browser loads the player script.
* **When it is sent:** When a page containing a Q-Tale player is rendered in a visitor's browser.
* **Why:** To deliver the embed-player.js file (Q-Tale's player frontend) and serve MP3 audio.
* Operated by: ActiveWEB AS (Norway, org. nr. 982 259 428)

**Cloud Storage backends** (optional, v2.6.25+) — Customer-owned external storage

* **What is sent:** Older MP3 files (default: files older than 30 days), uploaded directly from Q-Tale's servers to your configured backend (FTP, SFTP, Amazon S3, Wasabi, Cloudflare R2, or Backblaze B2).
* **When it is sent:** Daily cron job, only if you have configured a backend in Innstillinger → Q-Tale TTS → Cloud Storage.
* **Why:** To offload older audio from Q-Tale's infrastructure to your own storage, freeing up your quota.
* This is YOUR storage — you control where audio files end up. Q-Tale just hands them off via the credentials you provide.

== Changelog ==

= 2.6.25 =
* **Nytt — Cloud Storage offload:** flytt eldre MP3-filer (norske stemmer) til din egen FTP, SFTP, Amazon S3 (EU-soner inkl. Frankfurt/Stockholm/Paris), Wasabi, Cloudflare R2 eller Backblaze B2. Konfigurer fra Innstillinger → Q-Tale TTS → Cloud Storage. Velg backend, fyll inn legitimasjon, sett alders-grense (default 30 dager) og URL-base. Test-tilkobling-knapp validerer creds før lagring. Daglig cron flytter automatisk filer fra Q-Tales infrastruktur til din egen lagring; tidligere lyd-URL-er oppdateres transparent. Frigjør Q-Tale-kvoten din og gir full kontroll over arkivet. Legitimasjon krypteres i Q-Tales DB med Fernet-kryptering.
* Endring: cache-buster på embed-player.js bumpet til `2026060704`.

= 2.6.24 =
* **Forbedring — per-enhet Bredde/Høyde uten «Egen design»:** spilleren kan nå ha forskjellig bredde og høyde for Skjerm, Nettbrett og Mobil uten å aktivere full «Egen design»-modus per enhet. Designeren har 3 inline-knapper inni Dimensjoner-boksen (MOBIL | NETTBRETT | SKJERM) som lar deg sette egne dimensjoner per enhet mens resten av designet arves fra Skjerm. Embeden plukker alltid `width`/`height` fra device-config hvis satt, øvrige felter respekterer fortsatt override-flagget.
* Endring: cache-buster på embed-player.js bumpet til `2026060504`.

= 2.6.23 =
* **Nytt — per-enhet design (3 nivåer):** spilleren kan nå ha egne innstillinger for Skjerm, Nettbrett OG Mobil. Embeden velger riktig versjon etter skjermbredde — Mobil ≤480px, Nettbrett 481–1024px, Skjerm >1024px. Hver enhet kan stå på «Auto» (arver Skjerm + responsiv) eller «Egen design» (egen bredde/høyde/layout/farger). Bygges i Player Designer på app.qtale.no.
* **Endring:** mobil maks-bredde respekterer nå en mindre valgt bredde i egen mobil-design (min(valgt, 448px)) i stedet for alltid 448.
* Endring: cache-buster på embed-player.js bumpet til `2026060503`.

= 2.6.22 =
* **Nytt — Auto-Card på mobil:** en Bar-spiller vises nå som en ekte, pent arrangert Card på mobil (≤480px) i Auto-modus — to rene rader med god ikon-plassering (ikke lenger en sammenpakket bar). «Egen design» styrer fortsatt mobil-layouten selv; Skjerm/desktop er helt uberørt.
* **Endring:** maks mobil-bredde 320 → 448px (fyller alle vanlige telefoner pent, sentrert).
* **Nytt:** spilleren re-rendres ved kryssing av mobil-brytepunktet (f.eks. rotasjon) så layouten alltid passer enheten.
* Endring: cache-buster på embed-player.js bumpet til `2026060502`.

= 2.6.21 =
* **Design (spiller):** Mann/Kvinne-toggelen er nå nøyaktig samme høyde (32px) som nedlastings- og Q-Text-knappen, med litt mer luft rundt den aktive sirkelen.
* **Nytt:** mens lyd genereres viser tidtelleren tre pulserende prikker i synth-fargen (i stedet for «Genererer …»-teksten som sprengte pillen) — pille-bredden holder seg stabil.
* Endring: cache-buster på embed-player.js bumpet til `2026060501`.

= 2.6.20 =
* **Fix (auto-generering):** pre-generering av lyd skjer nå inline ved publisering i stedet for via wp-cron — uavhengig av om nettstedets cron fungerer (et serverbytte brøt tidligere wp-cron → nye innlegg ble hengende på «Genererer»). Pre-varmer begge norske + begge engelske stemmer ved publisering, re-varmer ved redigering.

= 2.6.19 =
* **Fix (dark mode-kontrast):** modal-ikoner arver kundens accent-farge; en mørk accent (f.eks. iNyheters blå) ble for svak mot mørk modal-bg. NÅ: auto-kontrast — lyst tema bruker accenten som den er, mørkt tema lysner accenten til WCAG 4.5:1 hvis den er for mørk (orange uendret, mørk blå lysnes). cache-buster embed → 2026060401.
* **Fix (mørkt tema):** bok-ikon + språknavn i modal-top-bar ble mørke på mørkt tema fordi kundens tema-CSS (global `h2{color}`) lekket inn på modal-tittelen. Modalen er ikke shadow-DOM-isolert → tvinger nå farge eksplisitt (lys på mørkt, mørk på lyst).
* **Mobil — stripped down (ren CSS-responsiv, ingen device-sniffing):** under 768px viewport = ÉN linje topp (Q-Text + flagg-velger + lukk; tier-badge + bok/språk-label skjult — aktiv ringet flagg viser språket) + ÉN linje bunn (skrift/PDF/Copy/Del/tema; footer-credit + Print skjult). Maks lese-plass på mobil.
* **Fix:** tier-badge manglet på live fordi `qtext_tier` aldri ble lagt i embed-config + plugin-setting var u-synket. NÅ: self-heal — `current_qtext_tier()` synker fra /me (6t-cache) hvis tom, persisterer, og emittes i data-config → badge vises uten manuell «Test nøkkel»/«Refresh».
* **Mobil-gester (kun <768px):** (1) dra modalen NED for å lukke (native sheet-dismiss, kun når innhold er på toppen), (2) «til toppen»-FAB (accent-farget) som dukker opp når artikkelen er scrollet > 500px. No-ops på desktop.
* Endring: accent-kantlinje rundt modal 1.5px → **2px**. LocalStorage-cache `qte-tx4` → `qte-tx5` (forkast stale meta uten tier).
* Endring: cache-buster på embed-player.js bumpet til `2026060303`.

= 2.6.18 =
* **Q-Text modal-redesign:** ny top-bar — Q-Text wordmark (kanonisk orange Q-logo + "Text") + redesignet tier-badge (Access=star/grønn, Pro=zap/blå, Enterprise=crown/violet) + bok-ikon + valgt språk, alt venstrejustert. Flagg-velger + lukk til høyre.
* **Nytt:** modal-tooltips følger NÅ valgt oversettelses-språk — åpner på engelsk → engelske tooltips (Smaller text, Print, Copy text, Share, Close …); bytt flagg til norsk → norske (Mindre skrift, Skriv ut …). 12 språk håndoversatt, fallback engelsk.
* **Nytt:** tynn accent-ring rundt modalen (desktop) + accent top-strip (mobil) — speiler spillerens design-accent. Premium-frame.
* Endring: footer-credit ett hakk større (10.5px) + dynamisk år (`© {år}` oppdateres automatisk hvert år).
* Endring: cache-buster på embed-player.js bumpet til `2026060301`.

= 2.6.17 =
* **Nytt:** Q-Text tier-badge i modal-header + PDF-header. Viser kundens tier ("Q-Text · Enterprise" for iNyheter/Helge) med Q-Tale orange Q-logo + tier-farget tekst. Hvit/mørk bg afhengig av tema. Skikkelig branding.
* **Nytt:** Q-Text marketing-side `qtale.no/q-text` får tier-badge over hver tier-card (ACCESS grønn, PRO blå, ENTERPRISE violet) m/ ikoner (star/zap/trophy).
* **Nytt:** `/api/v1/me` returnerer NÅ `addons.qtext_tier` (access/pro/enterprise/null). Konfig i `system_settings.qtext_tier_assignments` JSON map customer_id → tier. Default 'enterprise' for piloter.
* Endring: plugin lagrer `qtext_tier` i settings (sync ved Test nøkkel + Refresh fra /me).
* Endring: PDF-payload sender `qtext_tier`, marketing-template rendrer Q-Text-badge i header.
* Endring: cache-buster på embed-player.js bumpet til `2026060211`.

= 2.6.16 =
* **Brand-rebrand:** "Translation Modal"-feature heter NÅ **Q-Text** i all kunde-vendt UI. Plugin admin "Aktive addons"-kort viser «Q-Text» istedet for «Translation Modal». Q-family-naming: Q-Tale (Voice) + Q-Text (oversettelse + lesemodal + PDF) = komplett lese-opplevelse. Interne DB-keys + variable navn beholdt (`translation_modal_addon` etc.) — ingen migrasjon. Speilet i marketing-sider, portal, reseller, admin, api-docs.

= 2.6.15 =
* **Hotfix:** PDF feilet ("PDF Feilet prøv igjen") for artikler hvor body-translation allerede var WP-transient-cached. `$client` var initialisert inni cache-miss-blokken i `rest_translation_pdf`, så tittel-translate kallet på linje 578 traff udefinert variabel når body var cached. FIX: `$client` initialiseres NÅ utenfor if-blokken (alltid tilgjengelig for tittel-translate).
* **Hotfix:** Modal viste IKKE oversatt tittel etter v2.6.14-oppdateringen fordi localStorage-cache fra v2.6.13 manglet `title_translated`-feltet. Cache-prefix bumpet `qte-tx3` → `qte-tx4` så fersk meta hentes neste gang modal åpnes.
* Endring: cache-buster på embed-player.js bumpet til `2026060210`.

= 2.6.14 =
* **Bug-fix:** Artikkel-tittelen var IKKE oversatt i credit-header på modal eller PDF — bare body-teksten ble oversatt. Nå oversettes tittelen separat via plain-text translate (gratis Opus-MT for no→en, Azure for andre par). Lagres som WP-transient 7 dager pr (post, lang). Modal og PDF bruker oversatt tittel når target ≠ kilde-språk.
* Endring: REST-respons har nytt felt `meta.title_translated` ved siden av `meta.title`. JS bruker `title_translated || title` for visning.
* Endring: cache-buster på embed-player.js bumpet til `2026060209`.

= 2.6.13 =
* Endring: Translation Modal credit-merke renderes NÅ "© 2026 · AI SERVICE BY QTALE.NO" i ALL CAPS m/letter-spacing 0.1em, og hover-fargen er ALLTID Q-Tale orange (#e85124) — uavhengig av kundens accent-farge. Q-Tale-identiteten skal ikke overskrives av kundens design på vår egen signatur.
* Endring: cache-buster på embed-player.js bumpet til `2026060208`.

= 2.6.12 =
* Endring: Translation Modal-trigger-tooltip på spilleren utvidet fra "Les oversatt artikkel" til "**Les oversatt artikkel + Q-Tools**" — selger modalens verktøy-bredde (skrift, print, PDF, kopier, del, tema) på hover, ikke bare lese-aspektet.
* Endring: cache-buster på embed-player.js bumpet til `2026060207`.

= 2.6.11 =
* **Polish:** Translation Modal speiler NÅ spillerens accent-farge i alle interaktive elementer — tool-ikoner default state (samme system som player), hover-states, progress-bar, credit-lenker. Tidligere var modal hardkodet til Q-Tale orange (#e85124) uavhengig av kundens design. Aksent injectes som CSS-variabel `--qte-accent` fra `cfg.accent_dark` ved modal-åpning. Fallback til Q-Tale orange hvis cfg mangler verdi.
* Endring: cache-buster på embed-player.js bumpet til `2026060206`.

= 2.6.10 =
* **Hotfix:** Modal credit-header (tittel + permalink) og credit-footer (forfatter + dato) viste seg ikke på live etter v2.6.9-opplasting fordi localStorage-cache fra v2.6.7/v2.6.8 returnerte gammel body uten credit-wrap, FØR `_buildArticleHTML`-funksjonen kunne kjøre. Fix: cache-prefix bumpet `qte-tx2` → `qte-tx3` (forkaster gammel cache automatisk), og body + meta lagres separat så credit-wrap bygges på nytt ved hver render.
* Endring: cache-buster på embed-player.js bumpet til `2026060205`.

= 2.6.9 =
* **Nytt:** Translation Modal får NÅ credit-header (artikkel-tittel + permalink + separator-linje) FØR oversatt artikkel-tekst og credit-footer (separator-linje + forfatter-navn + rolle + publiseringstidspunkt) ETTER teksten. Speiler PDF-strukturen for konsistent rendering. Juridisk nødvendig kilde-attribusjon for redaksjonelt innhold.
* **Nytt:** PDF får tilsvarende author-footer (forfatter + rolle + dato) i samme stil. Modal og PDF deler nå credit-meta-strukturen (WP user-meta `display_name` + `description` + post `post_date`).
* Endring: REST-respons `/qtale-tts/v1/translation/<post>/<lang>` har nytt `meta`-objekt med {title, permalink, author_name, author_title, published_human}.
* Endring: cache-buster på embed-player.js bumpet til `2026060204`.

= 2.6.8 =
* **Polish:** Translation Modal redesign — subtil én-nyanse-step på top + footer (#f6f7f9 / #171724) gir klar visuell separasjon mot artikkel-body. Desktop border-radius 16 → 22px, sterkere box-shadow (0 30px 90px) + hairline highlight-ring. Backdrop-blur 4 → 8px + saturate 120% (premium look). Mobile beholder fullscreen uten radius.
* **Polish:** Body-padding bumpet (28/26 mobile → 32/44 desktop) for mer luft rundt artikkel-tekst.
* **Nytt:** Diskret credit-merke i footer venstre — "© 2026 · AI Service by Qtale.no" i JetBrains Mono 10px, 50% opacity, klikkbar til qtale.no. Hover orange + 95% opacity.
* Endring: cache-buster på embed-player.js bumpet til `2026060203`.

= 2.6.7 =
* **Nytt:** Translation Modal første-visning bruker NÅ HTML-strukturert oversettelse (samme som PDF) — overskrifter, avsnitt, lister, blockquote, fet/kursiv, lenker bevart visuelt i modal. Modal og PDF deler nå translation-cache + budget-cap. Format-felt i REST-respons (`format: "html"`) gjør at JS forstår å rendre direkte uten å wrappe i `<p>`.
* Endring: cache-buster på embed-player.js bumpet til `2026060202`.

= 2.6.6 =
* **Nytt:** Translation Modal PDF-eksport beholder NÅ artikkel-struktur — overskrifter (h1/h2/h3), avsnitt, lister, blockquote, fet/kursiv, lenker bevart i PDF. Plugin sender oversettelses-flyten gjennom nytt `/api/v1/translate-html`-endepunkt som velger Azure (textType=html) under månedlig budget-cap, og Opus-MT DOM-walk over cap (gratis fallback). Cap er konfigurerbar i `system_settings.translate_html_monthly_nok_cap` (default 150 NOK/mnd).
* **Endring:** PDF åpnes i NY FANE i browser-PDF-viewer i stedet for direct-download. Leseren ser PDF før de laster ned, får zoom/print/save i innebygd viewer. Standard-mønster på tvers av nyhetsnettsteder.

= 2.6.5 =
* **Bug-fix:** Translation Modal "Henter oversettelse …"-spinner hang for alltid. CSS-spesifisitet: `.qte-tx-loading{display:flex}` overstyrte HTML5 `[hidden]{display:none}`-default, så `_txLoading.hidden=true` lukket aldri elementet selv om fetch-en hadde løst og innholdet rendret seg under. Fix: eksplisitt `.qte-tx-loading[hidden]{display:none}` regel.
* **Server-fix:** PDF-eksport av oversatt artikkel feilet med 404 (`POST /api/pdf-html`). LiteSpeed `qtale.no`-vhost proxyet hele `/api/`-pathen til api-service (port 5051) som ikke har endepunktet — det lever på marketing-service (port 5058). La til mer spesifikk context `/api/pdf-html` foran `/api/` som ruter til marketing-backend.
* Endring: cache-buster på embed-player.js bumpet til `2026060201` for å buste WP Rocket combined-bundle hos kunder.

= 2.6.4 =
* **🔥 KRITISK BUG-FIX:** addon-flagg ble aldri persistert til DB. `sanitize_settings`-callbacken (som WP auto-kjører på HVER `update_option`) leste kun `$existing`-verdien for addon-flaggene — så når Test nøkkel/Refresh skrev `translation_modal_addon=1`, ble den umiddelbart overskrevet tilbake til `0` av sanitize-funksjonen. Bug har eksistert siden v2.4.3 (introduksjon av addon-flagg). FIX: sanitize sjekker nå `$input` FØR `$existing` — speiler `$keep()`-mønsteret brukt for andre felter. Resultat: Translation Modal-pillen i admin går nå til Aktiv etter Test nøkkel, og «📖 Les»-knappen rendres på spilleren.

= 2.6.3 =
* **Nytt:** Synlig "Aktive addons"-kort i Innstillinger → Q-Tale TTS. Pulserende grønn pille = aktiv, grå pille = inaktiv. Speiler `/api/v1/me.addons` så admin kan verifisere at addon-aktiveringer (Stripe-kjøp eller pilot-allowlist) faktisk har slått gjennom på plugin-siden uten å lese kildekoden.
* **Bug-fix:** Object-cache-invalidering (Redis/Memcached) rundt `update_option` i både Test nøkkel + Refresh. På sites med persistent object-cache returnerte read-pathen stale `translation_modal_addon=0` selv etter en vellykket Test-nøkkel-skriving. Cache klares nå pre+post update.

= 2.6.2 =
* **Bug-fix:** «↻ Refresh»-knappen (Innstillinger → Q-Tale TTS) syncer NÅ også addon-flagg + tier fra `/api/v1/me`, ikke bare designs. Tidligere måtte admin klikke separat «Test nøkkel» for å aktivere et nyaktivert addon (Translation Modal, Verktøy-pakke, Dual Player) — UX-felle. Nå: én klikk på Refresh = alt fra serveren speilet til WP.

= 2.6.1 =
* **Bug-fix:** design-cache i `class-qtale-tts-post-meta.php` brukte `HOUR_IN_SECONDS` (1 time) mens `class-qtale-tts-shortcode.php` bruker 30 sekunder. Hvis post-meta-koden cachet designet først, ble nye design-endringer fra Player Design Studio låst i 1 time. Symptom: Translation Modal eller andre toggles slått PÅ i Studio dukker ikke opp på player-rendering selv etter WP Rocket-cache-klarering. Nå 30s overalt.
* Endring: layout-bar padding 10/14 → 10/20 (mer luft til download-knapp ved 640px max-width). Gap mellom elementer 14 → 10px.
* Endring: timer-pille fikk eksplisitt `box-sizing:border-box` + `line-height:1` for å matche høyde med speed/vol-piller pixel-perfekt.

= 2.6.0 =
* Nytt: **Mobile-responsive BAR-layout** — spilleren wrapper automatisk til 2 rader når viewport ≤480px (mobil/smal nettbrett). Eksempel iNyheter-flagg, Les og Download som tidligere ble klippet av på mobil er nå alltid synlig. Layout: Rad 1 = play + spectrum + timer + flagg; Rad 2 = voice + speed + volum + Les + download. Auto-høyde 100-200px (var fast 54px).
* Nytt: **Manuell mobil-override** (designer-side) — kunden kan velge mellom «Auto» (arver alt fra Skjerm-versjonen) eller «Egen design» (separat mobil-config med egne farger, layout og funksjoner). Lagres i samme JSON-config-blob (`mobile_override:true` + `mobile_config:{...}` overrides). Embed-player.js merger mobile_config over desktop ved init når `window.matchMedia('(max-width:480px)')` treffer.
* Nytt: **Premium-addon-marketplace** — Verktøy-pakke (79 kr/mnd) og Translation Modal (149 kr/mnd) tilgjengelig via app.qtale.no/addons + qtale.no/addons. Tier-include-logic: Verktøy-pakke gratis fra Tor og oppover. Plugin honorerer addon-status fra `/api/v1/me.addons` ved «Test nøkkel».
* Endring: A+ (Skriftstørrelse) og Del er nå ALLTID gratis inkludert med utility-bar. Print + PDF krever Verktøy-pakke addon (eller Tor+-tier). Designer-card splittet visuelt i «Alltid gratis» og «Krever Verktøy-pakke».
* Forbedring: Translation Modal trigger og modal-toolbar bruker nå SVG-ikoner gjennomgående (book, copy, share, theme, print, PDF) — ingen emoji i UI per system-policy. Bevart PNG-flagg i language-picker for korrekt regional gjengivelse.
* Forbedring: hover-mønster på alle player-knapper unifisert — 8% accent-bg + 65% accent-border + drop-shadow glow. Voice-toggle unntak: 2% bg-hover for å ikke konkurrere med aktiv-state.

= 2.5.0 =
* Nytt: **Translation Modal (Premium Addon)** — responsive modal som viser AI-oversatt artikkel-tekst når leseren klikker «📖 Les»-knappen på spilleren. Mobile fullscreen + desktop overlay (80vw × 88vh).
* Nytt: ny REST-endpoint `wp-json/qtale-tts/v1/translation/<post_id>/<lang>` — proxy til qtale.no `/api/v1/translate` med Opus-MT cache (cache-hit = gratis + instant). Plugin-side transient cache (1 dag, invalidert ved post-redigering via content-hash).
* Nytt: «📖 Les»-trigger-knapp på spilleren — vises kun når `translate_on` er aktivert og det finnes flere språk i `translate_langs`. Bytter automatisk språk når leseren klikker flagg i modal-header.
* Nytt: modal har Skrift A−/A+ (persistert i localStorage), Print (egen `@media print`-stylesheet), Copy (clipboard), Del (navigator.share), Tema-toggle (auto / lys / mørk), full A11y (role=dialog, focus-trap, ESC, backdrop-close på desktop).
* Nytt: tema følger `prefers-color-scheme` automatisk, kan overstyres manuelt — bruker DM Sans + Q-Suite Tabler dark (#0f0f1a) + Q-Suite orange (#e85124) accent.
* Nytt: addon-gating — Translation Modal aktiveres kun for kunder med aktiv addon (`addons.translation_modal` fra `/api/v1/me`, satt via `system_settings.translation_modal_addon_customer_ids`-allowlist). Hvis av: `tx_modal_on` tvinges false + `data-tx-url` ikke emittes.
* Nytt: Player Design Studio (app.qtale.no/player-designer) har eget addon-kort «Translation Modal» med blå sky→indigo aurora-styling og preview-trigger som viser «📖 Les»-pillen i live-spilleren når slått på.
* Nytt: **PDF-eksport av oversatt tekst** via 📄-knappen i modalen — ny REST-route `qtale-tts/v1/translation-pdf/<post_id>/<lang>` POST-er translated body til qtale.no `/api/pdf-html` (chromium HTML→PDF + fitz-brand). Plugin streamer PDF tilbake til klient som vedlegg.
* Nytt: animert progress-bar (orange→lys) + status-toast (Ja/Feil) på toppen av modalen mens PDF bygges — bevarer A11y (role=progressbar + aria-live status).

= 2.4.3 =
* Nytt: **Dual Player (Premium Addon)** — vis 2 playere per artikkel: én TTS-player (med play-knapp) + én utility-only player (kun Print/PDF/Skrift/Del). Aktiveres i Innstillinger → Q-Tale TTS → Dual Player.
* Nytt: plassering vertikal (stablet) eller horisontal (side om side), valgbart mellomrom (0–64 px).
* Nytt: validering — nøyaktig én av de to designs må ha play-knapp (den andre må være utility-only, satt via «Play-knapp form: Ingen» i Player Design Studio). Ugyldig kombinasjon faller automatisk tilbake til single-player + HTML-kommentar med årsak i kilden.
* Nytt: design-cache utvidet med `play_shape` så plugin kan vise 🎵 TTS / 🛠️ Utility-badge per design i picker.
* Nytt: addon-gating — Dual Player-seksjonen vises kun for kunder med aktiv addon (settes via API basert på system_settings-allowlist). Stripe-pakke kommer i senere release.

= 2.4.2 =
* Nytt: Utility-bar (leser-verktøy) på spilleren — Print, PDF, Skriftstørrelse (A−/A+) og Del. Slå på i Player Design Studio (egen boks). Full bar på CARD; minimal på BAR.
* Nytt: PDF-knappen lager en merkevaret PDF av artikkelen («PDF Konvertering av qtale.no») med klikkbar «Lytt til artikkelen»-lenke til lydversjonen.
* Nytt: spilleren sender nå artikkel-permalink + brand (per nettsted-språk) + innholds-selector til utility-funksjonene (`data-permalink`/`data-pdf-brand`/`data-content-sel`). Brand kan overstyres med filteret `qtale_pdf_brand`.
* Nytt: Mørk/Lys versjon-toggle i designeren (kun Design Studio-preview).
* Nytt: print-stylesheet — Print-knappen (og nettleserens Ctrl+P) gir nå ren utskrift: skjuler meny/sidefelt/annonser/kommentarer/spiller, full bredde + lesbar typografi. Samme styling brukes i PDF-en.

= 2.4.1 =
* Endring: hastighets-toggle har nå stegene 0.75× / 0.85× / 1× / 1.10× / 1.25× (maks 1.25×).
* Endring: hastighets-slider har nå spekter 0.75×–1.25× (var 0.5×–2×).
* Endring: måler-nålen fordeles per hakk på toggle (1× rett opp, ±1 hakk ±45°, ±2 hakk vannrett); slider symmetrisk 0.75×→vannrett venstre, 1.25×→vannrett høyre.
* Endring: hastighets-kontrollen har fast bredde — etiketten hopper ikke lenger sidelengs ved bytte mellom f.eks. 1× og 1.25×.
* Endring: ×-symbolet i hastighets-etiketten forstørret ett hakk for bedre lesbarhet.
* Endring: Mute er nå en uavhengig avkrysning — kan kombineres med både Slider og Toggle (eller stå alene), i stedet for å være et eget-stående volum-valg.
* Endring: fast standardtekst på spilleren fjernet — tekst kommer nå kun fra Egendefinert tittel.

= 1.0.1 =
* Fix: player auto-init failed because of missing `data-qtale-player` attribute on rendered embed div.
* Fix: audio URL was passed as `data-audio` but qtale-player.js expects `data-audio-url`.
* New: tier-gated design picker — when you click "Test nøkkel" the plugin fetches your subscription tier from /api/v1/me and only shows player designs your plan unlocks (Nanna 2 → Odin 13).
* New: padding controls (top/right/bottom/left) for auto-injected player placement.
* New: dark mode admin UI (auto-switches when OS prefers-color-scheme is dark).
* Internal: bumped to use qtale-player.js v=35+ feature set (all 13 designs).

= 1.0.0 =
* Første utgivelse.
* 13 spiller-designs, 25+ språk, server-side caching, auto-generering, tier-baserte design-begrensninger.
* Pro-designet admin-side med Q-Tale-branding.

== Upgrade Notice ==

= 1.0.0 =
Første utgivelse.


== Changelog ==
