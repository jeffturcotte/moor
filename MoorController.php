<?php
/**
 * MoorController - Rails-esque Controller extension for Moor
 *
 * Copyright (c) 2009 Jeff Turcotte
 *
 * @author  Jeff Turcotte
 * @license MIT License
 * @version 0.1
 *
 * See README
 */
abstract class MoorController {
    
    // default class options 
    static $options = array(
        'controller' => 'RootController',
        'action'     => 'index'
    );
    
   	static function configure($options, $val=null) {
	    if (is_array($options)) {
		    self::$options = array_merge(self::$options, $options);
		} else {
		    self::$options[$options] = $val;
		}
	}
	
	static function dispatch($controller=null, $action=null) {
        try {
    	    self::$options['controller'] = ($controller) ? $controller : self::$options['controller'];
    	    self::$options['action'] = ($action) ? $action : self::$options['action'];
	    	    
            // make sure the controller is a child of MoorController
            $abstract_controller  = new ReflectionClass(__CLASS__);
            $extending_controller = new ReflectionClass(self::$options['controller']);
            if (!$extending_controller->isSubclassOf($abstract_controller)) {
    		    throw new Exception();
    		}
    		
    		// construct controller
    		new self::$options['controller']();

	    } catch (Exception $e) {
            Moor::triggerContinue();
	    }
	}
	
	/**
	 * initializes a controller. to be called from within MoorController
	 *
	 * @return void
	 */
	protected function __construct() 
	{	
	    // don't allow the action method to be static or private/protected
		$method = new ReflectionMethod($this, self::$options['action']);
		if (!$method->isPublic() || $method->isStatic()) {
            Moor::triggerContinue();
		}
		
		$this->__before();
		
		try {
		    // run controller action method
		    $this->{self::$options['action']}();
		    		    
		} catch (Exception $e) {
		    // pass exceptions to a __catch_ExceptionClass method 
		    $magic_exception_catcher = "__catch_" . get_class($e);
    		// rethrow if that method doesn't exists or can't be called
            if (!is_callable(array($this, $magic_exception_catcher))) throw $e;
            call_user_func_array(array($this, $magic_exception_catcher), array($e));
		}
	    
	    $this->__after();
	}
	
    // define protected __before and __after methods so __construct doesn't 
    // have to worry about whether or not they exist before calling them.
	protected function __before() {}
	protected function __after() {}
}