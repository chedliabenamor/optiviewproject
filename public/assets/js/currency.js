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
    // Fetch from Frankfurter
    return fetch('https://api.frankfurter.app/latest?from=EUR&to=USD')
      .then(function(r){ return r.json(); })
      .then(function(data){
        var rate = data && data.rates && data.rates.USD ? parseFloat(data.rates.USD) : 1.0;
        api.rate = rate;
        write(STORAGE_KEY_RATE, String(rate));
        write(STORAGE_KEY_RATE_TS, String(Date.now()));
        return rate;
      })
      .catch(function(){ api.rate = api.rate || 1.0; return api.rate; });
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

  // Load rate then announce availability
  loadRateIfNeeded().then(function(){
    document.dispatchEvent(new CustomEvent('currencyChanged', { detail: { currency: api.current, symbol: api.symbol, rate: api.rate } }));
  });
})();
