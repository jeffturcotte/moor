<?php
/**
 * Routing/Controller library for PHP5
 *
 * @copyright  Copyright (c) 2009 Jeff Turcotte
 * @author     Jeff Turcotte [jt] <jeff.turcotte@gmail.com>
 * @license    MIT (see LICENSE)
 * @package    Moor
 * @link       http://github.com/jeffturcotte/moor
 *
 * @version    1.0.0b
 */
class Moor {
	
	/**
	 * Moor options
	 * 
	 * path ............... string: Absolute path to where controller classes can be found
	 * pollute ............ boolean: Whether or not MoorController should create __APP__, __CONTROLLER__, etc. constants.
	 * debug .............. boolean: Display debug messages on default 404 callback. (VERY helpful in learning what Moor does with your URL)
	 * param_app .......... string: The $_GET param to accept as an app (default: 'app')
	 * param_controller ... string: The $_GET param to accept as a controller (default: 'controller')
	 * param_action ....... string: The $_GET param to accept as an action (default: 'action')
	 * callback_404 ....... callback: Callback for when a route is not found upon run()
	 *
	 * @var array
	 */
	protected static $options = array(
		'path' => null,
		'pollute' => true,
		'debug' => false,
		'param_app' => 'app',
		'param_controller' => 'controller',
		'param_action' => 'action',
		'callback_404' => 'Moor::routeNotFoundCallback'
	);
	
	/**
	 * Class specific caching
	 *
	 * @var array
	 */
	public static $cache = array();
	
	/**
	 * The debug messages
	 *
	 * @var array
	 */
	private static $debug_messages = array();
	
	/**
	 * The captured request path
	 *
	 * @var string
	 */
	private static $request_path = null;
	
	/**
	 * The incrementing numeric route key
	 *
	 * @var integer
	 */
	private static $serial = 0;
	
	/**
	 * An array of MoorRoute object indexed by serial
	 *
	 * @var array
	 */
	public static $routes = array();

	/**
	 * An array of route serials indexed by route names
	 *
	 * @var array
	 */
	public static $names  = array();

	/**
	 * An array of route serials indexed by route hashes
	 *
	 * @var array
	 */
	private static $hashes = array();
	
	/**
	 * A cache to minimize lookups on link generation
	 *
	 * @var array
	 */
	private static $location_cache = array();

	// ===========
	// = Methods =
	// ===========
	
	/**
	 * Adds a debug message to the stack
	 *
	 * @param string $class 
	 * @param string $method
	 * @param string $message
	 * @return void
	 **/
	public function addDebugMessage($class, $method, $message)
	{
		array_push(self::$debug_messages, "{$method}  -->  $message");
	}
	
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
	static public function camelize($original, $upper=FALSE)
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
		
		return (self::$cache[$key] = $string);
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
	static public function underscorize($string)
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
		
		return (self::$cache[$key] = $string);
	}
	
	/**
	 * Dispatch a MoorController class from app/controller/action $_GET params
	 *
	 * @return void
	 */
	public static function dispatchController() 
	{
		$param_app = self::$options['param_app'];
		$param_controller = self::$options['param_controller'];
		$param_action = self::$options['param_action'];
	
		if (!isset($_GET[$param_app]) || !isset($_GET[$param_controller]) || !isset($_GET[$param_action])) {
			self::addDebugMessage(__CLASS__, __FUNCTION__, "Continue Routing: Controller requires '{$param_app}', '{$param_controller}' and '{$param_action}' in \$_GET");
			self::triggerContinue();
		}
	
		$app = $_GET[$param_app];
		$controller = $_GET[$param_controller];
		$action = $_GET[$param_action];
	
		$namespace = self::camelize($app, TRUE);
		$class     = self::camelize($controller, TRUE);
		$method    = self::camelize($action);

		$is_5_3    = (strpos(phpversion(), '5.3') === 0);		
		$dir_sep   = DIRECTORY_SEPARATOR;
		$path      = self::$options['path'];

		$class_paths = array();
		
		// look for properly namespaced class if running 5.3
		if ($is_5_3) $class_paths["\\{$namespace}\\{$class}"] = "{$path}{$dir_sep}{$namespace}{$dir_sep}{$class}.php";
		$class_paths["{$namespace}_{$class}"] = "{$path}{$dir_sep}{$namespace}{$dir_sep}{$namespace}_{$class}.php";
		$class_paths["{$namespace}_{$class}"] = "{$path}{$dir_sep}{$namespace}_{$class}.php";
		
		foreach($class_paths as $classname => $path) {
			if (!class_exists($classname, FALSE)) {
				if (!file_exists($path)) {
					self::addDebugMessage(__CLASS__, __FUNCTION__, "Can't find {$classname} at {$path}");
					continue;
				} 
				self::addDebugMessage(__CLASS__, __FUNCTION__, "Found {$classname} at $path");
				include_once $path;
			}
			if (class_exists($classname, FALSE)) {

				// make sure class is extends MoorController
				if (!is_subclass_of($classname, 'MoorController')) {
					self::addDebugMessage(__CLASS__, __FUNCTION__, "Continue Routing: $controller_class doesn't not extend MoorController");
					self::triggerContinue();
				}

				// make sure the action method is public
				if (!in_array($method, get_class_methods($classname))) {
					self::addDebugMessage(__CLASS__, __FUNCTION__, "Continue Routing: Action method '{$method}' is not public");
					self::triggerContinue();
				}
				
				self::addDebugMessage(__CLASS__, __FUNCTION__, "Instantiating {$classname}");
				
				new $classname($method);
			}
		}
		
		self::addDebugMessage(__CLASS__, __FUNCTION__, "Continue Routing: No controller class found");
		self::triggerContinue();
	}
	
	/**
	 * Returns an array of debug messages
	 *
	 * @return array
	 **/
	public function getDebugMessages()
	{
		return self::$debug_messages;
	}
	
	
	/**
	 * Gets the value of an option, defined in self::$options
	 *
	 * @return mixed
	 **/
	public function getOption($name)
	{
		return self::$options[$name];
	}
	
	/**
	 * Find the URL for a controller route
	 *
	 * @param string $app 
	 * @param string $controller 
	 * @param string $action 
	 * @param array $params 
	 * @return void
	 */
	public static function linkTo($callback, $params=array()) 
	{	
		$name = MoorRoute::findCallback($callback);
		
		if (!$name) {
			return '#DEAD';
		}
		
		$route_hash = MoorRoute::hash($name, $params);
				
		if (isset(self::$hashes[$route_hash]) && !isset(self::$location_cache[$route_hash])) {
			self::$location_cache[$route_hash] = self::$routes[self::$hashes[$route_hash]];
		}
			
		if (!isset(self::$location_cache[$route_hash])) {
			
			$best_route_matches = array();
			foreach(self::$routes as $route):
				if ($route->name == $name) {
					array_push($best_route_matches, $route);
				}
			endforeach;
			
			if (count($best_route_matches) == 1) {
				$best_match = array_shift($best_route_matches);
			}
			
			foreach ($best_route_matches as $route):			
				$symbol_count = count($route->shorthand_symbols);
				$param_count  = count($params);
				
				$intersect = count(array_intersect(
					$route->shorthand_params, 
					array_keys($params)
				));
				
				$difference = ($symbol_count > $param_count) 
					? $symbol_count - $param_count 
					: $param_count - $symbol_count;
					
				$better_match = (
					!isset($best_match) ||
					$intersect >= $highest_intersect && 
					$difference > $highest_difference ||
					$intersect > $highest_intersect
				);
	
				if ($better_match) {
					$highest_intersect = $intersect;
					$highest_difference = $difference;
					$best_match = $route;
				}
			endforeach;	
			
			self::$location_cache[$route_hash] = $best_match;
		}
		
		$route  = self::$location_cache[$route_hash];
		$params = array_diff_key($params, $route->overrides);
		#$params = array_diff_key($params, MoorRoute::parseRoute)
		
		$url = $route->shorthand;

		foreach($route->shorthand_symbols as $key => $symbol) {
			if (isset($params[$route->shorthand_params[$key]])) {
				$url = str_replace($symbol, $params[$route->shorthand_params[$key]], $url);
				unset($params[$route->shorthand_params[$key]]);
			}
		}
	
		if (!empty($params)) {
			$url .= '?' . http_build_query($params);
		}
		
		return $url;
	}
	
	/**
	 * Find the URL for a callback route
	 *
	 * @param string $name    The name of the route
	 * @param string $params  The params to pass to the location
	 * @return string         The URL
	 */
	public static function linkToCallback($name, $params=array()) 
	{
		if (!isset(self::$names[$name])) {
			return false;
		}
		
		$route_hash = MoorRoute::hash($name, $params);
				
		if (isset(self::$hashes[$route_hash]) && !isset(self::$location_cache[$route_hash])) {
			self::$location_cache[$route_hash] = self::$routes[self::$hashes[$route_hash]];
		}
			
		if (!isset(self::$location_cache[$route_hash])) {
			
			$best_route_matches = array();
			foreach(self::$routes as $route):
				if ($route->name == $name) {
					array_push($best_route_matches, $route);
				}
			endforeach;
			
			if (count($best_route_matches) == 1) {
				$best_match = array_shift($best_route_matches);
			}
			
			foreach ($best_route_matches as $route):			
				$symbol_count = count($route->shorthand_symbols);
				$param_count  = count($params);
				
				$intersect = count(array_intersect(
					$route->shorthand_params, 
					array_keys($params)
				));
				
				$difference = ($symbol_count > $param_count) 
					? $symbol_count - $param_count 
					: $param_count - $symbol_count;
					
				$better_match = (
					!isset($best_match) ||
					$intersect >= $highest_intersect && 
					$difference > $highest_difference ||
					$intersect > $highest_intersect
				);
	
				if ($better_match) {
					$highest_intersect = $intersect;
					$highest_difference = $difference;
					$best_match = $route;
				}
			endforeach;	
			
			self::$location_cache[$route_hash] = $best_match;
		}
		
		$route  = self::$location_cache[$route_hash];
		$params = array_diff_key($params, $route->overrides);
		
		$url = $route->shorthand;

		foreach($route->shorthand_symbols as $key => $symbol) {
			if (isset($params[$route->shorthand_params[$key]])) {
				$url = str_replace($symbol, $params[$route->shorthand_params[$key]], $url);
				unset($params[$route->shorthand_params[$key]]);
			}
		}
	
		if (!empty($params)) {
			$url .= '?' . http_build_query($params);
		}
		
		return $url;
	}
	
	/**
	 * The default callback when a route cannot be found
	 *
	 * @return void
	 */
	public static function routeNotFoundCallback()
	{
		header("HTTP/1.1 404 Not Found");
		echo '<h1>NOT FOUND</h1>';
		echo "\n\n";
		
		if (self::getOption('debug')) {
			echo '<h2>Moor Debug</h2>';
			echo "\n\n";
			echo join("\n", Moor::getDebugMessages());
		}
	}

	/**
	 * define a controller route
	 *
	 * === $definition ===
	 *
	 * can be a shorthand url string:
	 *     '/users/:id/:slug'
	 * 
	 * or a regular expression using named parameters and # as the delimeter:
	 *     '#/users/(?P<id>\d+)/(?P<slug>[a-z_]+)#'
	 *
	 * @param string|regex $definition  A shorthand route definition or a full regular expression
	 * @param string $app               The app param override 
	 * @param string $controller        The controller param override
	 * @param string $action            The action param override
	 * @return void
	 */
	public static function routeTo($definition, $name, $callback=null) 
	{
		// just in case anyone gets too clever 
		// with the name/callback override
		if (!is_string($name)) {
			throw new MoorProgrammerException(
				'The name of your route must be a string.'
			);
		}
		
		if (is_null($callback)) {
			$callback = $name;
		}
		
		$route = new MoorRoute(
			self::$serial, $name, $definition, $callback
		);
	
		self::$routes[$route->serial] = $route;
		self::$names[$route->name] = self::$serial;
		self::$hashes[$route->hash] = self::$serial;
		self::$serial++;
	}
	
	/**
	 * Run all define routes. Exits upon completion.
	 *
	 * @return void
	 */
	public static function run() 
	{
		//self::addDebugMessage(__CLASS__, __FUNCTION__, 'Routing started');
		
		// transact GET so any variables 
		// we set can be rolled back.
		$old_GET = $_GET;
		$_GET = array();
		
		self::$request_path = preg_replace('#\?.*$#', '', $_SERVER['REQUEST_URI']);

		foreach(self::$routes as $route):
			// reset GET
			$_GET = $old_GET;

			try {
				if (!preg_match($route->expression, self::$request_path, $matches)) {
					continue;
				}
				
				foreach($matches as $name => $param):
					if (is_string($name)) {
						$_GET[$name] = $param;
					}
				endforeach;
				
				$_GET = array_merge($_GET, $route->overrides);
				
				$route->dispatch();
				exit();
				
			} catch (MoorContinueException $e) {
				continue;
			} catch (MoorNotFoundException $e) {
				break;
			}
		endforeach;
		
		call_user_func(self::$options['callback_404']);
		exit();
	}
	
	/**
	 * undocumented 
	 *
	 */
	public static function splitCallback($callback)
	{
		$split     = explode('\\', $callback);
		$function  = array_pop($split);
		$namespace = implode('\\', $split);

		if (strpos($function, '::') !== FALSE) {
			$split  = explode('::', $function);
			$class  = $split[0];
			$method = $split[1];
			$function = NULL;
		}
		
		return array(
			'namespace' => $namespace,
			'function' => $function,
			'class' => $class,
			'method' => $method
		);
	}
	
	/**
	 * Sets an option in self::$options
	 *
	 * @param string|array $name    Either an option name, or an array of option [names => values]
	 * @param mixed        $option  The option value
	 * @return void
	 **/
	public static function setOption($name, $option=null)
	{
		if (is_array($name)) {
			array_merge(self::$options, $name);
		} else {
			self::$options[$name] = $option;
		}
	}
	
	/**
	 * Throws a MoorContinueException which moves router to the next route
	 *
	 * @throws MoorContinueException
	 **/
	public static function triggerContinue()
	{
		throw new MoorContinueException();
	}
	
	/**
	 * Throws a MoorNotFoundException which halts routing and sends a 404
	 *
	 * @throws MoorNotFoundException
	 **/
	public static function triggerNotFound()
	{
		throw new MoorNotFoundException();
	}
}

class MoorNotFoundException extends Exception {}
class MoorContinueException extends Exception {}
class MoorProgrammerException extends Exception {}