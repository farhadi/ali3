<?php
/**
 * Ali3: my stuff for lithium framework.
 *
 * @copyright     Copyright 2011, Ali Farhadi (https://github.com/farhadi/ali3)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
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
 * This is actually a merger between `Form` and `Http` adapters. So all the features of `Form`
 * adpater like `filters` and `validators` are also available in this adapter.
 *
 * This adapter is backward compatible with the original Http adapter. In other words if you specify
 * `users` array in the configurations it doesn't use the database.
 *
 * Note that digest HTTP authentication only works when `users` array is provided and doesn't work
 * with users from database.
 */
class Http extends \lithium\security\auth\adapter\Form {

	/**
	 * Dynamic class dependencies.
	 *
	 * @var array Associative array of class names & their namespaces.
	 */
	protected $_classes = array(
		'auth' => 'lithium\net\http\Auth'
	);


	/**
	 * Setup default configuration options.
	 *
	 * @param array $config
	 *        - `method`: default: `digest` options: `basic|digest`
	 *        - `realm`: default to app name.
	 *        - `users`: the users to permit. key => value pair of username => password
	 *        - `'model'` _string_: The name of the model class to use. See the `$_model`
	 *          property for details.
	 *        - `'fields'` _array_: The model fields to query against when taking input from
	 *          the request data. See the `$_fields` property for details.
	 *        - `'scope'` _array_: Any additional conditions used to constrain the
	 *          authentication query. For example, if active accounts in an application have
	 *          an `active` field which must be set to `true`, you can specify
	 *          `'scope' => array('active' => true)`. See the `$_scope` property for more details.
	 *        - `'filters'` _array_: Named callbacks to apply to request data before the user
	 *          lookup query is generated. See the `$_filters` property for more details.
	 *        - `'validators'` _array_: Named callbacks to apply to fields in request data and
	 *          corresponding fields in database data in order to do programmatic
	 *          authentication checks after the query has occurred. See the `$_validators`
	 *          property for more details.
	 *        - `'query'` _string_: Determines the model method to invoke for authentication
	 *          checks. See the `$_query` property for more details.
	 */
	public function __construct(array $config = array()) {
		$realm = basename(Libraries::get(true, 'path'));
        $defaults = $config + array(
            'method' => 'basic', 
            'realm' => $realm,
            'users' => array(),
        );
		parent::__construct($defaults);
	}

	protected function _init() {
		$this->_config['fields'] = array_combine(
			array('username', 'password'),
			$this->_config['fields']
		);

		parent::_init();
	}

	/**
	 * Called by the `Auth` class to run an authentication check against the HTTP data using the
	 * credientials in a data container (a `Request` object), and returns an array of user
	 * information on success, or `false` on failure.
	 *
	 * @param object $request A env container which wraps the authentication credentials used
	 *               by HTTP (usually a `Request` object). See the documentation for this
	 *               class for further details.
	 * @param array $options Additional configuration options. Not currently implemented in this
	 *              adapter.
	 * @return array Returns an array containing user information on success, or `false` on failure.
	 */
	public function check($request, array $options = array()) {
		$method = "_{$this->_config['method']}";
		return $this->{$method}($request, $options);
	}

	/**
	 * Handler for HTTP Basic Authentication
	 *
	 * @param string $request a `\lithium\action\Request` object
	 * @return void
	 */
	protected function _basic($request, $options) {
		$users = $this->_config['users'];
		$username = $request->env('PHP_AUTH_USER');
		$password = $request->env('PHP_AUTH_PW');

		if ($users) {
			if (isset($users[$username]) && $users[$username] === $password) {
				$user = compact('username', 'password');
			} else {
				$user = false;
			}
		} else {
			$data = compact('username', 'password');
			$user = parent::check((object) compact('data'), $options);
		}
		if (!$user) {
			$this->_writeHeader("WWW-Authenticate: Basic realm=\"{$this->_config['realm']}\"");
			return;
		}

		return $user;
	}

	/**
	 * Handler for HTTP Digest Authentication
	 *
	 * @param string $request a `\lithium\action\Request` object
	 * @return void
	 */
	protected function _digest($request, $options) {
		$username = $password = null;
		$auth = $this->_classes['auth'];
		$data = $auth::decode($request->env('PHP_AUTH_DIGEST'));
		$data['realm'] = $this->_config['realm'];
		$data['method'] = $request->method;
		$users = $this->_config['users'];

		if (!empty($data['username']) && !empty($users[$data['username']])) {
			$username = $data['username'];
			$password = $users[$data['username']];
		}
		$encoded = $auth::encode($username, $password, $data);

		if ($encoded['response'] !== $data['response']) {
			$nonce = uniqid();
			$opaque = md5($data['realm']);
			$message = "WWW-Authenticate: Digest realm=\"{$data['realm']}\",qop=\"auth\",";
			$message .= "nonce=\"{$nonce}\",opaque=\"{$opaque}\"";
			$this->_writeHeader($message);
			return false;
		}
		return compact('username', 'password');
	}

	/**
	 * Helper method for writing headers. Mainly used to override the output while testing.
	 *
	 * @param string $string the string the send as a header
	 * @return void
	 */
	protected function _writeHeader($string) {
		header($string, true);
	}
}

?>