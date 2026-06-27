<?php

namespace FlameModule\Auth\Attribute;

use Attribute;

/**
 * 权限验证标记
 *
 * 当 flame.auth.strategy 为 'allow' 加该属性时，表示该方法需要权限验证
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class NeedAuth
{

}