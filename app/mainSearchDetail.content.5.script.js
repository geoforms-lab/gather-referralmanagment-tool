
var fitSticky=function(){
    
   var bar = $$('.ui-view.search-panel')[0];
   var frame=$$('.ui-view.main-panel')[0];
   
   if(bar&&frame){
       bar.setStyle('width', frame.getSize().x+'px');
   }
    
}
window.addEvent('resize', fitSticky);
fitSticky();