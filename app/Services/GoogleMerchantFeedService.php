<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GoogleMerchantFeedService
{
    /**
     * Active products Google can advertise, newest / catalog order first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function items(): Collection
    {
        $brands = Brand::pluck('title', 'id');
        $defaultBrand = (string) config('services.merchant.brand', 'Dhirago');

        return Product::query()
            ->where('status', 'active')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->orderByRaw('sort_order IS NULL')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Product $product) => $this->mapProduct($product, $brands, $defaultBrand))
            ->filter()
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $brands
     * @return array<string, mixed>|null
     */
    public function mapProduct(Product $product, $brands, string $defaultBrand): ?array
    {
        $images = $this->imageUrls($product->photo);
        if (empty($images)) {
            return null;
        }

        $pricing = Product::normalizePricing($product->price, $product->discount, $product->special_price);
        $price = number_format((float) $pricing['special_price'], 2, '.', '')
            . ' ' . config('services.merchant.currency', 'INR');

        $storefront = rtrim((string) config('services.merchant.storefront_url'), '/');
        $link = $storefront . '/product/' . ltrim($product->slug, '/');

        $sizes = array_values(array_filter(array_map('trim', explode(',', (string) $product->size))));
        $id = $product->sku ?: ('DF-' . $product->id);

        return [
            'id' => $id,
            'item_group_id' => $id,
            'title' => Str::limit(trim((string) $product->title), 150, ''),
            'description' => $this->description($product),
            'link' => $link,
            'image_link' => $images[0],
            'additional_image_link' => array_slice($images, 1, 10),
            'availability' => ((int) $product->stock) > 0 ? 'in_stock' : 'out_of_stock',
            'price' => $price,
            'brand' => $brands[$product->brand_id] ?? $defaultBrand,
            'condition' => $this->condition($product->condition),
            'google_product_category' => (string) config('services.merchant.google_product_category'),
            'product_type' => (string) config('services.merchant.product_type'),
            'color' => $product->color ? Str::lower(trim($product->color)) : null,
            'size' => $sizes ? implode('/', $sizes) : null,
            'gender' => (string) config('services.merchant.gender', 'male'),
            'age_group' => (string) config('services.merchant.age_group', 'adult'),
            'identifier_exists' => 'no',
            'mpn' => $product->sku ?: null,
            'channel' => 'online',
            'content_language' => (string) config('services.merchant.language', 'en'),
            'target_country' => (string) config('services.merchant.country', 'IN'),
        ];
    }

    public function toXml(Collection $items): string
    {
        $storefront = rtrim((string) config('services.merchant.storefront_url'), '/');
        $title = htmlspecialchars((string) config('services.merchant.brand', 'Dhirago') . ' Product Feed', ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $link = htmlspecialchars($storefront, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
        $xml .= "  <channel>\n";
        $xml .= "    <title>{$title}</title>\n";
        $xml .= "    <link>{$link}</link>\n";
        $xml .= "    <description>Google Merchant Center product feed</description>\n";

        foreach ($items as $item) {
            $xml .= "    <item>\n";
            $xml .= $this->xmlTag('g:id', $item['id']);
            $xml .= $this->xmlTag('g:item_group_id', $item['item_group_id']);
            $xml .= $this->xmlTag('title', $item['title']);
            $xml .= $this->xmlTag('description', $item['description']);
            $xml .= $this->xmlTag('link', $item['link']);
            $xml .= $this->xmlTag('g:image_link', $item['image_link']);
            foreach ($item['additional_image_link'] as $extra) {
                $xml .= $this->xmlTag('g:additional_image_link', $extra);
            }
            $xml .= $this->xmlTag('g:availability', $item['availability']);
            $xml .= $this->xmlTag('g:price', $item['price']);
            $xml .= $this->xmlTag('g:brand', $item['brand']);
            $xml .= $this->xmlTag('g:condition', $item['condition']);
            $xml .= $this->xmlTag('g:google_product_category', $item['google_product_category']);
            $xml .= $this->xmlTag('g:product_type', $item['product_type']);
            if (!empty($item['color'])) {
                $xml .= $this->xmlTag('g:color', $item['color']);
            }
            if (!empty($item['size'])) {
                $xml .= $this->xmlTag('g:size', $item['size']);
            }
            $xml .= $this->xmlTag('g:gender', $item['gender']);
            $xml .= $this->xmlTag('g:age_group', $item['age_group']);
            $xml .= $this->xmlTag('g:identifier_exists', $item['identifier_exists']);
            if (!empty($item['mpn'])) {
                $xml .= $this->xmlTag('g:mpn', $item['mpn']);
            }
            $xml .= "    </item>\n";
        }

        $xml .= "  </channel>\n</rss>\n";

        return $xml;
    }

    public function toTsv(Collection $items): string
    {
        $columns = [
            'id', 'item_group_id', 'title', 'description', 'link', 'image_link',
            'additional_image_link', 'availability', 'price', 'brand', 'condition',
            'google_product_category', 'product_type', 'color', 'size', 'gender',
            'age_group', 'identifier_exists', 'mpn',
        ];

        $lines = [implode("\t", $columns)];
        foreach ($items as $item) {
            $row = [];
            foreach ($columns as $col) {
                $value = $item[$col] ?? '';
                if (is_array($value)) {
                    $value = implode(',', $value);
                }
                $row[] = $this->tsvCell((string) $value);
            }
            $lines[] = implode("\t", $row);
        }

        return implode("\n", $lines) . "\n";
    }

    private function description(Product $product): string
    {
        $candidates = [
            $product->description,
            $product->summary,
            $product->title,
        ];

        foreach ($candidates as $raw) {
            $text = $this->plainText((string) $raw);
            if ($text === '' || $this->looksLikeJunk($text)) {
                continue;
            }
            return Str::limit($text, 5000, '');
        }

        return Str::limit(trim((string) $product->title), 5000, '');
    }

    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        return trim($text);
    }

    private function looksLikeJunk(string $text): bool
    {
        $lower = Str::lower($text);
        return str_contains($lower, 'schema.org')
            || str_contains($lower, '"@type"')
            || str_contains($lower, 'videoobject');
    }

    private function condition(?string $condition): string
    {
        return in_array($condition, ['new', 'refurbished', 'used'], true) ? $condition : 'new';
    }

    /**
     * @return list<string>
     */
    private function imageUrls(?string $photoJson): array
    {
        $photos = json_decode($photoJson ?? '[]', true);
        if (!is_array($photos)) {
            return [];
        }

        usort($photos, function ($a, $b) {
            $ao = is_array($a) ? ($a['sort_order'] ?? null) : null;
            $bo = is_array($b) ? ($b['sort_order'] ?? null) : null;
            if ($ao === null && $bo === null) return 0;
            if ($ao === null) return 1;
            if ($bo === null) return -1;
            return (int) $ao <=> (int) $bo;
        });

        $urls = [];
        foreach ($photos as $photo) {
            $path = is_array($photo) ? ($photo['url'] ?? '') : '';
            $url = media_url($path);
            if ($url !== '' && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))) {
                // Drop cache-buster query for cleaner Merchant Center URLs
                $urls[] = strtok($url, '?') ?: $url;
            }
        }

        return array_values(array_unique($urls));
    }

    private function xmlTag(string $name, string $value): string
    {
        return '      <' . $name . '>' . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</' . $name . ">\n";
    }

    private function tsvCell(string $value): string
    {
        $value = str_replace(["\t", "\r", "\n"], ' ', $value);
        return $value;
    }
}
