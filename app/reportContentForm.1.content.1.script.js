

    var application = GatherDashboard.getApplication();
 	var project = application.getNamedValue("currentProject");


    var mod=new ElementModule('div', {"class":"template-data width-2"});

    (new AjaxControlQuery(CoreAjaxUrlRoot, 'generate_report_data', {
		  "plugin": "ReferralManagement",
		  "project":project.getId()
    })).addEvent('success',function(resp){
        
        mod.getElement().appendChild(new Element('pre', {
            html:JSON.stringify(resp.data, null, '   ')
                 .replace(/&/g, "&amp;")
                 .replace(/</g, "&lt;")
                 .replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#039;")
        }));
        
    }).execute();
    
    
    
    return mod;
