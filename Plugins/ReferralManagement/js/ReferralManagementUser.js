var ReferralManagementUser = (function() {



	var MainRoles;


	(new AjaxControlQuery(CoreAjaxUrlRoot, 'get_user_roles', {
		'plugin': "ReferralManagement"
	}))
		.addEvent('success', function(result) {
			console.log(result);
			MainRoles=result.roles;
		})
		.execute();


	var ReferralManagementUser = new Class({
		Extends: CoreUser,
		getEmail: function() {
			var me = this;
			return me.options.metadata.email;
		},
		getUserId: function() {
			var me = this;
			return me.options.metadata.id;
		},
		getName: function() {
			var me = this;
			if (!me.options.metadata.name) {
				throw 'user does not have name metadata'
			}
			return me.options.metadata.name;
		},
		setOnline: function(online) {
			var me = this;
			var changed = online !== me._online;
			me._online = !!online;
			if (changed) {
				me.fireEvent('onlineStatusChanged', [online]);
			}
			return me;
		},
		isOnline: function() {
			var me = this;
			return !!me._online;
		},
		showsOnline: function() {
			var me = this;
			return !(typeof me._online == "undefined");
		},
		getRoles: function() {
			var me = this;
			return me.options.metadata.roles;
		},
		getRolesUserCanAssign: function() {
			var me = this;
			return me.options.metadata['can-assignroles'];
		},
		isTeamMember: function() {
			var me = this;
			var roles = me.getRoles();

			var allRoles=MainRoles.slice(0);
			allRoles.pop();


			if (roles.length) {



				return (allRoles).indexOf(roles[0]) >= 0;
			}

			return false;
		},
		isCommunityMember: function() {
			var me = this;
			var roles = me.getRoles();

			if (roles.length) {
				return ([
					"community-member"
				]).indexOf(roles[0]) >= 0;
			}

			return false;
		},
		getCommunity: function() {
			var me = this;
			if (!me.options.metadata.name) {
				throw 'user does not have name metadata'
			}
			return me.options.metadata.community;
		},
		isUnassigned: function() {
			var me = this;
			var roles = me.getRoles();

			if (roles.length) {
				return (MainRoles).indexOf(roles[0]) == -1;
			}

			return true;
		},
		getRole: function() {
			var me = this;
			if (me.isUnassigned()) {
				return 'none';
			}

			return me.getRoles()[0];
		},
		isTeamManager: function() {
			var me = this;
			var roles = me.getRoles();


			var allRoles=MainRoles.slice(0);
			allRoles.pop();
			allRoles.pop();

			if (roles.length) {
				return (allRoles).indexOf(roles[0]) >= 0;
			}

			return false;
		},

		getIcon: function() {
			var me = this;
			return me.options.metadata['role-icon'];
		},
		setData: function(data) {
			var me = this;
			var json = JSON.stringify(me.options.metadata);
			me.options.metadata = data;
			if (json !== JSON.stringify(data)) {
				me.fireEvent('update');
			}
			return me;
		},
		save: function(callback) {
			var me = this;
			AppClient.authorize('write', {
				id: me.getId(),
				type: me.getType()
			}, function(access) {
				//check access, bool.
				if (access) {
					callback(true);
					me.fireEvent('update');
				} else {
					callback(false);
				}
			});
			return me;
		},
		getProfileIcon: function() {
			var me = this;
			if (me.options.metadata.avatar) {
				return me.options.metadata.avatar;
			}
			return null;

		},
		setAttributes: function(attributes) {
			var me = this;
			var urls = Proposal.ParseHtmlUrls(attributes.userAttributes.profileIcon)
			if (urls.length) {
				var avatar = urls[0];
				me.options.metadata.avatar = avatar;
			}


			me.options.metadata.name = attributes.userAttributes.firstName;



		},
		isDevice: function() {
			return false;
		},
		isProjectMember: function() {
			return false;
		},
		isAdmin: function() {
			return false;
		},
		setRole: function(role, callback) {

			var me = this;

			var SetUserRoleQuery = new Class({
				Extends: AjaxControlQuery,
				initialize: function(user, role) {

					this.parent(CoreAjaxUrlRoot, "set_user_role", {
						plugin: "ReferralManagement",
						user: user,
						role: role
					});
				}
			});


			(new SetUserRoleQuery(me.getId(), role)).addEvent('success', function() {
				if (callback) {
					callback();
				}

			}).execute();

			me.options.metadata.roles = [role];
			if (role == "none") {
				me.options.metadata.roles = [];
			}
			me.fireEvent('update');


		}

	});


	ReferralManagementUser.GetMainRoles=function(){
		return MainRoles.slice(0);
	};


	return ReferralManagementUser;

})();



	var TeamMember = new Class({
		Extends: ReferralManagementUser,
		isProjectMember: function() {
			return true;
		},
		getProject: function() {
			var me = this;
			if (!me._p) {
				throw 'No project set for team member';
			}
			return me._p;
		},
		setProject: function(p) {
			var me = this;
			me._p = p;
		},

		getUser: function() {
			var me = this;
			if (!me._u) {
				throw 'No user set for team member';
			}
			return me._u;
		},
		setUser: function(u) {
			var me = this;
			me._u = u;
			me.options.metadata = Object.append(me.options.metadata, u.options.metadata);
		},
		setMissingUser: function() {
			var me = this;
			me.options.metadata = Object.append({
				name: "missing or deleted user",
				community: "unknown",
				email: ''
			}, me.options.metadata);
		},
		save: function(callback) {
			var me = this;


			var SaveProjectTeamMemberPermissions = new Class({
				Extends: AjaxControlQuery,
				initialize: function(data) {
					this.parent(CoreAjaxUrlRoot, 'save_team_member_permissions', Object.append({
						plugin: 'ReferralManagement'
					}, data));
				}
			});


			(new SaveProjectTeamMemberPermissions({
				id: me.getId(),
				project: me.getProject().getId(),
				permissions: me.options.metadata.permissions
			})).addEvent('success', function(resp) {

				if (resp.success) {

					callback(true);
					me.fireEvent('update');


				} else {
					callback(false);
				}


			}).execute();

		},

		setReceiveNotifications: function(bool) {
			var me = this;
			me._setPermission("recieves-notifications", bool);
		},
		_getPermission: function(n) {
			var me = this;
			return me.options.metadata.permissions.indexOf(n) >= 0;
		},
		_setPermission: function(n, bool) {
			var me = this;
			if (bool && me.options.metadata.permissions.indexOf(n) < 0) {
				me.options.metadata.permissions.push(n);
			}

			if ((!bool)) {
				var i = me.options.metadata.permissions.indexOf(n);
				if (i >= 0) {
					me.options.metadata.permissions.splice(i, 1);
				}
			}
		},

		setCanAddTeamMembers: function(bool) {
			var me = this;
			me._setPermission("adds-members", bool);
		},
		setCanAddTasks: function(bool) {
			var me = this;
			me._setPermission("adds-tasks", bool);
		},
		setCanAssignTasks: function(bool) {
			var me = this;
			me._setPermission("assigns-tasks", bool);
		},
		setCanSetTeamMembersRoles: function(bool) {
			var me = this;
			me._setPermission("sets-roles", bool);
		},

		receiveNotifications: function() {
			var me = this;
			return me._getPermission("recieves-notifications");
		},
		canAddTeamMembers: function() {
			var me = this;
			return me.isTeamManager() || me._getPermission("adds-members");
		},
		canAddTasks: function() {
			var me = this;
			return me.isTeamManager() || me._getPermission("adds-tasks");
		},
		canAssignTasks: function() {
			var me = this;
			return me.isTeamManager() || me._getPermission("assigns-tasks");
		},
		canSetTeamMembersRoles: function() {
			var me = this;
			return me.isTeamManager() || me._getPermission("sets-roles");
		},



	});


	var Device = new Class({
		Extends: ReferralManagementUser,
		isDevice: function() {
			return true;
		},
		isActivated: function() {
			var me = this;

			if ((!me.options.metadata.email) || me.options.metadata.email.indexOf('device.') === 0) {
				return false;
			}

			return true;
		}
	})



	var ProjectClient = new Class({
		Extends: DataTypeObject,
		Implements: [Events],
		initialize: function(id, data) {
			var me = this;

			me.type = 'ReferralManagement.client';
			me._id = id;

			me.data = data;
		},
		getName: function() {
			var me = this;
			return me.data.name;
		},
		getDescription: function() {
			var me = this;
			return 'Some description';
		}
	});