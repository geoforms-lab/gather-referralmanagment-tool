
if(item instanceof ProjectList&&item.getLockFilter){
   var filter=item.getLockFilter();
   if(filter&&filter.length==1&&ProjectTagList.getProjectTagsData(filter[0]).length>0){
        module.draw();
   }
}