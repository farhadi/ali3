<?php
/**
 * Ali3: my stuff for lithium framework.
 *
 * @copyright     Copyright 2011, Ali Farhadi (https://github.com/farhadi/ali3)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace ali3\storage;

/**
 * A wrapper for `\lithium\storage\Session` which uses 'cookie' configuration by default.
 */
class Cookie extends \ali3\storage\Session {

	protected static $_defaults = array('name' => 'cookie');
}

?>