<?php
/**
 * Per-post Q-Tale player toggle.
 *
 *   - Post-meta key:  _qtale_player_enabled
 *   - Values:         '' | 'inherit' | 'force' | 'skip'
 *   - inherit/empty: follow global auto_generate setting (default)
 *   - force:         always show player, even if auto is off
 *   - skip:          never show player, even if auto is on
 *
 *   Metabox in the classic + block editor with radio buttons + "Generate now" button.
 *
 * @package Qtale_TTS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qtale_TTS_Post_Meta {

	const META_KEY    = '_qtale_player_enabled';
	const DESIGN_META = '_qtale_player_design';   // per-post player override: '' = settings default | 'std:<key>' = standard player | else = Studio public_id

	public static function register() {
		add_action( 'add_meta_boxes',     array( __CLASS__, 'register_metabox' ) );
		add_action( 'save_post',          array( __CLASS__, 'save_metabox' ) );
		add_action( 'wp_ajax_qtale_tts_generate_post', array( __CLASS__, 'ajax_generate_post' ) );
		add_filter( 'manage_posts_columns',     array( __CLASS__, 'add_column' ) );
		add_action( 'manage_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_filter( 'manage_pages_columns',     array( __CLASS__, 'add_column' ) );
		add_action( 'manage_pages_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
	}

	public static function register_metabox() {
		$s = Qtale_TTS::settings();
		$pts = isset( $s['post_types'] ) && is_array( $s['post_types'] ) ? $s['post_types'] : array( 'post' );
		foreach ( $pts as $pt ) {
			add_meta_box(
				'qtale-tts-post-meta',
				__( 'Q-Tale Audio Player', 'qtale-tts' ),
				array( __CLASS__, 'render_metabox' ),
				$pt,
				'side',     // høyre sidebar (samme kolonne som Kategorier/Tags)
				'default'   // under standard-bokser (Publiser, Format, Kategorier, Tags)
			);
		}
	}

	public static function render_metabox( $post ) {
		wp_nonce_field( 'qtale_tts_post_meta', 'qtale_tts_post_meta_nonce' );
		$value = get_post_meta( $post->ID, self::META_KEY, true );
		if ( $value === '' ) $value = 'inherit';
		$s = Qtale_TTS::settings();
		$design_id = isset( $s['default_design_public_id'] ) ? $s['default_design_public_id'] : '';
		$auto_on = ! empty( $s['auto_generate'] ) && in_array( $s['placement'], array( 'above', 'below' ), true );

		// Per-post player picker — Odin/Tor/Valhalla only
		$me             = self::customer_meta();
		$picker_ok      = self::picker_allowed( $me['tier'] );
		$post_design    = get_post_meta( $post->ID, self::DESIGN_META, true );
		$custom_designs = $picker_ok ? self::fetch_custom_designs() : array();
		$std_all        = Qtale_TTS::designs();
		$std_allowed    = ! empty( $me['allowed'] ) ? array_intersect_key( $std_all, array_flip( $me['allowed'] ) ) : $std_all;

		// Show actual effective state
		$effective = self::is_enabled_for_post( $post->ID );

		?>
		<style>
			#qtale-tts-post-meta .qtale-pm-status{
				display:flex;align-items:center;gap:6px;padding:8px 11px;border-radius:6px;
				margin:0 0 12px;font-size:12px;font-weight:600;
			}
			#qtale-tts-post-meta .qtale-pm-status.on{background:#dcfce7;color:#166534;border:1px solid #86efac;}
			#qtale-tts-post-meta .qtale-pm-status.off{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}
			#qtale-tts-post-meta .qtale-pm-status .dot{width:8px;height:8px;border-radius:50%;display:inline-block;}
			#qtale-tts-post-meta .qtale-pm-status.on .dot{background:#22c55e;box-shadow:0 0 8px rgba(34,197,94,.5);}
			#qtale-tts-post-meta .qtale-pm-status.off .dot{background:#ef4444;}
			#qtale-tts-post-meta .qtale-pm-radio{
				display:block;padding:8px 11px;margin:0 0 5px;
				border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;font-size:13px;
				transition:border-color .12s;
			}
			#qtale-tts-post-meta .qtale-pm-radio:hover{border-color:#E85124;}
			#qtale-tts-post-meta .qtale-pm-radio input{margin-right:7px;}
			#qtale-tts-post-meta .qtale-pm-radio input:checked + strong{color:#E85124;}
			#qtale-tts-post-meta .qtale-pm-radio .desc{display:block;font-size:11px;color:#64748b;margin:3px 0 0 22px;font-weight:400;}
			#qtale-tts-post-meta .qtale-pm-gen{
				width:100%;margin-top:10px;padding:7px 12px;background:#0f172a;color:#fff;
				border:0;border-radius:6px;font-weight:600;font-size:12px;cursor:pointer;
			}
			#qtale-tts-post-meta .qtale-pm-gen:hover{background:#E85124;}
			#qtale-tts-post-meta .qtale-pm-gen:disabled{opacity:.5;cursor:wait;}
			#qtale-tts-post-meta .qtale-pm-no-design{
				background:#fef3ec;border:1px solid #fed7aa;color:#9a3412;
				padding:9px 12px;border-radius:6px;font-size:12px;margin:0 0 10px;
			}
		</style>

		<?php
		// Player is configured if EITHER Egen (Studio) OR Standard (Odin/Tor/etc) is set
		$has_player = ! empty( $design_id ) || ! empty( $s['default_design'] );
		$player_label = $design_id
			? __( 'Egen player', 'qtale-tts' )
			: ( ! empty( $s['default_design'] ) ? __( 'Standard:', 'qtale-tts' ) . ' ' . esc_html( $s['default_design'] ) : '' );
		?>
		<?php if ( empty( $s['api_key'] ) ) : ?>
			<div class="qtale-pm-no-design">
				<?php esc_html_e( '⚠ API-nøkkel mangler i Innstillinger → Q-Tale TTS.', 'qtale-tts' ); ?>
			</div>
		<?php elseif ( ! $has_player ) : ?>
			<div class="qtale-pm-no-design">
				<?php esc_html_e( '⚠ Velg en player-design under Innstillinger → Q-Tale TTS.', 'qtale-tts' ); ?>
			</div>
		<?php else : ?>
			<div class="qtale-pm-status <?php echo $effective ? 'on' : 'off'; ?>">
				<span class="dot"></span>
				<?php if ( $effective ) : ?>
					<?php esc_html_e( 'Player vises på denne posten', 'qtale-tts' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Player skjult fra denne posten', 'qtale-tts' ); ?>
				<?php endif; ?>
			</div>
			<div style="font-size:11px;color:#64748b;margin:-8px 0 12px;padding:0 2px;">
				<?php echo esc_html( $player_label ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $picker_ok ) : ?>
		<label style="display:block;font-size:12px;font-weight:600;margin:0 0 5px;color:#0f172a;">
			<?php esc_html_e( 'Player for denne posten', 'qtale-tts' ); ?>
		</label>
		<select name="qtale_player_design" style="width:100%;margin:0 0 14px;padding:7px 9px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;background:#fff;">
			<option value="" <?php selected( $post_design, '' ); ?>><?php esc_html_e( 'Standard (fra innstillinger)', 'qtale-tts' ); ?></option>
			<?php if ( $custom_designs ) : ?>
			<optgroup label="<?php esc_attr_e( 'Mine design', 'qtale-tts' ); ?>">
				<?php foreach ( $custom_designs as $d ) : ?>
					<option value="<?php echo esc_attr( $d['public_id'] ); ?>" <?php selected( $post_design, $d['public_id'] ); ?>><?php echo esc_html( $d['name'] ); ?></option>
				<?php endforeach; ?>
			</optgroup>
			<?php endif; ?>
			<optgroup label="<?php esc_attr_e( 'Standard-spillere', 'qtale-tts' ); ?>">
				<?php foreach ( $std_allowed as $key => $label ) : ?>
					<option value="std:<?php echo esc_attr( $key ); ?>" <?php selected( $post_design, 'std:' . $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</optgroup>
		</select>
		<?php endif; ?>

		<label class="qtale-pm-radio">
			<input type="radio" name="qtale_player_enabled" value="inherit" <?php checked( $value, 'inherit' ); ?>>
			<strong><?php esc_html_e( 'Følg standard', 'qtale-tts' ); ?></strong>
			<span class="desc">
				<?php
				echo $auto_on
					? esc_html__( '(global auto-injicering er PÅ → player vises)', 'qtale-tts' )
					: esc_html__( '(global auto-injicering er AV → player skjult)', 'qtale-tts' );
				?>
			</span>
		</label>
		<label class="qtale-pm-radio">
			<input type="radio" name="qtale_player_enabled" value="force" <?php checked( $value, 'force' ); ?>>
			<strong><?php esc_html_e( 'Tving på', 'qtale-tts' ); ?></strong>
			<span class="desc"><?php esc_html_e( 'Alltid vis player — selv om global er av', 'qtale-tts' ); ?></span>
		</label>
		<label class="qtale-pm-radio">
			<input type="radio" name="qtale_player_enabled" value="skip" <?php checked( $value, 'skip' ); ?>>
			<strong><?php esc_html_e( 'Skjul', 'qtale-tts' ); ?></strong>
			<span class="desc"><?php esc_html_e( 'Aldri vis player — selv om global er på', 'qtale-tts' ); ?></span>
		</label>

		<?php if ( $design_id && $post->post_status === 'publish' ) : ?>
			<button type="button" class="qtale-pm-gen" id="qtale-pm-gen-btn" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
				⚡ <?php esc_html_e( 'Generér audio for alle språk nå', 'qtale-tts' ); ?>
			</button>
			<div id="qtale-pm-gen-result" style="margin-top:8px;font-size:11px;"></div>
		<?php endif; ?>

		<script>
		(function(){
			const btn = document.getElementById('qtale-pm-gen-btn');
			if (!btn) return;
			btn.addEventListener('click', async () => {
				const res = document.getElementById('qtale-pm-gen-result');
				btn.disabled = true;
				res.textContent = '⏳ Planlegger generering …';
				res.style.color = '#64748b';
				try {
					const fd = new FormData();
					fd.append('action', 'qtale_tts_generate_post');
					fd.append('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'qtale_tts_generate_post' ) ); ?>');
					fd.append('post_id', btn.dataset.postId);
					const r = await fetch(ajaxurl, { method:'POST', body: fd, credentials:'same-origin' });
					const j = await r.json();
					if (j.success) {
						res.textContent = '✓ ' + j.data.scheduled + ' språk-jobber planlagt (~' + Math.ceil(j.data.scheduled * 4 / 60) + ' min)';
						res.style.color = '#16a34a';
					} else {
						res.textContent = '✗ ' + (j.data && j.data.message ? j.data.message : 'Failed');
						res.style.color = '#dc2626';
					}
				} catch (e) {
					res.textContent = '✗ ' + e.message;
					res.style.color = '#dc2626';
				} finally {
					setTimeout(() => { btn.disabled = false; }, 2000);
				}
			});
		})();
		</script>
		<?php
	}

	public static function save_metabox( $post_id ) {
		if ( ! isset( $_POST['qtale_tts_post_meta_nonce'] )
			|| ! wp_verify_nonce( $_POST['qtale_tts_post_meta_nonce'], 'qtale_tts_post_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		$val = isset( $_POST['qtale_player_enabled'] ) ? sanitize_key( $_POST['qtale_player_enabled'] ) : 'inherit';
		if ( ! in_array( $val, array( 'inherit', 'force', 'skip' ), true ) ) $val = 'inherit';
		update_post_meta( $post_id, self::META_KEY, $val );

		// Per-post player override ('' clears it; 'std:<key>' = standard player; else Studio public_id).
		if ( isset( $_POST['qtale_player_design'] ) ) {
			$pd = sanitize_text_field( wp_unslash( $_POST['qtale_player_design'] ) );
			$ok = ( $pd === '' )
				|| ( strpos( $pd, 'std:' ) === 0 && array_key_exists( substr( $pd, 4 ), Qtale_TTS::designs() ) )
				|| (bool) preg_match( '/^[A-Za-z0-9._\\-]{1,64}$/', $pd );
			if ( $ok ) {
				if ( $pd === '' ) delete_post_meta( $post_id, self::DESIGN_META );
				else update_post_meta( $post_id, self::DESIGN_META, $pd );
			}
		}
	}

	/**
	 * Effective decision: should player render for this post?
	 * Called by Qtale_TTS_Auto::maybe_inject before injecting.
	 */
	public static function is_enabled_for_post( $post_id ) {
		$meta = get_post_meta( $post_id, self::META_KEY, true );
		if ( $meta === 'force' ) return true;
		if ( $meta === 'skip' )  return false;
		// 'inherit' / empty → follow global
		$s = Qtale_TTS::settings();
		$auto_on = ! empty( $s['auto_generate'] )
			&& in_array( $s['placement'], array( 'above', 'below' ), true );
		return $auto_on;
	}

	/** Tiers that may pick a different player per post. */
	const PICKER_TIERS = array( 'valhalla', 'thor', 'odin' );

	/** Customer tier + allowed standard-designs (cached 15 min from /api/v1/me). */
	public static function customer_meta() {
		$cache = get_transient( 'qtale_tts_me' );
		if ( is_array( $cache ) ) return $cache;
		$out = array( 'tier' => '', 'allowed' => array() );
		$s = Qtale_TTS::settings();
		if ( ! empty( $s['api_key'] ) ) {
			$client = new Qtale_TTS_Client( $s['api_base'], $s['api_key'] );
			$me = $client->me();
			if ( ! is_wp_error( $me ) ) {
				$out['tier'] = ! empty( $me['tier'] ) ? strtolower( $me['tier'] ) : '';
				if ( ! empty( $me['allowed_designs'] ) && is_array( $me['allowed_designs'] ) ) {
					$out['allowed'] = $me['allowed_designs'];
				}
			}
		}
		set_transient( 'qtale_tts_me', $out, 15 * MINUTE_IN_SECONDS );
		return $out;
	}

	/** Per-post player picker is an Odin/Tor/Valhalla feature only. */
	public static function picker_allowed( $tier ) {
		return in_array( $tier, self::PICKER_TIERS, true );
	}

	/** Customer's saved Studio designs (cached 5 min) → [ ['public_id','name'], … ]. */
	public static function fetch_custom_designs() {
		$s = Qtale_TTS::settings();
		if ( empty( $s['api_key'] ) ) return array();
		$cache = get_transient( 'qtale_tts_designs_list' );
		if ( is_array( $cache ) ) return $cache;
		$client = new Qtale_TTS_Client( $s['api_base'], $s['api_key'] );
		$resp = $client->player_designs();
		$out = array();
		if ( ! is_wp_error( $resp ) && ! empty( $resp['designs'] ) && is_array( $resp['designs'] ) ) {
			foreach ( $resp['designs'] as $d ) {
				if ( empty( $d['public_id'] ) ) continue;
				// v2.4.3: cache play_shape også, for dual-player validering ('none' = utility-only).
				$play_shape = isset( $d['config']['play_shape'] ) ? (string) $d['config']['play_shape'] : 'circle';
				$out[] = array(
					'public_id'  => $d['public_id'],
					'name'       => ! empty( $d['name'] ) ? $d['name'] : $d['public_id'],
					'play_shape' => $play_shape,
				);
			}
		}
		set_transient( 'qtale_tts_designs_list', $out, 5 * MINUTE_IN_SECONDS );
		return $out;
	}

	/**
	 * Resolve which player a post should use.
	 *   Returns array( type, id ): type='studio' → id is a Studio public_id;
	 *   type='standard' → id is a standard-design key (odin/tor/…).
	 * Per-post override (_qtale_player_design) only applies to picker-eligible tiers;
	 * otherwise the settings default is used.
	 */
	public static function player_for_post( $post_id ) {
		$s = Qtale_TTS::settings();
		$meta = get_post_meta( $post_id, self::DESIGN_META, true );
		if ( is_string( $meta ) && $meta !== '' ) {
			if ( strpos( $meta, 'std:' ) === 0 ) return array( 'standard', substr( $meta, 4 ) );
			return array( 'studio', $meta );
		}
		// Inherit settings default
		if ( ! empty( $s['default_design_public_id'] ) ) return array( 'studio', $s['default_design_public_id'] );
		return array( 'standard', ! empty( $s['default_design'] ) ? $s['default_design'] : 'odin' );
	}

	/**
	 * AJAX: schedule audio generation for one specific post (all langs).
	 */
	public static function ajax_generate_post() {
		check_ajax_referer( 'qtale_tts_generate_post' );
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'qtale-tts' ) ), 403 );
		}
		$post = get_post( $post_id );
		if ( ! $post ) wp_send_json_error( array( 'message' => __( 'Post not found', 'qtale-tts' ) ) );

		$s = Qtale_TTS::settings();
		list( $ptype, $pid ) = self::player_for_post( $post_id );
		// Standard players render (and generate) on view — nothing to pre-schedule here.
		if ( $ptype !== 'studio' || $pid === '' ) {
			wp_send_json_success( array( 'scheduled' => 0, 'total_langs' => 0,
				'note' => __( 'Standard-spiller — lyd genereres ved visning.', 'qtale-tts' ) ) );
		}
		$design_id = $pid;

		$design = get_transient( 'qtale_design_' . md5( $design_id ) );
		if ( ! $design ) {
			$GLOBALS['qtale_app_base'] = isset( $s['app_base'] ) ? $s['app_base'] : 'https://app.qtale.no';
			$client = new Qtale_TTS_Client( $s['api_base'], $s['api_key'] );
			$resp = $client->player_design_by_id( $design_id );
			if ( is_wp_error( $resp ) || empty( $resp['design'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Design ikke funnet.', 'qtale-tts' ) ) );
			}
			$design = $resp['design'];
			set_transient( 'qtale_design_' . md5( $design_id ), $design, 30 );   // v2.6.1 BUG-FIX: var HOUR_IN_SECONDS → 1 time stale-cache hvis post-meta cachet før shortcode.php (som bruker 30s). Nå 30s overalt.
		}
		$langs = ( ! empty( $design['config']['translate_langs'] ) && is_array( $design['config']['translate_langs'] ) )
			? $design['config']['translate_langs']
			: array( 'no' );
		$source_lang = Qtale_TTS::resolve_source_language();
		if ( ! in_array( $source_lang, $langs, true ) ) array_unshift( $langs, $source_lang );
		$picker  = ( isset( $design['config']['voice_gender'] ) && $design['config']['voice_gender'] === 'picker' );
		$genders = $picker ? array( 'male', 'female' ) : array( $design['config']['voice_gender'] ?? 'female' );

		// SHARED extraction — guarantees cache_key matches what cron/poll computes
		$text = Qtale_TTS::extract_post_text( $post );

		$scheduled = 0;
		$delay = 1;
		foreach ( $langs as $lang ) {
			foreach ( $genders as $gender ) {
				$cache_key = Qtale_TTS::embed_cache_key( $design_id, $post_id, $lang . '-' . $gender, $text );
				if ( get_transient( $cache_key ) ) continue;  // already done
				$args = array( $design_id, $post_id, $text, $source_lang, $lang, $gender );
				if ( ! wp_next_scheduled( 'qtale_embed_gen_voice', $args ) ) {
					wp_schedule_single_event( time() + $delay, 'qtale_embed_gen_voice', $args );
					$delay += 3;
					$scheduled++;
				}
			}
		}
		if ( function_exists( 'spawn_cron' ) ) spawn_cron();
		wp_send_json_success( array( 'scheduled' => $scheduled, 'total_langs' => count( $langs ) ) );
	}

	/**
	 * Add "Q-Tale" column to admin posts list — status icon per row.
	 */
	public static function add_column( $columns ) {
		$s = Qtale_TTS::settings();
		if ( empty( $s['default_design_public_id'] ) ) return $columns;
		// Insert before "date" column
		$new = array();
		foreach ( $columns as $k => $v ) {
			if ( $k === 'date' ) $new['qtale_tts'] = __( 'Q-Tale', 'qtale-tts' );
			$new[ $k ] = $v;
		}
		if ( ! isset( $new['qtale_tts'] ) ) $new['qtale_tts'] = __( 'Q-Tale', 'qtale-tts' );
		return $new;
	}

	public static function render_column( $col, $post_id ) {
		if ( $col !== 'qtale_tts' ) return;
		$enabled = self::is_enabled_for_post( $post_id );
		$meta = get_post_meta( $post_id, self::META_KEY, true );
		if ( $meta === 'force' ) {
			echo '<span title="' . esc_attr__( 'Tvunget på (overstyrer global)', 'qtale-tts' ) . '" style="color:#16a34a;font-weight:700;">⚡ ON</span>';
		} elseif ( $meta === 'skip' ) {
			echo '<span title="' . esc_attr__( 'Tvunget av (overstyrer global)', 'qtale-tts' ) . '" style="color:#dc2626;font-weight:700;">⊘ OFF</span>';
		} elseif ( $enabled ) {
			echo '<span title="' . esc_attr__( 'På (følger standard)', 'qtale-tts' ) . '" style="color:#0a7f3a;">●</span>';
		} else {
			echo '<span title="' . esc_attr__( 'Av (følger standard)', 'qtale-tts' ) . '" style="color:#94a3b8;">○</span>';
		}
	}
}
