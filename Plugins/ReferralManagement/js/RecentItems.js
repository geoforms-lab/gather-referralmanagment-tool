var RecentItems = (function() {


	var MockEventDataTypeItem=new Class({
				Extends:MockDataTypeItem,
				Implements:[Events]

		});


	var RecentItems = new Class({
		Extends: DataTypeObject,
		Implements: [Events],
		initialize: function(config) {
			this._label = config.label || "Recent Items";
			this._list = config.list || []
		},
		getLabel: function() {
			return this._label;
		},


		setListData:function(data, filter){

			

			var me=this;

			this._listData=data.filter(function(item){
				if(filter){
					return item.text.indexOf(filter)>=0;
				}
				return true;
			}).map(function(item){
				return new MockEventDataTypeItem({
					name:me.formatEventText(item.text, item),
					creationDate:item.createdDate,
					data:item
				});
			});

		},

		formatEventText:function(text, data){





			if(text.indexOf('event:')===0){
				text=text.split(':').slice(1).join(':');
			}


			if (ProjectTeam.CurrentTeam().hasUser(data.user)) {

				var userName = ProjectTeam.CurrentTeam().getUser(data.user).getName();
				text=userName+text;

				text=text.replace('update.','updated.')
				text=text.replace('create.','created.')

			}

			if(data.metadata.items&&data.metadata.items.length){
				data.metadata.items.forEach(function(dataItem){

					if(dataItem.type=="User"){
						if (ProjectTeam.CurrentTeam().hasUser(dataItem.id)) {
							var targetUserName= ProjectTeam.CurrentTeam().getUser(dataItem.id).getName();
							text+=' for: '+targetUserName;
						}
					}

					if(dataItem.type=="ReferralManagement.proposal"){
						if (ProjectTeam.CurrentTeam().hasProject(dataItem.id)) {
							var targetUserName= ProjectTeam.CurrentTeam().getProject(dataItem.id).getName();
							text+=' for: '+targetUserName;
						}
					}
				})
			}


			text=text.replace('proposal', 'project');
			text=text.split('.').join(' ');


			return text;
		},
		getList: function(application, callback) {

			if(this._listData){
				callback(this._listData.slice(0));
				return;
			}

			ProjectTeam.CurrentTeam().runOnceOnLoad(function(team) {
				var proposals = team.getProposals();
				if (!application.getNamedValue("currentProject")) {
					application.setNamedValue("currentProject", proposals[0]);
				}
				callback(proposals.reverse())
			})

			return null;


			return this._list;
		},



	});



	RecentItems.colorizeItemEl=function(item, view){

		if(item instanceof Proposal){
			var type=item.getProjectType();

			if((!type)||type==""){
				return;
			}
			type=type.toLowerCase();

			var colors={
				"forestry":"#88ed88",
				"mining":"#f1ee40",
				"energy":"#6ab1ff",
				"roads":"#c8c8c8"
			};


			ReferralManagementDashboard.getProjectTagsData().filter(function(tag){
				if(tag.getName().toLowerCase()==type){
					view.getElement().setStyles({
					"background-color":tag.getColor()
					});
				}
			});
			// if(colors[type]){
			// 	view.getElement().setStyles({
			// 		"background-color":colors[type]
			// 	});
			// }



			view.getElement().setAttribute('data-project-type',type);

		}

	};

	RecentItems.getClassForItem=function(item){

	};
	RecentItems.setClassForItemEl=function(item, view){
		view.getElement().addClass('some-color-'+Math.round(Math.random()*4));
	};

	RecentItems.getIconForItem=function(item){

	};

	RecentItems.setIconForItemEl=function(item, element){


		if(item instanceof MockEventDataTypeItem){

			var data=item.getData();
			var modules=[];
			if(data.metadata.items&&data.metadata.items.length){
				data.metadata.items.forEach(function(dataItem){
					if(dataItem.type=="User"){
						var mod=ReferralManagementDashboard.createUserIcon(new MockEventDataTypeItem({userId:dataItem.id}));
						if(mod){
							modules.push(mod);
						}
					}
				});
			}

			if(modules.length==0){
				var mod=ReferralManagementDashboard.createUserIcon(new MockEventDataTypeItem({userId:data.user}));
				if(mod){
					modules.push(mod);
				}
			}

			modules.forEach(function(mod){
				mod.load(null, element, null);
			});

		//

		}

	};

	RecentItems.handleClickForItem=function(item){

		
	};


	RecentItems.RecentProjectActivity = new RecentItems({
		label: "Recent projects activity"
	});
	RecentItems.RecentActivity = new RecentItems({
		label: "Recent activity"
	});
	RecentItems.RecentUserActivity = new RecentItems({
		label: "Recent user activity"
	});



	return RecentItems;



})()