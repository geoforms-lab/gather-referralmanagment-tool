/*
 * Not Used
 */

var LayerGroupLegend=(function(){


    var LayerGroupLegend=new Class()



    LayerGroupLegend.FormatLegend=function(group, mouseover, element, legend){



            
            
                element.addClass(group /*"'.$groupName.($i>3?' bottom-align':'').'"*/);
                LegendHelper.addLegend(legend);
                element.addEvent("click", function(e){
                    if(e.target==element){
                        legend.toggle();
                    }
                });
                var p= new UIPopover(element, {description:mouseover, anchor:UIPopover.AnchorTo(["right"])});
                
                legend.addEvent("toggle",function(){
                    p.hide();
                });
                var checkState=function(){
                   
                   if(legend.countVisibleLayers()==0){
                       element.removeClass("active");
                   }else{
                        element.addClass("active");
                   }
                   
                    if(legend.countVisibleLayers()==legend.countLayers()){
                       element.addClass("all");
                   }else{
                       element.removeClass("all")
                   }
                   
                    
                };
                checkState();
                legend.addEvent("renderLayer", checkState);
                legend.addEvent("change",checkState);
                
                element.appendChild(new Element("span", {
                    "class":"indicator-switch",
                    "events":{
                        "click":function(){
                           var layers=legend.getLayers();
                         
                               if(legend.countVisibleLayers()>0){
                              
                                layers.forEach(function(layer){
                                   layer.hide();
                               });
                              
                              
                                return;     
                               }
                         
                           
                           layers.forEach(function(layer){
                               layer.show();
                           });
                          
                           
                        }
                    }
                }));

                if (!(AppClient.getUserType() == "admin" || ProjectTeam.CurrentTeam().getUser(AppClient.getId()).isTeamManager())) {
                    return;
                }
               
                
                var formName=group+"UploadForm";
                setTimeout(function(){
                    
                    
                    var application=ReferralManagementDashboard.getApplication()
                    if(application.getDisplayController().hasNamedFormView(formName)){
                        
                        
                        var GroupUpload=new Class({
                            Extends: DataTypeObject,
                            Implements:[Events],
                            getDescription:function(){return "";},
                            setDescription:function(d){
                                console.log(d);
                                var me=this;
                                me.file=Proposal.ParseHtmlUrls(d);
                            },
                            save:function(cb){
                                
                                var me=this;
                                var AddDocumentQuery = new Class({
                                    Extends: AjaxControlQuery,
                                    initialize: function() {
                                        this.parent(CoreAjaxUrlRoot, "upload_tus", Object.append({
                                            plugin: "ReferralManagement"
                                        }, {data:me.file||null}));
                                    }
                                });
                                (new AddDocumentQuery).addEvent("success", function(){
                                    cb(true)
                                }).execute();
                            }
                        });
                      
                        var button=legend.element.appendChild(new Element("button",{"class":"grp-layer-upload"}));
                        new UIModalFormButton(
                            button, 
                            application, new GroupUpload(), 
                            {
                                formName:formName, 
                                formOptions:{template:"form"}
                                
                            }
                        )
                    }
                
                }, 1000);

            



    }


    LayerGroupLegend.ShouldShowLayerScript=function(group, lid, layer){
        
        if(LayerGroupItemList.getLayerGroupItems(group).map(function(l){return l.getId();}).indexOf(lid)>=0){
            return true;
        }

        return layer.options.group===group;
    };

    return LayerGroupLegend;


})();




 
