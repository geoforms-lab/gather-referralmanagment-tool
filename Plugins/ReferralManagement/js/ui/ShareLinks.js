var ShareLinkItem=(function(){


	var ShareLinkItem = new Class_({
		Extends:MockDataTypeItem,
		remove:function(){

			(new AjaxControlQuery(CoreAjaxUrlRoot, 'delete_share_link', {
				'plugin': "ReferralManagement",
				'id':this.getId(),
				'token':this.getToken()
			})).execute();


		}
	});

	return ShareLinkItem;

})()


var ShareLinks=(function(){


	




	var ShareLinks=new Class_({

		render:function(item){


	        var link = new ElementModule('p', {
	            //html:'<a id="theShareLink" style="color:mediumseagreen;" target="_blank" href=""></a>'
	        });


	        var linkEl=new Element('a', {
	        	style:"color:mediumseagreen;",
	        	target:"_blank",
	        	href:""
	        });

	        link.getElement().appendChild(linkEl);



			var button= new ElementModule('button', {
			    "class":"form-btn primary-btn share",
			    'html':"Create share link",
			    events:{click:function(){
			        
			        (new AjaxControlQuery(CoreAjaxUrlRoot, 'generate_share_link', {
						'plugin': "ReferralManagement",
						'id':item.getId()
					})).addEvent('success', function(resp){
					    
					    linkEl.href=resp.link;
					    linkEl.innerHTML=resp.link;
					    
					    console.log(resp);
					    
					}).execute();
			    
			    }}
			});

			return [link, button];


		}


	})


	return ShareLinks;


})()