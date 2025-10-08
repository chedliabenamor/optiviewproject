(function ($) {
    "use strict";

    /*[ Load page ]
    ===========================================================*/
    $(".animsition").animsition({
        inClass: 'fade-in',
        outClass: 'fade-out',
        inDuration: 1500,
        outDuration: 800,
        linkElement: '.animsition-link',
        loading: true,
        loadingParentElement: 'html',
        loadingClass: 'animsition-loading-1',
        loadingInner: '<div class="loader05"></div>',
        timeout: false,
        timeoutCountdown: 5000,
        onLoadEvent: true,
        browser: ['animation-duration', '-webkit-animation-duration'],
        overlay: false,
        overlayClass: 'animsition-overlay-slide',
        overlayParentElement: 'html',
        transition: function(url) { window.location.href = url; }
    });
    
    /*[ Back to top ]
    ===========================================================*/
    var windowH = $(window).height() / 2;

    $(window).on('scroll', function() {
        if ($(this).scrollTop() > windowH) {
            $("#myBtn").css('display', 'flex');
        } else {
            $("#myBtn").css('display', 'none');
        }
    });

    $('#myBtn').on("click", function() {
        $('html, body').animate({scrollTop: 0}, 300);
    });

    /*==================================================================
    [ Fixed Header ]*/
    var headerDesktop = $('.container-menu-desktop');
    var wrapMenu = $('.wrap-menu-desktop');
    var posWrapHeader = 0;

    if ($('.top-bar').length > 0) {
        posWrapHeader = $('.top-bar').height();
    }

    if ($(window).scrollTop() > posWrapHeader) {
        $(headerDesktop).addClass('fix-menu-desktop');
        $(wrapMenu).css('top', 0); 
    } else {
        $(headerDesktop).removeClass('fix-menu-desktop');
        $(wrapMenu).css('top', posWrapHeader - $(this).scrollTop()); 
    }

    $(window).on('scroll', function() {
        if ($(this).scrollTop() > posWrapHeader) {
            $(headerDesktop).addClass('fix-menu-desktop');
            $(wrapMenu).css('top', 0); 
        } else {
            $(headerDesktop).removeClass('fix-menu-desktop');
            $(wrapMenu).css('top', posWrapHeader - $(this).scrollTop()); 
        } 
    });

    /*==================================================================
    [ Menu mobile ]*/
    $('.btn-show-menu-mobile').on('click', function() {
        $(this).toggleClass('is-active');
        $('.menu-mobile').slideToggle();
    });

    var arrowMainMenu = $('.arrow-main-menu-m');

    for (var i = 0; i < arrowMainMenu.length; i++) {
        $(arrowMainMenu[i]).on('click', function() {
            $(this).parent().find('.sub-menu-m').slideToggle();
            $(this).toggleClass('turn-arrow-main-menu-m');
        });
    }

    $(window).resize(function() {
        if ($(window).width() >= 992) {
            if ($('.menu-mobile').css('display') == 'block') {
                $('.menu-mobile').css('display', 'none');
                $('.btn-show-menu-mobile').toggleClass('is-active');
            }

            $('.sub-menu-m').each(function() {
                if ($(this).css('display') == 'block') {
                    $(this).css('display', 'none');
                    $(arrowMainMenu).removeClass('turn-arrow-main-menu-m');
                }
            });
        }
    });

    /*==================================================================
    [ Show / hide modal search ]*/
    $('.js-show-modal-search').on('click', function() {
        $('.modal-search-header').addClass('show-modal-search');
        $(this).css('opacity', '0');
    });

    $('.js-hide-modal-search').on('click', function() {
        $('.modal-search-header').removeClass('show-modal-search');
        $('.js-show-modal-search').css('opacity', '1');
    });

    $('.container-search-header').on('click', function(e) {
        e.stopPropagation();
    });

    /*==================================================================
    [ Isotope ]*/
    var $topeContainer = $('.isotope-grid');
    var $filter = $('.filter-tope-group');

    // filter items on button click
    $filter.each(function() {
        $filter.on('click', 'button', function() {
            var filterValue = $(this).attr('data-filter');
            $topeContainer.isotope({filter: filterValue});
        });
    });

    // init Isotope
    $(window).on('load', function() {
        var $grid = $topeContainer.each(function() {
            $(this).isotope({
                itemSelector: '.isotope-item',
                layoutMode: 'fitRows',
                percentPosition: true,
                animationEngine: 'best-available',
                masonry: {
                    columnWidth: '.isotope-item'
                }
            });
        });
    });

    var isotopeButton = $('.filter-tope-group button');

    $(isotopeButton).each(function() {
        $(this).on('click', function() {
            for (var i = 0; i < isotopeButton.length; i++) {
                $(isotopeButton[i]).removeClass('how-active1');
            }
            $(this).addClass('how-active1');
        });
    });

    /*==================================================================
    [ Filter / Search product ]*/
    $('.js-show-filter').on('click', function() {
        $(this).toggleClass('show-filter');
        $('.panel-filter').slideToggle(400);

        if ($('.js-show-search').hasClass('show-search')) {
            $('.js-show-search').removeClass('show-search');
            $('.panel-search').slideUp(400);
        }    
    });

    $('.js-show-search').on('click', function() {
        $(this).toggleClass('show-search');
        $('.panel-search').slideToggle(400);

        if ($('.js-show-filter').hasClass('show-filter')) {
            $('.js-show-filter').removeClass('show-filter');
            $('.panel-filter').slideUp(400);
        }    
    });

    /*==================================================================
    [ Cart ]*/
    $('.js-show-cart').on('click', function() {
        $('.js-panel-cart').addClass('show-header-cart');
    });

    $('.js-hide-cart').on('click', function() {
        $('.js-panel-cart').removeClass('show-header-cart');
    });
    
    $('.js-show-wishlist').on('click', function() {
        $('.js-panel-cart').addClass('show-header-cart');
    });

    $('.js-hide-wishlist').on('click', function() {
        $('.js-panel-cart').removeClass('show-header-cart');
    });
    
    /*==================================================================
    [ Cart ]*/
    $('.js-show-sidebar').on('click', function() {
        $('.js-sidebar').addClass('show-sidebar');
    });

    $('.js-hide-sidebar').on('click', function() {
        $('.js-sidebar').removeClass('show-sidebar');
    });

    /*==================================================================
    [ +/- num product ]*/
    $('.btn-num-product-down').on('click', function() {
        var numProduct = Number($(this).next().val());
        if (numProduct > 0) $(this).next().val(numProduct - 1);
    });

    $('.btn-num-product-up').on('click', function() {
        var numProduct = Number($(this).prev().val());
        $(this).prev().val(numProduct + 1);
    });

    /*==================================================================
    [ Rating ]*/
    $('.wrap-rating').each(function() {
        var item = $(this).find('.item-rating');
        var rated = -1;
        var input = $(this).find('input');
        $(input).val(0);

        $(item).on('mouseenter', function() {
            var index = item.index(this);
            var i = 0;
            for (i = 0; i <= index; i++) {
                $(item[i]).removeClass('zmdi-star-outline');
                $(item[i]).addClass('zmdi-star');
            }

            for (var j = i; j < item.length; j++) {
                $(item[j]).addClass('zmdi-star-outline');
                $(item[j]).removeClass('zmdi-star');
            }
        });

        $(item).on('click', function() {
            var index = item.index(this);
            rated = index;
            $(input).val(index + 1);
        });

        $(this).on('mouseleave', function() {
            var i = 0;
            for (i = 0; i <= rated; i++) {
                $(item[i]).removeClass('zmdi-star-outline');
                $(item[i]).addClass('zmdi-star');
            }

            for (var j = i; j < item.length; j++) {
                $(item[j]).addClass('zmdi-star-outline');
                $(item[j]).removeClass('zmdi-star');
            }
        });
    });
    
    /*==================================================================
    [ Show modal1 ]*/
    $('.js-show-modal1').on('click', function(e) {
        e.preventDefault();
        $('.js-modal1').addClass('show-modal1');
    });

    $('.js-hide-modal1').on('click', function() {
        $('.js-modal1').removeClass('show-modal1');
    });

    /*==================================================================
    [ Wishlist - Full Implementation ]*/
    function getWishlist() {
        if (window.isAuthenticated) {
            // For authenticated users, fetch from backend (AJAX)
            return $.ajax({
                url: '/wishlist', // adjust route if needed
                method: 'GET',
                dataType: 'json',
            });
        } else {
            // For guests, get from localStorage
            let wishlist = localStorage.getItem('wishlist');
            return $.Deferred().resolve(wishlist ? JSON.parse(wishlist) : []).promise();
        }
    }

    function saveWishlist(wishlist) {
        if (!window.isAuthenticated) {
            localStorage.setItem('wishlist', JSON.stringify(wishlist));
        }
    }

    function addToWishlist(product) {
        if (window.isAuthenticated) {
            // AJAX to backend
            return $.ajax({
                url: '/wishlist/add',
                method: 'POST',
                data: product,
                headers: {'X-Requested-With': 'XMLHttpRequest'},
            });
        } else {
            let wishlist = localStorage.getItem('wishlist');
            wishlist = wishlist ? JSON.parse(wishlist) : [];
            if (!wishlist.find(item => item.id == product.id)) {
                wishlist.push(product);
                localStorage.setItem('wishlist', JSON.stringify(wishlist));
            }
            return $.Deferred().resolve(wishlist).promise();
        }
    }

    function removeFromWishlist(productId) {
        if (window.isAuthenticated) {
            // AJAX to backend
            return $.ajax({
                url: '/wishlist/remove',
                method: 'POST',
                data: { id: productId },
                headers: {'X-Requested-With': 'XMLHttpRequest'},
            });
        } else {
            let wishlist = localStorage.getItem('wishlist');
            wishlist = wishlist ? JSON.parse(wishlist) : [];
            wishlist = wishlist.filter(item => item.id != productId);
            localStorage.setItem('wishlist', JSON.stringify(wishlist));
            return $.Deferred().resolve(wishlist).promise();
        }
    }

    function updateWishlistUI(wishlist) {
        // Update all product cards
        $('.js-addwish-b2').each(function() {
            var $btn = $(this);
            var pid = String($btn.data('product-id'));
            var vid = $btn.data('variant-id'); // optional; most cards won't have this
            var match = false;
            if (Array.isArray(wishlist)) {
                if (vid !== undefined && vid !== null && String(vid) !== '') {
                    // Variant-specific button: require exact product + variant match
                    match = wishlist.some(function(it){ return String(it.id) === pid && String(it.variantId || '') === String(vid); });
                } else {
                    // Base product card (no variant): highlight ONLY if the base product (no variant) is in wishlist
                    match = wishlist.some(function(it){ return String(it.id) === pid && String(it.variantId || '') === ''; });
                }
            }
            if (match) { $btn.addClass('js-addedwish-b2'); } else { $btn.removeClass('js-addedwish-b2'); }
        });
        // Update header count
        var count = wishlist.length;
        $('.icon-header-noti').each(function() {
            var $icon = $(this);
            if ($icon.find('.zmdi-favorite-outline').length > 0) {
                $icon.attr('data-notify', count);
            }
        });
    }

    // On page load, initialize wishlist
    getWishlist().then(function(wishlist) {
        updateWishlistUI(wishlist);
    });

    // Listen for global wishlist updates (e.g., sidebar removes) and refresh icons
    document.addEventListener('wishlistUpdated', function(e){
        try {
            var items = e && e.detail && Array.isArray(e.detail.items) ? e.detail.items : null;
            if (items) {
                updateWishlistUI(items);
            } else {
                getWishlist().then(function(wishlist){ updateWishlistUI(wishlist); });
            }
        } catch(err) { /* noop */ }
    });

    // Re-sync wishlist icons after Slick carousel renders or tab is switched
    if (typeof $ !== 'undefined') {
        // Slick carousel: update wishlist UI after rendering
        $('.slick2').on('init reInit afterChange', function(event, slick) {
            if (typeof updateWishlistUI === 'function') {
                getWishlist().then(function(wishlist) {
                    updateWishlistUI(wishlist);
                });
            }
        });
        // Bootstrap tabs: update wishlist UI after tab switch
        $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
            if (typeof updateWishlistUI === 'function') {
                getWishlist().then(function(wishlist) {
                    updateWishlistUI(wishlist);
                });
            }
        });
    }

    // Wishlist button click handler (add/remove)
    $(document).on('click', '.js-addwish-b2', function(e) {
        e.preventDefault();
        var $this = $(this);
        // Read attributes (set by Twig) without JS-escaped sequences
        var currency = $this.data('product-currency') || '€';
        var hasOffer = String($this.data('product-has-offer')) === '1';
        var price = parseFloat($this.data('product-price')); // discounted or normal
        var original = $this.data('product-original-price');
        var originalNum = original !== undefined && original !== '' ? parseFloat(original) : null;

        function fmt(n){
            try { var v = parseFloat(n); return isNaN(v) ? String(n) : v.toFixed(2); } catch(e){ return String(n); }
        }

        var product = {
            id: $this.data('product-id'),
            name: $this.data('product-name'),
            image: $this.data('product-image'),
            price: price, // numeric for operations
            originalPrice: originalNum,
            hasOffer: hasOffer,
            currency: currency,
            priceFormatted: currency + fmt(price),
            originalPriceFormatted: originalNum !== null ? (currency + fmt(originalNum)) : ''
        };
        var isAuthenticated = (typeof window.isAuthenticated !== 'undefined' && window.isAuthenticated === true) || 
                            (typeof window.isAuthenticated === 'string' && window.isAuthenticated === 'true');
        
        if ($this.hasClass('js-addedwish-b2')) {
            // Remove from wishlist
            removeFromWishlist(product.id).then(function(wishlist) {
                $this.removeClass('js-addedwish-b2');
                updateWishlistUI(wishlist);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'info',
                        title: 'Removed from Wishlist',
                        text: product.name + ' has been removed from your wishlist.',
                        showConfirmButton: false,
                        timer: 1200
                    });
                } else if (typeof swal !== 'undefined') {
                    swal(product.name || 'Product', "is removed from wishlist!", "info");
                } else {
                    alert(product.name + ' has been removed from your wishlist.');
                }
            });
        } else {
            // Add to wishlist
            addToWishlist(product).then(function(wishlist) {
                $this.addClass('js-addedwish-b2');
                updateWishlistUI(wishlist);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Added to Wishlist',
                        html: '<div style="font-weight:600">' + (product.name || 'Product') + '</div>' +
                              '<div style="margin-top:6px">' + (product.hasOffer && product.originalPrice ? 
                                    ('<span style="color:#e74c3c;font-weight:700">' + product.priceFormatted + '</span>' +
                                     '<span style="margin-left:8px;color:#777;text-decoration:line-through">' + product.originalPriceFormatted + '</span>') :
                                    ('<span>' + product.priceFormatted + '</span>')) +
                              '</div>' +
                              '<div style="margin-top:8px;color:#333">is added to wishlist!</div>',
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else if (typeof swal !== 'undefined') {
                    var safeName = product.name || 'Product';
                    swal(safeName + ' ' + (product.hasOffer && product.originalPrice ? ('- ' + product.priceFormatted + ' (was ' + product.originalPriceFormatted + ')') : ('- ' + product.priceFormatted)), "is added to wishlist!", "success");
                } else {
                    alert((product.name || 'Product') + ' has been added to your wishlist.');
                }
            });
        }
    });

    /*==================================================================
    [ Progressive product loading ]*/
    $(function() {
        var $showLessBtn = $('#show-less-btn');
        var $loadMoreBtn = $('#load-more-btn');
        
        if ($loadMoreBtn.length || $showLessBtn.length) {
            var productsPerPage = 4;
            var initialProducts = 8;
            var $productGrid = $('#product-grid');
            var $productItems = $productGrid.find('.product-item-wrapper');
            
            function updateProductVisibility(count) {
                $productItems.each(function(i) {
                    if (i < count) {
                        $(this).removeClass('d-none');
                    } else {
                        $(this).addClass('d-none');
                    }
                });
            }

            function shownCount() {
                return $productItems.not('.d-none').length;
            }
            
            function totalCount() {
                return $productItems.length;
            }

            $loadMoreBtn.on('click', function(e) {
                e.preventDefault();
                var currentlyShown = shownCount();
                var toShow = currentlyShown + productsPerPage;
                updateProductVisibility(toShow);
                if (toShow >= totalCount()) {
                    $loadMoreBtn.hide();
                    $showLessBtn.show();
                }
            });

            $showLessBtn.on('click', function(e) {
                e.preventDefault();
                updateProductVisibility(initialProducts);
                $showLessBtn.hide();
                $loadMoreBtn.show();
            });

            // On page load, set initial state
            updateProductVisibility(initialProducts);
            if (totalCount() > initialProducts) {
                $loadMoreBtn.show();
                $showLessBtn.hide();
            } else {
                $loadMoreBtn.hide();
                $showLessBtn.hide();
            }

            // When filters are changed, reset to first 8 visible
            $productGrid.on('arrangeComplete', function(event, filteredItems) {
                // Hide all but first 8 filtered
                var $filtered = $productItems.filter(function() { 
                    return $(this).css('display') !== 'none'; 
                });
                
                $filtered.each(function(i) {
                    if (i < initialProducts) {
                        $(this).removeClass('d-none');
                    } else {
                        $(this).addClass('d-none');
                    }
                });
                
                if ($filtered.length > initialProducts) {
                    $loadMoreBtn.show();
                    $showLessBtn.hide();
                } else {
                    $loadMoreBtn.hide();
                    $showLessBtn.hide();
                }
            });
        }
    });

    // ====================
    // Currency -> Reformat product card prices
    // ====================
    function reformatProductCardPrices(){
        try {
            var fmt = (window.Currency && typeof window.Currency.format === 'function')
                ? window.Currency.format
                : function(n){ var v = parseFloat(n); if (isNaN(v)) return '€0.00'; return '€' + v.toFixed(2); };
            // Visible price on cards
            document.querySelectorAll('.js-card-price').forEach(function(el){
                var raw = el.getAttribute('data-price-eur') || (el.dataset ? el.dataset.priceEur : null);
                var eur = raw != null && raw !== '' ? parseFloat(raw) : NaN;
                if (!isNaN(eur)) {
                    el.textContent = fmt(eur);
                }
            });
            // Original price (strikethrough)
            document.querySelectorAll('.js-card-original').forEach(function(el){
                var raw = el.getAttribute('data-original-eur') || (el.dataset ? el.dataset.originalEur : null);
                var eur = raw != null && raw !== '' ? parseFloat(raw) : NaN;
                if (!isNaN(eur)) {
                    el.textContent = fmt(eur);
                }
            });
        } catch(e) { /* noop */ }
    }
    // Re-run when currency changes
    document.addEventListener('currencyChanged', reformatProductCardPrices);
    // Initial sync on page load (in case stored currency is USD)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', reformatProductCardPrices);
    } else {
        reformatProductCardPrices();
    }
})(jQuery);