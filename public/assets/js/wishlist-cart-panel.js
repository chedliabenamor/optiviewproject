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

    wishlistShowBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            wishlistPanel.classList.add('show-header-cart');
            // Fetch and render wishlist items
            fetch('/wishlist', {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(function(response) {
                    if (response.status === 401) {
                        // Not logged in, try localStorage
                        var localData = localStorage.getItem('wishlist');
                        if (localData) {
                            try {
                                var items = JSON.parse(localData);
                                renderWishlistItems(items);
                            } catch (e) {
                                renderWishlistEmpty();
                            }
                        } else {
                            renderWishlistEmpty();
                        }
                        return null;
                    }
                    return response.json();
                })
                .then(function(items) {
                    if (items) renderWishlistItems(items);
                });

        function renderWishlistEmpty() {
            var list = document.getElementById('wishlist-items-list');
            list.innerHTML = '<li class="header-cart-item flex-w flex-t m-b-12"><span class="stext-110 cl2">Your wishlist is empty.</span></li>';
        }
        });
    });

    function renderWishlistItems(items) {
        var list = document.getElementById('wishlist-items-list');
        list.innerHTML = '';
        if (!items || items.length === 0) {
            list.innerHTML = '<li class="header-cart-item flex-w flex-t m-b-12"><span class="stext-110 cl2">Your wishlist is empty.</span></li>';
            return;
        }
        items.forEach(function(item) {
            var img = item.image ? `<img src="${item.image}" alt="${item.name}" class="header-cart-item-img">` : '';
            var html = `<li class="header-cart-item flex-w flex-t m-b-12">\
                <div class="header-cart-item-img">${img}</div>\
                <div class="header-cart-item-txt p-t-8">\
                    <a href="/product/${item.id}" class="header-cart-item-name m-b-18 hov-cl1 trans-04">${item.name}</a>\
                    <span class="header-cart-item-info">${item.price}</span>\
                </div>\
            </li>`;
            list.innerHTML += html;
        });
    }
    wishlistHideBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            wishlistPanel.classList.remove('show-header-cart');
        });
    });
});
