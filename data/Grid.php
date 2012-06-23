<?php
/**
 * Ali3: my stuff for lithium framework.
 *
 * @copyright     Copyright 2011, Ali Farhadi (https://github.com/farhadi/ali3)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace ali3\data;

use lithium\core\Libraries;

class Grid extends \lithium\util\Collection {

	protected $_autoConfig = array('model', 'request', 'data');

	protected $_model = null;

	protected $_request = null;

	protected function _init() {
		parent::_init();
		if ($this->_request) {
			$this->_config = static::options($this->_request, $this->_config) + $this->_config;
		}
		if (!$this->_model) {
			return;
		}
		$model = $this->_model = Libraries::locate('models', $this->_model);
		$this->_config += array('conditions' => null);
		$options = array_intersect_key($this->_config, array(
			'conditions' => null,
			'fields' => null,
			'page' => null,
			'limit' => null,
			'order' => null,
		));
		$this->_data = $model::all($options)->data();
	}

	public function total() {
		if (isset($this->_config['total'])) {
			if (is_callable($this->_config['total'])) {
				$this->_config['total'] = $this->_config['total']();
			}
		} elseif ($this->_model) {
			$model = $this->_model;
			$conditions = $this->_config['conditions'];
			$this->_config['total'] = $model::count(compact('conditions'));
		} else {
			$this->_config['total'] = null;
		}
		return $this->_config['total'];
	}

	public function pages() {
		if (!empty($this->_config['limit']) && $total = $this->total()) {
			return ceil($total / $this->_config['limit']);
		}
	}

	public static function options($request, $options = array()) {
		$defaults = array(
			'page' => 1,
			'limit' => 20,
			'order' => null,
		);
		$defaults = array_intersect_key($options, $defaults) + $defaults;
		$options = array_intersect_key($request->query, $defaults) + $options + $defaults;
		$options['page'] = intval($options['page']);
		if ($options['page'] < 1) {
			$options['page'] = $defaults['page'];
		} elseif (isset($options['maxPage']) && $options['page'] > $options['maxPage']) {
			$options['page'] = $options['maxPage'];
		}
		$options['limit'] = intval($options['limit']);
		if ($options['limit'] < 1) {
			$options['limit'] = $defaults['limit'];
		} elseif (isset($options['maxLimit']) && $options['limit'] > $options['maxLimit']) {
			$options['limit'] = $options['maxLimit'];
		}
		if (!static::_isOrderValid($options['order'], $options)) {
			$options['order'] = null;
		}

		return array_intersect_key($options, $defaults);
	}

	protected static function _isOrderValid($order, $options) {
		if (!isset($options['validOrders']) || !$order) {
			return true;
		}
		if (is_array($order)) {
			foreach ($order as $key => $field) {
				$orderFields[] = is_int($key) ? $field : $key;
			}
		} else {
			$orderFields = array($order);
		}
		foreach ($options['validOrders'] as $fields) {
			if ((array)$fields === $orderFields) {
				 return true;
			}
		}
		return false;
	}

	public function isOrderValid($order) {
		return static::_isOrderValid($order, $this->_config);
	}

	public function __call($method, $params = array()) {
		return isset($this->_config[$method]) ? $this->_config[$method] : null;
	}
}

?>