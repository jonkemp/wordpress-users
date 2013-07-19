<?php
/*
Plugin Name: User List
Plugin URI: http://kempwire.com/wordpress-user-list-plugin
Description: Display your WordPress users, profiles, avatars, images and uploaded files.
Version: 1.5
Author: Jonathan Kemp, Ivan Jakesevic
Author URI: http://kempwire.com/

Copyright 2009-2013  Jonathan Kemp  (email : kempdogg@gmail.com), Ivan Jakesevic (email:ivan82@gmail.com)

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
load_plugin_textdomain('user-list', PLUGINDIR . '/user-list/localization');

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


function wpu_get_roles(){
    global $wpdb;

    $administrator = get_option('wpu_roles_admin');
    $subscriber = get_option('wpu_roles_subscriber');
    $author = get_option('wpu_roles_author');
    $editor = get_option('wpu_roles_editor');
    $contributor = get_option('wpu_roles_contributor');

    $rolelist = array('administrator'=>$administrator, 'subscriber'=>$subscriber, 'author'=>$author, 'editor'=>$editor, 'contributor'=>$contributor);
    $roles = array();
    $searches = array();

    foreach($rolelist as $key=>$value)
    {
        if($value == 'yes'){
            array_push($roles, $key);
        }
    }

    if (empty($roles)){
        $roles = array('administrator', 'subscriber', 'author', 'editor', 'contributor');
    }

    
    foreach ( $roles as $role ){
        $searches[] = "$wpdb->usermeta.meta_key = '{$wpdb->prefix}capabilities' AND $wpdb->usermeta.meta_value LIKE '%$role%'";
    }
    
    //create a string for use in a MySQL statement
    $meta_values = implode(' OR ', $searches);

    return $meta_values;
}

function display_user_list() {
    global $wpdb;
    $onPage = get_query_var('page');
    $limit = get_option('wpu_users_per');
    $adjacents = get_option('wpu_pagination_adjacents');
    
    
    //get all user roles to be shown
    $meta_values = wpu_get_roles();
    
    //how many rows we have in database
    $totalitems = $wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->users WHERE ID = ANY (SELECT user_id FROM $wpdb->usermeta WHERE $meta_values)");
    $totalPages = ceil($totalitems / $limit);
    
    //if the user tryes to manipulate qury, set min and max..
    if(!$onPage || $onPage < 1){
        $onPage = 1;
    }elseif($onPage > $totalPages){
        $onPage = $totalPages;
    }
    
    
    //counting the offset
    $offset = ($onPage - 1) * $limit;

    //get the authors from the database ordered by user nicename
    $query = "SELECT $wpdb->users.ID, $wpdb->users.user_nicename FROM $wpdb->users INNER JOIN $wpdb->usermeta ON $wpdb->users.ID = $wpdb->usermeta.user_id WHERE $meta_values ORDER BY $wpdb->users.user_nicename LIMIT $offset, $limit";
    $author_ids = $wpdb->get_results($query);

    //loop through each author
    foreach($author_ids as $author) {
        // Get user data
        $curauth = get_userdata($author->ID);
        $html .= get_user_listing($curauth);
    }

    //pagination string
    $html .= getPaginationString($totalitems, $limit, $onPage, $adjacents);
    
    echo $html;
}

function get_user_listing($curauth) {  
    global $post;
    $concat = wpu_concat();

    $html = "<div class=\"wpu-user\">\n";
    
    //image
    if (get_option('wpu_image_list')) {
        //avatar
        if(get_option('wpu_avatars') == "gravatars") {
            $gravatar_type = get_option('wpu_gravatar_type');
            $gravatar_size = get_option('wpu_gravatar_size');
            $display_gravatar = get_avatar($curauth->user_email, $gravatar_size, $gravatar_type);
            $html .= "<div class=\"wpu-avatar\"><a href=\"" . get_permalink($post->ID) . $concat . "uid=$curauth->ID\" title=\"$curauth->display_name\">$display_gravatar</a></div>\n";
      
        //user photo
        }elseif (get_option('wpu_avatars') == "userphoto" && function_exists('userphoto_the_author_photo')) {
            $html .= "<div class=\"wpu-avatar\"><a href=\"" . get_permalink($post->ID) . $concat . "uid=$curauth->ID\" title=\"$curauth->display_name\">" . userphoto__get_userphoto($curauth->ID, USERPHOTO_THUMBNAIL_SIZE, "", "", array(), "") . "</a></div>\n";
        }
    }
    
    //name
    $html .= "<div class=\"wpu-id\"><a href=\"" . get_permalink($post->ID) . $concat . "uid=$curauth->ID\" title=\"$curauth->display_name\">$curauth->display_name</a></div>\n";
    
    //description
    if (get_option('wpu_description_list')) {
        $description = $curauth->description;
        if ($description) {
            if (get_option('wpu_description_limit')) {
                $desc_limit = get_option('wpu_description_limit');
                $description = substr($description, 0, $desc_limit) . "...";
            } 

            $description = preg_replace('/\n/', '<br/>', $description);
            $html .=  "<div class=\"wpu-about\">" . $description . "</div>\n";
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

    if (!$curauth) {
        return;
    }
    
    $recent_posts = get_posts( array( 'numberposts' => 10, 'author' => $curauth->ID ) );
    $recent_comments = wpu_recent_comments($uid);
    $created = date("F jS, Y", strtotime($curauth->user_registered));

    $html = "<p><a href=" . get_permalink($post->ID) . ">&laquo; ".__("Back to", "user-list")." ". get_the_title($post->ID) . " ". __("page", "user-list") ."</a></p>\n";

    $html .= "<h2>$curauth->display_name</h2>\n";

    if (get_option('wpu_image_profile')) {
        if(get_option('wpu_avatars') == "gravatars") {
            $html .= "<p><a href=\"http://en.gravatar.com/\" title=\"".__("Get your own avatar", "user-list")."\">" . get_avatar($curauth->user_email, '96', $gravatar) . "</a></p>\n";
        } elseif (get_option('wpu_avatars') == "userphoto" && function_exists('userphoto_the_author_photo')) {
            $html .= "<p>" . userphoto__get_userphoto($curauth->ID, USERPHOTO_FULL_SIZE, "", "", array(), "") . "</p>\n";
        }
    }

    if ($curauth->user_url && $curauth->user_url != "http://") {
        $html .= "<p><strong>".__("Website", "user-list").":</strong> <a href=\"$curauth->user_url\" rel=\"nofollow\">$curauth->user_url</a></p>\n";
    }

    $html .= "<p><strong>".__("Joined on", "user-list").":</strong>  " . $created . "</p>";

    if (get_option('wpu_description_profile')) {
        $description = $curauth->description;
        if ($curauth->description) {
            $description = preg_replace('/\n/', '<br/>', $description);
            $html .= "<p><strong>".__("Profile", "user-list").":</strong></p>\n";
            $html .= "<p>$description</p>\n";
        }
    }

    if(get_option('wpu_user_files') == 'yes'){
        $html .= "<h3>".__("Files", "user-list")."</h3>\n";
        $html .= get_user_files();
    }	




    if ($recent_posts) {
        $html .= "<h3>".__("Recent Posts by", "user-list")." $curauth->display_name</h3>\n";
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
        $html .= "<h3>".__("Recent Comments by", "user-list")." $curauth->display_name</h3>\n";
        $html .= "<ul>\n";
        foreach($recent_comments as $key=>$comment)
        {
            $html .= "<li>\"" . $comment->comment_content . "\" ".__("on", "user-list")." <a href=" . get_permalink($comment->comment_post_ID) . "#comment-" . $comment->comment_ID . ">" . get_the_title($comment->comment_post_ID) . "</a></li>";
        }
        $html .= "</ul>\n";
    }

    if (get_current_user_id() == $uid) {
        $html .= "<br /><a href=\"/wp-admin/profile.php\">".__("Edit profile", "user-list"). "</a>";
    }
  
    echo "<div id=\"wpu-profile\">" . $html . "</div>";
    
}															

function get_user_files(){
    $html = '';
    $upload_dir = wp_upload_dir();
    $user_id = func_get_arg(0);

    if(!$user_id){
       $user_id = $_GET['uid'];
    }

    if($user_id == 0){
        return;
    }


    $handle = @opendir($upload_dir['basedir'].'/file_uploads/'.$user_id);
    if(!$handle){
      return;
    }

    while (false !== ($file = readdir($handle))) {
        if ($file!= "." && $file != "..") {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            $ext = pathinfo($file, PATHINFO_EXTENSION); 
            $dLink = '/wp-content/uploads/file_uploads/'.$user_id .'/'. $file;

            $html .= '<a href="'.$dLink .'" target="_blank"><img src="'. SetIcon($ext) .'" /> '. $filename .'</a><br />';	
        }
    }

    return $html;
}																

function wpu_recent_comments($uid){
    global $wpdb;

    $comments = $wpdb->get_results( $wpdb->prepare("SELECT comment_ID, comment_post_ID, SUBSTRING(comment_content, 1, 150) AS comment_content
    FROM $wpdb->comments
    WHERE user_id = %s
    ORDER BY comment_ID DESC
    LIMIT 10
    ", $uid));

    return $comments;
}


function noindex_users() {
    if(is_page(get_option('wpu_page_id')) && get_option('wpu_noindex_users') == 'yes' && $_GET['uid'] == "") {
        echo '<meta name="robots" content="noindex, follow"/>';
    }
}

function wpu_styles() {
    if(is_page(get_option('wpu_page_id'))) {
        echo '<link href="' . get_bloginfo ( 'wpurl' )."/". PLUGINDIR .'/user-list/user-list-styles.css" rel="stylesheet" type="text/css" />';
    }
}


function wpu_admin_menu() {  
    add_options_page('User List', 'User List', 'manage_options', __FILE__, 'wpu_admin');
}

function wpu_admin() {
    if($_POST['Submit'] != ''){
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

        $user_files = $_POST['wpu_user_files'];
        update_option('wpu_user_files', $user_files); 
        
        $adjacents = $_POST['wpu_pagination_adjacents'];
        update_option('wpu_pagination_adjacents', $adjacents);
        
        ?>  
        
        
    
        <div class="updated"><p><strong><?php echo __("Options Saved", "user-list"); ?></strong></p></div>
    <?php } else {
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
        $user_files = get_option('wpu_user_files');
        $adjacents = get_option('wpu_pagination_adjacents');

        if (empty($usersperpage)){ $usersperpage = 10;}
        if(get_option('wpu_avatars') == 1) {
                if (empty($gravatar_type)) 	$gravatar_type = "mystery";
                if (empty($gravatar_size)) 	$gravatar_size = 80;
        }
    }?>
        
<form name="wpu_admin_form" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">

<div class="wrap">
<h2><?php echo __('User List Options', "user-list") ?></h2>
<table>
<tr>
    <td><?php echo __("Page ID", "user-list"); ?>:&nbsp;</td>
    <td colspan="2"><input type="text" name="wpu_page_id" value="<?php echo $pageid; ?>" size="3">&nbsp;<?php echo __("ID of the page on which you want to display the user directory.", "user-list")?></td>
</tr>
<tr>
    <td><?php echo __("Users Per Page", "user-list");?>:&nbsp;</td>
    <td colspan="2"><input type="text" name="wpu_users_per" value="<?php echo $usersperpage; ?>" size="3">&nbsp;<?php echo __("How many users you want to display at once.", "user-list")?></td>
</tr>
<tr>
    <td><?php echo __("Pagination Adjacents", "user-list");?>:&nbsp;</td>
    <td colspan="2"><input type="text" name="wpu_pagination_adjacents" value="<?php echo $adjacents; ?>" size="3">&nbsp;<?php echo __("Pagination adjacents size", "user-list")?></td>
</tr>
<tr>
    <td><?php echo __("Noindex User Listings", "user-list");?>:&nbsp;</td>
    <td colspan="2"><input name="wpu_noindex_users" type="checkbox" value="yes" <?php checked('yes', $noindex_users); ?> />&nbsp;<?php echo __("Insert robots noindex meta tag on user listings to prevent search engine indexing.", "user-list")?></td>
</tr>
<tr>
    <td colspan="3">&nbsp;</td>
</tr>
<tr>
    <td colspan="3"><h3><?php echo __("Avatar Options", "user-list");?></h3></td>
</tr>
<tr>
    <td><?php echo __("Avatar Type", "user-list"); ?></td>
    <td colspan="2"><input id="wpu_avatars_gravatars" type="radio" name="wpu_avatars" value="gravatars" <?php checked('gravatars', get_option('wpu_avatars')); ?> /> <?php echo __("Gravatars", "user-list"); ?></td>
</tr>
<tr>
    <td>&nbsp;</td>
    <td colspan="2"><input id="wpu_avatars_userphoto" type="radio" name="wpu_avatars" value="userphoto" <?php checked('userphoto', get_option('wpu_avatars')); ?> /> <?php echo __("User Photo", "user-list");?></td>
</tr>
<tr>
    <td colspan="3">&nbsp;</td>
</tr>
<tr>
    <td colspan="3"><h3><?php echo __("Gravatar Options", "user-list");?>:</h3></td>
</tr>
<tr>
    <td><?php echo __("Gravatar Type", "user-list"); ?>:&nbsp;</td>
    <td><input type="text" name="wpu_gravatar_type" value="<?php echo $gravatar_type; ?>" size="15">&nbsp; <?php echo __("Gravatar type - ex. mystery, blank, gravatar_default, identicon, wavatar, monsterid", "user-list");?></td>
</tr>
<tr>
    <td><?php echo __("Gravatar Size", "user-list"); ?>:&nbsp;</td>
    <td><input type="text" name="wpu_gravatar_size" value="<?php echo $gravatar_size; ?>" size="2"> <?php echo __("px Size of gravatar in the user listings.", "user-list");?></td>
</tr>
</table>

<br />

<table>
<tr>
    <td>
        <h3><?php echo __("Select Which Users to Display", "user-list")?></h3>
        <p><small><strong><?php echo __("Note", "user-list");?>:</strong><?php echo __("If no options are selected, all users will be displayed.", "user-list")?></small></p>
    </td>
</tr>
<tr>
    <td><input name="wpu_roles_admin" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_roles_admin')); ?> />&nbsp; <?php echo __("Administrator", "user-list"); ?></td>
</tr>
<tr>
    <td><input name="wpu_roles_editor" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_roles_editor')); ?> />&nbsp; <?php echo __("Editors", "user-list"); ?></td>
</tr>
<tr>
    <td><input name="wpu_roles_author" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_roles_author')); ?> />&nbsp; <?php echo __("Authors", "user-list"); ?></td>
</tr>
<tr>
    <td><input name="wpu_roles_contributor" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_roles_contributor')); ?> />&nbsp; <?php echo __("Contribtors", "user-list"); ?></td>
</tr>
<tr>
    <td><input name="wpu_roles_subscriber" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_roles_subscriber')); ?> />&nbsp; <?php echo __("Subscribers", "user-list"); ?></td>
</tr>
<tr>
    <td>&nbsp;</td>
</tr>
<tr>
    <td><h3><?php echo __("Profile Options", "user-list")?></h3></td>
</tr>
<tr>
    <td><input name="wpu_image_list" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_image_list')); ?> />&nbsp; <?php echo __("Display user images on directory page.", "user-list"); ?></td>
</tr>
<tr>
    <td><input name="wpu_description_list" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_description_list')); ?> />&nbsp; <?php echo __("Display user descriptions on directory page.", "user-list"); ?></td>
</tr>
<tr>
    <td>&nbsp;</td>
</tr>
<tr>
    <td><input name="wpu_image_profile" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_image_profile')); ?> />&nbsp; <?php echo __("Display user images on profile page.", "user-list"); ?></td>
</tr>
<tr>
    <td><input name="wpu_description_profile" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_description_profile')); ?> />&nbsp; <?php echo __("Display user descriptions on profile page.", "user-list"); ?></td>
</tr>
<tr>
    <td><input name="wpu_user_files" type="checkbox" value="yes" <?php checked('yes', get_option('wpu_user_files')); ?> />&nbsp; <?php echo __("Display user files on profile page.", "user-list"); ?></td>
</tr>
<tr>
    <td>&nbsp;</td>
</tr>
<tr>
    <td><input type="text" name="wpu_description_limit" value="<?php echo $desc_limit; ?>" size="3">&nbsp; <?php echo __("Number of characters to display of user description on the directory page.", "user-list"); ?></td>
</tr>
<tr>
    <td>
        <p><small><strong><?php echo __("Note", "user-list");?>:</strong> <?php echo __("If no limit is specified, entire user description will be displayed.", "user-list"); ?></small></p>
    </td>
</tr>
</table>

<p class="submit"><input type="submit" name="Submit" value="<?php echo __("Update Options", "user-list"); ?>" /></p>
</div>

</form>
<?php }



function wpu_concat(){
    return strpos(get_permalink(get_the_id()), '?') ? '&' : '?';
}

function getPaginationString($totalitems, $itemsPerPage, $onPage, $adjacents){
    //if there are no items, nothing to do...
    if(!$totalitems) return;
    
    //calculate the total pages, if there is only one page, nothing to do..
    $totalPages = ceil($totalitems / $itemsPerPage);
    if($totalPages == 1) return;
    
    //defaults
    if(!$itemsPerPage) $itemsPerPage = 15;
    if(!$onPage) $onPage = 1;
    if(!$adjacents) $adjacents = 5;


    $link = get_permalink() . wpu_concat() . "page=";
    $html = "<div class=\"wpu-pagination\">";
    
    //prev button 
    if($onPage > 1){
        $html .= "<a href=\"". $link . ($onPage - 1) ."\">&laquo; ".__("prev", "user-list") ."</a>";
    }else{
        $html .= "<span class=\"wpu-disabled\">&laquo; ".__("prev", "user-list")."</span>";
    }

    
    //pagination
    $startAt = max(1, min($totalPages - $adjacents, $onPage - ceil($adjacents / 2)));
    $endAt = $onPage + ceil($adjacents / 2);
    
    if($endAt > $totalPages){ $endAt = $totalPages; }
    
    //add the first page and dots after ...
    if($startAt != 1){
        $html .= "<a href=\"". $link. "1\" \>1</a>...";
    }
    
    //add all the remaining pages, pagination
    for($i = $startAt; $i <= $endAt; $i++){
        //if we are on the current page, add the css class
        $currentCssClass = $i == $onPage ? " class=\"wpu-current\"" : "";
        $html .= "<a href=\"". $link. $i ."\" $currentCssClass \>".$i."</a>";
    }
    
    //add the last page and dots before ...
    if($endAt != $totalPages){
        $html .= "...<a href=\"". $link. $totalPages ."\" \>".$totalPages."</a>";
    }
    
    //next button
    if($onPage < $totalPages){
        $html .= "<a href=\"" . $link . ($onPage + 1) ."\">".__("next", "user-list")." &raquo;</a>";
    }else{
        $html .= "<span class=\"wpu-disabled\">".__("next", "user-list")." &raquo;</span>";
    }    
    
    $html .= "</div>";
    return $html;
}
?>