        @php
            use App\Models\Setting;
            $setting = Setting::first();
            $primaryColor = $setting->primary_color ?? '#0d6efd'; // default blue
            $secondaryColor = $setting->secondary_color ?? '#6c757d'; // default gray
        @endphp

<style>
/* In your main CSS file or another style section */
.iq-sidebar { /* Assuming this is your main sidebar element */
    width: 260px; /* Default width */
    transition: width 0.3s ease;
}

body.sidebar-collapsed .iq-sidebar {
    width: 70px; /* Collapsed width */
}

body.sidebar-collapsed .iq-sidebar-logo h5,
body.sidebar-collapsed .iq-sidebar-logo img {
    /* Hide or adjust these elements when collapsed */
    display: none; /* or adjust margin/width */
}

/* We are removing the sidebar's internal toggle button, so this rule is no longer directly needed for it.
   If you had other elements with iq-menu-bt-sidebar that you wanted to always show, keep it.
   Otherwise, this block can be removed from your CSS.
.iq-menu-bt-sidebar {
    display: block !important;
}
*/

/* Adjust the top navbar's toggle button if needed */
/* For example, if you want it more prominent or precisely positioned */
.iq-top-navbar .wrapper-menu {
    cursor: pointer; /* Indicate it's clickable */
    font-size: 24px; /* Make it more visible */
    margin-right: 15px; /* Add some space from the logo */
    /* Add any other specific styling needed for this button */
}

</style>

<div class="iq-sidebar sidebar-default">
    <div class="iq-sidebar-logo d-flex align-items-center justify-content-between px-3 py-2">
        <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none">
            <img src="{{ $setting && $setting->logo_path ? asset('storage/' . $setting->logo_path) : asset('build/assets/images/logo.png') }}"
                 alt="logo"
                 class="img-fluid rounded"
                 style="height: 50px; width: 50px; object-fit: cover;">
            <h5 class="ml-3 mb-0" style="color: {{ $primaryColor }};">
                {{ posSetting('business_name', 'POSDash') }}
            </h5>
        </a>

{{--         
        <div class="iq-menu-bt-sidebar ml-2">
            <i class="las la-bars wrapper-menu" style="font-size: 20px;"></i>
        </div>
        --}}
    </div>


    <div class="data-scrollbar" data-scroll="1">
        <nav class="iq-sidebar-menu">
            <ul id="iq-sidebar-toggle" class="iq-menu">

                <li class="{{ Route::is('dashboard') ? 'active' : '' }}">
                    <a href="{{ route('dashboard') }}" class="svg-icon">
                        <svg class="svg-icon" id="p-dash1" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                        <span class="ml-4">Dashboards</span>
                    </a>
                </li>

                <li class="{{ Route::is('role.list') || Route::is('role.create') ? 'active' : '' }}">
                    <a href="#role"
                       class="{{ Route::is('role.list') || Route::is('role.create') ? '' : 'collapsed' }}"
                       data-toggle="collapse"
                       aria-expanded="{{ Route::is('role.list') || Route::is('role.create') ? 'true' : 'false' }}">

                        {{-- SVG Icon for Roles --}}
                        <svg class="svg-icon" width="20" height="20" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M20 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M4 21v-2a4 4 0 0 1 3-3.87"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span class="ml-4">Roles</span>

                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             viewBox="0 0 24 24">
                            <polyline points="10 15 15 20 20 15"></polyline>
                            <path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>

                    <ul id="role" class="iq-submenu collapse {{ Route::is('role.list') || Route::is('role.create') ? 'show' : '' }}" data-parent="#iq-sidebar-toggle">
                        <li class="{{ Route::is('role.list') ? 'active' : '' }}">
                            <a href="{{ route('role.list') }}">
                                <i class="las la-minus"></i><span>Roles List</span>
                            </a>
                        </li>
                        <li class="{{ Route::is('role.create') ? 'active' : '' }}">
                            <a href="{{ route('role.create') }}">
                                <i class="las la-minus"></i><span>Add Role</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="{{ Route::is('user.list') || Route::is('user.create') ? 'active' : '' }}">
                    <a href="#user"
                       class="{{ Route::is('user.list') || Route::is('user.create') ? '' : 'collapsed' }}"
                       data-toggle="collapse"
                       aria-expanded="{{ Route::is('user.list') || Route::is('user.create') ? 'true' : 'false' }}">

                        <svg class="svg-icon" width="20" height="20" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                          <circle cx="12" cy="7" r="4"></circle>
                          <path d="M5.5 21a8.38 8.38 0 0 1 13 0"></path>
                        </svg>
                        <span class="ml-4">Users</span>

                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             viewBox="0 0 24 24">
                            <polyline points="10 15 15 20 20 15"></polyline>
                            <path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>

                    <ul id="user" class="iq-submenu collapse {{ Route::is('user.list') || Route::is('user.create') ? 'show' : '' }}" data-parent="#iq-sidebar-toggle">
                        <li class="{{ Route::is('user.list') ? 'active' : '' }}">
                            <a href="{{ route('user.list') }}">
                                <i class="las la-minus"></i><span>Users List</span>
                            </a>
                        </li>
                        <li class="{{ Route::is('user.create') ? 'active' : '' }}">
                            <a href="{{ route('user.create') }}">
                                <i class="las la-minus"></i><span>Add User</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="{{ Route::is('warehouse.list') || Route::is('warehouse.create') ? 'active' : '' }}">
                    <a href="#warehouse"
                       class="{{ Route::is('warehouse.list') || Route::is('warehouse.create') ? '' : 'collapsed' }}"
                       data-toggle="collapse"
                       aria-expanded="{{ Route::is('warehouse.list') || Route::is('warehouse.create') ? 'true' : 'false' }}">

                        <svg class="svg-icon" width="20" height="20" xmlns="http://www.w3.org/2000/svg"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M3 9l9-6 9 6v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        <span class="ml-4">Warehouses</span>

                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             viewBox="0 0 24 24">
                            <polyline points="10 15 15 20 20 15"></polyline>
                            <path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>

                    <ul id="warehouse" class="iq-submenu collapse {{ Route::is('warehouse.list') || Route::is('warehouse.create') ? 'show' : '' }}" data-parent="#iq-sidebar-toggle">
                        <li class="{{ Route::is('warehouse.list') ? 'active' : '' }}">
                            <a href="{{ route('warehouse.list') }}">
                                <i class="las la-minus"></i><span>Warehouses List</span>
                            </a>
                        </li>
                        <li class="{{ Route::is('warehouse.create') ? 'active' : '' }}">
                            <a href="{{ route('warehouse.create') }}">
                                <i class="las la-minus"></i><span>Add Warehouse</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="{{ Route::is('branch.*') ? 'active' : '' }}">
                    <a href="#branch" class="{{ Route::is('branch.*') ? '' : 'collapsed' }}" data-toggle="collapse" aria-expanded="{{ Route::is('branch.*') ? 'true' : 'false' }}">
                        <svg class="svg-icon" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M3 12h18M3 6h18M3 18h18"></path>
                        </svg>
                        <span class="ml-4">Branches</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             viewBox="0 0 24 24">
                            <polyline points="10 15 15 20 20 15"></polyline>
                            <path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>
                    <ul id="branch" class="iq-submenu collapse {{ Route::is('branch.*') ? 'show' : '' }}" data-parent="#iq-sidebar-toggle">
                        <li class="{{ Route::is('branch.list') ? 'active' : '' }}">
                            <a href="{{ route('branch.list') }}"><i class="las la-minus"></i><span>Branches List</span></a>
                        </li>
                        <li class="{{ Route::is('branch.create') ? 'active' : '' }}">
                            <a href="{{ route('branch.create') }}"><i class="las la-minus"></i><span>Add Branch</span></a>
                        </li>
                    </ul>
                </li>

                <li class="{{ Route::is('units.list') || Route::is('units.create') ? 'active' : '' }}">
                    <a href="#unitMenu"
                       class="{{ Route::is('units.list') || Route::is('units.create') ? '' : 'collapsed' }}"
                       data-toggle="collapse"
                       aria-expanded="{{ Route::is('units.list') || Route::is('units.create') ? 'true' : 'false' }}">

                        <svg class="svg-icon" width="20" height="20" xmlns="http://www.w3.org/2000/svg"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round" viewBox="0 0 24 24">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="3" y1="9" x2="21" y2="9"></line>
                            <line x1="9" y1="21" x2="9" y2="9"></line>
                        </svg>
                        <span class="ml-4">Units</span>

                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             viewBox="0 0 24 24">
                            <polyline points="10 15 15 20 20 15"></polyline>
                            <path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>

                    <ul id="unitMenu" class="iq-submenu collapse {{ Route::is('units.list') || Route::is('units.create') ? 'show' : '' }}" data-parent="#iq-sidebar-toggle">
                        <li class="{{ Route::is('units.list') ? 'active' : '' }}">
                            <a href="{{ route('units.list') }}">
                                <i class="las la-minus"></i><span>Units List</span>
                            </a>
                        </li>
                        <li class="{{ Route::is('units.create') ? 'active' : '' }}">
                            <a href="{{ route('units.create') }}">
                                <i class="las la-minus"></i><span>Add Unit</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="{{ Route::is('categories.list') || Route::is('categories.create') || Route::is('categories.edit') ? 'active' : '' }}">
                    <a href="#categories" class="{{ Route::is('categories.list') || Route::is('categories.create') || Route::is('categories.edit') ? '' : 'collapsed' }}"
                       data-toggle="collapse" aria-expanded="{{ Route::is('categories.list') || Route::is('categories.create') || Route::is('categories.edit') ? 'true' : 'false' }}">

                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             viewBox="0 0 24 24">
                            <path d="M3 7a2 2 0 0 1 2-2h5l2 2h9a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                        </svg>

                        <span class="ml-4">Categories</span>

                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20"
                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline>
                            <path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>

                    <ul id="categories" class="iq-submenu collapse {{ Route::is('categories.list') || Route::is('categories.create') || Route::is('categories.edit') ? 'show' : '' }}"
                        data-parent="#iq-sidebar-toggle">

                        <li class="{{ Route::is('categories.list') ? 'active' : '' }}">
                            <a href="{{ route('categories.list') }}">
                                <i class="las la-minus"></i><span>Categories List</span>
                            </a>
                        </li>

                        <li class="{{ Route::is('categories.create') ? 'active' : '' }}">
                            <a href="{{ route('categories.create') }}">
                                <i class="las la-minus"></i><span>Add Category</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="{{ Route::is('suppliers.list') || Route::is('suppliers.create') || Route::is('reports.supplier_products') ? 'active' : '' }}">
                    <a href="#supplier" class="{{ Route::is('suppliers.list') || Route::is('suppliers.create') || Route::is('reports.supplier_products') ? '' : 'collapsed' }}" data-toggle="collapse" aria-expanded="{{ Route::is('suppliers.list') || Route::is('suppliers.create') || Route::is('reports.supplier_products') ? 'true' : 'false' }}">
                        <svg class="svg-icon" width="20" height="20" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path d="M3 4a2 2 0 0 1 2-2h3.5a2 2 0 0 1 1.41.59L12 4.09l2.09-1.5A2 2 0 0 1 15.5 2H19a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4z" />
                        </svg>
                        <span class="ml-4">Suppliers</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>

                    <ul id="supplier" class="iq-submenu collapse {{ Route::is('suppliers.list') || Route::is('suppliers.create') || Route::is('reports.supplier_products') ? 'show' : '' }}" data-parent="#iq-sidebar-toggle">
                        <li class="{{ Route::is('suppliers.list') ? 'active' : '' }}">
                            <a href="{{ route('suppliers.list') }}">
                                <i class="las la-minus"></i><span>Suppliers List</span>
                            </a>
                        </li>
                        <li class="{{ Route::is('suppliers.create') ? 'active' : '' }}">
                            <a href="{{ route('suppliers.create') }}">
                                <i class="las la-minus"></i><span>Add Supplier</span>
                            </a>
                        </li>
                        <li class="{{ Route::is('reports.supplier_products') ? 'active' : '' }}">
                            <a href="{{ route('reports.supplier_products') }}">
                                <i class="las la-minus"></i><span>Supplier Products</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="{{ Route::is('products.list') || Route::is('products.create') ? 'active' : '' }}">
                    <a href="#product" class="{{ Route::is('products.list') || Route::is('products.create') ? '' : 'collapsed' }}" data-toggle="collapse" aria-expanded="{{ Route::is('products.list') || Route::is('products.create') ? 'true' : 'false' }}">
                        <svg class="svg-icon" width="20" height="20" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path d="M20 13V5a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8m16 0v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-6m16 0H4" />
                        </svg>
                        <span class="ml-4">Product</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>

                    <ul id="product" class="iq-submenu collapse {{ Route::is('products.list') || Route::is('products.create') ? 'show' : '' }}" data-parent="#iq-sidebar-toggle">
                        <li class="{{ Route::is('products.list') ? 'active' : '' }}">
                            <a href="{{ route('products.list') }}">
                                <i class="las la-minus"></i><span>Products List</span>
                            </a>
                        </li>
                        <li class="{{ Route::is('products.create') ? 'active' : '' }}">
                            <a href="{{ route('products.create') }}">
                                <i class="las la-minus"></i><span>Add Product</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="{{ Route::is('customers.list') || Route::is('customers.create') ? 'active' : '' }}">
                    <a href="#customer" class="{{ Route::is('customers.list') || Route::is('customers.create') ? '' : 'collapsed' }}" data-toggle="collapse" aria-expanded="{{ Route::is('customers.list') || Route::is('customers.create') ? 'true' : 'false' }}">
                        <svg class="svg-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M20 13V5a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8m16 0v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-6m16 0H4"/>
                        </svg>
                        <span class="ml-4">Customer</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>
                    <ul id="customer" class="iq-submenu collapse {{ Route::is('customers.list') || Route::is('customers.create') ? 'show' : '' }}" data-parent="#iq-sidebar-toggle">
                        <li class="{{ Route::is('customers.list') ? 'active' : '' }}">
                            <a href="{{ route('customers.list') }}"><i class="las la-minus"></i><span>Customers List</span></a>
                        </li>
                        <li class="{{ Route::is('customers.create') ? 'active' : '' }}">
                            <a href="{{ route('customers.create') }}"><i class="las la-minus"></i><span>Add Customer</span></a>
                        </li>
                    </ul>
                </li>

                <li class="{{ Route::is('purchases.*') || Route::is('purchase_returns.*') ? 'active' : '' }}">
                    <a href="#purchase" class="{{ Route::is('purchases.*') || Route::is('purchase_returns.*') ? '' : 'collapsed' }}" data-toggle="collapse" aria-expanded="{{ Route::is('purchases.*') || Route::is('purchase_returns.*') ? 'true' : 'false' }}">
                        <svg class="svg-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M3 3h18v4H3zM3 7h18l-1 13H4L3 7zm5 4h2v5H8v-5zm6 0h2v5h-2v-5z"/>
                        </svg>
                        <span class="ml-4">Purchase</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline>
                            <path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>
                    <ul id="purchase" class="iq-submenu collapse {{ Route::is('purchases.*') || Route::is('purchase_returns.*') ? 'show' : '' }}" data-parent="#iq-sidebar-toggle">
                        <li class="{{ Route::is('purchases.list') ? 'active' : '' }}">
                            <a href="{{ route('purchases.list') }}"><i class="las la-minus"></i><span>Purchase List</span></a>
                        </li>
                        <li class="{{ Route::is('purchases.create') ? 'active' : '' }}">
                            <a href="{{ route('purchases.create') }}"><i class="las la-minus"></i><span>Add Purchase</span></a>
                        </li>
                        <li class="{{ Route::is('purchase_returns.*') ? 'active' : '' }}">
                            <a href="{{ route('purchase_returns.index') }}"><i class="las la-minus"></i><span>Purchase Returns</span></a>
                        </li>
                    </ul>
                </li>

                <li class="{{ Route::is('quotations.*') ? 'active' : '' }}">
                    <a href="#quotations" class="{{ Route::is('quotations.*') ? '' : 'collapsed' }}" data-toggle="collapse" aria-expanded="{{ Route::is('quotations.*') ? 'true' : 'false' }}">
                        {{-- SVG Icon for Quotations (e.g., File, Document, Invoice icon) --}}
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 10 12 13 12"></polyline>
                        </svg>
                        <span class="ml-4">Quotations</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>
                
                    <ul id="quotations" class="iq-submenu collapse {{ Route::is('quotations.*') ? 'show' : '' }}" data-parent="#iq-sidebar-toggle">
                        <li class="{{ Route::is('quotations.index') ? 'active' : '' }}">
                            <a href="{{ route('quotations.index') }}">
                                <i class="las la-minus"></i><span>List Quotations</span>
                            </a>
                        </li>
                        <li class="{{ Route::is('quotations.create') ? 'active' : '' }}">
                            <a href="{{ route('quotations.create') }}">
                                <i class="las la-minus"></i><span>Create Quotation</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="{{ Route::is('sales.list') || Route::is('sales.create') || Route::is('sales_return.list') ? 'active' : '' }}">
                    <a href="#sales"
                       class="{{ Route::is('sales.list') || Route::is('sales.create') || Route::is('sales_return.list') ? '' : 'collapsed' }}"
                       data-toggle="collapse"
                       aria-expanded="{{ Route::is('sales.list') || Route::is('sales.create') || Route::is('sales_return.list') ? 'true' : 'false' }}">

                        <svg class="svg-icon" id="sales-icon" width="20" height="20" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 17L10 10L21 21"></path>
                            <path d="M7 3H4a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1z"></path>
                        </svg>

                        <span class="ml-4">Sales</span>

                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20"
                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline>
                            <path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>

                    <ul id="sales" class="iq-submenu collapse {{ Route::is('sales.list') || Route::is('sales.create') || Route::is('sales_return.list') ? 'show' : '' }}" data-parent="#iq-sidebar-toggle">
                        <li class="{{ Route::is('sales.list') ? 'active' : '' }}">
                            <a href="{{ route('sales.list') }}">
                                <i class="las la-minus"></i><span>Sales List</span>
                            </a>
                        </li>
                        <li class="{{ Route::is('sales.create') ? 'active' : '' }}">
                            <a href="{{ route('sales.create') }}">
                                <i class="las la-minus"></i><span>Add Sale</span>
                            </a>
                        </li>
                        <li class="{{ Route::is('sales_return.*') ? 'active' : '' }}">
                            <a href="{{ route('sale_return.list') }}">
                                <i class="las la-minus"></i><span>Sale Returns</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="{{ Route::is('payments.list') || Route::is('payments.create') ? 'active' : '' }}">
                    <a href="#payments" class="{{ Route::is('payments.list') || Route::is('payments.create') ? '' : 'collapsed' }}" data-toggle="collapse" aria-expanded="{{ Route::is('payments.list') || Route::is('payments.create') ? 'true' : 'false' }}">
                        <svg class="svg-icon" width="20" height="20" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                            <path d="M16 3h-1a2 2 0 0 0-2 2v2h6V5a2 2 0 0 0-2-2z"></path>
                        </svg>
                        <span class="ml-4">Payments</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>

                    <ul id="payments" class="iq-submenu collapse {{ Route::is('payments.list') || Route::is('payments.create') ? 'show' : '' }}" data-parent="#iq-sidebar-toggle">
                        <li class="{{ Route::is('payments.list') ? 'active' : '' }}">
                            <a href="{{ route('payments.list') }}">
                                <i class="las la-minus"></i><span>Payments List</span>
                            </a>
                        </li>
                        <li class="{{ Route::is('payments.create') ? 'active' : '' }}">
                            <a href="{{ route('payments.create') }}">
                                <i class="las la-minus"></i><span>Add Payment</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="{{ Route::is('expense_categories.list') || Route::is('expense.list') ? 'active' : '' }}">
                    <a href="#expense" class="{{ Route::is('expense_categories.list') || Route::is('expense.list') ? '' : 'collapsed' }}" data-toggle="collapse" aria-expanded="{{ Route::is('expense.list') || Route::is('expense.create') ? 'true' : 'false' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 16v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2zM5 12h14v2H5zm0-4h14v2H5zm8-7h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2z" />
                        </svg>
                        <span class="ml-4">Expenses</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>

                    <ul id="expense" class="iq-submenu collapse {{ Route::is('expense_categories.list') || Route::is('expense.list') ? 'show' : '' }}" data-parent="#iq-sidebar-toggle">
                        <li class="{{ Route::is('expense_categories.list') ? 'active' : '' }}">
                            <a href="{{ route('expense_categories.list') }}">
                                <i class="las la-minus"></i><span>Expense Categories</span>
                            </a>
                        </li>
                        <li class="{{ Route::is('expense.list') ? 'active' : '' }}">
                            <a href="{{ route('expense.list') }}">
                                <i class="las la-minus"></i><span>Expense</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="{{ Route::is('orders.*') ? 'active' : '' }}">
                    <a href="{{ route('orders.index') }}" class="svg-icon">
                        <svg class="svg-icon" id="orders-icon" width="20" height="20" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 7h18"></path>
                            <path d="M3 12h18"></path>
                            <path d="M3 17h18"></path>
                        </svg>
                        <span class="ml-4">Orders</span>
                    </a>
                </li>
                
                <li class="{{ Route::is('discount_rules.*') ? 'active' : '' }}">
                    <a href="{{ route('discount_rules.index') }}" class="svg-icon">
                        <svg class="svg-icon" id="discount-icon" width="20" height="20" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 11a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"></path>
                            <path d="M15 17a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"></path>
                            <path d="M6 6l12 12"></path>
                            <path d="M21 15V9a2 2 0 0 0-.59-1.41l-5-5A2 2 0 0 0 14 2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 .59 1.41l5 5A2 2 0 0 0 10 19h8a2 2 0 0 0 2-2z"></path>
                        </svg>
                        <span class="ml-4">Discount Rules</span>
                    </a>
                </li>

                <li class="{{ Route::is('stock.list') ? 'active' : '' }}">
                    <a href="{{ route('stock.list') }}" class="svg-icon">
                        <svg class="svg-icon" id="p-dash1" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                        <span class="ml-4">Stock Inventory</span>
                    </a>
                </li>

                <li class="{{ Route::is('stock.ledger') ? 'active' : '' }}">
                    <a href="{{ route('stock.ledger') }}" class="svg-icon">
                        <svg class="svg-icon" id="p-dash1" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                        <span class="ml-4">Stock Ledger</span>
                    </a>
                </li>

                <li class="{{ Route::is('stock.transfers.*') ? 'active' : '' }}">
                    <a href="{{ route('stock.transfers.index') }}" class="svg-icon">
                        <svg class="svg-icon" width="20" height="20" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="17 1 21 5 17 9"></polyline>
                            <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                            <polyline points="7 23 3 19 7 15"></polyline>
                            <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
                        </svg>
                        <span class="ml-4">Stock Transfers</span>
                    </a>
                </li>

                <li class="{{ Route::is('stock.adjustment.*') ? 'active' : '' }}">
                    <a href="{{ route('stock.adjustment.create') }}" class="svg-icon">
                        <svg class="svg-icon" width="20" height="20" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="16"></line>
                            <line x1="8" y1="12" x2="16" y2="12"></line>
                        </svg>
                        <span class="ml-4">Stock Adjustment</span>
                    </a>
                </li>

                {{-- Reports --}}
                <li class="{{ Route::is('reports.*') ? 'active' : '' }}">
                    <a href="#reportsMenu"
                       class="{{ Route::is('reports.*') ? '' : 'collapsed' }}"
                       data-toggle="collapse"
                       aria-expanded="{{ Route::is('reports.*') ? 'true' : 'false' }}">
                        <svg class="svg-icon" width="20" height="20" xmlns="http://www.w3.org/2000/svg"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round" viewBox="0 0 24 24">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                        <span class="ml-4">Reports</span>
                        <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20"
                             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="10 15 15 20 20 15"></polyline>
                            <path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                        </svg>
                    </a>
                    <ul id="reportsMenu" class="iq-submenu collapse {{ Route::is('reports.*') ? 'show' : '' }}" data-parent="#iq-sidebar-toggle">
                        <li class="{{ Route::is('reports.sales') ? 'active' : '' }}">
                            <a href="{{ route('reports.sales') }}"><i class="las la-minus"></i><span>Sales Report</span></a>
                        </li>
                        <li class="{{ Route::is('reports.purchases') ? 'active' : '' }}">
                            <a href="{{ route('reports.purchases') }}"><i class="las la-minus"></i><span>Purchases Report</span></a>
                        </li>
                        <li class="{{ Route::is('reports.expenses') ? 'active' : '' }}">
                            <a href="{{ route('reports.expenses') }}"><i class="las la-minus"></i><span>Expenses Report</span></a>
                        </li>
                        <li class="{{ Route::is('reports.profit-loss') ? 'active' : '' }}">
                            <a href="{{ route('reports.profit-loss') }}"><i class="las la-minus"></i><span>Profit &amp; Loss</span></a>
                        </li>
                        <li class="{{ Route::is('reports.inventory-valuation') ? 'active' : '' }}">
                            <a href="{{ route('reports.inventory-valuation') }}"><i class="las la-minus"></i><span>Inventory Valuation</span></a>
                        </li>
                    </ul>
                </li>

                <li class="{{ Route::is('settings.index') ? 'active' : '' }}">
                    <a href="{{ route('settings.index') }}" class="svg-icon">
                        <svg class="svg-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65
                                     1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65
                                     0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65
                                     1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0
                                     0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65
                                     1.65 0 0 0 1.82.33h.09a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65
                                     0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65
                                     1.65 0 0 0-.33 1.82v.09a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65
                                     1.65 0 0 0-1.51 1z">
                            </path>
                        </svg>
                        <span class="ml-4">POS Settings</span>
                    </a>
                </li>

            </ul>
        </nav>
        <div class="p-3"></div>
    </div>
</div>

<div class="iq-top-navbar">
    <div class="iq-navbar-custom">
        <nav class="navbar navbar-expand-lg navbar-light p-0">
            <div class="iq-navbar-logo d-flex align-items-center justify-content-between">
                {{-- THIS IS THE TOGGLE BUTTON THAT SHOULD ALWAYS BE VISIBLE --}}
                <i class="ri-menu-line wrapper-menu"></i>
                <a href="{{ route('dashboard') }}" class="header-logo">
                    <img src="{{ asset('build/assets/images/logo.png') }}" class="img-fluid rounded-normal" alt="logo">
                    <h5 class="logo-title ml-3">POS</h5>
                </a>
            </div>
            <div class="d-flex align-items-center ml-auto">
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent">
                    <i class="ri-menu-3-line"></i>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ml-auto navbar-list align-items-center">
                        {{-- <li class="nav-item">
                            <a href="{{ route('pos.index') }}" class="btn btn-primary px-4 py-2 mx-2" style="font-size: 14px; height: 40px; line-height: 26px; border-radius: 8px;">
                                POS
                            </a>
                        </li> --}}

                        <li class="nav-item">
                            <a href="#" id="posBtn" class="btn px-4 py-2 mx-2 text-white"
                               style="font-size: 14px; height: 40px; line-height: 26px; border-radius: 8px; background-color: {{ $primaryColor }};">
                                POS
                            </a>
                        </li>


                        <li class="nav-item nav-icon dropdown caption-content">
                            <a href="#" class="search-toggle dropdown-toggle" data-toggle="dropdown">
                                <img src="{{ asset('build/assets/images/user/01.jpg') }}" class="img-fluid rounded" alt="user">
                            </a>
                            <div class="iq-sub-dropdown dropdown-menu">
                                <div class="card shadow-none m-0">
                                    <div class="card-body p-0 text-center">
                                        <div class="media-body profile-detail text-center">
                                            <img src="{{ asset('build/assets/images/page-img/profile-bg.jpg') }}" class="rounded-top img-fluid mb-4" alt="profile-bg">
                                            <img src="{{ asset('build/assets/images/user/01.jpg') }}" class="rounded profile-img img-fluid avatar-70" alt="profile-img">
                                        </div>
                                        <div class="p-3">
                                            <h5 class="mb-1">{{ Auth::user()->email }}</h5>
                                            <div class="d-flex align-items-center justify-content-center mt-3">
                                                <a href="{{ route('profile.edit') }}" class="btn border mr-2">Profile</a>
                                                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn border">Log Out</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>

                </div>
            </div>
        </nav>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const posBtn = document.getElementById('posBtn');

    if (posBtn) {
        posBtn.addEventListener('click', function (e) {
            e.preventDefault();

            fetch("{{ route('pos.checkRegister') }}")
                .then(res => res.json())
                .then(data => {
                    if (!data.open) {
                        Swal.fire({
                            title: 'POS Register',
                            text: 'Enter Opening Cash:',
                            input: 'number',
                            inputAttributes: {
                                min: 0,
                                step: 0.01
                            },
                            inputValue: 0,
                            showCancelButton: true,
                            confirmButtonText: 'Submit',
                            cancelButtonText: 'Cancel',
                            allowOutsideClick: false,
                            allowEscapeKey: true,
                            preConfirm: (opening_cash) => {
                                return fetch("{{ route('pos.openRegister') }}", {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({ opening_cash: parseFloat(opening_cash) })
                                })
                                .then(async response => {
                                    const result = await response.json();
                                    if (!response.ok || !result.success) {
                                        throw new Error(result.message || 'Failed to open register.');
                                    }
                                    return result;
                                })
                                .catch(error => {
                                    Swal.showValidationMessage(error.message);
                                });
                            }
                        }).then((result) => {
                            if (result.isConfirmed && result.value && result.value.success) {
                                window.location.href = result.value.redirect;
                            }
                        });
                    } else {
                        window.location.href = "{{ route('pos.index') }}";
                    }
                })
                .catch(err => {
                    console.error("Error checking register:", err);
                    Swal.fire('Error', 'Could not verify register status.', 'error');
                });
        });
    }
});

document.querySelector('.wrapper-menu').addEventListener('click', () => {
    document.body.classList.toggle('sidebar-collapsed'); // or toggle any class your layout uses
});

</script>