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

/**
 * Step 1
 * Cat & Tag Migrate
 */

$edd_cat_slug = 'download_category';
$wc_cat_slug = 'product_cat';
$wc_edd_cat_map = array();

// Fetch Cat from WC
$wc_cat_terms = get_terms( $wc_cat_slug, array( 'hide_empty' => false ) );
echo "\nWC Cat fetched ...\n";

foreach( $wc_cat_terms as $t ) {
	$edd_term = wp_insert_term( $t->name, $edd_cat_slug );
	var_dump($t);
	var_dump($edd_term);

	if( ! $edd_term instanceof WP_Error ) {
		// maintain array of category mapping
		$wc_edd_cat_map[ $t->term_id ] = $edd_term->term_id;
	} else {
		echo "\nFollowing Category not migrated : \n";
		var_dump( $t );
		var_dump( $edd_term );
	}
}
echo "\nEDD Cat migrated ...\n";

$edd_tag_slug = 'download_tag';
$wc_tag_slug = 'product_tag';
$wc_edd_tag_map = array();

// Fetch Tag from WC
$wc_tag_terms = get_terms( $wc_tag_slug, array( 'hide_empty' => false ) );
echo "\nWC Tag fetched ...\n";

foreach( $wc_tag_terms as $t ) {
	$edd_term = wp_insert_term( $t->name, $edd_tag_slug );

	if( ! $edd_term instanceof WP_Error ) {
		// maintain array of category mapping
		$wc_edd_tag_map[ $t->term_id ] = $edd_term->term_id;
	} else {
		echo "\nFollowing Tag not migrated : \n";
		var_dump( $t );
	}
}
echo "\nEDD Tag migrated ...\n";


/**
 * Step 2
 * Product Migrate
 */

$wc_product_cpt = 'product';
$edd_product_cpt = 'download';
$args = array(
	'post_type' => $wc_product_cpt,
    'posts_per_page' => -1,
    'post_status' => 'any',
);
$wc_product_list = get_posts( $args );
echo "\nWC Product fetched ...\n";
$wc_edd_product_map = array();

global $wpdb;
foreach( $wc_product_list as $p ) {
	$product = get_product( $p );
	echo "\nProduct - $p->ID\n";
	$wc_cat_terms = wp_get_post_terms( $p->ID, $wc_cat_slug );
	echo "\nWC Product Cat fetched ...\n";
	$edd_cat_terms = array();
	if ( ! $wc_cat_terms instanceof WP_Error ) {
		foreach( $wc_cat_terms as $t ) {
			if( isset( $wc_edd_cat_map[ $t->term_id ] ) ) {
				$edd_cat_terms[] = $wc_edd_cat_map[ $t->term_id ];
			}
		}
	}

	$wc_tag_terms = wp_get_post_terms( $p->ID, $wc_tag_slug );
	echo "\nWC Product Tag fetched ...\n";
	$edd_tag_terms = array();
	if ( ! $wc_tag_terms instanceof WP_Error ) {
		foreach( $wc_tag_terms as $t ) {
			if( isset( $wc_edd_tag_map[ $t->term_id ] ) ) {
				$edd_tag_terms[] = $wc_edd_tag_map[ $t->term_id ];
			}
		}
	}

	$data = array(
		'post_content' => $p->post_content,
	    'post_title' => $p->post_title,
	    'post_status' => $p->post_status,
	    'post_type' => $edd_product_cpt,
	    'post_author' => $p->post_author,
	    'post_parent' => $p->post_parent,
	    'post_excerpt' => $p->post_excerpt,
	    'post_date' => $p->post_date,
	    'post_date_gmt' => $p->post_date_gmt,
	    'comment_status' => $p->comment_status,
	    'tax_input' => array(
			$edd_cat_slug => $edd_cat_terms,
			$edd_tag_slug => $edd_tag_terms,
	    ),
	);

	$edd_product_id = wp_insert_post( $data );
	if( empty( $edd_product_id ) ) {
		echo "\nFollowing Product not migrated : \n";
		var_dump($p);
		continue;
	}
	$wc_edd_product_map[ $p->ID ] = $edd_product_id;
	echo "\nWC Product migrated ...\n";

//	$query = "SELECT DISTINCT meta_key from $$wpdb->postmeta WHERE post_id = %s";
//	$meta_keys = $wpdb->query( $wpdb->prepare( $query, $p->ID ) );
//
//	foreach( $meta_keys as $m ) {
//
//	}
}