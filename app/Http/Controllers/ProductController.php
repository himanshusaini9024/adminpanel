<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;

use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $products = Product::getAllProduct();
        return view('backend.product.index', compact('products'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $brands = Brand::get();
        $categories = Category::where('is_parent', 1)->get();
        return view('backend.product.create', compact('categories', 'brands'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        // dd($request->all());exit;    
        $validatedData = $request->validate([
            'title' => 'required|string',
            'summary' => 'required|string',
            'description' => 'nullable|string',
            'size' => 'nullable',
            'stock' => 'required|numeric',
            'color' => 'required|string',
            'cat_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'child_cat_id' => 'nullable|exists:categories,id',
            'is_featured' => 'sometimes|in:1',
            'status' => 'required|in:active,inactive',
            'condition' => 'required|in:default,new,hot',
            'price' => 'required|numeric',
            'discount' => 'nullable|numeric',
        ]);

        $slug = generateUniqueSlug($request->title, Product::class);
        $validatedData['slug'] = $slug;
        $validatedData['is_featured'] = $request->input('is_featured', 0);

        if ($request->has('size')) {
            $validatedData['size'] = is_array($request->size)
                ? implode(',', $request->size)
                : $request->size;
        } else {
            $validatedData['size'] = '';
        }


        $product = Product::create($validatedData);

        $message = $product
            ? 'Product Successfully added'
            : 'Please try again!!';

        return redirect()->route('product.index')->with(
            $product ? 'success' : 'error',
            $message
        );
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // Implement if needed
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $brands = Brand::get();
        $product = Product::findOrFail($id);
        $measurment = json_decode($product->measurements);
  

        $categories = Category::where('is_parent', 1)->get();
        $items = Product::where('id', $id)->get();

        return view('backend.product.edit', compact('product','measurment', 'brands', 'categories', 'items'));
    }



    public function update(Request $request, $id)
    {

        $product = Product::findOrFail($id);

        // ── Validation ────────────────────────────────────────────────────
        $request->validate([
            // General tab
            'product_description.1.name' => 'required|string|max:255',
            'cat_id'                     => 'required|exists:categories,id',
            'child_cat_id'               => 'nullable|exists:categories,id',
            'furniture_type'             => 'nullable|in:1,2,3,4,5',
            'condition'                  => 'nullable|in:default,new,hot',
            'status'                     => 'required|in:active,inactive',
            'date_added'                 => 'nullable|date',

            // SEO tab
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

            // Data tab
            'summary'   => 'required|string',
            'sku'       => 'nullable|string|max:100',
            'brand_id'  => 'nullable|exists:brands,id',
            'size'      => 'nullable|array',
            'size.*'    => 'nullable|in:S,M,L,XL',
            'color'     => 'nullable|string|max:50',

            // Price tab
            'price'         => 'required|numeric|min:0',
            'discount'      => 'nullable|numeric|min:0|max:100',
            'special_price' => 'nullable|numeric|min:0',
            'stock'         => 'required|numeric|min:0',

            // Image tab
            'photo'       => 'nullable|array',
            'photo.*.url' => 'required_with:photo|string',
            'photo.*.alt' => 'nullable|string',

            // Dimension tab (clothing measurements)
            'chest'         => 'nullable|numeric|min:0',
            'length'        => 'nullable|numeric|min:0',
            'shoulder'      => 'nullable|numeric|min:0',
            'sleeve_length' => 'nullable|numeric|min:0',
            'waist'         => 'nullable|numeric|min:0',
            'hip'           => 'nullable|numeric|min:0',

            // FAQ tab
            'faqs'            => 'nullable|array',
            'faqs.*.question' => 'required_with:faqs|string',
            'faqs.*.answer'   => 'required_with:faqs|string',
        ]);

        // ── Size: array → comma-separated string ──────────────────────────
        $size = null;
        if ($request->filled('size') && is_array($request->size)) {
            $size = implode(',', $request->size);
        }

        // ── Photos: clean & re-encode, keep existing if none submitted ────
        $photo = $product->photo; // fallback: preserve current images
        if ($request->has('photo') && is_array($request->photo)) {
            $clean = [];
            foreach ($request->photo as $p) {
                if (!empty($p['url'])) {
                    $clean[] = [
                        'url' => str_replace('https://res.cloudinary.com/ds48lk80f', '', $p['url']),
                        'alt' => $p['alt'] ?? null,
                    ];
                }
            }
            if (!empty($clean)) {
                $photo = json_encode($clean);
            }
        }

        // ── FAQs: filter empty rows, encode to JSON ───────────────────────
        $faqs = null;
        if ($request->has('faqs') && is_array($request->faqs)) {
            $filtered = collect($request->faqs)
                ->filter(fn($f) => !empty($f['question']) && !empty($f['answer']))
                ->values()
                ->toArray();

            $faqs = !empty($filtered) ? json_encode($filtered) : null;
        }
        $measurements = [
            'chest'         => $request->input('chest'),
            'length'        => $request->input('length'),
            'shoulder'      => $request->input('shoulder'),
            'sleeve_length' => $request->input('sleeve_length'),
            'waist'         => $request->input('waist'),
            'hip'           => $request->input('hip'),
        ];

        // remove null values (clean JSON)
        $measurements = array_filter($measurements, fn($v) => !is_null($v));
        // ── Build update payload ──────────────────────────────────────────
        $data = [
            // General
            'title'          => $request->input('product_description.1.name'),
            'display_name'   => $request->input('product_description.1.displaysetname'),
            'furniture_type' => $request->input('furniture_type'),
            'cat_id'         => $request->input('cat_id'),
            'child_cat_id'   => $request->input('child_cat_id'),
            'condition'      => $request->input('condition'),
            'status'         => $request->input('status'),
            'date_added'     => $request->input('date_added'),
            'is_featured'    => $request->has('is_featured') ? 1 : 0,

            // SEO
            'meta_title'       => $request->input('product_description.1.meta_title'),
            'meta_description' => $request->input('product_description.1.meta_description'),
            'meta_keyword'     => $request->input('product_description.1.meta_keyword'),
            'schema_gtm'       => $request->input('product_description.1.schema_gtm'),
            'description'      => $request->input('product_description.1.description'),
            'alt'              => $request->input('alt'),
            'search_tags'      => $request->input('search_tags'),
            'rating'           => $request->input('rating'),
            'star'             => $request->input('star'),
            'no_follow'        => $request->input('no_follow'),

            // Data
            'summary'  => $request->input('summary'),
            'sku'      => $request->input('sku'),
            'brand_id' => $request->input('brand_id'),
            'size'     => $size,
            'color'    => $request->input('color'),

            // Price
            'price'         => $request->input('price'),
            'discount'      => $request->input('discount', 0),
            'special_price' => $request->input('special_price'),
            'stock'         => $request->input('stock'),

            // Images
            'photo' => $photo,

            // Clothing dimensions
            'measurements' => !empty($measurements) ? json_encode($measurements) : null,

            // FAQ
            'faqs' => $faqs,
        ];

        $product->update($data);

        return redirect()->route('product.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $status = $product->delete();

        $message = $status
            ? 'Product successfully deleted'
            : 'Error while deleting product';

        return redirect()->route('product.index')->with(
            $status ? 'success' : 'error',
            $message
        );
    }
}
