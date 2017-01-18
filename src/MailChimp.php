<?php
namespace dragonblack\apimailchimp;

/**
 * Class MailChimp
 *
 * Класс инициализации API
 *
 * @package dragonblack\apimailchimp
 */
class MailChimp {
    protected $_url;
    protected $_key;
    protected $_root;

    public function __construct($apikey) {
        if(empty($apikey)){
            throw new MailChimpException('API key must be defined');
        }

        $this->_key = $apikey;
        list(,$domain) = explode('-', $this->_key);
        $this->_url = 'https://'.$domain.'.api.mailchimp.com/3.0';
        Curl::instance()->setKey($this->_key);
        $this->loadRoot();
    }

    protected function loadRoot(){
        $curl = Curl::instance();
        $res = $curl->setUrl($this->_url.'/')->get();

        $this->_root = new MailChimpResource($res);
    }

    public function __get($name){
        if($name == 'root'){
            return $this->_root;
        }

        throw new MailChimpException('Property "'.$name.'" is not found');
    }

    public function clear(){
        $this->loadRoot();
    }
}