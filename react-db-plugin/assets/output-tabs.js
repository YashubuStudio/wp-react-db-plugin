(function () {
  function toArray(list) {
    return Array.prototype.slice.call(list || []);
  }

  function applyFilters(container, filters) {
    var items = toArray(container.querySelectorAll('.reactdb-item'));
    items.forEach(function (item) {
      var visible = true;
      Object.keys(filters).forEach(function (key) {
        var value = filters[key];
        if (!value) {
          return;
        }
        var attr = item.getAttribute('data-' + key) || '';
        if (attr !== value) {
          visible = false;
        }
      });
      item.style.display = visible ? '' : 'none';
    });
  }

  function setupContainer(container) {
    if (!container || container.nodeType !== 1) {
      return;
    }
    if (container.dataset.reactdbTabsInit === '1') {
      return;
    }
    container.dataset.reactdbTabsInit = '1';
    var filters = {};
    var groups = toArray(container.querySelectorAll('.reactdb-tab-group[data-filter]'));

    groups.forEach(function (group) {
      var key = group.getAttribute('data-filter');
      if (!key) {
        return;
      }
      filters[key] = '';
      var buttons = toArray(group.querySelectorAll('[data-value]'));
      buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var value = btn.getAttribute('data-value') || '';
          filters[key] = value;
          buttons.forEach(function (b) {
            if (b === btn) {
              b.classList.add('is-active');
            } else {
              b.classList.remove('is-active');
            }
          });
          applyFilters(container, filters);
        });
      });
      var defaultBtn = group.querySelector('[data-default="1"]');
      if (defaultBtn) {
        defaultBtn.click();
      }
    });

    applyFilters(container, filters);
  }

  function initAll() {
    toArray(document.querySelectorAll('[data-reactdb-tabbed-output="1"]')).forEach(setupContainer);
  }

  function observeNew() {
    if (typeof MutationObserver === 'undefined') {
      return null;
    }
    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        toArray(mutation.addedNodes).forEach(function (node) {
          if (!(node instanceof HTMLElement)) {
            return;
          }
          if (typeof node.matches === 'function' && node.matches('[data-reactdb-tabbed-output="1"]')) {
            setupContainer(node);
          }
          if (typeof node.querySelectorAll === 'function') {
            toArray(node.querySelectorAll('[data-reactdb-tabbed-output="1"]')).forEach(setupContainer);
          }
        });
      });
    });
    if (document.body) {
      observer.observe(document.body, { childList: true, subtree: true });
    }
    return observer;
  }

  var api = window.ReactDBOutputTabs || {};
  api.init = initAll;
  api.setup = setupContainer;
  api.observe = function () {
    if (!api.observer && document.body) {
      api.observer = observeNew();
    }
    return api.observer || null;
  };
  window.ReactDBOutputTabs = api;

  function start() {
    initAll();
    api.observer = observeNew();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
