<?php
/**
 * MoorController - Resource/Controller extension for Moor
 *
 * Copyright (c) 2009 Jeff Turcotte
 *
 * @author  Jeff Turcotte
 * @license BSD (see LICENSE)
 * @version 0.1
 *
 * See README
 */

if (!class_exists('Moor', FALSE)) {
    include 'Moor.php';
}

abstract class MoorController {
	
	static $options = array(
		'default_controller' => 'index',
		'default_action'   => 'index'
	);
    
    static $data = array(
		'class' => null,
        'controller' => null,
        'action' => null,
    );
    
    protected static $class_reflections = array();
    
	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	static function reset()
	{   
	    self::$data = array(
	        'class' => null,
            'controller' => null,
            'action' => null,
        );
	}
	
	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	static function map($url_matcher, $controller=null, $action=null)
	{
	    Moor::map($url_matcher, __CLASS__.'::dispatch', array($controller, $action));
	}
	
	/**
     * a moor callback to dispath a controller/action pair (optionally from $_GET parameters)
	 * - $_GET parameters allowed:
	 *      controller, i.e. UserController
	 *      action, i.e. update
	 *
	 * - any params not present will be determined by the ones that are present
	 * - plural resource takes precendence over singular resource
	 * - singular resource takes precendence over controller	 *
     *
	 * @param string $controller  the controller, i.e. UserController
	 * @param string $action      the action, i.e. read
	 *
	 * @return void
	 */
	static function dispatch($controller=null, $action=null) {	    

	    if (!$controller) $controller = Moor::getParam('controller');
        if (!$controller) $controller = self::$options['default_controller'];
        
        if (!$action) $action = Moor::getParam('action'); 
		if (!$action) $action = self::$options['default_action'];
        
        if (!$controller || !$action) {
            Moor::$debug_messages[] = 'CONTINUE: No controller or action were supplied';
            self::triggerContinue();
        }

		$class = self::convertControllerToClass($controller);
        
        if (!class_exists($class)) {
		    Moor::$debug_messages[] = 'CONTINUE: Class ' . $class . ' does not exist';
            self::triggerContinue();
    	}
    	
    	if (!self::isChildClass($class)) {
    	    Moor::$debug_messages[] = 'CONTINUE: Class ' . $class . ' does not extend MoorController';
            self::triggerContinue();
    	}
    	
		self::$data['class'] = $class;
    	self::$data['controller'] = $controller;
    	self::$data['action'] = $action;
		
		Moor::$debug_messages[] = 'EXECUTING: new ' . $class . '()';
		
    	// construct controller
    	new self::$data['class']();
	}
	
	/**
	 * convert a controller to a singular resource
	 * i.e. UserController => user
	 *
	 * @param string $resource  the resource name
	 *
	 * @return string  the controller name
	 */
	protected static function convertControllerToClass($controller) {
	    if ($controller == null) return null;
		$controller = str_replace('_',' ', $controller . ' controller');
	    return str_replace(' ', '', ucwords($controller));
	}
	
	/**
	 * the current action option
	 *
	 * @return string
	 */
	static function getAction() {
	    return self::$data['action'];
	}
	
	/**
	 * the current controller option
	 *
	 * @return string
	 */
	static function getController() {
	    return self::$data['controller'];
	}
	
	/**
	 * the current singular resource option
	 *
	 * @return string
	 */
	static function getPath() {
        return self::getController() . '/' . self::getAction();
	}	
	
	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	protected static function isChildClass($child_class)
	{
	    $cr =& self::$class_reflections;
	    
	    $self      = (isset($cs[__CLASS__]))    ? $cr[__CLASS__]    : new ReflectionClass(__CLASS__);
        $extending = (isset($cs[$child_class])) ? $cr[$child_class] : new ReflectionClass($child_class);
        
        return $extending->isSubclassOf($self);
	}
	
	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	protected static function isPublicMethod($instance, $method)
	{
	    try {
    	    $r = new ReflectionMethod($instance, $method);
            return ($r->isPublic() && !$r->isStatic());
        } catch (ReflectionException $e) {}
        
        return false;
	}
		
	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	protected static function triggerContinue()
	{
	    self::reset();
	    Moor::triggerContinue();
	}
	
	/**
	 * initializes a controller. only to be called from within MoorController
	 *
	 * @return void
	 */
	protected function __construct() 
	{	
		if (!self::isPublicMethod($this, self::$data['action'])) {
		  
		    Moor::$debug_messages[] = 'CONTINUE: Method ' . get_class($this) . '->' . self::$data['action'] . ' is static or private/protected';
		  
		    self::triggerContinue();
		}
		
		$this->__before();
		
		
		try {		    
			Moor::$debug_messages[] = 'EXECUTING: ' . self::$data['controller'] . '->' . self::$data['action'] . '()';
    	    
		    // run controller action method
		    $this->{self::$data['action']}();
		    
		} catch (Exception $e) {
		    
		    $exception = new ReflectionClass($e);

		    while($exception) {
    		    // pass exceptions to a __catch_ExceptionClass method 
    		    $magic_exception_catcher = "__catch_" . $exception->getName();
                if (is_callable(array($this, $magic_exception_catcher))) {
                    call_user_func_array(array($this, $magic_exception_catcher), array($e));
                    break;
                }
                $exception = $exception->getParentClass();
            } 
		}
	    
	    $this->__after();
	    
	    exit();
	}
	
    // define protected __before and __after methods so __construct doesn't 
    // have to worry about whether or not they exist before calling them.
	protected function __before() {}
	protected function __after() {}
}