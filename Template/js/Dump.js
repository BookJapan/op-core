
if( window.attachEvent ){
	window.attachEvent('onload', dump);
}else{
	window.addEventListener('load', dump, false);
}

function dump(){
	//	Web Strage	
	var length = sessionStorage.length;
	for( var i=0; i<length; i++ ){
		var dump_id = sessionStorage.key(i);
		var io = sessionStorage.getItem(dump_id);
		if( io == 0 ){
			var tmp = document.getElementById(dump_id);
			if(tmp){
				tmp.style.display = 'none';
			}
		}
	}
	
	//	addEventListener
	var tags = document.getElementsByClassName('dkey');
	for( var i=0; i<tags.length; i++){
		tags[i].addEventListener('click',d2,true);
	}
};

function d2(e){
	var io;
	var dump_id = this.getAttribute('did');
	var div = document.getElementById(dump_id);
	if( div.style.display == 'none' ){
		io = 1;
		div.style.display = 'block';
	}else{
		io = 0;
		div.style.display = 'none';
	}
	sessionStorage.setItem( dump_id, io );

	e.stopPropagation();
};
