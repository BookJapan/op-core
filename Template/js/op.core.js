
(function(){
	console.log('op.core.js was loaded.');
	op.core = new Object;
	op.d = function(data){
		console.log(data);
	};
	$(function(){
		op.d('op was ready');
	});
})();
