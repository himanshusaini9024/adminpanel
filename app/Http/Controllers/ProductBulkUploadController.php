<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductBulkUploadController extends Controller
{
    private const MAX_ROWS = 500;

    private const COLUMNS = [
        'title', 'sku', 'category', 'child_category', 'brand', 'summary', 'description',
        'color', 'sizes', 'stock', 'price', 'discount', 'special_price', 'condition',
        'status', 'is_featured', 'sort_order', 'images', 'image_alt',
    ];

    private const REQUIRED_COLUMNS = ['title', 'sku', 'category', 'summary', 'color', 'stock', 'price'];

    private const ALLOWED_SIZES = ['S', 'M', 'L', 'XL'];

    public function index()
    {
        $categories = Category::where('is_parent', 1)->with('child_cat')->orderBy('title')->get();
        $brands = Brand::orderBy('title')->get();

        return view('backend.product.bulk-upload', compact('categories', 'brands'));
    }

    public function template()
    {
        $sample = [
            'Blue Kantha Shirt', 'DF2026100', 'shirts', '', 'Dhirago',
            'Hand-embroidered cotton shirt', 'Soft cotton with kantha detailing.',
            'blue', 'S|M|L|XL', '10', '2499', '15', '', 'new',
            'inactive', '0', '', '/ecommerce/Product/blue-kantha/front.webp|/ecommerce/Product/blue-kantha/back.webp', 'Blue Kantha Shirt',
        ];

        return response()->streamDownload(function () use ($sample) {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::COLUMNS);
            fputcsv($out, $sample);
            fclose($out);
        }, 'product-bulk-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        [$header, $rows, $fileError] = $this->readCsv($request->file('file')->getRealPath());

        if ($fileError) {
            return back()->with('error', $fileError);
        }

        $categories = Category::all();
        $brands = Brand::all();
        $existingSkus = Product::whereNotNull('sku')->pluck('sku')->map(fn ($s) => Str::lower(trim($s)))->flip();

        $errors = [];
        $payloads = [];
        $seenSkus = [];

        foreach ($rows as $line => $row) {
            [$data, $rowErrors] = $this->buildRow($row, $categories, $brands);

            $skuKey = Str::lower($data['sku'] ?? '');
            if ($skuKey !== '') {
                if (isset($existingSkus[$skuKey])) {
                    $rowErrors[] = "SKU \"{$data['sku']}\" already exists.";
                } elseif (isset($seenSkus[$skuKey])) {
                    $rowErrors[] = "SKU \"{$data['sku']}\" is repeated on row {$seenSkus[$skuKey]}.";
                } else {
                    $seenSkus[$skuKey] = $line;
                }
            }

            if ($rowErrors) {
                $errors[$line] = ['title' => $row['title'] ?? '', 'messages' => $rowErrors];
            } else {
                $payloads[] = $data;
            }
        }

        if ($errors) {
            return back()
                ->with('error', count($errors) . ' row(s) have errors. Nothing was imported; fix the file and upload again.')
                ->with('bulk_errors', $errors);
        }

        $usedSlugs = [];
        DB::transaction(function () use (&$payloads, &$usedSlugs) {
            foreach ($payloads as $data) {
                $data['slug'] = $this->uniqueSlug($data['title'], $usedSlugs);
                Product::create($data);
            }
        });

        app(ProductController::class)->revalidateStorefrontProduct();

        return redirect()->route('product.index')
            ->with('success', count($payloads) . ' product(s) imported successfully.');
    }

    /**
     * @return array{0: array, 1: array<int, array>, 2: ?string}
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            return [[], [], 'Could not read the uploaded file.'];
        }

        $first = fgetcsv($handle);
        if (!$first) {
            fclose($handle);
            return [[], [], 'The file is empty.'];
        }

        $first[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $first[0]);
        $header = array_map(fn ($h) => Str::snake(trim(Str::lower((string) $h))), $first);

        $missing = array_diff(self::REQUIRED_COLUMNS, $header);
        if ($missing) {
            fclose($handle);
            return [[], [], 'Missing column(s): ' . implode(', ', $missing) . '. Download the template to see the expected header row.'];
        }

        $rows = [];
        $line = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($values, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $values = array_pad(array_slice($values, 0, count($header)), count($header), '');
            $rows[$line] = array_map(fn ($v) => trim((string) $v), array_combine($header, $values));

            if (count($rows) > self::MAX_ROWS) {
                fclose($handle);
                return [[], [], 'Too many rows. Upload at most ' . self::MAX_ROWS . ' products per file.'];
            }
        }
        fclose($handle);

        if (!$rows) {
            return [[], [], 'The file has a header row but no products.'];
        }

        return [$header, $rows, null];
    }

    /**
     * @return array{0: array, 1: string[]}
     */
    private function buildRow(array $row, $categories, $brands): array
    {
        $errors = [];

        $validator = Validator::make($row, [
            'title' => 'required|string|max:255',
            'sku' => 'required|string|max:100',
            'category' => 'required',
            'summary' => 'required|string',
            'color' => 'required|string|max:50',
            'stock' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0|max:100',
            'special_price' => 'nullable|numeric|min:0',
            'condition' => 'nullable|in:default,new,hot',
            'status' => 'nullable|in:active,inactive',
            'is_featured' => 'nullable|in:0,1,yes,no,true,false',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $errors = $validator->errors()->all();

        $category = $this->findCategory($categories, $row['category'] ?? '', null);
        if (($row['category'] ?? '') !== '' && (!$category || !$category->is_parent)) {
            $errors[] = "Category \"{$row['category']}\" not found.";
        }

        $childCategory = null;
        if (($row['child_category'] ?? '') !== '' && $category) {
            $childCategory = $this->findCategory($categories, $row['child_category'], $category->id);
            if (!$childCategory) {
                $errors[] = "Child category \"{$row['child_category']}\" not found under \"{$category->title}\".";
            }
        }

        $brand = null;
        if (($row['brand'] ?? '') !== '') {
            $brand = $brands->first(fn ($b) => (string) $b->id === $row['brand']
                || Str::lower($b->title) === Str::lower($row['brand'])
                || Str::lower((string) $b->slug) === Str::lower($row['brand']));
            if (!$brand) {
                $errors[] = "Brand \"{$row['brand']}\" not found.";
            }
        }

        $sizes = $this->splitList($row['sizes'] ?? '', true);
        $invalidSizes = array_diff($sizes, self::ALLOWED_SIZES);
        if ($invalidSizes) {
            $errors[] = 'Invalid size(s): ' . implode(', ', $invalidSizes) . '. Allowed: ' . implode(', ', self::ALLOWED_SIZES) . '.';
        }

        if ($errors) {
            return [['sku' => $row['sku'] ?? ''], $errors];
        }

        $pricing = Product::normalizePricing($row['price'], $row['discount'] ?? null, $row['special_price'] ?? null);

        return [[
            'title' => $row['title'],
            'sku' => $row['sku'],
            'cat_id' => $category->id,
            'child_cat_id' => $childCategory?->id,
            'brand_id' => $brand?->id,
            'summary' => $row['summary'],
            'description' => format_product_description_html($row['description'] ?? ''),
            'color' => $row['color'],
            'size' => implode(',', array_values(array_intersect(self::ALLOWED_SIZES, $sizes))),
            'stock' => (int) $row['stock'],
            'price' => $pricing['price'],
            'special_price' => $pricing['special_price'],
            'discount' => $pricing['discount'],
            'condition' => ($row['condition'] ?? '') ?: 'default',
            'status' => ($row['status'] ?? '') ?: 'inactive',
            'is_featured' => in_array(Str::lower($row['is_featured'] ?? ''), ['1', 'yes', 'true'], true) ? 1 : 0,
            'sort_order' => ($row['sort_order'] ?? '') !== '' ? (int) $row['sort_order'] : null,
            'photo' => $this->buildPhotos($row['images'] ?? '', ($row['image_alt'] ?? '') ?: $row['title']),
            'measurements' => json_encode(['type' => 'size_guide', 'dimensions' => []]),
        ], []];
    }

    private function findCategory($categories, string $value, ?int $parentId): ?Category
    {
        $value = Str::lower(trim($value));

        return $categories->first(function ($c) use ($value, $parentId) {
            if ($parentId !== null && (int) $c->parent_id !== $parentId) {
                return false;
            }

            return (string) $c->id === $value
                || Str::lower($c->title) === $value
                || Str::lower((string) $c->slug) === $value;
        });
    }

    private function splitList(string $value, bool $upper = false): array
    {
        $parts = preg_split('/[|,]/', $value) ?: [];
        $parts = array_map(fn ($p) => $upper ? Str::upper(trim($p)) : trim($p), $parts);

        return array_values(array_unique(array_filter($parts, fn ($p) => $p !== '')));
    }

    private function buildPhotos(string $images, string $alt): ?string
    {
        $urls = array_values(array_filter(array_map('trim', explode('|', $images))));
        if (!$urls) {
            return null;
        }

        $version = time();
        $photos = [];
        foreach ($urls as $i => $url) {
            $photos[] = [
                'url' => media_path_versioned($url, $version),
                'url_mobile' => null,
                'alt' => $alt,
                'type' => null,
                'sort_order' => (string) ($i + 1),
                'new_size' => false,
            ];
        }

        return json_encode($photos);
    }

    private function uniqueSlug(string $title, array &$used): string
    {
        $base = Str::slug($title) ?: 'product';
        $slug = $base;
        $n = 1;
        while (isset($used[$slug]) || Product::where('slug', $slug)->exists()) {
            $n++;
            $slug = $base . '-' . $n;
        }
        $used[$slug] = true;

        return $slug;
    }
}
