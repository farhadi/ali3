This is my own library of extensions, helpers and other stuff for [Lithium](http://lithify.me)
framework which I use across my li3 projects.
You can use, distribute or modify them under the terms of BSD license.


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

This is actually a merger between `Form` and `Http` adapters. So all the features of `Form` adpater
like `filters` and `validators` are also available in this adapter.

This adapter is backward compatible with the original Http adapter. In other words it doesn't use
the database if you specify `users` array in the configurations.

Note that digest HTTP authentication only works when `users` array is provided and doesn't work
with users from database.


Globalized Date Class
---------------------

`ali3\g11n\Date` is an extented version of php's DateTime class with integrated IntlDateFormatter
functionality which adds support for multiple calendars and locales provided by ICU project.

`timezone`, `calendar`, and `locale` options are defaulted to your application configurations
retrieved from Environment class.

	use ali3\g11n\Date;

	// If you don't pass `$date` parameter, current time will be used.
	$date = new Date();

	// You can pass a `strtotime()` compatible string as `$date` parameter
	$date = new Date('2011-03-08');
	$date = new Date('2011-03-08 12:24:28');
	$date = new Date('yesterday');

	// You can pass a timestamp or a DateTime object as `$date` parameter
	$date1 = new Date(time() - 86400);
	$date2 = new Date($date1);

	// This will output a localized formatted date, depending on your application configurations.
	echo $date->format('yyyy-MM-dd hh:mm:ss');

	// You can create a Date object from a localized date string depending on your app configs.
	$date = new Date('۱۳۸۹-۱۲-۱۷ ۱۱:۳۳');

	// you can override default options by passing any or all of the options.
	$date = new Date('۱۳۸۹-۱۲-۱۷ ۱۱:۳۳', array('locale' => 'fa', 'calendar' => 'persian'));

	// You can override options when formatting, without altering object's internal options.
	echo $date->format('yyyy-MM-dd hh:mm:ss', array(
		'calendar' => 'gregorain',
		'locale' => 'en',
		'timezone' => 'UTC',
	));

	// You can alter object internal options
	$date->locale('ar');
	$date->calendar('islamic-civil');

	// And retrieve them
	$locale = $date->locale();
	$calendar = $date->calendar();

	// All other php DateTime methods are available
	$time = $date->getTimestamp();
	$date->setTimestamp($time);
	$date->setTimezone('UTC');

	// `modify` method is calendar-aware.
	// It depends on the number of days in month/year of that specific calendar.
	$date->modify('+1 year +2 month');

For more information on date pattern that `Date::format()` accepts see this:
http://userguide.icu-project.org/formatparse/datetime


Adaptable Config Class
----------------------

It is very common that you store some of your application configurations in database or
in configuration files (e.g. Website title, default template, default locale, etc.).
And to avoid preformance leaks you need to cache your configurations in memory.

In such situations ali3's adaptable Config comes in handy.

To start using it, you need to add a config.php in your bootstrap:

	use ali3\storage\Config;

	Config::config(array(
		'default' => array(
			'adapter' => 'Db',
			'model' => 'Configs',
			'fields' => array('name', 'value'),
			'cache' => array(
				'name' => 'default'
			),
		),
	));

If you didn't want to cache your configs just remove `'cache'` from configuration options.

Now every where in your application you can use `read()`, `write()`, and
`delete()` methods to access or modify your configs.

For example:

	//To read a config:
	$siteTitle = Config::read('default', 'site_title');
	//To write a config:
	Config::write('default', 'site_title', $newTitle);
	//To delete a config:
	Config::delete('default', 'site_title');

There is also shortcut methods for reading and writing configs:

	//Using shortcut method to read a config named 'site_title':
	$siteTitle = Config::siteTitle();
	//Using shortcut method to write a config named 'site_title':
	Config::siteTitle($newTitle);

Using shortcut methods, always your first adapter configuration will be used.
This is by design to make shortcuts shorter. Because in most of the cases just one
adapter configuration is defined.

For now only `Db` adapter is available. but you can write your own adapters if needed.
For example you can write an adapter to read configs from .ini files.


Data Grid
---------

To create a data grid, first you need to create an instance of `ali3\data\Grid` inside
your controller. Grid instances are descendants of `\lithium\util\Collection` and can be modified
using `each()` method also can be encoded as json.

For example to create a grid of your Posts model first write the controller code:

	use ali3\data\Grid;

	class PostsController extends \lithium\action\Controller {

		public function index() {
			$posts = new Grid(array(
				'model' => 'Posts',
				'request' => $this->request,
				'conditions' => array('published' => true),
				'fields' => array('id', 'slug', 'title', 'body'),
			));
			return compact('posts');
		}
	}

Then use grid helper inside the view file:

	// You can modify $posts before rendering grid.
	$context = $this;
	$posts->each(function($row) use ($context) {
		if (str_len($row['body']) > 50) {
			$link = $context->html->link('...', array('Posts::view', 'slug' => $row['slug']));
			$row['body'] = substr($row['body'], 0, 50) . ' ' . $link;
		}
		return $row;
	});

	// render method renders a grid using HTML table.
	echo $this->grid->render($posts, array(
		'hidden' => array('id', 'slug'),
		'titles' => array('body' => 'Post Body'),
		'actions' => array(
			'view' => array(
				'url' => function($row) {
					return array('Posts::view', 'slug' => $row['slug']);
				}
			),
			'edit',
			'delete' => array(
				'confirm' => 'Are you sure you want to delete post "{:title}"?',
			)
		);
	));

If you don't want the default table view write your own custom view:

	foreach ($posts as $post) {
		echo 'Title: ' . $post['title'] . '<br />';
		echo 'Body: ' . $post['body'] . '<br />';
	}
	echo $this->grid->pages($posts);


G11n Translate Command
----------------------

Using g11n translate command you can translate your messages template to other languages using
Google Translate API.

All you need is just to use the console command `li3 g11n translate`. Then it asks for your
catalog configuration and your Google API key then shows a list of available target languages for
you to choose.

Note that there is a limitation of 100,000 characters per day to use Google Translate API.


FireLogger log adapter
----------------------

`FireLogger` adapter allows you to log messages to [FireLogger](http://firelogger.binaryage.com/).

This allows you to inspect native PHP values and objects inside the FireBug console.

Because this adapter interacts directly with the `Response` object, some additional code is
required to use it. The simplest way to achieve this is to add a filter to the `Dispatcher`. For
example, the following can be placed in a bootstrap file:

	use lithium\action\Dispatcher;
	use lithium\analysis\Logger;

	Logger::config(array(
		'default' => array('adapter' => 'FireLogger')
	));

	Dispatcher::applyFilter('_call', function($self, $params, $chain) {
		if (isset($params['callable']->response)) {
			Logger::adapter('default')->bind($params['callable']->response);
		}
		return $chain->next($self, $params, $chain);
	});

This will cause the message and other debug settings added to the header of the
response, where FireLogger is able to locate and print it accordingly. As this adapter
implements the protocol specification directly, you don't need another vendor library to
use it.

Now, you can use the logger in your application code (like controllers, views and models).

	class PagesController extends \lithium\action\Controller {
		public function view() {
			//...
			Logger::error("Something bad happened!");
			//...
		}
	}

Because this adapter also has a queue implemented, it is possible to log messages even when the
`Response` object is not yet generated. When it gets generated (and bound), all queued messages
get flushed instantly.

Because FireLogger is not a conventional logging destination like a file or a database, you can
pass everything to the logger and inspect it further in FireLogger. In fact,
every message that is passed will be encoded via `json_encode()`, so check out this built-in
method for more information on how your message will be encoded.

	Logger::debug(array('debug' => 'me'));
	Logger::debug(new \lithium\action\Response());