<?php
/*
Plugin Name: Hot Sheet
Plugin URI: http://www.allendav.com/
Description: Hot Sheet provides a WordPress widget that can display a list of posts until their expiration date.  Also useful for a list of upcoming events.  To add a post to the Hot Sheet, simply set a date in the Hot Sheet options for the post.  To leave a post off of the Hot Sheet, leave the date empty.
Version: 1.1.1
Author: allendav, Designgeneers
Author URI: http://www.allendav.com
License: GPL2
*/

/*  Copyright 2018 Allen Snook (email : allendav@allensnook.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


function hotsheet_add_meta_box() {
    add_meta_box( 'hotsheet_sectionid', __( 'Hot Sheet' ), 'hotsheet_meta_box', 'post', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'hotsheet_add_meta_box' );

function hotsheet_meta_box( $post ) {
	echo '<input type="hidden" name="hotsheet_nonce" id="hotsheet_nonce" value="' . esc_attr( wp_create_nonce( 'hotsheet-' . $post->ID ) ) . '" />';

	$timestamp = get_post_meta( $post->ID, '_hotsheet_date', true);
	if ( ! empty( $timestamp ) ) {
		$hot_sheet_date = strftime( "%m/%d/%Y", $timestamp );
	}

	echo esc_html__( 'Feature this post until' );
  echo '<br><br>';
	echo '<input type="text" id="_hotsheet_date" name="_hotsheet_date" value="' . esc_attr( $hot_sheet_date ) . '" size="10" maxlength="10" />';
	echo '<br><br>';
	echo esc_html__( 'Enter date in the form m/d/yyyy.  Leave empty to leave this post off the Hot Sheet.' );
}

function hotsheet_save_postdata( $post_id ) {
	if ( ! wp_verify_nonce( $_POST['hotsheet_nonce'], 'hotsheet-' . $post_id ) ) {
		return $post_id;
	}

	// verify if this is an auto save routine. If it is our form has not been submitted, so we dont want
	// to do anything
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
	return $post_id;

	// Check permissions
	if ( 'page' == $_POST['post_type'] ) {
		if ( ! current_user_can( 'edit_page', $post_id ) )
			return $post_id;
	} else {
		if ( ! current_user_can( 'edit_post', $post_id ) )
			return $post_id;
	}

	// OK, we're authenticated: we need to find and save the data
	$hot_sheet_date = $_POST['_hotsheet_date'];

	if ( empty( $hot_sheet_date ) ) {
		delete_post_meta( $post_id, '_hotsheet_date' );
	} else {
		// Convert user entered date into unix time
		// Dates in the m/d/y or d-m-y formats are disambiguated by strtotime by looking at the separator
		// between the various components: if the separator is a slash (/), then the American m/d/y is assumed;
		// whereas if the separator is a dash (-) or a dot (.), then the European d-m-y format is assumed.
    $timestamp = strtotime( $hot_sheet_date );
		if ( $timestamp ) {
			update_post_meta( $post_id, '_hotsheet_date', $timestamp );
		} else {
      delete_post_meta( $post_id, '_hotsheet_date' );
    }
	}

	return $post_id;
}
add_action( 'save_post', 'hotsheet_save_postdata' );

class Hot_Sheet_Widget extends WP_Widget {
	function __construct() {
		parent::__construct( false, __( 'Hot Sheet' ) );
	}

	/***********************************************************************************************************/
	function widget( $args, $instance ) {
		extract( $args, EXTR_SKIP );

		$cat_ID = intval( $instance['cat_ID'] );
		$title  = $instance['title'];
		$title  = apply_filters( 'widget_title', $title );

		$escaped_content = '';

		// Find qualifying posts
		$args = array(
			'numberposts' => -1,
			'post_type'   => 'post',
			'orderby'	    => 'meta_value',
			'order'		    => 'ASC',
			'meta_key'    => '_hotsheet_date'
		);

		if ( 0 < $cat_ID ) {
			$args['cat'] = $cat_ID;
		}

		$my_posts = get_posts( $args );

		foreach ( (array) $my_posts as $my_post ) {
			$post_ID        = $my_post->ID;
			$post_title     = get_the_title( $post_ID );
			$post_link      = get_permalink( $post_ID );
			$post_timestamp = get_post_meta( $post_ID, '_hotsheet_date', true );
			if ( $post_timestamp >= time() ) {
				$found_one = true;
				$escaped_content .= '<li><a href="' . esc_url( $post_link ) .'">' . esc_html( $post_title ) . '</a></li>';
			}
		}

		if ( ! empty( $escaped_content ) ) {
			echo $before_widget;

			if ( ! empty( $title ) ) {
				echo $before_title . $title . $after_title;
			}

			echo "<ul>";
			echo $escaped_content;
			echo "</ul>";

			echo $after_widget;
		}
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['cat_ID'] = strip_tags( $new_instance['catID'] );

		return $instance;
	}

	function form( $instance ) {
		$defaults = array( 'cat_ID' => 0 );
		$instance = wp_parse_args( (array) $instance, $defaults );

		$title_field_ID    = $this->get_field_id( 'title' );
		$title_field_name  = $this->get_field_name( 'title' );
		$title_field_value = $instance['title'];
		$cat_field_ID      = $this->get_field_id( 'cat_ID' );
		$cat_field_name    = $this->get_field_name( 'cat_ID' );
		$cat_field_value   = intval( $instance['cat_ID'] );

		$title_field_value = htmlspecialchars( $title_field_value, ENT_QUOTES );

		echo '<p>';
		echo '<label for="' . esc_attr( $title_field_ID ) . '">' . esc_html__( 'Title:' ) . '</label>';
		echo '<input type="text" id="' .esc_attr( $title_field_ID ) . '" name="' . esc_attr( $title_field_name ) . '" value="' . esc_attr( $title_field_value ) . '"/>';
		echo '</p>';

		// Show the category selector
		$my_categories = get_terms( 'category' );

		echo '<p>';
		echo '<label for="' . esc_attr( $cat_field_ID ) . '">' . esc_html__( 'Category:' ) . '</label>';
		echo '<select id="' . esc_attr( $cat_field_ID ) . '" name="' . esc_attr( $cat_field_name ) . '>';

		if ( 0 === $cat_field_value ) {
			echo '<option value="0" selected="selected">' . esc_html__( 'All' ) . '</option>';
		} else {
			echo '<option value="0">' . esc_html__( 'All' ) . '</option>';
		}

		foreach ( (array) $my_categories as $my_category ) {
			$cat_ID    = $my_category->term_id;
			$cat_title = $my_category->name;
			$selected  = '';
			if ( $cat_ID == $cat_field_value ) {
				$selected = 'selected="selected"';
			}

			echo '<option value="' . esc_attr( $cat_ID ) . '"' . $selected . '>' . esc_html( $cat_title ) . '</option>';
		}

		echo '</select>';
		echo '</p>';
	}
}

function hot_sheet_init()
{
	register_widget( 'Hot_Sheet_Widget' );
}

add_action( 'widgets_init', 'hot_sheet_init' );
