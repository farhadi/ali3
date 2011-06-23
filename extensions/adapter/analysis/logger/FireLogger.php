<?php
/**
 * Ali3: my stuff for lithium framework.
 *
 * @copyright     Copyright 2011, Ali Farhadi (https://github.com/farhadi/ali3)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace ali3\extensions\adapter\analysis\logger;

use Exception;

/**
 * The `FireLogger` allows you to log messages to [FireLogger](http://firelogger.binaryage.com/).
 *
 * This allows you to inspect native PHP values and objects inside the FireBug console.
 *
 * Because this adapter interacts directly with the `Response` object, some additional code is
 * required to use it. The simplest way to achieve this is to add a filter to the `Dispatcher`. For
 * example, the following can be placed in a bootstrap file:
 *
 * {{{
 * use lithium\action\Dispatcher;
 * use lithium\analysis\Logger;
 *
 * Logger::config(array(
 * 	'default' => array('adapter' => 'FireLogger')
 * ));
 *
 * Dispatcher::applyFilter('_call', function($self, $params, $chain) {
 * 	if (isset($params['callable']->response)) {
 * 		Logger::adapter('default')->bind($params['callable']->response);
 * 	}
 * 	return $chain->next($self, $params, $chain);
 * });
 * }}}
 *
 * This will cause the message and other debug settings added to the header of the
 * response, where FireLogger is able to locate and print it accordingly. As this adapter
 * implements the protocol specification directly, you don't need another vendor library to
 * use it.
 *
 * Now, you can use the logger in your application code (like controllers, views and models).
 *
 * {{{
 * class PagesController extends \lithium\action\Controller {
 * 	public function view() {
 * 		//...
 * 		Logger::error("Something bad happened!");
 * 		//...
 * 	}
 * }
 * }}}
 *
 * Because this adapter also has a queue implemented, it is possible to log messages even when the
 * `Response` object is not yet generated. When it gets generated (and bound), all queued messages
 * get flushed instantly.
 *
 * Because FireLogger is not a conventional logging destination like a file or a database, you can
 * pass everything to the logger and inspect it further in FireLogger. In fact,
 * every message that is passed will be encoded via `json_encode()`, so check out this built-in
 * method for more information on how your message will be encoded.
 *
 * {{{
 * Logger::debug(array('debug' => 'me));
 * Logger::debug(new \lithium\action\Response());
 * }}}
 *
 * @see lithium\action\Response
 * @see lithium\net\http\Message::headers()
 * @link http://firelogger.binaryage.com/ FireLogger
 * @link https://github.com/darwin/firelogger/wiki FireLogger Protocol Reference
 * @link http://php.net/manual/en/function.json-encode.php PHP Manual: `json_encode()`
 */
class FireLogger extends \lithium\core\Object {

	/**
	 * This is a mapping table that maps Lithium log levels to FireLogger log levels as they
	 * do not correlate directly and FireLogger only accepts a distinct set.
	 *
	 * @var array
	 */
	protected $_levels = array(
		'emergency' => 'critical',
		'alert'	 => 'critical',
		'critical'  => 'critical',
		'error'	 => 'error',
		'warning'   => 'warning',
		'notice'	=> 'info',
		'info'	  => 'info',
		'debug'	 => 'debug'
	);

	/**
	 * PHP is really fast, timestamp has insufficient resolution for log records ordering.
	 *
	 * @var integer
	 */
	protected $_counter = 1;

	/**
	 * Holds the response object where the headers will be inserted.
	 */
	protected $_response = null;

	/**
	 * Contains messages that have been written to the log before the bind() call.
	 */
	protected $_queue = array();

	public function __construct(array $config = array()) {
		$defaults = array(
			'encoding' => 'UTF-8',
			'maxDepth' => 10,
		);
		parent::__construct($config + $defaults);
	}

	/**
	 * Binds the response object to the logger.
	 *
	 * @param object $response An instance of a response object (usually `lithium\action\Response`)
	 *			   with HTTP request information.
	 * @return void
	 */
	public function bind($response) {
		$this->_response = $response;
		$this->_write($this->_queue);
	}

	/**
	 * Appends a log message to the response header for FireLogger.
	 *
	 * @param string $priority Represents the message priority.
	 * @param string $message Contains the actual message to store.
	 * @return boolean Always returns `true`. Note that in order for message-writing to take effect,
	 *				 the adapter must be bound to the `Response` object instance associated with
	 *				 the current request. See the `bind()` method.
	 */
	public function write($priority, $message) {
		$_self =& $this;

		return function($self, $params) use (&$_self) {
			$priority = $params['priority'];
			$message = $params['message'];
			$options = $params['options'];
			$message = $_self->invokeMethod('_format', array($priority, $message, $options));
			$_self->invokeMethod('_write', array($message));
			return true;
		};
	}

	/**
	 * Helper method that writes the message to the header of a bound `Response` object. If no
	 * `Response` object is bound when this method is called, it is stored in a message queue.
	 *
	 * @see ali3\extensions\adapter\analysis\logger\FireLogger::_format()
	 * @param array $message A message containing the key and the content to store.
	 * @return void
	 */
	protected function _write($message) {
		if (!$this->_response) {
			return $this->_queue += $message;
		}
		$this->_response->headers += $message;
	}

	/**
	 * Generates the array of headers representing the type and message, suitable for FireLogger.
	 *
	 * @param string $priority Represents the message priority.
	 * @param string $message Contains the actual message to store.
	 * @return array Returns the array of headers representing the priority and message.
	 */
	protected function _format($type, $message, $options) {
		$options += $this->_config;
		$time = microtime(true);
		$item = array(
			'args' => array($message),
			'level' => $this->_levels[$type],
			'name' => 'lithium',
			'timestamp' => $time,
			'order' => $this->_counter++,
			'time' => date('H:i:s', (int)$time) . '.' . substr(fmod($time, 1.0), 2, 3),
			'template' => '',
			'message' => '',
		);
		if ($message instanceof Exception) {
			$trace = $message->getTrace();
			$ti = static::_extractTrace($trace);
			$item['exc_info'] = array(
				$message->getMessage(),
				$message->getFile(),
				$ti[0]
			);
			$item['exc_frames'] = $ti[1];
			$item['exc_text'] = 'exception';
			$item['template'] = $message->getMessage();
			$item['code'] = $message->getCode();
			$item['pathname'] = $message->getFile();
			$item['lineno'] = $message->getLine();
		} elseif (isset($options['line'], $options['file'])) {
			$item['pathname'] = $options['file'];
			$item['lineno'] = $options['line'];
		} else {
			$trace = debug_backtrace();
			extract(static::_extractFileLine($trace));
			$item['pathname'] = $file;
			$item['lineno'] = $line;
		}
		$logs = array(static::_pickle($item, $options));
		$id = dechex(mt_rand(0, 0xFFFF)) . dechex(mt_rand(0, 0xFFFF));
		$json = json_encode(array('logs' => $logs));
		$res = str_split(base64_encode($json), 76); // RFC 2045
		foreach($res as $k => $v) {
			$headers["FireLogger-$id-$k"] = $v;
		}
		return $headers;
	}

	protected static function _pickle($var, $options, $level = 0) {
		if (is_bool($var) || is_null($var) || is_int($var) || is_float($var)) {
			return $var;
		}
		if (is_string($var)) {
			return @iconv(
				'UTF-16',
				'UTF-8//IGNORE',
				iconv($options['encoding'], 'UTF-16//IGNORE', $var)
			); // intentionally @
		}
		if (is_array($var)) {
			static $marker;
			if ($marker === NULL) {
				$marker = uniqid("\x00", TRUE); // detects recursions
			}
			if (isset($var[$marker])) {
				return '*RECURSION*';
			}
			if ($level < $options['maxDepth'] || !$options['maxDepth']) {
				$var[$marker] = TRUE;
				$res = array();
				foreach ($var as $k => &$v) {
					if ($k !== $marker) {
						$res[static::_pickle($k, $options)] =
							static::_pickle($v, $options, $level + 1);
					}
				}
				unset($var[$marker]);
				return $res;
			}
			return '...';
		}
		if (is_object($var)) {
			$arr = (array)$var;
			$arr['__class##'] = get_class($var);

			static $list = array(); // detects recursions
			if (in_array($var, $list, TRUE)) {
				return '*RECURSION*';
			}
			if ($level < $options['maxDepth'] || !$options['maxDepth']) {
				$list[] = $var;
				$res = array();
				foreach ($arr as $k => &$v) {
					if ($k[0] === "\x00") {
						$k = substr($k, strrpos($k, "\x00") + 1);
					}
					$res[static::_pickle($k, $options)] = static::_pickle($v, $options, $level + 1);
				}
				array_pop($list);
				return $res;
			}
			return '...';
		}
		if (is_resource($var)) {
			return '*' . get_resource_type($var) . ' resource*';
		}
		return '*unknown type*';
	}

	protected static function _extractFileLine($trace) {
		$trace = array_slice($trace, 6);
		if (count($trace) == 0) {
			$file = '?';
			$line = 0;
		} else {
			$file = $trace[0]['file'];
			$line = $trace[0]['line'];
		}
		return compact('file', 'line');
	}

	protected static function _extractTrace($trace) {
		$t = array();
		$f = array();
		foreach ($trace as $frame) {
			$frame += array(
				'file' => null,
				'line' => null,
				'class' => null,
				'type' => null,
				'function' => null,
				'object' => null,
				'args' => null
			);
			$t[] = array(
				$frame['file'],
				$frame['line'],
				$frame['class'] . $frame['type'] . $frame['function'],
				$frame['object']
			);
			$f[] = $frame['args'];
		};
		return array($t, $f);
	}
}

?>