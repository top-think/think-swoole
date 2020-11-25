<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\swoole {

    use Swoole\Server\Helper;

    function checkOptions(array $options)
    {
        if (class_exists(Helper::class)) {
            $constOptions = Helper::GLOBAL_OPTIONS + Helper::SERVER_OPTIONS + Helper::PORT_OPTIONS + Helper::HELPER_OPTIONS;
            foreach ($options as $k => $v) {
                if (!array_key_exists(strtolower($k), $constOptions)) {
                    unset($options[$k]);
                }
            }
        }
        return $options;
    }
}
