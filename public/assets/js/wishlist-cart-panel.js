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
                // For authenticated users, fetch from server
                fetch('/wishlist', {
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                .then(function(response) {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(function(items) {
                    renderWishlistItems(items);
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
                if (!productId) return;
                
                var removePromise;
                
                if (isGuest()) {
                    // Handle guest removal
                    try {
                        var wishlist = JSON.parse(localStorage.getItem('wishlist') || '[]');
                        wishlist = wishlist.filter(function(item) { 
                            return item.id != productId; 
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

    function renderWishlistEmpty() {
        var list = document.getElementById('wishlist-items-list');
        list.innerHTML = '<li class="header-cart-item flex-w flex-t m-b-12"><span class="stext-110 cl2">Your wishlist is empty.</span></li>';
    }

    function renderWishlistItems(items) {
        var list = document.getElementById('wishlist-items-list');
        list.innerHTML = '';
        if (!items || items.length === 0) {
            list.innerHTML = '<li class="header-cart-item flex-w flex-t m-b-12"><span class="stext-110 cl2">Your wishlist is empty.</span></li>';
            return;
        }
        items.forEach(function(item) {
            var img = item.image ? `<img src="${item.image}" alt="${item.name}" class="header-cart-item-img">` : '';
            var removeBtn = `
                <button class="wishlist-remove-btn" data-product-id="${item.id}" title="Remove from wishlist">
                    <i class="zmdi zmdi-close"></i>
                </button>`;
            var addToCartBtn = `
                <button class="flex-c-m stext-101 cl0 size-101 bg1 bor1 hov-btn1 p-lr-15 trans-04 js-addcart-detail add-to-cart-btn" 
                        data-product-id="${item.id}" 
                        data-product-name="${item.name}" 
                        data-product-price="${item.price}"
                        data-product-image="${item.image || ''}">
                    Add to Cart
                </button>`;
                
            var html = `
            <li class="header-cart-item flex-w flex-t m-b-12">
                <div class="header-cart-item-img">${img}${removeBtn}</div>
                <div class="header-cart-item-txt p-t-8">
                    <a href="/product/${item.id}" class="header-cart-item-name m-b-8 hov-cl1 trans-04">${item.name}</a>
                    <span class="header-cart-item-info m-b-8">${item.price}</span>
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
            
            // Create an event that the main cart handler will catch
            const addToCartEvent = new CustomEvent('addToCart', {
                detail: {
                    productId: productId,
                    productName: productName,
                    productPrice: productPrice,
                    productImage: productImage,
                    quantity: 1
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
});
