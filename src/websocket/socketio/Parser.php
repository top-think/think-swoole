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

        if (!empty($packet->id)) {
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

        $packet = new Packet((int) $str{0});

        // look up namespace (if any)
        if ('/' === $str{$i + 1}) {
            $nsp = '';
            while (++$i) {
                $c = $str{$i};
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
        $next = $str{$i + 1};
        if ('' !== $next && is_numeric($next)) {
            $id = '';
            while (++$i) {
                $c = $str{$i};
                if (null == $c || !is_numeric($c)) {
                    --$i;
                    break;
                }
                $id .= $str{$i};
                if ($i === strlen($str)) {
                    break;
                }
            }
            $packet->id = intval($id);
        }

        // look up json data
        if ($str{++$i}) {
            $packet->data = json_decode(substr($str, $i), true);
        }

        return $packet;
    }
}
