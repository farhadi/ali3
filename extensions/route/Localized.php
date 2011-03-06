<?php
/**
 * Ali3: my stuff for lithium framework.
 *
 * @copyright     Copyright 2011, Ali Farhadi (https://github.com/farhadi/ali3)
 * @license       GNU General Public License 3.0 (http://www.gnu.org/licenses/gpl.html)
 */

namespace ali3\extensions\route;

use lithium\core\Environment;

/**
 * Localized route extension
 *
 * Using this extension all your urls will be prefixed by locale (e.g. /en/controller/action).
 * To use it first make sure g11n.php is loaded in your bootstrap.php and then add the following
 * line to your routes.php before connecting your routes:
 * {{{
 * Router::config(array('classes' => array('route' => 'ali3\extensions\route\Localized')));
 * }}}
 * Now every time you call `Router::connect()` this class will be used instead of the original
 * lithium Route class.
 */
class Localized extends \lithium\net\http\Route {

	protected function _init() {
		$locales = implode('|', array_keys(Environment::get('locales')));
		$this->_config['template'] = "/{:locale:$locales}" . rtrim($this->_config['template'], '/');
		$this->_config['params'] += array('locale' => null);
		array_push($this->_config['persist'], 'locale', 'controller');
		if (!empty($this->_config['params']['admin'])) {
			$this->_config['persist'][] = 'admin';
		}
		parent::_init();
	}
}

?>