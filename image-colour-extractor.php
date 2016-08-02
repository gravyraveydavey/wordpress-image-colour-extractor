<?php
/**
 * Plugin Name: Image Colour Extractor
 * Plugin URI: #
 * Description: Plugin to pull the primary colour from an image on upload / save, and store it in postmeta
 * Version: 1.0.0
 * Author: Dave Welch
 * Author URI: https://velocitypartners.com
 */

include_once( 'colorsofimage.class.php' );

$ICE_debugging = false;

function add_color_extractor_scripts($hook) {
	wp_register_script( 'vibrant_js', esc_url( plugins_url( 'js/vibrant.min.js', __FILE__ ) ), array( 'jquery' ) );
	wp_register_script( 'color_extractor_js', esc_url( plugins_url( 'js/color_extractor.js', __FILE__ ) ), array( 'jquery', 'vibrant_js' ) );
	wp_register_style( 'color_extractor_css', esc_url( plugins_url( 'css/color_extractor.css', __FILE__ ) ), false, '1.0.0' );

	wp_enqueue_style( 'color_extractor_css' );
	if ($hook == 'post.php'){
		wp_enqueue_script( 'vibrant_js' );
		wp_enqueue_script( 'color_extractor_js' );
	}
}
add_action( 'admin_enqueue_scripts', 'add_color_extractor_scripts' );


function add_primary_color_after_upload($post_ID) {
    if ($ICE_debugging) _log('running add attachment filter');
    $src = wp_get_attachment_image_src( $post_ID, 'thumbnail' )[0];
	$color = get_primary_color_from_image($src);
    if ($ICE_debugging) _log('return from color fetch was ' . $color);
	if ($color) add_post_meta( $post_ID, 'primary_color', $color, true ) || update_post_meta( $post_ID, 'primary_color', $color );
    return $post_ID;
}

add_filter('add_attachment', 'add_primary_color_after_upload', 10, 2);


function attachment_field_primary_color( $form_fields, $post ) {
	$form_fields['primary_color'] = array(
		'label' => 'Primary Image Color',
		'input' => 'text',
		'value' => get_post_meta( $post->ID, 'primary_color', true ),
		'helps'	=> '<div class="swatch" style="background-color: '.get_post_meta( $post->ID, 'primary_color', true ).'"></div>'
	);

	return $form_fields;
}

add_filter( 'attachment_fields_to_edit', 'attachment_field_primary_color', 10, 2 );

function attachment_field_primary_color_save( $post, $attachment ) {
	if( isset( $attachment['primary_color'] ) && $attachment['primary_color'] !== ''){
	    if ($ICE_debugging) _log('storing new color metadata');
		update_post_meta( $post['ID'], 'primary_color', $attachment['primary_color'] );
	} else {
	    if ($ICE_debugging) _log('no current color metadata was found');
		$color = get_primary_color_from_image($post['ID']);
		if ($color) update_post_meta( $post['ID'], 'primary_color', $color );
	}

	return $post;
}

add_filter( 'attachment_fields_to_save', 'attachment_field_primary_color_save', 10, 2 );

function get_primary_color_from_image($image){
	if (is_int($image)){
		$path = wp_get_attachment_image_src( $image, 'thumbnail' );
		$image = get_home_url().$path[0];
	} else if(is_string($image)){
		// check if it starts with a http
		if ( substr( $image, 0, 4 ) === "http" ){
			// all good
		} else if( substr( $image, 0, 1 ) === "/" ){
			$image = get_home_url().$image;
		} else {
			$image = get_home_url().'/'.$image;
		}
	} else{
		return false;
	}
    if ($ICE_debugging) _log('fetching colour on : ' . $image);
	$colors_of_image = new ColorsOfImage( $image, 15, 1 );
	$bg_color = $colors_of_image->getProminentColors();

	return $bg_color[0];
}

function get_image_primary_color($image_id){
	if ($color = get_post_meta($image_id, 'primary_color', true)){
		if ($ICE_debugging) _log('using existing meta: ' . $color);
	} else {
		if ($ICE_debugging) _log('no existing meta found - generating new meta');
		$color = get_primary_color_from_image($image_id);
		if ($color) update_post_meta( $image_id, 'primary_color', $color );
	}
	return $color;
}

?>