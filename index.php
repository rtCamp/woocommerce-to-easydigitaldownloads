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
	$args = array();
	if( !empty( $t->parent ) && isset( $wc_edd_cat_map[ $t->parent ] ) ) {
		$args[ 'parent' ] = $wc_edd_cat_map[ $t->parent ];
	}
	$edd_term = wp_insert_term( $t->name, $edd_cat_slug, $args );

	if( ! $edd_term instanceof WP_Error ) {
		// maintain array of category mapping
		$wc_edd_cat_map[ $t->term_id ] = $edd_term[ 'term_id' ];
	} else {
		echo "\n$t->name -- Category not migrated because : \n";
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
		$wc_edd_tag_map[ $t->term_id ] = $edd_term[ 'term_id' ];
	} else {
		echo "\n$t->name -- Tag not migrated because : \n";
		var_dump( $edd_term );
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
				$edd_cat_terms[] = intval( $wc_edd_cat_map[ $t->term_id ] );
			}
		}
	}

	$wc_tag_terms = wp_get_object_terms( $p->ID, $wc_tag_slug );
	echo "\nWC Product Tag fetched ...\n";
	$edd_tag_terms = array();
	if ( ! $wc_tag_terms instanceof WP_Error ) {
		foreach( $wc_tag_terms as $t ) {
			if( isset( $wc_edd_tag_map[ $t->term_id ] ) ) {
				$edd_tag_terms[] = intval( $wc_edd_tag_map[ $t->term_id ] );
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
	);

	$edd_product_id = wp_insert_post( $data );
	if( empty( $edd_product_id ) ) {
		echo "\nFollowing Product not migrated : \n";
		var_dump($p);
		continue;
	}

	$wc_edd_product_map[ $p->ID ] = $edd_product_id;
	echo "\nWC Product migrated ...\n";

	// Assign Cat
	$terms = wp_set_object_terms( $edd_product_id, $edd_cat_terms, $edd_cat_slug );
	if( $terms instanceof WP_Error ) {
		echo "\nProduct Categories failed to assign ...\n";
		var_dump($terms);
		continue;
	}
	echo "\nWC Category migrated ...\n";

	// Assign Tag
	$terms = wp_set_object_terms( $edd_product_id, $edd_tag_terms, $edd_tag_slug );
	if( $terms instanceof WP_Error ) {
		echo "\nProduct Tags failed to assign ...\n";
		var_dump($terms);
		continue;
	}
	echo "\nWC Tag migrated ...\n";

	// Featured Image
	$wc_product_featured_image = get_post_thumbnail_id( $p->ID );
	if( !empty( $wc_product_featured_image ) ) {
		// $filename should be the path to a file in the upload directory.
		$filename = get_attached_file( $wc_product_featured_image );

		// The ID of the post this attachment is for.
		$parent_post_id = $edd_product_id;

		// Check the type of tile. We'll use this as the 'post_mime_type'.
		$filetype = wp_check_filetype( basename( $filename ), null );

		// Get the path to the upload directory.
		$wp_upload_dir = wp_upload_dir();

		// Prepare an array of post data for the attachment.
		$attachment = array(
			'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename ),
			'post_mime_type' => $filetype['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
			'post_content'   => '',
			'post_status'    => 'inherit'
		);

		// Insert the attachment.
		$attach_id = wp_insert_attachment( $attachment, $filename, $parent_post_id );
		if( empty( $attach_id ) ) {
			echo "\nFeature Image could not be set for Product ...\n";
			continue;
		}

		$edd_product_fi_meta_id = set_post_thumbnail( $edd_product_id, $attach_id );
		if( empty( $edd_product_fi_meta_id ) ) {
			echo "\nFeature Image could not be set for Product ...\n";
			continue;
		}
	}
	echo "\nWC Featured Image migrated ...\n";

	// Product Gallery
	$attachment_ids = $product->get_gallery_attachment_ids();
	if ( $attachment_ids ) {
		foreach ( $attachment_ids as $attachment_id ) {
			// $filename should be the path to a file in the upload directory.
			$filename = get_attached_file( $attachment_id );

			// The ID of the post this attachment is for.
			$parent_post_id = $edd_product_id;

			// Check the type of tile. We'll use this as the 'post_mime_type'.
			$filetype = wp_check_filetype( basename( $filename ), null );

			// Get the path to the upload directory.
			$wp_upload_dir = wp_upload_dir();

			// Prepare an array of post data for the attachment.
			$attachment = array(
				'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename ),
				'post_mime_type' => $filetype['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
				'post_content'   => '',
				'post_status'    => 'inherit'
			);

			// Insert the attachment.
			$attach_id = wp_insert_attachment( $attachment, $filename, $parent_post_id );
			if( empty( $attach_id ) ) {
				echo "\nGallery Image ID $attachment_id could not be set for Product ...\n";
				continue;
			}
		}
		echo "\nWC Gallery migrated ...\n";
	}

	// Downloadable Files
	if( ! class_exists( 'WP_Http' ) ) {
		include_once( ABSPATH . WPINC. '/class-http.php' );
	}
	$wc_dl_files = $product->get_files();
	$edd_dl_files = array();
	$edd_dl_files_slug = 'edd_download_files';
	foreach( $wc_dl_files as $wc_file ) {
		$file = new WP_Http();
		$file = $file->request( $wc_file[ 'file' ] );
		if( $file[ 'response' ][ 'code' ] != 200 ) {
			echo "\nDownloadable File " . $wc_file[ 'name' ] . " could not be set for Product ...\n";
			var_dump( $wc_file[ 'file' ] );
			continue;
		}

		$attachment = wp_upload_bits( basename( $wc_file[ 'file' ] ), null, $file['body'], date("Y-m", strtotime( $file[ 'headers' ][ 'last-modified' ] ) ) );
		if( ! empty( $attachment[ 'error' ] ) ) {
			echo "\nDownloadable File " . $wc_file[ 'name' ] . " could not be set for Product ...\n";
			var_dump( $wc_file[ 'file' ] );
			continue;
		}

		$filetype = wp_check_filetype( basename( $attachment[ 'file' ] ), null );
		$wp_upload_dir = wp_upload_dir();

		$postinfo = array(
			'guid'           => $wp_upload_dir[ 'url' ] . '/' . basename( $attachment[ 'file' ] ),
			'post_mime_type' => $filetype[ 'type' ],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $attachment[ 'file' ] ) ),
			'post_content'   => '',
			'post_status'    => 'inherit'
		);
		$filename = $attachment[ 'file' ];
		$attach_id = wp_insert_attachment( $postinfo, $filename, $edd_product_id );
		if( empty( $attach_id ) ) {
			echo "\nDownloadable File " . $wc_file[ 'name' ] . " could not be set for Product ...\n";
			var_dump( $wc_file[ 'file' ] );
			continue;
		}

		$edd_dl_files[] = array(
			'attachment_id' => $attach_id,
		    'name' => basename( $attachment[ 'file' ] ),
		    'file' => $attachment[ 'file' ],
		);
	}

	if( !empty( $edd_dl_files ) ) {
		update_post_meta( $edd_product_id, $edd_dl_files_slug, $edd_dl_files );
		echo "\nWC Downloadable Files migrated ...\n";
	}

	// Download Limit
	$edd_dl_limit_slug = '_edd_download_limit';
	$wc_dl_limit_slug = '_download_limit';
	update_post_meta( $edd_product_id, $edd_dl_limit_slug, get_post_meta( $p->ID, $wc_dl_limit_slug, true ) );
	echo "\nWC Download Limit : " . get_post_meta( $p->ID, $wc_dl_limit_slug, true ) . " migrated ...\n";

	// Price
	$edd_product_price_slug = 'edd_price';
	$wc_product_price_slug = '_regular_price';
	update_post_meta( $edd_product_id, $edd_product_price_slug, get_post_meta( $p->ID, $wc_product_price_slug, true ) );
	echo "\nWC Product Price : " . get_post_meta( $p->ID, $wc_product_price_slug, true ) . " migrated ...\n";

	// Sales
	$edd_product_sales_slug = '_edd_download_sales';
	$wc_product_sales_slug = 'total_sales';
	update_post_meta( $edd_product_id, $edd_product_sales_slug, get_post_meta( $p->ID, $wc_product_sales_slug, true ) );
	echo "\nWC Product Total Sales : " . get_post_meta( $p->ID, $wc_product_sales_slug, true ) . " migrated ...\n";

	// Earnings
	// TODO - Do it when migrating orders i.e. Payment History
}

/**
 * Coupons
 */


/**
 * Orders
 */
