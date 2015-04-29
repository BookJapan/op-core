/*!
 * Dynamic translation by cloud.
 * 
 * Copyright 2015-04-11 Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 */

jQuery(function($){
	//	Loaded message.
	if(console){ console.log('jQuery was loaded.'); }
	
	//	op-unit-language if include.
	if( language = $('#language-selector-current').text() ){
		i18n.SetLanguage(language);
	}
	
	//	Do translation by cloud.
	i18n.Translation();
});

//	i18n object.
var i18n = new Object;

i18n.GetDomain = function(){
	var domain;
	/* <?php if( Toolbox::isLocalhost() ): ?> */
	domain = 'local.api.uqunie.com';
	/* <?php else: ?> */
	domain = 'api.uqunie.com';
	/* <?php endif; ?> */
	return domain;
};

//	Save to local web session storage.
i18n.SetLanguage = function(language){
	sessionStorage.setItem('__language__',language);
};

//	Get from local web session storage.
i18n.GetLanguage = function(){
	var language = sessionStorage.getItem('__language__');
	if(!language){
		language = i18n.GetLanguageFromCloudByGeoIP();
	}
	return language;
};

//	Get language from cloud by GeoIP.
i18n.GetLanguageFromCloudByGeoIP = function(){
	//	API's URL
	var domain = i18n.GetDomain();
	var url = '//' + domain + '/geo/language/?ip=own&jsonp=1&callback=geoip';
	if(console){ console.log(url); }
	
	$.ajax({
		type: 'GET',
		url: url,
		cache: true,
		data: {
		},
		async: true,
		contentType: "application/javascript",
		dataType: 'jsonp',
		jsonpCallback: 'geoip',
		success: function(json){
			//	error process
			if( json['error'] ){
				alert(json['error']);
				return;
			}
			
			//	Set language by GeoIP.
			i18n.SetLanguage(json['language']);
		},
		error: function(e){
			if(console){ console.log(e); }
			alert('unknown error');
		}
	});
};

i18n.Translation = function(language){
	if(!language){
		language = i18n.GetLanguage();
		if(!language){
			setTimeout('_i18n()',1000);
			return;
		}
	}
	
	//	API's URL
	var domain = i18n.GetDomain();
	var url = '//' + domain + '/i18n/?jsonp=1&callback=i18n';
	if(console){ console.log(url); }
	
	var i = 0;
	var n = 0;
	var texties = [];
	var weavers = [];
	$(".i18n").each(function(){
		//	Get translate html.
		var html = $(this).html();
		//	Replace join character.
		html = html.replace("\r",'');
		//	Swap html tag.
		while( result = html.match(/<([a-z0-9]+)[^>]*>([^<>]+)<\/\1>/i) ){
			if( result ){
				n++;
				var target = result[0];
				var replace = 'AAA'+n;
				weavers[n] = target;
				html = html.replace(target,replace);
			}
		}
		//	Bulk text.
		texties[i] = html;
		i++;
	});
	
	//	If not set i18n class.
	if( texties.length < 1){
		retrun;
	}
	
	$.ajax({
		type: 'GET',
		url: url,
		cache: false,
		data: {
			text: texties,
			from: 'en',
			to: language
		},
		async: true,
		contentType: "application/javascript",
		dataType: 'jsonp',
		jsonpCallback: 'i18n',
		success: function(json){
			//	error process
			if( json['error'] ){
				alert(json['error']);
				return;
			}
			
			//	recovery process
			var translates = json['translate'].join("\r");
			for( var n in weavers ){
				var target = 'AAA'+n;
				translates = translates.replace(target, weavers[n]);
			}
			texties = [];
			texties = translates.split("\r");
			
			//	overwrite process
			var i = 0;
			$(".i18n").each(function(){
				$(this).html(texties[i]);
				i++;
			});
		},
		error: function(e){
			if(console){ console.dir(e); }
			alert('unknown error');
		}
	});
};
