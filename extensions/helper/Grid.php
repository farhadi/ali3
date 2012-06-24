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
		'link'         => '<a href="{:url}"{:options}>{:title}</a>',
		'list'         => '<ul{:options}>{:content}</ul>',
		'list-item'    => '<li{:options}>{:content}</li>',
		'table'        => '<table{:options}><thead>{:header}</thead><tbody>{:body}</tbody></table>',
		'table-header' => '<th{:options}>{:content}</th>',
		'table-cell'   => '<td{:options}>{:content}</td>',
		'table-row'    => '<tr{:options}>{:content}</tr>',
	);

	public function page($grid, $page, $title = null, $options = array()) {
		$request = $this->_context->request();
		$url = $request->params + array('?' => array('page' => $page) + $request->query);
		$title = $title ?: $page;
		if ($page == $grid->page()) {
			$options += array('class' => 'active');
		}
		return $this->listItem($this->link(compact('url', 'title')), $options);
	}

	public function first($grid, $title = null, $options = array()) {
		return $grid->page() == 1 ? '' : $this->page($grid, 1, $title, $options);
	}

	public function last($grid, $title = null, $options = array()) {
		$last = $grid->pages();
		return $grid->page() == $last ? '' : $this->page($grid, $last, $title, $options);
	}

	public function prev($grid, $title = null, $options = array()) {
		$page = $grid->page();
		return $page == 1 ? '' : $this->page($grid, $page - 1, $title, $options);
	}

	public function next($grid, $title = null, $options = array()) {
		$page = $grid->page();
		return $page == $grid->pages() ? '' : $this->page($grid, $page + 1, $title, $options);
	}

	public function pages($grid, $options = array()) {
		if ($grid->pages() < 2) {
			return '';
		}
		$options += array(
			'count' => 9,
			'first' => '«',
			'last' => '»',
			'prev' => false,
			'next' => false,
			'wrap' => array('class' => 'pagination'),
		);
		$start = max($grid->page() - intval($options['count'] / 2), 1); 
		$end = min($start + $options['count'] - 1, $grid->pages());
		$pages = array();
		for ($i = $start; $i <= $end; $i++) {
			$pages[] = $this->page($grid, $i);
		}
		foreach (array('prev', 'first', 'next', 'last') as $key => $method) {
			if ($options[$method]) {
				if ($key < 2) {
					array_unshift($pages, $this->{$method}($grid, $options[$method]));
				} else {
					$pages[] = $this->{$method}($grid, $options[$method]);
				}
			}
		}
		$pages = $this->list(implode('', $pages));
		if ($options['wrap'] !== false) {
			$pages = $this->block($pages, $options['wrap']);
		}
		return $pages;
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
		$options = compact('class');
		return $this->link(compact('title', 'url', 'options'));
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
		);
		list($options, $tableOptions) = $this->_options($defaults, $options);
		if ($options['actions']) {
			$actions = $options['actions'];
			$titles = $options['titles'];
			$self = $this;
			$grid->each(function($row) use ($self, $titles, $actions) {
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
					$options += array('class' => $action);
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
					$link = $self->link(compact('title', 'url'));
					$row['actions'][] = $self->listItem($link, $options);
				}
				$row['actions'] = implode('', $row['actions']);
				$row['actions'] = $self->list($row['actions'], array('class' => 'actions'));
				return $row;
			});
			$options['titles'] += array('actions' => '');
		}
		$header = $body = '';
		if ($options['#']) {
			$header .= $this->tableHeader('#');
		}
		foreach (array_keys($grid->first()) as $field) {
			if ($field == 'options' || in_array($field, $options['hidden'])) {
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
			$header .= $this->tableHeader($title);
		}
		$header = $this->tableRow($header);
		$number = $grid->limit() * ($grid->page() - 1);
		foreach ($grid as $row) {
			$number++;
			$rowOptions = isset($row['options']) ? $row['options'] : array();
			unset($row['options']);
			$rowContent = '';
			if ($options['#']) {
				$rowContent .= $this->tableCell($number);
			}
			foreach ($row as $field => $cell) {
				if (in_array($field, $options['hidden'])) {
					continue;
				}
				$rowContent .= $this->tableCell($cell);
			}
			$body .= $this->tableRow($rowContent, $rowOptions);
		}
		$output = $this->table(compact('header', 'body') + array('options' => $tableOptions));

		if ($pages = $options['pages']) {
			$output .= $this->pages($grid, is_array($pages) ? $pages : array());
		}

		return $output;
	}

	function __call($method, $params = array()) {
		if ($params && is_array($params[0])) {
			$params = $params[0] + array('options' => array());
		} elseif ($params) {
			$params += array(null, array());
			$params = array('content' => $params[0], 'options' => $params[1]);
		}
		$template = strtolower(Inflector::slug($method));
		return $this->_render(__CLASS__ . '::' . $method, $template, $params);
	}
}

?>