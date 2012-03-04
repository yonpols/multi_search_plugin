<?php
    class MultiSearch implements Iterator {
        public static $custom_operands = array();

        protected $query;

        protected $count = null;

        protected $pages = null;
        protected $page = 1;
        protected $items_per_page = 20;

        protected $order_field = null;
        protected $order_direction = 'ASC';

        protected $params = array();
        protected $form_object = null;

        protected $filtered = false;

        public function __construct($query, $params = null) {
            $this->query = $query;

            if (is_array($params))
                $this->params = $params;

            if (isset($this->params['pagination']['page']))
                $this->page = $this->params['pagination']['page'];

            if (isset($this->params['pagination']['items_per_page']))
                $this->items_per_page = $this->params['pagination']['items_per_page'];

            if (isset($this->params['search'])) {
                $this->form_object = new search($this->params['search']);

                foreach ($this->params['search'] as $item => $value) {
                    if ($value === '')
                        continue;

                    list($field, $comp) = explode(':', $item);

                    switch ($comp) {
                        case 'equals':
                        case 'eq':
                            $this->query = $this->query->where(sprintf('%s = ?', $field), $value);
                            $this->filtered = true;
                            break;

                        case 'does_not_equal':
                        case 'noteq':
                            $this->query = $this->query->where(sprintf('%s <> ?', $field), $value);
                            $this->filtered = true;
                            break;

                        case 'is_null':
                            $this->query = $this->query->where(sprintf('%s IS NULL', $field));
                            $this->filtered = true;
                            break;

                        case 'is_not_null':
                            $this->query = $this->query->where(sprintf('%s IS NOT NULL', $field));
                            $this->filtered = true;
                            break;

                        case 'contains':
                        case 'like':
                            $this->query = $this->query->where(sprintf('%s LIKE ?', $field), '%'.$value.'%');
                            $this->filtered = true;
                            break;

                        case 'does_not_contain':
                        case 'not_like':
                            $this->query = $this->query->where(sprintf('NOT (%s LIKE ?)', $field), '%'.$value.'%');
                            $this->filtered = true;
                            break;

                        case 'starts_with':
                            $this->query = $this->query->where(sprintf('%s LIKE ?', $field), $value.'%');
                            $this->filtered = true;
                            break;

                        case 'does_not_start_with':
                            $this->query = $this->query->where(sprintf('NOT (%s LIKE ?)', $field), $value.'%');
                            $this->filtered = true;
                            break;

                        case 'ends_with':
                            $this->query = $this->query->where(sprintf('%s LIKE ?', $field), $value.'%');
                            $this->filtered = true;
                            break;

                        case 'does_not_end_with':
                            $this->query = $this->query->where(sprintf('NOT (%s LIKE ?)', $field), $value.'%');
                            $this->filtered = true;
                            break;

                        case 'greater_than':
                        case 'gt':
                            $this->query = $this->query->where(sprintf('%s > ?', $field), $value);
                            $this->filtered = true;
                            break;

                        case 'greater_than_or_equal_to':
                        case 'gteq':
                            $this->query = $this->query->where(sprintf('%s >= ?', $field), $value);
                            $this->filtered = true;
                            break;

                        case 'less_than':
                        case 'lt':
                            $this->query = $this->query->where(sprintf('%s < ?', $field), $value);
                            $this->filtered = true;
                            break;

                        case 'less_than_or_equal_to':
                        case 'lteq':
                            $this->query = $this->query->where(sprintf('%s <= ?', $field), $value);
                            $this->filtered = true;
                            break;

                        case 'in':
                            $sql = sprintf('%s IN (%s)', $field, implode(', ', array_fill(0, count($value), '?')));
                            $this->query = call_user_func_array(array($this->query, 'where'), array_merge(array($sql), $value));
                            $this->filtered = true;
                            break;

                        case 'not_in':
                            $sql = sprintf('%s NOT IN (%s)', $field, implode(', ', array_fill(0, count($value), '?')));
                            $this->query = call_user_func_array(array($this->query, 'where'), array_merge(array($sql), $value));
                            $this->filtered = true;
                            break;

                        default:
                            if (isset (self::$custom_operands[$comp]) && is_callable(self::$custom_operands[$comp])) {
                                $this->query = call_user_func(self::$custom_operands[$comp], $this->query, $field, $value);
                            }
                    }
                }
            } else
                $this->form_object = new search;

            if (isset($this->params['order'])) {
                $this->order_field = $this->params['order']['field'];
                $this->order_direction = isset($this->params['order']['direction'])? $this->params['order']['direction'] : 'ASC';

                if (array_search($this->order_direction, array('ASC', 'DESC')) === false)
                    $this->order_direction = 'ASC';

                $this->query = $this->query->orderBy($this->order_field.' '.$this->order_direction);
            }

            if ($this->items_per_page > 0) {
                $count = $this->query->count();
                $this->pages = round($count / $this->items_per_page);
                if (($count % $this->items_per_page) > 0)
                    $this->pages++;

                $this->query = $this->query->limit(array(($this->page-1)*$this->items_per_page, $this->items_per_page));
            }
        }

        public function __call($name, $arguments) {
            return call_user_func_array(array($this->query, $name), $arguments);
        }

        public function __get($name) {
            return $this->query->__get($name);
        }

        public function isFiltered() {
            return $this->filtered;
        }

        public function link($route = null, $extra_params = array()) {
            if ($route === null)
                $route = YPFramework::getApplication()->getCurrentRoute();

            if (!is_array($extra_params))
                $extra_params = array();

            $params = array_merge_deep($this->params, $extra_params);

            return $route->path($params);
        }

        public function orderLink($field, &$direction = null, $route = null) {
            if ($direction === null) {
                if ($this->order_field == $field)
                    $direction = ($this->order_direction=='ASC')? 'DESC': 'ASC';
                else
                    $direction = 'ASC';
            }
            $params = array('order' => array(
                'field' => $field,
                'direction' => $direction)
            );

            if (!$this->order_field == $field)
                $direction = null;

            return $this->link($route, $params);
        }

        public function pageLink($page, $route = null) {
            if ($page > $this->pages)
                $page = $this->pages;

            return $this->link($route, array('pagination'=> array('page' => $page)));
        }

        public function paginationLinks($route = null) {
            if ($this->pages <= 1)
                return '';

            $min = max(1, $this->page - 10);
            $max = min($this->pages, $this->page + 10);

            $html = '';

            if ($min > 1)
                $html .= sprintf('<span class="page_link"><a href="%s">1</a> ...</span>', $this->pageLink(1, $route));

            for ($i = $min; $i <= $max; $i++)
                if ($this->page != $i)
                    $html .= sprintf('<span class="page_link"><a href="%s">%d</a></span>', $this->pageLink($i, $route), $i);
                else
                    $html .= sprintf('<span class="page_link">%d</span>', $i);

            if ($max < $this->pages)
                $html .= sprintf('<span class="page_link">... <a href="%s">%d</a></span>', $this->pageLink($this->pages, $route), $this->pages);

            return $html;
        }

        public function form() {
            return $this->form_object;
        }

        public function current() {
            return $this->query->current();
        }

        public function key() {
            return $this->query->key();
        }

        public function next() {
            $this->query->next();
        }

        public function rewind() {
            $this->query->rewind();
        }

        public function valid() {
            return $this->query->valid();
        }
    }

    class search {
        private $params;

        public function __construct($params = array()) {
            $this->params = $params;
        }

        public function __get($name) {
            if (isset($this->params[$name]))
                return $this->params[$name];
        }

        public function __isset($name) {
            return true;
        }
    }
?>
