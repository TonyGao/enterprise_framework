<?php

namespace App\Service\Entity;

use App\Service\BaseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

use function PHPUnit\Framework\throwException;

class EntityCRUDService extends BaseService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * 新增或更新 EntityPropertyGroup
     *
     * @param object $entity
     * @param array $data
     * @return object
     */
    public function save(object $entity, array $data): object
    {
        // 动态设置属性 / Dynamically set attributes
        $entity = $this->setEntityProperties($entity, $data);

        try {
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            throw $e;
        }

        // 返回保存后的实体对象 / Return the persisted entity
        return $entity;
    }

    /**
     * 删除指定的 EntityPropertyGroup
     *
     * @param object $entity
     */
    public function delete(object $entity)
    {
        if ($entity->getId() !== null) {
            try {
                $this->entityManager->remove($entity);
                $this->entityManager->flush();
            } catch (\Exception $e) {
                throw $e;
            }
        }
    }

    /**
     * 动态设置实体的属性
     *
     * @param object $entity
     * @param array $data
     * @return object
     */
    private function setEntityProperties(object $entity, array $data): object
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        foreach ($data as $property => $value) {
            if ($propertyAccessor->isWritable($entity, $property)) {
                $propertyAccessor->setValue($entity, $property, $value);
            }
        }

        return $entity;
    }
}
