var MainNavigationMenuBase = new Class({
	Extends: NavigationMenuModule,
	initialize: function(menu, application) {

		this.parent(menu, {

			cloneStyle:{
				left:0,
				opacity:0.5
			},
			"class": "-collapsable-menu-disabled",
			targetUIView: function(button, section, viewer, callback) {

				/**
				 * TODO sometimes view is not rendered when this is called!
				 */
				
				var getTarget=function(){
					callback(viewer.getApplication().getChildView('content', 0).getChildView('content', 1).getChildView('content', DashboardConfig.getValue('showSearchMenu')?1:0));
				}

				try{
					getTarget();
					return;
				}catch(e){
					console.error(e);
				}


				var interval=setInterval(function(){

					try{
						getTarget();
						clearInterval(interval);
					}catch(e){
						console.error(e);
					}

				}, 250);

			},
			templateView: function(button, section) {
				return button.template || button.view || (section.toLowerCase() + (button.name || button.html) + "Detail");
			},
			buttonClass: function(button, section) {
				return button["class"] || ("menu-" + section.toLowerCase() + "-" + (button.name || button.html).toLowerCase())
			},
			sectionClass: function(section) {
				return "menu-" + section.toLowerCase()
			},
			// formatSectionLabel:function(section, labelEl){
			//     if(section==='People'){
			//         return 'Team';
			//     }
			// },
			initialView: {
				view: "Dashboard",
				section: "Main"
			}
		});

		this.application = application;



	}
});

var MainNavigationMenu = new Class({
	Extends: MainNavigationMenuBase,
	initialize: function(application) {
		MainNavigationMenuBase.prototype.initialize.call(this, null, application);
	},

	process: function() {

		var me = this;
		var application = this.application;

		if (me.menu) {
			MainNavigationMenuBase.prototype.process.call(this);
			return;
		}

		var navigationController = this;

		DashboardConfig.runOnceOnLoad(function(dashConfig, config) {



			me.menu = Object.append({
				"Main": [{
					html: "Dashboard",
				}, {
					html: "Projects",
					template:"mainProjectsDetail",
					events:{
						click:function(){
							DashboardConfig.getValue('showSplitProjectDetail', function(split) {

								if(!split){
									application.setNamedValue("currentProject", null);
								}


					 			navigationController.navigateTo('Projects','Main');
							});

						}
						
					},
					formatEl: function(li) {
						ProjectTeam.CurrentTeam().runOnceOnLoad(function(team) {

							var setCounter = function() {
								var l = team.getProjects().length;

								li.setAttribute('data-counter', l);


								DashboardConfig.getValue("enableTasks", function(enabled){
									if(!enabled){
										return;
									}
									li.setAttribute('data-counter-complete', team.getProjects().filter(function(p) {
										return p.isComplete();
									}).length + '/' + l)

									li.addClass('has-progress')
								})

								
								if (l > 0) {
									li.addClass('has-items')
								} else {
									li.removeClass('has-items')
								}
							}

							setCounter();
							navigationController.addWeakEvent(team, 'addProject', setCounter);
							navigationController.addWeakEvent(team, 'assignUser', setCounter);
							navigationController.addWeakEvent(team, 'removeProject', setCounter);
							navigationController.addWeakEvent(team, 'projectStatusChanged', setCounter);

						});

					},
					urlComponent: function(stub, segments) {

						var current = application.getNamedValue("currentProject");


						if (segments && segments.length && segments[0].indexOf('Project-') === 0) {

							if (current) {

								if ('Project-' + current.getId() !== segments[0]) {
									console.warn('should set current');
									try {
										var team = ProjectTeam.CurrentTeam()
										current = team.getProject(parseInt(segments[0].split('-').pop()));
										application.setNamedValue("currentProject", current);
									} catch (e) {
										console.error(e);
									}
								}
								return 'Projects/' + segments.join('/');
							}

							ProjectTeam.CurrentTeam().runOnceOnLoad(function(team) {

								current = team.getProject(parseInt(segments[0].split('-').pop()));
								application.setNamedValue("currentProject", current);
								navigationController.navigateTo('Projects', 'Main', {
									segments: segments
								});

							});

						}

						if (!current) {
							return stub;
						}

						return 'Projects/Project-' + current.getId()
					}
				}, 
				{
					html: "Datasets",
					alias: {"section":"Main", "button":"Projects", "useClassNames":true, "mirrorActive":true},
					item:{
						label:"Datasets",
					    showCreateBtn:true,
					    lockFilter:"!collection",
					    filter:null,
					    invertfilter:false
					}
				},
				{
					html: "Collections",
					alias: {"section":"Main", "button":"Projects", "useClassNames":true, "mirrorActive":true},
					item:{
						label:"Collections",
					    showCreateBtn:true,
					    lockFilter:"collection",
					    filter:null,
					    invertfilter:false
					}
				},
				{
					html: "Project",
					"class":"hidden",
					template: "documentProjectDetail",
					events:{
						// click:function(){
						// 	navigationController.navigateTo('Projects','Main');
							
						// },
						navigate:function(){
							navigationController.setActive('Projects','Main');
						}
					},
					urlComponent: function(stub, segments) {

						var current = application.getNamedValue("currentProject");


						if (segments && segments.length && segments[0].indexOf('Project-') === 0) {

							if (current) {

								if ('Project-' + current.getId() !== segments[0]) {
									console.warn('should set current');
									try {
										var team = ProjectTeam.CurrentTeam()
										current = team.getProject(parseInt(segments[0].split('-').pop()));
										application.setNamedValue("currentProject", current);
									} catch (e) {
										console.error(e);
									}
								}
								return 'Project/' + segments.join('/');
							}

							ProjectTeam.CurrentTeam().runOnceOnLoad(function(team) {

								current = team.getProject(parseInt(segments[0].split('-').pop()));
								application.setNamedValue("currentProject", current);
								navigationController.navigateTo('Project', 'Main', {
									segments: segments
								});

							});

						}

						if (!current) {
							return stub;
						}

						return 'Projects/Project-' + current.getId()
					}
				}, {
					html: "Messages",
					name: "Messages"
				},{
					html: "Documents",
					name: "Documents"
				},{
					html: "Notifications",
					name: "Notifications",
					class:"hidden"

				},{
					html: "Tracking",
					name: "TimeTracking"
				}, {
					html: "Users",
					template: "usersCombinedDetail"
				},{
					html: "User",
					class:"hidden",
					template: "userProfileDetail",
					events:{
						
						navigate:function(){
							navigationController.setActive('Users','Main');
						}
					},
				}, {
					html: "Department",
					template: "departmentsDetail"
				}, {
					html: "Tags",
					template: "tagsDetail"
				}, {
					html: "Tasks",
					"class":"hidden",
					formatEl: function(li) {
						ProjectTeam.CurrentTeam().runOnceOnLoad(function(team) {

							var setCounter = function() {
								var l = team.getTasks().length;
								li.setAttribute('data-counter', l);
								li.setAttribute('data-counter-complete', team.getTasks().filter(function(t) {
									return t.isComplete();
								}).length + '/' + l)

								if (l > 0) {
									li.addClass('has-items')
								} else {
									li.removeClass('has-items')
								}
							}

							setCounter();
							navigationController.addWeakEvent(team, 'addTask', setCounter);
							navigationController.addWeakEvent(team, 'assignUser', setCounter);
							navigationController.addWeakEvent(team, 'removeTask', setCounter);

						});

					}
				}, {
					html: "Calendar",
					urlComponent: function() {

						var todayStr = (new Date()).toISOString().split('T')[0];

						if (!application.getNamedValue("selectedDay")) {
							application.setNamedValue("selectedDay", todayStr);
						}

						var dayStr = application.getNamedValue("selectedDay");
						return 'Calendar/' + (dayStr === todayStr ? "Today" : dayStr);
					},
				}, {
					html: "Activity",
				}, {
					html: "Map",
				},{
					html: "Reports",
					name: "Reports"
				}, {
					html: "Archive",
					template: "configurationArchiveDetail"
				}
				// {
				// 	html: "Trash"
				// }
				],

				"Referrals":[
					{
						html: "Projects",
						alias: {"section":"Main", "button":"Projects", "useClassNames":true, "mirrorActive":true},
					},
					{
						html: "Projects",
						alias: {"section":"Main", "button":"Tasks", "useClassNames":true, "mirrorActive":true},
					},
					{
						html: "Documents",
						alias: {"section":"Main", "button":"Documents", "useClassNames":true, "mirrorActive":true},
					},
					{
						html: "Tracking",
						alias: {"section":"Main", "button":"TimeTracking", "useClassNames":true, "mirrorActive":true},
					},
					{
						html: "Reports",
						alias: {"section":"Main", "button":"Reports", "useClassNames":true, "mirrorActive":true},
					},
					{
						html: "Archive",
						alias: {"section":"Main", "button":"Archive", "class":"menu-main-archive", "mirrorActive":true},
					},
					{
						html: "Import",
						events:{
						
							click:function(){
								
								(new UIModalDialog(application, new MockDataTypeItem({
									description:''
								}), {
		                		"formName":"importProjectsForm", "formOptions":{template:"form"}})).show();


							}
						},
					}



				],

				"People": [{
					html: "Projects",
					name: "ProjectMembers",
				}, {
					html: "Proponents",
					name: "Clients"
				}, 
				{
					html: "Users",
					name: "Members",
					alias: {"section":"Main", "button":"Users", "useClassNames":true, "mirrorActive":true},
				},
				{
					name: "Users",
					html: "Team",
					urlComponent: function() {
						return 'Team';
					},
					formatEl: function(li) {
						ProjectTeam.CurrentTeam().runOnceOnLoad(function(team) {

							var setCounter = function() {
								team.getUsers(function(users) {



									var l = users.filter(function(u) {
										return true;
									}).length;
									li.setAttribute('data-counter', l);
									if (l > 0) {
										li.addClass('has-items')
									} else {
										li.removeClass('has-items')
									}
								})
							}

							setCounter();
							navigationController.addWeakEvent(team, 'userListChanged', setCounter);
							navigationController.addWeakEvent(team, 'addUser', setCounter);
							navigationController.addWeakEvent(team, 'assignUser', setCounter);
							navigationController.addWeakEvent(team, 'removeUser', setCounter);

						});

					}
				}, {
					template: "communityUsersDetail",
					"class": "menu-community-users",
					html: "Community",
					formatEl: function(li) {
						ProjectTeam.CurrentTeam().runOnceOnLoad(function(team) {

							var setCounter = function() {
								team.getAllUsers(function(users) {



									var l = users.filter(function(u) {
										var r = u.getRoles();
										return (!r) || r.length == 0 || r.indexOf('none') >= 0 || r.indexOf('community-member') >= 0;
									}).length;


									var n = users.filter(function(u) {
										var r = u.getRoles();
										return (!r) || r.length == 0 || r.indexOf('none') >= 0;
									}).length;

									li.setAttribute('data-counter', l + (n > 0 ? " (" + n + ")" : ""));

									if (l > 0) {
										li.addClass('has-items')
									} else {
										li.removeClass('has-items')
									}

									if (n > 0) {
										li.addClass('has-new-items')
									} else {
										li.removeClass('has-new-items')
									}

								})
							}

							setCounter();
							navigationController.addWeakEvent(team, 'userListChanged', setCounter);
							navigationController.addWeakEvent(team, 'addUser', setCounter);
							navigationController.addWeakEvent(team, 'assignUser', setCounter);
							navigationController.addWeakEvent(team, 'removeUser', setCounter);

						});

					}
				}, {
					template: "communityMobileDetail",
					"class": "menu-community-mobile",
					html: "Mobile",
					formatEl: function(li) {
						ProjectTeam.CurrentTeam().runOnceOnLoad(function(team) {

							var setCounter = function() {
								team.getActivatedDevices(function(users) {



									var l = users.filter(function(u) {
										var r = u.getRoles();
										return (!r) || r.length == 0 || r.indexOf('none') >= 0 || r.indexOf('community-member') >= 0;
									}).length;


									var n = users.filter(function(u) {
										var r = u.getRoles();
										return (!r) || r.length == 0 || r.indexOf('none') >= 0;
									}).length;

									li.setAttribute('data-counter', l + (n > 0 ? " (" + n + ")" : ""));

									if (l > 0) {
										li.addClass('has-items')
									} else {
										li.removeClass('has-items')
									}

									if (n > 0) {
										li.addClass('has-new-items')
									} else {
										li.removeClass('has-new-items')
									}

								})
							}

							setCounter();
							navigationController.addWeakEvent(team, 'deviceListChanged', setCounter);
							navigationController.addWeakEvent(team, 'addUser', setCounter);
							navigationController.addWeakEvent(team, 'assignUser', setCounter);
							navigationController.addWeakEvent(team, 'removeUser', setCounter);

						});

					}
				}],
				//   "Accounting":[
				//     {
				//       html:"Documents",
				//     },
				//     {
				//       html:"Timesheet",
				//     },
				//     {
				//       html:"Reports"
				//     }
				//   ]

				"Community": [{
						html: "Cultural",
					}, {
						html: "Transportation",
					}, {
						html: "Habitation"
					}, {
						html: "Environmental"
					}, {
						html: "Subsistence"
					}

				]

			}, (AppClient.getUserType() == "admin" ? {
				"Configuration": [{
					html: "Archive"
				}, {
					html: "Trash"
				}, {
					html: "Settings",
				}, {
					html: "Export",
					events: {
						click: function() {


							var exportQuery = new AjaxControlQuery(CoreAjaxUrlRoot, 'export_proposals', {
								'plugin': "ReferralManagement"
							});
							//exportQuery.execute(); //for testing.
							window.open(exportQuery.getUrl(true), 'Download');
						}

					}
				}]
			} : {}))

			me.menu=DashboardPageLayout.layoutMenu('mainMenu', me.menu);

			me.process();
			application.setNamedValue('navigationController', me);



		});


	}

});


var SettingsNavigationMenu=new Class({
	Extends: MainNavigationMenuBase,
	initialize: function(application) {

		MainNavigationMenuBase.prototype.initialize.call(this, null, application);
		this.options.activateFirstMenuItem=false;
		this.options.manipulateHistory=false;
	},
	process: function() {

		var me = this;
		var parent=this.parent;
		var application = this.application;
		application.getNamedValue('navigationController', function(mainMenu){

			mainMenu.runOnceOnLoad(function(){

				me.menu = {}; 


				if(mainMenu.hasView({view:"Settings", section:"Configuration"})){
					me.menu = {
						"Configuration": [{
							html: "Settings",
							alias: {"section":"Configuration", "button":"Settings", "mirrorActive":true, "menu":function(){
								return application.getNamedValue('navigationController');
							}},
						},{
							html: "Settings",
							name:"FixedSettings",
							alias: {"section":"Configuration", "button":"Settings", "mirrorActive":true, "menu":function(){
								return application.getNamedValue('navigationController');
							}},
						}]

					};



					

					var updateSticky=function(){
						var li0=me._buttons['Configuration']['Settings'];
						var li1=me._buttons['Configuration']['FixedSettings'];

						var p0=li0.getPosition();
						var p1=li1.getPosition();
						if(p0.y>p1.y){
							if(li0.hasClass('bottom')){
								return;
							}
							li0.addClass('bottom');
							li1.removeClass('bottom');
							return;
						}


						if(li1.hasClass('bottom')){
							return;
						}
						li0.removeClass('bottom');
						li1.addClass('bottom');

					};
					me.runOnceOnLoad(function(){
						updateSticky();
						setTimeout(updateSticky, 1000);
					});




					//me.menu=DashboardPageLayout.layoutMenu('mainMenu', me.menu);

					window.addEvent('resize', updateSticky);

				}

				MainNavigationMenuBase.prototype.process.call(me);
			});
		});
			
		

	}
		
});
