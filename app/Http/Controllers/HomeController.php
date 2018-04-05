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
