<?php
/**
 * Moor - PHP 5 Routing/Controller Library
 *
 * Copyright (c) 2009 Jeff Turcotte
 *
 * @author  Jeff Turcotte
 * @license MIT (see LICENSE)
 * @version 0.2
 *
 * See README
 */

class Moor {
	/**
	 * Moor options
	 *
	 * @var array
	 **/
	private static $options = array(
		'debug' => true,
		'pollute' => true,
		'on_not_found' => 'Moor::routeNotFoundCallback'
	);
	
	/**
	 * Debug messages
	 *
	 * @var array
	 **/
	private static $messages = array();
	
	/**
	 * Mounts to match
	 *
	 * @var array
	 **/
	private static $mounts = array();
	
	/**
	 * Routes to match
	 *
	 * @var array
	 **/
	private static $routes = array();
	
	/**
	 * The parameters captured from a URL match
	 *
	 * @var array
	 **/
	private static $params = array();
	
	/**
	 * The captured request path
	 *
	 * @var string
	 **/
	private static $request_path = null;
	
	/**
	 * The matched mount name
	 *
	 * @var string
	 **/
	private static $current_mount = null;
	
	/**
	 * Adds a named mounting callback to be matched on `mount`
	 *
	 * @param string $name 
	 * @param string $static_url_matcher 
	 * @param string $callback 
	 * @param array  $args 
	 * @return void
	 */
	public static function addMount($name, $path, $callback, $args=array())
	{
		if (strpos($path, '/') !== 0) {
			self::addMessage(
				__CLASS__, 
				__FUNCTION__, 
				"Path {$path} skipped. Doesn't start with forward-slash."
			);
			return;
		}
		
		self::$mounts[$name] = array(
			'path' => $path,
			'callback' => $callback,
			'args' => $args
		);
	}
	
	/**
	 * Iterate and match a mount to the request path
	 *
	 * @return void
	 **/
	public static function mount()
	{
		self::captureRequestPath();
		
		foreach(self::$mounts as $name => $mount) {
			if (strpos(self::$request_path, $mount['path']) === 0) {
				self::$request_path = substr(self::$request_path, strlen($mount['path']));
				
				// fix slashes
				if (strpos(self::$request_path, '/') !== 0) {
					self::$request_path = '/' . self::$request_path;
				}
				
				self::$current_mount = $name;

				call_user_func_array($mount['callback'], $mount['args']);
				exit();
			}
		}
		
		call_user_func(self::$options['on_not_found']);
		exit();
	}
	
	/**
	 * Get the mount path for the specified mount
	 *
	 * @return string The mount path
	 **/
	public static function getMountPath($name)
	{
		return self::$mounts[$name]['path'];
	}
	
	/**
	 * Gets the matched and current mount path
	 *
	 * @return mixed The current mount path if one exists, or null
	 **/
	public static function getCurrentMountPath()
	{
		if (self::$current_mount) {
			return self::getMountPath(self::$current_mount);
		}
		return null;
	}
	
	/**
	 * Adds a routing callback to be matched on `route`
	 *
	 * @param mixed  $matcher 
	 * @param string $callback 
	 * @param array  $args 
	 * @return void
	 */
	public static function addRoute($matcher, $callback, $args=array())
	{
		if (!is_array($matcher) && strpos($matcher, '/') !== 0) {
			self::addMessage(
				__CLASS__, 
				__FUNCTION__, 
				"Matcher {$matcher} skipped. Doesn't start with forward-slash."
			);
			return;
		}
		
		self::$routes[] = array(
			'matcher' => $matcher,
			'callback' => $callback,
			'args' => $args
		);
	}
	
	/**
	 * Iterate and match a route to the request path
	 *
	 * @return void
	 **/
	public static function route() {
		self::addMessage(
			__CLASS__,
			__FUNCTION__,
			"Starting"
		);
		
		self::captureRequestPath();
		
		foreach(self::$routes as $route) {
			$url_matcher = $route['matcher'];
			
			try {
				self::resetParams();
				self::createStandardURLMatcher($url_matcher);
				
				if (!preg_match($url_matcher[0], self::$request_path, $matches)) {
					self::addMessage(
						__CLASS__,
						__FUNCTION__,
						"{$url_matcher[0]} didn't match requested URI"
					);
				
					continue;
				}
				
				self::addMessage(
					__CLASS__,
					__FUNCTION__,
					"{$url_matcher[0]} matched requested URI. Calling " . $route['callback'] . '.'
				);
				
				$matches_count = count($matches);
				for ($n = 1; $n < $matches_count; $n++) {
					if (isset($url_matcher[$n])) { 
						self::$params[$url_matcher[$n]] = $matches[$n]; 
					}
				}
				
				if (self::$options['pollute']) {
					$_GET = array_merge($_GET, self::$params);
				}
				
				call_user_func_array($route['callback'], $route['args']); 
				exit();

			} catch (MoorContinueException $e) {
				continue;
			} catch (MoorNotFoundException $e) {
				break;
			}
		}
			
		self::addMessage(
			__CLASS__,
			__FUNCTION__,
			"Calling `on_not_found` callback"
		);
			
		call_user_func(self::$options['on_not_found']);
	}
	
	/**
	 * Throws a MoorNotFoundException which halts routing and sends a 404
	 *
	 * @return void
	 **/
	public static function triggerNotFound()
	{
		throw new MoorNotFoundException();
	}
	
	/**
	 * Throws a MoorContinueException which moves router to the next route
	 *
	 * @return void
	 **/
	public static function triggerContinue()
	{
		throw new MoorContinueException();
	}
	
	/**
	* Removes any previously captured URL parameters
	*
	* @return void
	**/
	private static function resetParams()
	{
		self::$params = array();
	}
	
	/**
	 * Get a param from the captured URL params
	 *
	 * @param string $name The name of the param
	 * @return mixed The param value
	 */
	public static function getParam($name)
	{
		if (isset(self::$params[$name])) {
			return self::$params[$name];
		} 
		return null;
	}
	
	/**
	 * Captures the request path from $_SERVER['REQUEST_URI'] for internal use
	 *
	 * @return void
	 */
	private static function captureRequestPath() 
	{
	    if (self::$request_path === null) {
			self::$request_path = preg_replace('#\?.*$#', '', $_SERVER['REQUEST_URI']);
	    }
	}
	
	/**
	 * Sets the request path
	 *
	 * @return void
	 **/
	public static function setRequestPath($path)
	{
		self::$request_path = $path;
	}
	
	/**
	 * Creates a standard array-based 
	 *
	 * @return void
	 **/
	private static function createStandardURLMatcher(&$url_matcher)
	{
		if (!is_array($url_matcher)) {
			$url_regex = preg_replace('/:[a-zA-Z_]+/', '([0-9a-zA-Z_-]+)', $url_matcher);
		
			$url_regex = str_replace(
				array("&",  ",",  "/",  ":",  ";",  "=",  "?",  "@",  "."),
				array('\&', '\,', '\/', '\:', '\;', '\=', '\?', '\@', "\."),
				$url_regex
			);
			
			$standard_matcher = array("/^{$url_regex}/");        
			
			preg_match_all('/[:]([a-zA-Z_]+)/', $url_matcher, $matches);
			
			$url_matcher = isset($matches[1]) 
				? array_merge($standard_matcher, $matches[1]) 
				: $standard_matcher;
		}
	}	
	
	/**
	 * Add a message to the stack for debugging
	 *
	 * @param string $class 	
	 * @param string $function 
	 * @param string $message 
	 * @return void
	 */
	public static function addMessage($class, $function, $message) 
	{
		self::$messages[] = "<span class=\"method\">{$class}::{$function}:</span> {$message}";
	}
	
	/**
	 * Gets all messages
	 *
	 * @return void
	 **/
	public static function getMessages()
	{
		return self::$messages;
	}
	
	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	public static function routeNotFoundCallback()
	{
		echo '<h1>404 Not Found</h1>';
		
		if (Moor::$options['debug'] == true) {
			echo "\n<h2>Moor Debug Messages</h2>\n\n";
			echo "<ul>\n";
			foreach(self::getMessages() as $message) {
				echo "\t<li>{$message}</li>\n";
			}
			echo "</ul>\n";
		}
		
		//var_dump(self::getMessages());
		
		exit();
	}
}

class MoorNotFoundException extends Exception {}
class MoorContinueException extends Exception {}
