(function($){
  $(document).ready(function(){
    bindSortingLinks();
    bindSeeMoreLink();
    bindPagerLink();
    bindFacetLink();

    showObjects();
    bindMltLink();
    sizeElements();
    $(window).load(sizeElements);
    $(window).resize(sizeElements);

  });

  function sizeElements(){
    var topHeight = $('.search-form').height();
    $('main').css('margin-top', topHeight);
    $('.sidebar').css('top', topHeight);
    $('.sidebar').height($(window).height() - topHeight);
  }

  function showObjects(){
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

  function bindSeeMoreLink(){
    $('.see-more a').unbind('click');
    $('.see-more a').click(function(e){
      e.preventDefault();
      var link = $(this);
      link.addClass('ajax-loading');
      $.ajax({
        url: link.attr('href')
      }).success(function(html){
        var selector = '#' + link.parents('.facet-block').attr('id') + ' .facet-content';
        var content = $(html).find(selector).html();
        $(selector).html(content);
        bindSeeMoreLink();
        bindFacetLink();
      });
      return false;
    });
  }

  function bindSortingLinks(){
    $('.sorting a').unbind('click');
    $('.sorting a').click(function(e){
      e.preventDefault();
      var link = $(this);
      link.addClass('ajax-loading');
      $.ajax({
        url: link.attr('href')
      }).success(function(html){
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

  function bindPagerLink(){
    $('.pager a').unbind('click');
    $('.pager a').click(function(e){
      e.preventDefault();
      var link = $(this);
      link.addClass('ajax-loading');
      $.ajax({
        url: link.attr('href')
      }).success(function(html){
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
    $('.facets h2').click(function(){
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

  function bindMltLink(){
    $('.results li .more-like-this a').unbind('click');
    $('.results li .more-like-this a').click(function(e){
      e.preventDefault();
      var link = $(this);
      if(link.parents('li').find('.more-like-this-container').size() == 0) {
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
      else{
        link.parents('li').find('.more-like-this-container').slideToggle();
      }
    });
  }

})(jQuery);