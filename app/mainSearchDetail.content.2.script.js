return new Element('div',{
    "class":"notifications",
    events:{click:function(){
        
        var controller = application.getNamedValue('navigationController');
				controller.navigateTo("Notifications", "Main");
        
    }}
})