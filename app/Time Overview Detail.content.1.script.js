







var chart= new BarChartModule({data:function(callback){
    
    //TODO: get team data
    var data;
    ProjectTeam.CurrentTeam().runOnceOnLoad(function(team){
        data=ReferralManagementDashboard.projectActivityChartData(team, application);
        
    
        
    
   
        chart.addEvent('load', function(){
           var nav= chart.getElement().appendChild(new Element('span', {"class":"nav"}));
           nav.appendChild(new Element('button',{"class":"prev-btn", events:{
               click:function(){
                   console.log(data[0]);
                   data=ReferralManagementDashboard.projectActivityChartData(team, application, {
                       endAt:data[0].day
                   });
                   chart.redraw(data);
               }
           }}));
           nav.appendChild(new Element('button',{"class":"next-btn", events:{
               click:function(){
                   console.log(data[data.length-1]);
                   data=ReferralManagementDashboard.projectActivityChartData(team, application, {
                       startAt:data[data.length-1].day
                   });
                   chart.redraw(data);
               }
           }}));
        });
        
        callback(data);
        
        
    
    });
    
}});

ProjectTeam.CurrentTeam().runOnceOnLoad(function(team){
    chart.addWeakEvent(team, "tasksChanged",function(){
        chart.redraw();
    });
});

return chart;



