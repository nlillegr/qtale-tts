<?php
/**
 * Fires on plugin uninstall. Removes plugin options and clears the audio cache transients.
 *
 * @package Qtale_TTS
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'qtale_tts_settings' );
delete_option( 'qtale_tts_player_designs' );
delete_option( 'qtale_tts_backfill_state' );

global $wpdb;
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE
	   option_name LIKE '\\_transient\\_qtale\\_tts\\_%' OR option_name LIKE '\\_transient\\_timeout\\_qtale\\_tts\\_%'
	OR option_name LIKE '\\_transient\\_qtale\\_design\\_%' OR option_name LIKE '\\_transient\\_timeout\\_qtale\\_design\\_%'
	OR option_name LIKE '\\_transient\\_qtale\\_emb\\_%'   OR option_name LIKE '\\_transient\\_timeout\\_qtale\\_emb\\_%'"
);
