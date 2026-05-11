<?php

namespace App\Service\Calendar;

use App\Entity\System\SystemCalendarEvent;
use App\Repository\System\SystemCalendarEventRepository;
use App\Service\Calendar\HolidayImportResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HolidayImportService
{
    private const TIMOR_URL = 'https://timor.tech/api/holiday/year/%d';

    private const HOLIDAY_CN_URLS = [
        'https://raw.githubusercontent.com/NateScarlet/holiday-cn/main/%d.json',
        'https://raw.githubusercontent.com/NateScarlet/holiday-cn/master/%d.json',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SystemCalendarEventRepository $repo,
        private readonly EntityManagerInterface $em,
    ) {}

    public function import(int $year, string $source): HolidayImportResult
    {
        return match ($source) {
            'timor' => $this->importFromTimor($year),
            'holiday_cn' => $this->importFromHolidayCn($year),
            default => throw new \InvalidArgumentException('不支持的数据源'),
        };
    }

    public function importFromTimor(int $year): HolidayImportResult
    {
        $payload = $this->fetchJson(sprintf(self::TIMOR_URL, $year));

        if (($payload['code'] ?? null) !== 0 || !isset($payload['holiday']) || !is_array($payload['holiday'])) {
            throw new \RuntimeException('TimOR API 返回格式异常');
        }

        $items = [];
        foreach ($payload['holiday'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = [
                'title' => (string) ($item['name'] ?? '法定安排'),
                'type' => !empty($item['holiday']) ? SystemCalendarEvent::TYPE_HOLIDAY : SystemCalendarEvent::TYPE_WORKDAY,
                'date' => (string) ($item['date'] ?? ''),
                'note' => $this->buildNote($item, 'TimOR API'),
            ];
        }

        return $this->persistItems($items);
    }

    public function importFromHolidayCn(int $year): HolidayImportResult
    {
        $payload = null;
        $lastError = null;

        foreach (self::HOLIDAY_CN_URLS as $urlPattern) {
            try {
                $payload = $this->fetchJson(sprintf($urlPattern, $year));
                break;
            } catch (\Throwable $e) {
                $lastError = $e;
            }
        }

        if (!is_array($payload) || !isset($payload['days']) || !is_array($payload['days'])) {
            throw new \RuntimeException($lastError?->getMessage() ?? 'Holiday CN 数据源不可用');
        }

        $items = [];
        foreach ($payload['days'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = [
                'title' => (string) ($item['name'] ?? '法定安排'),
                'type' => !empty($item['isOffDay']) ? SystemCalendarEvent::TYPE_HOLIDAY : SystemCalendarEvent::TYPE_WORKDAY,
                'date' => (string) ($item['date'] ?? ''),
                'note' => $this->buildNote($item, 'Holiday CN'),
            ];
        }

        return $this->persistItems($items);
    }

    /**
     * @param array<int, array{title:string,type:string,date:string,note:?string}> $items
     */
    private function persistItems(array $items): HolidayImportResult
    {
        $result = new HolidayImportResult();

        foreach ($items as $item) {
            if ($item['date'] === '') {
                $result->errors++;
                $result->addDetail('存在缺少日期的记录，已跳过');
                continue;
            }

            try {
                $date = new \DateTimeImmutable($item['date']);
            } catch (\Throwable) {
                $result->errors++;
                $result->addDetail(sprintf('日期 %s 无法解析，已跳过', $item['date']));
                continue;
            }

            if ($this->repo->findOneBy(['date' => $date, 'type' => $item['type']])) {
                $result->skipped++;
                continue;
            }

            $event = (new SystemCalendarEvent())
                ->setTitle($item['title'])
                ->setType($item['type'])
                ->setDate($date)
                ->setRecurring(false)
                ->setRecurringRule(null)
                ->setNote($item['note'])
                ->setColor(null);

            $this->em->persist($event);
            $result->imported++;
        }

        if ($result->imported > 0) {
            $this->em->flush();
        }

        return $result;
    }

    private function fetchJson(string $url): array
    {
        $response = $this->httpClient->request('GET', $url, [
            'headers' => ['Accept' => 'application/json'],
            'timeout' => 20,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            throw new \RuntimeException(sprintf('上游接口请求失败（HTTP %d）', $statusCode));
        }

        $content = $response->getContent(false);
        $payload = json_decode($content, true);

        if (!is_array($payload)) {
            throw new \RuntimeException('上游接口返回了无法解析的 JSON');
        }

        return $payload;
    }

    private function buildNote(array $item, string $source): string
    {
        $segments = ['来源：' . $source];

        if (isset($item['wage']) && is_numeric($item['wage']) && (int) $item['wage'] > 1) {
            $segments[] = '工资倍数：' . (int) $item['wage'];
        }

        return implode('；', $segments);
    }
}