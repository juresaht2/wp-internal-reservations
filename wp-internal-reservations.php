<?php
/*
Plugin Name: Wordpress internal reservations
Plugin URI: https://origin.git.t-2.local/sit/wp-internal-reservations
description: Wordpress plugin with a reservation calendar (to replace SharePoint).
Version: 1.0
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

		add_action('wp_ajax_wpir_edit_get', array($this, 'ajax_edit_get'));
		add_action('wp_ajax_nopriv_edit_get', array($this, 'ajax_edit_get'));
		add_action('wp_ajax_wpir_edit_set', array($this, 'ajax_edit_set'));
		add_action('wp_ajax_nopriv_edit_set', array($this, 'ajax_edit_set'));

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

	function ajax_edit_get() {
		$id = (int) $_POST["data"]["id"];

		global $current_user;
		wp_get_current_user();

		if($id > 0) {

			global $wpdb;

			$data = $wpdb->get_row($wpdb->prepare("
				SELECT `from`, `until`, `user`, `title`
				  FROM `".$wpdb->prefix."internal_reservations`
				 WHERE `id` = %d
				 LIMIT 1
			", array($id)), ARRAY_A);

			$title = $data["title"];
			$from = strtotime($data["from"]);
			$until = strtotime($data["until"]);
			$editable = ($data["user"] == $current_user->user_login || current_user_can('administrator'));

		} else {

			$title = $current_user->user_firstname." ".$current_user->user_lastname." rezervacija";
			$from = strtotime(date("Y-m-d 0:00", strtotime("tomorrow")));
			$until = strtotime(date("Y-m-d 23:59", strtotime("tomorrow")));
			$editable = True;

		}

		wp_send_json(array(
			'id' => $id,
			'title' => $title,
			'from' => date('Y-m-d\TH:i', $from),
			'until' => date('Y-m-d\TH:i', $until),
			'editable' => $editable
		));

		wp_die();
	}

	function ajax_edit_set() {
		ob_start();

		//un-serializeArray
		$data = array();
		foreach($_POST["data"] as $item) {
			$data[$item["name"]] = $item["value"];
		}

		global $wpdb;

		global $current_user;
		wp_get_current_user();

		$id = (int) $data["id"];
		$from = strtotime($data["from"]);
		$until = strtotime($data["until"]);

		$success = false;
		$overlap = false;
		if($id > 0) {
			//editing existing entry

			$existing_username = $wpdb->get_var($wpdb->prepare("
				SELECT `user`
				  FROM `".$wpdb->prefix."internal_reservations`
				 WHERE `id` = %d
				 LIMIT 1
			", array($id)));

			if($existing_username == $current_user->user_login || current_user_can('administrator')) {

				if(trim($data["title"]) == "") {
					//empty title means delete

					$success = true;
					$wpdb->delete(
						$wpdb->prefix."internal_reservations",
						array("id" => $id)
					);

				} else {

					//when checking for overlap exclude the ID we are currently editing
					if(!$this->checkOverlap($data["calendar"], $from, $until, $id)) {

						$success = true;
						//do not replace username on edit
						$wpdb->update(
							$wpdb->prefix."internal_reservations",
							array(
								"calendar" => $data["calendar"],
								"title" => $data["title"],
								"from" => date("Y-m-d H:i:s", $from),
								"until" => date("Y-m-d H:i:s", $until)
							),
							array("id" => $id)
						);

					} else {
						$overlap = true;
					}
				}

			} else {
				//don't have permission to edit
				//this should never happen normally and isn't handled
				status_header( 403 );
			}

		} else {
			//new entry

			if(!$this->checkOverlap($data["calendar"], $from, $until)) {
				$success = true;
				$wpdb->insert(
					$wpdb->prefix."internal_reservations",
					array(
						"calendar" => $data["calendar"],
						"user" => $current_user->user_login,
						"title" => $data["title"],
						"from" => date("Y-m-d H:i:s", $from),
						"until" => date("Y-m-d H:i:s", $until)
					)
				);
			} else {
				$overlap = true;
			}

		}

		wp_send_json(array(
			'success' => $success,
			'overlap' => $overlap,
			'debug' => ob_get_clean()
		));

		wp_die();
	}

	private function checkOverlap($calendar, $from, $until, $exclude = 0) {
		global $wpdb;

		return ($wpdb->get_var($wpdb->prepare("
			SELECT COUNT(*)
			  FROM `".$wpdb->prefix."internal_reservations`
			 WHERE `calendar` = %s
			   AND `id` != %d
			   AND (`from` <= %s AND `until` >= %s)
		", array(
			$calendar,
			$exclude,
			date("Y-m-d H:i:s", $until),
			date("Y-m-d H:i:s", $from)
		))) > 0);
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
			<input type="hidden" name="calendar" value="<?php echo $calendar; ?>">
			<table class='table borderless'>
				<thead>
					<tr>
						<td></td>
						<td>
							<h3>Vnos rezervacije</h3>
							<h4><?php echo $calendar; ?></h4>
						</td>
					</tr>
				</thead>
				<tbody>
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
						<td><small>Namig: Če želiš pobrisati rezervacijo, izprazni naslov.</small></td>
					</tr>
					<tr>
						<td></td>
						<td><button id="wpir-calendar-submit" type="submit" class="btn btn-primary" value="1">Shrani</button></td>
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