<?php
/**
 * [qtale] shortcode handler + async polling endpoint.
 *
 * Server-side flow:
 *   1. Hash text + voice + speed → cache key.
 *   2. If cache hit → render player div pointing at cached audio_url.
 *   3. If miss → POST /api/v1/generate, poll up to 6 s.
 *      - Hit within budget: cache + render.
 *      - Timeout: render placeholder with data-job-id; player JS polls via /qtale-tts/v1/status.
 *
 * @package Qtale_TTS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qtale_TTS_Shortcode {

	const TAG       = 'qtale';
	const TAG_EMBED = 'qtale-player';

	/**
	 * Default voice per lang (used when voice_gender is fixed).
	 * Engine policy (per customer directive 2026-05-26):
	 *   • Kokoro is the DEFAULT for every language Kokoro has a voice for
	 *     (en, es, fr, it, pt-br).
	 *   • Norwegian / Swedish / Danish → ALWAYS Azure premium neural.
	 *   • Languages with no Kokoro voice (fi, de, nl, pl, is) → Azure.
	 *   • fo → Meta-MMS, se → Giellalt (specialist engines).
	 * Voice IDs verified active against the `voices` table.
	 */
	const VOICE_PER_LANG = array(
		// Nordic-Scandinavian → always Azure
		'no' => 'nb-NO-FinnNeural',     'sv' => 'sv-SE-MattiasNeural',
		'da' => 'da-DK-JeppeNeural',
		// Kokoro default where a Kokoro voice exists
		'en' => 'en_GB-george-kokoro',  'es' => 'es-alex-kokoro',
		'fr' => 'fr-siwis-kokoro',      'it' => 'it-nicola-kokoro',
		'pt' => 'pt-alex-kokoro',
		// No Kokoro voice → Azure
		'fi' => 'fi-FI-HarriNeural',    'de' => 'de-DE-ConradNeural',
		'nl' => 'nl-NL-MaartenNeural',  'pl' => 'pl-PL-MarekNeural',
		'is' => 'is-IS-GunnarNeural',
		// Specialist engines
		'fo' => 'fo-mms-male',          'se' => 'se-mahtte-divvun',
	);

	/**
	 * Voice map per (lang, gender). Used when design.voice_gender === 'picker'.
	 * Visitor clicks ♂/♀ → embed-player swaps audio src to matching gender URL.
	 */
	const VOICE_PER_LANG_GENDER = array(
		// Nordic-Scandinavian → always Azure
		'no' => array( 'male' => 'nb-NO-FinnNeural',    'female' => 'nb-NO-PernilleNeural' ),
		'sv' => array( 'male' => 'sv-SE-MattiasNeural', 'female' => 'sv-SE-SofieNeural' ),
		'da' => array( 'male' => 'da-DK-JeppeNeural',   'female' => 'da-DK-ChristelNeural' ),
		// Kokoro where both genders exist
		'en' => array( 'male' => 'en_GB-george-kokoro', 'female' => 'en_GB-alice-kokoro' ),
		'es' => array( 'male' => 'es-alex-kokoro',      'female' => 'es-dora-kokoro' ),
		'it' => array( 'male' => 'it-nicola-kokoro',    'female' => 'it-sara-kokoro' ),
		'pt' => array( 'male' => 'pt-alex-kokoro',      'female' => 'pt-dora-kokoro' ),
		// French: Kokoro has only a female voice → Azure male fallback
		'fr' => array( 'male' => 'fr-FR-HenriNeural',   'female' => 'fr-siwis-kokoro' ),
		// No Kokoro voice → Azure both genders
		'fi' => array( 'male' => 'fi-FI-HarriNeural',   'female' => 'fi-FI-SelmaNeural' ),
		'de' => array( 'male' => 'de-DE-ConradNeural',  'female' => 'de-DE-KatjaNeural' ),
		'nl' => array( 'male' => 'nl-NL-MaartenNeural', 'female' => 'nl-NL-FennaNeural' ),
		'pl' => array( 'male' => 'pl-PL-MarekNeural',   'female' => 'pl-PL-AgnieszkaNeural' ),
	);

	/** Pick voice for (lang, gender or 'default'). */
	public static function pick_voice( $lang, $gender = '' ) {
		if ( $gender && isset( self::VOICE_PER_LANG_GENDER[ $lang ][ $gender ] ) ) {
			return self::VOICE_PER_LANG_GENDER[ $lang ][ $gender ];
		}
		return isset( self::VOICE_PER_LANG[ $lang ] ) ? self::VOICE_PER_LANG[ $lang ] : '';
	}

	/**
	 * Generate (or locate) audio for ONE (target_lang, gender) and store it under the
	 * canonical embed cache key — the SAME key render_embed + rest_embed_poll read.
	 *
	 * This is the single source of truth for "warm one voice into the cache the player
	 * reads", shared by the publish-hook pre-gen and the visit-time cron. It exists to
	 * kill the v2.1.0 bug where publish pre-gen wrote a legacy key (qtale_tts_*) the
	 * embed player never read, so every visit fell into poll/"Genererer" mode.
	 *
	 * @param bool $blocking true = wait for audio (publish-time warming of source lang);
	 *                       false = submit job + store pending (browser poll upgrades it).
	 * @param int  $timeout  seconds to wait when blocking.
	 * @return string audio_url if resolved, '' otherwise.
	 */
	public static function generate_voice( $design_id, $post_id, $text, $source_lang, $target_lang, $gender, $blocking = false, $timeout = 30 ) {
		$s = Qtale_TTS::settings();
		if ( empty( $s['api_key'] ) || $text === '' ) {
			return '';
		}
		$cache_key = Qtale_TTS::embed_cache_key( $design_id, (int) $post_id, $target_lang . '-' . $gender, $text );
		$existing  = get_transient( $cache_key );
		if ( is_array( $existing ) && ! empty( $existing['audio_url'] ) ) {
			return $existing['audio_url']; // already warm — never regenerate
		}
		$client = new Qtale_TTS_Client( $s['api_base'], $s['api_key'] );
		$ttl    = max( 1, (int) $s['cache_ttl_days'] ) * DAY_IN_SECONDS;

		// A job for this exact (post, design, lang, gender, text) is already in flight —
		// poll its status instead of submitting a duplicate. THIS is what stops the
		// per-visit re-submission that was overloading the TTS backend: a pending entry
		// is checked, never re-queued, until it goes stale (>15 min) or completes.
		if ( is_array( $existing ) && ! empty( $existing['job_id'] ) ) {
			$st = $client->status( $existing['job_id'] );
			if ( ! is_wp_error( $st ) && ! empty( $st['status'] ) && $st['status'] === 'done' && ! empty( $st['audio_url'] ) ) {
				set_transient( $cache_key, array( 'audio_url' => $st['audio_url'] ), $ttl );
				return $st['audio_url'];
			}
			if ( isset( $existing['submitted_at'] ) && ( time() - $existing['submitted_at'] ) < 900 ) {
				return ''; // still processing & recent — do NOT resubmit
			}
			// stale (>15 min, still not done) → fall through and resubmit once
		}

		$voice = self::pick_voice( $target_lang, $gender );
		if ( ! $voice ) {
			return '';
		}

		// Translate source→target if needed (sync, fast).
		if ( $source_lang === $target_lang ) {
			$translated = $text;
		} else {
			$tr = $client->translate_text( $text, $source_lang, $target_lang );
			if ( is_wp_error( $tr ) || empty( $tr['translated'] ) ) {
				return '';
			}
			$translated = $tr['translated'];
		}

		// Re-use server-side audio if the same text+voice was already rendered.
		$found = $client->find_audio( $translated, $voice );
		if ( ! is_wp_error( $found ) && ! empty( $found['found'] ) && ! empty( $found['audio_url'] ) ) {
			set_transient( $cache_key, array( 'audio_url' => $found['audio_url'] ), $ttl );
			return $found['audio_url'];
		}

		if ( $blocking ) {
			$res = $client->generate_and_wait( $translated, $voice, array( 'speed' => 1.0 ), $timeout );
			if ( ! is_wp_error( $res ) && ! empty( $res['audio_url'] ) ) {
				set_transient(
					$cache_key,
					array( 'audio_url' => $res['audio_url'], 'duration' => isset( $res['duration'] ) ? (float) $res['duration'] : 0.0 ),
					$ttl
				);
				return $res['audio_url'];
			}
			// timed out → fall through to non-blocking submit so the browser poll finishes it
		}

		// Non-blocking: submit job + store pending; rest_embed_poll upgrades to audio_url when done.
		$job_id = $client->generate_submit( $translated, $voice, array( 'speed' => 1.0 ) );
		if ( is_wp_error( $job_id ) || ! $job_id ) {
			return '';
		}
		set_transient(
			$cache_key,
			array( 'job_id' => $job_id, 'voice' => $voice, 'submitted_at' => time(), 'pending' => true ),
			$ttl
		);
		return '';
	}

	public static function register() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		add_shortcode( self::TAG_EMBED, array( __CLASS__, 'render_embed' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function maybe_enqueue() {
		// Defer enqueue to actual render time — the shortcode handler enqueues just before output.
	}

	public static function register_routes() {
		register_rest_route(
			'qtale-tts/v1',
			'/status/(?P<job_id>[A-Za-z0-9._\\-]+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'rest_status' ),
				'args'                => array(
					'job_id' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		// Embed-player polling endpoint — visitor browser polls this to discover
		// audio URLs as background-cron generates them per language.
		register_rest_route(
			'qtale-tts/v1',
			'/embed-poll/(?P<design_id>[A-Za-z0-9._\\-]+)/(?P<post_id>\d+)',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'rest_embed_poll' ),
			)
		);
		// v2.5 — Translation modal text endpoint. Returnerer oversatt artikkel-tekst.
		// Proxy til qtale.no /api/v1/translate (Opus-MT cache = gratis ved cache-hit).
		register_rest_route(
			'qtale-tts/v1',
			'/translation/(?P<post_id>\d+)/(?P<lang>[a-z]{2,5})',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'rest_translation' ),
			)
		);
		// v2.5 Fase 4 — Translation-PDF endpoint. Bygger merkevaret PDF av oversatt artikkel.
		register_rest_route(
			'qtale-tts/v1',
			'/translation-pdf/(?P<post_id>\d+)/(?P<lang>[a-z]{2,5})',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'rest_translation_pdf' ),
			)
		);
	}

	/**
	 * Returns {audio_urls: {no: url, sv: url, ...}} for the given design+post.
	 * Reads transients populated by cron handler; empty langs are omitted.
	 */
	public static function rest_embed_poll( $request ) {
		$design_id = sanitize_text_field( $request['design_id'] );
		$post_id   = (int) $request['post_id'];
		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return new WP_REST_Response( array( 'audio_urls' => array() ), 200 );
		}
		// SHARED text extraction (canonical, matches render_embed + cron)
		$text = Qtale_TTS::extract_post_text( $post );
		$s = Qtale_TTS::settings();

		// Fetch design to know which langs + voice-config to look up.
		// v2.3.9 FIX: MUST use the SAME transient key render_embed writes ('qtale_design_').
		// The prior 'qtale_tts_design_' never matched → $langs fell back to ['no'], so the
		// poll NEVER returned translations → every English click was stuck on "Genererer".
		$design_cache_key = 'qtale_design_' . md5( $design_id );
		$design = get_transient( $design_cache_key );
		if ( ! $design && ! empty( $s['api_key'] ) ) {
			// render's transient TTL is only 30s — re-fetch from API so the poll still knows
			// the design's languages after that cache has expired (else $langs → ['no'] again).
			$GLOBALS['qtale_app_base'] = isset( $s['app_base'] ) ? $s['app_base'] : 'https://app.qtale.no';
			$dclient = new Qtale_TTS_Client( $s['api_base'], $s['api_key'] );
			$dresp   = $dclient->player_design_by_id( $design_id );
			if ( ! is_wp_error( $dresp ) && ! empty( $dresp['design'] ) ) {
				$design = $dresp['design'];
				set_transient( $design_cache_key, $design, 30 );
			}
		}
		$langs = ( $design && ! empty( $design['config']['translate_langs'] ) )
			? $design['config']['translate_langs']
			: array( 'no' );
		// Source-lang from request param ('source' from player, fallback 'source_lang') or default
		$source_lang = sanitize_text_field( $request->get_param( 'source' ) ?: ( $request->get_param( 'source_lang' ) ?: '' ) );
		if ( ! $source_lang ) $source_lang = Qtale_TTS::resolve_source_language();
		if ( ! in_array( $source_lang, $langs, true ) ) array_unshift( $langs, $source_lang );

		// Harvester: for each pending lang, check QTale job-status & upgrade transient
		$client = ! empty( $s['api_key'] ) ? new Qtale_TTS_Client( $s['api_base'], $s['api_key'] ) : null;
		$ttl = max( 1, (int) $s['cache_ttl_days'] ) * DAY_IN_SECONDS;

		// LAZY on-demand generation: generate the exact lang+voice the visitor is asking
		// for right now (idempotent — generate_voice returns early if cached or a recent
		// job is in flight). Only the primary combo is generated eagerly at render/publish;
		// every other language/voice arrives here on demand → ~4x less newsroom load.
		$req_lang   = sanitize_text_field( $request->get_param( 'lang' ) ?: '' );
		$req_gender = sanitize_text_field( $request->get_param( 'gender' ) ?: '' );
		if ( $client && $req_lang && in_array( $req_gender, array( 'male', 'female' ), true ) ) {
			self::generate_voice( $design_id, $post_id, $text, $source_lang, $req_lang, $req_gender, false );
		}

		$urls = array();
		// Detect picker mode from cached design config
		$picker = ( $design && ! empty( $design['config']['voice_gender'] ) && $design['config']['voice_gender'] === 'picker' );
		$genders = $picker ? array( 'male', 'female' ) : array( $design['config']['voice_gender'] ?? 'female' );

		foreach ( $langs as $lang ) {
			foreach ( $genders as $gender ) {
				// Cache key uses lang-gender slug to keep male/female separate
				$key_suffix = $lang . '-' . $gender;
				$key = self::embed_cache_key( $post_id, $design_id, $key_suffix, $text );
				$c = get_transient( $key );
				$url_found = '';
				if ( is_array( $c ) && ! empty( $c['audio_url'] ) ) {
					$url_found = $c['audio_url'];
				}
				if ( ! $url_found && $client ) {
					$voice = self::pick_voice( $lang, $gender );
					if ( $voice ) {
						// Translate if non-source lang
						$lookup_text = $text;
						if ( $lang !== $source_lang ) {
							$tr = $client->translate_text( $text, $source_lang, $lang );
							if ( ! is_wp_error( $tr ) && ! empty( $tr['translated'] ) ) {
								$lookup_text = $tr['translated'];
							}
						}
						$found = $client->find_audio( $lookup_text, $voice );
						if ( ! is_wp_error( $found ) && ! empty( $found['found'] ) && ! empty( $found['audio_url'] ) ) {
							set_transient( $key, array( 'audio_url' => $found['audio_url'] ), $ttl );
							$url_found = $found['audio_url'];
						}
					}
				}
				if ( ! $url_found && is_array( $c ) && ! empty( $c['job_id'] ) && $client ) {
					$st = $client->status( $c['job_id'] );
					if ( ! is_wp_error( $st ) && ! empty( $st['status'] ) && $st['status'] === 'done' && ! empty( $st['audio_url'] ) ) {
						set_transient( $key, array( 'audio_url' => $st['audio_url'] ), $ttl );
						$url_found = $st['audio_url'];
					}
				}
				if ( $url_found ) {
					if ( $picker ) {
						if ( ! isset( $urls[ $lang ] ) || ! is_array( $urls[ $lang ] ) ) $urls[ $lang ] = array();
						$urls[ $lang ][ $gender ] = $url_found;
					} else {
						$urls[ $lang ] = $url_found;
					}
				}
			}
		}

		$resp = new WP_REST_Response( array( 'audio_urls' => $urls ), 200 );
		$resp->header( 'Cache-Control', 'public, max-age=5' );
		return $resp;
	}

	/**
	 * v2.5 — Translation Modal endpoint.
	 * GET /qtale-tts/v1/translation/<post_id>/<lang>
	 *
	 * Henter artikkel-tekst fra WP, proxy-er til qtale.no /api/v1/translate (Opus-MT cache).
	 * Returns: { text: "...", lang: "en", source_lang: "no", cached: bool, engine: "opus|azure|noop" }
	 *
	 * Cache-strategi: qtale.no har translation_cache (text-hash, src, tgt) — identical input
	 * returneres instant + gratis. Plugin-side: 1 dag transient på utgående svar for å redusere
	 * proxy-overhead på populære artikler.
	 */
	public static function rest_translation( $request ) {
		$post_id = (int) $request['post_id'];
		$lang    = sanitize_text_field( $request['lang'] );
		if ( $post_id <= 0 || $lang === '' ) {
			return new WP_Error( 'qtale_bad_request', __( 'Manglende post_id eller lang.', 'qtale-tts' ), array( 'status' => 400 ) );
		}
		$post = get_post( $post_id );
		if ( ! $post || $post->post_status !== 'publish' ) {
			return new WP_Error( 'qtale_not_found', __( 'Innlegg ikke funnet eller ikke publisert.', 'qtale-tts' ), array( 'status' => 404 ) );
		}
		$s = Qtale_TTS::settings();
		$src_lang = ! empty( $s['source_language'] ) ? $s['source_language'] : 'no';

		// v2.6.9 — credit-meta for modal-header + author-footer (juridisk nødvendig
		// for redaksjonelt innhold). Henter fra WP-meta: post-tittel, permalink,
		// author display_name, author description (rolle/tittel), publisert-tidspunkt.
		$meta_title     = (string) get_the_title( $post );
		$meta_permalink = (string) get_permalink( $post_id );
		$author_id      = (int) $post->post_author;
		$author_name    = $author_id ? (string) get_the_author_meta( 'display_name', $author_id ) : '';
		$author_desc    = $author_id ? trim( (string) get_the_author_meta( 'description', $author_id ) ) : '';
		// Ta KUN første linje av description (full bio kan være lang) — speilet kort tittel/rolle
		$author_title   = $author_desc !== '' ? trim( explode( "\n", $author_desc )[0] ) : '';
		// Norsk dato-format via wp_date (respekterer site locale)
		$published_ts   = (int) get_post_time( 'U', true, $post );
		$published_human = $published_ts ? wp_date( 'j. F Y | H:i', $published_ts ) : '';

		// v2.6.14 — oversetter ogsåtittelen separat (plain-text translate, kort tekst,
		// gratis via Opus for no→en, ellers Azure plain). WP-transient 7d cache pr
		// (post, lang) — endrer seg sjelden, gjenbrukes for modal + PDF. Hvis target=src
		// eller tittel-tomt: ingen API-rundtur.
		$meta_title_translated = $meta_title;
		if ( $lang !== $src_lang && $meta_title !== '' ) {
			$title_cache_key = 'qtt_title_' . $post_id . '_' . $lang;
			$cached_title    = get_transient( $title_cache_key );
			if ( $cached_title !== false ) {
				$meta_title_translated = (string) $cached_title;
			} else {
				$client_title = new Qtale_TTS_Client( $s['api_base'], $s['api_key'] );
				$out_title    = $client_title->translate_text( $meta_title, $src_lang, $lang );
				if ( ! is_wp_error( $out_title ) && ! empty( $out_title['translated'] ) ) {
					$meta_title_translated = (string) $out_title['translated'];
					set_transient( $title_cache_key, $meta_title_translated, WEEK_IN_SECONDS );
				}
			}
		}

		// v2.6.7 — modal-first-view bruker NÅ HTML-strukturert oversettelse (samme som PDF):
		// overskrifter, avsnitt, lister, blockquote, fet/kursiv, lenker bevart. Deler
		// translation-cache + budget-cap m/ PDF-flow (azure-html under cap, opus DOM-walk over).
		$allowed = array(
			'h1' => array(), 'h2' => array(), 'h3' => array(), 'h4' => array(),
			'p' => array(), 'br' => array(), 'hr' => array(),
			'strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(), 'u' => array(),
			'ul' => array(), 'ol' => array(), 'li' => array(),
			'blockquote' => array(),
			'a' => array( 'href' => true, 'title' => true ),
		);
		$raw_html = (string) apply_filters( 'the_content', $post->post_content );
		$text     = wp_kses( $raw_html, $allowed );
		$text     = preg_replace( '/<p[^>]*>\s*<\/p>/i', '', $text );
		$text     = preg_replace( '/\s+/u', ' ', $text );
		$text     = trim( $text );

		// Felles credit-meta returneres på ALLE response-paths (empty/noop/cache/fresh)
		// så modal kan rendre header (tittel + lenke) og footer (forfatter + dato).
		// `title` = original (kilde-språk), `title_translated` = på target-språk.
		// `qtext_tier` (v2.6.17) = access/pro/enterprise → modal viser tier-badge.
		$credit_meta = array(
			'meta' => array(
				'title'            => $meta_title,
				'title_translated' => $meta_title_translated,
				'permalink'        => $meta_permalink,
				'author_name'      => $author_name,
				'author_title'     => $author_title,
				'published_human'  => $published_human,
				'qtext_tier'       => self::current_qtext_tier( $s ),
			),
		);

		if ( $text === '' ) {
			return new WP_REST_Response( array_merge( $credit_meta, array(
				'text' => '', 'lang' => $lang, 'source_lang' => $src_lang,
				'cached' => false, 'engine' => 'empty', 'format' => 'html',
			) ), 200 );
		}

		// Hvis target = kilde → returner originalen direkte (ingen API-rundtur)
		if ( $lang === $src_lang ) {
			$resp = new WP_REST_Response( array_merge( $credit_meta, array(
				'text' => $text, 'lang' => $lang, 'source_lang' => $src_lang,
				'cached' => true, 'engine' => 'noop', 'format' => 'html',
			) ), 200 );
			$resp->header( 'Cache-Control', 'public, max-age=300' );
			return $resp;
		}

		// Plugin-side transient cache (1 dag) — egen prefix `qtt_txhtml_` så HTML-mode
		// ikke kolliderer med eldre plain-text cache fra v2.6.6 og tidligere.
		$content_hash = md5( $text );
		$cache_key    = 'qtt_txhtml_' . $post_id . '_' . $lang . '_' . substr( $content_hash, 0, 8 );
		$cached_val   = get_transient( $cache_key );
		if ( $cached_val !== false ) {
			$resp = new WP_REST_Response( array_merge( $credit_meta, array(
				'text' => $cached_val, 'lang' => $lang, 'source_lang' => $src_lang,
				'cached' => true, 'engine' => 'wp-transient', 'format' => 'html',
			) ), 200 );
			$resp->header( 'Cache-Control', 'public, max-age=300' );
			return $resp;
		}

		// Kall qtale.no /api/v1/translate-html (Azure under cap → Opus DOM-walk over cap)
		$client = new Qtale_TTS_Client( $s['api_base'], $s['api_key'] );
		$out = $client->translate_html( $text, $src_lang, $lang );
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		$translated = isset( $out['translated'] ) ? (string) $out['translated'] : '';
		$engine     = isset( $out['engine'] ) ? (string) $out['engine'] : 'unknown';
		if ( $translated !== '' ) {
			set_transient( $cache_key, $translated, DAY_IN_SECONDS );
		}
		$resp = new WP_REST_Response( array_merge( $credit_meta, array(
			'text' => $translated, 'lang' => $lang, 'source_lang' => $src_lang,
			'cached' => false, 'engine' => $engine, 'format' => 'html',
		) ), 200 );
		$resp->header( 'Cache-Control', 'public, max-age=300' );
		return $resp;
	}

	/**
	 * v2.5 Fase 4 — Translation-PDF endpoint.
	 * GET /qtale-tts/v1/translation-pdf/<post_id>/<lang>
	 *
	 * Henter oversatt tekst (gjenbruker rest_translation-flyten), bygger HTML-payload
	 * + POST-er til qtale.no /api/pdf-html. Streamer PDF tilbake til klient.
	 *
	 * Addon-gated via translation_modal_addon — uten addon: 403.
	 */
	public static function rest_translation_pdf( $request ) {
		$post_id = (int) $request['post_id'];
		$lang    = sanitize_text_field( $request['lang'] );
		if ( $post_id <= 0 || $lang === '' ) {
			return new WP_Error( 'qtale_bad_request', __( 'Manglende post_id eller lang.', 'qtale-tts' ), array( 'status' => 400 ) );
		}
		$s = Qtale_TTS::settings();
		if ( empty( $s['translation_modal_addon'] ) ) {
			return new WP_Error( 'qtale_addon_required', __( 'Translation Modal-addon kreves for PDF-eksport av oversatt tekst.', 'qtale-tts' ), array( 'status' => 403 ) );
		}
		$post = get_post( $post_id );
		if ( ! $post || $post->post_status !== 'publish' ) {
			return new WP_Error( 'qtale_not_found', __( 'Innlegg ikke funnet eller ikke publisert.', 'qtale-tts' ), array( 'status' => 404 ) );
		}
		$src_lang = ! empty( $s['source_language'] ) ? $s['source_language'] : 'no';

		// v2.6.6 — bruk HTML-strukturert artikkel (ikke stripped) så PDF beholder
		// overskrifter (h1/h2/h3), avsnitt (p), lister (ul/ol/li), blockquote,
		// strong/em og lenker. wp_kses sletter <script>/<style>/event-handlers etc.
		// Server-side oversettelse velger automatisk Azure HTML eller Opus DOM-walk
		// avhengig av månedlig budget-cap (system_settings.translate_html_monthly_nok_cap).
		$allowed = array(
			'h1' => array(), 'h2' => array(), 'h3' => array(), 'h4' => array(),
			'p' => array(), 'br' => array(), 'hr' => array(),
			'strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(), 'u' => array(),
			'ul' => array(), 'ol' => array(), 'li' => array(),
			'blockquote' => array(),
			'a' => array( 'href' => true, 'title' => true ),
		);
		$raw_html  = (string) apply_filters( 'the_content', $post->post_content );
		$html      = wp_kses( $raw_html, $allowed );
		// Komprimér whitespace + dropp tomme avsnitt så regex-replacement i fallback
		// ikke produserer rare mellomrom.
		$html      = preg_replace( '/<p[^>]*>\s*<\/p>/i', '', $html );
		$html      = preg_replace( '/\s+/u', ' ', $html );
		$html      = trim( $html );
		if ( $html === '' ) {
			return new WP_Error( 'qtale_empty', __( 'Ingen tekst i innlegget.', 'qtale-tts' ), array( 'status' => 400 ) );
		}
		// v2.6.14 hotfix — $client må være tilgjengelig UTENFOR cache-miss-blokken
		// fordi tittel-translate nedenfor bruker den uansett om body var cached.
		$client     = new Qtale_TTS_Client( $s['api_base'], $s['api_key'] );
		$translated = $html;
		if ( $lang !== $src_lang ) {
			$content_hash = md5( $html );
			$cache_key    = 'qtt_txhtml_' . $post_id . '_' . $lang . '_' . substr( $content_hash, 0, 8 );
			$cached_val   = get_transient( $cache_key );
			if ( $cached_val !== false ) {
				$translated = (string) $cached_val;
			} else {
				$out = $client->translate_html( $html, $src_lang, $lang );
				if ( is_wp_error( $out ) ) {
					return $out;
				}
				$translated = isset( $out['translated'] ) ? (string) $out['translated'] : '';
				if ( $translated !== '' ) {
					set_transient( $cache_key, $translated, DAY_IN_SECONDS );
				}
			}
		}
		if ( $translated === '' ) {
			return new WP_Error( 'qtale_translate_failed', __( 'Oversetting feilet.', 'qtale-tts' ), array( 'status' => 502 ) );
		}

		// POST til qtale.no /api/pdf-html
		$api_base = isset( $s['api_base'] ) ? rtrim( $s['api_base'], '/' ) : 'https://app.qtale.no';
		// PDF-host: api_base peker mot app.qtale.no, men /api/pdf-html ligger på qtale.no marketing.
		// Default qtale.no, kan overstyres via filter qtale_pdf_html_endpoint.
		$pdf_endpoint = apply_filters( 'qtale_pdf_html_endpoint', 'https://qtale.no/api/pdf-html' );
		$brand = self::pdf_brand();

		// v2.6.9 — author + publiseringstidspunkt fra WP user-meta (juridisk credit)
		$author_id   = (int) $post->post_author;
		$author_name = $author_id ? (string) get_the_author_meta( 'display_name', $author_id ) : '';
		$author_desc = $author_id ? trim( (string) get_the_author_meta( 'description', $author_id ) ) : '';
		$author_role = $author_desc !== '' ? trim( explode( "\n", $author_desc )[0] ) : '';
		$pub_ts      = (int) get_post_time( 'U', true, $post );
		$pub_human   = $pub_ts ? wp_date( 'j. F Y | H:i', $pub_ts ) : '';

		// v2.6.14 — oversetter tittelen separat (gjenbruker WP-transient fra rest_translation
		// hvis modalen allerede har åpnet artikkelen — typisk er modal-bruk før PDF-eksport).
		$post_title  = (string) get_the_title( $post );
		$pdf_title   = $post_title;
		if ( $lang !== $src_lang && $post_title !== '' ) {
			$title_cache_key = 'qtt_title_' . $post_id . '_' . $lang;
			$cached_title    = get_transient( $title_cache_key );
			if ( $cached_title !== false ) {
				$pdf_title = (string) $cached_title;
			} else {
				$out_title = $client->translate_text( $post_title, $src_lang, $lang );
				if ( ! is_wp_error( $out_title ) && ! empty( $out_title['translated'] ) ) {
					$pdf_title = (string) $out_title['translated'];
					set_transient( $title_cache_key, $pdf_title, WEEK_IN_SECONDS );
				}
			}
		}

		$payload = array(
			'title'           => $pdf_title,
			'body_html'       => $translated,
			'brand'           => $brand,
			'lang'            => $lang,
			'source_url'      => get_permalink( $post_id ),
			'author_name'     => $author_name,
			'author_title'    => $author_role,
			'published_human' => $pub_human,
			'qtext_tier'      => self::current_qtext_tier( $s ),
		);
		$resp = wp_remote_post( $pdf_endpoint, array(
			'timeout' => 90,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $payload ),
		) );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = wp_remote_retrieve_response_code( $resp );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'qtale_pdf_upstream',
				sprintf( __( 'PDF-tjenesten svarte %d', 'qtale-tts' ), $code ),
				array( 'status' => 502 ) );
		}
		$body = wp_remote_retrieve_body( $resp );
		$fname = 'qtale-' . sanitize_file_name( $post->post_name ?: 'artikkel' ) . '-' . $lang . '.pdf';
		// Send PDF direkte ut — IKKE bruk WP_REST_Response (binær)
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $fname . '"' );
		header( 'Content-Length: ' . strlen( $body ) );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * v2.6.18/19 — Q-Text tier (access/pro/enterprise) for modal-badge (desktop).
	 * Sannhetskilde: qtale.no /api/v1/me (system_settings.qtext_tier_assignments).
	 * Returnerer lagret `qtext_tier` hvis satt; ellers SELF-HEAL: synk fra /me + cache
	 * VERDIEN (ikke en «don't retry»-guard). Suksess → 6t-cache + persistér til options.
	 * Feil → kun 20 min cache så vi retry-er snart (unngår 6t-blokk ved ett /me-hikk).
	 */
	public static function current_qtext_tier( $settings, $client = null ) {
		$tier = isset( $settings['qtext_tier'] ) ? (string) $settings['qtext_tier'] : '';
		if ( $tier !== '' ) {
			return $tier;
		}
		$cached = get_transient( 'qtt_qtext_tier_val' );
		if ( $cached !== false ) {
			return (string) $cached; // cachet faktisk verdi (kan være '' ved kjent-tom)
		}
		if ( empty( $settings['api_base'] ) || empty( $settings['api_key'] ) ) {
			return '';
		}
		if ( ! $client ) {
			$client = new Qtale_TTS_Client( $settings['api_base'], $settings['api_key'] );
		}
		$me = $client->me();
		if ( ! is_wp_error( $me ) && ! empty( $me['addons']['qtext_tier'] ) ) {
			$tier = sanitize_key( (string) $me['addons']['qtext_tier'] );
			$opt  = get_option( Qtale_TTS::OPTION_KEY, array() );
			if ( is_array( $opt ) ) {
				$opt['qtext_tier'] = $tier;
				wp_cache_delete( Qtale_TTS::OPTION_KEY, 'options' );
				update_option( Qtale_TTS::OPTION_KEY, $opt );
			}
			set_transient( 'qtt_qtext_tier_val', $tier, 6 * HOUR_IN_SECONDS );
			return $tier;
		}
		// /me feilet eller ingen tier → kort cache (20 min) så vi retry-er snart, ikke 6t.
		set_transient( 'qtt_qtext_tier_val', '', 20 * MINUTE_IN_SECONDS );
		return '';
	}

	/**
	 * [qtale-player id="qpd_xyz"]Article text[/qtale-player]
	 *
	 * Design-driven embed-player. Hybrid-C generation strategy:
	 *   1. Source lang (Norwegian or design.source_language) → sync (block ~2-5 s)
	 *   2. Other langs in design.translate_langs → scheduled via wp-cron, lazy-poll
	 *      fallback in client if visitor arrives before audio ready.
	 *
	 * Cache key per (post_id, design_id, lang) using transients (TTL = cache_ttl_days).
	 */
	public static function render_embed( $atts, $content = '' ) {
		$settings = Qtale_TTS::settings();
		$atts     = shortcode_atts(
			array(
				'id'      => '',
				'text'    => '',
				'source'  => '',  // override source_language; empty = use settings
			),
			$atts,
			self::TAG_EMBED
		);

		$design_id = trim( $atts['id'] );
		if ( $design_id === '' ) {
			return self::error_box( __( 'Q-Tale: shortcode mangler id="…" attributt.', 'qtale-tts' ) );
		}
		if ( $settings['api_key'] === '' ) {
			return self::error_box( __( 'Q-Tale: API-nøkkel ikke konfigurert. Innstillinger → Q-Tale TTS.', 'qtale-tts' ) );
		}

		// Resolve text — ALWAYS use shared extract from current post for
		// cache_key consistency. (attribute/body overrides ignored intentionally
		// to ensure auto-inject + render + poll all hash the SAME text.)
		$post = get_post();
		$text = Qtale_TTS::extract_post_text( $post );
		if ( $text === '' ) {
			return self::error_box( __( 'Q-Tale: ingen tekst funnet.', 'qtale-tts' ) );
		}

		// Fetch the design — cached transient for 1 hour (designs rarely change)
		$design_cache_key = 'qtale_design_' . md5( $design_id );
		$design = get_transient( $design_cache_key );
		if ( ! $design ) {
			$GLOBALS['qtale_app_base'] = isset( $settings['app_base'] ) ? $settings['app_base'] : 'https://app.qtale.no';
			$client = new Qtale_TTS_Client( $settings['api_base'], $settings['api_key'] );
			$resp   = $client->player_design_by_id( $design_id );
			if ( is_wp_error( $resp ) || empty( $resp['design'] ) ) {
				return self::error_box( __( 'Q-Tale: kunne ikke finne design — sjekk public_id.', 'qtale-tts' ) );
			}
			$design = $resp['design'];
			set_transient( $design_cache_key, $design, 30 );  // very short TTL — quick config changes
		}

		$cfg = isset( $design['config'] ) ? $design['config'] : array();
		// v2.5 — addon-gating: hvis kunde ikke har Translation Modal addon, tvinges tx_modal_on=false
		// så «Les»-knappen + data-tx-url ikke rendres. Designer kan fortsatt sette flagget
		// (lagres i public design); kun render-laget gater.
		if ( empty( $settings['translation_modal_addon'] ) ) {
			$cfg['tx_modal_on'] = false;
		}
		// v2.6 — utility-pack addon-gating: Print og PDF krever Verktøy-pakke. A+ (font) og Del er ALLTID gratis.
		// Designer kan velge alle 4 i config; render-laget filtrerer Print/PDF bort uten addon.
		if ( empty( $settings['utility_pack_addon'] ) ) {
			$cfg['utilbar_print'] = false;
			$cfg['utilbar_pdf']   = false;
		}
		$langs = ( ! empty( $cfg['translate_langs'] ) && is_array( $cfg['translate_langs'] ) )
			? $cfg['translate_langs']
			: array( 'no' );

		// Source lang resolution: shortcode attr > settings > first lang in design > 'no'
		$source_lang = strtolower( trim( $atts['source'] ) );
		if ( ! $source_lang ) {
			$source_lang = Qtale_TTS::resolve_source_language();
		}
		if ( ! in_array( $source_lang, $langs, true ) ) {
			// If source isn't in design's langs, prepend it
			array_unshift( $langs, $source_lang );
			$langs = array_values( array_unique( $langs ) );
		}

		// Pre-generation: render submits all needed jobs (no sync wait — visitor browser polls)
		$post_id  = $post ? $post->ID : 0;
		$client   = new Qtale_TTS_Client( $settings['api_base'], $settings['api_key'] );
		$audio_urls = array();
		$ttl = max( 1, (int) $settings['cache_ttl_days'] ) * DAY_IN_SECONDS;
		$picker = ( isset( $cfg['voice_gender'] ) && $cfg['voice_gender'] === 'picker' );
		$genders_to_gen = $picker ? array( 'male', 'female' ) : array( $cfg['voice_gender'] ?? 'female' );
		// LAZY: eagerly generate ONLY the primary combo (source lang + default visible
		// voice). Any already-cached combo is still served. Everything else is generated
		// on demand when a visitor switches language/voice (see rest_embed_poll).
		$default_gender = $picker ? 'male' : ( $cfg['voice_gender'] ?? 'female' );

		foreach ( $langs as $lang ) {
			foreach ( $genders_to_gen as $gender ) {
				$voice = self::pick_voice( $lang, $gender );
				if ( ! $voice ) continue;
				$cache_key = self::embed_cache_key( $post_id, $design_id, $lang . '-' . $gender, $text );
				$existing = get_transient( $cache_key );
				if ( is_array( $existing ) && ! empty( $existing['audio_url'] ) ) {
					self::_add_audio_url( $audio_urls, $lang, $gender, $existing['audio_url'], $picker );
					continue;   // already warm → always serve it
				}
				// v2.4: NO generation at render time. Audio is generated only when a
				// reader actually clicks play/download (browser poll → rest_embed_poll),
				// or pre-warmed by the publish hook for NEW posts. This stops page
				// renders + cache-preload crawlers (WP Rocket Preload) from voicing the
				// whole archive. Already-cached combos are still served (above).
			}
		}

		self::enqueue_embed_script( $settings );

		// v2.6.18 — emit Q-Text tier inn i embed-config så modalen kan vise tier-badge
		// UMIDDELBART ved åpning (ikke bare etter body-load via REST-meta). Self-heal hvis tom.
		$cfg['qtext_tier'] = self::current_qtext_tier( $settings, $client );

		// Build container with data-config + data-audio-XX attrs + data-poll-url
		$container_id = 'qte-' . wp_generate_uuid4();
		$cfg_json     = wp_json_encode( $cfg );
		$poll_url     = rest_url( 'qtale-tts/v1/embed-poll/' . $design_id . '/' . $post_id );

		$data_attrs = sprintf(
			' data-qtale-embed data-design-id="%s" data-source-lang="%s" data-poll-url="%s" data-config=\'%s\'',
			esc_attr( $design_id ),
			esc_attr( $source_lang ),
			esc_url( $poll_url ),
			esc_attr( $cfg_json )
		);
		// Utility-bar/PDF-kontekst: artikkel-permalink + Q-Tale brand (per WP-locale) + innholds-selector
		$data_attrs .= sprintf( ' data-permalink="%s"', esc_url( get_permalink( $post_id ) ) );
		$data_attrs .= sprintf( ' data-pdf-brand="%s"', esc_attr( self::pdf_brand() ) );
		$content_sel = trim( (string) ( $settings['pdf_content_selector'] ?? '' ) );
		if ( '' === $content_sel ) {
			$content_sel = '.entry-content, article .entry-content, article, .post-content, main';
		}
		$data_attrs .= sprintf( ' data-content-sel="%s"', esc_attr( $content_sel ) );
		// v2.5 — Translation modal URL (post_id satt; embed bytter ut {lang} per fetch)
		// Bare emit hvis addon er aktiv OG designet faktisk har tx_modal_on på.
		if ( ! empty( $settings['translation_modal_addon'] ) && ! empty( $cfg['tx_modal_on'] ) ) {
			$tx_url     = rest_url( 'qtale-tts/v1/translation/' . $post_id . '/__LANG__' );
			$tx_pdf_url = rest_url( 'qtale-tts/v1/translation-pdf/' . $post_id . '/__LANG__' );
			$data_attrs .= sprintf( ' data-tx-url="%s"', esc_url( $tx_url ) );
			$data_attrs .= sprintf( ' data-tx-pdf-url="%s"', esc_url( $tx_pdf_url ) );
			$data_attrs .= sprintf( ' data-post-id="%d"', $post_id );
		}
		// audio_urls may be: lang => url (single voice) OR lang => {male: url, female: url}
		foreach ( $audio_urls as $lang => $val ) {
			if ( is_array( $val ) ) {
				foreach ( $val as $gender => $url ) {
					$data_attrs .= sprintf( ' data-audio-%s-%s="%s"', esc_attr( $lang ), esc_attr( $gender ), esc_url( $url ) );
				}
			} else {
				$data_attrs .= sprintf( ' data-audio-%s="%s"', esc_attr( $lang ), esc_url( $val ) );
			}
		}

		$print_css = ! empty( $cfg['utilbar'] ) ? self::maybe_print_css() : '';
		return $print_css . sprintf( '<div id="%s" class="qtale-embed-wrap"%s></div>',
			esc_attr( $container_id ), $data_attrs );
	}

	/**
	 * Print-stylesheet for «printer-vennlig» utskrift (Print-knappen i utility-baren).
	 * Skjuler nettsted-chrome/annonser/spiller + ren artikkel-typografi. Injiseres ÉN gang per side.
	 */
	private static $print_css_done = false;
	private static function maybe_print_css() {
		if ( self::$print_css_done ) {
			return '';
		}
		self::$print_css_done = true;
		$css = '@media print{'
			. '#wpadminbar,.site-header,#masthead,header#header,.site-footer,#colophon,footer#footer,'
			. 'nav,.navbar,.main-navigation,[role="navigation"],.menu-toggle,.breadcrumbs,'
			. '.sidebar,#sidebar,[role="complementary"],.widget-area,.widget,'
			. '#comments,.comments-area,.comment-respond,'
			. '.share,.sharing,.social-share,.social-links,.related-posts,.related-articles,.related,'
			. '.ad,.ads,.adsbygoogle,[class*="advert"],[id*="advert"],'
			. '.cookie-banner,.cookie-consent,.gdpr,.newsletter,.newsletter-signup,.subscribe-box,'
			. '.qtale-embed-wrap,.qte,.qte-utilbar{display:none !important;}'
			. 'body{background:#fff !important;color:#111 !important;font-size:12pt;line-height:1.55;}'
			. '.entry-content,article,.post,.post-content,main,#content,#main{width:100% !important;max-width:100% !important;margin:0 !important;padding:0 !important;float:none !important;}'
			. 'a[href]{color:#111 !important;text-decoration:underline;}'
			. 'img,figure{max-width:100% !important;height:auto !important;page-break-inside:avoid;}'
			. 'h1,h2,h3{page-break-after:avoid;}p,li,blockquote{orphans:3;widows:3;}'
			. '@page{margin:18mm 16mm;}'
			. '}';
		return '<style id="qtale-print-css" media="print">' . $css . '</style>';
	}

	private static function embed_cache_key( $post_id, $design_id, $lang, $text ) {
		// Delegate to canonical helper — identical across all plugin code paths
		return Qtale_TTS::embed_cache_key( $design_id, $post_id, $lang, $text );
	}

	/**
	 * Q-Tale brand-domene per WP-locale (PDF-stempel «PDF Konvertering av <brand>»).
	 * nb/nn/no → qtale.no · sv → qtal.se · da → qtale.dk · en + øvrige → qspeech.eu.
	 * Overstyrbar via filter `qtale_pdf_brand`.
	 */
	public static function pdf_brand() {
		$loc = strtolower( function_exists( 'get_locale' ) ? (string) get_locale() : '' );
		$p   = substr( $loc, 0, 2 );
		$map = array( 'sv' => 'qtal.se', 'da' => 'qtale.dk', 'nb' => 'qtale.no', 'nn' => 'qtale.no', 'no' => 'qtale.no' );
		if ( isset( $map[ $p ] ) ) {
			$brand = $map[ $p ];
		} elseif ( '' === $p ) {
			$brand = 'qtale.no';      // ukjent locale → trygg default (de fleste kunder er norske)
		} else {
			$brand = 'qspeech.eu';    // en + alle øvrige internasjonale
		}
		return apply_filters( 'qtale_pdf_brand', $brand );
	}

	/** Helper: append audio_url to map in picker (gendered) or single-voice mode. */
	private static function _add_audio_url( &$audio_urls, $lang, $gender, $url, $picker ) {
		if ( $picker ) {
			if ( ! isset( $audio_urls[ $lang ] ) || ! is_array( $audio_urls[ $lang ] ) ) {
				$audio_urls[ $lang ] = array();
			}
			$audio_urls[ $lang ][ $gender ] = $url;
		} else {
			$audio_urls[ $lang ] = $url;
		}
	}

	private static function enqueue_embed_script( $settings ) {
		$handle = 'qtale-embed-player';
		if ( wp_script_is( $handle, 'enqueued' ) ) return;
		$src = untrailingslashit( $settings['cdn_base'] ) . '/static/qtale-embed-player.js?v=2026060704';
		wp_enqueue_script( $handle, $src, array(), QTALE_TTS_VERSION, true );
	}

	public static function rest_status( $request ) {
		$job_id   = (string) $request['job_id'];
		$settings = Qtale_TTS::settings();
		$client   = new Qtale_TTS_Client( $settings['api_base'], $settings['api_key'] );
		$st       = $client->status( $job_id );
		if ( is_wp_error( $st ) ) {
			return new WP_REST_Response( array( 'error' => $st->get_error_message() ), 502 );
		}
		// If completed, refresh any matching transient that referenced this job.
		if ( ! empty( $st['status'] ) && $st['status'] === 'completed' && ! empty( $st['audio_url'] ) ) {
			$pending_key = 'qtale_tts_job_' . md5( $job_id );
			$cache_key   = get_transient( $pending_key );
			if ( $cache_key ) {
				$ttl = max( 1, (int) $settings['cache_ttl_days'] ) * DAY_IN_SECONDS;
				set_transient(
					$cache_key,
					array(
						'audio_url' => $st['audio_url'],
						'duration'  => isset( $st['duration'] ) ? (float) $st['duration'] : 0.0,
					),
					$ttl
				);
				delete_transient( $pending_key );
			}
		}
		return new WP_REST_Response(
			array(
				'status'    => isset( $st['status'] ) ? $st['status'] : 'unknown',
				'audio_url' => isset( $st['audio_url'] ) ? $st['audio_url'] : '',
			),
			200
		);
	}

	public static function render( $atts, $content = '' ) {
		$settings = Qtale_TTS::settings();
		$atts     = shortcode_atts(
			array(
				'design'       => $settings['default_design'],
				'voice'        => $settings['default_voice'],
				'theme'        => $settings['default_theme'],
				'speed'        => '1.0',
				'text'         => '',
				'remember'     => '',     // "true" to persist voice/lang in localStorage
				'remember_key' => 'qtale',// localStorage prefix; one site → one bucket
			),
			$atts,
			self::TAG
		);

		// Prefer attribute text, else shortcode body (HTML-stripped).
		$text = trim( $atts['text'] );
		if ( $text === '' && $content !== '' ) {
			$text = wp_strip_all_tags( html_entity_decode( $content, ENT_QUOTES, 'UTF-8' ) );
		}
		$text = trim( $text );

		if ( $text === '' ) {
			return self::error_box( __( 'Q-Tale: ingen tekst angitt for shortcode.', 'qtale-tts' ) );
		}
		if ( strlen( $text ) > 5000 ) {
			return self::error_box( __( 'Q-Tale: shortcode-tekst > 5000 tegn (bruk Generator i portalen for lengre).', 'qtale-tts' ) );
		}
		if ( $settings['api_key'] === '' ) {
			return self::error_box( __( 'Q-Tale: API-nøkkel ikke konfigurert. Innstillinger → Q-Tale TTS.', 'qtale-tts' ) );
		}

		$design = array_key_exists( $atts['design'], Qtale_TTS::designs() ) ? $atts['design'] : 'odin';
		$theme  = array_key_exists( $atts['theme'], Qtale_TTS::themes() ) ? $atts['theme'] : 'auto';
		$voice  = sanitize_text_field( $atts['voice'] );
		$speed  = max( 0.5, min( 2.0, (float) $atts['speed'] ) );

		// Cache key — same input always resolves to same cached audio.
		$cache_key = 'qtale_tts_' . sha1( $text . '|' . $voice . '|' . $speed );
		$cached    = get_transient( $cache_key );

		$audio_url = '';
		$pending   = false;
		$job_id    = '';
		$error     = '';

		if ( is_array( $cached ) && ! empty( $cached['audio_url'] ) ) {
			$audio_url = $cached['audio_url'];
		} else {
			$client = new Qtale_TTS_Client( $settings['api_base'], $settings['api_key'] );
			$res    = $client->generate_and_wait( $text, $voice, array( 'speed' => $speed ), 6 );
			if ( is_wp_error( $res ) ) {
				if ( $res->get_error_code() === 'qtale_timeout' ) {
					$pending = true;
					$data    = $res->get_error_data();
					if ( is_array( $data ) && ! empty( $data['job_id'] ) ) {
						$job_id = $data['job_id'];
						set_transient( 'qtale_tts_job_' . md5( $job_id ), $cache_key, 10 * MINUTE_IN_SECONDS );
					}
				} else {
					$error = $res->get_error_message();
				}
			} elseif ( is_array( $res ) && ! empty( $res['audio_url'] ) ) {
				$audio_url = $res['audio_url'];
				$ttl       = max( 1, (int) $settings['cache_ttl_days'] ) * DAY_IN_SECONDS;
				set_transient(
					$cache_key,
					array(
						'audio_url' => $audio_url,
						'duration'  => isset( $res['duration'] ) ? (float) $res['duration'] : 0.0,
					),
					$ttl
				);
			}
		}

		if ( $error ) {
			return self::error_box( 'Q-Tale: ' . $error );
		}

		self::enqueue_script( $settings );

		$container_id = 'qtale-' . wp_generate_uuid4();
		// qtale-player.js auto-init looks for [data-qtale-player]:not(.qp).
		// data-audio-url is the attribute name the player reads (not "data-audio").
		$data_attrs = array(
			'data-qtale-player' => '',
			'data-style'        => $design,
			'data-theme'        => $theme,
		);
		if ( strtolower( trim( $atts['remember'] ) ) === 'true' ) {
			$data_attrs['data-remember']     = 'true';
			$data_attrs['data-remember-key'] = sanitize_key( $atts['remember_key'] ?: 'qtale' );
		}
		if ( $audio_url ) {
			$data_attrs['data-audio-url'] = $audio_url;
		}
		if ( $pending && $job_id ) {
			$data_attrs['data-qtale-job']  = $job_id;
			$data_attrs['data-qtale-poll'] = rest_url( 'qtale-tts/v1/status/' . $job_id );
		}

		$attr_html = '';
		foreach ( $data_attrs as $k => $v ) {
			$attr_html .= sprintf( ' %s="%s"', esc_attr( $k ), esc_attr( $v ) );
		}

		$out = sprintf(
			'<div id="%1$s" class="qtale-player-embed"%2$s>%3$s</div>',
			esc_attr( $container_id ),
			$attr_html,
			$pending ? esc_html__( 'Genererer lyd …', 'qtale-tts' ) : ''
		);

		if ( $pending ) {
			$out .= self::pending_poll_script( $container_id );
		}
		return $out;
	}

	private static function enqueue_script( $settings ) {
		if ( wp_script_is( Qtale_TTS::SCRIPT_HANDLE, 'enqueued' ) ) {
			return;
		}
		$src = untrailingslashit( $settings['cdn_base'] ) . '/static/qtale-player.js';
		wp_enqueue_script(
			Qtale_TTS::SCRIPT_HANDLE,
			$src,
			array(),
			QTALE_TTS_VERSION,
			true
		);
	}

	private static function pending_poll_script( $id ) {
		// Polls /qtale-tts/v1/status/{job} every 1.5 s up to 60 s. When audio_url
		// arrives, sets data-audio on the container and lets qtale-player.js take over.
		ob_start();
		?>
		<script>
		(function(){
			const el = document.getElementById(<?php echo wp_json_encode( $id ); ?>);
			if (!el) return;
			const url = el.getAttribute('data-qtale-poll');
			if (!url) return;
			let tries = 0;
			const tick = async () => {
				tries++;
				if (tries > 40) return;
				try {
					const r = await fetch(url, { credentials: 'same-origin' });
					if (r.ok) {
						const j = await r.json();
						if (j.status === 'completed' && j.audio_url) {
							el.setAttribute('data-audio-url', j.audio_url);
							el.textContent = '';
							if (window.QtalePlayer && window.QtalePlayer.init) {
								window.QtalePlayer.init(el);
							} else {
								// JS may not have loaded yet; re-init shortly.
								setTimeout(() => window.QtalePlayer && window.QtalePlayer.init(el), 1500);
							}
							return;
						}
					}
				} catch (_) {}
				setTimeout(tick, 1500);
			};
			setTimeout(tick, 1500);
		})();
		</script>
		<?php
		return (string) ob_get_clean();
	}

	private static function error_box( $msg ) {
		return '<div class="qtale-error" style="display:inline-block;padding:8px 12px;border-radius:6px;background:rgba(239,68,68,.10);border:1px solid rgba(239,68,68,.35);color:#b91c1c;font-size:13px;">' . esc_html( $msg ) . '</div>';
	}
}

/**
 * Cron action — gender-aware. Generates one (lang, gender) pair.
 * Delegates to the shared helper so the publish-hook pre-gen and this visit-time
 * cron always write the identical embed cache key.
 */
add_action( 'qtale_embed_gen_voice', function ( $design_id, $post_id, $text, $source_lang, $target_lang, $gender ) {
	Qtale_TTS_Shortcode::generate_voice( $design_id, (int) $post_id, $text, $source_lang, $target_lang, $gender, false );
}, 10, 6 );

/**
 * Legacy cron action — kept for backward-compat with previously-scheduled events.
 * NON-BLOCKING ARCHITECTURE (v2.0.1):
 *   1. Translate sync (fast, ~1-2s)
 *   2. Submit gen-job to QTale (no wait, returns job_id immediately)
 *   3. Store pending-transient {job_id, voice, submitted_at}
 *   4. Polling endpoint (/embed-poll) checks status for pending entries
 *      and upgrades them to {audio_url} when QTale reports completed.
 *
 * Scales to 1000+ articles — no handler timeout regardless of queue depth.
 */
add_action( 'qtale_embed_gen_lang', function ( $design_id, $post_id, $text, $source_lang, $target_lang ) {
	$s = Qtale_TTS::settings();
	if ( empty( $s['api_key'] ) ) return;
	$cache_key = Qtale_TTS::embed_cache_key( $design_id, $post_id, $target_lang, $text );
	$existing = get_transient( $cache_key );
	if ( is_array( $existing ) && ! empty( $existing['audio_url'] ) ) return;  // done
	if ( is_array( $existing ) && ! empty( $existing['job_id'] ) ) {
		// Already pending — skip if submitted < 10 min ago (give it time)
		if ( isset( $existing['submitted_at'] ) && ( time() - $existing['submitted_at'] ) < 600 ) return;
	}

	$voice = isset( Qtale_TTS_Shortcode::VOICE_PER_LANG[ $target_lang ] )
		? Qtale_TTS_Shortcode::VOICE_PER_LANG[ $target_lang ]
		: '';
	if ( ! $voice ) return;

	$client = new Qtale_TTS_Client( $s['api_base'], $s['api_key'] );

	// 1) Translate source→target (sync, fast)
	if ( $source_lang === $target_lang ) {
		$translated = $text;
	} else {
		$tr = $client->translate_text( $text, $source_lang, $target_lang );
		if ( is_wp_error( $tr ) || empty( $tr['translated'] ) ) return;
		$translated = $tr['translated'];
	}

	// 2) Submit-only (NO wait) — returns job_id immediately
	$job_id = $client->generate_submit( $translated, $voice, array( 'speed' => 1.0 ) );
	if ( is_wp_error( $job_id ) || ! $job_id ) return;

	// 3) Store pending state — visitor polling will upgrade to audio_url when ready
	$ttl = max( 1, (int) $s['cache_ttl_days'] ) * DAY_IN_SECONDS;
	set_transient( $cache_key, array(
		'job_id'           => $job_id,
		'voice'            => $voice,
		'translated_chars' => strlen( $translated ),
		'submitted_at'     => time(),
		'pending'          => true,
	), $ttl );
}, 10, 5 );
