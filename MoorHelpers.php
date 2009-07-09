<?php
/**
 * MoorResource - Resource/Controller extension for Moor
 *
 * Copyright (c) 2009 Jeff Turcotte
 *
 * @author  Jeff Turcotte
 * @license BSD (see LICENSE)
 * @version 0.1
 *
 * See README
 */

class MoorHelpers {
	static $yank_extension_pattern = '/\.([a-zA-Z]{2,4})$/';
	
	static function yankExtension() {
		Moor::map(array(self::$yank_extension_pattern, 1 => 'extension'), __CLASS__.'::yankExtensionCallback'); 
	}

	static function yankExtensionCallback() {
		Moor::$request_path = preg_replace(self::$yank_extension_pattern, '', Moor::$request_path);
		Moor::triggerContinue();
	}
}