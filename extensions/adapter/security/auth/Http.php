<?php
/**
 * Ali3: my stuff for lithium framework.
 *
 * @copyright     Copyright 2011, Ali Farhadi (https://github.com/farhadi/ali3)
 * @license       GNU General Public License 3.0 (http://www.gnu.org/licenses/gpl.html)
 */

namespace ali3\extensions\adapter\security\auth;

use lithium\core\Libraries;

/**
 * Database-enabled HTTP authentication adapter.
 * 
 * This adapter provides basic HTTP authentication with users from database.
 * {{{
 * 	Auth::config(array(
 * 		'customer' => array(
 * 			'adapter' => 'Http',
 * 			'model' => 'Customer',
 * 			'fields' => array('email', 'password')
 * 		)
 * 	));
 * }}}
 *
 * This adapter is backward compatible with the original Http adapter. In other words if you specify
 * `users` array in the configurations it doesn't use the database.
 *
 * Note that digest HTTP authentication only works when `users` array is provided and doesn't work
 * with users from database.
 */
class Http extends \lithium\security\auth\adapter\Http {

	/**
	 * The name of the model class to query against. This can either be a model name (i.e.
	 * `'User'`), or a fully-namespaced class reference (i.e. `'app\models\User'`). When
	 * authenticating users, the magic `first()` method is invoked against the model to return the
	 * first record found when combining the conditions in the `$_scope` property with the
	 * authentication data yielded from the `Request` object in `Form::check()`. (Note that the
	 * model method called is configurable using the `$_query` property).
	 *
	 * @see ali3\extensions\adapter\security\auth\Http::$_query
	 * @var string
	 */
	protected $_model = '';

	/**
	 * The list of fields to extract from the `Request` object and use when querying the database.
	 * This can either be a simple array of field names, or a set of key/value pairs, which map
	 * the field names in the request to
	 *
	 * @var array
	 */
	protected $_fields = array();

	/**
	 * Additional data to apply to the model query conditions when looking up users, i.e.
	 * `array('active' => true)` to disallow authenticating against inactive user accounts.
	 *
	 * @var array
	 */
	protected $_scope = array();

	/**
	 * Callback filters to apply to request data before using it in the authentication query. Each
	 * key in the array must match a request field specified in the `$_fields` property, and each
	 * value must either be a reference to a function or method name, or a closure. For example, to
	 * automatically hash passwords, the `Form` adapter provides the following default
	 * configuration, i.e.: `array('password' => array('\lithium\util\String', 'hash'))`.
	 *
	 * Optionally, you can specify a callback with no key, which will receive (and can modify) the
	 * entire credentials array before the query is executed, as in the following example:
	 * {{{
	 * 	Auth::config(array(
	 * 		'members' => array(
	 * 			'adapter' => 'Http',
	 * 			'model' => 'Member',
	 * 			'fields' => array('email', 'password'),
	 * 			'filters' => array(function($data) {
	 * 				// If the user is outside the company, then their account must have the
	 * 				// 'private' field set to true in order to log in:
	 * 				if (!preg_match('/@mycompany\.com$/', $data['email'])) {
	 * 					$data['private'] = true;
	 * 				}
	 * 				return $data;
	 * 			})
	 * 		)
	 * 	));
	 * }}}
	 *
	 * @see ali3\extensions\adapter\security\auth\Http::$_fields
	 * @var array
	 */
	protected $_filters = array('password' => array('\lithium\util\String', 'hash'));

	/**
	 * If you require custom model logic in your authentication query, use this setting to specify
	 * which model method to call, and this method will receive the authentication query. In return,
	 * the `Form` adapter expects a `Record` object which implements the `data()` method. See the
	 * constructor for more information on setting this property. Defaults to `'first'`, which
	 * calls, for example, `User::first()`.
	 *
	 * @see ali3\extensions\adapter\security\auth\Http::__construct()
	 * @see lithium\data\entity\Record::data()
	 * @var string
	 */
	protected $_query = '';

	/**
	 * List of configuration properties to automatically assign to the properties of the adapter
	 * when the class is constructed.
	 *
	 * @var array
	 */
	protected $_autoConfig = array('model', 'fields', 'scope', 'filters' => 'merge', 'query');

	public function __construct(array $config = array()) {
		$defaults = array(
			'model' => 'User', 'query' => 'first', 'filters' => array(), 'fields' => array(
				'username', 'password'
			), 'method' => 'basic'
		);
		parent::__construct($config + $defaults);
	}

	/**
	 * Handler for HTTP Basic Authentication
	 *
	 * @param string $request a `\lithium\action\Request` object
	 * @return void
	 */
	protected function _basic($request) {
		$users = $this->_config['users'];
		$username = $request->env('PHP_AUTH_USER');
		$password = $request->env('PHP_AUTH_PW');
		if ($users) {
			if (isset($users[$username]) && $users[$username] === $password) {
				$user = compact('username', 'password');
			} else {
				$user = null;
			}
		} else {
			$model = $this->_model;
			$query = $this->_query;
			$conditions = $this->_scope + $this->_filters(compact('username', 'password'));
			$user = $model::$query(compact('conditions'));
			if ($user) {
				$user = $user->data();
			}
		}
		if (!$user) {
			$this->_writeHeader("WWW-Authenticate: Basic realm=\"{$this->_config['realm']}\"");
			return;
		}
		return $user;
	}

	protected function _init() {
		parent::_init();

		if (isset($this->_fields[0])) {
			$this->_fields = array_combine(array('username', 'password'), $this->_fields);
		}
		if (!$this->_config['users']) {
			$this->_model = Libraries::locate('models', $this->_model);
		}
	}

	/**
	 * Calls each registered callback, by field name.
	 *
	 * @param string $data Keyed form data.
	 * @return mixed Callback result.
	 */
	protected function _filters($data) {
		$result = array();

		foreach ($this->_fields as $key => $field) {
			$result[$field] = isset($data[$key]) ? $data[$key] : null;
			if (isset($this->_filters[$key])) {
				$result[$field] = call_user_func($this->_filters[$key], $result[$field]);
			}
		}
		return isset($this->_filters[0]) ? call_user_func($this->_filters[0], $result) : $result;
	}
}

?>