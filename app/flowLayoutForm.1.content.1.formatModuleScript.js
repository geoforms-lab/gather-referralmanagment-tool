(new UIFormListBehavior(module)).setNewItemFn(function(){
    
    return new MockDataTypeItem({
       name:"",
       description:"",
       icon:"default",
       link:true
    });
}).setUpdateField(item.getFlow());