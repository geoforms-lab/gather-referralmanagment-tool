var AdminMonitor=(function(){



	var options={
		autoCloseTimeout:5000,
		className:"debug",
		from:"bottom-right",
		to:"top"
	}


	var AdminMonitor=new Class({

		initialize:function(channels){


			if(AppClient.getUserType()!=='admin'){
				return;
			}

			channels.forEach(function(channel){
				AjaxControlQuery.Subscribe(channel, function(event){


					var el = $$('body')[0];
					if (el.hasClass('hide-admin')) {
						return;
					}


					console.log(channel);
					console.log(event);

					var opt=ObjectAppend_({}, options);

					if(event.status){
						opt.className+=' status-'+event.status;
					}

					if(event&&event.status&&event.status==="write"){
						NotificationBubble.Make("", channel.channel+" : "+event.status+" "+(event.interval||"~")+"s", opt);
					}

					if(event&&event.status&&event.status==="start"){
						NotificationBubble.Make("", channel.channel+" : "+event.status, opt);
					}
					if(event&&event.status&&event.status==="check"){
						NotificationBubble.Make("", channel.channel+" : "+event.status, opt);
					}
					if(event&&event.status&&event.status==="skip"){
						NotificationBubble.Make("", channel.channel+" : "+event.status, opt);
					}
				})
			})

		}

	});

	if(AppClient.getUserType()==='admin'){
				
		(new AjaxControlQuery(CoreAjaxUrlRoot, 'get_admin_channels', {
			'plugin': "ReferralManagement"
		}))
		.addEvent('success', function(result) {
				new AdminMonitor(result.channels);
		})
		.execute();

	}



	return AdminMonitor;

})()