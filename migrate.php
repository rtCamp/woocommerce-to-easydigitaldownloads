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

echo "\nWP Loaded ...\n";

/**
 * Detect plugin.
 * Check for required Plugins
 */
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) || ! is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) ) {
	exit( 'WC & EDD Not Activated.' );
}
echo "\nWC & EDD activated ...\n";

/**
 * Step 1
 * Category & Tag Migrate
 */

$edd_cat_slug = 'download_category';
$wc_cat_slug = 'product_cat';
$wc_edd_cat_map = array();

// Fetch Category from WC
$wc_cat_terms = get_terms( $wc_cat_slug, array( 'hide_empty' => false ) );
echo "\nWC Cat fetched ...\n";

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
		echo "\n$t->name -- Category not migrated because : \n";
		var_dump( $edd_term );
	}
}
echo "\nEDD Category migrated ...\n";

$edd_tag_slug = 'download_tag';
$wc_tag_slug = 'product_tag';
$wc_edd_tag_map = array();

// Fetch Tag from WC
$wc_tag_terms = get_terms( $wc_tag_slug, array( 'hide_empty' => false ) );
echo "\nWC Tag fetched ...\n";

foreach( $wc_tag_terms as $t ) {
	$edd_term = wp_insert_term( $t->name, $edd_tag_slug );

	if( ! $edd_term instanceof WP_Error ) {
		// maintain array of tag mapping
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

// Fetch WC Products
$args = array(
	'post_type' => $wc_product_cpt,
    'posts_per_page' => -1,
    'post_status' => 'any',
);
$wc_product_list = get_posts( $args );
echo "\nWC Product fetched ...\n";

$wc_edd_product_map = array();

foreach( $wc_product_list as $p ) {

	// WC Product Object
	$product = get_product( $p );
	echo "\nProduct - $p->ID\n";

	// Fetch WC Categories
	$wc_cat_terms = wp_get_post_terms( $p->ID, $wc_cat_slug );
	echo "\nWC Product Category fetched ...\n";
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

	update_post_meta( $edd_product_id, '_wc_product_id', $p->ID );

	// Assign Category
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

		// insert new attachment for new product
		$attach_id = wc_edd_insert_attachment( $wc_product_featured_image, $edd_product_id );
		if( empty( $attach_id ) ) {
			echo "\nFeature Image could not be set for Product ...\n";
			continue;
		}

		// Set featured image
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

			// insert new attachment for new product
			$attach_id = wc_edd_insert_attachment( $attachment_id, $edd_product_id );

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
		// To download file from the url
		$file = new WP_Http();
		$file = $file->request( $wc_file[ 'file' ] );
		if( $file[ 'response' ][ 'code' ] != 200 ) {
			echo "\nDownloadable File " . $wc_file[ 'name' ] . " could not be set for Product ...\n";
			var_dump( $wc_file[ 'file' ] );
			continue;
		}

		// Upload downloaded url to WP Upload directory
		$attachment = wp_upload_bits( basename( $wc_file[ 'file' ] ), null, $file['body'], date("Y-m", strtotime( $file[ 'headers' ][ 'last-modified' ] ) ) );
		if( ! empty( $attachment[ 'error' ] ) ) {
			echo "\nDownloadable File " . $wc_file[ 'name' ] . " could not be set for Product ...\n";
			var_dump( $wc_file[ 'file' ] );
			continue;
		}

		$filetype = wp_check_filetype( basename( $attachment[ 'file' ] ), null );
		$wp_upload_dir = wp_upload_dir();

		// Insert attachement for uploaded file
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
		echo "\nWC Downloadable Files migrated ...\n";
	}

	// Download Limit
	// Take old value from WC meta and save it into EDD meta.
	$edd_dl_limit_slug = '_edd_download_limit';
	$wc_dl_limit_slug = '_download_limit';
	update_post_meta( $edd_product_id, $edd_dl_limit_slug, get_post_meta( $p->ID, $wc_dl_limit_slug, true ) );
	echo "\nWC Download Limit : " . get_post_meta( $p->ID, $wc_dl_limit_slug, true ) . " migrated ...\n";

	// Price
	// Take old value from WC meta and save it into EDD meta.
	$edd_product_price_slug = 'edd_price';
	$wc_product_price_slug = '_regular_price';
	update_post_meta( $edd_product_id, $edd_product_price_slug, get_post_meta( $p->ID, $wc_product_price_slug, true ) );
	echo "\nWC Product Price : " . get_post_meta( $p->ID, $wc_product_price_slug, true ) . " migrated ...\n";

	// Sales
	// Take old value from WC meta and save it into EDD meta.
	$edd_product_sales_slug = '_edd_download_sales';
	$wc_product_sales_slug = 'total_sales';
	update_post_meta( $edd_product_id, $edd_product_sales_slug, get_post_meta( $p->ID, $wc_product_sales_slug, true ) );
	echo "\nWC Product Total Sales : " . get_post_meta( $p->ID, $wc_product_sales_slug, true ) . " migrated ...\n";

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
echo "\nWC Coupons fetched ...\n";

$wc_edd_coupon_map = array();

foreach( $wc_coupon_list as $c ) {

	// WC Coupon Object
	$code = $c->post_title;
	$status = ( $c->post_status == 'publish' ) ? 'active' : 'inactive';
	$coupon = new WC_Coupon( $code );
	echo "\nCoupon - $c->ID\n";

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
	echo "\nWC Coupon migrated ...\n";

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
echo "\nWC Orders fetched ...\n";

$wc_edd_order_map = array();

foreach( $wc_order_list as $o ) {

	// WC Order Object
	$order = new WC_Order( $o );
	echo "\nOrder - $o->ID\n";

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

	echo "\nStatus : $status\n";

	$break_loop = false;

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
		echo "\nUser could not be created. Invalid Email. So order could not be migrated ...\n";
		continue;
	}

	$downloads = array();
	$cart_details = array();
	$wc_items = $order->get_items();

	$wc_coupon = $order->get_used_coupons();
	if( ! empty( $wc_coupon ) ) {
		$wc_coupon = new WC_Coupon( $wc_coupon[0] );
	} else {
		$wc_coupon = null;
	}

	foreach( $wc_items as $item ) {
		$product = $order->get_product_from_item( $item );
		if( ! isset( $wc_edd_product_map[ $product->id ] ) || empty( $wc_edd_product_map[ $product->id ] ) ) {
			echo "\nEDD Product Not available for this WC Product.\n";
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

		$_wc_order_disc_meta = get_post_meta( $order->id, '_cart_discount', true );
		$_wc_order_disc_meta = floatval( $_wc_order_disc_meta );

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

		echo "=======================================================\n";
		echo "line_subtotal/item_price : ".$item_price."\n";
		echo "line_total : ".$item[ 'line_total' ]."\n";
		echo "discount : ".$discount."\n";
		echo "subtotal : ".$subtotal."\n";
		echo "price : ".$price."\n";
		echo "=======================================================\n";

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

	if( $break_loop ) {
		echo "\nWC Order could not be migrated ...\n";
		continue;
	}

	if( empty( $downloads ) || empty( $cart_details ) ) {
		echo "\nNo Products found. So order not migrated ...\n";
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

	update_post_meta( $payment_id, '_edd_payment_user_ip', get_post_meta( $order->id, '_customer_ip_address', true ) );
	update_post_meta( $payment_id, '_wc_order_key', get_post_meta( $order->id, '_order_key', true ) );
	update_post_meta( $payment_id, '_edd_payment_mode', 'live' );
	update_post_meta( $payment_id, '_edd_completed_date', get_post_meta( $order->id, '_completed_date', true ) );

	update_post_meta( $payment_id, '_wc_order_id', $o->ID );
}

/**
 * Step 5
 * Sales Logs
 */


/**
 * Step 7
 * Download Logs
 */
