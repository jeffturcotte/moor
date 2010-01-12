# Moor

- Introduction
- Concepts To Understand
- Hello World
- Hello :anybody
- Hello /BUT I NEED TO MATCH A SPECIFIC PATTERN!/
- Initial Set Up
- URL Routing
- Parameter Extraction
- Callback Wildcards
- Link Generation
- 404 Callback
- Debugging
- Built-In Controllers (DOCS COMING SOON)
	- MoorAbstractController 
	- MoorActionController 
		- Action Permissions 
		- Magic Methods 
		- Location Helpers 
		- Path Helpers 
- Advanced Tips (DOCS COMING SOON)
	- Path Helpers
	- 404 Triggering
	- Continue Triggering
- Credits

## Warning

In the process of being testing. Please wait a few days. :-)

## Introduction

Moor is URL routing & controller library for PHP 5. It performs 2 actions very well: routing URLs to callbacks and generating URLs for callbacks.

While an understanding of MVC will help your grasp Moor's role in your application, Moor is not an MVC framework. It's a library that only handles routing and linking. Use your favorite ORM for your Models and your favorite templating system for your Views. If you don't need or want these pieces, write cool stuff without them.

This is currently beta software. It is released under the MIT license.

## Concepts To Understand

If you don't have a basic understanding of the following concepts/techniques, you will be at a severe disadvantage. Please read up if you have to.

*MVC*
http://en.wikipedia.org/wiki/Model-view-controller

*Regular Expression Named Groups*
http://www.regular-expressions.info/named.html
	
## Hello World

	<?php
	Moor::route('/hello', 'hello_world');
	
	function hello_world() {
		echo 'HELLO WORLD!';
	}
	
	Moor::run();
	?>
	
## Hello :anybody

	<?php
	// use a shorthand expression. :name will match [A-Za-z0-9_-]+ in a URL pattern
	
	Moor::route('/hello/:name', 'hello_anybody');
	
	function hello_anybody() {
		echo 'HELLO ' . strtoupper($_GET['name']);
	}
	
	Moor::run();
	?>
	
## Hello "BUT I NEED TO MATCH A SPECIFIC PATTERN!!!"

	<?php 
	// Use a regex with named groups. ** start a regex with 'preg:' **

	Moor::route('preg:#^/hello/(?P<name>[A-Z]+)$#', 'hello_specific');
	
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
	
In this file, you can load all your libraries, configure your app(s), and define your Moor options/routes

## URL Routing

The primary feature of Moor is routing URLs to a callback. This is achieved with the Moor::route method. Moor::route takes 3 parameters: a URL definition, and a PHP Callback. The PHP Callback can be any valid PHP callback: function or method. For security reasons, if calling a method, the class must extend MoorAbstractController (or another built-in controller class.)

	// Will run function 'home_page' on /
	Moor::route('/', 'home_page');

	// Will run method 'Apps::list' on /apps
	// Remember! Class Apps should extend (at least) MoorAbstractController
	Moor::route('/apps', 'Apps::list'); 
	
	// For callback namespacing, always use 5.3 style
	// In 5.3: Will run method 'Site\Home::index'
	// In 5.2: Will run method 'Site_Home::index'
	Moor::route('/my-site', 'Site\Home::index');
	
	// Alternate syntax
	route('/', 'home_page');
	
Sometimes we need to match on a regular expression and not a simple string, supply a regex by prepending the URL definition with 'preg:'

	Moor::route('preg:#^/custom_urls/(\d+)/(\d+)$#', 'my_callback');
	
URL routing is triggered when Moor::run is called.

	Moor::run();
	// or run();
	
## Parameter Extraction

Parameters can be extracted from URLs by using a (ruby) symbol-like string.

	// Will match /[0-9A-Za-z_-]+ and put the value of 'name' in $_GET
	Moor::route('/:name', 'my_callback');
		
	// other examples	
	Moor::route('/users/:id', 'MyApp\User::read');

To extract parameters from a regular expression url definition, use named groups:

	// $_GET['id'] will be extracted from the url 
	Moor::route('preg:#^/users/(?P<id>\d+)$#', 'MyApp\User::read');


## Callback Wildcards

A lot of times the callbacks provided won't (and shouldn't) be static, so we have to allow wildcards. There are 4 special parameters to use in a url definition to help generate dynamic callbacks:

	@namespace  // => converted to UpperCamelCase for the callback
	@class      // => converted to UpperCamelCase for the callback
	@function   // => converted to lowerCamelCase for the callback
	@method     // => converted to lowerCamelCase for the callback

	// @function and @method are interchangeable, but are both available for semantic reasons. 
	
If we use one of these parameters in our url definition, we only need to replace the callback piece we want to be dynamic within the callback argument.

	Moor::route('/@class/:id/@method', '*::*');
	// incoming /user/4/delete resolves to User::delete AND $_GET['id'] = 4
	// incoming /user/5/activate resolves to User::activate AND $_GET['id'] = 5
	// incoming /groups/3/update resolves to Groups::update AND $_GET['id'] = 3
	
	Moor::route('/@class/:id, '*::read');
	// incoming /user/4 resolves to User::read and $_GET['id] = 4
	
Some dangerous patterns aren't ever allowed:

	Moor::route('/@function, '*');
	// won't throw an error, but will will add a debug message and not parse the route. 


## Link Generation

If we are defining our links with the route method, why should we have to write them over and over throughout our application? The answer is: we shouldn't. Let's say we define the following routes:

	Moor::route('/@class/:id/@action', '*::*');
	Moor::route('/@class/:id', '*::read');
	Moor::route('/@class', '*::index');
	
We can now link to these with the Moor::linkTo method (or alternatively link\_to). This method takes the callback we want to link to and the GET parameters to pass.

	Moor::linkTo('Users::delete', array('id' => 1)); 
	// generates /users/1/delete
	
	Moor::linkTo('Users::read', array('id' => 200));
	// generates /users/200
	
	Moor::linkTo('Users::index');
	// generates /users
	
	Moor::linkTo('Users::update', array('id' => 17, 'name' => 'john'));
	// generates /users/17/update?name=john
	
As you can see in the last example above, any GET params not found in the url will be appended to the end as a query string. If the route cannot be found, Moor::linkTo will return '#'.

## 404 Callback

There is a default callback that is run when a route cannot be found: Moor::routeNotFoundCallback. This can be changed with:

	Moor::setNotFoundCallback('your_own_404_callback');
	
Please note that the default not found callback can optionally display debugging information, so if you still wanted debug messages, you would need to add this functionality to your custom callback.

## Debugging

Enable debugging on the default 404 page with:

	Moor::setDebug(TRUE);
	// you can also check if debugging is 
	// enabled with: Moor::getDebug()
	
Debugging is off by default. If you are creating your own 404 page, you can get an array of all debug messages with:

	Moor::getMessages();
	
## Built-In Controllers

Coming Soon

## Advanced Tips

Coming Soon
	
## Credits

Designed and programmed by Jeff Turcotte. jeff.turcotte@gmail.com

(c) 2010
