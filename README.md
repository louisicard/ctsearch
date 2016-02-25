CtSearch : Get the best out of Elastic Search
=============================================

CtSearch is a federated search engine build upon [Elastic Search][1].
Default login/password :
Username : admin
Password : adminpass

CtSearch Recommendation engine
------------------------------
CtSearch comes with a recommendation engine.
Javascript code to include in your pages :
```html
<script>
  !function(e,c,n,t,r,o,a){e[r]=function(c,n){e[r+"_"+c]=n},o=c.createElement(n),
    a=c.getElementsByTagName(n)[0],o.src=t,a.parentNode.insertBefore(o,a)}
  (window,document,"script","//CTSEARCH_ROOT/reco/reco.js","regReco"); //Replace CTSEARCH_ROOT by the root path to your ctsearch installation

  regReco('id', 'DOC_ID'); //Doc ID of the elastic search document currently displayed
  regReco('target', 'INDEX.MAPPING'); //Index name and mapping like my_index.my_mapping
  regReco('callback', 'myRecoCallback'); //The name of the js function to callback for displaying recommended documents
  function myRecoCallback(data){
    console.log(data);
  }
</script>
```


[1]:  https://www.elastic.co
