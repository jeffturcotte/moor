<?php
/**
 * Moor Controller Extension
 *
 * @copyright  Copyright (c) 2009 Jeff Turcotte
 * @author     Jeff Turcotte <jeff.turcotte@gmail.com>
 * @license    MIT (see LICENSE)
 * @package    Moor
 * @link       http://github.com/moor
 *
 * @version    1.0.0b
 */
class MoorController {
	static $__app = null;
	static $__controller = null;
	static $__action = null;

	protected function __before() {}
	protected function __after() {}
	
	/**
	 * initializes a controller.
	 *
	 * @return void
	 */
	public function __construct($action_method) 
	{	
		self::$__app = $_GET[Moor::getOption('param_app')];
		self::$__controller = $_GET[Moor::getOption('param_controller')];
		self::$__action = $_GET[Moor::getOption('param_action')];

		if (Moor::getOption('pollute')) {
			define('__APP__', self::getApp());
			define('__CONTROLLER__', self::getController());
			define('__ACTION__', self::getAction());
			define('__APP_PATH__', self::getAppPath());
			define('__CONTROLLER_PATH__', self::getControllerPath());
			define('__ACTION_PATH__', self::getActionPath());
		}

		$this->__before();
		
		try {
		    $this->{$action_method}();
		    
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
	
	public static function getApp() {
		return self::$__app;
	}
	
	public static function getController() {
		return self::$__controller;
	}
	
	public static function getAction() {
		return self::$__action;
	}
	
	public static function getAppPath() {
		return DIRECTORY_SEPARATOR . self::$__app;
	}
	
	public static function getControllerPath() {
		return self::getAppPath() . DIRECTORY_SEPARATOR . self::$__controller;
	}
	
	public static function getActionPath() {
		return self::getControllerPath() . DIRECTORY_SEPARATOR . self::$__action;
	}

}