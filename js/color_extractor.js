jQuery(document).ready(function($){
    $('.swatch').on( 'click', function(){
        var val = $(this).text().replace('#', '');
        $('.compat-field-primary_color input').val( val );
    });
});
