<?php

namespace App\Controller\Admin;

use App\Controller\Api\ApiResponse;
use App\Entity\System\SystemCalendarEvent;
use App\Repository\System\SystemCalendarEventRepository;
use App\Service\Calendar\HolidayImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/calendar')]
#[IsGranted('ROLE_ADMIN')]
class CalendarController extends AbstractController
{
    public function __construct(
        private readonly SystemCalendarEventRepository $repo,
        private readonly EntityManagerInterface $em,
        private readonly HolidayImportService $holidayImportService,
    ) {}

    /** 系统日历主页 */
    #[Route('', name: 'admin_calendar_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/calendar/index.html.twig');
    }

    /** API：获取指定年月的事件列表 */
    #[Route('/api/events', name: 'admin_calendar_api_events', methods: ['GET'])]
    public function apiEvents(Request $request): ApiResponse
    {
        $year  = max(2000, min(2100, $request->query->getInt('year',  (int)date('Y'))));
        $month = max(1,    min(12,   $request->query->getInt('month', (int)date('m'))));

        $events = $this->repo->findForMonth($year, $month);

        return ApiResponse::success(json_encode(array_map(fn($e) => $this->serializeEvent($e, $year), $events)));
    }

    /** API：创建事件 */
    #[Route('/api/event', name: 'admin_calendar_api_create', methods: ['POST'])]
    public function apiCreate(Request $request): ApiResponse
    {
        $data = json_decode($request->getContent(), true);

        $event = new SystemCalendarEvent();
        $this->fillEvent($event, $data);

        $this->em->persist($event);
        $this->em->flush();

        return ApiResponse::success(json_encode($this->serializeEvent($event)));
    }

    /** API：更新事件 */
    #[Route('/api/event/{id}', name: 'admin_calendar_api_update', methods: ['PUT'])]
    public function apiUpdate(Request $request, SystemCalendarEvent $id): ApiResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->fillEvent($id, $data);
        $this->em->flush();

        return ApiResponse::success(json_encode($this->serializeEvent($id)));
    }

    /** API：删除事件 */
    #[Route('/api/event/{id}', name: 'admin_calendar_api_delete', methods: ['DELETE'])]
    public function apiDelete(SystemCalendarEvent $id): ApiResponse
    {
        $this->em->remove($id);
        $this->em->flush();

        return ApiResponse::success(json_encode(['deleted' => true]));
    }

    /** API：导入法定假日 */
    #[Route('/api/import-holidays', name: 'admin_calendar_api_import_holidays', methods: ['POST'])]
    public function apiImportHolidays(Request $request): ApiResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $year = (int) ($data['year'] ?? date('Y'));
        $source = (string) ($data['source'] ?? 'timor');

        if ($year < 2000 || $year > 2100) {
            return ApiResponse::error(json_encode([]), 400, '年份范围需在 2000-2100 之间');
        }

        if (!in_array($source, ['timor', 'holiday_cn'], true)) {
            return ApiResponse::error(json_encode([]), 400, '不支持的数据源');
        }

        try {
            $result = $this->holidayImportService->import($year, $source);

            return ApiResponse::success(json_encode($result->toArray()), 200, '假日导入完成');
        } catch (\Throwable $e) {
            return ApiResponse::error(json_encode(['details' => $e->getMessage()]), 500, '假日导入失败：' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────

    private function fillEvent(SystemCalendarEvent $event, array $data): void
    {
        if (isset($data['title']))        $event->setTitle((string)$data['title']);
        if (isset($data['type']))         $event->setType((string)$data['type']);
        if (isset($data['recurring']))    $event->setRecurring((bool)$data['recurring']);
        if (isset($data['recurringRule'])) $event->setRecurringRule($data['recurringRule']);
        if (isset($data['note']))         $event->setNote((string)$data['note'] ?: null);
        if (isset($data['color']))        $event->setColor((string)$data['color'] ?: null);

        if (!empty($data['date'])) {
            $event->setDate(new \DateTimeImmutable($data['date']));
        }
    }

    private function serializeEvent(SystemCalendarEvent $e, ?int $resolveYear = null): array
    {
        // 对于每年重复的事件，将 date 中的年份替换为请求年份
        $date = $e->getDate();
        if ($e->isRecurring() && $resolveYear !== null && $date !== null) {
            $rule = $e->getRecurringRule();
            if (isset($rule['type']) && $rule['type'] === 'annual') {
                $date = \DateTimeImmutable::createFromFormat(
                    'Y-m-d',
                    sprintf('%04d-%02d-%02d', $resolveYear, $rule['month'] ?? $date->format('m'), $rule['day'] ?? $date->format('d'))
                ) ?: $date;
            }
        }

        return [
            'id'            => (string)$e->getId(),
            'title'         => $e->getTitle(),
            'type'          => $e->getType(),
            'date'          => $date?->format('Y-m-d'),
            'recurring'     => $e->isRecurring(),
            'recurringRule' => $e->getRecurringRule(),
            'note'          => $e->getNote(),
            'color'         => $e->getColor(),
        ];
    }
}
