(function () {
  var MULTI_SEPARATOR = '|~|';

  function toArray(list) {
    return Array.prototype.slice.call(list || []);
  }

  function parseAttributeValues(attr) {
    if (!attr) {
      return [];
    }
    var values = attr.indexOf(MULTI_SEPARATOR) === -1 ? [attr] : attr.split(MULTI_SEPARATOR);
    return values
      .map(function (value) {
        return typeof value === 'string' ? value.trim() : '';
      })
      .filter(function (value) {
        return value !== '';
      });
  }

  function tokenizeSearch(query) {
    if (!query) {
      return [];
    }
    return query
      .toString()
      .toLowerCase()
      .split(/\s+/)
      .map(function (token) {
        return token.trim();
      })
      .filter(function (token) {
        return token.length > 0;
      });
  }

  function applyFilters(container, state) {
    var filters = state.filters || {};
    var searchTokens = state.searchTokens || [];
    var items = toArray(container.querySelectorAll('.reactdb-item'));
    items.forEach(function (item) {
      var visible = true;
      Object.keys(filters).forEach(function (key) {
        var value = filters[key];
        if (!value) {
          return;
        }
        var attr = item.getAttribute('data-filter-' + key) || '';
        var candidates = parseAttributeValues(attr);
        if (candidates.length === 0) {
          visible = false;
          return;
        }
        var matched = candidates.some(function (candidate) {
          return candidate === value;
        });
        if (!matched) {
          visible = false;
        }
      });
      if (visible && searchTokens.length > 0) {
        var indexAttr = item.getAttribute('data-search-index') || '';
        var target = indexAttr.toLowerCase();
        if (!target) {
          visible = false;
        } else {
          var hasAll = searchTokens.every(function (token) {
            return target.indexOf(token) !== -1;
          });
          if (!hasAll) {
            visible = false;
          }
        }
      }
      item.style.display = visible ? '' : 'none';
    });
  }

  function parseConfig(container) {
    if (!container || container.nodeType !== 1) {
      return {};
    }
    var attr = container.getAttribute('data-reactdb-config');
    if (!attr) {
      return {};
    }
    try {
      return JSON.parse(attr);
    } catch (error) {
      return {};
    }
  }

  function selectFilterValue(state, key, value, buttons) {
    var target = null;
    buttons.forEach(function (btn) {
      var btnValue = btn.getAttribute('data-value') || '';
      if (value !== '' && btnValue === value && !target) {
        target = btn;
      }
    });
    if (!target) {
      buttons.forEach(function (btn) {
        if (btn.getAttribute('data-default') === '1' && !target) {
          target = btn;
        }
      });
    }
    if (!target) {
      target = buttons.length > 0 ? buttons[0] : null;
    }
    buttons.forEach(function (btn) {
      if (btn === target) {
        btn.classList.add('is-active');
      } else {
        btn.classList.remove('is-active');
      }
    });
    var appliedValue = '';
    if (target) {
      appliedValue = target.getAttribute('data-value') || '';
    }
    state.filters[key] = appliedValue;
    return appliedValue;
  }

  function setupContainer(container) {
    if (!container || container.nodeType !== 1) {
      return;
    }
    if (container.dataset.reactdbTabsInit === '1') {
      return;
    }
    container.dataset.reactdbTabsInit = '1';
    var configData = parseConfig(container);
    var initialFilters = configData.initialFilters || {};
    var state = {
      filters: {},
      searchTokens: []
    };
    var groups = toArray(container.querySelectorAll('.reactdb-output-filterGroup[data-filter]'));

    groups.forEach(function (group) {
      var key = group.getAttribute('data-filter');
      if (!key) {
        return;
      }
      var buttons = toArray(group.querySelectorAll('[data-value]'));
      buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var value = btn.getAttribute('data-value') || '';
          selectFilterValue(state, key, value, buttons);
          applyFilters(container, state);
        });
      });
      var initialValue = Object.prototype.hasOwnProperty.call(initialFilters, key)
        ? initialFilters[key]
        : '';
      selectFilterValue(state, key, initialValue, buttons);
    });

    var searchInput = container.querySelector('.reactdb-output-searchInput');
    if (searchInput) {
      var handleSearch = function () {
        var query = searchInput.value || '';
        state.searchTokens = tokenizeSearch(query);
        applyFilters(container, state);
      };
      searchInput.addEventListener('input', handleSearch);
      handleSearch();
    } else {
      applyFilters(container, state);
    }
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
