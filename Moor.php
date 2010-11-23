<?php
/**
 * Moor is a URL Routing/Linking/Controller library for PHP 5
 *
 * @copyright  Copyright (c) 2010 Jeff Turcotte, others
 * @author     Jeff Turcotte [jt] <jeff.turcotte@gmail.com>
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    MIT (see LICENSE or bottom of this file)
 * @package    Moor
 * @link       http://github.com/jeffturcotte/moor
 * @version    1.0.0b7
 */
class Moor {
	/**
	 * The currently running callback
	 *
	 * @var string
	 */
	private static $active_callback = NULL;
	
	/**
	 * The currently running namespace
	 *
	 * @var string
	 */
	private static $active_namespace = NULL;

	/**
	 * The currently running class (w/ namespace)
	 *
	 * @var string
	 */
	private static $active_class = NULL;

	/**
	 * The currently running class (w/o namespace)
	 *
	 * @var string
	 */
	private static $active_short_class = NULL;

	/**
	 * The currently running method (w/ namespace & class)
	 *
	 * @var string
	 */
	private static $active_method = NULL;
	
	/**
	 * The currently running method (w/o namespace or class)
	 *
	 * @var string
	 */
	private static $active_short_method = NULL;
	
	/**
	 * The currently running function
	 *
	 * @var string
	 */
	private static $active_function = NULL;

	/**
	 * The camelize() cache
	 *
	 * @var array
	 */
	private static $camelize = array();

	/**
	 * Default pattern to match :id params in incoming urls
	 *
	 * @var string
	 */
	private static $default_request_param_pattern = '[A-Za-z0-9_]+';

	/**
	 * Whether or not to show debug messages on default 404 page
	 *
	 * @var boolean
	 **/
	private static $debug = FALSE;

	/**
	 * Wether or not to cache with APC
	 *
	 * @var boolean
	 **/
	private static $enable_cache = FALSE;
	
	/**
	 * The current more instance, only used for chaining
	 *
	 * @var object Moor
	 */ 
	private static $instance = NULL;

	/**
	 * The linkTo() cache
	 *
	 * @var array
	 */
	private static $link_to = array();

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
	 * The paramsTo() cache
	 *
	 * @var array
	 */
	private static $params_to = array();

	/**
	 * The request path
	 *
	 * @var string
	 **/
	private static $request_path = NULL;

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
	 * The underscore() cache
	 *
	 * @var array
	 */
	private static $underscorize = array();
	
	/**
	 * The prefix to add to $url_string when calling ::route()
	 * 
	 * @var string
	 */
	private static $url_prefix = '';


	// ==============
	// = Public API =
	// ==============

	/**
	 * Enable or disable the debugging
	 *
	 * @return object  The Moor instance for chaining
	 **/
	public static function enableDebug()
	{
		self::$debug = TRUE;
		return self::getInstance();
		
	}
	
	/**
	 * Find the URL where a particular callback lives
	 *
	 * @param  string $callback_string The callback to search for
	 * @param  string $params          The GET params to send
	 * @return string The URL
	 */
	public static function linkTo($key)
	{
		$param_values    = func_get_args();
		$callback_string = array_shift($param_values);
		$callback_string = trim($callback_string);
		$callback_string = self::expandCallback($callback_string);
		
		if (!isset(self::$link_to[$key])) {
			//self::$cache_for_linkTo[$key] = FALSE;
			$best_route = NULL;
			$param_names = preg_split('/(\s+:)|(\s+)|((?<!:):(?!:))/', $callback_string);
			$callback_string = array_shift($param_names);
			$param_names_flipped = array_flip($param_names);
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
					$diff = count(array_diff(array_keys($route->url->request_params), $param_names));
					$sect = count(array_intersect_key(array_flip(array_keys($route->url->request_params)), $param_names_flipped));
					
					$is_best = (
						$best_route === NULL ||
						$low_dist > $dist ||
						$low_dist == $dist && $top_accu < $accu ||
						$low_dist == $dist && $top_accu == $accu && $top_sect < $sect ||
						$low_dist == $dist && $top_accu == $accu && $top_sect <= $sect && $low_diff > $diff 
					);
					
					if ($is_best) {
						$best_route = $route;
						$low_dist = $dist;
						$top_accu = $accu;
						$top_sect = $sect;
						$low_diff = $diff;
					}
				}
			}
			
			if (!$best_route) {
				throw new MoorProgrammerException('No link could be found for the callback ' . $callback_string);
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
							self::compat($callback_param->formatter),
							array($callback_params[$callback_param->name])
						),
						$url
					);
				}
				
				$cache->url = $url;
				
				self::$link_to[$key] = $cache;
			}
		}
		
		$cache =& self::$link_to[$key];
		
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
			$url = str_replace(':'.$name, urlencode($value), $url);
		}
		
		foreach($excluded_params as $name => $value) {
			$excluded_params[$name] = $value;
		}
		
		if (!empty($excluded_params)) {
			$url .= '?' . http_build_query($excluded_params);
		}
		
		return $url;
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
	 * Returns the callback for the currently running route
	 *
	 * @return string
	 */
	public static function getActiveCallback()
	{
		return self::$active_callback;
	}
	
	/**
	 * Returns the class (w/ namespace for the currently running route
	 *
	 * @return string
	 **/
	public static function getActiveClass()
	{
		return self::$active_class;
	}
	
	/**
	 * Returns the path for the currently running route's class
	 *
	 * @return string
	 */
	public static function getActiveClassPath()
	{
		return self::pathTo(self::getActiveClass());
	}
	
	/**
	 * Returns the function for the currently running route
	 *
	 * @return string
	 */
	public static function getActiveFunction()
	{
		return self::$active_function;
	}

	/**
	 * Returns the method (w/ class and namespace) for the currently running route
	 *
	 * @return string
	 */
	public static function getActiveMethod()
	{
		return self::$active_method;
	}

	/**
	 * Returns the namespace for the currently running route
	 *
	 * @return string
	 */
	public static function getActiveNamespace()
	{
		return self::$active_namespace;
	}

	/**
	 * Returns the short class name (class w/o namespace) for the currently running route
	 *
	 * @return string
	 */
	public static function getActiveShortClass()
	{
		return self::$active_short_class;
	}

	/**
	 * Returns the short method (method w/o class or namespace) for the currently running route
	 *
	 * @return string
	 */
	public static function getActiveShortMethod()
	{
		return self::$active_short_method;
	}

	/**
	 * Returns the path for the currently running route
	 *
	 * @return string
	 */
	public static function getActivePath()
	{
		return self::pathTo(self::getActiveCallback());
	}
	
	/**
	 * Get the path to the supplied callback
	 *
	 * @param string $callback 
	 * @param string $directory_separator
	 * @return void
	 */
	public static function pathTo($callback, $directory_separator=NULL)
	{
		$string = self::expandCallback($callback);
		return self::makePath($string, $directory_separator);
	}
	
	/**
	 * Find the params to a particular callback. Will return an array of arrays.
	 * one per router with valid param of the route's URL.
	 *
	 * @param string $callback The callback to search for
	 * @return array The params
	 */
	public static function paramsTo($callback)
	{
		$callback = self::expandCallback($callback);
		
		if (isset(self::$params_to[$callback])) {
			return self::$params_to[$callback];
		}
		
		$params = array();

		foreach(self::$routes as $route) {
			if (preg_match($route->callback->pattern, $callback)) {
				$route_params = array();
				
				foreach($route->url->request_params as $name => $request_param) {
					array_push($route_params, $name);
				}
				
				array_push($params, $route_params);
			}
		}
		
		self::$params_to[$callback] = $params;
		return self::$params_to[$callback];
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
		// reset caches using routes
		self::$link_to = array();
		self::$params_to = array();
		
		if (self::$running == TRUE) {
			throw new MoorProgrammerException(
				'No new routes can be added once routing has been started.'
			);
		}
		
		$url_string = self::$url_prefix . $url_string;
		
		if ($callback_string instanceof Closure) {
			$function = $callback_string;
			$callback_string = '';
		}
		
		$route = (object) 'route';
		$route->url      = self::parseUrl($url_string);
		$route->callback = self::parseCallback($callback_string);
		$route->function = $function;
		
		// validate that the url and callback use the same callback params
		
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
		self::$request_path = urldecode(preg_replace('#\?.*$#', '', $_SERVER['REQUEST_URI']));
		
		$old_GET = $_GET;
		$_GET = array();
		
		foreach(self::$routes as $route) {
			self::$active_callback = NULL;
			self::$active_namespace = NULL;
			self::$active_class = NULL;
			self::$active_short_class = NULL;
			self::$active_method = NULL;
			self::$active_short_method = NULL;
			self::$active_function = NULL;
			
			$_GET = $old_GET;
			
			try {
				self::dispatchRoute($route);
			} catch (MoorContinueException $e) {
				continue;
			} catch (MoorNotFoundException $e) {
				break;
			}
		}
		
		self::$messages[] = 'No Valid Matches Found. Running Not Found callback: ' . self::$not_found_callback;
		
		call_user_func(self::compat(self::$not_found_callback));
		exit();
	}
	
	/**
	 * Sets the pattern that will match a URL request param if no pattern is given
	 *
	 * @param string $pattern  The regular expression to match a request param in the URL
	 * @return object  The Moor instance for chaining
	 **/
	public static function setRequestParamPattern($pattern)
	{
		self::$default_request_param_pattern = $pattern;
		return self::getInstance();
	}

	/**
	 * Set the callback for when a route is not found.
	 *
	 * @param string $callback  The static method or function callback for 404s
	 * @return object  The Moor instance for chaining
	 **/
	public static function setNotFoundCallback($callback)
	{
		self::$not_found_callback = $callback;
		return self::getInstance();
	}
	
	/**
	 * Sets a string to prefix to $url_string when calling ::route()
	 *
	 * @param string $prefix  The string to prefix
	 * @return object  The Moor instance for chaining
	 **/
	public static function setUrlPrefix($prefix)
	{
		self::$url_prefix = $prefix;
		return self::getInstance();
	}

	/**
	 * Triggers skipping the current route and moving to the next one.
	 *
	 * @return void
	 */
	public static function triggerContinue()
	{
		throw new MoorContinueException();
	}
	
	/**
	 * Triggers the not found callback when the router is running.
	 *
	 * @return void
	 */
	public static function triggerNotFound()
	{
		throw new MoorNotFoundException();
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
	 * Derived from MIT fGrammer::camelize
	 * Source: http://flourishlib.com/browser/fGrammar.php
	 *
	 * Copyright (c) 2007-2009 Will Bond <will@flourishlib.com>
	 * 
	 * Permission is hereby granted, free of charge, to any person obtaining a copy
	 * of this software and associated documentation files (the "Software"), to deal
	 * in the Software without restriction, including without limitation the rights
	 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	 * copies of the Software, and to permit persons to whom the Software is
	 * furnished to do so, subject to the following conditions:
	 * 
	 * The above copyright notice and this permission notice shall be included in
	 * all copies or substantial portions of the Software.
	 * 
	 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	 * THE SOFTWARE.	
	 *
	 * @param  string  $original The string to convert
	 * @param  boolean $upper    If the camel case should be `UpperCamelCase`
	 * @return string  The converted string
	 */
	private static function &camelize($original, $upper=FALSE)
	{
		$upper = (int) $upper;
		$key   = "{$upper}/{$original}";
		
		if (isset(self::$camelize[$key])) {
			return self::$camelize[$key];
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
		
		self::$camelize[$key] =& $string;
		return $string;
	}

	/**
	 * Provides a compatibility layer for PHP 5.2 style static callbacks to
	 * work with PHP 5.1.
	 * 
	 * @param callback $callback  The callback to make compatible
	 * @return callback  A callback that is compatibile with PHP 5.1
	 */
	private static function compat($callback)
	{
		if (is_string($callback) && strpos($callback, '::') !== FALSE) {
			$callback = explode('::', $callback);
		}
		return $callback;
	}
	
	/**
	 * Dispatch a callback
	 * 
	 * @param object $route a route stdObject
	 * @return void
	 **/
	private static function dispatchRoute($route)
	{
		if (!preg_match($route->url->pattern, self::$request_path, $matches)) {
			return FALSE;
		}
		
		self::$messages[] = 'Match. Request path ' . self::$request_path . ' matched URL definition "' . $route->url->scalar . '"';
		
		foreach($matches as $name => $param) {
			if (is_string($name)) {
				$_GET[$name] = urldecode($param);
			}
		}
		
		$callback_string = self::injectParamsIntoCallback($route->callback);
		
		self::$messages[] = 'Generated Callback: ' . $callback_string;
		
		self::$active_callback = $callback_string;
		
		// dispatch closure
		if ($route->function instanceof Closure) {
			self::$active_function = $callback_string;
			self::$messages[] = 'Calling assigned closure';
			call_user_func(self::compat($route->function));
			exit();
			
		// dispatch function
		} else if (function_exists($callback_string)) {
			// disallow dangerous functions
			if (preg_match('/^[\*_\\\\]+$/', $route->callback->finder)) {
				self::$messages[] = 'Skipping callback ' . $callback_string . ': Callback definition is dangerous.';
				self::triggerContinue();
			}
			
			$function = new ReflectionFunction($callback_string);
			
			if (method_exists($function, 'getNamespaceName')) {
				self::$active_namespace = $function->getNamespaceName();
			}
			self::$active_function  = $callback_string;
			
			self::$messages[] = 'Calling function: ' . $callback_string;
			call_user_func($callback_string);
			exit();
			
		// dispatch method
		} else {
			self::validateMethodCallback($callback_string);
			$method = new ReflectionMethod($callback_string);
			
			$class = self::compat($callback_string);
			$class = $class[0];
			
			$parsed_class = self::parseClass($class);
			
			self::$active_method = $callback_string;
			self::$active_short_method = $method->getName();
			self::$active_class = $class;
			self::$active_short_class = $parsed_class['short_class'];
			self::$active_namespace = $parsed_class['namespace'];
			
			if ($method->isStatic()) {
				self::$messages[] = 'Calling static method: ' . $callback_string;
				call_user_func(self::compat($callback_string));
				exit();
			} else {
				self::$messages[] = 'Instantiating class for ' . $callback_string;
				new $class();
				exit();
			}
		}
		
		self::$messages[] = 'Skipping callback: ' . $callback_string . '. Not a valid method or function.';
		self::triggerContinue();
	}

	/**
	 * Expands a callback starting with *:: or *\ or *_ to include the active class/namespace
	 *
	 * @param string  $callback 
	 * @return string The expanded callback
	 */
	private static function expandCallback($callback) {
		$string = $callback;
		
		if (strpos($callback, '*::') === 0) {
			$string = self::getActiveClass() . substr($callback, 1);
		}
		
		if (strpos($callback, '*\\') === 0 || preg_match('/^\*_[A-Z][A-Za-z0-9]*::/', $callback)) {
			$string = self::getActiveNamespace() . substr($callback, 1);
		}
		
		return $string;
	}

	/**
	 * Extracts callback params (@*) from a url string
	 *
	 * @param string $string  A url or callback string from route()
	 * @return array  An array of callback params in stdObject form
	 */
	private static function &extractCallbackParams($string) 
	{
		$callback_params = array();
		
		preg_match_all(
			'/{?(?P<param>@
				(?P<name>[A-Za-z]([A-Za-z]|(_(?!@)))*)
				(\((?P<format>[A_Z0-9a-z-_]+)\))?
			)}?/x',
			$string,
			$matches
		);
		
		$validator = $string;
		
		foreach($matches['param'] as $i => $param) {
			$name   = '_Moor_'.$matches['name'][$i];
			
			// if no format, default format to u
			$format = $matches['format'][$i] ? $matches['format'][$i] : 'u';
			
			switch ($format) {
				// UpperCamelCaseFormat
				case "uc":
					$pattern = '[A-Z][0-9A-Za-z]*';
					$formatter = __CLASS__.'::upperCamelize';
					break;
				// lowerCamelCaseFormat
				case "lc":
					$pattern = '[a-z][0-9A-Za-z]*';
					$formatter = __CLASS__.'::lowerCamelize';
					break;
				// underscore_format
				case "u":
					$pattern = '[a-z_][0-9a-z_]*';
					$formatter = __CLASS__.'::underscorize';
					break;
				// invalid format
				default:
					throw new MoorProgrammerException(
						$string . ' contains invalid formatting rule: ' . $format
					);
			}
			
			$callback_param              = (object) $name;
			$callback_param->search      = $param;
			$callback_param->name        = $name;
			$callback_param->pattern     = $pattern;
			$callback_param->formatter   = $formatter;
			$callback_param->replacement = "(?P<{$name}>{$pattern})";
			$callback_params[$name]      = $callback_param;
						
			$validator = str_replace($param, '%@'.$format.'%', $validator);
		}
		
		// check for invalid callback param juxtapositions 
		// that can't be used for routing/linking
		$invalid_patterns = array();
		
		$invalid_patterns['/(%@u%%@lc%)/'] = 
			'an underscore param directly before lowerCamelCase param';
		
		$invalid_patterns['/(((%@lc%)|(%@uc%))%@u%)/'] = 
			'an underscore param directly after UpperCamelCase param or lowerCamelCase param';
		
		$invalid_patterns['/(%@u%_?%@u%)/'] = 
			'directly juxtaposed underscore params or underscore params joined by an underscore character';
		
		$invalid_patterns['/((%@lc%%@uc%)|(%@uc%%@lc%))/'] = 
			'directly juxtaposed lowerCamelCase and/or UpperCamelCase params';
		
		foreach($invalid_patterns as $pattern => $message) {
			if (preg_match($pattern, $validator)) {
				throw new MoorProgrammerException(
					"Unroutable/Unlinkable callback params. {$string} contains {$message}"
				);
			}
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
			$pattern = self::$default_request_param_pattern;
			
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
				$replacement = call_user_func_array(self::compat($param->formatter), array($_GET[$param->name]));
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
	 * Makes a path out of a callback string.
	 *
	 * @param string $callback_string 
	 * @return string  The path created from the callback
	 */
	private static function makePath($callback_string, $ds=NULL)
	{
		$ds = ($ds === NULL) ? DIRECTORY_SEPARATOR : $ds;
		
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
	 * Reads a callback string and converts to a callback object
	 *
	 * @param  string $callback_string The callback
	 * @return object The callback object
	 */
	private static function &parseCallback($callback_string)
	{
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
		
		return $callback;
	}
	
	/**
	 * Parses a class into a namespace and short class name
	 * 5.3 can do this with reflection, but this method works for 5.3 
	 * style \namespaces and 5.2 Style_Namespaces
	 *
	 * @param string class  The class to parse
	 * @return array  An array of the namespace and short class name
	 */
	private static function parseClass($class)
	{
		$namespace = NULL;
		$short_class = $class;
		
		if (strpos($class, '\\') !== FALSE) {
			preg_match('/(?P<namespace>.*)\\\\(?P<short_class>)[a-zA-Z][a-zA-Z0-9]*)$/', $class, $matches);
			$namespace  = (isset($matches['namespace']))  ? $matches['namespace']  : NULL;
			$short_class = (isset($matches['short_class'])) ? $matches['short_class'] : NULL;
		} else if (strpos($class, '_') !== FALSE) {
			preg_match('/(?P<namespace>.*)_(?P<short_class>[A-Z][A-Za-z0-9]*)$/', $class, $matches);
			$namespace  = (isset($matches['namespace']))  ? $matches['namespace']  : NULL;
			$short_class = (isset($matches['short_class'])) ? $matches['short_class'] : NULL;
		}
		
		return array(
			'namespace' => $namespace,
			'short_class' => $short_class
		);
	}

	/**
	 * Reads a URL string and converts to a URL object
	 *
	 * @param  string $url_string    The URL string (either shorthand or a regular expression)
	 * @return object The URL object
	 */
	private static function &parseUrl($url_string)
	{
		$url = (object) $url_string;
		$url->shorthand = trim($url_string);
		$url->pattern   = str_replace('#', '\\#', $url->shorthand);
		
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
	protected static function routeNotFoundCallback()
	{
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
	 * Derived from MIT fGrammer::camelize
	 * Source: http://flourishlib.com/browser/fGrammar.php
	 *
	 * Copyright (c) 2007-2009 Will Bond <will@flourishlib.com>
	 * 
	 * Permission is hereby granted, free of charge, to any person obtaining a copy
	 * of this software and associated documentation files (the "Software"), to deal
	 * in the Software without restriction, including without limitation the rights
	 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	 * copies of the Software, and to permit persons to whom the Software is
	 * furnished to do so, subject to the following conditions:
	 * 
	 * The above copyright notice and this permission notice shall be included in
	 * all copies or substantial portions of the Software.
	 * 
	 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	 * THE SOFTWARE.
	 *
	 * @param  string $string  The string to convert
	 * @return string  The converted string
	 */
	private static function &underscorize($string)
	{
		$key = $string;
		
		if (isset(self::$underscorize[$key])) {
			return self::$underscorize[$key];
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
		
		self::$underscorize[$key] =& $string;
		return $string;
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
	
	/**
	 * Validates that a method callback can be dispatched
	 *
	 * @param string $callback  the callback string
	 * @return void
	 */
	private static function validateMethodCallback($callback)
	{
		try {
			$method = new ReflectionMethod($callback);
			$class = $method->getDeclaringClass();
		} catch (ReflectionException $e) {
			self::$messages[] = 'Continue. Method ' . $callback . ' doesn\'t exist.';
			self::triggerContinue();
		}
		
		if (!$class->isSubclassOf('MoorAbstractController')) {
			self::$messages[] = 'Continue. Class for method ' . $callback . '. isn\'t a subclass of MoorAbstractController.';
			self::triggerContinue();
		}
		
		if (strpos($method->getName(), '__') === 0) {
			self::$messages[] = 'Continue. Method ' . $callback . ' looks like magic method.';
			self::triggerContinue();
		}
		
		if (!$method->isPublic()) {
			self::$messages[] = 'Continue. Method ' . $callback . ' isn\'t public.';
			self::triggerContinue();
		}
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

if (!class_exists('MoorAbtractController', FALSE)) {
	require 'MoorAbstractController.php';
}
if (!class_exists('MoorActionController', FALSE)) {
	require 'MoorActionController.php';
}

// ===========
// = License =
// ===========

// Moor - a routing, linking and controller library for PHP5
// 
// Copyright (c) 2010 Jeff Turcotte, others
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