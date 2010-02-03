<?php
/**
 * Built-In Action Controller for Moor, a URL Routing/Linking/Controller library for PHP 5
 *
 * @copyright  Copyright (c) 2010 Jeff Turcotte
 * @author     Jeff Turcotte [jt] <jeff.turcotte@gmail.com>
 * @license    MIT (see LICENSE or bottom of this file)
 * @package    Moor
 * @link       http://github.com/jeffturcotte/moor
 * @version    1.0.0b4
 */
class MoorActionController extends MoorAbstractController  {
	/**
	 * Create an instance to encapsulate all controller logic
	 *
	 * @return void
	 */
	public function __construct() {
		$this->__before();
		
		try {
		    parent::__construct();
		
		} catch (Exception $e) {
		    
		    $exception = new ReflectionClass($e);

		    while($exception) {
    		    // pass exceptions to a __catch_ExceptionClass method 
    		    $magic_exception_catcher = "__catch" . $exception->getName();
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
	}
	
	protected function __before() {}
	protected function __after() {}
}

// ===========
// = License =
// ===========

// Moor - A URL Routing/Linking/Controller library for PHP 5

// 
// Copyright (c) 2010 Jeff Turcotte
// 
// Permission is hereby granted, free of charge, to any person
// obtaining a copy of this software and associated documentation
// files (the "Software"), to deal in the Software without
// restriction, including without limitation the rights to use,
// copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the
// Software is furnished to do so, subject to the following
// conditions:
// 
// The above copyright notice and this permission notice shall be
// included in all copies or substantial portions of the Software.
// 
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
// EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
// OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
// NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
// HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
// WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
// FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
// OTHER DEALINGS IN THE SOFTWARE.