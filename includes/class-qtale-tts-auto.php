<?php
/**
 * Auto-generation: hooks into post publish/save to pre-generate audio,
 * and into the_content to inject the player above/below the post body.
 *
 * Triggers only when the customer toggles auto_generate=1.
 *
 * @package Qtale_TTS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qtale_TTS_Auto {

	public static function register() {
		// Always register the_content filter — Post_Meta toggle decides per-post
		// whether to actually inject. This allows 'force' override even when
		// global auto_generate is off.
		add_filter( 'the_content', array( __CLASS__, 'maybe_inject' ), 20 );
		// Publish-hook always on so newly-published posts can pre-generate even if
		// auto-inject is off (so per-post 'force' on save has audio ready)
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 10, 3 );
	}

	public static function on_transition( $new_status, $old_status, $post ) {
		if ( $new_status !== 'publish' ) {
			return;
		}
		$s = Qtale_TTS::settings();
		if ( ! in_array( $post->post_type, (array) $s['post_types'], true ) ) {
			return;
		}
		if ( self::daily_limit_hit() ) {
			return;
		}
		$text = self::extract_text( $post );
		if ( $text === '' ) {
			return;
		}
		// v2.6.20 — Generate DIRECTLY at publish (non-blocking submit), no wp-cron dependency.
		// Previously this scheduled a wp_schedule_single_event → wp-cron, which on a high-traffic
		// site fired near-instantly on the next visit (so it *felt* like direct generation). But
		// when wp-cron is disabled or loopback is blocked (e.g. after a server move) the event
		// silently never fires and pre-gen stops. We now submit every voice combo inline as
		// non-blocking jobs: the editor waits only for the quick submit round-trips, while the
		// worker renders in the background (incl. slow Kokoro) so the first visitor gets cached
		// audio instead of "Genererer …". The qtale_tts_gen cron handler stays registered; if it
		// still fires, generate_voice's in-flight guard + server-side dedup make it a no-op.
		self::run_pregen( (int) $post->ID, true );
	}

	public static function maybe_inject( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$s    = Qtale_TTS::settings();
		$post = get_post();
		if ( ! $post || ! in_array( $post->post_type, (array) $s['post_types'], true ) ) {
			return $content;
		}
		// Per-post toggle takes priority — 'force' shows even if auto is off,
		// 'skip' hides even if auto is on, 'inherit' follows global.
		if ( class_exists( 'Qtale_TTS_Post_Meta' )
			&& ! Qtale_TTS_Post_Meta::is_enabled_for_post( $post->ID ) ) {
			return $content;
		}
		// Placement must be above/below to inject (manual = no injection)
		if ( ! in_array( $s['placement'], array( 'above', 'below' ), true ) ) {
			return $content;
		}
		if ( ! empty( $s['subscriber_only'] ) && ! is_user_logged_in() ) {
			return $content;
		}
		$text = self::extract_text( $post );
		if ( $text === '' ) {
			return $content;
		}
		// v2.4.3 — Dual-Player Addon: hvis aktivert + addon-gating OK + begge slots satt + validering OK,
		// rendrer vi BEGGE playerne i en flex-container (horisontal/vertikal). Validering:
		// nøyaktig én må ha play-knapp (play_shape!='none') — fanges via cached design-list.
		if ( ! empty( $s['dual_player_addon'] ) && ! empty( $s['dual_enabled'] ) && ! empty( $s['dual_slot1_id'] ) && ! empty( $s['dual_slot2_id'] ) ) {
			$psmap = self::dual_play_shape_map();
			$ps1   = isset( $psmap[ $s['dual_slot1_id'] ] ) ? $psmap[ $s['dual_slot1_id'] ] : 'circle';
			$ps2   = isset( $psmap[ $s['dual_slot2_id'] ] ) ? $psmap[ $s['dual_slot2_id'] ] : 'circle';
			$util1 = ( $ps1 === 'none' );
			$util2 = ( $ps2 === 'none' );
			$valid = ( $util1 xor $util2 );   // XOR — nøyaktig én av to må være utility-only
			if ( $valid ) {
				$p1 = do_shortcode( '[qtale-player id="' . esc_attr( $s['dual_slot1_id'] ) . '"]' . esc_html( $text ) . '[/qtale-player]' );
				$p2 = do_shortcode( '[qtale-player id="' . esc_attr( $s['dual_slot2_id'] ) . '"]' . esc_html( $text ) . '[/qtale-player]' );
				$layout  = ( $s['dual_layout'] === 'horizontal' ) ? 'horizontal' : 'vertical';
				$flexDir = ( $layout === 'horizontal' ) ? 'row' : 'column';
				$gap     = max( 0, min( 64, (int) ( isset( $s['dual_gap'] ) ? $s['dual_gap'] : 8 ) ) );
				$dual    = sprintf(
					'<div class="qtale-dual qtale-dual--%s" style="display:flex;flex-direction:%s;gap:%dpx;align-items:stretch;flex-wrap:wrap;">%s%s</div>',
					esc_attr( $layout ), esc_attr( $flexDir ), $gap, $p1, $p2
				);
				$mt = (int) ( isset( $s['placement_margin_top']    ) ? $s['placement_margin_top']    : 12 );
				$mr = (int) ( isset( $s['placement_margin_right']  ) ? $s['placement_margin_right']  : 0  );
				$mb = (int) ( isset( $s['placement_margin_bottom'] ) ? $s['placement_margin_bottom'] : 18 );
				$ml = (int) ( isset( $s['placement_margin_left']   ) ? $s['placement_margin_left']   : 0  );
				$wrapped = sprintf(
					'<div class="qtale-tts-wrap qtale-tts-wrap-dual" style="margin:%dpx %dpx %dpx %dpx;">%s</div>',
					$mt, $mr, $mb, $ml, $dual
				);
				return $s['placement'] === 'above' ? $wrapped . $content : $content . $wrapped;
			}
			// Ugyldig kombinasjon → HTML-kommentar i kilde (admin kan se hvorfor) + fallback til single nedenfor.
			$reason = ( $util1 && $util2 )
				? 'Begge designs mangler play-knapp. Velg ÉN med play-knapp.'
				: 'Begge designs har play-knapp. Velg ÉN utility-only (play_shape=Ingen).';
			$content = '<!-- Qtale dual-player ugyldig: ' . esc_html( $reason ) . ' — faller tilbake til single-player. -->' . $content;
		}
		// Per-post player: Odin/Tor/Valhalla may override per post; otherwise the
		// settings default. Studio public_id → [qtale-player id]; standard key → [qtale design].
		if ( class_exists( 'Qtale_TTS_Post_Meta' ) ) {
			list( $ptype, $pid ) = Qtale_TTS_Post_Meta::player_for_post( $post->ID );
		} else {
			$ptype = ! empty( $s['default_design_public_id'] ) ? 'studio' : 'standard';
			$pid   = ! empty( $s['default_design_public_id'] ) ? $s['default_design_public_id'] : $s['default_design'];
		}
		if ( $ptype === 'studio' && $pid !== '' ) {
			$player = do_shortcode( '[qtale-player id="' . esc_attr( $pid ) . '"]' . esc_html( $text ) . '[/qtale-player]' );
		} else {
			$std = ( $pid !== '' ) ? $pid : ( ! empty( $s['default_design'] ) ? $s['default_design'] : 'odin' );
			$player = do_shortcode( '[qtale design="' . esc_attr( $std ) . '" theme="' . esc_attr( $s['default_theme'] ) . '"]' . esc_html( $text ) . '[/qtale]' );
		}
		// Wrap with admin-configurable margin so editor can tune spacing per site.
		$mt = (int) ( isset( $s['placement_margin_top']    ) ? $s['placement_margin_top']    : 12 );
		$mr = (int) ( isset( $s['placement_margin_right']  ) ? $s['placement_margin_right']  : 0  );
		$mb = (int) ( isset( $s['placement_margin_bottom'] ) ? $s['placement_margin_bottom'] : 18 );
		$ml = (int) ( isset( $s['placement_margin_left']   ) ? $s['placement_margin_left']   : 0  );
		$wrapped = sprintf(
			'<div class="qtale-tts-wrap" style="margin:%dpx %dpx %dpx %dpx;">%s</div>',
			$mt, $mr, $mb, $ml, $player
		);
		return $s['placement'] === 'above' ? $wrapped . $content : $content . $wrapped;
	}

	/**
	 * v2.4.3 — Map public_id → play_shape (fra cached design-liste).
	 * Brukes av dual-player-validering: 'none' = utility-only, alt annet = TTS-player.
	 */
	private static function dual_play_shape_map() {
		$out = array();
		if ( class_exists( 'Qtale_TTS_Post_Meta' ) ) {
			foreach ( Qtale_TTS_Post_Meta::fetch_custom_designs() as $d ) {
				if ( ! empty( $d['public_id'] ) ) {
					$out[ $d['public_id'] ] = isset( $d['play_shape'] ) ? $d['play_shape'] : 'circle';
				}
			}
		}
		return $out;
	}

	public static function extract_text( $post ) {
		// Delegate to shared method (ensures cache_key consistency across plugin)
		$text = Qtale_TTS::extract_post_text( $post );
		// Auto-inject additionally enforces min_chars_auto (skip too-short posts)
		$s = Qtale_TTS::settings();
		$min = max( 0, (int) $s['min_chars_auto'] );
		if ( strlen( $text ) < $min ) return '';
		return $text;
	}

	private static function daily_limit_hit() {
		$s = Qtale_TTS::settings();
		$lim = (int) $s['daily_limit'];
		if ( $lim <= 0 ) {
			return false;
		}
		$key = 'qtale_tts_daily_' . gmdate( 'Ymd' );
		$used = (int) get_transient( $key );
		if ( $used >= $lim ) {
			return true;
		}
		set_transient( $key, $used + 1, DAY_IN_SECONDS );
		return false;
	}

	/**
	 * v2.6.20 — Pre-generate all voice combos for a post.
	 *
	 * @param int  $post_id           the post to warm.
	 * @param bool $force_nonblocking when true (the publish-time inline call) every combo is
	 *                                submitted NON-blocking so the editor's publish round-trip
	 *                                isn't held by slow synthesis (esp. Kokoro ~3-4 min). The
	 *                                worker renders in the background; the visitor poll /
	 *                                find_audio serves it once done. When false (the legacy
	 *                                wp-cron path) the original blocking-warm behaviour applies
	 *                                (source lang + free Kokoro langs warmed into transients).
	 */
	public static function run_pregen( $post_id, $force_nonblocking = false ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$s = Qtale_TTS::settings();
		$text = self::extract_text( $post );
		if ( $text === '' ) {
			return;
		}

		// ── Studio-design path ───────────────────────────────────────────────────
		// When a Studio design is configured, the player renders via render_embed and ONLY reads
		// embed cache keys (qtale_emb_*). Pre-generate into those exact keys so visits hit cache
		// instead of re-generating on every view. Per-post player resolution (Odin/Tor/Valhalla
		// override; else settings default).
		$design_id = ! empty( $s['default_design_public_id'] ) ? $s['default_design_public_id'] : '';
		if ( class_exists( 'Qtale_TTS_Post_Meta' ) ) {
			list( $ptype, $pid ) = Qtale_TTS_Post_Meta::player_for_post( $post_id );
			$design_id = ( $ptype === 'studio' ) ? $pid : '';
		}
		if ( $design_id !== '' && class_exists( 'Qtale_TTS_Shortcode' ) ) {
			@set_time_limit( 0 );
			// Resolve design config (cached; same transient key render_embed uses).
			$design = get_transient( 'qtale_design_' . md5( $design_id ) );
			if ( ! $design ) {
				$GLOBALS['qtale_app_base'] = isset( $s['app_base'] ) ? $s['app_base'] : 'https://app.qtale.no';
				$client = new Qtale_TTS_Client( $s['api_base'], $s['api_key'] );
				$resp   = $client->player_design_by_id( $design_id );
				if ( ! is_wp_error( $resp ) && ! empty( $resp['design'] ) ) {
					$design = $resp['design'];
					set_transient( 'qtale_design_' . md5( $design_id ), $design, 30 );
				}
			}
			$cfg   = ( $design && ! empty( $design['config'] ) ) ? $design['config'] : array();
			$langs = ( ! empty( $cfg['translate_langs'] ) && is_array( $cfg['translate_langs'] ) )
				? $cfg['translate_langs']
				: array( 'no' );
			$source_lang = ! empty( $cfg['source_language'] )
				? strtolower( $cfg['source_language'] )
				: Qtale_TTS::resolve_source_language();
			if ( ! in_array( $source_lang, $langs, true ) ) {
				array_unshift( $langs, $source_lang );
				$langs = array_values( array_unique( $langs ) );
			}
			$picker = ( isset( $cfg['voice_gender'] ) && $cfg['voice_gender'] === 'picker' );
			$default_gender = $picker ? 'male' : ( $cfg['voice_gender'] ?? 'female' );
			$genders = $picker ? array( 'male', 'female' ) : array( $default_gender );
			// Picker designs warm BOTH genders × all langs → e.g. Finn+Pernille (Azure no) and
			// george+alice (free Kokoro en). Each combo generates ONCE, then cached.
			foreach ( $langs as $lang ) {
				foreach ( $genders as $gender ) {
					$is_primary = ( $lang === $source_lang && $gender === $default_gender );
					$voice      = Qtale_TTS_Shortcode::pick_voice( $lang, $gender );
					$is_kokoro  = ( strpos( (string) $voice, 'kokoro' ) !== false ); // free engine
					// v2.6.20: publish-time inline call forces EVERY combo non-blocking so the
					// editor isn't held by slow synthesis; the worker renders in the background.
					// Cron path keeps blocking-warm for source lang + free Kokoro combos.
					$blocking   = ( ! $force_nonblocking ) && ( $is_primary || $is_kokoro );
					Qtale_TTS_Shortcode::generate_voice( $design_id, (int) $post_id, $text, $source_lang, $lang, $gender, $blocking, 30 );
				}
			}
			return;
		}

		// ── Legacy single-voice path (no Studio design configured) ───────────────
		$cache_key = 'qtale_tts_' . sha1( $text . '|' . $s['default_voice'] . '|1.0' );
		if ( get_transient( $cache_key ) ) {
			return; // already cached
		}
		$client = new Qtale_TTS_Client( $s['api_base'], $s['api_key'] );
		if ( $force_nonblocking ) {
			// publish-time: submit only (non-blocking) — the legacy shortcode render finishes it.
			$client->generate_submit( $text, $s['default_voice'], array( 'speed' => 1.0 ) );
			return;
		}
		$res = $client->generate_and_wait( $text, $s['default_voice'], array( 'speed' => 1.0 ), 30 );
		if ( is_wp_error( $res ) || empty( $res['audio_url'] ) ) {
			return;
		}
		$ttl = max( 1, (int) $s['cache_ttl_days'] ) * DAY_IN_SECONDS;
		set_transient(
			$cache_key,
			array( 'audio_url' => $res['audio_url'], 'duration' => isset( $res['duration'] ) ? (float) $res['duration'] : 0 ),
			$ttl
		);
	}
}

// Cron action — kept for backward-compat. v2.6.20 generates inline at publish (see on_transition),
// so this normally never fires; if wp-cron DOES trigger it, generate_voice's in-flight guard +
// server-side dedup make it a no-op. Uses the blocking-warm behaviour (force_nonblocking = false).
add_action( 'qtale_tts_gen', function ( $post_id ) {
	Qtale_TTS_Auto::run_pregen( (int) $post_id, false );
} );
