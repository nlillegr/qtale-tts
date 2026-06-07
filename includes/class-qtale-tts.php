<?php
/**
 * Main plugin bootstrapper.
 *
 * @package Qtale_TTS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qtale_TTS {

	const OPTION_KEY         = 'qtale_tts_settings';
	const OPTION_KEY_DESIGNS = 'qtale_tts_player_designs';   // separate — no sanitize_callback, no risk of being stripped
	const TRANSIENT_TTL      = DAY_IN_SECONDS * 30;
	const SCRIPT_HANDLE      = 'qtale-player';

	public static function boot() {
		load_plugin_textdomain( 'qtale-tts', false, dirname( plugin_basename( QTALE_TTS_FILE ) ) . '/languages' );
		Qtale_TTS_Settings::register();
		Qtale_TTS_Shortcode::register();
		Qtale_TTS_Post_Meta::register();
		Qtale_TTS_Auto::register();
	}

	public static function activate() {
		$defaults = self::default_settings();
		$existing = get_option( self::OPTION_KEY, array() );
		update_option( self::OPTION_KEY, array_merge( $defaults, is_array( $existing ) ? $existing : array() ) );
		// v2.3.7: flush any residual audio-generation cron queue. A previous backfill
		// could have scheduled thousands of qtale_embed_gen_voice events for old
		// articles; those keep draining the archive (and billing Azure) long after the
		// backfill tool itself was disabled. Clearing on activate gives a clean slate —
		// only the publish hook schedules new generations going forward.
		self::flush_pending_queue();
		// If a per-customer prefill JSON was bundled in the ZIP, read it now.
		require_once QTALE_TTS_DIR . 'includes/class-qtale-tts-prefill.php';
		Qtale_TTS_Prefill::maybe_hydrate();
	}

	public static function deactivate() {
		// Intentionally non-destructive for settings + cache (they survive re-activation),
		// BUT do stop the generation queue: a deactivated plugin shouldn't leave orphaned
		// cron events firing against the API. Mirrors the activate-time flush.
		self::flush_pending_queue();
	}

	/**
	 * Unschedule every queued audio-generation cron event, regardless of args.
	 *
	 * The backfill/publish hooks schedule single events WITH arguments
	 * (design_id, post_id, …), so wp_clear_scheduled_hook() — which only matches
	 * events with identical args — cannot clear them in bulk. wp_unschedule_hook()
	 * (WP 4.9+) removes ALL events for the hook. Returns the number cleared.
	 *
	 * @return int events removed
	 */
	public static function flush_pending_queue() {
		$hooks   = array( 'qtale_embed_gen_voice', 'qtale_embed_gen_lang', 'qtale_tts_gen' );
		$cleared = 0;
		foreach ( $hooks as $hook ) {
			if ( function_exists( 'wp_unschedule_hook' ) ) {
				$n = wp_unschedule_hook( $hook );
				if ( is_int( $n ) ) {
					$cleared += $n;
				}
			} else {
				wp_clear_scheduled_hook( $hook ); // pre-4.9 fallback (best-effort)
			}
		}
		return $cleared;
	}

	/**
	 * 15 språk støttet av source/target — matcher Q-Tale Player Designer.
	 * SE = Sámegiella (eget flagg via inline SVG i designer).
	 */
	public static function supported_languages() {
		return array(
			'no' => 'Norsk',
			'sv' => 'Svenska',
			'da' => 'Dansk',
			'fi' => 'Suomi',
			'en' => 'English',
			'de' => 'Deutsch',
			'fr' => 'Français',
			'es' => 'Español',
			'it' => 'Italiano',
			'nl' => 'Nederlands',
			'pl' => 'Polski',
			'pt' => 'Português',
			'se' => 'Sámegiella',
			'is' => 'Íslenska',
			'fo' => 'Føroyskt',
		);
	}

	/**
	 * Map WordPress locale (e.g. 'nb_NO', 'sv_SE') → Q-Tale lang-code.
	 * Returnerer null hvis ingen mapping finnes — caller bestemmer fallback.
	 */
	public static function auto_source_from_wp_locale() {
		$loc = function_exists( 'get_locale' ) ? get_locale() : '';
		if ( ! $loc ) {
			return '';
		}
		// Trim region: 'nb_NO' → 'nb', 'sv_SE' → 'sv'
		$base = strtolower( strtok( $loc, '_' ) );
		$map  = array(
			'nb' => 'no', 'nn' => 'no', 'no' => 'no',
			'sv' => 'sv', 'da' => 'da', 'fi' => 'fi',
			'en' => 'en', 'de' => 'de', 'fr' => 'fr',
			'es' => 'es', 'it' => 'it', 'nl' => 'nl',
			'pl' => 'pl', 'pt' => 'pt', 'is' => 'is',
			'fo' => 'fo', 'se' => 'se', 'sma' => 'se', 'sme' => 'se',
		);
		return isset( $map[ $base ] ) ? $map[ $base ] : '';
	}

	/**
	 * Returner den effektive source-language: bruker-overstyrt, ellers WP-auto, ellers 'no'.
	 */
	public static function resolve_source_language() {
		$s = self::settings();
		if ( ! empty( $s['source_language'] ) ) {
			return $s['source_language'];
		}
		$auto = self::auto_source_from_wp_locale();
		return $auto ?: 'no';
	}

	public static function default_settings() {
		return array(
			'api_key'           => '',
			'api_base'          => 'https://api.qtale.no',
			'app_base'          => 'https://app.qtale.no',
			'cdn_base'          => 'https://qtale.no',
			'default_voice'     => '',
			'default_design'    => 'odin',
			'default_design_public_id' => '',  // tom = bruk legacy [qtale design=odin]-flow
			'default_theme'     => 'auto',
			'source_language'   => '',  // tom = auto-detect fra get_locale() ved bruk

			'allowed_designs'   => array(),  // empty = all allowed
			'tier_name'         => '',       // shown in admin UI as a friendly label
			'cache_ttl_days'    => 30,
			// behaviour — defaults til "auto over content" så plugin fungerer ut-av-boksen
			'auto_generate'     => 1,        // 0 = manual via shortcode only, 1 = on publish
			'placement'         => 'above',  // manual | above | below
			'post_types'        => array( 'post' ),
			'max_chars_auto'    => 3000,
			'min_chars_auto'    => 200,
			'daily_limit'       => 0,        // 0 = ubegrenset
			'subscriber_only'   => 0,        // begrens player til innlogga brukere
			// Player placement padding (px) — applies when auto-injected above/below content
			'placement_margin_top'    => 12,
			'placement_margin_right'  => 0,
			'placement_margin_bottom' => 18,
			'placement_margin_left'   => 0,
			// Tier (set automatically by /api/v1/me when API key is verified)
			'tier_key'          => '',
			'company_name'      => '',
			// v2.4.3 Dual-Player Addon — 2 players (1 TTS + 1 utility-only) per post.
			// Validering ved render: nøyaktig én må ha play-knapp (play_shape!='none').
			'dual_enabled'      => 0,
			'dual_slot1_id'     => '',   // design public_id (Studio)
			'dual_slot2_id'     => '',
			'dual_layout'       => 'vertical',  // 'vertical' (stablet) | 'horizontal' (side-om-side)
			'dual_gap'          => 8,    // px mellom de to playerne
			'dual_player_addon' => 0,    // gating-flag — settes av /api/v1/me basert på system_settings-allowlist (read-only)
			'translation_modal_addon' => 0, // v2.5 — gating-flag (read-only, satt fra /api/v1/me)
			'utility_pack_addon'      => 0, // v2.6 — Verktøy-pakke (Print + PDF) gating. A+/Del er ALLTID gratis.
			'qtext_tier'              => '', // v2.6.17 — Q-Text tier (access/pro/enterprise) for badge + cap
		);
	}

	public static function post_type_choices() {
		$out = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			if ( in_array( $pt->name, array( 'attachment' ), true ) ) {
				continue;
			}
			$out[ $pt->name ] = $pt->labels->singular_name ? $pt->labels->singular_name : $pt->name;
		}
		return $out;
	}

	public static function placements() {
		return array(
			'manual' => __( 'Kun shortcode [qtale]', 'qtale-tts' ),
			'above'  => __( 'Auto: over innhold', 'qtale-tts' ),
			'below'  => __( 'Auto: under innhold', 'qtale-tts' ),
		);
	}

	/**
	 * Returns the design map filtered to those the customer's tier allows.
	 * If allowed_designs is empty the full map is returned.
	 */
	public static function allowed_designs_map() {
		$settings = self::settings();
		$all      = self::designs();
		$allow    = isset( $settings['allowed_designs'] ) && is_array( $settings['allowed_designs'] )
			? $settings['allowed_designs']
			: array();
		if ( empty( $allow ) ) {
			return $all;
		}
		return array_intersect_key( $all, array_flip( $allow ) );
	}

	/**
	 * SINGLE SOURCE OF TRUTH for post-text extraction.
	 * MUST be used identically by ALL code paths that compute cache_key, else
	 * hash will mismatch and audio_url will be cached under a key polling can't find.
	 *
	 *   - Title + ". " + content (stripped of HTML + shortcodes)
	 *   - Whitespace collapsed to single space
	 *   - Truncated to max_chars_auto setting (with ellipsis)
	 *
	 * Returns string (empty if post is null or has zero text).
	 */
	public static function extract_post_text( $post ) {
		if ( ! $post || ! is_object( $post ) ) return '';
		$raw = $post->post_title . '. ' . wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		$raw = trim( preg_replace( '/\s+/', ' ', $raw ) );
		if ( $raw === '' ) return '';
		$s = self::settings();
		$max = max( 100, (int) $s['max_chars_auto'] );
		if ( strlen( $raw ) > $max ) $raw = mb_substr( $raw, 0, $max ) . '…';
		return $raw;
	}

	/** Compute the canonical cache-key for (design, post, lang, text). */
	public static function embed_cache_key( $design_id, $post_id, $lang, $text ) {
		return 'qtale_emb_' . md5( $design_id . '|' . $post_id . '|' . $lang . '|' . sha1( $text ) );
	}

	public static function settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		$out = array_merge( self::default_settings(), is_array( $saved ) ? $saved : array() );
		// v2.0 migration: pre-v2.0 used api_base="https://app.qtale.no" (wrong — generate lives on api.qtale.no).
		if ( ! empty( $out['api_base'] ) && strpos( $out['api_base'], 'app.qtale.no' ) !== false ) {
			$out['api_base'] = 'https://api.qtale.no';
			$out['app_base'] = 'https://app.qtale.no';
			$saved['api_base'] = 'https://api.qtale.no';
			$saved['app_base'] = 'https://app.qtale.no';
			update_option( self::OPTION_KEY, $saved );
		}
		if ( empty( $out['app_base'] ) ) {
			$out['app_base'] = 'https://app.qtale.no';
		}
		// player_designs lives in its OWN option (avoids sanitize_callback stripping).
		// Merge into the settings blob for backwards-compat with downstream code.
		$designs_blob = get_option( self::OPTION_KEY_DESIGNS, array() );
		if ( is_array( $designs_blob ) ) {
			$out['player_designs']            = isset( $designs_blob['designs'] ) && is_array( $designs_blob['designs'] ) ? $designs_blob['designs'] : array();
			$out['player_designs_fetched_at'] = isset( $designs_blob['fetched_at'] ) ? $designs_blob['fetched_at'] : '';
		} else {
			$out['player_designs'] = array();
			$out['player_designs_fetched_at'] = '';
		}
		return $out;
	}

	/**
	 * Designs the front-end player JS supports. Used for the settings dropdown
	 * and to validate shortcode `design` attributes.
	 */
	public static function designs() {
		return array(
			'odin'     => 'Odin (pill)',
			'tor'      => 'Tor (square)',
			'bragi'    => 'Bragi (slim)',
			'lodur'    => 'Lodur',
			'frigg'    => 'Frigg',
			'baldr'    => 'Baldr',
			'njord'    => 'Njord',
			'froya'    => 'Frøya',
			'idunn'    => 'Idunn',
			'beaivi'   => 'Beaivi',
			'raedie'   => 'Raedie',
			'heimdall' => 'Heimdall',
			'saga'     => 'Saga',
		);
	}

	/**
	 * Player designs allowed per tier. Mirror of the server-side TIER_DESIGNS map
	 * in /opt/qtale/api/app.py — keep in sync. We still fetch the authoritative
	 * list from /api/v1/me when the user clicks "Test nøkkel", so this is just a
	 * fallback when the plugin is offline or pre-validation.
	 */
	public static function tier_designs() {
		return array(
			'nanna'    => array( 'bragi', 'lodur' ),
			'beaivi'   => array( 'bragi', 'lodur', 'beaivi', 'raedie' ),
			'frigg'    => array( 'bragi', 'lodur', 'frigg', 'baldr', 'njord', 'froya' ),
			'valhalla' => array( 'bragi', 'lodur', 'frigg', 'baldr', 'njord', 'froya',
			                     'idunn', 'saga', 'beaivi', 'raedie' ),
			'thor'     => array( 'bragi', 'lodur', 'frigg', 'baldr', 'njord', 'froya',
			                     'idunn', 'saga', 'beaivi', 'raedie', 'heimdall', 'tor' ),
			'odin'     => array_keys( self::designs() ),  // ALL
		);
	}

	public static function themes() {
		return array(
			'auto' => __( 'Auto (følger nettsiden)', 'qtale-tts' ),
			'dark' => __( 'Mørk', 'qtale-tts' ),
			'light' => __( 'Lys', 'qtale-tts' ),
		);
	}
}
