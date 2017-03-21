(function() {

  var
    ignore_class = 'do-not-touch',
    v_mark = 1,
    v_mark_used = 2,
    v_submit = 4,
    v_select = 8,
    NO_INSTANT = 0,
    STD_INSTANT = 1,
    SUPER_INSTANT = 2;

  var checks = {
    required: function(value) { return value ? true : false; },
    number: function(value) {
      if (value != '' && !isFinite(value)) {
        return false;
      }
      return true;
    },
    integer: function(value) {
      if (value != '' && (!isFinite(value) || value != Math.round(value))) {
        return false;
      }
      return true;
    },
    positiveNumber: function(value) { return value == '' || (checks.number(value) && value > 0) ? true : false; },
    positiveInteger: function(value) { return value == '' || (checks.integer(value) && value > 0) ? true : false; },
    nonNegativeNumber: function(value) { return value == '' || (checks.number(value) && value >= 0) ? true : false; },
    nonNegativeInteger: function(value) { return value == '' || (checks.integer(value) && value >= 0) ? true : false; },
    email: function(value) {
      // Regexp taken from PHPMailer library
      var regexp = /^(?:[\w\!\#\$\%\&\'\*\+\-\/\=\?\^\`\{\|\}\~]+\.)*[\w\!\#\$\%\&\'\*\+\-\/\=\?\^\`\{\|\}\~]+@(?:(?:(?:[a-zA-Z0-9_](?:[a-zA-Z0-9_\-](?!\.)){0,61}[a-zA-Z0-9_-]?\.)+[a-zA-Z0-9_](?:[a-zA-Z0-9_\-](?!$)){0,61}[a-zA-Z0-9_]?)|(?:\[(?:(?:[01]?\d{1,2}|2[0-4]\d|25[0-5])\.){3}(?:[01]?\d{1,2}|2[0-4]\d|25[0-5])\]))$/;
      if (value != '' && !regexp.test(value)) {
        return false;
      }
      return true;
    },
    date: function(value, format) {
      if (!format) format = Nudlle.i18n('forms.format_date');
      return moment(value, format, true).isValid();
    },
    datetime: function(value, format) {
      if (!format) format = Nudlle.i18n('forms.format_datetime');
      return moment(value, format, true).isValid();
    },
    time: function(value, format) {
      if (!format) format = Nudlle.i18n('forms.format_time');
      return moment(value, format, true).isValid();
    },
    phone: function(value) {
      // Very simple check. Use Nudlle.form.register_check() to replace it
      // with a more strict one.
      var regexp = /^\+?(?:[0-9][ .-]?){6,14}[0-9]$/;
      if (value != '' && !regexp.test(value)) {
        return false;
      }
      return true;
    },
    token: function(value) {
      var regexp = /\s/;
      if (value != '' && regexp.test(value)) {
        return false;
      }
      return true;
    }
  };

  var tooltips = {
    required:           Nudlle.i18n('forms.tooltip_required'),
    number:             Nudlle.i18n('forms.tooltip_number'),
    integer:            Nudlle.i18n('forms.tooltip_integer'),
    positiveNumber:     Nudlle.i18n('forms.tooltip_positiveNumber'),
    positiveInteger:    Nudlle.i18n('forms.tooltip_positiveInteger'),
    nonNegativeNumber:  Nudlle.i18n('forms.tooltip_nonNegativeNumber'),
    nonNegativeInteger: Nudlle.i18n('forms.tooltip_nonNegativeInteger'),
    email:              Nudlle.i18n('forms.tooltip_email'),
    date:             [ Nudlle.i18n('forms.tooltip_date'), Nudlle.i18n('forms.format_date') ],
    datetime:         [ Nudlle.i18n('forms.tooltip_datetime'), Nudlle.i18n('forms.format_datetime') ],
    time:             [ Nudlle.i18n('forms.tooltip_time'), Nudlle.i18n('forms.format_time') ],
    phone:              Nudlle.i18n('forms.tooltip_phone'),
    token:              Nudlle.i18n('forms.tooltip_token')
  };

  function assign_tooltip(elem) {
    var text = [];
    var format;
    $(elem).tooltip('destroy');

    for (var key in tooltips) {
      if ($(elem).hasClass(key)) {
        if (typeof(tooltips[key]) == 'object') {
          format = $(elem).data('format');
          if (!format) format = tooltips[key][1];
          text.push(tooltips[key][0].replace('%F', format));
        } else {
          text.push(tooltips[key]);
        }
      }
    }

    var own_tooltip = $(elem).data('tooltip');
    if (own_tooltip != '') text.push(own_tooltip);

    if (text.length > 0) {
      $(elem).tooltip({
        placement: 'top',
        trigger: 'focus',
        title: text.join('<br>'),
        html: true
      });
    }
  }

  function toggle_submit(form, enable) {
    var list = form.find('input[type="submit"], input[type="image"], button[type="submit"]');
    if (enable) {
      list.prop('disabled', false);
    } else {
      list.prop('disabled', true);
    }
  }

  var default_set_invalid = function(elem) {
    $(elem).addClass('invalid');
  };

  var default_unset_invalid = function(elem) {
    $(elem).removeClass('invalid');
  };

  // instant == 0: no instant
  // instant == 1: validation on blur
  // instant == 2: periodical validation
  Nudlle.form = function(id_elem, instant) {
    var f, interval_id, self = this;
    var set_invalid, unset_invalid;
    var submit_hook = null;

    if (typeof(instant) == 'undefined') instant = STD_INSTANT;

    if (typeof(id_elem) == 'string') {
      f = $('form#'+id_elem);
    } else if (typeof(id_elem) == 'object') {
      f = $(id_elem).closest('form');
    } else {
      throw "Invalid attribute";
    }

    this.get = function() { return f; };

    this.init = function() {
      f.find('input[type="text"], input[type="password"], textarea').each(function() {
        $(this).unbind('focus.validation').bind('focus.validation', function() {
          $(this).data('focus', true);
        });
        assign_tooltip(this);
      });

      if (instant == SUPER_INSTANT) self.is_valid(v_submit);
      set_instant();

      f.unbind('submit.validation').bind('submit.validation', function() {
        var v = v_mark | v_select;
        if (instant == SUPER_INSTANT) v = v | v_submit;
        return self.is_valid(v);
      });
    };

    this.is_instant = function() { return instant; };

    this.instant_on = function() {
      instant = STD_INSTANT;
      set_instant();
    };

    this.instant_off = function() {
      instant = NO_INSTANT;
      set_instant();
    };

    this.super_instant = function() {
      instant = SUPER_INSTANT;
      set_instant();
    };

    function set_instant() {
      if (interval_id != null) {
        clearInterval(interval_id);
        interval_id = null;
      }
      f.find('input[type="text"], input[type="password"], textarea').each(function() {
        $(this).unbind('blur.validation');
      });

      if (instant == STD_INSTANT) {
        f.find('input[type="text"], input[type="password"], textarea').each(function() {
          $(this).bind('blur.validation', function() {
            Nudlle.form_tools.validate_input(this, v_mark, set_invalid, unset_invalid);
          });
        });
      } else if (instant == SUPER_INSTANT) {
        interval_id = setInterval(function() {
          self.is_valid(v_mark_used | v_submit);
        }, 100);
      }
    }

    this.is_valid = function(verbosity) {
      if (typeof(verbosity) == 'undefined') verbosity = 0;

      var invalid = 0;
      var first = null;

      f.find('input[type="text"], input[type="password"], textarea').each(function() {
        if (!Nudlle.form_tools.validate_input(this, verbosity, set_invalid, unset_invalid)) {
          invalid++;
          if (invalid == 1) first = this;
        }
      });

      valid = invalid == 0;
      if (!valid && verbosity & v_select) $(first).select();

      if (valid && submit_hook) {
        valid = submit_hook(f.get(0));
      }

      if (verbosity & v_submit) toggle_submit(f, valid);
      return valid;
    }

    this.register_toggle = function(set, unset) {
      if (typeof(set) != 'function' || typeof(unset) != 'function') {
        throw 'Invalid argument.';
      }

      set_invalid = set;
      unset_invalid = unset;
    };

    this.register_submit_hook = function(hook) {
      if (typeof(hook) != 'function') {
        throw 'Invalid argument.';
      }

      submit_hook = hook;
    };

    return this;
  };

  Nudlle.form.register_check = function(label, tooltip, check) {
    if (typeof(label) != 'string' || typeof(tooltip) != 'string' || typeof(check) != 'function') {
      throw 'Invalid argument.';
    }

    tooltips[label] = tooltip;
    checks[label] = check;
  };

  Nudlle.form_tools = {};
  Nudlle.form_tools.disable_validation = function(elem) {
    $(elem).addClass('do-not-validate');
  };
  Nudlle.form_tools.enable_validation = function(elem) {
    $(elem).removeClass('do-not-validate');
  };
  Nudlle.form_tools.validate_input = function(elem, verbosity, set, unset) {
    if ($(elem).hasClass('do-not-validate')) return true;
    if ($(elem).prop('disabled')) return true;
    if (typeof(verbosity) == 'undefined') verbosity = 0;
    if (typeof(set) == 'undefined') set = default_set_invalid;
    if (typeof(unset) == 'undefined') unset = default_unset_invalid;
    var valid;
    var checked = false;
    var toggle = verbosity & v_mark || (verbosity & v_mark_used && $(elem).data('focus'));

    for (var key in checks) {
      if ($(elem).hasClass(key)) {
        checked = true;
        if ($(elem).data('format')) {
          valid = checks[key](elem.value, $(elem).data('format'));
        } else {
          valid = checks[key](elem.value);
        }

        if (!valid) {
          if (toggle) set(elem);
          return false;
        }
      }
    }

    if (checked && toggle) unset(elem);
    return true;
  };

  Nudlle.register_widget_init_hook(function(widget) {
    $(widget).widgetFind('form:not(.'+ignore_class+')').each(function() {
      var obj = new Nudlle.form(this, STD_INSTANT);
      obj.init();
    });
  });

})();
