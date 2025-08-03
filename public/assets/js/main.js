
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
        browser: [ 'animation-duration', '-webkit-animation-duration'],
        overlay : false,
        overlayClass : 'animsition-overlay-slide',
        overlayParentElement : 'html',
        transition: function(url){ window.location.href = url; }
    });
    
    /*[ Back to top ]
    ===========================================================*/
    var windowH = $(window).height()/2;

    $(window).on('scroll',function(){
        if ($(this).scrollTop() > windowH) {
            $("#myBtn").css('display','flex');
        } else {
            $("#myBtn").css('display','none');
        }
    });

    $('#myBtn').on("click", function(){
        $('html, body').animate({scrollTop: 0}, 300);
    });


    /*==================================================================
    [ Fixed Header ]*/
    var headerDesktop = $('.container-menu-desktop');
    var wrapMenu = $('.wrap-menu-desktop');

    if($('.top-bar').length > 0) {
        var posWrapHeader = $('.top-bar').height();
    }
    else {
        var posWrapHeader = 0;
    }
    

    if($(window).scrollTop() > posWrapHeader) {
        $(headerDesktop).addClass('fix-menu-desktop');
        $(wrapMenu).css('top',0); 
    }  
    else {
        $(headerDesktop).removeClass('fix-menu-desktop');
        $(wrapMenu).css('top',posWrapHeader - $(this).scrollTop()); 
    }

    $(window).on('scroll',function(){
        if($(this).scrollTop() > posWrapHeader) {
            $(headerDesktop).addClass('fix-menu-desktop');
            $(wrapMenu).css('top',0); 
        }  
        else {
            $(headerDesktop).removeClass('fix-menu-desktop');
            $(wrapMenu).css('top',posWrapHeader - $(this).scrollTop()); 
        } 
    });


    /*==================================================================
    [ Menu mobile ]*/
    $('.btn-show-menu-mobile').on('click', function(){
        $(this).toggleClass('is-active');
        $('.menu-mobile').slideToggle();
    });

    var arrowMainMenu = $('.arrow-main-menu-m');

    for(var i=0; i<arrowMainMenu.length; i++){
        $(arrowMainMenu[i]).on('click', function(){
            $(this).parent().find('.sub-menu-m').slideToggle();
            $(this).toggleClass('turn-arrow-main-menu-m');
        })
    }

    $(window).resize(function(){
        if($(window).width() >= 992){
            if($('.menu-mobile').css('display') == 'block') {
                $('.menu-mobile').css('display','none');
                $('.btn-show-menu-mobile').toggleClass('is-active');
            }

            $('.sub-menu-m').each(function(){
                if($(this).css('display') == 'block') { console.log('hello');
                    $(this).css('display','none');
                    $(arrowMainMenu).removeClass('turn-arrow-main-menu-m');
                }
            });
                
        }
    });


    /*==================================================================
    [ Show / hide modal search ]*/
    $('.js-show-modal-search').on('click', function(){
        $('.modal-search-header').addClass('show-modal-search');
        $(this).css('opacity','0');
    });

    $('.js-hide-modal-search').on('click', function(){
        $('.modal-search-header').removeClass('show-modal-search');
        $('.js-show-modal-search').css('opacity','1');
    });

    $('.container-search-header').on('click', function(e){
        e.stopPropagation();
    });


    /*==================================================================
    [ Isotope ]*/
    var $topeContainer = $('.isotope-grid');
    var $filter = $('.filter-tope-group');

    // filter items on button click
    $filter.each(function () {
        $filter.on('click', 'button', function () {
            var filterValue = $(this).attr('data-filter');
            $topeContainer.isotope({filter: filterValue});
        });
        
    });

    // init Isotope
    $(window).on('load', function () {
        var $grid = $topeContainer.each(function () {
            $(this).isotope({
                itemSelector: '.isotope-item',
                layoutMode: 'fitRows',
                percentPosition: true,
                animationEngine : 'best-available',
                masonry: {
                    columnWidth: '.isotope-item'
                }
            });
        });
    });

    var isotopeButton = $('.filter-tope-group button');

    $(isotopeButton).each(function(){
        $(this).on('click', function(){
            for(var i=0; i<isotopeButton.length; i++) {
                $(isotopeButton[i]).removeClass('how-active1');
            }

            $(this).addClass('how-active1');
        });
    });

    /*==================================================================
    [ Filter / Search product ]*/
    $('.js-show-filter').on('click',function(){
        $(this).toggleClass('show-filter');
        $('.panel-filter').slideToggle(400);

        if($('.js-show-search').hasClass('show-search')) {
            $('.js-show-search').removeClass('show-search');
            $('.panel-search').slideUp(400);
        }    
    });

    $('.js-show-search').on('click',function(){
        $(this).toggleClass('show-search');
        $('.panel-search').slideToggle(400);

        if($('.js-show-filter').hasClass('show-filter')) {
            $('.js-show-filter').removeClass('show-filter');
            $('.panel-filter').slideUp(400);
        }    
    });




    /*==================================================================
    [ Cart ]*/
    $('.js-show-cart').on('click',function(){
        $('.js-panel-cart').addClass('show-header-cart');
    });

    $('.js-hide-cart').on('click',function(){
        $('.js-panel-cart').removeClass('show-header-cart');
    });

    /*==================================================================
    [ Cart ]*/
    $('.js-show-sidebar').on('click',function(){
        $('.js-sidebar').addClass('show-sidebar');
    });

    $('.js-hide-sidebar').on('click',function(){
        $('.js-sidebar').removeClass('show-sidebar');
    });

    /*==================================================================
    [ +/- num product ]*/
    $('.btn-num-product-down').on('click', function(){
        var numProduct = Number($(this).next().val());
        if(numProduct > 0) $(this).next().val(numProduct - 1);
    });

    $('.btn-num-product-up').on('click', function(){
        var numProduct = Number($(this).prev().val());
        $(this).prev().val(numProduct + 1);
    });

    /*==================================================================
    [ Rating ]*/
    $('.wrap-rating').each(function(){
        var item = $(this).find('.item-rating');
        var rated = -1;
        var input = $(this).find('input');
        $(input).val(0);

        $(item).on('mouseenter', function(){
            var index = item.index(this);
            var i = 0;
            for(i=0; i<=index; i++) {
                $(item[i]).removeClass('zmdi-star-outline');
                $(item[i]).addClass('zmdi-star');
            }

            for(var j=i; j<item.length; j++) {
                $(item[j]).addClass('zmdi-star-outline');
                $(item[j]).removeClass('zmdi-star');
            }
        });

        $(item).on('click', function(){
            var index = item.index(this);
            rated = index;
            $(input).val(index+1);
        });

        $(this).on('mouseleave', function(){
            var i = 0;
            for(i=0; i<=rated; i++) {
                $(item[i]).removeClass('zmdi-star-outline');
                $(item[i]).addClass('zmdi-star');
            }

            for(var j=i; j<item.length; j++) {
                $(item[j]).addClass('zmdi-star-outline');
                $(item[j]).removeClass('zmdi-star');
            }
        });
    });
    
    /*==================================================================
    [ Show modal1 ]*/
    $('.js-show-modal1').on('click',function(e){
        e.preventDefault();
        $('.js-modal1').addClass('show-modal1');
    });

    $('.js-hide-modal1').on('click',function(){
        $('.js-modal1').removeClass('show-modal1');
    });

    /*==================================================================
    [ Wishlist - Full Implementation ]*/
    // Set this in your Twig template: <script>window.isAuthenticated = {{ app.user ? 'true' : 'false' }};</script>

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
            var pid = $btn.data('product-id');
            if (wishlist.find(item => item.id == pid)) {
                $btn.addClass('js-addedwish-b2');
            } else {
                $btn.removeClass('js-addedwish-b2');
            }
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

    // Wishlist button click handler (add/remove)
    $(document).on('click', '.js-addwish-b2', function(e){
        e.preventDefault();
        var $this = $(this);
        var product = {
            id: $this.data('product-id'),
            name: $this.data('product-name'),
            image: $this.data('product-image'),
            price: $this.data('product-price')
        };
        var isAdded = $this.hasClass('js-addedwish-b2');
        if (!isAdded) {
            addToWishlist(product).then(function(wishlist) {
                updateWishlistUI(wishlist);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Added to Wishlist!',
                        text: product.name + ' has been added to your wishlist.',
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else if (typeof swal !== 'undefined') {
                    swal(product.name, "is added to wishlist!", "success");
                } else {
                    alert(product.name + ' has been added to your wishlist.');
                }
            });
        } else {
            removeFromWishlist(product.id).then(function(wishlist) {
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
                    swal(product.name, "is removed from wishlist!", "info");
                } else {
                    alert(product.name + ' has been removed from your wishlist.');
                }
            });
        }
    });


    $(document).ready(function() {
        /* Progressive product loading */
        console.log("Progressive product loading");
        var productsPerPage = 4;
        var initialProducts = 8;
        var $productGrid = $('#product-grid');
        var $productItems = $productGrid.find('.product-item-wrapper');
        var $loadMoreBtn = $('#load-more-btn');
        var $showLessBtn = $('#show-less-btn');
        var isotopeInstance = $productGrid.data('isotope');

        function updateProductVisibility(count) {
            $productItems.each(function(i) {
                if (i < count) {
                    $(this).removeClass('d-none');
                } else {
                    $(this).addClass('d-none');
                }
            });
            if (isotopeInstance) {
                $productGrid.isotope('layout');
            }
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
            var $filtered = $productItems.filter(function(){ return $(this).css('display') !== 'none'; });
            $filtered.each(function(i){
                if(i < initialProducts) {
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
    });

})(jQuery);