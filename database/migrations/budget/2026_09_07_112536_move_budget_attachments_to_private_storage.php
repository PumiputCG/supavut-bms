<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->moveFiles('public', 'local');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->moveFiles('local', 'public');
    }

    private function moveFiles(string $from, string $to): void
    {
        if (! Schema::hasTable('budget_invest_files')) {
            return;
        }

        DB::table('budget_invest_files')
            ->select(['id', 'path'])
            ->whereNotNull('path')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($from, $to) {
                foreach ($rows as $row) {
                    $this->moveFile((string) $row->path, $from, $to);
                }
            });
    }

    private function moveFile(string $storedPath, string $from, string $to): void
    {
        $path = ltrim(str_replace(chr(92), '/', $storedPath), '/');

        if (! str_starts_with($path, 'budget/')) {
            return;
        }

        if (Storage::disk($to)->exists($path)) {
            Storage::disk($from)->delete($path);

            return;
        }

        if (! Storage::disk($from)->exists($path)) {
            return;
        }

        $stream = Storage::disk($from)->readStream($path);

        if ($stream === false) {
            throw new RuntimeException('Unable to read budget attachment: '.$path);
        }

        try {
            $written = Storage::disk($to)->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $written || ! Storage::disk($from)->delete($path)) {
            Storage::disk($to)->delete($path);
            throw new RuntimeException('Unable to move budget attachment: '.$path);
        }
    }
};
