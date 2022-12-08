var  DisplayTheme=(function(){

	var DisplayTheme=new Class_({


		/**
		 * TODO move dashboard loader `myTheme` code here`
		 */


		start:function(){
			this.setMode(localStorage.getItem('mode'));

			var me=this;
			theGooglemapMapLoader.on('displayMap', function(map){
	        	map.getDisplayController().setOptions(me._getFormOptions());
	        });

		},



		setDefaults:function(values, mainView){

			mainView.options.className+=" "+values.pageClassNames;
			if(values.darkMode){
				if(localStorage.getItem('mode')!='light'){
					mainView.options.className+=" dark";

					if(mainView.runOnceOnLoad(function(){
						mainView.getElement().addClass('dark');
					}));

					localStorage.setItem('mode','dark');
				}
			}

		},

		_getFormOptions:function(){


			var mode=this.getMode();


			var classNames=(mode=="dark"?" dark ":"")+DashboardConfig.getValue('pageClassNames');
			var formClassNames=this.getInvertsForms()?((mode=="dark"?"":" dark ")+DashboardConfig.getValue('pageClassNames')):classNames;
			var application = GatherDashboard.getApplication();
			return {
	            popoverOptions:{
	                parentClassName:formClassNames
	            }
	        };

		},

		getMode:function(){
			var el = $$('.ui-view.dashboard-main')[0];
			return  el.hasClass('dark') ? 'dark' : 'light';
		},

		getInvertsForms:function(){
			return localStorage.getItem('invert-forms')==="true";
		},
		setInvertForms:function(bool){
			localStorage.setItem('invert-forms', bool);

			var application = GatherDashboard.getApplication();
			application.getDisplayController().setOptions(this._getFormOptions());

		},


		hasBackgroundImage:function(){
			return DashboardConfig.getValue('backgroundImage').length>0;
		},

		showsBackgroundImage:function(){
			return this.hasBackgroundImage()&&localStorage.getItem('show-background')!=="false";
		},

		setShowsBackgroundImage:function(bool){
			localStorage.setItem('show-background', bool);

			var el = $$('.ui-view.dashboard-main')[0];
			if(bool){
				el.removeClass('no-bg');
			}else{
				el.addClass('no-bg');
			}
		},



		setMode:function(mode) {

			

			if (mode !== 'light' && mode !== 'dark') {
				mode = this.getMode();
			}

			localStorage.setItem('mode', mode);


			var classNames=(mode=="dark"?" dark ":"")+DashboardConfig.getValue('pageClassNames');
			var formClassNames=this.getInvertsForms()?((mode=="dark"?"":" dark ")+DashboardConfig.getValue('pageClassNames')):classNames;
			var application = GatherDashboard.getApplication();


			var formOptions=this._getFormOptions();
			application.getDisplayController().setOptions(formOptions);
	        
			theGooglemapMapLoader.getMaps().forEach(function(map){
				map.getDisplayController().setOptions(formOptions);
			});


	        NotificationBubble.SetOptions({
	            className:classNames
	        });
	       

	        var me=this;
	        UIPopover.SetOptions({
	             className:function(){

	             	if(me.getInvertsForms()){
	             		var el=this.element;
	             		while(el.parentNode!==document.body){
	             			el=el.parentNode;
	             		}
	             		if(el.hasClass('pb-w')){
	             			return formClassNames;
	             		}
	             	}

	             	return classNames;
	             }
	        });


	        var el = $$('.ui-view.dashboard-main')[0];
			if (mode === 'dark') {
				el.addClass('dark');
				return;
			}
			el.removeClass('dark');

		}


	});
	return new DisplayTheme();


})();
