## About Laravel

* 通过访问 `/api/login` 登录，输入邮箱和密码。

* 注册路由 在 AuthServiceProvider 的 boot 方法中

如果你的程序是需要前后端分离形式的OAuth认证而不是多平台认证那么你可以在routers()方法中传递一个匿名函数来自定定义自己需要注册的路由，我这里是前后端分离的认证形式，因此我只需要对我的前端一个Client提供Auth的认证，所以我只注册了获取Token的路由，同时我还为它自定义了前缀名。
```php
Passport::routes(function(RouteRegistrar $router) {
    $router->forAccessTokens();
},['prefix' => 'api/oauth']);
```

* 通过HTTP请求库去请求Passport, 列如：guzzlehttp

```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth')->except('login');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('home');
    }

    public function login(Request $request)
    {
//        $needs = $this->validate($request, rules('login'));
        $user = User::where('email', $request->get('email'))->first();

        if (!$user) {
            throw new UnauthorizedException('此用户不存在');
        }
        $tokens = $this->authenticate();
        $respond = ['token' => $tokens, 'user' => new UserResource($user)];
        return response()->json(['status' => '200', is_string($respond) ? 'message' : 'data' => $respond]);
    }


    public function authenticate()
    {
        $client = new Client();
        try {
            $url = request()->root() . '/api/oauth/token';
            $params = array_merge(config('passport.proxy'), [
                'username' => request('email'),
                'password' => request('password'),
            ]);
            $respond = $client->post($url, ['form_params' => $params]);
        } catch (RequestException $exception) {
            throw  new UnauthorizedException('请求失败，服务器错误');
        }

        if ($respond->getStatusCode() !== 401) {
            return json_decode($respond->getBody()->getContents(), true);
        }

        throw new UnauthorizedException('账号或密码错误');
    }
}

```

* 说一下使用代理的原因，Passport 认证的流程是 从属应用带着 主应用
生成的 Client secret 和 用户输入的账号密码去请求主应用的 Passport Token 路由，以获得 access token （访问令牌） 和 refresh token （刷新令牌），然后带着得到的 access token 就可以访问 auth:api 下的路由了。但是我们并没有从属应用，是由前后端分离的前端来请求这个token，如果从前端想来拉取这个 access token 就需要把 Client token 写死在前端里，这样是很不合理的，所以我们可以在内部写一个代理，由应用自身带着 Client token 去请求自身以获取 access token，这样说可能有一点绕，大概请求过程是下面这个样子

```text
1.前端带着用户输入的账号密码请求服务端
2.服务端带着从前端接收到账号与密码，并在其中添加 Client_id 与 Client_secret，然后带着这些参数请求自身的 Passport 认证路由，然后返回认证后的 Access token 与 refresh token
```