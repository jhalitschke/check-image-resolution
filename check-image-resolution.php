<?php
/*
Plugin Name: Check Image Resolution
Description: Checks if the uploaded image has a DPI greater than 96 and if it is not in RGB color model.
Version: 1.0
Author: jhalitschke
*/

add_action( 'wp_handle_upload_prefilter', 'check_image_properties' );

function check_image_properties( $image ) {
	$dpi_check = check_image_dpi( $image );
	if ( is_wp_error( $dpi_check ) ) {
		add_upload_error_notice( $dpi_check->get_error_message() );
		return $dpi_check;
	}

	$color_model_check = check_image_color_model( $image );
	if ( is_wp_error( $color_model_check ) ) {
		add_upload_error_notice( $color_model_check->get_error_message() );
		return $color_model_check;
	}

	return $image;
}

function check_image_dpi( $image ) {
	$imagick = new Imagick( $image['file'] );
	$dpi     = $imagick->getImageResolution();
	if ( $dpi['x'] > 96 || $dpi['y'] > 96 ) {
		return new WP_Error( 'dpi_error', 'Image DPI resolution is greater than 96.' );
	}

	return $image;
}

function check_image_color_model( $image ) {
	$imagick    = new Imagick( $image['file'] );
	$colorSpace = $imagick->getImageColorspace();
	if ( $colorSpace != Imagick::COLORSPACE_RGB ) {
		return new WP_Error( 'cmyk_error', 'Image is not in RGB color model.' );
	}

	return $image;
}

function add_upload_error_notice( $message ) {
	add_action( 'admin_notices', function() use ( $message ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
	} );
}
