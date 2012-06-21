<?php
/*
    NextGEN Gallery Image Chooser
    Version 0.1.0

    Author: Ulrich Mertin
    
    This plugin is based on g2image by Kirk Steffensen,
    see http://g2image.steffensenfamily.com/ for further contributors.

    Released under the GPL version 2.
    A copy of the license is in the root folder of this plugin.
*/

nggic_setdefaults();

/**
* Sets the default options in the global variable $nggic_options.
*
* @param NULL
* @return NULL
*/
function nggic_setdefaults() {
	global $nggic_options;

	$nggic_options['ngg_tagimgsize'] = '150';
	$nggic_options['ngg_tagblockshow'] = array(1);
	$nggic_options['ngg_tagalbumframe'] = 'None';
	$nggic_options['ngg_tagimageframe'] = 'None';

	$nggic_options['images_per_page'] = 15;
	$nggic_options['display_filenames'] = "no";
	$nggic_options['default_alignment'] = 'none';
	$nggic_options['custom_class_1'] = 'not_used';
	$nggic_options['custom_class_2'] = 'not_used';
	$nggic_options['custom_class_3'] = 'not_used';
	$nggic_options['custom_class_4'] = 'not_used';
	$nggic_options['custom_url'] = 'http://';
	$nggic_options['class_mode'] = 'img';
	$nggic_options['click_mode'] = 'one_click_insert';
	$nggic_options['click_mode_variable'] = "yes";
	$nggic_options['wpg2id_tags'] = 'yes';
	$nggic_options['default_action'] = 'ngg_thumb_multi';
	$nggic_options['sortby'] = 'gallery_order';
	$nggic_options['language'] = substr(get_locale(), 0, 2);
}


?>

