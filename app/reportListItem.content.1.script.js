var reportBtn=new ElementModule('button',{"identifier":"button-report", "html":"Create report", "class":"primary-btn report", "events":{"click":function(){
    
        var exportQuery=new AjaxControlQuery(CoreAjaxUrlRoot, 'generate_report', {
    		                "plugin": "ReferralManagement",
    		                "project":item.getProject(),
    		                "template":item.getName()
    		            });
        				//exportQuery.execute(); //for testing.
        				window.open(exportQuery.getUrl(true),'Download'); 
    
    }}})
    
    
return reportBtn