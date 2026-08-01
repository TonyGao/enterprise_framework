<?php

namespace App\EventListener\Entity;

use App\Service\Platform\MercureService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * 实体变更同步监听器
 * 当实体发生变化时，自动通过 Mercure 推送同步消息
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class EntitySyncListener
{
    private MercureService $mercureService;

    public function __construct(MercureService $mercureService)
    {
        $this->mercureService = $mercureService;
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->handleSync($args, 'create');
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->handleSync($args, 'update');
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->handleSync($args, 'delete');
    }

    private function handleSync(LifecycleEventArgs $args, string $action): void
    {
        try {
            $entity = $args->getObject();
            $className = (new \ReflectionClass($entity))->getShortName();
            
            // 获取实体 ID
            $id = null;
            if (method_exists($entity, 'getId')) {
                $id = $entity->getId();
            }

            if ($id) {
                $this->mercureService->publishEntitySync($className, (string)$id, $action);
            }
        } catch (\Throwable) {
            // Mercure 不可用时静默失败
        }
    }
}
