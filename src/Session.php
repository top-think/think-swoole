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
namespace think\swoole;

use think\facade\Cookie;
use think\Session as BaseSession;

/**
 * Swoole Cookie类
 */
class Session extends BaseSession
{
    /**
     * Session数据
     * @var array
     */
    protected $data = [];

    /**
     * 记录Session的Key 保存在Cookie中
     * @var string
     */
    protected $sessionKey = 'session_id';

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * session初始化
     * @access public
     * @param  array $config
     * @return void
     * @throws \think\Exception
     */
    public function init(array $config = [])
    {
        $config = $config ?: $this->config;

        if (isset($config['prefix'])) {
            $this->prefix = $config['prefix'];
        }

        if (!empty($config['auto_start'])) {
            $this->start();
        } else {
            $this->init = false;
        }

        return $this;
    }

    /**
     * session自动启动或者初始化
     * @access public
     * @return void
     */
    public function boot()
    {
        if (is_null($this->init)) {
            $this->init();
        }

        if (false === $this->init) {
            $this->start();
        }
    }

    public function setKey($key)
    {
        $this->sessionKey = $key;
    }

    /**
     * session_id设置
     * @access public
     * @param  string        $id session_id
     * @return void
     */
    public function setId($id)
    {
        Cookie::set($this->sessionKey, $id);
    }

    /**
     * 获取session_id
     * @access public
     * @return string
     */
    public function getId()
    {
        return Cookie::get($this->sessionKey) ?: '';
    }

    /**
     * 设置或者获取session作用域（前缀）
     * @access public
     * @param  string $prefix
     * @return string|void
     */
    public function prefix($prefix = '')
    {
        if (empty($prefix) && null !== $prefix) {
            return $this->prefix;
        } else {
            $this->prefix = $prefix;
        }
    }

    /**
     * 配置
     * @access public
     * @param  array $config
     * @return void
     */
    public function setConfig(array $config = [])
    {
        $this->config = array_merge($this->config, array_change_key_case($config));

        if (isset($config['prefix'])) {
            $this->prefix = $config['prefix'];
        }
    }

    /**
     * session设置
     * @access public
     * @param  string        $name session名称
     * @param  mixed         $value session值
     * @param  string|null   $prefix 作用域（前缀）
     * @return void
     */
    public function set($name, $value, $prefix = null)
    {
        empty($this->init) && $this->boot();

        $sessionId = $this->getId();

        if ($sessionId) {
            $prefix = !is_null($prefix) ? $prefix : $this->prefix;
            $this->setSession($sessionId, $name, $value, $prefix);
        }
    }

    /**
     * session设置
     * @access protected
     * @param  string        $sessionId session_id
     * @param  string        $name session名称
     * @param  mixed         $value session值
     * @param  string|null   $prefix 作用域（前缀）
     * @return void
     */
    protected function setSession($sessionId, $name, $value, $prefix = null)
    {
        if (strpos($name, '.')) {
            // 二维数组赋值
            list($name1, $name2) = explode('.', $name);
            if ($prefix) {
                $this->data[$sessionId][$prefix][$name1][$name2] = $value;
            } else {
                $this->data[$sessionId][$name1][$name2] = $value;
            }
        } elseif ($prefix) {
            $this->data[$sessionId][$prefix][$name] = $value;
        } else {
            $this->data[$sessionId][$name] = $value;
        }
    }

    /**
     * session获取
     * @access public
     * @param  string        $name session名称
     * @param  string|null   $prefix 作用域（前缀）
     * @return mixed
     */
    public function get($name = '', $prefix = null)
    {
        empty($this->init) && $this->boot();

        $sessionId = $this->getId();

        if ($sessionId) {
            $prefix = !is_null($prefix) ? $prefix : $this->prefix;
            return $this->readSession($sessionId, $name, $prefix);
        }
        return;

    }

    /**
     * session获取
     * @access protected
     * @param  string        $sessionId session_id
     * @param  string        $name session名称
     * @param  string|null   $prefix 作用域（前缀）
     * @return mixed
     */
    protected function readSession($sessionId, $name = '', $prefix = null)
    {
        if ($prefix) {
            $value = !empty($this->data[$sessionId][$prefix]) ? $this->data[$sessionId][$prefix] : [];
        } else {
            $value = isset($this->data[$sessionId]) ? $this->data[$sessionId] : [];
        }

        if (!is_array($value)) {
            $value = [];
        }

        if ('' != $name) {
            $name = explode('.', $name);

            foreach ($name as $val) {
                if (isset($value[$val])) {
                    $value = $value[$val];
                } else {
                    $value = null;
                    break;
                }
            }
        }

        return $value;
    }

    /**
     * 删除session数据
     * @access public
     * @param  string|array  $name session名称
     * @param  string|null   $prefix 作用域（前缀）
     * @return void
     */
    public function delete($name, $prefix = null)
    {
        empty($this->init) && $this->boot();

        $sessionId = $this->getId();

        if ($sessionId) {
            $prefix = !is_null($prefix) ? $prefix : $this->prefix;
            $this->deleteSession($sessionId, $name, $prefix);
        }
    }

    /**
     * 删除session数据
     * @access protected
     * @param  string        $sessionId session_id
     * @param  string|array  $name session名称
     * @param  string|null   $prefix 作用域（前缀）
     * @return void
     */
    protected function deleteSession($sessionId, $name, $prefix = null)
    {
        if (is_array($name)) {
            foreach ($name as $key) {
                $this->delete($key, $prefix);
            }
        } elseif (strpos($name, '.')) {
            list($name1, $name2) = explode('.', $name);
            if ($prefix) {
                unset($this->data[$sessionId][$prefix][$name1][$name2]);
            } else {
                unset($this->data[$sessionId][$name1][$name2]);
            }
        } else {
            if ($prefix) {
                unset($this->data[$sessionId][$prefix][$name]);
            } else {
                unset($this->data[$sessionId][$name]);
            }
        }
    }

    /**
     * 清空session数据
     * @access public
     * @param  string|null   $prefix 作用域（前缀）
     * @return void
     */
    public function clear($prefix = null)
    {
        empty($this->init) && $this->boot();

        $sessionId = $this->getId();

        if ($sessionId) {
            $prefix = !is_null($prefix) ? $prefix : $this->prefix;
            $this->clearSession($sessionId, $prefix);
        }

    }

    /**
     * 清空session数据
     * @access protected
     * @param  string        $sessionId session_id
     * @param  string|null   $prefix 作用域（前缀）
     * @return void
     */
    protected function clearSession($sessionId, $prefix)
    {
        if ($prefix) {
            unset($this->data[$sessionId][$prefix]);
        } else {
            unset($this->data[$sessionId]);
        }
    }

    /**
     * 判断session数据
     * @access public
     * @param  string        $name session名称
     * @param  string|null   $prefix
     * @return bool
     */
    public function has($name, $prefix = null)
    {
        empty($this->init) && $this->boot();
        $sessionId = $this->getId();

        if ($sessionId) {
            $prefix = !is_null($prefix) ? $prefix : $this->prefix;
            return $this->hasSession($sessionId, $name, $prefix);
        }

        return false;
    }

    /**
     * 判断session数据
     * @access protected
     * @param  string        $sessionId session_id
     * @param  string        $name session名称
     * @param  string|null   $prefix
     * @return bool
     */
    protected function hasSession($sessionId, $name, $prefix = null)
    {
        $value = $prefix ? (!empty($this->data[$sessionId][$prefix]) ? $this->data[$sessionId][$prefix] : []) : $this->data[$sessionId];

        $name = explode('.', $name);

        foreach ($name as $val) {
            if (!isset($value[$val])) {
                return false;
            } else {
                $value = $value[$val];
            }
        }

        return true;
    }

    /**
     * 启动session
     * @access public
     * @return void
     */
    public function start()
    {
        $sessionId = $this->getId();

        if (!$sessionId) {
            $this->regenerate();
        }

        $this->init = true;
    }

    /**
     * 销毁session
     * @access public
     * @return void
     */
    public function destroy()
    {
        $sessionId = $this->getId();

        if ($sessionId) {
            $this->destroySession($sessionId);
        }

        $this->init = null;
    }

    /**
     * 销毁session
     * @access protected
     * @param  string        $sessionId session_id
     * @return void
     */
    protected function destroySession($sessionId)
    {
        if (isset($this->data[$sessionId])) {
            unset($this->data[$sessionId]);
        }
    }

    /**
     * 重新生成session_id
     * @access public
     * @param  bool $delete 是否删除关联会话文件
     * @return void
     */
    public function regenerate($delete = false)
    {
        if ($delete) {
            $this->destroy();
        }

        $sessionId = password_hash(uniqid(), PASSWORD_DEFAULT);

        $this->setId($sessionId);
    }

    /**
     * 暂停session
     * @access public
     * @return void
     */
    public function pause()
    {
        $this->init = false;
    }
}
