<?php

namespace App\Annotation;

use Attribute;

/**
 * 实体属性 EF 元数据。
 *
 * 用法（PHP attribute）：
 *   #[Ef(group: 'company_base_info', isBF: true)]
 *
 * @param string $group 所属分组名
 * @param bool   $isBF  是否为业务字段
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Ef
{
    public function __construct(
        public ?string $group = null,
        public bool $isBF = false,
    ) {
    }
}
