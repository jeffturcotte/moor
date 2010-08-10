# Moor

A URL Routing/Linking/Controller library for PHP 5.

- Introduction
- Concepts To Understand
- Hello World (PHP 5.2)
- Hello World (PHP 5.3+)
- Initial Set Up
- URL Routing
	- Request Parameters
	- Callback Parameters
	- Valid Callbacks
- Link Generation
- Path Generation
- Active Callback Helpers
- 404 Callback
- Triggers
- Debugging
- Built-In Controllers
- Tips
	- Linking Helper
- Issues & Errata
- Credits

## Introduction

Moor is a URL Routing/Linking/Controller library for PHP 5. It performs 2 actions very well: routing URLs to callbacks and generating URLs for callbacks.

While an understanding of MVC will help your grasp Moor's role in your application, Moor is not an MVC framework. It's a library that only helps with routing, linking, and organizing your controller logic. Use your favorite ORM for your Models and your favorite templating system for your Views. If you don't need or want these pieces, write cool stuff without them.

*This is currently beta software.* It is released under the MIT license.

## Concepts To Understand

If you don't have a basic understanding of the following concepts/techniques, you will be at a severe disadvantage. Please read up if you have to.

[Wikipedia MVC Page](http://en.wikipedia.org/wiki/Model-view-controller)
	
## Hello World (PHP 5.2)

	<?php
	Moor::route('/hello/:name', 'hello_world')->run();
	
	function hello_world() {
		echo 'HELLO WORLD to ' . $_GET['name'];
	}
	
## Hello World (PHP 5.3+)
	
	<?php
	Moor::route('/hello/:name', function(){
		echo 'HELLO WORLD to ' . $_GET['name'];
	})->run();
	
## Initial Set Up

Not much is needed to set up Moor. With your HTTP Server of choice, simply route all requests for paths/files that don't exists to a single PHP script. This pattern is typically referred to as a Front Controller. In Apache, these rules can be placed in your .htaccess:

	# route non-existant requests to single script
	RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_URI} !-f
	RewriteRule ^.*$ bootstrap.php [QSA,NS]

Somewhere in your script, either bootstrap, or (I prefer) another included file, load up Moor!

	include /path/to/libraries/moor/Moor.php;

Then you are good to go! Load any other libraries, configure your app(s), and define your Moor options/routes.

## URL Routing

The primary feature of Moor is routing URLs to a callback. This is achieved with the Moor::route method. Moor::route takes 3 parameters in a few configurations: a URL definition, a valid callback, and a closure. The PHP Callback can be any valid PHP callback: function or method. For security reasons, if calling a method, the class must extend (at-least) MoorAbstractController. 

	// Will run function 'home_page' on /
	Moor::route('/', 'home_page');

	// Will run method 'Apps::list' on /apps
	// Remember! Class Apps should extend (at least) MoorAbstractController
	Moor::route('/apps', 'Apps::list'); 
	
	// Will run Site\Home::index on /my-site
	Moor::route('/my-site', 'Site\Home::index');
	
	// You can also chain routes together
	Moor::
		route('/', 'home')->
		route('/page1', 'page_one')->
		route('/page2', 'page_two');
	
By default, routes are matched from beginning to end, but we can change this behavior by adding wildcards to the beginning and/or end of our URL definition. They are not valid elsewhere in the URL.

	// Will run function start_app on /app/anything-can-go-here
	Moor::route('/app/*', 'start_app');
	
	// Will run function my_resouse on /anything-can-go-here/end
	// URL definitions starting with a wildcard cannot be linked to! (see Link Generation)
	Moor::route('*/end, 'my_resource');

If we're using a closure (5.3+), we can replace the second callback parameter.

	Moor::route('/', function(){
		// do stuff!
	});
	
However, we now have no way of referencing the route in order to link back to it, so most of the time we're probably best leaving a function name as the second parameter and our closure as the third parameter

	Moor::route('/', 'home', function(){
		// do stuff! and we can generate a link to this! :-)
	});
	
URL routing is triggered when Moor::run is called.

	Moor::run();
	
It's nice when you chain it on the end of some routes.

	Moor::
		route('/', 'home')->
		route('/page1', 'page_one')->
		route('/page2', 'page_two')->
		run();

Moor::setUrlPrefix can be used to route URLs which all have a common string at the beginning, such as when installed in a subfolder of a website. The prefix will be added to every $url_string passed to Moor::route.

	Moor::setUrlPrefix('/folder/to/route/under/')->
		route('page1', 'page_one')->
		route('page2', 'page_two');
		
### Request Parameters

Parameters can be extracted from URLs by using a prepending a URL piece with a colon, i.e. :var_name. By default, they are matched by the pattern [0-9A-Za-z\_]+.

	// Will match /[0-9A-Za-z_-]+ and put the value of 'name' in $_GET
	Moor::route('/:name', 'my_callback');
		
	// other examples	
	Moor::route('/users/:id', 'MyApp\User::read');

If we need to match a specific pattern, we can add a pattern in parenthesis after the request parameter definition.

	// only match digits for the :id param
	Moor::route('/users/:id(\d+)', 'MyApp\User::read');

To change the default request param pattern, use Moor::setRequestParamPattern:

	Moor::setRequestParamPattern('[A-Z_]+');

### Callback Parameters

A lot of times the callbacks provided won't (and shouldn't) be static, so we have to allow for parameters from the url to be moved into the callback. These are similar to request parameters, but are prefixed with an @. All callback parameters are available in the callback definition.
	
	Moor::route('/@class/:id/@method', '@class::@method');
	// incoming /user/4/delete resolves to user::delete AND $_GET['id'] = 4
	// incoming /user/5/activate resolves to user::activate AND $_GET['id'] = 5
	// incoming /groups/3/update resolves to groups::update AND $_GET['id'] = 3
	
	Moor::route('/@class/:id, '@class::read');
	// incoming /user/4 resolves to user::read and $_GET['id] = 4
	
Typically, we need to format the callback params to fit with our own coding standard, so we can add formatting rules in parenthesis after the callback parameter definition. There are currently 3 formatting rules that can be applied.

	u  => underscore (default, used if no formatting rule is specified)
	lc => lowerCamelCase
	cc => UpperCamelCase

	Moor::route('/@class/:id/@method', '@class(uc)::@method(lc)');

	// incoming /user/4/delete_me resolves to User::delete AND $_GET['id'] = 4
	// incoming /user/5/activate resolves to User::activate AND $_GET['id'] = 5
	// incoming /user/4/run_script resolves to User::runScript AND $_GET['id'] = 4
	// incoming /groups/3/update resolves to Groups::update AND $_GET['id'] = 3

The names of callback params are arbitrary. Just build a valid callback with them. 

	Moor::route('/@c/:id/@m', '@c(uc)::@m(lc)');
	
The only rule is that you must use have the same callback params in the URL and callback definitions.

	Moor::route('/@namespace/@class/:id/@method', '@class(uc)::@method(lc)');
	// Mismatched callback params! Will throw a MoorProgrammerException

### Valid Callbacks

For security reasons, some callbacks are not valid. Here are the requirements for callbacks:

__Closures__

- No requirements, all closure callbacks are valid

__Functions__

- Cannot be *fully* dynamic: '@function' or '@namespace\@function', as opposed to the valid: 'totally\_static\_func', 'MyNamespace\@function' or 'functions\_@name'

__Methods__

- Class must be a subclass of MoorAbstractController
- Method name cannot start with \_\_ (to protect magic methods)
- Method must have public visibility

## Link Generation

Moor can generate links to any callback defined in a route. Let's say we define the following routes.

	Moor::route('/:name(u)', 'home', function(){
		'Welcome, ' . $_GET['name'];
	});

	Moor::
		route('/@class(u)/:id/@action(u)', '@class(uc)::@method(lc)')->
		route('/@class(u)/:id', '@class(uc)::read')->
		route('/@class(u)/:id.:format([a-z]{1,3})', @class(uc)::read')->
		route('/@class(u)', '@class(uc)::index')->
		run();
		
Note the formatting rules in the URL definition callback params. Just as we defined these rules in the callback, we can define in the URL, above we are explicitly saying "convert the callback to underscore format" across the board. Linking to User::delete w/ id of 4 will generate /user/4/delete. See how User became user for the url? a class named UserGroup would become user\_group for the URL. 

Typically, we'll always want underscore format for the URL callback params, so we can drop that formatting rule altogether as it's the default. 

	Moor::route('/:name)', 'home', function(){
		'Welcome, ' . $_GET['name'];
	});

	Moor::
		route('/@class/:id/@action', '@class(uc)::@method(lc)')->
		route('/@class/:id.:format([a-z]{1,4})', @class(uc)::read')->
		route('/@class/:id', '@class(uc)::read')->
		route('/@class', '@class(uc)::index')->
		run();

Great. But how to we generate links?? We can with the Moor::linkTo method. This method takes the callback (or closure name) plus colon (and optionally space) separated request param names we want to pass, then a variable length argument list of the values of those params. Look at these examples:

	Moor::linkTo('Users::delete :id', 1); 
	// generates /users/1/delete
	
	Moor::linkTo('Users::read :id', 200);
	// generates /users/200
	
	Moor::linkTo('Users::read :id :format', 5, 'json');
	// generates /users/5.json
	
	Moor::linkTo('Users::index');
	// generates /users
	
	Moor::linkTo('home :name', 'bob');
	// generates /bob;
	
	Moor::linkTo('No\Way::jose');
	// no callback match can be found, throws a MoorProgrammerException
	
Request params that don't exist in the URL definition are appended to the query string.
	
	Moor::linkTo('Users::update :id :name', 17, 'john');
	// generates /users/17/update?name=john
	
If you want to link to a method callback within the current active class, linkTo can accept a wildcard in the callback:

	Moor::linkTo('*::edit');
	// links to Users::edit if our currently running callback is in the Users class.
	
This can also be used on the namespace level:

	// 5.3+ style namespaces
	Moor::linkTo('*\my_function');
	Moor::linkTo('*\Users::edit');
	
	// 5.2 style namespaces will consider anything before an _CamelCase class name as a namespace
	// Example: Admin_Users::edit => namespace will be Admin
	Moor::linkTo('*_Users::edit');
	

We can only use linkTo once Moor's router has been started with run(). If the route cannot be found, Moor::linkTo will throw a MoorProgrammerException. Currently, Moor considers a callback valid if it matches a route and does not determine if the callback actually exists.

## Path Generation

Typically, it's convenient to map your callbacks to file paths in order to pull in associated files, such as views. We can generate a path with Moor::pathTo(). This method takes the same argument style as Moor::linkTo(), minus the request params.

	Moor::pathTo('User::edit');
	// returns /user/edit
	
	Moor::pathTo('Dashboard\Groups::add');
	// returns /dashboard/groups/add
	
	Moor::pathTo('Admin_StoreItems::delete');
	// returns /admin/store_items/delete
	
	Moor::pathTo('*::index');
	// returns path to /ACTIVE-CLASS/index
	
Or, as a convenience, there are two active path methods to generate paths for running callbacks which are described in the next section.

## Active Callback Helpers

Moor defines some methods to help get information about the current active/running callback. If the callback you've defined in your route doesn't contain one of these pieces, that pieces' function will simply return NULL.

	Moor::getActiveCallback() 
	// returns the currently running callback in it's full form

	Moor::getActiveClass()
	// returns the class name (plus namespace) of the currently running callback

	Moor::getActiveFunction()
	// returns the function name of the currently running callback

	Moor::getActiveMethod()
	// returns the method name (plus namespace & class) of the currently running callback

	Moor::getActiveNamespace()
	// returns the namespace of the currently running callback

	Moor::getActiveShortClass()
	// returns the class name (minus namespace) of the currently running callback

	Moor::getActiveShortMethod()
	// returns the method name (minus namespace & class) of the currently running callback
	
	Moor::getActivePath();
	// returns the generated path for the currently running callback
	
	Moor::getActiveClassPath();
	// returns the generated path for the currently running callback's class

## 404 Callback

There is a default callback that is run when a route cannot be found: Moor::routeNotFoundCallback. This can be changed with:

	Moor::setNotFoundCallback('Your::own404Callback');
	
Please note that the default not found callback can optionally display debugging information, so if you still wanted debug messages, you would need to add this functionality to your custom callback.

## Triggers

While the router is running:
	- Trigger the 404 callback with Moor::triggerNotFound().
	- Skip the current active route and continue routing with Moor::triggerContinue().

## Debugging

Enable debugging on the default 404 page with:

	Moor::enableDebug();
	// you can also check if debugging is 
	// enabled with: Moor::getDebug()
	
Debugging is off by default. If you are creating your own 404 page, you can get an array of all debug messages with:

	Moor::getMessages();

## Built-In Controllers

Moor currently comes with two built in controller classes. MoorAbstractController and MoorActionController. If neither suit your needs, you're encouraged to extend MoorAbstractController and create your own controller classes.

MoorAbstractController is as simple as it gets:

	class MoorAbstractController {
		public function __construct() {
			call_user_func(array(
				$this, Moor::getActiveShortMethod()
			));
		}
	}
	
	// When a method callback is matched, and the class of that callback
	// is a subclass of MoorAbstractController, Moor instantiates the 
	// extending class in which __construct calls the public instance method. 
	
MoorActionController is built on top of MoorAbstractController, but has some special features that set it apart and make it more appealing for writing an application. The built in helper methods are:

	protected function beforeAction() { ...
	// Will be called before all action methods	
		
	protected function afterAction() { ...
	// Will be called after all action method (and exception handlers)	
	
	protected function catch{EXCEPTION_NAME}($e) { ...
	// Will catch the specified exception throw within the action method
	// Accepts one argument: the exception instance.
		
Here is an example of these methods in action:

	class Users extends MoorActionController() {
		
		protected function beforeAction() {
			// Called before all action methods
		}
		
		public function myAction() {
			// My action logic is here after __before
			$this-x = $this->myHelper();
		}
		
		public function myUnstableAction() {
			throw new FrameworkException($e);
		}
		
		protected function catchFrameworkException($e) {
			// Will catch FrameworkException (or a subclass of) then continue to afterAction
		}
		
		protected function catchException($e) {
			// Will catch Exception (or a subclass of), then continue to afterAction
		}
		
		protected function afterAction() {
			// Called after the action method (and possibly an exception handler.)
		}
		
		
		// We can use the valid callback rules to our advantage.
		// Below is a helper method that is not accessible through 
		// any URL because its visibility is not public. Secure
		// anything that's meant for internal use only.
		
		protected function myHelper() {
			return 'help';
		}
	}

## Tips & Extras

### Linking Helper

You'll probably not want to write Moor::linkTo() everywhere, so it's usually nice to write a global function . I typically use link_to() or l().

	function link_to() {
		$args = func_get_args();
		return call_user_func_array(
			'Moor::linkTo', $args
		);
	}
	
### Simple Views

Views (or any kind of templates) aren't built in to Moor, but that doesn't mean they aren't an important part of your app. Powerful templating engines are fine, but I prefer keeping things simple. PHP has free templating, so the easiest way to implement a template/view system is to simply include a file. This pattern has worked well for me .

		define('PATH_TO_VIEWS', '/path/to/your/views');

		function render($callback, $controller_instance=NULL, $format='html') {
			$_moor_view_file = PATH_TO_VIEWS . Moor::pathTo($callback) . ".{$format}.php",

			if (!file_exists($_moor_view_file)) { return; }

			unset($path_to_callback, $controller_instance, $format);

			if (is_object($controller_instance)) {
				extract(get_object_vars($controller_instance), EXTR_REFS);
			}

			include $moor_view_file;
		}

	Usually, this is enough, and it can be called from the afterMethod

## Issues & Errata

Please submit and issues or errata through the [issue tracker on GitHub](http://github.com/jeffturcotte/moor/issues). Your help is appreciated.

## Credits

Designed and programmed by Jeff Turcotte.

(c) 2010