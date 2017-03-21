(function() {

if (!window.console) {
  (function() {
    var names = [ "log", "debug", "info", "warn", "error", "assert", "dir",
      "dirxml", "group", "groupEnd", "time", "timeEnd", "count", "trace",
      "profile", "profileEnd" ];

    window.console = {};
    for (var i = 0; i < names.length; i++) {
      window.console[names[i]] = function() {};
    }
  })();
}

if (typeof(Nudlle) == 'undefined') {
  console.log('Error: Nudlle object is not defined, aborting.');
  return;
}

Nudlle.INDEX_MODULE = '_module';
Nudlle.INDEX_OPERATION = '_operation';
Nudlle.INDEX_SAVE_ID = '_saverid';
Nudlle.INDEX_FILE = '_file';

Nudlle.G = function(id) {
  return document.getElementById(id);
};

$.fn.widgetFind = function(selector) {
  var list = $();
  var stack = $(this).get();

  while (stack.length > 0) {
    $(stack.shift()).children().each(function() {
      if ($(this).is('div.widget')) return;
      if ($(this).is(selector)) list = list.add(this);
      stack.push(this);
    });
  }

  return list;
};

(function() {
  var index_tasks = 'tasks';

  Nudlle.get_task = function(widget, action) {
    var task = Nudlle.data(index_tasks);
    if (typeof(task) != 'object') return {};
    task = task[widget];
    if (typeof(task) != 'object') return {};
    task = task[action];
    return typeof(task) == 'object' ? task : {};
  }
})();

$.ajaxSetup({
  url: 'index.php',
  cache: false,
  dataType: 'json',
  timeout: 8000,
  type: 'POST'
});

(function() {
  var defaults = {
    async: true,
    error_message: Nudlle.i18n('core.ajax_fail'),
    debug: false,
    done: function(data){},
    quiet: false,
    fail: function(data){},
    origin: document.documentElement,
    loader_switch: null,
    update: false
  };

  var
    index_status = '_status',
    index_errors = '_errors',
    index_noauth = '_noauth',
    index_ajax = '_ajax',
    index_id = '_rid';

  function ajax_start(conf) {
    if (conf.quiet) return;

    if (conf.loader_switch) {
      conf.loader_switch(true, conf.origin);
    } else if (conf.loader) {
      var req_count = $(conf.loader).data('request_count') || 0;
      if (req_count <= 0) {
        req_count = 0;
        conf.loader_timeout = setTimeout(function(){$(conf.loader).show(200)}, 400);
      }
      $(conf.loader).data('request_count') = req_count + 1;
    }
  }

  function ajax_stop(conf) {
    if (conf.quiet) return;

    if (conf.loader_switch) {
      conf.loader_switch(false, conf.origin);
    } else if (conf.loader) {
      var req_count = $(conf.loader).data('request_count') || 1;
      req_count--;
      if (req_count <= 0) {
        req_count = 0;
        clearTimeout(conf.loader_timeout);
        $(conf.loader).stop(true).hide(200);
      }
      $(conf.loader).data('request_count') = req_count;
    }
  }

  function ajax_repeat(conf) {
    // TODO
  }

  // In addition to the default attributes the conf object can contain:
  // data - information to send to the server
  // loader_switch - callback showing/hiding the loader element - function(type, origin)
  Nudlle.ajax = function(conf) {
    for (var key in defaults) {
      if (typeof(conf[key]) == 'undefined') {
        conf[key] = defaults[key];
      }
    }

    var loader = null;
    if (!conf.quiet && conf.loader_switch === null) {
      var origin = conf.origin;
      while (loader === null && origin !== null) {
        origin = $(origin).parent().closest('.widget');
        if (origin.length == 1) {
          loader = $(origin).widgetFind('.loader').get(0) || null;
        } else {
          origin = null;
        }
      }
      conf.loader = loader;
    }

    conf.data[index_id] = Nudlle.data(index_id);
    conf.data[index_ajax] = 1;

    ajax_start(conf);

    var jqXHR = $.ajax(conf);

    jqXHR.done(function(data, status) {
      //alert('Done: data = '+data+"\nstatus = "+status);
      ajax_stop(conf);
      if (conf.debug) {
        alert(Nudlle.i18n('core.status')+": "+status+"\n"+Nudlle.i18n('core.response')+":\n"+jqXHR.responseText);
      }
      if (status == 'success') {
        if (data == null) return;

        if (data[index_noauth]) {
          Nudlle.data(index_id, data[index_id]);
          ajax_repeat(conf);
			  } else {
			    if (data[index_status]) {
            if (typeof(data[index_id]) != 'undefined') {
              Nudlle.data(index_id, data[index_id]);
            }
            if (conf.update) {
              var widget = new Nudlle.widget(conf.origin);
              if (widget) widget.update();
            }
			      conf.done(data);
			    } else {
            conf.fail(data);
            if (!conf.quiet) {
    			    if (data[index_errors].length > 0) {
    			      alert(data[index_errors].join("\n\n"));
    			    } else {
    			      alert(conf.error_message);
    			    }
			      }
			    }
			  }
      } else {
        conf.fail(data);
        if (!setup.quiet) {
          alert(conf.error_message);
        }
      }
    });

    jqXHR.fail(function(req, status, error) {
      //alert('Fail: req = '+req+"\nstatus = "+status+"\nerror = "+error);
      ajax_stop(conf);
      if (conf.debug) {
        alert(Nudlle.i18n('core.status')+": "+status+"\n"+Nudlle.i18n('core.response')+":\n"+jqXHR.responseText+"\n"+error);
      }
      if (status != 'abort') {
        conf.fail(null);
        if (!conf.quiet) {
          alert(conf.error_message);
        }
      }
    });

    return jqXHR;
  };

})();

(function() {
  var
    active_updates = new Object(),
    active_refreshes = new Object(),
    init_hooks = new Array(),
    index_widget = '_widget',
    index_fragment = '_fragment';

  // selector can be anything acceptable as a parameter of $.is() function
  Nudlle.register_widget_init_hook = function(f, selector) {
    if (typeof(f) != 'function') return false;
    var hook = [ f ];
    if (typeof(selector) != 'undefined') hook.push(selector);
    init_hooks.push(hook);
    return true;
  };

  Nudlle.widget = function(id_elem) {
    var w, self = this;

    if (typeof(id_elem) == 'string') {
      w = $('#'+id_elem);
    } else if (typeof(id_elem) == 'object') {
      w = $(id_elem).closest('.widget, body');
    } else if (typeof(id_elem) == 'undefined' || id_elem === null) {
      w = $('body');
    } else {
      return null;
    }

    this.init = function(data) {
      if (w.is('.widget') && w.data('refresh')) {
        active_refreshes[w.attr('id')] = setInterval(
          function() { self.update(); },
          w.data('refresh') * 1000
        );
      }
      for (i in init_hooks) {
        if (typeof(init_hooks[i][1]) != 'undefined' && !w.is(init_hooks[i][1])) {
          continue;
        }
        init_hooks[i][0](w, data);
      }
    }

    this.get = function() { return w; };

    this.update = function(params, callback) {
      if (!w.is('.widget')) return false;
      if (typeof(params) == 'undefined') params = new Object();
      if (typeof(params) != 'object') return false;
      if (typeof(callback) != 'function') callback = null;

      if (typeof(active_updates[w.attr('id')]) != 'undefined') {
        active_updates[w.attr('id')].abort();
        delete active_updates[w.attr('id')];
      }
      if (typeof(active_refreshes[w.attr('id')]) != 'undefined') {
        clearInterval(active_refreshes[w.attr('id')]);
        delete active_refreshes[w.attr('id')];
      }

      params[index_widget] = w.attr('id');
      active_updates[w.attr('id')] = Nudlle.ajax({
        data: params,
        error_message: Nudlle.i18n('core.update_fail'),
        origin: w.children().get(0),
        done: function(data) {
          w.empty().append(data[index_fragment]);
          self.init(data);
          if (callback) callback(data);
        }
      });
    };

    this.clear = function() {
      w.empty();
      if (typeof(active_refreshes[w.attr('id')]) != 'undefined') {
        clearInterval(active_refreshes[w.attr('id')]);
        delete active_refreshes[w.attr('id')];
      }
    };

    return this;
  };

})();

$(document).ready(function() {
  var index_ping = '_ping';
  var ping_interval = 600000; // 10 minutes

  function ping() {
    var data = new Object();
    data[index_ping] = 1;

    Nudlle.ajax({
      data: data,
      quiet: true
    });
  }

  if (Nudlle.data(index_ping)) {
    // Kazdych 10 minut se posle ajaxovy ping
    setInterval(ping, ping_interval);
  }

  $('.widget, body').each(function() {
    var obj = new Nudlle.widget(this);
    obj.init();
  });
});

})();
