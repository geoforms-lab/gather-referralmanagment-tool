var SpatialDocumentPreview = (function() {

	/**
	 * displays spatial document or documents (array)
	 * @param mixed<string|array>   urls  	a url or array of urls to spatial documents
	 * @param {Function} callback called when finished previewing
	 */
	var SpatialDocumentPreview = new Class({





		show: function(layers) {

			var me = this;
			var map = me._map;

			var clear;
			


			var bounds = null;
			var extendBounds = function(b) {
				if (!bounds) {
					bounds = ObjectAppend_({}, b);
				} else {

					bounds.north = Math.max(bounds.north, b.north);
					bounds.south = Math.min(bounds.south, b.south);
					bounds.east = Math.max(bounds.east, b.east);
					bounds.west = Math.min(bounds.west, b.west);

				}


				if(bounds.east>-Infinity&&bounds.west<Infinity&&bounds.north>-Infinity&&bounds.south<Infinity){
					map.fitBounds(bounds);
				}



			}

			var offset = 40;

			var addMapLayerIndicators=function(layer, i){


				var layerOpts=layer.options;
				var name=layer.getName();
					

				var div=new Element('div');
				div.innerHTML="Loading: "+name;
				var spinner = new Spinner(div, {
                            width: 20,
                            height: 20,
                            color: 'rgba(255,255,255)',
                            start: true
                        });

				var bytes=function(b){

					var p='B';
					var i=0;
					while(b>1024){
						b=b/1024;
						i++;
					}

					return Math.floor(b)+(["B","KB","MB","GB"])[i];

				}


				var notification=NotificationBubble.Make('', div, {
					autoClose:false,
					from:'top-center',
					position:window.getSize().y/2,
					className:"layer-loading"

				});

				layer.on('progress',function(data){

					if(data.loading){
						div.innerHTML="Loading: "+name+' '+data.loading;

						if(data.loaded){
							div.innerHTML+=' '+data.loaded+'/'+data.total;
						}

						return;
					}

					if(typeof data.loaded=='number'){
						div.innerHTML="Loading: "+name+' '+bytes(data.loaded)
					}

					if(typeof data.loaded=='string'){
						div.innerHTML="Loading: "+name+' '+data.loaded;
					}

				})

				layer.addEvent('error',function(){

					notification.setDescription("Error loading layer: "+name);
					setTimeout(function(){
						notification.fadeout();
					}, 3000);

				}).addEvent('load:once', function() {
					(new AjaxControlQuery(CoreAjaxUrlRoot, 'file_metadata', {
						'file': layerOpts.url
					})).addEvent('success', function(response) {
						var b = layer.getBounds();
						extendBounds(b);


						notification.fadeout();

						var imageUrl='';
						try{
						//imageUrl='/php-core-app/core.php?iam=administrator&format=raw&controller=plugins&view=plugin&plugin=Maps&pluginView=kml.tile&kml='+
						//	response.metadata.path+'&size=250&pad=10'; //&type=street&prj=GOOGLE
						}catch(e){
							console.error(e);
						}

						new UIMapSubTileButton(me._mapTile, {
							containerClassName: 'spatial-file-tile zoom-bounds',
							buttonClassName: '',
							image: imageUrl,
							//(response.metadata.image || response.metadata.mimeIcon || response.metadata.mediaTypeIcon),
							toolTip:{
								description:"Zoom to bounds: "+name
							}

						}).addEvent('click', function() {
							map.fitBounds(b);
						}).getElement().setStyle('right', i * offset + 40)


					}).execute();
				})

				me._map.getLayerManager().addLayer(layer);

				return layer;

			}

			layers.forEach(function(layer, i) {
				return addMapLayerIndicators(layer, i);
			});

			if(AppClient.getUserType()!=="guest"){
				
				var addLayerTile=null;
				var positionAddLayerTile=function(){

					if(addLayerTile){
						addLayerTile.getElement().setStyle('right', layers.length * offset + 40);
					}
				}
			
				addLayerTile=new UIMapSubTileButton(me._mapTile, {
					containerClassName: 'spatial-file-tile add always-show',
					buttonClassName: '',
					//image: response.metadata.image||response.metadata.mimeIcon||response.metadata.mediaTypeIcon,
					toolTip:{
						description:"Overlay other projects"
					}

				}).addEvent('click', function() {

					

					var selection=new InlineProjectSelection({

	                        });

					(new UIModalDialog(
	                        ReferralManagementDashboard.getApplication(),
	                        selection, {
	                            "formName": "datasetSelectForm",
	                            "formOptions": {
	                                template: "form"
	                            }
	                        }
	                    )).show();


				});

				positionAddLayerTile();

			}

			return clear;
		},
		_enterProposalWithControl: function(control) {
			var me = this;
			var map = me._map;
			// map.setMode('proposal', {
			// 	disablesControls: true, //tell geolive to disable all controls
			// 	control: control, //tell geolive that this control is taking control, also don't disable this one
			// 	suppressEvents: true, //tell geolive to stop responding to normal events (marker click etc)
			// 	fadesContent: true //make content semi-transparent.
			// });

		},
		_enterProposalWithClear: function(layers, callback) {

			var me = this;
			var map = me._map;

			var clearTile = me._mapTile;
			clearTile.enable();
			var clearControl = me._mapTileControl;

			me._enterProposalWithControl(clearControl);


			var clear = function() {


				clearTile.disable();
				//map.clearMode('proposal');

				layers.forEach(function(layer) {
					layer.hide();
				});

				layers = [];

				clear = function() {};
				if (callback) {
					callback();
				}
			}


			clearTile.addEvent('click', function() {
				clear();
			});

			return function() {
				clear();
			};



		},
		_enterProposalWithTile: function(layers, callback) {
			var me = this;
			var map = me._map;

			var proposalTile = me._tile;
			var ProposalControl = me._control


			me._enterProposalWithControl(ProposalControl);



			var subTile = new UIMapSubTileButton(proposalTile, {
				containerClassName: 'proposal',
				toolTip: ['Resume Proposal', 'click to continue the current proposal'],
				image: 'components/com_geolive/assets/Map%20Item%20Icons/sm_clipboard.png?tint=rgb(255,%20255,%20255)'
			});



			var clear = function() {


				subTile.remove();
				//map.clearMode('proposal');

				layers.forEach(function(layer) {
					layer.hide();
				});

				layers = [];

				clear = function() {};
				callback();
			}


			subTile.addEvent('click', function() {
				clear();
			});

			return function() {
				clear();
			};
		},
		_remove:function(){

			this._mapTileControl=null;
			this._mapTile=null;
			this._tile=null;
			this._control=null

		},
		setMap: function(map) {

			var me = this;
			me._map = map;


			map.once('remove',function(){

				me._remove();
				
				if(me._map==map){
					me._map=null;
				}
			});


		},
		setParentTile: function(tile, control) {

			var me = this;
			me._tile = tile;
			me._control = control;

		},
		setClearTile: function(tile, control) {
			var me = this;
			me.setTile(tile, control);
		},
		setTile: function(tile, control) {

			var me = this;
			me._mapTile = tile;
			me._mapTileControl = control;
		}

	});






	return new SpatialDocumentPreview();


})();