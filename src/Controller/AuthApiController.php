<?php

namespace FlameModule\Auth\Controller;

use Flame\BaseApiController;
use FlameModule\Auth\Token;
use think\App;
use think\Request;

/**
 * 具有 Auth 验证的 Api 控制器
 */
class AuthApiController extends BaseApiController
{
    /** @var mixed 登录用户 ID */
    private int $userId = 0;

    /** @var string (必填)登录用户 token 类型，自定义，如：user, admin, member,... */
    protected string $tokenType = 'user';

    /** @var string token 值 */
    private string $token = '';

    /** @var string 刷新 token 值 */
    private string $refreshToken = '';

    public function __construct(App $app, Request $request, protected readonly Token $tokenManager)
    {
        parent::__construct($app, $request);
        $this->initToken();
    }

    protected function initToken(): void
    {
        // 从请求中获取用户 ID
        $token = $this->tokenManager->getRequestToken();
        $tokenData = $this->tokenManager->get($token);
        if ($tokenData && isset($tokenData['type']) && $tokenData['type'] == $this->getTokenType()) {
            $this->userId = $tokenData['user_id'];
            $this->token = $token;
        }
    }

    /**
     * 检查用户是否登录，未登录则返回错误信息清空token
     * @return void
     */
    protected function checkAuth(): void
    {
        if (!$this->checkToken() || !$this->isLoggedIn()) {
            $this->tokenManager->delete($this->getToken());
            $this->error(null, 'please login first', 401);
        }
    }

    /**
     * 检查 Token 是否正确
     */
    protected function checkToken(): bool
    {
        return $this->tokenManager->check($this->token, $this->getTokenType(), $this->userId);
    }

    /**
     * 检查用户是否登录
     * @return bool
     */
    protected function isLoggedIn(): bool
    {
        return !empty($this->userId);
    }

    /**
     * 获取 userId
     * @return int
     */
    protected function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * 设置 userId，同时会重新生成新的token
     * @param int $userId
     * @param int $keepTime
     * @param bool $keep
     */
    protected function setUserId(int $userId, int $keepTime = 20 * 60, bool $keep = false): void
    {
        $this->clearToken($userId);
        // 生成新的 token
        $this->userId = $userId;
        $this->token = Token::generateToken();
        $this->tokenManager->set($this->getToken(), $this->getTokenType(), $this->userId, $keepTime);
        if ($keep) $this->setRefreshToken();
    }

    /**
     * 获取 token
     * @return string
     */
    protected function getToken(): string
    {
        return $this->token;
    }

    /**
     * 获取刷新 token
     * @return string
     */
    protected function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    /**
     * 设置刷新 token
     * @param int $keepTime 刷新 token 保存时间，单位秒
     * @return string
     */
    protected function setRefreshToken(int $keepTime = 0): string
    {
        if (empty($this->userId)) return '';
        $this->refreshToken = Token::generateToken();
        $this->tokenManager->clear($this->userId, $this->getTokenType() . '-refresh');
        $this->tokenManager->set($this->refreshToken, $this->getTokenType() . '-refresh', $this->userId, $keepTime);
        return $this->refreshToken;
    }

    /**
     * 清除 token 信息
     */
    protected function clearToken(int $userId = 0): void
    {
        $userId = $userId ?: $this->userId;
        $this->tokenManager->clear($userId, $this->getTokenType());
        $this->tokenManager->clear($userId, $this->getTokenType() . '-refresh');
        $this->userId = 0;
        $this->token = $this->refreshToken = '';
    }

    /**
     * 获取 token 类型
     * @return string
     */
    protected function getTokenType(): string
    {
        return $this->tokenType ?? 'user';
    }
}