
list.content.push(new ElementModule('button',{
    "class":"remove-btn",
    events:{click:function(){
        if(confirm("Are you sure")){
            alert("do");
            return;
        }
        alert("don't");
    }}
}))
list.content.push((new ModalFormButtonModule(application, listItem,{
        label:"Edit",
        formName:"fileItemForm",
        formOptions:{
            template:"form"
        },
        hideText:true,
        "class":"edit-btn"
    })).addEvent("show",function(){
        
    }))

return list