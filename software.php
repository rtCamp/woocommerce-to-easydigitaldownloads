<?php
/**
 * Helper Functions
 */

function wc_edd_create_url( $order_key, $activation_email, $product_id, $download_id = '', $user_id ) {
	global $wpdb;

	$sql = "
			SELECT product_id,order_id,downloads_remaining,user_id,download_count,access_expires,download_id
			FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions
			WHERE user_email = %s
			AND order_key = %s
			AND product_id = %s";

	$args = array(
		$activation_email,
		$order_key,
		$product_id
	);

	if ( $download_id ) {
		// backwards compatibility for existing download URLs
		$sql .= " AND download_id = %s";
		$args[] = $download_id;
	}

	// Returns an Object
	$result = $wpdb->get_row( $wpdb->prepare( $sql, $args ) );

	if ( is_object( $result ) ) {

		// Cost value is between 4 and 31
//		$hash_data = WCAM()->hash->wam_password_hash( null, null, array( 'cost' => 4 ), $user_id );

		$url_args = array(
			'am_download_file' 	=> $result->product_id,
			'am_order' 			=> $order_key,
			'am_email' 			=> $activation_email,
//			'hname' 			=> $hash_data['hname'],
//			'hkey' 				=> $hash_data['hkey'],
//			'hexpires' 			=> $hash_data['hexpires']
		);

		if ( $result->download_id != '' ) {
			$url_args['am_key'] = $result->download_id;
		}

		return home_url( '/?' ) . http_build_query( $url_args, '', '&' );

	} else {

		return false;

	}

	return false;

}

function wc_edd_decrypt( $string ) {

	if ( empty( $string ) ) {
		return $string;
	}

	$nonce_salt = ( defined( 'NONCE_SALT' ) ) ? NONCE_SALT : ']K4eR{$@^@.Cb*P6+i0 jg&qEa8+V H-@N>:WuL/pW^z9nEte j|]{w!i!B~|saD';

	$key = md5( DB_NAME . DB_USER . DB_PASSWORD . $nonce_salt );

	if ( stripos( $string, '$Api_Manager$ENC1$' ) !== FALSE ) {
		$string = str_ireplace( '$Api_Manager$ENC1$', '', $string );
		$result = '';
		$string = base64_decode( $string );
		for ( $i = 0; $i < strlen( $string ); $i ++ ) {
			$char    = substr( $string, $i, 1 );
			$keychar = substr( $key, ( $i % strlen( $key ) ) - 1, 1 );
			$char    = chr( ord( $char ) - ord( $keychar ) );
			$result .= $char;
		}

		return $result;
	}

	if ( function_exists( 'mcrypt_encrypt' ) && stripos( $string, '$Api_Manager$RIJNDAEL$' ) !== FALSE) {
		$string = str_ireplace( '$Api_Manager$RIJNDAEL$', '', $string );

		return rtrim( mcrypt_decrypt( MCRYPT_RIJNDAEL_256, md5( $key ), base64_decode( $string ), MCRYPT_MODE_CBC, md5( md5( $key ) ) ), "\0" );
	}

	return $string;
}

function wc_edd_format_secure_s3_url( $url, $expire = false ) {

	if ( ! empty( $url ) ) {

		$secret_key = WCAM()->encrypt->decrypt( get_option( 'woocommerce_api_manager_amazon_s3_secret_access_key' ) );

		if ( $expire === false ) {

			$expire = time() + ( get_option( 'woocommerce_api_manager_url_expire' ) * MINUTE_IN_SECONDS );

		}

		$objectpath = parse_url( $url, PHP_URL_PATH );

		$signature = utf8_encode( "GET\n\n\n$expire\n" . $objectpath );

		$hashed_signature = base64_encode( hash_hmac( 'sha1' ,$signature , $secret_key , true ) );

		$query_string = array(
			'AWSAccessKeyId'	=> get_option( 'woocommerce_api_manager_amazon_s3_access_key_id' ),
			'Expires'			=> $expire,
			'Signature'			=> $hashed_signature
		);

		return $url . '?' . http_build_query( $query_string, '', '&' );

	}

	return '';

}

function wc_edd_find_amazon_s3_in_url( $url ) {
	$result = preg_match( '!\b(amazonaws.com)\b!', $url );

	if ( $result == 1 ) {
		return true;
	}

	return false;
}

function wc_edd_get_download_url( $post_id ) {

	$file_path = get_post_meta( $post_id, '_downloadable_files', true );

	if ( is_array( $file_path ) ) {
		foreach ( $file_path as $key => $value ) {
			$path[] = $value;
		}
	}

	if ( empty( $path[0] ) ) {
		return false;
	}

	if ( empty( $path[0]['file'] ) ) {
		return false;
	}

	return $path[0]['file'];
}

function wc_edd_get_downloadable_data( $order_key, $activation_email, $product_id, $download_id = '' ) {
	global $wpdb;

	$sql = "
			SELECT product_id,order_id,downloads_remaining,user_id,download_count,access_expires,download_id
			FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions
			WHERE user_email = %s
			AND order_key = %s
			AND product_id = %s
			AND download_id = %s";

	$args = array(
		$activation_email,
		$order_key,
		$product_id,
		$download_id
	);

	// Returns an Object
	$result = $wpdb->get_row( $wpdb->prepare( $sql, $args ) );

	if ( is_object( $result ) ) {
		return $result;
	}

	return false;

}

function wc_edd_get_download_id( $post_id ) {

	$file_path = get_post_meta( $post_id, '_downloadable_files', true );

	if ( is_array( $file_path ) ) {
		foreach ( $file_path as $key => $value ) {
			$path[] = $key;
		}
	}

	if ( empty( $path[0] )  ) {
		return false;
	}

	return $path[0];
}

function wc_edd_get_download_count( $order_id, $order_key ) {
	global $wpdb;

	$download_count = $wpdb->get_var( $wpdb->prepare( "
			SELECT download_count FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions
			WHERE order_id = %s
			AND order_key = %s
			LIMIT 1
		", $order_id, $order_key ) );

	if ( isset( $download_count ) ) {

		return $download_count;

	}

	return false;
}

function wc_edd_send_api_data( $request, $plugin_name, $version, $order_id, $api_key, $activation_email, $post_id, $order_key, $user ) {

	$download_count_set = wc_edd_get_download_count( $order_id, $order_key );

	// The download ID is needed for the order specific download URL
	$download_id = wc_edd_get_download_id( $post_id );

	$downloadable_data = wc_edd_get_downloadable_data( $order_key, $activation_email, $post_id, $download_id );

	$downloads_remaining 	= $downloadable_data->downloads_remaining;
	$download_count 		= $downloadable_data->download_count;
	$access_expires 		= $downloadable_data->access_expires;

	if ( $downloads_remaining == '0' ) {

		wc_edd_send_error_api_data( $_REQUEST[ 'request' ], array( 'download_revoked' => 'download_revoked' ) );

	}

	if ( $access_expires > 0 && strtotime( $access_expires ) < current_time( 'timestamp' ) ) {

		wc_edd_send_error_api_data( $this->request, array( 'download_revoked' => 'download_revoked' ) );

	}

	if ( $download_count_set !== false ) {

		// Get the API data in an array
		$api_data = get_post_custom( $post_id );

		/**
		 * Check for Amazon S3 URL
		 * @since 1.3.2
		 */
		$url = wc_edd_get_download_url( $post_id );

		if ( ! empty( $url ) && wc_edd_find_amazon_s3_in_url( $url ) === true ) {

			$download_link = wc_edd_format_secure_s3_url( $url );

		} else {

			// Build the order specific download URL
			$download_link = wc_edd_create_url( $order_key, $activation_email, $post_id, $download_id, $user->ID );

		}

		if ( $download_link === false || empty( $download_link ) ) {

			wc_edd_send_error_api_data( $_REQUEST[ 'request' ], array( 'download_revoked' => 'download_revoked' ) );

		}

		/**
		 * Prepare pages for display in upgrade "View version details" screen
		 */
		$desc_obj 		= get_post( $api_data['_api_description'][0] );
		$install_obj 	= get_post( $api_data['_api_installation'][0] );
		$faq_obj 		= get_post( $api_data['_api_faq'][0] );
		$screen_obj 	= get_post( $api_data['_api_screenshots'][0] );
		$change_obj 	= get_post( $api_data['_api_changelog'][0] );
		$notes_obj 		= get_post( $api_data['_api_other_notes'][0] );

		// Instantiate $response object
		$response = new stdClass();

		switch( $request ) {

			/**
			 * new_version here is compared with the current version in plugin
			 * Provides info for plugin row and dashboard -> updates page
			 */
			case 'pluginupdatecheck':
				$response->slug 					= $plugin_name;
				$response->new_version 				= $api_data['_api_new_version'][0];
				$response->url 						= $api_data['_api_plugin_url'][0];
				$response->package 					= $download_link;
				break;
			/**
			 * Request for detailed information for view details page
			 * more plugin info:
			 * wp-admin/includes/plugin-install.php
			 * Display plugin information in dialog box form.
			 * function install_plugin_information()
			 *
			 */
			case 'plugininformation':
				$response->name 					= $_REQUEST[ 'product_id' ];
				$response->version 					= $api_data['_api_new_version'][0];
				$response->slug 					= $plugin_name;
				$response->author 					= $api_data['_api_author'][0];
				$response->homepage 				= $api_data['_api_plugin_url'][0];
				$response->requires 				= $api_data['_api_version_required'][0];
				$response->tested 					= $api_data['_api_tested_up_to'][0];
				$response->downloaded 				= $download_count;
				$response->last_updated 			= $api_data['_api_last_updated'][0];
				$response->download_link 			= $download_link;
				$response->sections = array(
					'description' 	=> '',
					'installation' 	=> '',
					'faq' 			=> '',
					'screenshots' 	=> '',
					'changelog' 	=> '',
					'other_notes' 	=> ''
				);
				break;
			/**
			 * more theme info
			 * wp-admin/includes/theme-install.php
			 * WordPress Theme Install Administration API
			 * $theme_field_defaults
			 *
			 * wp-admin/includes/theme.php
			 * function themes_api()
			 */

		}

		nocache_headers();

		die( serialize( $response ) );

	} else {

		wc_edd_send_error_api_data( $_REQUEST[ 'request' ], array( 'download_revoked' => 'download_revoked' ) );

	}
}

function wc_edd_get_product_checkbox_status( $post_id, $meta_key ) {

	$result = get_post_meta( $post_id, $meta_key, true );

	if ( $result == 'yes' ) {
		return true;
	} else {
		return false;
	}

}


function wc_edd_is_plugin_active( $slug ) {
	$active_plugins = (array) get_option( 'active_plugins', array() );

	if ( is_multisite() )
		$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );

	return in_array( $slug, $active_plugins ) || array_key_exists( $slug, $active_plugins );
}

function wc_edd_array_search_multi( $array, $value, $needle ) {

	foreach( $array as $index_key => $value_key ) {

		if ( $value_key[$value] === $needle ) {
			return true;
		}

	}

	return false;
}

function wc_edd_get_users_activation_data( $user_id = 0, $order_key = 0 ) {
	global $wpdb;

	$user_meta_key_activations = 'wc_am_activations_';

	if ( $user_id === 0 ) {
		$data = array();
		return $data;
	}

	if ( $order_key === 0 ) {
		$data = array();
		return $data;
	}

	$data = get_metadata( 'user', $user_id, $wpdb->get_blog_prefix() . $user_meta_key_activations . $order_key, true );

	if( empty( $data ) ) {
		$data = array();
	}

	return $data;
}

function wc_edd_get_users_data( $user_id = 0 ) {
	global $wpdb;

	$user_meta_key_orders = 'wc_am_orders';

	if ( $user_id === 0 ) {
		$data = array();
		return $data;
	}

	$data = get_metadata( 'user', $user_id, $wpdb->get_blog_prefix() . $user_meta_key_orders, true );

	if ( empty( $data ) ) {
		$data = array();
	}

	return $data;
}

function wc_edd_send_error_api_data( $request, $errors ) {

	$response = new stdClass();

	switch( $request ) {

		case 'pluginupdatecheck':
			$response->slug 					= '';
			$response->new_version 				= '';
			$response->url 						= '';
			$response->package 					= '';
			$response->errors 					= $errors;
			break;

		case 'plugininformation':
			$response->version 					= '';
			$response->slug 					= '';
			$response->author 					= '';
			$response->homepage 				= '';
			$response->requires 				= '';
			$response->tested 					= '';
			$response->downloaded 				= '';
			$response->last_updated 			= '';
			$response->download_link 			= '';
			$response->sections = array(
				'description' 	=> '',
				'installation' 	=> '',
				'faq' 			=> '',
				'screenshots' 	=> '',
				'changelog' 	=> '',
				'other_notes' 	=> ''
			);
			$response->errors 					= $errors;
			break;

	}

	nocache_headers();

	file_put_contents( "wc_edd_sl_upgrade." . current_time( 'mysql' ) . ".log", serialize( $_REQUEST )."\n\n".serialize( $response ), FILE_APPEND );
	die( serialize( $response ) );
}


function wc_edd_software_api_send_error() {

	$error = array(
		'error' => __( 'You need to upgrade our new version of plugin. We have migrated our store to EDD.' ),
	    'code' => '106',
	    'additional_info' => __( 'Additional Information' ),
	    'timestamp' => time(),
	);

	foreach ( $error as $k => $v ) {

		if ( $v === false ) $v = 'false';

		if ( $v === true ) $v = 'true';

		$sigjoined[] = "$k=$v";

	}

	$sig = implode( '&', $sigjoined );

	$sig = 'secret=' . $secret . '&' . $sig;

	if ( !$this->debug ) $sig = md5( $sig );

	$error['sig'] = $sig;

	$json = $error;

	wp_send_json( $json );
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

/**
 * Detect plugin.
 * Check for required Plugins
 */
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if ( ! is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) || ! is_plugin_active( 'edd-software-licensing/edd-software-licenses.php' ) ) {
	wc_edd_send_error_api_data( $_REQUEST[ 'request' ], array( 'no_edd_activated' => 'no_edd_activated' ) );
}

if ( isset( $_REQUEST[ 'wc-api' ] ) ) switch( $_REQUEST[ 'wc-api' ] ) {
	case 'am-software-api':

		if ( isset( $_REQUEST[ 'request' ] ) ) switch ( $_REQUEST[ 'request' ] ) {
			case 'status':
			case 'activation':
			case 'deactivation':
				wc_edd_software_api_send_error();
				break;
		}
		break;
	case 'upgrade-api':
		if ( isset( $_REQUEST[ 'request' ] ) ) switch( $_REQUEST[ 'request' ] ) {
			case 'pluginupdatecheck':
			case 'plugininformation':

				if ( ! empty( $_REQUEST[ 'request' ] ) || ! empty( $_REQUEST[ 'plugin_name' ] ) || ! empty( $_REQUEST[ 'version' ] ) || ! empty( $_REQUEST[ 'product_id' ] ) || ! empty( $_REQUEST[ 'api_key' ] ) || ! empty( $_REQUEST[ 'activation_email' ] ) ) {

					// If the remote plugin or theme has nothing entered into the license key and license email fields
					if ( $_REQUEST[ 'api_key' ] == '' || $_REQUEST[ 'activation_email' ] == '' ) {

						wc_edd_send_error_api_data( $_REQUEST[ 'request' ], array( 'no_key' => 'no_key' ) );
					}

					// returns $user->ID
					$user = get_user_by( 'email', $_REQUEST[ 'activation_email' ] );

					// If the remote plugin or theme has nothing entered into the license key and license email fields
					if ( ! is_object( $user ) ) {

						wc_edd_send_error_api_data( $_REQUEST[ 'request' ], array( 'no_key' => 'no_key' ) );
					}

					$user_orders = wc_edd_get_users_data( $user->ID );

					if ( ! empty( $user_orders ) ) {

						$order_info = $user_orders[ $_REQUEST[ 'api_key' ] ];

					} else {

						wc_edd_send_error_api_data( $_REQUEST[ 'request' ], array( 'no_key' => 'no_key' ) );

					}

					// Determine the Software Title from the customer order data
					$software_title = ( empty( $order_info[ '_api_software_title_var' ] ) ) ? $order_info[ '_api_software_title_parent' ] : $order_info[ '_api_software_title_var' ];

					if ( empty( $software_title ) ) {

						$software_title = $order_info[ 'software_title' ];

					}

					/**
					 * Verify the client Software Title matches the product Software Title
					 */
					if ( $software_title != $this->product_id  ) {

						wc_edd_send_error_api_data( $_REQUEST[ 'request' ], array( 'no_key' => 'no_key' ) );

					}

					// Get activation info
					$current_info = wc_edd_get_users_activation_data( $user->ID, $order_info[ 'order_key' ] );

					// Check if this software has been activated
					if ( is_array( $current_info ) && ! empty( $current_info ) ) {

						// If false is returned then the software has not yet been activated and an error is returned
						if ( wc_edd_array_search_multi( $current_info, 'order_key', $_REQUEST[ 'api_key' ] ) === false ) {

							wc_edd_send_error_api_data( $_REQUEST[ 'request' ], array( 'no_activation' => 'no_activation' ) );

						}

						// If false is returned then the software has not yet been activated and an error is returned
						if ( ! empty( $this->instance ) && wc_edd_array_search_multi( $current_info, 'instance', $this->instance ) === false ) {

							wc_edd_send_error_api_data( $_REQUEST[ 'request' ], array( 'no_activation' => 'no_activation' ) );

						}

						// If false is returned then the software has not yet been activated and an error is returned
						// if ( ! empty( $this->domain ) && wc_edd_array_search_multi( $current_info, 'activation_domain', $this->domain ) === false ) {

						// 	wc_edd_send_error_api_data( $_REQUEST[ 'request' ], array( 'no_activation' => 'no_activation' ) );

						// }


					} else { // Send an error if this software has not been activated

						wc_edd_send_error_api_data( $_REQUEST[ 'request' ], array( 'no_activation' => 'no_activation' ) );

					}

					// Finds the post ID (integer) for a product even if it is a variable product
					if ( $order_info[ 'is_variable_product' ] == 'no' ) {

						$post_id = $order_info[ 'parent_product_id' ];

					} else {

						$post_id = $order_info[ 'variable_product_id' ];

					}

					// Finds order ID that matches the license key. Order ID is the post_id in the post meta table
					$order_id 	= $order_info[ 'order_id' ];

					// Finds the product ID, which can only be the parent ID for a product
					$product_id = $order_info[ 'parent_product_id' ];

					// Check if this is an order_key. Finds the order_key for the product purchased
					$order_key = $order_info[ 'order_key' ];

					/**
					 * @since 1.3
					 * For WC 2.1 and above
					 * api_key array key introduced to replace order_key
					 */
					$api_key = ( empty( $order_info[ 'api_key' ] ) ) ? '' : $order_info[ 'api_key' ];

					// Does this order_key have Permission to get updates from the API?
					if ( $order_info[ '_api_update_permission' ] != 'yes' ) {

						wc_edd_send_error_api_data( $_REQUEST[ 'request' ], array( 'download_revoked' => 'download_revoked' ) );
					}

					if ( isset( $user ) && isset( $post_id ) && isset( $order_id ) && isset( $product_id ) && isset( $order_key ) ) {

						// Verifies license key exists. Returns true or false.
						if ( $_REQUEST[ 'api_key' ] == $order_key || $_REQUEST[ 'api_key' ] == $api_key ) {

							$key_exists = true;

						} else {

							$key_exists = false;

						}

						// Send a renew license key message to the customer
						if ( isset( $key_exists ) && $key_exists === false ) {

							wc_edd_send_error_api_data( $_REQUEST[ 'request' ], array( 'exp_license' => 'exp_license' ) );

							// If Subscriptions is active, and if a subscription is required for this product
//						} else if ( wc_edd_is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) && wc_edd_get_product_checkbox_status( $post_id, '_api_is_subscription' ) === true ) {
//
//							// Get the subscription status i.e. active
//							$status = WCAM()->helpers->get_subscription_status( $user->ID, $post_id, $order_id, $product_id );
//
//							// Send an error if no subscription is found and if the API key doesn't match what was sent by the software
//							if ( $status === false ) {
//
//								if ( $key_exists ) { // Matched the API Key, send the update data
//
//									// Update the software version for this user order
//									if ( ! empty( $this->software_version ) ) {
//
//										//$this->update_order_version( $user->ID, $order_info );
//
//										$this->update_activation_version( $user->ID, $current_info, $api_key, $order_key );
//
//									}
//
//									/**
//									 * Allows third party plugins to receive any kind of data from client software through the Update API
//									 * @since 1.3
//									 */
//									if ( ! empty( $this->extra ) ) {
//
//										do_action( 'api_manager_extra_data', $this->extra );
//
//									}
//
//									$this->send_api_data( $_REQUEST[ 'request' ], $_REQUEST[ 'plugin_name' ], $this->version, $order_id, $_REQUEST[ 'api_key' ], $_REQUEST[ 'activation_email' ], $post_id, $order_key, $user );
//
//								} else { // No API Key match, send an error
//
//									wc_edd_send_error_api_data( $_REQUEST[ 'request' ], array( 'no_subscription' => 'no_subscription', 'no_key' => 'no_key' ) );
//
//								}
//
//							}
//
//							// Sends update data if subscription is active, otherwise an error message is sent
//							if ( $status == 'active' && $status !== false && $key_exists ) {
//
//								// Update the software version for this user order
//								if ( ! empty( $this->software_version ) ) {
//
//									//$this->update_order_version( $user->ID, $order_info );
//
//									$this->update_activation_version( $user->ID, $current_info, $api_key, $order_key );
//
//								}
//
//								/**
//								 * Allows third party plugins to receive any kind of data from client software through the Update API
//								 * @since 1.3
//								 */
//								if ( ! empty( $this->extra ) ) {
//
//									do_action( 'api_manager_extra_data', $this->extra );
//
//								}
//
//								$this->send_api_data( $_REQUEST[ 'request' ], $_REQUEST[ 'plugin_name' ], $this->version, $order_id, $_REQUEST[ 'api_key' ], $_REQUEST[ 'activation_email' ], $post_id, $order_key, $user );
//
//							} else {
//
//								wc_edd_send_error_api_data( $_REQUEST[ 'request' ], WCAM()->helpers->check_subscription_status( $status ) );
//
//							}
//
							// If the API License Key is valid, and a subscription is not required for this product
						} else if ( $key_exists && WCAM()->helpers->get_product_checkbox_status( $post_id, '_api_is_subscription' ) === false ) {

							// Update the software version for this user order
//							if ( ! empty( $this->software_version ) ) {
//
//								//$this->update_order_version( $user->ID, $order_info );
//
//								$this->update_activation_version( $user->ID, $current_info, $api_key, $order_key );
//
//							}

							/**
							 * Allows third party plugins to receive any kind of data from client software through the Update API
							 * @since 1.3
							 */
//							if ( ! empty( $this->extra ) ) {
//
//								do_action( 'api_manager_extra_data', $this->extra );
//
//							}

							wc_edd_send_api_data( $_REQUEST[ 'request' ], $_REQUEST[ 'plugin_name' ], $_REQUEST[ 'version' ], $order_id, $_REQUEST[ 'api_key' ], $_REQUEST[ 'activation_email' ], $post_id, $order_key, $user );

						} // end if subscriptions installed

					} // end if isset data variables
				} else {
					wc_edd_send_error_api_data( $_REQUEST[ 'request' ], array( 'no_key' => 'no_key' ) );
				}

				break;
		}
		break;
}
