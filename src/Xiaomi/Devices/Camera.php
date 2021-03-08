<?php
namespace Xiaomi\Devices;

use Exception;
use Xiaomi\Util\XiaomiClient;

/**
 * Camera device
 *
 * @package Xiaomi\Devices
 */
class Camera extends Device {

    protected $params = [
        "light",
        "motion_record",
        "flip",
        "watermark",
        "sdcard_status",
        "power",
        "wdr",
        "night_mode",
        "mini_level",
        "full_color",
        "max_client",
        "track",
        "improve_program",
        "protocolversion",
    ];

    /**
     * Camera constructor.
     * @param $xiaomiClient XiaomiClient
     * @param $cameraDescription array returned by the API /home/device_list
     * @throws Exception
     */
    public function __construct($xiaomiClient, $cameraDescription) {
        parent::__construct($xiaomiClient, $cameraDescription, $this->params);
    }

}
