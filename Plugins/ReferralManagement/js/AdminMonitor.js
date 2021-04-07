var AdminMonitor=(function(){


	var AdminMonitor=new Class({

		initialize:function(channels){

			channels.forEach(function(channel){
				AjaxControlQuery.Subscribe(channel, function(event){
					console.log(channel);
					console.log(event);

					if(event&&event.status&&event.status==="write"){
						NotificationBubble.Make("", channel.channel+" : "+event.status);
					}
				})
			})

		}






	});



	(new AjaxControlQuery(CoreAjaxUrlRoot, 'get_admin_channels', {
		'plugin': "ReferralManagement"
	}))
	.addEvent('success', function(result) {
			new AdminMonitor(result.channels);
	})
	.execute();


	return AdminMonitor;

})()