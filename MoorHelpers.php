<?php
/**
 * MoorResource - Resource/Controller extension for Moor
 *
 * Copyright (c) 2009 Jeff Turcotte
 *
 * @author  Jeff Turcotte
 * @license MIT (see LICENSE)
 * @version 0.2
 *
 * See README
 */

class MoorHelpers {
	static $yank_extension_pattern = '/\.([a-zA-Z]{2,4})$/';
	
	/**
	 * Removes and sets an extension with the $yank_extension_pattern
	 *
	 * @return void
	 */
	static function yankExtension() {
		Moor::addRoute(array(self::$yank_extension_pattern, 1 => 'extension'), __CLASS__.'::yankExtensionCallback'); 
	}

	/**
	 * Callback for the yankExtension route
	 *
	 * @return void
	 */
	static function yankExtensionCallback() {
		Moor::setRequestPath(preg_replace(self::$yank_extension_pattern, '', Moor::$request_path));
		Moor::triggerContinue();
	}
	
	/**
	 * Adds standard controller routes
	 *
	 * @return void
	 */
	static function addControllerRoutes() {
		MoorController::addRoute('/:controller/:id/:action');
		MoorController::addRoute('/:controller/:action');
		MoorController::addRoute('/:controller/:id', null, 'read');
		MoorController::addRoute('/:controller');
		MoorController::addRoute('/:action');
		MoorController::addRoute('/');
	}
	
}