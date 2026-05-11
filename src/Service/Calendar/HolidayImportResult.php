<?php

namespace App\Service\Calendar;

class HolidayImportResult
{
    public int $imported = 0;
    public int $skipped = 0;
    public int $errors = 0;

    /** @var string[] */
    public array $details = [];

    public function addDetail(string $detail): void
    {
        $this->details[] = $detail;
    }

    public function toArray(): array
    {
        return [
            'imported' => $this->imported,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'details' => $this->details,
        ];
    }
}