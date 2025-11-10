<?php

namespace App\Services;

use App\Models\TaskList;
use Carbon\Carbon;

class CaseReportService
{
    /**
     * Generate report from selected cases
     *
     * @param array $caseIds Array of TaskList IDs
     * @param Carbon|null $fromDate Start date filter (optional)
     * @param Carbon|null $toDate End date filter (optional)
     * @return string Report content
     */
    public function generateReport(array $caseIds, ?Carbon $fromDate = null, ?Carbon $toDate = null): string
    {
        $query = TaskList::whereIn('id', $caseIds)
            ->where('status', 1)
            ->with('user')
            ->orderBy('created_at', 'desc');

        // Apply date filters if provided
        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate->startOfDay());
        }
        if ($toDate) {
            $query->where('created_at', '<=', $toDate->endOfDay());
        }
        $cases = $query->get();

        if ($cases->isEmpty()) {
            return 'Không có dữ liệu để tạo báo cáo.';
        }
        $report = $this->formatReport($cases, $fromDate, $toDate);
        return $report;
    }

    /**
     * Format cases into report text
     *
     * @param \Illuminate\Database\Eloquent\Collection $cases
     * @param Carbon|null $fromDate
     * @param Carbon|null $toDate
     * @return string
     */
    protected function formatReport($cases, ?Carbon $fromDate = null, ?Carbon $toDate = null): string
    {
        $lines = [];

        // Header
        $lines[] = 'BÁO CÁO TỔNG HỢP CASE';
        $lines[] = '=' . str_repeat('=', 50);
        $lines[] = '';

        // Date range if provided
        if ($fromDate || $toDate) {
            $lines[] = 'Thời gian báo cáo:';
            if ($fromDate && $toDate) {
                $lines[] = "Từ: {$fromDate->format('d/m/Y')} đến {$toDate->format('d/m/Y')}";
            } elseif ($fromDate) {
                $lines[] = "Từ: {$fromDate->format('d/m/Y')}";
            } elseif ($toDate) {
                $lines[] = "Đến: {$toDate->format('d/m/Y')}";
            }
            $lines[] = '';
        }

        $lines[] = "Tổng số case: {$cases->count()}";
        $lines[] = '';

        // Case details
        foreach ($cases as $index => $case) {
            $lines[] = str_repeat('-', 60);
            $lines[] = "Case #" . ($index + 1) . ": {$case->name}";
            $lines[] = "Người tạo: " . ($case->user->name ?? 'N/A');
            $lines[] = "Ngày tạo: " . $case->created_at->format('d/m/Y H:i');
            $lines[] = '';

            if ($case->sumary) {
                $lines[] = 'Tóm tắt:';
                $lines[] = $case->sumary;
            } else {
                $lines[] = 'Tóm tắt: (Chưa có tóm tắt)';
            }

            $lines[] = '';
        }

        $lines[] = str_repeat('=', 60);
        $lines[] = 'Hết báo cáo';
        $lines[] = 'Ngày xuất: ' . Carbon::now()->format('d/m/Y H:i:s');

        return implode("\n", $lines);
    }

    /**
     * Generate Word document content (HTML format for Word)
     *
     * @param array $caseIds
     * @param Carbon|null $fromDate
     * @param Carbon|null $toDate
     * @return string HTML content for Word
     */
    public function generateWordContent(array $caseIds, ?Carbon $fromDate = null, ?Carbon $toDate = null): string
    {
        $query = TaskList::whereIn('id', $caseIds)
            ->where('status', 1)
            ->with('user')
            ->orderBy('created_at', 'desc');

        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate->startOfDay());
        }
        if ($toDate) {
            $query->where('created_at', '<=', $toDate->endOfDay());
        }

        $cases = $query->get();

        if ($cases->isEmpty()) {
            return '<p>Không có dữ liệu để tạo báo cáo.</p>';
        }

        $html = '<html><head><meta charset="UTF-8"></head><body>';
        $html .= '<h1 style="text-align: center;">BÁO CÁO TỔNG HỢP CASE</h1>';

        if ($fromDate || $toDate) {
            $html .= '<p><strong>Thời gian báo cáo:</strong> ';
            if ($fromDate && $toDate) {
                $html .= "Từ: {$fromDate->format('d/m/Y')} đến {$toDate->format('d/m/Y')}";
            } elseif ($fromDate) {
                $html .= "Từ: {$fromDate->format('d/m/Y')}";
            } elseif ($toDate) {
                $html .= "Đến: {$toDate->format('d/m/Y')}";
            }
            $html .= '</p>';
        }

        $html .= '<p><strong>Tổng số case:</strong> ' . $cases->count() . '</p>';
        $html .= '<hr>';

        foreach ($cases as $index => $case) {
            $html .= '<h2>Case #' . ($index + 1) . ': ' . htmlspecialchars($case->name) . '</h2>';
            $html .= '<p><strong>Người tạo:</strong> ' . htmlspecialchars($case->user->name ?? 'N/A') . '</p>';
            $html .= '<p><strong>Ngày tạo:</strong> ' . $case->created_at->format('d/m/Y H:i') . '</p>';

            if ($case->sumary) {
                $html .= '<p><strong>Tóm tắt:</strong></p>';
                $html .= '<p style="text-align: justify; white-space: pre-wrap;">' . nl2br(htmlspecialchars($case->sumary)) . '</p>';
            } else {
                $html .= '<p><strong>Tóm tắt:</strong> (Chưa có tóm tắt)</p>';
            }

            $html .= '<hr>';
        }

        $html .= '<p style="text-align: right; font-style: italic;">Ngày xuất: ' . Carbon::now()->format('d/m/Y H:i:s') . '</p>';
        $html .= '</body></html>';

        return $html;
    }
}

