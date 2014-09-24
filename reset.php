<?php
/**
 * WordPress Load
 */

if ( ! defined( 'WP_LOAD_PATH' ) ) {
	$path ="../../../";
	if ( file_exists( $path . 'wp-load.php' ) )
		define( 'WP_LOAD_PATH', $path );
	else
		exit( "Could not find wp-load.php" );
}

require_once( WP_LOAD_PATH . 'wp-load.php');

echo "\nWP Loaded ...\n";

/**
 * Detect plugin. For use on Front End only.
 */
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

/**
 * Check for required Plugins
 */

if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) || ! is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) ) {
	exit( 'WC & EDD Not Activated.' );
}
echo "\nWC & EDD activated ...\n";

global $wpdb, $edd_options, $wp_roles;

/** Delete All the Custom Post Types */
$edd_taxonomies = array( 'download_category', 'download_tag', 'edd_log_type', );
$edd_post_types = array( 'download', 'edd_payment', 'edd_discount', 'edd_log' );
foreach ( $edd_post_types as $post_type ) {

	$edd_taxonomies = array_merge( $edd_taxonomies, get_object_taxonomies( $post_type ) );
	$items = get_posts( array( 'post_type' => $post_type, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );

	if ( $items ) {
		foreach ( $items as $item ) {
			wp_delete_post( $item, true);
		}
	}
}

/** Delete All the Terms & Taxonomies */
foreach ( array_unique( array_filter( $edd_taxonomies ) ) as $taxonomy ) {

	$terms = $wpdb->get_results( $wpdb->prepare( "SELECT t.*, tt.* FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy IN ('%s') ORDER BY t.name ASC", $taxonomy ) );

	// Delete Terms
	if ( $terms ) {
		foreach ( $terms as $term ) {
			$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $term->term_taxonomy_id ) );
			$wpdb->delete( $wpdb->terms, array( 'term_id' => $term->term_id ) );
		}
	}

	// Delete Taxonomies
	$wpdb->delete( $wpdb->term_taxonomy, array( 'taxonomy' => $taxonomy ), array( '%s' ) );
}

/** Delete the Plugin Pages */
$edd_created_pages = array( 'purchase_page', 'success_page', 'failure_page', 'purchase_history_page' );
foreach ( $edd_created_pages as $p ) {
	if ( isset( $edd_options[ $p ] ) ) {
		wp_delete_post( $edd_options[ $p ], true );
	}
}

/** Delete all the Plugin Options */
//delete_option( 'edd_settings' );

/** Delete Capabilities */
//EDD()->roles->remove_caps();

/** Delete the Roles */
//$edd_roles = array( 'shop_manager', 'shop_accountant', 'shop_worker', 'shop_vendor' );
//foreach ( $edd_roles as $role ) {
//	remove_role( $role );
//}

// Remove all database tables
//$wpdb->query( "DROP TABLE IF EXISTS " . $wpdb->prefix . "edd_customers" );

/** Cleanup Cron Events */
//wp_clear_scheduled_hook( 'edd_daily_scheduled_events' );
//wp_clear_scheduled_hook( 'edd_daily_cron' );
//wp_clear_scheduled_hook( 'edd_weekly_cron' );
