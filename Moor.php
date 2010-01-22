<?php
/**
 * Moor is a routing and controller library for PHP5
 *
 * @copyright  Copyright (c) 2010 Jeff Turcotte
 * @author     Jeff Turcotte [jt] <jeff.turcotte@gmail.com>
 * @license    MIT (see LICENSE or bottom of this file)
 * @package    Moor
 * @link       http://github.com/jeffturcotte/moor
 * @version    1.0.0b3
 */
class Moor {
	/**
	 * The currently running callback
	 *
	 * @var string
	 */
	private static $active_callback = NULL;
	
	/**
	 * The current path mapping for the active callback
	 *
	 * @var string
	 */
	private static $active_path = NULL;

	/**
	 * Internal cache
	 *
	 * @var array
	 **/
	private static $cache = array();

	/**
	 * Internal cache for LinkTo 
	 *
	 * @var array
	 */
	private static $cache_for_linkTo = array();

	/**
	 * Default pattern to match :id params in incoming urls
	 *
	 * @var string
	 */
	private static $default_param_pattern = '[A-Za-z0-9_]+';

	/**
	 * Whether or not to show debug messages on default 404 page
	 *
	 * @var boolean
	 **/
	private static $debug = FALSE;

	/**
	 * The current more instance, only used for chaining
	 *
	 * @var object Moor
	 */ 
	private static $instance = NULL;

	/**
	 * Default linkTo response for when a link can't be found
	 *
	 * @var string
	 **/
	private static $link_not_found_response = '#';

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
	 * All routes in their compiled form
	 *
	 * @var array
	 **/
	private static $routes = array();

	/**
	 * Whether or not the router is running
	 *
	 * @var boolean
	 */
	private static $running = FALSE;

	/**
	 * All routes that have yet to be compiled
	 * 
	 * @var array
	 **/
	private static $uncompiled_routes = array();

	// ==============
	// = Public API =
	// ==============

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
	 * Find the URL where a particular callback lives
	 *
	 * @param  string $callback_string The callback to search for
	 * @param  string $params          The GET params to send
	 * @return string The URL
	 */
	public static function linkTo($key) {
		if (!self::$running) {
			throw new MoorProgrammerException(
				'linkTo() cannot be used until routing has been started with run().'
			);
		}

		$param_values    = func_get_args();
		$callback_string = array_shift($param_values);
		$callback_string = trim($callback_string);

		if (strpos($callback_string, '::') == 0 && self::$active_callback) {
			try {
				$rm = new ReflectionMethod(self::$active_callback);
				$callback_string = $rm->getDeclaringClass()->getName() . $callback_string;
			} catch (ReflectionException $e) {
				return '#';
			}
		}

		if (!isset(self::$cache_for_linkTo[$key])) {
			self::$cache_for_linkTo[$key] = FALSE;

			$best_route = NULL;
			$param_names = preg_split('/\s+/', $callback_string);
			$param_names_flipped = array_flip($param_names);
			$callback_string = array_shift($param_names);
			$callback_params = array();
			$param_matches = NULL;
			$low_dist = NULL;
			$top_accu = NULL;
			$low_diff = NULL;
			$top_sect = NULL;
		
			if (!$best_route) {
				foreach(self::$routes as $route) {
					$callback = $route->callback;
			
					if (!preg_match($callback->pattern, $callback_string, $callback_param_matches)) {
						continue;
					}
				
					foreach($callback_param_matches as $name => $param_match) {
						if (is_string($name)) { $callback_params[$name] = $param_match; }
					}
								
					$dist = levenshtein($callback->finder, $callback_string);
					$accu = strrpos($callback->finder, '*');
					$diff = abs(count($route->url->request_params) - count($param_names));
					$sect = count(array_intersect_key(array_flip(array_keys($route->url->request_params)), $param_names_flipped));

					$is_best = (
						$best_route === NULL || 
						$low_dist > $dist ||
						$low_dist == $dist && $top_accu < $accu ||
						$low_dist == $dist && $top_accu == $accu && $top_sect < $sect ||
						$low_dist == $dist && $top_accu == $accu && $top_sect <= $sect && $top_diff > $diff 
					);
				
					if ($is_best) {
						$best_route = $route;
						$low_dist = $dist;
						$top_accu = $accu;
						$top_sect = $sect;
						$top_diff = $diff;
					}
				}
			}
		
			if ($best_route) {
				$cache = (object) $best_route->url->shorthand;
				$cache->param_names = $param_names;
				
				$cache->included_param_names = array_flip(array_intersect(
					$param_names, array_keys($best_route->url->request_params)
				));
				
				$cache->excluded_param_names = array_flip(array_diff(
					$param_names, array_keys($best_route->url->request_params)
				));

				$url = $best_route->url->shorthand;
				
				foreach($best_route->url->callback_params as $name => $callback_param) {
					$url = str_replace(
						$callback_param->search,
						call_user_func_array(
							$callback_param->formatter,
							array($callback_params[$callback_param->name])
						),
						$url
					);
				}
				
				$cache->url = $url;
				
				self::$cache_for_linkTo[$key] = $cache;
			}
		}

		$cache =& self::$cache_for_linkTo[$key];

		if ($cache == FALSE) {
			return '#';
		}

		$url = $cache->url;

		$params = array();
		if (!empty($cache->param_names)) {
			$params = array_combine(
				$cache->param_names, 
				$param_values
			);
		}

		$included_params = array_intersect_key($params, $cache->included_param_names);
		$excluded_params = array_intersect_key($params, $cache->excluded_param_names);
		
		foreach($included_params as $name => $value) {
			$url = str_replace(':'.$name, $value, $url);
		}

		if (!empty($excluded_params)) { 
			$url .= '?' . http_build_query($excluded_params); 
		}
		
		return $url;
	}

	/**
	 * Returns the callback string for the currently running route
	 *
	 * @return string
	 */
	public static function getCallback()
	{
		return self::$active_callback;
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
	 * Returns the path for the currently running route
	 *
	 * @return string
	 */
	public static function getPath()
	{
		return self::$active_path;
	}

	/**
	 * Makes a path out of a callback string.
	 *
	 * @param string $callback_string 
	 * @return string  The path created from the callback
	 */
	public static function makePath($callback_string)
	{
		$ds = DIRECTORY_SEPARATOR;
		
		$string = str_replace('::', $ds, $callback_string);
		$string = str_replace('\\', $ds, $string);
		$string = preg_replace('/_([A-Z])/', $ds.'$1', $string);

		$pieces = explode($ds, $string);
		foreach($pieces as $n => $piece) {
			$pieces[$n] = self::underscorize($piece);
		}
		
		return $ds . join($ds, $pieces);
	}

	/**
	 * Assign a URL to a callback for routing
	 *
	 * @param  string          $url_string      The shorthand URL or regular expression pattern to match
	 * @param  string|closure  $callback_string The callback to be run on a successful match, the name of the assoc. closure, or a closure
	 * @param  closure         $function        An optional closure, named by the previous argument (for linking)
	 * @return object The Moor instance for chaining
	 */
	public static function route($url_string, $callback_string, $function=NULL) 
	{
		if (self::$running == TRUE) {
			throw new MoorProgrammerException(
				'No new routes can be added once routing has been started.'
			);
		}
		
		if ($callback_string instanceof Closure) {
			$function = $callback_string;
			$callback_string = '';
		}

		$route = (object) 'uncompiled_route';
		$route->url      = $url_string;
		$route->callback = $callback_string;
		$route->function = $function;

		array_push(self::$uncompiled_routes, $route);

		return self::getInstance();
	}

	/**
	 * Starts routing
	 *
	 * @return void
	 */
	public static function run() 
	{
		self::$running = TRUE;
		
		self::compile();
		
		$old_GET = $_GET;
		$_GET = array();

		$request_path = preg_replace('#\?.*$#', '', $_SERVER['REQUEST_URI']);

		foreach(self::$routes as $route):
			self::$active_callback = NULL;
			self::$active_path = NULL;
		
			$_GET = $old_GET;

			try {
				if (!preg_match($route->url->pattern, $request_path, $matches)) {
					continue;
				}

				foreach($matches as $name => $param):
					if (is_string($name)) {
						$_GET[$name] = $param;
					}
				endforeach;

				self::dispatchRoute($route);
				exit();

			} catch (MoorContinueException $e) {
				continue;
			} catch (MoorNotFoundException $e) {
				break;
			}
		endforeach;

		self::addMessage(__METHOD__, 'No Matches Found. Running Not Found callback: ' . self::$not_found_callback);
		
		call_user_func(self::$not_found_callback);
		exit();
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
	 * Sets the pattern that will match a URL request param if no pattern is given
	 *
	 * @param string $pattern  The regular expression to match a request param in the URL
	 * @return void
	 **/
	public static function setDefaultRequestParamPattern($pattern)
	{
		self::$default_param_pattern = $pattern;
	}

	/**
	 * Sets the response from linkTo if a valid match can't be found
	 *
	 * @param string $response  The string to return a link isn't found
	 * @return void
	 **/
	public function setLinkNotFoundResponse($response)
	{
		self::$link_not_found_response = $response;
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
	 * Triggers skipping the current route and moving to the next one.
	 *
	 * @return void
	 */
	public static function triggerContinue() {
		throw new MoorContinueException();
	}
	
	/**
	 * Triggers the not found callback when the router is running.
	 *
	 * @return void
	 */
	public static function triggerNotFound() {
		throw new NotFoundException();
	}

	// ===============
	// = Private API =
	// ===============

	/**
	 * Private constructor to disallow instantiating
	 * 
	 * @return object
	 */
	private function __construct() {}

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
	 * Compiles routes for routing. Called from run();
	 *
	 * @return void
	 */
	private static function compile()
	{
		foreach(self::$uncompiled_routes as $uncompiled_route) {

			$route = (object) 'route';

			$route->url      = self::parseUrl($uncompiled_route->url);
			$route->callback = self::parseCallback($uncompiled_route->callback);
			$route->function = $uncompiled_route->function;

			// validate route
			$diff = array_merge(
				array_diff_key($route->callback->params, $route->url->callback_params),
				array_diff_key($route->url->callback_params, $route->callback->params)
			);

			if (count($diff)) {
				throw new MoorProgrammerException(
					'Route: ' . $route->url->scalar . ', url and callback have different callback params: ' . join(',', array_keys($diff))
				);
			}

			array_push(self::$routes, $route);
		}
	}
	
	/**
	 * Dispatch a callback
	 * 
	 * @param object $route a route stdObject
	 * @return void
	 **/
	private static function dispatchRoute($route)
	{
		// if there's an assoc. closure, no validation necessary.
		if ($route->function instanceof Closure) {
			call_user_func($route->function);
			exit();
		}

		$callback_string = self::injectParamsIntoCallback($route->callback);

		self::$active_callback = $callback_string;
		self::$active_path = self::makePath($callback_string);

		try {
			// attempt to run a method callback
			$method = new ReflectionMethod($callback_string);
			$class  = $method->getDeclaringClass();

			// validate method callback
			if (!$class->isSubclassOf('MoorAbstractController')) {
				self::addMessage(__METHOD__, 'Skipping callback: ' . $callback_string . '. Class doesn\'t implement or extend a MoorController interface.');
				self::triggerContinue();
			}

			if ($method->getName() == '_moor') {
				self::addMessage(__METHOD__, 'Skipping callback: ' . $callback_string . '. Method reserved.');
				self::triggerContinue();
			}

			if (strpos($method->getName(), '__') === 0) {
				self::addMessage(__METHOD__, 'Skipping callback: ' . $callback_string . '. Method looks like magic method.');
				self::triggerContinue();
			}

			if (!$method->isPublic()) {
				self::addMessage(__METHOD__, 'Skipping callback: ' . $callback_string . '. Method isn\'t public.');
				self::triggerContinue();
			}

			// set currently running Method
			$class_name = $class->getName();
			new $class_name($callback_string);
			exit();
		} catch (ReflectionException $e) {}

		try {

			// attempt to run a function callback
			$function = new ReflectionFunction($callback_string);
			call_user_func($function->getName());
			exit();

		} catch (ReflectionException $e) {}

		self::addMessage(__METHOD__, 'Skipping callback: ' . $callback_string . '. Not a valid method or function.');
		self::triggerContinue();
	}

	/**
	 * Extracts callback params (@*) from a url string
	 *
	 * @param string $url_or_callback_string  A url or callback string from route()
	 * @return array  An array of callback params in stdObject form
	 */
	private static function &extractCallbackParams($url_or_callback_string) 
	{
		$callback_params = array();

		preg_match_all(
			'/(?P<search>@(?P<param>[A-Za-z_][A-Za-z0-9_]*) (\((?P<format>[^\)]*)\))?) /x',
			$url_or_callback_string, 
			$matches 
		);

		foreach($matches['param'] as $key => $name) {
			$callback_param = (object) $name;
			$callback_param->search = $matches['search'][$key];
			$callback_param->name  = '_Moor_'.$name;

			// default format is underscore
			if (!$format = $matches['format'][$key]) {
				$format = 'u';
			}

			switch ($format) {
				case "uc":
					$callback_param->pattern = '[A-Za-z][0-9A-Za-z]*';
					$callback_param->formatter = __CLASS__.'::upperCamelize';
				break;
				case "lc":
					$callback_param->pattern = '[A-Za-z][0-9A-Za-z]*';
					$callback_param->formatter = __CLASS__.'::lowerCamelize';
				break;
				case "u":
					$callback_param->pattern = '[a-z_][0-9a-z_]*';
					$callback_param->formatter = __CLASS__.'::underscorize';
				break;
				default:
					throw new MoorProgrammerException(
						$url_or_callback_string . ' contains invalid formatting rule: ' . $format
					);
			}

			$callback_param->replacement = 
				"(?P<{$callback_param->name}>{$callback_param->pattern})";
				
			$callback_params[$callback_param->scalar] = $callback_param;
		}

		return $callback_params;
	}

	/**
	 * Extracts request params (:*) from a url string
	 *
	 * @param string $url_string  A url string from route()
	 * @return array  An array of request params in stdObject form
	 */
	private static function &extractRequestParams($url_string)
	{
		$request_params = array();

		preg_match_all(
			'/(?P<search>:(?P<param>[A-Za-z_][A-Za-z0-9_]*))(?P<pattern_offset>\()?/x',
			$url_string, 
			$matches, 
			PREG_OFFSET_CAPTURE
		);

		foreach($matches['param'] as $key => $name) {
			$request_param = (object) $name[0];
			$request_param->name = $name[0];
			$request_param->search = ':'.$name[0];
			$pattern = self::$default_param_pattern;

			if (isset($matches['pattern_offset'][$key][1])) {
				// match nested/symmetric parens
				$offset  = $matches['pattern_offset'][$key][1] + 1; 
				$length  = strlen($url_string);
				$parens  = 1;
				$pattern = '(';

				for ($i = $offset; $parens != 0 && $i < $length; $i++) {
					switch($url_string[$i]) {
						case '(': $parens++; break;
						case ')': $parens--; break;
					}
					$pattern .= $url_string[$i];
				}

				if ($parens != 0) {
					throw new MoorProgrammerException(
						'Supplied URL: ' . $url_string . ', contains mismatched request param pattern parenthesis'
					);
				}

				$request_param->search .= $pattern;				
			}

			$request_param->replacement = 
				"(?P<{$request_param->name}>{$pattern})";

			$request_params[$request_param->name] = $request_param;
		}

		return $request_params;
	}

	/**
	 * Gets instance of Moor for chaining methods
	 *
	 * @return void
	 */
	private static function getInstance()
	{
		if (self::$instance) {
			return self::$instance;
		}
		return self::$instance = new self();
	}

	/**
	 * Prepares a route callback to be run by injecting $_GET params into it
	 *
	 * @param object $callback  A callback stdObject
	 * @return string  The injected callback string
	 */
	private static function injectParamsIntoCallback($callback)
	{
		$callback_string = $callback->shorthand;

		foreach($callback->params as $name => $param) {
			if (isset($_GET[$param->name])) {
				$replacement = call_user_func_array($param->formatter, array($_GET[$param->name]));
				$callback_string = str_replace("{:{$param->name}}",	$replacement, $callback_string);
			}
		}

		return $callback_string;
	}

	/**
	 * convert a string to lowerCamelCase
	 *
	 * @param string $string 
	 * @return string  The lowerCamelCase version of the string
	 */
	private static function lowerCamelize($string)
	{
		return self::camelize($string);
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
		
		$callback->pattern   = $callback->scalar;
		$callback->finder    = $callback->scalar;
		$callback->shorthand = $callback->scalar;
		$callback->params    = self::extractCallbackParams($callback_string);

		foreach($callback->params as $param) {
			$callback->pattern   = str_replace($param->search, $param->replacement, $callback->pattern);
			$callback->finder    = str_replace($param->search, '*', $callback->finder);
			$callback->shorthand = str_replace($param->search, '{:'.$param->name.'}', $callback->shorthand);
		}

		$callback->pattern = "/^" . str_replace('\\', '\\\\', $callback->pattern) . "$/";

		return self::$cache[__FUNCTION__.$callback_string] =& $callback;
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

		$url = (object) $url_string;		
		$url->shorthand = trim($url_string);
		$url->pattern   = $url->shorthand;

		// determine whether we should match from beginning
		// to end of the url, or one or the other, or not at all

		$match_start = TRUE;
		$match_end   = TRUE;

		if (isset($url->scalar[0]) && $url->scalar[0] == '*') {
			$match_start = FALSE;
			$url->shorthand = substr($url->shorthand, 1);
		}
		if ($url->scalar[strlen($url->scalar)-1] == '*') {
			$match_end = FALSE;
			$url->shorthand = substr($url->shorthand, 0, -1);
		}

		// parse out callback params with formatting rules

		$url->callback_params = self::extractCallbackParams($url_string);
		$url->request_params  = self::extractRequestParams($url_string);

		foreach($url->callback_params as $param) {
			$url->pattern = str_replace($param->search, $param->replacement, $url->pattern);
		}

		foreach($url->request_params as $param) {
			$url->pattern   = str_replace($param->search, $param->replacement, $url->pattern);
			$url->shorthand = str_replace($param->search, ':'.$param->name, $url->shorthand);
		}

		$url->pattern = ($match_start ? '#^' : '#') . $url->pattern;
		$url->pattern = $url->pattern . ($match_end ? '$#' : '#');

		return $url;
	}

	/**
	 * The default 404 Not Found callback. Prints debug messages if they are on.
	 *
	 * @return void
	 */
	protected static function routeNotFoundCallback() {
		header("HTTP/1.1 404 Not Found");
		echo '<h1>NOT FOUND</h1>';
		echo "\n\n";

		if (self::$debug) {
			echo '<h2>Moor Debug</h2>';
			echo "\n\n";
			echo join("<br />\n", self::$messages);
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
	 * Convert a string to UpperCamelCase
	 *
	 * @param string $string 
	 * @return string  The upperCamelCase version of the string
	 */
	private static function upperCamelize($string)
	{
		return self::camelize($string, TRUE);
	}
}

// ==============
// = Exceptions =
// ==============

class MoorException extends Exception {}
class MoorProgrammerException extends MoorException {}
class MoorContinueException extends MoorException {}
class MoorNotFoundException extends MoorException {}

// ============
// = Includes =
// ============

require 'MoorAbstractController.php';
require 'MoorActionController.php';

// ===========
// = License =
// ===========

// Moor - a routing, linking and controller library for PHP5
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