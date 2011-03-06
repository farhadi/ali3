This is my own library of extensions, helpers and other stuff for [Lithium](http://lithify.me)
framework which I use across my li3 projects.
You can use, distribute or modify them under the terms of GPL license.


How to use?
-----------

Clone it from github and put it inside your `libraries` folder:

	git clone git://github.com/farhadi/ali3.git

Add the following line in your `config/bootstrap/libraries.php`:

	Libraries::add('ali3');


Session and Cookie classes
--------------------------

In lithium both sessions and cookies are handled through configurations for Session class and
every time you call a Session method you need to pass a `name` option to specify which
configuration you want to use.

To simplify it I extended the original Session class in the following classes:


### ali3\storage\Session

This works just like original Session class except that if you don't pass configuration name
it will use `default` configuration  by default.
Also there is a bonus `flash` method which is equivalent to calling `read` followed by `delete`.


### ali3\storage\Cookie

This is the same as the above except that it uses `cookie` configuration by default.


Localized route extension
-------------------------

Using this extension all your urls will be prefixed by locale (e.g. /en/controller/action).
To use it first make sure g11n.php is loaded in your bootstrap.php and then add the following
line to your routes.php before connecting your routes:

	Router::config(array('classes' => array('route' => 'ali3\extensions\route\Localized')));

Now every time you call `Router::connect()` this class will be used instead of the original
lithium Route class.


Flash Message Helper
--------------------

Using this helper you can show flash messages in your views usually after submitting forms.

Example usage:

Inside controller:

	//using ali3\storage\Session
	Session::write('Auth.message', 'Invalid password.');

Inside view:

	echo $this->message->flash('Auth.message');
	//output: <div class="message">Invalid password.</div>
	echo $this->message->flash('Auth.message', array('id' => 'flash', 'class' => 'message error'));
	//output: <div id="flash" class="message error">Invalid password.</div>


Database-enabled HTTP authentication adapter
--------------------------------------------

This adapter provides basic HTTP authentication with users from database.

	Auth::config(array(
		'customer' => array(
			'adapter' => 'Http',
			'model' => 'Customer',
			'fields' => array('email', 'password')
		)
	));

This adapter is backward compatible with the original Http adapter. In other words if you specify
`users` array in the configurations it doesn't use the database.

Note that digest HTTP authentication only works when `users` array is provided and doesn't work
with users from database.