<?php

namespace Xiaomi\Util;

use GuzzleHttp\Client;
use Exception;
use GuzzleHttp\Cookie\CookieJar;

/**
 * Add helper methods to GuzzleClient
 */
class XiaomiClient extends Client {

    const API_DOMAIN = "de"; // Europe => Germany

    /** @var string secret key used to encrypt data */
    protected $sSecurity;
    
    /** @var int counter used for RPC requests */
    protected $rpcCounter = 1;

    /**
     * XiaomiClient constructor.
     *
     * @param $config array Cf GuzzleClient constructor
     */
    public function __construct() {
        $jar = new CookieJar;
        parent::__construct([
            'cookies' => $jar,
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'Dalvik/2.1.0 (Linux; U; Android 9; STF-L09 Build/HUAWEISTF-L09) MIOTWeex/ (SmartHome;5.4.14;90D65564FF7E918AFB0FBFBB8DF8EA27;;A;238DDB38B1C392FDB455422F1CC3CFF4345E4D2B;MI_APP_STORE) MIOTStore/20180406 (SmartHome;5.4.14;90D65564FF7E918AFB0FBFBB8DF8EA27;;A;238DDB38B1C392FDB455422F1CC3CFF4345E4D2B;MI_APP_STORE) APP/xiaomi.smarthome',
            ],
//            'verify'          => false, // SSL certificates
//            'proxy' => [
//                'http'  => 'tcp://localhost:8888', // Use this proxy with "http"
//                'https' => 'tcp://localhost:8888', // Use this proxy with "https",
//            ]
        ]);
    }

    /**
     * @param $sSecurity string Set secret key used to encrypt requests
     */
    public function setSecretSecurity($sSecurity) {
        $this->sSecurity = $sSecurity;
    }

    /**
     * Execute a request to api.io.mi.com and add the encrypted signature.
     *
     * @param $urlPath string Path relative to '/app'.
     * @param $dataQuery array the 'data' parameter of the API
     * @param $rpcMode bool If true, then add a rpc counter. To use only for requests to the '/rpc' api.
     * @return array
     * @throws Exception
     */
    public function executeEncryptedRequest($urlPath, $dataQuery, $rpcMode = false) {

        if (!$this->sSecurity) {
            throw new Exception("You must call first XiaomiClient->setSecretSecurity(...)");
        }

        // Add a RPC counter ?
        if ($rpcMode) {
            $dataQuery['id'] = $this->rpcCounter++;
        }

        // First: compute the 'nonce' parameter.
        $ts = time() + 1; // original: add a diffTime, around 1500ms (so ~ 1 sec)
        $ts = (int) $ts / 60; // in minutes
        $tsBytes4 = hex2bin(str_pad(dechex($ts), 8, '0', STR_PAD_LEFT)); // convert into 8 bytes string
        $randomBytes8 = random_bytes(8); //  8 Random bytes
        $nonce = base64_encode($randomBytes8 . $tsBytes4);

        // now the secret key
        $key = base64_decode( $this->sSecurity) . base64_decode($nonce);
        $secretKey = base64_encode(hash('sha256', $key, true));

        // and the signature
        $jsonDataQuery = json_encode($dataQuery);
        $signature = $urlPath . '&' . $secretKey . '&' . $nonce . '&data=' . $jsonDataQuery;
        $signature = base64_encode(hash_hmac('sha256', $signature, base64_decode($secretKey), true));

        $response = $this->post("https://" . self::API_DOMAIN . ".api.io.mi.com/app" . $urlPath, [
            'form_params' => [
                'signature' => $signature,
                '_nonce' => $nonce,
                'data' => $jsonDataQuery,
            ],
            'headers' => [
                "X-XIAOMI-PROTOCAL-FLAG-CLI" => "PROTOCAL-HTTP2",
                'Content-Type'     => 'application/x-www-form-urlencoded',
            ]
        ]);
        return $this->jsonDecode($response->getBody());
    }

    /**
     * Some APIs return a json string starting with '&&&START&&&'. This method will remove this
     * string before json decoding.
     *
     * @param $body
     * @return array
     */
    public function jsonDecode($body) {
        // some json response starts with a specific string
        if ($body && substr($body, 0, 11) == '&&&START&&&') {
            $body = substr($body, 11);
        }
        $body = json_decode($body, true);
        return $body;
    }


}