'use strict';

var LegendHelper=(function(){

	var legends=[];


	var LegendHelper=new Class({



		addLegend:function(legend, map){
			legends.push(legend);
			legend.once('remove',function(){

				//this only happens on map remove

				legends=[];
				return;




				// var i=legends.indexOf(legend);
				// if(i>=0){
				// 	legends.splice(i,1);
				// }
			});


			legend.addEvent("maximize",function(){

				legends.forEach(function(l){

					if(l!==legend){
						l.minimize();
					}

				});


			})


		},
		getLegends:function(){
			return legends.slice(0);
		}





	});





	return new LegendHelper();


})();