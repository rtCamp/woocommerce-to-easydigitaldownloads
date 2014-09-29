<?php

/**
 * Extra Functions used in Script
 */

/**
 * Inserts Attachment with Parent Post as $edd_product_id
 *
 * @param $old_attachment_id
 * @param $edd_product_id
 *
 * @return int
 */
function wc_edd_insert_attachment( $old_attachment_id, $edd_product_id ) {
	// $filename should be the path to a file in the upload directory.
	$filename = get_attached_file( $old_attachment_id );

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
	return $attach_id;
}

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

$log_str = '';

$temp_log_str = "\nWP Loaded ...\n";
$log_str .= $temp_log_str;
echo $temp_log_str;

/**
 * Detect plugin.
 * Check for required Plugins
 */
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) || ! is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) ) {
	exit( 'WC & EDD Not Activated.' );
}
$temp_log_str = "\nWC & EDD activated ...\n";
$log_str .= $temp_log_str;
echo $temp_log_str;

/**
 * Step 1
 * Category & Tag Migrate
 */

$edd_cat_slug = 'download_category';
$wc_cat_slug = 'product_cat';
$wc_edd_cat_map = array();

// Fetch Category from WC
$wc_cat_terms = get_terms( $wc_cat_slug, array( 'hide_empty' => false ) );
$temp_log_str = "\nWC Cat fetched ...\n";
$log_str .= $temp_log_str;
echo $temp_log_str;

foreach( $wc_cat_terms as $t ) {
	$args = array();
	// Check for Parent Term; if any
	if( !empty( $t->parent ) && isset( $wc_edd_cat_map[ $t->parent ] ) ) {
		$args[ 'parent' ] = $wc_edd_cat_map[ $t->parent ];
	}
	$edd_term = wp_insert_term( $t->name, $edd_cat_slug, $args );

	if( ! $edd_term instanceof WP_Error ) {
		// maintain array of category mapping
		$wc_edd_cat_map[ $t->term_id ] = $edd_term[ 'term_id' ];
	} else {
		$temp_log_str = "\n$t->name -- Category not migrated because : \n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;
		$temp_log_str = var_export($edd_term, true);
		$log_str .= $temp_log_str;
		echo $temp_log_str;
	}
}
$temp_log_str = "\nEDD Category migrated ...\n";
$log_str .= $temp_log_str;
echo $temp_log_str;

$edd_tag_slug = 'download_tag';
$wc_tag_slug = 'product_tag';
$wc_edd_tag_map = array();

// Fetch Tag from WC
$wc_tag_terms = get_terms( $wc_tag_slug, array( 'hide_empty' => false ) );
$temp_log_str = "\nWC Tag fetched ...\n";
$log_str .= $temp_log_str;
echo $temp_log_str;

foreach( $wc_tag_terms as $t ) {
	$edd_term = wp_insert_term( $t->name, $edd_tag_slug );

	if( ! $edd_term instanceof WP_Error ) {
		// maintain array of tag mapping
		$wc_edd_tag_map[ $t->term_id ] = $edd_term[ 'term_id' ];
	} else {
		$temp_log_str = "\n$t->name -- Tag not migrated because : \n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;
		$temp_log_str = var_export( $edd_term, true );
		$log_str .= $temp_log_str;
		echo $temp_log_str;
	}
}
$temp_log_str = "\nEDD Tag migrated ...\n";
$log_str .= $temp_log_str;
echo $temp_log_str;


/**
 * Step 2
 * Product Migrate
 */

$wc_product_cpt = 'product';
$edd_product_cpt = 'download';

// Fetch WC Products
$args = array(
	'post_type' => $wc_product_cpt,
    'posts_per_page' => -1,
    'post_status' => 'any',
);
$wc_product_list = get_posts( $args );
$temp_log_str = "\nWC Product fetched ...\n";
$log_str .= $temp_log_str;
echo $temp_log_str;

$wc_edd_product_map = array();

foreach( $wc_product_list as $p ) {

	// WC Product Object
	$product = get_product( $p );
	$temp_log_str = "\nProduct - $p->ID\n";
	$log_str .= $temp_log_str;
	echo $temp_log_str;

	// Fetch WC Categories
	$wc_cat_terms = wp_get_post_terms( $p->ID, $wc_cat_slug );
	$temp_log_str = "\nWC Product Category fetched ...\n";
	$log_str .= $temp_log_str;
	echo $temp_log_str;
	$edd_cat_terms = array();
	if ( ! $wc_cat_terms instanceof WP_Error ) {
		foreach( $wc_cat_terms as $t ) {
			if( isset( $wc_edd_cat_map[ $t->term_id ] ) ) {
				$edd_cat_terms[] = intval( $wc_edd_cat_map[ $t->term_id ] );
			}
		}
	}

	// Fetch WC Tags
	$wc_tag_terms = wp_get_object_terms( $p->ID, $wc_tag_slug );
	$temp_log_str = "\nWC Product Tag fetched ...\n";
	$log_str .= $temp_log_str;
	echo $temp_log_str;
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
		$temp_log_str = "\nFollowing Product not migrated : \n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;
		$temp_log_str = var_export( $p, true );
		$log_str .= $temp_log_str;
		echo $temp_log_str;
		continue;
	}

	$wc_edd_product_map[ $p->ID ] = $edd_product_id;
	$temp_log_str = "\nWC Product migrated ...\n";
	$log_str .= $temp_log_str;
	echo $temp_log_str;

	update_post_meta( $edd_product_id, '_wc_product_id', $p->ID );

	// Assign Category
	$terms = wp_set_object_terms( $edd_product_id, $edd_cat_terms, $edd_cat_slug );
	if( $terms instanceof WP_Error ) {
		$temp_log_str = "\nProduct Categories failed to assign ...\n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;
		$temp_log_str = var_export( $terms, true );
		$log_str .= $temp_log_str;
		echo $temp_log_str;
		continue;
	}
	$temp_log_str = "\nWC Category migrated ...\n";
	$log_str .= $temp_log_str;
	echo $temp_log_str;

	// Assign Tag
	$terms = wp_set_object_terms( $edd_product_id, $edd_tag_terms, $edd_tag_slug );
	if( $terms instanceof WP_Error ) {
		$temp_log_str = "\nProduct Tags failed to assign ...\n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;
		$temp_log_str = var_export( $terms, true );
		$log_str .= $temp_log_str;
		echo $temp_log_str;
		continue;
	}
	$temp_log_str = "\nWC Tag migrated ...\n";
	$log_str .= $temp_log_str;
	echo $temp_log_str;

	// Featured Image
	$wc_product_featured_image = get_post_thumbnail_id( $p->ID );

	if( !empty( $wc_product_featured_image ) ) {

		// insert new attachment for new product
		$attach_id = wc_edd_insert_attachment( $wc_product_featured_image, $edd_product_id );
		if( empty( $attach_id ) ) {
			$temp_log_str = "\nFeature Image could not be set for Product ...\n";
			$log_str .= $temp_log_str;
			echo $temp_log_str;
			continue;
		}

		// Set featured image
		$edd_product_fi_meta_id = set_post_thumbnail( $edd_product_id, $attach_id );
		if( empty( $edd_product_fi_meta_id ) ) {
			$temp_log_str = "\nFeature Image could not be set for Product ...\n";
			$log_str .= $temp_log_str;
			echo $temp_log_str;
			continue;
		}
	}
	$temp_log_str = "\nWC Featured Image migrated ...\n";
	$log_str .= $temp_log_str;
	echo $temp_log_str;

	// Product Gallery
	$attachment_ids = $product->get_gallery_attachment_ids();
	if ( $attachment_ids ) {
		foreach ( $attachment_ids as $attachment_id ) {

			// insert new attachment for new product
			$attach_id = wc_edd_insert_attachment( $attachment_id, $edd_product_id );

			if( empty( $attach_id ) ) {
				$temp_log_str = "\nGallery Image ID $attachment_id could not be set for Product ...\n";
				$log_str .= $temp_log_str;
				echo $temp_log_str;
				continue;
			}
		}
		$temp_log_str = "\nWC Gallery migrated ...\n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;
	}

	// Downloadable Files

	if( ! class_exists( 'WP_Http' ) ) {
		include_once( ABSPATH . WPINC. '/class-http.php' );
	}

	$wc_dl_files = $product->get_files();
	$edd_dl_files = array();
	$edd_dl_files_slug = 'edd_download_files';

	foreach( $wc_dl_files as $wc_file ) {
		// To download file from the url
		$file = new WP_Http();
		$file = $file->request( $wc_file[ 'file' ] );
		if( $file[ 'response' ][ 'code' ] != 200 ) {
			$temp_log_str = "\nDownloadable File " . $wc_file[ 'name' ] . " could not be set for Product ...\n";
			$log_str .= $temp_log_str;
			echo $temp_log_str;
			$temp_log_str = var_export( $wc_file[ 'file' ], true );
			$log_str .= $temp_log_str;
			echo $temp_log_str;
			continue;
		}

		// Upload downloaded url to WP Upload directory
		$attachment = wp_upload_bits( basename( $wc_file[ 'file' ] ), null, $file['body'], date("Y-m", strtotime( $file[ 'headers' ][ 'last-modified' ] ) ) );
		if( ! empty( $attachment[ 'error' ] ) ) {
			$temp_log_str = "\nDownloadable File " . $wc_file[ 'name' ] . " could not be set for Product ...\n";
			$log_str .= $temp_log_str;
			echo $temp_log_str;
			$temp_log_str = var_export( $wc_file[ 'file' ], true );
			$log_str .= $temp_log_str;
			echo $temp_log_str;
			continue;
		}

		$filetype = wp_check_filetype( basename( $attachment[ 'file' ] ), null );
		$wp_upload_dir = wp_upload_dir();

		// Insert attachment for uploaded file
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
			$temp_log_str = "\nDownloadable File " . $wc_file[ 'name' ] . " could not be set for Product ...\n";
			$log_str .= $temp_log_str;
			echo $temp_log_str;
			$temp_log_str = var_export( $wc_file[ 'file' ], true );
			$log_str .= $temp_log_str;
			echo $temp_log_str;
			continue;
		}

		// Prepare aray entry for downloaded file
		$edd_dl_files[] = array(
			'attachment_id' => $attach_id,
		    'name' => basename( $attachment[ 'file' ] ),
		    'file' => $attachment[ 'file' ],
		);
	}

	// Store downloadable files into meta table
	if( !empty( $edd_dl_files ) ) {
		update_post_meta( $edd_product_id, $edd_dl_files_slug, $edd_dl_files );
		$temp_log_str = "\nWC Downloadable Files migrated ...\n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;
	}

	// Download Limit
	// Take old value from WC meta and save it into EDD meta.
	$edd_dl_limit_slug = '_edd_download_limit';
	$wc_dl_limit_slug = '_download_limit';
	update_post_meta( $edd_product_id, $edd_dl_limit_slug, get_post_meta( $p->ID, $wc_dl_limit_slug, true ) );
	$temp_log_str = "\nWC Download Limit : " . get_post_meta( $p->ID, $wc_dl_limit_slug, true ) . " migrated ...\n";
	$log_str .= $temp_log_str;
	echo $temp_log_str;

	// Price
	// Take old value from WC meta and save it into EDD meta.
	$edd_product_price_slug = 'edd_price';
	$wc_product_price_slug = '_regular_price';
	update_post_meta( $edd_product_id, $edd_product_price_slug, get_post_meta( $p->ID, $wc_product_price_slug, true ) );
	$temp_log_str = "\nWC Product Price : " . get_post_meta( $p->ID, $wc_product_price_slug, true ) . " migrated ...\n";
	$log_str .= $temp_log_str;
	echo $temp_log_str;

	// Sales
	// Take old value from WC meta and save it into EDD meta.
	$edd_product_sales_slug = '_edd_download_sales';
	$wc_product_sales_slug = 'total_sales';
	update_post_meta( $edd_product_id, $edd_product_sales_slug, get_post_meta( $p->ID, $wc_product_sales_slug, true ) );
	$temp_log_str = "\nWC Product Total Sales : " . get_post_meta( $p->ID, $wc_product_sales_slug, true ) . " migrated ...\n";
	$log_str .= $temp_log_str;
	echo $temp_log_str;

	// Earnings
	// TODO - Do it when migrating orders i.e. Payment History
}

/**
 * Step 3
 * Coupons Migrate
 */
$wc_coupon_cpt = 'shop_coupon';
$edd_coupon_cpt = 'edd_discount';

// Fetch WC Coupons
$args = array(
	'post_type' => $wc_coupon_cpt,
	'posts_per_page' => -1,
	'post_status' => 'any',
);
$wc_coupon_list = get_posts( $args );
$temp_log_str = "\nWC Coupons fetched ...\n";
$log_str .= $temp_log_str;
echo $temp_log_str;

$wc_edd_coupon_map = array();

foreach( $wc_coupon_list as $c ) {

	// WC Coupon Object
	$code = $c->post_title;
	$status = ( $c->post_status == 'publish' ) ? 'active' : 'inactive';
	$coupon = new WC_Coupon( $code );
	$temp_log_str = "\nCoupon - $c->ID\n";
	$log_str .= $temp_log_str;
	echo $temp_log_str;

	$data = array(
		'post_content' => $c->post_content,
		'post_title' => $c->post_title,
		'post_status' => $status,
		'post_type' => $edd_coupon_cpt,
		'post_author' => $c->post_author,
		'post_parent' => $c->post_parent,
		'post_excerpt' => $c->post_excerpt,
		'post_date' => $c->post_date,
		'post_date_gmt' => $c->post_date_gmt,
		'comment_status' => $c->comment_status,
	);
	$edd_coupon_id = wp_insert_post( $data );

	// Adjust according to EDD Format
	$expiry_date = get_post_meta( $c->ID, 'expiry_date', true );
	$expiry_date = new DateTime( $expiry_date );
	$expiry_date->add( new DateInterval( 'PT23H59M59S' ) );
	$discount_type = get_post_meta( $c->ID, 'discount_type', true );
	$data = array(
		'name' => $c->post_excerpt,
		'status' => $status,
		'code' => $code,
		// TODO - Update uses when migrating Orders
		// 'uses' => {number},
		'max' => get_post_meta( $c->ID, 'usage_limit', true ),
		'amount' => get_post_meta( $c->ID, 'coupon_amount', true ),
		'expiration' => $expiry_date->format('m/d/Y H:i:s'),
	    'type' => ( strstr( $discount_type, 'percent' ) == FALSE ) ? 'flat' : 'percent',
	    'min_price' => get_post_meta( $c->ID, 'minimum_amount', true ),
	    'products' => array_map( 'intval', explode( ',', get_post_meta( $c->ID, 'product_ids', true ) ) ),
	    'product_condition' => 'any',
	    'excluded-products' => array_map( 'intval', explode( ',', get_post_meta( $c->ID, 'exclude_product_ids', true ) ) ),
	    'not_global' => true,
	    'use_once' => false,
	);
	edd_store_discount( $data, $edd_coupon_id );

	$wc_edd_coupon_map[ $c->ID ] = $edd_coupon_id;
	$temp_log_str = "\nWC Coupon migrated ...\n";
	$log_str .= $temp_log_str;
	echo $temp_log_str;

	update_post_meta( $edd_coupon_id, '_wc_coupon_id', $c->ID );

}

/**
 * Step 4
 * Orders Migrate
 */
$wc_order_cpt = 'shop_order';
$edd_order_cpt = 'edd_payment';

// Fetch WC Coupons
$args = array(
	'post_type' => $wc_order_cpt,
	'posts_per_page' => -1,
	'post_status' => 'any',
    'orderby' => 'date',
    'order' => 'ASC',
);
$wc_order_list = get_posts( $args );
$temp_log_str = "\nWC Orders fetched ...\n";
$log_str .= $temp_log_str;
echo $temp_log_str;

$wc_edd_order_map = array();

foreach( $wc_order_list as $o ) {

	// WC Order Object
	$order = new WC_Order( $o );
	$temp_log_str = "\nOrder - $o->ID\n";
	$log_str .= $temp_log_str;
	echo $temp_log_str;

	// Process Order Status
	switch( $order->post_status ) {
		case 'wc-pending':
		case 'wc-processing':
		case 'wc-on-hold':
			$status = 'pending';
			break;
		case 'wc-completed':
			$status = 'publish';
			break;
		case 'wc-cancelled':
			$status = 'abandoned';
			break;
		case 'wc-refunded':
			$status = 'refunded';
			break;
		case 'wc-failed':
			$status = 'failed';
			break;
		default:
			$status = 'pending';
			break;
	}

	$temp_log_str = "\nStatus : $status\n";
	$log_str .= $temp_log_str;
	echo $temp_log_str;

	$break_loop = false;

	// Decide the customer from email used. If new then create new.
	$email = get_post_meta( $o->ID, '_billing_email', true );
	$user = get_user_by( 'email', $email );
	if( ! $user ) {
		$first_name = get_post_meta( $o->ID, '_billing_first_name', true );
		$last_name = get_post_meta( $o->ID, '_billing_last_name', true );
		$password = wp_generate_password();
		$user_id = wp_insert_user(
			array(
				'user_email' 	=> sanitize_email( $email ),
				'user_login' 	=> sanitize_email( $email ),
				'user_pass'		=> $password,
				'first_name'	=> sanitize_text_field( $first_name ),
				'last_name' 	=> sanitize_text_field( $last_name ),
			)
		);
	} else {
		$user_id = $user->ID;
		$email = $user->user_email;
	}

	if( $user_id instanceof WP_Error ) {
		$temp_log_str = "\nUser could not be created. Invalid Email. So order could not be migrated ...\n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;
		continue;
	}

	// Prepare Products array & cart array for the order.
	$downloads = array();
	$cart_details = array();
	$wc_items = $order->get_items();

	// Decide whether any coupon is used for discount or not.
	$wc_coupon = $order->get_used_coupons();
	if( ! empty( $wc_coupon ) ) {
		$wc_coupon = new WC_Coupon( $wc_coupon[0] );
	} else {
		$wc_coupon = null;
	}

	// Line Items from the WC Order
	foreach( $wc_items as $item ) {
		$product = $order->get_product_from_item( $item );

		$item[ 'quantity' ] = $item[ 'qty' ];
		$item[ 'data' ] = $product;

		if( ! isset( $wc_edd_product_map[ $product->id ] ) || empty( $wc_edd_product_map[ $product->id ] ) ) {
			$temp_log_str = "\nEDD Product Not available for this WC Product.\n";
			$log_str .= $temp_log_str;
			echo $temp_log_str;
			$break_loop = true;
			break;
		}
		$download = edd_get_download( $wc_edd_product_map[ $product->id ] );
		$item_number = array(
			'id' => $download->ID,
		    'options' => array(),
		    'quantity' => $item[ 'qty' ],
		);
		$downloads[] = $item_number;

		$_wc_cart_disc_meta = get_post_meta( $order->id, '_cart_discount', true );
		$_wc_cart_disc_meta = floatval( $_wc_cart_disc_meta );

		$_wc_order_disc_meta = get_post_meta( $order->id, '_order_discount', true );
		$_wc_order_disc_meta = floatval( $_wc_order_disc_meta );

		// Cart Discount Logic for migration - Two Types : 1. Cart Discount 2. Product Discount
		if( ! empty( $_wc_cart_disc_meta ) ) {
			$item_price = $item[ 'line_subtotal' ];
			$discount = ( floatval( $item[ 'line_subtotal' ] ) - floatval( $item[ 'line_total' ] ) ) * $item[ 'qty' ];
			$subtotal = ( $item[ 'line_subtotal' ] * $item[ 'qty' ] ) - $discount;
			$price = $subtotal;  // $item[ 'line_total' ]
		} else {
			$item_price = $item[ 'line_subtotal' ];
			$discount = $coupon->get_discount_amount( $item_price, $item );
			$subtotal = ( $item[ 'line_subtotal' ] * $item[ 'qty' ] ) - $discount;
			$price = $subtotal;  // $item[ 'line_total' ]
		}

		$temp_log_str = "=======================================================\n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;
		$temp_log_str = "line_subtotal/item_price : ".$item_price."\n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;
		$temp_log_str = "line_total : ".$item[ 'line_total' ]."\n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;
		$temp_log_str = "discount : ".$discount."\n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;
		$temp_log_str = "subtotal : ".$subtotal."\n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;
		$temp_log_str = "price : ".$price."\n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;
		$temp_log_str = "=======================================================\n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;

		$cart_details[] = array(
			'id'          => $download->ID,
			'name'        => $download->post_title,
			'item_number' => $item_number,
			'item_price'  => $item_price,
			'subtotal'    => $subtotal,
			'price'       => $price,
			'discount'    => $discount,
			'fees'        => array(),
			'tax'         => 0,
			'quantity'    => $item[ 'qty' ],
		);
	}

	// If Products & Cart array is not prepared ( loop broken in between ) then skip the order.
	if( $break_loop ) {
		$temp_log_str = "\nWC Order could not be migrated ...\n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;
		continue;
	}

	// If no products found in the order then also skip the order.
	if( empty( $downloads ) || empty( $cart_details ) ) {
		$temp_log_str = "\nNo Products found. So order not migrated ...\n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;
		continue;
	}

	$data = array(
		'currency' => 'USD',
		'downloads' => $downloads,
		'cart_details' => $cart_details,
		'price' => get_post_meta( $order->id, '_order_total', true ),
		'purchase_key' => get_post_meta( $order->id, '_order_key', true ),
		'user_info' => array(
			'id' => $user_id,
			'email' => $email,
			'first_name' => get_post_meta( $order->id, '_billing_first_name', true ),
		    'last_name' => get_post_meta( $order->id, '_billing_last_name', true ),
			'discount' => ( ! empty( $wc_coupon ) && isset( $wc_edd_coupon_map[ $wc_coupon->id ] ) && ! empty( $wc_edd_coupon_map[ $wc_coupon->id ] ) ) ? $wc_coupon->code : '',
		    'address' => array(
			    'line1' => get_post_meta( $order->id, '_billing_address_1', true ),
				'line2' => get_post_meta( $order->id, '_billing_address_2', true ),
				'city' => get_post_meta( $order->id, '_billing_city', true ),
				'zip' => get_post_meta( $order->id, '_billing_postcode', true ),
				'country' => get_post_meta( $order->id, '_billing_country', true ),
				'state' => get_post_meta( $order->id, '_billing_state', true ),
		    ),
		),
		'user_id' => $user_id,
	    'user_email' => $email,
	    'status' => 'pending',
	    'parent' => $o->post_parent,
	    'post_date' => $o->post_date,
	    'gateway' => get_post_meta( $order->id, '_payment_method', true ),
	);

	$payment_id = edd_insert_payment( $data );
	remove_action( 'edd_update_payment_status', 'edd_trigger_purchase_receipt', 10 );
	remove_action( 'edd_complete_purchase', 'edd_trigger_purchase_receipt', 999 );
	edd_update_payment_status( $payment_id, $status );

	$wc_edd_order_map[ $o->ID ] = $payment_id;
	$temp_log_str = "\nWC Order migrated ...\n";
	$log_str .= $temp_log_str;
	echo $temp_log_str;

	// Update relavent data.
	update_post_meta( $payment_id, '_edd_payment_user_ip', get_post_meta( $order->id, '_customer_ip_address', true ) );
	update_post_meta( $payment_id, '_wc_order_key', get_post_meta( $order->id, '_order_key', true ) );
	update_post_meta( $payment_id, '_edd_payment_mode', 'live' );
	update_post_meta( $payment_id, '_edd_completed_date', get_post_meta( $order->id, '_completed_date', true ) );

	update_post_meta( $payment_id, '_wc_order_id', $o->ID );

	// Order Notes
	$args = array(
		'post_id' => $order->id,
		'approve' => 'approve',
		'type' => ''
	);
	$wc_notes = get_comments( $args );
	$temp_log_str = "\nOrder Notes fetched ...\n";
	$log_str .= $temp_log_str;
	echo $temp_log_str;
	foreach($wc_notes as $note) {

		$temp_log_str = "\nWC Order Note - $note->comment_ID\n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;

		$edd_note_id = edd_insert_payment_note( $payment_id, $note->comment_content );

		// Update relevant data from old comment
		wp_update_comment( array(
			'comment_ID' => $edd_note_id,
			'comment_date' => $note->comment_date,
			'comment_date_gmt' => $note->comment_date_gmt,
			'comment_author' => $note->comment_author,
			'comment_author_email' => $note->comment_author_email,
		) );
		update_comment_meta( $edd_note_id, '_wc_order_note_id', $note->comment_ID );

		$temp_log_str = "\nWC Order Note migrated ...\n";
		$log_str .= $temp_log_str;
		echo $temp_log_str;
	}
}

echo "\nMIGRATION COMPLETE !!! PLEASE CHECK THE LOG FILE IN THE SAME FOLDER !!!\n";
file_put_contents( "wc_edd_migration.log" . current_time( 'mysql' ), $log_str, FILE_APPEND );

/**
 * Step 5
 * Order Notes
 * - This is covered up in Order Migration
 */

/**
 * Step 6
 * Sales Logs
 * - This is covered up in Order Migration.
 */


/**
 * Step 7
 * Download Logs
 */
