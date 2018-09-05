define(['jquery'], function($){
    var CustomWidget = function () {
    	var self = this;

	//this.server_url = 'http://127.0.0.1/amotest/handle.php';

	this.open_csv = function(data){
	    console.log(data);
	    var result = JSON.parse(data);
	    var file_path = result['file'];	
	    window.open(self.user_settings.url + file_path.substring(1));
	};
	this.callbacks = {
		render: function(){
			return true;
		},
		init: function(){
			return true;
		},
		bind_actions: function(){
			return true;
		},
		settings: function(){
			return true;
		},
		onSave: function(){
			return true;
		},
		destroy: function(){
			return true;
		},
		leads: {
		    selected: function(data){
			var leads = self.list_selected().selected;
			var lead_ids = [
			];
			leads.forEach(function(lead){
			    lead_ids.push(lead['id']);
			});
					
			self.user_settings = self.get_settings();
			var data = {
			    'method': 'make_csv', 
			    'lead_ids': lead_ids, 
			    'subdomain': self.user_settings.subdomain, 
			    'user_login': self.user_settings.login, 
			    'user_hash': self.user_settings.api_key
			}    
			$.post(self.user_settings.url + "handle.php", data, self.open_csv);
			return true;
		    }
		}
	};
	return this;
    };

return CustomWidget;
});
