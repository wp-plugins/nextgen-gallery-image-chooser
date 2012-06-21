<?php
/*
Plugin Name: NextGEN Gallery Image Chooser
Plugin URI: 
Description: Comfortable image chooser for the NextGEN Gallery. Based on g2image.
Version: 0.1.0
Author: Ulrich Mertin
Author URI: http://www.ulrich-mertin.de
*/

/*
    This plugin is based on g2image by Kirk Steffensen,
    see http://g2image.steffensenfamily.com/ for further contributors.

    Released under the GPL version 2.
    A copy of the license is in the root folder of this plugin.
*/

global $wpdb, $ngg, $wp_version;

// ====( Version Info )
$nggic_version_text = '0.1.0';

// Is this a TinyMCE window?
if(!isset($_REQUEST['nggic_tinymce']) && !isset($_SESSION['nggic_tinymce'])) {
  //Activate & Deactivate Plugin Functions
  register_activation_hook( __FILE__, 'nggic_pluginactivate' );
  // NextGEN Gallery Image Filters - Visual Editor
  add_action('init', 'nggic_addbuttons');
  return;
}

// ====( Initialization Code )
require_once('../../../wp-load.php');
session_start();

require_once('init.php');
nggic_setup_gettext();

// ====( NextGEN Gallery validation )

if (!function_exists('nggShowGallery')) {
  die(T_('Required NextGEN Gallery was not found!'));
}

nggic_get_request_and_session_options();
list($nggic_album_info, $nggic_gallery_items) = nggic_get_gallery_items();
$nggic_imginsert_options = nggic_get_imginsert_selectoptions();
$tree = nggic_get_album_tree();

// ====( Main HTML Generation Code )

echo nggic_make_html_header();

echo '        <table>' . "\n";
echo '            <tr>' . "\n";
echo '                <td width="200px" valign="top">' . "\n";

echo nggic_make_html_album_tree($tree);

echo '                </td>' . "\n";
echo '                <td valign="top">' . "\n";

echo '                    <div class="main">' . "\n";

echo nggic_make_html_ngg_album_insert_button();

if (empty($nggic_gallery_items)) {
	echo nggic_make_html_empty_page();
}
else {
	$nggic_page_navigation = nggic_make_html_page_navigation();
	echo nggic_make_html_display_options();
	echo nggic_make_html_controls();
	print_r($nggic_page_navigation);
	echo nggic_make_html_image_navigation();
	print_r($nggic_page_navigation);
}

echo nggic_make_html_about($nggic_version_text);

echo '                    </div>' . "\n";
echo '                </td>' . "\n";
echo '            </tr>' . "\n";
echo '        </table>' . "\n";
echo '    </form>' . "\n";
echo '</body>' . "\n\n";
echo '</html>';

$_SESSION['nggic_last_album_visited'] = $nggic_options['current_gallery'];

// ====( Functions - Alphabetical by Function Name)

/**
* Adds nggic to the TinyMCE button Toolbar 
*
* @param null
* @return null
*/
function nggic_addbuttons() {
   // Don't bother doing this stuff if the current user lacks permissions
   if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') )
     return;
 
   // Add only in Rich Editor mode
   if ( get_user_option('rich_editing') == 'true') {
     add_filter("mce_external_plugins", "nggic_plugin");
     add_filter('mce_buttons', 'nggic_wp_extended_editor_mce_buttons');
   }
}

/**
 * Create tree of albums and galleries
 *
 * @return array $tree The array of albums and galleries
 */
function nggic_get_album_tree() {
  global $wpdb, $ngg, $wp_version, $nggdb;
  global $albums, $galleries;

  $galleries = array();
  $albums = array();
  $rootalbums = array();
  
  // Get all galleries
  $orphan_galleries = $nggdb->find_all_galleries('gid');
  
  // Get all albums
  $dbalbums = $nggdb->find_all_album('id');
  
  // Set up key-less arrays and get galleries for albums
  // Remove all attached galleries from $orphan_galleries
  foreach ($dbalbums as $key => $album) {
    $albums[$album->id] = $album;
    $rootalbums[$album->id] = $album;
  	foreach ($album->galleries as $key => $value) {
      if (!nggic_starts_with($value, 'a')) {
        // gallery
        if (!key_exists($value, $galleries)) {
          $galleries[$value] = $orphan_galleries[$value];
          unset($orphan_galleries[$value]);
        }
      }
    }
  }  

  // Find root albums by removing all albums that are subalbums of other
  // albums from the list
  // Find orphan galleries by removing all galleries that are attached to
  // albums
  foreach($albums as $id => $album) {
  	foreach ($album->galleries as $key => $value) {
      if (nggic_starts_with($value, 'a')) {
        // album
        $album_id = substr($value, 1);
        unset($rootalbums[$album_id]);         
      }
    }
  }
  
  // Build tree
  $tree = array();
  foreach($rootalbums as $id => $rootalbum) {
    $rootalbum->type = 'album';
    $children = nggic_get_children($rootalbum);
    $rootalbum->children = $children;
    $tree[] = $rootalbum;
  }

  foreach($orphan_galleries as $gid => $orphan_gallery) {
    $orphan_gallery->type = 'gallery';
    $tree[] = $orphan_gallery;
  }  

  return $tree;
}

/**
 * Get child objects (albums and galleries) of an album
 *
 * @return array $children The recursive array of child albums and galleries
 */
function nggic_get_children($parent) {
  global $albums, $galleries;

  $children = array();
	foreach ($parent->galleries as $key => $value) {
      if (nggic_starts_with($value, 'a')) {
        // album, recurse
        $album_id = substr($value, 1);
        $album = $albums[$album_id];
        $album->type = 'album';
        $subchildren = nggic_get_children($album);
        $album->children = $subchildren;
        $children[] = $album;         
      }
      else {
        $gallery = $galleries[$value];
        $gallery->type = 'gallery';
        $children[] = $gallery;
      }
  }  
  return $children;
}

/**
 * Get info about an item from NGG
 *
 * @param int $item_id The NGG ID of the item
 * @return array $item_info The array of information about the item
 */
function nggic_get_gallery_info($item_id) {
	global $nggdb;

	if (nggic_starts_with($item_id, 'a')) {
		$gallery= $nggdb->find_album(substr($item_id, 1));
		$item_info['title'] = $gallery->name;
		
		$item_info['thumbnail_src'] = plugins_url( 'images/ngg_placeholder_album.jpg' , __FILE__ );
	}
	else {
		$gallery= $nggdb->find_gallery($item_id);
		$item_info['title'] = $gallery->title;

		$thumbnail = $nggdb->find_image($gallery->previewpic);
		$item_info['thumbnail_src'] = $thumbnail->thumbURL;
	}
	$item_info['id'] = $item_id;
	$item_info['description'] = $gallery->galdesc;
	$item_info['summary'] = $gallery->galdesc;
	
	
	if(empty($item_info['summary']))
		$item_info['summary'] = $item_info['title'];

	return $item_info;
}

/**
 * Get all of the Gallery2 items
 *
 * @return array $album_info Album Title and URL for the current album
 * @return array $gallery_items Sorted array of IDs and Titles for all Gallery2 Data Items in the current album
 */
function nggic_get_gallery_items() {
	GLOBAL $nggdb, $nggic_options;
  global $nggRewrite;
  
	$gallery_items = array();
	$item_mod_times = array();
	$item_orig_times = array();
	$item_create_times = array();
	$item_titles = array();
	$item_ids = array();
	$album_info = array();

	$gallery = $nggdb->find_gallery($nggic_options['current_gallery']);
  
	$album_info['title'] = $gallery->name;

	$items = $nggdb->get_gallery($nggic_options['current_gallery']);

	foreach ($items as $item) {
		$item_ids[] = $item->pid;
		$item_titles[] = $item->alttext;
		$item_mod_times[] = $item->imagedate;
		$item_orig_times[] = $item->imagedate;
		$item_create_times[] = $item->imagedate;
	}

	// Sort directories and files
	$count_files = count($item_ids);

	if($count_files>0){
		switch ($nggic_options['sortby']) {
			case 'gallery_order' :
				break;
			case 'title_asc' :
				array_multisort($item_titles, $item_ids);
				break;
			case 'title_desc' :
				array_multisort($item_titles, SORT_DESC, $item_ids);
				break;
			case 'orig_time_asc' :
				array_multisort($item_orig_times, $item_titles, $item_ids);
				break;
			case 'orig_time_desc' :
				array_multisort($item_orig_times, SORT_DESC, $item_titles, $item_ids);
		}
		for($i=0; $i<$count_files; $i++) {
			$gallery_items[$i] = array('title'=>$item_titles[$i],'id'=>$item_ids[$i]);
		}
	}

	return array($album_info, $gallery_items);
}

/**
 * Get info about an item from Gallery2
 *
 * @param int $item_id The Gallery2 ID of the item
 * @return array $item_info The array of information about the item
 */
function nggic_get_image_info($item_id) {
	global $nggdb;

	$image= $nggdb->find_image($item_id);
	
	$item_info['id'] = $item_id;
	$item_info['title'] = $image->alttext;
	$item_info['description'] = $image->description;
	$item_info['summary'] = $image->description;

	$item_info['image_url'] = $image->imageURL;
	$item_info['fullsize_img'] = $image->imageURL;
	$item_info['thumbnail_src'] = $image->thumbURL;
	
	$thumb_meta = $image->meta_data['thumbnail'];
	$item_info['thumbnail_width'] = $thumb_meta['width'];
	$item_info['thumbnail_height'] = $thumb_meta['height'];

	if(empty($item_info['summary']))
		$item_info['summary'] = $item_info['title'];

	return $item_info;
}

/**
 * Make the array of selection options for the "How to Insert?" select element
 *
 * @return array $imginsert_selectoptions The array of selection options for the "How to Insert?" select element
 */
function nggic_get_imginsert_selectoptions(){
	GLOBAL $nggic_options;

	$message = array();
	$imginsert_selectoptions = array();

	// These are the universal image insertion options
	$message['thumbnail_image'] = T_('Thumbnail with link to image');
	$message['thumbnail_custom_url'] = T_('Thumbnail with link to custom URL (from text box below)');
	$message['thumbnail_only'] = T_('Thumbnail only - no link');
	$message['fullsize_only'] = T_('Fullsized image only - no link');
	$message['link_image'] = T_('Text link to image');

	foreach($message as $key => $text) {
		$message[$key] = $text . ' (' . T_('HTML') . ')';
	}

	// These are CMS-specific image insertion options
	$message['ngg_singlepic'] = T_('NGG tag of image');
	$message['ngg_thumb'] = T_('NGG tag of thumbnail');
	$message['ngg_thumb_multi'] = T_('NGG tag of multiple thumbnails');

	// Make the universal message array
	$imginsert_selectoptions = array(
		'thumbnail_image' => array(
			'text'  => $message['thumbnail_image'] ),
		'thumbnail_custom_url' => array(
			'text'  => $message['thumbnail_custom_url'] ),
		'thumbnail_only' => array(
			'text'  => $message['thumbnail_only'] ),
		'fullsize_only' => array(
			'text'  => $message['fullsize_only'] ),
		'link_image' => array(
			'text'  => $message['link_image'] ),
	);

	$imginsert_selectoptions = array(
		'ngg_singlepic' => array(
			'text'  => $message['ngg_singlepic'] ),
		'ngg_thumb' => array(
			'text'  => $message['ngg_thumb'] ),
		'ngg_thumb_multi' => array(
			'text'  => $message['ngg_thumb_multi'] ),
	) + $imginsert_selectoptions;

	$imginsert_selectoptions[$nggic_options['default_action']]['selected'] = TRUE;
	return $imginsert_selectoptions;
}

/**
 * Get all of the options set in $_REQUEST and/or $_SESSION
 */
function nggic_get_request_and_session_options(){

	global $nggic_options;

  //TODO: Get true root album id
  $nggic_options['root_album'] = -1;
  
	nggic_magic_quotes_remove($_REQUEST);

	// Is this a TinyMCE window?
	if(isset($_REQUEST['nggic_tinymce'])){
		$nggic_options['tinymce'] = $_REQUEST['nggic_tinymce'];
		$_SESSION['nggic_tinymce'] = $_REQUEST['nggic_tinymce'];
	}
	else if (isset($_SESSION['nggic_tinymce']))
		$nggic_options['tinymce'] = $_SESSION['nggic_tinymce'];
	else $nggic_options['tinymce'] = 0;

	// Get the form name (if set) for insertion (not TinyMCE or FCKEditor)
	if(isset($_REQUEST['nggic_form'])){
		$nggic_options['form'] = $_REQUEST['nggic_form'];
		$_SESSION['nggic_form'] = $_REQUEST['nggic_form'];
	}
	else if (isset($_SESSION['nggic_form']))
		$nggic_options['form'] = $_SESSION['nggic_form'];
	else $nggic_options['form'] = '';

	// Get the field name (if set) for insertion (not TinyMCE or FCKEditor)
	if(isset($_REQUEST['nggic_field'])){
		$nggic_options['field'] = $_REQUEST['nggic_field'];
		$_SESSION['nggic_field'] = $_REQUEST['nggic_field'];
	}
	else if (isset($_SESSION['nggic_field']))
		$nggic_options['field'] = $_SESSION['nggic_field'];
	else $nggic_options['field'] = '';

	// Get the last album visited
	if(isset($_SESSION['nggic_last_album_visited'])) {
		$nggic_options['last_album'] = $_SESSION['nggic_last_album_visited'];
	}
	else {
		$nggic_options['last_album'] = $nggic_options['root_album'];
	}

	// Get the current album
	if(IsSet($_REQUEST['current_gallery'])){
		$nggic_options['current_gallery'] = $_REQUEST['current_gallery'];
	}
	else {
		$nggic_options['current_gallery'] = $nggic_options['last_album'];
	}

	// Get the current page
	if (isset($_REQUEST['nggic_page']) and is_numeric($_REQUEST['nggic_page'])) {
		$nggic_options['current_page'] = floor($_REQUEST['nggic_page']);
	}
	else {
		$nggic_options['current_page'] = 1;
	}

	// Get the current sort method
	if(IsSet($_REQUEST['sortby']))
		$nggic_options['sortby'] = $_REQUEST['sortby'];

	// Determine whether to display the titles or keep them hidden
	if(IsSet($_REQUEST['display']))
		if ($_REQUEST['display'] == 'filenames')
			$nggic_options['display_filenames'] = TRUE;

	// Determine how many images to display per page
	if(IsSet($_REQUEST['images_per_page']))
		$nggic_options['images_per_page'] = $_REQUEST['images_per_page'];

	return;
}

/**
 * Remove "Magic Quotes"
 *
 * @param array &$array POST or GET with magic quotes
 */
function nggic_magic_quotes_remove(&$array) {
	if(!get_magic_quotes_gpc())
		return;
	foreach($array as $key => $elem) {
		if(is_array($elem))
			nggic_magic_quotes_remove($elem);
		else
			$array[$key] = stripslashes($elem);
	}
}

/**
 * Creates the "About" alert HTML
 *
 * @return string $html The "About" alert HTML
 */
function nggic_make_html_about($version){
	global $nggic_options, $nggic_album_info;

	$html = '<div class="about_button">' . "\n"
	. '    <input type="button" onclick="alert(\'' . T_('NextGEN Gallery Image Chooser') . '\n' . T_('Version') . ' ' . $version
	. '\')" '
	. 'value="' . T_('About NextGEN Gallery Image Chooser') . '"/>' . "\n"
	. '    <input type="hidden" name="current_gallery" value="' . $nggic_options['current_gallery'] . '">' . "\n"
	. '    <input type="hidden" name="album_name" value="' . $nggic_album_info['title'] . '" />' . "\n"
	. '    <input type="hidden" name="album_url" value="' . $nggic_album_info['url'] . '" />' . "\n"
	. '    <input type="hidden" name="nggic_page" value="' . $nggic_options['current_page'] . '" />' . "\n"
	. '    <input type="hidden" name="nggic_form" value="' . $nggic_options['form'] . '" />' . "\n"
	. '    <input type="hidden" name="nggic_field" value="' . $nggic_options['field'] . '" />' . "\n"
	. '</div>' . "\n";

	return $html;
}

/**
 * Creates the album tree HTML
 *
 * @return string $html The album tree HTML
 */
function nggic_make_html_album_tree($tree){

	// Album navigation

	$html = '<div class="dtree">' . "\n"
	. '    <p><a href="javascript: d.openAll();">' . T_('Expand all') . '</a> | <a href="javascript: d.closeAll();">' . T_('Collapse all') . '</a></p>' . "\n"
	. '    <script type="text/javascript">' . "\n"
	. '        <!--' . "\n"
	. '        d = new dTree("d");' . "\n";
	$parent = -1;
	$node = 0;
	$html .= '        d.add(' . $node . ',' . $parent . ',"' . T_('Gallery') . '");' . "\n";
	$node++;
	$parent++;
	
  foreach($tree as $key => $root_album) {
  	$html .= nggic_make_html_album_tree_branches($root_album, $parent, $node);
    $node++;
  }
	$html .= '        document.write(d);' . "\n"
	. '        //-->' . "\n"
	. '    </script>' . "\n"
	. '</div>' . "\n\n";

	return $html;
}

/**
 * Generates album hierarchy as d.add entites of dtree
 *
 * @param int $current_node id of current node (album or gallery)
 * @param int $parent node of the parent album
 */
function nggic_make_html_album_tree_branches($current_node, $parent, &$node) {
	global $nggic_options;
	
	if(strcmp($current_node->type, 'album') == 0) {
  	$album_title = $current_node->name;
  	$album_title = preg_replace("/(\n|\r)/"," ",$album_title);
  	if(empty($album_title)) {
  		$album_title = $current_node->slug;
  	}

		$html = '        d.add(' . $node . ',' . $parent . ',"' . $album_title . '","'
		. '?nggic_tinymce=1&current_gallery=a' . $current_node->id . '");' . "\n";
		$sub_albums = $current_node->children;
	        if (is_array($sub_albums)) {
	  	$albums = array_values($sub_albums);
	  
	  	if (count($albums) > 0) {
	  		$parent = $node;
	  		foreach ($albums as $album) {
	  			$node++;
	  			$html .= nggic_make_html_album_tree_branches($album, $parent, $node);
	  		}
	  	}
	  }
	}
	else {
  	$album_title = $current_node->title;
  	$album_title = preg_replace("/(\n|\r)/"," ",$album_title);
  	if(empty($album_title)) {
  		$album_title = $current_node->slug;
  	}

		$html = '        d.add(' . $node . ',' . $parent . ',"' . $album_title . '","'
		. '?nggic_tinymce=1&current_gallery=' . $current_node->gid . '&sortby=' . $nggic_options['sortby']
		. '&images_per_page=' . $nggic_options['images_per_page'] . '");' . "\n";
	}
	return $html;
}

/**
 * Creates the alignment selection HTML
 *
 * @return string $html The alignment selection HTML
 */
function nggic_make_html_alignment_select(){
	GLOBAL $nggic_options;

	// array for output
	$align_options = array('none' => array('text' => T_('None')),
		'left' => array('text' => T_('Float Left')),
		'right' => array('text' => T_('Float Right')));

	if ($nggic_options['custom_class_1'] != 'not_used'){
		$align_options = array_merge($align_options, array($nggic_options['custom_class_1'] => array('text' => $nggic_options['custom_class_1'])));
	}

	if ($nggic_options['custom_class_2'] != 'not_used'){
		$align_options = array_merge($align_options, array($nggic_options['custom_class_2'] => array('text' => $nggic_options['custom_class_2'])));
	}

	if ($nggic_options['custom_class_3'] != 'not_used'){
		$align_options = array_merge($align_options, array($nggic_options['custom_class_3'] => array('text' => $nggic_options['custom_class_3'])));
	}

	if ($nggic_options['custom_class_4'] != 'not_used'){
		$align_options = array_merge($align_options, array($nggic_options['custom_class_4'] => array('text' => $nggic_options['custom_class_4'])));
	}

	$align_options[$nggic_options['default_alignment']]['selected'] = TRUE;

	$html = nggic_make_html_select('alignment',$align_options);

	return $html;
}

/**
 * Create the HTML for the image controls
 *
 * @return string $html The HTML for the image controls
 */
function nggic_make_html_controls(){
	global $nggdb, $nggic_imginsert_options, $nggic_options;

	// "How to insert:" radio buttons
	$html = "        <fieldset>\n"
	. '            <legend>' . T_('Insertion Options') . '</legend>' . "\n"
	. '            <label for="alignment">' . T_('How to Insert Image') . '</label>' . "\n"
	. nggic_make_html_select('imginsert', $nggic_imginsert_options, 'toggleTextboxes();')
	. '            <br />' . "\n"
	. '            <br />' . "\n"

	// "Custom URL" textbox
	. '            <div name="custom_url_textbox"';
	if ($nggic_options['default_action'] == 'thumbnail_custom_url') {
		$html .= ' class="displayed_textbox"';
	}
	else {
		$html .= 'class="hidden_textbox"';
	}
	$html .= '>' . "\n"
	. '            <label for="custom_url">' . T_('Custom URL') . '<br /></label>' . "\n"
	. '            <input type="text" name="custom_url" size="84" maxlength="150" value="' . $nggic_options['custom_url'] . '" />' . "\n"
	. '            <br />' . "\n"
	. '            <br />' . "\n"
	. '            </div>' . "\n"

	// "Link Text" textbox
	. '            <div name="link_text_textbox"';
	if ($nggic_options['default_action'] == 'link_image'){
		$html .= ' class="displayed_textbox"';
	}
	else {
		$html .= 'class="hidden_textbox"';
	}
	$html .= '>' . "\n"
	. '            <label for="link_text">' . T_('Text for text link') . '<br /></label>' . "\n"
	. '            <input type="text" name="link_text" size="84" maxlength="150" value="" />' . "\n"
	. '            <br />' . "\n"
	. '            <br />' . "\n"
	. '            </div>' . "\n";

	// singlepic tag "h and w" boxes

	$html .= '            <div name="ngg_tag_size_textbox"';
	if ($nggic_options['default_action'] == 'ngg_singlepic'){
		$html .= ' class="displayed_textbox"';
	}
	else {
		$html .= 'class="hidden_textbox"';
	}
	$html .= '>' . "\n"
	. '            <label for="ngg_tag_width">' . T_('Image width x  height (Leave blank for the original size)') . '</label>' . "\n"
	. '            <input type="text" name="ngg_tag_width" size="10" maxlength="5" value="" />' . " x \n"
	. '            <input type="text" name="ngg_tag_height" size="10" maxlength="5" value="" />' . "\n"
	. '            <br />' . "\n"
	. '            <br />' . "\n"
	. '            </div>' . "\n";

	// Link and alignment selection
	$html .= '            <div name="ngg_tag_align_select"';
	if ($nggic_options['default_action'] == 'ngg_singlepic'){
		$html .= ' class="displayed_textbox"';
	}
	else {
		$html .= 'class="hidden_textbox"';
	}
	$html .= '>' . "\n"
	. '            <label for="ngg_tag_link">' . T_('Link (Leave blank for none)') . '<br /></label>' . "\n"
	. '            <input type="text" name="ngg_tag_link" size="84" maxlength="150" value="" />' . "\n"
	. '            <br />' . "\n"
	. '            <br />' . "\n"
	. '            <label for="alignment">' . T_('NGG Float Class') . '</label>' . "\n"
	. nggic_make_html_alignment_select()
	. '            <br />' . "\n"
	. '            <br />' . "\n"
	. '            </div>' . "\n";

	// Mode selection
	$nggic_mode_options = array(
		'none' => array(
			'text'  => T_('Default') ),
		'web20' => array(
			'text'  => 'Web 2.0' ),
		'watermark' => array(
			'text'  => T_('Watermark') ),
	);
	$html .= '            <div name="ngg_tag_mode_select"';
	if ($nggic_options['default_action'] == 'ngg_singlepic'){
		$html .= ' class="displayed_textbox"';
	}
	else {
		$html .= 'class="hidden_textbox"';
	}
	$html .= '>' . "\n"
	. '            <label for="alignment">' . T_('Mode') . '</label>' . "\n"
	. nggic_make_html_select('ngg_tag_mode_select', $nggic_mode_options)
	. '            <br />' . "\n"
	. '            <br />' . "\n"
	. '            </div>' . "\n";

	// template box

	$html .= '            <div name="ngg_tag_template_textbox"';
	if ($nggic_options['default_action'] == 'ngg_singlepic'
		|| $nggic_options['default_action'] == 'ngg_thumb'
		|| $nggic_options['default_action'] == 'ngg_thumb_multi'
	){
		$html .= ' class="displayed_textbox"';
	}
	else {
		$html .= 'class="hidden_textbox"';
	}
	$html .= '>' . "\n"
	. '            <label for="ngg_tag_template">' . T_('Template name (Leave blank for the default template)') . '<br /></label>' . "\n"
	. '            <input type="text" name="ngg_tag_template" size="84" maxlength="150" value="" />' . "\n"
	. '            <br />' . "\n"
	. '            <br />' . "\n"
	. '            </div>' . "\n";

	$html .= "        </fieldset>\n\n";

	// "Insert" button
	$html .=  "        <fieldset>\n"
	. '            <legend>' . T_('Press button to insert checked image(s)') . '</legend>' . "\n"
	. "            <input disabled type='button'\n"
	. "            name='insert_button'\n"
	. '            onclick="insertItems();"' . "\n"
	. '            value="' . T_('Insert') . '"' . "\n"
	. '            />' . "\n"
	. '            <a href="javascript: checkAllImages();">' . T_('Check all') . '</a> | <a href="javascript: uncheckAllImages();">' . T_('Uncheck all') . '</a>' . "\n"
	. "        </fieldset>\n\n";

	return $html;
}

/**
 * Creates the HTML for the "Display Options" box
 *
 * @return string $html The HTML for the "Display Options" box
 */
function nggic_make_html_display_options(){
	global $nggic_options;

	$images_per_page_options = array(10,20,30,40,50,60,9999);

	if (!in_array($nggic_options['images_per_page'],$images_per_page_options)){
		array_push($images_per_page_options,$nggic_options['images_per_page']);
		sort($images_per_page_options);
	}

	// array for output
	$sortoptions = array('gallery_order' => array('text' => T_('NextGEN Gallery order')),
		'title_asc' => array('text' => T_('NextGEN Gallery Title (A-z)')),
		'title_desc' => array('text' => T_('NextGEN Gallery Title (z-A)')),
		'orig_time_desc' => array('text' => T_('Origination Time (Newest First)')),
		'orig_time_asc' => array('text' => T_('Origination Time (Oldest First)')));

	$sortoptions[$nggic_options['sortby']]['selected'] = TRUE;

	$html = "<div>\n"
	. "    <fieldset>\n"
	. '        <legend>' . T_('Display Options') . '</legend>' . "\n"
	. '            ' . T_('Sorted by:') . "\n"
	. nggic_make_html_select('sortby',$sortoptions,'document.forms[0].submit();')
	. '            ' . T_('Per Page:') . "\n"
	. '            <select name="images_per_page" onchange="document.forms[0].submit();">' . "\n";

	for($i=0;$i<count($images_per_page_options);$i++){
		$html .= '                <option value="' . $images_per_page_options[$i] . '"';
		if ($images_per_page_options[$i] == $nggic_options['images_per_page'])
			$html .= " selected='selected'";
		$html .= '>';
		if ($images_per_page_options[$i] == 9999)
			$html .= T_('All');
		else
			$html .= $images_per_page_options[$i];
		$html .= "</option>\n";
	}

	$html .=	"            </select>\n"
	. '            <br />' . "\n";

	$html .= '            <input type="radio" name="display" value="thumbnails"';
	if (!$nggic_options['display_filenames'])
		$html .= ' checked="checked"' . "\n";
	else
		$html .= "\n";
	$html .= "            onclick='showThumbnails()'"
	.  '>' . T_('Thumbnails') . '</input>' . "\n";

	$html .= '            <input type="radio" name="display" value="filenames"';
	if ($nggic_options['display_filenames'])
		$html .= ' checked="checked"' . "\n";
	else
		$html .= "\n";
	$html .= "            onclick='showFileNames()'"
	.  '>' . T_('Thumbnails with info') . '</input>' . "\n";

	$html .= "    </fieldset>\n"
	. "</div>\n\n";

	return $html;
}

/**
 * Make the HTML for the "No photos in this album" message
 *
 * @return string $html The HTML for the "No photos in this album" message
 */
function nggic_make_html_empty_page() {

	$html = '<p><strong>' . T_('There are no photos in this album.<br /><br />Please pick another album from the navigation options above.') . '</strong></p>' . "\n\n";

	return $html;
}
/**
 * Make the header
 *
 * @return string $html The HTML for the header
 */
function nggic_make_html_header(){
	global $nggic_options;
	$html = '<html xmlns="http://www.w3.org/1999/xhtml">' . "\n"
	. '<head>' . "\n"
	. '    <title>' . T_('NextGEN Gallery Image Chooser') . '</title>' . "\n"
	. '    <link rel="stylesheet" href="css/nggic.css" type="text/css" />' . "\n"
	. '    <link rel="stylesheet" href="css/dtree.css" type="text/css" />' . "\n"
	. '    <link rel="stylesheet" href="css/slimbox.css" type="text/css" />' . "\n"
	. '    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />' . "\n";
	$html .= "    <script language='javascript' type='text/javascript' src='../../../wp-includes/js/tinymce/tiny_mce_popup.js'></script>\n";
	$html .= '    <script language="javascript" type="text/javascript" src="jscripts/functions.js"></script>' . "\n"
	. '    <script language="javascript" type="text/javascript" src="jscripts/dtree.js"></script>' . "\n"
	. '    <script language="javascript" type="text/javascript" src="jscripts/mootools.js"></script>' . "\n"
	. '    <script language="javascript" type="text/javascript" src="jscripts/slimbox.js"></script>' . "\n"
	. '</head>' . "\n\n"
	. '<body id="nggic">' . "\n\n"
	. '    <form method="post">' . "\n";

	return $html;
}

/**
 * Make the HTML for the image block
 *
 * @return string $html The HTML for the image block
 */
function nggic_make_html_image_navigation(){
	global $nggic_gallery_items, $nggic_options;

	reset($nggic_gallery_items);

	$html = '';

	foreach($nggic_gallery_items as $key => $item) {

		$image_id = $item['id'];

		if (!(($nggic_options['current_page']-1)*$nggic_options['images_per_page'] <= $key)) // Haven't gotten there yet
			continue;
		else if (!($key < $nggic_options['current_page']*$nggic_options['images_per_page']))
			break; // Have gone past the range for this page

		if ($nggic_options['display_filenames']){
			$html .=  "<div class='title_imageblock'>\n";
		}
		else {
			$html .=  "<div class='thumbnail_imageblock'>\n";
		}

		$item_info = nggic_get_image_info($image_id);

		$html .= nggic_make_html_img($item_info) . "\n";

		if ($nggic_options['display_filenames'])
			$html .= '    <div class="displayed_title">' . "\n";
		else
			$html .= '    <div class="hidden_title">' . "\n";

		$html .= '        ' . T_('Title:') . ' ' . htmlspecialchars($item_info['title']) . '<br />' . "\n"
		. '        ' . T_('Summary:') . ' ' . htmlspecialchars($item_info['summary']) . '<br />' . "\n"
		. '        ' . T_('Description:') . ' ' . htmlspecialchars($item_info['description']) . '<br />' . "\n";

		$html .=  "    </div>\n\n";

		if ($nggic_options['display_filenames']){
			$html .=  "    <div class='active_placeholder'>\n"
			. "    </div>\n\n";
		}
		else {
			$html .=  "    <div class='inactive_placeholder'>\n"
			. "    </div>\n\n";
		}

		// hidden fields
		$html .= '    <input type="hidden" name="thumbnail_src" value="' . $item_info['thumbnail_src'] . '" />' . "\n"
		. '    <input type="hidden" name="fullsize_img" value="' . $item_info['fullsize_img'] . '" />' . "\n"
		. '    <input type="hidden" name="item_title" value="' . $item_info['title'] . '" />' . "\n"
		. '    <input type="hidden" name="item_summary" value="' . $item_info['summary'] . '" />' . "\n"
		. '    <input type="hidden" name="item_description" value="' . $item_info['description'] . '" />' . "\n"
		. '    <input type="hidden" name="image_url" value="' . $item_info['image_url'] . '" />' . "\n"
		. '    <input type="hidden" name="image_id" value="' . $image_id . '" />' . "\n"
		. '    <input type="hidden" name="thumbw" value="' . $item_info['thumbnail_width'] . '" />' . "\n"
		. '    <input type="hidden" name="thumbh" value="' . $item_info['thumbnail_height'] . '" />' . "\n"
		. '</div>' . "\n";
	}
	return $html;
}

/**
 * Make the HTML for an individual image
 *
 * @param array $item_info Information on the image
 * @return string $html The HTML for an individual image
 */
function nggic_make_html_img($item_info) {
	global $nggic_options;

	$html = '';

	// ---- image code
	$html .= '    <div style="background:#F0F0EE url(' . $item_info['thumbnail_src'] . '); width:'
	. $item_info['thumbnail_width'] . 'px; height:' . $item_info['thumbnail_height'] . 'px; float: left;">' . "\n"
	. '        <input type="checkbox" name="images" onclick="activateInsertButton();"/>' . "\n";

	$html .= '        <a title="' . $item_info['title'] .  '" rel="lightbox[nggimage]" href="'
	. $item_info['fullsize_img'] . '">' . "\n"
	. '        <img src="images/magnifier.gif" border="0"></a>' . "\n"
	. '    </div>' . "\n";

	return $html;

}

/**
 * Creates the HTML for inserting an album or gallery NGG Tag
 *
 * @return string $html The HTML for for inserting an album or gallery NGG Tag
 */
function nggic_make_html_ngg_album_insert_button(){

	GLOBAL $nggic_options, $nggic_gallery_items;
	$html = '';

	$album_info = nggic_get_gallery_info($nggic_options['current_gallery']);

	if ($album_info['id'] != -1) {
		// Create the form
		$html .= "<div>\n"
		. "    <fieldset>\n"
		. '        <legend>' . T_('Insert a NGG tag for the current gallery:') . ' ' . $album_info['title'] . '</legend>' . "\n";
	
		if (empty($nggic_gallery_items)) {
			$html .= '            <label for="ngg_tag_template">' . T_('Template name (Leave blank for the default template)') . '<br /></label>' . "\n"
			. '            <input type="text" name="ngg_tag_template" size="84" maxlength="150" value="" />' . "\n"
			. '            <br />' . "\n"
			. '            <br />' . "\n";
		}

		// Imagebrowser checkbox
		$html .= '            <div name="ngg_tag_imagebrowser_checkbox"';
		if (!nggic_starts_with($album_info['id'], 'a')){
			$html .= ' class="displayed_textbox"';
		}
		else {
			$html .= 'class="hidden_textbox"';
		}
		$html .= '>' . "\n"
		. '            <input type="checkbox" name="ngg_tag_imagebrowser" />' . "\n"
		. '            <label for="ngg_tag_imagebrowser">' . T_('Insert gallery as image browser') . '<br /></label>' . "\n"
		. '            <br />' . "\n"
		. '            </div>' . "\n";

		// "Insert" button
		$html .= '            <input type="button"' . "\n"
		. '            onclick="insertNggTag()"' . "\n"
		. '            value="' . T_('Insert') . '"' . "\n"
		. '            />' . "\n";
	
		if (!empty($nggic_gallery_items)) {
			$html .= '            ' . T_('Set the template name in "Insertion Options" below') . ' ' . "\n";
		}
		$html .= '            <input type="hidden" name="ngg_id" value="' . $nggic_options['current_gallery'] . '" />' . "\n"
		. '            <input type="hidden" name="ngg_summary" value="' . $album_info['summary'] . '" />' . "\n"
		. '            <input type="hidden" name="ngg_thumbnail" value="' . $album_info['thumbnail_src'] . '" />' . "\n"
		. '    </fieldset>' . "\n"
		. '</div>' . "\n\n";
	}
		
	return $html;
}

/**
 * Make the HTML for navigating multiple pages of images
 *
 * @return string $html The HTML for navigating multiple pages of images
 */
function nggic_make_html_page_navigation() {
	global $nggic_gallery_items, $nggic_options;

	// ---- navigation for pages of images
	$pages = ceil(count($nggic_gallery_items)/$nggic_options['images_per_page']);
	if ($nggic_options['current_page'] > $pages)
		$nggic_options['current_page'] = $pages;

	$pagelinks = array();
	for ($count = 1; $count <= $pages; $count++) {
		if ($nggic_options['current_page'] == $count) {
			$pagelinks[] = '        <strong>' . $count . '</strong>';
		}
		else {
			$pagelinks[] = '        <a href="?nggic_tinymce=1&nggic_page=' . $count
			. '&sortby=' . $nggic_options['sortby'] . '&current_album=' . $nggic_options['current_album']
			. '&images_per_page='  . $nggic_options['images_per_page'] . '">' . $count . '</a>';
		}
	}

	if (count($pagelinks) > 1) {
		$html = '<div>' . "\n"
		. '    <fieldset>' . "\n"
		. '        <legend>' . T_('Page Navigation:') . '</legend>' . "\n"
		. '        ' . T_('Page:') . ' '. "\n"
		. implode("     - \n", $pagelinks) . "\n"
		. '    </fieldset>' . "\n"
		. '</div>' . "\n\n";
	}
	else {
		$html = "";
	}

	return $html;
}

/**
 * Creates HTML for a select element
 *
 * The array $options should contain values and descriptions:
 * array(
 * 	'value' => array(
 * 	'text'     => 'Description',
 * 	'selected' => (TRUE|FALSE),
 * 	),
 * 	...
 * )
 *
 * @param string $name The name for the select element
 * @param array $options The array of options attributes for the select element
 * @param string $onchange (optional) The string that will be exectuted when the user changes options
 * @return string $html The HTML for for select element
 */
function nggic_make_html_select($name,$options,$onchange=null) {
	$html = '            <select name="' . $name . '" size="1" ';
	if($onchange) {
		$html .= 'onchange="' . $onchange . '" ';
	}
	$html .= '>' . "\n";
	foreach ($options as $value => $option) {
		$html .= "                <option value='{$value}'";
		if (isset($option['selected']) and $option['selected'])
			$html .= " selected='selected'";
		$html .= ">{$option['text']}</option>\n";
	}
	$html .= "            </select>\n";

	return $html;
}

/**
* Adds nggic to the TinyMCE plugins list
*
* @param string $plugins the buttons string from the WP filter
* @return string the appended plugins string
*/
function nggic_plugin($plugin_array) {
   $plugin_array['nggic'] = plugins_url( 'editor_plugin.js' , __FILE__ );
   return $plugin_array;
}

/**
* Sets up the NextGEN Gallery Image Chooser Plugin defaults, adds any user capabilities.
*
* @param NULL
* @return NULL
*/
function nggic_pluginactivate() {

  // ==============================================================
  // NextGEN Gallery validation
  // ==============================================================
  
  if (!function_exists('nggShowGallery')) {
    die(T_('Required NextGEN Gallery was not found!'));
  }
  
	// Add BTEV Event Message
	if (function_exists('btev_trigger_error')) {
		btev_trigger_error('NextGEN Gallery Image Chooser PLUGIN ACTIVATED', E_USER_NOTICE, __FILE__);
	}

}

/**
* Sets up gettext for the selected language.
*
* @param NULL
* @return NULL
*/
function nggic_setup_gettext() {
	global $nggic_options;

  // Determine gettext locale
  if (file_exists('./langs/' . $nggic_options[language] . '.mo')) {
  	$locale = $nggic_options[language];
  }
  else {
  	$locale = 'en';
  }

  // gettext setup
  require_once('gettext.inc');
  T_setlocale(LC_ALL, $locale);
  
  // Set the text domain as 'default'
  T_bindtextdomain('default', 'langs');
  T_bind_textdomain_codeset('default', 'UTF-8');
  T_textdomain('default');
}

/**
* Helper function to determine if one text starts with another.
*
* @param string $haystack the text to search in
* @param string $needle the text to search for
* @return true, if $haystack starts with $needle, else false
*/
function nggic_starts_with($haystack, $needle)
{
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}
/**
* Adds nggic to the TinyMCE button bar
*
* @param string $buttons the buttons string from the WP filter
* @return string the appended buttons string
*/
function nggic_wp_extended_editor_mce_buttons($buttons) {
	array_push($buttons, 'separator', 'nggic');
	return $buttons;
}

?>
