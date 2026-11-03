<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="{{route('admin')}}">
      <div class="sidebar-brand-icon rotate-n-15">
        <i class="fas fa-laugh-wink"></i>
      </div>
      <div class="sidebar-brand-text mx-3">Admin</div>
    </a>

    <hr class="sidebar-divider my-0">

    <li class="nav-item active">
      <a class="nav-link" href="{{route('admin')}}">
        <i class="fas fa-fw fa-tachometer-alt"></i>
        <span>Dashboard</span></a>
    </li>

    @module('file_manager')
    <hr class="sidebar-divider">
    <div class="sidebar-heading">Media</div>
    <li class="nav-item">
        <a class="nav-link" href="{{route('file-manager')}}">
            <i class="fas fa-fw fa-folder-open"></i>
            <span>File Manager</span></a>
    </li>
    @endmodule

    @if(auth()->user()->canModule('customers') || auth()->user()->canModule('push') || auth()->user()->canModule('whatsapp') || auth()->user()->canModule('orders'))
    <hr class="sidebar-divider">
    <div class="sidebar-heading">Leads</div>
    @module('customers')
    <li class="nav-item">
        <a class="nav-link" href="{{route('customer.index')}}">
            <i class="fas fa-user-friends"></i>
            <span>Customers</span></a>
    </li>
    @endmodule
    @module('push')
    <li class="nav-item">
        <a class="nav-link" href="{{route('push.index')}}">
            <i class="fas fa-bell"></i>
            <span>Push Notification</span></a>
    </li>
    @endmodule
    @module('whatsapp')
    <li class="nav-item">
        <a class="nav-link" href="{{route('whatsapp.index')}}">
            <i class="fab fa-whatsapp"></i>
            <span>WhatsApp Notification</span></a>
    </li>
    @endmodule
    @module('orders')
    <li class="nav-item">
        <a class="nav-link" href="{{route('order.index')}}">
            <i class="fas fa-hammer fa-chart-area"></i>
            <span>Orders</span>
        </a>
    </li>
    @endmodule
    @endif

    @module('banners')
    <hr class="sidebar-divider">
    <div class="sidebar-heading">Banner</div>
    <li class="nav-item">
      <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="true" aria-controls="collapseTwo">
        <i class="fas fa-image"></i>
        <span>Banners</span>
      </a>
      <div id="collapseTwo" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
        <div class="bg-white py-2 collapse-inner rounded">
          <h6 class="collapse-header">Banner Options:</h6>
          <a class="collapse-item" href="{{route('banner.index')}}">Banners</a>
          <a class="collapse-item" href="{{route('banner.create')}}">Add Banners</a>
        </div>
      </div>
    </li>
    @endmodule

    @if(auth()->user()->canModule('categories') || auth()->user()->canModule('brands') || auth()->user()->canModule('products') || auth()->user()->canModule('shipping') || auth()->user()->canModule('returns') || auth()->user()->canModule('reviews'))
    <hr class="sidebar-divider">
    <div class="sidebar-heading">Shop</div>

    @module('categories')
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#categoryCollapse" aria-expanded="true" aria-controls="categoryCollapse">
          <i class="fas fa-sitemap"></i>
          <span>Category</span>
        </a>
        <div id="categoryCollapse" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
          <div class="bg-white py-2 collapse-inner rounded">
            <h6 class="collapse-header">Category Options:</h6>
            <a class="collapse-item" href="{{route('category.index')}}">Category</a>
            <a class="collapse-item" href="{{route('category.create')}}">Add Category</a>
          </div>
        </div>
    </li>
    @endmodule

    @module('brands')
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#brandCollapse" aria-expanded="true" aria-controls="brandCollapse">
          <i class="fas fa-table"></i>
          <span>Brands</span>
        </a>
        <div id="brandCollapse" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
          <div class="bg-white py-2 collapse-inner rounded">
            <h6 class="collapse-header">Brand Options:</h6>
            <a class="collapse-item" href="{{route('brand.index')}}">Brands</a>
            <a class="collapse-item" href="{{route('brand.create')}}">Add Brand</a>
          </div>
        </div>
    </li>
    @endmodule

    @module('products')
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#productCollapse" aria-expanded="true" aria-controls="productCollapse">
          <i class="fas fa-cubes"></i>
          <span>Products</span>
        </a>
        <div id="productCollapse" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
          <div class="bg-white py-2 collapse-inner rounded">
            <h6 class="collapse-header">Product Options:</h6>
            <a class="collapse-item" href="{{route('product.index')}}">Products</a>
            <a class="collapse-item" href="{{route('product.create')}}">Add Product</a>
            <a class="collapse-item" href="{{route('product.bulk.index')}}">Bulk Upload</a>
            @module('merchant_feed')
            <a class="collapse-item" href="{{route('merchant-feed.index')}}">Merchant Feed</a>
            @endmodule
          </div>
        </div>
    </li>
    @endmodule

    @module('shipping')
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#shippingCollapse" aria-expanded="true" aria-controls="shippingCollapse">
          <i class="fas fa-truck"></i>
          <span>Shipping</span>
        </a>
        <div id="shippingCollapse" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
          <div class="bg-white py-2 collapse-inner rounded">
            <h6 class="collapse-header">Shipping Options:</h6>
            <a class="collapse-item" href="{{route('shipping.index')}}">Shipping</a>
            <a class="collapse-item" href="{{route('shipping.create')}}">Add Shipping</a>
          </div>
        </div>
    </li>
    @endmodule

    @module('returns')
    <li class="nav-item">
      <a class="nav-link" href="{{ route('admin.returns') }}">
        <i class="fas fa-undo"></i>
        <span>Returns</span>
      </a>
    </li>
    @endmodule

    @module('reviews')
    <li class="nav-item">
        <a class="nav-link" href="{{route('review.index')}}">
            <i class="fas fa-comments"></i>
            <span>Reviews</span></a>
    </li>
    @endmodule
    @endif

    @module('posts')
    <hr class="sidebar-divider">
    <div class="sidebar-heading">Posts</div>
    <li class="nav-item">
      <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#postCollapse" aria-expanded="true" aria-controls="postCollapse">
        <i class="fas fa-fw fa-folder"></i>
        <span>Posts</span>
      </a>
      <div id="postCollapse" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar">
        <div class="bg-white py-2 collapse-inner rounded">
          <h6 class="collapse-header">Post Options:</h6>
          <a class="collapse-item" href="{{route('post.index')}}">Posts</a>
          <a class="collapse-item" href="{{route('post.create')}}">Add Post</a>
        </div>
      </div>
    </li>
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#postCategoryCollapse" aria-expanded="true" aria-controls="postCategoryCollapse">
          <i class="fas fa-sitemap fa-folder"></i>
          <span>Category</span>
        </a>
        <div id="postCategoryCollapse" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar">
          <div class="bg-white py-2 collapse-inner rounded">
            <h6 class="collapse-header">Category Options:</h6>
            <a class="collapse-item" href="{{route('post-category.index')}}">Category</a>
            <a class="collapse-item" href="{{route('post-category.create')}}">Add Category</a>
          </div>
        </div>
      </li>
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#tagCollapse" aria-expanded="true" aria-controls="tagCollapse">
            <i class="fas fa-tags fa-folder"></i>
            <span>Tags</span>
        </a>
        <div id="tagCollapse" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
            <h6 class="collapse-header">Tag Options:</h6>
            <a class="collapse-item" href="{{route('post-tag.index')}}">Tag</a>
            <a class="collapse-item" href="{{route('post-tag.create')}}">Add Tag</a>
            </div>
        </div>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{route('comment.index')}}">
            <i class="fas fa-comments fa-chart-area"></i>
            <span>Comments</span></a>
    </li>
    @endmodule

    @if(auth()->user()->canModule('coupons') || auth()->user()->canModule('users') || auth()->user()->canModule('roles') || auth()->user()->canModule('settings'))
    <hr class="sidebar-divider d-none d-md-block">
    <div class="sidebar-heading">General Settings</div>
    @module('coupons')
    <li class="nav-item">
      <a class="nav-link" href="{{route('coupon.index')}}">
          <i class="fas fa-table"></i>
          <span>Coupon</span></a>
    </li>
    @endmodule
    @module('users')
    <li class="nav-item">
        <a class="nav-link" href="{{route('users.index')}}">
            <i class="fas fa-users"></i>
            <span>Users</span></a>
    </li>
    @endmodule
    @module('roles')
    <li class="nav-item">
        <a class="nav-link" href="{{route('roles.index')}}">
            <i class="fas fa-user-shield"></i>
            <span>Roles &amp; Access</span></a>
    </li>
    @endmodule
    @module('settings')
    <li class="nav-item">
        <a class="nav-link" href="{{route('settings')}}">
            <i class="fas fa-cog"></i>
            <span>Settings</span></a>
    </li>
    @endmodule
    @endif

    <div class="text-center d-none d-md-inline">
      <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>
