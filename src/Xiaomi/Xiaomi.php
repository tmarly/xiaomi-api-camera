<?php
namespace Xiaomi;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Xiaomi\Devices\Camera;
use Xiaomi\Devices\Device;
use Xiaomi\Util\Util;
use Xiaomi\Util\XiaomiClient;
use Exception;

class Xiaomi {


    /** @var string encoded id for the phone or server */
    protected $deviceId;

    /** @var XiaomiClient  */
    protected $httpClient;

    /** @var bool true when authenticated */
    protected $authenticated;

    /** @var int user id */
    protected $userId;
    /** @var string key used to encrypt data  */
    protected $sSecurityMain; // service security ? (linked to the service)
    /** @var array key used to encrypt data for third party apis */
    protected $sSecurityByService; // service security ? (linked to the service)
    /** @var string token used for some APIs */
    protected $serviceToken;

    /** @var array of Device */
    protected $devicesByClass;


    public function __construct() {
        $this->authenticated = false;

        $this->httpClient = new XiaomiClient();
    }

    public function __destruct() {
    }

    /**
     * @param $email
     * @param $password
     * @throws Exception on authentication problem
     */
    public function auth($email, $password) {


        // 1 step: call a WS that will reset all cookies, and that will send back '_sign'
        $response = $this->httpClient->get("https://account.xiaomi.com/pass/serviceLogin", [
            'query' => [
                '_json' => 'true',
                'sid' => 'xiaomiio',
            ]
        ]);
        $data = $this->httpClient->jsonDecode($response->getBody());

        // Now, let's set some mandatory cookies
        /** @var CookieJar $jar */
        $jar = $this->httpClient->getConfig('cookies');
        $jar->clear();
        $jar->setCookie(new SetCookie(['Name' => 'deviceId', 'Value' => $this->getLocalDeviceId(), 'Domain' => 'xiaomi.com', 'Path' => '/', 'Secure' => false, 'HttpOnly' => false]));
        $jar->setCookie(new SetCookie(['Name' => 'deviceId', 'Value' => $this->getLocalDeviceId(), 'Domain' => 'mi.com', 'Path' => '/', 'Secure' => false, 'HttpOnly' => false]));
        $jar->setCookie(new SetCookie(['Name' => 'sdkVersion', 'Value' => $this->getSdkVersion(), 'Domain' => 'xiaomi.com', 'Path' => '/', 'Secure' => false, 'HttpOnly' => false]));
        $jar->setCookie(new SetCookie(['Name' => 'sdkVersion', 'Value' => $this->getSdkVersion(), 'Domain' => 'mi.com', 'Path' => '/', 'Secure' => false, 'HttpOnly' => false]));


        // 2nd step: auth
        $response = $this->httpClient->post("https://account.xiaomi.com/pass/serviceLoginAuth2", [
            'form_params' => [
                'qs' => $data['qs'],
                'callback' => $data['callback'],
                '_sign' => $data['_sign'],
                'sid' => $data['sid'],
                '_json'  => 'true',
                //'env'  => ??? (seems optional !)
                //'envKey' => ??? (seems optional !)
                'user' => $email,
                'hash' => strtoupper(md5($password)),
            ]
        ]);
        $data = $this->httpClient->jsonDecode($response->getBody());

        // Success ?
        if (!isset($data['userId'])) {
            throw new Exception("Authentication failed !");
        }

        // Store important values
        $this->authenticated = true;
        $this->userId = $data['userId'];
        $this->sSecurityMain = $data['ssecurity']; // ??
        $this->httpClient->setSecretSecurity($this->sSecurityMain);

        // we must follow the location in order to get the cookie value for 'serviceToken'
        $url = $data['location'];
        // Must add these 2 variables otherwise 401 !
        $url .= '&_userIdNeedEncrypt=true';
        $clientSign = base64_encode(sha1('nonce=' . $data['nonce'] . '&' . $this->sSecurityMain, true));
        $url .= '&clientSign=' . urlencode($clientSign);
        $this->httpClient->get($url);

        // We store important data for next requests
        $this->serviceToken = $jar->getCookieByName('serviceToken')->getValue();

        if (!$this->serviceToken) {
            throw new Exception("No service token !");
        }

        $jar->setCookie(new SetCookie(['Name' => 'userId', 'Value' => $this->userId, 'Domain' => 'mi.com', 'Path' => '/', 'Secure' => false, 'HttpOnly' => false]));
        $jar->setCookie(new SetCookie(['Name' => 'yetAnotherServiceToken', 'Value' => $this->serviceToken, 'Domain' => 'mi.com', 'Path' => '/', 'Secure' => false, 'HttpOnly' => false]));
        $jar->setCookie(new SetCookie(['Name' => 'serviceToken', 'Value' => $this->serviceToken, 'Domain' => 'mi.com', 'Path' => '/', 'Secure' => false, 'HttpOnly' => false]));
        $jar->setCookie(new SetCookie(['Name' => 'locale', 'Value' => 'fr_FR', 'Domain' => 'mi.com', 'Path' => '/', 'Secure' => false, 'HttpOnly' => false]));
        $jar->setCookie(new SetCookie(['Name' => 'timezone', 'Value' => 'GMT+01:00', 'Domain' => 'mi.com', 'Path' => '/', 'Secure' => false, 'HttpOnly' => false]));
        $jar->setCookie(new SetCookie(['Name' => 'is_daylight', 'Value' => '1', 'Domain' => 'mi.com', 'Path' => '/', 'Secure' => false, 'HttpOnly' => false]));
        $jar->setCookie(new SetCookie(['Name' => 'dst_offset', 'Value' => '3600000', 'Domain' => 'mi.com', 'Path' => '/', 'Secure' => false, 'HttpOnly' => false]));

        // Authenticate to other services
        // (commented because do not seem usefull for what we need)
        //$this->authOtherService('passportapi');
        //$this->authOtherService('miot-third-test');
        return;
    }

    /**
     * @return array of devices of any type
     * @throws Exception
     */
    public function getDevices() {

        // first we must load devices
        if (!$this->devicesByClass) {
            $this->devicesByClass = $this->loadDevices();
        }

        $allDevices = [];
        foreach($this->devicesByClass as $type => $devices) {
            $allDevices = array_merge($allDevices, $devices);
        }

        return $allDevices;
    }

    /**
     * @return array of devices of type 'Camera'
     * @throws Exception
     */
    public function getCameras() {

        // first we must load devices
        if (!$this->devicesByClass) {
            $this->devicesByClass = $this->loadDevices();
        }

        return $this->devicesByClass[Camera::class];
    }

    /**
     * Authenticate to third party services which are needed for some specific API calls.
     *
     * @param $service string available services: 'passportapi', 'miot-third-test'
     */
    protected function authOtherService($service) {
        // Get other info PASSPORT API  (usefull to get the security key for the subsequent calls)
        $response = $this->httpClient->get("https://account.xiaomi.com/pass/serviceLogin", [
            'query' => [
                '_json' => 'true',
                'sid' => $service,
            ],
        ]);
        $data = $this->httpClient->jsonDecode($response->getBody());
        $this->sSecurityByService[$service] = $data['ssecurity'];

        // and call the callback otherwise not logged  (to be confirmed, is it really usefull ? or is it only for analytics ??
        $url = $data['location'];
        $url .= '&_userIdNeedEncrypt=true';
        $clientSign = base64_encode(sha1('nonce=' . $data['nonce'] . '&' . $this->sSecurityByService[$service], true));
        $url .= '&clientSign=' . urlencode($clientSign);
        $this->httpClient->get($url);
    }

    /**
     * Get devices for all homes and rooms
     *
     * @throws Exception
     */
    protected function loadDevices() {

        // Must be authenticated
        if (!$this->authenticated) {
            throw new Exception("You must authenticate first");
        }

        $dataQuery = ['fg' => false];
        $response = $this->httpClient->executeEncryptedRequest('/homeroom/gethome', $dataQuery);

        $deviceIds = [];

        // loop over each home
        foreach($response['result']['homelist'] as $home) {
            // loop over each room
            foreach($home['roomlist'] as $room) {
                // loop over each device
                foreach($room['dids'] as $deviceId) {
                    $deviceIds[$deviceId] = [
                        'home_id' => $home['id'],
                        'home_name' => $home['name'],
                        'room_id' => $room['id'],
                        'room_name' => $room['name'],
                        ];
                }
            }
        }

        // get description for each device
        $dataQuery = ['dids' => array_map('strval', array_keys($deviceIds))]; // strval: convert device ids int -> string
        $response = $this->httpClient->executeEncryptedRequest('/home/device_list', $dataQuery);

        $devicesByClass = [];

        // loop over each device
        foreach($response['result']['list'] as $deviceData) {
            // add home & room information
            $deviceData = array_merge($deviceData, $deviceIds[(int)$deviceData['did']]);

            // now, only camera api is implemented
            switch($deviceData['model']) {
                case 'chuangmi.camera.ipc009':
                    $device = new Camera($this->httpClient, $deviceData);
                    break;
                default:
                    echo "Device " . $deviceData . " is not implemented.";
                    // however we load it anyway
                    $device = new Device($this->httpClient, $deviceData, []);
                    break;
            }
            $devicesByClass[get_class($device)][] = $device;
        }

        return $devicesByClass;
    }


    /**
     * @return string An encoded string to represent the phone or server from which
     * the Xiaomi API is called.
     */
    protected function getLocalDeviceId() {

        if (!$this->deviceId) {
            $IMEI = false; // On the phone, the Xiaomi app use the IMEI as device ID (which is a long int). however on php servers there are no IMEI.
            if ($IMEI) {
                $id = $IMEI;
            } else {
                // Xiaomi use the Mac Adress (for WIFI) if IMEI not available.
                $id = Util::getMacAddress();
                if (!$id) {
                    // MAC address not found, so random ID
                    $id = uniqid();
                }
            }

            // Sha1 hash
            $sha1 = sha1($id, true);

            // Keep 16 chars
            $this->deviceId = substr(Util::base64EncodeUrlSafe($sha1), 0, 16);
        }
        return $this->deviceId;
    }


    /**
     * @return string fake sdk version
     */
    protected function getSdkVersion() {
        return "accountsdk-18.4.16";
    }

}