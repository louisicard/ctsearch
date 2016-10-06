(function($){
  $(document).ready(function(){
    bindSeeMoreLink();
    bindPagerLink();
    bindFacetLink();

    showObjects();
  });

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
        var selector = '#' + link.parents('.facet-block').attr('id');
        var content = $(html).find(selector).html();
        $(selector).html(content);
        bindSeeMoreLink();
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
          scrollTop: $(selector).offset().top
        }, 500);
        bindPagerLink();
        showObjects();
      });
      return false;
    });
  }

  function bindFacetLink() {
    $('.facets .facet-list a').unbind('click');
    $('.facets .facet-list a').click(function (e) {
      e.preventDefault();
      var link = $(this);
      link.addClass('ajax-loading');
      $.ajax({
        url: link.attr('href')
      }).success(function (html) {
        var selector = '.results';
        var selector2 = '.facets';
        var content = $(html).find(selector).html();
        var content2 = $(html).find(selector2).html();
        $(selector).html(content);
        $(selector2).html(content2);
        $('html, body').animate({
          scrollTop: $(selector).offset().top
        }, 500);
        bindPagerLink();
        bindFacetLink();
        bindSeeMoreLink();
        showObjects();
      });
      return false;
    });
  }

})(jQuery);