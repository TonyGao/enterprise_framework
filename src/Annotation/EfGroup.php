<?php

namespace App\Annotation;

use Attribute;

/**
 * EF 分组注解（当前未在运行时读取，保留为 attribute 形态以便后续使用）。
 *
 * @param string $value 分组名
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class EfGroup
{
    public function __construct(
        public string $value = '',
    ) {
    }
}
