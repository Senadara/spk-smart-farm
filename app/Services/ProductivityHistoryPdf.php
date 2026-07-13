<?php

namespace App\Services;

class ProductivityHistoryPdf
{
    private float $width = 841.89;

    private float $height = 595.28;

    private float $margin = 32;

    private array $pages = [];

    private int $currentPage = -1;

    private float $cursorY = 0;

    private string $title = 'Laporan Histori Produktivitas Kandang';

    private string $subtitle = '';

    public function make(array $report): string
    {
        $this->pages = [];
        $this->currentPage = -1;
        $this->subtitle = trim(($report['barn']['name'] ?? 'Kandang')." | Dibuat: ".($report['generated_at']?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i')));

        $this->addPage();
        $this->drawBarnInfo($report);
        $this->drawSummary($report);
        $this->drawWarnings($report['warnings'] ?? []);
        $this->drawHistoryTable($report['rows'] ?? []);

        return $this->output();
    }

    private function addPage(): void
    {
        $this->pages[] = '';
        $this->currentPage = count($this->pages) - 1;

        $this->rect(0, 0, $this->width, 56, null, [0.94, 0.99, 0.96]);
        $this->text($this->margin, 22, $this->title, 16, 'F2', [0.05, 0.25, 0.16]);
        $this->text($this->margin, 42, $this->subtitle, 8.5, 'F1', [0.28, 0.36, 0.32]);
        $this->text($this->width - $this->margin - 92, 24, 'SmartFarm SPK', 9.5, 'F2', [0.05, 0.33, 0.21]);
        $this->line($this->margin, 58, $this->width - $this->margin, 58, 0.82);
        $this->text($this->margin, $this->height - 18, 'File ini dibuat otomatis dari histori laporan kandang.', 7.5, 'F1', [0.45, 0.45, 0.45]);
        $this->text($this->width - $this->margin - 45, $this->height - 18, 'Hal. '.count($this->pages), 7.5, 'F1', [0.45, 0.45, 0.45]);

        $this->cursorY = 78;
    }

    private function drawBarnInfo(array $report): void
    {
        $barn = $report['barn'] ?? [];
        $left = $this->margin;
        $top = $this->cursorY;
        $width = $this->width - ($this->margin * 2);

        $this->rect($left, $top, $width, 58, [0.84, 0.88, 0.86], [1, 1, 1]);
        $this->text($left + 12, $top + 17, (string) ($barn['name'] ?? '-'), 12, 'F2', [0.06, 0.10, 0.09]);
        $this->text($left + 12, $top + 35, 'Lokasi: '.($barn['location'] ?? '-').' | Breed: '.($barn['breed'] ?? '-'), 8.5, 'F1', [0.28, 0.34, 0.32]);
        $this->text($left + 12, $top + 49, 'Tanggal masuk: '.($barn['start_date'] ?? '-').' | Umur flock: '.($barn['flock_age'] ?? '-'), 8.5, 'F1', [0.28, 0.34, 0.32]);

        $this->text($left + 520, $top + 22, 'Populasi saat ini', 8, 'F1', [0.40, 0.45, 0.43]);
        $this->text($left + 520, $top + 41, $this->formatNumber($barn['current_population'] ?? 0).' / '.$this->formatNumber($barn['capacity'] ?? 0).' ekor', 13, 'F2', [0.05, 0.33, 0.21]);

        $this->cursorY += 72;
    }

    private function drawSummary(array $report): void
    {
        $summary = $report['summary'] ?? [];
        $metrics = [
            ['Periode', ($summary['period_start'] ?? '-').' - '.($summary['period_end'] ?? '-'), 'Rentang data produktivitas'],
            ['Hari data', $this->formatNumber($summary['report_days'] ?? 0), 'Tanggal dengan panen/pakan/mortalitas'],
            ['Total telur', $this->formatNumber($summary['total_eggs'] ?? 0), 'Butir'],
            ['Total pakan', $this->formatDecimal($summary['total_feed_kg'] ?? 0).' kg', 'Konsumsi tercatat'],
            ['Rata-rata HDP', $this->formatDecimal($summary['avg_hdp'] ?? 0).'%', 'Produktivitas harian'],
            ['Rata-rata FCR', $this->formatDecimal($summary['avg_fcr'] ?? 0), 'Pakan / egg mass'],
            ['Feed intake', $this->formatDecimal($summary['avg_feed_intake'] ?? 0).' g', 'Rata-rata per ekor'],
            ['Mortalitas', $this->formatNumber($summary['total_mortality'] ?? 0), 'Ekor'],
        ];

        $gap = 8;
        $boxWidth = (($this->width - ($this->margin * 2)) - ($gap * 3)) / 4;
        $boxHeight = 44;
        $x = $this->margin;
        $y = $this->cursorY;

        foreach ($metrics as $index => $metric) {
            if ($index === 4) {
                $x = $this->margin;
                $y += $boxHeight + $gap;
            }

            $this->rect($x, $y, $boxWidth, $boxHeight, [0.82, 0.87, 0.84], [0.98, 1, 0.99]);
            $this->text($x + 10, $y + 14, $metric[0], 7.5, 'F1', [0.42, 0.48, 0.45]);
            $this->text($x + 10, $y + 29, $metric[1], 11, 'F2', [0.06, 0.12, 0.10]);
            $this->text($x + 10, $y + 39, $metric[2], 6.8, 'F1', [0.50, 0.55, 0.52]);
            $x += $boxWidth + $gap;
        }

        $this->cursorY = $y + $boxHeight + 18;
    }

    private function drawWarnings(array $warnings): void
    {
        if (empty($warnings)) {
            return;
        }

        $lines = [];
        foreach ($warnings as $warning) {
            foreach ($this->wrapText('- '.$warning, $this->width - ($this->margin * 2) - 24, 8.2, 2) as $line) {
                $lines[] = $line;
            }
        }

        $height = 24 + (count($lines) * 11);
        $this->rect($this->margin, $this->cursorY, $this->width - ($this->margin * 2), $height, [0.95, 0.75, 0.35], [1, 0.98, 0.90]);
        $this->text($this->margin + 12, $this->cursorY + 15, 'Catatan data', 9, 'F2', [0.55, 0.32, 0.02]);

        $y = $this->cursorY + 30;
        foreach ($lines as $line) {
            $this->text($this->margin + 12, $y, $line, 8.2, 'F1', [0.42, 0.28, 0.06]);
            $y += 11;
        }

        $this->cursorY += $height + 18;
    }

    private function drawHistoryTable(array $rows): void
    {
        $this->text($this->margin, $this->cursorY, 'Histori produktivitas', 11, 'F2', [0.05, 0.14, 0.10]);
        $this->cursorY += 12;

        if (empty($rows)) {
            $this->rect($this->margin, $this->cursorY + 4, $this->width - ($this->margin * 2), 42, [0.86, 0.88, 0.90], [1, 1, 1]);
            $this->text($this->margin + 12, $this->cursorY + 30, 'Belum ada data produktivitas historis yang bisa diexport.', 9, 'F1', [0.30, 0.34, 0.36]);

            return;
        }

        $columns = [
            ['key' => 'date_label', 'label' => 'Tanggal', 'width' => 62],
            ['key' => 'eggs', 'label' => 'Telur', 'width' => 58],
            ['key' => 'egg_mass_kg', 'label' => 'Egg kg', 'width' => 58],
            ['key' => 'feed_kg', 'label' => 'Pakan kg', 'width' => 58],
            ['key' => 'feed_intake', 'label' => 'FI g/ekor', 'width' => 66],
            ['key' => 'mortality', 'label' => 'Mati', 'width' => 44],
            ['key' => 'hdp', 'label' => 'HDP %', 'width' => 46],
            ['key' => 'hhep', 'label' => 'HHEP %', 'width' => 46],
            ['key' => 'fcr', 'label' => 'FCR', 'width' => 42],
            ['key' => 'note', 'label' => 'Catatan', 'width' => 296],
        ];

        $this->drawTableHeader($columns);

        foreach ($rows as $row) {
            $noteLines = $this->wrapText((string) ($row['note'] ?? ''), 286, 7.3, 2);
            $rowHeight = max(22, 11 + (count($noteLines) * 9));

            if ($this->cursorY + $rowHeight > $this->height - 38) {
                $this->addPage();
                $this->drawTableHeader($columns);
            }

            $this->drawTableRow($columns, $row, $noteLines, $rowHeight);
        }
    }

    private function drawTableHeader(array $columns): void
    {
        $this->cursorY += 8;
        $x = $this->margin;
        $height = 20;

        $this->rect($x, $this->cursorY, $this->width - ($this->margin * 2), $height, [0.72, 0.78, 0.75], [0.93, 0.97, 0.95]);
        foreach ($columns as $column) {
            $this->text($x + 5, $this->cursorY + 13, $column['label'], 7.2, 'F2', [0.14, 0.22, 0.18]);
            $x += $column['width'];
        }

        $this->cursorY += $height;
    }

    private function drawTableRow(array $columns, array $row, array $noteLines, float $rowHeight): void
    {
        $x = $this->margin;
        $top = $this->cursorY;

        $this->line($this->margin, $top + $rowHeight, $this->width - $this->margin, $top + $rowHeight, 0.88);

        foreach ($columns as $column) {
            $key = $column['key'];
            $value = $row[$key] ?? '-';

            if ($key === 'note') {
                $y = $top + 12;
                foreach ($noteLines as $line) {
                    $this->text($x + 5, $y, $line ?: '-', 7.2, 'F1', [0.32, 0.36, 0.34]);
                    $y += 9;
                }
            } else {
                $value = $this->formatCellValue($key, $value);
                $this->text($x + 5, $top + 14, $value, 7.4, $key === 'date_label' ? 'F2' : 'F1', [0.18, 0.22, 0.20]);
            }

            $x += $column['width'];
        }

        $this->cursorY += $rowHeight;
    }

    private function formatCellValue(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return match ($key) {
            'eggs', 'mortality' => $this->formatNumber($value),
            'egg_mass_kg', 'feed_kg', 'feed_intake', 'hdp', 'hhep', 'fcr' => $this->formatDecimal($value),
            default => (string) $value,
        };
    }

    private function text(float $x, float $top, string $text, float $size = 10, string $font = 'F1', array $rgb = [0, 0, 0]): void
    {
        $escaped = $this->escape($text);
        [$r, $g, $b] = $rgb;

        $this->pages[$this->currentPage] .= sprintf(
            "q %.3F %.3F %.3F rg BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET Q\n",
            $r,
            $g,
            $b,
            $font,
            $size,
            $x,
            $this->pdfY($top),
            $escaped
        );
    }

    private function line(float $x1, float $top1, float $x2, float $top2, float $gray = 0.8, float $width = 0.5): void
    {
        $this->pages[$this->currentPage] .= sprintf(
            "q %.3F G %.2F w %.2F %.2F m %.2F %.2F l S Q\n",
            $gray,
            $width,
            $x1,
            $this->pdfY($top1),
            $x2,
            $this->pdfY($top2)
        );
    }

    private function rect(float $x, float $top, float $width, float $height, ?array $stroke = null, ?array $fill = null): void
    {
        $y = $this->height - $top - $height;

        if ($fill) {
            [$r, $g, $b] = $fill;
            $this->pages[$this->currentPage] .= sprintf(
                "q %.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f Q\n",
                $r,
                $g,
                $b,
                $x,
                $y,
                $width,
                $height
            );
        }

        if ($stroke) {
            [$r, $g, $b] = $stroke;
            $this->pages[$this->currentPage] .= sprintf(
                "q %.3F %.3F %.3F RG 0.5 w %.2F %.2F %.2F %.2F re S Q\n",
                $r,
                $g,
                $b,
                $x,
                $y,
                $width,
                $height
            );
        }
    }

    private function wrapText(string $text, float $width, float $fontSize, int $maxLines): array
    {
        $text = $this->clean($text);
        if ($text === '') {
            return ['-'];
        }

        $maxChars = max(8, (int) floor($width / ($fontSize * 0.48)));
        $words = explode(' ', $text);
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = trim($line.' '.$word);
            if (strlen($candidate) <= $maxChars) {
                $line = $candidate;
                continue;
            }

            if ($line !== '') {
                $lines[] = $line;
            }
            $line = strlen($word) > $maxChars ? substr($word, 0, $maxChars) : $word;

            if (count($lines) >= $maxLines) {
                break;
            }
        }

        if ($line !== '' && count($lines) < $maxLines) {
            $lines[] = $line;
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
        }

        if (count($lines) === $maxLines && strlen(implode(' ', $words)) > strlen(implode(' ', $lines))) {
            $last = $lines[$maxLines - 1];
            $lines[$maxLines - 1] = rtrim(substr($last, 0, max(0, $maxChars - 3))).'...';
        }

        return $lines;
    }

    private function output(): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $pageRefs = [];
        $nextObject = 5;
        foreach ($this->pages as $content) {
            $pageId = $nextObject++;
            $contentId = $nextObject++;
            $pageRefs[] = $pageId.' 0 R';
            $objects[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                $this->width,
                $this->height,
                $contentId
            );
            $objects[$contentId] = "<< /Length ".strlen($content)." >>\nstream\n".$content."endstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageRefs).'] /Count '.count($pageRefs).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        $maxObject = max(array_keys($objects));

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".($maxObject + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObject; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size ".($maxObject + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    private function pdfY(float $top): float
    {
        return $this->height - $top;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->clean($text));
    }

    private function clean(string $text): string
    {
        $text = strip_tags($text);
        $text = str_replace(['&mdash;', '&ndash;'], '-', $text);
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }

        return trim((string) preg_replace('/\s+/', ' ', preg_replace('/[^\x20-\x7E]/', '', $text)));
    }

    private function formatNumber(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    private function formatDecimal(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }
}
