(function () {
  var STORAGE_PREFIX = 'athkar_progress_v1_';

  function getKey(section) {
    return STORAGE_PREFIX + section;
  }

  function defaultState() {
    return {
      counts: {},
      updatedAt: null
    };
  }

  window.AthkarStorage = {
    load: function (section) {
      try {
        var raw = localStorage.getItem(getKey(section));
        if (!raw) return defaultState();
        var parsed = JSON.parse(raw);
        return {
          counts: parsed && parsed.counts ? parsed.counts : {},
          updatedAt: parsed && parsed.updatedAt ? parsed.updatedAt : null
        };
      } catch (error) {
        return defaultState();
      }
    },

    save: function (section, state) {
      var payload = {
        counts: state.counts || {},
        updatedAt: new Date().toISOString()
      };
      localStorage.setItem(getKey(section), JSON.stringify(payload));
    },

    reset: function (section) {
      localStorage.removeItem(getKey(section));
    }
  };
})();
