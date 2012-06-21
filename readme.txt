=== NextGEN Gallery Image Chooser ===
Contributors: umertin
Tags: nextgen, gallery, image chooser
Requires at least: 3.3.1
Tested up to: 3.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Image Chooser for the excellent NextGEN Gallery, based on g2image 

== Description ==

[NextGEN Gallery](http://www.nextgen-gallery.com/) is an excellent gallery for WordPress. Its major drawback, as far as I'm concerned, is the minimalistic image chooser.
So I've taken the liberty to adapt the equally excellent [g2image](http://g2image.steffensenfamily.com/) image chooser, which also is embedded in the WPG2 connector to Gallery2.

At present, only the main tags [album], [nggallery], [thumb], and [singlepic], plus some html links are supported. Also, presently there is no elegant way to set the default values, you would have to edit the init.php file.

But as I think that the plugin already is helpful as it is, I'm offering it to you and will add further features, as time permits.
               
== Installation ==

1. Upload the `nextgen-gallery-image-chooser` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. In the visual editor, you will find a new NGG button which allows adding albums, galleries and images to the post

== Frequently Asked Questions ==

None yet.

== Screenshots ==

1. "Add new post" page with NextGEN Gallery Image Chooser button.
2. Image Chooser page for album (without images).
3. Image Chooser page for gallery (with images). Options for the [singlepic] tag are displayed.
4. Image Chooser page for gallery (with images). Options for the [thumb] tag are displayed.

== Changelog ==

= 0.1.0 =
* Added [imagebrowser] tag
* Disabled insert button for root element of the gallery tree
* Added placeholder image when inserting [album] tag
* Created different placeholder images for albums, galleries and images/thumbnails
* Removed hard link to the TinyMCE editor_plugin.js file
* Added screenshots

= 0.0.2 =
* Added template, mode and link options to NGG tags
* Added quotation marks to user entered options
* Added locale support and German translation

= 0.0.1 =
* Initial version

== Upgrade Notice ==

None yet.
