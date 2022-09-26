<?php
/*
	Plugin Name: WP Downloader Plus
	Plugin URI: https://pluginbazar.com/
	Description: This plugin for download WordPress plugin and theme.
	Version: 1.0.0
	Author: Pluginbazar
	Text Domain: woc-order-alert
	Author URI: https://pluginbazar.com/
	License: GPLv2 or later
	License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) || exit;
defined( 'WPDB_PLUGIN_URL' ) || define( 'WPDB_PLUGIN_URL', WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/' );
defined( 'WPDB_PLUGIN_DIR' ) || define( 'WPDB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
defined( 'WPDB_PLUGIN_FILE' ) || define( 'WPDB_PLUGIN_FILE', plugin_basename( __FILE__ ) );
defined( 'WPDB_PLUGIN_VERSION' ) || define( 'WPDB_PLUGIN_VERSION', '1.0.0' );

if ( ! class_exists( 'WPDP_Main' ) ) {
	/**
	 * Class WPDP_Main
	 */
	class WPDP_Main {

		protected static $_instance = null;

		protected static $_script_version = null;

		/**
		 * WPDP_Main constructor.
		 */
		function __construct() {

			self::$_script_version = defined( 'WP_DEBUG' ) && WP_DEBUG ? current_time( 'U' ) : WPDB_PLUGIN_VERSION;

			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
			add_filter( 'plugin_action_links', array( $this, 'add_download_btn_to_plugins' ), 10, 4 );
			add_action( 'admin_init', array( $this, 'download_object' ) );
		}


		/**
		 * Handle downloading the object
		 */
		function download_object() {

			if ( ! isset( $_GET['wpdp'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'wpdp-download' ) ) {
				return;
			}

			if ( ! class_exists( 'PclZip' ) ) {
				include ABSPATH . 'wp-admin/includes/class-pclzip.php';
			}

			$context = isset( $_GET['wpdp'] ) ? sanitize_text_field( $_GET['wpdp'] ) : '';
			$object  = isset( $_GET['object'] ) ? sanitize_text_field( $_GET['object'] ) : '';

			switch ( $context ) {
				case 'plugin':
					if ( strpos( $object, '/' ) ) {
						$object = dirname( $object );
					}
					$root = WP_PLUGIN_DIR;
					break;
				case 'muplugin':
					if ( strpos( $object, '/' ) ) {
						$object = dirname( $object );
					}
					$root = WPMU_PLUGIN_DIR;
					break;
				case 'theme':
					$root = get_theme_root( $object );
					break;
				default:
					wp_die( esc_html__( 'Something went wrong!', 'wp-downloader-plus' ) );
			}

			$object = sanitize_file_name( $object );

			if ( empty( $object ) ) {
				wp_die( esc_html__( 'Something went wrong!', 'wp-downloader-plus' ) );
			}

			$path       = $root . '/' . $object;
			$fileName   = $object . '.zip';
			$upload_dir = wp_upload_dir();
			$tmpFile    = trailingslashit( $upload_dir['path'] ) . $fileName;
			$archive    = new PclZip( $tmpFile );

			$archive->add( $path, PCLZIP_OPT_REMOVE_PATH, $root );

			header( 'Content-type: application/zip' );
			header( 'Content-Disposition: attachment; filename="' . $fileName . '"' );

			readfile( $tmpFile );
			unlink( $tmpFile );

			exit;
		}


		/**
		 * Add download button to plugins list page.
		 *
		 * @param $links
		 * @param $file
		 * @param $plugin_data
		 * @param $context
		 *
		 * @return mixed
		 */
		function add_download_btn_to_plugins( $links, $file, $plugin_data, $context ) {

			if ( 'dropins' === $context ) {
				return $links;
			}

			$what = ( 'mustuse' === $context ) ? 'muplugin' : 'plugin';

			$links['wpdp-download'] = sprintf( '<a href="%s">%s</a>', $this->get_object_download_link( $file, $what ), esc_html__( 'Download' ) );

			return $links;
		}


		/**
		 * Return object download link
		 *
		 * @param string $object
		 * @param string $object_type
		 *
		 * @return mixed|void
		 */
		function get_object_download_link( $download_object = '', $object_type = 'plugin' ) {

			$download_object = empty( $download_object ) ? 'object_name' : $download_object;
			$download_query  = build_query( array( 'wpdp' => $object_type, 'object' => $download_object ) );
			$download_link   = wp_nonce_url( admin_url( '?' . $download_query ), 'wpdp-download' );

			return apply_filters( 'WPDP_Main/Filters/get_object_download_link', $download_link, $download_object, $object_type );
		}

		/**
		 * Admin Scripts
		 */
		function admin_scripts() {

			wp_enqueue_script( 'wpdp-admin', plugins_url( '/assets/admin/js/scripts.js', __FILE__ ), array( 'jquery' ), self::$_script_version, true );
			wp_localize_script( 'wpdp-admin', 'wpdp', array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'themeDownloadText' => esc_html__( 'Download' ),
				'themeDownloadLink' => $this->get_object_download_link( '', 'theme' ),
			) );
		}


		/**
		 * @return WPDP_Main|null
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}
	}
}

WPDP_Main::instance();