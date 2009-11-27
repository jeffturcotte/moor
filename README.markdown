# Moor

*DISCLAIMER: THIS IS A FIRST DRAFT AND THERE ARE PROBABLY MISSING PIECES.*

- Introduction
- Concepts To Understand
- Quick Start
	- Hello World
	- Hello :anybody
	- Hello /BUT I NEED TO MATCH A SPECIFIC PATTERN!/
- Initial Set Up
- Options
- Callback Actions
	- Routing a URL to a Callback Action
	- Linking to a Callback Action
- Controller Actions
	- Routing a URL to a Controller Action
	- Linking to a Controller Action
	- Action Permissions
	- Magic Methods
	- Location Helpers
	- Path Helpers
- Advanced Tips
	- Controller Routing/Linking Functions
	- Combining Callbacks/Controller Routes
	- Continue Triggering
	- 404 Triggering
- Credits

## Introduction

Moor is URL routing & controller library for PHP 5. It aims to completely abstract local URLs out of your PHP applications. This is currently beta software. It is released under the MIT license.

While an understanding of MVC will help your grasp Moor's role in your application, Moor is not an MVC framework. It's a library that only handles routing and linking. Use your favorite ORM and/or Templating system for your Models and Views. If you don't need or want these pieces, write cool stuff without them.

## Concepts To Understand

If you don't have a basic understanding of the following concepts/techniques, you will be at a severe disadvantage. Please read up if you have to.

MVC
http://en.wikipedia.org/wiki/Model-view-controller

Regular Expression Named Groups
http://www.regular-expressions.info/named.html

## Quick Start

	<?php
	Moor::routeToCallback('/the-incoming-url', 'resource_name');
	
	function resource_name() { 
		// run this on '/the-the-incoming-url'	
	}
	
	Moor::run();
	?>
	
### Hello World

	<?php
	Moor::routeToCallback('/hello', 'hello_world');
	
	function hello_world() {
		echo 'HELLO WORLD!';
	}
	
	Moor::run();
	?>
	
### Hello :anybody

	<?php
	// use a shorthand expression. :name will match [A-Za-z0-9_-]+
	Moor::routeToCallback('/hello/:name', 'hello_anybody');
	
	function hello_anybody() {
		echo 'HELLO ' . strtoupper($_GET['name']);
	}
	
	Moor::run();
	?>
	
### Hello "BUT I NEED TO MATCH A SPECIFIC PATTERN!!!"

	<?php 
	// Use a regex with named groups. ** Delimit regex with #'s!! **
	Moor::routeToCallback('#^/hello/(?P<name>[A-Z]+)$#', 'hello_specific');
	
	function hello_specific() {
		echo 'Hello ' . $_GET['name'] . ', welcome to the capitals club.';
	}
	
	Moor::run();
	?>
	
## Initial Set Up

	
Not much is needed to set up Moor. With your HTTP Server of choice, simply route all requests that don't exists to a single PHP script. This pattern is typically referred to as a Front Controller. In Apache, these rules can be placed in your .htaccess:

	# route non-existant requests to single script
	RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_URI} !-f
	RewriteRule ^.*$ bootstrap.php [QSA,NS]
	
In this file, you can load all your libraries, define your Moor options/routes, configure your app, 

For Moor, only load.php needs to be included.

## Options

There are some options that can be set with Moor::setOption(). They can be set either one at a time:

	Moor::setOption('option_name', 'option_value');
	
or many at once:

	Moor::setOption(array(
		'option_one' => 'value_one',
		'option_two' => 'value_two'
	));

The options available are: 

	path (string)
	Absolute path to where controller classes can be found. You should use this option and NOT an autoloader for controller classes. This only needs to be set if you are using MoorController.
	
	pollute (boolean)
	Whether or not MoorController should create __APP__, __CONTROLLER__, etc. constants. (default: true)
	
	debug (boolean) 
	Display debug messages on default 404 callback. (default: false)
	(or access messages yourself with Moor::getDebugMessages())
	
	param_app (string)
	The $_GET param to accept as an app (default: 'app')

	param_controller (string)
	The $_GET param to accept as a controller (default: 'controller')

	param_action (string)  
	The $_GET param to accept as an action (default: 'action')

	callback_404 (callback)  
	Callback for when a route is not found upon run() (or when triggerContinue() is called)

## Callback Actions
	
Callback actions are the most simple form of route that can be created with Moor. It is a mapping between a URL and a PHP function/method. These are the building blocks of all advanced routing techniques. While you can build full web apps simply using callback actions, they are probably best reserved for when you only have a few resources or need a very custom route/resource pair. These are firmly planted in the *Sinatra* (ruby) camp.

### Routing a URL to a callback action

To route a URL to a callback, use the routeToCallback method. This takes 3 parameters: A URL expression, the name of the resource, and the function to call on a match. 

	Moor::routeToCallback('/users/:id', 'edit_user', 'edit_user');
	
	function edit_user() {
		echo 'looking up user #' . $_GET['id'];
	}
	
	Moor::run();
	
When the URL /users/5 comes in, the pattern /users/:id puts 5 into $\_GET['id'] and the callback, edit_user(), is called.
	
Most of the time, your resource name and callback function will 
be the same. if this is the case, you can drop the callback param 
and the name will be used as both. example below:
	
	Moor::routeToCallback('/users/:id', 'edit_user');
	
	function edit_user() {
		echo 'Looking up user #' . $_GET['id'];
	}
	
	Moor::run();
	
If you are lucky enough to be running PHP 5.3, you can forget about creating a function in the public space and simply create an anonymous one:

	// PHP 5.3 ONLY! ...much nicer :-)
	Moor::routeToCallback('/users/:id', 'edit_user', function(){
		echo 'Looking up user #' . $_GET['id'];
	});
	
	Moor::run();
	
### Linking to a callback action

Keeping track of all the links in your web app can become a tedious process. For this, Moor lets you easily generate a link to any resource you have defined. Define your link once and forget about using actual URLs throughout your app for local actions. For this task we use the linkToCallback() method. This method takes 2 parameters, the resource name and any GET parameters you want to send. Here is a simple app where we we want to link to other resources from our index page:

	Moor::routeToCallback('/', 'index');
	Moor::routeToCallback('/users', 'list_users');
	Moor::routeToCallback('/users/:id', 'read_user');
	
	function index() {
		echo '
			<h1>Admin</h1>
			<a href="' . Moor::linkToCallback('list_users') . '">List Users</a> |
			<a href="' . Moor::linkToCallback('list_blogs') . '">List Blogs</a>
		';
	}

	function list_users() {
		$users = fRecordSet::build('User');
		include 'views/' . __FUNCTION__ . '.php';
		
		// in our view we can link to the read_user action with:
		// Moor::linkToCallback('read_user', array('id' => $id));
	}

	function read_user() {
		$user = new User(fRequest::get('id'));
		include 'views/' . __FUNCTION__ . '.php';
	}
		
	function list_blogs() { ....

	function read_blogs() { ....

	Moor::run();
	
Using the Moor links, if you ever change a URL, you edit it in one place and it will cascade throughout your application.

## Controller Actions

Controller Actions work similarly to Callback Actions, but they have a more defined structure and provide helpers to make writing your apps more pleasant.

### Routing a URL to a Controller Action

All controller routes require three things: an app name, controller name, and action name. An app is essentially a namespace, i.e. the admin area of your app/site will be a different app than the front end. A controller should be the thing that we are performing the action on, i.e. users, blog, categories, etc. The action should be what we are doing to the thing the controller is representing.

	Moor::routeTo('/my-url', 'app', 'controller', 'action');
	
	class {CamelCase App}{CamelCase Controller}Controller extends MoorController {
		function {lowercase action}
	}

	Moor::routeTo('/', 'site', 'user', 'index');

This route maps / to, app: site, controller: user, action: index. These parameters are used to generate the class and method pair where your logic can be found.
	
	class SiteUserController extends MoorController {
		function index() {
			echo 'hello!';
		}
	}
	
The app and controller params are converted to UpperCamelCase format to generate the class name. The action parameter is kept as lowercase. The example above shows a route that is completely static and fully defined; no matter what, / will always run index() in SiteUserController. To create dynamic routes, these parameters can be pulled out of the routeToController definition and just accepted in the URL. Here is an example:

	Moor::routeTo('/:controller/:action', 'site');
	
This route will always use 'site' as the app, but leaves the controller and action to be determined by the URL. This one route will work for all of these controller actions:

	class SiteUserController extends MoorController {
		function index() {
			echo 'Welcome.';
		}
		
		function delete() {
			echo 'Let's delete a user!';
		}
	}
	
	class SiteBlogController extends MoorController {
		function create() {
			echo 'Create a blog';
		}
	}
	
Let's say we want to keep that really dynamic route around, but want to make a special url for our site blog action, create. We can do so like this.

	Moor::routeTo('/:controller/:action', 'site');
	Moor::routeTo('/create-a-blog', 'site', 'blog', 'create');

We can accept any variables we want in the URL, just like callback actions. Let's say we have a read action in every controller in our site app that accepts is looking for an id parameter in get.

	Moor::routeTo('/:controller/:id', 'site', '*', 'read');
	
The "*" parameter means accept whatever :controller is supplied in the URL.
	
### Linking to a Controller Action

Linking to a Controller Action works similarly to linking to Callback Actions but they are sometimes dependent upon where in the code they are called from. To link to a Controller Action, use the method linkTo(). It takes a dynamic parameter list in a few configurations:

	Moor::linkTo('site', 'user', 'read', array('id' => 5));
	// => generate a link for app: site, controller: user, action: read, with a GET parameter, id: 5
	
	Moor::linkTo('user', 'read', array('id' => 5));
	// => generate a link to CURRENT APP, controller: user, action: read, with a GET parameter, id: 5
	
	Moor::linkTo('read', array('id' => 5));
	// => generate a link to CURRENT APP, CURRENT CONTROLLER, action: read, with a GET parameter, id: 5
	
	Moor::linkTo(array('id' => 5));
	// => generate a link to CURRENT APP, CURRENT CONTROLLER, CURRENT ACTION, with a GET parameter, id: 5
	
So to generate links to different actions within our controller, we can do this:

	class SiteUserController extends MoorController {
		function index() {
			echo Moor::linkTo('read');
			// assumes app: site and controller: user
		}

		function read() {
			echo Moor::linkTo('index');
		}
	}
	
Maybe we want to link to the admin area from our site front end:

	class SiteUserController {
		function index() {
			echo Moor::linkTo('admin', 'user', 'index');
		}
	}
	
	class AdminUserController {
		function index() {
			// list users
		}
	}
	
### Action Permissions

Typically we'll have dynamic routes + methods of abstracted logic in our controller that we don't want the outside world to have access to. We can restrict access to these methods by making them private or protected.

	Moor::routeTo('/:id/:action', 'site', 'friends');
	
	class SiteFriendsController extends MoorController {
		function read() {
			$friend = $this->find();
		}
		
		function delete() {
			$friend = $this->find();
			$friend->delete();
		}
		
		protected function find() {
			return new Friend(fRequest::get('id'));
		}
		
	}
	
In this example, 'find' will never be accepted when being passed into the URL.

### Magic Methods

These aren't really *magic*, but they work like PHP's current magic methods (such as __call()), so we will refer to them as such.

	__before()
	This is called before any action.
	
	__after()
	This is called after any action and exception catcher.
	
	__catch_{Exception}($e)
	Used like __call(). This method will catch an Exception specified by {Exception}. 
	The exception will be passed to this method.
	
You will typically use these to set up a controller like so:

	class AdminBlogController extends MoorController {
		protected function __before() {
			// initialize and/or authenticate
		}
		
		function index() { ...
		
		function read() { ...
			
		function delete() { ...
			
		protected function __catch_ORMException($e)	{
			// set session error, redirect, etc.
		}
		
		protected function __after() {
			// render my view
		}
	}
	
They should always be protected or private to make sure the outside world doesn't have access to them.

### Location Helpers

Anytime you need to know what app, controller or action is currently running, you can use these.

	MoorController::getApp() (or __APP__ if 'pollute' is enabled)
	Returns the current app.

	MoorController::getController() (or __CONTROLLER__ if 'pollute' is enabled)
	Returns the current controller.

	MoorController::getAction() (or __ACTION__ if 'pollute is enabled)
	Returns the current action.

### Path Helpers

These are to help you map where you are to a file system path. These can be used to include files associated with a certain apps, controllers, or actions (such as styles or views.)

	MoorController::getAppPath() (or __APP_PATH__ if 'pollute' is enabled)
	Returns the current app in path form. i.e. '/admin'.

	MoorController::getControllerPath() (or __CONTROLLER_PATH__ if 'pullute' is enabled)
	Returns the current controller in path form. i.e. '/admin/user'.

	MoorController::getActionPath() (or __ACTION_PATH__ if 'pollute' is enabled)
	Returns the current action in path form. i.e. '/admin/user/read'.

Here's a simple example of including a view:

	define('VIEW_PATH', $_SERVER['DOCUMENT_ROOT'] . '/../views');

	class SiteUserController extends MoorController {
		function read() {
			include VIEW_PATH . __ACTION_PATH__ . '.html.php';
			// will include VIEW_PATH/site/user/read.html.php;
		}
	}

## Advanced Tips

### Controller Routing/Linking Functions

Writing Moor::* everywhere can get tedious, so there are some shortcut functions defined by Moor.

	route_to() // => Moor::routeTo()
	link_to()  // => Moor::linkTo()
	run()      // => Moor::run()
	
Currently these functions are only available for Controller routing and linking.

### Combining Callbacks/Controller Routes

There is no harm in doing this. Large apps should be structured with Controllers, but sometimes a one off callback action will make for a nicer solution.

	Moor::routeToCallback('#^/[0-9]{10}#', 'parse_member_number');
	// send any URL starting with 10 digits to the parse_member_number callback

	Moor::routeTo('/:controller/:id/:action', 'site');
	Moor::routeTo('/:controller/:action', 'site');
	Moor::routeTo('/:controller', 'site', '*', 'index');

	Moor::run();

### Continue Triggering

Sometimes we need a route that does something, then instead of exiting, moves onto the next route. We can use triggerContinue() to do just that. Here we capture the incoming file extension, then move on:

	// using a PHP 5.3 style callback here
	Moor::routeToCallback('#\.(?P<extension>[a-z]{1,4}$#', function(){
		Moor::triggerContinue(); // go to next defined route.
	});


### 404 Triggering

Oops, that route wasn't found! Well, occasionally it is found, but we don't want the user to know. We can trigger the 404 callback with triggerNotFound().

	class SiteUserController extends MoorController {
		function read() {
			try {
				$user = new User($_GET['id']);
			} catch (Exception $e) {
				Moor::triggerNotFound();
			}
		}
	}
	
## Credits

Designed and programmed by Jeff Turcotte. jeff.turcotte@gmail.com

(c) 2009
