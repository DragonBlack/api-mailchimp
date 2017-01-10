<?php
namespace dragonblack\apimailchimp;

/**
 * Class Curl
 *
 * Класс реализации curl-запросов
 *
 * @package dragonblack\apimailchimp
 */
class Curl {

    /** @var resource Ресурс curl */
    protected $_ch;

    protected $_options = [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ];

    /** @var array Информация о последнем запросе */
    protected $_lastInfo = [];

    /** @var  string Последняя ошибка curl */
    protected $_lastError;

    /** @var  mixed  Результат последнего запроса */
    protected $_lastResult;

    /** @var  string URL запроса */
    protected $_url;

    /** @var  Curl */
    protected static $_instance;

    private $_customOptions = [];

    protected function __construct() {
        //$this->_ch = curl_init();
        $this->clearOptions();
    }

    public static function instance() {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    protected function clearOptions(){
        $this->_customOptions = [];
    }

    /**
     * Сеттер токена
     *
     * @param $key
     *
     * @return $this
     */
    public function setKey($key) {
        $this->_options[CURLOPT_USERPWD] = 'apimailchimp:' . $key;

        return $this;
    }

    /**
     * Устанавливает URL запроса
     *
     * @param $url
     *
     * @return $this
     */
    public function setUrl($url) {
        $this->_url = $url;

        return $this;
    }

    /**
     * Возвращает результат запроса
     *
     * @return mixed
     * @throws MailChimpException
     */
    public function get() {
        $this->_ch = curl_init();
        curl_setopt_array($this->_ch, $this->_options + $this->_customOptions);
        curl_setopt($this->_ch, CURLOPT_URL, $this->_url);
        $this->_lastResult = curl_exec($this->_ch);
        $this->_lastInfo = curl_getinfo($this->_ch);
        $this->_lastError = curl_error($this->_ch);
        curl_close($this->_ch);
        $this->clearOptions();
        try {
            $this->_lastResult = json_decode($this->_lastResult, true);
        } catch (\Exception $e) {
            throw new MailChimpException('Error: Response code: ' . $this->_lastInfo['http_code'] . ' Curl error: ' . $this->_lastError);
        }

        if ($this->_lastInfo['http_code'] != 200 && $this->_lastInfo['http_code'] != 204) {
            throw new MailChimpException('Error: ' . $this->_lastResult['title'] . '(' . $this->_lastInfo['http_code'] . ') Description: ' . var_export($this->_lastResult, true));
        }

        if (!$this->_lastError) {
            return $this->_lastResult;
        }
        throw new MailChimpException($this->_lastError);
    }

    /**
     * Возвращает результат не-GET запроса
     *
     * @param $data
     *
     * @return mixed
     */
    public function post($data) {
        $this->_customOptions[CURLOPT_HTTPHEADER][] = 'Content-Length: ' . strlen($data);
        $this->_customOptions[CURLOPT_POSTFIELDS] = $data;

        return $this->get();
    }

    /**
     * Установка режима тестирования ошибок
     *
     * @param $code
     *
     * @return Curl
     */
    public function setErrorMode($code) {
        $headers = [
            'X-Trigger-Error: ' . $code
        ];

        return $this->setOption(CURLOPT_HTTPHEADER, $headers);
    }

    /**
     * Установка опций работы curl
     *
     * @param $code
     * @param $value
     *
     * @return $this
     */
    public function setOption($code, $value) {
        $this->_customOptions[$code] = $value;

        return $this;
    }

    /**
     * Возвращает результат последнего запроса
     *
     * @return mixed
     */
    public function getLastResult() {
        return $this->_lastResult;
    }

    /**
     * Возвращает информацию о последнем запросе
     *
     * @return array
     */
    public function getLastInfo() {
        return $this->_lastInfo;
    }

    /**
     * Возвращает последнюю ошибку curl
     *
     * @return mixed
     */
    public function getLastError() {
        return $this->_lastError;
    }
}