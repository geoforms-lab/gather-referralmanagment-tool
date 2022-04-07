var TableHeader = (function() {

	var TableHeader = new Class_({


		render: function(listModule) {

			var me = this;
			this._listModule = listModule;

			listModule.once('remove', function() {
				me._remove();
			});


			listModule.getSortObject(function(sort){
				sort.hide();
			});

			listModule.getFilterObject(function(filter){
				filter.hide();
			});


			listModule.runOnceOnLoad(function() {

				var module = listModule.getDetailViewAt(0);
				if (!module) {
					listModule.once('loadItem', function(module) {
						me._createHeaderFromContent(module, function() {
							me._addHeaderBehavior();
						});

					});
					return;
				}

				me._createHeaderFromContent(module, function() {
					me._addHeaderBehavior();
				});



			});


		},

		_createHeaderFromContent(module, then) {
			var me = this;
			module.once('load', function() {
				console.log('loaded: ');
			})
			module.once('display', function() {
				console.log('loaded: ' + module.getElement().innerHTML);
				me._headerString = module.getElement().innerHTML;
				then();
			})

		},

		_addHeaderBehavior: function() {

			var me = this;
			this._listModule.on('load', function() {

				me._renderHeader();

			});

			this._renderHeader();


		},

		_renderHeader: function() {

			var listEl = this._listModule.getElement();

			var header = this._makeHeaderEl();

			if (listEl.firstChild) {
				listEl.insertBefore(header, listEl.firstChild);
			} else {
				listEl.appendChild(header);
			}


		},

		_makeHeaderEl: function() {

			var me=this;

			var header = new Element('div', {
				"class": "table-header",
				html: this._headerString
			});


			header.firstChild.firstChild.childNodes.forEach(function(colEl) {

				colEl.addClass('sortable');

				var sort = colEl.getAttribute('data-col');
				if (!ProjectList.HasSortFn(sort)) {
					colEl.addClass('disabled');
					return;
				}

				colEl.addEvent('click', function() {

					var sort = colEl.getAttribute('data-col');
					var sortModule = me._listModule.getSortObject();

					if (!sortModule) {


						/**
							* Not going to render this temporary module, but it should still work
							*/


						sortModule = (new ListSortModule(function() {
							return listModule;
						}, {
							sorters: ProjectList.projectSorters()
						}));

						me._listModule.setSortObject(sortModule);



						/**
							*
							*/

					}

					sortModule.applySort(sort);



				});
			});


			return header;
		},

		_remove: function() {

		}

	});



	return TableHeader;



})()