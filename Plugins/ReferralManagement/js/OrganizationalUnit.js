var OrganizationalUnit = (function() {


	var SaveDepartmentQuery = new Class({
		Extends: AjaxControlQuery,
		initialize: function(options) {
			this.parent(CoreAjaxUrlRoot, 'save_department', Object.append({
				plugin: 'ReferralManagement'
			}, options));
		}
	});

	var OrganizationalUnit = new Class({
		Extends: MockDataTypeItem,
		initialize: function(options) {
			this.parent(options);

			this.setName = function(n) {
				options.name = n
			}
			this.setDescription = function(d) {
				options.description = d
			}

		},

		isEditable:function(){
			if(this._getEditable){
				return this._getEditable();
			}
			return true;
		},

		getForm:function(){

			return "departmentForm";
		},

		save: function(callback) {

			if(!this.isEditable()){
				throw 'Item should not be edited';
			}

			var i = ProjectDepartmentList.getProjectDepartments().indexOf(this);
			if (i < 0) {
				ProjectDepartmentList.addDepartment(this);
			}

			var args={
				
				name:this.getName(),
				description:this.getDescription()

			};

			if(this.getId()>0){
				args.id=this.getId();
			}

			var me=this;

			(new SaveDepartmentQuery(args)).addEvent('success', function(response){

				if(response.success){					
					me._id=response.department.id;
					me.fireEvent('update');
					callback(true);
				}

			}).execute();

		}
	});

	OrganizationalUnit.DefaultList=function(){


		var label=DashboardConfig.getValue('departmentKind')+'s';

		var list= new OrganizationalUnitList({
			label:label,
			editable:function(){
				return !DashboardConfig.getValue('useCommunitiesAsDepartments');
			},
			items:function(callback){
				var me=this;
				DashboardConfig.getValue('useCommunitiesAsDepartments', function(useCommunities){
					if(useCommunities){

						 callback(Community.territories.map(function(name) {
							var name=String.capitalize.call(null, name.split('|').pop());
							return new OrganizationalUnit({
								name:name,
								description:"",
								kind:me.getKind(),
								editable:false
							});
						}));

						//ashboardConfig.getValue('')
						return;
					}

					callback(ProjectDepartmentList.getProjectDepartments())

				});
			}
		});

		return list;
		
	}

	return OrganizationalUnit;

})();

var ProjectDepartment = new Class({
	Extends: OrganizationalUnit
});


var OrganizationalUnitList=(function(){

	var OrganizationalUnitList=new Class({
		Extends:MockDataTypeItem,

		getFormBtn:function(){

			if(!this.isEditable()){
				return null;
			}

			return new ModalFormButtonModule(ReferralManagementDashboard.getApplication(), ProjectDepartmentList.getNewDepartment(), {
	     
	            label: "Add "+this.getKind(),
	            formOptions: {template:"form"},
	            formName: this.getForm(),
	            "class": "primary-btn"

	    
			});
		},
		getForm:function(){
			return "departmentForm";
		},
		isEditable:function(){
			if(this._getEditable){
				var result=this._getEditable();
				if(typeof result=='function'){
					return result.bind(this)();
				}
				
				return result;
			}
			return true;
		},
		getKind:function(){
			var l=this.getLabel();
			return l.substring(0, l.length-1);
		},
		getItems:function(callback){

			if(this._getItems){
				var result=this._getItems();
				if(typeof result=='function'){
					result.bind(this)(callback);
					return;
				}
				callback(result);
				return;
			}

			callback([]);
		}
	});

	return OrganizationalUnitList;
})();



var ProjectDepartmentList = (function() {


	var _departments=false;

	var DepartmentListQuery = new Class({
		Extends: AjaxControlQuery,
		initialize: function(options) {
			this.parent(CoreAjaxUrlRoot, 'list_departments', Object.append({
				plugin: 'ReferralManagement'
			}, options));
		}
	});



	(new DepartmentListQuery()).addEvent('success', function(response) {

		_departments=response.departments.map(function(itemData){
			return new ProjectDepartment(Object.append({
				"type": "Project.department",
				"kind":"Department"
			}, itemData));
		});

	}).execute();


	return {

		getNewDepartment: function() {


			var newTag = new ProjectDepartment({
				name: "",
				description: "",
				type: "Project.department",
				kind:"Department",
				id: -1
			});

			return newTag;

		},

		addDepartment:function(department){
			_departments.push(department);
		},

		getProjectDepartments: function() {

			if(_departments===false){
				throw "departments not loaded yet";
			}


			return _departments.slice(0);

		}


	};



})();