(function($){
  $(document).ready(function(){

    // Öffnen
    $('.cnp-open-modal').on('click', function(e){
      e.preventDefault();
      var target = $(this).data('target');
      if (target) {
        // display:flex; => hide() => fadeIn(200)
        $(target).css('display','flex').hide().fadeIn(200);
      }
    });

    // Schließen
    $('.cnp-close-modal').on('click', function(e){
      e.preventDefault();
      var closeTarget = $(this).data('close');
      if (closeTarget) {
        $(closeTarget).fadeOut(200);
      }
    });

    // Klick aufs Overlay => schließen
    $('.cnp-modal-overlay').on('click', function(e){
      if ($(e.target).hasClass('cnp-modal-overlay')) {
        $(this).fadeOut(200);
      }
    });

  });
})(jQuery);
