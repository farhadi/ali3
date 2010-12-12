<?php
/**
 * Ali3: my stuff for lithium framework.
 *
 * @copyright     Copyright 2011, Ali Farhadi (https://github.com/farhadi/ali3)
 * @license       GNU General Public License 3.0 (http://www.gnu.org/licenses/gpl.html)
 */

namespace ali3\storage;

/**
 * A wrapper for `\lithium\storage\Session` which uses 'cookie' configuration by default.
 */
class Cookie extends \ali3\storage\Session {

	protected static $_defaults = array('name' => 'cookie');
}

?>