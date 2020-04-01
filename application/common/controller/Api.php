<?php

namespace app\common\controller;

use app\api\library\Auth;
use think\Cache;
use think\Config;
use think\Hook;
use think\Lang;
use think\Request;

/**
 * API控制器基类
 */
class Api
{
    protected $user_id = 0;
    protected $version = 1;

    /**
     * @var Request Request 实例
     */
    protected $request;

    /**
     * @var array 前置操作方法列表
     */
    protected $beforeActionList = [];

    /**
     * 无需登录的方法,同时也就不需要鉴权了（一维数组是控制器名称，二维是方法名称）（某模块是空数组代表整个模块都不需要登录）（剩下的接口都必须要登录）
     * @var array
     */
    protected $noNeedLogin = [
        'user' => [
            'login',
        ],
        'common' => [
            'error_log',
        ],
    ];

    /**
     * 权限Auth
     * @var Auth 
     */
    protected $auth = null;

    /**
     * 请求时间
     * @var int
     */
    protected $request_time = null;

    /**
     * 构造方法
     * @access public
     * @param Request $request Request 对象
     */
    public function __construct(Request $request = null)
    {
        $this->request = is_null($request) ? Request::instance() : $request;

        // 控制器初始化
        $this->_initialize();

        // 前置操作方法
        if ($this->beforeActionList)
        {
            foreach ($this->beforeActionList as $method => $options)
            {
                is_numeric($method) ?
                                $this->beforeAction($options) :
                                $this->beforeAction($method, $options);
            }
        }
    }

    public function _empty()
    {
        $this->error('Not Found', 404);
    }

    /**
     * 初始化操作
     * @access protected
     */
    protected function _initialize()
    {
        //移除HTML标签
        $this->request->filter('strip_tags');

        $module_name = $this->request->module();
        $controller_name = strtolower($this->request->controller());
        $action_name = strtolower($this->request->action());

        $this->auth = Auth::instance();

        // token
        $token = $this->request->header('token', $this->request->request('token', \think\Cookie::get('token')));

        // 检测是否需要验证登录
        if (!$this->auth->match($this->noNeedLogin))
        {
            //初始化
            $this->auth->init($token);
            //检测是否登录
            if (!$this->auth->isLogin())
            {
                $this->error(__('Please login first'), 401);
            }
        }
        else
        {
            // 如果有传递token才验证是否登录状态
            if ($token)
            {
                $this->auth->init($token);
            }
        }

        $this->user_id = $this->auth->getUserId();

        //别问为什么，问就是框架有BUG。在前面初始化Hook的时候就加载了默认配置，所以自定义配置无效，这里要清除掉
        Cache::$handler = null;

        // 上传信息配置
        $upload = \app\common\model\Config::upload();
        Hook::listen("upload_config_init", $upload);
        Config::set('upload', array_merge(Config::get('upload') ?: [], $upload));

        // 加载当前控制器语言包
        $this->loadlang($controller_name);

        $this->request_time = $this->request->server('REQUEST_TIME');
    }

    protected function getUserInfo()
    {
        return $this->auth->getUser();
    }

    /**
     * 加载语言文件
     * @param string $name
     */
    protected function loadlang($name)
    {
        Lang::load(APP_PATH . $this->request->module() . '/lang/' . $this->request->langset() . '/' . str_replace('.', '/', $name) . '.php');
    }

    /**
     * @explain 获取post和get
     * @param $key
     * @param string $type 数据类型，支持int,float和默认的str
     * @param string $default 默认值
     * @return float|int|string
     * @throws \Exception
     */
    protected function input($key = null, $type = 'str', $default = '')
    {
        static $input;
        if (empty($input)) {
            $input = request()->put();//post了raw的json字符串过来的话
            if (empty($input)) {
                $input = request()->post();
            }
            $input = array_merge($input, request()->get());
        }

        if ($key === null) {
            return $input;
        }

        $value = isset($input[$key]) ? $input[$key] : '';

        if (is_array($value)) {
            return $value;
        }

        $value = trim($value);
        //默认值
        if ($value === '' && !empty($default)) {
            $value = $default;
        }

        //担心前端JS没控制好
        if ($value === 'undefined' || $value === 'null') {
            if (config('app_debug')) {//调试模式直接报错
                throw new \Exception('参数错误', 400);
            } else {
                trace('参数为undefined或null', 'error');//记下错误日志
                $value = '';
            }
        }

        //修饰
        if ($type == 'str') {
            $value = strval($value);
        } elseif ($type == 'int') {
            $value = intval($value);
        } elseif ($type == 'float') {
            $value = floatval($value);
        }
        return $value;
    }

    /**
     * @explain 检查参数是否为空
     * @param mixed $data
     * @param bool $cannot_be_zero 默认可以是0，如果确定参数为0是错误的话可以设为true
     * @throws \Exception
     */
    protected function checkParams($data, $cannot_be_zero = false)
    {
        if (is_array($data)) {
            foreach ($data as $value) {
                if ($cannot_be_zero) {
                    if (empty($value)) {
                        throw new \Exception('参数错误', 400);
                    }
                } else {
                    if ($value === '' || $value === null) {
                        throw new \Exception('参数错误', 400);
                    }
                }
            }
        } else {
            if ($cannot_be_zero) {
                if (empty($data)) {
                    throw new \Exception('参数错误', 400);
                }
            } else {
                if ($data === '' || $data === null) {
                    throw new \Exception('参数错误', 400);
                }
            }
        }
    }

    /**
     * @explain 用于处理正常输出
     * @param $data
     * @param string $msg
     */
    protected function output($data = [], $msg = null)
    {
        $this->output_json($data, 0, $msg);
    }

    protected function success($msg = '', $data = [])
    {
        $this->output_json($data, 0, $msg);
    }

    protected function error($msg = '', $code = 500, $data = [])
    {
        $this->output_json($data, $code, $msg);
    }

    /**
     * 统一输出JSON
     * @param array  $data json中data的内容
     * @param int    $code 通知码,默认0
     * @param string $msg 通知信息,默认ok
     */
    protected function output_json($data = array(), $code = 0, $msg = null)
    {
        $output = get_output_contents($data, $code, $msg);
        out_json($output);
    }

    /**
     * 前置操作
     * @access protected
     * @param  string $method  前置操作方法名
     * @param  array  $options 调用参数 ['only'=>[...]] 或者 ['except'=>[...]]
     * @return void
     */
    protected function beforeAction($method, $options = [])
    {
        if (isset($options['only']))
        {
            if (is_string($options['only']))
            {
                $options['only'] = explode(',', $options['only']);
            }

            if (!in_array($this->request->action(), $options['only']))
            {
                return;
            }
        }
        elseif (isset($options['except']))
        {
            if (is_string($options['except']))
            {
                $options['except'] = explode(',', $options['except']);
            }

            if (in_array($this->request->action(), $options['except']))
            {
                return;
            }
        }

        call_user_func([$this, $method]);
    }

}
