<?php
namespace Xiaomi\Devices;


use Exception;
use Xiaomi\Util\XiaomiClient;

/**
 * Class Device - Generic class for all devices. Is extended by other classes Xiaomi/Devices/*
 *
 * @package Xiaomi\Devices
 */
class Device {

    /** @var XiaomiClient  */
    protected $httpClient;

    /** @var bool  If false, that means the camera is not reachable (network pb, ...) */
    protected $online;

    /** @var int */
    protected $id;
    /** @var string  */
    protected $name;
    /** @var string */
    protected $model;
    /** @var int */
    protected $room_id;
    /** @var string */
    protected $room_name;
    /** @var int */
    protected $home_id;
    /** @var string */
    protected $home_name;

    /** @var array of params  */
    protected $params;

    /**
     * Device constructor.
     * @param $xiaomiClient XiaomiClient
     * @param $deviceDescription array Static description
     * @param $paramNames array Dynamics params names for this device
     * @throws Exception if can not reach the device
     */
    public function __construct($xiaomiClient, $deviceDescription, $paramNames) {
        $this->httpClient = $xiaomiClient;
        $this->id = (int) $deviceDescription['did'];
        $this->name = $deviceDescription['name'];
        $this->model = $deviceDescription['model'];
        $this->room_id = (int) $deviceDescription['room_id'];
        $this->room_name = $deviceDescription['room_name'];
        $this->home_id = (int) $deviceDescription['home_id'];
        $this->home_name = $deviceDescription['home_name'];
        $this->online = $deviceDescription['isOnline'];


        $this->params = array_combine($paramNames, array_fill(0, count($paramNames), null));
    }

    /**
     * Send a request to the device in order to get the params defined by the user
     *
     * @throws Exception
     */
    public function reloadParams() {

        $paramsNames = array_keys($this->params);
        // get props
        $dataQuery = [
            "method" => "get_prop",
            "params" => $paramsNames,
        ];
        $response = $this->httpClient->executeEncryptedRequest('/home/rpc/' . $this->getId(), $dataQuery, true);
        if ($response['code'] == -2) {
            throw new Exception("The device " . $this->name . " is not reachable");
        } else if ($response['code'] != 0) {
            throw new Exception("The device " . $this->name . " returned an error code: " . $response['code'] . " - " . $response['error']['message']);
        }

        // parse results
        $this->params = array_combine($paramsNames, $response['result']);
    }


    /**
     * @return array of all params
     * @throws Exception
     */
    public function getParams() {
        // 'null' should never be a real value. So if it's null, that's means the params have never been loaded.
        if (reset($this->params) === null) {
            $this->reloadParams();
        }
        return $this->params;
    }


    /**
     * @param $paramName string Name of the parameter (depends on device model)
     * @return mixed
     * @throws Exception
     */
    public function getParam($paramName) {

        // 'null' should never be a real value. So if it's null, that's means the params have never been loaded.
        if (reset($this->params) === null) {
            $this->reloadParams();
        }

        if (isset($this->params[$paramName])) {
            return $this->params[$paramName];
        } else {
            throw new Exception("Param $paramName unknown");
        }
    }

    /**
     * @param $paramName string Name of the parameter (depends on device model)
     * @param $paramValue string the value to set
     * @throws Exception
     */
    public function setParam($paramName, $paramValue) {

        // 'null' should never be a real value. So if it's null, that's means the params have never been loaded.
        if (reset($this->params) === null) {
            $this->reloadParams();
        }

        if (!isset($this->params[$paramName])) {
            throw new Exception("Param $paramName unknown");
        }

        // set props
        $dataQuery = [
            "method" => "set_" . $paramName,
            "params" => [(string) $paramValue],
        ];
        $response = $this->httpClient->executeEncryptedRequest('/home/rpc/' . $this->getId(), $dataQuery, true);

        if ($response['code'] != 0) {
            throw new Exception("The device " . $this->name . " returned an error code: " . $response['code'] . " - " . $response['error']['message']);
        }

        // if code=0 then the param has the new value
        $this->params[$paramName] = (string) $paramValue;
    }


    /**
     * @return int
     */
    public function getId(): int {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getModel(): string {
        return $this->model;
    }

    /**
     * @return int
     */
    public function getRoomId(): int {
        return $this->room_id;
    }

    /**
     * @return string
     */
    public function getRoomName(): string {
        return $this->room_name;
    }

    /**
     * @return int
     */
    public function getHomeId(): int {
        return $this->home_id;
    }

    /**
     * @return string
     */
    public function getHomeName(): string {
        return $this->home_name;
    }

}
