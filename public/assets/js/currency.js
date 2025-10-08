// currency.js - simple client-side currency switcher using Frankfurter API
(function(){
  var STORAGE_KEY_CUR = 'currency';
  var STORAGE_KEY_RATE = 'fx_EUR_USD';
  var STORAGE_KEY_RATE_TS = 'fx_EUR_USD_ts';
  var ONE_DAY = 24 * 60 * 60 * 1000;

  function read(key){ try { return localStorage.getItem(key); } catch(e){ return null; } }
  function write(key, val){ try { localStorage.setItem(key, val); } catch(e){} }

  function currentCurrency(){
    var cur = read(STORAGE_KEY_CUR);
    return (cur === 'USD' || cur === 'EUR') ? cur : 'EUR';
  }

  function setCurrency(cur){
    if (cur !== 'EUR' && cur !== 'USD') return;
    write(STORAGE_KEY_CUR, cur);
    api.current = cur;
    api.symbol = cur === 'USD' ? '$' : '€';
    document.documentElement.setAttribute('data-currency', api.current);
    document.dispatchEvent(new CustomEvent('currencyChanged', { detail: { currency: api.current, symbol: api.symbol, rate: api.rate } }));
  }

  function loadRateIfNeeded(){
    var ts = parseInt(read(STORAGE_KEY_RATE_TS) || '0', 10);
    var cached = read(STORAGE_KEY_RATE);
    var now = Date.now();
    if (cached && (now - ts) < ONE_DAY) {
      api.rate = parseFloat(cached) || 1.0;
      return Promise.resolve(api.rate);
    }
    // Helper to cache and return the rate
    function setAndCache(rate){
      var r = (typeof rate === 'number' && isFinite(rate)) ? rate : 1.0;
      api.rate = r;
      write(STORAGE_KEY_RATE, String(r));
      write(STORAGE_KEY_RATE_TS, String(Date.now()));
      return r;
    }
    // Primary: Frankfurter
    function fetchFrankfurter(){
      return fetch('https://api.frankfurter.app/latest?from=EUR&to=USD')
        .then(function(r){ return r.json(); })
        .then(function(data){
          var rate = data && data.rates && data.rates.USD ? parseFloat(data.rates.USD) : NaN;
          if (!rate || !isFinite(rate)) throw new Error('Invalid Frankfurter payload');
          return setAndCache(rate);
        });
    }
    // Fallback: exchangerate.host
    function fetchHost(){
      return fetch('https://api.exchangerate.host/latest?base=EUR&symbols=USD')
        .then(function(r){ return r.json(); })
        .then(function(data){
          var rate = data && data.rates && data.rates.USD ? parseFloat(data.rates.USD) : NaN;
          if (!rate || !isFinite(rate)) throw new Error('Invalid exchangerate.host payload');
          return setAndCache(rate);
        });
    }
    return fetchFrankfurter().catch(function(){ return fetchHost(); }).catch(function(){
      // Final fallback: keep previous or 1.0
      api.rate = api.rate || 1.0;
      return api.rate;
    });
  }

  function convertEUR(amountEUR){
    var num = parseFloat(amountEUR);
    if (isNaN(num)) return null;
    if (api.current === 'EUR') return num;
    return num * (api.rate || 1.0);
  }

  function format(amountEUR){
    var val = convertEUR(amountEUR);
    if (val === null) return '';
    return api.symbol + val.toFixed(2);
  }

  function formatPair(discountedEUR, originalEUR){
    var d = convertEUR(discountedEUR);
    var o = originalEUR != null ? convertEUR(originalEUR) : null;
    var out = '';
    if (d != null) out += '<span style="color:#e74c3c;font-weight:700">' + api.symbol + d.toFixed(2) + '</span>';
    if (o != null) out += '<span style="margin-left:8px;color:#777;text-decoration:line-through">' + api.symbol + o.toFixed(2) + '</span>';
    return out;
  }

  // Update simple text decorations like free shipping amounts declared in EUR
  function refreshDecorations(){
    try {
      var list = document.querySelectorAll('.js-free-ship-amt');
      list.forEach(function(el){
        var eur = parseFloat(el.getAttribute('data-amount-eur'));
        if (isNaN(eur)) return;
        var shown = api.current === 'EUR' ? eur : (eur * (api.rate || 1.0));
        el.textContent = api.symbol + shown.toFixed(2);
      });
    } catch(e) { /* noop */ }
  }

  // Wire currency <select> controls
  function wireSelectors(){
    try {
      var sels = document.querySelectorAll('.js-currency-select');
      if (!sels || !sels.length) return;
      // Keep all selects in sync
      function syncAll(){
        try { document.querySelectorAll('.js-currency-select').forEach(function(s){ s.value = api.current; }); } catch(e){}
      }
      sels.forEach(function(sel){
        // Initialize current value
        sel.value = api.current;
        // Rebind change handler safely
        if (sel._curHandler) { sel.removeEventListener('change', sel._curHandler); }
        sel._curHandler = function(){
          var val = sel.value;
          if (val !== 'EUR' && val !== 'USD') return;
          setCurrency(val);
          syncAll();
          // Ensure rate is present/fresh, then notify again with updated rate
          loadRateIfNeeded().then(function(){
            document.dispatchEvent(new CustomEvent('currencyChanged', { detail: { currency: api.current, symbol: api.symbol, rate: api.rate } }));
          });
        };
        sel.addEventListener('change', sel._curHandler);
      });
    } catch(e) { /* noop */ }
  }

  var api = {
    current: currentCurrency(),
    symbol: (currentCurrency() === 'USD' ? '$' : '€'),
    rate: 1.0,
    set: setCurrency,
    convert: convertEUR,
    format: format,
    formatPair: formatPair
  };

  window.Currency = api;
  document.documentElement.setAttribute('data-currency', api.current);
  // Prepare UI bindings
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wireSelectors);
  } else {
    wireSelectors();
  }
  // Update decorations whenever currency changes
  document.addEventListener('currencyChanged', refreshDecorations);

  // Load rate then announce availability
  loadRateIfNeeded().then(function(){
    document.dispatchEvent(new CustomEvent('currencyChanged', { detail: { currency: api.current, symbol: api.symbol, rate: api.rate } }));
    // Ensure decorations reflect first known rate
    refreshDecorations();
  });
})();
