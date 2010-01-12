<?php
/**
 * Moor is a routing and controller library for PHP5
 *
 * @copyright  Copyright (c) 2010 Jeff Turcotte
 * @author     Jeff Turcotte [jt] <jeff.turcotte@gmail.com>
 * @license    MIT (see LICENSE or bottom of this file)
 * @package    Moor
 * @link       http://github.com/jeffturcotte/moor
 *
 * @version    1.0.0b2
 */
class Moor {

	/**
	 * Whether or not to show debug messages on default 404 page
	 *
	 * @var boolean
	 **/
	private static $debug = FALSE;
	
	/**
	 * Whether or not safe mode is enabled.
	 *
	 * @var string
	 **/
	private static $safe = TRUE;
	
	/**
	 * Debug messages
	 *
	 * @var array
	 **/
	private static $messages = array();

	/**
	 * The 404 callback to run upon no route matches
	 *
	 * @var string
	 **/
	private static $not_found_callback = 'Moor::routeNotFoundCallback';

	/**
	 * The name of the namespace GET param. Will replace @namespace in shorthand URLs
	 *
	 * @var string
	 **/
	private static $namespace_param_name = '_MOOR_NAMESPACE';

	/**
	 * The name of the class GET param. Will replace @class to shorthand URLs
	 *
	 * @var string
	 **/
	private static $class_param_name = '_MOOR_CLASS';
	
	/**
	 * The name of the function GET param. Will replace @function to shorthand URLs
	 *
	 * @var string
	 **/
	private static $function_param_name = '_MOOR_FUNCTION';

	/**
	 * Internal cache
	 *
	 * @var array
	 **/
	private static $cache = array();

	/**
	 * All routes indexed by number
	 *
	 * @var array
	 **/
	private static $routes = array();

	/**
	 * Routes indexed by callback. Not necessarily all routes
	 *
	 * @var array
	 **/
	private static $routes_by_callback = array();

	// ==============
	// = Public API =
	// ==============

	/**
	 * Find the URL where a particular callback lives
	 *
	 * @param  string $callback_string The callback to search for
	 * @param  string $params          The GET params to send
	 * @return string The URL
	 */
	public static function linkTo($callback_string, $params=array()) {		
	
		$key = __FUNCTION__ . '/' . $callback_string . '/' . implode('/', array_keys($params));

		if (isset(self::$cache[$key])):
			$best_route = self::$cache[$key];
		else:
			if (!$best_callback = self::findBestCallbackMatch($callback_string)) {
				return '#';
			}
	
			foreach(self::$routes as $route):
				if ($route->callback->scalar != $best_callback->scalar) {
					continue;
				}
				
				$diff = abs(count($route->url->params) - count($params));
				$sect = count(array_intersect($route->url->params, array_keys($params)));

				if (!isset($best_route) || ($sect >= $top_sect && $diff < $top_diff || $sect > $top_sect)) {
					$top_sect = $sect;
					$top_diff = $diff;
					$best_route = $route;
				}
			endforeach;
			
			self::$cache[$key] = $best_route;
		endif;
		
		$callback = self::parseCallback($callback_string);
		
		if ($best_route->callback->namespace == '*') {
			$params[self::$namespace_param_name] = $callback->underscore_namespace;
		}
		if ($best_route->callback->class == '*') {
			$params[self::$class_param_name] = $callback->underscore_class;
		}
		if ($best_route->callback->function == '*') {
			$params[self::$function_param_name] = $callback->underscore_function;
		}
	
		$params = array_merge(
			$params, 
			array_intersect_key(
				$best_route->callback->overrides,
				$best_route->url->params_flipped
			)
		);

		$url = $best_route->url->shorthand;
		
		foreach($best_route->url->params as $url_param):
			if (isset($params[$url_param])):
				$url = str_replace(':'.$url_param, $params[$url_param], $url);
				unset($params[$url_param]);
			endif;
		endforeach;
		
		if (!empty($params)) { 
			$url .= '?' . http_build_query($params); 
		}
		

		return $url;
	}

	/**
	 * Assign a URL to a callback for routing
	 *
	 * @param  string $url_string      The shorthand URL or regular expression pattern to match
	 * @param  string $callback_string The callback to be run on a successful match
	 * @param  string $function        An optional anonymous function to override the callback
	 * @return void
	 */
	public static function route($url_string, $callback_string, $function=NULL) {
		if (self::$safe && in_array($callback_string, array('*', '*\\*', '\\*'))) {
			self::addMessage(__METHOD__, '(SAFE MODE) Disallowing route with dangerous callback: ' . $callback_string);
			self::triggerContinue();
		}
		
		$route = (object) 'route';
		$route->url = self::parseUrl($url_string);
		$route->callback = self::parseCallback($callback_string);

		self::$routes_by_callback[$callback_string] =& $route;
		self::$routes[] =& $route;

	}

	/**
	 * Starts routing
	 *
	 * @return void
	 */
	public static function run() {
		$old_GET = $_GET;
		$_GET = array();
		
		$request_path = preg_replace('#\?.*$#', '', $_SERVER['REQUEST_URI']);

		foreach(self::$routes as $route):
			$_GET = $old_GET;

			try {
				if (!preg_match($route->url->regex, $request_path, $matches)) {
					continue;
				}
				
				foreach($matches as $name => $param):
					if (is_string($name)) {
						$_GET[$name] = $param;
					}
				endforeach;
				
				$callback =& self::parseCallback(self::buildCallback($route->callback));
	
				self::validateCallback($callback);
				self::dispatchCallback($callback);
			
			} catch (MoorContinueException $e) {
				continue;
			} catch (MoorNotFoundException $e) {
				break;
			}
		endforeach;
		
		call_user_func(self::$not_found_callback);
		exit();
	}
	
	/**
	 * Return whether debugging is enabled or not
	 *
	 * @return boolean 
	 */
	public static function getDebug()
	{
		return self::$debug;
	}
	
	/**
	 * Return all debug messages set up to this point
	 *
	 * @return array
	 **/
	public static function getMessages()
	{
		return self::$messages;
	}
	
	/**
	 * Enable or disable the debugging
	 *
	 * @return void
	 **/
	public static function setDebug($bool=TRUE)
	{
		self::$debug = (boolean) $bool;
	}
	
	/**
	 * Set the callback for when a route is not found.
	 *
	 * @return void
	 **/
	public static function setNotFoundCallback($callback)
	{
		self::$not_found_callback = $callback;
	}
	
	/**
	 * Add a debug messages to the messages stack
	 *
	 * @param string $method The method adding the message
	 * @param string $message The debug message
	 * @return void
	 */
	public static function addMessage($method, $message) 
	{
		array_push(self::$messages, $message);
	}
	
	/**
	 * Convert a callback string to a filepath
	 *
	 * Best used to map files to particular callbacks
	 *
	 * @param  string $callback_string The callback
	 * @return string The relative filepath
	 */
	public static function toPath($callback_string) {
		$callback = self::parseCallback($callback_string);
		
		$path = sprintf('/%s/%s/%s', 
			$callback->underscore_namespace, 
			$callback->underscore_class,
			$callback->underscore_function
		);
		
		return '/' . trim($path, '/');
	}
	
	public static function triggerContinue() {
		throw new MoorContinueException();
	}
	
	public static function triggerNotFound() {
		throw new NotFoundException();
	}
	
	// =========================
	// = Private/Protected API =
	// =========================
	
	/**
	 * Converts an `underscore_notation` or `camelCase` string to `camelCase`
	 * 
	 * Derived from MIT fGrammer::camelize by Will Bond <will@flourishlib.com>
	 * Source: http://flourishlib.com/browser/fGrammar.php
	 *
	 * @param  string  $original The string to convert
	 * @param  boolean $upper    If the camel case should be `UpperCamelCase`
	 * @return string  The converted string
	 */
	private static function &camelize($original, $upper=FALSE)
	{
		$upper = (int) $upper;
		$key   = __FUNCTION__ . "/{$upper}/{$original}";
		
		if (isset(self::$cache[$key])) {
			return self::$cache[$key];		
		}
		
		$string = $original;
		
		// Check to make sure this is not already camel case
		if (strpos($string, '_') === FALSE) {
			if ($upper) { 
				$string = strtoupper($string[0]) . substr($string, 1); 
			}
			
		// Handle underscore notation
		} else {
			$string = strtolower($string);
			if ($upper) { $string = strtoupper($string[0]) . substr($string, 1); }
			$string = preg_replace('/(_([a-z0-9]))/e', 'strtoupper("\2")', $string);		
		}
		
		return self::$cache[$key] =& $string;
	}
	
	
	/**
	 * Reads a URL string and converts to a URL object
	 *
	 * @param  string $url_string    The URL string (either shorthand or a regular expression)
	 * @return object The URL object
	 */
	private static function &parseUrl($url_string) {
		if (isset(self::$cache[__FUNCTION__.$url_string])) {
			return self::$cache[__FUNCTION__.$url_string];
		}
		
		$url_string = str_replace('@namespace', ':' . self::$namespace_param_name, $url_string);
		$url_string = str_replace('@class', ':' . self::$class_param_name, $url_string);
		$url_string = str_replace('@method', ':' . self::$function_param_name, $url_string);
		$url_string = str_replace('@function', ':' . self::$function_param_name, $url_string);

		$url = (object) $url_string;
		$url->regex = $url_string;
		$url->shorthand = $url_string;

		if (strpos($url->scalar, 'preg:') === 0):	

			$url->regex = substr($url->regex, 4);

			# pattern for matching regex named groups
			#  \(\?P ...................... start group
			#  \<([A-Z0-9a-z_-]+)\> ....... get name of group
			#  ((?:[^()]|\((?2)\))*+) ..... recursively make sure parens are even for subgroup(s)
			#  \) ......................... end group
		
			$named_group_pattern = "#\(\?P \<([A-Z0-9a-z_-]+)\> ((?:[^()]|\((?2)\))*+) \)#x";
			$shorthand = substr($url->regex, 1, -1);
			$shorthand = preg_replace($named_group_pattern, ':\1', $shorthand);
			$shorthand = ltrim($shorthand, '^');
			$shorthand = rtrim($shorthand, '$');
			
			$url->shorthand = $shorthand;
			
		else:

			$url->regex = preg_replace(
				'/\\\\:([a-zA-Z0-9_-]+)/', 
				'(?P<\1>[a-zA-Z0-9_-]+)', 
				'#^' . preg_quote($url->scalar, '#') . '$#'
			);
			
		endif;
		
		// extract url params
		preg_match_all('/\:([a-zA-Z0-9_]+)/', $url->shorthand, $matches);
		$url->params = isset($matches[1]) ? $matches[1] : array();
		$url->params_flipped = array_flip($url->params);
		
		return $url;
		
	}
	
	/**
	 * Reads a callback string and converts to a callback object
	 *
	 * @param  string $callback_string The callback
	 * @return object The callback object
	 */
	private static function &parseCallback($callback_string) {
		if (isset(self::$cache[__FUNCTION__.$callback_string])) {
			return self::$cache[__FUNCTION__.$callback_string];
		}

		$callback = (object) trim($callback_string, '\\');
		$callback->overrides = array();
		
		preg_match('/^((?P<namespace>.+)\\\\)?((?P<class>.+)::)?(?P<function>.+)$/', $callback_string, $matches);
		$callback->namespace = ($matches['namespace']) ? self::camelize($matches['namespace'], TRUE) : NULL;
		$callback->class     = ($matches['class']) ? self::camelize($matches['class'], TRUE) : NULL;
		$callback->function  = ($matches['function']) ? self::camelize($matches['function']) : NULL;
		
		$callback->underscore_namespace = ($callback->namespace) ? self::underscorize($callback->namespace) : NULL;
		$callback->underscore_class     = ($callback->class) ? self::underscorize($callback->class) : NULL;
		$callback->underscore_function  = ($callback->function) ? self::underscorize($callback->function) : NULL;
	
		if ($callback->namespace != '*' && $callback->namespace !== NULL) {
			$callback->overrides[self::$namespace_param_name] = $callback->underscore_namespace;
		}
		if ($callback->class != '*' && $callback->class !== NULL) {
			$callback->overrides[self::$class_param_name] = $callback->underscore_class;
		}
		if ($callback->function != '*' && $callback->function !== NULL) {
			$callback->overrides[self::$function_param_name] = $callback->underscore_function;
		}
	
		$callback->callable_class = ($callback->namespace) 
			? $callback->namespace . self::getNamespaceSeparator() . $callback->class
			: $callback->class;
			
		$callback->callable_function = $callback->function;	
		
		$callback->callable_function = ($callback->namespace && $callback->class)
			? $callback->namespace . self::getNamespaceSeparator() . $callback->class . '::' . $callback->function
			: $callback->function;
			
		$callback->callable_function = ($callback->namespace && !$callback->class)
			? $callback->namespace . self::getNamespaceSeparator() . $callback->function
			: $callback->function;
		
		return self::$cache[__FUNCTION__.$callback_string] =& $callback;
	}

	/**
	 * Get the namespace separator for the php version being used
	 *
	 * @return string The namespace separator
	 */
	private static function getNamespaceSeparator() {
		return (version_compare(PHP_VERSION, '5.3.0') >= 0) ? '\\' : '_';
	}
	
	/**
	 * Create a callback string from a callback object, using GET params for wildcard pieces
	 *
	 * @param object $callback The callback object from parseCallback
	 * @param string The callback string
	 */
	private static function &buildCallback($callback) {
		if (isset(self::$cache[__FUNCTION__.$callback->scalar])) {
			return self::$cache[__FUNCTION__.$callback->scalar];
		}
		
		$callback = clone $callback;
		
		$callback_string = '';
		
		if ($callback->namespace == '*') {
			$callback->namespace = (isset($_GET[self::$namespace_param_name]))
				? $_GET[self::$namespace_param_name]
				: $callback->namespace;
		}

		if ($callback->class == '*') {
			$callback->class = (isset($_GET[self::$class_param_name]))
				? $_GET[self::$class_param_name]
				: $callback->class;
		}

		if ($callback->function == '*') {
			$callback->function = (isset($_GET[self::$function_param_name]))
				? $_GET[self::$function_param_name]
				: $callback->function;
		}
		
		if ($callback->namespace) {
			$callback_string .= $callback->namespace . '\\';
		}
		if ($callback->class) {
			$callback_string .= $callback->class . '::';
		}
		if ($callback->function) {
			$callback_string .= $callback->function;
		}
		
		return $callback_string;
	}
	
	/**
	 * Finds the best callback match from defined routes using a callback string
	 *
	 * @param  string $callback_string The callback to search for
	 * @return object The best matched callback
	 */
	private static function findBestCallbackMatch($callback_string) 
	{
		$key = __FUNCTION__ . $callback_string;
		if (isset(Moor::$cache[$key])) {
			return Moor::$cache[$key];
		}

		$callback = self::parseCallback($callback_string);
		
		$n = $callback->namespace;
		$c = $callback->class;
		$f = $callback->function;

		if ($n && $c && $f) {
			$possible_callbacks = array(
				"{$n}\\{$c}::{$f}",
				"{$n}\\{$c}::*",
				"{$n}\\*::{$f}",
				"{$n}\\*::*",
				"*\\{$c}::{$f}",
				"*\\{$c}::*",
				"*\\*::{$f}",
				"*\\*::*"
			);
		} else if ($n && $f) {
			$possible_callbacks = array(
				"{$n}\\{$f}",
				"{$n}\\*",
				"*\\{$f}",
				"*\\*"
			);
		} else if (!$n && $c && $f) {
			$possible_callbacks = array(
				"{$c}::{$f}",
				"\\{$c}::{$f}",
				"{$c}::*",
				"\\{$c}::*",
				"*::{$f}",
				"\\*::{$f}",
				"*::*",
				"\\*::*"
			);
		} else if (!$n && $f) {
			$possible_callbacks = array(
				"{$f}",
				"\\{$f}",
				"*",
				"\\*"
			);
		}

		foreach ($possible_callbacks as $possible_callback) {
			if (isset(self::$routes_by_callback[$possible_callback])) {
				Moor::$cache[$key] =& self::$routes_by_callback[$possible_callback]->callback;
				return Moor::$cache[$key];
			}				
        }

		return FALSE;
	}

	/**
	 * The default 404 Not Found callback. Prints debug messages if they are on.
	 *
	 * @return void
	 */
	private static function routeNotFoundCallback() {
		header("HTTP/1.1 404 Not Found");
		echo '<h1>NOT FOUND</h1>';
		echo "\n\n";

		if (self::$debug) {
			echo '<h2>Moor Debug</h2>';
			echo "\n\n";
			echo join("\n", self::$messages);
		}
	}
	
	/**
	 * Converts a `camelCase` or `underscore_notation` string to `underscore_notation`
	 *
	 * Derived from MIT fGrammer::camelize by Will Bond <will@flourishlib.com>
	 * Source: http://flourishlib.com/browser/fGrammar.php
	 *
	 * @param  string $string  The string to convert
	 * @return string  The converted string
	 */
	private static function &underscorize($string)
	{
		$key = __FUNCTION__ . "/{$string}";
		
		if (isset(self::$cache[$key])) {
			return self::$cache[$key];		
		}
		
		$original = $string;
		$string = strtolower($string[0]) . substr($string, 1);
		
		// If the string is already underscore notation then leave it
		if (strpos($string, '_') !== FALSE) {
		
		// Allow humanized string to be passed in
		} elseif (strpos($string, ' ') !== FALSE) {
			$string = strtolower(preg_replace('#\s+#', '_', $string));
		
		} else {
			do {
				$old_string = $string;
				$string = preg_replace('/([a-zA-Z])([0-9])/', '\1_\2', $string);
				$string = preg_replace('/([a-z0-9A-Z])([A-Z])/', '\1_\2', $string);
			} while ($old_string != $string);
			
			$string = strtolower($string);
		}
		
		return self::$cache[$key] =& $string;
	}
	
	/**
	 * Dispatch a callback
	 *
	 * 
	 * @return void
	 **/
	private static function dispatchCallback($callback)
	{
		if ($callback->class) {
			// define our friendly neighborhood constants
			define('MOOR_NAMESPACE', $callback->namespace);
			define('MOOR_CLASS', $callback->callable_class);
			define('MOOR_FUNCTION', $callback->function);
			define('MOOR_METHOD', $callback->scalar);
			define('MOOR_PATH', self::toPath(MOOR_METHOD));
			new $callback->callable_class($callback->function);
			exit();
		} 

		if ($callback->function) {
			define('MOOR_NAMESPACE', $callback->namespace);
			define('MOOR_FUNCTION', $callback->scalar);
			define('MOOR_PATH', self::toPath(MOOR_FUNCTION));
			call_user_func($callback->callable_function);
			exit;
		}
	}
	
	/**
	 * Validates a callback for dispatch
	 *
	 * @param object $callback A callback object
	 * @return void
	 **/
	private static function validateCallback($callback)
	{
		// disallow unsafe callbacks
		
		if ($callback->namespace == '*' || $callback->class == '*' || $callback->function == '*') {
			self::addMessage(__METHOD__, 'Skipped route. Callback is missing the GET params for wildcard replacement.');
			self::triggerContinue();
		}
		
		if ($callback->class) {
			
			if (!class_exists($callback->callable_class)) {
				self::addMessage(__METHOD__, 'Skipped route. Class ' . $callback->callable_class . ' does not exist');
				self::triggerContinue();
			}
			
			if (strpos($callback->function, '__') === 0) {
				self::addMessage(__METHOD__, 'Skipped route. Method ' . $callback->function . ' looks like a magic method.');
				self::triggerContinue();
			}
			
			$method = new ReflectionMethod($callback->callable_class, $callback->function);
			$method_is_public = $method->isPublic();
			$method_is_static = $method->isStatic();
			
			if (!is_subclass_of($callback->callable_class, 'MoorAbstractController')) {
				self::addMessage(__METHOD__, 'Skipped route. Class ' . $callback->callable_class . ' doesn\'t extend MoorAbstractController.');
				self::triggerContinue();
			}
			
			if ($method->isStatic()) {
				self::addMessage(__METHOD__, 'Skipped route. Method ' . $callback->function . ' is static.');
				self::triggerContinue();
			}
			
			if (!$method->isPublic()) {
				self::addMessage(__METHOD__, 'Skipped route. Method ' . $callback->function . ' is not publicly accessible.');
				self::triggerContinue();
			}
			
		} else if ($callback->function) {

			if (!is_callable($callback->callable_function)) {
				self::addMessage(__METHOD__, 'Skip: function ' . $callback->callable_function . ' is not callable.');
				self::triggerContinue();
			}
		}
	}
}

// =============
// = Exception =
// =============

class MoorException extends Exception {}
class MoorContinueException extends MoorException {}
class MoorNotFoundException extends MoorException {}


// ===============================
// = Built-In Controller Classes =
// ===============================

/**
 * Abstract Controller
 * 
 * @package Moor
 */
class MoorAbstractController {
	public function __construct($action_method) {
		$this->$action_method();
	}
}

/**
 * Action Controller 
 *
 * @package Moor
 */
class MoorActionController extends MoorAbstractController {
	public function __construct($action_method) {
		$this->__before();
		
		try {
		    $this->{$action_method}();
		    
		} catch (Exception $e) {
		    
		    $exception = new ReflectionClass($e);

		    while($exception) {
    		    // pass exceptions to a __catch_ExceptionClass method 
    		    $magic_exception_catcher = "__catch_" . $exception->getName();
				if (is_callable(array($this, $magic_exception_catcher))) {
					call_user_func_array(array($this, $magic_exception_catcher), array($e));
					break;
				}
				$exception = $exception->getParentClass();
			}
			
 			if (!$exception) {
                throw $e;
            }
		}
		
		$this->__after();
		exit();
	}
	
	protected function __before() {}
	protected function __after() {}
}

// ====================
// = Helper Functions =
// ====================

if (!function_exists('link_to')) {
	function link_to($callback_string, $params) {
		return Moor::linkTo($callback_string, $params);
	}
}

if (!function_exists('route')) {
	function route($url, $callback_string, $function=null) {
		return Moor::route($url, $callback_string, $function);
	}
}

if (!function_exists('run')) {
	function run() {
		return Moor::run();
	}
}

// ===========
// = License =
// ===========

// Moor - a routing and controller library for PHP5
// 
// Copyright (c) 2010 Jeff Turcotte
// 
// Permission is hereby granted, free of charge, to any person
// obtaining a copy of this software and associated documentation
// files (the "Software"), to deal in the Software without
// restriction, including without limitation the rights to use,
// copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the
// Software is furnished to do so, subject to the following
// conditions:
// 
// The above copyright notice and this permission notice shall be
// included in all copies or substantial portions of the Software.
// 
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
// EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
// OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
// NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
// HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
// WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
// FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
// OTHER DEALINGS IN THE SOFTWARE.
