<?php
/**
 * Settings page (Settings → Q-Tale TTS).
 *
 * @package Qtale_TTS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qtale_TTS_Settings {

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'init_fields' ) );
		add_action( 'wp_ajax_qtale_tts_test_key', array( __CLASS__, 'ajax_test_key' ) );
		add_action( 'wp_ajax_qtale_tts_refresh_designs', array( __CLASS__, 'ajax_refresh_designs' ) );
		add_action( 'wp_ajax_qtale_tts_backfill', array( __CLASS__, 'ajax_backfill' ) );
		add_action( 'wp_ajax_qtale_tts_backfill_status', array( __CLASS__, 'ajax_backfill_status' ) );
		add_action( 'wp_ajax_qtale_tts_flush_queue', array( __CLASS__, 'ajax_flush_queue' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_css' ) );
	}

	public static function enqueue_admin_css( $hook ) {
		if ( $hook !== 'settings_page_qtale-tts' ) {
			return;
		}
		wp_add_inline_style( 'common', self::admin_css() );
	}

	private static function admin_css() {
		return <<<CSS
		.qtale-wrap{max-width:980px;margin:20px 0;font-family:-apple-system,BlinkMacSystemFont,'Inter',sans-serif;}
		/* WP admin notice — make readable against our hero/card background */
		.qtale-wrap .notice,
		.qtale-wrap .updated,
		.qtale-wrap .settings-error,
		.qtale-wrap div.error{
			background:#dcfce7 !important;border-left:4px solid #16a34a !important;
			color:#166534 !important;padding:10px 14px !important;margin:14px 0 18px !important;
			border-radius:6px !important;font-weight:600;
		}
		.qtale-wrap .notice-error,
		.qtale-wrap div.error{
			background:#fee2e2 !important;border-left-color:#dc2626 !important;color:#991b1b !important;
		}
		.qtale-wrap .notice p,
		.qtale-wrap .updated p{
			color:inherit !important;-webkit-text-fill-color:initial !important;margin:0 !important;
		}
		.qtale-hero{
			position:relative;padding:30px 32px;border-radius:18px;margin:0 0 24px;overflow:hidden;
			background:linear-gradient(135deg,#0d1117 0%,#1a1f2e 60%,#2a1e15 100%);
			border:1px solid rgba(232,81,36,.3);box-shadow:0 12px 40px rgba(0,0,0,.18);
			color:#fff;
		}
		.qtale-hero::before{
			content:'';position:absolute;top:0;left:0;right:0;height:3px;
			background:linear-gradient(90deg,#E85124 0%,#ff8a5c 30%,#c4b5fd 70%,#60a5fa 100%);
		}
		.qtale-hero h1{
			color:#fff !important;font-size:28px;font-weight:900;margin:0 0 6px;
			background:linear-gradient(135deg,#fff 55%,rgba(255,255,255,.55));
			-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;
		}
		.qtale-hero p{color:rgba(255,255,255,.7);margin:0;font-size:14px;line-height:1.55;}
		.qtale-hero .qt-badge{
			display:inline-block;padding:4px 12px;border-radius:99px;
			background:rgba(232,81,36,.18);border:1px solid rgba(232,81,36,.5);
			color:#ff8a5c;font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;
			margin-bottom:10px;
		}
		.qtale-tier-pill{
			display:inline-block;padding:3px 10px;border-radius:99px;
			background:rgba(96,165,250,.15);border:1px solid rgba(96,165,250,.4);
			color:#93c5fd;font-size:11px;font-weight:600;margin-left:8px;
		}
		.qtale-card{
			background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:22px 26px;margin:0 0 18px;
			box-shadow:0 1px 3px rgba(0,0,0,.04);
		}
		.qtale-card h2{
			margin:0 0 16px;padding:0 0 12px;border-bottom:1px solid #f1f5f9;
			font-size:16px;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:8px;
		}
		.qtale-card h2 .qt-dot{
			width:8px;height:8px;border-radius:50%;
			background:linear-gradient(135deg,#E85124,#ff8a5c);box-shadow:0 0 8px rgba(232,81,36,.4);
		}
		.qtale-wrap .form-table th{font-weight:600;color:#334155;padding-left:0;}
		.qtale-wrap .form-table td{padding:10px 0;}
		.qtale-wrap input[type=text],
		.qtale-wrap input[type=url],
		.qtale-wrap input[type=password],
		.qtale-wrap input[type=number]{
			border:1px solid #cbd5e1;border-radius:8px;padding:7px 11px;
			transition:border-color .15s,box-shadow .15s;
		}
		.qtale-wrap select{
			-webkit-appearance:none;-moz-appearance:none;appearance:none;
			border:1px solid #cbd5e1;border-radius:8px;
			padding:8px 36px 8px 12px;
			background-color:#fff;
			background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8' fill='none'><path d='M1 1l5 5 5-5' stroke='%23E85124' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/></svg>");
			background-repeat:no-repeat;
			background-position:right 12px center;
			background-size:11px;
			font-family:-apple-system,BlinkMacSystemFont,'Inter',sans-serif;font-size:13px;font-weight:500;color:#1e293b;
			cursor:pointer;min-width:240px;
			transition:border-color .15s, box-shadow .15s, background-color .15s;
		}
		.qtale-wrap select:hover{
			border-color:#E85124;background-color:#fff7f0;
		}
		.qtale-wrap input:focus,.qtale-wrap select:focus{
			border-color:#E85124;box-shadow:0 0 0 3px rgba(232,81,36,.12);outline:none;
			background-color:#fff;
		}
		.qtale-wrap .button-primary{
			background:linear-gradient(135deg,#E85124,#ff6b3d) !important;
			border-color:#c63d12 !important;text-shadow:none !important;
			box-shadow:0 2px 10px rgba(232,81,36,.25) !important;
			transition:transform .12s,box-shadow .12s;
		}
		.qtale-wrap .button-primary:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(232,81,36,.35) !important;}
		#qtale-test-result{margin-left:12px;font-weight:600;}
		.qtale-shortcode-table{width:100%;border-collapse:collapse;}
		.qtale-shortcode-table th,.qtale-shortcode-table td{padding:8px 12px;text-align:left;border-bottom:1px solid #f1f5f9;font-size:13px;}
		.qtale-shortcode-table th{background:#f8fafc;color:#475569;font-weight:700;}
		.qtale-shortcode-table code{background:#fef3ec;color:#c63d12;padding:2px 6px;border-radius:4px;font-size:12px;}
		.qtale-checkboxes label{display:inline-flex;align-items:center;gap:6px;padding:5px 11px;margin:0 6px 6px 0;border:1px solid #e2e8f0;border-radius:99px;cursor:pointer;background:#f8fafc;font-size:13px;}
		.qtale-checkboxes input{margin:0;}
		.qtale-checkboxes label:hover{border-color:#E85124;background:#fff7f0;}
		/* 4-column padding grid (top/right/bottom/left) */
		.qtale-margin-grid{display:grid;grid-template-columns:repeat(4,minmax(70px,1fr));gap:10px;max-width:380px;}
		.qtale-margin-grid label{display:flex;flex-direction:column;gap:4px;font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.08em;}
		.qtale-margin-grid input{width:100%;text-align:center;}
		/* Dark mode — auto-switch when browser/OS prefers dark */
		@media (prefers-color-scheme: dark){
			.qtale-wrap .qtale-card{background:#0f172a;border-color:#1e293b;color:#cbd5e1;}
			.qtale-wrap .qtale-card h2{color:#f1f5f9;border-bottom-color:#1e293b;}
			.qtale-wrap .form-table th{color:#94a3b8;}
			.qtale-wrap .form-table td{color:#cbd5e1;}
			.qtale-wrap input[type=text],
			.qtale-wrap input[type=url],
			.qtale-wrap input[type=password],
			.qtale-wrap input[type=number]{background:#0b1220;border-color:#1e293b;color:#e2e8f0;}
			.qtale-wrap select{
				background-color:#0b1220;border-color:#1e293b;color:#e2e8f0;
			}
			.qtale-wrap select:hover{background-color:#1f1410;border-color:#ff8a5c;}
			.qtale-wrap select:focus{background-color:#0b1220;}
			.qtale-wrap .description{color:#94a3b8;}
			.qtale-checkboxes label{background:#0b1220;border-color:#1e293b;color:#cbd5e1;}
			.qtale-checkboxes label:hover{background:#1f1410;border-color:#E85124;}
			.qtale-shortcode-table th{background:#0b1220;color:#94a3b8;}
			.qtale-shortcode-table td{color:#cbd5e1;}
			.qtale-shortcode-table th,.qtale-shortcode-table td{border-bottom-color:#1e293b;}
			.qtale-shortcode-table code{background:rgba(232,81,36,.12);color:#ff8a5c;}
			.qtale-margin-grid label{color:#94a3b8;}
		}
CSS;
	}

	public static function menu() {
		add_options_page(
			__( 'Q-Tale TTS', 'qtale-tts' ),
			__( 'Q-Tale TTS', 'qtale-tts' ),
			'manage_options',
			'qtale-tts',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function init_fields() {
		register_setting(
			'qtale_tts',
			Qtale_TTS::OPTION_KEY,
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);

		// Section 1: Tilkobling (connection)
		add_settings_section( 'qtale_tts_main', '', '__return_false', 'qtale-tts' );
		$conn_fields = array(
			'api_key'        => __( 'API-nøkkel', 'qtale-tts' ),
			'default_voice'  => __( 'Standard stemme-ID', 'qtale-tts' ),
			'cache_ttl_days' => __( 'Cache-levetid (dager)', 'qtale-tts' ),
			'api_base'       => __( 'API URL (avansert)', 'qtale-tts' ),
			'cdn_base'       => __( 'CDN URL (avansert)', 'qtale-tts' ),
		);
		foreach ( $conn_fields as $key => $label ) {
			add_settings_field( $key, $label, array( __CLASS__, 'render_field' ), 'qtale-tts', 'qtale_tts_main', array( 'key' => $key ) );
		}

		// Section 2: Spiller (player look)
		add_settings_section( 'qtale_tts_player', '', '__return_false', 'qtale-tts' );
		$player_fields = array(
			'source_language'         => __( 'Kildespråk', 'qtale-tts' ),
			'default_design_public_id'=> __( 'Egen player (fra Studio)', 'qtale-tts' ),
			'default_design'          => __( 'Standard player designs', 'qtale-tts' ),
			'default_theme'           => __( 'Tema', 'qtale-tts' ),
		);
		foreach ( $player_fields as $key => $label ) {
			add_settings_field( $key, $label, array( __CLASS__, 'render_field' ), 'qtale-tts', 'qtale_tts_player', array( 'key' => $key ) );
		}

		// Section 3: Atferd (behaviour)
		add_settings_section( 'qtale_tts_behavior', '', '__return_false', 'qtale-tts' );
		$beh_fields = array(
			'auto_generate'    => __( 'Auto-generer ved publisering', 'qtale-tts' ),
			'placement'        => __( 'Plassering', 'qtale-tts' ),
			'placement_margin' => __( 'Player-marg (top/right/bottom/left)', 'qtale-tts' ),
			'post_types'       => __( 'Innholdstyper', 'qtale-tts' ),
			'min_chars_auto'   => __( 'Min. tegn for auto-generering', 'qtale-tts' ),
			'max_chars_auto'   => __( 'Maks. tegn per auto-generering', 'qtale-tts' ),
			'daily_limit'      => __( 'Daglig grense (0 = ubegrenset)', 'qtale-tts' ),
			'subscriber_only'  => __( 'Kun for innlogga brukere', 'qtale-tts' ),
		);
		foreach ( $beh_fields as $key => $label ) {
			add_settings_field( $key, $label, array( __CLASS__, 'render_field' ), 'qtale-tts', 'qtale_tts_behavior', array( 'key' => $key ) );
		}
	}

	public static function sanitize( $input ) {
		$out = Qtale_TTS::default_settings();
		if ( ! is_array( $input ) ) {
			return $out;
		}
		$out['api_key']        = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
		$out['api_base']       = isset( $input['api_base'] ) ? esc_url_raw( trim( $input['api_base'] ) ) : $out['api_base'];
		$out['cdn_base']       = isset( $input['cdn_base'] ) ? esc_url_raw( trim( $input['cdn_base'] ) ) : $out['cdn_base'];
		$out['default_voice']  = isset( $input['default_voice'] ) ? sanitize_text_field( $input['default_voice'] ) : '';
		$out['default_design'] = isset( $input['default_design'] ) && array_key_exists( $input['default_design'], Qtale_TTS::designs() )
			? $input['default_design']
			: $out['default_design'];
		$out['default_design_public_id'] = isset( $input['default_design_public_id'] )
			? sanitize_text_field( $input['default_design_public_id'] )
			: '';
		// Preserve fields that are set OUTSIDE the settings form (AJAX-refreshed, tier-gated, etc.)
		// CRITICAL: WP's register_setting auto-filters update_option through sanitize_callback,
		// so AJAX updates ALSO pass through here. Must accept fields from BOTH $input (AJAX writes)
		// AND $existing (preserving across form-saves).
		$existing = get_option( Qtale_TTS::OPTION_KEY, array() );
		if ( ! is_array( $existing ) ) $existing = array();
		// Helper: pick value from input first, then existing, then default
		$keep = function( $key, $default = '' ) use ( $input, $existing ) {
			if ( isset( $input[ $key ] ) )    return $input[ $key ];
			if ( isset( $existing[ $key ] ) ) return $existing[ $key ];
			return $default;
		};
		$out['allowed_designs']           = $keep( 'allowed_designs', array() );
		$out['tier_name']                 = $keep( 'tier_name', '' );
		// player_designs lives in OPTION_KEY_DESIGNS — sanitize() shouldn't touch it
		// (kept here just so any stray $input value doesn't get reflected back as side-effect)
		$out['app_base']                  = $keep( 'app_base', 'https://app.qtale.no' );
		// Ensure arrays stay arrays even if WP serialized roundtrips them
		if ( ! is_array( $out['player_designs'] ) )   $out['player_designs'] = array();
		if ( ! is_array( $out['allowed_designs'] ) ) $out['allowed_designs'] = array();
		$out['default_theme']  = isset( $input['default_theme'] ) && array_key_exists( $input['default_theme'], Qtale_TTS::themes() )
			? $input['default_theme']
			: $out['default_theme'];
		// Source language — tom = auto fra WP-locale; ellers en av 15 supported codes
		$valid_src = array( '', 'no','sv','da','fi','en','de','fr','es','it','nl','pl','pt','se','is','fo' );
		$out['source_language'] = isset( $input['source_language'] ) && in_array( $input['source_language'], $valid_src, true )
			? $input['source_language']
			: '';
		$out['cache_ttl_days'] = isset( $input['cache_ttl_days'] ) ? max( 1, min( 365, (int) $input['cache_ttl_days'] ) ) : $out['cache_ttl_days'];

		// Behaviour
		$out['auto_generate']   = ! empty( $input['auto_generate'] ) ? 1 : 0;
		$placements             = Qtale_TTS::placements();
		$out['placement']       = isset( $input['placement'] ) && array_key_exists( $input['placement'], $placements )
			? $input['placement']
			: 'manual';
		$valid_pts              = array_keys( Qtale_TTS::post_type_choices() );
		$out['post_types']      = isset( $input['post_types'] ) && is_array( $input['post_types'] )
			? array_values( array_intersect( $input['post_types'], $valid_pts ) )
			: array( 'post' );
		$out['min_chars_auto']  = isset( $input['min_chars_auto'] ) ? max( 0, min( 50000, (int) $input['min_chars_auto'] ) ) : 200;
		$out['max_chars_auto']  = isset( $input['max_chars_auto'] ) ? max( 100, min( 50000, (int) $input['max_chars_auto'] ) ) : 3000;
		$out['daily_limit']     = isset( $input['daily_limit'] ) ? max( 0, (int) $input['daily_limit'] ) : 0;
		$out['subscriber_only'] = ! empty( $input['subscriber_only'] ) ? 1 : 0;
		// Placement margins (px, 0-200)
		$out['placement_margin_top']    = isset( $input['placement_margin_top']    ) ? max( 0, min( 200, (int) $input['placement_margin_top']    ) ) : 12;
		$out['placement_margin_right']  = isset( $input['placement_margin_right']  ) ? max( 0, min( 200, (int) $input['placement_margin_right']  ) ) : 0;
		$out['placement_margin_bottom'] = isset( $input['placement_margin_bottom'] ) ? max( 0, min( 200, (int) $input['placement_margin_bottom'] ) ) : 18;
		$out['placement_margin_left']   = isset( $input['placement_margin_left']   ) ? max( 0, min( 200, (int) $input['placement_margin_left']   ) ) : 0;
		// Preserve tier_key + company_name (set by ajax_test_key)
		$out['tier_key']     = isset( $existing['tier_key'] ) ? $existing['tier_key'] : '';
		$out['company_name'] = isset( $existing['company_name'] ) ? $existing['company_name'] : '';
		// v2.4.3 Dual-Player Addon
		$out['dual_enabled']  = ! empty( $input['dual_enabled'] ) ? 1 : 0;
		$out['dual_slot1_id'] = isset( $input['dual_slot1_id'] ) ? sanitize_text_field( $input['dual_slot1_id'] ) : '';
		$out['dual_slot2_id'] = isset( $input['dual_slot2_id'] ) ? sanitize_text_field( $input['dual_slot2_id'] ) : '';
		$valid_layouts        = array( 'vertical', 'horizontal' );
		$out['dual_layout']   = isset( $input['dual_layout'] ) && in_array( $input['dual_layout'], $valid_layouts, true ) ? $input['dual_layout'] : 'vertical';
		$out['dual_gap']      = isset( $input['dual_gap'] ) ? max( 0, min( 64, (int) $input['dual_gap'] ) ) : 8;
		// Read-only addon-flag — settes av ajax_test_key / ajax_refresh_designs fra /api/v1/me.
		// v2.6.4 CRITICAL: må sjekke $input FØR $existing. update_option() går alltid gjennom
		// denne sanitize-callbacken (register_setting auto-filter). Tidligere leste vi kun
		// $existing → AJAX-handleren satt $saved['translation_modal_addon']=1, men sanitize
		// overskrev tilbake til existing=0 og update_option lagret 0. Bug siden v2.4.3 — det
		// er HVORFOR Translation Modal aldri ble synlig på iNyheter selv etter Test nøkkel.
		$pick_flag = function( $key ) use ( $input, $existing ) {
			if ( array_key_exists( $key, $input ) )    return ! empty( $input[ $key ] ) ? 1 : 0;
			if ( array_key_exists( $key, $existing ) ) return ! empty( $existing[ $key ] ) ? 1 : 0;
			return 0;
		};
		$out['dual_player_addon']       = $pick_flag( 'dual_player_addon' );
		$out['translation_modal_addon'] = $pick_flag( 'translation_modal_addon' );
		$out['utility_pack_addon']      = $pick_flag( 'utility_pack_addon' );
		return $out;
	}

	public static function render_field( $args ) {
		$key      = $args['key'];
		$settings = Qtale_TTS::settings();
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		$name     = sprintf( '%s[%s]', Qtale_TTS::OPTION_KEY, $key );
		switch ( $key ) {
			case 'api_key':
				printf(
					'<input type="password" id="qtale-api-key" name="%1$s" value="%2$s" class="regular-text" autocomplete="new-password" /> ' .
					'<button type="button" class="button" id="qtale-test-key">%3$s</button> <span id="qtale-test-result"></span>' .
					'<p class="description">%4$s</p>',
					esc_attr( $name ),
					esc_attr( $value ),
					esc_html__( 'Test nøkkel', 'qtale-tts' ),
					esc_html__( 'Finn nøkkelen din på app.qtale.no → API-keys.', 'qtale-tts' )
				);
				break;
			case 'api_base':
			case 'cdn_base':
				printf(
					'<input type="url" name="%1$s" value="%2$s" class="regular-text" />',
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;
			case 'default_design_public_id':
				$designs = isset( $settings['player_designs'] ) && is_array( $settings['player_designs'] )
					? $settings['player_designs'] : array();
				if ( empty( $designs ) ) {
					printf(
						'<em style="color:#64748b;">%s</em><p class="description">%s</p>',
						esc_html__( 'Ingen player-designs hentet ennå.', 'qtale-tts' ),
						esc_html__( 'Klikk «Refresh designs» nederst → designs du har lagret på app.qtale.no/player-designer dukker opp her.', 'qtale-tts' )
					);
				} else {
					echo '<select name="' . esc_attr( $name ) . '" style="min-width:300px;">';
					printf( '<option value="" %s>— %s —</option>',
						selected( $value, '', false ),
						esc_html__( 'Ikke valgt (bruk legacy [qtale])', 'qtale-tts' )
					);
					foreach ( $designs as $d ) {
						printf(
							'<option value="%1$s" %3$s>%2$s</option>',
							esc_attr( $d['public_id'] ),
							esc_html( sprintf( '%s — %s', $d['name'], $d['public_id'] ) ),
							selected( $value, $d['public_id'], false )
						);
					}
					echo '</select>';
					printf(
						'<p class="description">%s</p>',
						esc_html__( 'Velg en av dine Studio-designs — overstyrer «Spiller-design (legacy)» og bruker det nye embed-systemet med flerspråk-velger.', 'qtale-tts' )
					);
				}
				break;
			case 'default_design':
				echo '<select name="' . esc_attr( $name ) . '">';
				$allowed = Qtale_TTS::allowed_designs_map();
				foreach ( $allowed as $slug => $label ) {
					printf(
						'<option value="%1$s" %3$s>%2$s</option>',
						esc_attr( $slug ),
						esc_html( $label ),
						selected( $value, $slug, false )
					);
				}
				echo '</select>';
				if ( count( $allowed ) < count( Qtale_TTS::designs() ) ) {
					$tier = ( $settings['tier_name'] ? ' (' . $settings['tier_name'] . ')' : '' );
					printf(
						'<p class="description">%s</p>',
						esc_html( sprintf(
							/* translators: %s: tier name. */
							__( 'Disse designene er tilgjengelige i din pakke%s. Oppgrader for fler.', 'qtale-tts' ),
							$tier
						) )
					);
				}
				break;
			case 'default_theme':
				echo '<select name="' . esc_attr( $name ) . '">';
				foreach ( Qtale_TTS::themes() as $slug => $label ) {
					printf(
						'<option value="%1$s" %3$s>%2$s</option>',
						esc_attr( $slug ),
						esc_html( $label ),
						selected( $value, $slug, false )
					);
				}
				echo '</select>';
				break;
			case 'source_language':
				$auto = Qtale_TTS::auto_source_from_wp_locale();
				$langs = Qtale_TTS::supported_languages();
				echo '<select name="' . esc_attr( $name ) . '" style="min-width:240px;">';
				printf(
					'<option value="" %s>%s</option>',
					selected( $value, '', false ),
					esc_html( sprintf(
						/* translators: %s: detected locale */
						__( 'Auto fra WordPress (%s)', 'qtale-tts' ),
						$auto ? strtoupper( $auto ) : '—'
					) )
				);
				foreach ( $langs as $code => $label ) {
					printf(
						'<option value="%1$s" %3$s>%2$s</option>',
						esc_attr( $code ),
						esc_html( $label ),
						selected( $value, $code, false )
					);
				}
				echo '</select>';
				printf(
					'<p class="description">%s</p>',
					esc_html__( 'Språk på artiklene dine. Visitor-oversettelser bruker dette som start­punkt. Tomt = bruk WordPress-locale automatisk.', 'qtale-tts' )
				);
				break;
			case 'cache_ttl_days':
				printf(
					'<input type="number" min="1" max="365" name="%1$s" value="%2$d" class="small-text" /> ' .
					'<span class="description">%3$s</span>',
					esc_attr( $name ),
					(int) $value,
					esc_html__( 'Hvor lenge audio-URL-er bufres lokalt.', 'qtale-tts' )
				);
				break;
			case 'auto_generate':
			case 'subscriber_only':
				printf(
					'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
					esc_attr( $name ),
					checked( $value, 1, false ),
					$key === 'auto_generate'
						? esc_html__( 'Generer lyd automatisk når et innlegg publiseres', 'qtale-tts' )
						: esc_html__( 'Bare innlogga brukere kan høre lyden', 'qtale-tts' )
				);
				break;
			case 'placement':
				echo '<select name="' . esc_attr( $name ) . '">';
				foreach ( Qtale_TTS::placements() as $slug => $label ) {
					printf(
						'<option value="%1$s" %3$s>%2$s</option>',
						esc_attr( $slug ),
						esc_html( $label ),
						selected( $value, $slug, false )
					);
				}
				echo '</select>';
				break;
			case 'post_types':
				echo '<div class="qtale-checkboxes">';
				$picked = is_array( $value ) ? $value : array();
				foreach ( Qtale_TTS::post_type_choices() as $slug => $label ) {
					printf(
						'<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s</label>',
						esc_attr( $name ),
						esc_attr( $slug ),
						checked( in_array( $slug, $picked, true ), true, false ),
						esc_html( $label )
					);
				}
				echo '</div>';
				break;
			case 'min_chars_auto':
			case 'max_chars_auto':
			case 'daily_limit':
				printf(
					'<input type="number" min="0" name="%1$s" value="%2$d" class="small-text" />',
					esc_attr( $name ),
					(int) $value
				);
				break;
			case 'placement_margin':
				$mt = (int) ( $settings['placement_margin_top']    ?? 12 );
				$mr = (int) ( $settings['placement_margin_right']  ?? 0  );
				$mb = (int) ( $settings['placement_margin_bottom'] ?? 18 );
				$ml = (int) ( $settings['placement_margin_left']   ?? 0  );
				$opt = Qtale_TTS::OPTION_KEY;
				printf(
					'<div class="qtale-margin-grid">'
					. '<label>top<input type="number" min="0" max="200" name="%1$s[placement_margin_top]" value="%2$d" /></label>'
					. '<label>right<input type="number" min="0" max="200" name="%1$s[placement_margin_right]" value="%3$d" /></label>'
					. '<label>bottom<input type="number" min="0" max="200" name="%1$s[placement_margin_bottom]" value="%4$d" /></label>'
					. '<label>left<input type="number" min="0" max="200" name="%1$s[placement_margin_left]" value="%5$d" /></label>'
					. '</div>'
					. '<p class="description">%6$s</p>',
					esc_attr( $opt ), $mt, $mr, $mb, $ml,
					esc_html__( 'Marg rundt auto-injisert spiller (px). Gjelder kun ved Auto-plassering over/under.', 'qtale-tts' )
				);
				break;
			default:
				printf(
					'<input type="text" name="%1$s" value="%2$s" class="regular-text" />',
					esc_attr( $name ),
					esc_attr( $value )
				);
		}
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = Qtale_TTS::settings();
		$tier     = $settings['tier_name'];
		?>
		<div class="wrap qtale-wrap">
			<div class="qtale-hero">
				<span class="qt-badge"><?php esc_html_e( 'Q-Tale TTS', 'qtale-tts' ); ?></span>
				<h1>
					<?php esc_html_e( 'Profesjonell talesyntese for WordPress', 'qtale-tts' ); ?>
					<?php if ( $tier ) : ?>
						<span class="qtale-tier-pill"><?php echo esc_html( $tier ); ?></span>
					<?php endif; ?>
				</h1>
				<p>
					<?php esc_html_e( 'Norsk premium AI-tale + 25 språk. Bruk shortcode ', 'qtale-tts' ); ?>
					<code style="background:rgba(255,255,255,.12);padding:2px 8px;border-radius:6px;color:#ff8a5c;">[qtale]Tekst her[/qtale]</code>
					<?php esc_html_e( ' eller la pluginen generere automatisk når du publiserer.', 'qtale-tts' ); ?>
				</p>
			</div>

			<form action="options.php" method="post">
				<?php settings_fields( 'qtale_tts' ); ?>

				<div class="qtale-card">
					<h2><span class="qt-dot"></span><?php esc_html_e( 'Tilkobling', 'qtale-tts' ); ?></h2>
					<?php do_settings_fields( 'qtale-tts', 'qtale_tts_main' ); ?>
				</div>

				<?php // v2.6.3 — synlig addon-status. Viser hvilke premium-addons /api/v1/me
				// rapporterer som aktive. Speiler addons-feltet i Test-nøkkel-respons så admin
				// kan verifisere at addon-aktivering (Stripe-kjøp eller pilot-allowlist) har
				// propagert til pluginen. Klikk «Refresh» eller «Test nøkkel» for å re-syncen.
				$tx_active   = ! empty( $settings['translation_modal_addon'] );
				$util_active = ! empty( $settings['utility_pack_addon'] );
				$dual_active = ! empty( $settings['dual_player_addon'] );
				?>
				<div class="qtale-card" style="background:linear-gradient(135deg, rgba(232,81,36,.025), rgba(15,15,26,.02));">
					<h2 style="display:flex;align-items:center;gap:10px;">
						<span class="qt-dot"></span>
						<?php esc_html_e( 'Aktive addons', 'qtale-tts' ); ?>
					</h2>
					<div style="display:flex;flex-wrap:wrap;gap:10px;margin:0;">
						<?php
						$addon_pill = function( $label, $active ) {
							$bg = $active ? 'linear-gradient(135deg,rgba(16,185,129,.10),rgba(16,185,129,.04))' : 'rgba(148,163,184,.08)';
							$bd = $active ? 'rgba(16,185,129,.45)' : 'rgba(148,163,184,.35)';
							$col = $active ? '#047857' : '#64748b';
							$dot_bg = $active ? '#10b981' : '#94a3b8';
							$state = $active ? __( 'Aktiv', 'qtale-tts' ) : __( 'Inaktiv', 'qtale-tts' );
							printf(
								'<div style="display:inline-flex;align-items:center;gap:10px;padding:8px 14px;background:%s;border:1px solid %s;border-radius:99px;font-size:13px;color:%s;">'
									. '<span style="display:inline-block;width:8px;height:8px;border-radius:50%%;background:%s;%s"></span>'
									. '<strong style="font-weight:700;">%s</strong>'
									. '<span style="color:#94a3b8;font-size:11px;letter-spacing:.04em;text-transform:uppercase;">%s</span>'
									. '</div>',
								esc_attr( $bg ), esc_attr( $bd ), esc_attr( $col ),
								esc_attr( $dot_bg ),
								$active ? 'box-shadow:0 0 0 3px rgba(16,185,129,.18);animation:qt-pulse 2.4s ease-in-out infinite;' : '',
								esc_html( $label ),
								esc_html( $state )
							);
						};
						$addon_pill( __( 'Q-Text', 'qtale-tts' ), $tx_active );
						$addon_pill( __( 'Verktøy-pakke',     'qtale-tts' ), $util_active );
						$addon_pill( __( 'Dual Player',        'qtale-tts' ), $dual_active );
						?>
					</div>
					<p style="font-size:12px;color:#64748b;margin:14px 0 0;">
						<?php esc_html_e( 'Statusen leses fra /api/v1/me ved klikk på «Test nøkkel» eller «↻ Refresh». Hvis et addon ble nylig aktivert på serveren men vises som inaktiv her, klikk en av knappene for å sync.', 'qtale-tts' ); ?>
					</p>
					<style>
						@keyframes qt-pulse {
							0%, 100% { box-shadow: 0 0 0 3px rgba(16,185,129,.18); }
							50%      { box-shadow: 0 0 0 5px rgba(16,185,129,.06); }
						}
					</style>
				</div>

				<?php
				$saved_designs = isset( $settings['player_designs'] ) && is_array( $settings['player_designs'] ) ? $settings['player_designs'] : array();
				$designs_fetched_at = isset( $settings['player_designs_fetched_at'] ) ? $settings['player_designs_fetched_at'] : '';
				$using_egen = ! empty( $settings['default_design_public_id'] );
				?>
				<div class="qtale-card">
					<h2><span class="qt-dot"></span><?php esc_html_e( 'Spiller', 'qtale-tts' ); ?></h2>

					<div style="display:flex;gap:0;padding:3px;background:#f1f5f9;border-radius:10px;margin:0 0 18px;border:1px solid #e2e8f0;">
						<label style="flex:1;text-align:center;padding:10px 14px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:700;transition:all .15s;<?php echo $using_egen ? 'background:#fff;color:#E85124;box-shadow:0 1px 3px rgba(0,0,0,.08);' : 'color:#64748b;'; ?>">
							<input type="radio" name="qtale_player_source" value="egen" <?php checked( $using_egen ); ?> style="display:none;">
							⚡ <?php esc_html_e( 'Egen player (fra Studio)', 'qtale-tts' ); ?>
						</label>
						<label style="flex:1;text-align:center;padding:10px 14px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:700;transition:all .15s;<?php echo ! $using_egen ? 'background:#fff;color:#E85124;box-shadow:0 1px 3px rgba(0,0,0,.08);' : 'color:#64748b;'; ?>">
							<input type="radio" name="qtale_player_source" value="standard" <?php checked( ! $using_egen ); ?> style="display:none;">
							🎨 <?php esc_html_e( 'Standard player', 'qtale-tts' ); ?>
						</label>
					</div>

					<div id="qtale-egen-section" style="<?php echo $using_egen ? '' : 'display:none;'; ?>">
						<div style="background:#fff7f0;border:1px solid #fed7aa;border-radius:8px;padding:14px 16px;margin:0 0 16px;">
							<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
								<strong style="font-size:13px;color:#9a3412;"><?php esc_html_e( 'Egne Player-designs', 'qtale-tts' ); ?></strong>
								<button type="button" class="button button-secondary" id="qtale-refresh-designs" style="margin:0;">
									↻ <?php esc_html_e( 'Refresh', 'qtale-tts' ); ?>
								</button>
							</div>
							<?php if ( $designs_fetched_at ) : ?>
								<div style="font-size:11px;color:#9a3412;opacity:.7;margin-bottom:8px;">
									<?php
									/* translators: %s: timestamp */
									echo esc_html( sprintf( __( 'Sist hentet: %s UTC', 'qtale-tts' ), $designs_fetched_at ) );
									?>
								</div>
							<?php endif; ?>
							<div id="qtale-refresh-status" style="font-size:12px;font-weight:700;margin-bottom:8px;"></div>
							<div id="qtale-designs-list">
								<?php if ( empty( $saved_designs ) ) : ?>
									<div style="padding:14px;background:#fff;border:1px dashed #fed7aa;border-radius:6px;font-size:12px;color:#9a3412;text-align:center;">
										<?php esc_html_e( 'Ingen designs hentet ennå. Klikk Refresh for å hente fra app.qtale.no/player-designer.', 'qtale-tts' ); ?>
									</div>
								<?php else : ?>
									<table style="width:100%;font-size:12px;border-collapse:collapse;">
										<thead>
											<tr style="background:#fff;">
												<th style="text-align:left;padding:6px 9px;border-bottom:1px solid #fed7aa;"><?php esc_html_e( 'Navn', 'qtale-tts' ); ?></th>
												<th style="text-align:left;padding:6px 9px;border-bottom:1px solid #fed7aa;"><?php esc_html_e( 'Shortcode', 'qtale-tts' ); ?></th>
												<th style="text-align:left;padding:6px 9px;border-bottom:1px solid #fed7aa;"><?php esc_html_e( 'Sist endret', 'qtale-tts' ); ?></th>
											</tr>
										</thead>
										<tbody>
										<?php foreach ( $saved_designs as $d ) : ?>
											<tr>
												<td style="padding:6px 9px;border-bottom:1px solid #ffedd5;"><strong><?php echo esc_html( $d['name'] ); ?></strong></td>
												<td style="padding:6px 9px;border-bottom:1px solid #ffedd5;"><code data-copy="<?php echo esc_attr( $d['shortcode'] ); ?>" style="background:#0d1117;color:#86efac;padding:3px 7px;border-radius:4px;font-size:10px;cursor:pointer;" title="<?php esc_attr_e( 'Klikk for å kopiere', 'qtale-tts' ); ?>"><?php echo esc_html( $d['shortcode'] ); ?></code></td>
												<td style="padding:6px 9px;border-bottom:1px solid #ffedd5;color:#9a3412;opacity:.7;font-size:11px;"><?php echo esc_html( $d['updated_at'] ); ?></td>
											</tr>
										<?php endforeach; ?>
										</tbody>
									</table>
								<?php endif; ?>
							</div>
						</div>

						<table class="form-table"><tbody>
							<tr><th><?php esc_html_e( 'Kildespråk', 'qtale-tts' ); ?></th><td><?php self::render_field( array( 'key' => 'source_language' ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Velg din player', 'qtale-tts' ); ?></th><td><?php self::render_field( array( 'key' => 'default_design_public_id' ) ); ?></td></tr>
						</tbody></table>
					</div>

					<div id="qtale-standard-section" style="<?php echo ! $using_egen ? '' : 'display:none;'; ?>">
						<table class="form-table"><tbody>
							<tr><th><?php esc_html_e( 'Kildespråk', 'qtale-tts' ); ?></th><td><?php self::render_field( array( 'key' => 'source_language' ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Player-design', 'qtale-tts' ); ?></th><td><?php self::render_field( array( 'key' => 'default_design' ) ); ?></td></tr>
							<tr><th><?php esc_html_e( 'Tema', 'qtale-tts' ); ?></th><td><?php self::render_field( array( 'key' => 'default_theme' ) ); ?></td></tr>
						</tbody></table>
					</div>

					<script>
					(function(){
						const radios = document.querySelectorAll('input[name="qtale_player_source"]');
						const egenSec = document.getElementById('qtale-egen-section');
						const stdSec  = document.getElementById('qtale-standard-section');
						const designSelect = document.querySelector('select[name="qtale_tts_settings[default_design_public_id]"]');
						radios.forEach(r => r.addEventListener('change', () => {
							const useEgen = r.value === 'egen' && r.checked;
							if (useEgen) {
								egenSec.style.display = '';
								stdSec.style.display = 'none';
								// Visual toggle background
								radios.forEach(rr => {
									rr.parentElement.style.background = rr.value === 'egen' ? '#fff' : 'transparent';
									rr.parentElement.style.color      = rr.value === 'egen' ? '#E85124' : '#64748b';
									rr.parentElement.style.boxShadow  = rr.value === 'egen' ? '0 1px 3px rgba(0,0,0,.08)' : 'none';
								});
							} else if (r.value === 'standard' && r.checked) {
								egenSec.style.display = 'none';
								stdSec.style.display = '';
								// Clear default_design_public_id when switching to standard
								if (designSelect) designSelect.value = '';
								radios.forEach(rr => {
									rr.parentElement.style.background = rr.value === 'standard' ? '#fff' : 'transparent';
									rr.parentElement.style.color      = rr.value === 'standard' ? '#E85124' : '#64748b';
									rr.parentElement.style.boxShadow  = rr.value === 'standard' ? '0 1px 3px rgba(0,0,0,.08)' : 'none';
								});
							}
						}));
					})();
					</script>
				</div>

				<div class="qtale-card">
					<h2><span class="qt-dot"></span><?php esc_html_e( 'Atferd & restriksjoner', 'qtale-tts' ); ?></h2>
					<?php do_settings_fields( 'qtale-tts', 'qtale_tts_behavior' ); ?>
				</div>

				<?php // v2.4.3 — Dual-Player Addon: 2 players (1 TTS + 1 utility-only) per artikkel.
				// Addon-gating: kun synlig hvis kunde har dual_player-addon (settes av /api/v1/me).
				$dual_addon_active = ! empty( $settings['dual_player_addon'] );
				if ( $dual_addon_active ) :
					$dual_designs = class_exists( 'Qtale_TTS_Post_Meta' ) ? Qtale_TTS_Post_Meta::fetch_custom_designs() : array();
					$dual_enabled = ! empty( $settings['dual_enabled'] ) ? 1 : 0;
					$dual_slot1   = isset( $settings['dual_slot1_id'] ) ? $settings['dual_slot1_id'] : '';
					$dual_slot2   = isset( $settings['dual_slot2_id'] ) ? $settings['dual_slot2_id'] : '';
					$dual_layout  = isset( $settings['dual_layout'] ) ? $settings['dual_layout'] : 'vertical';
					$dual_gap     = (int) ( isset( $settings['dual_gap'] ) ? $settings['dual_gap'] : 8 ); ?>
				<div class="qtale-card qtale-card-addon">
					<h2 style="display:flex;align-items:center;gap:10px;">
						<span class="qt-dot" style="background:linear-gradient(135deg,#A855F7,#EC4899);"></span>
						<?php esc_html_e( 'Dual Player', 'qtale-tts' ); ?>
						<span style="font-family:'Inter',sans-serif;font-size:10px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;padding:3px 9px;border-radius:99px;background:linear-gradient(135deg,#A855F7,#EC4899);color:#fff;box-shadow:0 2px 8px rgba(168,85,247,.35);">✨ ADDON</span>
					</h2>
					<p style="font-size:13px;color:#475569;margin:0 0 14px;">
						<?php esc_html_e( 'Vis 2 playere på artiklene dine: én TTS-player (med play-knapp) + én utility-only player (kun Print/PDF/Skrift/Del). Validering håndhever at nøyaktig én av de to har play-knapp.', 'qtale-tts' ); ?>
					</p>
					<table class="form-table" role="presentation"><tbody>
						<tr><th><label for="qtale_dual_enabled"><?php esc_html_e( 'Aktiver dual-player', 'qtale-tts' ); ?></label></th>
							<td><label><input type="checkbox" id="qtale_dual_enabled" name="<?php echo esc_attr( Qtale_TTS::OPTION_KEY ); ?>[dual_enabled]" value="1" <?php checked( $dual_enabled, 1 ); ?>>
								<?php esc_html_e( 'Auto-inject begge playerne over/under artikkel-innholdet.', 'qtale-tts' ); ?></label></td></tr>
						<tr><th><label for="qtale_dual_slot1"><?php esc_html_e( 'Slot 1 (player)', 'qtale-tts' ); ?></label></th>
							<td><select id="qtale_dual_slot1" name="<?php echo esc_attr( Qtale_TTS::OPTION_KEY ); ?>[dual_slot1_id]" style="min-width:280px;">
								<option value=""><?php esc_html_e( '— Velg design —', 'qtale-tts' ); ?></option>
								<?php foreach ( $dual_designs as $d ) : $util = ( isset( $d['play_shape'] ) && $d['play_shape'] === 'none' ); ?>
									<option value="<?php echo esc_attr( $d['public_id'] ); ?>" <?php selected( $dual_slot1, $d['public_id'] ); ?>>
										<?php echo esc_html( $d['name'] ); ?> · <?php echo $util ? '🛠️ Utility' : '🎵 TTS'; ?>
									</option>
								<?php endforeach; ?>
							</select></td></tr>
						<tr><th><label for="qtale_dual_slot2"><?php esc_html_e( 'Slot 2 (player)', 'qtale-tts' ); ?></label></th>
							<td><select id="qtale_dual_slot2" name="<?php echo esc_attr( Qtale_TTS::OPTION_KEY ); ?>[dual_slot2_id]" style="min-width:280px;">
								<option value=""><?php esc_html_e( '— Velg design —', 'qtale-tts' ); ?></option>
								<?php foreach ( $dual_designs as $d ) : $util = ( isset( $d['play_shape'] ) && $d['play_shape'] === 'none' ); ?>
									<option value="<?php echo esc_attr( $d['public_id'] ); ?>" <?php selected( $dual_slot2, $d['public_id'] ); ?>>
										<?php echo esc_html( $d['name'] ); ?> · <?php echo $util ? '🛠️ Utility' : '🎵 TTS'; ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description" style="font-size:12px;color:#7c3aed;margin-top:6px;">
								<strong>⚠️ <?php esc_html_e( 'Validering:', 'qtale-tts' ); ?></strong>
								<?php esc_html_e( 'Nøyaktig ÉN må være TTS (med play-knapp), den andre må være Utility-only (play_shape="Ingen" i Designeren). Ugyldig kombinasjon = fallback til single-player.', 'qtale-tts' ); ?>
							</p></td></tr>
						<tr><th><?php esc_html_e( 'Plassering', 'qtale-tts' ); ?></th>
							<td>
								<label style="margin-right:18px;"><input type="radio" name="<?php echo esc_attr( Qtale_TTS::OPTION_KEY ); ?>[dual_layout]" value="vertical" <?php checked( $dual_layout, 'vertical' ); ?>>
									<?php esc_html_e( 'Vertikal (stablet — over hverandre)', 'qtale-tts' ); ?></label>
								<label><input type="radio" name="<?php echo esc_attr( Qtale_TTS::OPTION_KEY ); ?>[dual_layout]" value="horizontal" <?php checked( $dual_layout, 'horizontal' ); ?>>
									<?php esc_html_e( 'Horisontal (etter hverandre — side om side)', 'qtale-tts' ); ?></label>
								<p class="description"><?php esc_html_e( 'Rekkefølge styres av Slot 1 → Slot 2. Bytt slot for å endre rekkefølge.', 'qtale-tts' ); ?></p></td></tr>
						<tr><th><label for="qtale_dual_gap"><?php esc_html_e( 'Mellomrom (gap)', 'qtale-tts' ); ?></label></th>
							<td><input type="number" id="qtale_dual_gap" name="<?php echo esc_attr( Qtale_TTS::OPTION_KEY ); ?>[dual_gap]" value="<?php echo esc_attr( $dual_gap ); ?>" min="0" max="64" step="1" style="width:80px;"> px
								<p class="description"><?php esc_html_e( 'Mellomrom mellom de to playerne (0–64 px).', 'qtale-tts' ); ?></p></td></tr>
					</tbody></table>
				</div>
				<style>
					.qtale-card-addon{
						position:relative;
						background:linear-gradient(135deg, rgba(168,85,247,.04), rgba(236,72,153,.02));
						border:1px solid rgba(168,85,247,.32);
						box-shadow:0 0 16px rgba(168,85,247,.08);
					}
					.qtale-card-addon::before{
						content:'';position:absolute;top:0;left:0;right:0;height:2px;
						background:linear-gradient(90deg,transparent 0,#A855F7 30%,#EC4899 50%,#A855F7 70%,transparent 100%);
					}
				</style>
				<?php endif; // /dual_addon_active ?>

				<?php submit_button( __( 'Lagre innstillinger', 'qtale-tts' ) ); ?>
			</form>

			<?php
			// ── Backfill card — only shown if a Studio-design is selected (multi-lang flow)
			$has_design = ! empty( $settings['default_design_public_id'] );
				// v2.3.8: hide the backfill batch-generator per build (define QTALE_TTS_HIDE_BACKFILL).
				// iNyheter build sets it: giant archive + many editors = an accidental "Generer batch"
				// would be a major Azure-cost risk. The queue-flush button stays in both builds.
				$backfill_on = ! ( defined( 'QTALE_TTS_HIDE_BACKFILL' ) && QTALE_TTS_HIDE_BACKFILL );
			?>
			<div class="qtale-card" id="qtale-backfill-card">
				<h2><span class="qt-dot"></span><?php echo esc_html( $backfill_on ? __( 'Backfill audio for eldre innlegg', 'qtale-tts' ) : __( 'Lyd-generering', 'qtale-tts' ) ); ?></h2>
				<?php // v2.3.7: manual queue-flush — always available, independent of design selection. ?>
				<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:0 0 16px;padding:0 0 16px;border-bottom:1px solid #eef2f7;">
					<button type="button" class="button" id="qtale-flush-queue-btn"><?php esc_html_e( 'Stopp & tøm generering-kø', 'qtale-tts' ); ?></button>
					<span style="font-size:12px;color:#64748b;flex:1 1 240px;"><?php esc_html_e( 'Fjerner alle planlagte (kø-lagte) lyd-genereringer — f.eks. rester etter en tidligere backfill. Påvirker ikke nye artikler; de genereres ved publisering.', 'qtale-tts' ); ?></span>
					<span id="qtale-flush-queue-result" style="font-size:13px;font-weight:600;"></span>
				</div>
				<script>
				(function(){
					var btn = document.getElementById('qtale-flush-queue-btn');
					if (!btn) return;
					var out = document.getElementById('qtale-flush-queue-result');
					btn.addEventListener('click', function(){
						btn.disabled = true;
						var orig = btn.textContent;
						btn.textContent = '<?php echo esc_js( __( 'Tømmer …', 'qtale-tts' ) ); ?>';
						if (out) out.textContent = '';
						var fd = new FormData();
						fd.append('action', 'qtale_tts_flush_queue');
						fd.append('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'qtale_tts_flush_queue' ) ); ?>');
						fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd })
							.then(function(r){ return r.json(); })
							.then(function(j){
								btn.disabled = false; btn.textContent = orig;
								if (!out) return;
								if (j && j.success && j.data) { out.style.color = '#0a7f3a'; out.textContent = '✓ ' + j.data.message; }
								else { out.style.color = '#b91c1c'; out.textContent = '✗ ' + ((j && j.data && j.data.message) ? j.data.message : 'Feilet'); }
							})
							.catch(function(e){ btn.disabled = false; btn.textContent = orig; if (out){ out.style.color = '#b91c1c'; out.textContent = '✗ ' + e.message; } });
					});
				})();
				</script>
				<?php if ( $backfill_on ) : ?>
				<?php if ( ! $has_design ) : ?>
					<p style="font-size:13px;color:#64748b;margin:6px 0 0;">
						<?php esc_html_e( 'Velg en player-design over først (under «Spiller» → «Egen player (fra Studio)») før du kan backfill audio.', 'qtale-tts' ); ?>
					</p>
				<?php else : ?>
					<p style="font-size:13px;color:#475569;margin:0 0 16px;">
						<?php esc_html_e( 'Generér audio for alle eldre innlegg — på alle aktive språk i designet ditt. Sjekker hver post: skipper hvis audio allerede er cached.', 'qtale-tts' ); ?>
					</p>
					<div style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;">
						<label style="display:flex;flex-direction:column;gap:4px;">
							<span style="font-size:11px;text-transform:uppercase;font-weight:700;color:#475569;letter-spacing:.06em;"><?php esc_html_e( 'Hvor langt tilbake', 'qtale-tts' ); ?></span>
							<select id="qtale-backfill-days" style="min-width:180px;">
								<option value="7">7 <?php esc_html_e( 'dager', 'qtale-tts' ); ?></option>
								<option value="30" selected>30 <?php esc_html_e( 'dager (siste måned)', 'qtale-tts' ); ?></option>
								<option value="90">90 <?php esc_html_e( 'dager', 'qtale-tts' ); ?></option>
								<option value="365">1 <?php esc_html_e( 'år', 'qtale-tts' ); ?></option>
								<option value="0"><?php esc_html_e( 'Alle publiserte innlegg', 'qtale-tts' ); ?></option>
							</select>
						</label>
						<input type="hidden" id="qtale-backfill-batch" value="5">
						<input type="hidden" id="qtale-backfill-throttle" value="gentle">
						<label style="display:flex;align-items:center;gap:7px;padding:6px 12px;background:#fff7f0;border:1px solid #fed7aa;border-radius:8px;font-size:13px;cursor:pointer;">
							<input type="checkbox" id="qtale-backfill-skip-cached" checked>
							<?php esc_html_e( 'Skip cached', 'qtale-tts' ); ?>
						</label>
						<button type="button" class="button button-primary" id="qtale-backfill-btn">
							<?php esc_html_e( 'Generér batch nå', 'qtale-tts' ); ?>
						</button>
						<button type="button" class="button" id="qtale-backfill-next-btn" style="display:none;">
							<?php esc_html_e( '→ Neste batch', 'qtale-tts' ); ?>
						</button>
					</div>
					<div id="qtale-backfill-status" style="margin-top:16px;display:none;">
						<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;">
							<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
								<strong id="qtale-backfill-headline" style="font-size:14px;color:#0f172a;"></strong>
								<span id="qtale-backfill-eta" style="font-size:12px;color:#64748b;"></span>
							</div>
							<div style="background:#e2e8f0;height:8px;border-radius:99px;overflow:hidden;">
								<div id="qtale-backfill-progress-bar" style="height:100%;width:0%;background:linear-gradient(90deg,#E85124,#ff8a5c);transition:width .5s;"></div>
							</div>
							<div style="margin-top:8px;font-size:12px;color:#475569;display:flex;gap:12px;flex-wrap:wrap;">
								<span><?php esc_html_e( 'Server-kø:', 'qtale-tts' ); ?> <strong id="qtale-backfill-done">0</strong> <?php esc_html_e( 'av', 'qtale-tts' ); ?> <strong id="qtale-backfill-total">0</strong> <?php esc_html_e( 'ferdig', 'qtale-tts' ); ?></span>
								<span id="qtale-backfill-pending" style="color:#94a3b8;"></span>
								<span id="qtale-backfill-state" style="color:#0a7f3a;font-weight:600;"></span>
							</div>
						</div>
					</div>
				<?php endif; ?>
				<?php endif; /* backfill_on */ ?>
			</div>

			<div class="qtale-card">
				<h2><span class="qt-dot"></span><?php esc_html_e( 'Shortcode-attributter', 'qtale-tts' ); ?></h2>
				<table class="qtale-shortcode-table">
					<thead><tr><th><?php esc_html_e( 'Attributt', 'qtale-tts' ); ?></th><th><?php esc_html_e( 'Beskrivelse', 'qtale-tts' ); ?></th><th><?php esc_html_e( 'Standard', 'qtale-tts' ); ?></th></tr></thead>
					<tbody>
						<tr><td><code>design</code></td><td><?php esc_html_e( 'odin, tor, frigg, baldr, idunn, beaivi … (13 totalt — tilgjengelige avhenger av pakke)', 'qtale-tts' ); ?></td><td>odin</td></tr>
						<tr><td><code>voice</code></td><td><?php esc_html_e( 'Voice-ID fra Q-Tale (overstyrer standard)', 'qtale-tts' ); ?></td><td>—</td></tr>
						<tr><td><code>theme</code></td><td>auto / dark / light</td><td>auto</td></tr>
						<tr><td><code>speed</code></td><td>0.5–2.0</td><td>1.0</td></tr>
						<tr><td><code>text</code></td><td><?php esc_html_e( 'Tekst inline (alternativ til shortcode-innhold)', 'qtale-tts' ); ?></td><td>—</td></tr>
					</tbody>
				</table>
				<p style="margin:14px 0 0;font-size:12px;color:#64748b;">
					<?php esc_html_e( 'Trenger du flere designs eller stemmer? Oppgrader pakken på ', 'qtale-tts' ); ?>
					<a href="https://app.qtale.no/" target="_blank" rel="noopener">app.qtale.no</a>.
				</p>
			</div>
			<script>
			(function(){
				// ── Backfill audio for older posts ──
				const bfBtn = document.getElementById('qtale-backfill-btn');
				const bfNextBtn = document.getElementById('qtale-backfill-next-btn');
				let bfCurrentOffset = 0;
				if (bfBtn) {
					const $ = id => document.getElementById(id);
					let pollTimer = null;
					const updateUI = (data) => {
						const qs = data.queue_stats || {};
						const totalJobs = (qs.done || 0) + (qs.queued || 0) + (qs.processing || 0) + (qs.failed || 0);
						const serverDone = qs.done || 0;
						$('qtale-backfill-done').textContent  = serverDone;
						$('qtale-backfill-total').textContent = totalJobs;
						const pct = totalJobs > 0 ? Math.round((serverDone / totalJobs) * 100) : 0;
						$('qtale-backfill-progress-bar').style.width = pct + '%';
						const inQueue = (qs.queued || 0) + (qs.processing || 0);
						$('qtale-backfill-pending').textContent =
							inQueue > 0 ? '⏳ ' + inQueue + ' i kø' : '✓ kø tom';
						if (data.eta_sec && data.eta_sec > 0 && data.pending > 0) {
							const m = Math.floor(data.eta_sec / 60);
							const s = Math.round(data.eta_sec % 60);
							$('qtale-backfill-eta').textContent = '~' + (m > 0 ? m + 'min ' : '') + s + 's igjen';
						} else {
							$('qtale-backfill-eta').textContent = '';
						}
						if (data.done >= data.total && data.total > 0) {
							$('qtale-backfill-state').textContent = '✓ Ferdig';
							$('qtale-backfill-headline').textContent = '<?php echo esc_js( __( 'Backfill ferdig', 'qtale-tts' ) ); ?>';
							if (pollTimer) clearInterval(pollTimer);
						} else if (data.pending > 0) {
							$('qtale-backfill-state').textContent = '⏳ Kjører i bakgrunn …';
						}
					};
					const runBatch = async (offset) => {
						bfBtn.disabled = true;
						bfNextBtn.style.display = 'none';
						bfBtn.textContent = '<?php echo esc_js( __( 'Planlegger …', 'qtale-tts' ) ); ?>';
						$('qtale-backfill-status').style.display = 'block';
						$('qtale-backfill-headline').textContent = '<?php echo esc_js( __( 'Henter innlegg …', 'qtale-tts' ) ); ?>';
						if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
						try {
							const fd = new FormData();
							fd.append('action', 'qtale_tts_backfill');
							fd.append('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'qtale_tts_backfill' ) ); ?>');
							fd.append('days', $('qtale-backfill-days').value);
							fd.append('skip_cached', $('qtale-backfill-skip-cached').checked ? '1' : '0');
							fd.append('throttle_mode', $('qtale-backfill-throttle').value);
							fd.append('batch_size', $('qtale-backfill-batch').value);
							fd.append('batch_offset', offset);
							const r = await fetch(ajaxurl, { method:'POST', body: fd, credentials:'same-origin' });
							const j = await r.json();
							if (!j.success) {
								$('qtale-backfill-headline').textContent = '✗ ' + (j.data && j.data.message ? j.data.message : 'Failed');
								return;
							}
							const d = j.data;
							const batchEnd = Math.min(d.batch_offset + d.batch_size, d.total_matching);
							$('qtale-backfill-headline').textContent =
								'Batch ' + (d.batch_offset + 1) + '–' + batchEnd + ' av ' + d.total_matching +
								' · ' + d.posts + ' innlegg × ' + d.langs + ' språk';
							updateUI({ total: d.scheduled, done: d.already_cached, pending: d.scheduled - d.already_cached, eta_sec: (d.scheduled - d.already_cached) * 4 });
							bfCurrentOffset = d.next_offset;
							// Show "Next batch" button if more posts remain
							if (d.has_more) {
								bfNextBtn.style.display = '';
								bfNextBtn.textContent = '→ <?php echo esc_js( __( 'Neste batch', 'qtale-tts' ) ); ?> (' + (d.next_offset + 1) + '–' + Math.min(d.next_offset + d.batch_size, d.total_matching) + ')';
							}
							pollTimer = setInterval(async () => {
								try {
									const sfd = new FormData();
									sfd.append('action', 'qtale_tts_backfill_status');
									sfd.append('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'qtale_tts_backfill_status' ) ); ?>');
									const sr = await fetch(ajaxurl, { method:'POST', body: sfd, credentials:'same-origin' });
									const sj = await sr.json();
									if (sj.success) updateUI(sj.data);
								} catch(_){}
							}, 4000);
						} catch (e) {
							$('qtale-backfill-headline').textContent = '✗ ' + e.message;
						} finally {
							bfBtn.disabled = false;
							bfBtn.textContent = '<?php echo esc_js( __( 'Generér batch nå', 'qtale-tts' ) ); ?>';
						}
					};
					bfBtn.addEventListener('click', () => {
						if (!confirm('<?php echo esc_js( __( 'Generere lyd for valgt batch? Du kan stoppe når som helst.', 'qtale-tts' ) ); ?>')) return;
						bfCurrentOffset = 0;
						runBatch(0);
					});
					bfNextBtn.addEventListener('click', () => runBatch(bfCurrentOffset));
				}

				// ── Refresh designs button ──
				const rBtn = document.getElementById('qtale-refresh-designs');
				if (rBtn) {
					rBtn.addEventListener('click', async () => {
						const status = document.getElementById('qtale-refresh-status');
						const list   = document.getElementById('qtale-designs-list');
						rBtn.disabled = true;
						status.textContent = '<?php echo esc_js( __( 'Henter…', 'qtale-tts' ) ); ?>';
						status.style.color = '#64748b';
						try {
							const fd = new FormData();
							fd.append('action', 'qtale_tts_refresh_designs');
							fd.append('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'qtale_tts_refresh_designs' ) ); ?>');
							const r = await fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' });
							const j = await r.json();
							if (j.success) {
								status.textContent = '✓ ' + (j.data.diag || j.data.count + ' designs hentet');
								status.style.color = (j.data.verify_count === j.data.count) ? '#0a7f3a' : '#dc2626';
								if (j.data.verify_count === j.data.count) {
									setTimeout(() => location.reload(), 1200);
								}
							} else {
								status.textContent = '✗ ' + (j.data && j.data.message ? j.data.message : 'Failed');
								status.style.color = '#b40000';
							}
						} catch (e) {
							status.textContent = '✗ ' + e.message;
							status.style.color = '#b40000';
						} finally {
							rBtn.disabled = false;
						}
					});
				}
				// ── Click shortcode → copy to clipboard ──
				document.querySelectorAll('[data-copy]').forEach(el => {
					el.addEventListener('click', async () => {
						const txt = el.getAttribute('data-copy');
						try { await navigator.clipboard.writeText(txt); } catch(e){
							const ta = document.createElement('textarea'); ta.value = txt;
							document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
						}
						const orig = el.style.background;
						el.style.background = '#22c55e';
						setTimeout(() => { el.style.background = orig; }, 400);
					});
				});
				// ── Test API key button ──
				const btn = document.getElementById('qtale-test-key');
				if (!btn) return;
				btn.addEventListener('click', async () => {
					const res = document.getElementById('qtale-test-result');
					res.textContent = '<?php echo esc_js( __( 'Tester…', 'qtale-tts' ) ); ?>';
					try {
						const fd = new FormData();
						fd.append('action', 'qtale_tts_test_key');
						fd.append('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'qtale_tts_test_key' ) ); ?>');
						fd.append('key', document.getElementById('qtale-api-key').value);
						const r = await fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' });
						const j = await r.json();
						if (j.success) {
							const d = j.data;
							res.innerHTML = '<?php echo esc_js( __( '✓ Verifisert — ', 'qtale-tts' ) ); ?>'
								+ '<strong>' + (d.tier_label || '') + '</strong>'
								+ ' · ' + d.allowed_count + ' / ' + d.total_designs + '<?php echo esc_js( __( ' designs', 'qtale-tts' ) ); ?>'
								+ ' · ' + (d.voice_count || 0) + '<?php echo esc_js( __( ' stemmer', 'qtale-tts' ) ); ?>'
								+ '<br><small><?php echo esc_js( __( 'Last siden på nytt for å se oppdatert design-utvalg.', 'qtale-tts' ) ); ?></small>';
							res.style.color = '#0a7f3a';
						} else {
							res.textContent = '<?php echo esc_js( __( '✗ ', 'qtale-tts' ) ); ?>' + (j.data && j.data.message ? j.data.message : 'Failed');
							res.style.color = '#b40000';
						}
					} catch (e) {
						res.textContent = '✗ ' + e.message;
						res.style.color = '#b40000';
					}
				});
			})();
			</script>

			<?php
			// ═══════════════════════════════════════════════════════════════════════════
			// CLOUD STORAGE — kunden konfigurerer egen FTP/S3-bakend for cold-tier MP3.
			// Filer eldre enn N dager (norske stemmer) flyttes til kundens egen lagring.
			// API: /api/v1/cold-storage/{config,test} med kundens X-API-Key.
			// ═══════════════════════════════════════════════════════════════════════════
			$cdn = isset( $settings['cdn_base'] ) ? untrailingslashit( $settings['cdn_base'] ) : 'https://qtale.no';
			$api = isset( $settings['api_base'] ) ? untrailingslashit( $settings['api_base'] ) : 'https://api.qtale.no';
			$key = isset( $settings['api_key'] ) ? trim( (string) $settings['api_key'] ) : '';
			?>
			<div class="qtale-card" style="margin-top:24px;">
				<h2><span class="qt-dot" style="background:#60a5fa;"></span><?php esc_html_e( 'Cloud Storage (offload eldre lyd-filer)', 'qtale-tts' ); ?></h2>
				<p style="font-size:13px;color:#475569;margin:0 0 14px 0;">
					<?php esc_html_e( 'Flytt eldre MP3-filer (norske stemmer Finn/Pernille) til din egen FTP eller Amazon S3-bucket. Frigjør Q-Tale-kvoten din, gir deg full kontroll over arkivet. Aktiveres automatisk daglig.', 'qtale-tts' ); ?>
				</p>
				<div id="qtale-cs-status" style="margin-bottom:12px;"></div>

				<table class="form-table" style="margin:0;">
					<tr>
						<th scope="row"><label for="qtale-cs-backend"><?php esc_html_e( 'Backend', 'qtale-tts' ); ?></label></th>
						<td>
							<select id="qtale-cs-backend" style="min-width:180px;">
								<option value=""><?php esc_html_e( '— deaktivert —', 'qtale-tts' ); ?></option>
								<option value="ftp"><?php esc_html_e( 'FTP', 'qtale-tts' ); ?></option>
								<option value="sftp"><?php esc_html_e( 'SFTP (SSH)', 'qtale-tts' ); ?></option>
								<option value="s3"><?php esc_html_e( 'Amazon S3', 'qtale-tts' ); ?></option>
								<option value="wasabi"><?php esc_html_e( 'Wasabi (S3-kompatibel)', 'qtale-tts' ); ?></option>
								<option value="r2"><?php esc_html_e( 'Cloudflare R2 (S3-kompatibel)', 'qtale-tts' ); ?></option>
								<option value="b2"><?php esc_html_e( 'Backblaze B2 (S3-kompatibel)', 'qtale-tts' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="qtale-cs-age"><?php esc_html_e( 'Flytt filer eldre enn (dager)', 'qtale-tts' ); ?></label></th>
						<td>
							<input type="number" id="qtale-cs-age" min="1" max="3650" value="30" style="width:90px;">
							<p class="description"><?php esc_html_e( 'Anbefalt: 30 dager. Nyere filer ligger fortsatt på Q-Tales infrastruktur.', 'qtale-tts' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="qtale-cs-urlbase"><?php esc_html_e( 'Offentlig URL-base', 'qtale-tts' ); ?></label></th>
						<td>
							<input type="url" id="qtale-cs-urlbase" placeholder="https://dittsted.no/wp-content/uploads/qtale-mp3" style="width:520px;">
							<p class="description"><?php esc_html_e( 'Hvor leserne henter filene. F.eks. https://dittsted.no/wp-content/uploads/qtale-mp3 for FTP til WP-uploads-mappen.', 'qtale-tts' ); ?></p>
						</td>
					</tr>
				</table>

				<div id="qtale-cs-form-ftp" class="qtale-cs-form" hidden style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;margin:14px 0;">
					<table class="form-table" style="margin:0;">
						<tr><th><label><?php esc_html_e( 'Server (host)', 'qtale-tts' ); ?></label></th><td><input type="text" data-cs-creds="host" placeholder="ftp.dittsted.no" style="width:340px;"></td></tr>
						<tr><th><label><?php esc_html_e( 'Port', 'qtale-tts' ); ?></label></th><td><input type="number" data-cs-creds="port" value="21" style="width:90px;"></td></tr>
						<tr><th><label><?php esc_html_e( 'Brukernavn', 'qtale-tts' ); ?></label></th><td><input type="text" data-cs-creds="user" placeholder="ftp@dittsted.no" style="width:340px;" autocomplete="off"></td></tr>
						<tr><th><label><?php esc_html_e( 'Passord', 'qtale-tts' ); ?></label></th><td><input type="password" data-cs-creds="pass" placeholder="••••••••" style="width:340px;" autocomplete="new-password"></td></tr>
						<tr><th><label><?php esc_html_e( 'Base-mappe (valgfri)', 'qtale-tts' ); ?></label></th><td><input type="text" data-cs-creds="base_dir" placeholder="" style="width:340px;"><p class="description"><?php esc_html_e( 'Tom = rotmappe. Eks: "audio" eller "wp-content/uploads/qtale-mp3"', 'qtale-tts' ); ?></p></td></tr>
					</table>
				</div>

				<div id="qtale-cs-form-sftp" class="qtale-cs-form" hidden style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;margin:14px 0;">
					<table class="form-table" style="margin:0;">
						<tr><th><label><?php esc_html_e( 'Server (host)', 'qtale-tts' ); ?></label></th><td><input type="text" data-cs-creds="host" placeholder="ssh.dittsted.no" style="width:340px;"></td></tr>
						<tr><th><label><?php esc_html_e( 'Port', 'qtale-tts' ); ?></label></th><td><input type="number" data-cs-creds="port" value="22" style="width:90px;"></td></tr>
						<tr><th><label><?php esc_html_e( 'Brukernavn', 'qtale-tts' ); ?></label></th><td><input type="text" data-cs-creds="user" style="width:340px;" autocomplete="off"></td></tr>
						<tr><th><label><?php esc_html_e( 'Passord', 'qtale-tts' ); ?></label></th><td><input type="password" data-cs-creds="pass" placeholder="•••••••• (tom hvis SSH-nøkkel)" style="width:340px;" autocomplete="new-password"></td></tr>
						<tr><th><label><?php esc_html_e( 'Base-mappe', 'qtale-tts' ); ?></label></th><td><input type="text" data-cs-creds="base_dir" placeholder="/home/user/audio" style="width:340px;"></td></tr>
					</table>
				</div>

				<div id="qtale-cs-form-s3" class="qtale-cs-form" hidden style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;margin:14px 0;">
					<table class="form-table" style="margin:0;">
						<tr><th><label><?php esc_html_e( 'Access Key ID', 'qtale-tts' ); ?></label></th><td><input type="text" data-cs-creds="access_key" placeholder="AKIA…" style="width:340px;" autocomplete="off"></td></tr>
						<tr><th><label><?php esc_html_e( 'Secret Access Key', 'qtale-tts' ); ?></label></th><td><input type="password" data-cs-creds="secret_key" placeholder="••••••••" style="width:340px;" autocomplete="new-password"></td></tr>
						<tr><th><label><?php esc_html_e( 'Bucket-navn', 'qtale-tts' ); ?></label></th><td><input type="text" data-cs-creds="bucket" placeholder="ditt-bucket-navn" style="width:340px;"></td></tr>
						<tr><th><label><?php esc_html_e( 'Region', 'qtale-tts' ); ?></label></th><td>
							<select data-cs-creds="region" style="min-width:240px;" data-cs-default="s3">
								<optgroup label="<?php esc_attr_e( 'EU (GDPR-vennlig)', 'qtale-tts' ); ?>">
									<option value="eu-central-1" selected>Europe (Frankfurt) — eu-central-1</option>
									<option value="eu-west-1">Europe (Ireland) — eu-west-1</option>
									<option value="eu-west-2">Europe (London) — eu-west-2</option>
									<option value="eu-west-3">Europe (Paris) — eu-west-3</option>
									<option value="eu-north-1">Europe (Stockholm) — eu-north-1</option>
									<option value="eu-south-1">Europe (Milan) — eu-south-1</option>
									<option value="eu-south-2">Europe (Spain) — eu-south-2</option>
									<option value="eu-central-2">Europe (Zurich) — eu-central-2</option>
								</optgroup>
								<optgroup label="<?php esc_attr_e( 'Andre regioner', 'qtale-tts' ); ?>">
									<option value="us-east-1">US East (Virginia) — us-east-1</option>
									<option value="us-west-2">US West (Oregon) — us-west-2</option>
								</optgroup>
							</select>
						</td></tr>
						<tr><th><label><?php esc_html_e( 'Key-prefix (valgfri)', 'qtale-tts' ); ?></label></th><td><input type="text" data-cs-creds="key_prefix" placeholder="qtale-mp3" style="width:340px;"><p class="description"><?php esc_html_e( 'Prefix for alle filer i bucket. Tom = rotnivå.', 'qtale-tts' ); ?></p></td></tr>
					</table>
				</div>

				<div id="qtale-cs-form-s3compat" class="qtale-cs-form" hidden style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;margin:14px 0;">
					<table class="form-table" style="margin:0;">
						<tr><th><label><?php esc_html_e( 'Access Key ID', 'qtale-tts' ); ?></label></th><td><input type="text" data-cs-creds="access_key" style="width:340px;" autocomplete="off"></td></tr>
						<tr><th><label><?php esc_html_e( 'Secret Access Key', 'qtale-tts' ); ?></label></th><td><input type="password" data-cs-creds="secret_key" style="width:340px;" autocomplete="new-password"></td></tr>
						<tr><th><label><?php esc_html_e( 'Bucket-navn', 'qtale-tts' ); ?></label></th><td><input type="text" data-cs-creds="bucket" style="width:340px;"></td></tr>
						<tr><th><label><?php esc_html_e( 'Endpoint URL', 'qtale-tts' ); ?></label></th><td><input type="url" data-cs-creds="endpoint_url" placeholder="https://s3.eu-central-1.wasabisys.com" style="width:520px;"><p class="description"><?php esc_html_e( 'Wasabi: s3.[region].wasabisys.com · R2: [account-id].r2.cloudflarestorage.com · B2: s3.[region].backblazeb2.com', 'qtale-tts' ); ?></p></td></tr>
						<tr><th><label><?php esc_html_e( 'Region', 'qtale-tts' ); ?></label></th><td><input type="text" data-cs-creds="region" value="eu-central-1" style="width:200px;"></td></tr>
						<tr><th><label><?php esc_html_e( 'Key-prefix (valgfri)', 'qtale-tts' ); ?></label></th><td><input type="text" data-cs-creds="key_prefix" placeholder="qtale-mp3" style="width:340px;"></td></tr>
					</table>
				</div>

				<div style="display:flex;gap:10px;margin-top:14px;align-items:center;flex-wrap:wrap;">
					<button type="button" class="button" id="qtale-cs-test"><?php esc_html_e( 'Test tilkobling', 'qtale-tts' ); ?></button>
					<button type="button" class="button button-primary" id="qtale-cs-save"><?php esc_html_e( 'Lagre konfigurasjon', 'qtale-tts' ); ?></button>
					<button type="button" class="button" id="qtale-cs-delete" style="color:#b91c1c;"><?php esc_html_e( 'Slett (deaktiver)', 'qtale-tts' ); ?></button>
					<span id="qtale-cs-msg" style="font-size:13px;font-weight:700;"></span>
				</div>
			</div>

			<script>
			(function(){
				const API = '<?php echo esc_js( $api ); ?>';
				const KEY = '<?php echo esc_js( $key ); ?>';
				const STATUS_DIV = document.getElementById('qtale-cs-status');
				const BACKEND_SEL = document.getElementById('qtale-cs-backend');
				const AGE_INP   = document.getElementById('qtale-cs-age');
				const URL_INP   = document.getElementById('qtale-cs-urlbase');
				const MSG       = document.getElementById('qtale-cs-msg');
				const FORMS = {
					ftp: document.getElementById('qtale-cs-form-ftp'),
					sftp: document.getElementById('qtale-cs-form-sftp'),
					s3: document.getElementById('qtale-cs-form-s3'),
					wasabi: document.getElementById('qtale-cs-form-s3compat'),
					r2: document.getElementById('qtale-cs-form-s3compat'),
					b2: document.getElementById('qtale-cs-form-s3compat'),
				};
				function showForm(type){
					Object.entries(FORMS).forEach(([t,el])=>{ if(el) el.hidden = (t !== type) && !(el === FORMS[type]); });
					// Felles s3compat container brukes for wasabi/r2/b2
					const compat = document.getElementById('qtale-cs-form-s3compat');
					if (compat) compat.hidden = !(type === 'wasabi' || type === 'r2' || type === 'b2');
					if (FORMS.s3) FORMS.s3.hidden = (type !== 's3');
					if (FORMS.ftp) FORMS.ftp.hidden = (type !== 'ftp');
					if (FORMS.sftp) FORMS.sftp.hidden = (type !== 'sftp');
				}
				BACKEND_SEL.addEventListener('change', ()=> showForm(BACKEND_SEL.value));
				function gather(){
					const type = BACKEND_SEL.value;
					if (!type) return null;
					let panel = FORMS[type];
					if (type === 'wasabi' || type === 'r2' || type === 'b2') {
						panel = document.getElementById('qtale-cs-form-s3compat');
					}
					const creds = {};
					if (panel) {
						panel.querySelectorAll('[data-cs-creds]').forEach(el => {
							const k = el.getAttribute('data-cs-creds');
							const v = (el.value || '').trim();
							if (v) creds[k] = (el.type === 'number') ? parseInt(v, 10) : v;
						});
					}
					return {
						backend_type: type,
						enabled: true,
						age_threshold_days: parseInt(AGE_INP.value, 10) || 30,
						url_base: URL_INP.value.trim(),
						creds: creds,
					};
				}
				async function apiCall(method, path, body){
					const opts = {
						method: method,
						headers: { 'X-API-Key': KEY, 'Accept': 'application/json' },
					};
					if (body) {
						opts.headers['Content-Type'] = 'application/json';
						opts.body = JSON.stringify(body);
					}
					const r = await fetch(API + path, opts);
					const j = await r.json().catch(()=>({error:'invalid json'}));
					return { ok: r.ok, status: r.status, body: j };
				}
				function setMsg(text, color){
					MSG.textContent = text;
					MSG.style.color = color || '#0a7f3a';
				}
				async function loadCurrent(){
					if (!KEY) {
						STATUS_DIV.innerHTML = '<div style="color:#b40000;font-size:13px;"><?php echo esc_js( __( 'Sett API-nøkkel og lagre først for å konfigurere cloud storage.', 'qtale-tts' ) ); ?></div>';
						return;
					}
					const res = await apiCall('GET', '/api/v1/cold-storage/config');
					if (!res.ok) { STATUS_DIV.innerHTML = '<div style="color:#b40000;font-size:13px;">Error: ' + (res.body.error || res.status) + '</div>'; return; }
					const configs = (res.body && res.body.configs) || [];
					if (configs.length === 0) {
						STATUS_DIV.innerHTML = '<div style="background:#fef3c7;border:1px solid #fbbf24;border-radius:8px;padding:10px 14px;font-size:13px;color:#78350f;"><?php echo esc_js( __( 'Ingen cloud storage konfigurert. Velg en backend nedenfor for å aktivere.', 'qtale-tts' ) ); ?></div>';
						return;
					}
					const c = configs[0];
					const usedGB = (c.bytes_used / 1e9).toFixed(2);
					STATUS_DIV.innerHTML = '<div style="background:#dcfce7;border:1px solid #4ade80;border-radius:8px;padding:10px 14px;font-size:13px;color:#14532d;">'
						+ '<strong><?php echo esc_js( __( 'Aktiv:', 'qtale-tts' ) ); ?></strong> ' + c.backend_type.toUpperCase()
						+ ' · <strong>' + usedGB + ' GB</strong> <?php echo esc_js( __( 'lagret cold-tier', 'qtale-tts' ) ); ?>'
						+ ' · <?php echo esc_js( __( 'flytter filer eldre enn', 'qtale-tts' ) ); ?> <strong>' + c.age_threshold_days + ' <?php echo esc_js( __( 'dager', 'qtale-tts' ) ); ?></strong>'
						+ (c.last_run_at ? ' · <?php echo esc_js( __( 'sist kjørt', 'qtale-tts' ) ); ?>: ' + c.last_run_at : '')
						+ '</div>';
					// Pre-utfyll skjemaet
					BACKEND_SEL.value = c.backend_type;
					AGE_INP.value = c.age_threshold_days;
					URL_INP.value = c.url_base;
					showForm(c.backend_type);
					// Fyll inn ikke-sensitive cred-felter
					const target = c.backend_type;
					let panel = FORMS[target];
					if (target === 'wasabi' || target === 'r2' || target === 'b2') panel = document.getElementById('qtale-cs-form-s3compat');
					if (panel && c.creds) {
						Object.entries(c.creds).forEach(([k, v]) => {
							const el = panel.querySelector('[data-cs-creds="' + k + '"]');
							if (el) el.value = v;
						});
					}
				}
				document.getElementById('qtale-cs-test').addEventListener('click', async ()=>{
					const data = gather();
					if (!data) { setMsg('<?php echo esc_js( __( 'Velg en backend først', 'qtale-tts' ) ); ?>', '#b40000'); return; }
					setMsg('<?php echo esc_js( __( 'Tester…', 'qtale-tts' ) ); ?>', '#475569');
					const r = await apiCall('POST', '/api/v1/cold-storage/test', data);
					if (r.body.ok) setMsg('✓ ' + r.body.message, '#0a7f3a');
					else setMsg('✗ ' + (r.body.message || r.body.error || 'Failed'), '#b40000');
				});
				document.getElementById('qtale-cs-save').addEventListener('click', async ()=>{
					const data = gather();
					if (!data) { setMsg('<?php echo esc_js( __( 'Velg en backend først', 'qtale-tts' ) ); ?>', '#b40000'); return; }
					setMsg('<?php echo esc_js( __( 'Lagrer…', 'qtale-tts' ) ); ?>', '#475569');
					const r = await apiCall('POST', '/api/v1/cold-storage/config', data);
					if (r.body.ok) {
						setMsg('✓ <?php echo esc_js( __( 'Lagret', 'qtale-tts' ) ); ?>', '#0a7f3a');
						loadCurrent();
					} else {
						setMsg('✗ ' + (r.body.error || 'Failed'), '#b40000');
					}
				});
				document.getElementById('qtale-cs-delete').addEventListener('click', async ()=>{
					if (!confirm('<?php echo esc_js( __( 'Slette cloud storage-konfigurasjon? (Eksisterende cold-filer ligger der de er — kun deaktivering)', 'qtale-tts' ) ); ?>')) return;
					const type = BACKEND_SEL.value || 'ftp';
					setMsg('<?php echo esc_js( __( 'Sletter…', 'qtale-tts' ) ); ?>', '#475569');
					const r = await apiCall('DELETE', '/api/v1/cold-storage/config?backend_type=' + type, null);
					if (r.body.ok) {
						setMsg('✓ <?php echo esc_js( __( 'Slettet', 'qtale-tts' ) ); ?>', '#0a7f3a');
						BACKEND_SEL.value = ''; showForm(''); loadCurrent();
					} else {
						setMsg('✗ ' + (r.body.error || 'Failed'), '#b40000');
					}
				});
				loadCurrent();
			})();
			</script>
		</div>
		<?php
	}

	/**
	 * AJAX: flush the queued audio-generation cron events ("Stopp & tøm kø" button).
	 * Uses the same helper as the activation hook so a runaway backfill queue can be
	 * drained at any time without reinstalling the plugin.
	 */
	public static function ajax_flush_queue() {
		check_ajax_referer( 'qtale_tts_flush_queue' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'qtale-tts' ) ), 403 );
		}
		$cleared = Qtale_TTS::flush_pending_queue();
		wp_send_json_success( array(
			'cleared' => (int) $cleared,
			'message' => sprintf(
				/* translators: %d = number of queued generations removed */
				_n( '%d planlagt generering fjernet fra køen.', '%d planlagte genereringer fjernet fra køen.', (int) $cleared, 'qtale-tts' ),
				(int) $cleared
			),
		) );
	}

	/**
	 * Backfill: schedule audio generation for all published posts in the last N days.
	 * Throttled — staggered scheduling (5 sec spread) so we don't spike Azure rate limit.
	 */
	public static function ajax_backfill() {
		check_ajax_referer( 'qtale_tts_backfill' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'qtale-tts' ) ), 403 );
		}
		// HISTORISK BACKFILL DEAKTIVERT (2026-05-26, kundekrav: "kun framover").
		// Kun nye innlegg genererer lyd via publiserings-hooken. Returnerer her
		// før noe planlegges, så verktøyet ikke kan trigge arkiv-generering.
		wp_send_json_error( array( 'message' => __( 'Backfill av eldre innlegg er deaktivert — kun nye artikler genereres automatisk fra publisering.', 'qtale-tts' ) ) );
		$s = Qtale_TTS::settings();
		$design_id = isset( $s['default_design_public_id'] ) ? trim( $s['default_design_public_id'] ) : '';
		if ( $design_id === '' ) {
			wp_send_json_error( array( 'message' => __( 'Velg en player-design først.', 'qtale-tts' ) ) );
		}
		if ( empty( $s['api_key'] ) ) {
			wp_send_json_error( array( 'message' => __( 'API-nøkkel mangler.', 'qtale-tts' ) ) );
		}

		// Fetch design to know which langs to generate
		$design_cache_key = 'qtale_design_' . md5( $design_id );
		$design = get_transient( $design_cache_key );
		if ( ! $design ) {
			$GLOBALS['qtale_app_base'] = isset( $s['app_base'] ) ? $s['app_base'] : 'https://app.qtale.no';
			$client = new Qtale_TTS_Client( $s['api_base'], $s['api_key'] );
			$resp   = $client->player_design_by_id( $design_id );
			if ( is_wp_error( $resp ) || empty( $resp['design'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Kunne ikke hente design.', 'qtale-tts' ) ) );
			}
			$design = $resp['design'];
			set_transient( $design_cache_key, $design, 30 );
		}
		$langs = ( ! empty( $design['config']['translate_langs'] ) && is_array( $design['config']['translate_langs'] ) )
			? $design['config']['translate_langs']
			: array( 'no' );
		$source_lang = Qtale_TTS::resolve_source_language();
		if ( ! in_array( $source_lang, $langs, true ) ) array_unshift( $langs, $source_lang );

		// Voice gender(s) — picker needs BOTH male+female generated, same as
		// render_embed. Cache keys are ALWAYS gender-suffixed (lang-gender), so
		// the backfill must match or the player never reads the audio it makes.
		$picker  = ( isset( $design['config']['voice_gender'] ) && $design['config']['voice_gender'] === 'picker' );
		$genders = $picker ? array( 'male', 'female' ) : array( $design['config']['voice_gender'] ?? 'female' );

		// Query posts
		$days = max( 0, (int) ( isset( $_POST['days'] ) ? $_POST['days'] : 30 ) );
		$skip_cached = ! empty( $_POST['skip_cached'] );
		$post_types  = isset( $s['post_types'] ) && is_array( $s['post_types'] ) ? $s['post_types'] : array( 'post' );

		// Batch-size HARDLÅST til 5 — beskytter mot cascade-overload.
		// Med "Fortsett med neste batch"-knapp kan Helge kjøre videre kontrollert.
		$batch_size = 5;
		$batch_offset = isset( $_POST['batch_offset'] ) ? max( 0, (int) $_POST['batch_offset'] ) : 0;
		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
			'offset'         => $batch_offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		);
		if ( $days > 0 ) {
			$args['date_query'] = array(
				array( 'after' => $days . ' days ago' ),
			);
		}
		$query = new WP_Query( $args );
		$post_ids = $query->posts;
		$total_posts = count( $post_ids );

		$max_chars = max( 100, (int) $s['max_chars_auto'] );
		$min_chars = max( 0,   (int) $s['min_chars_auto'] );

		$scheduled = 0;
		$already_cached = 0;
		// Stagger relaxed 2026-05-26: Azure is now pay-as-you-go (S0 standard ~200 TPS,
		// not the old free-tier ~5 RPS), worker concurrency=8, and the worker is CPU-fenced
		// (CPUQuota 900%) so a burst can't disturb the web/app. A 1s ramp just avoids a
		// thundering herd; the Redis valve (QTALE_QUEUE_MAX) caps total depth. Tunable via
		// QTALE_BATCH_STAGGER if a site ever needs it gentler.
		$delay_step = (int) ( defined( 'QTALE_BATCH_STAGGER' ) ? QTALE_BATCH_STAGGER : 1 );
		$delay      = 1;

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) continue;
			// SHARED extraction — guarantees cache_key consistency
			$text = Qtale_TTS::extract_post_text( $post );
			if ( $text === '' || strlen( $text ) < $min_chars ) continue;

			foreach ( $langs as $lang ) {
				foreach ( $genders as $gender ) {
					// Gender-suffixed key + gendered cron — matches render_embed + generate_voice.
					$cache_key = Qtale_TTS::embed_cache_key( $design_id, $post_id, $lang . '-' . $gender, $text );
					if ( $skip_cached && get_transient( $cache_key ) ) {
						$already_cached++;
						$scheduled++;  // counts toward total even if skipped
						continue;
					}
					$args_cron = array( $design_id, $post_id, $text, $source_lang, $lang, $gender );
					if ( ! wp_next_scheduled( 'qtale_embed_gen_voice', $args_cron ) ) {
						wp_schedule_single_event( time() + $delay, 'qtale_embed_gen_voice', $args_cron );
						$delay += $delay_step;
					}
					$scheduled++;
				}
			}
		}

		// Count TOTAL matching posts (for "X of Y" progress) — separate query without limit
		$count_args = $args;
		unset( $count_args['posts_per_page'], $count_args['offset'] );
		$count_args['posts_per_page'] = -1;
		$count_args['fields'] = 'ids';
		$total_matching = count( get_posts( $count_args ) );

		// Persist totals so the polling endpoint can compute progress
		// Use UTC ISO for since-query (api expects ISO 8601)
		$batch_start_iso = gmdate( 'Y-m-d\TH:i:s', time() );
		update_option( 'qtale_tts_backfill_state', array(
			'started_at'      => time(),
			'started_at_iso'  => $batch_start_iso,
			'design_id'       => $design_id,
			'total'           => $scheduled,
			'already_cached'  => $already_cached,
			'langs'           => count( $langs ),
			'genders'         => $genders,
			'posts'           => $total_posts,
			'post_ids'        => $post_ids,
			'source_lang'     => $source_lang,
		), false );

		if ( function_exists( 'spawn_cron' ) ) spawn_cron();

		$next_offset = $batch_offset + $batch_size;
		$has_more = $next_offset < $total_matching;

		wp_send_json_success( array(
			'scheduled'      => $scheduled,
			'already_cached' => $already_cached,
			'posts'          => $total_posts,
			'langs'          => count( $langs ),
			'eta_sec'        => max( 0, $scheduled - $already_cached ) * 4,
			'batch_offset'   => $batch_offset,
			'batch_size'     => $batch_size,
			'next_offset'    => $next_offset,
			'total_matching' => $total_matching,
			'has_more'       => $has_more,
		) );
	}

	/**
	 * Backfill polling — counts how many lang-audios are now cached for the
	 * post-list this backfill scheduled. Returns {total, done, pending, eta_sec}.
	 */
	public static function ajax_backfill_status() {
		check_ajax_referer( 'qtale_tts_backfill_status' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'qtale-tts' ) ), 403 );
		}
		$state = get_option( 'qtale_tts_backfill_state', array() );
		if ( ! is_array( $state ) || empty( $state['post_ids'] ) ) {
			wp_send_json_success( array( 'total' => 0, 'done' => 0, 'pending' => 0, 'eta_sec' => 0 ) );
		}
		$s = Qtale_TTS::settings();
		$design = get_transient( 'qtale_design_' . md5( $state['design_id'] ) );
		$langs = ( $design && ! empty( $design['config']['translate_langs'] ) )
			? $design['config']['translate_langs']
			: array( 'no' );
		if ( ! in_array( $state['source_lang'], $langs, true ) ) array_unshift( $langs, $state['source_lang'] );

		// Same gender set the backfill scheduled (gender-suffixed cache keys).
		$genders = ( ! empty( $state['genders'] ) && is_array( $state['genders'] ) )
			? $state['genders']
			: ( ( $design && ( $design['config']['voice_gender'] ?? '' ) === 'picker' )
				? array( 'male', 'female' )
				: array( $design['config']['voice_gender'] ?? 'female' ) );

		$max_chars = max( 100, (int) $s['max_chars_auto'] );
		// Harvester: also upgrade pending {job_id} transients to {audio_url} when QTale reports done
		$client = ! empty( $s['api_key'] ) ? new Qtale_TTS_Client( $s['api_base'], $s['api_key'] ) : null;
		$ttl = max( 1, (int) $s['cache_ttl_days'] ) * DAY_IN_SECONDS;
		// Count ARTICLES (not langs): article is "done" only when ALL its langs are done
		$articles_done = 0;
		$total_articles = count( $state['post_ids'] );
		// Debug info for troubleshooting
		$debug = array( 'checked' => 0, 'no_transient' => 0, 'has_audio' => 0, 'has_pending' => 0, 'status_done' => 0, 'status_other' => 0, 'sample_keys' => array() );
		foreach ( $state['post_ids'] as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) { continue; }
			// SHARED extraction — same as ajax_backfill so cache_keys match
			$text = Qtale_TTS::extract_post_text( $post );
			if ( $text === '' ) continue;
			$article_lang_done = 0;
			foreach ( $langs as $lang ) {
				foreach ( $genders as $gender ) {
					$key = Qtale_TTS::embed_cache_key( $state['design_id'], $post_id, $lang . '-' . $gender, $text );
					$debug['checked']++;
					if ( count( $debug['sample_keys'] ) < 3 ) $debug['sample_keys'][] = substr( $key, -10 ) . '/' . $lang . '-' . $gender;
					$c = get_transient( $key );
					if ( ! is_array( $c ) ) { $debug['no_transient']++; continue; }
					if ( ! empty( $c['audio_url'] ) ) { $debug['has_audio']++; $article_lang_done++; continue; }
					if ( ! empty( $c['job_id'] ) && $client ) {
						$debug['has_pending']++;
						$st = $client->status( $c['job_id'] );
						if ( ! is_wp_error( $st ) && ! empty( $st['status'] ) ) {
							if ( $st['status'] === 'done' && ! empty( $st['audio_url'] ) ) {
								set_transient( $key, array(
									'audio_url'        => $st['audio_url'],
									'translated_chars' => isset( $c['translated_chars'] ) ? $c['translated_chars'] : 0,
								), $ttl );
								$article_lang_done++;
								$debug['status_done']++;
							} elseif ( $st['status'] === 'failed' ) {
								delete_transient( $key );
							} else {
								$debug['status_other']++;
							}
						}
					}
				}
			}
			if ( $article_lang_done >= count( $langs ) * count( $genders ) ) $articles_done++;
		}
		$done = $articles_done;
		$total = $total_articles;
		$pending = max( 0, $total - $done );
		// Bonus: query QTale directly for server-side queue stats since batch started
		$queue_stats = array( 'done' => 0, 'queued' => 0, 'processing' => 0, 'failed' => 0 );
		if ( $client && ! empty( $state['started_at_iso'] ) ) {
			$summary = $client->jobs_summary( $state['started_at_iso'] );
			if ( ! is_wp_error( $summary ) && ! empty( $summary['counts'] ) ) {
				$queue_stats = array_merge( $queue_stats, $summary['counts'] );
			}
		}
		wp_send_json_success( array(
			'debug'        => $debug,
			'queue_stats'  => $queue_stats,
			'total'        => $total,
			'done'         => $done,
			'pending'      => $pending,
			'eta_sec' => $pending * 4,
		) );
	}

	public static function ajax_test_key() {
		check_ajax_referer( 'qtale_tts_test_key' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'qtale-tts' ) ), 403 );
		}
		$key      = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		$settings = Qtale_TTS::settings();
		$client   = new Qtale_TTS_Client( $settings['api_base'], $key );

		// 1) Verify key + fetch tier from /api/v1/me
		$me = $client->me();
		if ( is_wp_error( $me ) ) {
			wp_send_json_error( array( 'message' => $me->get_error_message() ) );
		}

		// 2) Persist tier_label + allowed_designs (admin can't edit these directly)
		$saved = get_option( Qtale_TTS::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$saved['tier_name']       = isset( $me['tier_label'] ) ? sanitize_text_field( $me['tier_label'] ) : '';
		$saved['tier_key']        = isset( $me['tier'] ) ? sanitize_text_field( $me['tier'] ) : '';
		$saved['allowed_designs'] = isset( $me['allowed_designs'] ) && is_array( $me['allowed_designs'] )
			? array_map( 'sanitize_key', $me['allowed_designs'] )
			: array();
		$saved['company_name']    = isset( $me['company'] ) ? sanitize_text_field( $me['company'] ) : '';
		// v2.4.3 — addon-gating: lese addons.dual_player fra /me-respons
		$saved['dual_player_addon']       = ! empty( $me['addons']['dual_player'] ) ? 1 : 0;
		// v2.5 — addon-gating: addons.translation_modal styrer om Translation Modal-feature rendres
		$saved['translation_modal_addon'] = ! empty( $me['addons']['translation_modal'] ) ? 1 : 0;
		// v2.6 — addon-gating: addons.utility_pack styrer Print + PDF (A+/Del er alltid gratis)
		$saved['utility_pack_addon']      = ! empty( $me['addons']['utility_pack'] ) ? 1 : 0;
		// v2.6.17 — Q-Text tier (access/pro/enterprise) for badge i modal + tier-cap
		$saved['qtext_tier']              = isset( $me['addons']['qtext_tier'] ) ? sanitize_key( (string) $me['addons']['qtext_tier'] ) : '';
		// v2.6.3 — bypass persistent object-cache (Redis/Memcached): clear pre+post update.
		// Sett observert på iNyheter der Test-nøkkel returnerte korrekt JSON men options
		// beholdt translation_modal_addon=0 — object-cache returnerte stale read tilbake
		// til render-laget selv etter update_option.
		wp_cache_delete( Qtale_TTS::OPTION_KEY, 'options' );
		update_option( Qtale_TTS::OPTION_KEY, $saved );
		wp_cache_delete( Qtale_TTS::OPTION_KEY, 'options' );

		// 3) Voice count is just informational
		$voices = $client->voices();
		$voice_count = ! is_wp_error( $voices ) && isset( $voices['voices'] ) ? count( $voices['voices'] ) : 0;

		wp_send_json_success(
			array(
				'voice_count'    => $voice_count,
				'tier_label'     => $saved['tier_name'],
				'allowed_count'  => count( $saved['allowed_designs'] ),
				'total_designs'  => count( Qtale_TTS::designs() ),
				'company'        => $saved['company_name'],
			)
		);
	}

	/**
	 * Refresh customer's saved player designs from app.qtale.no/api/public/player-designs.
	 * Persists list to OPTION_KEY['player_designs'] + 'player_designs_fetched_at'.
	 */
	public static function ajax_refresh_designs() {
		check_ajax_referer( 'qtale_tts_refresh_designs' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'qtale-tts' ) ), 403 );
		}
		$settings = Qtale_TTS::settings();
		$key      = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
		if ( ! $key ) {
			wp_send_json_error( array( 'message' => __( 'API-nøkkel mangler. Lim inn nøkkel + test først.', 'qtale-tts' ) ) );
		}
		$client = new Qtale_TTS_Client( $settings['api_base'], $key );
		$resp   = $client->player_designs();
		if ( is_wp_error( $resp ) ) {
			wp_send_json_error( array( 'message' => $resp->get_error_message() ) );
		}
		$designs = isset( $resp['designs'] ) && is_array( $resp['designs'] ) ? $resp['designs'] : array();

		// v2.6.2 — Refresh syncer NÅ også addon/tier-flagg fra /api/v1/me. Tidligere
		// måtte admin klikke separat "Test nøkkel" når et addon (Translation Modal,
		// Utility Pack, Dual Player) ble aktivert serverside — UX-felle. Én knapp,
		// ett API-kall ekstra: hele server-state speilet til WP-options.
		$me = $client->me();
		if ( ! is_wp_error( $me ) && is_array( $me ) ) {
			$saved_me = get_option( Qtale_TTS::OPTION_KEY, array() );
			if ( ! is_array( $saved_me ) ) $saved_me = array();
			$saved_me['tier_name']                = isset( $me['tier_label'] ) ? sanitize_text_field( $me['tier_label'] ) : ( $saved_me['tier_name'] ?? '' );
			$saved_me['tier_key']                 = isset( $me['tier'] ) ? sanitize_text_field( $me['tier'] ) : ( $saved_me['tier_key'] ?? '' );
			$saved_me['allowed_designs']          = isset( $me['allowed_designs'] ) && is_array( $me['allowed_designs'] )
				? array_map( 'sanitize_key', $me['allowed_designs'] ) : ( $saved_me['allowed_designs'] ?? array() );
			$saved_me['company_name']             = isset( $me['company'] ) ? sanitize_text_field( $me['company'] ) : ( $saved_me['company_name'] ?? '' );
			$saved_me['dual_player_addon']        = ! empty( $me['addons']['dual_player'] ) ? 1 : 0;
			$saved_me['translation_modal_addon']  = ! empty( $me['addons']['translation_modal'] ) ? 1 : 0;
			$saved_me['utility_pack_addon']       = ! empty( $me['addons']['utility_pack'] ) ? 1 : 0;
			$saved_me['qtext_tier']               = isset( $me['addons']['qtext_tier'] ) ? sanitize_key( (string) $me['addons']['qtext_tier'] ) : '';
			wp_cache_delete( Qtale_TTS::OPTION_KEY, 'options' );
			update_option( Qtale_TTS::OPTION_KEY, $saved_me );
			wp_cache_delete( Qtale_TTS::OPTION_KEY, 'options' );
		}

		// Clear stale design-transients so shortcode picks up the fresh design immediately
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_qtale\\_tts\\_design\\_%' OR option_name LIKE '\\_transient\\_timeout\\_qtale\\_tts\\_design\\_%' OR option_name LIKE '\\_transient\\_qtale\\_design\\_%' OR option_name LIKE '\\_transient\\_timeout\\_qtale\\_design\\_%'" );

		// Persist on the option blob so render_page() + shortcode handlers can read it back
		$saved = get_option( Qtale_TTS::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$clean = array();
		foreach ( $designs as $d ) {
			if ( empty( $d['public_id'] ) ) {
				continue;
			}
			$clean[] = array(
				'public_id'  => sanitize_text_field( $d['public_id'] ),
				'name'       => isset( $d['name'] ) ? sanitize_text_field( $d['name'] ) : '',
				'shortcode'  => isset( $d['shortcode'] ) ? sanitize_text_field( $d['shortcode'] ) : '',
				'updated_at' => isset( $d['updated_at'] ) ? sanitize_text_field( $d['updated_at'] ) : '',
				'config'     => isset( $d['config'] ) && is_array( $d['config'] ) ? $d['config'] : array(),
			);
		}
		// Write player_designs to its OWN option key (no sanitize_callback, no stripping).
		// Clear cache before + after to bypass persistent object caches.
		$designs_blob = array(
			'designs'    => $clean,
			'fetched_at' => gmdate( 'Y-m-d H:i:s' ),
		);
		wp_cache_delete( Qtale_TTS::OPTION_KEY_DESIGNS, 'options' );
		$write_ok = update_option( Qtale_TTS::OPTION_KEY_DESIGNS, $designs_blob, false );
		wp_cache_delete( Qtale_TTS::OPTION_KEY_DESIGNS, 'options' );

		// Verify by re-reading the dedicated option
		$verify_blob = get_option( Qtale_TTS::OPTION_KEY_DESIGNS, array() );
		$verify_count = ( is_array( $verify_blob ) && isset( $verify_blob['designs'] ) && is_array( $verify_blob['designs'] ) )
			? count( $verify_blob['designs'] ) : 0;

		wp_send_json_success(
			array(
				'count'        => count( $clean ),
				'designs'      => $clean,
				'fetched_at'   => $saved['player_designs_fetched_at'],
				'write_ok'     => $write_ok,
				'verify_count' => $verify_count,
				'diag'         => sprintf(
					'wrote=%d verified=%d %s',
					count( $clean ),
					$verify_count,
					$verify_count === count( $clean ) ? '✓' : '✗ MISMATCH — options ikke persistert (cache?)'
				),
			)
		);
	}
}
