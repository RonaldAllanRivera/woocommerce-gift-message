(function($){
	'use strict';
	$(function(){
		var $input = $('#wcgm_gift_message');
		if(!$input.length){ return; }
		var max = (window.WCGM && WCGM.maxLen) ? parseInt(WCGM.maxLen, 10) : parseInt($input.attr('maxlength')||150,10);
		var $counter = $('#wcgm-counter');
		var update = function(){
			var len = $input.val().length;
			if($counter.length){ $counter.text(len); }
			if(len > max){ $input.addClass('wcgm-too-long'); } else { $input.removeClass('wcgm-too-long'); }
		};
		$input.on('input keyup change', update);
		update();
	});
})(jQuery);
