<?php
/*
Plugin Name: Wordpress internal reservations
Plugin URI: https://origin.git.t-2.local/sit/wp-internal-reservations
description: Wordpress plugin with a reservation calendar (to replace SharePoint).
Version: 0.1
Author: Jure Sah (T-2)
Author URI: https://canary.t-2.com/sis/messages/@juresah
License: Proprietary
Based on: https://github.com/Serhioromano/bootstrap-calendar
*/

class wp_internal_reservations {

	function __construct() {
		add_action('init', array($this, 'register'));
		register_activation_hook(__FILE__, array($this, 'activate'));

		add_action('wp_enqueue_scripts', array($this, 'enqueue'));
		//nopriv is okay since the whole site is authenticated

		add_action('wp_ajax_wpir_events', array($this, 'ajax'));
		add_action('wp_ajax_nopriv_wpir_events', array($this, 'ajax'));

		add_action('wp_ajax_wpir_edit_prompt', array($this, 'ajax_edit_prompt'));
		add_action('wp_ajax_nopriv_edit_prompt', array($this, 'ajax_edit_prompt'));

	}

	//shortcode registration
	function register() {
		add_shortcode('internal-reservations', array($this, 'render'));
	}

	function activate() {
		global $wpdb;

		if(!in_array($wpdb->prefix."internal_reservations", $wpdb->get_col("SHOW TABLES;"))) {

			$wpdb->query("
				CREATE TABLE `".$wpdb->prefix."internal_reservations` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `calendar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
				  `user` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
				  `from` datetime DEFAULT NULL,
				  `until` datetime DEFAULT NULL,
				  `title` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
				  PRIMARY KEY (`id`)
				) ENGINE=InnoDB ".$wpdb->get_charset_collate().";
			");
		}
	}

	//register CSS and javascript
	function enqueue() {
		wp_register_style('bootstrap', 
			plugins_url('css/bootstrap.min.css', __FILE__),
			array(),
			filemtime(dirname(__FILE__).'/css/bootstrap.min.css')
		);
		wp_register_style('wpir-calendar', 
			plugins_url('css/calendar.css', __FILE__),
			array('bootstrap'),
			filemtime(dirname(__FILE__).'/css/calendar.css')
		);

		//this is technically not needed, already provided
		wp_register_script('underscore', 
			plugins_url('js/underscore-min.js', __FILE__),
			array(),
			filemtime(dirname(__FILE__).'/js/underscore-min.js')
		);

		wp_register_script('bootstrap', 
			plugins_url('js/bootstrap.min.js', __FILE__),
			array('jquery-core'),
			filemtime(dirname(__FILE__).'/js/bootstrap.min.js')
		);

		wp_register_script('wpir-calendar-SI', 
			plugins_url('js/language/sl-SL.js', __FILE__),
			array('jquery-core', 'bootstrap', 'underscore'),
			filemtime(dirname(__FILE__).'/js/language/sl-SL.js')
		);
		wp_register_script('wpir-calendar', 
			plugins_url('js/calendar.js', __FILE__),
			array('jquery-core', 'bootstrap', 'underscore', 'wpir-calendar-SI'),
			filemtime(dirname(__FILE__).'/js/calendar.js')
		);

		wp_register_script('wpir-calendar-app',
			plugins_url('js/app.js', __FILE__),
			array('wpir-calendar'),
			filemtime(dirname(__FILE__).'/js/app.js'),
			true
		);

    wp_localize_script('wpir-calendar-app', 
    	'wordpress',
      array(
      	'ajax_url' => admin_url('admin-ajax.php'),
      	'tmpl_url' => plugins_url('tmpls', __FILE__).'/'
      )
    );

	}

	function ajax() {
		global $wpdb;

		$out = array();
		foreach($wpdb->get_results($wpdb->prepare("
			SELECT `id`, `from`, `until`, `user`, `title`
			  FROM `".$wpdb->prefix."internal_reservations`
			 WHERE `calendar` = %s
			 ORDER BY `id` DESC
			 LIMIT 10000
		", array($_POST["special"]["ime"])), ARRAY_A) as $event) {

			$title = $event["title"].
				" od ".date("j. n. Y H:i", strtotime($event["from"])).
				" do ".date("j. n. Y H:i", strtotime($event["until"]));

			$out[] = array(
				'id' => $event["id"],
				'title' => $title,
				'url' => "javascript:window.wpir_edit_box(".str_replace('"', "'", json_encode(
					(object) array("data" => array("id" => $event["id"]))
				)).")",
				'class' => 'event-important',
				'start' => strtotime($event["from"]).'000',
				'end' => strtotime($event["until"]).'000'
			);

		}

		wp_send_json(array(
			'success' => 1,
			'result' => $out
		));

		wp_die();
	}

	function ajax_edit_prompt() {

		//TODO: This needs to be reworked into two AJAX calls:
		//1. to retrieve the details of one event (by calendar name and ID)
		//2. to save a changed event, permissions allowing

		wp_send_json(array(
			'success' => 1
		));

		wp_die();
	}


	//render calendar
	function render($attr = array()) {
		wp_localize_script('wpir-calendar-app', 'wpir_special', $attr);

		wp_enqueue_style('wpir-calendar');
		wp_enqueue_script('wpir-calendar-app');

		global $current_user;
    wp_get_current_user();
    $calendar = $attr["ime"];

		ob_start(); ?>

<div class="pull-right form-inline">
	<div class="btn-group">
		<button class="btn btn-success" data-calendar-edit="add">Dodaj rezervacijo</button>
	</div>
	<div class="btn-group">
		<button class="btn" data-calendar-nav="prev">&lt;&lt; Nazaj</button>
		<button class="btn" data-calendar-nav="today">Trenutni</button>
		<button class="btn" data-calendar-nav="next">Naprej &gt;&gt;</button>
	</div>
	<div class="btn-group">
		<button class="btn" data-calendar-view="year">Leto</button>
		<button class="btn active" data-calendar-view="month">Mesec</button>
		<button class="btn" data-calendar-view="day">Dan</button>
	</div>
</div>
<h3 id="wpir-calendar-title"></h3>
<div style="clear: both;"><br></div>
<div id="wpir-calendar">Koledar se nalaga...</div>


<div id="wpir-calendar-overlay">
	<div id="wpir-calendar-edit-box">
		<form>
			<input type="hidden" name="id" value="0">
			<input type="hidden" name="user" value="<?php echo $current_user->user_login; ?>">
			<table class='table borderless'>
				<thead>
					<tr>
						<td></td>
						<td><h3>Vnos rezervacije</h3></td>
					</tr>
				</thead>
				<tbody>
					<tr>
						<th>Koledar: </th>
						<td><input type="text" name="calendar" disabled value="<?php echo $calendar; ?>"></td>
					</tr>
					<tr>
						<th>Naslov: </th>
						<td><input type="text" name="title"></td>
					</tr>
					<tr>
						<th>Od: </th>
						<td><input type="datetime-local" name="from"></td>
					</tr>
					<tr>
						<th>Do: </th>
						<td><input type="datetime-local" name="until"></td>
					</tr>
					<tr>
						<td></td>
						<td><button type="submit" class="btn btn-primary" value="1">Shrani</button></td>
					</tr>
				</tbody>
			</table>
		</form>
	</div>
</div><?php
		return ob_get_clean();
	}

}

new wp_internal_reservations;