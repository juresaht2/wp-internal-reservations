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
		add_action('wp_enqueue_scripts', array($this, 'enqueue'));
		add_action('wp_ajax_wipr_events', array($this, 'ajax'));
	}

	//shortcode registration
	function register() {
		add_shortcode('internal-reservations', array($this, 'render'));
	}

	//register CSS and javascript
	function enqueue() {
		wp_register_style('bootstrap', 
			plugins_url('css/bootstrap.min.css', __FILE__)
		);

		wp_enqueue_style('wpir-calendar', 
			plugins_url('css/calendar.min.css', __FILE__),
			array('bootstrap')
		);

		wp_register_script('underscore', 
			plugins_url('js/underscore-min.js', __FILE__)
		);
		wp_register_script('bootstrap', 
			plugins_url('js/bootstrap.min.js', __FILE__),
			array('jquery-core')
		);

		wp_register_script('wpir-calendar-SI', 
			plugins_url('js/language/sl-SL.js', __FILE__)
		);
		wp_register_script('wpir-calendar', 
			plugins_url('js/calendar.js', __FILE__),
			array('jquery-core', 'bootstrap', 'underscore', 'wpir-calendar-SI')
		);

		wp_enqueue_script('wpir-calendar-app',
			plugins_url('js/app.js', __FILE__),
			array('wpir-calendar')
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
		$out = array();

		for($i = 1; $i <= 15; $i++) { 	//from day 01 to day 15
			$data = date('Y-m-d', strtotime("+".$i." days"));
			$out[] = array(
				'id' => $i,
				'title' => 'Event name '.$i,
				'url' => "#",
				'class' => 'event-important',
				'start' => strtotime($data).'000'
			);
		}

		wp_send_json(array(
			'success' => 1,
			'result' => $out
		));

		wp_die();
	}

	//render calendar
	function render() {
		ob_start();
			?>Dela!<div id="calendar"></div><?php
		return ob_get_clean();
	}

}

new wp_internal_reservations;