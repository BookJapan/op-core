/*!
 * Dynamic translation by cloud.
 * 
 * Copyright 2015-04-11 Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 */
jQuery(function($){
	//	Loaded message.
	console.log('jQuery was loaded.');
	
	//	Get GeoIP Information.
	_get_geo_ip();
	
	//	Do translation by cloud.
	_i18n();
});

function _get_geo_ip(){
	//	API's URL
	var url = '//api.uqunie.com/geo/language/?ip=own&jsonp=1&callback=geoip';
	console.log(url);
	
	$.ajax({
		type: 'GET',
		url: url,
		cache: false,
		data: {
		},
		async: true,
		contentType: "application/json",
		dataType: 'jsonp',
		jsonpCallback: 'geoip',
		success: function(json){
			//	error process
			if( json['error'] ){
				alert(json['error']);
				return;
			}
			
			//	Get language by GeoIP.
			language = json['language'];
			
			//	Save to local web strage.
			sessionStorage.setItem('__language__',language);
		},
		error: function(e){
			console.log(e);
			alert('unknown error');
		}
	});
}

function _i18n(){
	var language = sessionStorage.getItem('__language__');
	if(!language){
		setTimeout('_i18n()',1000);
		return;
	}
	
	//	API's URL
	var url = '//api.uqunie.com/i18n/?jsonp=1&callback=i18n';
	console.log(url);
	
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
		while( result = html.match(/<([a-z0-9])[^>]*>([^<>]+)<\/\1>/i) ){
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
		contentType: "application/json",
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
			
			console.log('Translation was successful.');
		},
		error: function(e){
			console.dir(e);
			alert('unknown error');
		}
	});
}
