<?php

namespace FlameModule\Auth;

use Closure;
use Flame\Attribute\Middleware;
use Flame\Attribute\Module;
use Flame\Attribute\Provides;
use Flame\BaseApiController;
use Flame\BaseModuleService;
use Flame\ModuleManager;
use FlameModule\Auth\Attribute\NeedAuth;
use FlameModule\Auth\Attribute\NoAuth;
use think\Request;
use think\Response;
use Throwable;

#[Module(
    name: 'flame-auth',
    version: '1.0.0',
    description: '身份认证模块',
)]
#[Provides(Auth::class)]
#[Provides(Token::class)]
#[Middleware([Auth::class], 'route')]
class Auth extends BaseModuleService
{
    protected string $name = 'auth';

    public function initialize(): void
    {
        $this->loadConfig([
            // 是否开启全局路由检测，开启后容易影响现有业务
            'enable' => false,
            // 检测策略： allow => 允许所有路由通过，只检查 NeedAuth 路由（不影响现有业务）, block => 阻止所有路由通过，只允许 NoAuth 路由通过（影响现有业务）
            'strategy' => 'allow',

            // 用于传输token的key
            'token_name' => 'user',
            // 密钥
            'key' => '599dd3c7d37cb5e2d5045b60c9b95df4',
            // 加密算法
            'algo' => 'ripemd160',
            // 数据表
            'table' => '',
            // 默认过期时间
            'expire' => 7 * 24 * 3600,
            // sql 链接名称，不指定则使用默认链接
            'name' => '',
        ], true);
    }

    /**
     * middleware handler
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {

        // 未开启鉴权
        if (self::config()->get('enable', false) !== true) return $next($request);

        // 获取当前路由请求是否有放行标记
        $manager = app(ModuleManager::class);

        $hasNoAuth = $manager->currentRouteHasAttribute(NoAuth::class);
        $hasNeedAuth = $manager->currentRouteHasAttribute(NeedAuth::class);

        if (self::config()->get('strategy', 'allow') === 'block') {
            if ($hasNoAuth) return $next($request);
        } else {
            if (!$hasNeedAuth) return $next($request);
        }

        // 检查token
        $token = app(Token::class);
        $tokenValue = $token->getRequestToken();

        if (empty($tokenValue)) $this->TokenExpired();

        try {
            $token->tokenExpirationCheck($tokenValue);
        } catch (Throwable) {
            $this->TokenExpired();
        }

        return $next($request);
    }

    protected function TokenExpired(): void
    {
        BaseApiController::response([], 'Unauthorized', 401, 401);
    }
}