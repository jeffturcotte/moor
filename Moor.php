<?php
/**
 * Moor - PHP 5 Routing/Controller Library
 *
 * Copyright (c) 2009 Jeff Turcotte
 *
 * @author  Jeff Turcotte
 * @license MIT (see LICENSE)
 * @version 0.1
 *
 * See README
 */

// Moor specific Exceptions

if (!class_exists('MoorRoute', FALSE)) {
    include 'MoorRoute.php';
}

class MoorNotFoundException extends Exception {}
class MoorContinueException extends Exception {}

class Moor {
    public static $debug = false;
    public static $debug_messages = array();

    public static $running = false;
    public static $routes = array();
    public static $request_path = null;

    protected static $mount_path = null;    

    public static $params = array();
    
    /**
     * Options
     *
     * pollute boolean       pollute $_GET with the matched url params and $_SERVER['REQUEST_PATH'] with any mounts
     * on_missing callback
     */
    public static $options = array(
        'pollute' => true,
        'on_not_found' => 'Moor::routeMissing'
    );
        
    /**
     * undocumented function
     *
     * @return void
     * @author Jeff Turcotte
     **/
    static function getParam($name)
    {
        if (isset(self::$params[$name])) {
            return self::$params[$name];
        }
        return null;
    }
    
    /**
     * undocumented function
     *
     * @return void
     * @author Jeff Turcotte
     **/
    static function resetParams()
    {
        self::$params = array();
    }
	
	
	/**
	 * mount a callback to a url
	 *
	 * @param string $static_url_matcher 
	 * @param string $callback 
	 * @param string $callback_arguments 
	 * @return void
	 */
	static function mount($static_url_matcher, $callback, $callback_arguments=array())
	{
	    self::captureRequestPath();
	    
	    $static_url_matcher = preg_replace('#(^/+|/+$)#', '', $static_url_matcher);
	    $static_url_regex   = preg_replace('#/+#', '/', "#^/{$static_url_matcher}/?#");
	    
	    if (preg_match($static_url_regex, self::$request_path)) {
	        self::$request_path = preg_replace("#^/{$static_url_matcher}#", '/', self::$request_path);
	        
	        // 
	        if (self::$options['pollute'] == true) {
	            $_SERVER['REQUEST_PATH'] = self::$request_path;
	        }
	        
	        call_user_func_array($callback, $callback_arguments);
	        exit();
	    } 
	}
	
	/**
	 * associates a matching HTTP REQUEST PATH to a callback function and extracts parameters
	 *
	 * @param  mixed $url_matcher
     * @param  callback $route_callback
     * @param  array $route_callback_arguments
	 */
	static function map($url_matcher, $route_callback, $route_callback_arguments=array())
	{
        self::$routes[] = new MoorRoute($url_matcher, $route_callback, $route_callback_arguments);
	}
		
	/**
	 * run all of the routes that have been established
	 *
     * @return void
	 */
	static function run()
	{
	    if (self::$running == true) {
	        die("only run(); once");
	    }
	    
	    self::$running = true;
	    self::captureRequestPath();
	    
	    try {
	        foreach(self::$routes as $route) {

				try {
				    self::resetParams();
		      		self::parseRoute($route);
		      		
		      		// add params to $_GET if pollute is on
		      		if (self::$options['pollute'] == true) {
		      		    $_GET = array_merge($_GET, self::$params);
		      		}
	                
	        	    call_user_func_array($route->callback, $route->callback_args); 
					exit();

				} catch (MoorContinueException $e) {
					continue;
				}
            }
            
		    self::triggerNotFound();
		
		} catch (MoorNotFoundException $e) {
		    if (is_callable(self::$options['on_not_found'])) {
		        call_user_func(self::$options['on_not_found']);
		    }
		} 
		exit();
	}
	
	
	// matches a route and extacts the parameters
	protected static function parseRoute($route)
    {
        if (!preg_match($route->matcher[0], self::$request_path, $matches)) {
		    self::triggerContinue();
		}
        
       	$matches_count = count($matches);
		for ($n = 1; $n < $matches_count; $n++) {
			if (isset($route->matcher[$n])) {
			    self::$params[$route->matcher[$n]] = $matches[$n];
			}
		}
    }
	
	// grabs the request path for internal usage
	protected static function captureRequestPath() 
	{
	    if (self::$request_path === null) {
	        self::$request_path = preg_replace('#\?.*$#', '', $_SERVER['REQUEST_URI']);        
	        self::$request_path = preg_replace('#\/+$#', '', self::$request_path);
	    }	   	    
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
	static function routeMissing() 
	{
	    echo '<h1>404 Not Found</h1>';
	}

	/**
	 * default error callback
	 *
	 * @param  Exception $e  The uncaught exception 
     * @return void
	 */
	static function routeError($e)
	{
	    self::printHTMLHeader('Error');
	    ?>
	    
	    <div id="error">
	        <h1>Error</h1>
	    
	        <h2><small>Uncaught</small> <strong><?php echo get_class($e); ?></strong> <small>says:</small></h2>
	    
    	    <blockquote><?php echo $e->getMessage() ?></blockquote>
	    
    	    <h2>Where?</h2>
	    
    	    <p>Line <?php echo $e->getLine() ?> in <?php echo $e->getFile() ?></p>
	    
    	    <h2>PHP Trace</h2>
	    
    	    <p><?php echo nl2br($e->getTraceAsString()) ?></p>
    	    
    	    <h2>Routing Trace</h2>
    	    
    	    <p><?php echo implode('<br />', self::$debug_messages)?></p>
	    </div>
	    
	    <?php
	    self::printHTMLFooter();
	}
	
}