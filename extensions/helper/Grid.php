<?php
/**
 * Ali3: my stuff for lithium framework.
 *
 * @copyright     Copyright 2011, Ali Farhadi (https://github.com/farhadi/ali3)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace ali3\extensions\helper;

use lithium\util\Inflector;

class Grid extends \lithium\template\Helper {

	public function page($grid, $page, $text = null) {
		$request = $this->_context->request();
		$url = $request->params + array('?' => array('page' => $page) + $request->query);
		if ($grid->page() == $page) {
			return $this->_context->html->link($text ?: $page, $url, array('class' => 'current'));
		}
		return $this->_context->html->link($text ?: $page, $url);
	}

	public function first($grid, $text = '« first') {
		return $grid->page() == 1 ? '' : $this->page($grid, 1, $text);
	}

	public function last($grid, $text = 'last »') {
		$last = $grid->pages();
		return $grid->page() == $last ? '' : $this->page($grid, $last, $text);
	}

	public function prev($grid, $text = '« previous') {
		$page = $grid->page();
		return $page == 1 ? '' : $this->page($grid, $page - 1, $text);
	}

	public function next($grid, $text = 'next »') {
		$page = $grid->page();
		return $page == $grid->pages() ? '' : $this->page($grid, $page + 1, $text);
	}

	public function pages($grid, $options = array()) {
		$options += array(
			'separator' => '',
			'count' => 9
		);
		$end = min(
			max($grid->page() - intval($options['count'] / 2), 1) + $options['count'] - 1,
			$grid->pages()
		);
		$start = max($end - $options['count'] + 1, 1);
		$pages = array();
		for ($i = $start; $i <= $end; $i++) {
			$pages[] = $this->page($grid, $i);
		}
		return implode($options['separator'], $pages);
	}

	public function sort($grid, $field, $title = null) {
		if (!$title) {
			$title = Inflector::humanize($field);
		}
		$order = (array)$grid->order();
		if (isset($order[$field])) {
			$currentOrder = strtolower($order[$field]) == 'desc' ? 'desc' : 'asc';
			$class = 'sort ' . $currentOrder;
		} elseif (current($order) == $field) {
			$currentOrder = 'asc';
			$class = 'sort asc';
		} else {
			$currentOrder = null;
		}
		$order = array($field => $currentOrder == 'asc' ? 'desc' : 'asc');
		if (!$grid->isOrderValid($order)) {
			return $title;
		}
		$request = $this->_context->request();
		$url = $request->params + array('?' => compact('order') + $request->query);
		return $this->_context->html->link($title, $url, compact('class'));
	}
}

?>