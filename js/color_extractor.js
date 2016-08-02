var original_col;
jQuery(document).ready(function($){
	$("img.thumbnail").load(function(){
	    var vibrant = new Vibrant( $(this)[0], 64, 5 );
	    var swatches = vibrant.swatches();
	    original_col = $('.compat-field-primary_color input').val();
	    $("img.thumbnail").parent().append('<div class="swatch-holder"><div class="swatch-original swatch" style="background-color: '+ original_col+'">CURRENT</div></div>');
	    for (var swatch in swatches){
	        if (swatches.hasOwnProperty(swatch) && swatches[swatch]){
	    	    $('.swatch-holder').append('<div class="swatch" style="background-color: '+swatches[swatch].getHex()+'"></div>');
	            console.log(swatch, swatches[swatch].getHex())
	        }
	    }

	    $('.swatch').each(function(){
		    $(this).click(function(){
			    var val = $(this).css('background-color');
			    val = (val == 'rgba(0, 0, 0, 0)') ? '' : rgb2hex(val);
			    $('.compat-field-primary_color input').val(val);
		    });
	    });

	}).each(function() {
		if(this.complete) $(this).load();
	});
	var input = $('.compat-attachment-fields').detach()
	input.insertAfter(".swatch-holder");

});

function rgb2hex(rgb) {
     if (  rgb.search("rgb") == -1 ) {
          return rgb;
     } else {
          rgb = rgb.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+))?\)$/);
          function hex(x) {
               return ("0" + parseInt(x).toString(16)).slice(-2);
          }
          return "#" + hex(rgb[1]) + hex(rgb[2]) + hex(rgb[3]);
     }
}