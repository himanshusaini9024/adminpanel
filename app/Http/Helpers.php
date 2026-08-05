<?php

use App\Models\Message;
use App\Models\Category;
use App\Models\PostTag;
use App\Models\PostCategory;
use App\Models\Order;
use App\Models\Wishlist;
use App\Models\Shipping;
use App\Models\Cart;
use Illuminate\Support\Str;

// use Auth;
class Helper
{
    public static function messageList()
    {
        return Message::whereNull('read_at')->orderBy('created_at', 'desc')->get();
    }
    public static function getAllCategory()
    {
        $category = new Category();
        $menu = $category->getAllParentWithChild();
        return $menu;
    }

    public static function getHeaderCategory()
    {
        $category = new Category();
        // dd($category);
        $menu = $category->getAllParentWithChild();

        if ($menu) {
?>

            <li>
                <a href="javascript:void(0);">Category<i class="ti-angle-down"></i></a>
                <ul class="dropdown border-0 shadow">
                    <?php
                    foreach ($menu as $cat_info) {
                        if ($cat_info->child_cat->count() > 0) {
                    ?>
                            <li><a href="<?php echo route('product-cat', $cat_info->slug); ?>"><?php echo $cat_info->title; ?></a>
                                <ul class="dropdown sub-dropdown border-0 shadow">
                                    <?php
                                    foreach ($cat_info->child_cat as $sub_menu) {
                                    ?>
                                        <li><a href="<?php echo route('product-sub-cat', [$cat_info->slug, $sub_menu->slug]); ?>"><?php echo $sub_menu->title; ?></a></li>
                                    <?php
                                    }
                                    ?>
                                </ul>
                            </li>
                        <?php
                        } else {
                        ?>
                            <li><a href="<?php echo route('product-cat', $cat_info->slug); ?>"><?php echo $cat_info->title; ?></a></li>
                    <?php
                        }
                    }
                    ?>
                </ul>
            </li>
<?php
        }
    }

    public static function productCategoryList($option = 'all')
    {
        if ($option == 'all') {
            return Category::orderBy('id', 'DESC')->get();
        }
        return Category::has('products')->orderBy('id', 'DESC')->get();
    }

    public static function postTagList($option = 'all')
    {
        if ($option == 'all') {
            return PostTag::orderBy('id', 'desc')->get();
        }
        return PostTag::has('posts')->orderBy('id', 'desc')->get();
    }

    public static function postCategoryList($option = "all")
    {
        if ($option == 'all') {
            return PostCategory::orderBy('id', 'DESC')->get();
        }
        return PostCategory::has('posts')->orderBy('id', 'DESC')->get();
    }
    // Cart Count
    public static function cartCount($user_id = '')
    {

        if (Auth::check()) {
            if ($user_id == "") $user_id = auth()->user()->id;
            return Cart::where('user_id', $user_id)->where('order_id', null)->sum('quantity');
        } else {
            return 0;
        }
    }
    // relationship cart with product
    public function product()
    {
        return $this->hasOne('App\Models\Product', 'id', 'product_id');
    }

    public static function getAllProductFromCart($user_id = '')
    {
        if (Auth::check()) {
            if ($user_id == "") $user_id = auth()->user()->id;
            return Cart::with('product')->where('user_id', $user_id)->where('order_id', null)->get();
        } else {
            return 0;
        }
    }
    // Total amount cart
    public static function totalCartPrice($user_id = '')
    {
        if (Auth::check()) {
            if ($user_id == "") $user_id = auth()->user()->id;
            return Cart::where('user_id', $user_id)->where('order_id', null)->sum('amount');
        } else {
            return 0;
        }
    }
    // Wishlist Count
    public static function wishlistCount($user_id = '')
    {

        if (Auth::check()) {
            if ($user_id == "") $user_id = auth()->user()->id;
            return Wishlist::where('user_id', $user_id)->where('cart_id', null)->sum('quantity');
        } else {
            return 0;
        }
    }
    public static function getAllProductFromWishlist($user_id = '')
    {
        if (Auth::check()) {
            if ($user_id == "") $user_id = auth()->user()->id;
            return Wishlist::with('product')->where('user_id', $user_id)->where('cart_id', null)->get();
        } else {
            return 0;
        }
    }
    public static function totalWishlistPrice($user_id = '')
    {
        if (Auth::check()) {
            if ($user_id == "") $user_id = auth()->user()->id;
            return Wishlist::where('user_id', $user_id)->where('cart_id', null)->sum('amount');
        } else {
            return 0;
        }
    }

    // Total price with shipping and coupon
    public static function grandPrice($id, $user_id)
    {
        $order = Order::find($id);
        if ($order) {
            $shipping_price = (float)$order->shipping->price;
            $order_price = self::orderPrice($id, $user_id);
            return number_format((float)($order_price + $shipping_price), 2, '.', '');
        } else {
            return 0;
        }
    }


    // Admin home
    public static function earningPerMonth()
    {
        $month_data = Order::where('status', 'delivered')->get();
        // return $month_data;
        $price = 0;
        foreach ($month_data as $data) {
            $price = $data->cart_info->sum('price');
        }
        return number_format((float)($price), 2, '.', '');
    }

    public static function shipping()
    {
        return Shipping::orderBy('id', 'DESC')->get();
    }
}



if (!function_exists('generateUniqueSlug')) {
    /**
     * Generate a unique slug for a given title and model.
     *
     * @param string $title
     * @param string $modelClass
     * @return string
     */
    function generateUniqueSlug($title, $modelClass)
    {
        $slug = Str::slug($title);
        $count = $modelClass::where('slug', $slug)->count();

        if ($count > 0) {
            $slug = $slug . '-' . date('ymdis') . '-' . rand(0, 999);
        }

        return $slug;
    }
}

if (!function_exists('media_path')) {
    /**
     * Strip known media base URLs so only the object path is stored.
     */
    function media_path(?string $url): string
    {
        if ($url === null || $url === '') {
            return '';
        }

        $bases = array_filter([
            rtrim((string) config('filesystems.disks.s3.url'), '/'),
            rtrim((string) config('app.cloud_url'), '/'),
            'https://res.cloudinary.com/ds48lk80f',
        ]);

        foreach ($bases as $base) {
            if ($base !== '' && str_starts_with($url, $base)) {
                return '/' . ltrim(substr($url, strlen($base)), '/');
            }
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $path = parse_url($url, PHP_URL_PATH);
            return $path ? '/' . ltrim($path, '/') : $url;
        }

        return str_starts_with($url, '/') ? $url : '/' . ltrim($url, '/');
    }
}

if (!function_exists('media_url')) {
    /**
     * Build a public media URL from a stored path or absolute URL.
     * Legacy Cloudinary paths (/image/upload/...) keep working after S3 migration.
     */
    function media_url(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $normalized = '/' . ltrim($path, '/');

        if (str_starts_with($normalized, '/image/upload/')) {
            return 'https://res.cloudinary.com/ds48lk80f' . $normalized;
        }

        $base = rtrim((string) (config('filesystems.disks.s3.url') ?: config('app.cloud_url')), '/');

        return $base . $normalized;
    }
}

if (!function_exists('format_product_description_html')) {
    /**
     * Normalize product description to storefront HTML:
     *
     * <p class="font-futura">
     *   <span>Intro paragraph…</span><br>
     *   <span>- First bullet.</span><br>
     *   <span style="font-family: Futura;">- More bullets<br>- …</span><br>
     *   <span data-sheets-root="1">- The model…</span>
     * </p>
     *
     * Always extracts ALL text lines (never span-only), so re-saves do not drop content.
     */
    function format_product_description_html(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        $content = trim($content);
        if ($content === '') {
            return '';
        }

        $html = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Turn every visual break into a newline, then take ALL plain-text lines.
        // Do not parse <span> only — Summernote often moves bullets into <p> on edit.
        $html = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n", $html);
        $html = preg_replace('/<\/div>\s*<div[^>]*>/i', "\n", $html);
        $html = preg_replace('/<\/li>\s*<li[^>]*>/i', "\n", $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/(p|div|li|h[1-6]|tr)>/i', "\n", $html);
        $html = strip_tags($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = str_replace("\xC2\xA0", ' ', $html); // nbsp

        $lines = [];
        foreach (preg_split("/\r\n|\r|\n/", $html) as $line) {
            $line = trim(preg_replace('/[ \t]+/', ' ', $line));
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        if (empty($lines)) {
            return '';
        }

        $esc = static function (string $text): string {
            return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        };

        $intro = [];
        $bullets = [];
        $model = null;

        foreach ($lines as $line) {
            $isModel = (bool) preg_match('/\bmodel\b/i', $line)
                && (bool) preg_match('/(wearing|size|5[\'’]|ft)/i', $line);
            $isBullet = (bool) preg_match('/^\s*[-–—•]/u', $line);

            if ($isModel) {
                $model = $line;
            } elseif ($isBullet || !empty($bullets)) {
                $bullets[] = $line;
            } else {
                $intro[] = $line;
            }
        }

        $parts = [];

        if (!empty($intro)) {
            $parts[] = '<span>' . $esc(implode(' ', $intro)) . '</span>';
        }

        if (!empty($bullets)) {
            $first = array_shift($bullets);
            $parts[] = '<span>' . $esc($first) . '</span>';

            if (!empty($bullets)) {
                $joined = implode('<br>', array_map($esc, $bullets));
                $parts[] = '<span style="font-family: Futura;">' . $joined . '</span>';
            }
        }

        if ($model !== null) {
            $parts[] = '<span data-sheets-root="1">' . $esc($model) . '</span>';
        }

        if (empty($parts)) {
            return '';
        }

        return '<p class="font-futura">' . implode('<br>', $parts) . '</p>';
    }
}