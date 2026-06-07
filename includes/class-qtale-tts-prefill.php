<?php
/**
 * Reads a per-customer prefill JSON (api key, default voice, design tied to
 * tier) on activation. Self-destructs after hydrating so the key doesn't
 * linger on the file system once WP options are populated.
 *
 * @package Qtale_TTS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Qtale_TTS_Prefill {

	const FILE = 'qtale-prefill.json';

	/**
	 * Called from Qtale_TTS::activate(). Idempotent: bails if nothing to read.
	 * Returns true if hydration happened, false otherwise.
	 */
	public static function maybe_hydrate() {
		$path = QTALE_TTS_DIR . self::FILE;
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return false;
		}
		$raw = file_get_contents( $path );
		if ( ! $raw ) {
			return false;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return false;
		}

		$defaults = Qtale_TTS::default_settings();
		$existing = get_option( Qtale_TTS::OPTION_KEY, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		// First install = no saved settings yet. On first install we apply the
		// prefill values OVER the boilerplate defaults (so the tier-correct
		// default_design — e.g. 'beaivi' for the Beaivi tier — actually takes effect
		// instead of the hardcoded 'odin'). On later re-activations we only fill
		// fields the user left empty, so we never clobber a customised setting.
		$first_install = empty( $existing );
		$merged = array_merge( $defaults, $existing );

		foreach ( array( 'api_key', 'default_voice', 'default_design', 'default_design_public_id', 'default_theme', 'api_base', 'cdn_base' ) as $field ) {
			if ( ! isset( $data[ $field ] ) ) {
				continue;
			}
			if ( $first_install || empty( $merged[ $field ] ) ) {
				$merged[ $field ] = $data[ $field ];
			}
		}
		// Always overwrite if explicitly given for first-time install (api_key empty above filters this).
		update_option( Qtale_TTS::OPTION_KEY, $merged );

		// Self-destruct so the API key doesn't sit in the file system unnecessarily.
		@unlink( $path );
		return true;
	}
}
