<?php
namespace dragonblack\apimailchimp;

/**
 * Class MailChimpResource
 *
 * Класс, представляющий объект ресурса API
 *
 * @package dragonblack\apimailchimp
 */
class MailChimpResource extends \ArrayIterator {

    /** @var array Ссылки на связанные ресурсы */
    protected $_links = [];

    /** @var array Содержимое ресурса */
    protected $_items = [];

    /** @var array Свойства ресурса */
    protected $_properties = [];

    /** @var  array Ключи массива _items */
    private $_keys;

    /** @var  string Текущий ключ массива _items */
    private $_position;

    public function __construct($data, $type = null) {
        $this->compileLinks($data['_links']);
        unset($data['_links']);
        if ($type && isset($data[$type])) {
            $this->compileItems($data[$type]);
            unset($data[$type]);
        }
        $this->_properties = array_map(function ($a) {
            if (is_array($a)) {
                return new \ArrayObject($a, \ArrayObject::ARRAY_AS_PROPS);
            }

            return $a;
        }, $data);
    }

    /**
     * Формирует массив ссылок на связанные ресурсы
     *
     * @param $data
     */
    protected function compileLinks($data) {
        if (empty($data)) {
            return;
        }
        $this->_links = [];
        foreach ($data as $link) {
            if ($link['rel'] == 'self') {
                continue;
            }

            $this->_links[$link['rel']] = $link;
        }
    }

    /**
     * Формирует массив содержимого ресурса
     *
     * @param $data
     */
    protected function compileItems($data) {
        foreach ($data as $item) {
            $this->_items[$item['id']] = new MailChimpResource($item);
        }
        $this->_keys = array_keys($this->_items);
    }

    public function __get($name) {
        if (isset($this->_items[$name])) {
            return $this->_items[$name];
        }

        if (isset($this->_properties[$name])) {
            return $this->_properties[$name];
        }

        if (isset($this->_links[$name]) && strtoupper($this->_links[$name]['method']) == 'GET') {
            $data = $this->loadData($this->_links[$name]);
            $this->_items[$name] = new MailChimpResource($data, $name);

            return $this->_items[$name];
        }

        throw new MailChimpException('Property "' . $name . '" is not found');
    }

    public function __call($name, $arguments) {
        if (empty($arguments) || is_scalar($arguments[0])) {
            if (isset($this->_items[$name])) {
                if(empty($arguments)){
                    return $this->_items[$name];
                }

                if(empty($this->_items[$name][$arguments[0]])){
                    $data = $this->loadData($this->_links[$name], $arguments[0]);
                    $this->_items[$name] = [$arguments[0] => new MailChimpResource($data)];
                }
                return $this->_items[$name][$arguments[0]];
            }

            if (isset($this->_links[$name]) && strtoupper($this->_links[$name]['method']) == 'GET') {
                if (empty($arguments)) {
                    $data = $this->loadData($this->_links[$name]);
                    $this->_items[$name] = new MailChimpResource($data, $name);

                    return $this->_items[$name];
                }
                else {
                    $data = $this->loadData($this->_links[$name], $arguments[0]);
                    $this->_items[$name] = [$arguments[0] => new MailChimpResource($data)];

                    return $this->_items[$name][$arguments[0]];
                }

            }
            elseif (isset($this->_links[$name])) {
                return $this->_callFunc($this->_links[$name]);
            }
        }
        elseif (!empty($arguments) && is_array($arguments[0])) {
            if (isset($this->_links[$name]) && strtoupper($this->_links[$name]['method']) == 'GET') {
                $data = $this->loadData($this->_links[$name], $arguments[0]);
                if (empty($data[$name])) {
                    return null;
                }

                return new MailChimpResource($data, $name);

            }
            elseif (isset($this->_links[$name])) {
                return $this->_callFunc($this->_links[$name], $arguments[0]);
            }
        }
    }

    /**
     * Преобразует вызов функции в соответствующий запрос к API и выполняет его
     *
     * @param       $link
     * @param array $data
     *
     * @return mixed
     */
    private function _callFunc($link, $data = []) {
        $curl = Curl::instance();
        if (isset($link['schema']) || isset($link['targetSchema'])) {
            $curl->setUrl(isset($link['schema']) ? $link['schema'] : $link['targetSchema']);
            $schema = $curl->get();

            foreach ($data as $k => $val) {
                if (!isset($schema['properties'][$k])) {
                    unset($data[$k]);
                }
            }
        }

        $curl->setUrl($link['href']);
        if (strtoupper($link['method']) != 'POST') {
            $curl->setOption(CURLOPT_CUSTOMREQUEST, strtoupper($link['method']));
        }
        else {
            $curl->setOption(CURLOPT_POST, 1);
        }
        $res = $curl->post(json_encode($data));
        $this->compileItems([$res]);

        return $this->_items[$res['id']];
    }

    public function __isset($name) {
        return isset($this->_items[$name]) || isset($this->_properties[$name]) || isset($this->$name);
    }

    /**
     * Получение данных по GET-запросу
     *
     * @param       $data
     * @param array $params
     *
     * @return mixed
     */
    protected function loadData($data, $params = []) {
        $curl = Curl::instance();
        $url = $data['href'];
        if (!empty($params)) {
            $url .= is_scalar($params) ? '/' . $params : '?' . http_build_query($params);
        }
        $curl->setUrl($url);

        return $curl->get();
    }

    /**
     * @inheritdoc
     *
     * @param string $offset
     */
    public function offsetExists($offset) {
        isset($this->_items[$offset]);
    }

    /**
     * @inheritdoc
     *
     * @param string $offset
     *
     * @return mixed
     */
    public function offsetGet($offset) {
        return $this->_items[$offset];
    }

    /**
     * @inheritdoc
     *
     * @param string $offset
     */
    public function offsetUnset($offset) {
        unset($this->_items[$offset]);
    }

    /**
     * @inheritdoc
     *
     * @param string $offset
     * @param string $value
     *
     * @throws MailChimpException
     */
    public function offsetSet($offset, $value) {
        throw new MailChimpException('Items is only read');
    }

    /**
     * @inheritdoc
     * @return int
     */
    public function count() {
        return count($this->_items);
    }

    /**
     * @inheritdoc
     */
    public function next() {
        $this->_position = next($this->_keys);
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function key() {
        return $this->_position;
    }

    /**
     * @inheritdoc
     */
    public function rewind() {
        $this->_position = reset($this->_keys);
    }

    /**
     * @inheritdoc
     * @return mixed
     */
    public function current() {
        return $this->_items[$this->_position];
    }

    /**
     * @inheritdoc
     * @return bool
     */
    public function valid() {
        return isset($this->_items[$this->_position]);
    }
}