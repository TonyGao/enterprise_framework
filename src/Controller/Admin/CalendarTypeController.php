<?php

namespace App\Controller\Admin;

use App\Entity\System\SystemCalendarEventType;
use App\Repository\System\SystemCalendarEventTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/calendar')]
#[IsGranted('ROLE_ADMIN')]
class CalendarTypeController extends AbstractController
{
    public function __construct(
        private readonly SystemCalendarEventTypeRepository $repo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/api/types', name: 'admin_calendar_api_types', methods: ['GET'])]
    public function listTypes(): JsonResponse
    {
        return $this->json(['data' => $this->serializeTypes($this->repo->findAllOrdered())]);
    }

    #[Route('/api/type', name: 'admin_calendar_api_type_create', methods: ['POST'])]
    public function createType(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $key = strtolower(trim((string) ($data['key'] ?? '')));
        $label = trim((string) ($data['label'] ?? ''));

        if (!$this->isValidKey($key) || $label === '') {
            return $this->json(['message' => 'key 格式无效，且 label 为必填'], 400);
        }

        if ($this->repo->findOneBy(['code' => $key])) {
            return $this->json(['message' => 'key 已存在'], 400);
        }

        $type = (new SystemCalendarEventType())
            ->setCode($key)
            ->setLabel($label)
            ->setColor($this->normalizeColor($data['color'] ?? null))
            ->setIcon($this->normalizeIcon($data['icon'] ?? null))
            ->setIsSystem(false)
            ->setSortOrder(isset($data['sort']) ? (int) $data['sort'] : $this->repo->findNextSortOrder());

        $this->em->persist($type);
        $this->em->flush();

        return $this->json(['data' => $this->serializeTypes($this->repo->findAllOrdered())]);
    }

    #[Route('/api/type/{key}', name: 'admin_calendar_api_type_update', methods: ['PUT'])]
    public function updateType(Request $request, string $key): JsonResponse
    {
        $type = $this->repo->findOneBy(['code' => $key]);
        if (!$type instanceof SystemCalendarEventType) {
            return $this->json(['message' => '未找到类型'], 404);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        if (isset($data['label']) && trim((string) $data['label']) !== '') {
            $type->setLabel(trim((string) $data['label']));
        }
        if (array_key_exists('color', $data)) {
            $type->setColor($this->normalizeColor($data['color']));
        }
        if (array_key_exists('icon', $data)) {
            $type->setIcon($this->normalizeIcon($data['icon']));
        }
        if (isset($data['sort'])) {
            $type->setSortOrder((int) $data['sort']);
        }

        $this->em->flush();

        return $this->json(['data' => $this->serializeTypes($this->repo->findAllOrdered())]);
    }

    #[Route('/api/type/{key}', name: 'admin_calendar_api_type_delete', methods: ['DELETE'])]
    public function deleteType(string $key): JsonResponse
    {
        $type = $this->repo->findOneBy(['code' => $key]);
        if (!$type instanceof SystemCalendarEventType) {
            return $this->json(['message' => '未找到类型'], 404);
        }
        if ($type->isSystem()) {
            return $this->json(['message' => '系统预设类型不可删除'], 400);
        }

        $this->em->remove($type);
        $this->em->flush();

        return $this->json(['data' => $this->serializeTypes($this->repo->findAllOrdered())]);
    }

    /**
     * @param SystemCalendarEventType[] $types
     */
    private function serializeTypes(array $types): array
    {
        return array_map(static fn(SystemCalendarEventType $type) => [
            'key' => $type->getCode(),
            'label' => $type->getLabel(),
            'color' => $type->getColor(),
            'icon' => $type->getIcon(),
            'sort' => $type->getSortOrder(),
            'isSystem' => $type->isSystem(),
        ], $types);
    }

    private function isValidKey(string $key): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]{0,31}$/', $key);
    }

    private function normalizeColor(mixed $color): string
    {
        $value = trim((string) $color);

        return $value !== '' ? $value : '#6b7280';
    }

    private function normalizeIcon(mixed $icon): string
    {
        $value = trim((string) $icon);

        return $value !== '' ? $value : 'fa-solid fa-tag';
    }
}
