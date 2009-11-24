<?php
// Moor Loader

include dirname(__FILE__).'/Moor.php';
include dirname(__FILE__).'/MoorRoute.php';
include dirname(__FILE__).'/MoorController.php';

if (!function_exists('link_to')) {
	function link_to() {
		$args = func_get_args();
		return call_user_func_array('Moor::linkTo', $args);
	}
}

if (!function_exists('route')) {
	function route_to() {
		$args = func_get_args();
		return call_user_func_array('Moor::routeTo', $args);
	}
}

if (!function_exists('run')) {
	function run() {
		return call_user_func('Moor::run');
	}
}