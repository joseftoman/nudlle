(function() {
  var toggle_hooks = new Array();

  var loader_class = 'glyphicon-hourglass';

  function icon_loader_switch(start, origin) {
    var icon = $(origin).children('span.glyphicon');
    if (start) {
      $(origin)
        .prop('disabled', true)
        .data('disable_action', true)
        .data('title', $(origin).attr('title'))
        .removeAttr('title')
        .data('loader_timeout', setTimeout(function() { icon.addClass(loader_class) }, 400));
    } else {
      clearTimeout($(origin).data('loader_timeout'));
      $(origin)
        .attr('title', $(origin).data('title'))
        .removeData([ 'disable_action', 'title', 'loader_timeout' ])
        .prop('disabled', false);
      icon.removeClass(loader_class);
    }
  }

  Nudlle.register_pager_toggle_hook = function(f, selector) {
    if (typeof(f) != 'function') return false;
    var hook = [ f ];
    if (typeof(selector) != 'undefined') hook.push(selector);
    toggle_hooks.push(hook);
    return true;
  };

  Nudlle.register_widget_init_hook(function(widget) {
    var module = widget.attr('id').split('-')[1];

    widget.find('*[data-action]').each(function() {
      $(this).click(function() {
        if ($(this).data('disable_action')) return;

        var action = $(this).data('action');
        var params = new Object();
        params[Nudlle.INDEX_MODULE] = module;
        params[Nudlle.INDEX_OPERATION] = action;
        params.id = $(this).closest('tr[data-id]').data('id');

        var task = Nudlle.get_task(widget.attr('id'), action);
        var conf = {
          origin: this,
          loader_switch: icon_loader_switch,
        };

        if (action == 'move') {
          params.amount = $(this).data('amount');
          conf.error_message = 'Změna pořadí se nezdařila.';
        }
        if (action == 'toggle') {
          params.column = $(this).data('column');
          conf.error_message = 'Změna příznaku se nezdařila.';
          task = task[params.column];
          conf.done = function(data) {
            var value = data[params.column] ? 1 : 0;
            $(conf.origin)
              .attr('title', task.titles[value])
              .children('span.glyphicon')
                .removeClass(task.icons.join(' '))
                .addClass(task.icons[value]);

            for (i in toggle_hooks) {
              if (typeof(toggle_hooks[i][1]) != 'undefined' && !widget.is(toggle_hooks[i][1])) {
                continue;
              }
              toggle_hooks[i][0](conf.origin, value);
            }
          };
        }
        if (action == 'delete') {
          conf.error_message = 'Smazání záznamu se nezdařilo.';
        }

        conf.data = params;
        if (task.update) conf.update = true;

        Nudlle.ajax(conf);
      });
    });

    widget.find('a[data-update]').each(function() {
      var raw_params = this.href.split('?')[1].split('&');
      $(this).removeAttr('href data-update').addClass('pager-clickable');
      var params = new Object();

      for (var i in raw_params) {
        var pair = raw_params[i].split('=');
        params[pair[0]] = pair[1];
      }

      $(this).click(function() {
        var w_obj = new Nudlle.widget(widget);
        w_obj.update(params);
      });
    });

    widget.find('input[type="submit"][data-update]').each(function() {
      $(this).removeAttr('data-update');
      var form = $(this).closest('form').removeAttr('action method');

      form.submit(function() {
        var params = new Object();
        form.find('input[name]').each(function() {
          params[this.name] = this.value;
        });

        var w_obj = new Nudlle.widget(widget);
        w_obj.update(params);
        return false;
      });
    });

    /*
    widget.find('table.paginated.ordered > tbody').sortable({
      items: '> tr.ordered',
      containment: 'parent',
      cursor: 'move',
      update: function (e, ui) {
        var new_pos = ui.item.prev().data('position');
        if (!new_pos) new_pos = ui.item.next().data('position') - 1;
        var amount = new_pos - ui.item.data('position');
        if (amount < 0) amount++;
        widget.find('table.paginated.ordered > tbody').sortable('disable');

        var params = new Object();
        params[Nudlle.INDEX_MODULE] = module;
        params[Nudlle.INDEX_OPERATION] = 'move';
        params.id = ui.item.data('id');
        params.amount = amount;

        var conf = {
          origin: ui.item,
          error_message: 'Změna pořadí se nezdařila.',
          data: params,
          update: true,
          done: function() { widget.find('table.paginated.ordered > tbody').sortable('enable') }
        };

        Nudlle.ajax(conf);
      }
    });
    */

  }, ':has(> table.paginated)');
})();

