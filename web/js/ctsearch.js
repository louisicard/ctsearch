(function ($) {
  $(document).ready(function () {
    $('a.index-delete').click(function (e) {
      e.preventDefault();
      var url = $(this).attr('href');
      return advConfirm(__ctsearch_js_translations.DeleteIndexConfirm, function () {
        window.location = url;
      });
    });
    $('a.datasource-delete').click(function (e) {
      e.preventDefault();
      var url = $(this).attr('href');
      return advConfirm(__ctsearch_js_translations.DeleteDatasourceConfirm, function () {
        window.location = url;
      });
    });
    $('a.processor-delete').click(function (e) {
      e.preventDefault();
      var url = $(this).attr('href');
      return advConfirm(__ctsearch_js_translations.DeleteProcessorConfirm, function () {
        window.location = url;
      });
    });
    $('a.search-page-delete').click(function (e) {
      e.preventDefault();
      var url = $(this).attr('href');
      return advConfirm(__ctsearch_js_translations.DeleteSearchPageConfirm, function () {
        window.location = url;
      });
    });
    $('a.matching-list-delete').click(function (e) {
      e.preventDefault();
      var url = $(this).attr('href');
      return advConfirm(__ctsearch_js_translations.DeleteMatchingListConfirm, function () {
        window.location = url;
      });
    });

    $('#delete-mapping-link').click(function (e) {
      e.preventDefault();
      var url = $(this).attr('href');
      return advConfirm(__ctsearch_js_translations.DeleteMappingConfirm, function () {
        window.location = url;
      });
    });

    $('#container form').each(function () {
      $(this).children().children('div').addClass('form-item clearfix');
      $(this).find('input[required="required"]').each(function () {
        $(this).parents('.form-item').addClass('required');
      });
    });

    $('.search-page .search-result-source-toggler a').click(function () {
      $(this).parents('.search-result').find('.search-result-source').slideToggle();
    });

    var agg_facet_see_more_handler = function (e) {
      e.preventDefault();
      var agg_id = $(this).parents('.agg').attr('id');
      var link = $(this);
      $(this).addClass("ajax-processing");
      $.ajax({
        url: $(this).attr('href')
      }).done(function (html) {
        $('#' + agg_id + '.agg').html($(html).find('#' + agg_id + '.agg').html());
      }).complete(function () {
        $('#' + agg_id + '.agg .see-more a').click(agg_facet_see_more_handler);
      });
    };
    $('.search-page .aggregations .see-more a').click(agg_facet_see_more_handler);

    $('.search-page-logs table.logs td.object').each(function () {
      var obj = JSON.parse($(this).html());
      $(this).html(prettyPrint(obj), {
        // Config
        maxArray: 20, // Set max for array display (default: infinity)
        expanded: false, // Expanded view (boolean) (default: true),
        maxDepth: 5 // Max member depth (when displaying objects) (default: 3)
      }).wrapInner('<div class="pretty-json" style="display:none"></div>');
      $(this).prepend('<div><a href="javascript:void(0)">Show/hide object</a></div>');
      $(this).find('a').click(function () {
        $(this).parents('td').find('.pretty-json').slideToggle();
      });
    });

    if ($('#form_mappingDefinition').size() > 0) {
      $('#form_mappingDefinition').parents('form').bind('submit', function (e) {
        e.preventDefault();
        var form = $(this);
        if ($('#form_wipeData').is(':checked')) {
          return advConfirm(__ctsearch_js_translations.UpdateMappingWipe, function () {
            form.unbind();
            form.submit();
          });
        }
        else {
          return advConfirm(__ctsearch_js_translations.UpdateMappingNoWipe, function () {
            form.unbind();
            form.submit();
          });
        }
      });
      $('<div id="mapping-json-toggle-container"><a href="javascript:void(0)" id="mapping-json-toggle" class="json-link">' + __ctsearch_js_translations.ShowHideJSONDef + '</a></div>').insertBefore($('#form_mappingDefinition'));
      initMappingAssistant();
      $('#form_mappingDefinition').width($('#mapping-table').width());
      $('#form_mappingDefinition').css('display', 'none');
      $('#mapping-json-toggle').click(function () {
        $('#form_mappingDefinition').slideToggle();
      });
    }
    if ($('#form_processor #form_definition').size() > 0) {
      $('<div id="mapping-json-toggle-container"><a href="javascript:void(0)" id="mapping-json-toggle" class="json-link">' + __ctsearch_js_translations.ShowHideJSONDef + '</a></div>').insertBefore($('#form_processor #form_definition'));
      initProcessorStack();
      $('#form_processor #form_definition').width($('#processor-stack').width());
      $('#form_processor #form_definition').css('display', 'none');
      $('#mapping-json-toggle').click(function () {
        $('#form_processor #form_definition').slideToggle();
      });
      $('#form_processor').submit(function (e) {
        if ($(this).find('.stack-item.error').size() > 0) {
          advAlert('Your processor needs some fixing');
          return false;
        }
        else {
          return true;
        }
      });
    }
    if ($('#form_matching_list #form_list').size() > 0) {
      $('<div id="matching-list-json-toggle-container"><a href="javascript:void(0)" id="matching-list-json-toggle" class="json-link">' + __ctsearch_js_translations.ShowHideJSONDef + '</a></div>').insertBefore($('#form_matching_list #form_list'));
      initMatchingListAssistant();
      $('#form_matching_list #form_list').width($('#matching-list-table').width());
      $('#form_matching_list #form_list').css('display', 'none');
      $('#matching-list-json-toggle').click(function () {
        $('#form_matching_list #form_list').slideToggle();
      });
    }

    $('#matching-list-size-selector + a').click(function (e) {
      e.preventDefault();
      if ($('#matching-list-field-selector').val() != '' && $('#matching-list-size-selector').val() != '') {
        window.location = $(this).attr('href') + '&field=' + encodeURIComponent($('#matching-list-field-selector').val()) + '&size=' + encodeURIComponent($('#matching-list-size-selector').val());
      }
      else {
        advAlert('You must select a field and a maximum');
      }
    });

    $(window).load(reactResponsive);
    $(window).resize(reactResponsive);

    $('.search-page .search-result .more-like-this a').click(function () {
      var container = $(this).parent();
      var link = $(this);
      if ($(this).hasClass('collapse')) {
        $(this).removeClass('collapse');
        $(this).text('See more like this');
        container.removeClass('with-children');
        container.find('.search-result').detach();
      }
      else {
        $(this).addClass('ajax-link');
        $(this).addClass('ajax-processing');

        var searchPageId = $(this).parents('*[search-page-id]').attr('search-page-id');
        var docId = $(this).parents('*[doc-id]').attr('doc-id');
        var type = $(this).parents('*[doc-type]').attr('doc-type');
        $.ajax({
          url: __base_url + 'search-pages/more-like-this/' + searchPageId + '/' + docId + '/' + type
        }).success(function (data) {
          link.removeClass('ajax-link');
          link.removeClass('ajax-processing');
          link.text('Hide');
          link.addClass('collapse');
          if ($(data).find('.search-results').children().size() > 0) {
            container.addClass('with-children');
          }
          $(data).find('.search-results').children().each(function () {
            $(this).appendTo(container);
          });
        });
      }
    });
    if ($('#search-page-form input[type="text"]').size() > 0 && __autocomplete_enabled) {
      $('#search-page-form input[type="text"]').autocomplete({
        source: function (request, response) {
          var searchPageId = $('*[search-page-id]').attr('search-page-id');
          $.ajax({
            url:  __base_url + 'search-pages/autocomplete/' + searchPageId + '/' + encodeURIComponent(request.term)
          }).success(function(data){
            if(typeof console !== 'undefined') {
              console.log('AC took : ' + data.took + 'ms');
            }
            response(data.data);
          });
        },
        select: function(event, ui){
          $('#search-page-form input[type="text"]').val(ui.item.value);
          $('#search-page-form').submit();
        }
      });
    }
    
    $('ul li.index-mapping').each(function(){
      var indexName = $(this).parents('tr').find('td:first-child').text();
      var mappingName = $(this).find('a').text();
      $(this).addClass('ajax-loading');
      var li = $(this);
      $.ajax({
        url: __ctsearch_base_url + 'indexes/mapping-stat/' + indexName + '/' + mappingName
      }).success(function(data){
        li.removeClass('ajax-loading');
        li.find('.mapping-stat').html('<ul><li>' + data.docs + '<span> documents</span></li><li>' + data.fields + '<span> fields</span></li></ul>');
      });
    });
  });

  function reactResponsive() {
    if ($(window).width() > 720) {
      $('.search-page.with-aggregations .aggregations h2').detach();
      $('.search-page.with-aggregations .aggregations .agg-wrapper').css('display', 'block');
    }
    else {
      if ($('.search-page.with-aggregations .aggregations h2').size() == 0) {
        $('.search-page.with-aggregations .aggregations').prepend('<h2>Filters</h2>');
        $('.search-page.with-aggregations .aggregations h2').click(function () {
          $('.search-page.with-aggregations .aggregations .agg-wrapper').slideToggle();
        });
      }
      if ($('.search-page.with-aggregations .aggregations .agg-wrapper').size() == 0) {
        $('.search-page.with-aggregations .aggregations .agg').wrapAll('<div class="agg-wrapper"></div>');
        $('.search-page.with-aggregations .aggregations .agg-wrapper').css('display', 'none');
      }
    }
  }

  function initMappingAssistant() {
    $('#mapping-table').detach();
    var table = $('<table id="mapping-table"><thead><tr><th>' + __ctsearch_js_translations.FieldName + '</th><th>' + __ctsearch_js_translations.FieldType + '</th><th>' + __ctsearch_js_translations.FieldFormat + '</th><th>' + __ctsearch_js_translations.FieldAnalysis + '</th><th>' + __ctsearch_js_translations.FieldStore + '</th><th>' + __ctsearch_js_translations.FieldBoost + '</th><th>&nbsp;</th></tr></thead><tbody></tbody></table>').insertBefore($('#mapping-json-toggle-container'));
    var json = JSON.parse($('#form_mappingDefinition').val());
    if (json.length == 0) {
      json = {};
      $('#form_mappingDefinition').val('{}');
    }
    for (var field in json) {
      var type = json[field].type;
      var store = typeof json[field].store !== 'undefined' && !json[field].store ? __ctsearch_js_translations.FieldNotStored : __ctsearch_js_translations.FieldStored;
      var format = typeof json[field].format !== 'undefined' ? json[field].format : '-';
      var analyzed = typeof json[field].index !== 'undefined' && json[field].index == 'not_analyzed' ? __ctsearch_js_translations.FieldNotAnalyzed : __ctsearch_js_translations.FieldAnalyzed;
      var analyzer = typeof json[field].analyzer !== 'undefined' ? json[field].analyzer : null;
      var boost = typeof json[field].boost !== 'undefined' ? json[field].boost : '1';
      table.find('tbody').append('<tr><td>' + field + '</td><td>' + type + '</td><td>' + format + '</td><td>' + analyzed + (analyzer != null ? ' (' + analyzer + ')' : '') + '</td><td>' + store + '</td><td>' + boost + '</td><td><a href="javascript:void(0)" class="mapping-delete-field action-delete">' + __ctsearch_js_translations.FieldDelete + '</a></td></tr>');
    }
    var type_select = '<select id="mapping-definition-field-type" tabindex="2">';
    type_select += '<option value="">' + __ctsearch_js_translations.FieldType + '</option>';
    for (var i = 0; i < __field_types.length; i++) {
      type_select += '<option value="' + __field_types[i] + '">' + __field_types[i] + '</option>';
    }
    type_select += '</select>';
    var format_select = '<select id="mapping-definition-field-format" disabled="disabled" tabindex="3">';
    format_select += '<option value="">' + __ctsearch_js_translations.FieldFormat + '</option>';
    for (var i = 0; i < __date_formats.length; i++) {
      format_select += '<option value="' + __date_formats[i] + '">' + __date_formats[i] + '</option>';
    }
    format_select += '</select>';
    var analysis_select = '<select id="mapping-definition-field-analysis" tabindex="4">';
    analysis_select += '<option value="">' + __ctsearch_js_translations.FieldAnalyzed + '</option>';
    analysis_select += '<option value="not_analyzed">' + __ctsearch_js_translations.FieldNotAnalyzed + '</option>';
    for (var i = 0; i < __index_analyzers.length; i++) {
      analysis_select += '<option value="' + __index_analyzers[i] + '">' + __ctsearch_js_translations.FieldAnalyzed + ' (analyzer = ' + __index_analyzers[i] + ')</option>';
    }
    analysis_select += '</select>';
    var store_select = '<select id="mapping-definition-field-store" tabindex="5">';
    store_select += '<option value="true">' + __ctsearch_js_translations.FieldStored + '</option>';
    store_select += '<option value="false">' + __ctsearch_js_translations.FieldNotStored + '</option>';
    store_select += '</select>';
    var boost_select = '<select id="mapping-definition-field-boost" tabindex="6">';
    boost_select += '<option value="1">' + __ctsearch_js_translations.FieldBoost + '</option>';
    for (var i = 1; i <= 10; i++) {
      boost_select += '<option value="' + i + '">' + i + '</option>';
    }
    boost_select += '</select>';
    table.find('tbody').append('<tr><td><input type="text" id="mapping-definition-field-name" placeholder="' + __ctsearch_js_translations.FieldName + '" tabindex="1" /><br /><a href="javascript:void(0)" id="mapping-add-field" tabindex="7">' + __ctsearch_js_translations.FieldAdd + '</a></td><td>' + type_select + '</td><td>' + format_select + '</td><td>' + analysis_select + '</td><td>' + store_select + '</td><td>' + boost_select + '</td><td></td></tr>');
    table.wrap('<div class="mapping-table-container"></div>');
    $('#mapping-add-field').click(function () {
      var field_name = $('#mapping-definition-field-name').val();
      var field_type = $('#mapping-definition-field-type').val();
      if (field_name != '' && field_type != '') {
        if (typeof json[field_name] === 'undefined') {
          json[field_name] = {
            'type': field_type
          };
          if ($('#mapping-definition-field-format').val() != '') {
            json[field_name].format = $('#mapping-definition-field-format').val();
          }
          if ($('#mapping-definition-field-analysis').val() != '') {
            if ($('#mapping-definition-field-analysis').val() == 'not_analyzed') {
              json[field_name].index = 'not_analyzed';
            }
            else {
              json[field_name].analyzer = $('#mapping-definition-field-analysis').val();
            }
          }
          json[field_name].store = $('#mapping-definition-field-store').val() != 'false';
          if ($('#mapping-definition-field-boost').val() != '1') {
            json[field_name].boost = $('#mapping-definition-field-boost').val();
          }
          $('#form_mappingDefinition').val(JSON.stringify(json));
          initMappingAssistant();
        }
        else {
          advAlert(__ctsearch_js_translations.FieldAlreadyExists);
        }
      }
      else {
        advAlert(__ctsearch_js_translations.FieldMissingNameOrType);
      }
    });
    $('.mapping-delete-field').click(function () {
      var field_name = $(this).parents('tr').find('td:first-child').html();
      delete json[field_name];
      $('#form_mappingDefinition').val(JSON.stringify(json));
      initMappingAssistant();
    });
    $('#mapping-definition-field-type').change(function () {
      if ($(this).val() == 'date') {
        $('#mapping-definition-field-format').removeAttr('disabled');
      }
      else {
        $('#mapping-definition-field-format').attr('disabled', 'disabled');
        $('#mapping-definition-field-format').val('');
      }
    });
  }

  function initProcessorStack() {
    $('#processor-stack').detach();
    var stack = $('<div id="processor-stack" class="clearfix"></div>').insertBefore($('#mapping-json-toggle-container'));
    var json = JSON.parse($('#form_processor #form_definition').val());
    for (var i = 0; i < __datasource_fields.length; i++) {
      if ($.inArray(__datasource_fields[i], json.datasource.fields, 0) < 0) {
        json.datasource.fields.push(__datasource_fields[i]);
      }
    }
    for (var i = json.datasource.fields.length - 1; i >= 0; i--) {
      if ($.inArray(json.datasource.fields[i], __datasource_fields, 0) < 0)
        json.datasource.fields.splice(i, 1);
    }
    var available_inputs = [];
    var ds_html = '<div class="datasource stack-item"><div class="inside"><div class="name">Datasource</div><div class="display-name  ">' + json.datasource.name + '</div>';
    ds_html += '<div class="fields"><div class="legend">Output</div><ul>';
    for (var i = 0; i < json.datasource.fields.length; i++) {
      available_inputs.push('datasource.' + json.datasource.fields[i]);
      ds_html += '<li><em>' + json.datasource.fields[i] + '</em></li>';
    }
    ds_html += '</ul></div>';
    ds_html += '</div></div>';
    stack.append(ds_html);

    var error_filters = [];
    for (var i = 0; i < json.filters.length; i++) {
      var filters_html = '';
      filters_html = '<div class="filter stack-item" id="filter-' + json.filters[i].id + '"><div class="inside"><div class="edit-filter"><a href="javascript:void(0);">Edit</a></div><div class="move-filter"><a href="javascript:void(0);" class="move-left">&lt;</a><a href="javascript:void(0);" class="move-right">&gt;</a></div><div class="filter-id">ID = ' + json.filters[i].id + '</div><div class="remove-filter"><a href="javascript:void(0);">Remove</a></div><div class="name">Filter #' + (i + 1) + '</div><div class="in-stack-name">' + (typeof json.filters[i].inStackName != 'undefined' ? json.filters[i].inStackName : '') + '</div><div class="display-name">' + json.filters[i].filterDisplayName + '</div>';

      filters_html += '<div class="fields"><div class="legend">Input</div>';
      if (json.filters[i].arguments.length > 0) {
        filters_html += '<ul>';
        var error = false;
        for (var j = 0; j < json.filters[i].arguments.length; j++) {
          if ($.inArray(json.filters[i].arguments[j].value, available_inputs) < 0 && json.filters[i].arguments[j].value != 'empty_value') {
            error_filters.push('#filter-' + json.filters[i].id);
            error = true;
          }
          else {
            error = false;
          }
          filters_html += '<li' + (error ? ' class="error"' : '') + '><em>' + json.filters[i].arguments[j].key + '</em> : ' + json.filters[i].arguments[j].value + '</li>';
        }
        filters_html += '</ul>';
      }
      else {
        filters_html += '<div class="no-arguments">No input</div>';
      }
      filters_html += '</div>';

      filters_html += '<div class="fields"><div class="legend">Output</div><ul>';
      for (var j = 0; j < json.filters[i].fields.length; j++) {
        available_inputs.push('filter_' + json.filters[i].id + '.' + json.filters[i].fields[j]);
        filters_html += '<li><em>' + json.filters[i].fields[j] + '</em></li>';
      }
      filters_html += '</ul></div>';

      filters_html += '</div></div>';
      stack.append(filters_html);
    }
    for (var i = 0; i < error_filters.length; i++) {
      $(error_filters[i]).addClass('error');
    }

    var add_filter_html = '<div id="add-filter-container" class="actions">';
    add_filter_html += '<select><option value="">Select a filter</option>';
    for (var i = 0; i < __filter_types.length; i++) {
      add_filter_html += '<option value="' + __filter_types[i].split('#')[0] + '">' + __filter_types[i].split('#')[1] + '</option>';
    }
    add_filter_html += '</select>';
    add_filter_html += '<a href="javascript:void(0)">Add filter</a>';
    add_filter_html += '</div>';
    stack.append(add_filter_html);
    $('#processor-stack #add-filter-container a').click(function () {
      if ($('#processor-stack #add-filter-container select').val() != '') {
        displayFilterSettings(json, $('#processor-stack #add-filter-container select').val(), null);
      }
      else {
        advAlert('You must select a filter type');
      }
    });
    $('#processor-stack .stack-item .edit-filter a').click(function () {
      var id = $(this).parents('.stack-item').attr('id').split('-')[1];
      for (var i = 0; i < json.filters.length; i++) {
        if (json.filters[i].id == id) {
          displayFilterSettings(json, json.filters[i].class, json.filters[i]);
          break;
        }
      }
    });
    $('#processor-stack .stack-item .remove-filter a').click(function () {
      var id = $(this).parents('.stack-item').attr('id').split('-')[1];
      for (var i = 0; i < json.filters.length; i++) {
        if (json.filters[i].id == id) {
          json.filters.splice(i, 1);
          $('#form_processor #form_definition').val(JSON.stringify(json, null, 2));
          initProcessorStack();
          break;
        }
      }
    });
    $('#processor-stack .stack-item .move-filter a.move-left').click(function () {
      var id = $(this).parents('.stack-item').attr('id').split('-')[1];
      for (var i = 0; i < json.filters.length; i++) {
        if (json.filters[i].id == id) {
          if (i >= 1) {
            var before = json.filters[i - 1];
            json.filters[i - 1] = json.filters[i];
            json.filters[i] = before;
          }
          $('#form_processor #form_definition').val(JSON.stringify(json, null, 2));
          initProcessorStack();
          break;
        }
      }
    });
    $('#processor-stack .stack-item .move-filter a.move-right').click(function () {
      var id = $(this).parents('.stack-item').attr('id').split('-')[1];
      for (var i = 0; i < json.filters.length; i++) {
        if (json.filters[i].id == id) {
          if (i <= json.filters.length - 2) {
            var after = json.filters[i + 1];
            json.filters[i + 1] = json.filters[i];
            json.filters[i] = after;
          }
          $('#form_processor #form_definition').val(JSON.stringify(json, null, 2));
          initProcessorStack();
          break;
        }
      }
    });

    if (typeof json.mapping == 'undefined') {
      json['mapping'] = {};
    }
    var mapping_html = '<div class="mapping-container"><h2>Mapping</h2>';
    mapping_html += '<table id="mapping-table"><thead><tr><th>Input</th><th>Target</th></tr></thead><tbody>';
    mapping_html += '<tr><td class="input">' + getMappingInputSelect(json, '_id') + '</td><td class="target">' + __mapping_name + '._id</td></tr>';
    for (var i = 0; i < __target_fields.length; i++) {
      mapping_html += '<tr><td class="input">' + getMappingInputSelect(json, __target_fields[i]) + '</td><td class="target">' + __mapping_name + '.' + __target_fields[i] + '</td></tr>';
    }
    mapping_html += '</tbody></table></div>';
    stack.append(mapping_html);
    stack.find('.mapping-select').change(function () {
      var target_field = $(this).attr('id').split('-')[1];
      json.mapping[target_field] = $(this).val();
      $('#form_processor #form_definition').val(JSON.stringify(json, null, 2));
    });
  }

  function getMappingInputSelect(json, target_field) {
    var html = '<select id="mapping_select-' + target_field + '" class="mapping-select"><option value="">No input</option>';
    var found_in_mapping = false;
    for (var i = 0; i < json.datasource.fields.length; i++) {
      var selected = typeof json.mapping[target_field] != 'undefined' && json.mapping[target_field] == 'datasource.' + json.datasource.fields[i];
      if (selected)
        found_in_mapping = true;
      html += '<option value="datasource.' + json.datasource.fields[i] + '"' + (selected ? ' selected="selected"' : '') + '>Datasource field &quot;' + json.datasource.fields[i] + '&quot;</option>';
    }
    for (var i = 0; i < json.filters.length; i++) {
      for (var j = 0; j < json.filters[i].fields.length; j++) {
        var selected = typeof json.mapping[target_field] != 'undefined' && json.mapping[target_field] == 'filter_' + json.filters[i].id + '.' + json.filters[i].fields[j];
        if (selected)
          found_in_mapping = true;
        html += '<option value="filter_' + json.filters[i].id + '.' + json.filters[i].fields[j] + '"' + (selected ? ' selected="selected"' : '') + '>Filter #' + (i + 1) + ' (' + json.filters[i].inStackName + ') field &quot;' + json.filters[i].fields[j] + '&quot;</option>';
      }
    }
    html += '</select>';
    if (!found_in_mapping) {
      json.mapping[target_field] = '';
      $('#form_processor #form_definition').val(JSON.stringify(json, null, 2));
    }
    return html;
  }

  function displayFilterSettings(json, filterClass, filter) {
    var waitDialog = $('<div style="text-align:center;padding:50px;"><img src="' + __loading_image_url + '" /></div>').dialog({
      modal: true
    });
    var mainDialog = $('<div class="dialog-content filter-dialog"></div>').dialog({
      modal: true,
      autoOpen: false,
      title: 'Filter settings',
      width: 600,
      create: function () {
        //$(this).html('<div style="text-align:center;padding:50px;"><img src="' + __loading_image_url + '" /></div>');
        var dialog = $(this);
        var data = {
          class: filterClass
        };
        if (filter != null) {
          var filter_data = {};
          for (var k in filter.settings) {
            filter_data['setting_' + k] = filter.settings[k];
          }
          for (var i = 0; i < filter.arguments.length; i++) {
            filter_data['arg_' + filter.arguments[i].key] = filter.arguments[i].value;
          }
          if (filter.inStackName != 'undefined') {
            filter_data['in_stack_name'] = filter.inStackName;
          }
          if (filter.autoImplode != 'undefined') {
            filter_data['autoImplode'] = filter.autoImplode;
          }
          if (filter.autoImplodeSeparator != 'undefined') {
            filter_data['autoImplodeSeparator'] = filter.autoImplodeSeparator;
          }
          if (filter.autoStriptags != 'undefined') {
            filter_data['autoStriptags'] = filter.autoStriptags;
          }
          if (filter.isHTML != 'undefined') {
            filter_data['isHTML'] = filter.isHTML;
          }
          data['data'] = JSON.stringify(filter_data);
        }
        $.ajax({
          method: 'POST',
          url: __proc_settings_ajx_form_url,
          data: data
        }).done(function (data) {
          dialog.html(data);
          dialog.find('form').each(function () {
            $(this).children().children('div').addClass('form-item clearfix');
            $(this).find('input[required="required"]').each(function () {
              $(this).parents('.form-item').addClass('required');
            });
          });
          dialog.find('input.filter-argument').each(function () {
            setFilterSelect($(this), json, filter);
          });
          $(mainDialog).dialog('open');
          $(waitDialog).dialog('destroy');
          dialog.find('form').submit(function (e) {
            e.preventDefault();
            $(this).append('<input type="hidden" name="class" value="' + filterClass + '" />');
            var formData = $(this).serialize();
            $(this).html('<div style="text-align:center;padding:50px;"><img src="' + __loading_image_url + '" /></div>');
            $.ajax({
              method: $(this).attr('method'),
              url: __proc_settings_ajx_form_url,
              data: formData,
              dataType: 'json'
            }).done(function (r) {
              dialog.dialog('destroy');
              if (filter == null) {
                r['id'] = Math.round(Math.random() * 100000) + '';
                json.filters.push(r);
              }
              else {
                for (var i = 0; i < json.filters.length; i++) {
                  if (json.filters[i].id == filter.id) {
                    r['id'] = filter.id;
                    json.filters[i] = r;
                    break;
                  }
                }
              }
              $('#form_processor #form_definition').val(JSON.stringify(json, null, 2));
              initProcessorStack();
            });
          });
        });
      }
    });
  }

  function setFilterSelect(input, json, filter) {
    var html = '<select id="' + input.attr('id') + '" name="' + input.attr('name') + '" required="required" class="' + input.attr('class') + '">';
    html += '<option value="">Select a source</option><option value="empty_value">Empty value</option>';
    for (var i = 0; i < json.datasource.fields.length; i++) {
      html += '<option value="datasource.' + json.datasource.fields[i] + '">Datasource field &quot;' + json.datasource.fields[i] + '&quot;</option>';
    }
    for (var i = 0; i < json.filters.length; i++) {
      if (filter != null && filter.id == json.filters[i].id) {
        break;
      } else {
        for (var j = 0; j < json.filters[i].fields.length; j++) {
          html += '<option value="filter_' + json.filters[i].id + '.' + json.filters[i].fields[j] + '">Filter #' + (i + 1) + ' (' + json.filters[i].inStackName + ') field &quot;' + json.filters[i].fields[j] + '&quot;</option>';
        }
      }
    }
    html += '</select>';
    var value = input.val();
    var id = input.attr('id');
    $(html).appendTo(input.parent()).val(value);
    input.detach();
  }

  function initMatchingListAssistant() {
    $('#matching-list-table').detach();
    var table = $('<table id="matching-list-table"><thead><tr><th>Input</th><th>Output</th><th>&nbsp;</th></tr></thead><tbody></tbody></table>').insertBefore($('#matching-list-json-toggle-container'));
    var json = JSON.parse($('#form_matching_list #form_list').val());
    for (var key in json) {
      table.find('tbody').append('<tr><td>' + key + '</td><td>' + json[key] + '</td><td><a href="javascript:void(0);" class="delete action-delete">Delete</a></td></tr>');
    }
    table.find('tbody').append('<tr><td><input type="text" id="matching-list-key" /></td><td><input type="text" id="matching-list-value" /></td><td class="actions"><a href="javascript:void(0);" class="add">Add</a></td></tr>');
    table.wrap('<div class="matching-list-table-container"></div>');
    table.find('a.delete').click(function () {
      delete json[$(this).parents('tr').find('td:first-child').html()];
      $('#form_matching_list #form_list').val(JSON.stringify(json));
      initMatchingListAssistant();
    });
    table.find('a.add').click(function () {
      if ($('#matching-list-key').val() != '') {
        if (typeof json[$('#matching-list-key').val()] == 'undefined') {
          json[$('#matching-list-key').val()] = $('#matching-list-value').val();
          $('#form_matching_list #form_list').val(JSON.stringify(json));
          initMatchingListAssistant();
        } else {
          advAlert('Input key "' + $('#matching-list-key').val() + '" is already defined.');
        }
      }
      else {
        advAlert('You must provide an input key');
      }
    });
  }


  function advAlert(text) {
    text = text.toString();
    var msg = text.split('\n');
    var html = '<ul class="messages">';
    for (var i = 0; i < msg.length; i++) {
      if (msg[i].trim().length > 0)
        html += '<li>' + msg[i].trim() + '</li>';
    }
    html += '</ul>';
    html = '<div><div class="adv-content">' + html + '</div>';
    html += '<div class="adv-actions"><button>' + __ctsearch_js_translations.OK + '</button></div></div>';
    var dialog = $(html).dialog({
      title: '',
      modal: true,
      minWidth: 300,
      dialogClass: 'adv-alert',
      resizable: false,
      close: function () {
        $(this).dialog('destroy').remove();
      },
      show: {
        effect: "drop",
        direction: 'up',
        duration: 300
      },
      hide: {
        effect: "fadeOut",
        duration: 200
      }
    });
    $('.adv-actions button').click(function () {
      $(dialog).dialog('close');
      return false;
    });
  }
  function advConfirm(text, callback) {
    var msg = text.split('\n');
    var html = '<ul class="messages">';
    for (var i = 0; i < msg.length; i++) {
      if (msg[i].trim().length > 0)
        html += '<li>' + msg[i].trim() + '</li>';
    }
    html += '</ul>';
    html = '<div><div class="adv-content">' + html + '</div>';
    html += '<div class="adv-actions"><button class="ok">' + __ctsearch_js_translations.OK + '</button><button class="cancel">' + __ctsearch_js_translations.Cancel + '</button></div></div>';
    var dialog = $(html).dialog({
      title: '',
      modal: true,
      minWidth: 300,
      dialogClass: 'adv-alert',
      resizable: false,
      close: function () {
        $(this).dialog('destroy').remove();
      },
      show: {
        effect: "drop",
        direction: 'up',
        duration: 300
      },
      hide: {
        effect: "fadeOut",
        duration: 200
      }
    });
    $('.adv-actions button.cancel').click(function () {
      $(dialog).dialog('close');
      return false;
    });
    $('.adv-actions button.ok').click(function () {
      $(dialog).dialog('close');
      callback();
      return false;
    });
  }

})(jQuery);