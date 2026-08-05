<?php

namespace App\Console\Commands;

use App\Models\Banner;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Console\Command;

class FixStackedMediaVersions extends Command
{
    protected $signature = 'media:fix-versions {--dry-run : Show what would change without saving}';

    protected $description = 'Collapse repeated ?v= cache-busters in stored media paths';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fixed = 0;

        $fixed += $this->fixProducts($dryRun);
        $fixed += $this->fixSimpleColumn(Category::class, ['photo', 'photo_mobile'], $dryRun);
        $fixed += $this->fixSimpleColumn(Banner::class, ['photo', 'photo_mobile'], $dryRun);
        $fixed += $this->fixSimpleColumn(OrderItem::class, ['image'], $dryRun);

        $this->info(($dryRun ? 'Would fix ' : 'Fixed ') . $fixed . ' record(s).');

        return self::SUCCESS;
    }

    private function fixProducts(bool $dryRun): int
    {
        $count = 0;

        Product::whereNotNull('photo')->chunkById(100, function ($products) use (&$count, $dryRun) {
            foreach ($products as $product) {
                $images = json_decode($product->photo, true);
                if (!is_array($images)) {
                    continue;
                }

                $changed = false;
                foreach ($images as $i => $image) {
                    foreach (['url', 'url_mobile'] as $key) {
                        if (empty($image[$key])) {
                            continue;
                        }
                        $clean = $this->collapse($image[$key]);
                        if ($clean !== $image[$key]) {
                            $images[$i][$key] = $clean;
                            $changed = true;
                        }
                    }
                }

                if (!$changed) {
                    continue;
                }

                $count++;
                $this->line('product #' . $product->id);

                if (!$dryRun) {
                    $product->photo = json_encode($images);
                    $product->saveQuietly();
                }
            }
        });

        return $count;
    }

    private function fixSimpleColumn(string $modelClass, array $columns, bool $dryRun): int
    {
        $count = 0;

        $modelClass::query()->chunkById(200, function ($rows) use (&$count, $columns, $dryRun, $modelClass) {
            foreach ($rows as $row) {
                $changed = false;

                foreach ($columns as $column) {
                    $value = $row->getAttribute($column);
                    if (!is_string($value) || $value === '') {
                        continue;
                    }

                    // Banner photo columns can hold a JSON array of paths
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        $cleanList = array_map(fn ($p) => is_string($p) ? $this->collapse($p) : $p, $decoded);
                        if ($cleanList !== $decoded) {
                            $row->setAttribute($column, json_encode($cleanList));
                            $changed = true;
                        }
                        continue;
                    }

                    $clean = $this->collapse($value);
                    if ($clean !== $value) {
                        $row->setAttribute($column, $clean);
                        $changed = true;
                    }
                }

                if (!$changed) {
                    continue;
                }

                $count++;
                $this->line(class_basename($modelClass) . ' #' . $row->getKey());

                if (!$dryRun) {
                    $row->saveQuietly();
                }
            }
        });

        return $count;
    }

    /**
     * Keep the path plus at most the first ?v= value.
     */
    private function collapse(string $value): string
    {
        if (!str_contains($value, '?')) {
            return $value;
        }

        [$path, $query] = explode('?', $value, 2);
        $firstVersion = explode('?', $query, 2)[0];

        return $firstVersion === '' ? $path : $path . '?' . $firstVersion;
    }
}
