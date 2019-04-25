var extract_preselected_ids = function(){
  var preselected_ids = [];
  if(jQuery('#wc_increase_price_products').val())
      jQuery(jQuery('#wc_increase_price_products').val().split(",")).each(function () {
          preselected_ids.push({id: this});
      });
  return preselected_ids;
};

var preselect = function(preselected_ids){
  var pre_selections = [];
  for(index in selections)
      for(id_index in preselected_ids)
          if (selections[index].id == preselected_ids[id_index].id)
              pre_selections.push(selections[index]);
  return pre_selections;
};

jQuery("#wc_increase_price_products_select").attr('multiple','multiple').select2({
  multiple: true,
  initSelection: function(element, callback){
    var preselected_ids = extract_preselected_ids();
    var preselections = preselect(preselected_ids);
    callback(preselections);
  },
  ajax: {
    url: ajaxurl,
    dataType: 'json',
    method:'POST',
    delay: 250,
    data: function (term) {
      return {
        q: term,
        action: 'get_products_ajax',
      };
    },
    results: function (data, params) {
      params.page = params.page || 1;
      return {
        results: data.results,
        pagination: {
          more: (params.page * 30) < data.total_count
        }
      };
    },
    cache: true
  },
  placeholder: 'Search for product',
  escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
  minimumInputLength: 3,
});
jQuery('#wc_increase_price_products_select').on("select2:select", function(e) { 
  console.log('vlaga');
  updateSelected();
});
function updateSelected() {
  var select = document.getElementById('wc_increase_price_products_select'),
      options = select.options,
      input = document.getElementById('wc_increase_price_products');

  var selected = [];
  for (var i = 0, ii = options.length; i < ii; ++i) {
    var opt = options[i];

    selected.push(opt.value);
  }

  input.value = selected.join(',');
}