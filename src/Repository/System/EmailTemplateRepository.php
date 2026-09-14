<?php

namespace App\Repository\System;

use App\Entity\System\EmailTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailTemplate>
 *
 * @method EmailTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method EmailTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method EmailTemplate[]    findAll()
 * @method EmailTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmailTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailTemplate::class);
    }

    /**
     * 按代码 + 语言查找模板；指定语言缺失时回退到回退语言，再回退到任意一条。
     *
     * Find a template by code + locale; falls back to the fallback locale,
     * then to any template with that code.
     */
    public function findByCodeLocalized(string $code, string $locale, string $fallbackLocale = 'zh_CN'): ?EmailTemplate
    {
        foreach ([$locale, $fallbackLocale] as $candidate) {
            $tpl = $this->findOneBy(['code' => $code, 'locale' => $candidate]);
            if ($tpl) {
                return $tpl;
            }
        }

        return $this->findOneBy(['code' => $code]);
    }
}
