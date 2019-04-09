<?php

namespace think\swoole\websocket\socketio;

use think\Config;
use think\Cookie;
use think\Request;

class Controller
{
    protected $transports = ['polling', 'websocket'];

    public function upgrade(Request $request, Config $config, Cookie $cookie)
    {
        if (!in_array($request->param('transport'), $this->transports)) {
            return json(
                [
                    'code'    => 0,
                    'message' => 'Transport unknown',
                ],
                400
            );
        }

        if ($request->has('sid')) {
            $response = response('1:6');
        } else {
            $sid     = base64_encode(uniqid());
            $payload = json_encode(
                [
                    'sid'          => $sid,
                    'upgrades'     => ['websocket'],
                    'pingInterval' => $config->get('swoole.websocket.ping_interval'),
                    'pingTimeout'  => $config->get('swoole.websocket.ping_timeout'),
                ]
            );
            $cookie->set('io', $sid);
            $response = response('97:0' . $payload . '2:40');
        }

        return $response->contentType('text/plain');
    }

    public function reject(Request $request)
    {
        return json(
            [
                'code'    => 3,
                'message' => 'Bad request',
            ],
            400
        );
    }
}
