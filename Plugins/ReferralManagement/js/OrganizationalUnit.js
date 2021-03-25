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
		save: function(callback) {

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
		return new OrganizationalUnitList({
			label:"Department"
		});
		
	}

	return OrganizationalUnit;

})();

var ProjectDepartment = new Class({
	Extends: OrganizationalUnit,
	getFormBtn:function(){

		return new ModalFormButtonModule(ReferralManagementDashboard.getApplication(), ReferralManagementDashboard.getNewDepartment(), {
     
            label: "Add Department",
            formOptions: {template:"form"},
            formName: "departmentForm",
            "class": "primary-btn"

    
		});
	}
});


var OrganizationalUnitList=(function(){

	var OrganizationalUnitList=new Class({
		Extends:MockDataTypeItem
	});

	return OrganizationalUnitList;
});



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
			}, itemData));
		});

	}).execute();


	return {

		getNewDepartment: function() {


			var newTag = new ProjectDepartment({
				name: "",
				description: "",
				type: "Project.department",
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