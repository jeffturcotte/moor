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
class MoorController extends MoorAbstractController {
	protected function __before() {}
	protected function __after() {}
	
	/**
	 * initializes a controller.
	 *
	 * @return void
	 */
	public function __construct($action_method) 
	{	
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
}