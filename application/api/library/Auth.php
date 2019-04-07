<?php

namespace app\api\library;

use app\common\model\UserModel;
use think\Config;
use think\exception\DbException;
use think\Hook;
use think\Request;

class Auth
{

    protected static $instance = null;
    protected $_error = '';
    protected $_logined = FALSE;
    protected $_user = NULL;
    protected $_user_id = 0;
    //默认配置
    protected $config = [];

    protected $request = null;
    protected $moduleName = '';
    protected $controllerName = '';
    protected $actionName = '';

    public function __construct($options = [])
    {
        $config = Config::get('token');
        if ($config) {
            $this->config = array_merge($this->config, $config);
        }
        $this->config = array_merge($this->config, $options);

        $this->request = Request::instance();
    }

    /**
     * 
     * @param array $options 参数
     * @return Auth
     */
    public static function instance($options = [])
    {
        if (is_null(self::$instance))
        {
            self::$instance = new static($options);
        }

        return self::$instance;
    }

    /**
     * 根据Token初始化
     *
     * @param string       $token    Token
     * @return boolean
     */
    public function init($token)
    {
        if ($this->_logined)
        {
            return TRUE;
        }
        if ($this->_error)
            return FALSE;
        $data = $this->decryptToken($token);
        if (!$data)
        {
            return FALSE;
        }
        $user_id = intval($data);
        if ($user_id > 0)
        {
            $this->_logined = TRUE;
            $this->_user_id = $user_id;

            //初始化成功的事件
            Hook::listen("user_init_successed", $this->_user);

            return TRUE;
        }
        else
        {
            $this->setError('You are not logged in');
            return FALSE;
        }
    }

    public function getModuleName()
    {
        if (empty($this->moduleName)) {
            $this->moduleName = $this->request->module();
        }
        return $this->moduleName;
    }

    public function getControllerName()
    {
        if (empty($this->controllerName)) {
            $this->controllerName = strtolower($this->request->controller());
        }
        return $this->controllerName;
    }

    public function getActionName()
    {
        if (empty($this->actionName)) {
            $this->actionName = strtolower($this->request->action());
        }
        return $this->actionName;
    }

    public function getUserId()
    {
        return $this->_user_id;
    }

    /**
     * 获取User模型
     * @return UserModel|null
     */
    public function getUser()
    {
        if (empty($this->_user) && $this->_user_id) {
            try {
                $user = UserModel::get($this->_user_id);
            } catch (DbException $exception) {
                $user = null;
            }
            if (!$user)
            {
                $this->setError('Account not exist');
                return null;
            }
            if ($user['status'] != 1)
            {
                $this->setError('Account is locked');
                return null;
            }
            $this->_user = $user;
        }
        return $this->_user;
    }

    /**
     * 兼容调用user模型的属性
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if (empty($this->_user)) {
            $this->getUser();
        }
        return $this->_user ? $this->_user->$name : NULL;
    }

    /**
     * 判断是否登录
     * @return boolean
     */
    public function isLogin()
    {
        if ($this->_logined)
        {
            return true;
        }
        return false;
    }

    /**
     * 检测当前控制器和方法是否匹配传递的数组
     *
     * @param array $noNeedLogin 不需要登录的数组
     * @return boolean 返回false就是需要登录
     */
    public function match($noNeedLogin = [])
    {
        $controller_name = $this->getControllerName();
        $action_name = $this->getActionName();

        if ($noNeedLogin) {
            if (!isset($noNeedLogin[$controller_name])) {
                return false;
            } elseif ($noNeedLogin[$controller_name] && !in_array($action_name, $noNeedLogin[$controller_name])) {
                return false;
            } else {
                return true;
            }
        }
        return false;
    }

    /**
     * 设置错误信息
     *
     * @param string $error 错误信息
     * @return Auth
     */
    public function setError($error)
    {
        $this->_error = $error;
        return $this;
    }

    /**
     * 获取错误信息
     * @return string
     */
    public function getError()
    {
        return $this->_error ? __($this->_error) : '';
    }

    /**
     * @explain 加密用户ID（以后可加有效时间）
     * @param $id
     * @return mixed
     */
    public function encryptToken($id)
    {
        return authcode($id, 'ENCODE', $this->config['key'], $this->config['expire']);
    }

    /**
     * @explain 解密用户ID
     * @param $token
     * @return mixed
     */
    public function decryptToken($token)
    {
        return authcode($token, 'DECODE', $this->config['key']);
    }
}
