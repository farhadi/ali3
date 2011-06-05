<?php
/**
 * Ali3: my stuff for lithium framework.
 *
 * @copyright     Copyright 2011, Ali Farhadi (https://github.com/farhadi/ali3)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace ali3\extensions\helper;

use ali3\storage\Session;

/**
 * Flash Message Helper
 *
 * Using this helper you can show flash messages in your views usually after submitting forms.
 *
 * Example usage:
 *
 * Inside controller:
 * {{{
 * //using ali3\storage\Session
 * Session::write('Auth.message', 'Invalid password.');
 * }}}
 *
 * Inside view:
 * {{{
 * echo $this->message->flash('Auth.message');
 * //output: <div class="message">Invalid password.</div>
 * echo $this->message->flash('Auth.message', array('id' => 'flash', 'class' => 'message error'));
 * //output: <div id="flash" class="message error">Invalid password.</div>
 * }}}
 */
class Message extends \lithium\template\Helper {

	/**
	 * String templates used by this helper.
	 *
	 * @var array
	 */
	protected $_strings = array(
		'block' => '<div{:options}>{:content}</div>',
	);

	public function flash($key, $options = array()) {
		$content = Session::flash($key);
		if (!$content) {
			return '';
		}
		return $this->show($content, $options);
	}

	public function show($content, $options = array()) {
		$options += array('class' => 'message');
		return $this->_render(__METHOD__, 'block', compact('content', 'options'));
	}
}

?>