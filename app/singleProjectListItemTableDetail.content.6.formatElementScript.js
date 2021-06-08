el.addClass("inline");


if(item.getDocumentsRecursive().concat(item.getAttachmentsRecursive()).concat(item.getSpatialDocumentsRecursive()).length>0){
    el.addClass('withItems');
}

if(item.getDocumentsChildren().concat(item.getAttachmentsChildren()).concat(item.getSpatialDocumentsChildren()).length>0){
    el.addClass('withChildItems');
}

el.setAttribute("data-col","attachments");

el.addEvent('click', function(e){
   e.stop();
   UIInteraction.navigateToProjectSection(item ,"Map");
    
});


if(el.hasClass('withItems')){
    el.appendChild(new Element('button',{
    "html":"", 
    "style":"", 
    "class":"download-link", 
    "events":{"click":function(){
    
        var downloadQuery=new AjaxControlQuery(CoreAjaxUrlRoot, 'download_files', {
		                "plugin": "ReferralManagement",
		                "proposal":item.getId()
		                });
    				//downloadQuery.execute(); //for testing.
    				window.open(downloadQuery.getUrl(true),'Download'); 

    }}}))
}