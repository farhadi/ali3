<?php
/**
 * Ali3: my stuff for lithium framework.
 *
 * @copyright     Copyright 2011, Ali Farhadi (https://github.com/farhadi/ali3)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace ali3\storage;

use lithium\util\Inflector;

class Config extends \lithium\core\Adaptable {

	/**
	 * Stores configurations for cache adapters
	 *
	 * @var object Collection of cache configurations
	 */
	protected static $_configurations = array();

	/**
	 * Libraries::locate() compatible path to adapters for this class.
	 *
	 * @var string Dot-delimited path.
	 */
	protected static $_adapters = 'adapter.storage.config';

	/**
	 * Libraries::locate() compatible path to strategies for this class.
	 *
	 * @var string Dot-delimited path.
	 */
	protected static $_strategies = 'strategy.storage.config';

	/**
	 * Dynamic class dependencies.
	 *
	 * @var array Associative array of class names & their namespaces.
	 */
	protected static $_classes = array(
		'cache' => '\lithium\storage\Cache'
	);

	/**
	 * Called when an adapter configuration is first accessed, this method sets the default
	 * configuration for caching. While each configuration can use its own cache class
	 * and options, this method initializes them to the default dependencies written into the class.
	 *
	 * @param string $name The name of the adapter configuration being accessed.
	 * @param array $config The user-specified configuration.
	 * @return array Returns an array that merges the user-specified configuration with the
	 *         generated default values.
	 */
	protected static function _initConfig($name, $config) {
		$config = parent::_initConfig($name, $config);
		$config['filters'] += array(
			'read' => array(),
			'write' => array(),
			'delete' => array(),
		);
		if (!empty($config['cache'])) {
			$cache = array(
				'name' => 'default',
				'class' => static::$_classes['cache'],
				'prefix' => 'config.' . $name . '.'
			);
			if (!is_array($config['cache'])) {
				$config['cache'] = $cache;
			} else {
				$config['cache'] += $cache;
			}
			$config['filters']['read'][] = function($self, $params, $chain) {
				extract($params);
				$config = $self::config($name);
				$cache = $config['cache'];
				$value = $cache['class']::read($cache['name'], $cache['prefix'].$key);
				if ($value) {
					return $value;
				}
				$value = $chain->next($self, $params, $chain);
				$cache['class']::write($cache['name'], $cache['prefix'].$key, $value);
				return $value;
			};
			$config['filters']['write'][] = function($self, $params, $chain) {
				extract($params);
				$config = $self::config($name);
				$cache = $config['cache'];
				$cache['class']::write($cache['name'], $cache['prefix'].$key, $value);
				return $chain->next($self, $params, $chain);
			};
			$config['filters']['delete'][] = function($self, $params, $chain) {
				extract($params);
				$config = $self::config($name);
				$cache = $config['cache'];
				$cache['class']::delete($cache['name'], $cache['prefix'].$key);
				return $chain->next($self, $params, $chain);
			};
		}
		return $config;
	}

	public static function  __callStatic($method, $arguments) {
		$key = Inflector::underscore($method);
		$name = key(static::config());
		if ($arguments) {
			return static::write($name, $key, $arguments[0]);
		} else {
			return static::read($name, $key);
		}
	}

	public static function read($name, $key, array $options = array()) {
		$settings = static::_config($name);
		$params = compact('name', 'key', 'options');
		$filters = $settings['filters'][__FUNCTION__];
		return static::_filter(__FUNCTION__, $params, function($self, $params) {
			extract($params);
			return $self::adapter($name)->read($key, $options);
		}, $filters);
	}

	public static function write($name, $key, $value, array $options = array()) {
		$settings = static::_config($name);
		$params = compact('name', 'key', 'value', 'options');
		$filters = $settings['filters'][__FUNCTION__];
		return static::_filter(__FUNCTION__, $params, function($self, $params) {
			extract($params);
			return $self::adapter($name)->write($key, $value, $options);
		}, $filters);
	}

	public static function delete($name, $key, array $options = array()) {
		$settings = static::_config($name);
		$params = compact('name', 'key', 'options');
		$filters = $settings['filters'][__FUNCTION__];
		return static::_filter(__FUNCTION__, $params, function($self, $params) {
			extract($params);
			return $self::adapter($name)->delete($key, $options);
		}, $filters);
	}
}

?>