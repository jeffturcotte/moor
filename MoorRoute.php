<?php
/**
 * Representation of a single Moor route
 *
 * @copyright  Copyright (c) 2009 Jeff Turcotte
 * @author     Jeff Turcotte <jeff.turcotte@gmail.com>
 * @license    MIT (see LICENSE)
 * @package    Moor
 * @link       http://github.com/moor
 *
 * @version    1.0.0b
 */
class MoorRoute {
	/**
	 * Local cache
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Sequence for route serials 
	 *
	 * @var integer
	 */
	private static $sequence = 0;
	
	/**
	 * MoorRoute object indexed by serial
	 *
	 * @var array
	 */
	private static $routes_by_serial = array();
	
	/**
	 * MoorRoute serials indexed by callback
	 *
	 * @var array
	 */
	private static $serials_by_callback = array();

	/**
	 * MoorRoute serials indexed by hash
	 *
	 * @var array
	 */
	private static $serials_by_hash = array();
	
	/**
	 * The numeric key
	 *
	 * @var integer
	 */
	private $serial;
	
	/**
	 * The callbacks to executed on a route match
	 *
	 * @var array
	 */
	private $callbacks;
	
	/**
	 * Parameters to add to $_GET
	 *
	 * @var array
	 */
	private $params;
				
	/**
	 * The hashed route
	 *
	 * @var string
	 */
	private $hash;
	
	static private $unsafe_callback_patterns = array(
		'*', '\\*'
	);
	
	/**
	 * undocumented function
	 *
	 * @param string $url 
	 * @param string $callback 
	 * @param string $closure 
	 * @return void
	 */
	public function create($url, $callback, $closure=NULL) {
		new self($url, $callback, $closure);
	}
	



	/**
	 * undocumented function
	 *
	 * @return void
	 */	
	function buildCallback() {
		$pieces = self::parseCallback($this->callback);
		
		if ($pieces['namespace'] == '*' && isset($_GET['namespace'])) {
			$pieces['namespace'] = $_GET['namespace'];
		}
		if ($pieces['class'] == '*' && isset($_GET['class'])) {
			$pieces['class'] = $_GET['class'];
		}
		if ($pieces['function'] == '*' && isset($_GET['function'])) {
			$pieces['function'] = $_GET['function'];
		}
		
		return self::generateCallback($pieces);
	}
	
	/**
	 * Parse an array of params out of a shorthand route url
	 *
	 * @param string $shorthand  A shorthand route url
	 * @return array             An array of param names
	 */
	private static function parseParamsFromShorthand($shorthand)
	{
		preg_match_all('/\:([a-zA-Z0-9_]+)/', $shorthand, $matches);
		return (isset($matches[1])) ? $matches[1] : array();
	}
	
	/**
	 * Create a regular expression from a shorthand route url
	 *
	 * @param string $shorthand  A shorthand route url
	 * @return string            A regular expression
	 */
	private static function createRouteExpression($shorthand) {
		if ($shorthand[0] == '/') {
			$expression = preg_quote($shorthand, '#');
			return preg_replace('/\\\\:([a-zA-Z0-9_-]+)/', '(?P<\1>[a-zA-Z0-9_-]+)', '#^' . $expression . '$#');
		}
		return $shorthand;
	}
}