
/**
 * onepiece
 */
var op = new Object;

/**
 * Loading onepiece-framework for javascript.
 */
(function(){
	var list = new Array();
	
	if( typeof jQuery == 'undefined' ){
		console.log('Does not define jQuery.');
		list[list.length] = '//ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js';
	}

	var path = '//192.168.33.10:8001/ja/js/';
	list[list.length] = path + 'op.core.js';
	list[list.length] = path + 'op.i18n.js';
	var firstScript = document.getElementsByTagName('script')[0];
	for(var i=0; i<list.length; i++){
		var url = list[i];
		console.log(url);
		var script  = document.createElement('script');
		script.type = 'text/javascript';
		script.src  = url;
		firstScript.parentNode.insertBefore(script, firstScript);
	}
})();

/**
 * Correspond to IE.
 */
if( typeof console != 'object' ){
	console = new Object;
	console.log = function(data){
		/* <?php if( $this->Admin() ): ?> */
		alert(data);
		/* <?php endif; ?> */
	};
	console.dir = function(data){
		/* <?php if( $this->Admin() ): ?> */
		alert(data);
		/* <?php endif; ?> */
	};
}
