<?php

namespace FlameModule\Auth;

use Flame\BaseService;
use FlameModule\Auth\Exception\InvalidTokenArgumentException;
use FlameModule\Auth\Exception\TokenExpirationException;
use think\db\exception\DbException;
use think\db\Query;
use think\facade\Cache;
use think\facade\Db;
use Throwable;

/**
 * Token 管理器
 */
class Token extends BaseService
{

    /**
     * 生成Token
     * @return string
     */
    static public function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * 创建用户Token
     *
     * @param string $type Type
     * @param int $userId 用户ID
     * @param int|null $expire 过期时间（秒），null 使用默认值，0 永不过期
     * @param bool $force 强制创建，如果为false则不会删除旧token
     * @return string Token
     * @throws InvalidTokenArgumentException
     */
    public function create(string $type, int $userId, ?int $expire = null, bool $force = true): string
    {
        if (!$type) throw new InvalidTokenArgumentException('invalid token type');
        if (!$expire) $expire = Auth::config()->get('expire', 7 * 24 * 3600);
        if ($force) $this->clear($userId, $type);
        $token = self::generateToken();
        $this->set($token, $type, $userId, $expire);
        return $token;
    }

    /**
     * 获取请求 token
     * @return string
     */
    public function getRequestToken(): string
    {
        return $this->getToken(Auth::config()->get('token_name', ''));
    }

    /**
     * 获取请求 token 数据
     * @return array|null
     */
    public function getRequestTokenData(): ?array
    {
        $token = $this->getRequestToken();
        if (!$token) return null;
        return $this->get($token);
    }

    /**
     * 获取当前请求的token
     * @param string $tokenName token名称
     * @return string
     */
    public function getToken(string $tokenName): string
    {
        if (empty($tokenName)) return '';
        return $this->app->request->header($tokenName, '');
    }

    /**
     * 获取当前请求的token数据
     * @param string $tokenName
     * @return array|null
     */
    public function getTokenData(string $tokenName): ?array
    {
        $token = $this->getToken($tokenName);
        if (!$token) return null;
        return $this->get($token);
    }

    /**
     * 设置 token
     * @param string $token Token
     * @param string $type Type
     * @param int $userId 用户ID
     * @param int|null $expire 过期时间（秒），null 使用默认值，0 永不过期
     * @return bool
     * @throws InvalidTokenArgumentException
     */
    public function set(string $token, string $type, int $userId, ?int $expire = null): bool
    {
        if (is_null($expire)) {
            $expire = Auth::config()->get('expire', 7 * 24 * 3600);
        }

        $expireTime = $expire !== 0 ? time() + $expire : 0;
        $encryptedToken = $this->getEncryptedToken($token);

        $db = $this->getDb();
        $db->insert([
            'token' => $encryptedToken,
            'type' => $type,
            'user_id' => $userId,
            'create_time' => time(),
            'expire_time' => $expireTime,
        ]);

        // 每隔48小时清理一次过期Token
        $time = time();
        $lastCacheCleanupTime = Cache::get('last_cache_cleanup_time');
        if (!$lastCacheCleanupTime || $lastCacheCleanupTime < $time - 172800) {
            Cache::set('last_cache_cleanup_time', $time);
            $this->gc();
        }

        return true;
    }

    /**
     * 获取 token 的数据
     * @param string $token Token
     * @return array|null
     */
    public function get(string $token): ?array
    {
        if (!$token) return null;
        $encryptedToken = $this->getEncryptedToken($token);
        $data = $this->getDb()->where('token', $encryptedToken)->find();

        if (!$data) {
            return null;
        }

        $data['token'] = $token;
        $data['expires_in'] = $this->getExpiredIn($data['expire_time'] ?? 0);

        return $data;
    }

    /**
     * 检查token是否有效
     * @param string $token Token
     * @param string $type Type
     * @param int $userId 用户ID
     * @return bool
     */
    public function check(string $token, string $type, int $userId): bool
    {
        $data = $this->get($token);

        if (!$data) {
            return false;
        }

        if ($data['expire_time'] && $data['expire_time'] <= time()) {
            return false;
        }

        return $data['type'] == $type && $data['user_id'] == $userId;
    }

    /**
     * 删除一个token
     * @param string $token
     * @return bool
     */
    public function delete(string $token): bool
    {
        if (!$token) return true;
        $encryptedToken = $this->getEncryptedToken($token);
        $this->getDb()->where('token', $encryptedToken)->delete();
        return true;
    }

    /**
     * 清理一个用户的所有token
     * @param int $userId 用户ID
     * @param string $type Type
     * @return bool
     */
    public function clear(int $userId, string $type): bool
    {
        try {
            $this->getDb()
                ->where('user_id', $userId)
                ->where('type', $type)
                ->delete();
        } catch (DbException) {
        }
        return true;
    }

    /**
     * Token过期检查
     * @param string|array|null $token token数据
     * @throws TokenExpirationException
     */
    public function tokenExpirationCheck(string|array|null $token): void
    {
        if (is_null($token) || (is_array($token) && empty($token))) {
            throw new TokenExpirationException('invalid token');
        }
        if (is_string($token)) {
            $token = $this->get($token);
            if (is_null($token)) {
                throw new TokenExpirationException('invalid token');
            }
        }
        if (isset($token['expire_time']) && $token['expire_time'] <= time()) {
            throw new TokenExpirationException('token expiration');
        }
    }

    /**
     * 获取数据库查询对象
     * @return Query
     */
    protected function getDb(): Query
    {
        $dbName = Auth::config()->get('name');
        if (empty($dbName)) $dbName = '';
        $table = Auth::config()->get('table');
        if (empty($table)) $table = 'flame_token';
        return Db::connect($dbName)
            ->name($table);
    }

    /**
     * 加密token
     * @param string $token
     * @return string
     */
    protected function getEncryptedToken(string $token): string
    {
        if (!$token) return '';
        $algo = Auth::config()->get('algo', 'ripemd160');
        if (empty($algo) || !in_array($algo, hash_algos()))
            throw new InvalidTokenArgumentException('Invalid token algo');
        $key = Auth::config()->get('key');
        if (empty($key)) throw new InvalidTokenArgumentException('Invalid token key');

        return hash_hmac($algo, $token, $key);
    }

    /**
     * 计算剩余有效时间
     * @param int $expireTime 过期时间戳
     * @return int
     */
    protected function getExpiredIn(int $expireTime): int
    {
        return $expireTime ? max(0, $expireTime - time()) : 365 * 86400;
    }

    /**
     * 清理过期Token
     */
    public function gc(): void
    {
        try {
            $this->getDb()
                ->where('expire_time', '<', time())
                ->where('expire_time', '>', 0)
                ->delete();
        } catch (Throwable) {
        }
    }
}