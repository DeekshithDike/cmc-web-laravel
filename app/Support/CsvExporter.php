<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvExporter
{
    /**
     * @param  iterable<int, array<int|string, scalar|null>>  $rows
     * @param  array<int, string>  $headers
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_values($row));
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
