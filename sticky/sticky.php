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


### Function: Sticky Option Menu
add_action('admin_menu', 'sticky_menu');
function sticky_menu() {
	if (function_exists('add_options_page')) {
		add_options_page(__('Sticky', 'wp-sticky'), __('Sticky', 'wp-sticky'), 'manage_options', 'sticky/sticky.php', 'sticky_options');
	}
}


### Function: WHERE Clause Of The Sticky Query
add_filter('posts_where', 'sticky_posts_where');
function sticky_posts_where($where) {
	$sticky_options = get_option('sticky_options');
	if(intval($sticky_options['category_only']) == 0 || is_category()) {
		$where = ' AND 0=1 '.$where;
	}
	return $where;
}


### Functiom: Get Sticky Posts
add_filter('the_posts', 'sticky_the_posts');
function sticky_the_posts($posts) {
	global $wpdb, $wp_query, $request, $q;
	$sticky_options = get_option('sticky_options');
	if(!intval($sticky_options['category_only']) || is_category()) {
		if($stickies = $wpdb->get_col("SELECT $wpdb->postmeta.Post_ID FROM $wpdb->postmeta WHERE meta_key = 'sticky' AND meta_value = '1'")) {
			$insticky = 'NOT (ID IN ('.implode(',', $stickies).')),';
		} else {
			$insticky = '';
			$stickies = array();
		}
		if($request == '') {
			$request = $wp_query->request;
		}
		$request = str_replace(' AND 0=1 ', '', $request);
		$wp_query->request = $request;
		$request = preg_replace("/ORDER BY post_{$q['orderby']}/", "ORDER BY $insticky post_{$q['orderby']}", $request);
		unset($posts);
		$posts = array();
		if($temp = $wpdb->get_results($request)) {
			foreach($temp as $post) {
				$post->is_sticky = (in_array($post->ID, $stickies));
				array_push($posts, $post);
			}
		}
	}
	return $posts;
}


### Function: Sticky The Date
add_filter('the_date', 'sticky_the_date');
function sticky_the_date($content) {
	global $post, $previousday;
	$sticky_options = get_option('sticky_options');
	if($post->is_sticky && intval($sticky_options['display_date']) == 0) {
		$previousday = '';
		return $sticky_options['sticky_banner'];
	}
	return $content;
}


### Function: Sticky The Title
add_filter('the_title', 'sticky_the_title');
function sticky_the_title($content) {
	global $post;
	$sticky_options = get_option('sticky_options');
	//if(preg_match('|edit.php|i', $_SERVER['REQUEST_URI']) && ($post->is_sticky)) {
	if($post->is_sticky) {
		 $content = $sticky_options['sticky_text'].$content;
	}
	return $content;
}


### Function: Sticky The Content
//add_filter('the_content', 'sticky_the_content');
function sticky_the_content($content) {
	global $post;
	return ($post->is_sticky) ?
		"<script type=\"text/javascript\">window.document.getElementById('post-{$post->ID}').parentNode.className += ' adhesive_post';</script>{$content}" :
		$content;
}


### Function: Processing Sticky Post
add_action('edit_post', 'add_sticky_admin_process');
add_action('publish_post', 'add_sticky_admin_process');
add_action('save_post', 'add_sticky_admin_process');
function add_sticky_admin_process($post_ID) {
	$post_status_sticky = intval($_POST['post_status_sticky']);
	$check_sticky = get_post_meta($post_ID, 'sticky', true);
	if(empty($check_sticky)) {
		add_post_meta($post_ID, 'sticky', $post_status_sticky);
	} else {
		update_post_meta($post_ID, 'sticky', $post_status_sticky);
	}
}


### Function: Add Sticky To Admin
add_action('edit_page_form', 'add_sticky_admin');
add_action('edit_form_advanced', 'add_sticky_admin');
add_action('simple_edit_form', 'add_sticky_admin');
function add_sticky_admin($content) {
	global $wpdb;
	if(isset($_REQUEST['post'])) {
		$post_status_sticky = intval($wpdb->get_var("SELECT meta_value FROM $wpdb->postmeta WHERE Post_ID = {$_REQUEST['post']} AND meta_key = 'sticky';"));
	}
?>
<div id="stickydiv">
	<label for="post_status_sticky" class="selectit"><input type="checkbox" id="post_status_sticky" name="post_status_sticky" value="1"<?php checked($post_status_sticky, 1); ?>/>&nbsp;<?php _e('Sticky', 'wp-sticky'); ?></label>
</div>
<script type="text/javascript">
	var placement = document.getElementById("post_status_private");
	var substitution = document.getElementById("stickydiv");
	var mozilla = document.getElementById&&!document.all;
	if(mozilla) {
		placement.parentNode.parentNode.appendChild(substitution);
	} else { 
		placement.parentElement.parentElement.appendChild(substitution);
	}
</script>
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
		$sticky_options['sticky_banner'] = trim($_POST['sticky_banner']);		
		$sticky_options['sticky_text'] = $_POST['sticky_text'];
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
					<br /><?php _e('Display sticky posts only when viewing categories.', 'wp-sticky'); ?>
				</td>
			</tr>
			<tr>
				<td valign="top"><strong><?php _e('Display Date:', 'wp-sticky'); ?></strong></td>
				<td>
					<select name="display_date">
						<option value="0"<?php selected('0', $sticky_options['display_date']); ?>><?php _e('No', 'wp-sticky'); ?></option>
						<option value="1"<?php selected('1', $sticky_options['display_date']); ?>><?php _e('Yes', 'wp-sticky'); ?></option>
					</select>
					<br /><?php _e('Displays the date instead of the <strong>Sticky Banner</strong> on sticky posts.<br />You can make use of the <strong>Sticky Text</strong> to denote a sticky post if you choose \'Yes\' for this option.', 'wp-sticky'); ?>
				</td>
			</tr>
			<tr>
				<td valign="top"><strong><?php _e('Sticky Banner:', 'wp-sticky'); ?></strong></td>
				<td>
					<input type="text" name="sticky_banner" size="60" value="<?php echo htmlentities($sticky_options['sticky_banner']); ?>" />
					<br /><?php _e('This banner is displayed instead of the date if you choose \'No\' for <strong>Display Date</strong>.', 'wp-sticky'); ?>
				</td>
			</tr>
			<tr>
				<td valign="top"><strong><?php _e('Sticky Text:', 'wp-sticky'); ?></strong></td>
				<td>
					<input type="text" name="sticky_text" size="60" value="<?php echo htmlentities($sticky_options['sticky_text']); ?>" />
					<br /><?php _e('Text to display beside the post title if the post is a sticky post.', 'wp-sticky'); ?>
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
	// Delete Options First
	delete_option('sticky_options');
	// Add Options
	$sticky_options = array();
	$sticky_options['category_only'] = 0;
	$sticky_options['display_date'] = 0;
	$sticky_options['sticky_banner'] = __('Announcement', 'wp-sticky');
	$sticky_options['sticky_text'] = __('Sticky: ', 'wp-sticky');
	add_option('sticky_options', $sticky_options, 'Sticky Options');
}
?>