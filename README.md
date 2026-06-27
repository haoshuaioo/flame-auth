# Flame Auth Module

基于 [Flame Module Kernel](https://github.com/haoshuaioo/flame-module-kernel) 的身份认证模块，提供 Token 管理和路由鉴权功能。

## ✨ 特性

- 🛡️ 基于中间件的路由鉴权
- 🔑 Token 生成、验证和管理
- 🎯 支持 `#[NeedAuth]` 和 `#[NoAuth]` 属性标记
- 📦 灵活的鉴权策略（allow/block）
- 🔄 支持 Refresh Token 机制

## 📋 安装

```bash
composer require hnraytek/flame-auth
```

## 📚 配置说明

```php
'auth' => [
    'enable' => false,          // 是否开启全局鉴权
    'strategy' => 'allow',      // allow: 只检查 NeedAuth | block: 只允许 NoAuth

    'token_name' => 'user',     // 请求头中的 token key，header[token_name]
    'key' => 'your-secret-key', // 加密密钥（请修改！）
    'algo' => 'ripemd160',      // 加密算法
    'table' => '',              // 数据表名（如自行更改请保证数据结构正确，默认留空）
    'expire' => 604800,         // 默认 Token 过期时间（秒）
],
```

## 🚀 快速开始

### 1. 基础用法

```php
use FlameModule\Attribute\NeedAuth;
use FlameModule\Auth\Attribute\NoAuth;

class UserController
{
    #[NoAuth]
    public function login()
    {
        // 无需鉴权的登录接口，strategy 为 block 生效 
    }

    #[NeedAuth]
    public function profile()
    {
        // 需要鉴权的用户信息接口，strategy 为 allow 才生效
    }
}
```

### 2. 使用 AuthApiController

```php
use FlameModule\Auth\Attribute\NoAuth;use FlameModule\Auth\Controller\AuthApiController;

class ApiController extends AuthApiController
{
    protected string $tokenType = 'user'; // （必填）token 类型，支持多种类型（如 `user`、`admin`、`member` 等）

    #[NoAuth]
    public function login()
    {
        // 验证用户...
        $this->setUserId($userId); // 设置用户ID并生成 token
        
        $this->success([
            'token' => $this->getToken(),
            'refresh_token' => $this->getRefreshToken(),
        ]);
    }
    
    public function userInfo()
    {
        $userId = $this->getUserId(); // 获取当前登录用户ID
        // ...
    }
    
    public function logout()
    {
        $this->clearToken(); // 清除 token
        $this->success(['message' => 'Logout success']);
    }
}
```

> tips：继承该类从而统一 `$tokenType` 的设置（如 `frontend` 、 `backend`）

### 3. Token 管理

```php
use FlameModule\Auth\Token;

$tokenService = app(Token::class);

// 生成 token
$token = Token::generateToken();

// 设置 token
$tokenService->set($token, 'user', $userId, 7 * 24 * 3600);

// 获取 token 数据
$data = $tokenService->get($token);

// 验证 token
$isValid = $tokenService->check($token, 'user', $userId);

// 删除 token
$tokenService->delete($token);

// 清理用户所有 token
$tokenService->clear($userId, 'user');
```

## 📝 API 说明

### AuthApiController 方法

| 方法                                     | 说明                                                                          |
|----------------------------------------|-----------------------------------------------------------------------------|
| `getUserId()`                          | 获取当前登录用户 ID                                                                 |
| `setUserId($userId, $keepTime, $keep)` | 设置用户 ID 并生成新 token，`$keepTime` 默认 1200 秒，`$keep` 为 true 时同时生成 refresh token |
| `getToken()`                           | 获取当前 token                                                                  |
| `getRefreshToken()`                    | 获取刷新 token                                                                  |
| `setRefreshToken($keepTime)`           | 生成刷新 token，返回 refresh token 字符串                                             |
| `clearToken($userId)`                  | 清除指定用户的 token（默认清除当前用户）                                                     |
| `checkAuth()`                          | 检查用户是否登录，未登录返回 401                                                          |
| `checkToken()`                         | 验证当前 token 是否有效                                                             |
| `isLoggedIn()`                         | 判断用户是否已登录                                                                   |

### Token 服务方法

| 方法                                     | 说明               |
|----------------------------------------|------------------|
| `generateToken()`                      | 生成随机 token（静态方法） |
| `getRequestToken()`                    | 从请求头获取 token     |
| `set($token, $type, $userId, $expire)` | 存储 token         |
| `get($token)`                          | 获取 token 数据      |
| `check($token, $type, $userId)`        | 验证 token         |
| `delete($token)`                       | 删除指定 token       |
| `clear($userId, $type)`                | 清理用户所有 token     |
| `tokenExpirationCheck($token)`         | 检查 token 是否过期    |

## 📝 鉴权策略

### Allow 模式（推荐）

- 默认允许所有路由访问
- 只有标记 `#[NeedAuth]` 的路由需要鉴权
- 适合大多数场景，不影响现有业务

### Block 模式

- 默认阻止所有路由访问
- 只有标记 `#[NoAuth]` 的路由可以访问
- 适合高安全性要求的场景

> **tips: 为了保持兼容性，推荐在所有路由增加 `#[NoAuth]` 和 `#[NeedAuth]`**

## 📝 异常处理

模块会抛出 `TokenExpirationException` 异常（HTTP 401），建议在全局异常处理器中统一处理：

```php
use FlameModule\Auth\Exception\TokenExpirationException;

if ($e instanceof TokenExpirationException) {
    return json([
        'code' => 401,
        'message' => 'Token has expired or is invalid',
    ], 401);
}
```

## 📝 注意事项

⚠️ **安全提示**：

- 务必修改配置中的 `key` 为随机密钥
- 生产环境建议开启鉴权（`enable => true`）
- 定期清理过期 token（模块会自动清理）

💡 **最佳实践**：

- 使用 `AuthApiController` 作为 API 控制器基类
- 登录接口使用 `#[NoAuth]` 标记
- 敏感操作接口使用 `#[NeedAuth]` 标记
- 合理设置 token 过期时间

## 📝 许可证

Apache-2.0 License

## 👤 作者

- **haoshuaioo** <bbmu@qq.com>

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！