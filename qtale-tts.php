<?php
/**
 * Plugin Name:       Q-Tale TTS
 * Plugin URI:        https://qtale.no/
 * Description:       Embed text-to-speech audio players in WordPress posts with the [qtale] shortcode. Norwegian focus with Sámi (Giellalt) support and 25+ languages. Requires a Q-Tale account — sign up at <a href="https://qtale.no/priser">qtale.no/priser</a>. Service by Nils Otto Lillegrein at <a href="https://activeweb.no">ActiveWEB AS</a>.
 * Version:           2.6.26
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Nils Otto Lillegrein
 * Author URI:        https://activeweb.no/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       qtale-tts
 *
 * @package Qtale_TTS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QTALE_TTS_VERSION', '2.6.26' );
define( 'QTALE_TTS_FILE', __FILE__ );
define( 'QTALE_TTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'QTALE_TTS_URL', plugin_dir_url( __FILE__ ) );

require_once QTALE_TTS_DIR . 'includes/class-qtale-tts.php';
require_once QTALE_TTS_DIR . 'includes/class-qtale-tts-client.php';
require_once QTALE_TTS_DIR . 'includes/class-qtale-tts-settings.php';
require_once QTALE_TTS_DIR . 'includes/class-qtale-tts-shortcode.php';
require_once QTALE_TTS_DIR . 'includes/class-qtale-tts-auto.php';
require_once QTALE_TTS_DIR . 'includes/class-qtale-tts-prefill.php';
require_once QTALE_TTS_DIR . 'includes/class-qtale-tts-post-meta.php';

register_activation_hook( __FILE__, array( 'Qtale_TTS', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Qtale_TTS', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Qtale_TTS', 'boot' ) );
