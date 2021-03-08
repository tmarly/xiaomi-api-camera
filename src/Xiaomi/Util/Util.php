<?php

namespace Xiaomi\Util;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Throwable;

class Util {

    /**
     * @return string the MAC Adress
     */
    public static function getMacAddress() {
        try {
            // find the interface (wifi, ethernet, ...)
            $route = shell_exec("ip route get 8.8.8.8");
            preg_match('/.*dev\s+(\w+)\s+src.*/', $route, $matches);
            $interface = $matches[1];

            // now get the MAC address
            $definition = shell_exec("ifconfig | grep $interface");
            preg_match('/.*HWaddr\s+(\S+).*/', $definition, $matches);
            $mac = $matches[1];
        } catch (Throwable $t) {
            $mac = false;
        }
        return $mac;
    }

    /**
     * Base 64 URL Safe
     * @return string the MAC Adress
     */
    public static function base64EncodeUrlSafe($string) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($string));
    }

    /**
     * Helper method to debug development
     * @param $httpClient Client
     */
    public static function dumpCookies($httpClient) {
        $jar = $httpClient->getConfig('cookies');
        $cookies = $jar->getIterator();
        $text = [];
        foreach($cookies as $cookie) {
            $text[] = $cookie;
        }
        sort($text);
        echo implode("\n", $text) . "\n";
    }

    /**
     * Helper method to clean cookies that have expired.
     * @param $httpClient Client
     */
    public static function cleanExpiredCookies($httpClient) {
        /** @var CookieJar $jar */
        $jar = $httpClient->getConfig('cookies');
        $cookies = $jar->getIterator();
        /** @var SetCookie $cookie */
        foreach($cookies as $cookie) {
            if ($cookie->isExpired()) {
                $jar->clear($cookie->getDomain(), $cookie->getPath(), $cookie->getName());
            }
        }

    }


}