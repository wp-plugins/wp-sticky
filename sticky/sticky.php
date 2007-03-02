<?php
/*
Plugin Name: WP-Sticky
Plugin URI: http://www.lesterchan.net/portfolio/programming.php
Description: Adds a sticky post feature to your WordPress's blog. Modified From Adhesive By Owen Winkler.
Version: 1.00
Author: GaMerZ
Author URI: http://www.lesterchan.net
*/


/*  
	Copyright 2007  Lester Chan  (email : gamerz84@hotmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


### Create Text Domain For Translations
load_plugin_textdomain('wp-sticky', 'wp-content/plugins/sticky');


### Sticky Table Name
$wpdb->sticky = $table_prefix . 'sticky';


### Function: Sticky Option Menu
add_action('admin_menu', 'sticky_menu');
function sticky_menu() {
	if (function_exists('add_options_page')) {
		add_options_page(__('Sticky', 'wp-sticky'), __('Sticky', 'wp-sticky'), 'manage_options', 'sticky/sticky.php', 'sticky_options');
	}
}


### Function: Get Sticky Option
function get_sticky_option($option) {
	$sticky_options = get_option('sticky_options');
	return $sticky_options[$option];
}


### Function: Check Whether In Category
if(!function_exists('check_in_category')) {
	function check_in_category() {
		$category_base = get_option('category_base');
		if(empty($category_base)) {
			$category_base = '/category';
		}
		if(strpos($_SERVER['REQUEST_URI'], $category_base) !== false || intval($_GET['cat']) > 0) {
			return true;
		}
		return false;
	}
}


### Function: Sticky Query
if(intval(get_sticky_option('category_only')) == 1) {
	if(check_in_category()) {
		add_filter('posts_fields', 'sticky_fields');
		add_filter('posts_join', 'sticky_join');
		add_filter('posts_orderby', 'sticky_orderby', 1);
	}
} else {
	add_filter('posts_fields', 'sticky_fields');
	add_filter('posts_join', 'sticky_join');
	add_filter('posts_orderby', 'sticky_orderby', 1);
}
function sticky_fields($content) {
	global $wpdb;
	$content .= ", $wpdb->sticky.sticky_status";
	return $content;
}
function sticky_join($content) {
	global $wpdb;
	$content .= " LEFT JOIN $wpdb->sticky ON $wpdb->sticky.sticky_post_id = $wpdb->posts.ID";
	return $content;
}
function sticky_orderby($content) {
	global $wpdb;
	$content = "($wpdb->sticky.sticky_status = 2 AND $wpdb->sticky.sticky_status IS NOT NULL) DESC, DATE_FORMAT($wpdb->posts.post_date,'%Y-%m-%d') DESC, ($wpdb->sticky.sticky_status = 1 AND $wpdb->sticky.sticky_status IS NULL) DESC, DATE_FORMAT($wpdb->posts.post_date,'%T') DESC";
	return $content;
}


### Function: Output Post Sticky Status, Sticky Or Announcement
function post_sticky_status($before = '', $after = '', $display = true) {
	global $id, $post;
	$temp = '';
	switch($post->sticky_status) {
		case 1:
			$temp = $before.__('Sticky', 'wp-sticky').$after;
			break;
		case 2:
			$temp = $before.__('Announcement', 'wp-sticky').$after;
			break;
	}
	if($display) {
		echo $temp;
	} else {
		return $temp;
	}
}


### Function: Sticky The Date
add_filter('the_date', 'sticky_the_date');
function sticky_the_date($content) {
	global $post, $previousday, $printed_announcement;
	if($post->sticky_status == 2 && intval(get_sticky_option('display_date')) == 0) {
		$previousday = '';
		if($printed_announcement) {
			return;
		} else {
			$printed_announcement = true;
			return get_sticky_option('announcement_banner');
		}
	}
	return $content;
}


### Function: Sticky The Title
add_filter('the_title', 'sticky_the_title');
function sticky_the_title($content) {
	global $post;
	if(strpos($_SERVER['REQUEST_URI'], '/edit.php') !== false && ($post->sticky_status > 0)) {
		 $content = post_sticky_status('', '', false).': '.$content;
	}
	return $content;
}


### Function: Sticky The Content
//add_filter('the_content', 'sticky_the_content');
function sticky_the_content($content) {
	global $post;
	$css_style = '';
	switch($post->sticky_status) {
		case 1:
			$css_style = "<script type=\"text/javascript\">window.document.getElementById('post-{$post->ID}').parentNode.className += 'sticky_post';</script>";
			break;
		case 2:
			$css_style = "<script type=\"text/javascript\">window.document.getElementById('post-{$post->ID}').parentNode.className += 'announcement_post';</script>";
			break;
	}
	return $css_style.$content;
}


### Function: Processing Sticky Post
add_action('save_post', 'add_sticky_admin_process');
function add_sticky_admin_process($post_ID) {
	global $wpdb;
	$post_status_sticky_status = intval($_POST['post_status_sticky']);
	// Normal Posts
	if($post_status_sticky_status == 0 && intval($post_ID) > 0) {
		$wpdb->query("DELETE FROM $wpdb->sticky WHERE sticky_post_id = $post_ID");
	// Sticky Post/ Announcement Post
	} else {
		// Ensure No Duplicate Field
		$check = intval($wpdb->get_var("SELECT sticky_status FROM $wpdb->sticky WHERE sticky_post_id = $post_ID"));
		if($check == 0) {
			$wpdb->query("INSERT INTO $wpdb->sticky VALUES($post_ID, $post_status_sticky_status)");
		} else {
			$wpdb->query("UPDATE $wpdb->sticky SET sticky_status = $post_status_sticky_status WHERE sticky_post_id = $post_ID");
		}
	}
}


### Function: Delete Away Sticky If Post Is Deleted
add_action('delete_post', 'delete_sticky_admin_process');
function delete_sticky_admin_process($post_ID) {
	global $wpdb;
	$wpdb->query("DELETE FROM $wpdb->sticky WHERE sticky_post_id = $post_ID");
}


### Function: Add Sticky To Admin
add_action('dbx_post_sidebar', 'sticky_admin');
function sticky_admin() {
	global $wpdb;
	$edit_post = intval($_GET['post']);
	$post_status_sticky_id = 0;
	$post_status_sticky_status  = 0;
	if($edit_post > 0) {
		$post_status_sticky_status = intval($wpdb->get_var("SELECT sticky_status FROM $wpdb->sticky WHERE sticky_post_id = $edit_post"));
	}
?>
<fieldset id="poststickystatusdiv" class="dbx-box">
	<h3 class="dbx-handle"><?php _e('Post Sticky Status', 'wp-sticky'); ?></h3> 
	<div class="dbx-content">
		<label for="post_status_announcement" class="selectit"><input type="radio" id="post_status_announcement" name="post_status_sticky" value="2"<?php checked($post_status_sticky_status, 2); ?>/>&nbsp;<?php _e('Announcement', 'wp-sticky'); ?></label>
		<label for="post_status_sticky" class="selectit"><input type="radio" id="post_status_sticky" name="post_status_sticky" value="1"<?php checked($post_status_sticky_status, 1); ?>/>&nbsp;<?php _e('Sticky', 'wp-sticky'); ?></label>
		<label for="post_status_normal" class="selectit"><input type="radio" id="post_status_normal" name="post_status_sticky" value="0"<?php checked($post_status_sticky_status, 0); ?>/>&nbsp;<?php _e('Normal', 'wp-sticky'); ?></label>
	</div>
</fieldset>
<?php
}


### Function: Sticky Options
function sticky_options() {
	global $wpdb;
	$text = '';
	$sticky_options = array();
	$sticky_options = get_option('sticky_options');
	if($_POST['Submit']) {
		$sticky_options['display_date'] = intval($_POST['display_date']);
		$sticky_options['category_only'] = intval($_POST['category_only']);
		$sticky_options['announcement_banner'] = trim($_POST['announcement_banner']);		
		$update_sticky_options = update_option('sticky_options', $sticky_options);
		if($update_sticky_options) {
			$text = '<font color="green">'.__('Sticky Options Updated', 'wp-sticky').'</font>';
		}
		if(empty($text)) {
			$text = '<font color="red">'.__('No Sticky Option Updated', 'wp-sticky').'</font>';
		}
	}
?>
<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.$text.'</p></div>'; } ?>
<!-- Sticky Options -->
<div class="wrap">
	<h2><?php _e('Sticky Options', 'wp-sticky'); ?></h2>
	<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
		<table width="100%" cellspacing="3" cellpadding="3" border="0">
			<tr>
				<td valign="top"><strong><?php _e('Categories Only:', 'wp-sticky'); ?></strong></td>
				<td>
					<select name="category_only">
						<option value="0"<?php selected('0', $sticky_options['category_only']); ?>><?php _e('No', 'wp-sticky'); ?></option>
						<option value="1"<?php selected('1', $sticky_options['category_only']); ?>><?php _e('Yes', 'wp-sticky'); ?></option>
					</select>
					<br /><?php _e('Display announcement and sticky posts only when viewing categories.', 'wp-sticky'); ?>
				</td>
			</tr>
			<tr>
				<td valign="top"><strong><?php _e('Display Date:', 'wp-sticky'); ?></strong></td>
				<td>
					<select name="display_date">
						<option value="0"<?php selected('0', $sticky_options['display_date']); ?>><?php _e('No', 'wp-sticky'); ?></option>
						<option value="1"<?php selected('1', $sticky_options['display_date']); ?>><?php _e('Yes', 'wp-sticky'); ?></option>
					</select>
					<br /><?php _e('Displays the date instead of the <strong>Announcement Banner</strong> on announcement posts.', 'wp-sticky'); ?>
				</td>
			</tr>
			<tr>
				<td valign="top"><strong><?php _e('Announcement Banner:', 'wp-sticky'); ?></strong></td>
				<td>
					<input type="text" name="announcement_banner" size="60" value="<?php echo htmlentities($sticky_options['announcement_banner']); ?>" />
					<br /><?php _e('This banner is displayed instead of the date if you choose \'No\' for <strong>Display Date</strong>.', 'wp-sticky'); ?>
				</td>
			</tr>
			<tr>
				<td width="100%" colspan="2" align="center"><input type="submit" name="Submit" class="button" value="<?php _e('Update Options', 'wp-sticky'); ?>" />&nbsp;&nbsp;<input type="button" name="cancel" value="<?php _e('Cancel', 'wp-sticky'); ?>" class="button" onclick="javascript:history.go(-1)" /></td>
			</tr>
		</table>
	</form>
<?php
}


### Function: Sticky Init
add_action('activate_sticky/sticky.php', 'sticky_init');
function sticky_init() {
	global $wpdb;
	include_once(ABSPATH.'/wp-admin/upgrade-functions.php');
	// Create Sticky Table
	$create_sticky_sql = "CREATE TABLE $wpdb->sticky (".
								"sticky_post_id bigint(20) NOT NULL,".
								"sticky_status tinyint(1) NOT NULL default '0',".
								"PRIMARY KEY (sticky_post_id))";
	maybe_create_table($wpdb->sticky, $create_sticky_sql);
	// Delete Options First
	delete_option('sticky_options');
	// Add Options
	$sticky_options = array();
	$sticky_options['category_only'] = 0;
	$sticky_options['display_date'] = 0;
	$sticky_options['announcement_banner'] = __('Announcement', 'wp-sticky');
	add_option('sticky_options', $sticky_options, 'Sticky Options');
}
?>