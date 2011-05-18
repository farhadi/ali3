<?php
/**
 * Ali3: my stuff for lithium framework.
 *
 * @copyright     Copyright 2011, Ali Farhadi (https://github.com/farhadi/ali3)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace ali3\extensions\helper;

use lithium\util\Inflector;
use lithium\util\String;

class Grid extends \lithium\template\Helper {

	/**
	 * String templates used by this helper.
	 *
	 * @var array
	 */
	protected $_strings = array(
		'block'        => '<div{:options}>{:content}</div>',
		'table'        => '<table{:options}><thead>{:header}</thead><tbody>{:body}</tbody></table>',
		'table-header' => '<th{:options}>{:content}</th>',
		'table-cell'   => '<td{:options}>{:content}</td>',
		'table-row'    => '<tr{:options}>{:content}</tr>',
	);

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
		if ($grid->pages() < 2) {
			return '';
		}
		$options += array(
			'separator' => '',
			'count' => 9,
			'first' => true,
			'last' => true,
			'prev' => true,
			'next' => true,
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
		foreach (array('prev', 'first', 'next', 'last') as $key => $method) {
			if ($options[$method]) {
				if ($key < 2) {
					array_unshift($pages, $this->{$method}($grid));
				} else {
					$pages[] = $this->{$method}($grid);
				}
			}
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

	public function render($grid, $options = array()) {
		if (!$grid->count()) {
			return '';
		}
		$defaults =  array(
			'sortable' => true,
			'pages' => true,
			'actions' => array(),
			'#' => true,
			'hidden' => array(),
			'titles' => array(),
			'wrap' => array('class' => 'grid'),
		);
		list($options, $tableOptions) = $this->_options($defaults, $options);
		if ($options['actions']) {
			$actions = $options['actions'];
			$titles = $options['titles'];
			$context = $this->_context;
			$grid->each(function($row) use ($context, $titles, $actions) {
				foreach ($actions as $action => $options) {
					if (is_int($action)) {
						$action = $options;
						$options = array();
					}
					if (isset($options['confirm'])) {
						$confirm = String::insert($options['confirm'], $row);
						$confirm = str_replace("\n", '\n', addslashes($confirm));
						$options['onclick'] = "return confirm('$confirm');";
						unset($options['confirm']);
					}
					$options += array('class' => 'action ' . $action);
					if (isset($options['url'])) {
						$url = $options['url']($row);
						if (!$url) {
							continue;
						}
						unset($options['url']);
					} else {
						$url = true;
					}
					if ($url === true) {
						$url = array(
							'action' => $action,
							'args' => array($row['id'])
						);
					}
					if (isset($titles[$action])) {
						$title = $titles[$action];
					} else {
						$title = Inflector::humanize($action);
					}
					$row['actions'][] = $context->html->link($title, $url, $options);
				}
				$row['actions'] = implode(' ', $row['actions']);
				return $row;
			});
			$options['titles'] += array('actions' => '');
		}
		$header = $body = '';
		if ($options['#']) {
			$header .= $this->tableHeader('#', array('class' => 'row'));
		}
		foreach (array_keys($grid->first()) as $field) {
			if (in_array($field, $options['hidden'])) {
				continue;
			}
			if (isset($options['titles'][$field])) {
				$title = $options['titles'][$field];
			} else {
				$title = Inflector::humanize($field);
			}
			if (
				$title && $options['sortable'] &&
				(!is_array($options['sortable']) || in_array($field, $options['sortable']))
			) {
				$title = $this->sort($grid, $field, $title);
			}
			$header .= $this->tableHeader($title, array('class' => $field));
		}
		$header = $this->tableRow($header);
		$number = $grid->limit() * ($grid->page() - 1);
		foreach ($grid as $row) {
			$rowContent = '';
			$number++;
			if ($options['#']) {
				$rowContent .= $this->tableCell($number, array('class' => 'row'));
			}
			foreach ($row as $field => $cell) {
				if (in_array($field, $options['hidden'])) {
					continue;
				}
				$rowContent .= $this->tableCell($cell, array('class' => $field));
			}
			$body .= $this->tableRow($rowContent, array('class' => $number % 2 ? 'odd' : 'even'));
		}
		$output = $this->table(compact('header', 'body') + array('options' => $tableOptions));

		if ($options['pages'] && $pages = $this->pages($grid)) {
			$output .= $this->_render(__METHOD__, 'block', array(
				'content' => $pages,
				'options' => array('class' => 'pages')
			));
		}

		if ($options['wrap'] !== false) {
			$output = $this->_render(__METHOD__, 'block', array(
				'content' => $output,
				'options' => $options['wrap']
			));
		}
		
		return $output;
	}

	function __call($method, $params = array()) {
		if ($params && is_array($params[0])) {
			$params = $params[0];
		} elseif ($params) {
			$params = array(
				'content' => $params[0],
				'options' => $params[1]
			);
		}
		$template = strtolower(Inflector::slug($method));
		return $this->_render(__CLASS__ . '::' . $method, $template, $params);
	}
}

?>