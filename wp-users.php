<?php
/*
Plugin Name: WordPress Users
Plugin URI: http://kempwire.com/wordpress-users-plugin
Description: Display your WordPress users and user profiles.
Version: 1.4
Author: Jonathan Kemp
Author URI: http://kempwire.com/

Copyright 2009-2011  Jonathan Kemp  (email : kempdogg@gmail.com)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
    
*/

add_action('admin_menu', 'wpu_admin_menu');
add_action('wp_head', 'noindex_users');
add_action('wp_head', 'wpu_styles');
add_filter('the_content', 'wpu_get_users', 1);

function wpu_get_users($content) {  
	if(is_page(get_option('wpu_page_id'))) {
		
		if(isset($_GET['uid'])) {
			display_user();	
		} else {
			echo $content;
			display_user_list();
		}
	} else {
		//display the content
		return $content;
	}
}

function wpu_get_roles()
{
	global $wpdb;
	
	$administrator = get_option('wpu_roles_admin');
	$subscriber = get_option('wpu_roles_subscriber');
	$author = get_option('wpu_roles_author');
	$editor = get_option('wpu_roles_editor');
	$contributor = get_option('wpu_roles_contributor');
	
	$rolelist = array('administrator'=>$administrator, 'subscriber'=>$subscriber, 'author'=>$author, 'editor'=>$editor, 'contributor'=>$contributor);
	
	$roles = array();
	
	foreach($rolelist as $key=>$value)
	{
		if($value == 'yes')
			array_push($roles, $key);
		else
		{}
	}
	
	if (empty($roles))
		$roles = array('administrator', 'subscriber', 'author', 'editor', 'contributor');

	$searches = array();

	foreach ( $roles as $role )
		$searches[] = "$wpdb->usermeta.meta_key = '{$wpdb->prefix}capabilities' AND $wpdb->usermeta.meta_value LIKE '%$role%'";
		
	//create a string for use in a MySQL statement
	$meta_values = implode(' OR ', $searches);
	
	return $meta_values;
}

function display_user_list() {

	// if $_GET['page'] defined, use it as page number
	if(isset($_GET['page'])) {
	    $page = $_GET['page'];
	} else {
		// by default we show first page
		$page = 1;
	}
	$limit = get_option('wpu_users_per');
	
	// counting the offset
	$offset = ($page - 1) * $limit;
	
	// Get the authors from the database ordered by user nicename
	global $wpdb;
	$meta_values = wpu_get_roles();
	
	$query = "SELECT $wpdb->users.ID, $wpdb->users.user_nicename FROM $wpdb->users INNER JOIN $wpdb->usermeta ON $wpdb->users.ID = $wpdb->usermeta.user_id WHERE $meta_values ORDER BY $wpdb->users.user_nicename LIMIT $offset, $limit";
	$author_ids = $wpdb->get_results($query);
	
        
    $output = '';

	// Loop through each author
	foreach($author_ids as $author) {

		// Get user data
		$curauth = get_userdata($author->ID);

		$output .= get_user_listing($curauth);
	}
         
	echo $output;

	// how many rows we have in database
	$totalitems = $wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->users WHERE ID = ANY (SELECT user_id FROM $wpdb->usermeta WHERE $meta_values)");

	$adjacents = 3;

	$concat = wpu_concat_index();

	echo getPaginationString($page, $totalitems, $limit, $adjacents, $concat);
}

function getPaginationString($page = 1, $totalitems, $limit = 15, $adjacents = 1, $concat)
{		
	//defaults
	if(!$adjacents) $adjacents = 1;
	if(!$limit) $limit = 15;
	if(!$page) $page = 1;
	
	//other vars
	$prev = $page - 1;									//previous page is page - 1
	$next = $page + 1;									//next page is page + 1
	$lastpage = ceil($totalitems / $limit);				//lastpage is = total items / items per page, rounded up.
	$lpm1 = $lastpage - 1;								//last page minus 1
	
	/* 
		Now we apply our rules and draw the pagination object. 
		We're actually saving the code to a variable in case we want to draw it more than once.
	*/
	$pagination = "";
	if($lastpage > 1)
	{	
		$pagination .= "<div class=\"wpu-pagination\"";
		$pagination .= ">";

		//previous button
		if ($page > 1) 
			$pagination .= "<a href=\"" . get_permalink() . $concat . "page=$prev\">« prev</a>";
		else
			$pagination .= "<span class=\"wpu-disabled\">« prev</span>";	
		
		//pages	
		if ($lastpage < 7 + ($adjacents * 2))	//not enough pages to bother breaking it up
		{	
			for ($counter = 1; $counter <= $lastpage; $counter++)
			{
				if ($counter == $page)
					$pagination .= "<span class=\"wpu-current\">$counter</span>";
				else
					$pagination .= "<a href=\"" . get_permalink() . $concat . "page=$counter\">$counter</a>";					
			}
		}
		elseif($lastpage >= 7 + ($adjacents * 2))	//enough pages to hide some
		{
			//close to beginning; only hide later pages
			if($page < 1 + ($adjacents * 3))		
			{
				for ($counter = 1; $counter < 4 + ($adjacents * 2); $counter++)
				{
					if ($counter == $page)
						$pagination .= "<span class=\"wpu-current\">$counter</span>";
					else
						$pagination .= "<a href=\"" . get_permalink() . $concat . "page=$counter\">$counter</a>";					
				}
				$pagination .= "<span class=\"wpu-elipses\">...</span>";
				$pagination .= "<a href=\"" . get_permalink() . $concat . "page=$lpm1\">$lpm1</a>";
				$pagination .= "<a href=\"" . get_permalink() . $concat . "page=$lastpage\">$lastpage</a>";		
			}
			//in middle; hide some front and some back
			elseif($lastpage - ($adjacents * 2) > $page && $page > ($adjacents * 2))
			{
				$pagination .= "<a href=\"" . get_permalink() . $concat . "page=1\">1</a>";
				$pagination .= "<a href=\"" . get_permalink() . $concat . "page=2\">2</a>";
				$pagination .= "<span class=\"wpu-elipses\">...</span>";
				for ($counter = $page - $adjacents; $counter <= $page + $adjacents; $counter++)
				{
					if ($counter == $page)
						$pagination .= "<span class=\"wpu-current\">$counter</span>";
					else
						$pagination .= "<a href=\"" . get_permalink() . $concat . "page=$counter\">$counter</a>";					
				}
				$pagination .= "...";
				$pagination .= "<a href=\"" . get_permalink() . $concat . "page=$lpm1\">$lpm1</a>";
				$pagination .= "<a href=\"" . get_permalink() . $concat . "page=$lastpage\">$lastpage</a>";		
			}
			//close to end; only hide early pages
			else
			{
				$pagination .= "<a href=\"" . get_permalink() . $concat . "page=1\">1</a>";
				$pagination .= "<a href=\"" . get_permalink() . $concat . "page=2\">2</a>";
				$pagination .= "<span class=\"wpu-elipses\">...</span>";
				for ($counter = $lastpage - (1 + ($adjacents * 3)); $counter <= $lastpage; $counter++)
				{
					if ($counter == $page)
						$pagination .= "<span class=\"wpu-current\">$counter</span>";
					else
						$pagination .= "<a href=\"" . get_permalink() . $concat . "page=$counter\">$counter</a>";					
				}
			}
		}
		
		//next button
		if ($page < $counter - 1) 
			$pagination .= "<a href=\"" . get_permalink() . $concat . "page=$next\">next »</a>";
		else
			$pagination .= "<span class=\"wpu-disabled\">next »</span>";
		$pagination .= "</div>\n";
	}
	
	return $pagination;

}

function wpu_concat_index()
{
	$url = $_SERVER['REQUEST_URI'];
	$permalink = get_permalink(get_the_id());
	
	if(strpos($permalink, '?'))
		return '&';
	else
		return '?';
}

function wpu_concat_single()
{
	$url = $_SERVER['REQUEST_URI'];
	$permalink = get_permalink(get_the_id());
	
	if(strpos($permalink, '?'))
		return '&';
	else
		return '?';
}

function get_user_listing($curauth) {  
	global $post;
	$concat = wpu_concat_single();
	
	$html = "<div class=\"wpu-user\">\n";
	if (get_option('wpu_image_list')) {
		if(get_option('wpu_avatars') == "gravatars") {
			$gravatar_type = get_option('wpu_gravatar_type');
			$gravatar_size = get_option('wpu_gravatar_size');
			$display_gravatar = get_avatar($curauth->user_email, $gravatar_size, $gravatar_type);
			$html .= "<div class=\"wpu-avatar\"><a href=\"" . get_permalink($post->ID) . $concat . "uid=$curauth->ID\" title=\"$curauth->display_name\">$display_gravatar</a></div>\n";
		} elseif (get_option('wpu_avatars') == "userphoto") {
			if(function_exists('userphoto_the_author_photo')) 
			{
				$html .= "<div class=\"wpu-avatar\"><a href=\"" . get_permalink($post->ID) . $concat . "uid=$curauth->ID\" title=\"$curauth->display_name\">" . userphoto__get_userphoto($curauth->ID, USERPHOTO_THUMBNAIL_SIZE, "", "", array(), "") . "</a></div>\n";
			}
		}
	}
	$html .= "<div class=\"wpu-id\"><a href=\"" . get_permalink($post->ID) . $concat . "uid=$curauth->ID\" title=\"$curauth->display_name\">$curauth->display_name</a></div>\n";
	if (get_option('wpu_description_list')) {
		if ($curauth->description) {
			if (get_option('wpu_description_limit')) {
				$desc_limit = get_option('wpu_description_limit');
				$html .=  "<div class=\"wpu-about\">" . substr($curauth->description, 0, $desc_limit) . " [...]</div>\n";
			} else {
				$html .=  "<div class=\"wpu-about\">" . $curauth->description . "</div>\n";
			}
		}
	}
	$html .= "</div>";
	return $html;
}

function display_user() {  
	global $post;
	
	if (isset($_GET['uid'])) {
		$uid = $_GET['uid'];
		$curauth = get_userdata($uid);
	}
	
	if ( $curauth ) {
		$recent_posts = get_posts( array( 'numberposts' => 10, 'author' => $curauth->ID ) );
		$recent_comments = wpu_recent_comments($uid);
		$created = date("F jS, Y", strtotime($curauth->user_registered));
		
		$html = "<p><a href=" . get_permalink($post->ID) . ">&laquo; Back to " . get_the_title($post->ID) . " page</a></p>\n";
		
		$html .= "<h2>$curauth->display_name</h2>\n";
		
		if (get_option('wpu_image_profile')) {
			if(get_option('wpu_avatars') == "gravatars") {
				$html .= "<p><a href=\"http://en.gravatar.com/\" title=\"Get your own avatar.\">" . get_avatar($curauth->user_email, '96', $gravatar) . "</a></p>\n";
			} elseif (get_option('wpu_avatars') == "userphoto") {
				if(function_exists('userphoto_the_author_photo')) 
				{
					$html .= "<p>" . userphoto__get_userphoto($curauth->ID, USERPHOTO_FULL_SIZE, "", "", array(), "") . "</p>\n";
				}
			}
		}
	
		if ($curauth->user_url && $curauth->user_url != "http://") {
			$html .= "<p><strong>Website:</strong> <a href=\"$curauth->user_url\" rel=\"nofollow\">$curauth->user_url</a></p>\n";
		}
		
		$html .= "<p><strong>Joined on:</strong>  " . $created . "</p>";
		
		if (get_option('wpu_description_profile')) {
			if ($curauth->description) {
				$html .= "<p><strong>Profile:</strong></p>\n";
				$html .= "<p>$curauth->description</p>\n";
			}
		}
		
		if ($recent_posts) {
			$html .= "<h3>Recent Posts by $curauth->display_name</h3>\n";
			$html .= "<ul>\n";
			foreach( $recent_posts as $post )
			{
				setup_postdata($post);
				
				$html .= "<li><a href=" . get_permalink($post->ID) . ">" . $post->post_title . "</a></li>";
			}
			$html .= "</ul>\n";
		}
		
		wp_reset_query();
		
		if ($recent_comments) {
			$html .= "<h3>Recent Comments by $curauth->display_name</h3>\n";
			$html .= "<ul>\n";
			foreach($recent_comments as $key=>$comment)
			{
				$html .= "<li>\"" . $comment->comment_content . "\" on <a href=" . get_permalink($comment->comment_post_ID) . "#comment-" . $comment->comment_ID . ">" . get_the_title($comment->comment_post_ID) . "</a></li>";
			}
			$html .= "</ul>\n";
		}
		
		echo "<div id=\"wpu-profile\">
		";
		echo $html;
		echo "</div>
		";
	}
}

function wpu_recent_comments($uid)
{
	global $wpdb;
	
	$comments = $wpdb->get_results( $wpdb->prepare("SELECT comment_ID, comment_post_ID, SUBSTRING(comment_content, 1, 150) AS comment_content
	FROM $wpdb->comments
	WHERE user_id = %s
	ORDER BY comment_ID DESC
	LIMIT 10
	", $uid ) );

	return $comments;
}

function noindex_users() {
	if(is_page(get_option('wpu_page_id')) && get_option('wpu_noindex_users') == 'yes') {
		if($_GET['uid'] == "")
			echo '	<meta name="robots" content="noindex, follow"/>
			';
	}
}

// 2.0 Feature
function wpu_styles() {
	if(is_page(get_option('wpu_page_id'))) {
		echo '<link href="' . get_bloginfo ( 'wpurl' ) . '/wp-content/plugins/wordpress-users/wpu-styles.css" rel="stylesheet" type="text/css" />
		';
	}
}

function wpu_admin_menu() {  
	add_options_page('WordPress Users Options', 'WordPress Users', 'manage_options', __FILE__, 'wpu_admin');
}

function wpu_admin() {
	if($_POST['wpu_hidden'] == 'Y') {
		//Form data sent
		$pageid = $_POST['wpu_page_id'];
		update_option('wpu_page_id', $pageid);
			
		$usersperpage = $_POST['wpu_users_per'];
		update_option('wpu_users_per', $usersperpage);
			
		$avatars = $_POST['wpu_avatars'];
		update_option('wpu_avatars', $avatars);
		
		$gravatar_type = $_POST['wpu_gravatar_type'];
		update_option('wpu_gravatar_type', $gravatar_type);
			
		$gravatar_size = $_POST['wpu_gravatar_size'];
		update_option('wpu_gravatar_size', $gravatar_size);
		
		$noindex_users = $_POST['wpu_noindex_users'];
		update_option('wpu_noindex_users', $noindex_users);
		
		$roles_admin = $_POST['wpu_roles_admin'];
		update_option('wpu_roles_admin', $roles_admin);
		
		$roles_editor = $_POST['wpu_roles_editor'];
		update_option('wpu_roles_editor', $roles_editor);
		
		$roles_author = $_POST['wpu_roles_author'];
		update_option('wpu_roles_author', $roles_author);
		
		$roles_contributor = $_POST['wpu_roles_contributor'];
		update_option('wpu_roles_contributor', $roles_contributor);
		
		$roles_subscriber = $_POST['wpu_roles_subscriber'];
		update_option('wpu_roles_subscriber', $roles_subscriber);
		
		$image_list = $_POST['wpu_image_list'];
		update_option('wpu_image_list', $image_list);
		
		$description_list = $_POST['wpu_description_list'];
		update_option('wpu_description_list', $description_list);
		
		$image_profile = $_POST['wpu_image_profile'];
		update_option('wpu_image_profile', $image_profile);
		
		$description_profile = $_POST['wpu_description_profile'];
		update_option('wpu_description_profile', $description_profile);
		
		$desc_limit = $_POST['wpu_description_limit'];
		update_option('wpu_description_limit', $desc_limit);
	?>  
    	<div class="updated"><p><strong><?php _e('Options saved.' ); ?></strong></p></div>
	<?php  
	} else {
		//Normal page display
		$pageid = get_option('wpu_page_id');
		$usersperpage = get_option('wpu_users_per');
		$gravatar_type = get_option('wpu_gravatar_type');
		$gravatar_size = get_option('wpu_gravatar_size');
		$noindex_users = get_option('wpu_noindex_users');
		$image_list = get_option('wpu_image_list');
		$description_list = get_option('wpu_description_list');
		$image_profile = get_option('wpu_image_profile');
		$description_profile = get_option('wpu_description_profile');
		$desc_limit = get_option('wpu_description_limit');
		
		if (empty($usersperpage))	$usersperpage = 10;
		if(get_option('wpu_avatars') == 1) {
			if (empty($gravatar_type)) 	$gravatar_type = "mystery";
			if (empty($gravatar_size)) 	$gravatar_size = 80;
		}
	}
?>
	
	<div class="wrap">
		<h2><?php _e('WordPress Users Options') ?></h2>
			
		<form name="wpu_admin_form" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
			<input type="hidden" name="wpu_hidden" value="Y">
            <table>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td><?php _e("Page ID: " ); ?>&nbsp;</td>
                	<td colspan="2"><input type="text" name="wpu_page_id" value="<?php echo $pageid; ?>" size="3">&nbsp; ID of the page on which you want to display the user directory.</td>
                </tr>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td><?php _e("Users Per Page: " ); ?>&nbsp;</td>
                	<td colspan="2"><input type="text" name="wpu_users_per" value="<?php echo $usersperpage; ?>" size="3">&nbsp; How many users you want to display at once.</td>
                </tr>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td><?php _e("Noindex User Listings: " ); ?>&nbsp;</td>
                	<td colspan="2"><input name="wpu_noindex_users" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_noindex_users')); ?> />&nbsp; Insert robots noindex meta tag on user listings to prevent search engine indexing.</td>
                </tr>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td colspan="3">
                    	<h3>Select Which Users to Display</h3>
                        <p><small><strong>Note:</strong> If no options are selected, all users will be displayed.</small></p>
                    </td>
                </tr>
                <tr>
                	<td colspan="3"><input name="wpu_roles_admin" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_roles_admin')); ?> />&nbsp; <?php _e("Administrator" ); ?></td>
                </tr>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td colspan="3"><input name="wpu_roles_editor" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_roles_editor')); ?> />&nbsp; <?php _e("Editors" ); ?></td>
                </tr>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td colspan="3"><input name="wpu_roles_author" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_roles_author')); ?> />&nbsp; <?php _e("Authors" ); ?></td>
                </tr>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td colspan="3"><input name="wpu_roles_contributor" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_roles_contributor')); ?> />&nbsp; <?php _e("Contribtors" ); ?></td>
                </tr>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td colspan="3"><input name="wpu_roles_subscriber" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_roles_subscriber')); ?> />&nbsp; <?php _e("Subscribers" ); ?></td>
                </tr>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td colspan="3"><h3>Profile Options</h3></td>
                </tr>
                <tr>
                	<td colspan="3"><input name="wpu_image_list" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_image_list')); ?> />&nbsp; <?php _e("Display user images on directory page." ); ?></td>
                </tr>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td colspan="3"><input name="wpu_description_list" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_description_list')); ?> />&nbsp; <?php _e("Display user descriptions on directory page." ); ?></td>
                </tr>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td colspan="3"><input name="wpu_image_profile" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_image_profile')); ?> />&nbsp; <?php _e("Display user images on profile page." ); ?></td>
                </tr>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td colspan="3"><input name="wpu_description_profile" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_description_profile')); ?> />&nbsp; <?php _e("Display user descriptions on profile page." ); ?></td>
                </tr>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td colspan="3"><input type="text" name="wpu_description_limit" value="<?php echo $desc_limit; ?>" size="3">&nbsp; <?php _e("Number of characters to display of user description on the directory page." ); ?></td>
                </tr>
                <tr>
                	<td colspan="3">
                        <p><small><strong>Note:</strong> If no limit is specified, entire user description will be displayed.</small></p>
                    </td>
                </tr>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td colspan="3"><h3>Avatar Options</h3></td>
                </tr>
                <tr>
                	<td><?php _e("Avatar Type: " ); ?></td>
                	<td colspan="2"><input id="wpu_avatars_gravatars" type="radio" name="wpu_avatars" value="gravatars" <?php checked('gravatars', get_option('wpu_avatars')); ?> /> Gravatars</td>
                </tr>
                <tr>
                	<td></td>
                	<td colspan="2"><input id="wpu_avatars_userphoto" type="radio" name="wpu_avatars" value="userphoto" <?php checked('userphoto', get_option('wpu_avatars')); ?> /> User Photo</td>
                </tr>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td colspan="3"><strong>Gravatar Options:</strong></td>
                </tr>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td><?php _e("Gravatar Type: " ); ?>&nbsp;</td>
                	<td><input type="text" name="wpu_gravatar_type" value="<?php echo $gravatar_type; ?>" size="15">&nbsp; Gravatar type - ex. mystery, blank, gravatar_default, identicon, wavatar, monsterid</td>
                </tr>
                <tr>
                	<td>&nbsp;</td>
                	<td>&nbsp;</td>
                    <td></td>
                </tr>
                <tr>
                	<td><?php _e("Gravatar Size: " ); ?>&nbsp;</td>
                	<td><input type="text" name="wpu_gravatar_size" value="<?php echo $gravatar_size; ?>" size="2"> px &nbsp; Size of gravatar in the user listings.</td>
                </tr>
                <tr>
                	<td colspan="3"><p class="submit"><input type="submit" name="Submit" value="<?php _e('Update Options') ?>" /></p></td>
                </tr>
            </table>
		</form>
	</div>
<?php
}
?>