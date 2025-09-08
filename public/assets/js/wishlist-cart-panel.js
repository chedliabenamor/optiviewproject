// wishlist-cart-panel.js
// Handles showing/hiding wishlist and cart slide-out panels

document.addEventListener('DOMContentLoaded', function () {
    // Cart panel
    var cartPanel = document.querySelector('.wrap-header-cart.js-panel-cart');
    var cartShowBtns = document.querySelectorAll('.js-show-cart');
    var cartHideBtns = document.querySelectorAll('.js-hide-cart');

    cartShowBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            cartPanel.classList.add('show-header-cart');
            try { renderCartFromSource(); } catch(e) { /* noop */ }
        });
    });
    cartHideBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            cartPanel.classList.remove('show-header-cart');
        });
    });

    // Wishlist panel
    var wishlistPanel = document.querySelector('.wrap-header-wishlist.js-panel-wishlist');
    var wishlistShowBtns = document.querySelectorAll('.js-show-wishlist');
    var wishlistHideBtns = document.querySelectorAll('.js-hide-wishlist');

    // Function to check if user is a guest
    function isGuest() {
        return !(typeof window.isAuthenticated !== 'undefined' && 
               (window.isAuthenticated === true || window.isAuthenticated === 'true'));
    }

    // Show wishlist panel
    var wishlistList = document.getElementById('wishlist-items-list');
    wishlistShowBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            wishlistPanel.classList.add('show-header-cart');
            
            if (isGuest()) {
                // For guests, use localStorage
                try {
                    var localData = localStorage.getItem('wishlist') || '[]';
                    var items = JSON.parse(localData);
                    renderWishlistItems(items);
                } catch (e) {
                    console.error('Error parsing wishlist from localStorage:', e);
                    renderWishlistEmpty();
                }
            } else {
                // For authenticated users, fetch from server and enrich with offers/images
                fetch('/wishlist', { headers: {'X-Requested-With': 'XMLHttpRequest'} })
                .then(function(response) { if (!response.ok) throw new Error('Network response was not ok'); return response.json(); })
                .then(function(items) {
                    if (!Array.isArray(items) || items.length === 0) { renderWishlistEmpty(); return; }
                    // Enrich each item with active offer and variant image if available
                    return Promise.all(items.map(function(it){
                        return fetch('/api/products/' + it.id + '/quick-view')
                            .then(function(r){ return r.ok ? r.json() : null; })
                            .then(function(data){
                                if (!data) return it;
                                // choose image: variant-specific if variantId present
                                if (it.variantId) {
                                    var v = (data.productVariants || []).find(function(vv){ return String(vv.id) === String(it.variantId); });
                                    if (v && v.productVariantImages && v.productVariantImages.length > 0) {
                                        it.image = v.productVariantImages[0].imageUrl || it.image;
                                    }
                                    // price/offer
                                    if (v && v.offer && v.offer.has_offer) {
                                        it.hasOffer = true;
                                        it.price = v.offer.discounted_price;
                                        it.originalPrice = v.offer.original_price;
                                        it.currency = '€';
                                    } else {
                                        it.hasOffer = false;
                                        it.price = v ? v.price : it.price;
                                    }
                                } else {
                                    // base product
                                    if (data.offer && data.offer.has_offer) {
                                        it.hasOffer = true;
                                        it.price = data.offer.discounted_price;
                                        it.originalPrice = data.offer.original_price;
                                        it.currency = '€';
                                    } else {
                                        it.hasOffer = false;
                                        it.price = data.price;
                                    }
                                    // prefer overview image from API
                                    if (data.overviewImage) it.image = data.overviewImage;
                                }
                                return it;
                            })
                            .catch(function(){ return it; });
                    })).then(function(enriched){ renderWishlistItems(enriched); });
                })
                .catch(function(error) {
                    console.error('Error fetching wishlist:', error);
                    renderWishlistEmpty();
                });
            }
        });
    });

    // Delegated remove-from-wishlist handler for header panel
    if (wishlistList) {
        wishlistList.addEventListener('click', function(e) {
            var target = e.target;
            // Support clicking either the button or the icon inside
            if (target.classList.contains('wishlist-remove-btn') || 
                (target.closest && target.closest('.wishlist-remove-btn'))) {
                
                var btn = target.classList.contains('wishlist-remove-btn') ? 
                         target : target.closest('.wishlist-remove-btn');
                var productId = btn.getAttribute('data-product-id');
                var variantId = btn.getAttribute('data-variant-id');
                if (!productId) return;
                
                var removePromise;
                
                if (isGuest()) {
                    // Handle guest removal
                    try {
                        var wishlist = JSON.parse(localStorage.getItem('wishlist') || '[]');
                        wishlist = wishlist.filter(function(item) {
                            var sameProduct = String(item.id) == String(productId);
                            var sameVariant = String(item.variantId || '') == String(variantId || '');
                            // keep item if it's not the exact pair we are removing
                            return !(sameProduct && sameVariant);
                        });
                        localStorage.setItem('wishlist', JSON.stringify(wishlist));
                        removePromise = Promise.resolve(wishlist);
                    } catch (e) {
                        console.error('Error processing wishlist removal:', e);
                        removePromise = Promise.reject(e);
                    }
                } else {
                    // Handle authenticated user removal
                    removePromise = fetch('/wishlist/remove', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: 'id=' + encodeURIComponent(productId)
                    })
                    .then(function(resp) { 
                        if (!resp.ok) throw new Error('Network response was not ok');
                        return resp.json(); 
                    });
                }

                // Update UI after removal
                removePromise
                    .then(function(updatedWishlist) {
                        // Remove item from DOM
                        var li = btn.closest('li.header-cart-item');
                        if (li) li.parentNode.removeChild(li);
                        
                        // Update count in header
                        var count = Array.isArray(updatedWishlist) ? updatedWishlist.length : 0;
                        document.querySelectorAll('.icon-header-noti').forEach(function(icon) {
                            if (icon.querySelector('.zmdi-favorite-outline')) {
                                icon.setAttribute('data-notify', count);
                            }
                        });
                        
                        // Show empty message if needed
                        if (count === 0) {
                            renderWishlistEmpty();
                        }

                        // Update product card wishlist icons if helper is available
                        try {
                            if (typeof updateWishlistUI === 'function' && Array.isArray(updatedWishlist)) {
                                updateWishlistUI(updatedWishlist);
                            }
                            // Notify other parts of the app (e.g., product detail heart)
                            document.dispatchEvent(new CustomEvent('wishlistUpdated', {
                                detail: { items: Array.isArray(updatedWishlist) ? updatedWishlist : null }
                            }));
                        } catch(e) { /* noop */ }
                    })
                    .catch(function(error) {
                        console.error('Error removing item:', error);
                        // Show error to user
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Error', 'Could not remove item from wishlist', 'error');
                        } else {
                            alert('Could not remove item from wishlist');
                        }
                    });
            }
        });
    }
    // Helpers to determine auth and reload wishlist
    function isAuth(){ return (typeof window.isAuthenticated !== 'undefined') && (window.isAuthenticated === true || window.isAuthenticated === 'true'); }
    function reloadWishlistFromSource(){
        return new Promise(function(resolve){
            if (!isAuth()) {
                // Guest -> localStorage
                try {
                    var items = JSON.parse(localStorage.getItem('wishlist') || '[]');
                    resolve(Array.isArray(items) ? items : []);
                } catch(e){ resolve([]); }
                return;
            }
            // Authenticated -> fetch from server (expects JSON array like header count fetch)
            fetch('/wishlist', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r){ return r.json(); })
                .then(function(arr){ resolve(Array.isArray(arr) ? arr : []); })
                .catch(function(){ resolve([]); });
        });
    }

    // Re-render prices on currency change
    document.addEventListener('currencyChanged', function(){
        if (_lastWishlistItems && _lastWishlistItems.length) {
            renderWishlistItems(_lastWishlistItems);
            return;
        }
        // If we don't have a cached list yet, reload from source so we can format values
        reloadWishlistFromSource().then(function(items){
            _lastWishlistItems = items;
            renderWishlistItems(items);
        });
    });

    function renderWishlistEmpty() {
        var list = document.getElementById('wishlist-items-list');
        list.innerHTML = '<li class="header-cart-item flex-w flex-t m-b-12"><span class="stext-110 cl2">Your wishlist is empty.</span></li>';
    }

    var _lastWishlistItems = null;
    function renderWishlistItems(items) {
        var list = document.getElementById('wishlist-items-list');
        list.innerHTML = '';
        if (!items || items.length === 0) {
            list.innerHTML = '<li class="header-cart-item flex-w flex-t m-b-12"><span class="stext-110 cl2">Your wishlist is empty.</span></li>';
            return;
        }
        _lastWishlistItems = items.slice();
        function decodeUnicodeEscapes(str){
            if (typeof str !== 'string') return str;
            try { return str.replace(/\\u([0-9a-fA-F]{4})/g, function(m, g1){ return String.fromCharCode(parseInt(g1,16)); }); }
            catch(e){ return str; }
        }
        items.forEach(function(item) {
            var name = decodeUnicodeEscapes(item.name || '');
            var img = item.image ? `<img src="${item.image}" alt="${name}" class="header-cart-item-img">` : '';
            var hasOffer = !!item.hasOffer || (item.hasOffer === '1');
            var priceNum = (typeof item.price === 'number') ? item.price : (function(){ try { return parseFloat(String(item.price).replace(/[^0-9.\-]/g,'')); } catch(e){ return null; } })();
            var originalNum = (typeof item.originalPrice === 'number') ? item.originalPrice : (item.originalPrice ? parseFloat(String(item.originalPrice)) : null);
            var priceHtml;
            if (window.Currency) {
                if (hasOffer && originalNum != null) {
                    priceHtml = window.Currency.formatPair(priceNum, originalNum);
                } else {
                    priceHtml = '<span>' + window.Currency.format(priceNum) + '</span>';
                }
            } else {
                function fmt(n){ try { var v = parseFloat(n); return isNaN(v)? String(n): v.toFixed(2);} catch(e){ return String(n);} }
                var currency = item.currency || '€';
                var priceFormatted = (priceNum !== null && !isNaN(priceNum)) ? (currency + fmt(priceNum)) : (String(item.price) || '');
                var originalFormatted = (originalNum !== null && !isNaN(originalNum)) ? (currency + fmt(originalNum)) : '';
                priceHtml = hasOffer && originalFormatted
                    ? `<span style="color:#e74c3c;font-weight:700">${priceFormatted}</span><span style="margin-left:8px;color:#777;text-decoration:line-through">${originalFormatted}</span>`
                    : `<span>${priceFormatted}</span>`;
            }
            var removeBtn = `
                <button class="wishlist-remove-btn" data-product-id="${item.id}" data-variant-id="${item.variantId || ''}" title="Remove from wishlist">
                    <i class="zmdi zmdi-close"></i>
                </button>`;
            var addToCartBtn = `
                <button class="flex-c-m stext-101 cl0 size-101 bg1 bor1 hov-btn1 p-lr-15 trans-04 js-addcart-detail add-to-cart-btn" 
                        data-product-id="${item.id}" 
                        data-product-name="${name}" 
                        data-product-price="${priceNum !== null && !isNaN(priceNum) ? priceNum : ''}"
                        data-product-image="${item.image || ''}"
                        data-variant-id="${item.variantId || ''}"
                        data-variant-name="${item.variantName || ''}">
                    Add to Cart
                </button>`;
                
            var html = `
            <li class="header-cart-item flex-w flex-t m-b-12">
                <div class="header-cart-item-img">${img}${removeBtn}</div>
                <div class="header-cart-item-txt p-t-8">
                    <a href="/product/${item.id}" class="header-cart-item-name m-b-8 hov-cl1 trans-04">${name}</a>
                    <span class="header-cart-item-info m-b-8">${priceHtml}</span>
                    <div class="w-full">${addToCartBtn}</div>
                </div>
            </li>`;
            list.innerHTML += html;
        });
    }
    wishlistHideBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            wishlistPanel.classList.remove('show-header-cart');
        });
    });

    // Handle Add to Cart from wishlist
    document.addEventListener('click', function(e) {
        // Check if the clicked element or its parent is an add-to-cart button in the wishlist
        const addToCartBtn = e.target.closest('.add-to-cart-btn');
        if (addToCartBtn && wishlistPanel.contains(addToCartBtn)) {
            e.preventDefault();
            
            const productId = addToCartBtn.getAttribute('data-product-id');
            const productName = addToCartBtn.getAttribute('data-product-name');
            const productPrice = addToCartBtn.getAttribute('data-product-price');
            const productImage = addToCartBtn.getAttribute('data-product-image');
            const variantId = addToCartBtn.getAttribute('data-variant-id') || '';
            const variantName = addToCartBtn.getAttribute('data-variant-name') || '';
            
            // Create an event that the main cart handler will catch
            const addToCartEvent = new CustomEvent('addToCart', {
                detail: {
                    productId: productId,
                    productName: productName,
                    productPrice: productPrice,
                    productImage: productImage,
                    quantity: 1,
                    variantId: variantId,
                    variantName: variantName
                },
                bubbles: true
            });
            
            // Dispatch the event
            document.dispatchEvent(addToCartEvent);
            
            // Optional: Show success message
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    position: 'top-end',
                    icon: 'success',
                    title: 'Added to cart!',
                    showConfirmButton: false,
                    timer: 1000
                });
            }
        }
    });

    // ====================
    // Cart Functions (Guest + Auth)
    // ====================
    function isAuth(){ return (typeof window.isAuthenticated !== 'undefined') && (window.isAuthenticated === true || window.isAuthenticated === 'true'); }

    function getCart() {
        if (isAuth()) { return []; /* unused for auth; use getCartFromServer */ }
        try {
            var raw = localStorage.getItem('cart') || '[]';
            var arr = JSON.parse(raw);
            return Array.isArray(arr) ? arr : [];
        } catch(e) { return []; }
    }

    function saveCart(items) {
        try { localStorage.setItem('cart', JSON.stringify(items || [])); } catch(e) { /* noop */ }
    }

    function addItemToCart(item) {
        if (isAuth()) { return null; }
        var cart = getCart();
        var existing = cart.find(function(ci){ return String(ci.id) === String(item.id) && String(ci.variantId || '') === String(item.variantId || ''); });
        if (existing) {
            existing.quantity = (parseInt(existing.quantity || 1, 10) + parseInt(item.quantity || 1, 10));
        } else {
            cart.push({
                id: item.id,
                variantId: item.variantId || '',
                name: item.name,
                variantName: item.variantName || '',
                price: parseFloat(item.price || 0) || 0,
                image: item.image || '',
                quantity: parseInt(item.quantity || 1, 10)
            });
        }
        saveCart(cart);
        return cart;
    }

    function removeItemFromCart(productId, variantId) {
        if (isAuth()) { return null; }
        var cart = getCart().filter(function(ci){ return !(String(ci.id) === String(productId) && String(ci.variantId || '') === String(variantId || '')); });
        saveCart(cart);
        return cart;
    }

    function updateCartBadge(count) {
        try {
            document.querySelectorAll('.icon-header-noti').forEach(function(icon) {
                if (icon.querySelector('.zmdi-shopping-cart')) {
                    icon.setAttribute('data-notify', String(count));
                }
            });
        } catch(e) { /* noop */ }
    }

    function formatCurrency(num) {
        var n = parseFloat(num);
        if (window.Currency && typeof window.Currency.format === 'function') {
            return window.Currency.format(n);
        }
        if (isNaN(n)) return '€0.00';
        return '€' + n.toFixed(2);
    }

    var _lastCartItems = null;
    function normalizeServerCart(cart){
        try {
            var items = (cart && Array.isArray(cart.items)) ? cart.items : [];
            return items.map(function(it){ return {
                id: it.productId,
                variantId: it.variantId || '',
                name: it.name,
                variantName: it.variantName || '',
                image: it.image || '',
                price: typeof it.unitPrice === 'number' ? it.unitPrice : parseFloat(it.unitPrice || 0) || 0,
                quantity: typeof it.quantity === 'number' ? it.quantity : parseInt(it.quantity || 1, 10)
            }; });
        } catch(e){ return []; }
    }
    function renderCartItems(items) {
        var list = document.getElementById('cart-items-list');
        var totalEl = document.getElementById('cart-total');
        if (!list || !totalEl) return;
        list.innerHTML = '';
        if (!items || items.length === 0) {
            list.innerHTML = '<li class="header-cart-item flex-w flex-t m-b-12"><span class="stext-110 cl2">Your cart is empty.</span></li>';
            totalEl.textContent = 'Total: ' + formatCurrency(0);
            updateCartBadge(0);
            _lastCartItems = [];
            try { document.dispatchEvent(new CustomEvent('cartUpdated', { detail: { items: [] } })); } catch(e) {}
            return;
        }
        _lastCartItems = items.slice();
        var total = 0;
        items.forEach(function(ci){
            var name = (ci.name || '').replace(/\u([0-9a-fA-F]{4})/g, function(m,g1){ return String.fromCharCode(parseInt(g1,16)); });
            var variantName = (ci.variantName || '').replace(/\u([0-9a-fA-F]{4})/g, function(m,g1){ return String.fromCharCode(parseInt(g1,16)); });
            var price = parseFloat(ci.price || 0) || 0;
            var qty = parseInt(ci.quantity || 1, 10);
            var imgHtml = ci.image ? '<img src="' + ci.image + '" alt="' + name + '">' : '';
            var line = price * qty;
            total += line;
            var li = document.createElement('li');
            li.className = 'header-cart-item flex-w flex-t m-b-12';
            li.innerHTML = `
                <div class="header-cart-item-img">${imgHtml}</div>
                <div class="header-cart-item-txt p-t-8">
                    <a href="/product/${ci.id}" class="header-cart-item-name m-b-18 hov-cl1 trans-04">${name}</a>
                    ${variantName ? `<div class="stext-110 cl2 m-b-6 d-none">Variant: ${variantName}</div>` : ''}
                    <div class="flex-w flex-m m-b-6">
                        <button class="cart-qty-minus size-28 flex-c-m cl2 hov-cl1 trans-04" data-product-id="${ci.id}" data-variant-id="${ci.variantId || ''}" title="Decrease">-</button>
                        <span class="m-lr-10 stext-110 cart-qty-value" data-product-id="${ci.id}" data-variant-id="${ci.variantId || ''}">${qty}</span>
                        <button class="cart-qty-plus size-28 flex-c-m cl2 hov-cl1 trans-04" data-product-id="${ci.id}" data-variant-id="${ci.variantId || ''}" title="Increase">+</button>
                        <span class="m-l-12 header-cart-item-info">x ${formatCurrency(price)}</span>
                    </div>
                    <button class="cart-remove-btn stext-110 cl2 p-t-8" data-product-id="${ci.id}" data-variant-id="${ci.variantId || ''}" title="Remove">
                        <i class="zmdi zmdi-delete" style="color:#e74c3c"></i>
                    </button>
                </div>`;
            list.appendChild(li);
        });
        totalEl.textContent = 'Total: ' + formatCurrency(total);
        updateCartBadge(items.reduce(function(sum, it){ return sum + (parseInt(it.quantity || 1, 10)); }, 0));
        try { document.dispatchEvent(new CustomEvent('cartUpdated', { detail: { items: _lastCartItems } })); } catch(e) {}
    }

    function renderCartFromSource() {
        if (isAuth()) {
            fetch('/api/cart')
                .then(function(r){ return r.ok ? r.json() : Promise.reject(); })
                .then(function(cart){ var items = normalizeServerCart(cart); renderCartItems(items); })
                .catch(function(){ renderCartItems([]); });
            return;
        }
        // Guest: enrich prices from quick-view (to apply discounts) before rendering
        var items = getCart();
        if (!items || !items.length) { renderCartItems(items); return; }
        Promise.all(items.map(function(ci){
            var url = '/api/products/' + encodeURIComponent(ci.id) + '/quick-view';
            return fetch(url).then(function(r){ return r.ok ? r.json() : null; }).then(function(data){
                if (!data) return ci;
                if (ci.variantId) {
                    var v = (data.productVariants || []).find(function(vv){ return String(vv.id) === String(ci.variantId); });
                    if (v) {
                        if (v.offer && v.offer.has_offer) { ci.price = v.offer.discounted_price; }
                        else if (typeof v.price !== 'undefined') { ci.price = v.price; }
                        if (!ci.image && v.productVariantImages && v.productVariantImages.length>0) { ci.image = v.productVariantImages[0].imageUrl; }
                    }
                } else {
                    if (data.offer && data.offer.has_offer) { ci.price = data.offer.discounted_price; }
                    else if (typeof data.price !== 'undefined') { ci.price = data.price; }
                    if (!ci.image && data.overviewImage) { ci.image = data.overviewImage; }
                }
                return ci;
            }).catch(function(){ return ci; });
        })).then(function(enriched){ saveCart(enriched); renderCartItems(enriched); })
          .catch(function(){ renderCartItems(items); });
        }

    // Re-render cart when currency changes
    document.addEventListener('currencyChanged', function(){
        if (_lastCartItems && _lastCartItems.length) {
            renderCartItems(_lastCartItems);
        } else {
            renderCartFromSource();
        }
    });

    // Remove-from-cart (delegated)
    var cartList = document.getElementById('cart-items-list');
    if (cartList) {
        cartList.addEventListener('click', function(e){
            var target = e.target;
            // Quantity decrement
            var minus = target.closest ? target.closest('.cart-qty-minus') : (target.classList.contains('cart-qty-minus') ? target : null);
            if (minus) {
                var pid = minus.getAttribute('data-product-id');
                var vid = minus.getAttribute('data-variant-id') || '';
                if (isAuth()) {
                    var qtyEl = cartList.querySelector('.cart-qty-value[data-product-id="' + pid + '"][data-variant-id="' + vid + '"]');
                    var current = qtyEl ? parseInt(qtyEl.textContent) || 1 : 1;
                    var next = current - 1;
                    fetch('/api/cart/update', { method:'POST', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify({ productId: pid, variantId: vid || null, quantity: next }) })
                        .then(function(r){ return r.ok ? r.json() : Promise.reject(); })
                        .then(function(resp){ var items = normalizeServerCart(resp.cart || resp); renderCartItems(items); })
                        .catch(function(){});
                } else {
                    var items = getCart();
                    var idx = items.findIndex(function(ci){ return String(ci.id) === String(pid) && String(ci.variantId||'') === String(vid||''); });
                    if (idx >= 0) {
                        items[idx].quantity = Math.max(0, parseInt(items[idx].quantity||1,10) - 1);
                        if (items[idx].quantity <= 0) { items.splice(idx,1); }
                        saveCart(items);
                        renderCartItems(items);
                    }
                }
                return;
            }
            // Quantity increment
            var plus = target.closest ? target.closest('.cart-qty-plus') : (target.classList.contains('cart-qty-plus') ? target : null);
            if (plus) {
                var pid2 = plus.getAttribute('data-product-id');
                var vid2 = plus.getAttribute('data-variant-id') || '';
                if (isAuth()) {
                    var qtyEl2 = cartList.querySelector('.cart-qty-value[data-product-id="' + pid2 + '"][data-variant-id="' + vid2 + '"]');
                    var current2 = qtyEl2 ? parseInt(qtyEl2.textContent) || 1 : 1;
                    var next2 = current2 + 1;
                    fetch('/api/cart/update', { method:'POST', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify({ productId: pid2, variantId: vid2 || null, quantity: next2 }) })
                        .then(function(r){ return r.ok ? r.json() : Promise.reject(); })
                        .then(function(resp){ var items2 = normalizeServerCart(resp.cart || resp); renderCartItems(items2); })
                        .catch(function(){});
                } else {
                    var items2 = getCart();
                    var idx2 = items2.findIndex(function(ci){ return String(ci.id) === String(pid2) && String(ci.variantId||'') === String(vid2||''); });
                    if (idx2 >= 0) {
                        items2[idx2].quantity = parseInt(items2[idx2].quantity||1,10) + 1;
                        saveCart(items2);
                        renderCartItems(items2);
                    }
                }
                return;
            }
            var btn = target.classList.contains('cart-remove-btn') ? target : (target.closest ? target.closest('.cart-remove-btn') : null);
            if (!btn) return;
            var pid = btn.getAttribute('data-product-id');
            var vid = btn.getAttribute('data-variant-id') || '';
            if (isAuth()) {
                fetch('/api/cart/remove', { method:'POST', headers:{ 'Content-Type': 'application/json' }, body: JSON.stringify({ productId: pid, variantId: vid }) })
                    .then(function(r){ return r.ok ? r.json() : Promise.reject(); })
                    .then(function(resp){ var items = normalizeServerCart(resp.cart || resp); renderCartItems(items); })
                    .catch(function(){ /* noop */ });
                return;
            }
            var updated = removeItemFromCart(pid, vid);
            renderCartItems(updated);
        });
    }

    // Main add-to-cart handler (listens to events from wishlist or product pages)
    document.addEventListener('addToCart', function(e){
        try {
            var d = e && e.detail ? e.detail : {};
            if (isAuth()) {
                fetch('/api/cart/add', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ productId: d.productId, variantId: d.variantId || null, quantity: d.quantity || 1 }) })
                    .then(function(r){ return r.ok ? r.json() : Promise.reject(); })
                    .then(function(resp){ var items = normalizeServerCart(resp.cart || resp); renderCartItems(items); })
                    .catch(function(){ /* noop */ });
                // Also remove the specific item from the server-side wishlist and refresh wishlist panel
                try {
                    var pid = d && d.productId ? String(d.productId) : null;
                    var vid = (d && (d.variantId !== undefined && d.variantId !== null)) ? d.variantId : null;
                    if (pid) {
                        // Use API toggle to remove just this product/variant from wishlist
                        fetch('/api/wishlist/toggle/' + encodeURIComponent(pid), {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ variantId: vid })
                        })
                        .then(function(){
                            // Refresh wishlist panel from server to avoid showing it empty incorrectly
                            return fetch('/wishlist', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                        })
                        .then(function(r){ return r && r.ok ? r.json() : []; })
                        .then(function(list){
                            // Update wishlist count badge
                            try {
                                document.querySelectorAll('.icon-header-noti').forEach(function(icon) {
                                    if (icon.querySelector('.zmdi-favorite-outline')) {
                                        icon.setAttribute('data-notify', String(Array.isArray(list) ? list.length : 0));
                                    }
                                });
                            } catch(e) { /* noop */ }
                            if (wishlistPanel && wishlistPanel.classList.contains('show-header-cart')) {
                                renderWishlistItems(Array.isArray(list) ? list : []);
                            }
                            // Broadcast update
                            try { document.dispatchEvent(new CustomEvent('wishlistUpdated', { detail: { items: Array.isArray(list) ? list : [] } })); } catch(e) {}
                        })
                        .catch(function(){ /* noop */ });
                    }
                } catch(err) { /* noop */ }
            } else {
                // Guest: resolve discounted price from quick-view API before adding
                var pid = d.productId; var vid = d.variantId || '';
                var url = '/api/products/' + encodeURIComponent(pid) + '/quick-view';
                fetch(url).then(function(r){ return r.ok ? r.json() : null; }).then(function(data){
                    var price = d.productPrice;
                    var image = d.productImage;
                    var variantName = d.variantName || '';
                    if (data) {
                        if (vid) {
                            var v = (data.productVariants || []).find(function(vv){ return String(vv.id) === String(vid); });
                            if (v) {
                                if (v.offer && v.offer.has_offer) { price = v.offer.discounted_price; }
                                else if (typeof v.price !== 'undefined') { price = v.price; }
                                if (v.productVariantImages && v.productVariantImages.length > 0) {
                                    image = v.productVariantImages[0].imageUrl || image;
                                }
                                if (v.color && v.color.name) { variantName = v.color.name; }
                            }
                        } else {
                            if (data.offer && data.offer.has_offer) { price = data.offer.discounted_price; }
                            else if (typeof data.price !== 'undefined') { price = data.price; }
                            if (data.overviewImage) { image = data.overviewImage; }
                        }
                    }
                    var product = {
                        id: pid,
                        variantId: vid,
                        name: d.productName,
                        variantName: variantName,
                        price: price,
                        image: image,
                        quantity: d.quantity || 1
                    };
                    var updated = addItemToCart(product);
                    renderCartItems(updated);
                }).catch(function(){
                    // Fallback to provided price if quick-view fails
                    var product = {
                        id: d.productId,
                        variantId: d.variantId || '',
                        name: d.productName,
                        variantName: d.variantName || '',
                        price: d.productPrice,
                        image: d.productImage,
                        quantity: d.quantity || 1
                    };
                    var updated = addItemToCart(product);
                    renderCartItems(updated);
                });
            }
            // Remove from guest wishlist only if user is NOT authenticated
            if (!isAuth()) {
                try {
                    var wishlist = JSON.parse(localStorage.getItem('wishlist') || '[]');
                    var remPid = d && d.productId ? String(d.productId) : null;
                    var remVid = d && (d.variantId !== undefined && d.variantId !== null) ? String(d.variantId) : '';
                    if (remPid !== null) {
                        wishlist = wishlist.filter(function(it){ return !(String(it.id) === remPid && String(it.variantId || '') === remVid); });
                    }
                    localStorage.setItem('wishlist', JSON.stringify(wishlist));
                    // Update wishlist UI count
                    document.querySelectorAll('.icon-header-noti').forEach(function(icon) {
                        if (icon.querySelector('.zmdi-favorite-outline')) {
                            icon.setAttribute('data-notify', String(wishlist.length));
                        }
                    });
                    // If the wishlist panel is open, re-render its list
                    if (wishlistPanel && wishlistPanel.classList.contains('show-header-cart')) {
                        renderWishlistItems(wishlist);
                    }
                    // Broadcast update for other components
                    document.dispatchEvent(new CustomEvent('wishlistUpdated', { detail: { items: wishlist } }));
                } catch(err) { /* noop */ }
            }
        } catch(err) { /* noop */ }
    });

    // Merge guest cart into server cart on login
    function mergeGuestCartIfNeeded(){
        try {
            if (!isAuth()) return;
            if (sessionStorage.getItem('cart_merged') === '1') return;
            var items = JSON.parse(localStorage.getItem('cart') || '[]');
            if (!Array.isArray(items) || items.length === 0) return;
            var payload = { items: items.map(function(it){ return { productId: it.id, variantId: it.variantId || null, quantity: parseInt(it.quantity || 1, 10) }; }) };
            fetch('/api/cart/merge', { method:'POST', headers:{ 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
                .then(function(r){ return r.ok ? r.json() : Promise.reject(); })
                .then(function(){ localStorage.removeItem('cart'); sessionStorage.setItem('cart_merged','1'); renderCartFromSource(); })
                .catch(function(){});
        } catch(e) { /* noop */ }
    }

    // Initialize badges/lists on load
    (function initCartUI(){
        try {
            if (isAuth()) {
                mergeGuestCartIfNeeded();
                // Load from server to set badge
                fetch('/api/cart')
                    .then(function(r){ return r.ok ? r.json() : Promise.reject(); })
                    .then(function(cart){ var items = normalizeServerCart(cart); updateCartBadge(items.reduce(function(sum, it){ return sum + (parseInt(it.quantity || 1, 10)); }, 0)); })
                    .catch(function(){ updateCartBadge(0); });
            } else {
                var items = getCart();
                updateCartBadge(items.reduce(function(sum, it){ return sum + (parseInt(it.quantity || 1, 10)); }, 0));
            }
            // Do not render list immediately to avoid layout cost; it will render when opened
        } catch(e) { /* noop */ }
    })();
});
