(function() {

var translations = new Object();

Nudlle.i18n = function(label) {
  if (typeof translations[label] != 'undefined') {
    return translations[label];
  } else {
    throw "I18n error - unknown label '"+label+"'";
  }
};

Nudlle.i18n.store = function(data) {
  for (label in data) {
    translations[label] = data[label];
  }
};

})();
