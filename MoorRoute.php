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
	 * The numeric key
	 *
	 * @var integer
	 */
	public $serial;
	
	/**
	 * The user defined name
	 *
	 * @var string
	 */
	public $name;
	
	/**
	 * The callbacks to executed on a route match
	 *
	 * @var array
	 */
	public $callbacks;
	
	/**
	 * Overrides for incoming $_GET parameters
	 *
	 * @var array
	 */
	public $overrides;
	
	/**
	 * The regular expression used to match a url
	 *
	 * @var string
	 */
	public $expression;
	
	/**
	 * The shorthand expression used to generate urls
	 *
	 * @var string
	 */
	public $shorthand;
	
	/**
	 * All param names found within the shorthand expression
	 *
	 * @var array
	 */
	public $shorthand_params;
	
	/**
	 * All the symbols found within the shorthand expression
	 *
	 * @var array
	 */
	public $shorthand_symbols;

	/**
	 * A flipped version of the $shorthand_params 
	 *
	 * @var array
	 */
	public $shorthand_params_flipped;
	
	/**
	 * A flipped version of the $shorthand_symbols
	 *
	 * @var array
	 */
	public $shorthand_symbols_flipped;
	
	/**
	 * The hashed route
	 *
	 * @var string
	 */
	public $hash;
	
	/**
	 * Creates a new route to be used for matching and link generation
	 * 
	 * @param string $serial            The route's numeric id
	 * @param string $name              The route name
	 * @param string $definition        The expression used to define the route, either a regex or shorthand
	 * @param string|array $callbacks   The callbacks to be executed upon a match
	 * @param string $overrides         Any $_GET overrides to apply upon a match
	 */
	function __construct($serial, $name, $definition, $callback, $overrides=array()) {
		$this->serial = (int) $serial;
		
		$this->name = $name;
		$this->definition = $definition;
		$this->expression = self::createRouteExpression($definition);

		$this->shorthand = self::createShortHandRouteExpression($definition);
		$this->shorthand_params = self::parseParamsFromShorthand($this->shorthand);
		$this->shorthand_symbols = self::createSymbolsFromParams($this->shorthand_params);	
		$this->shorthand_params_flipped	= array_flip($this->shorthand_params);
		$this->shorthand_symbols_flipped = array_flip($this->shorthand_symbols);

		$this->callback = $callback;
		$this->callback_pieces = self::parseCallback($this->callback);
		$this->overrides = self::parseOverrides($this->callback_pieces);
		
		print_r($this->overrides);
		
		$this->hash = "$name:" . join(':', array_merge(array_keys($this->shorthand_params), array_keys($this->overrides)));
	}
	
	function dispatch() {
		$callback = $this->buildCallback();
		print_r($callback . "\n\n");
		
		if (is_callable($callback)) {
			call_user_func($callback);
		} else {
			print_r('NOT A VALID CALLBACK' . "\n\n");
		}
		
		/*
		$namespace = $route->callback['namespace'];
		$class = $route->callback['class'];
		$class = $namespace.'\\'.$class;
		
		$method = new ReflectionMethod($class, $route->callback['method']); 
		if (!$method->isPublic()) {
			continue;
		}
		
		if (is_subclass_of($namespace.'\\'.$class, 'MoorController')) {
			$class = $namespace.'\\'.$class;
			
			if (!$method->isStatic()) {
				new $class($route->callback['method']);
			}
		}
		*/
	}
	
	private static function parseOverrides($callback_pieces)
	{
		$overrides = array();

		foreach($callback_pieces as $name => $val) {
			if ($val && $val != '*') {
				$overrides[$name] = $val;
			}
		}

		return $overrides;
	}
	
	public static function findCallback($callback_search)
	{			
		$key = __FUNCTION__ . "/$callback_search";
		
		if (isset(Moor::$cache[$key])) {
			return Moor::$cache[$key];
		}
		
		extract(self::parseCallback($callback_search));
		
		if ($namespace && $class && $method) {
			$names = array(
				"{$namespace}\\{$class}::{$method}",
				"{$namespace}\\{$class}::*",
				"{$namespace}\\*::{$method}",
				"{$namespace}\\*::*",
				"*\\{$class}::{$method}",
				"*\\{$class}::*",
				"*\\*::{$method}",
				"*\\*::*"
			);
		} else if ($namespace && $function) {
			$names = array(
				"{$namespace}\\{$function}",
				"{$namespace}\\*",
				"*\\{$function}",
				"*\\*"
			);
		} else if (!$namespace && $class && $method) {
			$names = array(
				"{$class}::{$method}",
				"\\{$class}::{$method}",
				"{$class}::*",
				"\\{$class}::*",
				"*::{$method}",
				"\\*::{$method}",
				"*::*",
				"\\*::*"
			);
		} else if (!$namespace && $function) {
			$names = array(
				"{$function}",
				"\\{$function}",
				"*",
				"\\*"
			);
		}

		foreach ($names as $name) {
			if (isset(Moor::$names[$name])) {
				return Moor::$cache[$key] = $name;
			}				
        }
	}
	
	public static function parseCallback($callback)
	{
		if (strpos($callback, "\\") === 0) {
			$callback = substr($callback, 1);
		}
		
		$split = explode("\\", $callback);
		$function  = array_pop($split);
		$namespace = implode("\\", $split);

		if (strpos($function, '::') !== FALSE) {
			$split  = explode('::', $function);
			$class  = $split[0];
			$method = $split[1];
			$function = NULL;
		}
		
		echo $namespace . "\n\n";
		
		return array(
			'namespace' => $namespace,
			'function'  => $function,
			'class'     => $class,
			'method'    => $method
		);

	}
	
	function buildCallback() {
		extract($this->callback_pieces);
		
		if ($namespace == '*' && isset($_GET['namespace'])) {
			$namespace = Moor::camelize($_GET['namespace'], TRUE);
		}
		if ($function == '*' && isset($_GET['function'])) {
			$function = Moor::underscorize($_GET['function']);
		}
		if ($class == '*' && isset($_GET['class'])) {
			$class = Moor::camelize($_GET['class'], TRUE);
		}
		if ($method == '*' && isset($_GET['method'])) {
			$method = Moor::camelize($_GET['method']);
		}
		
		$callback = '';

		if ($namespace) {
			$callback .= $namespace.'\\';
		}
		if ($function) {
			$callback .= $function;
		} else if ($method) {
			$callback .= $class . '::' . $method;
		}
		
		return $callback;
	}
	
	/**
	 * Creates an array of symbols from an array of params
	 *
	 * @param array $params  An array of params names
	 * @return array         An array of symbols
	 */
	private static function createSymbolsFromParams($params)
	{
		return array_map(__CLASS__.'::createSymbolsFromParamsCallback', $params);
	}
	
	/**
	 * The callback for createSymbolsFromParams
	 *
	 * @param string $param  The param string 
	 * @return string        The symbol
	 */
	private static function createSymbolsFromParamsCallback($param)
	{
		return ':'.$param;
	}
	
	/**
	 * Parse an array of params out of a shorthand route definition
	 *
	 * @param string $shorthand  A shorthand route definition
	 * @return array             An array of param names
	 */
	private static function parseParamsFromShorthand($shorthand)
	{
		preg_match_all('/\:([a-zA-Z0-9_]+)/', $shorthand, $matches);
		return (isset($matches[1])) ? $matches[1] : array();
	}
	
	/**
	 * Create a regular expression from a shorthand route definition
	 *
	 * @param string $shorthand  A shorthand route definition
	 * @return string            A regular expression
	 */
	private static function createRouteExpression($shorthand) {
		if ($shorthand[0] == '/') {
			$expression = preg_quote($shorthand, '#');
			return preg_replace('/\\\\:([a-zA-Z0-9_-]+)/', '(?P<\1>[a-zA-Z0-9_-]+)', '#^' . $expression . '$#');
		}
		return $shorthand;
	}
	
	/**
	 * Create a shorthand route definition from a regular expression
	 *
	 * @param string $expression  A regular expression route definition
	 * @return string             A shorthand route definition
	 */
	private static function createShorthandRouteExpression($expression) {
		if ($expression[0] == '#') {		
			# pattern for matching regex named groups
			#  \(\?P ...................... start group
			#  \<([A-Z0-9a-z_-]+)\> ....... get name of group
			#  ((?:[^()]|\((?2)\))*+) ..... recursively make sure parens are even for subgroup(s)
			#  \) ......................... end group
		
			$named_group_pattern = 
				"#\(\?P 
				\<([A-Z0-9a-z_-]+)\>   
				((?:[^()]|\((?2)\))*+)   
				\)#x";
		
			$shorthand = substr($expression, 1, -1);
			$shorthand = preg_replace($named_group_pattern, ':\1', $shorthand);
			$shorthand = ltrim($shorthand, '^');
			$shorthand = rtrim($shorthand, '$');
	
			return $shorthand;
		}
		return $expression;
	}
	
	/**
	 * Create a hash for a route
	 *
	 * @param string $name    The route namae
	 * @param array $params  The route params
	 * @return string
	 */
	public static function hash($name, $params=array()) {
		return $name . ':' . join(':', array_keys($params));
		
	}
}