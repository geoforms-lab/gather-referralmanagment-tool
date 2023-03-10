var GatherDashboard = (function() {


	var _application = null;
	var _setApplication = function(app, callback) {
		
		if(!_application){
			_application = app;
			callback();
		}
		
	};



	var GatherDashboardClass = new Class_({
		Implements: [Events],

		getApplication: function(callback) {

			if (callback){

				if(!_application) {
					this.addEvent('load:once', function(){
						callback(_application);
					});
					return;
				}
				callback(_application);
			}

			return _application
		},

		setApplication:function(app){
			var me=this;
			_setApplication(app, function(){
				me.fireEvent('load');
			});
			return this;
		},


		getView: function(app, callback) {
			var me=this;
			_setApplication(app, function(){
				me.fireEvent('load');
			});

			app.getNamedValue('navigationController', function(controller) {
				var view = controller.getTemplateNameForView(controller.getCurrentView());
				callback(view);
			});

		},

		getProjectTags: function(item, callback) {
			return ProjectTagList.getSelectableProjectTags(item, callback);
		},


		getProjectTagsData: function(category) {
			return ProjectTagList.getProjectTagsData(category);
		},


		getCreatedByString: function(item) {


			var name = item.getProjectSubmitter();
			return name;

			return "unknown";

		},

		/**
		 * @deprecated

		 */
		getCommunitiesString: function(item) {


			return item.getCommunitiesSelectedString();


		},

		getDatesString: function(item) {

			var dates = {
				"submitted": item.getSubmitDate(),
				"expires on": item.getExpiryDate()||'--',
				//"deadline": item.getDeadlineDate()
			};



			return Object.keys(dates).map(function(k) {
				return k + ": " + dates[k];
				//return '<span data-type="'+k+'">'+dates[k]+'</span>';
			}).join(', ');

		},

		onSaveProfile: function(item, application) {

			var user = ProjectTeam.CurrentTeam().getUser(item.getId());

			if (user.getId() == AppClient.getId() && user.isUnassigned()) {

				(new UIModalDialog(application, "Your profile has been saved. An administrator must approve your account.", {
					"formName": "dialogForm",
					"formOptions": {
						"template": "form",
						"className": "alert-view",
						"showCancel":false,
						"closable":true,
						"labelForSubmit":"Update profile",
						"labelForCancel":"Cancel",
					}
				})).show();
			}

		},



		loadUserDashboardView: function(application) {

			//return;

			var currentView = 'dashboardLoader';
			var loadView = function(view) {

				if (currentView == view) {
					return;
				}

				if (currentView != 'dashboardLoader') {
					view = 'dashboardLoader';
				}


				currentView = view;
				application.getChildView('content', 0).redraw({
					"namedView": view
				});


			}



			var checkUserRole = function(team) {

				if (AppClient.getUserType() == "admin") {
					loadView("dashboardContent");
					return;
				}

				try {
					var user = team.getUser(AppClient.getId());
					if (user.isTeamMember()) {
						loadView("dashboardContent");

						return;
					}

					if (user.isCommunityMember()) {

						loadView("communityMemberDashboard")
						return;
					}

				} catch (e) {

				}
				return loadView('nonMemberDashboard');

			}
			ProjectTeam.CurrentTeam().runOnceOnLoad(function(team) {
				checkUserRole(team);
				team.addEvent('userListChanged:once', function() {
					checkUserRole(team);
				});
			})


		},


		
		currentTeamMemberSortfn: function(a, b) {

			var roles = ["tribal-council", "chief-council", "lands-department-manager", "lands-department", "community-member"];
			var cmp = roles.indexOf(a.getRoles()[0]) - roles.indexOf(b.getRoles()[0]);

			if (cmp == 0) {
				return a.getId() - b.getId();
			}

			return cmp;
		},



		currentTaskFilterFn: function(a) {
			return !a.isComplete();
		},
		currentTaskSortFn: function(a, b) {
			if (a.isPriorityTask()) {
				return -1;
			}
			if (b.isPriorityTask()) {
				return 1;
			}
			return 0;
		},
		taskFilterIncomplete: function(a) {
			return !a.isComplete();
		},
		taskSortPriority: function(a, b) {

			if (a.isComplete() !== b.isComplete()) {
				if (!a.isComplete()) {
					return -1;
				}
				return 1;
			}

			if (a.isPriorityTask() !== b.isPriorityTask()) {
				if (a.isPriorityTask()) {
					return -1;
				}
				return 1;
			}



			return (a.getDueDate() < b.getDueDate() ? -1 : 1);
		},
		taskFilters: function() {

			return [{
				label: "complete",
				filterFn: function(a) {
					return a.isComplete();
				}
			}, {
				label: "assigned to you",
				filterFn: function(a) {
					return a.isAssignedToClient();
				}
			}, {
				label: "overdue",
				filterFn: function(a) {
					return a.isOverdue();
				}
			}, {
				label: "starred",
				filterFn: function(a) {
					return a.isStarred();
				}
			}, {
				label: "priority",
				filterFn: function(a) {
					return a.isPriorityTask();
				}
			}];



		},



		taskSorters: function() {

			return [{
				label: "name",
				sortFn: function(a, b) {
					return (a.getName() > b.getName() ? 1 : -1);
				}
			}, {
				label: "date",
				sortFn: function(a, b) {
					return (a.getDueDate() > b.getDueDate() ? 1 : -1);
				}
			}, {
				label: "priority",
				sortFn: function(a, b) {
					return GatherDashboard.taskSortPriority(a, b);
				}
			}, {
				label: "complete",
				sortFn: function(a, b) {
					if (a.isComplete()) {
						return -1;
					}
					if (b.isComplete()) {
						return 1;
					}
					return 0;
				}
			}];

		},

		taskHighlightMouseEvents: function(tasks) {
			return ProjectCalendar.AddTaskHighlighter(tasks);
		},
		
		addChartNavigation: function(chart, initialData, item, application) {
			var data = initialData;
			var startDate = initialData[0].day;
			chart.addEvent('load', function() {
				var nav = chart.getElement().appendChild(new Element('span', {
					"class": "nav"
				}));
				nav.appendChild(new Element('button', {
					"class": "prev-btn",
					events: {
						click: function() {
							console.log(data[0]);
							data = GatherDashboard.projectActivityChartData(item, application, {
								endAt: data[0].day
							});
							chart.redraw(data);
						}
					}
				}));
				nav.appendChild(new Element('button', {
					"class": "next-btn",
					events: {
						click: function() {
							console.log(data[data.length - 1]);
							data = GatherDashboard.projectActivityChartData(item, application, {
								startAt: data[data.length - 1].day
							});
							chart.redraw(data);
						}
					}
				}));

				if (data[0].day.valueOf() != startDate.valueOf()) {
					nav.appendChild(new Element('button', {
						"class": "prev-btn",
						html: "Reset",
						styles: {
							width: 'auto',
							"background-image": "none"
						},
						events: {
							click: function() {
								console.log(data[0]);
								data = GatherDashboard.projectActivityChartData(item, application, {
									startAt: startDate
								});
								chart.redraw(data);
							}
						}
					}));
				}

			});


		},


		projectActivityChartData: function(item, application, options) {
			return ProjectActivityChart.ProjectActivityChartData(item, application, options);
		},



		createNavigationMenu: function(application) {
			return (new MainNavigationMenu(application));
		},
		createUserIcon:function(item, defaultIcon) {
			return UserIcon.createUserAvatarModule(item, defaultIcon);
		},


		logout:function(){


			var div=new Element('div');
			div.innerHTML="Signing out";
			var spinner = new Spinner(div, {
                width: 20,
                height: 20,
                color: 'rgba(255,255,255)',
                start: true
            });

			var notification=NotificationBubble.Make('', div, {
				autoClose:false,
				from:'top-center',
				position:window.getSize().y/2,
				className:"layer-loading signing-out"

			});

			AppClient.logout();
		},
		addLogoutBtn: function() {
			var me=this;
			return new Element('button', {
				"class": "primary-btn warn logout-btn",
				"html": "Log out",
				events: {
					"click": function() {
						me.logout();
					}
				}
			});
		},
		createProfileButtons: function(item) {
			var me = this;

			var items = [];

			var itemIsCurrentClient = item.getId() + "" == AppClient.getId() + "";


			var showLogoutButton=false;
			if (showLogoutButton&&itemIsCurrentClient) {

				items.push(
					me.addLogoutBtn()
				);

			}

			var isSiteAdmin=ProjectTeam.CurrentTeam().getUser(AppClient.getId()).isSiteAdmin();

			if ((!itemIsCurrentClient) && (AppClient.getUserType() === "admin" || (isSiteAdmin&&UserGroups.ClientCanEditUsersRoles(item) ))) {
				items.push(
					new Element('button', {
						"class": "primary-btn error",
						"html": "Delete",
						events: {
							"click": function() {
								if (confirm("Are you sure you want to delete this user")) {

									(new AjaxControlQuery(CoreAjaxUrlRoot, "delete_user", {
										'plugin': "Users",
										'user': item.getId()
									})).addEvent('success', function() {

									}).execute();

								};
							}
						}
					})
				);

				items.push(
					new Element('button', {
						"class": "primary-btn report",
						"html": "Impersonate",
						events: {
							"click": function() {
								if (confirm("Are you sure you want to impersonate this user")) {

									(new AjaxControlQuery(CoreAjaxUrlRoot, "impersonate", {
										'plugin': "Users",
										'user': item.getId()
									})).addEvent('success', function() {
										 window.location.reload();
									}).execute();

								};
							}
						}
					})
				);


			}



			if (items.length == 0) {
				return null;
			}


			var d = new ElementModule('div', {
				styles: {
					"display": "inline-table",
					"width": "100%",
					"border-bottom": "1px dotted #6A7CE9"
				}
			});

			items.forEach(function(b) {
				d.appendChild(b);
			});

			return d;


		},

		limitUserCommunityValues: function(module) {

			//modify tag cloud 

			var user = ProjectTeam.CurrentTeam().getUser(AppClient.getId());
			if (user.isUnassigned() || AppClient.getUserType() == "admin") {
				return;
			}

			module.runOnceOnLoad(function() {
				var cloud = module.getCloud();

				cloud.getElement().addClass('community locked');

				cloud.getWords().map(function(word) {
					return cloud.getWordElement(word);
				}).forEach(function(el) {
					el.removeEvents('click');

				});
			});
		},

		getLabelForManager: function() {
			return GatherDashboard.getLabelForUserRole('lands-department-manager');
		},
		getLabelForMember: function() {
			return GatherDashboard.getLabelForUserRole('lands-department');
		},
		getLabelForCommunityMember: function() {
			return 'Community Member';
		},


		getLabelForUserRole: function(role) {


			var roleLables = {

			};

			roleLables = DashboardConfig.getValue('roleLabels');

			if (roleLables[role]) {
				return roleLables[role];
			}

			return '' + (role.replace('-', ' ').capitalize()) + '';
		},


		/*
		 * @deprecated
		 */
		
		getUsersMobileDevicesDescription: function() {



			var title = '';


			var text = 'You can download the Wabun Community App for your mobile phone on the ' +
				'<a class="apple-app-link" href="https://testflight.apple.com/join/dGJFXTKB" >Apple Store</a> and on ' +
				'<a class="google-app-link" href="https://play.google.com/store/apps/details?id=org.wabun.com">Google Play</a>.<br/> ' +
				'Mobile users will appear below when they have added a valid email address and self identified thier community';

			return "<p class=\"\">" + title + text + "</p>";

		},



		createGuestDashboardNavigationController: function() {
			return new GuestNavigationMenu(GatherDashboard.getApplication());

		},


		createGuestAmendmentButton:function(application){


			var proposalObj = new GuestProposalAmendment(-1, {});
			return new ModalFormButtonModule(application, proposalObj, {
			    label: "Add Amendment",
			    formName: "ProposalTemplate",
			    "class": "primary-btn edit",
			    formOptions: {
						template: "form"
				},
			}).addEvent('complete', function() {

				(new UIModalDialog(application, proposalObj, {
					formName:'emailVerificationForm',
					formOptions:{
						template: "form"
					}
				})).show();

			});




		},


		createLoginFormButtons: function(application, wizard) {


			/* Register and Proposal Form */



			var registration = new Element('div', {
				"style": "margin-top: 20px; height: 50px;"
			})
			var registrationLabel = registration.appendChild(new Element('label', {
				html: 'Register as a new user',
				'class': 'login-button-text',
				style: "text-align:left; color: #6A7CE9; line-height: 55px;",
				events: {
					click: function() {
						//goto next step
						wizard.displayNext();
					}
				}
			}));
			//login.appendChild(new Element('br'));
			registrationLabel.appendChild(new Element('button', {
				html: 'Register',
				style: "background-color:mediumseagreen;",
				"class": "primary-btn"

			}));

		

			var buttons=[registration];

			DashboardConfig.getValue('enableProposals', function(enabled) {
				if (!enabled) {
					return;
				}

	
				var proposal = GuestProject.CreateGuestProjectButton();

				buttons.push(proposal);

			});



			return buttons;

		},



	});

	return new GatherDashboardClass();

})();

var ReferralManagementDashboard = GatherDashboard;