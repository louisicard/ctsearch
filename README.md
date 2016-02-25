CtSearch : Get the best out of Elastic Search
=============================================

CtSearch is a federated search engine build upon [Elastic Search][1]

CtSearch Recommendation engine
------------------------------
CtSearch comes with a recommendation engine.
Javascript code to include in your pages :
```html
<script>
  !function(e,c,n,t,r,o,a){e[r]=function(c,n){e[r+"_"+c]=n},o=c.createElement(n),
    a=c.getElementsByTagName(n)[0],o.src=t,a.parentNode.insertBefore(o,a)}
  (window,document,"script","//ctsearch.lan/app_dev.php/reco/reco.js","regReco");

  regReco('id', '<?php print $notice_id;?>');
  regReco('target', 'cud.notice');
  regReco('callback', 'cudGetReco');
  function cudGetReco(data){
    console.log(data);
  }
</script>
```


[1]:  https://www.elastic.co
