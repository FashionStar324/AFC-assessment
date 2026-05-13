<?php
/**
 * Plugin Name:       Partner Directory
 * Plugin URI:        https://github.com/fashionStar324/partner-directory
 * Description:       Manage and display a directory of Partner Organizations.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Donald Woodward
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       partner-directory
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PARTNER_DIR_VERSION', '1.0.0' );
define( 'PARTNER_DIR_FILE', __FILE__ );
define( 'PARTNER_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'PARTNER_DIR_URL', plugin_dir_url( __FILE__ ) );

require_once PARTNER_DIR_PATH . 'includes/class-partner-cpt.php';
require_once PARTNER_DIR_PATH . 'includes/class-partner-meta.php';
require_once PARTNER_DIR_PATH . 'includes/class-partner-rest-api.php';
require_once PARTNER_DIR_PATH . 'includes/class-partner-block.php';

function partner_directory_init(): void {
	Partner_CPT::register();
	Partner_Meta::register();
	Partner_REST_API::register();
	Partner_Block::register();
}
add_action( 'init', 'partner_directory_init' );
