(function ($) {
  $(document).ready(function () {
    bindSortingLinks();
    bindSeeMoreLink();
    bindPagerLink();
    bindFacetLink();

    showObjects();
    bindMltLink();
    sizeElements();
    $(window).load(sizeElements);
    $(window).resize(sizeElements);

    autocomplete();

  });

  function sizeElements() {
    var topHeight = $('.search-form').height();
    $('main').css('margin-top', topHeight);
    $('.sidebar').css('top', topHeight);
    $('.sidebar').height($(window).height() - topHeight);
  }

  function showObjects() {
    $('.results li .source').each(function () {
      var obj = JSON.parse($(this).html());
      $(this).html(prettyPrint(obj), {
        // Config
        maxArray: 20, // Set max for array display (default: infinity)
        expanded: false, // Expanded view (boolean) (default: true),
        maxDepth: 5 // Max member depth (when displaying objects) (default: 3)
      }).wrapInner('<div class="pretty-json" style="display:none"></div>');
      $(this).prepend('<div><a href="javascript:void(0)" class="toggler">Show/hide object</a></div>');
      $(this).find('a.toggler').click(function () {
        $(this).parents('.source').parent().find('.pretty-json').slideToggle();
      });
      $(this).show();
    });
  }

  function bindSeeMoreLink() {
    $('.see-more a').unbind('click');
    $('.see-more a').click(function (e) {
      e.preventDefault();
      var link = $(this);
      link.addClass('ajax-loading');
      $.ajax({
        url: link.attr('href')
      }).success(function (html) {
        var selector = '#' + link.parents('.facet-block').attr('id') + ' .facet-content';
        var content = $(html).find(selector).html();
        $(selector).html(content);
        bindSeeMoreLink();
        bindFacetLink();
      });
      return false;
    });
  }

  function bindSortingLinks() {
    $('.sorting a').unbind('click');
    $('.sorting a').click(function (e) {
      e.preventDefault();
      var link = $(this);
      link.addClass('ajax-loading');
      $.ajax({
        url: link.attr('href')
      }).success(function (html) {
        var selector = '.results';
        var selector2 = '.sorting';
        var selector3 = '.facets';
        var content = $(html).find(selector).html();
        var content2 = $(html).find(selector2).html();
        var content3 = $(html).find(selector3).html();
        $(selector).html(content);
        $(selector2).html(content2);
        $(selector3).html(content3);
        bindPagerLink();
        bindFacetLink();
        bindSeeMoreLink();
        showObjects();
        bindMltLink();
        bindSortingLinks();
      });
      return false;
    });
  }

  function bindPagerLink() {
    $('.pager a').unbind('click');
    $('.pager a').click(function (e) {
      e.preventDefault();
      var link = $(this);
      link.addClass('ajax-loading');
      $.ajax({
        url: link.attr('href')
      }).success(function (html) {
        var selector = '.results';
        var content = $(html).find(selector).html();
        $(selector).html(content);
        $('html, body').animate({
          scrollTop: 0
        }, 500);
        bindPagerLink();
        showObjects();
        bindMltLink();
      });
      return false;
    });
  }

  function bindFacetLink() {
    $('.facets h2').unbind('click');
    $('.facets h2').click(function () {
      $(this).parent().find('.facet-content').slideToggle();
      $(this).parents('.facet-block').toggleClass('open');
    });
    $('.facets .facet-list a').unbind('click');
    $('.facets .facet-list a').click(function (e) {
      e.preventDefault();
      var link = $(this);
      link.addClass('ajax-loading');
      $.ajax({
        url: link.attr('href')
      }).success(function (html) {
        var selector = '.results';
        var selector2 = '.sorting';
        var selector3 = '.facets';
        var content = $(html).find(selector).html();
        var content2 = $(html).find(selector2).html();
        var content3 = $(html).find(selector3).html();
        $(selector).html(content);
        $(selector2).html(content2);
        $(selector3).html(content3);
        $('html, body').animate({
          scrollTop: 0
        }, 500);
        bindPagerLink();
        bindFacetLink();
        bindSeeMoreLink();
        showObjects();
        bindMltLink();
        bindSortingLinks();
      });
      return false;
    });
  }

  function bindMltLink() {
    $('.results li .more-like-this a').unbind('click');
    $('.results li .more-like-this a').click(function (e) {
      e.preventDefault();
      var link = $(this);
      if (link.parents('li').find('.more-like-this-container').size() == 0) {
        link.addClass('ajax-loading');
        $.ajax({
          url: link.attr('href')
        }).success(function (html) {
          if (html != '') {
            var mltContainer = $('<div class="more-like-this-container"></div>');
            mltContainer.html(html);
            mltContainer.prepend($('<h4>More like this</h4>'));
            link.parent().append(mltContainer);
          }
          link.removeClass('ajax-loading');
        });
      }
      else {
        link.parents('li').find('.more-like-this-container').slideToggle();
      }
    });
  }

  function autocomplete() {
    if (typeof __searchclient_params.autocomplete !== 'undefined' && __searchclient_params.autocomplete.field != '') {
      //alert(__searchclient_params.autocomplete.field + ' group by ' + __searchclient_params.autocomplete.group);
      var url = __searchclient_service_url + '/autocomplete?mapping=' + encodeURIComponent(__searchclient_mapping) + '&field=' + encodeURIComponent(__searchclient_params.autocomplete.field) + '&group=' + encodeURIComponent(__searchclient_params.autocomplete.group);// + '&text=' + encodeURIComponent($('.search-form input[name="query"]').val());
      var timeoutId = -1;
      var initialValue = $('.search-form input[name="query"]').val();
      var scrollTop = 0;
      $(window).click(function (e) {
        if ($(e.target).parents('#autocomplete-suggestions').length == 0 && $(e.target).parents('.search-form').length == 0) {
          if ($('#autocomplete-suggestions').length > 0) {
            $('.search-form input[name="query"]').val(initialValue);
            $('#autocomplete-suggestions').detach();
          }
        }
      });
      $('.search-form input[name="query"]').keydown(function (e) {
        if (e.key == 'ArrowDown' || e.key == 'ArrowUp') {
          e.preventDefault();
          if (e.key == 'ArrowDown') {
            if ($('#autocomplete-suggestions li.active').length == 0) {
              $('#autocomplete-suggestions a.autocomplete-link').first().parent().addClass('active');
            }
            else {
              var current = $('#autocomplete-suggestions li.active');
              if (current.next().length > 0) {
                current.next().addClass('active');
              }
              else {
                if (current.parents('li').next() != null) {
                  current.parents('li').next().find('a.autocomplete-link').first().parent().addClass('active');
                }
              }
              current.removeClass('active');
            }
          }
          else {
            if ($('#autocomplete-suggestions li.active').length == 0) {
              $('#autocomplete-suggestions a.autocomplete-link').last().parent().addClass('active');
            }
            else {
              var current = $('#autocomplete-suggestions li.active');
              if (current.prev().length > 0) {
                current.prev().addClass('active');
              }
              else {
                if (current.parents('li').prev() != null) {
                  current.parents('li').prev().find('a.autocomplete-link').last().parent().addClass('active');
                }
              }
              current.removeClass('active');
            }
          }
          if ($('#autocomplete-suggestions li.active').length > 0) {
            var offset = $('#autocomplete-suggestions li.active').offset().top - $('#autocomplete-suggestions').offset().top;
            var height = $('#autocomplete-suggestions').height();
            if (offset < 0) {
              scrollTop = 0;
            }
            else if (offset + $('#autocomplete-suggestions li.active').outerHeight() > height) {
              scrollTop += offset - height + $('#autocomplete-suggestions li.active').outerHeight();
            }
            $('#autocomplete-suggestions').scrollTop(scrollTop);
            $('.search-form input[name="query"]').val($('#autocomplete-suggestions li.active').text());
          }
          else {
            $('.search-form input[name="query"]').val(initialValue);
          }
          return false;
        }
        else if (e.key == 'Escape' || e.key == 'Tab') {
          $('.search-form input[name="query"]').val(initialValue);
          $('#autocomplete-suggestions').detach();
        }
        else {
          initialValue = $('.search-form input[name="query"]').val();
          if (timeoutId >= 0) {
            clearTimeout(timeoutId);
          }
          if ($('.search-form input[name="query"]').val() != '') {
            timeoutId = setTimeout(function () {
              $.ajax({
                url: url + '&text=' + encodeURIComponent($('.search-form input[name="query"]').val())
              }).success(function (data) {
                $('#autocomplete-suggestions').detach();
                var autocomplete = $('<div id="autocomplete-suggestions" class="' + (data.grouped ? 'grouped' : 'not-grouped') + '"></div>');
                var query = $('.search-form input[name="query"]');
                autocomplete.width(query.outerWidth());
                autocomplete.css('max-height', '300px');
                autocomplete.css('top', query.position().top + query.outerHeight() + 'px');
                autocomplete.css('left', query.position().left + 'px');
                autocomplete.css('position', 'absolute');
                autocomplete.css('overflow', 'auto');
                autocomplete.css('background', '#fff');
                autocomplete.css('border', '1px solid #ccc');
                $('body').append(autocomplete);
                var html = '<ul>';
                var count = 0;
                if (data.grouped) {
                  for (var group in data.results) {
                    html += '<li><div class="group">' + group + '</div>';
                    if (data.results[group].length > 0)
                      html += '<ul>';
                    for (var i = 0; i < data.results[group].length; i++) {
                      html += '<li><a href="#" class="autocomplete-link">' + data.results[group][i] + '</a></li>';
                      count++;
                    }
                    if (data.results[group].length > 0)
                      html += '</ul>';
                    html += '</li>'
                  }
                }
                else {
                  for (var i = 0; i < data.results.length; i++) {
                    html += '<li><a href="#" class="autocomplete-link">' + data.results[i] + '</a></li>';
                    count++;
                  }
                }
                html += '</ul>';
                autocomplete.append($('<div class="inside">' + html + '</div>'));
                autocomplete.find('a.autocomplete-link').click(function (e) {
                  e.preventDefault();
                  $('.search-form input[name="query"]').val($(this).text());
                  $('#autocomplete-suggestions').detach();
                  $('.search-form input[name="query"]').parents('form').submit();
                });
                if (count == 0) {
                  $('#autocomplete-suggestions').detach();
                }
                $('#autocomplete-suggestions').mark(query.val());
              });
            }, 500);
          }
          else {
            $('#autocomplete-suggestions').detach();
          }
        }
      });
    }
  }

})(jQuery);