<?php
/**
 * Thin Q-Tale API client. Uses wp_remote_post / wp_remote_get so it works behind
 * proxies and respects WP HTTP filters. All endpoints require an X-API-Key header.
 *
 * @package Qtale_TTS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qtale_TTS_Client {

	private $api_base;
	private $api_key;

	public function __construct( $api_base, $api_key ) {
		$this->api_base = untrailingslashit( $api_base );
		$this->api_key  = trim( $api_key );
	}

	private function headers() {
		return array(
			'X-API-Key'    => $this->api_key,
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		);
	}

	private function configured() {
		return $this->api_key !== '' && $this->api_base !== '';
	}

	/**
	 * Submits a generate job. Returns array(job_id, status, chars) or WP_Error.
	 */
	public function generate( $text, $voice_id, $params = array() ) {
		if ( ! $this->configured() ) {
			return new WP_Error( 'qtale_not_configured', __( 'Q-Tale API-nøkkel mangler.', 'qtale-tts' ) );
		}
		$body = array_merge(
			array( 'text' => $text ),
			$voice_id ? array( 'voice_id' => $voice_id ) : array(),
			$params
		);
		$resp = wp_remote_post(
			$this->api_base . '/api/v1/generate',
			array(
				'headers' => $this->headers(),
				'body'    => wp_json_encode( $body ),
				'timeout' => 15,
			)
		);
		return $this->parse( $resp );
	}

	/** Submit-only generate (no polling). Returns job_id immediately or WP_Error. */
	public function generate_submit( $text, $voice_id, $params = array() ) {
		$gen = $this->generate( $text, $voice_id, $params );
		if ( is_wp_error( $gen ) ) return $gen;
		if ( empty( $gen['job_id'] ) ) return new WP_Error( 'qtale_no_job', __( 'Uventet API-svar.', 'qtale-tts' ) );
		return $gen['job_id'];
	}

	/** Bulk counts of customer's jobs since a timestamp (ISO 8601). */
	public function jobs_summary( $since_iso = '' ) {
		if ( ! $this->configured() ) {
			return new WP_Error( 'qtale_not_configured', __( 'Q-Tale API-nøkkel mangler.', 'qtale-tts' ) );
		}
		$url = $this->api_base . '/api/v1/jobs-summary';
		if ( $since_iso ) $url .= '?since=' . rawurlencode( $since_iso );
		$resp = wp_remote_get( $url, array( 'headers' => $this->headers(), 'timeout' => 6 ) );
		return $this->parse( $resp );
	}

	/**
	 * Find existing done-job audio by text+voice — bypass cache_key mismatch.
	 * Returns array(found, audio_url, duration, job_id) or WP_Error.
	 */
	public function find_audio( $text, $voice_id ) {
		if ( ! $this->configured() ) {
			return new WP_Error( 'qtale_not_configured', __( 'Q-Tale API-nøkkel mangler.', 'qtale-tts' ) );
		}
		$resp = wp_remote_post(
			$this->api_base . '/api/v1/find-audio',
			array(
				'headers' => $this->headers(),
				'body'    => wp_json_encode( array( 'text' => $text, 'voice_id' => $voice_id ) ),
				'timeout' => 6,
			)
		);
		return $this->parse( $resp );
	}

	public function status( $job_id ) {
		if ( ! $this->configured() ) {
			return new WP_Error( 'qtale_not_configured', __( 'Q-Tale API-nøkkel mangler.', 'qtale-tts' ) );
		}
		$resp = wp_remote_get(
			$this->api_base . '/api/v1/status/' . rawurlencode( $job_id ),
			array(
				'headers' => $this->headers(),
				'timeout' => 8,
			)
		);
		return $this->parse( $resp );
	}

	/**
	 * Fetches /api/v1/me — returns tier, allowed_designs, company info.
	 * Used by the settings page to tier-gate the design picker.
	 */
	public function me() {
		if ( ! $this->configured() ) {
			return new WP_Error( 'qtale_not_configured', __( 'Q-Tale API-nøkkel mangler.', 'qtale-tts' ) );
		}
		$resp = wp_remote_get(
			$this->api_base . '/api/v1/me',
			array( 'headers' => $this->headers(), 'timeout' => 8 )
		);
		return $this->parse( $resp );
	}

	/**
	 * Fetches customer's saved player designs from Player Design Studio.
	 * Returns array(ok, count, designs:[{public_id, name, config, shortcode, updated_at}]) or WP_Error.
	 *
	 * NOTE: This endpoint lives on app.qtale.no (the portal), NOT api.qtale.no.
	 * We read app_base from settings instead of using api_base.
	 */
	public function player_designs() {
		if ( ! $this->configured() ) {
			return new WP_Error( 'qtale_not_configured', __( 'Q-Tale API-nøkkel mangler.', 'qtale-tts' ) );
		}
		$s = Qtale_TTS::settings();
		$app_base = ! empty( $s['app_base'] ) ? $s['app_base'] : 'https://app.qtale.no';
		$resp = wp_remote_get(
			untrailingslashit( $app_base ) . '/api/public/player-designs',
			array( 'headers' => $this->headers(), 'timeout' => 10 )
		);
		return $this->parse( $resp );
	}

	/**
	 * Translate text via Azure Translator. Returns array('translated','engine')
	 * or WP_Error. Used by [qtale-player id] shortcode for multi-lang pre-gen.
	 */
	public function translate_text( $text, $source_lang, $target_lang ) {
		if ( ! $this->configured() ) {
			return new WP_Error( 'qtale_not_configured', __( 'Q-Tale API-nøkkel mangler.', 'qtale-tts' ) );
		}
		if ( $source_lang === $target_lang ) {
			return array( 'translated' => $text, 'engine' => 'noop' );
		}
		$resp = wp_remote_post(
			$this->api_base . '/api/v1/translate',
			array(
				'headers' => $this->headers(),
				'body'    => wp_json_encode( array(
					'text'        => $text,
					'source_lang' => $source_lang,
					'target_lang' => $target_lang,
				) ),
				'timeout' => 20,
			)
		);
		return $this->parse( $resp );
	}

	/**
	 * HTML-strukturert oversettelse for Translation Modal PDF-eksport.
	 * Server velger Azure (textType=html) under månedscap; over cap → Opus DOM-walk.
	 * Tags som h1-h6, p, ul, ol, li, blockquote, strong, em, a bevares.
	 */
	public function translate_html( $html, $source_lang, $target_lang ) {
		if ( ! $this->configured() ) {
			return new WP_Error( 'qtale_not_configured', __( 'Q-Tale API-nøkkel mangler.', 'qtale-tts' ) );
		}
		if ( $source_lang === $target_lang ) {
			return array( 'translated' => $html, 'engine' => 'noop' );
		}
		$resp = wp_remote_post(
			$this->api_base . '/api/v1/translate-html',
			array(
				'headers' => $this->headers(),
				'body'    => wp_json_encode( array(
					'text'        => $html,
					'source_lang' => $source_lang,
					'target_lang' => $target_lang,
				) ),
				'timeout' => 60,
			)
		);
		return $this->parse( $resp );
	}

	/**
	 * Look up a single player design by public_id — no auth required (public_id IS auth).
	 * Used by [qtale-player id] shortcode. Endpoint lives on app.qtale.no.
	 */
	public function player_design_by_id( $public_id ) {
		$s = Qtale_TTS::settings();
		$app_base = ! empty( $s['app_base'] ) ? $s['app_base'] : 'https://app.qtale.no';
		$resp = wp_remote_get(
			untrailingslashit( $app_base ) . '/api/public/player-designs/' . rawurlencode( $public_id ),
			array( 'timeout' => 8 )
		);
		return $this->parse( $resp );
	}

	public function voices( $language = '' ) {
		if ( ! $this->configured() ) {
			return new WP_Error( 'qtale_not_configured', __( 'Q-Tale API-nøkkel mangler.', 'qtale-tts' ) );
		}
		$url = $this->api_base . '/api/v1/voices';
		if ( $language ) {
			$url .= '?language=' . rawurlencode( $language );
		}
		$resp = wp_remote_get( $url, array( 'headers' => $this->headers(), 'timeout' => 8 ) );
		return $this->parse( $resp );
	}

	/**
	 * Convenience: generate + poll status until completed or timeout (seconds).
	 * Returns array(audio_url, duration, ...) or WP_Error (incl. timeout).
	 */
	public function generate_and_wait( $text, $voice_id, $params = array(), $timeout_seconds = 6 ) {
		$gen = $this->generate( $text, $voice_id, $params );
		if ( is_wp_error( $gen ) ) {
			return $gen;
		}
		if ( empty( $gen['job_id'] ) ) {
			return new WP_Error( 'qtale_no_job', __( 'Uventet API-svar.', 'qtale-tts' ) );
		}
		$job_id   = $gen['job_id'];
		$deadline = microtime( true ) + (float) $timeout_seconds;
		$delay    = 400000; // 0.4 s — increases exponentially
		while ( microtime( true ) < $deadline ) {
			usleep( $delay );
			$st = $this->status( $job_id );
			if ( is_wp_error( $st ) ) {
				return $st;
			}
			if ( ! empty( $st['status'] ) && $st['status'] === 'completed' && ! empty( $st['audio_url'] ) ) {
				$st['job_id'] = $job_id;
				return $st;
			}
			if ( ! empty( $st['status'] ) && $st['status'] === 'failed' ) {
				return new WP_Error( 'qtale_job_failed', $st['error'] ? $st['error'] : __( 'Generering feilet.', 'qtale-tts' ) );
			}
			$delay = (int) min( $delay * 1.5, 1500000 );
		}
		// Timed out — return job_id so a future request (or async polling) can pick it up.
		return new WP_Error( 'qtale_timeout', __( 'Generering tar lengre tid enn forventet.', 'qtale-tts' ), array( 'job_id' => $job_id ) );
	}

	private function parse( $resp ) {
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = wp_remote_retrieve_response_code( $resp );
		$body = wp_remote_retrieve_body( $resp );
		$data = json_decode( $body, true );
		if ( $code >= 400 ) {
			$msg = is_array( $data ) && ! empty( $data['error'] )
				? $data['error']
				: sprintf( /* translators: %d: HTTP status code. */ __( 'API-feil (HTTP %d).', 'qtale-tts' ), $code );
			return new WP_Error( 'qtale_http_' . $code, $msg, array( 'http_code' => $code ) );
		}
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'qtale_bad_json', __( 'Ugyldig JSON fra Q-Tale.', 'qtale-tts' ) );
		}
		return $data;
	}
}
