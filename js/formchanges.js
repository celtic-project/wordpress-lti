(function ($) {
  $(document).ready(function () {
    window.lti_tool_changed = false;
    window.onbeforeunload = function (e) {
      if (window.lti_tool_changed) {
        return '';
      } else {
        return;
      }
    };
    $(document).on('change', 'input[type="text"], input[type="checkbox"], input[type="radio"]', function () {
      window.lti_tool_changed = true;
    });
    $(document).on('submit', 'form', function () {
      window.lti_tool_changed = false;
    });

  });
})(jQuery);
