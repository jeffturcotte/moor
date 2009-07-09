<?php
/**
 * MoorRoute - A representation of a URL => callback with param extraction
 *
 * Copyright (c) 2009 Jeff Turcotte
 *
 * @author  Jeff Turcotte
 * @license MIT (see LICENSE)
 * @version 0.1
 *
 * See README
 */
class MoorRoute {
    
    public $definition;
    public $matcher;
    public $callback;
    public $callback_args;
    
    /**
     * construct a moor route
     *
     * @param string $matcher 
     * @param string $callback 
     * @param string $callback_args 
     */
    public function __construct($matcher, $callback, $callback_args=array()) {
        if (is_array($matcher)) {
            $this->definition = $matcher[0];
            $this->matcher    = $matcher;
        } else {
            $this->definition = $matcher;
            $this->matcher    = self::createUrlMatcher($matcher);
        }
    	$this->callback = $callback;
    	$this->callback_args = $callback_args;
    }
    
    /**
	 * creates a route map array from a magic route string
	 *
	 * @param  string $magic_route  *See 'route' method comments for details*
	 * @return array $route_map     The route map array
	 */	
	static function createUrlMatcher($magic_route)
	{
		$url_regex = preg_replace('/:[a-zA-Z_]+/', '([0-9a-zA-Z_]+)', $magic_route);
		
     	$url_regex = str_replace(
			array("&",  ",",  "/",  ":",  ";",  "=",  "?",  "@",  "."),
			array('\&', '\,', '\/', '\:', '\;', '\=', '\?', '\@', "\."),
			$url_regex
		);

		$route_map = array("/^{$url_regex}/");        
		
		preg_match_all('/[:]([a-zA-Z_]+)/', $magic_route, $matches);
		
		return isset($matches[1]) ? array_merge($route_map, $matches[1]) : $route_map;
	}	
}