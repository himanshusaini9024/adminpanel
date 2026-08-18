<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::getAllProduct();
        return view('backend.product.index', compact('products'));
    }

    public function create()
    {
        $brands = Brand::get();
        $categories = Category::where('is_parent', 1)->get();
        return view('backend.product.create', compact('categories', 'brands'));
    }

    public function store(Request $request)
    {
        // Full form from "Copy Product" uses the edit blade payload
        if ($request->boolean('from_copy') || $request->has('product_description.1.name')) {
            return $this->storeFromFullForm($request);
        }

        $validatedData = $request->validate([
            'title' => 'required|string',
            'summary' => ['required', 'string', function ($attribute, $value, $fail) {
                $text = trim(html_entity_decode(strip_tags((string) $value)));
                if ($text === '') {
                    $fail('The summary field is required.');
                }
            }],
            'description' => 'nullable|string',
            'size' => 'nullable',
            'stock' => 'required|numeric',
            'color' => 'required|string',
            'cat_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'child_cat_id' => 'nullable|exists:categories,id',
            'is_featured' => 'sometimes|in:1',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'required|in:active,inactive',
            'condition' => 'required|in:default,new,hot',
            'price' => 'required|numeric',
            'discount' => 'nullable|numeric',
            'size_guide_json' => 'nullable|string',
        ]);

        $slug = generateUniqueSlug($request->title, Product::class);
        $validatedData['slug'] = $slug;
        $validatedData['is_featured'] = $request->input('is_featured', 0);
        $validatedData['sort_order'] = $request->filled('sort_order') ? (int) $request->input('sort_order') : null;

        if ($request->has('size')) {
            $validatedData['size'] = is_array($request->size)
                ? implode(',', $request->size)
                : $request->size;
        } else {
            $validatedData['size'] = '';
        }

        if (array_key_exists('description', $validatedData)) {
            $validatedData['description'] = format_product_description_html($validatedData['description'] ?? '');
        }

        $validatedData['measurements'] = json_encode(
            $this->buildSizeGuideFromRequest($request)
        );

        unset($validatedData['size_guide_json']);

        $product = Product::create($validatedData);

        return redirect()->route('product.index')->with(
            $product ? 'success' : 'error',
            $product ? 'Product Successfully added' : 'Please try again!!'
        );
    }

    /**
     * Open the product edit form prefilled for copying.
     * Saving creates a NEW product (does not update the original).
     */
    public function copy($id)
    {
        $original = Product::findOrFail($id);
        $product = $original->replicate();
        $product->title = rtrim($original->title) . ' (Copy)';
        $product->sku = $this->uniqueCopySku($original->sku);
        $product->photo = null; // do not copy images
        // Keep original id on the in-memory model so edit-view JS (child category) still works
        $product->setAttribute('id', $original->id);

        $measurment = json_decode($original->measurements);
        $sizeGuide = $this->normalizeSizeGuide($original->measurements);
        $brands = Brand::get();
        $categories = Category::where('is_parent', 1)->get();
        $items = Product::where('id', $id)->get();
        $isCopy = true;

        return view('backend.product.edit', compact(
            'product',
            'measurment',
            'sizeGuide',
            'brands',
            'categories',
            'items',
            'isCopy'
        ));
    }

    public function show($id)
    {
        //
    }

    public function edit($id)
    {
        $brands = Brand::get();
        $product = Product::findOrFail($id);
        $measurment = json_decode($product->measurements);
        $sizeGuide = $this->normalizeSizeGuide($product->measurements);
        $categories = Category::where('is_parent', 1)->get();
        $items = Product::where('id', $id)->get();
        $isCopy = false;

        return view('backend.product.edit', compact(
            'product',
            'measurment',
            'sizeGuide',
            'brands',
            'categories',
            'items',
            'isCopy'
        ));
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $this->validateFullProductForm($request);
        
        $data = $this->buildFullProductPayload($request, $product->photo);

        $product->update($data);

        return redirect()->route('product.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $status = $product->delete();

        return redirect()->route('product.index')->with(
            $status ? 'success' : 'error',
            $status ? 'Product successfully deleted' : 'Error while deleting product'
        );
    }

    private function storeFromFullForm(Request $request)
    {
        $this->validateFullProductForm($request);

        $data = $this->buildFullProductPayload($request, null);
        $title = $data['title'];
        $data['slug'] = generateUniqueSlug($title, Product::class);

        // Ensure SKU is unique if already taken
        if (!empty($data['sku']) && Product::where('sku', $data['sku'])->exists()) {
            $data['sku'] = $this->uniqueCopySku($data['sku']);
        }

        $product = Product::create($data);

        return redirect()->route('product.index')
            ->with('success', 'Product copied and created successfully.');
    }

    private function validateFullProductForm(Request $request): void
    {
        $request->validate([
            'product_description.1.name' => 'required|string|max:255',
            'cat_id'                     => 'required|exists:categories,id',
            'child_cat_id'               => 'nullable|exists:categories,id',
            'furniture_type'             => 'nullable|in:1,2,3,4,5',
            'condition'                  => 'nullable|in:default,new,hot',
            'status'                     => 'required|in:active,inactive',
            'date_added'                 => 'nullable|date',

            'product_description.1.meta_title'       => 'nullable|string|max:255',
            'product_description.1.meta_description' => 'nullable|string|max:500',
            'product_description.1.meta_keyword'     => 'nullable|string',
            'product_description.1.schema_gtm'       => 'nullable|string',
            'product_description.1.description'      => 'nullable|string',
            'alt'                                    => 'nullable|string|max:255',
            'search_tags'                            => 'nullable|string',
            'rating'                                 => 'nullable|numeric|min:0',
            'star'                                   => 'nullable|numeric|min:0|max:5',
            'no_follow'                              => 'nullable|string',

            'summary'   => ['required', 'string', function ($attribute, $value, $fail) {
                $text = trim(html_entity_decode(strip_tags((string) $value)));
                if ($text === '') {
                    $fail('The summary field is required.');
                }
            }],
            'sku'       => 'required|string|max:100',
            'brand_id'  => 'nullable|exists:brands,id',
            'size'      => 'nullable|array',
            'size.*'    => 'nullable|in:S,M,L,XL',
            'color'     => 'nullable|string|max:50',

            'price'         => 'required|numeric|min:0',
            'discount'      => 'nullable|numeric|min:0|max:100',
            'special_price' => 'nullable|numeric|min:0',
            'stock'         => 'required|numeric|min:0',
            'sort_order'    => 'nullable|integer|min:0',

            'photo'       => 'nullable|array',
            'photo.*.url' => 'required_with:photo|string',
            'photo.*.alt' => 'nullable|string',

            'size_guide_json' => 'nullable|string',

            'faqs'            => 'nullable|array',
            'faqs.*.question' => 'required_with:faqs|string',
            'faqs.*.answer'   => 'required_with:faqs|string',
        ]);
    }

    private function buildFullProductPayload(Request $request, $fallbackPhoto = null): array
    {
             
        $size = null;
        if ($request->filled('size') && is_array($request->size)) {
            $size = implode(',', $request->size);
        }

        $photo = $fallbackPhoto;
        if ($request->has('photo') && is_array($request->photo)) {
            $clean = [];
            foreach ($request->photo as $p) {
                if (!empty($p['url'])) {
                    $clean[] = [
                        'url' => media_path_versioned($p['url'], strtotime($request->date_added)),
                        'url_mobile' => !empty($p['url_mobile']) ? media_path_versioned($p['url_mobile'], $request->date_added) : null,
                        'alt' => $p['alt'] ?? null,         
                        'type' => $p['type'] ?? null,
                        'sort_order' => $p['sort_order'] ?? null,
                        'new_size' => !empty($p['new_size']),
                    ];
                }
            }
            if (!empty($clean)) {
                usort($clean, function ($a, $b) {
                    $ao = $a['sort_order'] ?? null;
                    $bo = $b['sort_order'] ?? null;
                    if ($ao === null && $bo === null) return 0;
                    if ($ao === null) return 1;
                    if ($bo === null) return -1;
                    return (int) $ao <=> (int) $bo;
                });
                $photo = json_encode(array_values($clean));
            }
        }

        $faqs = null;
        if ($request->has('faqs') && is_array($request->faqs)) {
            $filtered = collect($request->faqs)
                ->filter(fn ($f) => !empty($f['question']) && !empty($f['answer']))
                ->values()
                ->toArray();

            $faqs = !empty($filtered) ? json_encode($filtered) : null;
        }

        $measurements = $this->buildSizeGuideFromRequest($request);
        // echo "<pre>";
        // print_r($photo);die;
        // $data['slug'] = generateUniqueSlug($request->input('product_description.1.displaysetname'), Product::class);
        $data['slug'] = $request->input('product_description.1.displaysetname');
        // Only persist columns that exist on products / are fillable
        return [
            'title'        => $request->input('product_description.1.name'),
            'cat_id'       => $request->input('cat_id'),
            'child_cat_id' => $request->input('child_cat_id'),
            'condition'    => $request->input('condition'),
            'status'       => $request->input('status'),
            'is_featured'  => $request->has('is_featured') ? 1 : 0,
            'sort_order'   => $request->filled('sort_order') ? (int) $request->input('sort_order') : null,
            'description'  => format_product_description_html($request->input('product_description.1.description')),
            'summary'      => $request->input('summary'),
            'sku'          => $request->input('sku'),
            'brand_id'     => $request->input('brand_id'),
            'size'         => $size,
            'color'        => $request->input('color'),
            'price'        => $request->input('price'),
            'discount'     => $request->input('discount', 0),
            'stock'        => $request->input('stock'),
            'photo'        => $photo,
            'slug'        => $data['slug'],
            'measurements' => json_encode($measurements),
        ];
    }

    /**
     * Build size guide payload from admin form (size_guide_json).
     */
    private function buildSizeGuideFromRequest(Request $request): array
    {
        $raw = $request->input('size_guide_json');
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $this->sanitizeSizeGuide($decoded);
            }
        }

        // Legacy flat cm fields (create form fallback)
        $legacyKeys = ['chest', 'length', 'shoulder', 'sleeve_length', 'waist', 'hip'];
        $hasLegacy = false;
        foreach ($legacyKeys as $key) {
            if ($request->filled($key)) {
                $hasLegacy = true;
                break;
            }
        }
        if ($hasLegacy) {
            return $this->legacyFlatToSizeGuide($request->only($legacyKeys));
        }

        return ['type' => 'size_guide', 'dimensions' => []];
    }

    private function sanitizeSizeGuide(array $data): array
    {
        $dimensions = [];
        foreach ($data['dimensions'] ?? [] as $dim) {
            $name = trim((string) ($dim['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $sizes = [];
            foreach (($dim['sizes'] ?? []) as $sizeKey => $vals) {
                $sizeKey = strtoupper(trim((string) $sizeKey));
                if ($sizeKey === '') {
                    continue;
                }
                $inch = isset($vals['inch']) && $vals['inch'] !== '' && $vals['inch'] !== null
                    ? round((float) $vals['inch'], 1) : null;
                $cm = isset($vals['cm']) && $vals['cm'] !== '' && $vals['cm'] !== null
                    ? round((float) $vals['cm'], 1) : null;

                if ($inch === null && $cm !== null) {
                    $inch = round($cm / 2.54, 1);
                } elseif ($cm === null && $inch !== null) {
                    $cm = round($inch * 2.54, 1);
                }

                if ($inch === null && $cm === null) {
                    continue;
                }

                $sizes[$sizeKey] = ['inch' => $inch, 'cm' => $cm];
            }
            $dimensions[] = ['name' => $name, 'sizes' => $sizes];
        }

        return ['type' => 'size_guide', 'dimensions' => $dimensions];
    }

    /**
     * Normalize DB measurements (new size_guide or legacy flat cm) for the admin UI.
     */
    private function normalizeSizeGuide($raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        } elseif (is_object($raw)) {
            $raw = json_decode(json_encode($raw), true);
        }

        if (!is_array($raw) || empty($raw)) {
            return ['type' => 'size_guide', 'dimensions' => []];
        }

        if (($raw['type'] ?? null) === 'size_guide') {
            return $this->sanitizeSizeGuide($raw);
        }

        return $this->legacyFlatToSizeGuide($raw);
    }

    private function legacyFlatToSizeGuide(array $flat): array
    {
        $labels = [
            'chest' => 'Chest',
            'length' => 'Length',
            'shoulder' => 'Shoulder',
            'sleeve_length' => 'Sleeve Length',
            'waist' => 'Waist',
            'hip' => 'Hip',
        ];

        $dimensions = [];
        foreach ($labels as $key => $label) {
            if (!isset($flat[$key]) || $flat[$key] === '' || $flat[$key] === null) {
                continue;
            }
            $cm = round((float) $flat[$key], 1);
            $inch = round($cm / 2.54, 1);
            $dimensions[] = [
                'name' => $label,
                'sizes' => [
                    'M' => ['inch' => $inch, 'cm' => $cm],
                ],
            ];
        }

        return ['type' => 'size_guide', 'dimensions' => $dimensions];
    }

    private function uniqueCopySku(?string $sku): string
    {
        $base = trim((string) $sku);
        if ($base === '') {
            $base = 'SKU';
        }

        $candidate = $base . '-COPY';
        $i = 1;
        while (Product::where('sku', $candidate)->exists()) {
            $candidate = $base . '-COPY-' . $i;
            $i++;
        }

        return $candidate;
    }
}
