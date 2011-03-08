<?php
/**
 * Ali3: my stuff for lithium framework.
 *
 * @copyright     Copyright 2011, Ali Farhadi (https://github.com/farhadi/ali3)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace ali3\storage;

/**
 * A wrapper for `\lithium\storage\Session` which uses 'default' configuration by default.
 */
class Session extends \lithium\storage\Session {

	protected static $_defaults = array('name' => 'default');

	public static function read($key = null, array $options = array()) {
		$options += static::$_defaults;
		return parent::read($key, $options);
	}

	public static function write($key, $value = null, array $options = array()) {
		$options += static::$_defaults;
		return parent::write($key, $value, $options);
	}

	public static function delete($key, array $options = array()) {
		$options += static::$_defaults;
		return parent::delete($key, $options);
	}

	public static function check($key, array $options = array()) {
		$options += static::$_defaults;
		return parent::check($key, $options);
	}

	public static function clear(array $options = array()) {
		$options += static::$_defaults;
		return parent::clear($options);
	}

	public static function key($name = null) {
		$name = $name ?: static::$_defaults['name'];
		return parent::key($name);
	}

	public static function isValid($name = null) {
		$name = $name ?: static::$_defaults['name'];
		return parent::isValid($name);
	}

	public static function isStarted($name = null) {
		$name = $name ?: static::$_defaults['name'];
		return parent::isStarted($name);
	}

	public static function flash($key = null, array $options = array()) {
		$result = static::read($key, $options);
		static::delete($key, $options);
		return $result;
	}

	public static function adapter($name = null) {
		$name = $name ?: static::$_defaults['name'];
		return parent::adapter($name);
	}

	public static function defaults($defaults = null) {
		if (!$defaults) {
			return static::$_defaults;
		}
		static::$_defaults = $defaults;
	}
}

?>