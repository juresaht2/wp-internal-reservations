<?php
/*
Plugin Name: Wordpress internal reservations
Plugin URI: https://origin.git.t-2.local/sit/wp-internal-reservations
description: Wordpress plugin with a reservation calendar (to replace SharePoint).
Version: 0.1
Author: Jure Sah (T-2)
Author URI: https://canary.t-2.com/sis/messages/@juresah
License: Proprietary
Based on: https://www.smashingmagazine.com/2012/05/wordpress-shortcodes-complete-guide/
*/

//render calendar
function wpir_render() {
	return "Tu pride koledar za rezervacije...";
}

//shortcode registration
function wpir_register() {
	add_shortcode('internal-reservations', 'wpir_render');
}

add_action('init', 'wpir_register');