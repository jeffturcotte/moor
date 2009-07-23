<?php
/**
 * MoorController - Resource/Controller extension for Moor
 *
 * Copyright (c) 2009 Jeff Turcotte
 *
 * @author  Jeff Turcotte
 * @license MIT (see LICENSE)
 * @version 0.2
 *
 * See README
 */

if (!class_exists('Moor', FALSE)) {
    include 'Moor.php';
}

abstract class MoorController {
	/**
	 * MoorController options
	 *
	 * @var array
	 */
	public static $options = array(
		'default_controller' => 'index',
		'default_action'     => 'index',
		'controller_to_class_callback' => 'MoorController::convertControllerToClass',
		'class_to_controller_callback' => 'MoorController::convertClassToController'
	);
    
	/**
	 * MoorController data
	 *
	 * @var array
	 */
    private static $data = array(
		'class' => null,
        'controller' => null,
        'action' => null,
    );
    
	/**
	 * Reflections cache
	 *
	 * @var array
	 */
    protected static $class_reflections = array();
    
	/**
	 * resets all data
	 *
	 * @return void
	 **/
	static function resetData()
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
	static function addRoute($url_matcher, $controller=null, $action=null)
	{
	    Moor::addRoute($url_matcher, __CLASS__.'::dispatchControllerAction', array($controller, $action));
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
	static function dispatchControllerAction($controller=null, $action=null) {	    

	    if (!$controller) $controller = Moor::getParam('controller');
        if (!$controller) $controller = self::$options['default_controller'];
        
        if (!$action) $action = Moor::getParam('action'); 
		if (!$action) $action = self::$options['default_action'];
        
        if (!$controller || !$action) {
			Moor::addMessage(
				__CLASS__,
				__FUNCTION__,
				'Continue to next route. No controller or action were supplied'
			);
            self::triggerContinue();
        }

		$class = call_user_func_array(self::$options['controller_to_class_callback'], array($controller));
        
        if (!class_exists($class)) {
			Moor::addMessage(
				__CLASS__,
				__FUNCTION__,
				"Continue to next route. Class {$class} does not exist"
			);
	
            self::triggerContinue();
    	}
    	
    	if (!self::isChildClass($class)) {
			Moor::addMessage(
				__CLASS__,
				__FUNCTION__,
				"Continue to next route. Class {$class} does not extend " . __CLASS__
			);
	
        	self::triggerContinue();
    	}
    	
		self::$data['class'] = $class;
    	self::$data['controller'] = $controller;
    	self::$data['action'] = $action;

		Moor::addMessage(
			__CLASS__,
			__FUNCTION__,
			"Instantiating controller {$class}"
		);
		
    	// construct controller
    	new self::$data['class']();
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
	    self::resetData();
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
			
			Moor::addMessage(
				__CLASS__, 
				__FUNCTION__,
				'Continue to next route. "' . self::$data['action'] . '" is not a public instance method in ' . get_class($this)
			);
		    self::triggerContinue();
		}
		
		$this->__before();
		
		
		try {
			Moor::addMessage(
				__CLASS__,
				__FUNCTION__,
				'Calling valid action method "' . self::$data['action'] . '" on ' . get_class($this) . ' instance'
			);
					    
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
            
            if (!$exception) {
                throw $e;
            }
		}
	    
	    $this->__after();
	    
	    exit();
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
	 * convert a controller to a singular resource
	 * i.e. UserController => user
	 *
	 * @param string $resource  the resource name
	 *
	 * @return string  the controller name
	 */
	public static function convertControllerToClass($controller) {
	    if ($controller == null) return null;
		$controller = str_replace('_',' ', $controller . ' controller');
	    return str_replace(' ', '', ucwords($controller));
	}
	
	/**
	 * convert a controller to a singular resource
	 * i.e. UserController => user
	 *
	 * @param string $resource  the resource name
	 *
	 * @return string  the controller name
	 */
	public static function convertClassToController($class) {
	    if ($class == null) return null;
		$controller = preg_replace('/([a-z0-9A-Z])([A-Z])/', '\1_\2', $class);
		$controller = preg_replace('/Controller$/', '', $controller);
		return strtolower($controller);
	}
	
    // define protected __before and __after methods so __construct doesn't 
    // have to worry about whether or not they exist before calling them.
	protected function __before() {}
	protected function __after() {}
}