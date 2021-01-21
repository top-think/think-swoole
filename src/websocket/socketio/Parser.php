<?php

namespace think\swoole\websocket\socketio;

class Parser
{

    public static function encode(Packet $packet)
    {
        $str = '' . $packet->type;
        if ($packet->nsp && '/' !== $packet->nsp) {
            $str .= $packet->nsp . ',';
        }

        if ($packet->id !== null) {
            $str .= $packet->id;
        }

        if (null !== $packet->data) {
            $str .= json_encode($packet->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $str;
    }

    public static function decode(string $str)
    {
        $i = 0;

        $packet = new Packet((int) substr($str, 0, 1));

        // look up namespace (if any)
        if ('/' === substr($str, $i + 1, 1)) {
            $nsp = '';
            while (++$i) {
                $c = substr($str, $i, 1);
                if (',' === $c) {
                    break;
                }
                $nsp .= $c;
                if ($i === strlen($str)) {
                    break;
                }
            }
            $packet->nsp = $nsp;
        } else {
            $packet->nsp = '/';
        }

        // look up id
        $next = substr($str, $i + 1, 1);
        if ('' !== $next && is_numeric($next)) {
            $id = '';
            while (++$i) {
                $c = substr($str, $i, 1);
                if (null == $c || !is_numeric($c)) {
                    --$i;
                    break;
                }
                $id .= substr($str, $i, 1);
                if ($i === strlen($str)) {
                    break;
                }
            }
            $packet->id = intval($id);
        }

        // look up json data
        if (substr($str, ++$i, 1)) {
            $packet->data = json_decode(substr($str, $i), true);
        }

        return $packet;
    }
}
