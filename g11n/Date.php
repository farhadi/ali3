<?php
/**
 * Ali3: my stuff for lithium framework.
 *
 * @copyright     Copyright 2011, Ali Farhadi (https://github.com/farhadi/ali3)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace ali3\g11n;

use lithium\core\Environment;
use DateTimeZone;
use IntlDateFormatter;
use NumberFormatter;

/**
 * An extented version of DateTime class with integrated IntlDateFormatter functionality
 * which adds support for multiple calendars and locales provided by ICU project.
 * Note that this class is not compatible with php DateTime because it uses ICU pattern syntax
 * for formatting and parsing date strings.
 * @see http://userguide.icu-project.org/formatparse/datetime
 */
class Date extends \DateTime {

	/**
	 * @var string The current locale in use.
	 */
	protected $_locale;

	/**
	 * @var string The current calendar in use.
	 */
	protected $_calendar;

	/**
	 * Creates a new instance of Date.
	 *
	 * @param mixed $date Unix timestamp or strtotime() compatible string or an instance of
	 *        DateTime class or its descendants.
	 * @param array $options Options:
	 *        - `'timezone'` _string|object_: `DateTimeZone` object or timezone identifier
	 *          as full name (e.g. `'Asia/Tehran'`) or abbreviation (e.g. `'IRDT'`).
	 *          If timezone is not provided tries to get it from the current Environment
	 *          configurations and if it's not in there too, php's default timezone will be used.
	 *        - `'locale'` _string_: Any locale supported by ICU project (`'en'`, `'en_US'`, etc).
	 *          If locale is not provided tries to get it from the current Environment
	 *          configurations and if it's not in there too, `'en_US'` will be used.
	 *        - `'calendar'` _string_: Any calendar supported by ICU project (e.g. `'gregorian'`,
	 *          `'persian'`, `'islamic'`, ...).
	 *          If calendar is not provided tries to get it from the current Environment
	 *          configurations and if it's not in there too, `'gregorian'` will be used.
	 *        - `'pattern'` _string_: The date pattern in which `$date` is formatted.
	 *          Common date patterns will be detected automaticlly. So in most of the cases
	 *          pattern option is not needed.
	 * @return void
	 */
	public function __construct($date = null, $options = array()) {
		if (!is_array($options)) {
			//keep backward compatibility with DateTime::__construct()
			$options = array('timezone' => $options);
		}
		$options += array(
			'timezone' => Environment::get('timezone') ?: date_default_timezone_get(),
			'locale' => Environment::get('locale') ?: 'en_US',
			'calendar' => Environment::get('calendar') ?: 'gregorian',
			'pattern' => null,
		);
		if (is_string($options['timezone'])) {
			$options['timezone'] = new DateTimeZone($options['timezone']);
		}
		parent::__construct(null, $options['timezone']);
		$this->locale($options['locale']);
		$this->calendar($options['calendar']);
		if (isset($date)) {
			$this->set($date);
		}
	}

	/**
	 * Updates current object options and returns previous options.
	 *
	 * @param array $options
	 * @return array previous options.
	 */
	protected function _options($options = array()) {
		$_options = array(
			'timezone' => $this->getTimezone(),
			'locale' => $this->_locale,
			'calendar' => $this->_calendar,
		);
		if (isset($options['timezone'])) {
			$this->setTimezone($options['timezone']);
		}
		if (isset($options['locale'])) {
			$this->locale($options['locale']);
		}
		if (isset($options['calendar'])) {
			$this->calendar($options['calendar']);
		}
		return $_options;
	}

	/**
	 * Returns an instance of IntlDateFormatter with specified options.
	 *
	 * @param array $options Same as `Date::__construct()` options except that current
	 *        object options are used as default values.
	 * @return object IntlDateFormatter
	 * @see ali3\g11n\Date::__construct()
	 */
	protected function _formatter($options = array()) {
		$options += $this->_options() + array('pattern' => null);
		if (is_a($options['timezone'], '\DateTimeZone')) {
			$options['timezone'] = $options['timezone']->getName();
		}
		$calendarType = $options['calendar'] === 'gregorian' ? 'GREGORIAN' : 'TRADITIONAL';
		return new IntlDateFormatter(
			$options['locale'] . '@calendar=' . $options['calendar'],
			IntlDateFormatter::FULL, IntlDateFormatter::FULL, $options['timezone'],
			constant("IntlDateFormatter::{$calendarType}"), $options['pattern']
		);
	}

	/**
	 * Replaces localized digits in $str with latin digits and returns the modified string.
	 *
	 * @param string $str Thes string to be latinized.
	 * @return string Latinized string.
	 */
	protected function _latinizeDigits($str) {
		$result = '';
		$num = new NumberFormatter($this->_locale, NumberFormatter::DECIMAL);
		preg_match_all('/.[\x80-\xBF]*/', $str, $matches);
		foreach ($matches[0] as $char) {
			$pos = 0;
			$parsedChar = $num->parse($char, NumberFormatter::TYPE_INT32, $pos);
			$result .= $pos ? $parsedChar : $char;
		}
		return $result;
	}

	/**
	 * Tries to guess the date pattern in which `$date` is formatted.
	 *
	 * @param string $date The date string.
	 * @return string|boolean Detected ICU pattern on success, FALSE otherwise.
	 */
	protected function _guessPattern($date) {
		$date = $this->_latinizeDigits(trim($date));

		$shortDate = '(\d{2,4})(-|\\\\|/)\d{1,2}\2\d{1,2}';
		$longDate = '([^\d]*\s)?\d{1,2}(-| )[^-\s\d]+\4(\d{2,4})';
		$time = '\d{1,2}:\d{1,2}(:\d{1,2})?(\s.*)?';

		if (preg_match("@^(?:(?:$shortDate)|(?:$longDate))(\s+$time)?$@", $date, $match)) {
			if (!empty($match[1])) {
				$separator = $match[2];
				$pattern = strlen($match[1]) == 2 ? 'yy' : 'yyyy';
				$pattern .= $separator . 'MM' . $separator . 'dd';
			} else {
				$separator = $match[4];
				$pattern = 'dd' . $separator . 'LLL' . $separator;
				$pattern .= strlen($match[5]) == 2 ? 'yy' : 'yyyy';
				if (!empty($match[3])) {
					$pattern = (preg_match('/,\s+$/', $match[3]) ? 'E, ' : 'E ') . $pattern;
				}
			}
			if (!empty($match[6])) {
				$pattern .= !empty($match[8]) ? ' hh:mm' : ' HH:mm';
				if (!empty($match[7])) $pattern .= ':ss';
				if (!empty($match[8])) $pattern .= ' a';
			}
			return $pattern;
		}

		return false;
	}

	/**
	 * Gets/Sets the locale used by the object.
	 *
	 * @param string $locale
	 * @return string|object Current locale / The modified Date object.
	 */
	public function locale($locale = null) {
		if (!isset($locale)) {
			return $this->_locale;
		}
		$this->_locale = $locale;
		return $this;
	}

	/**
	 * Gets/Sets the calendar used by the object.
	 *
	 * @param string $calendar
	 * @return string|object Current calendar / The modified Date object.
	 */
	public function calendar($calendar = null) {
		if (!isset($calendar)) {
			return $this->_calendar;
		}
		$this->_calendar = strtolower($calendar);
		return $this;
	}

	/**
	 * Alters object's internal timestamp with a string acceptable by strtotime() or
	 * a Unix timestamp or an instance of DateTime class or its descendants.
	 *
	 * @param mixed $date Unix timestamp or strtotime() compatible string or an instance of
	 *        DateTime class or its descendants.
	 * @param array $options Same as `Date::__construct()` options except that current
	 *        object options are used as default values.
	 * @return object The modified Date object.
	 * @see ali3\g11n\Date::__construct()
	 */
	public function set($date, $options = array()) {
		if (is_a($date, '\DateTime')) {
			$date = $date->getTimestamp();
		} elseif (!is_integer($date)) {
			if (!isset($options['pattern'])) {
				$options['pattern'] = $this->_guessPattern($date);
			}

			if (!$options['pattern'] &&
				preg_match('/((?:[+-]?\d+)|next|last|previous)\s*(year|month)s?/i', $date)) {
				$this->setTimestamp(time());
				$_options = $this->_options($options);
				$this->modify($date);
				$this->_options($_options);
				return $this;
			}

			if ($options['pattern']) {
				$_options = $this->_options($options);
				$date = $this->_formatter(array(
					'timezone' => 'GMT',
					'pattern' => $options['pattern']
				))->parse($date);
				$this->setTimestamp($date);
				$date -= $this->getOffset();
				$this->_options($_options);
			} else {
				$date = strtotime($date);
			}
		}

		$this->setTimestamp($date);

		return $this;
	}

	/**
	 * Resets the current date of the object.
	 *
	 * This method overrides DateTime::setDate() to add support for current calendar in use.
	 *
	 * @param integer $year
	 * @param integer $month
	 * @param integer $day
	 * @return object The modified Date object.
	 */
	public function setDate($year, $month, $day) {
		$this->set("$year/$month/$day " . parent::format('H:i:s'), array(
			'pattern' => 'yyyy/MM/dd HH:mm:ss',
		));
		return $this;
	}

	/**
	 * Sets the timezone for the object.
	 *
	 * @param mixed $timezone `DateTimeZone` object or timezone identifier
	 *        as full name (e.g. `'Asia/Tehran'`) or abbreviation (e.g. `'IRDT'`).
	 * @return object The modified Date object.
	 */
	public function setTimezone($timezone) {
		if (is_string($timezone)) {
			$timezone = new DateTimeZone($timezone);
		}
		parent::setTimezone($timezone);
		return $this;
	}

	/**
	 * Internally used by modify method to calculate calendar-specific modifications.
	 *
	 * @param array $matches
	 * @return string An empty string
	 */
	protected function _modifyCallback($matches) {
		if (!empty($matches[1])) {
			parent::modify($matches[1]);
		}

		list($y, $m, $d) = explode('-', $this->format('y-M-d', array('locale' => 'en')));
		$change = strtolower($matches[2]);
		$unit = strtolower($matches[3]);

		switch ($change) {
			case "next":
				$change = 1;
			break;
			case "last":
			case "previous":
				$change = -1;
			break;
		}

		switch ($unit) {
			case "month":
				$m += $change;
				if ($m > 12) {
					$y += floor($m/12);
					$m = $m % 12;
				} elseif ($m < 1) {
					$y += ceil($m/12) - 1;
					$m = $m % 12 + 12;
				}
			break;
			case "year":
				$y += $change;
			break;
		}

		$this->setDate($y, $m, $d);

		return '';
	}

	/**
	 * Alters the timestamp by incrementing or decrementing in a format accepted by strtotime().
	 *
	 * @param string $modify A string in a relative format accepted by strtotime().
	 * @return object The modified Date object.
	 */
	public function modify($modify) {
		$modify = $this->_latinizeDigits(trim($modify));
		$modify = preg_replace_callback(
			'/(.*?)((?:[+-]?\d+)|next|last|previous)\s*(year|month)s?/i',
			array($this, '_modifyCallback'), $modify
		);
		if ($modify) {
			parent::modify($modify);
		}
		return $this;
	}

	/**
	 * Returns date formatted according to the given pattern.
	 *
	 * @param string $pattern
	 * @return string|boolean Formatted date on success or FALSE on failure.
	 */
	protected function _format($pattern) {
		return $this->_formatter(array(
			// ICU timezones DST data are not as accurate as PHP.
			// So we get timezone difference in hours from php and pass it to ICU.
			'timezone' => 'GMT' . parent::format('O'),
			'pattern' => $pattern
		))->format($this->getTimestamp());
	}

	/**
	 * Returns date formatted according to the given pattern and options.
	 *
	 * @param string $pattern Date pattern in ICU syntax
	 * @param array $options Same as `Date::__construct()` options except that current
	 *        object options are used as default values.
	 * @return string|boolean Formatted date on success or FALSE on failure.
	 * @see ali3\g11n\Date::__construct()
	 * @see http://userguide.icu-project.org/formatparse/datetime
	 */
	public function format($pattern = 'yyyy/MM/dd HH:mm:ss', $options = null) {
		if (!isset($options)) {
			return $this->_format($pattern);
		}

		$_options = $this->_options($options);
		$result = $this->_format($pattern);
		$this->_options($_options);
		return $result;
	}

	/**
	 * Preserve original DateTime::format functionality
	 *
	 * @param string $format Format accepted by date().
	 * @return string Formatted date on success or FALSE on failure.
	 */
	public function classicFormat($format) {
		return parent::format($format);
	}

	/**
	 * Casts the Date object to a date string which is suitable for storing in database.
	 *
	 * @return string Date string.
	 */
	public function __toString() {
		return gmdate('Y-m-d H:i:s', $this->getTimestamp());
	}
}

?>