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
	function __construct($serial, $name, $definition, $callbacks=null, $overrides=array()) {
		$this->serial = (int) $serial;
		$this->name = $name;
		$this->definition = $definition;
		$this->shorthand = self::createShortHandRouteExpression($definition);
		$this->expression = self::createRouteExpression($definition);
		$this->shorthand_params = self::parseParamsFromShorthand($this->shorthand);
		$this->shorthand_symbols = self::createSymbolsFromParams($this->shorthand_params);	
		$this->shorthand_params_flipped	= array_flip($this->shorthand_params);
		$this->shorthand_symbols_flipped = array_flip($this->shorthand_symbols);
		$this->callbacks = self::parseCallbacks($callbacks);
		$this->overrides = $overrides;
		$this->hash = "$name:" . join(':', array_merge(array_keys($this->shorthand_params), array_keys($this->overrides)));
	}
	
	/**
	 * Normalized a callback string/array
	 *
	 * @param string|array $callback 
	 * @return array
	 */
	private static function parseCallbacks($callback)
	{
		if (!is_array($callback) || is_array($callback) && (isset($callback[0]) && is_object($callback[0]))) {
			return array('*' => $callback);
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