<?php

namespace App\Console\Commands;

use App\Services\LegacyAttachmentPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use JsonException;

class ManageLegacyAttachments extends Command
{
    protected $signature = 'legacy:attachments
        {--export : Output the database attachment inventory as JSON lines}
        {--manifest= : Verify and link files from a JSON-lines checksum manifest}
        {--dry-run : Verify without updating attachment records}';

    protected $description = 'Export, verify, and link privately stored legacy procurement attachments';

    public function handle(): int
    {
        if ($this->option('export')) {
            if ($this->option('manifest') !== null || $this->option('dry-run')) {
                $this->error('导出不能与清单验证选项同时使用。');

                return self::FAILURE;
            }
            foreach (DB::table('legacy_procurement_attachments')->select(['id', 'legacy_path'])->orderBy('id')->cursor() as $file) {
                $this->line(json_encode(['id' => $file->id, 'legacy_path' => $file->legacy_path], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            }

            return self::SUCCESS;
        }

        $manifest = $this->option('manifest');
        if (! is_string($manifest) || ! is_file($manifest) || ! is_readable($manifest)) {
            $this->error('请提供可读取的校验清单。');

            return self::FAILURE;
        }

        $handle = fopen($manifest, 'rb');
        if ($handle === false) {
            $this->error('无法打开校验清单。');

            return self::FAILURE;
        }

        $seen = [];
        $ready = [];
        $errors = [];
        $lineNumber = 0;
        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            try {
                $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($entry) || ! is_int($entry['id'] ?? null)
                    || ! is_string($entry['legacy_path'] ?? null)
                    || ! is_int($entry['size'] ?? null) || $entry['size'] < 0
                    || ! is_string($entry['sha256'] ?? null)
                    || ! preg_match('/\A[a-f0-9]{64}\z/D', $entry['sha256'])) {
                    throw new InvalidArgumentException('清单字段无效');
                }
                $id = $entry['id'];
                if (isset($seen[$id])) {
                    throw new InvalidArgumentException('附件编号重复');
                }
                $seen[$id] = true;
                $record = DB::table('legacy_procurement_attachments')->where('id', $id)->first(['legacy_path', 'stored_path', 'migration_status']);
                if ($record === null || $record->legacy_path !== $entry['legacy_path']) {
                    throw new InvalidArgumentException('附件记录或原始路径不匹配');
                }
                $path = LegacyAttachmentPath::storedPath($entry['legacy_path']);
                $disk = Storage::disk('local');
                if (! $disk->exists($path) || is_link($disk->path($path)) || $disk->size($path) !== $entry['size']
                    || hash_file('sha256', $disk->path($path)) !== $entry['sha256']) {
                    throw new InvalidArgumentException('文件不存在、大小或校验值不符');
                }
                $ready[$id] = $path;
            } catch (JsonException|InvalidArgumentException $exception) {
                $errors[] = "第 {$lineNumber} 行：{$exception->getMessage()}";
            }
        }
        fclose($handle);

        $total = DB::table('legacy_procurement_attachments')->count();
        if (count($seen) !== $total) {
            $errors[] = '清单数量与数据库附件记录不一致。';
        }
        if ($errors !== []) {
            foreach (array_slice($errors, 0, 20) as $error) {
                $this->error($error);
            }
            $this->error('校验失败：'.count($errors).' 项；未修改附件记录。');

            return self::FAILURE;
        }

        if (! $this->option('dry-run')) {
            DB::transaction(function () use ($ready): void {
                foreach ($ready as $id => $path) {
                    DB::table('legacy_procurement_attachments')->where('id', $id)
                        ->where(function ($query) use ($path) {
                            $query->where('migration_status', '!=', 'available')->orWhere('stored_path', '!=', $path)->orWhereNull('stored_path');
                        })
                        ->update(['stored_path' => $path, 'migration_status' => 'available', 'updated_at' => now()]);
                }
            });
        }
        $this->info(($this->option('dry-run') ? '预览通过：' : '关联完成：').count($ready).' 个附件。');

        return self::SUCCESS;
    }
}
