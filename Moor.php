<?php
/**
 * Moor - PHP 5 Routing/Controller Library
 *
 * Copyright (c) 2009 Jeff Turcotte
 *
 * @author  Jeff Turcotte
 * @license MIT License
 * @version 0.1
 *
 * See README
 */

class MoorNotFoundException extends Exception {}
class MoorContinueException extends Exception {}
 
class Moor {
    
	static $url_matchers      = array();
	static $url_callbacks     = array();
	static $url_callback_args = array();
	
	
	// class configuration
	static $configuration = array(
		'on_not_found' => 'Moor::routeNotFound', // a callback for when a MercyNotFoundException is thrown
		'on_error'     => 'Moor::routeError',  // a callback for when an exception is thrown
		'views_path'   => null
	);
	
    /**
     * configures the class
     *
     *
     */	
	static function configure($options) {
		self::$configuration = array_merge(self::$configuration, $options);
	}
	
	/**
	 * creates a route map array from a magic route string
	 *
	 * @param  string $magic_route  *See 'route' method comments for details*
	 * @return array $route_map     The route map array
	 */	
	static function createUrlMatcher($magic_route)
	{
	    // squeeze the slashes
		$url_regex = preg_replace('#/+#', '/', "^/{$magic_route}");
	    $url_regex = preg_replace_callback('/([:@#])[a-zA-Z_]+([0-9\{\,\}]+)?/', __CLASS__.'::createParameterMatcherCallback', $url_regex);
        $url_regex = preg_replace('#/#',  '\/', $url_regex);

		$route_map = array("/{$url_regex}/");        
		
		preg_match_all('/[@#:]([a-zA-Z_]+)/', $magic_route, $matches);
		
		return isset($matches[1]) ? array_merge($route_map, $matches[1]) : $route_map;
	}
	
	/**
	 * a preg_match_replace callback for finding parameters
	 *
	 * @param  string $matches  preg_match matches
	 * @return string The modified param matcher
	 */
	static function createParameterMatcherCallback($matches)
	{
	    switch($matches[1]) {
    		case '#': $type = '[0-9]'; break;
    		case '@': $type = '[a-zA-Z_]'; break;
    		case ':': $type = '[0-9a-zA-Z_]'; break;
    	}

    	$length = (isset($matches[2])) ? $matches[2] : '+';

    	return '(' . $type . $length . ')';
	}
	
	/**
	 * associates a matching HTTP REQUEST PATH to a callback function and extracts parameters to $_GET
	 *
	 * @param  mixed $url_matcher   See below
     * @param  mixed $url_callback  The callback or array of callbacks to pass directly to call_user_func_array
	 */
	static function map($url_matcher, $url_callback, $arguments=array())
	{
		self::$url_matchers[]      = (is_array($url_matcher)) ? $url_matcher : self::createUrlMatcher($url_matcher);
		self::$url_callbacks[]     = $url_callback;
		self::$url_callback_args[] = $arguments;
	}
	
	/**
	 * run all of the routes that have been established
	 *
     * @return void
	 */
	static function run()
	{
	    try {
	        
			foreach(self::$url_matchers as $key => $matcher) {
				try {
		    
			        $request_path = preg_replace('#\?.*$#', '', $_SERVER['REQUEST_URI']);        	   
	        		if (!preg_match($matcher[0], $request_path, $matches)) continue;
        			
	        		// insert url params
	        		$params =& $_GET;
	        		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	        		    $params =& $_POST;
	        		}

	    			$matches_count = count($matches);
	    			for ($n = 1; $n < $matches_count; $n++) {
	    				if (isset($matcher[$n])) $params[$matcher[$n]] = $matches[$n];
	    			}
    			
	    			$callback  =& self::$url_callbacks[$key];
	    			$arguments =& self::$url_callback_args[$key];
    			
	    		    call_user_func_array($callback, $arguments); 
					exit();

				} catch (MoorContinueException $e) {
					continue;
				}
            }
            
		    self::triggerNotFound();
		
		} catch (MoorNotFoundException $e) {
			call_user_func_array(self::$configuration['on_not_found'], array($e));

		} catch (Exception $e) {
			call_user_func_array(self::$configuration['on_error'], array($e));

		}
		
		exit();
	}
	
	/**
	 * sets appropriate headers and throws a MoorNotFoundException
	 *
     * @return void
	 */
	static function triggerNotFound()
	{
		header("HTTP/1.1 404 Not Found");
		$_SERVER['REQUEST_METHOD'] = 'GET'; 
		throw new MoorNotFoundException();
	}

	/**
	 * throws a MoorContinueException. allows for routing to continue.
	 *
     * @return void
	 */
	static function triggerContinue()
	{
		throw new MoorContinueException();
	}
	
	/**
	 * default not found callback
	 *
     * @return void
	 */
	static function routeNotFound() 
	{
	    echo '<h1>Not Found</h1>';
	}

	/**
	 * default error callback
	 *
     * @return void
	 */
	static function routeError($e)
	{
	    echo '<h1>Error</h1>';
	}
}